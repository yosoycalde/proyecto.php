<?php
include(__DIR__ . '/../config/database.php');

/**
 * Obtiene el centro de costo apropiado según la lógica de negocio
 * Si ILABOR está vacío, busca el centro_costo_1 del elemento
 * Si ILABOR tiene valor, lo valida contra la tabla centros_costos
 */
function obtenerCentroCosto($ilabor, $codigo_elemento)
{
    $database = new Database();
    $conn = $database->connect();

    // Si ILABOR está vacío o es null, buscar centro de costo 1 del elemento
    if (empty($ilabor) || trim($ilabor) === '') {
        $query = "SELECT centro_costo_1 FROM elementos WHERE codigo = :codigo_elemento";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':codigo_elemento', $codigo_elemento);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $centro_costo = $result ? $result['centro_costo_1'] : '11212317001'; // Default REVISTAS

        // Si el centro_costo_1 está vacío, usar default
        return !empty($centro_costo) ? $centro_costo : '11212317001';
    }

    // Si ILABOR tiene valor, validar que exista en centros_costos
    $query = "SELECT codigo FROM centros_costos WHERE codigo = :ilabor AND activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':ilabor', $ilabor);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si ILABOR existe en centros_costos, usarlo; sino usar default
    return $result ? $ilabor : '11212317001';
}

/**
 * Procesa el archivo CSV de inventario de Ineditto
 * Formato esperado: codigo_elemento, referencia, cantidad, fecha_movimiento, ILABOR, observaciones
 */
function procesarInventario($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    // Limpiar tabla temporal
    $conn->exec("DELETE FROM inventarios_temp");

    // Verificar que el archivo existe y es legible
    if (!file_exists($archivo_csv) || !is_readable($archivo_csv)) {
        throw new Exception("El archivo CSV no existe o no es legible");
    }

    // Leer archivo CSV
    $datos = [];
    $linea_numero = 0;

    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        // Leer headers (primera fila)
        $headers = fgetcsv($handle, 1000, ",");
        $linea_numero++;

        if (!$headers) {
            throw new Exception("No se pudieron leer los headers del archivo CSV");
        }

        // Limpiar headers (remover espacios y caracteres especiales)
        $headers = array_map(function ($header) {
            return trim(str_replace(["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"], '', $header));
        }, $headers);

        // Validar headers mínimos requeridos
        $headers_requeridos = ['codigo_elemento', 'cantidad', 'fecha_movimiento'];
        foreach ($headers_requeridos as $header_req) {
            if (!in_array($header_req, $headers)) {
                throw new Exception("Header requerido '$header_req' no encontrado en el archivo CSV");
            }
        }

        // Leer datos línea por línea
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $linea_numero++;

            if (count($row) !== count($headers)) {
                error_log("Advertencia: Línea $linea_numero tiene " . count($row) . " columnas, esperadas " . count($headers));
                continue;
            }

            $fila_datos = array_combine($headers, $row);

            // Validar datos mínimos
            if (empty($fila_datos['codigo_elemento']) || empty($fila_datos['cantidad'])) {
                error_log("Advertencia: Línea $linea_numero - código o cantidad vacíos, saltando registro");
                continue;
            }

            $datos[] = $fila_datos;
        }
        fclose($handle);
    } else {
        throw new Exception("No se pudo abrir el archivo CSV");
    }

    if (empty($datos)) {
        throw new Exception("No se encontraron datos válidos en el archivo CSV");
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Insertar datos en tabla temporal
        $registros_insertados = 0;

        foreach ($datos as $fila) {
            // Obtener centro de costo según lógica de negocio
            $centro_costo = obtenerCentroCosto(
                isset($fila['ILABOR']) ? $fila['ILABOR'] : '',
                $fila['codigo_elemento']
            );

            // Generar número de soporte único
            $numero_soporte = 'INV' . date('Ymd') . str_pad($registros_insertados + 1, 6, '0', STR_PAD_LEFT);

            // Preparar fecha en formato dd/mm/yyyy para ContaPyme
            $fecha_movimiento = $fila['fecha_movimiento'];
            $fecha_formateada = date('d/m/Y', strtotime($fecha_movimiento));

            $query = "INSERT INTO inventarios_temp 
                      (codigo_elemento, nombre_elemento, referencia, cantidad, valor_unitario, valor_total,
                       fecha_movimiento, ILABOR, observaciones, centro_costo_asignado,
                       IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, ICCSUBCC, 
                       QCANTLUN, SOBSERVAC) 
                      VALUES (:codigo, :nombre, :referencia, :cantidad, :valor_unit, :valor_total,
                              :fecha, :ilabor, :obs, :centro_costo,
                              :iemp, :fsoport, :itdsop, :inumsop, :inventario, :irecurso, :iccsubcc,
                              :qcantlun, :sobservac)";

            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':codigo' => $fila['codigo_elemento'],
                ':nombre' => isset($fila['nombre_elemento']) ? $fila['nombre_elemento'] : '',
                ':referencia' => isset($fila['referencia']) ? $fila['referencia'] : '',
                ':cantidad' => floatval($fila['cantidad']),
                ':valor_unit' => isset($fila['valor_unitario']) ? floatval($fila['valor_unitario']) : 0,
                ':valor_total' => isset($fila['valor_total']) ? floatval($fila['valor_total']) : 0,
                ':fecha' => $fecha_movimiento,
                ':ilabor' => isset($fila['ILABOR']) ? $fila['ILABOR'] : '',
                ':obs' => isset($fila['observaciones']) ? $fila['observaciones'] : '',
                ':centro_costo' => $centro_costo,
                // Campos ContaPyme
                ':iemp' => '001',
                ':fsoport' => $fecha_formateada,
                ':itdsop' => 'INV',
                ':inumsop' => $numero_soporte,
                ':inventario' => 'S',
                ':irecurso' => $fila['codigo_elemento'],
                ':iccsubcc' => $centro_costo,
                ':qcantlun' => floatval($fila['cantidad']),
                ':sobservac' => isset($fila['observaciones']) ? $fila['observaciones'] : ''
            ]);

            $registros_insertados++;
        }

        // Confirmar transacción
        $conn->commit();
        return $registros_insertados;

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        throw new Exception("Error al insertar datos: " . $e->getMessage());
    }
}

