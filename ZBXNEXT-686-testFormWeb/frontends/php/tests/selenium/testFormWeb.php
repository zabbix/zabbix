<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

define('WEB_GOOD', 0);
define('WEB_BAD', 1);

class testFormWeb extends CWebTest {


	/**
	 * The name of the test host for simpleCreate checks created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormWeb_Setup() {
		DBsave_tables('httptest');
	}

	// Returns layout data
	public static function layout() {
		return array(
			array(
				array(
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'agent' => 'Opera 12.00',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'agent' => 'Opera 12.00',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'agent' => 'Opera 12.00',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'agent' => 'Safari 5.0',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'agent' => 'Safari 5.0',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'agent' => 'Safari 5.0',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'agent' => 'Google Chrome 17',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'agent' => 'Google Chrome 17',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'agent' => 'Google Chrome 17',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'agent' => '(other ...)',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'agent' => '(other ...)',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'agent' => '(other ...)',
					'authentication' => 'NTLM authentication'
				)
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormWeb_CheckLayout($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Web scenarios');

		$this->checkTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
		$this->zbxTestTextPresent('Scenario');

		if (isset($data['authentication'])) {
			$this->zbxTestDropdownSelectWait('authentication', $data['authentication']);
		}
		$authentication = $this->getSelectedLabel('authentication');

		if (isset($data['agent']) && $data['agent']!='(other ...)') {
			$this->zbxTestDropdownSelect('agent', $data['agent']);
			$agent = $this->getSelectedLabel('agent');
		}
		else {
			$this->zbxTestDropdownSelect('agent', 'Internet Explorer 10.0');
			$this->zbxTestDropdownSelect('agent', $data['agent']);
			$agent = $this->getValue("//div[@class='dd']/input[@name='agent']");
		}

		$this->zbxTestTextPresent('Host');
		$this->assertVisible('hostname');
		$this->assertAttribute("//input[@id='hostname']/@maxlength", 255);
		$this->assertAttribute("//input[@id='hostname']/@size", 50);
		$this->assertAttribute("//*[@id='hostname']/@value", $this->host);
		$this->assertAttribute("//*[@id='hostname']/@readonly", 'readonly');

		$this->assertVisible('button_popup');
		$this->assertAttribute("//input[@id='button_popup']/@value", 'Select');

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", 64);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');

		$this->zbxTestTextPresent('Application');

		$this->zbxTestTextPresent('New application');
		$this->assertVisible('new_application');
		$this->assertAttribute("//input[@id='new_application']/@maxlength", 255);
		$this->assertAttribute("//input[@id='new_application']/@size", 50);

		$this->zbxTestTextPresent('Authentication');
		$this->assertVisible('authentication');
		$this->zbxTestDropdownHasOptions('authentication', array(
			'None',
			'Basic authentication',
			'NTLM authentication'
		));

		if ($authentication!='None') {
		$this->zbxTestTextPresent('User');
		$this->assertVisible('http_user');
		$this->assertAttribute("//input[@id='http_user']/@maxlength", 64);
		$this->assertAttribute("//input[@id='http_user']/@size", 50);

		$this->zbxTestTextPresent('Password');
		$this->assertVisible('http_password');
		$this->assertAttribute("//input[@id='http_password']/@maxlength", 64);
		$this->assertAttribute("//input[@id='http_password']/@size", 50);
		}

		$this->zbxTestTextPresent('Update interval (in sec)');
		$this->assertVisible('delay');
		$this->assertAttribute("//input[@id='delay']/@maxlength", 5);
		$this->assertAttribute("//input[@id='delay']/@size", 5);
		$this->assertAttribute("//input[@id='delay']/@value", 60);

		$this->zbxTestTextPresent('Retries');
		$this->assertVisible('retries');
		$this->assertAttribute("//input[@id='retries']/@maxlength", 2);
		$this->assertAttribute("//input[@id='retries']/@size", 2);
		$this->assertAttribute("//input[@id='retries']/@value", 1);

		if ($data['agent']!='(other ...)') {
			$this->zbxTestTextPresent('Agent');
			$this->assertVisible('agent');
			$this->assertElementPresent("//select[@id='agent']/option[text()='(other ...)']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Internet Explorer']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 10.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 9.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 8.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 7.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 6.0']");

			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 8.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 7.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 6.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 5.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 4.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 3.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Mozilla Firefox 2.0']");

			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Opera']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 12.00']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 11.00']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 10.00']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 9.00']");

			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Safari']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 5.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 4.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 3.0']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari on iPhone']");

			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Google Chrome 17']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Google Chrome 16']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Google Chrome 15']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Google Chrome 14']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Google Chrome 13']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Google Chrome 12']");

			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Konqueror 4.7']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Konqueror 4.6']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Lynx 2.8.7rel.1']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Lynx 2.8.4rel.1']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Links 2.3pre1']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Links 2.2']");
			$this->assertElementPresent("//select[@id='agent']/optgroup[@label='Others']/option[text()='Googlebot']");
		}
		else {
			$this->zbxTestTextPresent('Agent');
			$this->assertElementPresent("//div[@class='dd']/input[@name='agent']");
			$this->assertEquals($agent, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
		}

		$this->zbxTestTextPresent('HTTP proxy');
		$this->assertVisible('http_proxy');
		$this->assertAttribute("//input[@id='http_proxy']/@maxlength", 255);
		$this->assertAttribute("//input[@id='http_proxy']/@size", 50);
		$this->assertElementPresent("//input[@placeholder='http://[username[:password]@]proxy.example.com[:port]']");

		$this->zbxTestTextPresent('Variables');
		$this->assertVisible('macros');
		$this->assertAttribute("//textarea[@id='macros']/@rows", 7);

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//*[@id='status']/@checked", 'checked');

		$this->zbxTestTextPresent('Steps');
		$this->zbxTestTextPresent(array('Steps', 'Name', 'Timeout', 'URL', 'Required' ,'Status codes'));
		$this->assertVisible('tab_stepTab');
		$this->zbxTestClick('link=Steps');

		$this->assertVisible('add_step');
		$this->assertAttribute("//input[@id='add_step']/@value", 'Add');
		$this->assertAttribute("//input[@id='add_step']/@type", 'button');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');
		$this->assertAttribute("//input[@id='save']/@role", 'button');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');
		$this->assertAttribute("//input[@id='cancel']/@role", 'button');
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from httptest where hostid = 40001 and name LIKE 'testFormWeb%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormWeb_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlItems = "select * from items";
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Web scenarios');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent('Scenario updated');
		$this->zbxTestTextPresent("$name");
		$this->checkTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');

		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormWeb_Teardown() {
		DBrestore_tables('httptest');
	}
}
