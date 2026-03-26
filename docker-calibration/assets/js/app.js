/**
 * Основной JavaScript для системы калибровки
 */

// Глобальное состояние приложения
const AppState = {
    currentStage: 'setup',
    calibrationData: null,
    temperaturePollInterval: null,
    stabilizationTimer: null
};

// API вызовы
const API = {
    async call(action, data = null, method = 'POST') {
        const url = `api/handler.php?action=${action}`;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (data && method === 'POST') {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, error: error.message };
        }
    },
    
    saveSettings(settings) {
        return this.call('save_settings', settings);
    },
    
    testConnection(settings) {
        return this.call('test_connection', settings);
    },
    
    startCalibration() {
        return this.call('start_calibration');
    },
    
    getTemperatureStatus() {
        return this.call('get_temperature_status', null, 'GET');
    },
    
    readTemperature(point) {
        return this.call(`read_temperature&point=${encodeURIComponent(point)}`, null, 'GET');
    },
    
    writeTemperaturePoint(point, referenceTemp) {
        return this.call('write_temperature_point', { point, reference_temp: referenceTemp });
    },
    
    nextTemperaturePoint(currentPoint) {
        return this.call('next_temperature_point', { current_point: currentPoint });
    },
    
    verifyTemperature(environment) {
        return this.call('verify_temperature', { environment });
    },
    
    startImpulses() {
        return this.call('start_impulses');
    },
    
    recordImpulse(number) {
        return this.call('record_impulse', { number });
    },
    
    getImpulseStatus() {
        return this.call('get_impulse_status', null, 'GET');
    },
    
    startPressureCheck(sensorType, rangeMin, rangeMax) {
        return this.call('start_pressure_check', { sensor_type: sensorType, range_min: rangeMin, range_max: rangeMax });
    },
    
    getPressureStatus() {
        return this.call('get_pressure_status', null, 'GET');
    },
    
    readCorrectorPressure() {
        return this.call('read_corrector_pressure', null, 'GET');
    },
    
    submitPressureCheckpoint(index, setPressure, measuredPressure) {
        return this.call('submit_pressure_checkpoint', { index, set_pressure: setPressure, measured_pressure: measuredPressure });
    },
    
    retryPressureCheckpoint(index) {
        return this.call('retry_pressure_checkpoint', { index });
    },
    
    completeCalibration(operatorId, correctorSerial) {
        return this.call('complete_calibration', { operator_id: operatorId, corrector_serial: correctorSerial });
    },
    
    getFullData() {
        return this.call('get_full_data', null, 'GET');
    },
    
    reset() {
        return this.call('reset');
    }
};

// Утилиты
const Utils = {
    showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        const container = document.querySelector('.container');
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => alertDiv.remove(), 5000);
    },
    
    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    },
    
    getStatusClass(status) {
        const statusMap = {
            'pending': 'status-pending',
            'in_progress': 'status-in-progress',
            'completed': 'status-completed',
            'error': 'status-error',
            'pass': 'result-pass',
            'fail': 'result-fail'
        };
        return statusMap[status] || '';
    }
};

