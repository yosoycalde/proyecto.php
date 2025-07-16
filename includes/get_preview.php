<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    $query = "SELECT codigo_elemento, nombre_elemento, cantidad, valor_unitario, 
                     valor_total, fecha_movimiento, centro_costo_asignado 
              FROM inventarios_temp 
              ORDER BY fecha_movimiento 
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>