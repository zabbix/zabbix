<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup httptest, profiles
 */
class testFormWeb extends CLegacyWebTest {

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

	// Returns layout data
	public static function layout() {
		return [
			[
				[
					'agent' => 'Internet Explorer 10',
					'authentication' => 'None',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Internet Explorer 10',
					'authentication' => 'Basic',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'agent' => 'Internet Explorer 10',
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
					'agent' => 'other ...',
					'authentication' => 'Kerberos',
					'host' => 'Simple form test host'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10',
					'authentication' => 'None',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10',
					'authentication' => 'Basic',
					'template' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'agent' => 'Internet Explorer 10',
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

			// If the template is not present on this page anymore - check on next page.
			for ($i = 0; $i < 2; $i++) {
				if ($this->query('link', $data['template'])->one(false)->isValid() === true) {
					break;
				}
				$this->query('xpath://div[@class="table-paging"]//span[@class="arrow-right"]/..')->one()->click();
				$this->page->waitUntilReady();
			}

			$this->zbxTestClickLinkTextWait($data['template']);
			$this->zbxTestClickLinkTextWait('Web scenarios');
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin(self::HOST_LIST_PAGE);
			$this->filterEntriesAndOpenWeb($data['host']);
		}

		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestCheckHeader('Web monitoring');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestContentControlButtonClickTextWait('Create web scenario');
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
		if (isset($data['templatedHost'])) {
			$this->zbxTestAssertAttribute("//input[@id='name']", 'readonly');
		}
		else {
			$this->zbxTestAssertAttribute("//input[@id='name']", 'autofocus');
		}

		$this->zbxTestTextNotPresent(['Application', 'New application']);

		$this->zbxTestTextPresent('Update interval');
		$this->zbxTestAssertVisibleId('delay');
		$this->zbxTestAssertAttribute("//input[@id='delay']", 'maxlength', 255);
		$this->zbxTestAssertElementValue('delay', '1m');

		$this->zbxTestTextPresent('Attempts');
		$this->zbxTestAssertVisibleId('retries');
		$this->zbxTestAssertAttribute("//input[@id='retries']", 'maxlength', 2);
		$this->zbxTestAssertElementValue('retries', 1);

		$this->zbxTestTextPresent('Agent');

		$agents = ['Microsoft Edge 80', 'Microsoft Edge 44', 'Internet Explorer 11', 'Internet Explorer 10',
			'Internet Explorer 9', 'Internet Explorer 8', 'Firefox 73 (Windows)', 'Firefox 73 (Linux)',
			'Firefox 73 (macOS)', 'Chrome 80 (Windows)', 'Chrome 80 (Linux)', 'Chrome 80 (macOS)', 'Chrome 80 (iOS)',
			'Chromium 80 (Linux)', 'Opera 67 (Windows)', 'Opera 67 (Linux)', 'Opera 67 (macOS)', 'Safari 13 (macOS)',
			'Safari 13 (iPhone)', 'Safari 13 (iPad)', 'Safari 13 (iPod Touch)', 'Zabbix', 'Lynx 2.8.8rel.2', 'Links 2.8',
			'Googlebot 2.1', 'other ...'];
		$agent_element = $this->query('id:agent')->asDropdown()->one();
		$this->assertEquals($agent_element->getOptions()->asText(), $agents);

		$agent_groups = ['Internet Explorer', 'Mozilla Firefox', 'Opera', 'Safari', 'Google Chrome', 'Others'];
		foreach ($agent_groups as $group) {
			$this->zbxTestAssertElementPresentXpath("//z-select[@id='agent']//li[@optgroup='$group']");
		}

		if ($agent == 'other ...') {
			$this->zbxTestAssertVisibleId('agent_other');
		}
		else {
			$this->zbxTestAssertNotVisibleId('agent_other');
		}

		$this->zbxTestTextPresent('HTTP proxy');
		$this->zbxTestAssertVisibleId('http_proxy');
		$this->zbxTestAssertAttribute("//input[@id='http_proxy']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='http_proxy']", 'placeholder', '[protocol://][user[:password]@]proxy.example.com[:port]');

		$this->zbxTestTextPresent('Variables');
		$this->zbxTestAssertVisibleXpath("//div[@id='scenarioTab']//table[contains(@data-type, 'variables')]");
		$this->zbxTestAssertAttribute("//table[@data-type='variables']//tr[@data-index='1']//input[@data-type='name']", 'maxlength', 255);
		$this->zbxTestAssertVisibleXpath("//table[@data-type='variables']//tr[@data-index='1']//input[@data-type='value']");

		$this->zbxTestTextPresent('Headers');
		$this->zbxTestAssertVisibleXpath("//div[@id='scenarioTab']//table[contains(@data-type, 'headers')]");
		$this->zbxTestAssertAttribute("//table[@data-type='headers']//tr[@data-index='1']//input[@data-type='name']", 'maxlength', 255);
		$this->zbxTestAssertVisibleXpath("//table[@data-type='headers']//tr[@data-index='1']//input[@data-type='value']");

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertElementPresentId('status');
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

			$this->zbxTestAssertElementPresentXpath("//button[@id='delete'][@disabled]");
		}
		else {
			$this->zbxTestAssertElementPresentId('add');
			$this->zbxTestAssertElementNotPresentId('clone');
			$this->zbxTestAssertElementNotPresentId('delete');
			$this->zbxTestAssertElementNotPresentId('update');
		}

		$this->zbxTestTabSwitchById('tab_authenticationTab', 'Authentication');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('authentication'));

		$this->zbxTestTextPresent('Authentication');
		$this->zbxTestDropdownHasOptions('authentication', ['None', 'Basic', 'NTLM', 'Kerberos', 'Digest']);

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

			$this->zbxTestTextPresent('Password');
			$this->zbxTestAssertVisibleId('http_password');
			$this->zbxTestAssertAttribute("//input[@id='http_password']", 'maxlength', 64);
		}
		else {
			$this->zbxTestTextNotVisible(['User', 'Password'], $this->query('id:authenticationTab')->one());
			$this->zbxTestAssertNotVisibleId('http_user');
			$this->zbxTestAssertNotVisibleId('http_password');
		}

