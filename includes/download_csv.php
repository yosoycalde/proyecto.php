<?php

ob_start();

require_once '../config/database.php';
require_once 'functions.php';

try {
    $database = new Database();
    $conn = $database->connect();

    $checkQuery = "SELECT COUNT(*) as total FROM inventarios_temp";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($result['total'] == 0) {
        throw new Exception("No hay datos procesados para descargar. Primero debe procesar un archivo de inventario.");
    }

    $query = "SELECT IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, 
                     centro_costo_asignado as ICCSUBCC, ILABOR, QCANTLUN, QCANTMAR, 
                     QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, SOBSERVAC 
              FROM inventarios_temp 
              ORDER BY fecha_procesamiento";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        throw new Exception("No se encontraron datos para procesar");
    }

    ob_start();
    $csvOutput = fopen('php://output', 'w');

    fprintf($csvOutput, chr(0xEF) . chr(0xBB) . chr(0xBF));

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

    fputcsv($csvOutput, $headers);

    foreach ($resultados as $row) {
        $csvRow = [
            $row['IEMP'] ?? '',
            $row['FSOPORT'] ?? '',
            $row['ITDSOP'] ?? '',
            $row['INUMSOP'] ?? '',
            $row['INVENTARIO'] ?? '',
            $row['IRECURSO'] ?? '',
            $row['ICCSUBCC'] ?? '',
            '',
            $row['QCANTLUN'] ?? '',
            '',
            '',
            '',
            '',
            '',
            '',
            $row['SOBSERVAC'] ?? ''
        ];

        fputcsv($csvOutput, $csvRow);
    }

    fclose($csvOutput);
    $csvContent = ob_get_contents();
    ob_end_clean();

    try {
        $deleteQuery = "DELETE FROM inventarios_temp";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->execute();
        $registrosEliminados = $deleteStmt->rowCount();
    } catch (Exception $e) {
        error_log("Error limpiando tabla temporal: " . $e->getMessage());
        $registrosEliminados = 0;
    }

    $archivosEliminados = 0;
    $uploadDir = '../uploads/';
    if (is_dir($uploadDir)) {
        $archivos = scandir($uploadDir);
        foreach ($archivos as $archivo) {
            if ($archivo === '.' || $archivo === '..')
                continue;

            $rutaArchivo = $uploadDir . $archivo;
            if (is_file($rutaArchivo) && preg_match('/^\d+_/', $archivo)) {
                if (unlink($rutaArchivo)) {
                    $archivosEliminados++;
                }
            }
        }
    }

    error_log("Limpieza realizada antes de descarga: $archivosEliminados archivos eliminados, $registrosEliminados registros eliminados");

    $filename = 'contapyme_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($csvContent));

    echo $csvContent;
    exit;

} catch (Exception $e) {
    ob_end_clean();

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

            .cleanup-info {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
                padding: 15px;
                border-radius: 5px;
                margin-top: 20px;
                font-size: 0.9em;
            }
        </style>
    </head>

    <body>
        <div class="error-container">
            <h2> Error al generar archivo</h2>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="../index.php" class="back-btn"> Volver al inicio</a>

            <div class="cleanup-info">
                <h4> Limpieza automática</h4>
                <p>Si había datos procesados, puede realizar una limpieza manual desde el panel principal.</p>
            </div>
        </div>

        <script>
            setTimeout(function () {
                fetch('../includes/cleanup.php', {
                    method: 'POST'
                }).then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log(' Limpieza automática completada');
                        }
                    }).catch(error => {
                        console.log('Error en limpieza automática:', error);
                    });
            }, 3000);
        </script>
    </body>

    </html>
    <?php
}
?>