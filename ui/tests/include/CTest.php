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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';

require_once dirname(__FILE__).'/helpers/CDBHelper.php';
require_once dirname(__FILE__).'/helpers/CConfigHelper.php';
require_once dirname(__FILE__).'/helpers/CAPIHelper.php';
require_once dirname(__FILE__).'/helpers/CAPIScimHelper.php';
require_once dirname(__FILE__).'/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/helpers/CExceptionHelper.php';
require_once dirname(__FILE__).'/helpers/CTestArrayHelper.php';
require_once dirname(__FILE__).'/helpers/CDateTimeHelper.php';

define('USER_ACTION_ADD', 'add');
define('USER_ACTION_UPDATE', 'update');
define('USER_ACTION_REMOVE', 'remove');

define('STRING_6000', str_repeat('long_string_', 500));
define('STRING_2200', substr(STRING_6000, 0, 2200));
define('STRING_2048', substr(STRING_6000, 0, 2048));
define('STRING_2000', substr(STRING_6000, 0, 2000));
define('STRING_1024', substr(STRING_6000, 0, 1024));
define('STRING_512', substr(STRING_6000, 0, 512));
define('STRING_255', substr(STRING_6000, 0, 255));
define('STRING_128', substr(STRING_6000, 0, 128));
define('STRING_64', substr(STRING_6000, 0, 64));

/**
 * Base class of php unit tests.
 */
use PHPUnit\Framework\TestCase;

class CTest extends TestCase {

	// Table that should be backed up at the test suite level.
	protected static $suite_backup = null;
	// Table that should be backed up at the test case level.
	protected $case_backup = null;
	// Table that should be backed up at the test case level once (for multiple case executions).
	protected static $case_backup_once = null;
	// zabbix.conf.php should be backed up at the test suite level.
	protected static $suite_backup_config = false;
	// zabbix.conf.php should be backed up at the test case level.
	protected $case_backup_config = false;
	// Name of the last executed test.
	protected static $last_test_case = null;
	// Test case data key.
	protected $data_key = null;
	// Lists of test case data set keys.
	protected static $test_data_sets = [];
	// Test case annotations.
	protected $annotations = null;
	// Test case warnings.
	protected static $warnings = [];
	// Skip test suite execution.
	protected static $skip_suite = false;
	// Callbacks that should be executed at the test case level.
	protected $case_callbacks = [];
	// Callbacks that should be executed at the test suite level.
	protected static $suite_callbacks = [
		'afterOnce' => [],
		'beforeEach' => [],
		'afterEach' => [],
		'after' => []
	];
	// Instances counter to keep track of test count.
	protected static $instances = 0;
	// List of behaviors.
	protected $behaviors = null;

	/**
	 * Overridden constructor for collecting data on data sets from dataProvider annotations.
	 *
	 * @param string $name
	 * @param array  $data
	 * @param string $data_name
	 */
	public function __construct($name = null, array $data = [], $data_name = '') {
		parent::__construct($name, $data, $data_name);

		// If data limits are enabled and test case uses data.
		if (defined('PHPUNIT_ENABLE_DATA_LIMITS') && PHPUNIT_ENABLE_DATA_LIMITS && $data) {
			$this->data_key = $data_name;
			self::$test_data_sets[$name][] = $data_name;
		}

		self::$instances++;
	}

