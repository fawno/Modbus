<?php
	namespace Fawno\Modbus;

	use Fawno\Modbus\ModbusRTU;

	class ModbusDDS238 {
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

		public function read (bool $raw = false) {
			$data = ['time' => date('Y-m-d H:i:s')];

			$buffer = $this->modbus->requestSend(0x01, 0x03, 0, 18);

			if ($buffer and !$raw) {
				$response = unpack($this->dds238_response, $buffer);

				foreach (self::DDS238_REGISTERS_SCALE as $key => $scale) {
					$data[$key] = isset($response[$key]) ? $response[$key] * $scale : null;
				}

				return $data;
			}

			return $buffer;
		}
	}
