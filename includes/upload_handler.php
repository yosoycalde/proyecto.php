<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once 'functions.php';

// Función para convertir Excel a CSV (si es necesario)
function convertirExcelACSV($archivoExcel)
{
    $fileExtension = strtolower(pathinfo($archivoExcel, PATHINFO_EXTENSION));

    if ($fileExtension === 'csv') {
        return $archivoExcel; // Ya es CSV, no necesita conversión
    }

    // Para archivos Excel, necesitarías PhpSpreadsheet
    // Por simplicidad, por ahora solo aceptamos CSV
    throw new Exception("Por favor, convierta el archivo Excel a CSV antes de subirlo.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // MANEJAR IMPORTACIÓN DE CENTROS DE COSTOS
        if (isset($_POST['action']) && $_POST['action'] === 'import_centros' && isset($_FILES['configFile'])) {

            $uploadDir = '../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $file = $_FILES['configFile'];

            // Validar tipo de archivo
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
                throw new Exception('Solo se permiten archivos CSV, XLS o XLSX');
            }

            $fileName = time() . '_centros_' . $file['name'];
            $uploadPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al subir el archivo');
            }

            // Convertir a CSV si es necesario
            $csvPath = convertirExcelACSV($uploadPath);

            $importados = importarCentrosCostos($csvPath);

            // Limpiar archivos temporales
            if (file_exists($uploadPath))
                unlink($uploadPath);
            if ($csvPath !== $uploadPath && file_exists($csvPath))
                unlink($csvPath);

            echo json_encode([
                'success' => true,
                'records' => $importados,
                'message' => 'Centros de costos importados correctamente'
            ]);
            exit;
        }

        // MANEJAR IMPORTACIÓN DE ELEMENTOS
        if (isset($_POST['action']) && $_POST['action'] === 'import_elementos' && isset($_FILES['configFile'])) {

            $uploadDir = '../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $file = $_FILES['configFile'];

            // Validar tipo de archivo
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
                throw new Exception('Solo se permiten archivos CSV, XLS o XLSX');
            }

            $fileName = time() . '_elementos_' . $file['name'];
            $uploadPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al subir el archivo');
            }

            // Convertir a CSV si es necesario
            $csvPath = convertirExcelACSV($uploadPath);

            $importados = importarElementos($csvPath);

            // Limpiar archivos temporales
            if (file_exists($uploadPath))
                unlink($uploadPath);
            if ($csvPath !== $uploadPath && file_exists($csvPath))
                unlink($csvPath);

            echo json_encode([
                'success' => true,
                'records' => $importados,
                'message' => 'Elementos importados correctamente'
            ]);
            exit;
        }

        // MANEJAR PROCESAMIENTO DE INVENTARIO INEDITTO
        if (isset($_FILES['csvFile'])) {

            $uploadDir = '../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $file = $_FILES['csvFile'];

            // Validar que es un archivo CSV
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($fileExtension !== 'csv') {
                throw new Exception('Solo se permiten archivos CSV para el inventario');
            }

            $fileName = time() . '_inventario_' . $file['name'];
            $uploadPath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al subir el archivo de inventario');
            }

            // Procesar el inventario
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
            exit;
        }

        // Si llegamos aquí, no se encontró una acción válida
        throw new Exception('No se encontró una acción válida o archivo requerido');

    } catch (Exception $e) {
        // Limpiar archivos en caso de error
        if (isset($uploadPath) && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        if (isset($csvPath) && $csvPath !== $uploadPath && file_exists($csvPath)) {
            unlink($csvPath);
        }

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Se requiere POST.'
    ]);
}
?>