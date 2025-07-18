<?php
require_once __DIR__ . '/../config/database.php';

function obtenerCentroCosto($ilabor, $codigo_elemento)
{
    $database = new Database();
    $conn = $database->connect();

    $mapeoIlabor = [
        'PERIODICOS' => '11212317002',
        'PULICOMERCIALES' => '11212317003', 
        'REVISTAS' => '11212317001',
        'PLEGADIZAS' => '11212317004'
    ];

    if (!empty(trim($ilabor))) {
        $ilaborUpper = strtoupper(trim($ilabor));
        if (isset($mapeoIlabor[$ilaborUpper])) {
            return $mapeoIlabor[$ilaborUpper];
        }
        
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

    $mapeoElemento = [
        '72312' => '11212317005', 
        '54003' => '11212317006', 
        '62027' => '11212317007', 
        '62028' => '11212317007', 
        '62031' => '11212317007'  
    ];

    if (!empty($codigo_elemento) && isset($mapeoElemento[$codigo_elemento])) {
        return $mapeoElemento[$codigo_elemento];
    }

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

    return '11212317001';
}

function procesarInventarioIneditto($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    try {
        $conn->exec("DELETE FROM inventarios_temp");

        $datos = [];
        if (!file_exists($archivo_csv)) {
            throw new Exception("Archivo CSV no encontrado: $archivo_csv");
        }

        $handle = fopen($archivo_csv, "r");
        if ($handle === FALSE) {
            throw new Exception("No se pudo abrir el archivo CSV");
        }

        $headers = fgetcsv($handle, 1000, ",");
        if ($headers === FALSE) {
            throw new Exception("No se pudieron leer los headers del archivo CSV");
        }

        $headers = array_map(function($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);

        $lineNumber = 1;
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $lineNumber++;
            
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($row) === count($headers)) {
                $datos[] = array_combine($headers, $row);
            } else {
                error_log("Línea $lineNumber: número de columnas no coincide. Esperadas: " . count($headers) . ", encontradas: " . count($row));
            }
        }
        fclose($handle);

        if (empty($datos)) {
            throw new Exception("No se encontraron datos válidos en el archivo CSV");
        }


        $query = "INSERT INTO inventarios_temp 
                  (IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, ICCSUBCC, ILABOR,
                   QCANTLUN, QCANTMAR, QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, 
                   SOBSERVAC, centro_costo_asignado) 
                  VALUES (:iemp, :fsoport, :itdsop, :inumsop, :inventario, :irecurso, :iccsubcc, :ilabor,
                          :qcantlun, :qcantmar, :qcantmie, :qcantjue, :qcantvie, :qcantsab, :qcantdom,
                          :sobservac, :centro_costo)";

        $stmt = $conn->prepare($query);
        $procesados = 0;

        foreach ($datos as $index => $fila) {
            try {
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
        throw new Exception("Error procesando inventario: " . $e->getMessage());
    }
}

function importarCentrosCostos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    $importados = 0;
    $handle = fopen($archivo_csv, "r");
    
    if ($handle === FALSE) {
        throw new Exception("No se pudo abrir el archivo de centros de costos");
    }

    $headers = fgetcsv($handle, 1000, ",");
    if ($headers === FALSE) {
        throw new Exception("No se pudieron leer los headers del archivo");
    }

    $headers = array_map(function($header) {
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
    return $importados;
}

function importarElementos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    $importados = 0;
    $handle = fopen($archivo_csv, "r");
    
    if ($handle === FALSE) {
        throw new Exception("No se pudo abrir el archivo de elementos");
    }

    $headers = fgetcsv($handle, 1000, ",");
    if ($headers === FALSE) {
        throw new Exception("No se pudieron leer los headers del archivo");
    }

    $headers = array_map(function($header) {
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
    return $importados;
}

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