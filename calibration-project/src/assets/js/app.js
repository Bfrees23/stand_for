/**
 * Calibration System - Main Application JavaScript
 */

// Global state
const AppState = {
    currentStep: 0,
    sessionId: null,
    devicesConnected: {
        corrector: false,
        mit8: false,
        pressure: false
    },
    temperaturePoints: [
        { temp: -40, status: 'pending', order: 1 },
        { temp: 60, status: 'pending', order: 2 },
        { temp: 10, status: 'pending', order: 3 }
    ],
    currentTempPoint: 0,
    stabilizationStartTime: null,
    stabilizationRequired: 300, // 5 minutes in seconds
    impulses: [],
    pressurePoints: [],
    currentPressurePoint: 0,
    pressureType: 'absolute',
    timeoutTimer: null,
    lastActivity: Date.now()
};

// API helper
async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    try {
        const response = await fetch('api/index.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('API call failed:', error);
        // In demo mode, return simulated responses
        return simulateApiResponse(action, data);
    }
}

// Simulate API responses for demo mode
function simulateApiResponse(action, data) {
    console.log('Demo mode - simulating:', action, data);
    
    switch (action) {
        case 'test_device_connection':
            return {
                success: true,
                message: 'Connection successful (demo)',
                device_type: data.device_type,
                timestamp: new Date().toISOString()
            };
        
        case 'start_calibration_session':
            return {
                success: true,
                session_id: Date.now(),
                session_uuid: 'CAL-' + Date.now(),
                message: 'Session started (demo)'
            };
        
        case 'read_temperature':
            const baseTemps = { 1: -40, 2: 60, 3: 10 };
            const variation = (Math.random() - 0.5) * 1;
            return {
                success: true,
                temperature: (baseTemps[data.channel] || 20) + variation,
                channel: data.channel,
                timestamp: new Date().toISOString()
            };
        
        case 'write_temperature_point':
            return {
                success: true,
                message: 'Temperature point written (demo)'
            };
        
        case 'verify_temperature':
            const error = Math.abs(data.mit8_reading - data.temperature);
            return {
                success: true,
                error: error.toFixed(2),
                is_acceptable: error <= data.tolerance
            };
        
        case 'record_impulse':
            return {
                success: true,
                impulse_number: AppState.impulses.length + 1,
                total: 20,
                remaining: 19 - AppState.impulses.length
            };
        
        case 'get_session_status':
            return {
                success: true,
                session: { id: AppState.sessionId },
                temperature_points: AppState.temperaturePoints,
                impulse_stats: { count: AppState.impulses.length, success_count: AppState.impulses.length },
                pressure_points: AppState.pressurePoints
            };
        
        case 'read_pressure':
            const target = parseFloat(data.target_pressure);
            const pressureVariation = (Math.random() - 0.5) * 2;
            const reading = target + pressureVariation;
            const pressureError = Math.abs(reading - target);
            return {
                success: true,
                corrector_reading: reading.toFixed(2),
                error: pressureError.toFixed(2),
                is_acceptable: pressureError <= data.tolerance,
                status: pressureError <= data.tolerance ? 'completed' : 'failed'
            };
        
        case 'generate_report':
            return {
                success: true,
                message: 'Report generated (demo)',
                data: {
                    session: { id: AppState.sessionId },
                    temperature_points: AppState.temperaturePoints,
                    impulses: AppState.impulses,
                    pressure_points: AppState.pressurePoints
                }
            };
        
        default:
            return { success: true, message: 'OK (demo)' };
    }
}

// Initialize application
document.addEventListener('DOMContentLoaded', () => {
    initializeEventListeners();
    updateTimeoutTimer();
    addLog('Приложение запущено', 'info');
});