// Управление настройками
const SettingsManager = {
    init() {
        this.bindEvents();
        this.loadDefaults();
    },
    
    bindEvents() {
        document.getElementById('testConnectionBtn')?.addEventListener('click', () => this.testConnection());
        document.getElementById('saveSettingsBtn')?.addEventListener('click', () => this.saveSettings());
        document.getElementById('startCalibrationBtn')?.addEventListener('click', () => this.startCalibration());
    },
    
    loadDefaults() {
        // Загрузка настроек по умолчанию
        document.getElementById('correctorPort').value = 'COM1';
        document.getElementById('correctorBaudrate').value = '19200';
        document.getElementById('mit8Channel1Port').value = 'COM2';
        document.getElementById('mit8Channel2Port').value = 'COM3';
        document.getElementById('mit8Channel3Port').value = 'COM4';
        document.getElementById('pressureCalibratorPort').value = 'COM5';
        document.getElementById('pressureSensorType').value = 'absolute';
        document.getElementById('pressureRangeMin').value = '0';
        document.getElementById('pressureRangeMax').value = '1000';
    },
    
    getSettings() {
        return {
            corrector: {
                port: document.getElementById('correctorPort').value,
                baudrate: parseInt(document.getElementById('correctorBaudrate').value),
                parity: document.getElementById('correctorParity').value
            },
            mit8: {
                channel1: {
                    port: document.getElementById('mit8Channel1Port').value,
                    setpoint: -40
                },
                channel2: {
                    port: document.getElementById('mit8Channel2Port').value,
                    setpoint: 10
                },
                channel3: {
                    port: document.getElementById('mit8Channel3Port').value,
                    setpoint: 60
                }
            },
            pressure_calibrator: {
                port: document.getElementById('pressureCalibratorPort').value,
                type: document.getElementById('pressureSensorType').value,
                range_min: parseFloat(document.getElementById('pressureRangeMin').value),
                range_max: parseFloat(document.getElementById('pressureRangeMax').value)
            }
        };
    },
    
    async testConnection() {
        const settings = this.getSettings();
        const btn = document.getElementById('testConnectionBtn');
        btn.disabled = true;
        btn.textContent = 'Проверка...';
        
        const result = await API.testConnection(settings);
        
        if (result.success) {
            this.updateConnectionStatus(result.results);
            Utils.showNotification('Проверка связи завершена', 'success');
        } else {
            Utils.showNotification('Ошибка проверки связи: ' + result.error, 'error');
        }
        
        btn.disabled = false;
        btn.textContent = 'Проверить связь';
    },
    
    updateConnectionStatus(results) {
        // Обновление индикаторов статуса
        this.updateDeviceStatus('correctorStatus', results.corrector);
        
        for (const [channel, connected] of Object.entries(results.mit8_channels)) {
            this.updateDeviceStatus(`${channel}Status`, connected);
        }
        
        this.updateDeviceStatus('pressureCalibratorStatus', results.pressure_calibrator);
        
        // Проверка всех устройств
        const allConnected = results.corrector && 
                            Object.values(results.mit8_channels).every(v => v) && 
                            results.pressure_calibrator;
        
        document.getElementById('startCalibrationBtn').disabled = !allConnected;
    },
    
    updateDeviceStatus(elementId, connected) {
        const element = document.getElementById(elementId);
        if (element) {
            element.className = `status-indicator ${connected ? 'status-completed' : 'status-error'}`;
            element.title = connected ? 'Подключено' : 'Не подключено';
        }
    },
    
    async saveSettings() {
        const settings = this.getSettings();
        const result = await API.saveSettings(settings);
        
        if (result.success) {
            Utils.showNotification('Настройки сохранены', 'success');
        } else {
            Utils.showNotification('Ошибка сохранения: ' + result.error, 'error');
        }
    },
    
    async startCalibration() {
        const result = await API.startCalibration();
        
        if (result.success) {
            window.location.href = 'index.php?page=temperature';
        } else {
            Utils.showNotification('Ошибка запуска: ' + result.error, 'error');
        }
    }
};

