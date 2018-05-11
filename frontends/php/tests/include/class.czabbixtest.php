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

require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/dbfunc.php';
require_once dirname(__FILE__).'/class.cexceptionhelper.php';

class CZabbixTest extends PHPUnit_Framework_TestCase {
	// API request id.
	public $request_id = 0;

	// Table that should be backed up at the test suite level.
	protected static $suite_backup = null;
	// Table that should be backed up at the test case level.
	protected $case_backup = null;
	// Table that should be backed up at the test case level once (for multiple case executions).
	protected static $case_backup_once = null;
	// Shared browser instance.
	protected static $shared_browser = null;
	// Name of the last executed test.
	protected static $last_test_case = null;
	// Debug info.
	protected $debug = [];
	// Session id.
	protected $session = null;

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
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		global $DB, $URL;
		static $suite = null;
		$class_name = get_class($this);
		$case_name = $this->getName(false);
		$backup = [];

		$URL = PHPUNIT_URL.'api_jsonrpc.php';

		if (!isset($DB['DB'])) {
			DBconnect($error);
		}

		$annotations = $this->getAnnotations();

		// Restore data from backup if test case changed
		if (self::$case_backup_once !== null && self::$last_test_case !== $case_name) {
			DBrestore_tables(self::$case_backup_once);
			self::$case_backup_once = null;
		}

		// Test case level annotations.
		$method_annotations = $this->getAnnotationsByType($annotations, 'method');

		if ($method_annotations !== null) {
			// Backup performed before every test case execution.
			$case_backup = $this->getAnnotationsByType($method_annotations, 'backup');

			if ($case_backup !== null && count($case_backup) === 1) {
				$backup['case'] = $case_backup[0];
			}

			if (self::$last_test_case !== $case_name) {
				// Backup performed once before first test case execution.
				$case_backup_once = $this->getAnnotationsByType($method_annotations, 'backup-once');

				if ($case_backup_once !== null && count($case_backup_once) === 1) {
					$backup['case-once'] = $case_backup_once[0];
				}
			}
		}

		// Class name change is used to determine suite change.
		if ($suite !== $class_name) {
			// Test suite level annotations.
			$class_annotations = $this->getAnnotationsByType($annotations, 'class');

			// Backup performed before test suite execution.
			$suite_backup = $this->getAnnotationsByType($class_annotations, 'backup');

			if ($suite_backup !== null && count($suite_backup) === 1) {
				$backup['suite'] = $suite_backup[0];
			}
		}

		$suite = $class_name;
		self::$last_test_case = $case_name;

		// Backup is performed only for non-skipped tests.
		foreach ($backup as $level => $table) {
			switch ($level) {
				case 'suite':
					self::$suite_backup = $table;
					break;

				case 'case-once':
					self::$case_backup_once = $table;
					break;

				case 'case':
					$this->case_backup = $table;
					break;
			}

			DBsave_tables($table);
		}
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		if ($this->case_backup !== null) {
			DBrestore_tables($this->case_backup);
		}

		DBclose();
	}

	/**
	 * Callback executed after every test suite.
	 *
	 * @afterClass
	 */
	public static function onAfterTestSuite() {
		if (self::$suite_backup !== null || self::$case_backup_once !== null) {
			DBconnect($error);

			// Restore suite level backups.
			if (self::$suite_backup !== null) {
				DBrestore_tables(self::$suite_backup);
				self::$suite_backup = null;
			}

			// Restore case level backups.
			if (self::$case_backup_once !== null) {
				DBrestore_tables(self::$case_backup_once);
				self::$case_backup_once = null;
			}

			DBclose();
		}
	}

	/**
	 * Get annotations by type name.
	 * Helper function for method / class annotation processing.
	 *
	 * @param array  $annotations    annotations
	 * @param string $type	         type name
	 *
	 * @return array or null
	 */
	protected function getAnnotationsByType($annotations, $type) {
		return (array_key_exists($type, $annotations) && is_array($annotations[$type]))
				? $annotations[$type]
				: null;
	}

	protected function onNotSuccessfulTest($e) {
		if ($this->debug && $e instanceof Exception) {
			CExceptionHelper::setMessage($e, $e->getMessage()."\n\nAPI calls:\n".$this->getDebugInfoAsString());
		}

		parent::onNotSuccessfulTest($e);
	}
}
