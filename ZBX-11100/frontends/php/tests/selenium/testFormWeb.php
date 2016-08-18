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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class testFormWeb extends CWebTest {

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $template = 'Inheritance test template';

	/**
	 * The number of the test host created in the test data set.
	 *
	 * @var int
	 */
	protected $hostid = 40001;

	public function testFormWeb_backup() {
		DBsave_tables('httptest');
	}

	// Returns layout data
	public static function layout() {
		return [
			[
				[
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'NTLM',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'NTLM',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'NTLM',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'NTLM',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'other ...',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'other ...',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'other ...',
					'authentication' => 'NTLM',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'NTLM',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'NTLM',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'NTLM',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'NTLM',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'other ...',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'other ...',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'other ...',
					'authentication' => 'NTLM',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormWeb1'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceWeb1'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceWeb1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormWeb_CheckLayout($data) {
		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickLinkTextWait($data['template']);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickLinkTextWait($data['host']);
		}

		$this->zbxTestClickLinkTextWait('Web scenarios');

		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestCheckHeader('Web monitoring');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestCheckHeader('Web monitoring');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent web scenarios');
			if (isset($data['hostTemplate'])) {
				$this->zbxTestAssertElementPresentXpath("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent web scenarios');
		}

		if (isset($data['agent'])) {
			$this->zbxTestDropdownSelect('agent', $data['agent']);
			$agent = $data['agent'];
		}
		else {
			$agent = $this->zbxTestGetSelectedLabel('agent');
		}

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleId('name');
		$this->zbxTestAssertAttribute("//input[@id='name']", 'maxlength', 64);
		$this->zbxTestAssertAttribute("//input[@id='name']", 'size', 20);
		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//input[@id='name']", 'readonly');
		}
		else {
			$this->zbxTestAssertAttribute("//input[@id='name']", 'autofocus');
		}

		$this->zbxTestTextPresent('Application');

		$this->zbxTestTextPresent('New application');
		$this->zbxTestAssertVisibleId('new_application');
		$this->zbxTestAssertAttribute("//input[@id='new_application']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='new_application']", 'size', 20);

		$this->zbxTestTextPresent('Update interval (in sec)');
		$this->zbxTestAssertVisibleId('delay');
		$this->zbxTestAssertAttribute("//input[@id='delay']", 'maxlength', 5);
		$this->zbxTestAssertAttribute("//input[@id='delay']", 'size', 20);
		$this->zbxTestAssertElementValue('delay', 60);

		$this->zbxTestTextPresent('Attempts');
		$this->zbxTestAssertVisibleId('retries');
		$this->zbxTestAssertAttribute("//input[@id='retries']", 'maxlength', 2);
		$this->zbxTestAssertAttribute("//input[@id='retries']", 'size', 20);
		$this->zbxTestAssertElementValue('retries', 1);

		$this->zbxTestTextPresent('Agent');
		$this->zbxTestAssertVisibleId('agent');

		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 11.0']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 10.0']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 9.0']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 8.0']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 7.0']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Internet Explorer']/option[text()='Internet Explorer 6.0']");

		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Mozilla Firefox']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Firefox 33.0 (Windows)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Firefox 33.0 (Linux)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Mozilla Firefox']/option[text()='Firefox 33.0 (Mac)']");

		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Opera']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 25.0 (Windows)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 25.0 (Mac)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera 12.16 (Linux)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera Mobile 12.02']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Opera']/option[text()='Opera Mini 9.80']");

		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Safari']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 7.0.6 (Mac)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 5.1.7 (Windows)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 6.0 (iPad)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Safari']/option[text()='Safari 5.0.2 (iPhone)']");

		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Google Chrome']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Chrome 38.0 (Windows)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Chrome 38.0 (Linux)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Chrome 38.0 (Mac)']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Google Chrome']/option[text()='Chromium 37.0 (Linux)']");

		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']/option[text()='Konqueror 4.14.2']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']/option[text()='Lynx 2.8.8rel.2']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']/option[text()='Links 2.8']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']/option[text()='Android Webkit Browser 4.4.4']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']/option[text()='Googlebot 2.1']");
		$this->zbxTestAssertElementPresentXpath("//select[@id='agent']/optgroup[@label='Others']/option[text()='other ...']");

		if ($agent == 'other ...') {
			$this->zbxTestAssertVisibleId('agent_other');
		}
		else {
			$this->zbxTestAssertNotVisibleId('agent_other');
		}

		$this->zbxTestTextPresent('HTTP proxy');
		$this->zbxTestAssertVisibleId('http_proxy');
		$this->zbxTestAssertAttribute("//input[@id='http_proxy']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='http_proxy']", 'size', 20);
		$this->zbxTestAssertAttribute("//input[@id='http_proxy']", 'placeholder', 'http://[user[:password]@]proxy.example.com[:port]');

		$this->zbxTestTextPresent('Variables');
		$this->zbxTestAssertVisibleId('variables');
		$this->zbxTestAssertAttribute("//textarea[@id='variables']", 'rows', 7);

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertVisibleId('status');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertAttribute("//button[@id='cancel']", 'name', 'cancel');
		$this->zbxTestAssertAttribute("//button[@id='cancel']", 'type', 'button');

		if (isset($data['form']) && !isset($data['templatedHost'])) {
			$this->zbxTestAssertVisibleId('update');
			$this->zbxTestAssertElementValue('update', 'Update');

			$this->zbxTestAssertVisibleId('clone');
			$this->zbxTestAssertElementValue('clone', 'Clone');

			$this->zbxTestAssertVisibleId('delete');
			$this->zbxTestAssertElementValue('delete', 'Delete');
		}
		elseif (isset($data['form']) && isset($data['templatedHost']))  {
			$this->zbxTestAssertVisibleId('clone');
			$this->zbxTestAssertElementValue('clone', 'Clone');

			$this->zbxTestAssertElementNotPresentId('delete');
		}
		else {
			$this->zbxTestAssertElementPresentId('add');
			$this->zbxTestAssertElementNotPresentId('clone');
			$this->zbxTestAssertElementNotPresentId('delete');
			$this->zbxTestAssertElementNotPresentId('update');
		}

		$this->zbxTestClickWait('tab_authenticationTab');
		if ($this->zbxTestGetText("//li[contains(@class, 'ui-tabs-active')]/a") != 'Authentication' ) {
			$this->zbxTestTabSwitch('Authentication');
		}
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('authentication'));

		$this->zbxTestTextPresent('Authentication');
		$this->zbxTestDropdownHasOptions('authentication', ['None',	'Basic', 'NTLM']);

		if (isset($data['authentication'])) {
			$this->zbxTestDropdownSelect('authentication', $data['authentication']);
			$authentication = $data['authentication'];
		}
		else {
			$authentication = $this->zbxTestGetSelectedLabel('authentication');
		}

		if ($authentication!='None') {
			$this->zbxTestTextPresent('User');
			$this->zbxTestAssertVisibleId('http_user');
			$this->zbxTestAssertAttribute("//input[@id='http_user']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='http_user']", 'size', 20);

			$this->zbxTestTextPresent('Password');
			$this->zbxTestAssertVisibleId('http_password');
			$this->zbxTestAssertAttribute("//input[@id='http_password']", 'maxlength', 64);
			$this->zbxTestAssertAttribute("//input[@id='http_password']", 'size', 20);
		}
		else {
			$this->zbxTestTextNotVisibleOnPage(['User', 'Password']);
			$this->zbxTestAssertNotVisibleId('http_user');
			$this->zbxTestAssertNotVisibleId('http_password');
		}

		$this->zbxTestTabSwitch('Steps');
		$this->zbxTestTextPresent(['Steps', 'Name', 'Timeout', 'URL', 'Required' ,'Status codes', 'Action']);

		if (isset($data['form']) && !isset($data['templatedHost'])) {
			$this->zbxTestAssertVisibleId('add_step');
			$this->zbxTestAssertElementText("//button[@id='add_step']", 'Add');

			$this->zbxTestAssertVisibleId('remove_0');
			$this->zbxTestAssertElementText("//button[@id='remove_0']", 'Remove');
		}
		elseif (!isset($data['form'])) {
			$this->zbxTestAssertVisibleId('add_step');
			$this->zbxTestAssertElementText("//button[@id='add_step']", 'Add');

			$this->zbxTestAssertElementNotPresentId('remove_0');
		}
		else {
			$this->zbxTestAssertElementNotPresentId('add_step');
			$this->zbxTestAssertElementNotPresentId('remove_0');
		}
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

		$sqlItems = "select * from items ORDER BY itemid";
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Web scenarios');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent('Web scenario updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestCheckTitle('Configuration of web monitoring');

		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	// Returns create data
	public static function create() {
		return [
			// Empty name/steps
			[
				[
					'expected' => TEST_BAD,
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.',
						'Field "Steps" is mandatory.'
					]
				]
			],
			// Empty steps
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Empty steps',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Field "Steps" is mandatory.'
					]
				]
			],
			// Empty name
			[
				[
					'expected' => TEST_BAD,
					'add_step' => [
						['step' => 'Empty name']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.'
					]
				]
			],
			// Name -numbers only
			[
				[
					'expected' => TEST_GOOD,
					'name' => '1234567890',
					'add_step' => [
						['step' => '1234567890']
					]
				]
			],
			// Name -symbols only
			[
				[
					'expected' => TEST_GOOD,
					'name' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => '!@#$%^&*()_+{}:"|<>?,./']
					]
				]
			],
			// Name-spaces
			[
				[
					'expected' => TEST_GOOD,
					'name' => '   zabbix  123  ',
					'add_step' => [
						['step' => '   zabbix  123  ']
					]
				]
			],
			// Max -64 symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'qwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'add_step' => [
						['step' => 'qwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop']
					]
				]
			],
			// Application -numbers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Application numbers only',
					'new_application' => '1234567890',
					'add_step' => [
						['step' => 'Application numbers only']
					]
				]
			],
			// Application -symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Application symbols only',
					'new_application' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => 'Application symbols only']
					]
				]
			],
			// Application -max length
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Application max length',
					'new_application' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => [
						['step' => 'Application max length']
					]
				]
			],
			// User/password empty
			[
				[
					'expected' => TEST_BAD,
					'name' => 'User/password empty',
					'authentication' => 'Basic',
					'add_step' => [
						['step' => 'User/password empty']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "User": cannot be empty.',
						'Incorrect value for field "Password": cannot be empty.'
					]
				]
			],
			// User empty
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Password empty',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'add_step' => [
						['step' => 'Password empty']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Password": cannot be empty.'
					]
				]
			],
			// Password empty
			[
				[
					'expected' => TEST_BAD,
					'name' => 'User empty',
					'authentication' => 'Basic',
					'http_password' => 'zabbix',
					'add_step' => [
						['step' => 'User empty']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "User": cannot be empty.'
					]
				]
			],
			// Username/password numbers only
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Username/password numbers only',
					'authentication' => 'Basic',
					'http_user' => '12345',
					'http_password' => '67890',
					'add_step' => [
						['step' => 'Username/password numbers only']
					]
				]
			],
			// Username/password symbols only
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Username/password symbols only',
					'authentication' => 'Basic',
					'http_user' => '!@#$%^&*()_+{}:"|<>?,./',
					'http_password' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => 'Username/password symbols only']
					]
				]
			],
			// Username/password with spaces
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Username/password with spaces',
					'authentication' => 'Basic',
					'http_user' => '   zabbix  123  ',
					'http_password' => '   zabbix  123  ',
					'add_step' => [
						['step' => 'Username/password with spaces']
					]
				]
			],
			// Username/password -64 max allowed
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Username/password max allowed',
					'authentication' => 'Basic',
					'http_user' => 'wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'http_password' => 'wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'add_step' => [
						['step' => 'Username/password max allowed']
					]
				]
			],
			// Retries-minus one
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Retries- minus one',
					'retries' => '-1',
					'add_step' => [
						['step' => 'Retries- minus one']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "-1" for "Attempts" field: must be between 1 and 10.'
					]
				]
			],
			// Retries-zero
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Retries- zero',
					'retries' => '0',
					'add_step' => [
						['step' => 'Retries- zero']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "0" for "Attempts" field: must be between 1 and 10.'
					]
				]
			],
			// Retries-number
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Retries-one',
					'retries' => '1',
					'add_step' => [
						['step' => 'Retries-one']
					]
				]
			],
			// Retries-ten
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Retries- ten',
					'retries' => '10',
					'add_step' => [
						['step' => 'Retries- ten']
					]
				]
			],
			// Retries-eleven
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Retries- eleven',
					'retries' => '11',
					'add_step' => [
						['step' => 'Retries- eleven']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "11" for "Attempts" field: must be between 1 and 10.'
					]
				]
			],
			// Interval-minus one
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Interval- minus one',
					'delay' => '-1',
					'add_step' => [
						['step' => 'Interval- minus one']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "-1" for "Update interval (in sec)" field: must be between 1 and 86400.'
					]
				]
			],
			// Interval-zero
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Interval- zero',
					'delay' => '0',
					'add_step' => [
						['step' => 'Interval- zero']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "0" for "Update interval (in sec)" field: must be between 1 and 86400.'
					]
				]
			],
			// Interval-number
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Interval-one',
					'delay' => '1',
					'add_step' => [
						['step' => 'Interval-one']
					]
				]
			],
			// Interval-86400
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Interval- 86400',
					'delay' => '86400',
					'add_step' => [
						['step' => 'Interval- 86400']
					]
				]
			],
			// Interval-86401
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Interval- 86401',
					'delay' => '86401',
					'add_step' => [
						['step' => 'Interval- 86401']
					],
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value "86401" for "Update interval (in sec)" field: must be between 1 and 86400.'
					]
				]
			],
			// Http proxy -just numbers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Http proxy -just numbers',
					'http_proxy' => '1234567890',
					'add_step' => [
						['step' => 'Http proxy -just numbers']
					]
				]
			],
			// Http proxy -symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Http proxy -symbols',
					'http_proxy' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => 'Http proxy -symbols']
					]
				]
			],
			// Http proxy -max allowed length
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Http proxy -max allowed length',
					'http_proxy' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => [
						['step' => 'Http proxy -max allowed length']
					]
				]
			],
			// Variables -just numbers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables -just numbers',
					'variables' => '1234567890',
					'add_step' => [
						['step' => 'Variables -just numbers']
					]
				]
			],
			// Variables -symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables -symbols',
					'variables' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => 'Variables -symbols']
					]
				]
			],
			// Variables -255 length-allowed more
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables -255 length',
					'http_proxy' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => [
						['step' => 'Variables -255 length']
					]
				]
			],
			// Duplicate web scenario
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Duplicate web test',
					'add_step' => [
						['step' => 'Duplicate web test']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Duplicate web test',
					'add_step' => [
						['step' => 'Duplicate web test']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Web scenario "Duplicate web test" already exists.'
					]
				]
			],
			// testing created items using triggers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Trigger create web test',
					'add_step' => [
						['step' => 'Trigger create web test']
					],
					'createTriggers' => [
						'web.test.in[Trigger create web test,,bps]',
						'web.test.fail[Trigger create web test]',
						'web.test.error[Trigger create web test]',
						'web.test.in[Trigger create web test,Trigger create web test step,bps]',
						'web.test.time[Trigger create web test,Trigger create web test step,resp]',
						'web.test.rspcode[Trigger create web test,Trigger create web test step]'
					]
				]
			],
			// testing created items using triggers -multiple steps added
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Trigger create multiple steps web test',
					'add_step' => [
						['step' => 'Trigger create multiple steps web test1'],
						['step' => 'Trigger create multiple steps web test2'],
						['step' => 'Trigger create multiple steps web test3'],
						['step' => 'Trigger create multiple steps web test4'],
					],
					'createTriggers' => [
						'web.test.in[Trigger create multiple steps web test,,bps]',
						'web.test.fail[Trigger create multiple steps web test]',
						'web.test.error[Trigger create multiple steps web test]',
						'web.test.in[Trigger create multiple steps web test,Trigger create multiple steps web test1 step,bps]',
						'web.test.time[Trigger create multiple steps web test,Trigger create multiple steps web test1 step,resp]',
						'web.test.rspcode[Trigger create multiple steps web test,Trigger create multiple steps web test1 step]',
						'web.test.in[Trigger create multiple steps web test,Trigger create multiple steps web test2 step,bps]',
						'web.test.time[Trigger create multiple steps web test,Trigger create multiple steps web test2 step,resp]',
						'web.test.rspcode[Trigger create multiple steps web test,Trigger create multiple steps web test2 step]',
						'web.test.in[Trigger create multiple steps web test,Trigger create multiple steps web test3 step,bps]',
						'web.test.time[Trigger create multiple steps web test,Trigger create multiple steps web test3 step,resp]',
						'web.test.rspcode[Trigger create multiple steps web test,Trigger create multiple steps web test3 step]',
						'web.test.in[Trigger create multiple steps web test,Trigger create multiple steps web test4 step,bps]',
						'web.test.time[Trigger create multiple steps web test,Trigger create multiple steps web test4 step,resp]',
						'web.test.rspcode[Trigger create multiple steps web test,Trigger create multiple steps web test4 step]'
					]
				]
			],
		// many steps added
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Many websteps added web test',
					'add_step' => [
						['step' => 'Many websteps added web test1'],
						['step' => 'Many websteps added web test2'],
						['step' => 'Many websteps added web test3'],
						['step' => 'Many websteps added web test4'],
						['step' => 'Many websteps added web test5'],
						['step' => 'Many websteps added web test6'],
						['step' => 'Many websteps added web test7'],
						['step' => 'Many websteps added web test8'],
						['step' => 'Many websteps added web test9'],
						['step' => 'Many websteps added web test10'],
						['step' => 'Many websteps added web test11'],
						['step' => 'Many websteps added web test12'],
						['step' => 'Many websteps added web test13'],
						['step' => 'Many websteps added web test14'],
						['step' => 'Many websteps added web test15'],
						['step' => 'Many websteps added web test16'],
						['step' => 'Many websteps added web test17'],
						['step' => 'Many websteps added web test18'],
						['step' => 'Many websteps added web test19'],
						['step' => 'Many websteps added web test20'],
						['step' => 'Many websteps added web test21'],
						['step' => 'Many websteps added web test22'],
						['step' => 'Many websteps added web test23'],
						['step' => 'Many websteps added web test24'],
						['step' => 'Many websteps added web test25'],
						['step' => 'Many websteps added web test26'],
						['step' => 'Many websteps added web test27'],
						['step' => 'Many websteps added web test28'],
						['step' => 'Many websteps added web test29'],
						['step' => 'Many websteps added web test30']
					]
				]
			],
			// List of main agents
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 11.0 None',
					'agent' => 'Internet Explorer 11.0',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Internet Explorer 11.0 None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 11.0 Basic',
					'agent' => 'Internet Explorer 11.0',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Internet Explorer 11.0 Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 11.0 NTLM',
					'agent' => 'Internet Explorer 11.0',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Internet Explorer 11.0 NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Firefox 33.0 (Windows) None',
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Firefox 33.0 (Windows) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Firefox 33.0 (Windows) Basic',
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Firefox 33.0 (Windows) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Firefox 33.0 (Windows) 8.0 NTLM',
					'agent' => 'Firefox 33.0 (Windows)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Firefox 33.0 (Windows) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Opera 25.0 (Windows) None',
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Opera 25.0 (Windows) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Opera 25.0 (Windows) Basic',
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Opera 25.0 (Windows) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Opera 25.0 (Windows) NTLM',
					'agent' => 'Opera 25.0 (Windows)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Opera 25.0 (Windows) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Safari 7.0.6 (Mac)',
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Safari 7.0.6 (Mac) None']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Safari 7.0.6 (Mac) Basic',
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Safari 7.0.6 (Mac) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Safari 7.0.6 (Mac) NTLM',
					'agent' => 'Safari 7.0.6 (Mac)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Safari 7.0.6 (Mac) NTLM', 'remove' => true],
						['step' => 'Safari 7.0.6 (Mac) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Chrome 38.0 (Windows) None',
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Chrome 38.0 (Windows) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Chrome 38.0 (Windows) Basic',
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Chrome 38.0 (Windows) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Chrome 38.0 (Windows) NTLM',
					'agent' => 'Chrome 38.0 (Windows)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Chrome 38.0 (Windows) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => '(other ...) None',
					'agent' => 'other ...',
					'authentication' => 'None',
					'add_step' => [
						['step' => '(other ...) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => '(other ...) Basic',
					'agent' => 'other ...',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => '(other ...) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => '(other ...) NTLM',
					'agent' => 'other ...',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => '(other ...) NTLM', 'remove' => true],
						['step' => '(other ...) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormWeb_SimpleCreate($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Web scenarios');

		$this->zbxTestCheckTitle('Configuration of web monitoring');

		$this->zbxTestClickWait('form');
		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestCheckHeader('Web monitoring');

		if (isset($data['agent'])) {
			switch ($data['agent']) {
				case 'other ...':
					$this->zbxTestDropdownSelect('agent', $data['agent']);
					$agent = $this->zbxTestGetValue("//input[@id='agent_other']");
					break;
				default:
					$this->zbxTestDropdownSelect('agent', $data['agent']);
					$agent = $this->zbxTestGetValue("//select[@id='agent']//option[text()='".$data['agent']."']");
					break;
			}
		}

		if (isset($data['name'])) {
			$this->zbxTestInputTypeWait('name', $data['name']);
		}
		$name = $this->zbxTestGetValue("//input[@id='name']");

		if (isset($data['new_application'])) {
			$this->zbxTestInputType('new_application', $data['new_application']);
		}
		$new_application = $this->zbxTestGetValue("//input[@id='new_application']");

		if (isset($data['delay']))	{
			$this->zbxTestInputTypeOverwrite('delay', $data['delay']);
		}
		$delay = $this->zbxTestGetValue("//input[@id='delay']");

		if (isset($data['retries'])) {
			$this->zbxTestInputTypeOverwrite('retries', $data['retries']);
		}
		$retries = $this->zbxTestGetValue("//input[@id='retries']");

		if (isset($data['http_proxy'])) {
			$this->zbxTestInputType('http_proxy', $data['http_proxy']);
		}

		if (isset($data['variables'])) {
			$this->zbxTestInputType('variables', $data['variables']);
		}

		$this->zbxTestTabSwitch('Authentication');
		if (isset($data['authentication'])) {
			$this->zbxTestDropdownSelectWait('authentication', $data['authentication']);
		}
		$authentication = $this->zbxTestGetSelectedLabel('authentication');

		if (isset($data['http_user'])) {
			$this->zbxTestInputTypeWait('http_user', $data['http_user']);
		}

		if (isset($data['http_password'])) {
			$this->zbxTestInputType('http_password', $data['http_password']);
		}

		$check = false;
		if (isset($data['add_step'])) {
			$this->zbxTestTabSwitch('Steps');
			foreach($data['add_step'] as $item) {
				$this->zbxTestClickWait('add_step');
				$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
				$this->zbxTestCheckFatalErrors();
				$step = $item['step']." step";
				$this->zbxTestInputTypeWait('name',$step);
				$url = $step." url";
				$this->zbxTestInputTypeWait('url', $url);
				$this->zbxTestClick('add');
				$this->zbxTestWaitWindowClose();

				if(isset($item['remove'])) {
					$this->zbxTestClickWait('remove_0');
				}
			}
		}

		$this->zbxTestClickWait('add');
		$expected = $data['expected'];
		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Web scenario added');
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestTextPresent(['Number of steps', 'Update interval', 'Status']);
				break;

			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextNotPresent('Web scenario added');
				break;
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT * FROM httptest test LEFT JOIN httpstep step ON ".
				"step.httptestid = test.httptestid ".
				"WHERE test.name = '".$name."' AND step.name = '".$step."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['agent'], $agent);
				$this->assertEquals($row['url'], $url);
				$this->assertEquals($row['delay'], $delay);
				$this->assertEquals($row['hostid'], $this->hostid);
				$this->assertEquals($row['retries'], $retries);
				$httptestid = $row['httptestid'];
			}
		}
		if (isset($data['formCheck'])) {
			if (isset ($data['dbName'])) {
				$dbName = $data['dbName'];
			}
			else {
				$dbName = $name;
			}
			$this->zbxTestClickLinkTextWait($dbName);
			$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('name'));
			$this->zbxTestAssertElementValue('name', $name);
			$this->zbxTestDropdownAssertSelected('agent', $data['agent']);
			if (isset($data['add_step'])) {
				$this->zbxTestTabSwitch('Steps');
				foreach($data['add_step'] as $item) {
					$step = $item['step']." step";
					$this->zbxTestTextPresent($step);
				}
			}
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Web scenarios');
			$this->zbxTestCheckHeader('Web monitoring');
			$this->zbxTestTextPresent($name);
		}

		if (isset($data['createTriggers'])) {
			$this->zbxTestClickLinkTextWait('Triggers');

			foreach ($data['createTriggers'] as $trigger) {
				$this->zbxTestClickWait('form');

				$this->zbxTestInputType('description', $trigger);
				$expressionTrigger = '{'.$this->host.':'.$trigger.'.last(0)}=0';
				$this->zbxTestInputTypeWait('expression', $expressionTrigger);
				$this->zbxTestClickWait('add');

				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger added');
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestCheckHeader('Triggers');
			}
		}

		if (isset($data['remove'])) {
			$this->zbxTestCheckboxSelect("group_httptestid_$httptestid");
			$this->zbxTestClickButton('httptest.massdelete');

			$this->webDriver->switchTo()->alert()->accept();

			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Web scenario deleted');
			$this->assertEquals(0, DBcount("SELECT * FROM httptest test LEFT JOIN httpstep step ON ".
				"step.httptestid = test.httptestid ".
				"WHERE test.name = '".$name."' AND step.name = '".$step."'"));
		}
	}

	public function testFormWeb_restore() {
		DBrestore_tables('httptest');
	}
}
