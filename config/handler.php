<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Función para convertir Excel a CSV usando método nativo PHP
 */
function convertirExcelACSVNativo($archivoExcel)
{
    $fileExtension = strtolower(pathinfo($archivoExcel, PATHINFO_EXTENSION));

    if ($fileExtension === 'csv') {
        return $archivoExcel; // Ya es CSV, no necesita conversión
    }

    if ($fileExtension === 'xlsx') {
        return convertirXLSXACSVNativo($archivoExcel);
    } elseif ($fileExtension === 'xls') {
        // Para XLS, recomendamos conversión manual
        throw new Exception("Archivos XLS no soportados directamente. Por favor, convierta a XLSX o CSV desde Excel.");
    }

    throw new Exception("Formato de archivo no soportado: $fileExtension");
}

/**
 * Convertir XLSX a CSV usando ZipArchive y SimpleXML
 */
function convertirXLSXACSVNativo($archivoXLSX)
{
    if (!class_exists('ZipArchive')) {
        throw new Exception("La extensión ZipArchive de PHP es requerida para procesar archivos XLSX");
    }

    try {
        $csvPath = pathinfo($archivoXLSX, PATHINFO_DIRNAME) . '/' .
            pathinfo($archivoXLSX, PATHINFO_FILENAME) . '_converted.csv';

        $zip = new ZipArchive();
        $result = $zip->open($archivoXLSX);

        if ($result !== TRUE) {
            throw new Exception("No se pudo abrir el archivo XLSX. Código de error: $result");
        }

        // Leer strings compartidas
        $sharedStrings = [];
        if (($sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($sharedStringsXML);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        // Texto enriquecido
                        $text = '';
                        foreach ($si->r as $r) {
                            if (isset($r->t)) {
                                $text .= (string) $r->t;
                            }
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }
        }

        // Leer la primera hoja de trabajo
        $worksheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($worksheetXML === false) {
            throw new Exception("No se pudo leer la hoja de trabajo del archivo XLSX");
        }

        $zip->close();

        // Parsear XML de la hoja de trabajo
        $xml = simplexml_load_string($worksheetXML);
        if ($xml === false) {
            throw new Exception("No se pudo parsear el contenido XML de la hoja de trabajo");
        }

        $csvFile = fopen($csvPath, 'w');
        if ($csvFile === false) {
            throw new Exception("No se pudo crear el archivo CSV temporal");
        }

        // Procesar filas
        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowData = [];
                $maxCol = 0;

                // Primero, determinar el número máximo de columnas
                foreach ($row->c as $cell) {
                    $cellRef = (string) $cell['r'];
                    $colNum = obtenerNumeroColumna($cellRef);
                    if ($colNum > $maxCol) {
                        $maxCol = $colNum;
                    }
                }

                // Inicializar array con celdas vacías
                for ($i = 0; $i <= $maxCol; $i++) {
                    $rowData[$i] = '';
                }

                // Llenar datos de celdas
                foreach ($row->c as $cell) {
                    $cellRef = (string) $cell['r'];
                    $colNum = obtenerNumeroColumna($cellRef);
                    $cellValue = '';

                    if (isset($cell['t']) && (string) $cell['t'] === 's') {
                        // Referencia a string compartida
                        $stringIndex = (int) $cell->v;
                        if (isset($sharedStrings[$stringIndex])) {
                            $cellValue = $sharedStrings[$stringIndex];
                        }
                    } elseif (isset($cell->v)) {
                        $cellValue = (string) $cell->v;
                    }

                    $rowData[$colNum] = $cellValue;
                }

                // Remover celdas vacías del final
                $rowData = array_values($rowData);
                while (count($rowData) > 0 && end($rowData) === '') {
                    array_pop($rowData);
                }

                // Escribir fila si no está completamente vacía
                if (!empty($rowData) && !empty(array_filter($rowData))) {
                    fputcsv($csvFile, $rowData);
                }
            }
        }

        fclose($csvFile);
        return $csvPath;

    } catch (Exception $e) {
        if (isset($csvFile) && $csvFile !== false) {
            fclose($csvFile);
        }
        if (isset($csvPath) && file_exists($csvPath)) {
            unlink($csvPath);
        }
        throw new Exception("Error convirtiendo XLSX a CSV: " . $e->getMessage());
    }
}

/**
 * Obtener número de columna desde referencia de celda (ej: A1 -> 0, B1 -> 1)
 */
function obtenerNumeroColumna($cellRef)
{
    $col = preg_replace('/[0-9]+/', '', $cellRef);
    $colNum = 0;
    $len = strlen($col);

    for ($i = 0; $i < $len; $i++) {
        $colNum = $colNum * 26 + (ord($col[$i]) - ord('A') + 1);
    }

    return $colNum - 1; // Convertir a índice base 0
}

