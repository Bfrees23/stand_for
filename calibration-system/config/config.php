<?php
/**
 * Конфигурация системы калибровки
 */

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'calibration_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Настройки сессии
ini_set('session.gc_maxlifetime', 3600); // 1 час
ini_set('session.cookie_lifetime', 3600);

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

// Серийные порты (по умолчанию)
$defaultPorts = [
    'corrector' => [
        'port' => 'COM1',
        'baudrate' => 19200,
        'parity' => 'none',
        'data_bits' => 8,
        'stop_bits' => 1
    ],
    'mit8_channel1' => [
        'port' => 'COM2',
        'setpoint' => -40
    ],
    'mit8_channel2' => [
        'port' => 'COM3',
        'setpoint' => 10
    ],
    'mit8_channel3' => [
        'port' => 'COM4',
        'setpoint' => 60
    ],
    'pressure_calibrator' => [
        'port' => 'COM5',
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
