<?php
	namespace Fawno\Modbus;

	use Fawno\Modbus\ModbusRTU;

	/**
	 * @package Fawno\Modbus
	 *
	*/
	class ModbusDDS238 extends ModbusRTU {
		public const DDS238_REGISTERS = [
			'station' => 'C1',
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

		protected $dds238_response = null;

		public function __construct (string $device = null) {
			$this->dds238_response = null;
			foreach (self::DDS238_REGISTERS as $name => $format) {
				$this->dds238_response .= $format . $name . '/';
			}

			parent::__construct($device);
		}

		public function setTime (int $station = 0x01, int $time = null) {
			$time = $time ?: time();
			$time = unpack('n3', pack('C*', ...explode(';', date('y;m;d;H;i;s', $time))));

			$response = $this->writeMultipleRegisters($station, 0x0012, 3, ...$time);

			return $response;
		}

		public function resetTotalEnergy (int $station = 0x01) {
			$response = $this->writeMultipleRegisters($station, 0x0000, 2, 0, 0);

			return $response;
		}

		public function getData (int $station = 0x01, bool $raw = false) {
			$time = time();

			$response = $this->readHoldingRegisters($station, 0, 21, true);

			if (strlen($response) == 47) {
				if (bin2hex(substr($response, -8, 6)) == '000000000000') {
					$response = substr($response, 0, -8);
					$response .= pack('C*', ...explode(';', date('y;m;d;H;i;s', $time)));
					$response .= $this->crc16($response);
				}

				if (!$raw) {
					$response = $this->parse($response);
				}
			}

			return $response;
		}

		public function parse (string $response) {
			if (strlen($response) != 47) {
				return false;
			}

			$time = unpack('C*', substr($response, -8, 6));
			$time = date('Y-m-d H:i:s', strtotime(sprintf('%s-%s-%s %s:%s:%s', ...$time)));

			$response = unpack($this->dds238_response, $response);

			$data = ['time' => $time];
			foreach (self::DDS238_REGISTERS_SCALE as $key => $scale) {
				$data[$key] = isset($response[$key]) ? $response[$key] * $scale : null;
			}

			return $data;
		}
	}