/**
 * Obtiene el centro de costo según la lógica del negocio
 */
function obtenerCentroCosto($ilabor, $codigo_elemento)
{
    $database = new Database();
    $conn = $database->connect();

    // Mapeo directo por ILABOR (primera prioridad)
    $mapeoIlabor = [
        'PERIODICOS' => '11212317002',
        'PULICOMERCIALES' => '11212317003',
        'REVISTAS' => '11212317001',
        'PLEGADIZAS' => '11212317004'
    ];

    // Si ILABOR no está vacío, buscar en el mapeo directo
    if (!empty(trim($ilabor))) {
        $ilaborUpper = strtoupper(trim($ilabor));
        if (isset($mapeoIlabor[$ilaborUpper])) {
            return $mapeoIlabor[$ilaborUpper];
        }

        // Si no está en el mapeo directo, buscar en la base de datos
        try {
            $query = "SELECT codigo FROM centros_costos WHERE UPPER(nombre) LIKE UPPER(:ilabor)";
            $stmt = $conn->prepare($query);
            $searchTerm = '%' . $ilabor . '%';
            $stmt->bindParam(':ilabor', $searchTerm);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['codigo'];
            }
        } catch (Exception $e) {
            error_log("Error buscando centro de costo por ILABOR: " . $e->getMessage());
        }
    }

    // Mapeo por código de elemento (segunda prioridad)
    $mapeoElemento = [
        '72312' => '11212317005', // Material de Empaque
        '54003' => '11212317006', // Tintas
        '62027' => '11212317007', // Material Preprensa
        '62028' => '11212317007', // Material Preprensa
        '62031' => '11212317007'  // Material Preprensa
    ];

    if (!empty($codigo_elemento) && isset($mapeoElemento[$codigo_elemento])) {
        return $mapeoElemento[$codigo_elemento];
    }

    // Si hay código de elemento, buscar en la base de datos
    if (!empty($codigo_elemento)) {
        try {
            $query = "SELECT centro_costo_1 FROM elementos WHERE codigo = :codigo_elemento";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':codigo_elemento', $codigo_elemento);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['centro_costo_1'])) {
                return $result['centro_costo_1'];
            }
        } catch (Exception $e) {
            error_log("Error buscando centro de costo por elemento: " . $e->getMessage());
        }
    }

    // Centro de costo por defecto
    return '11212317001'; // REVISTAS
}

/**
 * Procesa el archivo CSV de inventario de Ineditto (ahora compatible con Excel)
 */
