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

require_once dirname(__FILE__).'/CTest.php';

/**
 * Base class for Zabbix API tests.
 */
class CAPITest extends CTest {

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
			$this->assertArrayHasKey('result', $response, json_encode($response, JSON_PRETTY_PRINT));
			$this->assertArrayNotHasKey('error', $response);
		}
		else {
			$this->assertArrayNotHasKey('result', $response);
			$this->assertArrayHasKey('error', $response);

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
		return CAPIHelper::callRaw($data);
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
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = CAPIHelper::call($method, $params);
		$this->checkResult($response, $error);

		return $response;
	}

	/**
	 * Enable authorization/session for the following API calls.
	 */
	public static function enableAuthorization() {
		CAPIHelper::setSessionId(null);
	}

	/**
	 * Disable authorization/session for the following API calls.
	 */
	public static function disableAuthorization() {
		CAPIHelper::setSessionId(false);
	}

	/**
	 * Authorize as user.
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function authorize(string $username, string $password) {
		CAPIHelper::authorize($username, $password);
	}

	/**
	 * @inheritdoc
	 */
	protected function onNotSuccessfulTest($t): void {
		if ($t instanceof Exception && CAPIHelper::getDebugInfo()) {
			CExceptionHelper::setMessage($t, $t->getMessage()."\n\nAPI calls:\n".CAPIHelper::getDebugInfoAsString());
		}

		parent::onNotSuccessfulTest($t);
	}

	/**
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		global $URL;
		$URL = PHPUNIT_URL.'api_jsonrpc.php';
		CAPIHelper::reset();

		parent::onBeforeTestCase();
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		parent::onAfterTestCase();

		CAPIHelper::clearDebugInfo();
	}
}
