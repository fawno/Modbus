<?php
	namespace SerialConnection;

	/**
	 * Serial port control class
	 *
	 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTIES !
	 * USE IT AT YOUR OWN RISKS !
	 *
	 * @author Rémy Sanchez <remy.sanchez@hyperthese.net>
	 * @author Rizwan Kassim <rizwank@geekymedia.com>
	 * @thanks Aurélien Derouineau for finding how to open serial ports with windows
	 * @thanks Alec Avedisyan for help and testing with reading
	 * @thanks Jim Wright for OSX cleanup/fixes.
	 * @copyright under GPL 2 licence
	 */
	class SerialConnection {
		const SERIAL_DEVICE_NOTSET = 0;
		const SERIAL_DEVICE_SET    = 1;
		const SERIAL_DEVICE_OPENED = 2;

    const	SERIAL_BAUD_RATES = [110, 150, 300, 600, 1200, 2400, 4800, 9600, 19000, 38400, 57600, 115200];

    const	STTY = [
			'Linux' => 'stty -F %s',
			'Darwin' => 'stty -f %s',
			'Windows' => 'mode %s',
		];

		protected $_device = null;
		protected $_dAttr = ['baud' => 9600, 'data' => 8, 'stop' => 1, 'parity' => 'none', 'flowcontrol' => 'none'];
		protected $_dHandle = null;
		protected $_dState = self::SERIAL_DEVICE_NOTSET;
		protected $_buffer = null;
		protected $_os = null;
		protected $autoFlush = true;

    /**
     * Constructor. Perform some checks about the OS and setserial
     *
     * @return SerialConnection
     */
		public function __construct () {
			// Parse php_uname string and get only certain OS names:
			$this->_os = preg_replace('~^(Linux|Darwin|Windows).*~', '$1', php_uname());

			if ($this->_os == php_uname()) {
				trigger_error('Host OS is neither osx, linux nor windows, unable to run.', E_USER_ERROR);

				return false;
			}

			if ($this->_stty() !== 0) {
				trigger_error('No stty availible, unable to run.', E_USER_ERROR);

				return false;
			}

			register_shutdown_function([$this, 'close']);
		}

		/**
		 * Opens the device for reading and/or writing.
		 *
		 * @param  string $mode Opening mode : same parameter as fopen()
		 * @return bool
		 */
		public function open ($mode = 'r+b') {
			if ($this->_dState === self::SERIAL_DEVICE_OPENED) {
				trigger_error('The device is already opened', E_USER_NOTICE);

				return true;
			}

			if ($this->_dState === self::SERIAL_DEVICE_NOTSET) {
				trigger_error('The device must be set before to be open', E_USER_WARNING);

				return false;
			}

			if (!preg_match('~^[raw]\+?b?$~', $mode)) {
				trigger_error('Invalid opening mode : ' . $mode . '. Use fopen() modes.', E_USER_WARNING);

				return false;
			}

			$this->_dHandle = @fopen($this->_device, $mode);

			if ($this->_dHandle !== false) {
				stream_set_blocking($this->_dHandle, 0);
				$this->_dState = self::SERIAL_DEVICE_OPENED;

				return true;
			}

			$this->_dHandle = null;
			trigger_error('Unable to open the device', E_USER_WARNING);

			return false;
		}

		/**
		 * Closes the device
		 *
		 * @return bool
		 */
		public function close () {
			if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
				return true;
			}

			if (fclose($this->_dHandle)) {
				$this->_dHandle = null;
				$this->_dState = self::SERIAL_DEVICE_SET;

				return true;
			}

			trigger_error('Unable to close the device', E_USER_ERROR);

			return false;
		}

		/**
		 * Device set function : used to set the device name/address.
		 * -> linux : use the device address, like /dev/ttyS0
		 * -> osx : use the device address, like /dev/tty.serial
		 * -> windows : use the COMxx device name, like COM1 (can also be used
		 *     with linux)
		 *
		 * @param  string $device the name of the device to be used
		 * @return bool
		 */
		public function setDevice (string $device) {
			if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
				switch ($this->_os) {
					case 'Linux':
					case 'Darwin':
						if (preg_match('~^COM(\d+):?$~i', $device, $matches)) {
							$device = '/dev/ttyS' . ($matches[1] - 1);
						}

						break;
					case 'Windows':
						$device = preg_replace('~^COM(\d+):?$~i', 'COM$1', $device);
						if (preg_match('~^/dev/ttyS(\d+)$~i', $device, $matches)) {
							$device = 'COM' . ($matches[1] + 1);
						}

						break;
				}

				if ($this->_stty($device) === 0) {
					$this->_device = $device;
					$this->_dState = self::SERIAL_DEVICE_SET;

					return true;
				}

				trigger_error('Specified serial port is not valid', E_USER_WARNING);

				return false;
			} else {
				trigger_error('You must close your device before to set an other one', E_USER_WARNING);

				return false;
			}
		}

		/**
		 * Configure the Baud Rate
		 * Possible rates : 110, 150, 300, 600, 1200, 2400, 4800, 9600, 38400,
		 * 57600 and 115200.
		 *
		 * @param  int  $rate the rate to set the port in
		 * @return bool
		 */
		public function setBaudRate (int $rate) {
			if ($this->_dState !== self::SERIAL_DEVICE_SET) {
				trigger_error('Unable to set the baud rate : the device is either not set or opened', E_USER_WARNING);

				return false;
			}

			if (in_array($rate, self::SERIAL_BAUD_RATES)) {
				$this->_dAttr['baud'] = $rate;

				if ($this->_stty($this->_device, $this->_dAttr, $out) !== 0) {
					trigger_error('Unable to set baud rate: ' . $out[1], E_USER_WARNING);

					return false;
				}

				return true;
			} else {
				return false;
			}
		}

		/**
		 * Configure parity.
		 * Modes : odd, even, none
		 *
		 * @param  string $parity one of the modes
		 * @return bool
		 */
		public function setParity (string $parity) {
			if ($this->_dState !== self::SERIAL_DEVICE_SET) {
				trigger_error('Unable to set parity : the device is either not set or opened', E_USER_WARNING);

				return false;
			}

			if (!preg_match('~^none|odd|even$~i', $parity)) {
				trigger_error('Parity mode not supported', E_USER_WARNING);

				return false;
			}

			$this->_dAttr['parity'] = strtolower($parity);

			if ($this->_stty($this->_device, $this->_dAttr, $out) !== 0) {
				trigger_error('Unable to set parity: ' . $out[1], E_USER_WARNING);

				return false;
			}

			return true;
		}

		/**
		 * Sets the length of a character.
		 *
		 * @param  int  $int length of a character (5 <= length <= 8)
		 * @return bool
		 */
		public function setCharacterLength (int $int) {
			if ($this->_dState !== self::SERIAL_DEVICE_SET) {
				trigger_error('Unable to set length of a character : the device is either not set or opened', E_USER_WARNING);

				return false;
			}

			$int = ($int < 5) ? 5 : ($int > 8) ? 8 : $int;
			$this->_dAttr['data'] = $int;

			if ($this->_stty($this->_device, $this->_dAttr, $out) !== 0) {
				trigger_error('Unable to set character length: ' . $out[1], E_USER_WARNING);

				return false;
			}

			return true;
		}

		/**
		 * Sets the length of stop bits.
		 *
		 * @param  int $length the length of a stop bit. It must be either 1 or 2.
		 * @return bool
		 */
		public function setStopBits (int $length) {
			if ($this->_dState !== self::SERIAL_DEVICE_SET) {
				trigger_error('Unable to set the length of a stop bit : the device is either not set or opened', E_USER_WARNING);

				return false;
			}

			$length = ($length < 1) ? 1 : ($length > 2) ? 2 : $length;
			$this->_dAttr['stop'] = $length;

			if ($this->_stty($this->_device, $this->_dAttr, $out) !== 0) {
				trigger_error('Unable to set stop bit length: ' . $out[1], E_USER_WARNING);

				return false;
			}

			return true;
		}

		/**
		 * Configures the flow control
		 *
		 * @param  string $mode Set the flow control mode. Availible modes :
		 *                      -> "none" : no flow control
		 *                      -> "rts/cts" : use RTS/CTS handshaking
		 *                      -> "xon/xoff" : use XON/XOFF protocol
		 * @return bool
		 */
		public function setFlowControl (string $mode) {
			if ($this->_dState !== self::SERIAL_DEVICE_SET) {
				trigger_error('Unable to set flow control mode : the device is either not set or opened', E_USER_WARNING);

				return false;
			}

			if (!preg_match('~^none|rts/cts|xon/xoff~i', $mode)) {
				trigger_error('Invalid flow control mode specified', E_USER_WARNING);

				return false;
			}

			$this->_dAttr['flowcontrol'] = strtolower($mode);

			if ($this->_stty($this->_device, $this->_dAttr, $out) !== 0) {
				trigger_error('Unable to set flow control: ' . $out[1], E_USER_WARNING);

				return false;
			}

			return true;
		}

		/**
		 * Set if buffer should be flushed by sendMessage (true) or manually (false)
		 *
		 * @var bool
		 */
		public function setAutoFlush (bool $flush = true) {
			$this->autoFlush = (bool) $flush;
		}

		/**
		 * Sends a string to the device
		 *
		 * @param string $str          string to be sent to the device
		 * @param float  $waitForReply time to wait for the reply (in seconds)
		 */
		public function send (string $message, float $waitForReply = 0.1) {
			$this->_buffer .= $message;

			if ($this->autoFlush === true) {
				$this->flush();
			}

			usleep((int) ($waitForReply * 1000000));
		}

		/**
		 * Reads the port until no new datas are availible, then return the content.
		 *
		 * @param int $count Number of characters to be read (will stop before
		 *                   if less characters are in the buffer)
		 * @return string
		 */
		public function readPort (int $count = null) {
			if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
				trigger_error('Device must be opened to read it', E_USER_WARNING);

				return false;
			}

			$buffer = null;
			if ($count > 0) {
				do {
					usleep(5000);
					$buffer .= fread($this->_dHandle, 1);
				} while (!feof($this->_dHandle) and strlen($buffer) < $count);
			} else {
				do {
					usleep(5000);
					$buffer .= fread($this->_dHandle, 1);
				} while (!feof($this->_dHandle));
			}

			return $buffer;
		}

		/**
		 * Flushes the output buffer
		 * Renamed from flush for osx compat. issues
		 *
		 * @return bool
		 */
		public function flush () {
			if (!$this->_ckOpened()) {
				return false;
			}

			if (fwrite($this->_dHandle, $this->_buffer) !== false) {
				$this->_buffer = null;

				return true;
			} else {
				$this->_buffer = null;

				trigger_error('Error while sending message', E_USER_WARNING);

				return false;
			}
		}

		//
		// I/O SECTION -- {STOP}
		//

		private function _ckOpened () {
			if ($this->_dState !== self::SERIAL_DEVICE_OPENED) {
				trigger_error('Device must be opened', E_USER_WARNING);

				return false;
			}

			return true;
		}

		private function _ckClosed () {
			if ($this->_dState === self::SERIAL_DEVICE_OPENED) {
				trigger_error('Device must be closed', E_USER_WARNING);

				return false;
			}

			return true;
		}

		private function _stty (string $device = null, array $dAttr = [], &$out = null) {
			$stty = sprintf(self::STTY[$this->_os], $device);

			if (array_key_exists('baud', $dAttr)) {
				if ($this->_os == 'Windows') {
					$stty .= ' baud=' . $dAttr['baud'];
				} else {
					$stty .= ' ' . $dAttr['baud'];
				}
			}

			if (array_key_exists('parity', $dAttr)) {
				if ($this->_os == 'Windows') {
					$stty .= ' parity=' . $dAttr['parity'][0];
				} else {
					$parity = [
						'none' => ' -parenb',
						'odd'  => ' parenb parodd',
						'even' => ' parenb -parodd',
					];
					$stty .= $parity[$dAttr['parity']];
				}
			}

			if (array_key_exists('data', $dAttr)) {
				if ($this->_os == 'Windows') {
					$stty .= ' data=' . $dAttr['data'];
				} else {
					$stty .= ' cs ' . $dAttr['data'];
				}
			}

			if (array_key_exists('stop', $dAttr)) {
				if ($this->_os == 'Windows') {
					$stty .= ' stop=' . $dAttr['stop'];
				} else {
					$stty .= ($dAttr['stop'] == 1) ? ' -cstopb' : ' cstopb';
				}
			}

			if (array_key_exists('flowcontrol', $dAttr)) {
				if ($this->_os == 'Windows') {
					$flowcontrol = [
						'none'     => ' xon=off octs=off rts=on',
						'rts/cts'  => ' xon=off octs=on rts=hs',
						'xon/xoff' => ' xon=on octs=off rts=on',
					];
				} else {
					$flowcontrol = [
						'none'     => ' clocal -crtscts -ixon -ixoff',
						'rts/cts'  => ' -clocal crtscts -ixon -ixoff',
						'xon/xoff' => ' -clocal -crtscts ixon ixof',
					];
				}
				$stty .= $flowcontrol[$dAttr['flowcontrol']];
			}

			return $this->_exec($stty, $out);
		}

		private function _exec ($cmd, &$out = null) {
			$desc = [
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			];

			$proc = proc_open($cmd, $desc, $pipes);

			$ret = stream_get_contents($pipes[1]);
			$err = stream_get_contents($pipes[2]);

			fclose($pipes[1]);
			fclose($pipes[2]);

			$retVal = proc_close($proc);

			if (func_num_args() == 2) {
				$out = [$ret, $err];
			}

			return $retVal;
		}
	}
