<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		return array(
			array(
				array(
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'Basic authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'NTLM authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Opera 12.00',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Opera 12.00',
					'authentication' => 'Basic authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Opera 12.00',
					'authentication' => 'NTLM authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Safari 5.0',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Safari 5.0',
					'authentication' => 'Basic authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Safari 5.0',
					'authentication' => 'NTLM authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Google Chrome 17',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Google Chrome 17',
					'authentication' => 'Basic authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => 'Google Chrome 17',
					'authentication' => 'NTLM authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => '(other ...)',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => '(other ...)',
					'authentication' => 'Basic authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'agent' => '(other ...)',
					'authentication' => 'NTLM authentication',
					'host' => 'Simple form test host'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'Basic authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'NTLM authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Opera 12.00',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Opera 12.00',
					'authentication' => 'Basic authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Opera 12.00',
					'authentication' => 'NTLM authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Safari 5.0',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Safari 5.0',
					'authentication' => 'Basic authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Safari 5.0',
					'authentication' => 'NTLM authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Google Chrome 17',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Google Chrome 17',
					'authentication' => 'Basic authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => 'Google Chrome 17',
					'authentication' => 'NTLM authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => '(other ...)',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => '(other ...)',
					'authentication' => 'Basic authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'agent' => '(other ...)',
					'authentication' => 'NTLM authentication',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'form' => 'testFormWeb1'
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
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormWeb_CheckLayout($data) {
		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickWait('link='.$data['template']);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
		}

		$this->zbxTestClickWait('link=Web scenarios');

		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of web monitoring');
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
		elseif (!isset($data['agent'])) {
			$agent = $this->getSelectedLabel('agent');
		}
		else {
			$this->zbxTestDropdownSelect('agent', 'Internet Explorer 10.0');
			$this->zbxTestDropdownSelect('agent', $data['agent']);
			$agent = $this->getValue("//div[@class='dd']/input[@name='agent']");
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
		$this->assertElementPresent("//input[@placeholder='http://[user[:password]@]proxy.example.com[:port]']");

		$this->zbxTestTextPresent('Variables');
		$this->assertVisible('variables');
		$this->assertAttribute("//textarea[@id='variables']/@rows", 7);

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//*[@id='status']/@checked", 'checked');

		$this->assertVisible('update');
		$this->assertAttribute("//input[@id='update']/@value", 'Update');
		$this->assertAttribute("//input[@id='update']/@role", 'button');

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
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Web scenarios');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent('Scenario updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');

		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	// Returns create data
	public static function create() {
		return array(
			// Empty name/steps
			array(
				array(
					'expected' => TEST_BAD,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Field "Steps" is mandatory.'
					)
				)
			),
			// Empty steps
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Empty steps',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Field "Steps" is mandatory.'
					)
				)
			),
			// Empty name/steps
			array(
				array(
					'expected' => TEST_BAD,
					'add_step' => array(
						array('step' => 'Empty name')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			// Empty name/steps
			array(
				array(
					'expected' => TEST_BAD,
					'add_step' => array(
						array('step' => 'Empty name')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),

			// Name -numbers only
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '1234567890',
					'add_step' => array(
						array('step' => '1234567890')
					)
				)
			),
			// Name -symbols only
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => array(
						array('step' => '!@#$%^&*()_+{}:"|<>?,./')
					)
				)
			),
			// Name-spaces
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '   zabbix  123  ',
					'add_step' => array(
						array('step' => '   zabbix  123  ')
					)
				)
			),
			// Max -64 symbols
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'qwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'add_step' => array(
						array('step' => 'qwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop')
					)
				)
			),
			// Application -numbers
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Application numbers only',
					'new_application' => '1234567890',
					'add_step' => array(
						array('step' => 'Application numbers only')
					)
				)
			),
			// Application -symbols
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Application symbols only',
					'new_application' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => array(
						array('step' => 'Application symbols only')
					)
				)
			),
			// Application -max length
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Application max length',
					'new_application' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => array(
						array('step' => 'Application max length')
					)
				)
			),
			// User/password empty
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'User/password empty',
					'authentication' => 'Basic authentication',
					'add_step' => array(
						array('step' => 'User/password empty')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "User": cannot be empty.',
						'Incorrect value for field "Password": cannot be empty.'
					)
				)
			),
			// User empty
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Password empty',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'add_step' => array(
						array('step' => 'Password empty')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Password": cannot be empty.'
					)
				)
			),
			// Password empty
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'User empty',
					'authentication' => 'Basic authentication',
					'http_password' => 'zabbix',
					'add_step' => array(
						array('step' => 'User empty')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "User": cannot be empty.'
					)
				)
			),
			// Username/password numbers only
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Username/password numbers only',
					'authentication' => 'Basic authentication',
					'http_user' => '12345',
					'http_password' => '67890',
					'add_step' => array(
						array('step' => 'Username/password numbers only')
					)
				)
			),
			// Username/password symbols only
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Username/password symbols only',
					'authentication' => 'Basic authentication',
					'http_user' => '!@#$%^&*()_+{}:"|<>?,./',
					'http_password' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => array(
						array('step' => 'Username/password symbols only')
					)
				)
			),
			// Username/password with spaces
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Username/password with spaces',
					'authentication' => 'Basic authentication',
					'http_user' => '   zabbix  123  ',
					'http_password' => '   zabbix  123  ',
					'add_step' => array(
						array('step' => 'Username/password with spaces')
					)
				)
			),
			// Username/password -64 max allowed
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Username/password max allowed',
					'authentication' => 'Basic authentication',
					'http_user' => 'wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'http_password' => 'wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'add_step' => array(
						array('step' => 'Username/password max allowed')
					)
				)
			),
			// Retries-minus one
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Retries- minus one',
					'retries' => '-1',
					'add_step' => array(
						array('step' => 'Retries- minus one')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "-1" for "Retries" field: must be between 1 and 10.'
					)
				)
			),
			// Retries-zero
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Retries- zero',
					'retries' => '0',
					'add_step' => array(
						array('step' => 'Retries- zero')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "0" for "Retries" field: must be between 1 and 10.'
					)
				)
			),
			// Retries-number
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Retries-one',
					'retries' => '1',
					'add_step' => array(
						array('step' => 'Retries-one')
					)
				)
			),
			// Retries-ten
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Retries- ten',
					'retries' => '10',
					'add_step' => array(
						array('step' => 'Retries- ten')
					)
				)
			),
			// Retries-eleven
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Retries- eleven',
					'retries' => '11',
					'add_step' => array(
						array('step' => 'Retries- eleven')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "11" for "Retries" field: must be between 1 and 10.'
					)
				)
			),
			// Interval-minus one
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Interval- minus one',
					'delay' => '-1',
					'add_step' => array(
						array('step' => 'Interval- minus one')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "-1" for "Update interval (in sec)" field: must be between 1 and 86400.'
					)
				)
			),
			// Interval-zero
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Interval- zero',
					'delay' => '0',
					'add_step' => array(
						array('step' => 'Interval- zero')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "0" for "Update interval (in sec)" field: must be between 1 and 86400.'
					)
				)
			),
			// Interval-number
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Interval-one',
					'delay' => '1',
					'add_step' => array(
						array('step' => 'Interval-one')
					)
				)
			),
			// Interval-86400
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Interval- 86400',
					'delay' => '86400',
					'add_step' => array(
						array('step' => 'Interval- 86400')
					)
				)
			),
			// Interval-86401
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Interval- 86401',
					'delay' => '86401',
					'add_step' => array(
						array('step' => 'Interval- 86401')
					),
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "86401" for "Update interval (in sec)" field: must be between 1 and 86400.'
					)
				)
			),
			// agent-remove from (other ...) then save (will be saved as internet explorer 10.0)
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '(other ...) Remove agent',
					'agent' => '(other ...)',
					'removeAgent' => 'true',
					'add_step' => array(
						array('step' => '(other ...) Remove agent')
					)
				)
			),
			// Http proxy -just numbers
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Http proxy -just numbers',
					'http_proxy' => '1234567890',
					'add_step' => array(
						array('step' => 'Http proxy -just numbers')
					)
				)
			),
			// Http proxy -symbols
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Http proxy -symbols',
					'http_proxy' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => array(
						array('step' => 'Http proxy -symbols')
					)
				)
			),
			// Http proxy -max allowed length
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Http proxy -max allowed length',
					'http_proxy' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => array(
						array('step' => 'Http proxy -max allowed length')
					)
				)
			),
			// Variables -just numbers
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Variables -just numbers',
					'variables' => '1234567890',
					'add_step' => array(
						array('step' => 'Variables -just numbers')
					)
				)
			),
			// Variables -symbols
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Variables -symbols',
					'variables' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => array(
						array('step' => 'Variables -symbols')
					)
				)
			),
			// Variables -255 length-allowed more
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Variables -255 length',
					'http_proxy' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => array(
						array('step' => 'Variables -255 length')
					)
				)
			),
			// Duplicate web scenario
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Duplicate web test',
					'add_step' => array(
						array('step' => 'Duplicate web test')
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'Duplicate web test',
					'add_step' => array(
						array('step' => 'Duplicate web test')
					),
					'errors' => array(
						'ERROR: Cannot add web scenario',
						'Web scenario "Duplicate web test" already exists.'
					)
				)
			),
			// testing created items using triggers
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Trigger create web test',
					'add_step' => array(
						array('step' => 'Trigger create web test')
					),
					'createTriggers' => array(
						'web.test.in[Trigger create web test,,bps]',
						'web.test.fail[Trigger create web test]',
						'web.test.error[Trigger create web test]',
						'web.test.in[Trigger create web test,Trigger create web test step,bps]',
						'web.test.time[Trigger create web test,Trigger create web test step,resp]',
						'web.test.rspcode[Trigger create web test,Trigger create web test step]'
					)
				)
			),
			// testing created items using triggers -multiple steps added
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Trigger create multiple steps web test',
					'add_step' => array(
						array('step' => 'Trigger create multiple steps web test1'),
						array('step' => 'Trigger create multiple steps web test2'),
						array('step' => 'Trigger create multiple steps web test3'),
						array('step' => 'Trigger create multiple steps web test4'),
					),
					'createTriggers' => array(
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
					)
				)
			),
		// many steps added
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Many websteps added web test',
					'add_step' => array(
						array('step' => 'Many websteps added web test1'),
						array('step' => 'Many websteps added web test2'),
						array('step' => 'Many websteps added web test3'),
						array('step' => 'Many websteps added web test4'),
						array('step' => 'Many websteps added web test5'),
						array('step' => 'Many websteps added web test6'),
						array('step' => 'Many websteps added web test7'),
						array('step' => 'Many websteps added web test8'),
						array('step' => 'Many websteps added web test9'),
						array('step' => 'Many websteps added web test10'),
						array('step' => 'Many websteps added web test11'),
						array('step' => 'Many websteps added web test12'),
						array('step' => 'Many websteps added web test13'),
						array('step' => 'Many websteps added web test14'),
						array('step' => 'Many websteps added web test15'),
						array('step' => 'Many websteps added web test16'),
						array('step' => 'Many websteps added web test17'),
						array('step' => 'Many websteps added web test18'),
						array('step' => 'Many websteps added web test19'),
						array('step' => 'Many websteps added web test20'),
						array('step' => 'Many websteps added web test21'),
						array('step' => 'Many websteps added web test22'),
						array('step' => 'Many websteps added web test23'),
						array('step' => 'Many websteps added web test24'),
						array('step' => 'Many websteps added web test25'),
						array('step' => 'Many websteps added web test26'),
						array('step' => 'Many websteps added web test27'),
						array('step' => 'Many websteps added web test28'),
						array('step' => 'Many websteps added web test29'),
						array('step' => 'Many websteps added web test30')
					)
				)
			),
			// List of main agents
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 10.0 None',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'None',
					'add_step' => array(
						array('step' => 'Internet Explorer 10.0 None')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 10.0 Basic',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Internet Explorer 10.0 Basic')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 10.0 NTLM',
					'agent' => 'Internet Explorer 10.0',
					'authentication' => 'NTLM authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Internet Explorer 10.0 NTLM')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Mozilla Firefox 8.0 None',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'None',
					'add_step' => array(
						array('step' => 'Mozilla Firefox 8.0 None')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Mozilla Firefox 8.0 Basic',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Mozilla Firefox 8.0 Basic')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Mozilla Firefox 8.0 NTLM',
					'agent' => 'Mozilla Firefox 8.0',
					'authentication' => 'NTLM authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Mozilla Firefox 8.0 NTLM')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Opera 12.00 None',
					'agent' => 'Opera 12.00',
					'authentication' => 'None',
					'add_step' => array(
						array('step' => 'Opera 12.00 None')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Opera 12.00 Basic',
					'agent' => 'Opera 12.00',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Opera 12.00 Basic')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Opera 12.00 NTLM',
					'agent' => 'Opera 12.00',
					'authentication' => 'NTLM authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Opera 12.00 NTLM')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Safari 5.0 None',
					'agent' => 'Safari 5.0',
					'authentication' => 'None',
					'add_step' => array(
						array('step' => 'Safari 5.0 None')
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Safari 5.0 Basic',
					'agent' => 'Safari 5.0',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Safari 5.0 Basic')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Safari 5.0 NTLM',
					'agent' => 'Safari 5.0',
					'authentication' => 'NTLM authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Safari 5.0 NTLM', 'remove' => true),
						array('step' => 'Safari 5.0 NTLM')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Google Chrome 17 None',
					'agent' => 'Google Chrome 17',
					'authentication' => 'None',
					'add_step' => array(
						array('step' => 'Google Chrome 17 None')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Google Chrome 17 Basic',
					'agent' => 'Google Chrome 17',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Google Chrome 17 Basic')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'Google Chrome 17 NTLM',
					'agent' => 'Google Chrome 17',
					'authentication' => 'NTLM authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => 'Google Chrome 17 NTLM')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '(other ...) None',
					'agent' => '(other ...)',
					'authentication' => 'None',
					'add_step' => array(
						array('step' => '(other ...) None')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '(other ...) Basic',
					'agent' => '(other ...)',
					'authentication' => 'Basic authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => '(other ...) Basic')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => '(other ...) NTLM',
					'agent' => '(other ...)',
					'authentication' => 'NTLM authentication',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => array(
						array('step' => '(other ...) NTLM', 'remove' => true),
						array('step' => '(other ...) NTLM')
					),
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testFormWeb_SimpleCreate($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait('link=Web scenarios');

		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');

		$this->zbxTestClickWait('form');
		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
		$this->zbxTestTextPresent('Scenario');

		if (isset($data['authentication'])) {
			$this->zbxTestDropdownSelectWait('authentication', $data['authentication']);
		}
		$authentication = $this->getSelectedLabel('authentication');

		if (isset($data['http_user'])) {
			$this->input_type('http_user', $data['http_user']);
		}

		if (isset($data['http_password'])) {
			$this->input_type('http_password', $data['http_password']);
		}

		if (isset($data['agent'])) {
			switch ($data['agent']) {
				case '(other ...)':
					$this->zbxTestDropdownSelect('agent', 'Internet Explorer 10.0');
					$this->zbxTestDropdownSelect('agent', $data['agent']);
					$agent = $this->getValue("//div[@class='dd']/input[@name='agent']");
					$agentForm = $agent;
					if (isset($data['removeAgent'])) {
						$this->input_type("//div[@class='dd']/input[@name='agent']", '');
					}
					break;
				default:
					$this->zbxTestDropdownSelect('agent', $data['agent']);
					$agent = $this->getSelectedValue('agent');
					$agentForm = $this->getSelectedLabel('agent');
					break;
			}
		}

		if (isset($data['name'])) {
			$this->input_type('name', $data['name']);
		}
		$name = $this->getValue('name');

		if (isset($data['new_application'])) {
			$this->input_type('new_application', $data['new_application']);
		}
		$new_application = $this->getValue('new_application');

		if (isset($data['delay']))	{
			$this->input_type('delay', $data['delay']);
		}
		$delay = $this->getValue('delay');

		if (isset($data['retries'])) {
			$this->input_type('retries', $data['retries']);
		}
		$retries = $this->getValue('retries');

		if (isset($data['http_proxy'])) {
			$this->input_type('http_proxy', $data['http_proxy']);
		}

		if (isset($data['variables'])) {
			$this->input_type('variables', $data['variables']);
		}

		$check = false;
		if (isset($data['add_step'])) {
			$this->zbxTestClick('tab_stepTab');
			foreach($data['add_step'] as $item) {
				$this->click('add_step');
				$this->waitForPopUp('zbx_popup', 6000);
				$this->selectWindow('zbx_popup');
				$this->zbxTestCheckFatalErrors();
				$step = $item['step']." step";
				$this->input_type('name',$step);
				$url = $step." url";
				$this->input_type('url', $url);
				$this->zbxTestClick('add');
				$this->selectWindow(null);

				if(isset($item['remove'])) {
					$this->wait();
					$this->zbxTestClick('remove_0');
				}
				else {
					$this->zbxTestClickWait('tab_scenarioTab');
					$this->zbxTestClick('tab_stepTab');
				}
			}
		}

		$this->zbxTestClickWait('add');
		$expected = $data['expected'];
		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestTextPresent('Scenario added');
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
				$this->zbxTestTextPresent(array('Number of steps', 'Update interval', 'Status'));
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestTextPresent('CONFIGURATION OF WEB MONITORING');
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextNotPresent('Scenario added');
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
			$this->zbxTestClick("link=$dbName");
			$this->wait();
			$this->assertAttribute("//input[@id='name']/@value", 'exact:'.$name);
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait('link=Web scenarios');
		}

		if (isset($data['createTriggers'])) {
			$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");

			foreach ($data['createTriggers'] as $trigger) {
				$this->zbxTestClickWait('form');

				$this->input_type('description', $trigger);
				$expressionTrigger = '{'.$this->host.':'.$trigger.'.last(0)}=0';
				$this->input_type('expression', $expressionTrigger);
				$this->zbxTestClickWait('add');

				$this->zbxTestTextPresent('Trigger added');
				$this->zbxTestCheckTitle('Configuration of triggers');
				$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
			}
		}

		if (isset($data['remove'])) {
			$this->zbxTestCheckboxSelect("group_httptestid_$httptestid");
			$this->zbxTestDropdownSelect('action', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Web scenario deleted');
			$this->zbxTestTextNotPresent($data['name']);
		}
	}

	public function testFormWeb_restore() {
		DBrestore_tables('httptest');
	}
}