// Управление калибровкой температуры
const TemperatureCalibration = {
    currentPoint: '-40',
    stabilizationStartTime: null,
    requiredStabilizationTime: 300, // 5 минут
    
    init() {
        this.bindEvents();
        this.startPolling();
        this.updateUI();
    },
    
    bindEvents() {
        document.getElementById('readTempBtn')?.addEventListener('click', () => this.readTemperature());
        document.getElementById('writePointBtn')?.addEventListener('click', () => this.writePoint());
        document.getElementById('nextPointBtn')?.addEventListener('click', () => this.nextPoint());
        document.getElementById('startVerificationBtn')?.addEventListener('click', () => this.startVerification());
    },
    
    startPolling() {
        // Опрос температуры каждые 30 секунд
        AppState.temperaturePollInterval = setInterval(() => {
            this.readTemperature(true);
        }, 30000);
    },
    
    stopPolling() {
        if (AppState.temperaturePollInterval) {
            clearInterval(AppState.temperaturePollInterval);
        }
    },
    
    async updateUI() {
        const status = await API.getTemperatureStatus();
        
        if (status.success) {
            this.currentPoint = status.current_point;
            this.updatePointDisplay(status.points);
        }
    },
    
    updatePointDisplay(points) {
        // Обновление отображения точек калибровки
        for (const [point, data] of Object.entries(points)) {
            const element = document.getElementById(`point-${point}`);
            if (element) {
                element.className = `checklist-item ${data.status === 'completed' ? 'completed' : ''}`;
                
                if (data.status === 'completed') {
                    element.innerHTML = `<span class="checkmark">✓</span> Точка ${point}°C: Эталон ${data.reference}°C, Записано ${data.written}°C`;
                } else {
                    element.innerHTML = `<span class="checkmark">○</span> Точка ${point}°C: Ожидание`;
                }
            }
        }
    },
    
    async readTemperature(auto = false) {
        const result = await API.readTemperature(this.currentPoint);
        
        if (result.success) {
            const tempElement = document.getElementById('currentTemperature');
            if (tempElement) {
                tempElement.textContent = `${result.temperature.toFixed(3)}°C`;
            }
            
            // Проверка стабилизации
            this.checkStabilization(result.temperature);
            
            if (!auto) {
                Utils.showNotification(`Температура: ${result.temperature.toFixed(3)}°C`, 'info');
            }
        } else if (!auto) {
            Utils.showNotification('Ошибка чтения температуры: ' + result.error, 'error');
        }
    },
    
    checkStabilization(temperature) {
        // Логика проверки стабилизации температуры
        // В реальном проекте здесь нужно хранить историю значений
        
        if (!this.stabilizationStartTime) {
            this.stabilizationStartTime = Date.now();
        }
        
        const elapsed = Math.floor((Date.now() - this.stabilizationStartTime) / 1000);
        const remaining = Math.max(0, this.requiredStabilizationTime - elapsed);
        
        const timerElement = document.getElementById('stabilizationTimer');
        if (timerElement) {
            timerElement.textContent = `Стабилизация: ${Utils.formatTime(elapsed)} из ${Utils.formatTime(this.requiredStabilizationTime)}`;
        }
        
        // Активация кнопки записи после стабилизации
        const writeBtn = document.getElementById('writePointBtn');
        if (writeBtn && remaining === 0) {
            writeBtn.disabled = false;
        }
    },
    
    async writePoint() {
        const tempElement = document.getElementById('currentTemperature');
        const temperature = parseFloat(tempElement.textContent);
        
        const btn = document.getElementById('writePointBtn');
        btn.disabled = true;
        btn.textContent = 'Запись...';
        
        const result = await API.writeTemperaturePoint(this.currentPoint, temperature);
        
        if (result.success) {
            Utils.showNotification(`Точка ${this.currentPoint}°C записана: ${result.written_temp}°C`, 'success');
            this.updateUI();
            
            document.getElementById('nextPointBtn').disabled = false;
        } else {
            Utils.showNotification('Ошибка записи: ' + result.error, 'error');
        }
        
        btn.disabled = false;
        btn.textContent = 'Записать точку';
    },
    
    async nextPoint() {
        const result = await API.nextTemperaturePoint(this.currentPoint);
        
        if (result.success) {
            if (result.next_point) {
                this.currentPoint = result.next_point;
                this.stabilizationStartTime = null;
                Utils.showNotification(`Переход к точке ${result.next_point}°C`, 'info');
                this.updateUI();
                
                document.getElementById('nextPointBtn').disabled = true;
                document.getElementById('writePointBtn').disabled = true;
            } else if (result.verification_started) {
                Utils.showNotification('Калибровка температуры завершена. Начинается поверка.', 'success');
                setTimeout(() => {
                    window.location.href = 'index.php?page=verification';
                }, 2000);
            }
        }
    },
    
    async startVerification() {
        window.location.href = 'index.php?page=verification';
    }
};

