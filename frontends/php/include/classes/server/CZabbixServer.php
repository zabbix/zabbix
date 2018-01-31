<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * A class for interacting with the Zabbix server.
 *
 * Class CZabbixServer
 */
class CZabbixServer {

	/**
	 * Return item queue overview.
	 */
	const QUEUE_OVERVIEW = 'overview';

	/**
	 * Return item queue overview by proxy.
	 */
	const QUEUE_OVERVIEW_BY_PROXY = 'overview by proxy';

	/**
	 * Return a detailed item queue.
	 */
	const QUEUE_DETAILS = 'details';

	/**
	 * Response value if the request has been executed successfully.
	 */
	const RESPONSE_SUCCESS = 'success';

	/**
	 * Response value if an error occurred.
	 */
	const RESPONSE_FAILED = 'failed';

	/**
	 * Auxiliary constants for request() method.
	 */
	const ZBX_TCP_EXPECT_HEADER = 1;
	const ZBX_TCP_EXPECT_DATA = 2;

	/**
	 * Max number of bytes to read from the response for each each iteration.
	 */
	const READ_BYTES_LIMIT = 8192;

	/**
	 * Zabbix server host name.
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * Zabbix server port number.
	 *
	 * @var string
	 */
	protected $port;

	/**
	 * Request timeout.
	 *
	 * @var int
	 */
	protected $timeout;

	/**
	 * Maximum response size. If the size of the response exceeds this value, an error will be triggered.
	 *
	 * @var int
	 */
	protected $totalBytesLimit;

	/**
	 * Zabbix server socket resource.
	 *
	 * @var resource
	 */
	protected $socket;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected $error;

	/**
	 * Total result count (if any).
	 *
	 * @var int
	 */
	protected $total;

	/**
	 * Class constructor.
	 *
	 * @param string $host
	 * @param int $port
	 * @param int $timeout
	 * @param int $totalBytesLimit
	 */
	public function __construct($host, $port, $timeout, $totalBytesLimit) {
		$this->host = $host;
		$this->port = $port;
		$this->timeout = $timeout;
		$this->totalBytesLimit = $totalBytesLimit;
	}

	/**
	 * Executes a script on the given host and returns the result.
	 *
	 * @param $scriptId
	 * @param $hostId
	 * @param $sid
	 *
	 * @return bool|array
	 */
	public function executeScript($scriptId, $hostId, $sid) {
		return $this->request([
			'request' => 'command',
			'scriptid' => $scriptId,
			'hostid' => $hostId,
			'sid' => $sid
		]);
	}

	/**
	 * Retrieve item queue information.
	 *
	 * Possible $type values:
	 * - self::QUEUE_OVERVIEW
	 * - self::QUEUE_OVERVIEW_BY_PROXY
	 * - self::QUEUE_DETAILS
	 *
	 * @param string $type
	 * @param string $sid   user session ID
	 * @param int    $limit item count for details type
	 *
	 * @return bool|array
	 */
	public function getQueue($type, $sid, $limit = 0) {
		$request = [
			'request' => 'queue.get',
			'sid' => $sid,
			'type' => $type
		];

		if ($type == self::QUEUE_DETAILS) {
			$request['limit'] = $limit;
		}

		return $this->request($request);
	}

