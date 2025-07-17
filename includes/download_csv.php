<?php
// Evitar cualquier output antes de los headers
ob_start();

require_once '../config/database.php';
require_once 'functions.php';

try {
    // Verificar que hay datos en la tabla temporal
    $database = new Database();
    $conn = $database->connect();

    $checkQuery = "SELECT COUNT(*) as total FROM inventarios_temp";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($result['total'] == 0) {
        throw new Exception("No hay datos procesados para descargar. Primero debe procesar un archivo de inventario.");
    }

    // Generar el archivo CSV directamente
    $query = "SELECT IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, 
                     centro_costo_asignado as ICCSUBCC, ILABOR, QCANTLUN, QCANTMAR, 
                     QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, SOBSERVAC 
              FROM inventarios_temp 
              ORDER BY fecha_procesamiento";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Limpiar cualquier output previo
    ob_end_clean();

    // Configurar headers para descarga
    $filename = 'contapyme_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Pragma: no-cache');

    // Crear el output del CSV directamente
    $output = fopen('php://output', 'w');

    // BOM para UTF-8 (opcional, para Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers del CSV según formato ContaPyme
    $headers = [
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
    ];

    fputcsv($output, $headers);

    // Datos con la lógica correcta según los requisitos
    foreach ($resultados as $row) {
        $csvRow = [
            $row['IEMP'] ?? '',
            $row['FSOPORT'] ?? '',
            $row['ITDSOP'] ?? '',
            $row['INUMSOP'] ?? '',
            $row['INVENTARIO'] ?? '',
            $row['IRECURSO'] ?? '',
            $row['ICCSUBCC'] ?? '', // Centro de costo calculado
            '', // ILABOR siempre vacío en la salida según especificación
            $row['QCANTLUN'] ?? '',
            '', // QCANTMAR vacío
            '', // QCANTMIE vacío  
            '', // QCANTJUE vacío
            '', // QCANTVIE vacío
            '', // QCANTSAB vacío
            '', // QCANTDOM vacío
            $row['SOBSERVAC'] ?? ''
        ];
        
        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    // Limpiar buffer si hay error
    ob_end_clean();
    
    // Mostrar error en página HTML
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Descarga CSV</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: 100px auto;
                padding: 20px;
                text-align: center;
            }
            .error-container {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                padding: 30px;
                border-radius: 10px;
            }
            .back-btn {
                background: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h2>❌ Error al generar archivo</h2>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="../index.php" class="back-btn">🔙 Volver al inicio</a>
        </div>
    </body>
    </html>
    <?php
}
?>