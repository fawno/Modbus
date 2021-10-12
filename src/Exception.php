<?php
	namespace Fawno\Modbus;

	use \Throwable;
	use \RuntimeException;

	class Exception extends RuntimeException {
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

		protected $_defaultMessage = '';
		protected $_defaultCode = 0x00;
		protected $request = null;
		protected $response = null;

		public function __construct (string $message = null, int $code = 0, string $request = null, string $response = null, Throwable $previous = null) {
			$message = $message ?: $this->_defaultMessage;
			$code = $code ?: $this->_defaultCode;
			$this->request = bin2hex($request);
			$this->response = bin2hex($response);

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
	}