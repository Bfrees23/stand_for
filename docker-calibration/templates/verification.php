<?php
/**
 * Страница поверки температуры
 */
?>

<div class="card">
    <h2>✔️ Поверка температуры</h2>
    
    <div class="instruction-box">
        <h3>📋 Инструкция для оператора</h3>
        <p id="verificationInstruction">Поместите датчик в среду с температурой -40°C и нажмите "Считать показания"</p>
    </div>
    
    <!-- Таблица результатов поверки -->
    <div class="card" style="margin-top: 20px;">
        <h3>📊 Результаты поверки</h3>
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
            <tbody id="verificationResults">
                <!-- Результаты будут добавлены динамически -->
            </tbody>
        </table>
    </div>
    
    <!-- Кнопки действий -->
    <div style="text-align: center; margin-top: 30px;">
        <button id="verifyBtn" class="operator-button">
            📖 Считать показания
        </button>
        
        <button id="nextEnvironmentBtn" class="btn btn-primary btn-large" disabled style="display: none;">
            ➡️ Следующая среда
        </button>
    </div>
</div>

<div class="alert alert-info">
    <strong>ℹ️ Информация:</strong> Система одновременно опрашивает МИТ-8 (эталон) и корректор (внутреннее значение).
    Если хотя бы в одной точке погрешность превышает ±0.5°C, программа предложит выполнить перекалибровку.
</div>
