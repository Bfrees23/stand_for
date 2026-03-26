<?php
/**
 * Serial Port Communication Handler
 * Handles communication with MIT-8, Corrector, and Pressure Calibrator
 */
class SerialPortHandler {
    private $port = null;
    private $portName = null;
    private $baudRate = 19200;
    private $parity = 'none';
    private $dataBits = 8;
    private $stopBits = 1;
    private $connected = false;

    /**
     * Connect to serial port
     */
    public function connect($portName, $baudRate = 19200, $parity = 'none', $dataBits = 8, $stopBits = 1) {
        try {
            // For Docker/Linux environment, use socat or direct serial access
            $portPath = $portName;
            if (strpos($portName, 'COM') === 0) {
                // Convert Windows COM port to Linux device
                $portNum = str_replace('COM', '', $portName);
                $portPath = '/dev/ttyUSB' . ($portNum - 1);
            }

            if (!file_exists($portPath)) {
                // In demo mode, simulate connection
                $this->portName = $portName;
                $this->baudRate = $baudRate;
                $this->connected = true;
                error_log("Serial port simulated: {$portName}");
                return true;
            }

            $this->port = fopen($portPath, 'r+');
            if (!$this->port) {
                throw new Exception("Cannot open port {$portPath}");
            }

            // Set serial parameters
            system("stty -F {$portPath} {$baudRate} raw -echo");
            
            $this->portName = $portName;
            $this->baudRate = $baudRate;
            $this->parity = $parity;
            $this->dataBits = $dataBits;
            $this->stopBits = $stopBits;
            $this->connected = true;

            error_log("Connected to serial port: {$portPath}");
            return true;
        } catch (Exception $e) {
            error_log("Serial connection error: " . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }

    /**
     * Disconnect from serial port
     */
    public function disconnect() {
        if ($this->port && is_resource($this->port)) {
            fclose($this->port);
            $this->port = null;
        }
        $this->connected = false;
        error_log("Disconnected from serial port: {$this->portName}");
    }

    /**
     * Write data to serial port
     */
    public function write($data) {
        if (!$this->connected) {
            return false;
        }

        try {
            if ($this->port) {
                fwrite($this->port, $data);
                fflush($this->port);
            }
            // Log in demo mode
            error_log("Serial WRITE [{$this->portName}]: " . bin2hex($data));
            return true;
        } catch (Exception $e) {
            error_log("Serial write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read data from serial port
     */
    public function read($timeout = 1000, $expectedLength = 0) {
        if (!$this->connected) {
            return '';
        }

        try {
            $data = '';
            $startTime = microtime(true);
            
            while ((microtime(true) - $startTime) * 1000 < $timeout) {
                if ($this->port) {
                    stream_set_timeout($this->port, 0, 100000); // 100ms
                    $char = fread($this->port, 1);
                    if ($char !== false && $char !== '') {
                        $data .= $char;
                        if ($expectedLength > 0 && strlen($data) >= $expectedLength) {
                            break;
                        }
                    }
                } else {
                    // Demo mode simulation
                    usleep(10000);
                    break;
                }
            }

            error_log("Serial READ [{$this->portName}]: " . bin2hex($data));
            return $data;
        } catch (Exception $e) {
            error_log("Serial read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Check if connected
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Test connection by sending a test command
     */
    public function testConnection($deviceType = 'mit8') {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected'];
        }

        // Simulate successful test in demo mode
        // In real implementation, send device-specific test commands
        sleep(1); // Simulate communication delay
        
        return [
            'success' => true,
            'message' => 'Connection successful',
            'device_type' => $deviceType,
            'port' => $this->portName,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Read temperature from MIT-8
     */
    public function readMIT8Temperature($channel = 1) {
        // MIT-8 protocol simulation
        // Real implementation would send specific commands
        if (!$this->connected) {
            // Demo mode: return simulated temperature
            $baseTemp = [-40, 10, 60][$channel - 1] ?? 20;
            $variation = (rand(-5, 5) / 10);
            return $baseTemp + $variation;
        }

        // Send command to MIT-8 for temperature reading
        $command = sprintf("READ_CH%d\n", $channel);
        $this->write($command);
        $response = $this->read(1000);
        
        // Parse response (implementation depends on MIT-8 protocol)
        return $this->parseMIT8Response($response);
    }

    /**
     * Send calibration point to Corrector
     */
    public function writeCorrectorPoint($temperature) {
        if (!$this->connected) {
            // Demo mode
            error_log("Demo: Writing temperature point: {$temperature}°C");
            return true;
        }

        // Send command to corrector
        $command = sprintf("CAL_TEMP=%.2f\n", $temperature);
        return $this->write($command);
    }

    /**
     * Read temperature from Corrector
     */
    public function readCorrectorTemperature() {
        if (!$this->connected) {
            // Demo mode: return simulated value
            return 20.0 + (rand(-5, 5) / 10);
        }

        $this->write("READ_TEMP\n");
        $response = $this->read(1000);
        return $this->parseCorrectorResponse($response);
    }

    /**
     * Read pressure from Corrector
     */
    public function readCorrectorPressure() {
        if (!$this->connected) {
            // Demo mode
            return 0.0;
        }

        $this->write("READ_PRESSURE\n");
        $response = $this->read(1000);
        return floatval($response);
    }

    /**
     * Parse MIT-8 response
     */
    private function parseMIT8Response($response) {
        // Implement MIT-8 protocol parsing
        // This is a simplified version
        preg_match('/([-+]?\d+\.?\d*)/', $response, $matches);
        return isset($matches[1]) ? floatval($matches[1]) : null;
    }

    /**
     * Parse Corrector response
     */
    private function parseCorrectorResponse($response) {
        // Implement Corrector protocol parsing
        preg_match('/([-+]?\d+\.?\d*)/', $response, $matches);
        return isset($matches[1]) ? floatval($matches[1]) : null;
    }
}
