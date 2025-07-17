<?php
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

    $filename = generarCSVContaPyme();

    if (file_exists($filename)) {
        // Configurar headers para descarga
        header('Content-Description: File Transfer');
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));

        // Limpiar cualquier output previo
        ob_clean();
        flush();

        // Enviar archivo
        readfile($filename);

        // Opcional: eliminar archivo después de descarga
        // unlink($filename);
        exit;
    } else {
        throw new Exception("Error al generar el archivo CSV.");
    }

} catch (Exception $e) {
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