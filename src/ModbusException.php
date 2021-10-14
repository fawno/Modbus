<?php
  namespace Fawno\Modbus;

  use \Throwable;
  use \Exception;

  class ModbusException extends Exception {
    public const EXCEPTION_CODES = [
      0x00 => 'Undefined failure code',
      0x01 => 'Illegal function',
      0x02 => 'Illegal data address',
      0x03 => 'Illegal data value',
      0x04 => 'Server device failure',
      0x05 => 'Acknowledge',
      0x06 => 'Server device busy',
      0x08 => 'Memory parity error',
      0x0A => 'Gateway path unavailable',
      0x0B => 'Gateway target device failed to respond',
    ];

    private $previous = null;
    protected $request = null;
    protected $response = null;

    public function __construct (string $message = null, int $code = 0, string $request = null, string $response = null, Throwable $previous = null) {
      $this->previous = !is_null($previous) ? $previous : null;
      $this->request = !is_null($request) ? bin2hex($request) : null;
      $this->response = !is_null($response) ? bin2hex($response) : null;

      if (empty($message) and $code != 0) {
        $message = empty(self::EXCEPTION_CODES[$code]) ? self::EXCEPTION_CODES[0x00] : self::EXCEPTION_CODES[$code];
      }

      parent::__construct($message, $code, $previous);
    }

    public function getRequest () {
      return $this->request;
    }

    public function getResponse () {
      return $this->response;
    }

    public function __toString () {
      $output = '';

      if ($this->previous) {
        $output .= $this->previous . "\n" . 'Next ';
      }

      $output .= sprintf('%s: %s in %s:%s', get_class($this), $this->message, $this->file, $this->line) . "\n";

      if ($this->request !== null) {
        $output .= 'Request: "' . $this->request . '"' . "\n";
      }

      if ($this->response !== null) {
        $output .= 'Response: "' . $this->response . '"' . "\n";
      }

      $trace = $this->getTraceAsString();
      if ($trace) {
        $output .= 'Stack trace:' . "\n" . $trace . "\n";
      }

      return $output;
    }
  }
