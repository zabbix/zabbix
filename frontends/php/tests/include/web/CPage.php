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

require_once dirname(__FILE__).'/CElementQuery.php';

/**
 * Web page implementation.
 */
class CPage {
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
		$this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub',
				DesiredCapabilities::firefox()->setCapability('loggingPrefs', ['browser' => 'SEVERE'])
		);
		$this->driver->manage()->window()->setSize(new WebDriverDimension(1280, 1024));
		CElementQuery::setDriver($this->driver);
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
		if (!CDBHelper::getRow('select null from sessions where sessionid='.zbx_dbstr($sessionid))) {
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
	 * Take screenshot of current page.
	 *
	 * @return string
	 */
	public function takeScreenshot() {
		return $this->driver->takeScreenshot();
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
}
