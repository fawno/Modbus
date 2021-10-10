<?php
	namespace Fawno\Modbus;

	use \ErrorException;
	use Fawno\PhpSerial\SerialDio;

	class ModbusRTU extends SerialDio {
		private const MODBUS_REQUEST = 'C2n2';
		public const MODBUS_RESPONSE = 'C1station/C1function/n1address/n1records/';
		public const MODBUS_ERROR = 'C1station/C1error/C1exception/';

		protected $_options = [
			'data_rate' => 9600,
			'data_bits' => 8,
			'stop_bits' => 1,
			'parity' => 0,
			'flow_control' => 0,
			'is_canonical' => 1,
		];

		public function open (string $mode = 'r+b') {
			parent::open('r+b');

			$this->setBlocking(0);
			$this->setTimeout(0, 100000);

			$this->sync();
		}

		public function sync () {
			$this->send(hex2bin('000300150001941f'));
			return $this->read();
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

			$this->send($message);

			$buffer = $this->read();
			if (strlen($buffer) > 2 and substr($buffer, -2) == $this->crc16(substr($buffer, 0, -2))) {
				if (strlen($buffer) > 5) {
					return $buffer;
				} else {
					return false;
				}
			}

			if (strlen($buffer) > 4 and substr($buffer, 3, 2) == $this->crc16(substr($buffer, 0, 3))) {
				return substr($buffer, 0, 5);
			}

			return false;
		}
	}
