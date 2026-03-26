<?php
/**
 * Main API Entry Point
 * Handles all AJAX requests for calibration system
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/SessionManager.php';
require_once __DIR__ . '/../includes/SerialPortHandler.php';

SessionManager::start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {
        case 'test_device_connection':
            $response = testDeviceConnection();
            break;
        
        case 'save_device_config':
            $response = saveDeviceConfig();
            break;
        
        case 'get_device_config':
            $response = getDeviceConfig();
            break;
        
        case 'start_calibration_session':
            $response = startCalibrationSession();
            break;
        
        case 'get_session_status':
            $response = getSessionStatus();
            break;
        
        case 'read_temperature':
            $response = readTemperature();
            break;
        
        case 'write_temperature_point':
            $response = writeTemperaturePoint();
            break;
        
        case 'verify_temperature':
            $response = verifyTemperature();
            break;
        
        case 'record_impulse':
            $response = recordImpulse();
            break;
        
        case 'get_impulse_status':
            $response = getImpulseStatus();
            break;
        
        case 'set_pressure_point':
            $response = setPressurePoint();
            break;
        
        case 'read_pressure':
            $response = readPressure();
            break;
        
        case 'complete_calibration':
            $response = completeCalibration();
            break;
        
        case 'generate_report':
            $response = generateReport();
            break;
        
        case 'get_session_logs':
            $response = getSessionLogs();
            break;
        
        default:
            $response = ['success' => false, 'message' => 'Unknown action: ' . $action];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    error_log("API Error: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * Test device connection
 */
function testDeviceConnection() {
    $deviceType = $_POST['device_type'] ?? '';
    $comPort = $_POST['com_port'] ?? '';
    $baudRate = intval($_POST['baud_rate'] ?? 19200);
    
    if (empty($deviceType) || empty($comPort)) {
        return ['success' => false, 'message' => 'Device type and COM port required'];
    }
    
    $serial = new SerialPortHandler();
    $connected = $serial->connect($comPort, $baudRate);
    
    if (!$connected) {
        return ['success' => false, 'message' => 'Failed to connect to ' . $comPort];
    }
    
    $result = $serial->testConnection($deviceType);
    $serial->disconnect();
    
    // Save test result to database
    if ($result['success']) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE devices_config SET last_tested_at = NOW(), test_status = 'success' WHERE device_type = ? AND com_port = ?");
        $stmt->execute([$deviceType, $comPort]);
    }
    
    return $result;
}

/**
 * Save device configuration
 */
