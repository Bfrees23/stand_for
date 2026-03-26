<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мастер калибровки корректора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .step-container { display: none; }
        .step-container.active { display: block; }
        .nav-tabs .nav-link { cursor: pointer; }
        .nav-tabs .nav-link.active { background-color: #0d6efd; color: white; font-weight: bold; }
        .status.connected { color: green; }
        .status.disconnected { color: gray; }
        .status.error { color: red; }
        .status.ready { color: orange; }
        .log-area { height: 150px; overflow-y: auto; background: #f8f9fa; padding: 10px; font-family: monospace; font-size: 0.85rem; border-radius: 4px; }
        .big-button { font-size: 1.3em; padding: 16px; margin:10px 0; }
        .step-disabled { opacity: 0.5; pointer-events: none; }
        .step-done { background-color: #d4edda; border-left: 4px solid #28a745; }
        .step-active { background-color: #e7f3ff; border-left: 4px solid #0d6efd; }
        .step-error { background-color: #f8d7da; border-left: 4px solid #dc3545; }
        .chart-container { height: 200px; background: white; border: 1px solid #ccc; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">Мастер калибровки корректора</span>
    </div>
</nav>

<div class="container">
    <div class="progress mb-4">
        <div class="progress-bar progress-bar-blue" id="progressBar" style="width: 0%;">0%</div>
    </div>

    <!-- Навигация -->
    <div class="nav nav-tabs mb-4" role="tablist">
        <button class="nav-link active" data-step="setup" type="button">Настройки</button>
        <button class="nav-link step-disabled" data-step="tempCalib" type="button">Калибровка темп.</button>
        <button class="nav-link step-disabled" data-step="pulseCheck" type="button">Проверка импульсов</button>
        <button class="nav-link step-disabled" data-step="pressureCheck" type="button">Проверка давления</button>
        <button class="nav-link step-disabled" data-step="report" type="button">Отчет</button>
    </div>

    <div class="mb-3">
        <label>Лог:</label>
        <div class="log-area" id="log"></div>
    </div>

    <!-- === ЭТАП: Настройки === -->
    <div id="setup" class="step-container active">
        <div class="card p-4">
            <h3>Настройки стенда</h3>
            <div class="mb-3">
                <h5>Корректор</h5>
                <label>COM-порт:</label>
                <select class="form-select" id="comPortKorr">
                    <option value="">Выберите порт</option>
                </select>
                <label>Скорость (bps):</label>
                <input type="number" class="form-control" id="baudRate" value="19200">
                <label>Единица давления:</label>
                <input type="text" class="form-control" id="pressureUnit" value="кПа" readonly>
                <button class="btn btn-outline-primary mt-1" onclick="refreshPorts()">Обновить порты</button>
            </div>
            <div class="mb-3">
                <h5>МИТ-8 (термопреобразователи)</h5>
                <div class="row">
                    <div class="col-md-4">
                        <label>Канал 1:</label>
                        <select class="form-select" id="comPortMit1">
                            <option value="">Выберите порт</option>
                        </select>
                        <label>Уставка (°C):</label>
                        <input type="number" class="form-control" id="setpoint1" value="-40">
                    </div>
                    <div class="col-md-4">
                        <label>Канал 2:</label>
                        <select class="form-select" id="comPortMit2">
                            <option value="">Выберите порт</option>
                        </select>
                        <label>Уставка (°C):</label>
                        <input type="number" class="form-control" id="setpoint2" value="10">
                    </div>
                    <div class="col-md-4">
                        <label>Канал 3:</label>
                        <select class="form-select" id="comPortMit3">
                            <option value="">Выберите порт</option>
                        </select>
                        <label>Уставка (°C):</label>
                        <input type="number" class="form-control" id="setpoint3" value="60">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-success" onclick="connectMIT(1)">Подключить МИТ-1</button>
                    <button class="btn btn-success ms-1" onclick="connectMIT(2)">Мит-2</button>
                    <button class="btn btn-success ms-1" onclick="connectMIT(3)">Мит-3</button>
                    <span id="mitStatus1" class="status ready">Готов</span>
                    <span id="mitStatus2" class="status ready">Готов</span>
                    <span id="mitStatus3" class="status ready">Готов</span>
                </div>
            </div>
            <div class="mb-3">
                <h5>Калибратор давления</h5>
                <label>COM-порт:</label>
                <select class="form-select" id="comPortPressure">
                    <option value="">Выберите порт</option>
                </select>
                <label>Тип датчика:</label>
                <select class="form-select" id="pressureType">
                    <option value="absolute">Абсолютный</option>
                    <option value="diff">Перепад</option>
                </select>
                <label>Диапазон (кПа):</label>
                <input type="text" class="form-control" id="pressureRange" value="0-1000">
                <label>Количество точек:</label>
                <input type="number" class="form-control" id="numPoints" value="5" readonly>
            </div>
            <button class="btn btn-success" onclick="testConnections()">Проверить связь</button>
            <button class="btn btn-primary float-end" onclick="startCalibration()" disabled id="startBtn">Начать калибровку</button>
        </div>
    </div>

    <!-- === ЭТАП: Калибровка температуры === -->
    <div id="tempCalib" class="step-container">
        <div class="card p-4">
            <h3>Калибровка температуры</h3>
            <div class="alert alert-info">
                Поместите датчик корректора и термопару МИТ-8 в камеру с температурой <strong>-40°C</strong>.<br>
                Убедитесь, что Элемер М90 с уставкой -40 подключен и стабилизирован.
            </div>
            <div class="mb-3">
                <label>Текущая темп. (МИТ-1):</label>
                <input type="number" class="form-control" id="tempCurrent" readonly>
            </div>
            <div class="mb-3">
                <label>Стабилизация (мин):</label>
                <div class="progress">
                    <div class="progress-bar progress-bar-blue" id="stabilizeProgress" style="width: 0%;"></div>
                </div>
                <small id="stabilizeText">Осталось 5 мин</small>
            </div>
            <div class="chart-container mt-3">
                <canvas id="tempChart"></canvas>
            </div>
            <button class="btn btn-success big-button w-100" id="recordPointBtn" onclick="recordTempPoint(-40)" disabled>Записать точку -40°C</button>
            <div class="mt-3">
                <h5>Статусы точек:</h5>
                <ul class="list-group">
                    <li class="list-group-item list-group-item-success">✓ -40°C выполнено</li>
                    <li class="list-group-item list-group-item-light">⏳ +10°C</li>
                    <li class="list-group-item list-group-item-light">⏳ +60°C</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- === ЭТАП: Проверка импульсов === -->
    <div id="pulseCheck" class="step-container">
        <div class="card p-4">
            <h3>Проверка импульсного входа</h3>
            <p>Подайте 20 импульсов. После каждого нажмите кнопку.</p>
            <div class="mb-3">
                <label>Зафиксировано:</label>
                <span id="impulseCount">0</span>/20
            </div>
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-blue" id="impulseProgress" style="width: 0%;"></div>
            </div>
            <button class="btn btn-outline-primary big-button w-100" onclick="addImpulse()">Зафиксировать импульс</button>
            <button class="btn btn-primary float-end mt-3" onclick="nextStep('pressureCheck')" id="nextImpulseBtn" disabled>Продолжить</button>
        </div>
    </div>

    <!-- === ЭТАП: Проверка давления === -->
    <div id="pressureCheck" class="step-container">
        <div class="card p-4">
            <h3>Проверка датчиков давления</h3>
            <div class="alert alert-info">
                Точка <strong>1 из 5</strong>. Установите на калибраторе давление <strong id="targetPressureDisplay">200.00</strong> кПа.
            </div>
            <div class="mb-3">
                <label>Установите на калибраторе (кПа):</label>
                <input type="number" class="form-control" id="targetPressure" value="200.00" step="0.01">
            </div>
            <div class="mb-3">
                <label>Показания корректора:</label>
                <input type="number" class="form-control" id="corrPressure" readonly>
            </div>
            <button class="btn btn-info big-button w-100" onclick="readPressureFromKorr()">Считать показания</button>
            <div class="mt-3">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Точка</th>
                            <th>Установлено</th>
                            <th>Корректор</th>
                            <th>Погрешность</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody id="pressureTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- === ЭТАП: Отчет === -->
    <div id="report" class="step-container">
        <div class="card p-4">
            <h3>Финальный отчет</h3>
            <pre id="reportText">[Здесь будет сгенерированный отчет]</pre>
            <button class="btn btn-success" onclick="exportPDF()">Экспорт PDF</button>
            <button class="btn btn-secondary" onclick="resetCalibration()">Начать заново</button>
        </div>
    </div>
</div>

<script>
    // Глобальные переменные
    let currentStep = 'setup';
    let impulseCount = 0;
    let mitConnected = [false, false, false];
    let connectedDevices = {
        korrek: null,
        mit1: null,
        mit2: null,
        mit3: null,
        pressure: null
    };
    let stabilizationTimer = null;
    let stabilizeTimeLeft = 300; // 5 минут
    let tempHistory = [];

    // Логирование
    function log(msg) {
        const el = document.getElementById('log');
        const ts = new Date().toLocaleTimeString();
        el.innerHTML += `[${ts}] ${msg}\n`;
        el.scrollTop = el.scrollHeight;
    }

    // Показать шаг
    function showStep(stepId) {
        document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
        document.getElementById(stepId).classList.add('active');
        currentStep = stepId;
        document.querySelectorAll('.nav-link').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-step="${stepId}"]`).classList.add('active');
        log(`Переход к этапу: ${stepId}`);
    }

    // Навигация по табам
    document.querySelectorAll('.nav-link').forEach(btn => {
        btn.addEventListener('click', () => {
            const step = btn.dataset.step;
            if (!btn.classList.contains('step-disabled')) showStep(step);
        });
    });

    // Проверка поддержки Web Serial API
    if (!navigator.serial) {
        alert('Web Serial API не поддерживается. Используйте Chrome/Edge.');
    }

    // Обновить список портов
    async function refreshPorts() {
        const ports = await navigator.serial.getPorts();
        const selects = [
            document.getElementById('comPortKorr'),
            document.getElementById('comPortMit1'),
            document.getElementById('comPortMit2'),
            document.getElementById('comPortMit3'),
            document.getElementById('comPortPressure')
        ];
        selects.forEach(select => {
            select.innerHTML = '<option value="">Выберите порт</option>';
            ports.forEach((port, i) => {
                const option = document.createElement('option');
                option.value = port.getInfo().usbProductId || `port${i}`;
                option.text = `COM${i} (${port.getInfo().usbVendorId ||                select.appendChild(option);
            });
        });
    }

    // Подключиться к МИТ
    async function connectMIT(channel) {
        const elStatus = document.getElementById(`mitStatus${channel}`);
        elStatus.textContent = 'Подключение...';
        elStatus.className = 'status connected';

        try {
            const port = await navigator.serial.requestPort({ filters: [] });
            await port.open({ baudRate: 9600 });

            connectedDevices[`mit${channel}`] = port;
            elStatus.textContent = 'Подключено';
            elStatus.className = 'status connected';
            log(`Подключено МИТ-${channel}`);

            // Начать опрос
            pollMIT(port, channel);
        } catch (e) {
            elStatus.textContent = 'Ошибка';
            elStatus.className = 'status error';
            log(`Ошибка подключения МИТ-${channel}: ${e.message}`);
        }
    }

    // Опрос МИТ
    async function pollMIT(port, channel) {
        const reader = port.readable.getReader();
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            const text = new TextDecoder().decode(value);
            const temp = parseFloat(text.trim());

            if (!isNaN(temp)) {
                if (channel === 1) {
                    document.getElementById('tempCurrent').value = temp;
                    tempHistory.push({ time: new Date(), temp });
                    if (currentStep === 'tempCalib') {
                        updateTempChart();
                    }
                }
            }
        }
        reader.releaseLock();
    }

    // Обновить график температуры
    function updateTempChart() {
        // (Заглушка — можно подключить Chart.js)
    }

    // Проверка связи
    async function testConnections() {
        let allConnected = true;
        for (const device in connectedDevices) {
            if (!connectedDevices[device]) {
                allConnected = false;
                break;
            }
        }

        if (allConnected) {
            log('Все устройства подключены');
            document.getElementById('startBtn').disabled = false;
            document.querySelector('[data-step="tempCalib"]').classList.remove('step-disabled');
        } else {
            log('Не все устройства подключены');
            alert('Подключите все устройства перед }
    }

    // Начать калибровку
    function startCalibration() {
        document.getElementById('startBtn').disabled = true;
        showStep('tempCalib');
        startStabilizationTimer();
        log('Калибровка начата');
    }

    // Таймер стабилизации
    function startStabilizationTimer() {
        stabilizeTimeLeft = 300;
        updateStabilizeUI();

        if (stabilizationTimer) clearInterval(stabilizationTimer);
        stabilizationTimer = setInterval(() => {
            stabilizeTimeLeft--;
            updateStabilizeUI();

            if (stabilizeTimeLeft <= 0) {
                clearInterval(stabilizationTimer);
                document.getElementById('recordPointBtn').disabled = false;
                log('Температура стабилизирована. Точка -40°C готова к записи.');
            }
        }, 1000);
    }

    function updateStabilizeUI() {
        const minutes = Math.floor(stabilizeTimeLeft / 60);
        const seconds = stabilizeTimeLeft % 60;
        const percent = 100 - (stabilizeTimeLeft / 300) * 100;
        document.getElementById('stabilizeProgress').style.width = percent + '%';
        document.getElementById('stabilizeText').textContent = `${minutes} мин ${seconds} сек`;
    }

    // Запись точки температуры
    async function recordTempPoint(temp) {
        const port = connectedDevices.korrek;
        if (!port) {
            alert('Корректор не подключен');
            return;
        }

        const writer = port.writable.getWriter();
        const encoder = new TextEncoder();
        const cmd = `SET TEMP ${temp.toFixed(1)}\r`;
        await writer.write(encoder.encode(cmd));
        writer.releaseLock();

        log(`Команда отправлена: ${cmd}`);
        document.getElementById('recordPointBtn').disabled = true;
        document.querySelector('[data-step="pulseCheck"]').classList.remove('step-disabled');
    }

    // Импульсы
    function addImpulse() {
        impulseCount++;
        document.getElementById('impulseCount').textContent = impulseCount;
        const percent = Math.round((impulseCount / 20) * 100);
        document.getElementById('impulseProgress').style.width = percent + '%';
        log(`Импульс #${impulseCount} зафиксирован`);

        if (impulseCount >= 20) {
            document.getElementById('nextImpulseBtn').disabled = false;
        }
    }

    // Переход к следующему этапу
    function nextStep(next) {
        log(`Переход к этапу: ${next}`);
        showStep(next);
    }

    // Чтение давления
    async function readPressureFromKorr() {
        const port = connectedDevices.korrek;
        if (!port) {
            alert('Корректор не подключен');
            return;
        }

        const writer = port.writable.getWriter();
        const encoder = new TextEncoder();
        await writer.write(encoder.encode("READ P\r"));
        writer.releaseLock();

        log('Команда "READ P" отправлена');
    }

    // Экспорт
    function exportPDF() {
        alert('Функция экспорта PDF будет реализована позже.');
    }

    // Сброс
    function resetCalibration() {
        impulseCount = 0;
        document.getElementById('impulseCount').textContent = '0';
        document.getElementById('impulseProgress').style.width = '0%';
        document.getElementById('nextImpulseBtn').disabled = true;

        if (stabilizationTimer) clearInterval(stabilizationTimer);
        stabilizeTimeLeft = 300;
        updateStabilizeUI();

        document.querySelector('[data-step="tempCalib"]').classList.add('step-disabled');
        document.querySelector('[data-step="pulseCheck"]').classList.add('step-disabled');
        document.querySelector('[data-step="pressureCheck"]').classList.add('step-disabled');

        log('Сброс калибровки');
        showStep('setup');
        document.getElementById('startBtn').disabled = true;
    }

    // Инициализация
    log('Система запущена');
</script>

</body>
</html>