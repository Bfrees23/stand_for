<?php
/**
 * Страница проверки импульсов
 */
?>

<div class="card">
    <h2>⚡ Проверка импульсов</h2>
    
    <div class="instruction-box">
        <h3>📋 Инструкция для оператора</h3>
        <p>Подайте импульсы на корректор и фиксируйте каждый успешный импульс нажатием кнопки.</p>
        <p><strong>Требуется зафиксировать 20 импульсов для продолжения.</strong></p>
    </div>
    
    <!-- Прогресс бар -->
    <div class="chart-container" style="margin-top: 20px;">
        <h3>📊 Прогресс</h3>
        <div id="impulseCount" style="text-align: center; font-size: 36px; font-weight: bold; margin: 20px 0;">
            0/20
        </div>
        
        <div class="progress-bar-container">
            <div id="impulseProgressFill" class="progress-bar" style="width: 0%;">
                0%
            </div>
        </div>
    </div>
    
    <!-- Чек-лист импульсов -->
    <div class="card" style="margin-top: 20px;">
        <h3>✅ Зафиксированные импульсы</h3>
        <ul id="impulseChecklist" class="checklist">
            <!-- Импульсы будут добавлены динамически -->
        </ul>
    </div>
    
    <!-- Кнопки действий -->
    <div style="text-align: center; margin-top: 30px;">
        <button id="recordImpulseBtn" class="operator-button">
            👆 Зафиксировать импульс
        </button>
        
        <button id="finishImpulsesBtn" class="btn btn-success btn-large" disabled>
            ✔️ Завершить проверку
        </button>
    </div>
</div>

<div class="alert alert-info">
    <strong>ℹ️ Информация:</strong> Кнопка "Завершить проверку" станет активной только после фиксации всех 20 импульсов.
</div>
