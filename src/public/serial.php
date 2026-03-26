<?php
/**
 * SerialPort — обёртка для php_serial
 */
class SerialPort {
    private $serial;
    private $port;
    private $baudRate = 9600;
    private $parity = 'none';
    private $dataBits = 8;
    private $stopBits = 1;

    public function __construct($port = null) {
        $this->serial = new phpSerial();
        if ($port) {
            $this->port = $port;
        }
    }

    public function connect($port = null, $baud = 9600, $parity = 'none') {
        if ($port) $this->port = $port;
        (!$this->port) return false;

        $this->serial->deviceSet($this->port);
        $this->serial->confBaudRate($baud);
        $this->serial->confParity($parity);
        $this->serial->confCharacter(8, 1, 'none');

        $result = $this->serial->deviceOpen();
        return $result === true;
    }

    public function disconnect() {
        $this->serial->deviceClose();
    }

    public function write($data) {
        return $this->serial->sendMessage($data);
    }

    public function read($length = 100) {
        return $this->serial->readPort($length);
    }

    public function readLine($timeout = 2) {
        $line = '';
        $start = time();
        while (time() - $start < $timeout) {
            $char = $this->serial->readPort(1);
            if ($char === false || $char === '') continue;
            if ($char === "\r" || $char === "\n") break;
            $line .= $char;
        }
        return trim($line);
    }

    public function flush() {
        $this->serial->flush();
    }
}