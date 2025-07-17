<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Manejar importación de centros de costos
    if (isset($_POST['action']) && $_POST['action'] === 'import_centros' && isset($_FILES['configFile'])) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_centros_' . $_FILES['configFile']['name'];
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['configFile']['tmp_name'], $uploadPath)) {
            try {
                $importados = importarCentrosCostos($uploadPath);
                unlink($uploadPath);

                echo json_encode([
                    'success' => true,
                    'records' => $importados,
                    'message' => 'Centros de costos importados correctamente'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al importar centros de costos: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al subir el archivo de centros de costos'
            ]);
        }
        exit;
    }

    // Manejar importación de elementos
    if (isset($_POST['action']) && $_POST['action'] === 'import_elementos' && isset($_FILES['configFile'])) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_elementos_' . $_FILES['configFile']['name'];
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['configFile']['tmp_name'], $uploadPath)) {
            try {
                $importados = importarElementos($uploadPath);
                unlink($uploadPath);

                echo json_encode([
                    'success' => true,
                    'records' => $importados,
                    'message' => 'Elementos importados correctamente'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al importar elementos: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al subir el archivo de elementos'
            ]);
        }
        exit;
    }

    // Manejar procesamiento de inventario Ineditto
    if (isset($_FILES['csvFile'])) {
        $uploadDir = '../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_inventario_' . $_FILES['csvFile']['name'];
        $uploadPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['csvFile']['tmp_name'], $uploadPath)) {
            try {
                $records = procesarInventarioIneditto($uploadPath);

                // Limpiar archivo temporal
                unlink($uploadPath);

                // Obtener estadísticas del procesamiento
                $stats = obtenerEstadisticasTablaTemp();

                echo json_encode([
                    'success' => true,
                    'records' => $records,
                    'statistics' => $stats,
                    'message' => 'Archivo de inventario procesado correctamente'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al procesar inventario: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al subir el archivo de inventario'
            ]);
        }
        exit;
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Método no permitido o archivo no encontrado'
]);
?>