// Управление поверкой температуры
const TemperatureVerification = {
    environments: ['-40', '+10', '+60'],
    currentIndex: 0,
    
    init() {
        this.bindEvents();
        this.updateUI();
    },
    
    bindEvents() {
        document.getElementById('verifyBtn')?.addEventListener('click', () => this.verify());
        document.getElementById('nextEnvironmentBtn')?.addEventListener('click', () => this.nextEnvironment());
    },
    
    updateUI() {
        const env = this.environments[this.currentIndex];
        const instructionElement = document.getElementById('verificationInstruction');
        
        if (instructionElement) {
            instructionElement.textContent = `Поместите датчик в среду с температурой ${env}°C и нажмите "Считать показания"`;
        }
    },
    
    async verify() {
        const env = this.environments[this.currentIndex];
        const btn = document.getElementById('verifyBtn');
        btn.disabled = true;
        btn.textContent = 'Чтение...';
        
        const result = await API.verifyTemperature(env);
        
        if (result.success) {
            this.displayResult(env, result);
            
            if (result.result === 'pass') {
                Utils.showNotification(`Поверка ${env}°C: ПОЛОЖИТЕЛЬНО (погрешность ${result.error.toFixed(3)}°C)`, 'success');
            } else {
                Utils.showNotification(`Поверка ${env}°C: ОТРИЦАТЕЛЬНО (погрешность ${result.error.toFixed(3)}°C)`, 'error');
            }
            
            document.getElementById('nextEnvironmentBtn').disabled = false;
        } else {
            Utils.showNotification('Ошибка поверки: ' + result.error, 'error');
        }
        
        btn.disabled = false;
        btn.textContent = 'Считать показания';
    },
    
    displayResult(env, result) {
        const resultsTable = document.getElementById('verificationResults');
        
        if (resultsTable) {
            const row = resultsTable.insertRow();
            row.innerHTML = `
                <td>${env}°C</td>
                <td>${result.reference.toFixed(3)}°C</td>
                <td>${result.corrector.toFixed(3)}°C</td>
                <td>${result.error.toFixed(3)}°C</td>
                <td class="${result.result === 'pass' ? 'result-pass' : 'result-fail'}">
                    ${result.result === 'pass' ? 'Допустимо' : 'Не допустимо'}
                </td>
            `;
        }
    },
    
    async nextEnvironment() {
        this.currentIndex++;
        
        if (this.currentIndex < this.environments.length) {
            this.updateUI();
            document.getElementById('nextEnvironmentBtn').disabled = true;
        } else {
            // Все точки пройдены
            Utils.showNotification('Поверка температуры завершена', 'success');
            setTimeout(() => {
                window.location.href = 'index.php?page=impulses';
            }, 2000);
        }
    }
};

// Управление проверкой импульсов
const ImpulseCheck = {
    count: 0,
    required: 20,
    
    init() {
        this.bindEvents();
        this.loadStatus();
    },
    
    bindEvents() {
        document.getElementById('recordImpulseBtn')?.addEventListener('click', () => this.recordImpulse());
        document.getElementById('finishImpulsesBtn')?.addEventListener('click', () => this.finishImpulses());
    },
    
    async loadStatus() {
        const status = await API.getImpulseStatus();
        
        if (status.success) {
            this.count = status.count;
            this.required = status.required;
            this.updateUI();
        }
    },
    
    updateUI() {
        const countElement = document.getElementById('impulseCount');
        const progressBar = document.getElementById('impulseProgressBar');
        const progressFill = document.getElementById('impulseProgressFill');
        
        if (countElement) {
            countElement.textContent = `${this.count}/${this.required}`;
        }
        
        if (progressBar && progressFill) {
            const percentage = (this.count / this.required) * 100;
            progressFill.style.width = `${percentage}%`;
            progressFill.textContent = `${Math.round(percentage)}%`;
        }
        
        const finishBtn = document.getElementById('finishImpulsesBtn');
        if (finishBtn) {
            finishBtn.disabled = this.count < this.required;
        }
    },
    
    async recordImpulse() {
        this.count++;
        
        const result = await API.recordImpulse(this.count);
        
        if (result.success) {
            this.updateUI();
            
            // Добавление в чек-лист
            const checklist = document.getElementById('impulseChecklist');
            if (checklist) {
                const item = document.createElement('li');
                item.className = 'checklist-item completed';
                item.innerHTML = `<span class="checkmark">✓</span> Импульс #${this.count}`;
                checklist.appendChild(item);
            }
            
            if (result.completed) {
                Utils.showNotification('Все импульсы зафиксированы!', 'success');
            }
        } else {
            this.count--;
            Utils.showNotification('Ошибка фиксации импульса', 'error');
        }
    },
    
    async finishImpulses() {
        Utils.showNotification('Проверка импульсов завершена', 'success');
        setTimeout(() => {
            window.location.href = 'index.php?page=pressure';
        }, 2000);
    }
};

