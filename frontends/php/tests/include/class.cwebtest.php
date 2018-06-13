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
require_once dirname(__FILE__).'/../../include/hosts.inc.php';
require_once dirname(__FILE__).'/dbfunc.php';
require_once dirname(__FILE__) . '/class.ctestdbhelper.php';
require_once dirname(__FILE__).'/class.cexceptionhelper.php';

define('TEST_GOOD', 0);
define('TEST_BAD', 1);
define('TEST_ERROR', 2);

class CWebTest extends PHPUnit_Framework_TestCase {

	const WAIT_ITERATION = 50;

	// List of strings that should NOT appear on any page
	public $failIfExists = [
		'pg_query',
		'Error in',
		'expects parameter',
		'Undefined index',
		'Undefined variable',
		'Undefined offset',
		'Fatal error',
		'Call to undefined method',
		'Invalid argument supplied',
		'Missing argument',
		'Warning:',
		'PHP notice',
		'PHP warning',
		'Use of undefined',
		'You must login',
		'DEBUG INFO',
		'Cannot modify header',
		'Parse error',
		'syntax error',
		'Try to read inaccessible property',
		'Illegal string offset',
		'must be an array'
	];

	// List of strings that SHOULD appear on every page
	public $failIfNotExists = [
		'Zabbix Share',
		'Help',
		'Admin',
		'Sign out'
	];

	protected $capture_screenshot = true;

	// Screenshot taken on test failure.
	protected $screenshot = null;
	// Failed test URL.
	protected $current_url = null;

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
	// Shared cookie value.
	protected static $cookie = null;
	// Enable supressing of browser errors on test case level.
	protected $supress_case_errors = false;
	// Enable supressing of browser errors on test suite level.
	protected static $supress_suite_errors = false;
	// Test case data key.
	protected $data_key = null;
	// Lists of test case data set keys.
	protected static $test_data_sets = [];

	protected function putBreak() {
		fwrite(STDOUT, "\033[s    \033[93m[Breakpoint] Press \033[1;93m[RETURN]\033[0;93m to continue...\033[0m");
			while (fgets(STDIN, 1024) == '') {}
			fwrite(STDOUT, "\033[u");
		return;
	}

	protected function onNotSuccessfulTest($e) {
		if ($this->screenshot !== null && $e instanceof Exception) {
			$screenshot_name = md5(microtime(true)).'.png';

			if (file_put_contents(PHPUNIT_SCREENSHOT_DIR.$screenshot_name, $this->screenshot) !== false) {
				CExceptionHelper::setMessage($e, 'URL: '.$this->current_url."\n".
						'Screenshot: '.PHPUNIT_SCREENSHOT_URL.$screenshot_name."\n".
						$e->getMessage()
				);

				$this->screenshot = null;
			}
		}

		if (($e instanceof PHPUnit_Framework_SkippedTestError) === false) {
			self::closeBrowser();
		}

		parent::onNotSuccessfulTest($e);
	}

