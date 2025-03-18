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

require_once __DIR__.'/CTest.php';
require_once __DIR__.'/web/CPage.php';
require_once __DIR__.'/helpers/CDataHelper.php';
require_once __DIR__.'/helpers/CXPathHelper.php';
require_once __DIR__.'/helpers/CImageHelper.php';
require_once __DIR__.'/../../include/classes/helpers/CMessageHelper.php';
require_once __DIR__.'/../../include/classes/routing/CUrl.php';

require_once __DIR__.'/../selenium/behaviors/CMacrosBehavior.php';
require_once __DIR__.'/../selenium/behaviors/CMessageBehavior.php';
require_once __DIR__.'/../selenium/behaviors/CPreprocessingBehavior.php';
require_once __DIR__.'/../selenium/behaviors/CTagBehavior.php';
require_once __DIR__.'/../selenium/behaviors/CTableBehavior.php';
require_once __DIR__.'/../selenium/behaviors/CWidgetBehavior.php';

define('TEST_GOOD', 0);
define('TEST_BAD', 1);
define('TEST_ERROR', 2);

/**
 * Base class for Selenium tests.
 */
class CWebTest extends CTest {

	// Network throttling emulation modes.
	const NETWORK_THROTTLING_NONE		= 'none';
	const NETWORK_THROTTLING_OFFLINE	= 'offline';
	const NETWORK_THROTTLING_SLOW		= 'slow';
	const NETWORK_THROTTLING_FAST		= 'fast';
	const HOST_LIST_PAGE				= 'zabbix.php?action=host.list';

	// Screenshot capture on error.
	private $capture_screenshot = true;

	// Screenshot taken on test failure.
	private $screenshot = null;
	// Errors captured during the test.
	protected $errors = [];
	// Failed test URL.
	private $current_url = null;
	// Browser errors captured during test.
	private $browser_errors = null;

	// Shared page instance.
	private static $shared_page = null;
	// Enable suppressing of browser errors on test case level.
	private $suppress_case_errors = false;
	// Enable suppressing of browser errors on test suite level.
	private static $suppress_suite_errors = false;

	// Instance of web page.
	protected $page = null;

	// Shared screenshot data.
	protected static $screenshot_data = [];

