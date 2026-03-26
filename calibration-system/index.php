<?php
/**
 * Главный файл приложения - маршрутизатор и отображение страниц
 */

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/calibration_manager.php';

$manager = new CalibrationManager();
$data = $manager->getData();
$page = $_GET['page'] ?? 'setup';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система калибровки корректоров</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Заголовок -->
        <header class="card">
            <h1>🔧 Система калибровки корректоров</h1>
            <p>Программное обеспечение для поверки и калибровки датчиков температуры и давления</p>
        </header>

        <!-- Wizard прогресс -->
        <?php if ($page !== 'setup'): ?>
        <nav class="card">
            <div class="wizard-progress">
                <div class="wizard-step <?= $data['temperature_calibration']['status'] === 'completed' ? 'completed' : ($data['current_stage'] === 'temperature' ? 'in-progress' : '') ?>">
                    <div class="wizard-step-circle">1</div>
                    <div class="wizard-step-label">Температура</div>
                </div>
                <div class="wizard-step <?= $data['impulses']['status'] === 'completed' ? 'completed' : ($data['current_stage'] === 'impulses' ? 'in-progress' : '') ?>">
                    <div class="wizard-step-circle">2</div>
                    <div class="wizard-step-label">Импульсы</div>
                </div>
                <div class="wizard-step <?= $data['pressure']['status'] === 'completed' ? 'completed' : ($data['current_stage'] === 'pressure' ? 'in-progress' : '') ?>">
                    <div class="wizard-step-circle">3</div>
                    <div class="wizard-step-label">Давление</div>
                </div>
                <div class="wizard-step <?= $data['completed'] ? 'completed' : ($data['current_stage'] === 'report' ? 'in-progress' : '') ?>">
                    <div class="wizard-step-circle">4</div>
                    <div class="wizard-step-label">Отчет</div>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <!-- Контент страницы -->
        <main>
            <?php
            switch ($page) {
                case 'setup':
                    include __DIR__ . '/templates/setup.php';
                    break;
                case 'temperature':
                    include __DIR__ . '/templates/temperature.php';
                    break;
                case 'verification':
                    include __DIR__ . '/templates/verification.php';
                    break;
                case 'impulses':
                    include __DIR__ . '/templates/impulses.php';
                    break;
                case 'pressure':
                    include __DIR__ . '/templates/pressure.php';
                    break;
                case 'report':
                    include __DIR__ . '/templates/report.php';
                    break;
                default:
                    echo '<div class="alert alert-error">Страница не найдена</div>';
            }
            ?>
        </main>

        <!-- Подвал -->
        <footer class="card" style="margin-top: 30px; text-align: center; color: #666;">
            <p>Система калибровки v1.0 &copy; <?= date('Y') ?></p>
        </footer>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
