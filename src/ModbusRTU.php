<?php
	namespace Fawno\Modbus;

	use \ErrorException;

	class ModbusRTU {
		private const MODBUS_REQUEST = 'C2n2';
		public const MODBUS_RESPONSE = 'C1station/C1function/n1address/n1records/';
		public const MODBUS_ERROR = 'C1station/C1error/C1exception/';

    public const SERIAL_DATA_RATES = [75, 110, 134, 150, 300, 600, 1200, 1800, 2400, 4800, 7200, 9600, 14400, 19200, 38400, 57600, 115200, 56000, 128000, 256000];
		public const SERIAL_DATA_BITS = [8, 7, 6, 5];
		public const SERIAL_STOP_BITS = [1, 2];
		public const SERIAL_PARITY = [0, 1, 2];
		public const SERIAL_FLOW_CONTROL = [0, 1];
		public const SERIAL_CANONICAL = [0, 1];

		private $_serial = null;
		protected $_device = null;
		protected $_options = [
			'data_rate' => 9600,
			'data_bits' => 8,
			'stop_bits' => 1,
			'parity' => 0,
			'flow_control' => 0,
			'is_canonical' => 1,
		];

		public function __construct () {
			register_shutdown_function([$this, 'close']);
		}

		public function setDevice (string $device) {
			$this->_device = $device;
		}

		public function setDataRate (int $data_rate = 9600) {
			if (!in_array($data_rate, self::SERIAL_DATA_RATES)) {
				throw new ErrorException(sprintf('invalid data_rate value (%d)', $data_rate), 0, E_USER_WARNING);
			}

			$this->_options['data_rate'] = $data_rate;
			return true;
		}

		public function setParity (int $parity = 0) {
			if (!in_array($parity, self::SERIAL_PARITY)) {
				throw new ErrorException(sprintf('invalid parity value (%d)', $parity), 0, E_USER_WARNING);
			}

			$this->_options['parity'] = $parity;
			return true;
		}

		public function setDataBits (int $data_bits) {
			if (!in_array($data_bits, self::SERIAL_DATA_BITS)) {
				throw new ErrorException(sprintf('invalid data_bits value (%d)', $data_bits), 0, E_USER_WARNING);
			}

			$this->_options['data_bits'] = $data_bits;
			return true;
		}

		public function setStopBits (int $stop_bits) {
			if (!in_array($stop_bits, self::SERIAL_STOP_BITS)) {
				throw new ErrorException(sprintf('invalid stop_bits value (%d)', $stop_bits), 0, E_USER_WARNING);
			}

			$this->_options['stop_bits'] = $stop_bits;
			return true;
		}

		public function setFlowControl (int $flow_control) {
			if (!in_array($flow_control, self::SERIAL_FLOW_CONTROL)) {
				throw new ErrorException(sprintf('invalid flow_control value (%d)', $flow_control), 0, E_USER_WARNING);
			}

			$this->_options['flow_control'] = $flow_control;
			return true;
		}

		public function setCanonical (int $canonical) {
			if (!in_array($canonical, self::SERIAL_CANONICAL)) {
				throw new ErrorException(sprintf('invalid flow_control value (%d)', $canonical), 0, E_USER_WARNING);
			}

			$this->_options['is_canonical'] = $canonical;
			return true;
		}

		public function open (string $mode = 'r+b') {
			if (is_resource($this->_serial)) {
				throw new ErrorException('The device is already opened', 0, E_USER_WARNING);
			}

			if (empty($this->_device)) {
				throw new ErrorException('The device must be set before to be open', 0, E_USER_WARNING);
			}

			if (!preg_match('~^[raw]\+?b?$~', $mode)) {
				throw new ErrorException(sprintf('Invalid opening mode: %s. Use fopen() modes.', $mode), 0, E_USER_WARNING);
			}

			$this->_serial = @dio_serial($this->_device, $mode, $this->_options);

			if (!is_resource($this->_serial)) {
				throw new ErrorException(sprintf('Unable to open the device %s', $this->_device), 0, E_USER_WARNING);
			}

			$meta = stream_get_meta_data($this->_serial);

			if ($meta['blocked'] and !stream_set_blocking($this->_serial, false)) {
				throw new ErrorException('Unable to set unblocking mode on device', 0, E_USER_WARNING);
			}

			fwrite($this->_serial, hex2bin('000300150001941f'));
			usleep(100000);
			$buffer = stream_get_contents($this->_serial);

			return true;
		}

		public function close () {
			if (is_resource($this->_serial)) {
				if (!fclose($this->_serial)) {
					throw new ErrorException('Unable to close the device', 0, E_USER_WARNING);
				}
			}

			$this->_serial = null;
			return true;
		}

		public function send (string $message, float $wait = 0.1) {
			if (!is_resource($this->_serial)) {
				throw new ErrorException('Device must be opened to read it', 0, E_USER_WARNING);
			}

			if (fwrite($this->_serial, $message) === false) {
				throw new ErrorException('Error while sending message', 0, E_USER_WARNING);
			}

			usleep((int) ($wait * 1000000));

			return true;
		}

		public function read (int $length = -1) {
			if (!is_resource($this->_serial)) {
				throw new ErrorException('Device must be opened to read it', 0, E_USER_WARNING);
			}

			$buffer = '';
			do {
				$buffer .= stream_get_contents($this->_serial);
			} while (strlen($buffer) > 2 and substr($buffer, -2) != $this->crc16(substr($buffer, 0, -2)));

			return $buffer;
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

			if (strlen($buffer) > 4) {
				if (substr($buffer, 3, 2) == $this->crc16(substr($buffer, 0, 3))) {
					return substr($buffer, 0, 5);
				}
			}

			return false;
		}
	}