	/**
	 * @inheritdoc
	 */
	protected function onNotSuccessfulTest($exception): void {
		if ($this->browser_errors !== null && $exception instanceof Exception) {
			CExceptionHelper::setMessage($exception, $exception->getMessage()."\n\n".$this->browser_errors);
		}

		if ($this->errors !== [] && $exception instanceof Exception) {
			CExceptionHelper::setMessage($exception, $exception->getMessage()."\n\n".implode("\n",$this->errors));
		}

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
	protected function assertPostConditions() : void {
		// Check for JS errors.
		$errors = [];
		if (self::$shared_page !== null) {
			foreach (self::$shared_page->getBrowserLog() as $log) {
				$errors[] = $log['message'];
			}
		}

		if ($errors) {
			$errors = "Severe browser errors:\n".implode("\n", array_unique($errors));

			if (!$this->hasFailed() && $this->getStatus() !== null) {
				if (!$this->suppress_case_errors) {
					$this->captureScreenshot();
					$this->fail($errors);
				}
			}
			else {
				$this->browser_errors = $errors;
			}
		}

		if ($this->errors) {
			if (!$this->hasFailed() && $this->getStatus() !== null) {
				$this->fail('Test case errors.');
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function tearDown() : void {
		if ($this->hasFailed() || $this->getStatus() === null) {
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
		self::$suppress_suite_errors = false;

		// Test suite level annotations.
		$class_annotations = $this->getAnnotationsByType($this->annotations, 'class');

		// Suppress browser error on a test case level.
		$suppress_suite_errors = $this->getAnnotationsByType($class_annotations, 'ignoreBrowserErrors');
		self::$suppress_suite_errors = ($suppress_suite_errors !== null);

		// Browsers supported by test suite.
		$browsers = $this->getAnnotationTokensByName($class_annotations, 'browsers');
		if ($browsers) {
			$mapping = [
				'MicrosoftEdge' => 'edge'
			];

			$browser = defined('PHPUNIT_BROWSER_NAME') ? PHPUNIT_BROWSER_NAME : 'chrome';
			if (array_key_exists($browser, $mapping)) {
				$browser = $mapping[$browser];
			}

			if (!in_array($browser, $browsers)) {
				self::markTestSuiteSkipped();
				return;
			}
		}
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

		$this->errors = [];
		$this->page = self::$shared_page;

		// Test case level annotations.
		$method_annotations = $this->getAnnotationsByType($this->annotations, 'method');
		if ($method_annotations !== null) {
			// Suppress browser error on a test case level.
			$suppress_case_errors = $this->getAnnotationsByType($method_annotations, 'ignoreBrowserErrors');
			$this->suppress_case_errors = ($suppress_case_errors !== null);
		}

		// Errors on a test case level should be suppressed if suite level error suppression is enabled.
		if (self::$suppress_suite_errors) {
			$this->suppress_case_errors = self::$suppress_suite_errors;
		}

		// Browsers supported by test case.
		$browsers = $this->getAnnotationTokensByName($method_annotations, 'browsers');
		if ($browsers) {
			$mapping = [
				'MicrosoftEdge' => 'edge'
			];

			$browser = defined('PHPUNIT_BROWSER_NAME') ? PHPUNIT_BROWSER_NAME : 'chrome';
			if (array_key_exists($browser, $mapping)) {
				$browser = $mapping[$browser];
			}

			if (!in_array($browser, $browsers)) {
				self::markTestSkipped('Test case is not supported in this browser.');
				return;
			}
		}
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		// Reset default fill mode for multiselect elements.
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);
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

	/**
	 * Get instance of web page used in this test.
	 *
	 * @return CPage
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * Normalize regions defined in various formats.
	 *
	 * @param CElement|null $element    element to get screenshot of (set to null to get screenshot of a page)
	 * @param array         $regions    regions to be normalized
	 *
	 * @return array
	 */
	protected function getNormalizedRegions($element, $regions) {
		if (!is_array($regions) || CTestArrayHelper::isAssociative($regions)) {
			$regions = [$regions];
		}

		$append = [];
		$offset = ($element instanceof CElement) ? $element->getRect() : ['x' => 0, 'y' => 0];
		foreach ($regions as $i => &$region) {
			if (is_array($region)) {
				$color = array_key_exists('color', $region) ? $region['color'] : null;

				if (array_key_exists('element', $region)) {
					if (!$region['element'] instanceof CElement) {
						$this->fail('Except element is not an instance of CElement.');
					}
				}
				elseif (array_key_exists('query', $region)) {
					if ($region['query'] instanceof CElementQuery) {
						$query = $region['query'];
					}
					else {
						$source = ($element instanceof CElement) ? $element : $this->page;
						$query = $source->query($region['query']);
					}

					foreach ($query->all() as $item) {
						$append[] = array_merge(['element' => $item], ($color !== null) ? ['color' => $color] : []);
					}

					unset($regions[$i]);
				}
				elseif (is_array($region) && (!array_key_exists('x', $region) || !array_key_exists('y', $region)
						|| !array_key_exists('width', $region) || !array_key_exists('height', $region))) {
					$this->fail('Screenshot except configuration is invalid.');
				}
			}
			elseif ($region instanceof CElement) {
				$region = ['element' => $region];
			}
			else {
				$this->fail('Screenshot except configuration is invalid.');
			}
		}
		unset($region);

		foreach ($append as &$region) {
			if (array_key_exists('element', $region)) {
				continue;
			}

			$region['x'] -= $offset['x'];
			$region['y'] -= $offset['y'];
		}
		unset($region);

		return array_merge(array_values($regions), $append);
	}

	/**
	 * Perform screenshot comparison.
	 *
	 * @param CElement|null $element    element to get screenshot of (set to null to get screenshot of a page)
	 * @param string|null   $id         unique id of the screenshot
	 * @param string|null   $message    error message if assertion fails
	 */
	public function assertScreenshot($element = null, $id = null, $message = null) {
		$this->assertScreenshotExcept($element, [], $id, $message);
	}

	/**
	 * Perform screenshot comparison with specified regions covered.
	 *
	 * @param CElement|null $element    element to get screenshot of (set to null to get screenshot of a page)
	 * @param array         $regions    regions to be covered on a screenshot
	 * @param string|null   $id         unique id of the screenshot
	 * @param string|null   $message    error message if assertion fails
	 */
	public function assertScreenshotExcept($element = null, $regions = [], $id = null, $message = null) {
		if ($message === null) {
			$message = 'Screenshots don\'t match.';
		}

		$script = 'var tag = document.createElement("style");tag.setAttribute("id", "selenium-injected-style");'.
				'tag.textContent = "* {text-rendering: geometricPrecision; image-rendering: pixelated}'.
				' .selenium-hide {opacity: 0 !important;transition: opacity 0s !important;}'.
				' #selenium-injected-pixel {position:absolute;right:0;bottom:0;width:1px;height:1px;opacity:0}";'.
				'(document.head||document.documentElement).appendChild(tag);'.
				'var pix = document.createElement("div");pix.setAttribute("id", "selenium-injected-pixel");'.
				'(document.body||document.documentElement).appendChild(pix);';

		try {
			$this->page->getDriver()->executeScript($script);

			$pixel = $this->query('id:selenium-injected-pixel')->one(false);
			if ($pixel->isValid()) {
				$pixel->moveMouse();
			}
		} catch (Exception $exception) {
			// Code is not missing here.
		}

		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
		if (($class = CTestArrayHelper::get($backtrace, '1.class')) === CWebTest::class) {
			$class = CTestArrayHelper::get($backtrace, '2.class');
			$function = CTestArrayHelper::get($backtrace, '2.function');
		}
		else {
			$function = CTestArrayHelper::get($backtrace, '1.function');
		}

		if ($function === null && $id === null) {
			$this->fail('Cannot get unique name of the screenshot.');
		}

		try {
			$name = md5($function.$id).'.png';

			$coordinates = [];
			foreach ($this->getNormalizedRegions($element, $regions) as $region) {
				if (array_key_exists('element', $region)) {
					try {
						$this->page->getDriver()->executeScript('arguments[0].classList.add(\'selenium-hide\');', [$region['element']]);
					} catch (Exception $exception) {
						// Code is not missing here.
					}
				}
				else {
					$coordinates[] = $region;
				}
			}

			$screenshot = CImageHelper::getImageWithoutRegions($this->page->takeScreenshot($element), $coordinates);

			if (($reference = @file_get_contents(PHPUNIT_REFERENCE_DIR.$class.'/'.$name)) === false) {
				if (file_put_contents(PHPUNIT_SCREENSHOT_DIR.'ref_'.$name, $screenshot) !== false) {
					static::$screenshot_data[] = [
						'class'		=> $class,
						'function'	=> $function,
						'id'		=> $id,
						'delta'		=> null,
						'error'		=> 'Reference screenshot is not set.'
					];

					throw new Exception("Reference screenshot is not set.\nCurrent screenshot saved: ".
							PHPUNIT_SCREENSHOT_URL.'ref_'.$name
					);
				}

				$this->fail('Reference screenshot is not set and cannot be created.');
			}

			$compare = CImageHelper::compareImages($reference, $screenshot);

			if ($compare['match'] === false) {
				static::$screenshot_data[] = [
					'class'		=> $class,
					'function'	=> $function,
					'id'		=> $id,
					'delta'		=> $compare['delta'],
					'error'		=> $compare['error']
				];

				if (file_put_contents(PHPUNIT_SCREENSHOT_DIR.'ref_'.$name, $screenshot) === false) {
					$this->fail($message."\n".'Cannot save current screenshot.');
				}

				if ($compare['ref'] !== null
						&& file_put_contents(PHPUNIT_SCREENSHOT_DIR.'src_'.$name, $compare['ref']) === false) {
					$this->fail($message."\n".'Cannot save reference screenshot.');
				}

				if ($compare['diff'] !== null) {
					if (file_put_contents(PHPUNIT_SCREENSHOT_DIR.'diff_'.$name, $compare['diff']) === false) {
						$this->fail($message."\n".'Cannot save screenshot diff.');
					}

					throw new Exception($message."\n".'Diff: '.PHPUNIT_SCREENSHOT_URL.'diff_'.$name);
				}
				else {
					throw new Exception($message.' ('.$compare['error'].")\nReference saved: ".PHPUNIT_SCREENSHOT_URL.'ref_'.$name);
				}
			}
		}
		catch (PHPUnit_Framework_AssertionFailedError $failure) {
			throw $failure;
		}
		catch (Exception $e) {
			$this->addCaseError($e->getMessage());
		}

		try {
			$this->page->getDriver()->executeScript('document.getElementById("selenium-injected-style").remove();'.
					'document.getElementById("selenium-injected-pixel").remove();');
		} catch (Exception $exception) {
			// Code is not missing here.
		}
	}

	/**
	 * @inheritdoc
	 */
	public static function onAfterAllTests() {
		if (self::$screenshot_data) {
			$data = [
				'url'		=> PHPUNIT_SCREENSHOT_URL,
				'report'	=> self::$screenshot_data
			];

			if (@file_put_contents(PHPUNIT_SCREENSHOT_DIR.'report.json', json_encode($data))) {
				echo 'Screenshot data report is saved as: '.PHPUNIT_SCREENSHOT_URL.'report.json'."\n";
			}
			else {
				echo 'Failed to save screenshot data report.'."\n";
			}
		}
	}

	/**
	 * Set network throttling mode.
	 *
	 * @param string $mode    one of the NETWORK_THROTTLING_* constants
	 *
	 * @return boolean
	 *
	 * @throws Exception on invalid throttling mode
	 */
	public function setNetworkThrottlingMode($mode) {
		$modes = [
			self::NETWORK_THROTTLING_NONE => [
				'emulation' => false,
				'cache' => true,
				'offline' => false,
				'latency' => 0,
				'downloadThroughput' => -1,
				'uploadThroughput' => -1
			],
			self::NETWORK_THROTTLING_OFFLINE => [
				'emulation' => true,
				'cache' => false,
				'offline' => true,
				'latency' => 0,
				'downloadThroughput' => -1,
				'uploadThroughput' => -1
			],
			self::NETWORK_THROTTLING_SLOW => [
				'emulation' => true,
				'cache' => false,
				'offline' => false,
				'latency' => 200,
				'downloadThroughput' => 32 * 1024,
				'uploadThroughput' => 4 * 1024
			],
			self::NETWORK_THROTTLING_FAST => [
				'emulation' => true,
				'cache' => true,
				'offline' => false,
				'latency' => 50,
				'downloadThroughput' => 128 * 1024,
				'uploadThroughput' => 32 * 1024
			]
		];

		if (!array_key_exists($mode, $modes)) {
			throw new Exception('Unknown network throttling mode.');
		}

		$options = $modes[$mode];

		try {
			CommandExecutor::executeCustom($this->page->getDriver(), [
				'cmd' => 'Network.'.($options['emulation'] ? 'enable' : 'disable'),
				'params' => [
					'enable' => $options['emulation']
				]
			]);

			CommandExecutor::executeCustom($this->page->getDriver(), [
				'cmd' => 'Network.setCacheDisabled',
				'params' => [
					'cacheDisabled'	=> !$options['cache']
				]
			]);

			CommandExecutor::executeCustom($this->page->getDriver(), [
				'cmd' => 'Network.emulateNetworkConditions',
				'params' => [
					'offline' => $options['offline'],
					'latency' => $options['latency'],
					'downloadThroughput' => $options['downloadThroughput'],
					'uploadThroughput' => $options['uploadThroughput']
				]
			]);

			return true;
		} catch (Exception $exception) {
			return false;
		}
	}

	/**
	 * Set CPU throttling rate.
	 *
	 * @param integer $rate    throttling rate as a slowdown factor (1 is no throttle, 2 is 2x slowdown, etc).
	 *
	 * @return boolean
	 *
	 * @throws Exception on invalid throttling mode
	 */
	public function setCPUThrottlingRate($rate) {
		if (!is_int($rate) || $rate < 1) {
			throw new Exception('CPU throttling rate should be a positive integer starting from 1.');
		}

		try {
			CommandExecutor::executeCustom($this->page->getDriver(), [
				'cmd' => 'Emulation.setCPUThrottlingRate',
				'params' => [
					'rate'	=> $rate
				]
			]);

			return true;
		} catch (Exception $exception) {
			return false;
		}
	}

	/**
	 * Adds test case error to the error list. Case errors are reported at the end of the test.
	 *
	 * @param string $error    error message
	 */
	public function addCaseError($error) {
		$this->errors[] = $error;
	}
}