		$this->zbxTestTabSwitchById('tab_stepTab' ,'Steps');
		$this->zbxTestTextPresent(['Steps', 'Name', 'Timeout', 'URL', 'Required' ,'Status codes', 'Action']);

		if (isset($data['form']) && !isset($data['templatedHost'])) {
			$this->zbxTestAssertVisibleXpath("//td[@colspan='8']/button[contains(@class, 'element-table-add')]");
			$this->zbxTestAssertElementText("//td[@colspan='8']/button[contains(@class, 'element-table-add')]", 'Add');

			$this->zbxTestAssertVisibleXpath("//table[contains(@class,'httpconf-steps-dynamic-row')]//button[contains(@class,'element-table-remove')]");
			$this->zbxTestAssertElementText("//table[contains(@class,'httpconf-steps-dynamic-row')]//button[contains(@class,'element-table-remove')]", 'Remove');
		}
		elseif (!isset($data['form'])) {
			$this->zbxTestAssertVisibleXpath("//td[@colspan='8']/button[contains(@class, 'element-table-add')]");
			$this->zbxTestAssertElementText("//td[@colspan='8']/button[contains(@class, 'element-table-add')]", 'Add');

			$this->zbxTestAssertElementNotPresentXpath("//table[contains(@class,'httpconf-steps-dynamic-row')]//button[contains(@class,'element-table-remove')]");
		}
		else {
			$this->zbxTestAssertElementNotPresentXpath("//td[@colspan='8']/button[contains(@class, 'element-table-add')]");
			$this->zbxTestAssertElementNotPresentXpath("//table[contains(@class,'httpconf-steps-dynamic-row')]//button[contains(@class,'element-table-remove')]");
		}
	}

	// Returns update data
	public static function update() {
		return CDBHelper::getDataProvider("select * from httptest where hostid = 40001 and name LIKE 'testFormWeb%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormWeb_SimpleUpdate($data) {
		$name = $data['name'];

		$sqlItems = "select * from items ORDER BY itemid";
		$oldHashItems = CDBHelper::getHash($sqlItems);

		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenWeb($this->host);
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent('Web scenario updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestCheckTitle('Configuration of web monitoring');

		$this->assertEquals($oldHashItems, CDBHelper::getHash($sqlItems));
	}

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
			// Name - numbers only
			[
				[
					'expected' => TEST_GOOD,
					'name' => '1234567890',
					'add_step' => [
						['step' => '1234567890']
					]
				]
			],
			// Name - symbols only
			[
				[
					'expected' => TEST_GOOD,
					'name' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => '!@#$%^&*()_+{}:"|<>?,./']
					]
				]
			],
			// Name- spaces
			[
				[
					'expected' => TEST_GOOD,
					'name' => '   zabbix  123  ',
					'add_step' => [
						['step' => '   zabbix  123  ']
					]
				]
			],
			// Max - 64 symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'qwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop1234',
					'add_step' => [
						['step' => 'qwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop']
					]
				]
			],
			// User/password empty
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'User/password empty',
					'authentication' => 'Basic',
					'add_step' => [
						['step' => 'User/password empty']
					]
				]
			],
			// User empty
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Password empty',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'add_step' => [
						['step' => 'Password empty']
					]
				]
			],
			// Password empty
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'User empty',
					'authentication' => 'Basic',
					'http_password' => 'zabbix',
					'add_step' => [
						['step' => 'User empty']
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
			// Username/password - 64 max allowed
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
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/delay": value must be one of 1-86400.'
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
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/delay": value must be one of 1-86400.'
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
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/delay": value must be one of 1-86400.'
					]
				]
			],
			// Http proxy - just numbers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Http proxy - just numbers',
					'http_proxy' => '1234567890',
					'add_step' => [
						['step' => 'Http proxy - just numbers']
					]
				]
			],
			// Http proxy - symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Http proxy - symbols',
					'http_proxy' => '!@#$%^&*()_+{}:"|<>?,./',
					'add_step' => [
						['step' => 'Http proxy - symbols']
					]
				]
			],
			// Http proxy - max allowed length
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Http proxy - max allowed length',
					'http_proxy' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
						'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
						'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
						'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
						'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012345',
					'add_step' => [
						['step' => 'Http proxy - max allowed length']
					]
				]
			],
			// Variables - just numbers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables - just numbers',
					'variables' => [
						['name' => '{1234567890}']
					],
					'add_step' => [
						['step' => 'Variables - just numbers']
					]
				]
			],
			// Variables - symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables - symbols',
					'variables' => [
						['name' => '{!@#$%^&*()_+:"|<>?,./}']
					],
					'add_step' => [
						['step' => 'Variables - symbols']
					]
				]
			],
			// Variables - 255 max allowed
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables - 255 length',
					'variables' => [
						['name' => '{qwertyuiopqwertyuiopqwertyuiopqwertyui'.
							'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
							'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
							'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
							'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop123456789012}']
					],
					'add_step' => [
						['step' => 'Variables - 255 length']
					]
				]
			],
			// Variables - without {}
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Variables - without {}',
					'variables' => [
						['name' => 'test']
					],
					'add_step' => [
						['step' => 'Variables - without {}']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
					]
				]
			],
			// Variables - without {}
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Variables - without {}',
					'variables' => [
						['name' => '{test']
					],
					'add_step' => [
						['step' => 'Variables - without {}']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
					]
				]
			],
			// Variables - without {}
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Variables - without {}',
					'variables' => [
						['name' => 'test}']
					],
					'add_step' => [
						['step' => 'Variables - without {}']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
					]
				]
			],
			// Variables - with the same names
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Variables - with the same names',
					'variables' => [
						['name' => '{test}'],
						['name' => '{test}']
					],
					'add_step' => [
						['step' => 'Variables - with the same names']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/variables/2": value (name)=({test}) already exists.'
					]
				]
			],
			// Variables - two different
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Variables - two different',
					'variables' => [
						['name' => '{test1}', 'value' => 'test1'],
						['name' => '{test2}', 'value' => 'test1']
					],
					'add_step' => [
						['step' => 'Variables - two different']
					]
				]
			],
			// Variables - empty name
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Variables - two different',
					'variables' => [
						['value' => 'test']
					],
					'add_step' => [
						['step' => 'Variables - two different']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/variables/1/name": cannot be empty.'
					]
				]
			],
			// Headers - just numbers
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Headers - just numbers',
					'headers' => [
						['name' => '1234567890', 'value' => '123456']
					],
					'add_step' => [
						['step' => 'Headers - just numbers']
					]
				]
			],
			// Headers - just symbols
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Headers - just symbols',
					'headers' => [
						['name' => '!@#$%^&*()_+:"{}|<>?,./', 'value' => '!@#$%^&*()_+:"{}|<>?,./']
					],
					'add_step' => [
						['step' => 'Headers - just symbols']
					]
				]
			],
			// Headers - 255 length
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Headers - 255 length',
					'headers' => [
						['name' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
							'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
							'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
							'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
							'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop12345678901234',
							'value' => 'qwertyuiopqwertyuiopqwertyuiopqwertyui'.
							'opqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwe.'.
							'rtyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqw'.
							'ertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwer'.
							'tyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiop12345678901234']
					],
					'add_step' => [
						['step' => 'Headers - 255 length']
					]
				]
			],
			// Headers - two different
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Headers - two different',
					'headers' => [
						['name' => 'test', 'value' => 'test_value'],
						['name' => 'test', 'value' => 'test_value']
					],
					'add_step' => [
						['step' => 'Headers - two different']
					]
				]
			],
			// Headers - empty value
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Headers - empty value',
					'headers' => [
						['name' => 'test']
					],
					'add_step' => [
						['step' => 'Headers - empty value']
					]
				]
			],
			// Headers - empty name
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Headers - empty name',
					'headers' => [
						['value' => 'test']
					],
					'add_step' => [
						['step' => 'Headers - empty name']
					],
					'error_msg' => 'Cannot add web scenario',
					'errors' => [
						'Invalid parameter "/1/headers/1/name": cannot be empty.'
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
			// testing created items using triggers - multiple steps added
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Trigger create multiple steps web test',
					'add_step' => [
						['step' => 'Trigger create multiple steps web test1'],
						['step' => 'Trigger create multiple steps web test2']
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
						'web.test.rspcode[Trigger create multiple steps web test,Trigger create multiple steps web test2 step]'
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
						['step' => 'Many websteps added web test15']
					]
				]
			],
			// List of main agents
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 11 None',
					'agent' => 'Internet Explorer 11',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Internet Explorer 11 None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 11 Basic',
					'agent' => 'Internet Explorer 11',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Internet Explorer 11 Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Internet Explorer 11 NTLM',
					'agent' => 'Internet Explorer 11',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Internet Explorer 11 NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Firefox 73 (Windows) None',
					'agent' => 'Firefox 73 (Windows)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Firefox 73 (Windows) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Firefox 73 (Windows) Basic',
					'agent' => 'Firefox 73 (Windows)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Firefox 73 (Windows) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Firefox 73 (Windows) 8.0 NTLM',
					'agent' => 'Firefox 73 (Windows)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Firefox 73 (Windows) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Opera 67 (Windows) None',
					'agent' => 'Opera 67 (Windows)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Opera 67 (Windows) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Opera 67 (Windows) Basic',
					'agent' => 'Opera 67 (Windows)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Opera 67 (Windows) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Opera 67 (Windows) NTLM',
					'agent' => 'Opera 67 (Windows)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Opera 67 (Windows) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Safari 13 (macOS)',
					'agent' => 'Safari 13 (macOS)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Safari 13 (macOS) None']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Safari 13 (macOS) Basic',
					'agent' => 'Safari 13 (macOS)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Safari 13 (macOS) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Safari 13 (macOS) NTLM',
					'agent' => 'Safari 13 (macOS)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Safari 13 (macOS) NTLM', 'remove' => true],
						['step' => 'Safari 13 (macOS) NTLM']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Chrome 80 (Windows) None',
					'agent' => 'Chrome 80 (Windows)',
					'authentication' => 'None',
					'add_step' => [
						['step' => 'Chrome 80 (Windows) None']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Chrome 80 (Windows) Basic',
					'agent' => 'Chrome 80 (Windows)',
					'authentication' => 'Basic',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Chrome 80 (Windows) Basic']
					],
					'dbCheck' => true,
					'formCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Chrome 80 (Windows) NTLM',
					'agent' => 'Chrome 80 (Windows)',
					'authentication' => 'NTLM',
					'http_user' => 'zabbix',
					'http_password' => 'zabbix123',
					'add_step' => [
						['step' => 'Chrome 80 (Windows) NTLM']
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
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Kerberos empty fields',
					'authentication' => 'Kerberos',
					'add_step' => [
						['step' => 'Kerberos1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Kerberos user-password',
					'authentication' => 'Kerberos',
					'http_user' => 'k_user',
					'http_password' => 'zabbix_k2',
					'add_step' => [
						['step' => 'Kerberos2']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormWeb_SimpleCreate($data) {
		$this->zbxTestLogin(self::HOST_LIST_PAGE);
		$this->filterEntriesAndOpenWeb($this->host);

		$this->zbxTestCheckTitle('Configuration of web monitoring');
		$this->zbxTestContentControlButtonClickTextWait('Create web scenario');
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
					$agent = $this->zbxTestGetValue('//z-select[@id="agent"]');
					break;
			}
		}

		if (isset($data['name'])) {
			$this->zbxTestInputTypeWait('name', $data['name']);
		}
		$name = $this->zbxTestGetValue("//input[@id='name']");

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
			$i = 1;
			foreach($data['variables'] as $variable) {
				if (isset($variable['name'])) {
					$this->zbxTestInputTypeByXpath('//table[@data-type="variables"]//tr[@data-index="'.$i.'"]//input[@data-type="name"]', $variable['name']);
				}
				if (isset($variable['value'])) {
					$this->zbxTestInputTypeByXpath('//table[@data-type="variables"]//tr[@data-index="'.$i.'"]//input[@data-type="value"]', $variable['value']);
				}
				$this->zbxTestClickXpath('//table[@data-type="variables"]//button[contains(@class, "element-table-add")]');
				$i++;
			}
		}

		if (isset($data['headers'])) {
			$i = 1;
			foreach($data['headers'] as $header) {
				if (isset($header['name'])) {
					$this->zbxTestInputTypeByXpath('//table[@data-type="headers"]//tr[@data-index="'.$i.'"]//input[@data-type="name"]', $header['name']);
				}
				if (isset($header['value'])) {
					$this->zbxTestInputTypeByXpath('//table[@data-type="headers"]//tr[@data-index="'.$i.'"]//input[@data-type="value"]', $header['value']);
				}
				$this->zbxTestClickXpath('//table[@data-type="headers"]//button[contains(@class, "element-table-add")]');
				$i++;
			}
		}

		$this->zbxTestTabSwitchById('tab_authenticationTab', 'Authentication');
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
			$this->zbxTestTabSwitchById('tab_stepTab' ,'Steps');
			foreach($data['add_step'] as $item) {
				$this->zbxTestClickXpathWait('//td[@colspan="8"]/button[contains(@class, "element-table-add")]');
				$this->zbxTestLaunchOverlayDialog('Step of web scenario');
				$step = $item['step'].' step';
				$this->zbxTestInputTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@id="step_name"]', $step, false);
				$url = $step.' url';
				$this->zbxTestInputTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@id="url"]', $url);
				$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');
				$this->zbxTestWaitForPageToLoad();
				COverlayDialogElement::ensureNotPresent();

				if (isset($item['remove'])) {
					$this->zbxTestClickXpathWait('//table[contains(@class, "httpconf-steps-dynamic-row")]//button[contains(@class,"element-table-remove")]');
				}
			}
		}

		$this->zbxTestClickWait('add');
		$expected = $data['expected'];
		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Web scenario added');
				$this->zbxTestCheckTitle('Configuration of web monitoring');
				$this->zbxTestTextPresent(['Number of steps', 'Interval', 'Status']);
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
				$this->zbxTestTabSwitchById('tab_stepTab' ,'Steps');
				foreach($data['add_step'] as $item) {
					$step = $item['step']." step";
					$this->zbxTestTextPresent($step);
				}
			}
			$this->zbxTestClickLinkTextWait('Web scenarios');
			$this->zbxTestCheckHeader('Web monitoring');
			$this->zbxTestTextPresent($name);
		}

		if (isset($data['createTriggers'])) {
			$this->zbxTestClickLinkTextWait('Triggers');

			foreach ($data['createTriggers'] as $trigger) {
				$this->zbxTestContentControlButtonClickTextWait('Create trigger');

				$this->zbxTestInputType('description', $trigger);
				$expressionTrigger = 'last(/'.$this->host.'/'.$trigger.')=0';
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

			$this->zbxTestAcceptAlert();

			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Web scenario deleted');
			$this->assertEquals(0, CDBHelper::getCount("SELECT * FROM httptest test LEFT JOIN httpstep step ON ".
				"step.httptestid = test.httptestid ".
				"WHERE test.name = '".$name."' AND step.name = '".$step."'"));
		}
	}

	/**
	 * Function for filtering necessary hosts and opening their Web scenarios.
	 *
	 * @param string    $host    name of a host where web scenarios are opened
	 */
	private function filterEntriesAndOpenWeb($host) {
		$this->query('button:Reset')->one()->click();
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilReady()->one();
		$form->fill(['Name' => $host]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $host)
				->getColumn('Web')->query('link:Web')->one()->click();
	}
}
