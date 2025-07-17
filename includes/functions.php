<?php
include(__DIR__ . '/../config/database.php');

function obtenerCentroCosto($ilabor, $codigo_elemento)
{
    $database = new Database();
    $conn = $database->connect();

    // Si ILABOR no está vacío, buscar el centro de costo correspondiente
    if (!empty($ilabor)) {
        $query = "SELECT codigo FROM centros_costos WHERE nombre LIKE :ilabor OR codigo LIKE :ilabor";
        $stmt = $conn->prepare($query);
        $searchTerm = '%' . $ilabor . '%';
        $stmt->bindParam(':ilabor', $searchTerm);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result['codigo'];
        }
    }

    // Si ILABOR está vacío o no se encontró, buscar centro de costo 1 del elemento
    $query = "SELECT centro_costo_1 FROM elementos WHERE codigo = :codigo_elemento";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':codigo_elemento', $codigo_elemento);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['centro_costo_1'] ? $result['centro_costo_1'] : '11212317001'; // Default REVISTAS
}

function procesarInventarioIneditto($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    // Limpiar tabla temporal
    $conn->exec("DELETE FROM inventarios_temp");

    // Leer archivo CSV
    $datos = [];
    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ","); // Primera fila con headers

        // Limpiar headers de espacios en blanco
        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) == count($headers)) {
                $datos[] = array_combine($headers, $row);
            }
        }
        fclose($handle);
    }

    $procesados = 0;
    // Insertar datos en tabla temporal
    foreach ($datos as $fila) {
        try {
            // Obtener centro de costo usando la lógica requerida
            $centro_costo = obtenerCentroCosto($fila['ILABOR'], $fila['IRECURSO']);

            $query = "INSERT INTO inventarios_temp 
                      (IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, ICCSUBCC, ILABOR,
                       QCANTLUN, QCANTMAR, QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, 
                       SOBSERVAC, centro_costo_asignado) 
                      VALUES (:iemp, :fsoport, :itdsop, :inumsop, :inventario, :irecurso, :iccsubcc, :ilabor,
                              :qcantlun, :qcantmar, :qcantmie, :qcantjue, :qcantvie, :qcantsab, :qcantdom,
                              :sobservac, :centro_costo)";

            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':iemp' => $fila['IEMP'] ?: 1,
                ':fsoport' => $fila['FSOPORT'] ?: '',
                ':itdsop' => $fila['ITDSOP'] ?: 160,
                ':inumsop' => $fila['INUMSOP'] ?: null,
                ':inventario' => $fila['INVENTARIO'] ?: 1,
                ':irecurso' => $fila['IRECURSO'] ?: '',
                ':iccsubcc' => $centro_costo, // Usar el centro de costo calculado
                ':ilabor' => $fila['ILABOR'] ?: '',
                ':qcantlun' => $fila['QCANTLUN'] ?: 0,
                ':qcantmar' => $fila['QCANTMAR'] ?: null,
                ':qcantmie' => $fila['QCANTMIE'] ?: null,
                ':qcantjue' => $fila['QCANTJUE'] ?: null,
                ':qcantvie' => $fila['QCANTVIE'] ?: null,
                ':qcantsab' => $fila['QCANTSAB'] ?: null,
                ':qcantdom' => $fila['QCANTDOM'] ?: null,
                ':sobservac' => $fila['SOBSERVAC'] ?: '',
                ':centro_costo' => $centro_costo
            ]);
            $procesados++;
        } catch (Exception $e) {
            error_log("Error procesando fila: " . print_r($fila, true) . " Error: " . $e->getMessage());
        }
    }

    return $procesados;
}

