<?php
/**
 * Страница калибровки температуры
 */
?>

<div class="card">
    <h2>🌡️ Калибровка температуры</h2>
    
    <div class="instruction-box">
        <h3>📋 Инструкция для оператора</h3>
        <p><strong>Текущая точка: <span id="currentPointDisplay"><?= $data['temperature_calibration']['current_point'] ?? '-40' ?>°C</span></strong></p>
        <p>Поместите датчик корректора и термопару МИТ-8 в камеру с температурой <strong id="targetTempDisplay">-40°C</strong>.</p>
        <p>Убедитесь, что Элемер М90 с соответствующей уставкой подключен и стабилизирован.</p>
    </div>
    
    <!-- Отображение текущей температуры -->
    <div class="chart-container">
        <h3>📊 Текущие показания</h3>
        <div style="text-align: center; margin: 20px;">
            <div style="font-size: 48px; font-weight: bold; color: var(--primary-color);">
                <span id="currentTemperature">--.---°C</span>
            </div>
            <p style="color: #666;">Показания МИТ-8 (эталон)</p>
        </div>
        
        <!-- Таймер стабилизации -->
        <div id="stabilizationTimer" class="timer-display">
            Стабилизация: 00:00 из 05:00
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button id="readTempBtn" class="btn btn-primary">
                📖 Считать температуру
            </button>
        </div>
    </div>
    
    <!-- Чек-лист точек калибровки -->
    <div class="card" style="margin-top: 20px;">
        <h3>✅ Точки калибровки</h3>
        <ul class="checklist" id="temperatureChecklist">
            <li id="point--40" class="checklist-item">
                <span class="checkmark">○</span> Точка -40°C: Ожидание
            </li>
            <li id="point-+10" class="checklist-item">
                <span class="checkmark">○</span> Точка +10°C: Ожидание
            </li>
            <li id="point-+60" class="checklist-item">
                <span class="checkmark">○</span> Точка +60°C: Ожидание
            </li>
        </ul>
    </div>
    
    <!-- Кнопки действий -->
    <div style="text-align: center; margin-top: 30px;">
        <button id="writePointBtn" class="operator-button" disabled>
            ✍️ Записать точку <?= $data['temperature_calibration']['current_point'] ?? '-40' ?>°C
        </button>
        
        <button id="nextPointBtn" class="btn btn-primary btn-large" style="display: none;">
            ➡️ Следующая точка
        </button>
        
        <button id="startVerificationBtn" class="btn btn-success btn-large" style="display: none;">
            ✔️ Начать поверку
        </button>
    </div>
</div>

<div class="alert alert-info">
    <strong>ℹ️ Информация:</strong> Система автоматически опрашивает МИТ-8 каждые 30 секунд. 
    Кнопка "Записать точку" станет активной после 5 минут стабильной температуры (±0.5°C).
</div>

<script>
// Установка начальной точки
document.addEventListener('DOMContentLoaded', function() {
    const currentPoint = '<?= $data['temperature_calibration']['current_point'] ?? '-40' ?>';
    document.getElementById('currentPointDisplay').textContent = currentPoint + '°C';
    document.getElementById('targetTempDisplay').textContent = currentPoint + '°C';
});
</script>
