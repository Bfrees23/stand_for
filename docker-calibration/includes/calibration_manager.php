<?php
/**
 * Класс для управления процессом калибровки и сессией
 */

class CalibrationManager {
    private $sessionData;
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['calibration'])) {
            $_SESSION['calibration'] = $this->getDefaultSessionData();
        }
        
        $this->sessionData = &$_SESSION['calibration'];
    }
    
    /**
     * Получить данные сессии по умолчанию
     */
    private function getDefaultSessionData() {
        return [
            'settings' => [
                'corrector' => [],
                'mit8' => [
                    'channel1' => ['port' => '', 'setpoint' => -40],
                    'channel2' => ['port' => '', 'setpoint' => 10],
                    'channel3' => ['port' => '', 'setpoint' => 60]
                ],
                'pressure_calibrator' => [
                    'type' => 'absolute',
                    'range_min' => 0,
                    'range_max' => 1000
                ]
            ],
            'devices_connected' => false,
            'current_stage' => 'setup', // setup, temperature, impulses, pressure, report
            'temperature_calibration' => [
                'status' => 'pending',
                'current_point' => null,
                'points' => [
                    '-40' => ['status' => 'pending', 'reference' => null, 'written' => null, 'time' => null],
                    '+10' => ['status' => 'pending', 'reference' => null, 'written' => null, 'time' => null],
                    '+60' => ['status' => 'pending', 'reference' => null, 'written' => null, 'time' => null]
                ],
                'verification' => [
                    'status' => 'pending',
                    'results' => []
                ]
            ],
            'impulses' => [
                'status' => 'pending',
                'count' => 0,
                'required' => 20,
                'log' => []
            ],
            'pressure' => [
                'status' => 'pending',
                'sensor_type' => 'absolute',
                'checkpoints' => [],
                'results' => [],
                'failed_attempts' => 0
            ],
            'completed' => false,
            'started_at' => null,
            'completed_at' => null,
            'operator_id' => null,
            'corrector_serial' => null
        ];
    }
    
    /**
     * Сохранить настройки оборудования
     */
    public function saveSettings($settings) {
        $this->sessionData['settings'] = $settings;
        $this->save();
    }
    
    /**
     * Отметить устройства как подключенные
     */
    public function setDevicesConnected($connected) {
        $this->sessionData['devices_connected'] = $connected;
        if ($connected && !$this->sessionData['started_at']) {
            $this->sessionData['started_at'] = date('Y-m-d H:i:s');
        }
        $this->save();
    }
    
    /**
     * Начать этап калибровки температуры
     */
    public function startTemperatureCalibration() {
        $this->sessionData['current_stage'] = 'temperature';
        $this->sessionData['temperature_calibration']['status'] = 'in_progress';
        $this->sessionData['temperature_calibration']['current_point'] = '-40';
        $this->save();
    }
    
    /**
     * Обновить данные точки калибровки температуры
     */
    public function updateTemperaturePoint($pointType, $referenceTemp, $writtenTemp) {
        $this->sessionData['temperature_calibration']['points'][$pointType]['reference'] = $referenceTemp;
        $this->sessionData['temperature_calibration']['points'][$pointType]['written'] = $writtenTemp;
        $this->sessionData['temperature_calibration']['points'][$pointType]['status'] = 'completed';
        $this->sessionData['temperature_calibration']['points'][$pointType]['time'] = date('Y-m-d H:i:s');
        $this->save();
    }
    
    /**
     * Установить текущую точку температуры
     */
    public function setCurrentTemperaturePoint($pointType) {
        $this->sessionData['temperature_calibration']['current_point'] = $pointType;
        $this->save();
    }
    
    /**
     * Завершить калибровку температуры и начать поверку
     */
    public function completeTemperatureCalibration() {
        $this->sessionData['temperature_calibration']['status'] = 'completed';
        $this->sessionData['temperature_calibration']['verification']['status'] = 'in_progress';
        $this->save();
    }
    
    /**
     * Добавить результат поверки температуры
     */
    public function addVerificationResult($environment, $reference, $correctorValue, $error, $result) {
        $this->sessionData['temperature_calibration']['verification']['results'][] = [
            'environment' => $environment,
            'reference' => $reference,
            'corrector_value' => $correctorValue,
            'error' => $error,
            'result' => $result,
            'time' => date('Y-m-d H:i:s')
        ];
        $this->save();
    }
    
    /**
     * Начать этап проверки импульсов
     */
    public function startImpulseCheck() {
        $this->sessionData['current_stage'] = 'impulses';
        $this->sessionData['impulses']['status'] = 'in_progress';
        $this->save();
    }
    
    /**
     * Зафиксировать импульс
     */
    public function recordImpulse($impulseNumber) {
        $this->sessionData['impulses']['count'] = $impulseNumber;
        $this->sessionData['impulses']['log'][] = [
            'number' => $impulseNumber,
            'time' => date('Y-m-d H:i:s')
        ];
        
        if ($impulseNumber >= $this->sessionData['impulses']['required']) {
            $this->sessionData['impulses']['status'] = 'completed';
        }
        
        $this->save();
    }
    
    /**
     * Начать этап проверки давления
     */
    public function startPressureCheck($sensorType, $rangeMin, $rangeMax) {
        $this->sessionData['current_stage'] = 'pressure';
        $this->sessionData['pressure']['status'] = 'in_progress';
        $this->sessionData['pressure']['sensor_type'] = $sensorType;
        
        // Генерация точек поверки
        $range = $rangeMax - $rangeMin;
        if ($sensorType === 'absolute') {
            $percentages = [0, 25, 50, 75, 100];
        } else {
            $percentages = [0, 50, 100];
        }
        
        $checkpoints = [];
        foreach ($percentages as $index => $pct) {
            $pressure = $rangeMin + ($range * $pct / 100);
            $checkpoints[] = [
                'index' => $index + 1,
                'percentage' => $pct,
                'pressure' => $pressure,
                'status' => 'pending'
            ];
        }
        
        $this->sessionData['pressure']['checkpoints'] = $checkpoints;
        $this->save();
    }
    
    /**
     * Обновить результат точки давления
     */
    public function updatePressureCheckpoint($index, $setPressure, $measuredPressure, $error, $result) {
        $checkpointIndex = $index - 1;
        
        $this->sessionData['pressure']['checkpoints'][$checkpointIndex]['set_pressure'] = $setPressure;
        $this->sessionData['pressure']['checkpoints'][$checkpointIndex]['measured_pressure'] = $measuredPressure;
        $this->sessionData['pressure']['checkpoints'][$checkpointIndex]['error'] = $error;
        $this->sessionData['pressure']['checkpoints'][$checkpointIndex]['status'] = $result ? 'completed' : 'error';
        $this->sessionData['pressure']['checkpoints'][$checkpointIndex]['time'] = date('Y-m-d H:i:s');
        
        $this->sessionData['pressure']['results'][] = [
            'checkpoint_index' => $index,
            'set_pressure' => $setPressure,
            'measured_pressure' => $measuredPressure,
            'error' => $error,
            'result' => $result,
            'time' => date('Y-m-d H:i:s')
        ];
        
        // Проверка на ошибки
        if (!$result) {
            $this->sessionData['pressure']['failed_attempts']++;
            
            // Если 3 неудачи на одной точке - брак
            if ($this->sessionData['pressure']['failed_attempts'] >= 3) {
                $this->sessionData['pressure']['status'] = 'failed';
            }
        }
        
        // Проверка завершения всех точек
        $allCompleted = true;
        $hasError = false;
        foreach ($this->sessionData['pressure']['checkpoints'] as $cp) {
            if ($cp['status'] === 'pending') {
                $allCompleted = false;
            }
            if ($cp['status'] === 'error') {
                $hasError = true;
            }
        }
        
        if ($allCompleted && !$hasError) {
            $this->sessionData['pressure']['status'] = 'completed';
        }
        
        $this->save();
    }
    
    /**
     * Сбросить попытку для точки давления
     */
    public function retryPressureCheckpoint($index) {
        $checkpointIndex = $index - 1;
        $this->sessionData['pressure']['checkpoints'][$checkpointIndex]['status'] = 'pending';
        $this->save();
    }
    
    /**
     * Завершить калибровку
     */
    public function completeCalibration($operatorId, $correctorSerial) {
        $this->sessionData['completed'] = true;
        $this->sessionData['completed_at'] = date('Y-m-d H:i:s');
        $this->sessionData['operator_id'] = $operatorId;
        $this->sessionData['corrector_serial'] = $correctorSerial;
        $this->sessionData['current_stage'] = 'report';
        $this->save();
    }
    
    /**
     * Получить все данные сессии
     */
    public function getData() {
        return $this->sessionData;
    }
    
    /**
     * Сохранить данные в сессии
     */
    private function save() {
        $_SESSION['calibration'] = $this->sessionData;
    }
    
    /**
     * Сбросить сессию
     */
    public function reset() {
        $_SESSION['calibration'] = $this->getDefaultSessionData();
        $this->sessionData = &$_SESSION['calibration'];
    }
    
    /**
     * Получить логи действий
     */
    public function getActionLog() {
        $log = [];
        
        // Добавляем логи из разных этапов
        if (!empty($this->sessionData['temperature_calibration']['points'])) {
            foreach ($this->sessionData['temperature_calibration']['points'] as $point => $data) {
                if ($data['time']) {
                    $log[] = [
                        'action' => "Калибровка точки {$point}",
                        'time' => $data['time'],
                        'details' => "Эталон: {$data['reference']}°C, Записано: {$data['written']}°C"
                    ];
                }
            }
        }
        
        if (!empty($this->sessionData['impulses']['log'])) {
            foreach ($this->sessionData['impulses']['log'] as $impulse) {
                $log[] = [
                    'action' => "Импульс #{$impulse['number']}",
                    'time' => $impulse['time'],
                    'details' => ''
                ];
            }
        }
        
        if (!empty($this->sessionData['pressure']['results'])) {
            foreach ($this->sessionData['pressure']['results'] as $result) {
                $log[] = [
                    'action' => "Проверка давления точка {$result['checkpoint_index']}",
                    'time' => $result['time'],
                    'details' => "Установлено: {$result['set_pressure']} кПа, Измерено: {$result['measured_pressure']} кПа, Погрешность: {$result['error']} кПа"
                ];
            }
        }
        
        // Сортировка по времени
        usort($log, function($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });
        
        return $log;
    }
}
