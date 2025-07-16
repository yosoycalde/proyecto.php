<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $uploadDir = '../uploads/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = time() . '_' . $_FILES['csvFile']['name'];
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['csvFile']['tmp_name'], $uploadPath)) {
        try {
            $records = procesarInventario($uploadPath);
            
            // Limpiar archivo temporal
            unlink($uploadPath);
            
            echo json_encode([
                'success' => true,
                'records' => $records,
                'message' => 'Archivo procesado correctamente'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al procesar: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al subir el archivo'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>