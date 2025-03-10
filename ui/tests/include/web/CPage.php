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

require_once __DIR__.'/CElementQuery.php';
require_once __DIR__.'/CommandExecutor.php';

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\Exception\NoSuchAlertException;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Web page implementation.
 */
class CPage {

	/**
	 * Page defaults.
	 */
	const DEFAULT_PAGE_WIDTH = 1440;
	const DEFAULT_PAGE_HEIGHT = 1024;

	/**
	 * Web driver instance.
	 *
	 * @var RemoteWebDriver
	 */
	protected $driver;

	/**
	 * Local cookie cache.
	 *
	 * @var array
	 */
	protected static $cookie = null;

	/**
	 * Page height.
	 *
	 * @var integer
	 */
	protected $height = null;

	/**
	 * Page width.
	 *
	 * @var integer
	 */
	protected $width = null;

	/**
	 * Viewport freeze flag.
	 *
	 * @var boolean
	 */
	protected $viewportUpdated = false;

	/**
	 * Web driver and CElementQuery initialization.
	 */
	public function __construct() {
		$this->connect();
		CElementQuery::setPage($this);
	}

	/**
	 * Web driver initialization.
	 */
	public function connect() {
		$capabilities = DesiredCapabilities::chrome();
		if (defined('PHPUNIT_BROWSER_NAME')) {
			$capabilities->setBrowserName(PHPUNIT_BROWSER_NAME);
		}

		if (!defined('PHPUNIT_BROWSER_NAME') || PHPUNIT_BROWSER_NAME === 'chrome') {
			$options = new ChromeOptions();
			$options->addArguments([
				'--no-sandbox',
				'--enable-font-antialiasing=false',
				'--window-size='.self::DEFAULT_PAGE_WIDTH.','.self::DEFAULT_PAGE_HEIGHT,
				'--disable-dev-shm-usage',
				'--autoplay-policy=no-user-gesture-required',
				'--remote-debugging-pipe'
			]);

			if (defined('PHPUNIT_BROWSER_LOG_DIR')) {
				$options->addArguments([
					'--enable-logging',
					'--log-file='.PHPUNIT_BROWSER_LOG_DIR.'/'.microtime(true).'.log',
					'--log-level=0'
				]);
			}

			$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
		}

		$phpunit_driver_address = PHPUNIT_DRIVER_ADDRESS;

		if (strpos($phpunit_driver_address, ':') === false) {
			$phpunit_driver_address .= ':4444';
		}

		$this->driver = RemoteWebDriver::create('http://'.$phpunit_driver_address.'/wd/hub', $capabilities);
		$this->driver->setCommandExecutor(new CommandExecutor($this->driver->getCommandExecutor()));

		$this->driver->manage()->window()->setSize(
				new WebDriverDimension(self::DEFAULT_PAGE_WIDTH, self::DEFAULT_PAGE_HEIGHT)
		);
	}

