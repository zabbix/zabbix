<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Client session for making authorized API requests.
 */
class CClient {

	/**
	 * @var string
	 */
	public $auth;

	/**
	 * @param string $user
	 * @param array $password
	 */
	public function __construct(string $user, string $password) {
		list($result, $error) = $this->call('user.login', compact('user', 'password'));

		if ($error) {
			throw new Exception($error);
		}

		$this->auth = $result;
	}

	/**
	 * @param string $method
	 * @param array $params
	 *
	 * @return array
	 */
	public function call(string $method, array $params = []) {
		$error = null;
		$result = null;

		$auth = $this->auth;
		$http = [
			'method' => 'POST',
			'header' => 'Content-type: application/json',
			'content' => json_encode(compact('method', 'params', 'auth') + ['id' => 1, 'jsonrpc' => '2.0'])
		];

		$resp = file_get_contents(PHPUNIT_URL.'api_jsonrpc.php', false, stream_context_create(compact('http')));

		if (!$resp) {
			$error = CClientError::no_resp();

			return [$result, $error];
		}

		$resp_decoded = json_decode($resp, true);
		if (!$resp_decoded) {
			$error = CClientError::json($resp);

			return [$result, $error];
		}

		if (array_key_exists('error', $resp_decoded)) {
			// TODO provide original request in error data
			$error = CClientError::from_resp($resp_decoded);

			return [$result, $error];
		}

		$result = $resp_decoded['result'];

		return [$result, $error];
	}
}