	protected function tearDown() {
		// Check for JS errors.
		if (!$this->hasFailed()) {
			if (!$this->supress_case_errors) {
				$errors = [];

				foreach ($this->webDriver->manage()->getLog('browser') as $log) {
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

	public function authenticate() {
		$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55a', 1);
	}

	public function authenticateUser($sessionid, $userId) {
		$row = DBfetch(DBselect("select null from sessions where sessionid='$sessionid'"));

		if (!$row) {
			DBexecute("insert into sessions (sessionid, userid) values ('$sessionid', $userId)");
		}

		if (self::$cookie === null || $sessionid !== self::$cookie['value']) {
			self::$cookie = [
				'name' => 'zbx_sessionid',
				'value' => $sessionid,
				'domain' => parse_url(PHPUNIT_URL, PHP_URL_HOST),
				'path' => parse_url(PHPUNIT_URL, PHP_URL_PATH)
			];

			$this->webDriver->get(PHPUNIT_URL);
		}

		$this->webDriver->manage()->addCookie(self::$cookie);
	}

	public function zbxTestOpen($url) {
		$this->webDriver->get(PHPUNIT_URL.$url);
	}

	public function zbxTestLogin($url, $server_name = true) {
		global $ZBX_SERVER_NAME;

		$this->authenticate();
		$this->zbxTestOpen($url);

		if ($server_name && $ZBX_SERVER_NAME !== '') {
			$this->zbxTestWaitUntilMessageTextPresent('server-name', $ZBX_SERVER_NAME);
		}

		$this->zbxTestTextNotPresent('Login name or password is incorrect');
	}

	public function zbxTestLogout() {
		$this->zbxTestClickXpath('//a[@class="top-nav-signout"]');
	}

	public function zbxTestCheckFatalErrors() {
		$this->zbxTestTextNotPresent($this->failIfExists);
	}

	public function zbxTestCheckMandatoryStrings() {
		$this->zbxTestTextPresent($this->failIfNotExists);
	}

	public function zbxTestCheckTitle($title, $check_server_name = true) {
		global $ZBX_SERVER_NAME;

		if ($check_server_name && $ZBX_SERVER_NAME !== '') {
			$title = $ZBX_SERVER_NAME.NAME_DELIMITER.$title;
		}

		$this->assertEquals($title, $this->webDriver->getTitle());
	}

	public function zbxTestCheckHeader($header) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::tagName('h1'));
		$headerElemnt = $this->webDriver->findElement(WebDriverBy::tagName('h1'));
		$this->assertEquals($header, $headerElemnt->getText());
	}

	public function zbxTestHeaderNotPresent($header) {
		$this->assertFalse($this->zbxTestIsElementPresent("//h1[contains(text(),'".$header."')]"), '"'.$header.'" must not exist.' );
	}

	public function zbxTestTextPresent($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}
		$page_source = $this->webDriver->getPageSource();

		foreach ($strings as $string) {
			if (empty($string)) {
				$this->assertTrue(true);
			}
			else {
				$this->assertTrue(strstr($page_source, $string) !== false, '"'.$string.'" must exist.');
			}
		}
	}

	public function zbxTestTextNotPresent($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			$elements = $this->webDriver->findElements(WebDriverBy::xpath("//*[contains(text(),'".$string."')]"));
			$this->assertTrue(count($elements) === 0, '"'.$string.'" must not exist.');
		}
	}

