<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

/**
 * Verificar el estado de la limpieza
 */
function verificarEstadoLimpieza()
{
    try {
        $database = new Database();
        $conn = $database->connect();

        // Verificar registros en tabla temporal
        $query = "SELECT COUNT(*) as total_registros FROM inventarios_temp";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $registrosTemporales = $result['total_registros'];

        // Verificar archivos temporales
        $uploadDir = '../uploads/';
        $archivosTemporales = 0;

        if (is_dir($uploadDir)) {
            $archivos = scandir($uploadDir);
            foreach ($archivos as $archivo) {
                if ($archivo === '.' || $archivo === '..')
                    continue;

                $rutaArchivo = $uploadDir . $archivo;
                if (is_file($rutaArchivo) && preg_match('/^\d+_/', $archivo)) {
                    $archivosTemporales++;
                }
            }
        }

        $estaLimpio = ($registrosTemporales === 0 && $archivosTemporales === 0);

        return [
            'success' => true,
            'limpio' => $estaLimpio,
            'registros_temporales' => $registrosTemporales,
            'archivos_temporales' => $archivosTemporales,
            'message' => $estaLimpio ? 'Sistema limpio' : 'Pendiente de limpieza'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error verificando estado: ' . $e->getMessage()
        ];
    }
}

/**
 * Realizar limpieza forzada
 */
function realizarLimpiezaForzada()
{
    try {
        $database = new Database();
        $conn = $database->connect();

        // Limpiar tabla temporal
        $query = "DELETE FROM inventarios_temp";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $registrosEliminados = $stmt->rowCount();

        // Limpiar archivos temporales
        $uploadDir = '../uploads/';
        $archivosEliminados = 0;

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

        return [
            'success' => true,
            'archivos_eliminados' => $archivosEliminados,
            'registros_eliminados' => $registrosEliminados,
            'message' => 'Limpieza forzada completada'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error en limpieza forzada: ' . $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verificar estado
    echo json_encode(verificarEstadoLimpieza());
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Realizar limpieza forzada
    echo json_encode(realizarLimpiezaForzada());
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>