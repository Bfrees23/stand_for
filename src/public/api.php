<?php
session_start();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'save_calibration':
        $data = $_POST['data'] ?? null;
        if ($data) {
            $_SESSION['calibration_data'] = $data;
            echo json_encode(['status' => 'saved']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Нет данных']);
        }
        break;

    case 'get_report':
        $data = $_SESSION['calibration_data'] ?? null;
        if ($data) {
            echo json_encode(['report' => $data]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Нет данных']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Неизвестное действие']);
}
?>