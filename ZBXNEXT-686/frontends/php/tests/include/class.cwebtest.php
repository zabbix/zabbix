<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

define('TEST_GOOD', 0);
define('TEST_BAD', 1);

class CWebTest extends PHPUnit_Framework_TestCase {

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

	protected function setUp() {
		global $DB;

		$this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', DesiredCapabilities::firefox());

		if (!isset($DB['DB'])) {
			DBconnect($error);
		}
	}

	protected function tearDown() {
		$this->webDriver->quit();
	}

	public function authenticate() {
		$this->webDriver->get(PHPUNIT_URL);
		$row = DBfetch(DBselect("select null from sessions where sessionid='09e7d4286dfdca4ba7be15e0f3b2b55a'"));

		if (!$row) {
			DBexecute("insert into sessions (sessionid, userid) values ('09e7d4286dfdca4ba7be15e0f3b2b55a', 1)");
		}

		$domain = parse_url(PHPUNIT_URL, PHP_URL_HOST);
		$path = parse_url(PHPUNIT_URL, PHP_URL_PATH);

		$cookie  = ['name' => 'zbx_sessionid', 'value' => '09e7d4286dfdca4ba7be15e0f3b2b55a', 'domain' => $domain, 'path' => $path];
		$this->webDriver->manage()->addCookie($cookie);
	}

	public function zbxTestOpen($url) {
		$this->webDriver->get(PHPUNIT_URL.$url);
	}

	public function zbxTestLogin($url) {
		global $ZBX_SERVER_NAME;

		$this->authenticate();
		$this->zbxTestOpen($url);
//$this->webDriver->takeScreenshot('/home/jenkins/public_html/screenshots/1.png');

//		$this->zbxTestTextPresent([$ZBX_SERVER_NAME, 'Admin']);
		$this->zbxTestTextNotPresent('Login name or password is incorrect');
	}

	public function zbxTestLogout() {
		$this->zbxTestClickWait('link=Logout');
	}

	public function zbxTestCheckFatalErrors() {
		$this->zbxTestTextNotPresent($this->failIfExists);
	}

	public function zbxTestCheckMandatoryStrings() {
		$this->zbxTestTextPresent($this->failIfNotExists);
	}

	public function zbxTestCheckTitle($title) {
		global $ZBX_SERVER_NAME;

		if ($ZBX_SERVER_NAME !== '') {
			$title = $ZBX_SERVER_NAME.NAME_DELIMITER.$title;
		}

		$this->assertEquals($title, $this->webDriver->getTitle());
	}