function saveDeviceConfig() {
    $db = Database::getInstance()->getConnection();
    
    $deviceType = $_POST['device_type'] ?? '';
    $comPort = $_POST['com_port'] ?? '';
    $baudRate = intval($_POST['baud_rate'] ?? 19200);
    $parity = $_POST['parity'] ?? 'None';
    $channelId = $_POST['channel_id'] ?? null;
    $temperatureSetpoint = $_POST['temperature_setpoint'] ?? null;
    $pressureType = $_POST['pressure_type'] ?? null;
    $pressureRangeMin = $_POST['pressure_range_min'] ?? 0;
    $pressureRangeMax = $_POST['pressure_range_max'] ?? 1000;
    
    if (empty($deviceType) || empty($comPort)) {
        return ['success' => false, 'message' => 'Device type and COM port required'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO devices_config 
        (device_type, com_port, baud_rate, parity, channel_id, temperature_setpoint, pressure_type, pressure_range_min, pressure_range_max)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        baud_rate = VALUES(baud_rate),
        parity = VALUES(parity),
        channel_id = VALUES(channel_id),
        temperature_setpoint = VALUES(temperature_setpoint),
        pressure_type = VALUES(pressure_type),
        pressure_range_min = VALUES(pressure_range_min),
        pressure_range_max = VALUES(pressure_range_max),
        updated_at = NOW()
    ");
    
    $stmt->execute([
        $deviceType, $comPort, $baudRate, $parity, 
        $channelId, $temperatureSetpoint, $pressureType,
        $pressureRangeMin, $pressureRangeMax
    ]);
    
    return [
        'success' => true,
        'message' => 'Configuration saved successfully',
        'device_id' => $db->lastInsertId()
    ];
}

/**
 * Get device configuration
 */
function getDeviceConfig() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM devices_config ORDER BY device_type, channel_id");
    $devices = $stmt->fetchAll();
    
    return [
        'success' => true,
        'devices' => $devices
    ];
}

/**
 * Start new calibration session
 */
function startCalibrationSession() {
    SessionManager::regenerate();
    
    $db = Database::getInstance()->getConnection();
    $operatorId = SessionManager::getUserId() ?? 1; // Default to first user if not logged in
    $correctorSerial = $_POST['corrector_serial'] ?? '';
    $correctorModel = $_POST['corrector_model'] ?? '';
    $pressureType = $_POST['pressure_type'] ?? 'absolute';
    
    if (empty($correctorSerial)) {
        return ['success' => false, 'message' => 'Corrector serial number required'];
    }
    
    $sessionUuid = uniqid('CAL-', true);
    
    $stmt = $db->prepare("
        INSERT INTO calibration_sessions 
        (session_uuid, operator_id, corrector_serial_number, corrector_model, status)
        VALUES (?, ?, ?, ?, 'in_progress')
    ");
    $stmt->execute([$sessionUuid, $operatorId, $correctorSerial, $correctorModel]);
    
    $sessionId = $db->lastInsertId();
    SessionManager::set('calibration_session_id', $sessionId);
    
    // Initialize temperature points
    $tempPoints = [-40, 60, 10]; // Order: negative, positive, middle
    foreach ($tempPoints as $index => $temp) {
        $stmt = $db->prepare("
            INSERT INTO temperature_points (session_id, point_order, setpoint_temperature, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$sessionId, $index + 1, $temp]);
    }
    
    // Initialize pressure points based on type
    $pressureRange = floatval($_POST['pressure_range_max'] ?? 1000);
    if ($pressureType === 'absolute') {
        $points = [0, 25, 50, 75, 100]; // 5 points
    } else {
        $points = [0, 50, 100]; // 3 points for differential
    }
    
    foreach ($points as $index => $percent) {
        $targetPressure = ($pressureRange * $percent) / 100;
        $stmt = $db->prepare("
            INSERT INTO pressure_points (session_id, point_order, target_pressure, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$sessionId, $index + 1, $targetPressure]);
    }
    
    // Log session start
    logSessionAction($sessionId, 'info', 'Calibration session started', null, $correctorSerial);
    
    return [
        'success' => true,
        'message' => 'Calibration session started',
        'session_id' => $sessionId,
        'session_uuid' => $sessionUuid
    ];
}

/**
 * Get current session status
 */
function getSessionStatus() {
    $sessionId = SessionManager::getCurrentSessionId();
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Get session info
    $stmt = $db->prepare("SELECT * FROM calibration_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        return ['success' => false, 'message' => 'Session not found'];
    }
    
    // Get temperature points
    $stmt = $db->prepare("SELECT * FROM temperature_points WHERE session_id = ? ORDER BY point_order");
    $stmt->execute([$sessionId]);
    $tempPoints = $stmt->fetchAll();
    
    // Get impulse status
    $stmt = $db->prepare("SELECT COUNT(*) as count, SUM(CASE WHEN is_success THEN 1 ELSE 0 END) as success_count FROM impulse_checks WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $impulseStats = $stmt->fetch();
    
    // Get pressure points
    $stmt = $db->prepare("SELECT * FROM pressure_points WHERE session_id = ? ORDER BY point_order");
    $stmt->execute([$sessionId]);
    $pressurePoints = $stmt->fetchAll();
    
    // Get temperature verification results
    $stmt = $db->prepare("SELECT * FROM temperature_verification WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $tempVerification = $stmt->fetchAll();
    
    return [
        'success' => true,
        'session' => $session,
        'temperature_points' => $tempPoints,
        'impulse_stats' => $impulseStats,
        'pressure_points' => $pressurePoints,
        'temperature_verification' => $tempVerification
    ];
}

/**
 * Read temperature from MIT-8
 */
function readTemperature() {
    $channel = intval($_POST['channel'] ?? 1);
    $sessionId = SessionManager::getCurrentSessionId();
    
    // In demo mode, simulate reading
    $baseTemps = [1 => -40, 2 => 60, 3 => 10];
    $baseTemp = $baseTemps[$channel] ?? 20;
    $variation = (rand(-5, 5) / 10);
    $temperature = $baseTemp + $variation;
    
    // Update database
    if ($sessionId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE temperature_points 
            SET mit8_reading = ?, status = 'stabilizing'
            WHERE session_id = ? AND point_order = ?
        ");
        $stmt->execute([$temperature, $sessionId, $channel]);
    }
    
    return [
        'success' => true,
        'temperature' => round($temperature, 2),
        'channel' => $channel,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Write temperature calibration point to corrector
 */
function writeTemperaturePoint() {
    $sessionId = SessionManager::getCurrentSessionId();
    $pointOrder = intval($_POST['point_order'] ?? 1);
    $temperature = floatval($_POST['temperature'] ?? 0);
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    // Simulate writing to corrector
    $serial = new SerialPortHandler();
    $success = $serial->writeCorrectorPoint($temperature);
    
    if ($success) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE temperature_points 
            SET corrector_reading = ?, point_written = TRUE, written_at = NOW(), status = 'completed'
            WHERE session_id = ? AND point_order = ?
        ");
        $stmt->execute([$temperature, $sessionId, $pointOrder]);
        
        logSessionAction($sessionId, 'action', 'Temperature point written', null, "{$temperature}°C (point {$pointOrder})");
    }
    
    return [
        'success' => $success,
        'message' => $success ? 'Temperature point written successfully' : 'Failed to write temperature point'
    ];
}

/**
 * Verify temperature readings
 */
function verifyTemperature() {
    $sessionId = SessionManager::getCurrentSessionId();
    $temperature = floatval($_POST['temperature'] ?? 0);
    $mit8Reading = floatval($_POST['mit8_reading'] ?? 0);
    $tolerance = floatval($_POST['tolerance'] ?? 0.5);
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $error = abs($mit8Reading - $temperature);
    $isAcceptable = $error <= $tolerance;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO temperature_verification 
        (session_id, temperature_point, mit8_etalon, corrector_value, error_value, tolerance_limit, is_acceptable)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId, $temperature, $mit8Reading, $temperature, 
        $error, $tolerance, $isAcceptable
    ]);
    
    logSessionAction(
        $sessionId, 
        $isAcceptable ? 'info' : 'warning', 
        'Temperature verification', 
        null, 
        "Point: {$temperature}°C, Error: {$error}°C, Acceptable: " . ($isAcceptable ? 'Yes' : 'No')
    );
    
    return [
        'success' => true,
        'error' => round($error, 2),
        'is_acceptable' => $isAcceptable,
        'tolerance' => $tolerance
    ];
}

/**
 * Record impulse
 */
function recordImpulse() {
    $sessionId = SessionManager::getCurrentSessionId();
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Get current impulse count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM impulse_checks WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch();
    $currentCount = intval($result['count']);
    
    if ($currentCount >= 20) {
        return ['success' => false, 'message' => 'All 20 impulses already recorded'];
    }
    
    $nextImpulse = $currentCount + 1;
    
    $stmt = $db->prepare("
        INSERT INTO impulse_checks (session_id, impulse_number, is_success)
        VALUES (?, ?, TRUE)
    ");
    $stmt->execute([$sessionId, $nextImpulse]);
    
    logSessionAction($sessionId, 'action', 'Impulse recorded', null, "Impulse #{$nextImpulse}");
    
    return [
        'success' => true,
        'impulse_number' => $nextImpulse,
        'total' => 20,
        'remaining' => 20 - $nextImpulse
    ];
}

/**
 * Get impulse status
 */
function getImpulseStatus() {
    $sessionId = SessionManager::getCurrentSessionId();
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    ];
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT impulse_number, is_success, recorded_at 
        FROM impulse_checks 
        WHERE session_id = ? 
        ORDER BY impulse_number
    ");
    $stmt->execute([$sessionId]);
    $impulses = $stmt->fetchAll();
    
    return [
        'success' => true,
        'impulses' => $impulses,
        'count' => count($impulses),
        'total_required' => 20
    ];
}

/**
 * Set pressure point target
 */
function setPressurePoint() {
    $sessionId = SessionManager::getCurrentSessionId();
    $pointOrder = intval($_POST['point_order'] ?? 1);
    $targetPressure = floatval($_POST['target_pressure'] ?? 0);
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE pressure_points 
        SET target_pressure = ?, attempts_count = attempts_count + 1
        WHERE session_id = ? AND point_order = ?
    ");
    $stmt->execute([$targetPressure, $sessionId, $pointOrder]);
    
    return ['success' => true, 'message' => 'Pressure point set'];
}

/**
 * Read pressure from corrector
 */
function readPressure() {
    $sessionId = SessionManager::getCurrentSessionId();
    $pointOrder = intval($_POST['point_order'] ?? 1);
    $targetPressure = floatval($_POST['target_pressure'] ?? 0);
    $tolerance = floatval($_POST['tolerance'] ?? 1.0);
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    // Simulate reading from corrector
    $variation = (rand(-10, 10) / 10);
    $correctorReading = $targetPressure + $variation;
    $error = abs($correctorReading - $targetPressure);
    $isAcceptable = $error <= $tolerance;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE pressure_points 
        SET corrector_reading = ?, error_value = ?, is_acceptable = ?, 
            status = ?, checked_at = NOW()
        WHERE session_id = ? AND point_order = ?
    ");
    
    $newStatus = $isAcceptable ? 'completed' : 'failed';
    $stmt->execute([
        $correctorReading, $error, $isAcceptable, 
        $newStatus, $sessionId, $pointOrder
    ]);
    
    logSessionAction(
        $sessionId,
        $isAcceptable ? 'info' : 'warning',
        'Pressure point checked',
        null,
        "Point {$pointOrder}: Target={$targetPressure}, Reading={$correctorReading}, Error={$error}"
    );
    
    return [
        'success' => true,
        'corrector_reading' => round($correctorReading, 2),
        'error' => round($error, 2),
        'is_acceptable' => $isAcceptable,
        'status' => $newStatus
    ];
}

/**
 * Complete calibration session
 */
function completeCalibration() {
    $sessionId = SessionManager::getCurrentSessionId();
    $finalResult = $_POST['final_result'] ?? 'pending';
    $notes = $_POST['notes'] ?? '';
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE calibration_sessions 
        SET status = 'completed', completed_at = NOW(), final_result = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$finalResult, $notes, $sessionId]);
    
    logSessionAction($sessionId, 'info', 'Calibration completed', null, "Result: {$finalResult}");
    
    return [
        'success' => true,
        'message' => 'Calibration session completed',
        'session_id' => $sessionId
    ];
}

/**
 * Generate report
 */
function generateReport() {
    $sessionId = SessionManager::getCurrentSessionId();
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Get all session data
    $stmt = $db->prepare("SELECT * FROM calibration_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT * FROM temperature_points WHERE session_id = ? ORDER BY point_order");
    $stmt->execute([$sessionId]);
    $tempPoints = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM temperature_verification WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $tempVerification = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM impulse_checks WHERE session_id = ? ORDER BY impulse_number");
    $stmt->execute([$sessionId]);
    $impulses = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT * FROM pressure_points WHERE session_id = ? ORDER BY point_order");
    $stmt->execute([$sessionId]);
    $pressurePoints = $stmt->fetchAll();
    
    $reportData = [
        'session' => $session,
        'temperature_points' => $tempPoints,
        'temperature_verification' => $tempVerification,
        'impulses' => $impulses,
        'pressure_points' => $pressurePoints
    ];
    
    // Save report to database
    $stmt = $db->prepare("
        INSERT INTO reports (session_id, generated_at)
        VALUES (?, NOW())
        ON DUPLICATE KEY UPDATE generated_at = NOW()
    ");
    $stmt->execute([$sessionId]);
    
    return [
        'success' => true,
        'message' => 'Report generated',
        'data' => $reportData
    ];
}

/**
 * Get session logs
 */
function getSessionLogs() {
    $sessionId = SessionManager::getCurrentSessionId();
    
    if (!$sessionId) {
        return ['success' => false, 'message' => 'No active session'];
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT * FROM session_logs 
        WHERE session_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$sessionId]);
    $logs = $stmt->fetchAll();
    
    return [
        'success' => true,
        'logs' => $logs
    ];
}

/**
 * Log session action
 */
function logSessionAction($sessionId, $logType, $description, $oldValue = null, $newValue = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO session_logs (session_id, log_type, action_description, old_value, new_value)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$sessionId, $logType, $description, $oldValue, $newValue]);
    } catch (Exception $e) {
        error_log("Failed to log session action: " . $e->getMessage());
    }
}
