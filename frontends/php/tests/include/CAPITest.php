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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/CTest.php';

/**
 * Base class for Zabbix API tests.
 */
class CAPITest extends CTest {
	// API request id.
	public $request_id = 0;
	// Debug info.
	protected $debug = [];
	// Session id.
	protected $session = null;

	/**
	 * Check API response.
	 *
	 * @param array $response  response to be checked
	 * @param mixed $error     expected error:
	 *
	 * Possible $error types:
	 *     null/false - there should not be an error
	 *     array      - exact match
	 *     string     - error message should match
	 *     int        - error code should match
	 */
	public function checkResult($response, $error = null) {
		// Check response data.
		if ($error === null || $error === false) {
			$this->assertTrue(array_key_exists('result', $response));
			$this->assertFalse(array_key_exists('error', $response));
		}
		else {
			$this->assertFalse(array_key_exists('result', $response));
			$this->assertTrue(array_key_exists('error', $response));

			if (is_array($error)) {
				$this->assertSame($error, $response['error']);
			}
			elseif (is_string($error)) {
				$this->assertSame($error, $response['error']['data']);
			}
			elseif (is_numeric($error)) {
				$this->assertSame($error, $response['error']['code']);
			}
		}
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
	public function callRaw($data) {
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
				'method' => 'post',
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
			$response = json_decode($response, true);
			$this->assertTrue(is_array($response));
		}
		else {
			$this->debug[] = $debug;
			throw new Exception('Problem with '.$URL.', '.$php_errormsg);
		}

		$this->request_id++;
		$this->debug[] = $debug;

		return $response;
	}

	/**
	 * Prepare request for API call and make API call (@see callRaw).
	 *
	 * @param string $method    API method to be called.
	 * @param mixed $params     API call params.
	 * @param mixed $error      expected error if any or null/false if successful result is expected.
	 *
	 * @return array
	 */
	public function call($method, $params, $error = null) {
		$data = [
			'jsonrpc' => '2.0',
			'method' => $method,
			'params' => $params,
			'id' => $this->request_id
		];

		if ($this->session === null) {
			$this->authorize('Admin', 'zabbix');
		}

		if ($this->session) {
			$data['auth'] = $this->session;
		}

		$response = $this->callRaw($data);
		$this->checkResult($response, $error);

		return $response;
	}

	/**
	 * Set session id.
	 * @param string $session    session id to be used.
	 */
	public function setSessionId($session) {
		$this->session = $session;
	}

	/**
	 * Enable authorization/session for the following API calls.
	 */
	public function enableAuthorization() {
		$this->setSessionId(null);
	}

	/**
	 * Disable authorization/session for the following API calls.
	 */
	public function disableAuthorization() {
		$this->setSessionId(false);
	}

	/**
	 * Authorize as user.
	 *
	 * @param string $user        username to be used for authorization.
	 * @param string $password    password.
	 */
	public function authorize($user, $password) {
		$this->disableAuthorization();

		$result = $this->call('user.login', ['user' => $user, 'password' => $password]);
		$this->setSessionId($result['result']);
	}

	/**
	 * Get debug information.
	 *
	 * @param boolean $clear    whether debug info should be cleared after retrieval.
	 *
	 * @return array
	 */
	public function getDebugInfo($clear = false) {
		$result = $this->debug;
		if ($clear) {
			$this->clearDebugInfo();
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
	public function getDebugInfoAsString($clear = false) {
		$steps = [];
		foreach ($this->getDebugInfo($clear) as $call) {
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
	public function clearDebugInfo() {
		$this->debug = [];
	}

	/**
	 * @inheritdoc
	 */
	protected function onNotSuccessfulTest($e) {
		if ($this->debug && $e instanceof Exception) {
			CExceptionHelper::setMessage($e, $e->getMessage()."\n\nAPI calls:\n".$this->getDebugInfoAsString());
		}

		parent::onNotSuccessfulTest($e);
	}

	/**
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		global $URL;
		parent::onBeforeTestCase();

		$URL = PHPUNIT_URL.'api_jsonrpc.php';
	}
}
