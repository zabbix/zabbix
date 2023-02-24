<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @dataSource WebScenarios
 *
 * @onBefore getContextData
 *
 * @backup httptest
 */
class testFormWeb extends CWebTest {

	private static $hostid;
	private static $templateid;
	private static $template_name;
	private static $template_scenarioid;

	const TEMPLATE_SCENARIO = 'Template_Web_scenario';

	public static function getContextData() {
		self::$hostid = CDataHelper::get('WebScenarios.hostid');
		self::$templateid = CDataHelper::get('WebScenarios.templateid');
		self::$template_name = CDataHelper::get('WebScenarios.template_name');
		self::$template_scenarioid = CDataHelper::get('WebScenarios.httptestids.'.self::TEMPLATE_SCENARIO);
	}

	// Returns layout data
	public static function layout() {
		return [
			[
				[
					'context' => 'host'
				]
			],
			[
				[
					'scenario_name' => self::TEMPLATE_SCENARIO,
					'context' => 'host'
				]
			],
			[
				[
					'context' => 'template'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormWeb_CheckLayout($data) {
		$context_id = ($data['context'] === 'host') ? self::$hostid : self::$templateid;
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.$context_id)->waitUntilReady();

		$selector = (array_key_exists('scenario_name', $data))
			? 'link:'.$data['scenario_name']
			: 'button:Create web scenario';

		$this->query($selector)->waitUntilClickable()->one()->click();
		$form = $this->query('name:httpForm')->waitUntilVisible()->asForm()->one();

		$this->page->assertHeader('Web monitoring');
		$this->page->assertTitle('Configuration of web monitoring');

		// Check tabs available in the form.
		$this->assertEquals(['Scenario', 'Steps', 'Authentication'], $form->getTabs());

		$scenario_fields = [
			'Name' => ['autofocus' => 'true', 'maxlength' => 64],
			'Application' => [],
			'id:new_application' => ['maxlength' => 255],
			'Update interval' => ['value' => '1m', 'maxlength' => 255],
			'Attempts' => ['value' => 1, 'maxlength' => 2],
			'Agent' => ['value' => 'Zabbix'],
			'id:agent_other' => ['visible' => false, 'enabled' => false, 'maxlength' => 255],
			'HTTP proxy' => ['placeholder' => '[protocol://][user[:password]@]proxy.example.com[:port]', 'maxlength' => 255],
			'xpath:(//table[@data-type="variables"]//input)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(//table[@data-type="variables"]//input)[2]' => ['placeholder' => 'value', 'maxlength' => 2000],
			'xpath:(//table[@data-type="headers"]//input)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(//table[@data-type="headers"]//input)[2]' => ['placeholder' => 'value', 'maxlength' => 2000],
			'Enabled' => ['value' => true]
		];

		// Substitute inherited web scenario speciffic fields and check Parent web scenario field.
		if (array_key_exists('scenario_name', $data)) {
			$scenario_fields['Name'] = ['value' => $data['scenario_name'], 'enabled' => false, 'maxlength' => 64];
			$scenario_fields['Agent'] = ['value' => 'Internet Explorer 10'];

			$parent_field = $form->getField('Parent web scenarios');
			$this->assertTrue($parent_field->isCLickable());

			$this->assertEquals('httpconf.php?form=update&hostid='.self::$templateid.'&httptestid='.self::$template_scenarioid,
					$parent_field->query('link', self::$template_name)->one()->getAttribute('href')
			);
		}

		$this->checkFieldAttributes($form, $scenario_fields);
		$this->assertEquals(['Name', 'Update interval', 'Attempts'], $form->getRequiredLabels());

		$dropdowns = [
			'Agent' => ['Microsoft Edge 80', 'Microsoft Edge 44', 'Internet Explorer 11', 'Internet Explorer 10',
				'Internet Explorer 9', 'Internet Explorer 8', 'Firefox 73 (Windows)', 'Firefox 73 (Linux)',
				'Firefox 73 (macOS)', 'Chrome 80 (Windows)', 'Chrome 80 (Linux)', 'Chrome 80 (macOS)', 'Chrome 80 (iOS)',
				'Chromium 80 (Linux)', 'Opera 67 (Windows)', 'Opera 67 (Linux)', 'Opera 67 (macOS)', 'Safari 13 (macOS)',
				'Safari 13 (iPhone)', 'Safari 13 (iPad)', 'Safari 13 (iPod Touch)', 'Zabbix', 'Lynx 2.8.8rel.2', 'Links 2.8',
				'Googlebot 2.1', 'other ...'
			]
		];

		// The template doesn't have any applications, so a "No applications found" text should be displayed.
		if ($data['context'] === 'template') {
			$this->assertEquals('No applications found.', $form->getField('Application')->getText());
		}
		else {
			$dropdowns['Application'] = ['', 'App 1', 'Заббикс'];
		}

		// Check the dropdown options.
		foreach ($dropdowns as $dropdown => $options) {
			$this->assertEquals($options, $form->getField($dropdown)->getOptions()->asText());
		}

		// Check that "User agent string" field is displayed only when Agent is set to other
		$user_string = $form->getField('User agent string');
		$this->assertFalse($user_string->isDisplayed());
		$form->getField('Agent')->select('other ...');
		$this->assertTrue($user_string->isDisplayed());
		$this->assertTrue($user_string->isEnabled());

		// Check layout of Variables and Headers tables.
		$table_layout = [
			'headers' => ['', 'Name', '', 'Value', '']
		];

		foreach (['Variables' => false, 'Headers' => true] as $table_name => $row_dragable) {
			$table = $form->getField($table_name)->asTable();
			$row = $table->getRow(0);
			$this->assertSame($table_layout['headers'], $table->getHeadersText());

			// Check that Add button is clickable and tha Remove button is not.
			$add_button = $table->query('button:Add')->one();
			$this->assertTrue($add_button->isClickable());
			$remove_button = $row->query('button:Remove')->one();
			$this->assertFalse($remove_button->isClickable());

			// Check the presence of the draggable icon.
			if ($row_dragable) {
				$drag_icon = $row->query('xpath:.//div[contains(@class,"drag-icon")]')->one();
				$this->assertFalse($drag_icon->isEnabled());
			}
			else {
				$this->assertFalse($row->query('xpath:.//div[contains(@class,"drag-icon")]')->one(false)->isValid());
			}

			// Fill in some data in first for and check that Remove buttons and draggable icon became enabled.
			$row->getColumn('Name')->query('xpath:./input')->one()->fill('zabbix');
			$this->assertTrue($remove_button->isClickable());

			// Check that draggable icon becomes enabled when a new row is added.
			if ($row_dragable) {
				$this->assertFalse($drag_icon->isEnabled());
				$add_button->click();
				$this->assertTrue($drag_icon->isEnabled());
			}
		}

		$form->selectTab('Steps');
		$this->assertTrue($form->isRequired('Steps'));
		$steps_table = $form->getField('Steps')->asTable();

		$this->assertEquals(['', '', 'Name', 'Timeout', 'URL', 'Required', 'Status codes', 'Action'],
				$steps_table->getHeadersText()
		);

		if (array_key_exists('scenario_name', $data)) {
			$this->assertFalse($steps_table->query('xpath:.//button')->one(false)->isValid());
		}
		else {
			$this->assertEquals(['Add'], $steps_table->query('xpath:.//button')->all()->asText());
		}


		$form->selectTab('Authentication');

		$authentication_fields = [
			'HTTP authentication' => ['value' => 'None'],
			'User' => ['visible' => false, 'enabled' => false, 'maxlength' => 64],
			'Password' => ['visible' => false, 'enabled' => false, 'maxlength' => 64],
			'SSL verify peer' => ['value' => false],
			'SSL verify host' => ['value' => false],
			'SSL certificate file' => ['maxlength' => 255],
			'SSL key file' => ['maxlength' => 255],
			'SSL key password' => ['maxlength' => 64]
		];

		$this->checkFieldAttributes($form, $authentication_fields);

		$auth_field = $form->getField('HTTP authentication');
		$this->assertEquals(['None', 'Basic', 'NTLM', 'Kerberos'], $auth_field->getOptions()->asText());

		$user_field = $form->getField('User');
		$password_field = $form->getField('Password');

		foreach (['Basic', 'NTLM', 'Kerberos'] as $auth_type) {
			$auth_field->select($auth_type);

			$this->assertTrue($user_field->isDisplayed());
			$this->assertTrue($user_field->isEnabled());
		}

		$expected_buttons = (array_key_exists('scenario_name', $data))
			? ['Update' => true, 'Clone' => true, 'Clear history and trends' => true, 'Delete' => false, 'Cancel' => true]
			: ['Add' => true, 'Cancel' => true];

		$footer_buttons = $form->query('xpath:.//div[contains(@class, "tfoot-buttons")]')->one()->query('xpath:.//button')->all();
		$this->assertEquals(count($expected_buttons), $footer_buttons->count());

		foreach ($footer_buttons as $footer_button) {
			$button_text = $footer_button->getText();
			$this->assertEquals($expected_buttons[$button_text], $footer_button->isClickable());
		}
	}

	private function checkFieldAttributes($form, $fields) {
		foreach ($fields as $field => $attributes) {
			$value = (array_key_exists('value', $attributes)) ? $attributes['value'] : '';
			$visible = (array_key_exists('visible', $attributes)) ? $attributes['visible'] : true;
			$enabled = (array_key_exists('enabled', $attributes)) ? $attributes['enabled'] : true;

			$this->assertEquals($visible, $form->getField($field)->isVisible());
			$this->assertEquals($value, $form->getField($field)->getValue());
			$this->assertTrue($form->getField($field)->isEnabled($enabled));

			foreach (['maxlength', 'placeholder', 'autofocus'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $form->getField($field)->getAttribute($attribute));
				}
			}
		}
	}

	public static function getWebScenarioData() {
		return [
			// Empty name
			[
				[
					'expected' => TEST_BAD,
//					'error_msg' => 'Page received incorrect data',
					'error' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// Empty space in name
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => '   '
					],
					'error' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// Missing steps
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Missing steps'
					],
					'missing_steps' => true,
					'error' => 'Field "Steps" is mandatory.'
				]
			],
			// Netagite update interval
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Netagite update interval',
						'Update interval' => '-1'
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// Zero update interval
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Zero update interval',
						'Update interval' => 0
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// Too big update interval
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big update interval',
						'Update interval' => 86401
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// Too big update interval with suffix
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big update interval with suffix',
						'Update interval' => '1441h'
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// Negative number of retries.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Negative number of retries',
						'Attempts' => '-1'
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Incorrect value "-1" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// Zero retries
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Zero retries',
						'Attempts' => 0
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Incorrect value "0" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// Too high number of retries
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too high number of retries',
						'Attempts' => 11
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Incorrect value "11" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// Non-numeric number of retries
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too high number of retries',
						'Attempts' => 'aa'
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Incorrect value "0" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// Variable without brackets
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Variable name without brackets'
					],
					'variables' => [
						['name' => 'abc']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Variable without opening bracket
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Variable name without opening bracket'
					],
					'variables' => [
						['name' => 'abc}']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Variable without closing bracket
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Variable name without closing bracket'
					],
					'variables' => [
						['name' => '{abc']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Variable with misplaced brackets
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Variable with misplaced brackets'
					],
					'variables' => [
						['name' => '{}abc']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Duplicate variable names
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Duplicate variable names'
					],
					'variables' => [
						['name' => '{abc}', 'value' => '123'],
						['name' => '{abc}', 'value' => '987']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/variables/2": value (name)=({abc}) already exists.'
				]
			],
			// Missing variable name
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Missing variable name'
					],
					'variables' => [
						['name' => ' ', 'value' => '123'],
						['name' => '{abc}', 'value' => '987']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/variables/1/name": cannot be empty.'
				]
			],
			// Headers - empty name
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Missing header name'
					],
					'headers' => [
						['name' => ' ', 'value' => '123'],
						['name' => 'abc', 'value' => '987']
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Invalid parameter "/1/headers/1/name": cannot be empty.'
				]
			],
			// Duplicate web scenario name
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => self::TEMPLATE_SCENARIO
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Web scenario "'.self::TEMPLATE_SCENARIO.'" already exists.'
				]
			],
			// Both Application and New application fields are specified
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Application + New application',
						'Application' => 'App 1',
						'New application' => 'New app'
					],
					'error_title' => 'Cannot add web scenario',
					'errors' => 'Cannot create new application, web scenario is already assigned to application.'
				]
			],
			// Minimal config
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Name' => 'Min required configuration'
					]
				]
			],
			// All possible fields specified
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Name' => 'All fields specified',
						'Application' => 'Заббикс',
						'Update interval' => '6h',
						'Attempts' => 7,
						'Agents' => 'other ...',
						'User agent string' => 'My super puper agent string 良い一日を',
						'HTTP proxy' => '良い一日を',
						'Enabled' => false,
						'HTTP authentication' => 'Basic',
						'User' => '!@#$%^&*()_+=-良い一日を',
						'Password' => '!@#$%^&*()_+=-良い一日を',
						'SSL verify peer' => true,
						'SSL verify host' => true,
						'SSL certificate file' => '!@#$%^&*()_+=-良い一日を',
						'SSL key file' => '!@#$%^&*()_+=-良い一日を',
						'SSL key password' => '!@#$%^&*()_+=-良い一日を'
					],
					'variables' => [
						['name' => '{!@#$%^&*()_+=-良い一日を}', 'value' => '!@#$%^&*()_+=-良い一日を'],
						['name' => '{abc}']
					],
					'headers' => [
						['name' => 'OneTwoThree'],
						['name' => '!@#$%^&*()_+=-良い一日を', 'value' => '!@#$%^&*()_+=-良い一日を']
					]
				]
			],

			// Password empty + add case for user empty
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

			// Max allowed length
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

			// Trim trailing and leading spaces in fields
			[
				[
					'expected' => TEST_GOOD,
					'name' => '   zabbix  123  ',
					'add_step' => [
						['step' => '   zabbix  123  ']
					]
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
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Web scenarios');
			$this->zbxTestCheckHeader('Web monitoring');
			$this->zbxTestTextPresent($name);
		}

		if (isset($data['createTriggers'])) {
			$this->zbxTestClickLinkTextWait('Triggers');

			foreach ($data['createTriggers'] as $trigger) {
				$this->zbxTestContentControlButtonClickTextWait('Create trigger');

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

			$this->zbxTestAcceptAlert();

			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Web scenario deleted');
			$this->assertEquals(0, CDBHelper::getCount("SELECT * FROM httptest test LEFT JOIN httpstep step ON ".
				"step.httptestid = test.httptestid ".
				"WHERE test.name = '".$name."' AND step.name = '".$step."'"));
		}
	}
}