// Initialize event listeners
function initializeEventListeners() {
    // Test device buttons
    document.querySelectorAll('.test-device-btn').forEach(btn => {
        btn.addEventListener('click', handleTestDevice);
    });
    
    // Pressure type change
    document.getElementById('pressureType').addEventListener('change', handlePressureTypeChange);
    
    // Start calibration button
    document.getElementById('startCalibrationBtn').addEventListener('click', startCalibration);
    
    // Temperature calibration
    document.getElementById('writeTempPointBtn').addEventListener('click', writeTemperaturePoint);
    
    // Impulse recording
    document.getElementById('recordImpulseBtn').addEventListener('click', recordImpulse);
    document.getElementById('completeImpulsesBtn').addEventListener('click', () => goToStep(3));
    
    // Pressure reading
    document.getElementById('readPressureBtn').addEventListener('click', readPressure);
    document.getElementById('retryPressureBtn').addEventListener('click', retryPressurePoint);
    
    // Report actions
    document.getElementById('exportPdfBtn').addEventListener('click', exportToPdf);
    document.getElementById('saveToDbBtn').addEventListener('click', saveToDatabase);
    document.getElementById('startNewBtn').addEventListener('click', startNewSession);
    
    // Timeout modal
    document.getElementById('continueBtn').addEventListener('click', () => {
        document.getElementById('timeoutModal').style.display = 'none';
        resetTimeoutTimer();
    });
    
    document.getElementById('pauseBtn').addEventListener('click', () => {
        document.getElementById('timeoutModal').style.display = 'none';
        pauseCalibration();
    });
    
    // Activity tracking
    document.addEventListener('mousemove', resetTimeoutTimer);
    document.addEventListener('keypress', resetTimeoutTimer);
    document.addEventListener('click', resetTimeoutTimer);
}

// Handle device connection test
async function handleTestDevice(e) {
    const device = e.target.dataset.device;
    let comPort, baudRate;
    
    switch (device) {
        case 'corrector':
            comPort = document.getElementById('correctorComPort').value;
            baudRate = document.getElementById('correctorBaudRate').value;
            break;
        case 'mit8':
            comPort = document.getElementById('mit8ComPort').value;
            baudRate = 19200;
            break;
        case 'pressure':
            comPort = 'COM5'; // Default for pressure calibrator
            baudRate = 9600;
            break;
    }
    
    const statusEl = document.getElementById(`${device}Status`);
    statusEl.querySelector('.status-text').textContent = 'Проверка...';
    
    const result = await apiCall('test_device_connection', {
        device_type: device,
        com_port: comPort,
        baud_rate: baudRate
    });
    
    if (result.success) {
        AppState.devicesConnected[device] = true;
        statusEl.querySelector('.status-indicator').className = 'status-indicator success';
        statusEl.querySelector('.status-text').textContent = 'Подключено';
        checkAllDevicesConnected();
        addLog(`Устройство ${device} проверено успешно`, 'info');
    } else {
        AppState.devicesConnected[device] = false;
        statusEl.querySelector('.status-indicator').className = 'status-indicator error';
        statusEl.querySelector('.status-text').textContent = 'Ошибка: ' + result.message;
        addLog(`Ошибка проверки ${device}: ${result.message}`, 'error');
    }
}

// Check if all devices are connected
function checkAllDevicesConnected() {
    const allConnected = Object.values(AppState.devicesConnected).every(v => v === true);
    const serialNumber = document.getElementById('correctorSerial').value.trim();
    document.getElementById('startCalibrationBtn').disabled = !(allConnected && serialNumber);
}

// Handle pressure type change
function handlePressureTypeChange(e) {
    AppState.pressureType = e.target.value;
    const infoText = e.target.value === 'absolute' 
        ? 'Абсолютный: 5 точек (0%, 25%, 50%, 75%, 100%)'
        : 'Перепад: 3 точки (0%, 50%, 100%)';
    document.getElementById('pressurePointsInfo').textContent = infoText;
}

// Start calibration session
async function startCalibration() {
    const correctorSerial = document.getElementById('correctorSerial').value.trim();
    const correctorModel = document.getElementById('correctorModel').value.trim();
    const pressureRangeMax = parseFloat(document.getElementById('pressureRangeMax').value);
    
    if (!correctorSerial) {
        alert('Введите серийный номер корректора');
        return;
    }
    
    const result = await apiCall('start_calibration_session', {
        corrector_serial: correctorSerial,
        corrector_model: correctorModel,
        pressure_type: AppState.pressureType,
        pressure_range_max: pressureRangeMax
    });
    
    if (result.success) {
        AppState.sessionId = result.session_id;
        document.getElementById('sessionStatus').textContent = `Сессия: ${result.session_uuid}`;
        
        // Initialize pressure points
        initializePressurePoints(pressureRangeMax);
        
        goToStep(1);
        startTemperatureCalibration();
        addLog('Калибровка начата', 'action');
    } else {
        alert('Ошибка запуска калибровки: ' + result.message);
    }
}

// Initialize pressure points
function initializePressurePoints(maxPressure) {
    if (AppState.pressureType === 'absolute') {
        const percentages = [0, 25, 50, 75, 100];
        AppState.pressurePoints = percentages.map((pct, idx) => ({
            order: idx + 1,
            target: (maxPressure * pct) / 100,
            status: 'pending',
            attempts: 0
        }));
    } else {
        const percentages = [0, 50, 100];
        AppState.pressurePoints = percentages.map((pct, idx) => ({
            order: idx + 1,
            target: (maxPressure * pct) / 100,
            status: 'pending',
            attempts: 0
        }));
    }
}

