<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'functions.php';

// Habilitar CORS si es necesario
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['configFile']) && isset($_POST['tipo'])) {
    $uploadDir = '../uploads/config/';

    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $tipo = $_POST['tipo'];
    $fileName = time() . '_' . $tipo . '_' . $_FILES['configFile']['name'];
    $uploadPath = $uploadDir . $fileName;

    // Validar tipo de archivo
    $fileExtension = strtolower(pathinfo($_FILES['configFile']['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Solo se permiten archivos CSV, XLS o XLSX'
        ]);
        exit;
    }

    if (move_uploaded_file($_FILES['configFile']['tmp_name'], $uploadPath)) {
        try {
            $registros = 0;

            // Convertir Excel a CSV si es necesario
            if (in_array($fileExtension, ['xlsx', 'xls'])) {
                $csvPath = convertirExcelACSV($uploadPath);
                $uploadPath = $csvPath;
            }

            switch ($tipo) {
                case 'centros_costos':
                    $registros = importarCentrosCostos($uploadPath);
                    $mensaje = "Centros de costos importados correctamente";
                    break;

                case 'elementos':
                    $registros = importarElementos($uploadPath);
                    $mensaje = "Elementos importados correctamente";
                    break;

                default:
                    throw new Exception("Tipo de configuración no válido");
            }

            // Limpiar archivo temporal
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }

            echo json_encode([
                'success' => true,
                'records' => $registros,
                'message' => $mensaje
            ]);

        } catch (Exception $e) {
            // Limpiar archivo en caso de error
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }

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
        'message' => 'Solicitud no válida. Se requiere archivo y tipo.'
    ]);
}

/**
 * Convierte archivo Excel a CSV
 */
function convertirExcelACSV($archivoExcel)
{
    require_once '../vendor/autoload.php'; // Si usas Composer para PhpSpreadsheet

    try {
        // Si no tienes PhpSpreadsheet, puedes usar una alternativa más simple
        // o procesar manualmente el archivo Excel

        $csvPath = str_replace(['.xlsx', '.xls'], '.csv', $archivoExcel);

        // Implementación básica - reemplazar con PhpSpreadsheet si está disponible
        // Por ahora, solo copiamos el archivo si ya es CSV
        if (pathinfo($archivoExcel, PATHINFO_EXTENSION) === 'csv') {
            copy($archivoExcel, $csvPath);
        } else {
            throw new Exception("Para procesar archivos Excel, instale PhpSpreadsheet");
        }

        return $csvPath;

    } catch (Exception $e) {
        throw new Exception("Error al convertir Excel a CSV: " . $e->getMessage());
    }
}
?>