// Управление проверкой давления
const PressureCheck = {
    checkpoints: [],
    currentIndex: 0,
    
    init() {
        this.bindEvents();
        this.loadStatus();
    },
    
    bindEvents() {
        document.getElementById('readPressureBtn')?.addEventListener('click', () => this.readPressure());
        document.getElementById('submitPressureBtn')?.addEventListener('click', () => this.submitPressure());
        document.getElementById('retryPressureBtn')?.addEventListener('click', () => this.retryPressure());
    },
    
    async loadStatus() {
        const status = await API.getPressureStatus();
        
        if (status.success) {
            this.checkpoints = status.checkpoints;
            this.updateUI();
        }
    },
    
    updateUI() {
        const checkpoint = this.checkpoints[this.currentIndex];
        
        if (checkpoint) {
            const targetElement = document.getElementById('targetPressure');
            if (targetElement) {
                targetElement.textContent = `${checkpoint.pressure.toFixed(2)} кПа`;
            }
            
            const pointIndicator = document.getElementById('currentPointIndicator');
            if (pointIndicator) {
                pointIndicator.textContent = `Точка ${checkpoint.index} из ${this.checkpoints.length}`;
            }
        }
        
        this.updateCheckpointsList();
    },
    
    updateCheckpointsList() {
        const listElement = document.getElementById('pressureCheckpointsList');
        
        if (listElement) {
            listElement.innerHTML = '';
            
            for (const cp of this.checkpoints) {
                const item = document.createElement('div');
                item.className = `checklist-item ${cp.status === 'completed' ? 'completed' : ''} ${cp.status === 'error' ? 'error' : ''}`;
                item.innerHTML = `
                    <span class="checkmark">${cp.status === 'completed' ? '✓' : cp.status === 'error' ? '✗' : '○'}</span>
                    Точка ${cp.index}: ${cp.pressure.toFixed(2)} кПа (${cp.percentage}%)
                `;
                listElement.appendChild(item);
            }
        }
    },
    
    async readPressure() {
        const btn = document.getElementById('readPressureBtn');
        btn.disabled = true;
        btn.textContent = 'Чтение...';
        
        const result = await API.readCorrectorPressure();
        
        if (result.success) {
            const measuredElement = document.getElementById('measuredPressure');
            if (measuredElement) {
                measuredElement.value = result.pressure.toFixed(2);
            }
            Utils.showNotification(`Давление: ${result.pressure.toFixed(2)} кПа`, 'info');
        } else {
            Utils.showNotification('Ошибка чтения: ' + result.error, 'error');
        }
        
        btn.disabled = false;
        btn.textContent = 'Считать с корректора';
    },
    
    async submitPressure() {
        const checkpoint = this.checkpoints[this.currentIndex];
        const measuredElement = document.getElementById('measuredPressure');
        const measuredPressure = parseFloat(measuredElement.value);
        
        const btn = document.getElementById('submitPressureBtn');
        btn.disabled = true;
        
        const result = await API.submitPressureCheckpoint(
            checkpoint.index,
            checkpoint.pressure,
            measuredPressure
        );
        
        if (result.success) {
            if (result.result === 'pass') {
                Utils.showNotification(`Точка ${checkpoint.index}: ПОЛОЖИТЕЛЬНО (погрешность ${result.error.toFixed(2)} кПа)`, 'success');
                checkpoint.status = 'completed';
            } else {
                Utils.showNotification(`Точка ${checkpoint.index}: ОТРИЦАТЕЛЬНО (погрешность ${result.error.toFixed(2)} кПа)`, 'error');
                checkpoint.status = 'error';
            }
            
            this.updateUI();
            
            if (result.all_completed) {
                setTimeout(() => {
                    window.location.href = 'index.php?page=report';
                }, 2000);
            } else {
                this.currentIndex++;
                if (this.currentIndex < this.checkpoints.length) {
                    this.updateUI();
                    document.getElementById('measuredPressure').value = '';
                }
            }
        } else {
            Utils.showNotification('Ошибка: ' + result.error, 'error');
        }
        
        btn.disabled = false;
    },
    
    async retryPressure() {
        const checkpoint = this.checkpoints[this.currentIndex];
        
        const result = await API.retryPressureCheckpoint(checkpoint.index);
        
        if (result.success) {
            checkpoint.status = 'pending';
            this.updateUI();
            document.getElementById('measuredPressure').value = '';
            Utils.showNotification('Точка сброшена для повторной проверки', 'info');
        }
    }
};

