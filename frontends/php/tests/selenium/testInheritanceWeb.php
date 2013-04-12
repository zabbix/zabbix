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

class testInheritanceWeb extends CWebTest {


	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Template inheritance test host';

	/**
	 * The number of the test host created in the test data set.
	 *
	 * @var int
	 */
	protected $templateid = 30000;

	/**
	 * The number of the test host created in the test data set.
	 *
	 * @var int
	 */
	protected $hostid = 30001;

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceWeb_Setup() {
		DBsave_tables('httptest');
	}

	// Returns layout data
	public static function layout() {
		return array(
		/*	array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Opera 12.00',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Opera 12.00',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Opera 12.00',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Safari 5.0',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Safari 5.0',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Safari 5.0',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Google Chrome 17',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Google Chrome 17',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Google Chrome 17',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => '(other ...)',
					'authentication' => 'None'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => '(other ...)',
					'authentication' => 'Basic authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => '(other ...)',
					'authentication' => 'NTLM authentication'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceWeb1'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceWeb1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				)
			)*/
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testInheritanceWeb_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
		}

		$this->zbxTestClickWait('link=Web scenarios');

		$this->checkTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');

		if (!isset($data['form'])) {
			$this->zbxTestClickWait('form');
		}
		else {
			$this->zbxTestClickWait('link='.$data['form']);
		}

		$this->checkTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
		$this->zbxTestTextPresent('Scenario');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent web scenarios');
			if (isset($data['hostTemplate'])) {
				$this->assertElementPresent("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent web scenarios');
		}

		if (isset($data['authentication'])) {
			$this->zbxTestDropdownSelectWait('authentication', $data['authentication']);
		}
		$authentication = $this->getSelectedLabel('authentication');

		if (isset($data['agent']) && $data['agent']!='(other ...)') {
			$this->zbxTestDropdownSelect('agent', $data['agent']);
			$agent = $this->getSelectedLabel('agent');
		}
		elseif (isset($data['agent']) && $data['agent']=='(other ...)') {
			$this->zbxTestDropdownSelect('agent', 'Internet Explorer 10.0');
			$this->zbxTestDropdownSelect('agent', $data['agent']);
			$agent = $this->getValue("//div[@class='dd']/input[@name='agent']");
		}
		else {
			$agent = $this->getSelectedLabel('agent');
		}

		$this->zbxTestTextPresent('Host');
		$this->assertVisible('hostname');
		$this->assertAttribute("//input[@id='hostname']/@maxlength", 255);
		$this->assertAttribute("//input[@id='hostname']/@size", 50);
		if (isset($data['template'])) {
			$this->assertAttribute("//*[@id='hostname']/@value", $data['template']);
		}
		elseif (isset($data['host'])) {
			$this->assertAttribute("//*[@id='hostname']/@value", $data['host']);
		}
		$this->assertAttribute("//*[@id='hostname']/@readonly", 'readonly');

		if (!isset($data['form'])) {
			$this->assertVisible('button_popup');
			$this->assertAttribute("//input[@id='button_popup']/@value", 'Select');
		}
		else {
			$this->assertElementNotPresent('button_popup');
		}

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('name');
		$this->assertAttribute("//input[@id='name']/@maxlength", 64);
		$this->assertAttribute("//input[@id='name']/@size", 50);
		if (isset($data['templatedHost'])) {
			$this->assertAttribute("//input[@id='name']/@readonly", 'readonly');
		}
		else {
			$this->assertAttribute("//input[@id='name']/@autofocus", 'autofocus');
		}

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
		else {
			$this->zbxTestTextNotPresent(array('User', 'Password'));
			$this->assertElementNotPresent('http_user');
			$this->assertElementNotPresent('http_password');
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

		if ((isset($data['agent']) && $data['agent'] !='(other ...)') || !isset($data['agent'])) {
			$this->zbxTestTextPresent('Agent');
			$this->assertVisible('agent');
			if (!isset($data['form'])) {
				$this->assertElementPresent("//select[@id='agent']/option[text()='(other ...)']");
			}
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


		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');
		$this->assertAttribute("//input[@id='save']/@role", 'button');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');
		$this->assertAttribute("//input[@id='cancel']/@role", 'button');

		if (isset($data['form']) && !isset($data['templatedHost'])) {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');

			$this->assertVisible('delete');
			$this->assertAttribute("//input[@id='delete']/@value", 'Delete');
		}
		elseif (isset($data['form']) && isset($data['templatedHost']))  {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');
		}
		else {
			$this->assertElementNotPresent('clone');
			$this->assertElementNotPresent('delete');
		}

		$this->zbxTestClick('link=Steps');
		$this->zbxTestTextPresent('Steps');
		$this->zbxTestTextPresent(array('Steps', 'Name', 'Timeout', 'URL', 'Required' ,'Status codes'));
		$this->assertVisible('tab_stepTab');

		if (isset($data['form']) && !isset($data['templatedHost'])) {
			$this->assertVisible('add_step');
			$this->assertAttribute("//input[@id='add_step']/@value", 'Add');
			$this->assertAttribute("//input[@id='add_step']/@type", 'button');

			$this->assertVisible('remove_0');
			$this->assertAttribute("//input[@id='remove_0']/@value", 'Remove');
			$this->assertAttribute("//input[@id='remove_0']/@type", 'button');
		}
		elseif (!isset($data['form'])) {
			$this->assertVisible('add_step');
			$this->assertAttribute("//input[@id='add_step']/@value", 'Add');
			$this->assertAttribute("//input[@id='add_step']/@type", 'button');

			$this->assertElementNotPresent('remove_0');
		}
		else {
			$this->assertElementNotPresent('add_step');
			$this->assertElementNotPresent('remove_0');
		}
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from httptest where hostid = 30000 and name LIKE 'testInheritanceWeb%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceWeb_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlItems = "select * from items ORDER BY itemid";
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$this->template);
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
	public function testInheritanceWeb_Teardown() {
		DBrestore_tables('httptest');
	}
}