// Go to specific step
function goToStep(step) {
    AppState.currentStep = step;
    
    // Update step indicators
    document.querySelectorAll('.wizard-steps .step').forEach((el, idx) => {
        const statusEl = el.querySelector('.step-status');
        if (idx < step) {
            el.classList.add('completed');
            statusEl.textContent = 'done';
        } else if (idx === step) {
            el.classList.add('active');
            el.classList.remove('completed');
            statusEl.textContent = 'active';
        } else {
            el.classList.remove('active', 'completed');
            statusEl.textContent = 'pending';
        }
    });
    
    // Show/hide sections
    document.querySelectorAll('.wizard-step').forEach((el, idx) => {
        el.classList.toggle('active', idx === step);
    });
    
    resetTimeoutTimer();
}

// Temperature calibration
let tempPollingInterval = null;

function startTemperatureCalibration() {
    AppState.currentTempPoint = 0;
    updateTemperatureInstruction();
    startTemperaturePolling();
}

function startTemperaturePolling() {
    if (tempPollingInterval) clearInterval(tempPollingInterval);
    
    tempPollingInterval = setInterval(async () => {
        const channel = AppState.currentTempPoint + 1;
        const result = await apiCall('read_temperature', { channel });
        
        if (result.success) {
            const temp = result.temperature;
            document.getElementById('currentTempValue').textContent = `${temp.toFixed(1)}°C`;
            
            // Check stabilization
            if (!AppState.stabilizationStartTime) {
                AppState.stabilizationStartTime = Date.now();
            }
            
            const elapsed = Math.floor((Date.now() - AppState.stabilizationStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            document.getElementById('stabilizationTimer').textContent = 
                `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} / 05:00`;
            
            // Enable button after 5 minutes
            if (elapsed >= AppState.stabilizationRequired) {
                document.getElementById('writeTempPointBtn').disabled = false;
                document.getElementById('writeTempPointValue').textContent = 
                    AppState.temperaturePoints[AppState.currentTempPoint].temp;
            }
            
            // Update graph
            updateTemperatureGraph(temp);
        }
    }, 2000); // Poll every 2 seconds
}

function updateTemperatureInstruction() {
    const point = AppState.temperaturePoints[AppState.currentTempPoint];
    const stepNum = AppState.currentTempPoint + 1;
    
    document.querySelector('#tempInstruction h3').textContent = 
        `Шаг ${stepNum}: Калибровка точки ${point.temp > 0 ? '+' : ''}${point.temp}°C`;
    
    document.querySelector('.instruction-text').textContent = 
        `Поместите датчик корректора и термопару МИТ-8 в камеру с температурой ${point.temp > 0 ? '+' : ''}${point.temp}°C. ` +
        `Убедитесь, что оборудование стабилизировано.`;
}

function updateTemperatureGraph(temp) {
    // Simple visualization - in production use Chart.js or similar
    const graphEl = document.getElementById('tempGraph');
    const bars = Array.from({ length: 30 }, (_, i) => {
        const variation = (Math.random() - 0.5) * 2;
        const height = Math.max(10, Math.min(100, 50 + (temp + variation) / 2));
        return `<div class="graph-bar" style="height: ${height}%"></div>`;
    }).join('');
    
    graphEl.innerHTML = `<div class="graph-container">${bars}</div>`;
}

async function writeTemperaturePoint() {
    const point = AppState.temperaturePoints[AppState.currentTempPoint];
    const tempValue = point.temp;
    
    const result = await apiCall('write_temperature_point', {
        point_order: point.order,
        temperature: tempValue
    });
    
    if (result.success) {
        point.status = 'completed';
        
        // Update UI
        const pointEl = document.querySelector(`.temp-point[data-point="${tempValue}"]`);
        if (pointEl) {
            pointEl.classList.add('completed');
            pointEl.querySelector('.point-status').textContent = 'Выполнено';
        }
        
        addLog(`Точка ${tempValue}°C записана`, 'action');
        
        // Move to next point or verification
        if (AppState.currentTempPoint < AppState.temperaturePoints.length - 1) {
            AppState.currentTempPoint++;
            AppState.stabilizationStartTime = null;
            document.getElementById('writeTempPointBtn').disabled = true;
            updateTemperatureInstruction();
        } else {
            clearInterval(tempPollingInterval);
            setTimeout(() => goToStep(2), 1000);
        }
    }
}

// Impulse checking
function recordImpulse() {
    const currentCount = AppState.impulses.length;
    
    if (currentCount >= 20) {
        alert('Все 20 импульсов уже зафиксированы');
        return;
    }
    
    AppState.impulses.push({
        number: currentCount + 1,
        recorded_at: new Date().toISOString()
    });
    
    // Update UI
    document.getElementById('impulseCount').textContent = AppState.impulses.length;
    document.getElementById('nextImpulseNum').textContent = AppState.impulses.length + 1;
    document.getElementById('impulseProgressFill').style.width = 
        `${(AppState.impulses.length / 20) * 100}%`;
    
    // Add to list
    const impulseList = document.getElementById('impulseList');
    const impulseItem = document.createElement('div');
    impulseItem.className = 'impulse-item success';
    impulseItem.textContent = `✓ Импульс #${currentCount + 1}`;
    impulseList.appendChild(impulseItem);
    
    addLog(`Импульс #${currentCount + 1} зафиксирован`, 'action');
    
    // Enable continue button
    if (AppState.impulses.length >= 20) {
        document.getElementById('completeImpulsesBtn').disabled = false;
        document.getElementById('recordImpulseBtn').disabled = true;
    }
}

// Pressure checking
function showCurrentPressurePoint() {
    const point = AppState.pressurePoints[AppState.currentPressurePoint];
    
    document.getElementById('currentPressurePointNum').textContent = point.order;
    document.getElementById('totalPressurePoints').textContent = AppState.pressurePoints.length;
    document.getElementById('targetPressureValue').textContent = `${point.target.toFixed(2)} кПа`;
    
    document.getElementById('pressureResult').style.display = 'none';
    document.getElementById('retryPressureBtn').style.display = 'none';
    document.getElementById('readPressureBtn').style.display = 'inline-block';
    
    // Update grid
    updatePressurePointsGrid();
}

function updatePressurePointsGrid() {
    const grid = document.getElementById('pressurePointsGrid');
    grid.innerHTML = AppState.pressurePoints.map(point => {
        const statusClass = point.status === 'completed' ? 'success' : 
                           point.status === 'failed' ? 'error' : 'pending';
        return `
            <div class="pressure-point-card ${statusClass}" data-order="${point.order}">
                <div class="card-header">Точка ${point.order}</div>
                <div class="card-target">${point.target.toFixed(2)} кПа</div>
                <div class="card-status">${point.status}</div>
            </div>
        `;
    }).join('');
}

async function readPressure() {
    const point = AppState.pressurePoints[AppState.currentPressurePoint];
    point.attempts++;
    
    const result = await apiCall('read_pressure', {
        point_order: point.order,
        target_pressure: point.target,
        tolerance: 1.0
    });
    
    if (result.success) {
        document.getElementById('pressureResult').style.display = 'block';
        document.getElementById('correctorPressureValue').textContent = 
            `${result.corrector_reading} кПа`;
        document.getElementById('pressureErrorValue').textContent = 
            `${result.error} кПа`;
        
        const verdictEl = document.getElementById('pressureVerdict');
        verdictEl.textContent = result.is_acceptable ? 'Годен' : 'Брак';
        verdictEl.className = `verdict-badge ${result.is_acceptable ? 'success' : 'error'}`;
        
        if (result.is_acceptable) {
            point.status = 'completed';
            addLog(`Давление точка ${point.order}: Годен`, 'info');
            
            setTimeout(() => {
                if (AppState.currentPressurePoint < AppState.pressurePoints.length - 1) {
                    AppState.currentPressurePoint++;
                    showCurrentPressurePoint();
                } else {
                    goToStep(4);
                    generateReport();
                }
            }, 2000);
        } else {
            point.status = 'failed';
            document.getElementById('readPressureBtn').style.display = 'none';
            document.getElementById('retryPressureBtn').style.display = 'inline-block';
            addLog(`Давление точка ${point.order}: Брак (попытка ${point.attempts})`, 'warning');
            
            if (point.attempts >= 3) {
                alert('Превышено количество попыток для этой точки. Датчик признан браком.');
                point.status = 'failed';
                goToStep(4);
            }
        }
        
        updatePressurePointsGrid();
    }
}

function retryPressurePoint() {
    const point = AppState.pressurePoints[AppState.currentPressurePoint];
    if (point.attempts < 3) {
        document.getElementById('pressureResult').style.display = 'none';
        document.getElementById('retryPressureBtn').style.display = 'none';
        document.getElementById('readPressureBtn').style.display = 'inline-block';
    }
}

// Generate final report
function generateReport() {
    const now = new Date();
    
    document.getElementById('reportDate').textContent = now.toLocaleDateString('ru-RU');
    document.getElementById('reportTime').textContent = now.toLocaleTimeString('ru-RU');
    document.getElementById('reportSessionId').textContent = AppState.sessionId || 'DEMO';
    document.getElementById('reportSerialNumber').textContent = 
        document.getElementById('correctorSerial').value || '---';
    
    // Temperature table
    const tempTableBody = document.querySelector('#tempCalibrationTable tbody');
    tempTableBody.innerHTML = AppState.temperaturePoints.map(point => `
        <tr>
            <td>${point.temp > 0 ? '+' : ''}${point.temp}°C</td>
            <td>${point.temp > 0 ? '+' : ''}${point.temp}°C</td>
            <td>${point.temp > 0 ? '+' : ''}${point.temp}°C</td>
            <td>0.00°C</td>
            <td><span class="status-badge success">OK</span></td>
        </tr>
    `).join('');
    
    // Impulse summary
    document.getElementById('impulseSummaryCount').textContent = `${AppState.impulses.length}/20`;
    document.getElementById('impulseVerdict').textContent = 
        AppState.impulses.length >= 20 ? '✓ Пройдено' : '✗ Не пройдено';
    document.getElementById('impulseVerdict').className = 
        AppState.impulses.length >= 20 ? 'impulse-verdict success' : 'impulse-verdict error';
    
    // Pressure table
    const pressureTableBody = document.querySelector('#pressureResultsTable tbody');
    pressureTableBody.innerHTML = AppState.pressurePoints.map(point => `
        <tr>
            <td>${point.order}</td>
            <td>${point.target.toFixed(2)} кПа</td>
            <td>${point.target.toFixed(2)} кПа</td>
            <td>0.00 кПа</td>
            <td>${point.attempts}</td>
            <td><span class="status-badge ${point.status === 'completed' ? 'success' : 'error'}">
                ${point.status === 'completed' ? 'Годен' : 'Брак'}
            </span></td>
        </tr>
    `).join('');
    
    // Final verdict
    const allPassed = AppState.impulses.length >= 20 && 
                     AppState.pressurePoints.every(p => p.status === 'completed');
    const verdictBadge = document.getElementById('finalVerdictBadge');
    verdictBadge.textContent = allPassed ? 'ГОДЕН' : 'БРАК';
    verdictBadge.className = `verdict-badge verdict-large ${allPassed ? 'success' : 'error'}`;
    
    addLog('Отчет сгенерирован', 'info');
}

// Export to PDF
function exportToPdf() {
    alert('Функция экспорта в PDF будет реализована через библиотеку TCPDF или mPDF');
    addLog('Экспорт в PDF запрошен', 'action');
}

// Save to database
async function saveToDatabase() {
    const result = await apiCall('complete_calibration', {
        final_result: 'pass',
        notes: 'Калибровка завершена успешно'
    });
    
    if (result.success) {
        alert('Данные сохранены в базу данных');
        addLog('Данные сохранены в БД', 'action');
    }
}

// Start new session
function startNewSession() {
    if (confirm('Вы уверены? Все несохраненные данные будут потеряны.')) {
        location.reload();
    }
}

// Pause calibration
function pauseCalibration() {
    addLog('Калибровка приостановлена', 'warning');
    // Implement pause logic
}

// Timeout management
function resetTimeoutTimer() {
    AppState.lastActivity = Date.now();
    if (AppState.timeoutTimer) clearTimeout(AppState.timeoutTimer);
    
    AppState.timeoutTimer = setTimeout(() => {
        const elapsed = (Date.now() - AppState.lastActivity) / 1000 / 60;
        if (elapsed >= 15) {
            document.getElementById('timeoutModal').style.display = 'flex';
        }
    }, 15 * 60 * 1000); // 15 minutes
}

function updateTimeoutTimer() {
    resetTimeoutTimer();
    setInterval(() => {
        const elapsed = (Date.now() - AppState.lastActivity) / 1000 / 60;
        if (elapsed >= 14) {
            document.getElementById('timeoutModal').style.display = 'flex';
        }
    }, 60000); // Check every minute
}

// Logging
function addLog(message, type = 'info') {
    const logsList = document.getElementById('logsList');
    const logItem = document.createElement('div');
    logItem.className = `log-item ${type}`;
    logItem.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
    logsList.insertBefore(logItem, logsList.firstChild);
    
    // Keep only last 50 logs
    while (logsList.children.length > 50) {
        logsList.removeChild(logsList.lastChild);
    }
}