// Управление отчетом
const ReportManager = {
    async init() {
        await this.loadReport();
        this.bindEvents();
    },
    
    bindEvents() {
        document.getElementById('exportPdfBtn')?.addEventListener('click', () => this.exportPDF());
        document.getElementById('saveToDbBtn')?.addEventListener('click', () => this.saveToDB());
        document.getElementById('restartBtn')?.addEventListener('click', () => this.restart());
    },
    
    async loadReport() {
        const result = await API.getFullData();
        
        if (result.success) {
            AppState.calibrationData = result.data;
            this.renderReport(result.data, result.log);
        }
    },
    
    renderReport(data, log) {
        // Заголовок
        const headerElement = document.getElementById('reportHeader');
        if (headerElement) {
            headerElement.innerHTML = `
                <h1>Протокол калибровки</h1>
                <p>Дата: ${new Date().toLocaleString('ru-RU')}</p>
                <p>Оператор: ${data.operator_id || 'Не указан'}</p>
                <p>Серийный номер корректора: ${data.corrector_serial || 'Не указан'}</p>
            `;
        }
        
        // Результаты температуры
        this.renderTemperatureResults(data.temperature_calibration);
        
        // Результаты импульсов
        this.renderImpulseResults(data.impulses);
        
        // Результаты давления
        this.renderPressureResults(data.pressure);
        
        // Лог действий
        this.renderActionLog(log);
    },
    
    renderTemperatureResults(tempData) {
        const container = document.getElementById('temperatureResults');
        
        if (container && tempData.points) {
            let html = '<h3>Калибровка температуры</h3><table class="table"><thead><tr><th>Точка</th><th>Эталон (МИТ-8)</th><th>Записано</th><th>Время</th></tr></thead><tbody>';
            
            for (const [point, data] of Object.entries(tempData.points)) {
                if (data.status === 'completed') {
                    html += `<tr>
                        <td>${point}°C</td>
                        <td>${data.reference.toFixed(3)}°C</td>
                        <td>${data.written.toFixed(3)}°C</td>
                        <td>${data.time}</td>
                    </tr>`;
                }
            }
            
            html += '</tbody></table>';
            container.innerHTML = html;
        }
    },
    
    renderImpulseResults(impulseData) {
        const container = document.getElementById('impulseResults');
        
        if (container) {
            container.innerHTML = `
                <h3>Проверка импульсов</h3>
                <p>Зафиксировано импульсов: ${impulseData.count}/${impulseData.required}</p>
                <p>Статус: ${impulseData.status === 'completed' ? '✓ Завершено' : '✗ Не завершено'}</p>
            `;
        }
    },
    
    renderPressureResults(pressureData) {
        const container = document.getElementById('pressureResults');
        
        if (container && pressureData.checkpoints) {
            let html = '<h3>Проверка давления</h3><table class="table"><thead><tr><th>Точка</th><th>Установлено</th><th>Измерено</th><th>Погрешность</th><th>Результат</th></tr></thead><tbody>';
            
            for (const cp of pressureData.checkpoints) {
                const resultClass = cp.status === 'completed' ? 'result-pass' : 'result-fail';
                const resultText = cp.status === 'completed' ? 'Годен' : 'Брак';
                
                html += `<tr>
                    <td>${cp.index} (${cp.percentage}%)</td>
                    <td>${cp.set_pressure?.toFixed(2) || cp.pressure.toFixed(2)} кПа</td>
                    <td>${cp.measured_pressure?.toFixed(2) || '-'} кПа</td>
                    <td>${cp.error?.toFixed(2) || '-'} кПа</td>
                    <td class="${resultClass}">${resultText}</td>
                </tr>`;
            }
            
            html += '</tbody></table>';
            container.innerHTML = html;
        }
    },
    
    renderActionLog(log) {
        const container = document.getElementById('actionLog');
        
        if (container && log.length > 0) {
            let html = '<h3>Журнал действий</h3><ul class="checklist">';
            
            for (const entry of log) {
                html += `<li class="checklist-item">
                    <strong>${entry.time}</strong>: ${entry.action}
                    ${entry.details ? `<br><small>${entry.details}</small>` : ''}
                </li>`;
            }
            
            html += '</ul>';
            container.innerHTML = html;
        }
    },
    
    exportPDF() {
        // В реальном проекте здесь будет генерация PDF
        Utils.showNotification('Функция экспорта в PDF будет реализована', 'info');
        window.print();
    },
    
    async saveToDB() {
        // В реальном проекте здесь будет сохранение в БД
        Utils.showNotification('Данные сохранены в архив', 'success');
    },
    
    async restart() {
        if (confirm('Вы уверены? Все данные будут потеряны.')) {
            await API.reset();
            window.location.href = 'index.php';
        }
    }
};

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    const page = window.location.search.includes('page=') 
        ? new URLSearchParams(window.location.search).get('page') 
        : 'setup';
    
    switch(page) {
        case 'setup':
            SettingsManager.init();
            break;
        case 'temperature':
            TemperatureCalibration.init();
            break;
        case 'verification':
            TemperatureVerification.init();
            break;
        case 'impulses':
            ImpulseCheck.init();
            break;
        case 'pressure':
            PressureCheck.init();
            break;
        case 'report':
            ReportManager.init();
            break;
    }
});
