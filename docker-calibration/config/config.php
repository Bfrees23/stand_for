<?php
/**
 * Конфигурация системы калибровки
 */

// Настройки базы данных (для Docker)
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'calibration');
define('DB_USER', getenv('DB_USER') ?: 'calibration_user');
define('DB_PASS', getenv('DB_PASS') ?: 'calibration_pass');

// Таймауты (в секундах)
define('STABILIZATION_TIMEOUT', 900); // 15 минут
define('TEMP_STABILIZATION_TIME', 300); // 5 минут для стабилизации температуры
define('TEMP_POLL_INTERVAL', 30); // 30 секунд опрос МИТ-8

// Допуски
define('TEMP_TOLERANCE', 0.5); // ±0.5°C для температуры
define('PRESSURE_TOLERANCE_PERCENT', 1.0); // 1% для давления

// Пути
define('BASE_PATH', dirname(__DIR__));
define('LOGS_PATH', BASE_PATH . '/logs');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('DATA_PATH', BASE_PATH . '/data');

// Создаем директорию для данных если не существует
if (!file_exists(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

// Серийные порты (по умолчанию)
$defaultPorts = [
    'corrector' => [
        'port' => getenv('CORRECTOR_PORT') ?: '/dev/ttyUSB0',
        'baudrate' => 19200,
        'parity' => 'none',
        'data_bits' => 8,
        'stop_bits' => 1
    ],
    'mit8_channel1' => [
        'port' => getenv('MIT8_CH1_PORT') ?: '/dev/ttyUSB1',
        'setpoint' => -40
    ],
    'mit8_channel2' => [
        'port' => getenv('MIT8_CH2_PORT') ?: '/dev/ttyUSB2',
        'setpoint' => 10
    ],
    'mit8_channel3' => [
        'port' => getenv('MIT8_CH3_PORT') ?: '/dev/ttyUSB3',
        'setpoint' => 60
    ],
    'pressure_calibrator' => [
        'port' => getenv('PRESSURE_PORT') ?: '/dev/ttyUSB4',
        'type' => 'absolute', // absolute или differential
        'range_min' => 0,
        'range_max' => 1000
    ]
];

// Типы датчиков давления
$pressureSensorTypes = [
    'absolute' => 'Абсолютный датчик (5 точек)',
    'differential' => 'Датчик перепада (3 точки)'
];

// Точки поверки для разных типов датчиков
$pressureCheckpoints = [
    'absolute' => [0, 25, 50, 75, 100], // проценты от диапазона
    'differential' => [0, 50, 100] // проценты от диапазона
];

// Статусы этапов
$statuses = [
    'pending' => 'Ожидание',
    'in_progress' => 'В процессе',
    'completed' => 'Завершено',
    'error' => 'Ошибка',
    'skipped' => 'Пропущено'
];
