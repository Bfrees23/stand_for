<?php
/**
 * Страница проверки давления
 */
$sensorType = $data['pressure']['sensor_type'] ?? 'absolute';
$checkpoints = $data['pressure']['checkpoints'] ?? [];
?>

<div class="card">
    <h2>🔵 Проверка датчиков давления</h2>
    
    <div class="instruction-box">
        <h3>📋 Инструкция для оператора</h3>
        <p id="currentPointIndicator"><strong>Точка 1 из <?= $sensorType === 'absolute' ? 5 : 3 ?></strong></p>
        <p>Установите на калибраторе давление <strong id="targetPressure">--.-- кПа</strong></p>
        <p>После стабилизации нажмите "Считать с корректора" и затем "Подтвердить точку"</p>
    </div>
    
    <!-- Ввод показаний -->
    <div class="chart-container" style="margin-top: 20px;">
        <h3>📊 Показания давления</h3>
        
        <div class="form-row" style="margin-top: 20px;">
            <div class="form-col">
                <div class="form-group">
                    <label for="measuredPressure">Измеренное давление (кПа):</label>
                    <input type="number" id="measuredPressure" class="form-control" step="0.01" placeholder="Введите значение">
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button id="readPressureBtn" class="btn btn-primary btn-large">
                📖 Считать с корректора
            </button>
        </div>
    </div>
    
    <!-- Список точек поверки -->
    <div class="card" style="margin-top: 20px;">
        <h3>✅ Точки поверки (<?= $sensorType === 'absolute' ? 'Абсолютный (5 точек)' : 'Перепад (3 точки)' ?>)</h3>
        <div id="pressureCheckpointsList">
            <!-- Точки будут добавлены динамически -->
        </div>
    </div>
    
    <!-- Кнопки действий -->
    <div style="text-align: center; margin-top: 30px;">
        <button id="submitPressureBtn" class="operator-button">
            ✔️ Подтвердить точку
        </button>
        
        <button id="retryPressureBtn" class="btn btn-warning btn-large" style="display: none;">
            🔄 Повторить точку
        </button>
    </div>
</div>

<div class="alert alert-info">
    <strong>ℹ️ Информация:</strong> 
    <?php if ($sensorType === 'absolute'): ?>
    Для абсолютного датчика проверяется 5 точек: 0%, 25%, 50%, 75%, 100% от диапазона.
    <?php else: ?>
    Для датчика перепада проверяется 3 точки: 0%, 50%, 100% от диапазона.
    <?php endif; ?>
    <br><br>
    Если погрешность превышает допустимую (1%), точка помечается красным. 
    После 3 неудачных попыток на одной точке датчик маркируется как "Брак".
</div>