	public function zbxTestCheckHeader($header) {
		$this->zbxWaitUntil(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::tagName("h1")), 'element is not visible');
		$headerElemnt = $this->webDriver->findElement(WebDriverBy::tagName("h1"));
		$headerElemnt->isDisplayed();
		$this->assertEquals($header, $headerElemnt->getText());
	}

	public function zbxTestHeaderNotPresent($header) {
		$this->assertFalse($this->zbxIsElementPresent(WebDriverBy::xpath("//h1[contains(text(),'".$header."')]")), '"'.$header.'" must not exist.' );
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

	public function zbxTestTextNotPresent($strings) {
		if (!is_array($strings)) {
			$strings = [$strings];
		}

		foreach ($strings as $string) {
			$elements = $this->webDriver->findElements(WebDriverBy::xpath("//*[contains(text(),'".$string."')]"));
			$this->assertTrue(count($elements) === 0, '"'.$string.'" must not exist.');
		}
	}

	public function zbxTestClickLinkText($link_text) {
		$this->webDriver->findElement(WebDriverBy::linkText($link_text))->click();
	}

	public function zbxTestClickButtonText($button_text) {
		$this->webDriver->findElement(WebDriverBy::xpath("//button[contains(text(),'$button_text')]"))->click();
	}

	public function zbxTestClick($id) {
		$this->webDriver->findElement(WebDriverBy::id($id))->click();
	}

	public function zbxTestClickWait($id) {
		$this->webDriver->findElement(WebDriverBy::id($id))->click();
//		$this->wait();
	}

	public function zbxTestClickXpath($xpath) {
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->click();
	}

	public function zbxTestHrefClickWait($href) {
		$this->webDriver->findElement(WebDriverBy::xpath("//a[contains(@href,'$href')]"))->click();
//		$this->wait();
	}

	public function href_click($a) {
		$this->webDriver->findElement(WebDriverBy::xpath("//a[contains(@href,'$a')]"))->click();
	}

	public function zbxTestClickOnclick($a) {
		$this->webDriver->findElement(WebDriverBy::xpath("//a[contains(@onclick,'$a')]"))->click();
	}

	public function zbxTestCheckboxSelect($a, $select = true) {
		$checkbox = $this->webDriver->findElement(WebDriverBy::id($a));
		if ($select != $checkbox->isSelected()) {
			$checkbox->click();
		}
	}

	public function zbxTestClickButton($value) {
		$this->webDriver->findElement(WebDriverBy::xpath("//button[@value='".$value."']"))->click();
	}

	public function input_type($id, $str) {
		$this->webDriver->findElement(WebDriverBy::id($id))->sendKeys($str);
	}

	public function zbxTestInputTypeByXpath($xpath, $str) {
		$this->webDriver->findElement(WebDriverBy::xpath($xpath))->sendKeys($str);
	}

	public function zbxTestInputTypeWait($id, $str) {
		$this->zbxWaitUntil(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id($id)), 'input is not visible');
		$this->webDriver->findElement(WebDriverBy::id($id))->sendKeys($str);
	}

	public function zbxTestDropdownHasOptions($id, array $strings) {
		$attribute = $this->zbxIsElementPresent(WebDriverBy::xpath("//select[@id='".$id."']")) ? 'id' : 'name';
		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@".$attribute."='".$id."']"));

		foreach ($strings as $string) {
			$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@".$attribute."='".$id."']//option[text()='".$string."']"));
		}
	}

	public function zbxTestDropdownSelect($id, $string) {
		$attribute = $this->zbXisElementPresent(WebDriverBy::xpath("//select[@id='".$id."']")) ? 'id' : 'name';
		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@".$attribute."='".$id."']"));

		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@".$attribute."='".$id."']//option[text()='".$string."']"));
		$this->webDriver->findElement(WebDriverBy::xpath("//select[@".$attribute."='".$id."']//option[text()='".$string."']"))->click();
	}

	public function zbxTestDropdownSelectWait($id, $string) {
		$attribute = $this->zbxIsElementPresent(WebDriverBy::xpath("//select[@id='".$id."']")) ? 'id' : 'name';
		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@".$attribute."='".$id."']"));
		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@".$attribute."='".$id."']//option[text()='".$string."']"));

		$selected = $this->webDriver->findElement(WebDriverBy::xpath("//select[@".$attribute."='".$id."']/option[@selected='selected']"))->getText();

		if ($selected != $string) {
			$this->webDriver->findElement(WebDriverBy::xpath("//select[@".$attribute."='".$id."']//option[text()='".$string."']"))->click();
			$this->zbxWaitUntil(WebDriverExpectedCondition::elementToBeSelected(WebDriverBy::xpath("//select[@".$attribute."='".$id."']//option[text()='".$string."']")), 'element not selected');
		}
	}

	public function zbxAssertElementPresent($by) {
		$elements = $this->webDriver->findElements($by);

		if (count($elements) === 0) {
			$this->fail("Element was not found");
		}

		$this->assertTrue(true);
	}

	public function zbxAssertElementNotPresent($by) {
		$elements = $this->webDriver->findElements($by);

		if (count($elements) !== 0) {
			$this->fail("Element was found");
		}

		$this->assertTrue(true);
	}

	public function zbxIsElementPresent($by) {
		return (count($this->webDriver->findElements($by)) > 0);
	}

	public function zbxWaitUntil($condition, $message) {
		$this->webDriver->wait(5)->until($condition, $message);
		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * Assert that the element with the given name contains a specific text.
	 *
	 * @param $name
	 * @param $text
	 */
	public function zbxTestDrowpdownAssertSelected($name, $text) {
		$this->zbxAssertElementPresent(WebDriverBy::xpath("//select[@name='".$name."']//option[text()='".$text."' and @selected]"));
	}

//	public function wait() {
//		$this->waitForPageToLoad();
//		$this->zbxTestCheckFatalErrors();
//	}

	public function tab_switch($tab) {
		// switches tab by receiving tab title text
		$this->click("xpath=//div[@id='tabs']/ul/li/a[text()='$tab']");
		$this->waitForElementPresent("xpath=//li[contains(@class, 'ui-tabs-selected')]/a[text()='$tab']");
		$this->zbxTestCheckFatalErrors();
	}

	// zbx_popup is the default opened window id if none is passed
	public function zbxTestLaunchPopup($buttonId, $windowId = 'zbx_popup') {
		// the above does not seem to work, thus this ugly method has to be used - at least until buttons get unique names...
		$this->click("//input[@id='$buttonId']");	// and contains(@onclick, 'return PopUp')
		$this->waitForPopUp($windowId, 6000);
		$this->selectWindow($windowId);
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxWaitWindowAndSwitchToIt($id) {
		$this->webDriver->wait(5)->until(function () use ($id) {
			try {
				$handles = count($this->webDriver->getWindowHandles());
					if ($handles > 1) {
						return $this->webDriver->switchTo()->window($id);
				}
			}
			catch (NoSuchElementException $ex) {
				return false;
			}
		});

		$this->zbxTestCheckFatalErrors();
	}

	public function zbxGetDropDownElements($dropdownId) {
		$optionCount = count($this->webDriver->findElements(WebDriverBy::xpath('//*[@id="'.$dropdownId.'"]/option')));
		$optionList = [];
		for ($i = 1; $i <= $optionCount; $i++) {
			$optionList[] = [
				'id' => $this->webDriver->findElement(WebDriverBy::xpath('//*[@id="'.$dropdownId.'"]/option['.$i.']'))->getAttribute('value'),
				'content' => $this->webDriver->findElement(WebDriverBy::xpath('//*[@id="'.$dropdownId.'"]/option['.$i.']'))->getText()
			];
		}
		return $optionList;
	}

	/**
	 * Assert that the element with the given name has a specific value.
	 *
	 * @param $name
	 * @param $value
	 */
	public function assertElementValue($name, $value) {
		$this->webDriver->findElement(WebDriverBy::xpath("//*[@name='".$name."' and @value='".$value."']"));
	}

	/**
	 * Assert that the element with the given name contains a specific text.
	 *
	 * @param $name
	 * @param $text
	 */
	public function assertElementText($name, $text) {
		$this->assertElementPresent("//*[@name='".$name."' and text()='".$text."']");
	}

	// check that page does not have real (not visible) host or template names
	public function checkNoRealHostnames() {
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
}
