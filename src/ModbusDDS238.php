<?php
	namespace Fawno\Modbus;

	use Fawno\Modbus\ModbusRTU;

	class ModbusDDS238 {
		public const MODBUS_RESPONSE = ModbusRTU::MODBUS_RESPONSE;
		public const MODBUS_ERROR = ModbusRTU::MODBUS_ERROR;

		public const DDS238_REGISTERS = [
			'address' => 'C1',
			'function' => 'C1',
			'count' => 'C1',
			'total_energy' => 'N1',
			'reserved' => 'N3',
			'export_energy' => 'N1',
			'import_energy' => 'N1',
			'voltage' => 'n1',
			'current' => 'n1',
			'active_power' => 'n1',
			'reactive_power' => 'n1',
			'power_factor' => 'n1',
			'frecuency' => 'n1',
			'year' => 'C1',
			'month' => 'C1',
			'day' => 'C1',
			'hour' => 'C1',
			'minute' => 'C1',
			'second' => 'C1',
		];

		public const DDS238_REGISTERS_SCALE = [
			'total_energy' => 1/100,	// daWh / 100 => kWh
			'export_energy' => 1/100,	// daWh / 100 => kWh
			'import_energy' => 1/100,	// daWh / 100 => kWh
			'voltage' => 1/10,				// dV / 10 => V
			'current' => 1/100,				// cA / 100 => A
			'active_power' => 1,			// W
			'reactive_power' => 1,		// VA
			'power_factor' => 1/1000,
			'frecuency' => 1/100,			// cHz / 100 => Hz
		];

		private $modbus = null;
		protected $dds238_response = null;

		public function __construct () {
			$this->modbus = new ModbusRTU;

			$this->dds238_response = null;
			foreach (self::DDS238_REGISTERS as $name => $format) {
				$this->dds238_response .= $format . $name . '/';
			}
			$this->dds238_response .= 'v1crc/';
		}

		public function setDevice (string $device) {
			$this->modbus->setDevice('COM4');
			$this->modbus->setBaudRate(9600);
			$this->modbus->setParity('none');
			$this->modbus->setCharacterLength(8);
			$this->modbus->setStopBits(1);
			$this->modbus->setFlowControl('none');
		}

		public function open () {
			return $this->modbus->open();
		}

		public function close () {
			return $this->modbus->close();
		}

		public function crc16 (string $data) {
			return $this->modbus->crc16($data);
		}

		public function setTime (int $time = null) {
			$time = $time ?: time();
			$time = unpack('n3', pack('C*', ...explode(';', date('y;m;d;H;i;s', $time))));
			$buffer = $this->modbus->requestSend(0x01, 0x10, 0x0012, 3, ...$time);

			return $buffer;
		}

		public function resetTotalEnergy () {
			$buffer = $this->modbus->requestSend(0x01, 0x10, 0x0000, 2, 0, 0);

			return $buffer;
		}

		public function read (bool $raw = false) {
			$time = time();

			$buffer = $this->modbus->requestSend(0x01, 0x03, 0, 21);

			if (strlen($buffer) == 47) {
				if (bin2hex(substr($buffer, -8, 6)) == '000000000000') {
					$buffer = substr($buffer, 0, -8);
					$buffer .= pack('C*', ...explode(';', date('y;m;d;H;i;s', $time)));
					$buffer .= $this->crc16($buffer);
				}

				if (!$raw) {
					$buffer = $this->parse($buffer);
				}
			}

			return $buffer;
		}

		public function parse (string $buffer) {
			if (strlen($buffer) != 47) {
				return false;
			}

			$response = unpack($this->dds238_response, $buffer);
			$time = unpack('C*', substr($buffer, -8, 6));
			$time = date('Y-m-d H:i:s', strtotime(sprintf('%s-%s-%s %s:%s:%s', ...$time)));

			$data = ['time' => $time];
			foreach (self::DDS238_REGISTERS_SCALE as $key => $scale) {
				$data[$key] = isset($response[$key]) ? $response[$key] * $scale : null;
			}

			return $data;
		}
	}