	/**
	 * Perform page cleanup.
	 * Close all popup windows, switch to the initial window, remove cookies.
	 */
	public function cleanup() {
		$this->resetViewport();

		if (self::$cookie !== null) {
			foreach ($this->driver->manage()->getCookies() as $cookie) {
				if ($cookie->getName() === 'zbx_session') {
					if ($cookie->getValue() !== self::$cookie['value']) {
						self::$cookie = null;
					}
					break;
				}
			}
		}

		$this->driver->manage()->deleteAllCookies();
		try {
			$this->driver->executeScript('sessionStorage.clear();');
		} catch (Exception $exception) {
			// Code is not missing here.
		}

		$windows = $this->driver->getWindowHandles();
		if (count($windows) <= 1) {
			return true;
		}

		try {
			foreach (array_slice($windows, 1) as $window) {
				$this->driver->switchTo()->window($window);
				$this->driver->close();
			}
		}
		catch (Exception $exception) {
			// Error handling is not missing here.
		}

		if (count($this->driver->getWindowHandles()) >= 1) {
			try {
				$this->driver->switchTo()->window($windows[0]);
			}
			catch (Exception $exception) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Destroy web page.
	 */
	public function destroy() {
		$this->driver->quit();
		self::$cookie = null;
	}

	/**
	 * Reconnect web driver.
	 */
	public function reset() {
		$this->destroy();
		$this->connect();
	}

	/**
	 * Login as specified user.
	 *
	 * @param string  $sessionid
	 * @param integer $userid
	 *
	 * @return $this
	 */
	public function login(string $sessionid = '09e7d4286dfdca4ba7be15e0f3b2b55a', $userid = 1) {
		$session = CDBHelper::getRow('SELECT status FROM sessions WHERE sessionid='.zbx_dbstr($sessionid));

		if (!$session) {
			$secret = bin2hex(random_bytes(16));
			DBexecute('INSERT INTO sessions (sessionid,userid,lastaccess,secret)'.
				' VALUES ('.zbx_dbstr($sessionid).','.$userid.','.time().','.zbx_dbstr($secret).')'
			);
		}
		elseif ($session['status'] != 0) {	/* ZBX_SESSION_ACTIVE */
			DBexecute('UPDATE sessions SET status=0 WHERE sessionid='.zbx_dbstr($sessionid));
		}

		if (self::$cookie !== null) {
			$cookie = json_decode(base64_decode(urldecode(self::$cookie['value'])), true);
		}

		if (self::$cookie === null || $sessionid !== $cookie['sessionid']) {
			$data = ['sessionid' => $sessionid];

			$config = CDBHelper::getRow('SELECT session_key FROM config WHERE configid=1');
			$data['sign'] = hash_hmac('sha256', json_encode($data), $config['session_key'], false);

			$path = parse_url(PHPUNIT_URL, PHP_URL_PATH);
			self::$cookie = [
				'name' => 'zbx_session',
				'value' => base64_encode(json_encode($data)),
				'path' => rtrim(substr($path, 0, strrpos($path, '/')), '/')
			];

			$this->driver->get(PHPUNIT_URL);
		}

		$this->driver->manage()->addCookie(self::$cookie);

		return $this;
	}

	/**
	 * Logout and clean cookies.
	 */
	public function logout() {
		try {
			// Before logout open page without any scripts, otherwise session might be restored and logout won't work.
			$this->open('setup.php');

			$session = null;

			if (self::$cookie === null) {
				foreach ($this->driver->manage()->getCookies() as $cookie) {
					if ($cookie->getName() === 'zbx_session') {
						$session = $cookie->getValue();
						break;
					}
				}
			}
			else {
				$session = self::$cookie['value'];
			}

			if ($session !== null) {
				DBExecute('DELETE FROM sessions WHERE sessionid='.zbx_dbstr($session));
			}

			$this->driver->manage()->deleteAllCookies();
			self::$cookie = null;
		}
		catch (\Exception $e) {
			throw new \Exception('Cannot logout user: '.$e->getTraceAsString());
		}
	}

	/**
	 * Open specified URL.
	 *
	 * @param string $url   URL to be opened.
	 *
	 * @return $this
	 */
	public function open($url) {
		$this->driver->get(PHPUNIT_URL.$url);

		return $this;
	}

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->driver->getTitle();
	}

	/**
	 * Get current page URL.
	 *
	 * @return string
	 */
	public function getCurrentUrl() {
		return $this->driver->getCurrentURL();
	}

	/**
	 * Set width and height of viewport.
	 *
	 * @param int $width
	 * @param int $height
	 */
	protected function setViewport($width, $height) {
		try {
			CommandExecutor::executeCustom($this->driver, [
				'cmd' => 'Emulation.setDeviceMetricsOverride',
				'params' => [
					'width'				=> $width,
					'height'			=> $height,
					'deviceScaleFactor'	=> 1,
					'mobile'			=> false,
					'fitWindow'			=> false
				]
			]);
		} catch (Exception $exception) {
			// Code is not missing here.
		}
	}

	/**
	 * Setting "frozen" viewport size.
	 */
	public function updateViewport() {
		try {
			if (!$this->driver->executeScript('return !!window.chrome;')) {
				throw new Exception();
			}
		} catch (Exception $exception) {
			return false;
		}

		try {
			// Calculate page width and height depending on sidemenu and scrollbars presence.
			$size = $this->driver->executeScript(
				'var side = document.getElementsByClassName("sidebar")[0];'.
				'var wrapper = document.getElementsByClassName("wrapper")[0];'.

				'var width = ((typeof side !== "undefined") ? side.scrollWidth : 0)'.
					'+ ((typeof wrapper !== "undefined") ? wrapper.scrollWidth : 0);'.
				'var height = Math.max((typeof wrapper !== "undefined") ? wrapper.scrollHeight : 0,'.
				'(typeof side !== "undefined") ? side.scrollHeight : 0);'.
				'return'.
					'[(width !== 0) ? width : window.getComputedStyle(document.documentElement)["width"],'.
					'(height !== 0) ? height : window.getComputedStyle(document.documentElement)["height"],'.
					'(typeof wrapper !== "undefined" && wrapper.scrollWidth >'.
						'parseInt(window.getComputedStyle(wrapper)["width"], 10)) ? 20 : 0];'
				);

			$this->width = (int)$size[0];

			// Screenshot is 1px smaller to ensure that scroll is still present.
			$this->height = (int)$size[1] - 1;

			if ($this->height > self::DEFAULT_PAGE_HEIGHT || $this->width > self::DEFAULT_PAGE_WIDTH) {
				$this->setViewport(max([
					// Add 20px to page width when vertical scroll presents.
					$this->width + (int)$size[2], self::DEFAULT_PAGE_WIDTH]),
					max([$this->height, self::DEFAULT_PAGE_HEIGHT
				]));

				$this->viewportUpdated = true;
			}
		} catch (Exception $exception) {
			// Code is not missing here.
		}

		return true;
	}

	/**
	 * Resetting viewport size to default.
	 */
	public function resetViewport() {
		if ($this->viewportUpdated === false) {
			return;
		}

		if (isset($this->height) && $this->height > self::DEFAULT_PAGE_HEIGHT) {
			try {
				CommandExecutor::executeCustom($this->driver, [
					'cmd' => 'Emulation.clearDeviceMetricsOverride',
					'params' => ['clear' => true]
				]);
			} catch (Exception $exception) {
				// Code is not missing here.
			}

			$this->height = self::DEFAULT_PAGE_HEIGHT;
		}

		$this->viewportUpdated = false;
	}

	/**
	 * Take screenshot of current page.
	 *
	 * @return string
	 */
	protected function takePageScreenshot() {
		if ($this->viewportUpdated === true || !$this->updateViewport()) {
			return $this->driver->takeScreenshot();
		}

		$screenshot = $this->driver->takeScreenshot();
		$this->resetViewport();

		return $screenshot;
	}

	/**
	 * Take screenshot of current page or page element.
	 *
	 * @param CElement|null $element    page element to get screenshot of
	 *
	 * @return string
	 */
	public function takeScreenshot($element = null) {
		$screenshot = $this->takePageScreenshot();

		if ($element !== null) {
			$screenshot = CImageHelper::getImageRegion($screenshot, $element->getRect());
		}

		return $screenshot;
	}

	/**
	 * Get browser logs.
	 *
	 * @return array
	 */
	public function getBrowserLog() {
		return $this->driver->manage()->getLog('browser');
	}

	/**
	 * Get page source.
	 *
	 * @return type
	 */
	public function getSource() {
		return $this->driver->getPageSource();
	}

	/**
	 * Wait until page is ready.
	 *
	 * @param integer $timeout    timeout in seconds
	 */
	public function waitUntilReady($timeout = null) {
		return (new CElementQuery(null))->waitUntilReady($timeout);
	}

	/**
	 * Wait until alert is present.
	 */
	public function waitUntilAlertIsPresent($timeout = null) {
		CElementQuery::wait($timeout)->until(WebDriverExpectedCondition::alertIsPresent(),
				'Failed to wait for alert to be present.'
		);
	}

	/**
	 * Check if alert is present.
	 *
	 * @return boolean
	 */
	public function isAlertPresent() {
		return ($this->getAlertText() !== null);
	}

	/**
	 * Get alert text.
	 *
	 * @return string|null
	 */
	public function getAlertText() {
		try {
			return $this->driver->switchTo()->alert()->getText();
		}
		catch (NoSuchAlertException $exception) {
			return null;
		}
	}

	/**
	 * Wait until alert is present and accept it.
	 */
	public function acceptAlert() {
		$this->waitUntilAlertIsPresent();
		$this->driver->switchTo()->alert()->accept();
	}

	/**
	 * Wait until alert is present and dismiss it.
	 */
	public function dismissAlert() {
		$this->waitUntilAlertIsPresent();
		$this->driver->switchTo()->alert()->dismiss();
	}

	/**
	 * Emulate key presses.
	 *
	 * @param array|string $keys   keys to be pressed
	 */
	public function pressKey($keys) {
		if (!is_array($keys)) {
			$keys = [$keys];
		}

		$keyboard = $this->driver->getKeyboard();
		foreach ($keys as $key) {
			$keyboard->pressKey($key);
		}
	}

	/**
	 * Create CElementQuery instance.
	 * @see CElementQuery
	 *
	 * @param string $type     selector type (method) or selector
	 * @param string $locator  locator part of selector
	 *
	 * @return CElementQuery
	 */
	public function query($type, $locator = null) {
		return new CElementQuery($type, $locator);
	}

	/**
	 * Get web driver instance.
	 *
	 * @return RemoteWebDriver
	 */
	public function getDriver() {
		return $this->driver;
	}

	/**
	 * Remove focus from the element.
	 */
	public function removeFocus() {
		try {
			$this->driver->executeScript('for (var i = 0; i < 5; i++) if (document.activeElement.tagName !== "BODY")'.
					' document.activeElement.blur(); else break;');
		}
		catch (\Exception $ex) {
			throw new \Exception('Cannot remove focus.');
		}
	}

	/**
	 * Refresh page.
	 *
	 * @return $this
	 */
	public function refresh() {
		$this->driver->navigate()->refresh();

		return $this;
	}

	/**
	 * Switching to frame or iframe.
	 *
	 * @param CElement|string|array|null $element    iframe element
	 *
	 * @return $this
	 */
	public function switchTo($element = null) {
		if ($element === null) {
			$this->driver->switchTo()->defaultContent();

			return $this;
		}

		if (is_string($element)) {
			$element = $this->query($element)->one(false);
		}
		elseif (is_array($element)) {
			$element = $this->query($element[0], $element[1])->one(false);
		}

		if ($element instanceof RemoteWebElement) {
			$this->driver->switchTo()->frame($element);
		}
		else {
			throw new \Exception('Cannot switch to frame that is not an element.');
		}

		return $this;
	}

	/**
	 * Allows to login with user credentials.
	 *
	 * @param string $alias     Username on login screen
	 * @param string $password  Password on login screen
	 * @param int $scenario  	Scenario TEST_BAD means that passed credentials are invalid, TEST_GOOD - user successfully logged in
	 * @param string $url		Direct link to certain Zabbix page
	 */
	public function userLogin($alias, $password, $scenario = TEST_GOOD, $url = 'index.php') {
		if (self::$cookie === null) {
			$this->driver->get(PHPUNIT_URL);
		}

		$this->logout();
		$this->open($url);
		$this->query('id:name')->waitUntilVisible()->one()->fill($alias);
		$this->query('id:password')->one()->fill($password);
		$this->query('id:enter')->one()->click();
		$this->waitUntilReady();

		// Check login result.
		$sign_out = $this->query('class:zi-sign-out')->exists();

		if ($scenario === TEST_GOOD && !$sign_out) {
			throw new \Exception('"Sign out" button is not found on the page. Probably user is not logged in.');
		}
		elseif ($scenario === TEST_BAD && $sign_out) {
			throw new \Exception('"Sign out" button is found on the page. Probably user is logged in, but shouldn\'t.');
		}
	}

	/**
	 * Check page title text.
	 *
	 * @param string $title		page title
	 *
	 * @throws Exception
	 */
	public function assertTitle($title) {
		global $ZBX_SERVER_NAME;

		if ($ZBX_SERVER_NAME !== '') {
			$title = $ZBX_SERVER_NAME.NAME_DELIMITER.$title;
		}

		$text = $this->getTitle();
		if ($text !== $title) {
			throw new \Exception('Title of the page "'.$text.'" is not equal to "'.$title.'".');
		}
	}

	/**
	 * Check page header.
	 *
	 * @param string $header	page header to be compared
	 *
	 * @throws Exception
	 */
	public function assertHeader($header) {
		$text = $this->query('xpath://h1[@id="page-title-general"]')->one()->getText();

		if ($text !== $header) {
			throw new \Exception('Header of the page "'.$text.'" is not equal to "'.$header.'".');
		}
	}

	/**
	 * Scroll page to the top position.
	 */
	public function scrollToTop() {
		$this->getDriver()->executeScript('document.getElementsByClassName(\'wrapper\')[0].scrollTo(0, 0)');
	}

	/**
	 * Scroll down to the bottom of the page.
	 */
	public function scrollDown() {
		$this->getDriver()->executeScript('document.getElementsByClassName(\'wrapper\')[0].scrollTo(0,'.
				' document.body.scrollHeight)');
	}

	/**
	 * Navigates back to the previous page.
	 */
	public function navigateBack() {
		$this->driver->navigate()->back();
	}
}
