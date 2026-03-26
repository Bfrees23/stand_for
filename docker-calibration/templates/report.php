<?php
/**
 * Страница итогового отчета
 */
?>

<div class="card">
    <div id="reportHeader" class="report-header">
        <h1>📄 Протокол калибровки</h1>
        <p>Дата: <?= date('d.m.Y H:i:s') ?></p>
        <p>Оператор: <span id="operatorId"><?= $data['operator_id'] ?? 'Не указан' ?></span></p>
        <p>Серийный номер корректора: <span id="correctorSerial"><?= $data['corrector_serial'] ?? 'Не указан' ?></span></p>
    </div>
    
    <!-- Результаты калибровки температуры -->
    <div id="temperatureResults" class="report-section">
        <!-- Будет заполнено динамически -->
    </div>
    
    <!-- Результаты поверки температуры -->
    <?php if (!empty($data['temperature_calibration']['verification']['results'])): ?>
    <div class="report-section">
        <h3>✔️ Результаты поверки температуры</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Среда</th>
                    <th>Эталон (МИТ-8)</th>
                    <th>Корректор</th>
                    <th>Погрешность</th>
                    <th>Результат</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['temperature_calibration']['verification']['results'] as $result): ?>
                <tr>
                    <td><?= htmlspecialchars($result['environment']) ?>°C</td>
                    <td><?= number_format($result['reference'], 3) ?>°C</td>
                    <td><?= number_format($result['corrector_value'], 3) ?>°C</td>
                    <td><?= number_format($result['error'], 3) ?>°C</td>
                    <td class="<?= $result['result'] === 'pass' ? 'result-pass' : 'result-fail' ?>">
                        <?= $result['result'] === 'pass' ? 'Допустимо' : 'Не допустимо' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Результаты проверки импульсов -->
    <div id="impulseResults" class="report-section">
        <h3>⚡ Проверка импульсов</h3>
        <p>Зафиксировано импульсов: <strong><?= $data['impulses']['count'] ?>/<?= $data['impulses']['required'] ?></strong></p>
        <p>Статус: 
            <?php if ($data['impulses']['status'] === 'completed'): ?>
                <span class="result-pass">✓ Завершено</span>
            <?php else: ?>
                <span class="result-fail">✗ Не завершено</span>
            <?php endif; ?>
        </p>
    </div>
    
    <!-- Результаты проверки давления -->
    <div id="pressureResults" class="report-section">
        <!-- Будет заполнено динамически -->
    </div>
    
    <!-- Журнал действий -->
    <div id="actionLog" class="report-section">
        <!-- Будет заполнено динамически -->
    </div>
    
    <!-- Форма завершения -->
    <div class="card" style="margin-top: 30px; background-color: #f8f9fa;">
        <h3>📝 Завершение калибровки</h3>
        
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="finalOperatorId">ID оператора:</label>
                    <input type="text" id="finalOperatorId" class="form-control" placeholder="Введите ID">
                </div>
            </div>
            
            <div class="form-col">
                <div class="form-group">
                    <label for="finalCorrectorSerial">Серийный номер корректора:</label>
                    <input type="text" id="finalCorrectorSerial" class="form-control" placeholder="Введите серийный номер">
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button id="completeCalibrationBtn" class="btn btn-success btn-large">
                ✅ Завершить калибровку
            </button>
        </div>
    </div>
    
    <!-- Кнопки действий с отчетом -->
    <div style="text-align: center; margin-top: 30px;">
        <button id="exportPdfBtn" class="btn btn-primary btn-large">
            📄 Экспорт в PDF
        </button>
        
        <button id="saveToDbBtn" class="btn btn-success btn-large">
            💾 Сохранить в БД
        </button>
        
        <button id="restartBtn" class="btn btn-danger btn-large">
            🔄 Начать заново
        </button>
    </div>
</div>

<div class="alert alert-success">
    <strong>✓ Калибровка завершена!</strong> Все этапы успешно пройдены. 
    Вы можете экспортировать протокол в PDF, сохранить результаты в базу данных или начать новую калибровку.
</div>

<script>
// Обработчик завершения калибровки
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('completeCalibrationBtn')?.addEventListener('click', async function() {
        const operatorId = document.getElementById('finalOperatorId').value;
        const correctorSerial = document.getElementById('finalCorrectorSerial').value;
        
        if (!operatorId || !correctorSerial) {
            alert('Пожалуйста, заполните ID оператора и серийный номер корректора');
            return;
        }
        
        const result = await API.completeCalibration(operatorId, correctorSerial);
        
        if (result.success) {
            document.getElementById('operatorId').textContent = operatorId;
            document.getElementById('correctorSerial').textContent = correctorSerial;
            alert('Калибровка завершена!');
            location.reload();
        } else {
            alert('Ошибка: ' + result.error);
        }
    });
});
</script>