function generarCSVContaPyme()
{
    $database = new Database();
    $conn = $database->connect();

    $query = "SELECT IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, 
                     centro_costo_asignado as ICCSUBCC, ILABOR, QCANTLUN, QCANTMAR, 
                     QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, SOBSERVAC 
              FROM inventarios_temp 
              ORDER BY fecha_procesamiento";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $timestamp = date('Y-m-d_H-i-s');
    $filename = '../exports/contapyme_' . $timestamp . '.csv';

    // Crear directorio si no existe
    $exportDir = '../exports';
    if (!file_exists($exportDir)) {
        mkdir($exportDir, 0777, true);
    }

    $file = fopen($filename, 'w');

    // Headers del CSV según formato ContaPyme
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

    // Datos con la lógica correcta según los requisitos
    foreach ($resultados as $row) {
        fputcsv($file, [
            $row['IEMP'],
            $row['FSOPORT'],
            $row['ITDSOP'],
            $row['INUMSOP'],
            $row['INVENTARIO'],
            $row['IRECURSO'],
            $row['ICCSUBCC'], // Centro de costo calculado
            '', // ILABOR siempre vacío en la salida según especificación
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
    return $filename;
}

function importarCentrosCostos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    $importados = 0;
    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) == count($headers)) {
                $data = array_combine($headers, $row);

                $query = "INSERT INTO centros_costos (codigo, nombre) VALUES (:codigo, :nombre)
                          ON DUPLICATE KEY UPDATE nombre = :nombre";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':codigo' => trim($data['Codigo'] ?? $data['codigo'] ?? ''),
                    ':nombre' => trim($data['Nombre'] ?? $data['nombre'] ?? '')
                ]);
                $importados++;
            }
        }
        fclose($handle);
    }
    return $importados;
}

function importarElementos($archivo_csv)
{
    $database = new Database();
    $conn = $database->connect();

    $importados = 0;
    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_map('trim', $headers);

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($row) == count($headers)) {
                $data = array_combine($headers, $row);

                $query = "INSERT INTO elementos 
                          (codigo, referencia, descripcion, centro_costo_1, centro_costo_2, centro_costo_3, centro_costo_4, centro_costo_5) 
                          VALUES (:codigo, :referencia, :descripcion, :cc1, :cc2, :cc3, :cc4, :cc5)
                          ON DUPLICATE KEY UPDATE 
                          referencia = :referencia,
                          descripcion = :descripcion,
                          centro_costo_1 = :cc1,
                          centro_costo_2 = :cc2,
                          centro_costo_3 = :cc3,
                          centro_costo_4 = :cc4,
                          centro_costo_5 = :cc5";

                $stmt = $conn->prepare($query);
                $stmt->execute([
                    ':codigo' => trim($data['Cód. Artículo'] ?? $data['codigo'] ?? ''),
                    ':referencia' => trim($data['Referencia'] ?? $data['referencia'] ?? ''),
                    ':descripcion' => trim($data['Descripción'] ?? $data['descripcion'] ?? ''),
                    ':cc1' => !empty($data['Centro Costos 1']) ? trim($data['Centro Costos 1']) : null,
                    ':cc2' => !empty($data['Centro Costos 2']) ? trim($data['Centro Costos 2']) : null,
                    ':cc3' => !empty($data['Centro Costos 3']) ? trim($data['Centro Costos 3']) : null,
                    ':cc4' => !empty($data['Centro Costos 4']) ? trim($data['Centro Costos 4']) : null,
                    ':cc5' => !empty($data['Centro Costos 5']) ? trim($data['Centro Costos 5']) : null
                ]);
                $importados++;
            }
        }
        fclose($handle);
    }
    return $importados;
}

function obtenerEstadisticasTablaTemp()
{
    $database = new Database();
    $conn = $database->connect();

    $query = "SELECT 
                COUNT(*) as total_registros,
                COUNT(CASE WHEN ILABOR IS NULL OR ILABOR = '' THEN 1 END) as ilabor_vacios,
                COUNT(DISTINCT centro_costo_asignado) as centros_costo_diferentes,
                SUM(QCANTLUN) as suma_cantidades
              FROM inventarios_temp";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>