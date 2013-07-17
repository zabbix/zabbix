<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
	 * Bite count to read from the response with each iteration.
	 *
	 * @var int
	 */
	protected $readBytesLimit = 8192;

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
	 * Class constructor.
	 *
	 * @param string $host
	 * @param int $port
	 * @param int $timeout
	 * @param int $totalBytesLimit
	 */
	public function __construct($host, $port, $timeout, $totalBytesLimit = null) {
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
	 *
	 * @return bool|array
	 */
	public function executeScript($scriptId, $hostId) {
		return $this->request(array(
			'request' => 'command',
			'nodeid' => id2nodeid($hostId),
			'scriptid' => $scriptId,
			'hostid' => $hostId
		));
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
	 *
	 * @return bool|array
	 */
	public function getQueue($type, $sid) {
		return $this->request(array(
			'request' => 'queue.get',
			'sid' => $sid,
			'type' => $type
		));
	}

	/**
	 * Returns true if the Zabbix server is running and false otherwise.
	 *
	 * @return bool
	 */
	public function isRunning() {
		return (bool) $this->connect();
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
	 * Executes a given JSON request and returns the result. Returns false if an error has occurred.
	 *
	 * @param array $params
	 *
	 * @return bool|mixed
	 */
	protected function request(array $params) {
		// connect to the server
		if (!$socket = $this->connect()) {
			return false;
		}

		// set timeout
		stream_set_timeout($socket, $this->timeout);

		// send the command
		if (fwrite($socket, CJs::encodeJson($params)) === false) {
			$this->error = _('Error description: can\'t send command, check connection.');

			return false;
		}

		// read the response
		if ($this->totalBytesLimit && $this->totalBytesLimit < $this->readBytesLimit) {
			$readBytesLimit = $this->totalBytesLimit;
		}
		else {
			$readBytesLimit = $this->readBytesLimit;
		}
		$response = '';
		$now = time();
		$i = 0;
		while (!feof($socket)) {
			$i++;
			if ((time() - $now) >= $this->timeout) {
				$this->error = _('Error description: defined in "include/defines.inc.php" constant ZBX_SCRIPT_TIMEOUT timeout is reached. You can try to increase this value.');

				return false;
			}
			elseif ($this->totalBytesLimit && ($i * $readBytesLimit) >= $this->totalBytesLimit) {
				$this->error = _('Error description: defined in "include/defines.inc.php" constant ZBX_SCRIPT_BYTES_LIMIT read bytes limit is reached. You can try to increase this value.');

				return false;
			}

			if (($out = fread($socket, $readBytesLimit)) !== false) {
				$response .= $out;
			}
			else {
				$this->error = _('Error description: defined in "include/defines.inc.php" constant ZBX_SCRIPT_BYTES_LIMIT read bytes limit is reached. You can try to increase this value.');

				return false;
			}
		}

		// check if the response is empty
		if (!strlen($response)) {
			$this->error = _('Error description: empty response received.');

			return false;
		}

		fclose($socket);

		$response = CJs::decodeJson($response);

		// script executed successfully
		if ($response['response'] == 'success') {
			return $response['data'];
		}
		// an error on the server side occurred
		else {
			$this->error = _('Error description').':'.$response['info'];

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

					case 'php_network_getaddresses: getaddrinfo failed: Name or service not known':
						$dErrorMsg = _s("Connection to Zabbix server \"%s\" failed. Possible reasons:\n1. Incorrect server IP/DNS in the \"zabbix.conf.php\";\n2. Incorrect DNS server configuration.\n", $this->host);
						break;

					default:
						$dErrorMsg = '';
				}

				$this->error = $dErrorMsg._('Error description').NAME_DELIMITER.$errorMsg;
			}

			$this->socket = $socket;
		}

		return $this->socket;
	}

}
