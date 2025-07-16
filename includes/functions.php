<?php
include 'config/database.php';

function obtenerCentroCosto($ilabor, $codigo_elemento) {
    $database = new Database();
    $conn = $database->connect();
    
    // Si ILABOR está vacío, buscar centro de costo 1 del elemento
    if (empty($ilabor)) {
        $query = "SELECT centro_costo_1 FROM elementos WHERE codigo = :codigo_elemento";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':codigo_elemento', $codigo_elemento);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['centro_costo_1'] : '11212317001'; // Default REVISTAS
    }
    
    return $ilabor;
}

function procesarInventario($archivo_csv) {
    $database = new Database();
    $conn = $database->connect();
    
    // Limpiar tabla temporal
    $conn->exec("DELETE FROM inventarios_temp");
    
    // Leer archivo CSV
    $datos = [];
    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ","); // Primera fila con headers
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $datos[] = array_combine($headers, $row);
        }
        fclose($handle);
    }
    
    // Insertar datos en tabla temporal
    foreach ($datos as $fila) {
        $centro_costo = obtenerCentroCosto($fila['ILABOR'], $fila['codigo_elemento']);
        
        $query = "INSERT INTO inventarios_temp 
                  (codigo_elemento, referencia, cantidad, fecha_movimiento, ILABOR, observaciones,
                   FSOPORT, IRECURSO, ICCSUBCC, QCANTLUN, SOBSERVAC) 
                  VALUES (:codigo, :referencia, :cantidad, :fecha, :ilabor, :obs,
                          :fsoport, :irecurso, :iccsubcc, :qcantlun, :sobservac)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':codigo' => $fila['codigo_elemento'],
            ':referencia' => $fila['referencia'] ?? '',
            ':cantidad' => $fila['cantidad'],
            ':fecha' => $fila['fecha_movimiento'],
            ':ilabor' => $fila['ILABOR'],
            ':obs' => $fila['observaciones'] ?? '',
            ':fsoport' => date('d/m/Y', strtotime($fila['fecha_movimiento'])),
            ':irecurso' => $fila['codigo_elemento'],
            ':iccsubcc' => $centro_costo,
            ':qcantlun' => $fila['cantidad'],
            ':sobservac' => $fila['observaciones'] ?? ''
        ]);
    }
    
    return count($datos);
}

function generarCSVContaPyme() {
    $database = new Database();
    $conn = $database->connect();
    
    $query = "SELECT IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, ICCSUBCC, 
                     ILABOR, QCANTLUN, QCANTMAR, QCANTMIE, QCANTJUE, QCANTVIE, 
                     QCANTSAB, QCANTDOM, SOBSERVAC 
              FROM inventarios_temp ORDER BY fecha_movimiento";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = 'exports/contapyme_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Crear directorio si no existe
    if (!file_exists('exports')) {
        mkdir('exports', 0777, true);
    }
    
    $file = fopen($filename, 'w');
    
    // Headers del CSV según formato ContaPyme
    fputcsv($file, ['IEMP', 'FSOPORT', 'ITDSOP', 'INUMSOP', 'INVENTARIO', 'IRECURSO', 
                   'ICCSUBCC', 'ILABOR', 'QCANTLUN', 'QCANTMAR', 'QCANTMIE', 'QCANTJUE', 
                   'QCANTVIE', 'QCANTSAB', 'QCANTDOM', 'SOBSERVAC']);
    
    // Datos
    foreach ($resultados as $row) {
        fputcsv($file, [
            $row['IEMP'],
            $row['FSOPORT'],
            $row['ITDSOP'],
            $row['INUMSOP'],
            $row['INVENTARIO'],
            $row['IRECURSO'],
            $row['ICCSUBCC'],
            '', // ILABOR siempre vacío en la salida
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

function importarCentrosCostos($archivo_csv) {
    $database = new Database();
    $conn = $database->connect();
    
    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data = array_combine($headers, $row);
            
            $query = "INSERT INTO centros_costos (codigo, nombre) VALUES (:codigo, :nombre)
                      ON DUPLICATE KEY UPDATE nombre = :nombre";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':codigo' => $data['Codigo'],
                ':nombre' => $data['Nombre']
            ]);
        }
        fclose($handle);
    }
}

function importarElementos($archivo_csv) {
    $database = new Database();
    $conn = $database->connect();
    
    if (($handle = fopen($archivo_csv, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data = array_combine($headers, $row);
            
            $query = "INSERT INTO elementos 
                      (codigo, referencia, centro_costo_1, centro_costo_2, centro_costo_3, centro_costo_4, centro_costo_5) 
                      VALUES (:codigo, :referencia, :cc1, :cc2, :cc3, :cc4, :cc5)
                      ON DUPLICATE KEY UPDATE 
                      referencia = :referencia,
                      centro_costo_1 = :cc1,
                      centro_costo_2 = :cc2,
                      centro_costo_3 = :cc3,
                      centro_costo_4 = :cc4,
                      centro_costo_5 = :cc5";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':codigo' => $data['Cód. Artículo'],
                ':referencia' => $data['Referencia'],
                ':cc1' => !empty($data['Centro Costos 1']) ? $data['Centro Costos 1'] : null,
                ':cc2' => !empty($data['Centro Costos 2']) ? $data['Centro Costos 2'] : null,
                ':cc3' => !empty($data['Centro Costos 3']) ? $data['Centro Costos 3'] : null,
                ':cc4' => !empty($data['Centro Costos 4']) ? $data['Centro Costos 4'] : null,
                ':cc5' => !empty($data['Centro Costos 5']) ? $data['Centro Costos 5'] : null
            ]);
        }
        fclose($handle);
    }
}
?>

<?php
include "css/style.css"
?>