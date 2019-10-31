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

require_once dirname(__FILE__).'/CElementQuery.php';
require_once dirname(__FILE__).'/CommandExecutor.php';

/**
 * Web page implementation.
 */
class CPage {

	/**
	 * Page defaults.
	 */
	const DEFAULT_PAGE_WIDTH = 1280;
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
	 * Web driver and CElementQuery initialization.
	 */
	public function __construct() {
		$options = new ChromeOptions();
		$options->addArguments([
			'--no-sandbox',
			'--enable-font-antialiasing=false',
			'--window-size='.self::DEFAULT_PAGE_WIDTH.','.self::DEFAULT_PAGE_HEIGHT
		]);

		$this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub',
				DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options)
		);

		CElementQuery::setPage($this);
	}

	/**
	 * Perform page cleanup.
	 * Close all popup windows, switch to the initial window, remove cookies.
	 */
	public function cleanup() {
		if (self::$cookie !== null) {
			$session_id = $this->driver->manage()->getCookieNamed('zbx_sessionid');

			if ($session_id === null || !array_key_exists('value', $session_id)
					|| $session_id['value'] !== self::$cookie['value']) {
				self::$cookie = null;
			}
		}

		$this->driver->manage()->deleteAllCookies();
		try {
			$this->driver->executeScript('sessionStorage.clear();');
		} catch (Exception $exeption) {
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
	 * Login as specified user.
	 *
	 * @param string  $sessionid   session id
	 * @param integer $user_id     user id
	 *
	 * @return $this
	 */
	public function login($sessionid = '09e7d4286dfdca4ba7be15e0f3b2b55a', $user_id = 1) {
		if (!CDBHelper::getRow('select null from sessions where status=0 AND sessionid='.zbx_dbstr($sessionid))) {
			DBexecute('insert into sessions (sessionid, userid) values ('.zbx_dbstr($sessionid).', '.$user_id.')');
		}

		if (self::$cookie === null || $sessionid !== self::$cookie['value']) {
			self::$cookie = [
				'name' => 'zbx_sessionid',
				'value' => $sessionid,
				'domain' => parse_url(PHPUNIT_URL, PHP_URL_HOST),
				'path' => parse_url(PHPUNIT_URL, PHP_URL_PATH)
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
			$session = (self::$cookie === null)
					? CTestArrayHelper::get($this->driver->manage()->getCookieNamed('zbx_sessionid'), 'value')
					: self::$cookie['value'];

			if ($session !== null) {
				DBExecute('DELETE FROM sessions WHERE sessionid='.zbx_dbstr($session));
			}

			$this->driver->manage()->deleteAllCookies();
			self::$cookie = null;
		}
		catch (\Exception $e) {
			// Code is not missing here.
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
	 * Take screenshot of current page.
	 *
	 * @return string
	 */
	protected function takePageScreenshot() {
		try {
			if (!$this->driver->executeScript('return !!window.chrome;')) {
				throw new Exception();
			}
		} catch (Exception $exception) {
			return $this->driver->takeScreenshot();
		}

		try {
			// Screenshot is 1px smaller to ensure that scroll is still present.
			$height = (int)$this->driver->executeScript('return document.documentElement.getHeight();') - 1;

			if ($height > self::DEFAULT_PAGE_HEIGHT) {
				$this->setViewport(self::DEFAULT_PAGE_WIDTH, $height);
			}
		} catch (Exception $exception) {
			// Code is not missing here.
		}

		$screenshot = $this->driver->takeScreenshot();

		if (isset($height) && $height > self::DEFAULT_PAGE_HEIGHT) {
			$this->setViewport(self::DEFAULT_PAGE_WIDTH, self::DEFAULT_PAGE_HEIGHT);
		}

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
	 */
	public function waitUntilReady() {
		return (new CElementQuery(null))->waitUntilReady();
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
		catch (NoAlertOpenException $exception) {
			return null;
		}
	}

	/**
	 * Wait until alert is present and accept it.
	 */
	public function acceptAlert() {
		CElementQuery::wait()->until(WebDriverExpectedCondition::alertIsPresent());
		$this->driver->switchTo()->alert()->accept();
	}

	/**
	 * Wait until alert is present and dismiss it.
	 */
	public function dismissAlert() {
		CElementQuery::wait()->until(WebDriverExpectedCondition::alertIsPresent());
		$this->driver->switchTo()->alert()->dismiss();
	}

	/**
	 * Emulate key presses.
	 *
	 * @param array|string $keys   keys to be pressed
	 */
	public function keyPress($keys) {
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
}
