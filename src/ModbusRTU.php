<?php
	namespace Fawno\Modbus;

	use Fawno\Modbus\SerialConnection;

	class ModbusRTU {
		private const MODBUS_REQUEST = 'C2n2';

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
			$message .= $this->crc16($message);

			$this->serial->send($message, true);

			$buffer = $this->serial->readPort();
			if (strlen($buffer) > 2 and substr($buffer, -2) == $this->crc16(substr($buffer, 0, -2))) {
				if (strlen($buffer) > 5) {
					return $buffer;
				} else {
					echo bin2hex($buffer), PHP_EOL;
					return false;
				}
			}

			return false;
		}
	}
