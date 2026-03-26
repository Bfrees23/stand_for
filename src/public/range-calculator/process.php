<?php
header('Content-Type: application/json; charset=utf-8');

// Разрешаем CORS для локальной разработки (если нужно)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запроса
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Метод должен быть POST'
    ]);
    exit;
}

// Получаем JSON из тела запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Проверяем наличие данных
if (!isset($data['minValue']) || !isset($data['maxValue'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Необходимо указать minValue и maxValue'
    ]);
    exit;
}

$minValue = floatval($data['minValue']);
$maxValue = floatval($data['maxValue']);

// Валидация
if ($minValue >= $maxValue) {
    echo json_encode([
        'success' => false,
        'error' => 'Минимальное значение должно быть меньше максимального'
    ]);
    exit;
}

// Расчет трех равных частей
$totalRange = $maxValue - $minValue;
$partSize = $totalRange / 3;

$parts = [
    [
        'start' => round($minValue, 2),
        'end' => round($minValue + $partSize, 2),
        'range' => round($partSize, 2)
    ],
    [
        'start' => round($minValue + $partSize, 2),
        'end' => round($minValue + 2 * $partSize, 2),
        'range' => round($partSize, 2)
    ],
    [
        'start' => round($minValue + 2 * $partSize, 2),
        'end' => round($maxValue, 2),
        'range' => round($partSize, 2)
    ]
];

// Возвращаем результат
echo json_encode([
    'success' => true,
    'parts' => $parts,
    'input' => [
        'min' => $minValue,
        'max' => $maxValue,
        'total_range' => round($totalRange, 2)
    ]
]);