function procesarInventarioIneditto($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    try {
        // Limpiar tabla temporal
        $conn->exec("DELETE FROM inventarios_temp");

        // Verificar si es Excel y convertir si es necesario
        $fileExtension = strtolower(pathinfo($archivo_csv, PATHINFO_EXTENSION));
        $archivoAProcesar = $archivo_csv;

        if (in_array($fileExtension, ['xlsx', 'xls'])) {
            $archivoAProcesar = convertirExcelACSVNativo($archivo_csv);
        }

        // Leer archivo CSV
        $datos = [];
        if (!file_exists($archivoAProcesar)) {
            throw new Exception("Archivo no encontrado: $archivoAProcesar");
        }

        $handle = fopen($archivoAProcesar, "r");
        if ($handle === FALSE) {
            throw new Exception("No se pudo abrir el archivo");
        }

        // Leer headers
        $headers = fgetcsv($handle, 1000, ",");
        if ($headers === FALSE) {
            throw new Exception("No se pudieron leer los headers del archivo");
        }

        // Limpiar headers de espacios en blanco y BOM
        $headers = array_map(function ($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);

        $lineNumber = 1;
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $lineNumber++;

            if (empty(array_filter($row))) {
                continue; // Saltar líneas vacías
            }

            if (count($row) === count($headers)) {
                $datos[] = array_combine($headers, $row);
            } else {
                error_log("Línea $lineNumber: número de columnas no coincide. Esperadas: " . count($headers) . ", encontradas: " . count($row));
            }
        }
        fclose($handle);

        // Limpiar archivo temporal si se creó
        if ($archivoAProcesar !== $archivo_csv && file_exists($archivoAProcesar)) {
            unlink($archivoAProcesar);
        }

        if (empty($datos)) {
            throw new Exception("No se encontraron datos válidos en el archivo");
        }

        // Preparar consulta de inserción
        $query = "INSERT INTO inventarios_temp 
                  (IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, ICCSUBCC, ILABOR,
                   QCANTLUN, QCANTMAR, QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, 
                   SOBSERVAC, centro_costo_asignado) 
                  VALUES (:iemp, :fsoport, :itdsop, :inumsop, :inventario, :irecurso, :iccsubcc, :ilabor,
                          :qcantlun, :qcantmar, :qcantmie, :qcantjue, :qcantvie, :qcantsab, :qcantdom,
                          :sobservac, :centro_costo)";

        $stmt = $conn->prepare($query);
        $procesados = 0;

        // Procesar cada fila
        foreach ($datos as $index => $fila) {
            try {
                // Obtener centro de costo usando la lógica requerida
                $centro_costo = obtenerCentroCosto(
                    $fila['ILABOR'] ?? '',
                    $fila['IRECURSO'] ?? ''
                );

                $stmt->execute([
                    ':iemp' => $fila['IEMP'] ?? '1',
                    ':fsoport' => $fila['FSOPORT'] ?? '',
                    ':itdsop' => $fila['ITDSOP'] ?? '160',
                    ':inumsop' => $fila['INUMSOP'] ?? '',
                    ':inventario' => $fila['INVENTARIO'] ?? '1',
                    ':irecurso' => $fila['IRECURSO'] ?? '',
                    ':iccsubcc' => $centro_costo,
                    ':ilabor' => $fila['ILABOR'] ?? '',
                    ':qcantlun' => !empty($fila['QCANTLUN']) ? floatval($fila['QCANTLUN']) : 0,
                    ':qcantmar' => !empty($fila['QCANTMAR']) ? floatval($fila['QCANTMAR']) : null,
                    ':qcantmie' => !empty($fila['QCANTMIE']) ? floatval($fila['QCANTMIE']) : null,
                    ':qcantjue' => !empty($fila['QCANTJUE']) ? floatval($fila['QCANTJUE']) : null,
                    ':qcantvie' => !empty($fila['QCANTVIE']) ? floatval($fila['QCANTVIE']) : null,
                    ':qcantsab' => !empty($fila['QCANTSAB']) ? floatval($fila['QCANTSAB']) : null,
                    ':qcantdom' => !empty($fila['QCANTDOM']) ? floatval($fila['QCANTDOM']) : null,
                    ':sobservac' => $fila['SOBSERVAC'] ?? '',
                    ':centro_costo' => $centro_costo
                ]);

                $procesados++;

            } catch (Exception $e) {
                error_log("Error procesando fila " . ($index + 2) . ": " . $e->getMessage() . " - Datos: " . print_r($fila, true));
            }
        }

        return $procesados;

    } catch (Exception $e) {
        // Limpiar archivo temporal en caso de error
        if (isset($archivoAProcesar) && $archivoAProcesar !== $archivo_csv && file_exists($archivoAProcesar)) {
            unlink($archivoAProcesar);
        }
        throw new Exception("Error procesando inventario: " . $e->getMessage());
    }
}

/**
 * Importa centros de costos desde CSV o Excel
 */
function importarCentrosCostos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    try {
        // Verificar si es Excel y convertir si es necesario
        $fileExtension = strtolower(pathinfo($archivo_csv, PATHINFO_EXTENSION));
        $archivoAProcesar = $archivo_csv;

        if (in_array($fileExtension, ['xlsx', 'xls'])) {
            $archivoAProcesar = convertirExcelACSVNativo($archivo_csv);
        }

        $importados = 0;
        $handle = fopen($archivoAProcesar, "r");

        if ($handle === FALSE) {
            throw new Exception("No se pudo abrir el archivo de centros de costos");
        }

        $headers = fgetcsv($handle, 1000, ",");
        if ($headers === FALSE) {
            throw new Exception("No se pudieron leer los headers del archivo");
        }

        // Limpiar headers
        $headers = array_map(function ($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);

        $query = "INSERT INTO centros_costos (codigo, nombre) VALUES (:codigo, :nombre)
                  ON DUPLICATE KEY UPDATE nombre = :nombre2";
        $stmt = $conn->prepare($query);

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) === count($headers)) {
                $data = array_combine($headers, $row);

                $codigo = trim($data['Codigo'] ?? $data['codigo'] ?? '');
                $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');

                if (!empty($codigo) && !empty($nombre)) {
                    try {
                        $stmt->execute([
                            ':codigo' => $codigo,
                            ':nombre' => $nombre,
                            ':nombre2' => $nombre
                        ]);
                        $importados++;
                    } catch (Exception $e) {
                        error_log("Error importando centro de costo: " . $e->getMessage());
                    }
                }
            }
        }

        fclose($handle);

        // Limpiar archivo temporal si se creó
        if ($archivoAProcesar !== $archivo_csv && file_exists($archivoAProcesar)) {
            unlink($archivoAProcesar);
        }

        return $importados;

    } catch (Exception $e) {
        // Limpiar archivo temporal en caso de error
        if (isset($archivoAProcesar) && $archivoAProcesar !== $archivo_csv && file_exists($archivoAProcesar)) {
            unlink($archivoAProcesar);
        }
        throw $e;
    }
}

