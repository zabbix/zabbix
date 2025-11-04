<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
	 * Max number of bytes to read from the response for each iteration.
	 */
	const READ_BYTES_LIMIT = 8192;

	const ERROR_CODE_NONE = 0;
	const ERROR_CODE_TLS = 1;
	const ERROR_CODE_TCP = 2;

	/**
	 * Zabbix server host name.
	 *
	 * @var string|null
	 */
	protected $host;

	/**
	 * Zabbix server port number.
	 *
	 * @var int|null
	 */
	protected $port;

	/**
	 * Request connect timeout.
	 *
	 * @var int
	 */
	protected $connect_timeout;

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
	protected $total_bytes_limit;

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
	 * @var array $debug  Section 'debug' data from server response.
	 */
	protected $debug = [];

	protected array $tls_config;
	protected int $error_code;

	/**
	 * @param string|null $host
	 * @param int|null    $port
	 * @param int         $connect_timeout
	 * @param int         $timeout
	 * @param int         $total_bytes_limit
	 * @param array       $tls_config
	 */
	public function __construct($host, $port, $connect_timeout, $timeout, $total_bytes_limit, array $tls_config = []) {
		$this->host = $host;
		$this->port = $port;
		$this->connect_timeout = $connect_timeout;
		$this->timeout = $timeout;
		$this->total_bytes_limit = $total_bytes_limit;
		$this->tls_config = $tls_config ?: APP::getConfig()['ZBX_SERVER_TLS'];
		$this->error_code = self::ERROR_CODE_NONE;
	}

	/**
	 * Executes a script on the given host or event and returns the result.
	 *
	 * @param string      $scriptid
	 * @param string      $sid
	 * @param null|string $hostid
	 * @param null|string $eventid
	 * @param null|string $manualinput
	 *
	 * @return bool|array
	 */
	public function executeScript(string $scriptid, string $sid, ?string $hostid = null, ?string $eventid = null,
			$manualinput = null) {
		$params = [
			'request' => 'command',
			'scriptid' => $scriptid,
			'sid' => $sid,
			'clientip' => CWebUser::getIp()
		];

		if ($hostid !== null) {
			$params['hostid'] = $hostid;
		}

		if ($eventid !== null) {
			$params['eventid'] = $eventid;
		}

		if ($manualinput !== null) {
			$params['manualinput'] = $manualinput;
		}

		return $this->request($params);
	}

	/**
	 * @param array  $data
	 *        string $data['itemid']  (optional) Item ID.
	 *        string $data['host']    (optional) Technical name of the host.
	 *        string $data['key']     (optional) Item key.
	 *        string $data['value']   Item value.
	 *        string $data['clock']   (optional) Time when the value was received.
	 *        string $data['ns']      (optional) Nanoseconds when the value was received.
	 * @param string $sid             User session ID or user API token.
	 *
	 * @return array|bool
	 */
	public function pushHistory(array $data, string $sid) {
		return $this->request([
			'request' => 'history.push',
			'data' => $data,
			'sid' => $sid,
			'clientip' => CWebUser::getIp()
		]);
	}

	/**
	 * Request server to test item.
	 *
	 * @param array  $data
	 * @param array  $data['item']     Array of item parameters.
	 * @param array  $data['host']     (optional) Array of host parameters.
	 * @param array  $data['options']  (optional) Array of test parameters.
	 * @param string $sid              User session ID.
	 *
	 * @return array|bool
	 */
	public function testItem(array $data, string $sid) {
		return $this->request([
			'request' => 'item.test',
			'data' => $data,
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
	 * Request server to test media type.
	 *
	 * @param array  $data                 Array of media type test data to send.
	 * @param string $data['mediatypeid']  Media type ID.
	 * @param string $data['sendto']       Message destination.
	 * @param string $data['subject']      Message subject.
	 * @param string $data['message']      Message body.
	 * @param string $data['params']       Custom parameters for media type webhook.
	 * @param string $sid                  User session ID.
	 *
	 * @return bool|array
	 */
	public function testMediaType(array $data, $sid) {
		return $this->request([
			'request' => 'alert.send',
			'sid' => $sid,
			'data' => $data
		]);
	}

	/**
	 * Request server to test report.
	 *
	 * @param array  $data                       Array of report test data to send.
	 * @param string $data['name']               Report name (used to make attachment file name).
	 * @param string $data['dashboardid']        Dashboard ID.
	 * @param string $data['userid']             User ID used to access the dashboard.
	 * @param string $data['period']             Report period. Possible values:
	 *                                            0 - ZBX_REPORT_PERIOD_DAY;
	 *                                            1 - ZBX_REPORT_PERIOD_WEEK;
	 *                                            2 - ZBX_REPORT_PERIOD_MONTH;
	 *                                            3 - ZBX_REPORT_PERIOD_YEAR.
	 * @param string $data['now']                Report generation time (seconds since Epoch).
	 * @param array  $data['params']             Report parameters.
	 * @param string $data['params']['subject']  Report message subject.
	 * @param string $data['params']['body']     Report message text.
	 * @param string $sid                        User session ID.
	 *
	 * @return bool|array
	 */
	public function testReport(array $data, string $sid) {
		return $this->request([
			'request' => 'report.test',
			'sid' => $sid,
			'data' => $data
		]);
	}

	/**
	 * Retrieve System information.
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
			]],
			'server stats' =>			['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'version' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $response, '/', $this->error)) {
			return false;
		}

		return $response;
	}

	public function isRunning(): bool {
		$active_node = API::getApiService('hanode')->get([
			'output' => ['address', 'port', 'lastaccess'],
			'filter' => ['status' => ZBX_NODE_STATUS_ACTIVE],
			'sortfield' => 'lastaccess',
			'sortorder' => 'DESC',
			'limit' => 1
		], false);

		if ($active_node && $active_node[0]['address'] === $this->host && $active_node[0]['port'] == $this->port) {
			if ((time() - $active_node[0]['lastaccess']) <
					timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HA_FAILOVER_DELAY))) {
				return true;
			}
		}

		return false;
	}

	public function canConnect(string $sid): bool {
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
	 * Evaluate trigger expressions.
	 *
	 * @param array  $data
	 * @param string $sid
	 *
	 * @return bool|array
	 */
	public function expressionsEvaluate(array $data, string $sid) {
		$response = $this->request([
			'request' => 'expressions.evaluate',
			'sid' => $sid,
			'data' => $data
		]);

		if ($response === false) {
			return false;
		}

		$api_input_rules = ['type' => API_OBJECTS, 'fields' => [
			'expression' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'value' =>		['type' => API_INT32, 'in' => '0,1'],
			'error' =>		['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $response, '/', $this->error)) {
			return false;
		}

		return $response;
	}

	/**
	 * Returns the error message.
	 *
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	public function getErrorCode(): int {
		return $this->error_code;
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
	 * Returns debug section from server response.
	 *
	 * @return array
	 */
	public function getDebug() {
		return $this->debug;
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
		$this->debug = [];

		// Connect to the server.
		if (!$this->connect()) {
			return false;
		}

		// Set timeout.
		stream_set_timeout($this->socket, $this->timeout);

		// Send the command.
		$json = json_encode($params);
		if (fwrite($this->socket, ZBX_TCP_HEADER.pack('V', strlen($json))."\x00\x00\x00\x00".$json) === false) {
			$this->error = _s('Cannot send command, check connection with Zabbix server "%1$s".', $this->host);

			fclose($this->socket);

			return false;
		}

		$expect = self::ZBX_TCP_EXPECT_HEADER;
		$response = '';
		$response_len = 0;
		$expected_len = null;

		while (!feof($this->socket)) {
			if (($buffer = fread($this->socket, self::READ_BYTES_LIMIT)) === false) {
				$info = stream_get_meta_data($this->socket);

				if ($info['timed_out']) {
					$this->error = _s(
						'Response timeout of %1$s exceeded when connecting to Zabbix server "%2$s".',
						secondsToPeriod($this->timeout), $this->host
					);
				} else {
					$this->error = _s('Cannot read response from Zabbix server "%1$s".', $this->host);
				}

				fclose($this->socket);

				return false;
			}

			$response_len += strlen($buffer);
			$response .= $buffer;

			if ($expect == self::ZBX_TCP_EXPECT_HEADER) {
				if (strncmp($response, ZBX_TCP_HEADER, min($response_len, ZBX_TCP_HEADER_LEN)) != 0) {
					$this->error = _s('Incorrect response received from Zabbix server "%1$s".', $this->host);

					fclose($this->socket);

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
				$expected_len = unpack('Vlen', substr($response, ZBX_TCP_HEADER_LEN, 4))['len'];
				$expected_len += ZBX_TCP_HEADER_LEN + ZBX_TCP_DATALEN_LEN;

				if ($this->total_bytes_limit != 0 && $expected_len >= $this->total_bytes_limit) {
					$this->error = _s(
						'Size of the response received from Zabbix server "%1$s" exceeds the allowed size of %2$s bytes. This value can be increased in the ZBX_SOCKET_BYTES_LIMIT constant in include/defines.inc.php.',
						$this->host, $this->total_bytes_limit
					);
					fclose($this->socket);

					return false;
				}
			}

			if ($response_len >= $expected_len) {
				break;
			}
		}

		fclose($this->socket);

		if ($expected_len > $response_len || $response_len > $expected_len) {
			$this->error = _s('Incorrect response received from Zabbix server "%1$s".', $this->host);
			return false;
		}

		$response = json_decode(substr($response, ZBX_TCP_HEADER_LEN + ZBX_TCP_DATALEN_LEN), true);

		if (!$response || !$this->normalizeResponse($response)) {
			$this->error = _s('Incorrect response received from Zabbix server "%1$s".', $this->host);

			return false;
		}

		if (array_key_exists('debug', $response)) {
			$this->debug = $response['debug'];
		}

		// Request executed successfully.
		if ($response['response'] == self::RESPONSE_SUCCESS) {
			// saves total count
			$this->total = array_key_exists('total', $response) ? $response['total'] : null;

			return array_key_exists('data', $response) ? $response['data'] : true;
		}

		// An error on the server side occurred.
		$this->error = rtrim($response['info']);

		return false;
	}

	protected function connect(): bool {
		if ($this->socket && is_resource($this->socket)) {
			return true;
		}

		if ($this->host === null || $this->port === null) {
			$this->error = _('Connection to Zabbix server failed. Incorrect configuration.');

			return false;
		}

		if ($this->tls_config['ACTIVE'] == 1) {
			$this->socket = $this->connectTLS();
		}
		else {
			$this->socket = $this->connectTCP();
		}

		return $this->socket !== null;
	}

	/**
	 * @return resource|null
	 */
	protected function connectTCP() {
		$address = $this->host.':'.$this->port;
		$socket = @stream_socket_client($address, $error_code, $error_msg, $this->connect_timeout);

		if (!is_resource($socket)) {
			$this->error = $this->connectionErrorMessage($error_msg);
			$this->error_code = self::ERROR_CODE_TCP;

			return null;
		}

		return $socket;
	}

	/**
	 * @return resource|null
	 */
	protected function connectTLS() {
		if (!extension_loaded('openssl')) {
			$this->error = _('OpenSSL extension is not available.');
			$this->error_code = self::ERROR_CODE_TLS;

			return null;
		}

		$required_files = [
			'CA_FILE' => $this->tls_config['CA_FILE'] ?? '',
			'KEY_FILE' => $this->tls_config['KEY_FILE'] ?? '',
			'CERT_FILE' => $this->tls_config['CERT_FILE'] ?? ''
		];

		if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			foreach ($required_files as $required_file => $path) {
				if ($error_message = self::checkTLSFile($required_file, $path)) {
					$this->error = $error_message;
					$this->error_code = self::ERROR_CODE_TLS;
				}
			}
		}

		$capture_peer_cert = $this->tls_config['CERTIFICATE_ISSUER'] || $this->tls_config['CERTIFICATE_SUBJECT'];
		$context = stream_context_create([
			'ssl' => [
				'cafile' => $this->tls_config['CA_FILE'],
				'local_pk' => $this->tls_config['KEY_FILE'],
				'local_cert' => $this->tls_config['CERT_FILE'],
				'capture_peer_cert' => $capture_peer_cert,
				'verify_peer_name' => false
			]
		]);

		$address = 'tls://'.$this->host.':'.$this->port;
		$socket = @stream_socket_client($address, $error_code, $error_msg, $this->connect_timeout, context: $context);

		if (!is_resource($socket)) {
			$this->error = $this->connectionErrorMessage($error_msg);
			$this->error_code = self::ERROR_CODE_TCP;

			if ($this->connectTCP()) {
				$this->error = _('Unable to connect to the Zabbix server due to TLS settings. Some functions are unavailable.');
				$this->error_code = self::ERROR_CODE_TLS;
			}

			return null;
		}

		if ($capture_peer_cert && !$this->validatePeerCertificate($socket)) {
			$this->error = _('Unable to connect to the Zabbix server due to TLS settings. Some functions are unavailable.');
			$this->error_code = self::ERROR_CODE_TLS;

			return null;
		}

		return $socket;
	}

	protected function connectionErrorMessage(string $error_msg): string {
		$host_port = $this->host.':'.$this->port;

		switch ($error_msg) {
			case 'Connection refused':
				$descriptive_error_msg = _s("Connection to Zabbix server \"%1\$s\" refused. Possible reasons:\n1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n2. Security environment (for example, SELinux) is blocking the connection;\n3. Zabbix server daemon not running;\n4. Firewall is blocking TCP connection.\n", $host_port);
				break;

			case 'No route to host':
				$descriptive_error_msg = _s("Zabbix server \"%1\$s\" cannot be reached. Possible reasons:\n1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n2. Incorrect network configuration.\n", $host_port);
				break;

			case 'Connection timed out':
				$descriptive_error_msg = _s("Connection to Zabbix server \"%1\$s\" timed out. Possible reasons:\n1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n2. Firewall is blocking TCP connection.\n", $host_port);
				break;

			default:
				$descriptive_error_msg = _s("Connection to Zabbix server \"%1\$s\" failed. Possible reasons:\n1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n2. Incorrect DNS server configuration.\n", $host_port);
		}

		return rtrim($descriptive_error_msg.$error_msg);
	}

	/**
	 * Returns true if the response received from the Zabbix server is valid.
	 *
	 * @param array $response
	 *
	 * @return bool
	 */
	protected function normalizeResponse(array &$response): bool {
		return (array_key_exists('response', $response) && ($response['response'] == self::RESPONSE_SUCCESS
				|| $response['response'] == self::RESPONSE_FAILED && array_key_exists('info', $response))
		);
	}

	protected static function checkTLSFile(string $type, string $path): ?string {
		$configFields = [
			'CA_FILE' => _('TLS CA file'),
			'KEY_FILE' => _('TLS key file'),
			'CERT_FILE' => _('TLS certificate file')
		];
		$error_message = null;

		if ($path === '') {
			$error_message = _s('%1$s: %2$s.', $configFields[$type], _('cannot be empty'));
		}
		elseif (!file_exists($path)) {
			$error_message = _s('%1$s: invalid path or file not found.', $configFields[$type]);
		}
		elseif (!is_readable($path)) {
			$error_message = _s('%1$s: file is not readable.', $configFields[$type]);
		}

		if ($error_message !== null) {
			CMessageHelper::addMessage([
				'type' => CMessageHelper::MESSAGE_TYPE_ERROR,
				'message' => $error_message,
				'is_technical_error' => false
			]);
		}

		return $error_message;
	}

	/**
	 * Constructs issuer string according to Zabbix rules for matching Issuer and Subject strings.
	 *
	 * @param array<string|string[]>  Parsed and structured issuer or subject field from openssl_x509_parse result.
	 */
	protected static function implodeDn(array $attributes): string {
		// Correcting the mixed type from openssl extension, where multivalue RDN is list of values.
		$attributes = array_map(fn (string|array $value) => (array) $value, $attributes);
		$attributes = array_map(fn (array $value) => array_reverse($value), $attributes);
		$attributes = array_reverse($attributes, true);

		$result = [];
		foreach ($attributes as $name => $values) {
			$multivalue = [];
			foreach ($values as $value) {
				$value = addcslashes($value, ',+');
				$value = preg_replace_callback('/(^\s+)|(\s+$)/', function($matches) {
					return str_replace(' ', '\\ ', $matches[0]);
				}, $value);

				$multivalue[] = "{$name}={$value}";
			}
			$result[] = implode(',', $multivalue);
		}

		return implode(',', $result);
	}

	protected function validatePeerCertificate($socket): bool {
		$subject_dn = $this->tls_config['CERTIFICATE_SUBJECT'];
		$issuer_dn = $this->tls_config['CERTIFICATE_ISSUER'];

		$params = stream_context_get_params($socket);
		$cert = $params['options']['ssl']['peer_certificate'];

		if ($info = @openssl_x509_parse($cert)) {
			if ($subject_dn && self::implodeDn((array) $info['subject']) !== $subject_dn) {
				return false;
			}

			if ($issuer_dn && self::implodeDn((array) $info['issuer']) !== $issuer_dn) {
				return false;
			}

			return true;
		}

		return false;
	}
}
