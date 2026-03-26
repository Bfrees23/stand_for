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
            // Flush any remaining data
            fflush($this->port);
            fclose($this->port);
            $this->port = null;
        }
        $this->connected = false;
        $this->demoMode = false;
        error_log("Disconnected from serial port: {$this->portName} ({$this->portPath})");
    }

    /**
     * Write data to serial port - REAL HARDWARE ACCESS
     */
    public function write($data) {
        if (!$this->connected) {
            error_log("Serial write failed: Not connected");
            return false;
        }

        try {
            if ($this->port && is_resource($this->port)) {
                $bytes = fwrite($this->port, $data);
                fflush($this->port);
                error_log("Serial WRITE [{$this->portName}]: {$bytes} bytes sent - " . bin2hex($data));
                return $bytes > 0;
            } else {
                error_log("Serial write failed: Port resource is invalid");
                return false;
            }
        } catch (Exception $e) {
            error_log("Serial write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read data from serial port - REAL HARDWARE ACCESS
     */
    public function read($timeout = 1000, $expectedLength = 0) {
        if (!$this->connected) {
            error_log("Serial read failed: Not connected");
            return '';
        }

        try {
            $data = '';
            $startTime = microtime(true);
            $emptyReads = 0;
            $maxEmptyReads = 50; // Prevent infinite loop
            
            while ((microtime(true) - $startTime) * 1000 < $timeout) {
                if ($this->port && is_resource($this->port)) {
                    stream_set_timeout($this->port, 0, 100000); // 100ms timeout per read
                    $char = fread($this->port, 1);
                    
                    if ($char !== false && $char !== '') {
                        $data .= $char;
                        $emptyReads = 0; // Reset counter on successful read
                        
                        if ($expectedLength > 0 && strlen($data) >= $expectedLength) {
                            error_log("Serial READ [{$this->portName}]: Expected length reached ({strlen($data)} bytes)");
                            break;
                        }
                    } else {
                        $emptyReads++;
                        if ($emptyReads >= $maxEmptyReads) {
                            error_log("Serial READ [{$this->portName}]: Too many empty reads, stopping");
                            break;
                        }
                        usleep(10000); // 10ms delay between reads
                    }
                } else {
                    error_log("Serial READ [{$this->portName}]: Port resource invalid");
                    break;
                }
            }

            if ($data !== '') {
                error_log("Serial READ [{$this->portName}]: " . strlen($data) . " bytes received - " . bin2hex($data));
            }
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
        return $this->connected && !$this->demoMode;
    }
    
    /**
     * Check if in demo mode
     */
    public function isDemoMode() {
        return $this->demoMode;
    }

    /**
     * Test connection by sending a test command - REAL HARDWARE TEST
     */
    public function testConnection($deviceType = 'mit8') {
        if (!$this->connected) {
            return ['success' => false, 'message' => 'Not connected to serial port'];
        }
        
        if ($this->demoMode) {
            return [
                'success' => false, 
                'message' => 'Demo mode: No real hardware connected. Check device permissions and connections.',
                'demo_mode' => true
            ];
        }

        // Send device-specific test commands based on device type
        $testCommand = $this->getTestCommand($deviceType);
        error_log("Testing connection to {$deviceType} with command: " . bin2hex($testCommand));
        
        // Write test command
        $writeResult = $this->write($testCommand);
        if (!$writeResult) {
            return ['success' => false, 'message' => 'Failed to send test command'];
        }
        
        // Read response with timeout
        $response = $this->read(2000, 0); // 2 second timeout
        
        if (empty($response)) {
            return [
                'success' => false, 
                'message' => 'No response from device. Check wiring and device power.',
                'device_type' => $deviceType,
                'port' => $this->portName,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Validate response based on device type
        $isValid = $this->validateDeviceResponse($response, $deviceType);
        
        return [
            'success' => $isValid,
            'message' => $isValid ? 'Connection successful' : 'Invalid response from device',
            'device_type' => $deviceType,
            'port' => $this->portName,
            'response' => bin2hex($response),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get test command for specific device type
     */
    private function getTestCommand($deviceType) {
        switch (strtolower($deviceType)) {
            case 'mit8':
                // MIT-8 identification command (adjust based on actual protocol)
                return "?ID\n";
            case 'corrector':
                // Corrector status request
                return "STATUS\n";
            case 'pressure':
                // Pressure calibrator read command
                return "READ?\n";
            default:
                return "?\n";
        }
    }
    
    /**
     * Validate device response
     */
    private function validateDeviceResponse($response, $deviceType) {
        // Basic validation - response should not be empty or contain error codes
        if (empty($response)) {
            return false;
        }
        
        // Check for common error indicators
        $errorPatterns = ['ERROR', 'ERR', 'NACK', "\x15"];
        foreach ($errorPatterns as $pattern) {
            if (strpos(strtoupper($response), $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Read temperature from MIT-8 - REAL HARDWARE ACCESS
     */
    public function readMIT8Temperature($channel = 1) {
        if (!$this->connected || $this->demoMode) {
            error_log("MIT-8 read: Demo mode or not connected, returning simulated value for channel {$channel}");
            $baseTemp = [-40, 10, 60][$channel - 1] ?? 20;
            $variation = (rand(-5, 5) / 10);
            return $baseTemp + $variation;
        }

        // Send command to MIT-8 for temperature reading (adjust protocol as needed)
        // MIT-8 typically uses ASCII commands
        $command = sprintf(":READ%d?\n", $channel);
        error_log("MIT-8: Sending command for channel {$channel}: " . trim($command));
        
        $this->write($command);
        
        // Wait for response with appropriate timeout
        $response = $this->read(1500); // 1.5 second timeout
        
        if (empty($response)) {
            error_log("MIT-8: No response received for channel {$channel}");
            return null;
        }
        
        // Parse response (implementation depends on MIT-8 protocol)
        $temperature = $this->parseMIT8Response($response);
        error_log("MIT-8: Channel {$channel} temperature: {$temperature}°C (raw: " . trim($response) . ")");
        
        return $temperature;
    }

    /**
     * Send calibration point to Corrector - REAL HARDWARE ACCESS
     */
    public function writeCorrectorPoint($temperature) {
        if (!$this->connected) {
            error_log("Corrector: Not connected, demo mode write for {$temperature}°C");
            return false;
        }
        
        if ($this->demoMode) {
            error_log("Corrector: Demo mode, simulating write of {$temperature}°C");
            return true;
        }

        // Send command to corrector (adjust protocol based on device manual)
        $command = sprintf("CAL:TEMP %.2f\n", $temperature);
        error_log("Corrector: Writing calibration point: {$temperature}°C with command: " . trim($command));
        
        $result = $this->write($command);
        
        if ($result) {
            // Wait for acknowledgment
            $response = $this->read(2000);
            error_log("Corrector: Response after write: " . trim($response));
        }
        
        return $result;
    }

    /**
     * Read temperature from Corrector - REAL HARDWARE ACCESS
     */
    public function readCorrectorTemperature() {
        if (!$this->connected) {
            error_log("Corrector: Not connected, returning simulated value");
            return 20.0 + (rand(-5, 5) / 10);
        }
        
        if ($this->demoMode) {
            error_log("Corrector: Demo mode, returning simulated value");
            return 20.0 + (rand(-5, 5) / 10);
        }

        $command = "MEAS:TEMP?\n";
        error_log("Corrector: Reading temperature with command: " . trim($command));
        
        $this->write($command);
        $response = $this->read(1500);
        
        if (empty($response)) {
            error_log("Corrector: No response to temperature read");
            return null;
        }
        
        $temperature = $this->parseCorrectorResponse($response);
        error_log("Corrector: Temperature reading: {$temperature}°C (raw: " . trim($response) . ")");
        
        return $temperature;
    }

    /**
     * Read pressure from Corrector - REAL HARDWARE ACCESS
     */
    public function readCorrectorPressure() {
        if (!$this->connected) {
            error_log("Corrector: Not connected, returning 0");
            return 0.0;
        }
        
        if ($this->demoMode) {
            error_log("Corrector: Demo mode, returning simulated pressure");
            return rand(0, 1000) / 10; // Random 0-100 kPa
        }

        $command = "MEAS:PRES?\n";
        error_log("Corrector: Reading pressure with command: " . trim($command));
        
        $this->write($command);
        $response = $this->read(1500);
        
        if (empty($response)) {
            error_log("Corrector: No response to pressure read");
            return 0.0;
        }
        
        $pressure = floatval(trim($response));
        error_log("Corrector: Pressure reading: {$pressure} kPa (raw: " . trim($response) . ")");
        
        return $pressure;
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
