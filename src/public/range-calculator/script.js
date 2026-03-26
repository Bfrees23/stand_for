document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('rangeForm');
    const resultDiv = document.getElementById('result');
    const rangesOutput = document.getElementById('rangesOutput');
    const errorMsg = document.getElementById('errorMsg');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const minValue = parseFloat(document.getElementById('minValue').value);
        const maxValue = parseFloat(document.getElementById('maxValue').value);
        
        // Валидация на клиенте
        if (isNaN(minValue) || isNaN(maxValue)) {
            showError('Пожалуйста, введите корректные числовые значения');
            return;
        }
        
        if (minValue >= maxValue) {
            showError('Минимальное значение должно быть меньше максимального');
            return;
        }
        
        // Отправка данных на сервер
        try {
            const response = await fetch('process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    minValue: minValue,
                    maxValue: maxValue
                })
            });
            
            if (!response.ok) {
                throw new Error('Ошибка сервера: ' + response.status);
            }
            
            const data = await response.json();
            
            if (data.success) {
                displayResults(data.parts);
            } else {
                showError(data.error || 'Произошла ошибка при расчете');
            }
        } catch (error) {
            showError('Ошибка соединения с сервером: ' + error.message);
        }
    });
    
    function displayResults(parts) {
        let html = '';
        
        parts.forEach((part, index) => {
            const colors = ['#007bff', '#28a745', '#dc3545'];
            const names = ['Первая часть', 'Вторая часть', 'Третья часть'];
            
            html += `
                <div class="range-part" style="border-left-color: ${colors[index]}">
                    <strong>${names[index]}</strong><br>
                    от ${part.start} до ${part.end}<br>
                    <small>Диапазон: ${part.range}</small>
                </div>
            `;
        });
        
        rangesOutput.innerHTML = html;
        resultDiv.classList.add('show');
        errorMsg.classList.remove('show');
    }
    
    function showError(message) {
        errorMsg.textContent = message;
        errorMsg.classList.add('show');
        resultDiv.classList.remove('show');
    }
});
