<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';
require_once dirname(__FILE__).'/dbfunc.php';

class CWebTest extends PHPUnit_Extensions_SeleniumTestCase {

	protected $captureScreenshotOnFailure = TRUE;
	protected $screenshotPath = '/home/hudson/public_html/screenshots';
	protected $screenshotUrl = 'http://192.168.3.32/~hudson/screenshots';

	// List of strings that should NOT appear on any page
	public $failIfExists = array (
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
	);

	// List of strings that SHOULD appear on every page
	public $failIfNotExists = array (
		'Help',
		'Get support',
		'Print',
		'Profile',
		'Logout',
		'Connected',
		'Admin'
	);

	protected function setUp() {
		global $DB;

		$this->setHost(PHPUNIT_FRONTEND_HOST);
		$this->setBrowser('*firefox');
		if (strstr(PHPUNIT_URL, 'http://')) {
			$this->setBrowserUrl(PHPUNIT_URL);
		}
		else {
			$this->setBrowserUrl('http://hudson/~hudson/'.PHPUNIT_URL.'/frontends/php/');
		}

		/*
		if (!DBConnect($error)) {
			$this->assertTrue(FALSE, 'Unable to connect to the database: '.$error);
			exit;
		}
		*/

		if (!isset($DB['DB'])) {
			DBConnect($error);
		}
	}

	protected function tearDown() {
		// DBclose();
	}

	public function zbxTestOpen($url) {
		$this->open($url);
	}

	public function zbxTestOpenWait($url) {
		$this->zbxTestOpen($url);
		$this->wait();
	}

	public function zbxTestLogin($url = null) {
		global $ZBX_SERVER_NAME;

		$this->zbxTestOpenWait('index.php');
		// Login if not logged in already
		if ($this->isElementPresent('id=password')) {
			$this->input_type('name', PHPUNIT_LOGIN_NAME);
			$this->input_type('password', PHPUNIT_LOGIN_PWD);
			$this->zbxTestClickWait('enter');
		}
		if (isset($url)) {
			$this->zbxTestOpenWait($url);
		}
		$this->zbxTestTextPresent(array($ZBX_SERVER_NAME, 'Admin'));
		$this->zbxTestTextNotPresent('Login name or password is incorrect');
	}

	public function zbxTestLogout() {
		$this->zbxTestClickWait('link=Logout');
	}

	public function zbxTestCheckFatalErrors() {
		foreach ($this->failIfExists as $str) {
			$this->assertTextNotPresent($str, "Chuck Norris: I do not expect string '$str' here.");
		}
	}

	public function checkTitle($title) {
		global $ZBX_SERVER_NAME;

		if ($ZBX_SERVER_NAME !== '') {
			$title = $ZBX_SERVER_NAME.NAME_DELIMITER.$title;
		}

		$this->assertTitle($title);
	}

	public function zbxTestTextPresent($strings) {
		if (!is_array($strings)) {
			$strings = array($strings);
		}
		foreach ($strings as $string) {
			$this->assertTextPresent('exact:'.$string, 'Chuck Norris: I expect string "'.$string.'" here');
		}
	}

	public function zbxTestTextNotPresent($strings) {
		if (!is_array($strings)) {
			$strings = array($strings);
		}
		foreach ($strings as $string) {
			$this->assertTextNotPresent($string, "Chuck Norris: I do not expect string '$string' here");
		}
	}

	public function zbxTestClick($id) {
		$this->click($id);
	}

	public function zbxTestClickWait($id) {
		$this->zbxTestClick($id);
		$this->wait();
	}

	public function href_click($a) {
		$this->click("xpath=//a[contains(@href,'$a')]");
	}

	public function zbxTestCheckboxSelect($a) {
		if (!$this->isChecked($a)) {
			$this->click($a);
		}
	}

	public function zbxTestCheckboxUnselect($a) {
		if ($this->isChecked($a)) {
			$this->click($a);
		}
	}

	public function input_type($id, $str) {
		$this->type($id, $str);
	}

	public function zbxTestDropdownHasOptions($id, array $strings) {
		foreach ($strings as $string) {
			$this->assertSelectHasOption($id, $string);
		}
	}

	public function zbxTestDropdownSelect($id, $str) {
		$this->zbxTestDropdownHasOptions($id, array($str));
		$this->select($id, $str);
	}

	public function zbxTestDropdownSelectWait($id, $str) {
		$selected = $this->getSelectedLabel($id);
		$this->zbxTestDropdownSelect($id, $str);
		// Wait only if drop down selection was changed
		if ($selected != $str) {
			$this->wait();
		}
	}

	public function wait() {
		$this->waitForPageToLoad();
		$this->zbxTestCheckFatalErrors();
	}

	public function tab_switch($tab) {
		// switches tab by receiving tab title text
		$this->click("xpath=//div[@id='tabs']/ul/li/a[text()='$tab']");
		$this->waitForElementPresent("xpath=//li[contains(@class, 'ui-tabs-selected')]/a[text()='$tab']");
		$this->zbxTestCheckFatalErrors();
	}

