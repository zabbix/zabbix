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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/CTest.php';
require_once dirname(__FILE__).'/web/CPage.php';
require_once dirname(__FILE__).'/helpers/CXPathHelper.php';

define('TEST_GOOD', 0);
define('TEST_BAD', 1);
define('TEST_ERROR', 2);

/**
 * Base class for Selenium tests.
 */
class CWebTest extends CTest {
	// Screenshot capture on error.
	private $capture_screenshot = true;

	// Screenshot taken on test failure.
	private $screenshot = null;
	// Failed test URL.
	private $current_url = null;

	// Shared page instance.
	private static $shared_page = null;
	// Enable supressing of browser errors on test case level.
	private $supress_case_errors = false;
	// Enable supressing of browser errors on test suite level.
	private static $supress_suite_errors = false;

	// Instance of web page.
	protected $page = null;

	/**
	 * @inheritdoc
	 */
	protected function onNotSuccessfulTest($exception) {
		if ($this->screenshot !== null && $exception instanceof Exception) {
			$screenshot_name = md5(microtime(true)).'.png';

			if (file_put_contents(PHPUNIT_SCREENSHOT_DIR.$screenshot_name, $this->screenshot) !== false) {
				$runtime_errors = @file_get_contents(PHPUNIT_ERROR_LOG);
				$runtime_errors = $runtime_errors ? "\n\nRuntime errors:\n".$runtime_errors : '';

				CExceptionHelper::setMessage($exception, 'URL: '.$this->current_url."\n".
						'Screenshot: '.PHPUNIT_SCREENSHOT_URL.$screenshot_name."\n".
						$exception->getMessage().$runtime_errors
				);

				$this->screenshot = null;
			}
		}

		if (($exception instanceof PHPUnit_Framework_SkippedTestError) === false
				&& ($exception instanceof PHPUnit_Framework_Warning) === false) {
			self::closePage();
		}

		parent::onNotSuccessfulTest($exception);
	}

	/**
	 * @inheritdoc
	 */
	protected function tearDown() {
		// Check for JS errors.
		if (!$this->hasFailed()) {
			if (!$this->supress_case_errors && self::$shared_page !== null) {
				$errors = [];

				foreach (self::$shared_page->getBrowserLog() as $log) {
					$errors[] = $log['message'];
				}

				if ($errors) {
					$this->captureScreenshot();
					$this->fail("Severe browser errors:\n" . implode("\n", array_unique($errors)));
				}
			}
		}
		else {
			$this->captureScreenshot();
		}
	}

	/**
	 * Capture screenshot if screenshot capturing is enabled.
	 */
	private function captureScreenshot() {
		try {
			if ($this->capture_screenshot) {
				$this->current_url = self::$shared_page->getCurrentUrl();
				$this->screenshot = self::$shared_page->takeScreenshot();
			}
		}
		catch (Exception $exception) {
			// Error handling is not missing here.
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function onBeforeTestSuite() {
		parent::onBeforeTestSuite();

		// Browser errors are not ignored by default.
		self::$supress_suite_errors = false;

		// Test suite level annotations.
		$class_annotations = $this->getAnnotationsByType($this->annotations, 'class');

		// Supress browser error on a test case level.
		$supress_suite_errors = $this->getAnnotationsByType($class_annotations, 'ignore-browser-errors');
		self::$supress_suite_errors = ($supress_suite_errors !== null);
	}

	/**
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		parent::onBeforeTestCase();

		// Share page when it is possible.
		if (self::$shared_page === null) {
			self::$shared_page = new CPage();
		}

		$this->page = self::$shared_page;

		// Test case level annotations.
		$method_annotations = $this->getAnnotationsByType($this->annotations, 'method');
		if ($method_annotations !== null) {
			// Supress browser error on a test case level.
			$supress_case_errors = $this->getAnnotationsByType($method_annotations, 'ignore-browser-errors');
			$this->supress_case_errors = ($supress_case_errors !== null);
		}

		// Errors on a test case level should be supressed if suite level error supression is enabled.
		if (self::$supress_suite_errors) {
			$this->supress_case_errors = self::$supress_suite_errors;
		}
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		if (!self::$shared_page->cleanup()) {
			self::closePage();
		}

		parent::onAfterTestCase();
	}

	/**
	 * Callback executed after every test suite.
	 *
	 * @afterClass
	 */
	public static function onAfterTestSuite() {
		// Page is always terminated at the end of the test suite.
		self::closePage();

		parent::onAfterTestSuite();
	}

	/**
	 * Close shared page instance.
	 */
	protected static function closePage() {
		try {
			if (self::$shared_page !== null) {
				self::$shared_page->destroy();
				self::$shared_page = null;
			}
		}
		catch (Exception $exception) {
			// Error handling is not missing here.
		}
	}

	/**
	 * Create CElementQuery instance.
	 * @see CElementQuery, CPage::query
	 *
	 * @param string $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return CElementQuery
	 */
	public function query($type, $locator = null) {
		return $this->page->query($type, $locator);
	}
}