	/**
	 * Destructor to run callback when all tests are executed.
	 */
	public function __destruct() {
		self::$instances--;

		if (self::$instances === 0) {
			static::onAfterAllTests();
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
		if ($annotations === null || !array_key_exists($type, $annotations) || !is_array($annotations[$type])) {
			return null;
		}

		return $annotations[$type];
	}

	/**
	 * Get annotation tokens by annotation name.
	 * Helper function for method / class annotation processing.
	 *
	 * @param array  $annotations    annotations
	 * @param string $name	         annotation name
	 *
	 * @return array
	 */
	protected function getAnnotationTokensByName($annotations, $name) {
		if ($annotations === null || !array_key_exists($name, $annotations) || !is_array($annotations[$name])) {
			return [];
		}

		$result = [];
		foreach ($annotations[$name] as $annotation) {
			foreach (explode(',', $annotation) as $token) {
				$result[] = trim($token);
			}
		}

		return $result;
	}

	/**
	 * Execute callbacks specified at some point of test execution.
	 *
	 * @param mixed $context		class instance or class name
	 * @param array $callbacks		callbacks to be called
	 * @param bool $required		flag marking callbacks required
	 *
	 * @return boolean
	 */
	protected static function executeCallbacks($context, $callbacks, $required = false) {
		if (!$callbacks) {
			return true;
		}

		CDataHelper::setSessionId(null);

		$class = new ReflectionClass($context);
		if (!is_object($context)) {
			$context = null;
		}

		foreach ($callbacks as $callback) {
			try {
				$method = $class->getMethod($callback);
			}
			catch (ReflectionException $exception) {
				$method = null;
			}

			if (!$method) {
				$error = 'Callback "'.$callback.'" is not defined in requested context.';
				if (!$required) {
					self::zbxAddWarning($error);
				}
				else {
					throw new Exception($error);
				}

				continue;
			}

			try {
				$method->invoke(!$method->isStatic() ? $context : null);
			} catch (Exception $e) {
				$error = 'Failed to execute callback "'.$callback.'": '.$e->getMessage()."\n\n".$e->getTraceAsString();
				if (!$required) {
					self::zbxAddWarning($error);
				}
				else {
					throw new Exception($error);
				}

				return false;
			}
		}

		return true;
	}

	/**
	 * Callback executed before every test suite.
	 */
	protected function onBeforeTestSuite() {
		// Test suite level annotations.
		$class_annotations = $this->getAnnotationsByType($this->annotations, 'class');

		// Data sources are processed before the backups.
		$data_source = $this->getAnnotationTokensByName($class_annotations, 'dataSource');
		if ($data_source) {
			CDataHelper::load($data_source);
		}

		// Backup performed before test suite execution.
		$suite_backup = $this->getAnnotationTokensByName($class_annotations, 'backup');

		if ($suite_backup) {
			self::$suite_backup = $suite_backup;
			CDBHelper::backupTables(self::$suite_backup);
		}

		$suite_backup_config = $this->getAnnotationTokensByName($class_annotations, 'backupConfig');

		if ($suite_backup_config) {
			self::$suite_backup_config = true;
			CConfigHelper::backupConfig();
		}

		self::$skip_suite = false;

		// Callbacks to be performed before test suite execution.
		$callbacks = $this->getAnnotationTokensByName($class_annotations, 'onBefore');
		if (!self::executeCallbacks($this, $callbacks)) {
			self::markTestSuiteSkipped();
			throw new Exception(implode("\n", static::$warnings));
		}

		// Store callback to be executed later.
		self::$suite_callbacks = ['afterOnce' => []];
		foreach (['beforeEach', 'afterEach', 'after'] as $key) {
			self::$suite_callbacks[$key] = $this->getAnnotationTokensByName($class_annotations, 'on'.ucfirst($key));
		}
	}

	/**
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		global $DB;
		static $suite = null;
		$class_name = get_class($this);
		$case_name = $this->getName(false);
		self::$warnings = [];

		// Clear contents of error log.
		if (defined('PHPUNIT_ERROR_LOG') && file_exists(PHPUNIT_ERROR_LOG)) {
			@file_put_contents(PHPUNIT_ERROR_LOG, '');
		}

		if (!isset($DB['DB'])) {
			DBconnect($error);
		}

		$this->annotations = $this->getAnnotations();

		if (self::$last_test_case !== $case_name) {
			// Restore data from backup if test case changed.
			if (self::$case_backup_once !== null) {
				CDBHelper::restoreTables();
				self::$case_backup_once = null;
			}

			self::executeCallbacks($this, self::$suite_callbacks['afterOnce']);
			self::$suite_callbacks['afterOnce'] = [];
		}

		// Class name change is used to determine suite change.
		if ($suite !== $class_name) {
			$suite = $class_name;
			$this->onBeforeTestSuite();
		}

		// Execute callbacks that should be executed before every test case.
		self::executeCallbacks($this, self::$suite_callbacks['beforeEach'], true);

		// Test case level annotations.
		$method_annotations = $this->getAnnotationsByType($this->annotations, 'method');

		if ($method_annotations !== null) {
			// Data sources are processed before the backups.
			$data_source = $this->getAnnotationTokensByName($method_annotations, 'dataSource');
			if ($data_source) {
				CDataHelper::load($data_source);
			}

			// Backup performed before every test case execution.
			$case_backup = $this->getAnnotationTokensByName($method_annotations, 'backup');

			if ($case_backup) {
				$this->case_backup = $case_backup;
				CDBHelper::backupTables($this->case_backup);
			}

			$case_backup_config = $this->getAnnotationTokensByName($method_annotations, 'backupConfig');

			if ($case_backup_config) {
				$this->case_backup_config = true;
				CConfigHelper::backupConfig();
			}

			if (self::$last_test_case !== $case_name) {
				if (array_key_exists($case_name, self::$test_data_sets)) {
					// Check for data set limit.
					$limit = $this->getAnnotationTokensByName($method_annotations, 'dataLimit');

					if (count($limit) === 1 && is_numeric($limit[0]) && $limit[0] >= 1
							&& count(self::$test_data_sets[$case_name]) > $limit[0]) {
						$sets = self::$test_data_sets[$case_name];
						shuffle($sets);
						self::$test_data_sets[$case_name] = array_slice($sets, 0, (int)$limit[0]);
					}
				}

				// Backup performed once before first test case execution.
				$case_backup_once = $this->getAnnotationTokensByName($method_annotations, 'backupOnce');

				if ($case_backup_once) {
					self::$case_backup_once = $case_backup_once;
					CDBHelper::backupTables(self::$case_backup_once);
				}

				// Execute callbacks that should be executed once for multiple test cases.
				self::executeCallbacks($this, $this->getAnnotationTokensByName($method_annotations, 'onBeforeOnce'), true);

				// Store callback to be executed after test case is executed for all data sets.
				self::$suite_callbacks['afterOnce'] = $this->getAnnotationTokensByName($method_annotations,
						'onAfterOnce'
				);
			}

			// Execute callbacks that should be executed before specific test case.
			self::executeCallbacks($this, $this->getAnnotationTokensByName($method_annotations, 'onBefore'), true);

			// Store callback to be executed after test case.
			$this->case_callbacks = $this->getAnnotationTokensByName($method_annotations, 'onAfter');
		}

		self::$last_test_case = $case_name;

		// Mark excessive test cases as skipped.
		if (array_key_exists($case_name, self::$test_data_sets)
				&& !in_array($this->data_key, self::$test_data_sets[$case_name])) {
			self::markTestSkipped('Test case skipped by data provider limit check.');
		}

		if (self::$skip_suite) {
			self::markTestSkipped();
		}
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		$errors = @file_get_contents(PHPUNIT_ERROR_LOG);

		if ($this->case_backup_config) {
			CConfigHelper::restoreConfig();
			$this->case_backup_config = false;
		}

		if (CDataHelper::getSessionId() !== null) {
			foreach (CDBHelper::$backups as $backup) {
				if (in_array('sessions', $backup)) {
					CDataHelper::reset();
				}
			}
		}

		if ($this->case_backup !== null) {
			CDBHelper::restoreTables();
		}

		// Execute callbacks that should be executed after specific test case.
		self::executeCallbacks($this, $this->case_callbacks);

		// Execute callbacks that should be executed after every test case.
		self::executeCallbacks($this, self::$suite_callbacks['afterEach']);

		DBclose();

		if (defined('PHPUNIT_REPORT_WARNINGS') && PHPUNIT_REPORT_WARNINGS && self::$warnings) {
			throw new PHPUnit_Framework_Warning(implode("\n", self::$warnings));
		}

		if ($errors !== '' && $errors !== false) {
			$this->fail("Runtime errors:\n".$errors);
		}
	}

	/**
	 * Callback executed after every test suite.
	 *
	 * @afterClass
	 */
	public static function onAfterTestSuite() {
		global $DB;

		if (self::$suite_backup_config) {
			CConfigHelper::restoreConfig();
			self::$suite_backup_config = false;
		}

		if (self::$suite_backup === null && self::$case_backup_once === null && !self::$suite_callbacks['afterOnce']
				&& !self::$suite_callbacks['after']) {

			// Nothing to do after test suite.
			return;
		}

		DBconnect($error);

		// Restore case level backups.
		if (self::$case_backup_once !== null) {
			CDBHelper::restoreTables();
			self::$case_backup_once = null;
		}

		// Restore suite level backups.
		if (self::$suite_backup !== null) {
			CDBHelper::restoreTables();
			self::$suite_backup = null;
		}

		$context = get_called_class();
		self::executeCallbacks($context, self::$suite_callbacks['afterOnce']);
		self::executeCallbacks($context, self::$suite_callbacks['after']);

		DBclose();
	}

	/**
	 * Add warning to test case warning list.
	 *
	 * @param string $warning    warning text
	 */
	public static function zbxAddWarning($warning) {
		if (!in_array($warning, self::$warnings)) {
			self::$warnings[] = $warning;
		}
	}

	/**
	 * Mark test suite skipped.
	 */
	public static function markTestSuiteSkipped() {
		self::$skip_suite = true;
	}

	/**
	 * Callback to be executed after all test cases.
	 */
	public static function onAfterAllTests() {
		// Code is not missing here.
	}

	/**
	 * Get list of static behaviors.
	 * Static behaviors get attached when object is created.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [];
	}

	/**
	 * Load static behaviors.
	 */
	public function loadBehaviors() {
		if ($this->behaviors !== null) {
			return;
		}

		$this->behaviors = [];
		foreach ($this->getBehaviors() as $name => $behavior) {
			if (is_int($name)) {
				$name = null;
			}

			$this->attachBehavior($behavior, $name);
		}
	}

	/**
	 * Attach dynamic behavior.
	 *
	 * @param string|CBehavior $behavior    behavior or behavior class name
	 * @param string           $name        name of the behavior or null for anonymous behavior
	 *
	 * @throws Exception    on invalid configuration
	 */
	public function attachBehavior($behavior, $name = null) {
		$this->loadBehaviors();

		if (is_string($behavior)) {
			$behavior = ['class' => $behavior];
		}

		if (is_array($behavior) && array_key_exists('class', $behavior) && class_exists($behavior['class'])) {
			$class = $behavior['class'];
			unset($behavior['class']);
			$behavior = new $class($behavior);
		}

		if ($behavior instanceof CBehavior) {
			if ($name !== null) {
				$this->detachBehavior($name);
			}

			$behavior->setTest($this);
			if ($name !== null) {
				$this->behaviors[$name] = $behavior;
			}
			else {
				$this->behaviors[] = $behavior;
			}
		}
		else {
			throw new Exception('Cannot attach behavior that is not an instance of CBehavior class');
		}
	}

	/**
	 * Detach dynamic behavior.
	 *
	 * @param string $name        name of the behavior or null for anonymous behavior
	 */
	public function detachBehavior($name) {
		$this->loadBehaviors();

		unset($this->behaviors[$name]);
	}

	/**
	 * Detach all behaviors.
	 */
	public function detachBehaviors() {
		$this->behaviors = [];
	}

	/**
	 * Magic method to execute methods defined in behaviors.
	 *
	 * @param string $name      method name
	 * @param array  $params    method params
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function __call($name, $params) {
		$this->loadBehaviors();

		$target = null;
		foreach ($this->behaviors as $behavior) {
			if ($behavior->hasMethod($name)) {
				$target = $behavior;
			}
		}

		if ($target !== null) {
			return call_user_func_array([$target, $name], $params);
		}

		throw new Exception('Cannot call method '.get_class($this).'::'.$name.'(): unknown method.');
	}

	/**
	 * Magic method to get attributes defined in behaviors.
	 *
	 * @param string $name      attribute name
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function __get($name) {
		$this->loadBehaviors();

		foreach ($this->behaviors as $behavior) {
			if ($behavior->hasAttribute($name)) {
				return $behavior->$name;
			}
		}

		throw new Exception('Cannot get attribute "'.$name.'": unknown attribute.');
	}

	/**
	 * Magic method to set attributes defined in behaviors.
	 *
	 * @param string $name     attribute name
	 * @param array  $value    attribute value
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function __set($name, $value) {
		$this->loadBehaviors();

		foreach ($this->behaviors as $behavior) {
			if ($behavior->hasAttribute($name)) {
				$behavior->$name = $value;

				return;
			}
		}

		throw new Exception('Cannot set attribute "'.$name.'": unknown attribute.');
	}
}