/**
 * Importa elementos desde CSV o Excel
 */
function importarElementos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    try {
        // Verificar si es Excel y convertir si es necesario
        $fileExtension = strtolower(pathinfo($archivo_csv, PATHINFO_EXTENSION));
        $archivoAProcesar = $archivo_csv;

        if (in_array($fileExtension, ['xlsx', 'xls'])) {
            $archivoAProcesar = convertirExcelACSVNativo($archivo_csv);
        }

        $importados = 0;
        $handle = fopen($archivoAProcesar, "r");

        if ($handle === FALSE) {
            throw new Exception("No se pudo abrir el archivo de elementos");
        }

        $headers = fgetcsv($handle, 1000, ",");
        if ($headers === FALSE) {
            throw new Exception("No se pudieron leer los headers del archivo");
        }

        // Limpiar headers
        $headers = array_map(function ($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);

        $query = "INSERT INTO elementos 
                  (codigo, referencia, descripcion, centro_costo_1, centro_costo_2, centro_costo_3, centro_costo_4, centro_costo_5) 
                  VALUES (:codigo, :referencia, :descripcion, :cc1, :cc2, :cc3, :cc4, :cc5)
                  ON DUPLICATE KEY UPDATE 
                  referencia = :referencia2,
                  descripcion = :descripcion2,
                  centro_costo_1 = :cc1_2,
                  centro_costo_2 = :cc2_2,
                  centro_costo_3 = :cc3_2,
                  centro_costo_4 = :cc4_2,
                  centro_costo_5 = :cc5_2";

        $stmt = $conn->prepare($query);

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) === count($headers)) {
                $data = array_combine($headers, $row);

                $codigo = trim($data['Cód. Artículo'] ?? $data['codigo'] ?? '');
                $referencia = trim($data['Referencia'] ?? $data['referencia'] ?? '');
                $descripcion = trim($data['Descripción'] ?? $data['descripcion'] ?? '');

                if (!empty($codigo)) {
                    try {
                        $cc1 = !empty($data['Centro Costos 1']) ? trim($data['Centro Costos 1']) : null;
                        $cc2 = !empty($data['Centro Costos 2']) ? trim($data['Centro Costos 2']) : null;
                        $cc3 = !empty($data['Centro Costos 3']) ? trim($data['Centro Costos 3']) : null;
                        $cc4 = !empty($data['Centro Costos 4']) ? trim($data['Centro Costos 4']) : null;
                        $cc5 = !empty($data['Centro Costos 5']) ? trim($data['Centro Costos 5']) : null;

                        $stmt->execute([
                            ':codigo' => $codigo,
                            ':referencia' => $referencia,
                            ':descripcion' => $descripcion,
                            ':cc1' => $cc1,
                            ':cc2' => $cc2,
                            ':cc3' => $cc3,
                            ':cc4' => $cc4,
                            ':cc5' => $cc5,
                            ':referencia2' => $referencia,
                            ':descripcion2' => $descripcion,
                            ':cc1_2' => $cc1,
                            ':cc2_2' => $cc2,
                            ':cc3_2' => $cc3,
                            ':cc4_2' => $cc4,
                            ':cc5_2' => $cc5
                        ]);
                        $importados++;
                    } catch (Exception $e) {
                        error_log("Error importando elemento: " . $e->getMessage());
                    }
                }
            }
        }

        fclose($handle);

        // Limpiar archivo temporal si se creó
        if ($archivoAProcesar !== $archivo_csv && file_exists($archivoAProcesar)) {
            unlink($archivoAProcesar);
        }

        return $importados;

    } catch (Exception $e) {
        // Limpiar archivo temporal en caso de error
        if (isset($archivoAProcesar) && $archivoAProcesar !== $archivo_csv && file_exists($archivoAProcesar)) {
            unlink($archivoAProcesar);
        }
        throw $e;
    }
}

/**
 * Obtiene estadísticas de la tabla temporal
 */
function obtenerEstadisticasTablaTemp()
{
    $database = new Database();
    $conn = $database->connect();

    try {
        $query = "SELECT 
                    COUNT(*) as total_registros,
                    COUNT(CASE WHEN ILABOR IS NULL OR ILABOR = '' THEN 1 END) as ilabor_vacios,
                    COUNT(DISTINCT centro_costo_asignado) as centros_costo_diferentes,
                    COALESCE(SUM(QCANTLUN), 0) as suma_cantidades
                  FROM inventarios_temp";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
        return [
            'total_registros' => 0,
            'ilabor_vacios' => 0,
            'centros_costo_diferentes' => 0,
            'suma_cantidades' => 0
        ];
    }
}
?>