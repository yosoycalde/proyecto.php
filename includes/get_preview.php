<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();

    $query = "SELECT 
                IRECURSO as codigo_elemento,
                ICCSUBCC as descripcion_categoria, 
                QCANTLUN as cantidad,
                FSOPORT as fecha_movimiento,
                centro_costo_asignado,
                ILABOR as labor_original,
                SOBSERVAC as observaciones
              FROM inventarios_temp 
              ORDER BY fecha_procesamiento DESC
              LIMIT 15";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statsQuery = "SELECT 
                    COUNT(*) as total_registros,
                    COUNT(CASE WHEN ILABOR IS NULL OR ILABOR = '' THEN 1 END) as registros_sin_labor,
                    COUNT(DISTINCT centro_costo_asignado) as centros_costo_utilizados,
                    SUM(QCANTLUN) as suma_total_cantidades,
                    MIN(fecha_procesamiento) as primer_registro,
                    MAX(fecha_procesamiento) as ultimo_registro
                   FROM inventarios_temp";

    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $distQuery = "SELECT centro_costo_asignado, COUNT(*) as cantidad_registros 
                  FROM inventarios_temp 
                  GROUP BY centro_costo_asignado 
                  ORDER BY cantidad_registros DESC";

    $distStmt = $conn->prepare($distQuery);
    $distStmt->execute();
    $distribucion = $distStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $results,
        'statistics' => $stats,
        'distribucion_centros_costo' => $distribucion
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener vista previa: ' . $e->getMessage()
    ]);
}
?>