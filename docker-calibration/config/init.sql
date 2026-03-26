-- Инициализация базы данных для системы калибровки

CREATE DATABASE IF NOT EXISTS calibration;
USE calibration;

-- Таблица пользователей/операторов
CREATE TABLE IF NOT EXISTS operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица устройств (корректоров)
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_number VARCHAR(50) NOT NULL UNIQUE,
    model VARCHAR(100),
    pressure_type ENUM('absolute', 'differential') NOT NULL,
    pressure_range_min DECIMAL(10,2) DEFAULT 0,
    pressure_range_max DECIMAL(10,2) DEFAULT 1000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица калибровок
CREATE TABLE IF NOT EXISTS calibrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    operator_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('in_progress', 'completed', 'failed', 'aborted') DEFAULT 'in_progress',
    temperature_calibration_data JSON,
    impulse_check_data JSON,
    pressure_check_data JSON,
    verification_data JSON,
    final_report JSON,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица логов событий
CREATE TABLE IF NOT EXISTS event_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calibration_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (calibration_id) REFERENCES calibrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица точек калибровки температуры
CREATE TABLE IF NOT EXISTS temperature_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calibration_id INT NOT NULL,
    point_name VARCHAR(20) NOT NULL, -- '-40', '+10', '+60'
    target_temp DECIMAL(5,2) NOT NULL,
    reference_temp DECIMAL(5,2), -- Показания МИТ-8
    device_temp DECIMAL(5,2), -- Показания корректора
    tolerance DECIMAL(5,2) DEFAULT 0.5,
    stabilization_time INT, -- Время стабилизации в секундах
    status ENUM('pending', 'stabilizing', 'ready', 'calibrated', 'failed') DEFAULT 'pending',
    calibrated_at TIMESTAMP NULL,
    FOREIGN KEY (calibration_id) REFERENCES calibrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблица точек проверки давления
CREATE TABLE IF NOT EXISTS pressure_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calibration_id INT NOT NULL,
    point_number INT NOT NULL,
    target_pressure DECIMAL(10,2) NOT NULL,
    reference_pressure DECIMAL(10,2),
    device_pressure DECIMAL(10,2),
    error DECIMAL(10,2),
    attempts INT DEFAULT 0,
    status ENUM('pending', 'passed', 'failed', 'retry') DEFAULT 'pending',
    checked_at TIMESTAMP NULL,
    FOREIGN KEY (calibration_id) REFERENCES calibrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Индексы для ускорения поиска
CREATE INDEX idx_calibrations_device ON calibrations(device_id);
CREATE INDEX idx_calibrations_operator ON calibrations(operator_id);
CREATE INDEX idx_calibrations_status ON calibrations(status);
CREATE INDEX idx_logs_calibration ON event_logs(calibration_id);
CREATE INDEX idx_logs_timestamp ON event_logs(timestamp);
CREATE INDEX idx_temp_points_calibration ON temperature_points(calibration_id);
CREATE INDEX idx_pressure_points_calibration ON pressure_points(calibration_id);

-- Начальные данные (демо оператор)
INSERT INTO operators (username, full_name) VALUES 
('admin', 'Администратор Системы'),
('operator1', 'Оператор 1');

-- Демо устройство
INSERT INTO devices (serial_number, model, pressure_type, pressure_range_min, pressure_range_max) VALUES 
('CORR-001', 'Корректор-М90', 'absolute', 0, 1000);
