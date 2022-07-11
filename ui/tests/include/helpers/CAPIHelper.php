<?php

/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../../include/hosts.inc.php';

class CAPIHelper {

	// API request id.
	protected static $request_id = 0;
	// Debug info.
	protected static $debug = [];
	// Session id.
	protected static $session = null;

	/**
	 * Reset API helper state.
	 */
	public static function reset() {
		static::$request_id = 0;

		static::clearDebugInfo();
		static::setSessionId(null);
	}

	/**
	 * Make API call.
	 *
	 * @param mixed $data     string containing request data as json.
	 *
	 * @return array
	 *
	 * @throws Exception      if API call fails.
	 */
	public static function callRaw($data) {
		global $URL;

		if (is_array($data)) {
			$data = json_encode($data);
		}

		$debug = [
			'request' => $data,
			'response' => null
		];

		$params = [
			'http' => [
				'method' => 'POST',
				'content' => $data,
				'header' => [
					'Content-type: application/json-rpc',
					'Content-Length: '.strlen($data)
				]
			]
		];

		$handle = fopen($URL, 'rb', false, stream_context_create($params));
		if ($handle) {
			$response = @stream_get_contents($handle);
			fclose($handle);
		}
		else {
			$response = false;
		}

		if ($response !== false) {
			$debug['response'] = $response;

			if ($response !== '') {
				$response = json_decode($response, true);

				if (!is_array($response)) {
					throw new Exception('API response is not in JSON format');
				}
			}
		}
		else {
			static::$debug[] = $debug;
			throw new Exception('Problem with '.$URL.', '.$php_errormsg);
		}

		static::$request_id++;
		static::$debug[] = $debug;

		return $response;
	}

	/**
	 * Prepare request for API call and make API call (@see callRaw).
	 *
	 * @param string $method    API method to be called.
	 * @param mixed $params     API call params.
	 *
	 * @return array
	 */
	public static function call($method, $params) {
		$data = [
			'jsonrpc' => '2.0',
			'method' => $method,
			'params' => $params,
			'id' => static::$request_id
		];

		if (static::$session) {
			$data['auth'] = static::$session;
		}

		return static::callRaw($data);
	}

	/**
	 * Set session id.
	 *
	 * @param string $session    session id to be used.
	 */
	public static function setSessionId($session) {
		static::$session = $session;
	}

	/**
	 * Create session id.
	 *
	 * @param string $userid       user id to be used.
	 * @param string $sessionid    session id to be used.
	 */
	public static function createSessionId($userid = 1, $sessionid = '09e7d4286dfdca4ba7be15e0f3b2b55a') {
		if (!CDBHelper::getRow('select null from sessions where status=0 and userid='.zbx_dbstr($userid).
				' and sessionid='.zbx_dbstr($sessionid))) {
			DBexecute('insert into sessions (sessionid, userid) values ('.zbx_dbstr($sessionid).', '.$userid.')');
		}

		static::$session = $sessionid;
	}

	/**
	 * Get session id.
	 *
	 * @return string
	 */
	public static function getSessionId() {
		return static::$session;
	}

	/**
	 * Authorize as user.
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @returns CAPIHelper
	 */
	public static function authorize(string $username, string $password) {
		static::$session = false;

		$result = static::call('user.login', ['username' => $username, 'password' => $password]);
		if (array_key_exists('result', $result)) {
			static::setSessionId($result['result']);
		}
	}

	/**
	 * Get debug information.
	 *
	 * @param boolean $clear    whether debug info should be cleared after retrieval.
	 *
	 * @return array
	 */
	public static function getDebugInfo($clear = false) {
		$result = static::$debug;
		if ($clear) {
			static::clearDebugInfo();
		}

		return $result;
	}

	/**
	 * Get debug information as string (@see getDebugInfo).
	 *
	 * @param boolean $clear    whether debug info should be cleared after retrieval.
	 *
	 * @return string
	 */
	public static function getDebugInfoAsString($clear = false) {
		$steps = [];
		foreach (static::getDebugInfo($clear) as $call) {
			$step = "  Request: ".$call['request'];
			if ($call['response'] !== null) {
				$step .= "\n\n  Response: ".$call['response'];
			}

			$steps[] = $step;
		}

		return implode("\n\n---------------------------\n\n", $steps);
	}

	/**
	 * Remove debug info of previous API calls (if any).
	 */
	public static function clearDebugInfo() {
		static::$debug = [];
	}
}