	public function zbxTestTextVisibleOnPage($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			if (empty($string)) {
				$this->assertTrue(true);
			}
			else {
				$elements = $this->webDriver->findElements(WebDriverBy::xpath("//*[contains(text(),'".$string."')]"));
				$this->assertTrue(count($elements) !== 0, '"'.$string.'" must exist.');
			}
		}
	}

	public function zbxTestTextNotVisibleOnPage($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			$elements = $this->webDriver->findElement(WebDriverBy::xpath("//*[contains(text(),'".$string."')]"));
			$this->assertFalse($elements->isDisplayed());
		}
	}

	public function zbxTestClickLinkText($link_text) {
		$this->webDriver->findElement(WebDriverBy::linkText($link_text))->click();
	}

	public function zbxTestClickLinkTextWait($link_text) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::linkText($link_text));
		$this->webDriver->findElement(WebDriverBy::linkText($link_text))->click();
	}

	public function zbxTestDoubleClickLinkText($link_text, $id) {
		$this->zbxTestClickLinkTextWait($link_text);
		if (!$this->zbxTestElementPresentId($id)){
			$this->zbxTestClickLinkTextWait($link_text);
		}
	}

	public function zbxTestClickButtonText($button_text) {
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(text(),'$button_text')]"));
		$this->webDriver->findElement(WebDriverBy::xpath("//button[contains(text(),'$button_text')]"))->click();
	}

	public function zbxTestClick($id) {
		$this->webDriver->findElement(WebDriverBy::id($id))->click();
	}

	public function zbxTestClickWait($id) {
		$this->zbxTestWaitUntilElementClickable(WebDriverBy::id($id));
		$this->webDriver->findElement(WebDriverBy::id($id))->click();
	}

	public function zbxTestDoubleClick($click_id, $id) {
		$this->zbxTestClickWait($click_id);
		if (!$this->zbxTestElementPresentId($id)){
			$this->zbxTestClickWait($click_id);
		}
	}

	public function zbxTestDoubleClickBeforeMessage($click_id, $id) {
		$this->webDriver->wait(30, self::WAIT_ITERATION)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id($click_id)));
		$this->zbxTestClickWait($click_id);
		$msg = count($this->webDriver->findElements(WebDriverBy::className('msg-bad')));
		if (!$this->zbxTestElementPresentId($id) and $msg === 0){
			$this->zbxTestClickWait($click_id);
		}
	}

	public function zbxTestClickXpath($xpath) {
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->click();
	}

	public function zbxTestClickXpathWait($xpath) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath($xpath));
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->click();
	}

	public function zbxTestDoubleClickXpath($click_xpath, $id) {
		$this->zbxTestClickXpathWait($click_xpath);
		if (!$this->zbxTestElementPresentId($id)){
			$this->zbxTestClickXpathWait($click_xpath);
		}
	}

	public function zbxTestHrefClickWait($href) {
		$this->webDriver->findElement(WebDriverBy::xpath("//a[contains(@href,'$href')]"))->click();
	}

	public function zbxTestCheckboxSelect($id, $select = true) {
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::id($id));
		$checkbox = $this->webDriver->findElement(WebDriverBy::id($id));
		if ($select != $checkbox->isSelected()) {
			$this->webDriver->findElement(WebDriverBy::id($id))->click();
		}
	}

	public function zbxTestCheckboxSelected($id) {
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::id($id));
		return $this->webDriver->findElement(WebDriverBy::id($id))->isSelected();
	}

	public function zbxTestClickButton($value) {
		$this->zbxTestWaitUntilElementClickable(WebDriverBy::xpath("//button[@value='".$value."']"));
		$this->webDriver->findElement(WebDriverBy::xpath("//button[@value='".$value."']"))->click();
	}

	/**
	 * Clicks on the "Select" button to the right of the multiselect
	 *
	 * @param string $id  ID of the multiselect.
	 */
	public function zbxTestClickButtonMultiselect($id) {
		$this->zbxTestClickXpath(
			"//div[contains(@class, 'multiselect') and @id='$id']/../div[@class='multiselect-button']/button"
		);
	}

	public function zbxTestMultiselectNew($id, $string) {
		$this->webDriver->findElement(
			WebDriverBy::xpath("//div[contains(@class, 'multiselect') and @id='$id']/input")
		)
			->clear()
			->sendKeys($string);
		$this->zbxTestClickXpathWait(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='available']".
			"/ul[@class='multiselect-suggest']/li[@data-id='$string']"
		);
		$this->zbxTestMultiselectAssertSelected($id, $string.' (new)');
	}

	public function zbxTestMultiselectAssertSelected($id, $string) {
		$this->zbxTestAssertVisibleXpath(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='selected']".
			"/ul[@class='multiselect-list']/li/span[@class='subfilter-enabled']/span[text()='$string']"
		);
	}

	public function zbxTestMultiselectRemove($id, $string) {
		$this->zbxTestClickXpathWait(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='selected']".
			"/ul[@class='multiselect-list']/li/span[@class='subfilter-enabled']/span[text()='$string']/..".
			"/span[@class='subfilter-disable-btn']"
		);
		$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath(
			"//div[contains(@class, 'multiselect') and @id='$id']/div[@class='selected']".
			"/ul[@class='multiselect-list']/li/span[@class='subfilter-enabled']/span[text()='$string']"
		));
	}

	/**
	 * If 'Filter' tab is closed, then open it
	 */

	public function zbxTestExpandFilterTab() {
		$element = $this->webDriver->findElement(WebDriverBy::xpath("//div[contains(@class,'table filter-forms')]"))->isDisplayed();
		if (!$element) {
			$this->zbxTestClickXpathWait("//a[contains(@class,'filter-trigger')]");
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//div[contains(@class,'table filter-forms')]"));
		}
	}

	public function zbxTestInputType($id, $str) {
		$this->webDriver->findElement(WebDriverBy::id($id))->clear()->sendKeys($str);
	}

	public function zbxTestInputTypeOverwrite($id, $str) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id($id));
		$this->webDriver->findElement(WebDriverBy::id($id))->click();
		$this->webDriver->getKeyboard()->pressKey(WebDriverKeys::CONTROL);
		$this->webDriver->getKeyboard()->pressKey('a');
		$this->webDriver->getKeyboard()->pressKey(WebDriverKeys::CONTROL);
		$this->webDriver->findElement(WebDriverBy::id($id))->sendKeys($str);
	}

	public function zbxTestInputTypeByXpath($xpath, $str, $validate = true) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath($xpath));
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->sendKeys($str);

		if ($validate) {
			$this->zbxTestWaitUntilElementValuePresent(WebDriverBy::xpath($xpath), $str);
		}
	}

	public function zbxTestInputTypeWait($id, $str) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id($id));
		$this->webDriver->findElement(WebDriverBy::id($id))->sendKeys($str);
	}

	public function zbxTestDropdownHasOptions($id, array $strings) {
		$values = [];
		foreach ($this->getDropdownOptions($id) as $option) {
			$values[] = $option->getText();
		}

		$this->assertTrue(count(array_diff($strings, $values)) === 0);
	}

	public function zbxTestDropdownSelect($id, $string) {
		// Simplified escaping of xpath string.
		if (strpos($string, '"') !== false) {
			$string = '\''.$string.'\'';
		}
		else {
			$string = '"'.$string.'"';
		}

		$option = $this->getDropdown($id)->findElement(WebDriverBy::xpath('.//option[text()='.$string.']'));

		if (!$option->isSelected()) {
			$option->click();

			return $option;
		}

		return null;
	}

	public function zbxTestDropdownSelectWait($id, $string) {
		$option = $this->zbxTestDropdownSelect($id, $string);

		if ($option !== null) {
			try {
				$this->zbxTestWaitUntil(WebDriverExpectedCondition::elementToBeSelected($option), null);
			} catch (StaleElementReferenceException $e) {
				// Element not found in the cache, looks like page changed.
				$this->zbxTestWaitForPageToLoad();
			}
		}
	}

	public function zbxTestDropdownAssertSelected($name, $text) {
		$this->assertEquals($text, $this->zbxTestGetSelectedLabel($name));
	}

	public function zbxTestGetSelectedLabel($id) {
		foreach ($this->getDropdownOptions($id) as $option) {
			if ($option->isSelected()) {
				return $option->getText();
			}
		}
	}

	public function zbxTestElementPresentId($id) {
		$elements = $this->webDriver->findElements(WebDriverBy::id($id));

		if (count($elements) === 0) {
			return false;
		}

		return true;
	}

	public function zbxTestAssertElementPresentId($id) {
		$elements = $this->webDriver->findElements(WebDriverBy::id($id));

		if (count($elements) === 0) {
			$this->fail("Element was not found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestAssertElementPresentXpath($xpath) {
		$elements = $this->webDriver->findElements(WebDriverBy::xpath($xpath));

		if (count($elements) === 0) {
			$this->fail("Element was not found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestAssertAttribute($xpath, $attribute, $value = 'true') {
		$element = $this->webDriver->findElement(WebDriverBy::xpath($xpath));
		$this->assertEquals($element->getAttribute($attribute), $value);
	}

	public function zbxTestAssertElementNotPresentId($id) {
		$elements = $this->webDriver->findElements(WebDriverBy::id($id));

		if (count($elements) !== 0) {
			$this->fail("Element was found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestAssertElementNotPresentXpath($xpath) {
		$elements = $this->webDriver->findElements(WebDriverBy::xpath($xpath));

		if (count($elements) !== 0) {
			$this->fail("Element was found");
		}

		$this->assertTrue(true);
	}

	public function zbxTestIsElementPresent($xpath) {
		return (count($this->webDriver->findElements(WebDriverBy::xpath($xpath))) > 0);
	}

	public function zbxTestWaitUntil($condition, $message) {
		$this->webDriver->wait(60, self::WAIT_ITERATION)->until($condition, $message);
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestWaitUntilElementVisible($by) {
		$this->webDriver->wait(60, self::WAIT_ITERATION)->until(WebDriverExpectedCondition::visibilityOfElementLocated($by), 'after 60 sec element still not visible');
	}

	public function zbxTestWaitUntilElementValuePresent($by, $value) {
		$this->webDriver->wait(20, self::WAIT_ITERATION)->until(
			function ($driver) use ($by, $value) {
				try {
					return $driver->findElement($by)->getAttribute('value') === $value;
				} catch (StaleElementReferenceException $e) {
					return null;
				}
			}
		);
	}

	public function zbxTestWaitUntilElementNotVisible($by) {
		$this->webDriver->wait(60)->until(WebDriverExpectedCondition::invisibilityOfElementLocated($by), 'after 60 sec element still visible');
	}

	public function zbxTestWaitUntilElementClickable($by) {
		$this->webDriver->wait(60, self::WAIT_ITERATION)->until(WebDriverExpectedCondition::elementToBeClickable($by));
	}

	public function zbxTestWaitUntilElementPresent($by) {
		$this->webDriver->wait(60, self::WAIT_ITERATION)->until(WebDriverExpectedCondition::presenceOfElementLocated($by));
	}

	public function zbxTestWaitUntilMessageTextPresent($css, $string) {
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::className($css));
		$this->webDriver->wait(60, self::WAIT_ITERATION)->until(WebDriverExpectedCondition::textToBePresentInElement(WebDriverBy::className($css), $string));
	}

	public function zbxTestTabSwitch($tab) {
		$this->zbxTestClickXpathWait("//div[@id='tabs']/ul/li/a[text()='$tab']");
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//li[contains(@class, 'ui-tabs-active')]/a[text()='$tab']"));
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestTabSwitchById($id, $tab) {
		$this->zbxTestClickWait($id);
		if ($this->zbxTestGetText("//li[contains(@class, 'ui-tabs-active')]/a") != $tab ) {
			$this->zbxTestClickXpathWait("//div[@id='tabs']/ul/li/a[text()='$tab']");
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//li[contains(@class, 'ui-tabs-active')]/a[text()='$tab']"));
		}
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestLaunchOverlayDialog($header) {
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@id='overlay_dialogue']/div[@class='dashbrd-widget-head']/h4[text()='$header']"));
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestSwitchToWindow($id) {
		// No need to wait for default window
		if ($id !== '') {
			$this->webDriver->wait(60, self::WAIT_ITERATION)->until(function () use ($id) {
				$handle = $this->webDriver->getWindowHandle();
				return ($handle !== $this->webDriver->switchTo()->window($id)->getWindowHandle());
			});
		}
		else {
			$this->webDriver->switchTo()->window($id);
		}
	}

	public function zbxTestWaitWindowClose() {
		$this->webDriver->wait(10, self::WAIT_ITERATION)->until(function () {
			return count($this->webDriver->getWindowHandles()) === 1;
		});

		$this->webDriver->switchTo()->window('');
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxTestClickLinkAndWaitWindowClose($link) {
		$this->zbxTestClickLinkTextWait($link);
		$this->zbxTestWaitWindowClose();
	}

	public function zbxTestClickAndAcceptAlert($id) {
		$this->zbxTestClickWait($id);
		try {
			$this->zbxTestAcceptAlert();
		}
		catch (TimeoutException $ex) {
			$this->zbxTestClickWait($id);
			$this->zbxTestAcceptAlert();
		}
	}

	public function zbxTestAcceptAlert() {
		$this->webDriver->wait(10, self::WAIT_ITERATION)->until(WebDriverExpectedCondition::alertIsPresent());
		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestWaitForPageToLoad();
	}

	public function zbxTestGetDropDownElements($dropdownId) {
		$elements = [];
		foreach ($this->getDropdownOptions($dropdownId) as $option) {
			$elements[] = [
				'id' => $option->getAttribute('value'),
				'content' => $option->getText()
			];
		}

		return $elements;
	}

	public function zbxTestAssertElementValue($id, $value) {
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::id($id));
		$element = $this->webDriver->findElement(WebDriverBy::id($id))->getAttribute('value');
		$this->assertEquals($value, $element);
	}

	public function zbxTestGetValue($xpath) {
		return $this->webDriver->findElement(WebDriverBy::xpath($xpath))->getAttribute('value');
	}

	public function zbxTestGetAttributeValue($xpath, $attribute) {
		return $this->webDriver->findElement(WebDriverBy::xpath($xpath))->getAttribute($attribute);
	}

	public function zbxTestGetText($xpath) {
		return $this->webDriver->findElement(WebDriverBy::xpath($xpath))->getText();
	}

	public function zbxTestAssertElementText($xpath, $text){
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath($xpath));
		$element = $this->webDriver->findElement(WebDriverBy::xpath($xpath))->getText();
		$element_text = trim(preg_replace('/\s+/', ' ', $element));
		$this->assertEquals($text, $element_text);
	}

	public function zbxTestAssertNotVisibleId($id){
		$this->assertFalse($this->webDriver->findElement(WebDriverBy::id($id))->isDisplayed());
	}

	public function zbxTestAssertNotVisibleXpath($xpath){
		$this->assertFalse($this->webDriver->findElement(WebDriverBy::xpath($xpath))->isDisplayed());
	}

	public function zbxTestAssertVisibleId($id){
		$this->assertTrue($this->webDriver->findElement(WebDriverBy::id($id))->isDisplayed());
	}

	public function zbxTestAssertVisibleXpath($xpath){
		$this->assertTrue($this->webDriver->findElement(WebDriverBy::xpath($xpath))->isDisplayed());
	}

	public function zbxTestIsEnabled($xpath){
		return $this->webDriver->findElement(WebDriverBy::xpath($xpath))->isEnabled();
	}

	// check that page does not have real (not visible) host or template names
	public function zbxTestCheckNoRealHostnames() {
		$result = DBselect(
			'SELECT host'.
			' FROM hosts'.
			' WHERE status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')'.
				' AND name <> host'
		);
		while ($row = DBfetch($result)) {
			$this->zbxTestTextNotPresent($row['host']);
		}
	}

	public function zbxTestWaitForPageToLoad() {
		$this->webDriver->wait(10, self::WAIT_ITERATION)->until(function () {
			return $this->webDriver->executeScript("return document.readyState;") == "complete";
			}
		);
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific text.
	 *
	 * @param string $text  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickText($text) {
		$this->webDriver->findElement(WebDriverBy::xpath(
			"//div[contains(@class, 'header-title')]".
				"//nav[@aria-label='Content controls']".
					"//button[text()='{$text}']"
		))->click();
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific class name.
	 *
	 * @param string $class  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickClass($class) {
		$this->webDriver->findElement(WebDriverBy::xpath(
			"//div[contains(@class, 'header-title')]".
				"//nav[@aria-label='Content controls']".
					"//button[contains(@class, '{$class}')]"
		))->click();
	}

	/**
	 * Select option for select element inside 'Main filter' area.
	 *
	 * @param string $name   Select tag name attribute.
	 * @param string $value  Option value to select.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestMainFilterDropdownSelect($name, $value) {
		$this->webDriver->findElement(WebDriverBy::xpath(
			"//div[contains(@class, 'header-title')]".
				"//form[@aria-label='Main filter']".
					"//select[@name='{$name}']".
						"/option[@value='{$value}']"
		))->click();
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific text.
	 *
	 * @param string $text  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickTextWait($text) {
		$xpath = "//div[contains(@class, 'header-title')]".
					"//nav[@aria-label='Content controls']".
						"//button[text()='{$text}']";

		$this->zbxTestWaitUntilElementClickable(WebDriverBy::xpath($xpath));
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->click();
	}

	/**
	 * Find and click on button inside header 'Content controls' area having specific class name.
	 *
	 * @param string $class  Button text label.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestContentControlButtonClickClassWait($class) {
		$xpath = "//div[contains(@class, 'header-title')]".
					"//nav[@aria-label='Content controls']".
						"//button[contains(@class, '{$class}')]";

		$this->zbxTestWaitUntilElementClickable(WebDriverBy::xpath($xpath));
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->click();
	}

	/**
	 * Select option for select element inside 'Main filter' area.
	 *
	 * @param string $name   Select tag name attribute.
	 * @param string $value  Option value to select.
	 *
	 * @throws NoSuchElementException
	 */
	public function zbxTestMainFilterDropdownSelectWait($name, $value) {
		$xpath = "//div[contains(@class, 'header-title')]".
					"//form[@aria-label='Main filter']".
						"//select[@name='{$name}']".
							"/option[@value='{$value}']";

		$this->zbxTestWaitUntilElementClickable(WebDriverBy::xpath($xpath));
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->click();
	}

	/**
	 * Perform browser cleanup.
	 * Close all popup windows, switch to the initial window, remove cookies.
	 */
	protected function cleanup() {
		$error = false;

		if (self::$shared_browser !== null) {
			try {
				if (self::$cookie !== null) {
					$session_id = self::$shared_browser->manage()->getCookieNamed('zbx_sessionid');

					if ($session_id === null || !array_key_exists('value', $session_id)
							|| $session_id['value'] !== self::$cookie['value']) {
						self::$cookie = null;
					}
				}

				$windows = self::$shared_browser->getWindowHandles();

				if (count($windows) > 1) {
					try {
						foreach (array_slice($windows, 1) as $window) {
							self::$shared_browser->switchTo()->window($window);
							self::$shared_browser->close();
						}
					}
					catch (Exception $e) {
						// Error handling is not missing here.
					}

					if (count(self::$shared_browser->getWindowHandles()) >= 1) {
						try {
							self::$shared_browser->switchTo()->window($windows[0]);
						}
						catch (Exception $e) {
							$error = true;
						}
					}
				}

				self::$shared_browser->manage()->deleteAllCookies();
			}
			catch (Exception $e) {
				$error = true;
			}
		}

		if ($error) {
			// Cleanup failed, browser will be terminated.
			self::closeBrowser();
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
		$backup = [];

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
				if (array_key_exists($case_name, self::$test_data_sets)) {
					// Check for data data set limit.
					$limit = $this->getAnnotationsByType($method_annotations, 'data-limit');

					if ($limit !== null && count($limit) === 1 && is_numeric($limit[0]) && $limit[0] >= 1
							&& count(self::$test_data_sets[$case_name]) > $limit[0]) {
						$sets = self::$test_data_sets[$case_name];
						shuffle($sets);
						self::$test_data_sets[$case_name] = array_slice($sets, 0, (int)$limit[0]);
					}
				}

				// Backup performed once before first test case execution.
				$case_backup_once = $this->getAnnotationsByType($method_annotations, 'backup-once');

				if ($case_backup_once !== null && count($case_backup_once) === 1) {
					$backup['case-once'] = $case_backup_once[0];
				}
			}

			// Supress browser error on a test case level.
			$supress_case_errors = $this->getAnnotationsByType($method_annotations, 'ignore-browser-errors');
			$this->supress_case_errors = ($supress_case_errors !== null);
		}

		// Class name change is used to determine suite change.
		if ($suite !== $class_name) {
			// Browser errors are not ignored by default.
			self::$supress_suite_errors = false;

			// Test suite level annotations.
			$class_annotations = $this->getAnnotationsByType($annotations, 'class');

			// Backup performed before test suite execution.
			$suite_backup = $this->getAnnotationsByType($class_annotations, 'backup');

			if ($suite_backup !== null && count($suite_backup) === 1) {
				$backup['suite'] = $suite_backup[0];
			}

			// Supress browser error on a test case level.
			$supress_suite_errors = $this->getAnnotationsByType($class_annotations, 'ignore-browser-errors');
			self::$supress_suite_errors = ($supress_suite_errors !== null);
		}

		// Errors on a test case level should be supressed if suite level error supression is enabled.
		if (self::$supress_suite_errors) {
			$this->supress_case_errors = self::$supress_suite_errors;
		}

		$suite = $class_name;
		self::$last_test_case = $case_name;

		// Mark excessive test cases as skipped.
		if (array_key_exists($case_name, self::$test_data_sets)
				&& !in_array($this->data_key, self::$test_data_sets[$case_name])) {
			self::markTestSkipped('Test case skipped by data provider limit check.');
		}

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

		// Share browser when it is possible.
		if (self::$shared_browser !== null) {
			$this->webDriver = self::$shared_browser;
		}
		else {
			$this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub',
					DesiredCapabilities::firefox()->setCapability('loggingPrefs', ["browser" => "SEVERE"])
			);
			$this->webDriver->manage()->window()->setSize(new WebDriverDimension(1280, 1024));
			self::$shared_browser = $this->webDriver;
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

		// Perform browser cleanup.
		$this->cleanup();
		DBclose();
	}

	/**
	 * Callback executed after every test suite.
	 *
	 * @afterClass
	 */
	public static function onAfterTestSuite() {
		global $DB;

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

		// Browser is always terminated at the end of the test suite.
		self::closeBrowser();
	}

	/**
	 * Close shared browser instance.
	 */
	protected static function closeBrowser() {
		try {
			if (self::$shared_browser !== null) {
				self::$shared_browser->quit();
				self::$shared_browser = null;
				self::$cookie = null;
			}
		}
		catch (Exception $e) {
			// Error handling is not missing here.
		}
	}

	/**
	 * Get dropdown element by id or name.
	 *
	 * @param string $id    dropdown id or name
	 *
	 * @return WebDriverElement
	 */
	protected function getDropdown($id) {
		foreach (['id', 'name'] as $type) {
			$by = call_user_func(['WebDriverBy', $type], $id);
			$elements = $this->webDriver->findElements($by);

			foreach ($elements as $element) {
				if ($element->getTagName() === 'select') {
					return $element;
				}
			}
		}

		$this->fail('Dropdown element "'.$id.'" was not found!');
	}

	/**
	 * Get dropdown option elements by dropdown id or name.
	 *
	 * @param string $id    dropdown id or name
	 *
	 * @return array of WebDriverElement
	 */
	protected function getDropdownOptions($id) {
		return $this->getDropdown($id)->findElements(WebDriverBy::tagName('option'));
	}

	/**
	 * Capture screenshot if screenshot capturing is enabled.
	 */
	protected function captureScreenshot() {
		try {
			if ($this->capture_screenshot && $this->webDriver !== null) {
				$this->current_url = $this->webDriver->getCurrentURL();
				$this->screenshot = $this->webDriver->takeScreenshot();
			}
		}
		catch (Exception $ex) {
			// Error handling is not missing here.
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

	/**
	 * Overriden constructor for collecting data on data sets from dataProvider annotations.
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
	}
}