	// zbx_popup is the default opened window id if none is passed
	public function zbxTestLaunchPopup($buttonId, $windowId = 'zbx_popup') {
		// the above does not seem to work, thus this ugly method has to be used - at least until buttons get unique names...
		$this->click("//input[@id='$buttonId' and contains(@onclick, 'return PopUp')]");
		$this->waitForPopUp($windowId, 6000);
		$this->selectWindow($windowId);
		$this->zbxTestCheckFatalErrors();
	}

	public function zbxGetDropDownElements($dropdownId) {
		$optionCount = $this->getXpathCount('//*[@id="'.$dropdownId.'"]/option');
		$optionList = array();
		for ($i = 1; $i <= $optionCount; $i++) {
			$optionList[] = array(
				'id' => $this->getAttribute('//*[@id="'.$dropdownId.'"]/option['.$i.']@value'),
				'content' => $this->getText('//*[@id="'.$dropdownId.'"]/option['.$i.']')
			);
		}
		return $optionList;
	}

	public function template_unlink_and_clear($template) {
		// WARNING: not tested yet
		// clicks button named "Unlink and clear" next to template named $template
		$this->click("xpath=//div[text()='$template']/../div[@class='dd']/input[@value='Unlink']/../input[@value='Unlink and clear']");
	}

	/**
	 * Assert that the element with the given name has a specific value.
	 *
	 * @param $name
	 * @param $value
	 */
	public function assertElementValue($name, $value) {
		$this->assertElementPresent("//*[@name='".$name."' and @value='".$value."']");
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

	/**
	 * Assert that the element with the given name contains a specific text.
	 *
	 * @param $name
	 * @param $text
	 */
	public function assertDrowpdownValueText($name, $text) {
		$this->assertElementPresent("//select[@name='".$name."']//option[text()='".$text."' and @selected]");
	}

	public function templateLink($host, $template) {
		// $template = "Template_Linux";
		// $host = "Zabbix server";
		$sql = "select hostid from hosts where name='".$host."' and status in (".HOST_STATUS_MONITORED.",".HOST_STATUS_NOT_MONITORED.")";
		$this->assertEquals(1, DBcount($sql), "Chuck Norris: No such host: $host");
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		// using template by name for now only. id will be needed for linkage tests etc
		// $sql = "select hostid from hosts where host='".$template."'";
		// $this->assertEquals(1, DBcount($sql), "Chuck Norris: No such template: $template");
		// $row = DBfetch(DBselect($sql));
		// $templateid = $row['hostid'];

		// Link a template to a host from host properties page
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');
		$this->zbxTestClickWait('link='.$host);
		$this->tab_switch("Templates");
		$this->zbxTestTextNotPresent($template);

		// adds template $template to the list of linked templates
		// for now, ignores the fact that template might be already linked
		$this->zbxTestLaunchPopup('add');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->check("//input[@value='$template' and @type='checkbox']");
		$this->zbxTestClick('select');
		$this->selectWindow();
		$this->wait();
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of hosts');
		$this->zbxTestTextPresent('Host updated');
		// no entities should be deleted, they all should be updated
		$this->zbxTestTextNotPresent('deleted');
		$this->zbxTestTextNotPresent('created');

		// linking finished, checks proceed
		// should check that items, triggers, graphs and applications exist on the host and are linked to the template
		// should do that by looking in the db
		// currently doing something very brutal - just looking whether Template_Linux is present on entity pages
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		$this->zbxTestTextPresent($template.':');
		// using "host navigation bar" at the top of entity list
		$this->href_click("triggers.php?hostid=$hostid&sid=");
		$this->wait();
		$this->zbxTestTextPresent($template.':');
		// default data.sql has a problem - graphs are not present in the template
		// $this->href_click("graphs.php?hostid=$hostid&sid=");
		// $this->wait();
		$this->href_click("applications.php?hostid=$hostid&sid=");
		$this->wait();
		$this->zbxTestTextPresent($template.':');

		// tests that items that should have interfaceid don't have it set to NULL
		// checks all items on enabled and disabled hosts (types 0 and 1) except:
		// ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE, ITEM_TYPE_CALCULATED, ITEM_TYPE_HTTPTEST
		// and item is not item prototype (flags!=2)
		// if any found, something's wrong
		$sql = 'SELECT i.itemid'.
				' FROM items i, hosts h'.
				' WHERE i.hostid=h.hostid'.
					' AND h.status in (0,1)'.
					' AND i.interfaceid is NULL'.
					' AND i.type not in (2,5,7,8,9,15)'.
					' AND i.flags NOT IN (2)';

		$this->assertEquals(0, DBcount($sql), "Chuck Norris: There are items with interfaceid NULL not of types 2, 5, 7, 8, 9, 15");

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
