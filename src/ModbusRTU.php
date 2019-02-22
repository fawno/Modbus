<?php
	namespace Fawno\Modbus;

	use SerialConnection\SerialConnection;

	class ModbusRTU {
		private const MODBUS_REQUEST = 'C2n2';
		public const MODBUS_RESPONSE = 'C1station/C1function/n1address/n1records/';
		public const MODBUS_ERROR = 'C1station/C1error/C1exception/';

		private $serial = null;

		public function __construct () {
			return $this->serial = new SerialConnection;
		}

		public function setDevice (string $device) {
			return $this->serial->setDevice($device);
		}

		public function setBaudRate (int $rate) {
			return $this->serial->setBaudRate($rate);
		}

		public function setParity (string $parity) {
			return $this->serial->setParity($parity);
		}

		public function setCharacterLength (int $int) {
			return $this->serial->setCharacterLength($int);
		}

		public function setStopBits (int $length) {
			return $this->serial->setStopBits($length);
		}

		public function setFlowControl (string $mode) {
			return $this->serial->setFlowControl($mode);
		}

		public function open () {
			return $this->serial->open();
		}

		public function close () {
			return $this->serial->close();
		}

		public function crc16 ($data) {
			$crc = 0xFFFF;
			foreach (unpack('C*', $data) as $byte) {
				$crc ^= $byte;
				for ($j = 8; $j; $j--) {
					$crc = ($crc >> 1) ^ (($crc & 0x0001) * 0xA001);
				}
			}

			return pack('v1', $crc);
		}

		public function requestSend (int $station, int $function, int $address, int $records) {
			$message = pack(self::MODBUS_REQUEST, $station, $function, $address, $records);

			if ($function == 0x10) {
				if (func_num_args() != (4 + $records)) {
					return false;
				}
				$message .= pack('C1n*', 2 * $records, ...array_slice(func_get_args(), 4));
			}

			$message .= $this->crc16($message);

			$this->serial->send($message, true);

			$buffer = $this->serial->readPort();
			if (strlen($buffer) > 2 and substr($buffer, -2) == $this->crc16(substr($buffer, 0, -2))) {
				if (strlen($buffer) > 5) {
					return $buffer;
				} else {
					return false;
				}
			}

			if (strlen($buffer) > 4) {
				if (substr($buffer, 3, 2) == $this->crc16(substr($buffer, 0, 3))) {
					return substr($buffer, 0, 5);
				}
			}

			return false;
		}
	}
