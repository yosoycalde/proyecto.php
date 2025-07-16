<?php
require_once '../config/database.php';
require_once 'functions.php';

try {
    $filename = generarCSVContaPyme();
    
    if (file_exists($filename)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        
        readfile($filename);
        
        // Opcional: eliminar archivo después de descarga
        unlink($filename);
        exit;
    }
} catch (Exception $e) {
    echo "Error al generar el archivo: " . $e->getMessage();
}
?>