/**
 * Genera el archivo CSV en formato ContaPyme
 */
function generarCSVContaPyme()
{
    $database = new Database();
    $conn = $database->connect();

    $query = "SELECT IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, ICCSUBCC, 
                     '' as ILABOR_EXPORT, QCANTLUN, '' as QCANTMAR, '' as QCANTMIE, 
                     '' as QCANTJUE, '' as QCANTVIE, '' as QCANTSAB, '' as QCANTDOM, 
                     SOBSERVAC 
              FROM inventarios_temp 
              ORDER BY fecha_movimiento, codigo_elemento";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        throw new Exception("No hay datos para exportar. Debe procesar un archivo primero.");
    }

    // Crear directorio exports si no existe
    $exports_dir = __DIR__ . '/../exports/';
    if (!file_exists($exports_dir)) {
        mkdir($exports_dir, 0777, true);
    }

    $filename = $exports_dir . 'contapyme_' . date('Y-m-d_H-i-s') . '.csv';

    $file = fopen($filename, 'w');
    if (!$file) {
        throw new Exception("No se pudo crear el archivo de exportación");
    }

    // Headers del CSV según formato ContaPyme (sin BOM para evitar problemas)
    fputcsv($file, [
        'IEMP',
        'FSOPORT',
        'ITDSOP',
        'INUMSOP',
        'INVENTARIO',
        'IRECURSO',
        'ICCSUBCC',
        'ILABOR',
        'QCANTLUN',
        'QCANTMAR',
        'QCANTMIE',
        'QCANTJUE',
        'QCANTVIE',
        'QCANTSAB',
        'QCANTDOM',
        'SOBSERVAC'
    ]);

    // Escribir datos
    foreach ($resultados as $row) {
        fputcsv($file, [
            $row['IEMP'],
            $row['FSOPORT'],
            $row['ITDSOP'],
            $row['INUMSOP'],
            $row['INVENTARIO'],
            $row['IRECURSO'],
            $row['ICCSUBCC'],
            '', // ILABOR siempre vacío en la exportación
            $row['QCANTLUN'],
            '', // QCANTMAR vacío
            '', // QCANTMIE vacío  
            '', // QCANTJUE vacío
            '', // QCANTVIE vacío
            '', // QCANTSAB vacío
            '', // QCANTDOM vacío
            $row['SOBSERVAC']
        ]);
    }

    fclose($file);

    if (!file_exists($filename)) {
        throw new Exception("Error al crear el archivo de exportación");
    }

    return $filename;
}

