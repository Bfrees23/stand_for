<?php
/**
 * Класс для работы с последовательными портами (COM/USB)
 */

class SerialPort {
    private $port;
    private $handle;
    private $isOpen = false;
    
    /**
     * Конструктор
     * @param string $portName Имя порта (например, COM1 или /dev/ttyUSB0)
     */
    public function __construct($portName) {
        $this->port = $portName;
    }
    
    /**
     * Открыть порт
     * @param int $baudrate Скорость передачи
     * @param string $parity Четность (none, even, odd)
     * @param int $dataBits Биты данных
     * @param int $stopBits Стоповые биты
     * @return bool
     */
    public function open($baudrate = 9600, $parity = 'none', $dataBits = 8, $stopBits = 1) {
        if ($this->isOpen) {
            return true;
        }
        
        // Для Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $mode = "baud={$baudrate} parity={$parity} data={$dataBits} stop={$stopBits} xon=off odsr=off";
            $this->handle = fopen("{$this->port}", 'w+');
            if ($this->handle) {
                dio_setopt($this->handle, DIO_BAUD, $baudrate);
                $this->isOpen = true;
                return true;
            }
        } else {
            // Для Linux
            $this->handle = fopen($this->port, 'w+');
            if ($this->handle) {
                // Настройка порта через system вызов
                $sttyCmd = "stty -F {$this->port} {$baudrate} raw -echo -icanon";
                exec($sttyCmd);
                $this->isOpen = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Закрыть порт
     */
    public function close() {
        if ($this->handle && $this->isOpen) {
            fclose($this->handle);
            $this->isOpen = false;
        }
    }
    
    /**
     * Отправить данные
     * @param string $data Данные для отправки
     * @return bool
     */
    public function write($data) {
        if (!$this->isOpen || !$this->handle) {
            return false;
        }
        
        return fwrite($this->handle, $data) !== false;
    }
    
    /**
     * Прочитать данные
     * @param int $length Количество байт для чтения
     * @param int $timeout Таймаут в миллисекундах
     * @return string|false
     */
    public function read($length = 1024, $timeout = 1000) {
        if (!$this->isOpen || !$this->handle) {
            return false;
        }
        
        // Установка таймаута
        stream_set_timeout($this->handle, 0, $timeout * 1000);
        
        return fread($this->handle, $length);
    }
    
    /**
     * Проверка доступности порта
     * @return bool
     */
    public function isConnected() {
        return $this->isOpen && $this->handle !== null;
    }
    
    /**
     * Очистить буфер
     */
    public function flush() {
        if ($this->handle) {
            fflush($this->handle);
        }
    }
}

/**
 * Драйвер для калибратора температуры МИТ-8
 */
class MIT8Driver {
    private $serial;
    private $channel;
    
    public function __construct($portName) {
        $this->serial = new SerialPort($portName);
    }
    
    /**
     * Подключение к МИТ-8
     * @return bool
     */
    public function connect() {
        return $this->serial->open(9600, 'none', 8, 1);
    }
    
    /**
     * Считать температуру с канала
     * @param int $channel Номер канала (1-8)
     * @return float|false Температура в °C
     */
    public function readTemperature($channel = 1) {
        // Команда для чтения температуры с канала (зависит от протокола МИТ-8)
        $command = "READ:CH{$channel}\r\n";
        $this->serial->write($command);
        usleep(100000); // Ждем 100мс
        
        $response = $this->serial->read(256);
        
        // Парсинг ответа (формат зависит от конкретной модели)
        // Пример ответа: "+25.345°C" или "25.345"
        if (preg_match('/([+-]?\d+\.?\d*)/', $response, $matches)) {
            return (float)$matches[1];
        }
        
        return false;
    }
    
    /**
     * Проверка связи с устройством
     * @return bool
     */
    public function testConnection() {
        $this->serial->write("IDN?\r\n");
        usleep(100000);
        $response = $this->serial->read(256);
        return !empty($response);
    }
    
    /**
     * Закрыть соединение
     */
    public function disconnect() {
        $this->serial->close();
    }
}

/**
 * Драйвер для корректора (тестируемое устройство)
 */
class CorrectorDriver {
    private $serial;
    
    public function __construct($portName) {
        $this->serial = new SerialPort($portName);
    }
    
    /**
     * Подключение к корректору
     * @param int $baudrate Скорость (по умолчанию 19200)
     * @return bool
     */
    public function connect($baudrate = 19200) {
        return $this->serial->open($baudrate, 'none', 8, 1);
    }
    
    /**
     * Записать точку калибровки температуры
     * @param float $temperature Температура для записи
     * @param string $pointType Тип точки (-40, +10, +60)
     * @return bool
     */
    public function writeCalibrationPoint($temperature, $pointType) {
        // Формат команды зависит от протокола корректора
        $command = sprintf("CAL:TEMP:%s:%.3f\r\n", $pointType, $temperature);
        return $this->serial->write($command);
    }
    
    /**
     * Считать текущую температуру с датчиков корректора
     * @return float|false
     */
    public function readTemperature() {
        $this->serial->write("READ:TEMP?\r\n");
        usleep(100000);
        
        $response = $this->serial->read(256);
        
        if (preg_match('/([+-]?\d+\.?\d*)/', $response, $matches)) {
            return (float)$matches[1];
        }
        
        return false;
    }
    
    /**
     * Считать показания датчика давления
     * @return float|false Давление в кПа
     */
    public function readPressure() {
        $this->serial->write("READ:PRES?\r\n");
        usleep(100000);
        
        $response = $this->serial->read(256);
        
        if (preg_match('/([+-]?\d+\.?\d*)/', $response, $matches)) {
            return (float)$matches[1];
        }
        
        return false;
    }
    
    /**
     * Проверка связи
     * @return bool
     */
    public function testConnection() {
        $this->serial->write("*IDN?\r\n");
        usleep(100000);
        $response = $this->serial->read(256);
        return !empty($response);
    }
    
    /**
     * Закрыть соединение
     */
    public function disconnect() {
        $this->serial->close();
    }
}

/**
 * Драйвер для калибратора давления
 */
class PressureCalibratorDriver {
    private $serial;
    private $type;
    private $rangeMin;
    private $rangeMax;
    
    public function __construct($portName, $type = 'absolute', $rangeMin = 0, $rangeMax = 1000) {
        $this->serial = new SerialPort($portName);
        $this->type = $type;
        $this->rangeMin = $rangeMin;
        $this->rangeMax = $rangeMax;
    }
    
    /**
     * Подключение
     * @return bool
     */
    public function connect() {
        return $this->serial->open(9600, 'none', 8, 1);
    }
    
    /**
     * Считать текущее давление
     * @return float|false Давление в кПа
     */
    public function readPressure() {
        $this->serial->write("MEAS:PRESS?\r\n");
        usleep(100000);
        
        $response = $this->serial->read(256);
        
        if (preg_match('/([+-]?\d+\.?\d*)/', $response, $matches)) {
            return (float)$matches[1];
        }
        
        return false;
    }
    
    /**
     * Получить точки поверки в зависимости от типа датчика
     * @return array Массив точек в кПа
     */
    public function getCheckpoints() {
        $range = $this->rangeMax - $this->rangeMin;
        
        if ($this->type === 'absolute') {
            // 5 точек: 0%, 25%, 50%, 75%, 100%
            $percentages = [0, 25, 50, 75, 100];
        } else {
            // 3 точки: 0%, 50%, 100%
            $percentages = [0, 50, 100];
        }
        
        $points = [];
        foreach ($percentages as $pct) {
            $points[] = $this->rangeMin + ($range * $pct / 100);
        }
        
        return $points;
    }
    
    /**
     * Проверка связи
     * @return bool
     */
    public function testConnection() {
        $this->serial->write("*IDN?\r\n");
        usleep(100000);
        $response = $this->serial->read(256);
        return !empty($response);
    }
    
    /**
     * Закрыть соединение
     */
    public function disconnect() {
        $this->serial->close();
    }
}
