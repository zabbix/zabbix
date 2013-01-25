<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
		'.php:'
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

	public function login($url = null) {
		global $ZBX_SERVER_NAME;

		$this->open('index.php');
		$this->wait();
		// Login if not logged in already
		if ($this->isElementPresent('id=password')) {
			$this->input_type('name', PHPUNIT_LOGIN_NAME);
			$this->input_type('password', PHPUNIT_LOGIN_PWD);
			$this->click('enter');
			$this->wait();
		}
		if (isset($url)) {
			$this->open($url);
			$this->wait();
		}
		$this->ok($ZBX_SERVER_NAME);
		$this->ok('Admin');
		$this->nok('Login name or password is incorrect');
	}

	public function logout() {
		$this->click('link=Logout');
		$this->wait();
	}

	public function checkFatalErrors() {
		foreach ($this->failIfExists as $str) {
			$this->assertTextNotPresent($str, "Chuck Norris: I do not expect string '$str' here.");
		}
	}

	public function checkTitle($title) {
		global $ZBX_SERVER_NAME;

		if ($ZBX_SERVER_NAME !== '') {
			$title = $ZBX_SERVER_NAME.': '.$title;
		}

		$this->assertTitle($title);
	}

	public function ok($strings) {
		if (!is_array($strings)) {
			$strings = array($strings);
		}
		foreach ($strings as $string) {
			$this->assertTextPresent($string, "Chuck Norris: I expect string '$string' here");
		}
	}

	public function nok($strings) {
		if (!is_array($strings)) {
			$strings = array($strings);
		}
		foreach ($strings as $string) {
			$this->assertTextNotPresent($string, "Chuck Norris: I do not expect string '$string' here");
		}
	}

	public function button_click($a) {
		$this->click($a);
	}

	public function href_click($a) {
		$this->click("xpath=//a[contains(@href,'$a')]");
	}

	public function checkbox_select($a) {
		if (!$this->isChecked($a)) {
			$this->click($a);
		}
	}

	public function checkbox_unselect($a) {
		if ($this->isChecked($a)) {
			$this->click($a);
		}
	}

	public function input_type($id, $str) {
		$this->type($id, $str);
	}

	public function dropdown_select($id, $str) {
		$this->assertSelectHasOption($id, $str);
		$this->select($id, $str);
	}

	public function dropdown_select_wait($id, $str) {
		$selected = $this->getSelectedLabel($id);
		$this->dropdown_select($id, $str);
		// Wait only if drop down selection was changed
		if ($selected != $str) {
			$this->wait();
		}
	}

	public function wait() {
		$this->waitForPageToLoad();
		$this->checkFatalErrors();
	}

	public function tab_switch($tab) {
		// switches tab by receiving tab title text
		$this->click("xpath=//div[@id='tabs']/ul/li/a[text()='$tab']");
		$this->waitForElementPresent("xpath=//li[contains(@class, 'ui-tabs-selected')]/a[text()='$tab']");
		$this->checkFatalErrors();
	}

	// zbx_popup is the default opened window id if none is passed
	public function zbxLaunchPopup($buttonId, $windowId = 'zbx_popup') {
		// $this->button_click('add');
		// the above does not seem to work, thus this ugly method has to be used - at least until buttons get unique names...
		$this->click("//input[@id='$buttonId' and contains(@onclick, 'return PopUp')]");
		$this->waitForPopUp($windowId, 6000);
		$this->selectWindow($windowId);
		$this->checkFatalErrors();
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
		$this->login('hosts.php');
		$this->dropdown_select_wait('groupid', 'all');
		$this->click("link=$host");
		$this->wait();
		$this->tab_switch("Templates");
		$this->nok("$template");

		// adds template $template to the list of linked templates
		// for now, ignores the fact that template might be already linked
		$this->zbxLaunchPopup('add');
		$this->dropdown_select_wait('groupid', 'Templates');
		$this->check("//input[@value='$template' and @type='checkbox']");
		$this->button_click('select');
		$this->selectWindow();
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of hosts');
		$this->ok('Host updated');
		// no entities should be deleted, they all should be updated
		$this->nok('deleted');
		$this->nok('created');

		// linking finished, checks proceed
		// should check that items, triggers, graphs and applications exist on the host and are linked to the template
		// should do that by looking in the db
		// currently doing something very brutal - just looking whether Template_Linux is present on entity pages
		$this->href_click("items.php?filter_set=1&hostid=$hostid&sid=");
		$this->wait();
		$this->ok("$template:");
		// using "host navigation bar" at the top of entity list
		$this->href_click("triggers.php?hostid=$hostid&sid=");
		$this->wait();
		$this->ok("$template:");
		// default data.sql has a problem - graphs are not present in the template
		// $this->href_click("graphs.php?hostid=$hostid&sid=");
		// $this->wait();
		// $this->ok("$template:");
		$this->href_click("applications.php?hostid=$hostid&sid=");
		$this->wait();
		$this->ok("$template:");

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
			$this->nok($row['host']);
		}
	}
}
