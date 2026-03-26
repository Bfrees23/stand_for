-- Database initialization script for Calibration System

CREATE DATABASE IF NOT EXISTS calibration_db;
USE calibration_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('operator', 'admin') DEFAULT 'operator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Devices configuration table
CREATE TABLE IF NOT EXISTS devices_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_type ENUM('corrector', 'mit8', 'pressure_calibrator') NOT NULL,
    com_port VARCHAR(20) NOT NULL,
    baud_rate INT DEFAULT 19200,
    parity ENUM('None', 'Even', 'Odd') DEFAULT 'None',
    data_bits INT DEFAULT 8,
    stop_bits INT DEFAULT 1,
    channel_id INT NULL COMMENT 'For MIT-8 multi-channel',
    temperature_setpoint DECIMAL(5,2) NULL COMMENT 'Temperature setpoint for MIT-8',
    pressure_type ENUM('absolute', 'differential') NULL,
    pressure_range_min DECIMAL(10,2) DEFAULT 0,
    pressure_range_max DECIMAL(10,2) DEFAULT 1000,
    is_active BOOLEAN DEFAULT TRUE,
    last_tested_at TIMESTAMP NULL,
    test_status ENUM('unknown', 'success', 'failed') DEFAULT 'unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calibration sessions table
CREATE TABLE IF NOT EXISTS calibration_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_uuid VARCHAR(36) UNIQUE NOT NULL,
    operator_id INT NOT NULL,
    corrector_serial_number VARCHAR(50) NOT NULL,
    corrector_model VARCHAR(100),
    status ENUM('in_progress', 'completed', 'failed', 'aborted') DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    temperature_calibration_passed BOOLEAN NULL,
    impulse_check_passed BOOLEAN NULL,
    pressure_check_passed BOOLEAN NULL,
    final_result ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temperature calibration points table
CREATE TABLE IF NOT EXISTS temperature_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    point_order INT NOT NULL,
    setpoint_temperature DECIMAL(5,2) NOT NULL COMMENT 'Target temperature (-40, +10, +60)',
    mit8_reading DECIMAL(5,2) NULL COMMENT 'Actual reading from MIT-8',
    corrector_reading DECIMAL(5,2) NULL COMMENT 'Reading from corrector',
    stabilization_time_seconds INT NULL,
    tolerance_achieved BOOLEAN DEFAULT FALSE,
    point_written BOOLEAN DEFAULT FALSE,
    written_at TIMESTAMP NULL,
    error_value DECIMAL(5,2) NULL,
    status ENUM('pending', 'stabilizing', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES calibration_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Temperature verification results table
CREATE TABLE IF NOT EXISTS temperature_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    temperature_point DECIMAL(5,2) NOT NULL,
    mit8_etalon DECIMAL(5,2) NOT NULL,
    corrector_value DECIMAL(5,2) NOT NULL,
    error_value DECIMAL(5,2) NOT NULL,
    tolerance_limit DECIMAL(5,2) DEFAULT 0.5,
    is_acceptable BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES calibration_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Impulse check table
CREATE TABLE IF NOT EXISTS impulse_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    impulse_number INT NOT NULL,
    is_success BOOLEAN DEFAULT FALSE,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (session_id) REFERENCES calibration_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_impulse (session_id, impulse_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pressure check points table
CREATE TABLE IF NOT EXISTS pressure_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    point_order INT NOT NULL,
    target_pressure DECIMAL(10,2) NOT NULL,
    corrector_reading DECIMAL(10,2) NULL,
    error_value DECIMAL(10,2) NULL,
    tolerance_limit DECIMAL(10,2) DEFAULT 1.0,
    attempts_count INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    is_acceptable BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    checked_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (session_id) REFERENCES calibration_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session logs table
CREATE TABLE IF NOT EXISTS session_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    log_type ENUM('info', 'warning', 'error', 'action') NOT NULL,
    action_description TEXT NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES calibration_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL UNIQUE,
    report_pdf_path VARCHAR(255) NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    signed_by INT NULL,
    signature_timestamp TIMESTAMP NULL,
    FOREIGN KEY (session_id) REFERENCES calibration_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (signed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user (password: admin123)
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin'),
('operator1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operator One', 'operator');

-- Indexes for performance
CREATE INDEX idx_session_status ON calibration_sessions(status);
CREATE INDEX idx_session_operator ON calibration_sessions(operator_id);
CREATE INDEX idx_temp_points_session ON temperature_points(session_id);
CREATE INDEX idx_pressure_points_session ON pressure_points(session_id);
CREATE INDEX idx_logs_session ON session_logs(session_id);
CREATE INDEX idx_logs_created ON session_logs(created_at);