	/**
	 * Retrieve Status of Zabbix information.
	 *
	 * @param $sid
	 *
	 * @return bool|array
	 */
	public function getStatus($sid) {
		$response = $this->request([
			'request' => 'status.get',
			'type' => 'full',
			'sid' => $sid
		]);

		if ($response === false) {
			return false;
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'template stats' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_INT32]
			]],
			'host stats' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
					'proxyid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
					'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])]
				]],
				'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_INT32]
			]],
			'item stats' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
					'proxyid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
					'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
					'state' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATE_NORMAL, ITEM_STATE_NOTSUPPORTED])]
				]],
				'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_INT32]
			]],
			'trigger stats' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
					'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED])],
					'value' =>					['type' => API_INT32, 'in' => implode(',', [TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE])]
				]],
				'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_INT32]
			]],
			'user stats' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
					'status' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_SESSION_ACTIVE, ZBX_SESSION_PASSIVE])]
				]],
				'count' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '0:'.ZBX_MAX_INT32]
			]],
			// only for super-admins 'required performance' is available
			'required performance' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
				'attributes' =>				['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
					'proxyid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
				]],
				'count' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]	// API_FLOAT 0-n
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $response, '/', $this->error)) {
			return false;
		}

		return $response;
	}

	/**
	 * Returns true if the Zabbix server is running and false otherwise.
	 *
	 * @param $sid
	 *
	 * @return bool
	 */
	public function isRunning($sid) {
		$response = $this->request([
			'request' => 'status.get',
			'type' => 'ping',
			'sid' => $sid
		]);

		if ($response === false) {
			return false;
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => []];
		return CApiInputValidator::validate($api_input_rules, $response, '/', $this->error);
	}

	/**
	 * Returns the error message.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Returns the total result count.
	 *
	 * @return int|null
	 */
	public function getTotalCount() {
		return $this->total;
	}

	/**
	 * Executes a given JSON request and returns the result. Returns false if an error has occurred.
	 *
	 * @param array $params
	 *
	 * @return mixed    the output of the script if it has been executed successfully or false otherwise
	 */
	protected function request(array $params) {
		// Reset object state.
		$this->error = null;
		$this->total = null;

		// Connect to the server.
		if (!$this->connect()) {
			return false;
		}

		// Set timeout.
		stream_set_timeout($this->socket, $this->timeout);

		// Send the command.
		$json = CJs::encodeJson($params);
		if (fwrite($this->socket, ZBX_TCP_HEADER.pack('V', strlen($json))."\x00\x00\x00\x00".$json) === false) {
			$this->error = _s('Cannot send command, check connection with Zabbix server "%1$s".', $this->host);
			return false;
		}

		$expect = self::ZBX_TCP_EXPECT_HEADER;
		$response = '';
		$response_len = 0;
		$expected_len = null;
		$now = time();

		while (true) {
			if ((time() - $now) >= $this->timeout) {
				$this->error = _s(
					'Connection timeout of %1$s seconds exceeded when connecting to Zabbix server "%2$s".',
					$this->timeout, $this->host
				);
				return false;
			}

			if (!feof($this->socket) && ($buffer = fread($this->socket, self::READ_BYTES_LIMIT)) !== false) {
				$response_len += strlen($buffer);
				$response .= $buffer;

				if ($expect == self::ZBX_TCP_EXPECT_HEADER) {
					if (strncmp($response, ZBX_TCP_HEADER, min($response_len, ZBX_TCP_HEADER_LEN)) != 0) {
						$this->error = _s('Incorrect response received from Zabbix server "%1$s".', $this->host);
						return false;
					}

					if ($response_len < ZBX_TCP_HEADER_LEN) {
						continue;
					}

					$expect = self::ZBX_TCP_EXPECT_DATA;
				}

				if ($response_len < ZBX_TCP_HEADER_LEN + ZBX_TCP_DATALEN_LEN) {
					continue;
				}

				if ($expected_len === null) {
					$expected_len = unpack('Plen', substr($response, ZBX_TCP_HEADER_LEN, ZBX_TCP_DATALEN_LEN))['len'];
					$expected_len += ZBX_TCP_HEADER_LEN + ZBX_TCP_DATALEN_LEN;

					if ($this->totalBytesLimit != 0 && $expected_len >= $this->totalBytesLimit) {
						$this->error = _s(
							'Size of the response received from Zabbix server "%1$s" exceeds the allowed size of %2$s bytes. This value can be increased in the ZBX_SOCKET_BYTES_LIMIT constant in include/defines.inc.php.',
							$this->host, $this->totalBytesLimit
						);
						return false;
					}
				}

				if ($response_len >= $expected_len) {
					break;
				}
			}
			else {
				$this->error =
					_s('Cannot read the response, check connection with the Zabbix server "%1$s".', $this->host);
				return false;
			}
		}

		fclose($this->socket);

		if ($expected_len > $response_len || $response_len > $expected_len) {
			$this->error = _s('Incorrect response received from Zabbix server "%1$s".', $this->host);
			return false;
		}

		$response = CJs::decodeJson(substr($response, ZBX_TCP_HEADER_LEN + ZBX_TCP_DATALEN_LEN));

		if (!$response || !$this->validateResponse($response)) {
			$this->error = _s('Incorrect response received from Zabbix server "%1$s".', $this->host);

			return false;
		}

		// request executed successfully
		if ($response['response'] == self::RESPONSE_SUCCESS) {
			// saves total count
			$this->total = array_key_exists('total', $response) ? $response['total'] : null;

			return $response['data'];
		}
		// an error on the server side occurred
		else {
			$this->error = $response['info'];

			return false;
		}
	}

	/**
	 * Opens a socket to the Zabbix server. Returns the socket resource if the connection has been established or
	 * false otherwise.
	 *
	 * @return bool|resource
	 */
	protected function connect() {
		if (!$this->socket) {
			if (!$this->host || !$this->port) {
				return false;
			}

			if (!$socket = @fsockopen($this->host, $this->port, $errorCode, $errorMsg, $this->timeout)) {
				switch ($errorMsg) {
					case 'Connection refused':
						$dErrorMsg = _s("Connection to Zabbix server \"%s\" refused. Possible reasons:\n1. Incorrect server IP/DNS in the \"zabbix.conf.php\";\n2. Security environment (for example, SELinux) is blocking the connection;\n3. Zabbix server daemon not running;\n4. Firewall is blocking TCP connection.\n", $this->host);
						break;

					case 'No route to host':
						$dErrorMsg = _s("Zabbix server \"%s\" can not be reached. Possible reasons:\n1. Incorrect server IP/DNS in the \"zabbix.conf.php\";\n2. Incorrect network configuration.\n", $this->host);
						break;

					case 'Connection timed out':
						$dErrorMsg = _s("Connection to Zabbix server \"%s\" timed out. Possible reasons:\n1. Incorrect server IP/DNS in the \"zabbix.conf.php\";\n2. Firewall is blocking TCP connection.\n", $this->host);
						break;

					default:
						$dErrorMsg = _s("Connection to Zabbix server \"%s\" failed. Possible reasons:\n1. Incorrect server IP/DNS in the \"zabbix.conf.php\";\n2. Incorrect DNS server configuration.\n", $this->host);
				}

				$this->error = $dErrorMsg.$errorMsg;
			}

			$this->socket = $socket;
		}

		return $this->socket;
	}

	/**
	 * Returns true if the response received from the Zabbix server is valid.
	 *
	 * @param array $response
	 *
	 * @return bool
	 */
	protected function validateResponse(array $response) {
		return (isset($response['response'])
					&& ($response['response'] == self::RESPONSE_SUCCESS && isset($response['data'])
						|| $response['response'] == self::RESPONSE_FAILED && isset($response['info'])));
	}
}
