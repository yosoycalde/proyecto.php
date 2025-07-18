<?php
// Evitar cualquier output antes de los headers
ob_start();

require_once '../config/database.php';
require_once 'functions.php';

/**
 * Función para limpiar archivos temporales del directorio uploads
 */
function limpiarArchivosTemporales() {
    $uploadDir = '../uploads/';
    
    if (!is_dir($uploadDir)) {
        return;
    }
    
    $archivos = scandir($uploadDir);
    $archivosEliminados = 0;
    
    foreach ($archivos as $archivo) {
        if ($archivo === '.' || $archivo === '..') {
            continue;
        }
        
        $rutaArchivo = $uploadDir . $archivo;
        
        // Verificar si es un archivo y no un directorio
        if (is_file($rutaArchivo)) {
            // Eliminar archivos que contengan timestamps (archivos temporales generados por el sistema)
            if (preg_match('/^\d+_/', $archivo)) {
                if (unlink($rutaArchivo)) {
                    $archivosEliminados++;
                }
            }
        }
    }
    
    return $archivosEliminados;
}

/**
 * Función para limpiar tabla temporal
 */
function limpiarTablaTemporalInventarios() {
    try {
        $database = new Database();
        $conn = $database->connect();
        
        $query = "DELETE FROM inventarios_temp";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Error limpiando tabla temporal: " . $e->getMessage());
        return 0;
    }
}

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

    // Obtener los datos antes de limpiar
    $query = "SELECT IEMP, FSOPORT, ITDSOP, INUMSOP, INVENTARIO, IRECURSO, 
                     centro_costo_asignado as ICCSUBCC, ILABOR, QCANTLUN, QCANTMAR, 
                     QCANTMIE, QCANTJUE, QCANTVIE, QCANTSAB, QCANTDOM, SOBSERVAC 
              FROM inventarios_temp 
              ORDER BY fecha_procesamiento";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si hay datos, proceder con la descarga y limpieza
    if (!empty($resultados)) {
        
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
        
        // REALIZAR LIMPIEZA DESPUÉS DE ENVIAR EL ARCHIVO
        // Usar register_shutdown_function para ejecutar la limpieza después de que se complete la descarga
        register_shutdown_function(function() {
            // Limpiar archivos temporales
            $archivosEliminados = limpiarArchivosTemporales();
            
            // Limpiar tabla temporal
            $registrosEliminados = limpiarTablaTemporalInventarios();
            
            // Log de la limpieza (opcional)
            error_log("Limpieza automática completada: $archivosEliminados archivos eliminados, $registrosEliminados registros de tabla temporal eliminados");
        });
        
        exit;
    } else {
        throw new Exception("No se encontraron datos para procesar");
    }

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
            <h2>❌ Error al generar archivo</h2>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="../index.php" class="back-btn">🔙 Volver al inicio</a>
            
            <div class="cleanup-info">
                <h4>🧹 Limpieza automática</h4>
                <p>Si había datos procesados, puede realizar una limpieza manual desde el panel principal.</p>
            </div>
        </div>
        
        <script>
        // Opcional: limpiar automáticamente en caso de error después de 5 segundos
        setTimeout(function() {
            if (confirm('¿Desea limpiar los archivos temporales y datos procesados?')) {
                fetch('../includes/cleanup.php', {
                    method: 'POST'
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Limpieza completada');
                    }
                }).catch(error => {
                    console.log('Error en limpieza automática:', error);
                });
            }
        }, 5000);
        </script>
    </body>
    </html>
    <?php
}
?>