/**
 * Importa centros de costos desde CSV
 * Formato esperado: Codigo, Nombre
 */
function importarCentrosCostos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    if (!file_exists($archivo_csv)) {
        throw new Exception("Archivo de centros de costos no encontrado");
    }

    $registros_importados = 0;

    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");

        if (!$headers || !in_array('Codigo', $headers) || !in_array('Nombre', $headers)) {
            throw new Exception("El archivo debe tener headers 'Codigo' y 'Nombre'");
        }

        $conn->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data = array_combine($headers, $row);

                if (empty($data['Codigo']) || empty($data['Nombre'])) {
                    continue;
                }

                $query = "INSERT INTO centros_costos (codigo, nombre) VALUES (:codigo, :nombre)
                          ON DUPLICATE KEY UPDATE nombre = :nombre, activo = 1";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':codigo' => trim($data['Codigo']),
                    ':nombre' => trim($data['Nombre'])
                ]);

                $registros_importados++;
            }

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Error al importar centros de costos: " . $e->getMessage());
        }

        fclose($handle);
    }

    return $registros_importados;
}

/**
 * Importa elementos desde CSV  
 * Formato esperado: Cód. Artículo, Referencia, Centro Costos 1, Centro Costos 2, etc.
 */
function importarElementos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    if (!file_exists($archivo_csv)) {
        throw new Exception("Archivo de elementos no encontrado");
    }

    $registros_importados = 0;

    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");

        if (!$headers || !in_array('Cód. Artículo', $headers)) {
            throw new Exception("El archivo debe tener header 'Cód. Artículo'");
        }

        $conn->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $data = array_combine($headers, $row);

                if (empty($data['Cód. Artículo'])) {
                    continue;
                }

                $query = "INSERT INTO elementos 
                          (codigo, referencia, descripcion, centro_costo_1, centro_costo_2, 
                           centro_costo_3, centro_costo_4, centro_costo_5) 
                          VALUES (:codigo, :referencia, :descripcion, :cc1, :cc2, :cc3, :cc4, :cc5)
                          ON DUPLICATE KEY UPDATE 
                          referencia = :referencia,
                          descripcion = :descripcion,
                          centro_costo_1 = :cc1,
                          centro_costo_2 = :cc2,
                          centro_costo_3 = :cc3,
                          centro_costo_4 = :cc4,
                          centro_costo_5 = :cc5,
                          activo = 1";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':codigo' => trim($data['Cód. Artículo']),
                    ':referencia' => isset($data['Referencia']) ? trim($data['Referencia']) : '',
                    ':descripcion' => isset($data['Descripción']) ? trim($data['Descripción']) : '',
                    ':cc1' => isset($data['Centro Costos 1']) && !empty($data['Centro Costos 1']) ? trim($data['Centro Costos 1']) : null,
                    ':cc2' => isset($data['Centro Costos 2']) && !empty($data['Centro Costos 2']) ? trim($data['Centro Costos 2']) : null,
                    ':cc3' => isset($data['Centro Costos 3']) && !empty($data['Centro Costos 3']) ? trim($data['Centro Costos 3']) : null,
                    ':cc4' => isset($data['Centro Costos 4']) && !empty($data['Centro Costos 4']) ? trim($data['Centro Costos 4']) : null,
                    ':cc5' => isset($data['Centro Costos 5']) && !empty($data['Centro Costos 5']) ? trim($data['Centro Costos 5']) : null
                ]);

                $registros_importados++;
            }

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Error al importar elementos: " . $e->getMessage());
        }

        fclose($handle);
    }

    return $registros_importados;
}

/**
 * Obtiene estadísticas de la tabla temporal
 */
function obtenerEstadisticasTemp()
{
    $database = new Database();
    $conn = $database->connect();

    $query = "SELECT 
                COUNT(*) as total_registros,
                COUNT(DISTINCT codigo_elemento) as elementos_unicos,
                COUNT(DISTINCT centro_costo_asignado) as centros_costo_unicos,
                MIN(fecha_movimiento) as fecha_min,
                MAX(fecha_movimiento) as fecha_max,
                SUM(cantidad) as cantidad_total
              FROM inventarios_temp";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>