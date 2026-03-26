<?php
/**
 * API для взаимодействия с оборудованием и управления калибровкой
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/drivers.php';
require_once __DIR__ . '/../includes/calibration_manager.php';

header('Content-Type: application/json');

$manager = new CalibrationManager();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save_settings':
            // Сохранение настроек оборудования
            $settings = json_decode(file_get_contents('php://input'), true);
            $manager->saveSettings($settings);
            echo json_encode(['success' => true, 'message' => 'Настройки сохранены']);
            break;
            
        case 'test_connection':
            // Проверка связи с устройствами
            $settings = json_decode(file_get_contents('php://input'), true);
            $results = [
                'corrector' => false,
                'mit8_channels' => [],
                'pressure_calibrator' => false
            ];
            
            // Тест корректора
            if (!empty($settings['corrector']['port'])) {
                $corrector = new CorrectorDriver($settings['corrector']['port']);
                $results['corrector'] = $corrector->testConnection();
                $corrector->disconnect();
            }
            
            // Тест МИТ-8 каналов
            foreach (['channel1', 'channel2', 'channel3'] as $channel) {
                if (!empty($settings['mit8'][$channel]['port'])) {
                    $mit8 = new MIT8Driver($settings['mit8'][$channel]['port']);
                    $results['mit8_channels'][$channel] = $mit8->testConnection();
                    $mit8->disconnect();
                }
            }
            
            // Тест калибратора давления
            if (!empty($settings['pressure_calibrator']['port'])) {
                $pressureCal = new PressureCalibratorDriver(
                    $settings['pressure_calibrator']['port'],
                    $settings['pressure_calibrator']['type'] ?? 'absolute',
                    $settings['pressure_calibrator']['range_min'] ?? 0,
                    $settings['pressure_calibrator']['range_max'] ?? 1000
                );
                $results['pressure_calibrator'] = $pressureCal->testConnection();
                $pressureCal->disconnect();
            }
            
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'start_calibration':
            // Начало калибровки
            $manager->setDevicesConnected(true);
            $manager->startTemperatureCalibration();
            echo json_encode(['success' => true, 'message' => 'Калибровка начата']);
            break;
            
        case 'get_temperature_status':
            // Получение статуса калибровки температуры
            $data = $manager->getData();
            echo json_encode([
                'success' => true,
                'current_point' => $data['temperature_calibration']['current_point'],
                'points' => $data['temperature_calibration']['points'],
                'verification' => $data['temperature_calibration']['verification']
            ]);
            break;
            
        case 'read_temperature':
            // Чтение температуры с МИТ-8
            $settings = $manager->getData()['settings'];
            $pointType = $_GET['point'] ?? '-40';
            
            // Определение канала по точке
            $channelMap = [
                '-40' => 'channel1',
                '+10' => 'channel2',
                '+60' => 'channel3'
            ];
            
            $channel = $channelMap[$pointType] ?? 'channel1';
            $port = $settings['mit8'][$channel]['port'] ?? '';
            
            if ($port) {
                $mit8 = new MIT8Driver($port);
                if ($mit8->connect()) {
                    $temp = $mit8->readTemperature(1);
                    $mit8->disconnect();
                    echo json_encode(['success' => true, 'temperature' => $temp]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Не удалось подключиться к МИТ-8']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Порт МИТ-8 не настроен']);
            }
            break;
            
        case 'write_temperature_point':
            // Запись точки калибровки температуры в корректор
            $data = json_decode(file_get_contents('php://input'), true);
            $pointType = $data['point'] ?? '-40';
            $referenceTemp = $data['reference_temp'] ?? 0;
            
            $settings = $manager->getData()['settings'];
            $port = $settings['corrector']['port'] ?? '';
            
            if ($port) {
                $corrector = new CorrectorDriver($port);
                if ($corrector->connect()) {
                    $success = $corrector->writeCalibrationPoint($referenceTemp, $pointType);
                    
                    // Читаем записанное значение
                    $writtenTemp = $corrector->readTemperature();
                    $corrector->disconnect();
                    
                    if ($success) {
                        $manager->updateTemperaturePoint($pointType, $referenceTemp, $writtenTemp);
                        echo json_encode(['success' => true, 'written_temp' => $writtenTemp]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Ошибка записи точки']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Не удалось подключиться к корректору']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Порт корректора не настроен']);
            }
            break;
            
        case 'next_temperature_point':
            // Переход к следующей точке температуры
            $data = json_decode(file_get_contents('php://input'), true);
            $currentPoint = $data['current_point'] ?? '-40';
            
            $nextPoints = [
                '-40' => '+60',
                '+60' => '+10'
            ];
            
            $nextPoint = $nextPoints[$currentPoint] ?? null;
            
            if ($nextPoint) {
                $manager->setCurrentTemperaturePoint($nextPoint);
                echo json_encode(['success' => true, 'next_point' => $nextPoint]);
            } else {
                // Все точки пройдены, начинаем поверку
                $manager->completeTemperatureCalibration();
                echo json_encode(['success' => true, 'verification_started' => true]);
            }
            break;
            
        case 'verify_temperature':
            // Поверка температуры
            $data = json_decode(file_get_contents('php://input'), true);
            $environment = $data['environment'] ?? '-40';
            
            $settings = $manager->getData()['settings'];
            
            // Чтение с МИТ-8 (эталон)
            $channelMap = [
                '-40' => 'channel1',
                '+10' => 'channel2',
                '+60' => 'channel3'
            ];
            
            $channel = $channelMap[$environment] ?? 'channel1';
            $mit8Port = $settings['mit8'][$channel]['port'] ?? '';
            $correctorPort = $settings['corrector']['port'] ?? '';
            
            $referenceTemp = null;
            $correctorTemp = null;
            
            if ($mit8Port) {
                $mit8 = new MIT8Driver($mit8Port);
                if ($mit8->connect()) {
                    $referenceTemp = $mit8->readTemperature(1);
                    $mit8->disconnect();
                }
            }
            
            if ($correctorPort) {
                $corrector = new CorrectorDriver($correctorPort);
                if ($corrector->connect()) {
                    $correctorTemp = $corrector->readTemperature();
                    $corrector->disconnect();
                }
            }
            
            if ($referenceTemp !== null && $correctorTemp !== null) {
                $error = abs($correctorTemp - $referenceTemp);
                $result = $error <= TEMP_TOLERANCE ? 'pass' : 'fail';
                
                $manager->addVerificationResult($environment, $referenceTemp, $correctorTemp, $error, $result);
                
                echo json_encode([
                    'success' => true,
                    'reference' => $referenceTemp,
                    'corrector' => $correctorTemp,
                    'error' => $error,
                    'result' => $result
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось считать показания']);
            }
            break;
            
        case 'start_impulses':
            // Начало проверки импульсов
            $manager->startImpulseCheck();
            echo json_encode(['success' => true, 'message' => 'Проверка импульсов начата']);
            break;
            
        case 'record_impulse':
            // Фиксация импульса
            $data = json_decode(file_get_contents('php://input'), true);
            $impulseNumber = $data['number'] ?? 1;
            
            $manager->recordImpulse($impulseNumber);
            
            $calibData = $manager->getData();
            echo json_encode([
                'success' => true,
                'count' => $impulseNumber,
                'required' => $calibData['impulses']['required'],
                'completed' => $impulseNumber >= $calibData['impulses']['required']
            ]);
            break;
            
        case 'get_impulse_status':
            // Статус проверки импульсов
            $data = $manager->getData();
            echo json_encode([
                'success' => true,
                'count' => $data['impulses']['count'],
                'required' => $data['impulses']['required'],
                'status' => $data['impulses']['status']
            ]);
            break;
            
        case 'start_pressure_check':
            // Начало проверки давления
            $data = json_decode(file_get_contents('php://input'), true);
            $sensorType = $data['sensor_type'] ?? 'absolute';
            $rangeMin = $data['range_min'] ?? 0;
            $rangeMax = $data['range_max'] ?? 1000;
            
            $manager->startPressureCheck($sensorType, $rangeMin, $rangeMax);
            echo json_encode(['success' => true, 'message' => 'Проверка давления начата']);
            break;
            
        case 'get_pressure_status':
            // Статус проверки давления
            $data = $manager->getData();
            echo json_encode([
                'success' => true,
                'checkpoints' => $data['pressure']['checkpoints'],
                'status' => $data['pressure']['status'],
                'sensor_type' => $data['pressure']['sensor_type']
            ]);
            break;
            
        case 'read_corrector_pressure':
            // Чтение давления с корректора
            $settings = $manager->getData()['settings'];
            $port = $settings['corrector']['port'] ?? '';
            
            if ($port) {
                $corrector = new CorrectorDriver($port);
                if ($corrector->connect()) {
                    $pressure = $corrector->readPressure();
                    $corrector->disconnect();
                    echo json_encode(['success' => true, 'pressure' => $pressure]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Не удалось подключиться к корректору']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Порт корректора не настроен']);
            }
            break;
            
        case 'submit_pressure_checkpoint':
            // Подтверждение точки давления
            $data = json_decode(file_get_contents('php://input'), true);
            $index = $data['index'] ?? 1;
            $setPressure = $data['set_pressure'] ?? 0;
            $measuredPressure = $data['measured_pressure'] ?? 0;
            
            $tolerance = ($setPressure > 0) ? (PRESSURE_TOLERANCE_PERCENT / 100 * $setPressure) : 1.0;
            $error = abs($measuredPressure - $setPressure);
            $result = $error <= $tolerance;
            
            $manager->updatePressureCheckpoint($index, $setPressure, $measuredPressure, $error, $result);
            
            $calibData = $manager->getData();
            echo json_encode([
                'success' => true,
                'result' => $result ? 'pass' : 'fail',
                'error' => $error,
                'tolerance' => $tolerance,
                'all_completed' => $calibData['pressure']['status'] === 'completed'
            ]);
            break;
            
        case 'retry_pressure_checkpoint':
            // Повтор точки давления
            $data = json_decode(file_get_contents('php://input'), true);
            $index = $data['index'] ?? 1;
            
            $manager->retryPressureCheckpoint($index);
            echo json_encode(['success' => true, 'message' => 'Точка сброшена для повторной проверки']);
            break;
            
        case 'complete_calibration':
            // Завершение калибровки
            $data = json_decode(file_get_contents('php://input'), true);
            $operatorId = $data['operator_id'] ?? 'Unknown';
            $correctorSerial = $data['corrector_serial'] ?? 'Unknown';
            
            $manager->completeCalibration($operatorId, $correctorSerial);
            echo json_encode(['success' => true, 'message' => 'Калибровка завершена']);
            break;
            
        case 'get_full_data':
            // Получение всех данных для отчета
            $data = $manager->getData();
            $log = $manager->getActionLog();
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'log' => $log
            ]);
            break;
            
        case 'reset':
            // Сброс калибровки
            $manager->reset();
            echo json_encode(['success' => true, 'message' => 'Сессия сброшена']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
