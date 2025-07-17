<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'functions.php';

try {
    $stats = obtenerEstadisticasTemp();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>