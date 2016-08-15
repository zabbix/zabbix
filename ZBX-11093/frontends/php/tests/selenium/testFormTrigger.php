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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormTrigger extends CWebTest {

	/**
	 * The name of the Simple form test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	// Returns layout data
	public static function layout() {
		return [
			[
				['constructor' => 'open', 'host' => 'Simple form test host'
				]
			],
			[
				['constructor' => 'open_close', 'host' => 'Simple form test host'
				]
			],
			[
				['constructor' => 'open', 'severity' => 'Warning', 'host' => 'Simple form test host'
				]
			],
			[
				['constructor' => 'open_close', 'severity' => 'Disaster', 'host' => 'Simple form test host'
				]
			],
			[
				['severity' => 'Not classified', 'host' => 'Simple form test host'
				]
			],
			[
				['severity' => 'Information', 'host' => 'Simple form test host'
				]
			],
			[
				['severity' => 'Warning', 'host' => 'Simple form test host'
				]
			],
			[
				['severity' => 'Average', 'host' => 'Simple form test host'
				]
			],
			[
				['severity' => 'High', 'host' => 'Simple form test host'
				]
			],
			[
				['severity' => 'Disaster', 'host' => 'Simple form test host'
				]
			],
			[
				['constructor' => 'open', 'template' => 'Inheritance test template'
				]
			],
			[
				['constructor' => 'open_close', 'template' => 'Inheritance test template'
				]
			],
			[
				['constructor' => 'open', 'severity' => 'Warning', 'template' => 'Inheritance test template'
				]
			],
			[
				[
					'constructor' => 'open_close',
					'severity' => 'Disaster',
					'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Not classified', 'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Information', 'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Warning', 'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Average', 'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'High', 'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Disaster', 'template' => 'Inheritance test template'
				]
			],
			[
				['host' => 'Simple form test host', 'form' => 'testFormTrigger1'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTrigger1'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger2',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTrigger1',
					'constructor' => 'open'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTrigger1',
					'constructor' => 'open'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger1',
					'templatedHost' => true,
					'constructor' => 'open'
				]
			]
		];
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormTrigger_Setup() {
		DBsave_tables('triggers');
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormTrigger_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickLinkTextWait($data['template']);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickLinkTextWait($data['host']);
		}

		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Triggers']");
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}
		$this->zbxTestCheckTitle('Configuration of triggers');

		if (isset($data['constructor'])) {
			switch ($data['constructor']) {
				case 'open':
					$this->zbxTestClickButtonText('Expression constructor');
					break;
				case 'open_close':
					$this->zbxTestClickButtonText('Expression constructor');
					$this->zbxTestClickButtonText('Close expression constructor');
					break;
			}
		}

		$this->zbxTestTextPresent('Trigger');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent triggers');
			if (isset($data['hostTemplate'])) {
				$this->zbxTestAssertElementPresentXpath("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent triggers');
		}

		$this->zbxTestTextPresent('Name');
		$this->zbxTestAssertVisibleId('description');
		$this->zbxTestAssertAttribute("//input[@id='description']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='description']", 'size', 20);

		if (!isset($data['constructor']) || $data['constructor'] == 'open_close') {
			$this->zbxTestTextPresent(['Expression', 'Expression constructor']);
			$this->zbxTestAssertVisibleXpath("//textarea[@id='expression']");
			$this->zbxTestAssertAttribute("//textarea[@id='expression']", 'rows', 7);
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//textarea[@id='expression']", 'readonly');
			}

			$this->zbxTestAssertVisibleId('insert');
			$this->zbxTestAssertElementText("//button[@id='insert']", 'Add');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//button[@id='insert']", 'disabled');
			}

			$this->zbxTestAssertElementNotPresentId('add_expression');
			$this->zbxTestAssertElementNotPresentId('insert_macro');
			$this->zbxTestAssertElementNotPresentId('exp_list');
		}
		else {
			$this->zbxTestTextPresent('Expression');
			$this->zbxTestAssertVisibleId('expr_temp');
			$this->zbxTestAssertAttribute("//textarea[@id='expr_temp']", 'rows', 7);
			$this->zbxTestAssertAttribute("//textarea[@id='expr_temp']", 'readonly');
			$this->zbxTestTextPresent('Close expression constructor');
			$this->zbxTestAssertNotVisibleId('expression');

			if (!isset($data['form'])) {
				$this->zbxTestAssertVisibleId('add_expression');
				$this->zbxTestAssertElementValue('add_expression', 'Add');
			}
			else {
				$this->zbxTestAssertElementNotPresentId('add_expression');
			}

			$this->zbxTestAssertVisibleId('insert');
			$this->zbxTestAssertElementText("//button[@id='insert']", 'Edit');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertElementPresentXpath("//button[@id='insert'][@disabled]");
			}

			$this->zbxTestAssertVisibleId('insert_macro');
			$this->zbxTestAssertElementText("//button[@id='insert_macro']", 'Insert expression');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertElementPresentXpath("//button[@id='insert_macro'][@disabled]");
			}

			if (!isset($data['templatedHost'])) {
				$this->zbxTestTextPresent(['Target', 'Expression', 'Action', 'Info', 'Close expression constructor']);
			}
			else {
				$this->zbxTestTextPresent(['Expression', 'Info', 'Close expression constructor']);
			}
			$this->zbxTestAssertVisibleId('exp_list');
			$this->zbxTestTextPresent('Close expression constructor');
		}

		$this->zbxTestTextPresent(['OK event generation', 'PROBLEM event generation mode']);
		$this->zbxTestTextPresent(['Expression', 'Recovery expression', 'None']);
		$this->zbxTestTextPresent(['Single', 'Multiple']);
		if (!isset($data['templatedHost'])) {
			$this->assertTrue($this->zbxTestCheckboxSelected('type_0'));
		}

		$this->zbxTestTextPresent('Description');
		$this->zbxTestAssertVisibleId('comments');
		$this->zbxTestAssertAttribute("//textarea[@id='comments']", 'rows', 7);

		$this->zbxTestTextPresent('URL');
		$this->zbxTestAssertVisibleId('url');
		$this->zbxTestAssertAttribute("//input[@id='url']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@id='url']", 'size', 20);

		$this->zbxTestAssertVisibleId('priority_0');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_0'));
		$this->zbxTestAssertElementText("//*[@id='priority_0']/../label", 'Not classified');
		$this->zbxTestAssertVisibleId('priority_1');
		$this->zbxTestAssertElementText("//*[@id='priority_1']/../label", 'Information');
		$this->zbxTestAssertVisibleId('priority_2');
		$this->zbxTestAssertElementText("//*[@id='priority_2']/../label", 'Warning');
		$this->zbxTestAssertVisibleId('priority_3');
		$this->zbxTestAssertElementText("//*[@id='priority_3']/../label", 'Average');
		$this->zbxTestAssertVisibleId('priority_4');
		$this->zbxTestAssertElementText("//*[@id='priority_4']/../label", 'High');
		$this->zbxTestAssertVisibleId('priority_5');
		$this->zbxTestAssertElementText("//*[@id='priority_5']/../label", 'Disaster');

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClickXpathWait("//*[@id='priority_0']/../label");
					break;
				case 'Information':
					$this->zbxTestClickXpathWait("//*[@id='priority_1']/../label");
					break;
				case 'Warning':
					$this->zbxTestClickXpathWait("//*[@id='priority_2']/../label");
					break;
				case 'Average':
					$this->zbxTestClickXpathWait("//*[@id='priority_3']/../label");
					break;
				case 'High':
					$this->zbxTestClickXpathWait("//*[@id='priority_4']/../label");
					break;
				case 'Disaster':
					$this->zbxTestClickXpathWait("//*[@id='priority_5']/../label");
					break;
			}
		}

		$this->zbxTestTextPresent('Enabled');
		$this->zbxTestAssertVisibleId('status');
		$this->zbxTestAssertAttribute("//input[@id='status']", 'type', 'checkbox');

		$this->zbxTestAssertVisibleId('cancel');
		$this->zbxTestAssertElementText("//button[@id='cancel']", 'Cancel');

		if (isset($data['form'])) {
			$this->zbxTestAssertVisibleId('update');
			$this->zbxTestAssertElementValue('update', 'Update');

			$this->zbxTestAssertVisibleId('clone');
			$this->zbxTestAssertElementText("//button[@id='clone']", 'Clone');

			$this->zbxTestAssertVisibleId('delete');
			$this->zbxTestAssertElementValue('delete', 'Delete');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertElementPresentXpath("//button[@id='delete'][@disabled]");
				$this->assertTrue($this->zbxTestCheckboxSelected('recovery_mode_0'));
				$this->zbxTestAssertElementPresentXpath("//input[@id='recovery_mode_0'][@disabled]");
			}
		}
		else {
			$this->zbxTestAssertElementNotPresentId('clone');
			$this->zbxTestAssertElementNotPresentId('update');
			$this->zbxTestAssertElementNotPresentId('delete');
		}

		$this->zbxTestTabSwitch('Dependencies');
		$this->zbxTestTextPresent(['Dependencies', 'Name', 'Action']);
		$this->zbxTestAssertElementPresentId('bnt1');
		$this->zbxTestAssertElementText("//button[@id='bnt1']", 'Add');
	}

	// Returns update data
	public static function update() {
		return DBdata("select description from triggers t left join functions f on f.triggerid=t.triggerid where f.itemid='30004' and t.description LIKE 'testFormTrigger%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormTrigger_SimpleUpdate($data) {
		$sqlTriggers = 'select * from triggers order by triggerid';
		$sqlFunctions = 'select * from functions order by functionid';

		$oldHashTriggers = DBhash($sqlTriggers);
		$oldHashFunctions = DBhash($sqlFunctions);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Triggers']");
		$this->zbxTestClickLinkTextWait($data['description']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger updated');
		$this->zbxTestTextPresent($data['description']);
		$this->zbxTestCheckHeader('Triggers');

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
		$this->assertEquals($oldHashFunctions, DBhash($sqlFunctions));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.',
						'Incorrect value for field "Expression": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Expression": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'expression' => '6 & 0 | 0',
					'error_msg' => 'Page received incorrect data',
					'errors' => [
						'Incorrect value for field "Name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '6 and 0 or 0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Trigger expression must contain at least one host:key reference.',
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host}',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect trigger expression. Check expression part starting from "{Simple form test host}".'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_simple',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'HTML_symbols&#8704;&forall;&#8734;&ne;&sup;&Eta;&#937;&#958;&pi;&#8194;&mdash;&#8364;&loz;',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'ASCII_characters&#33;&#40;&#51;&#101;&#10;&#25;',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_allFields',
					'type' => true,
					'comments' => 'MyTrigger_allFields -Description textbox for comments',
					'url' => 'MyTrigger_allFields -URL field for link',
					'severity' => 'Disaster',
					'status' => false,
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '1234567890',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '0',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'a?aa+',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '}aa]a{',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '-aaa=%',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa,;:',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa><.',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa*&_',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa#@!',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '([)$^',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new value, if this value is part of this expression. If time based functions are used in the expression, it is recalculated every 30 seconds by a zabbix timer process.',
					'url' => 'www.zabbix.com',
					'severity' => 'High',
					'status' => false
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:test-item-reuse.last(0)}<0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect trigger expression. Host "Zabbix host" does not exist or you have no access to this host.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect item key "someItem.uptime" provided for trigger expression on "Simple form test host".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.somefunc(0)}<0',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect trigger function "somefunc(0)" provided in expression. Unknown function.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.last(0)} or {#MACRO}',
					'error_msg' => 'Cannot add trigger',
					'errors' => [
						'Incorrect trigger expression. Check expression part starting from " {#MACRO}".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.last(0)} or {#MACRO}',
					'constructor' => [[
						'text' => ['A or B', 'A', 'B'],
						'elements' => ['expr_0_46', 'expr_51_58']
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:test-item-reuse.last(0)}<0 or 8 and 9',
					'constructor' => [[
						'text' => ['A or (B and C)', 'Or', 'And', 'A', 'B', 'C'],
						'elements' => ['expr_0_38', 'expr_43_43', 'expr_49_49'],
						'elementError' => true
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0 or 8 and 9 + {Simple form test host:test-item-reuse.last(0)}',
					'constructor' => [[
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_48', 'expr_53_53', 'expr_59_109'],
						'elementError' => true
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.lasta(0)}<0 or 8 and 9 + {Simple form test host:test-item-reuse.last(0)}',
					'constructor' => [[
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_49', 'expr_54_54', 'expr_60_110'],
						'elementError' => true
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host@:test-item-reuse.last(0)}',
					'constructor' => [[
						'errors' => [
							'Expression syntax error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host@:test-item-reuse.last(0)}".'],
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:system .uptime.last(0)}',
					'constructor' => [[
						'errors' => [
							'Expression syntax error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:system .uptime.last(0)}".'],
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:system .uptime.last(0)}',
					'constructor' => [[
						'errors' => [
							'Expression syntax error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:system .uptime.last(0)}".'],
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.lastA(0)}',
					'constructor' => [[
						'errors' => [
							'Expression syntax error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:test-item-reuse.lastA(0)}".'],
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTrigger_SimpleCreate($data) {

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickXpathWait("//ul[@class='object-group']//a[text()='Triggers']");
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestClickWait('form');
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');

		if (isset($data['description'])) {
			$this->zbxTestInputTypeWait('description', $data['description']);
		}
		$description = $this->zbxTestGetValue("//input[@id='description']");

		if (isset($data['expression'])) {
			$this->zbxTestInputType('expression', $data['expression']);
		}
		$expression = $this->zbxTestGetValue("//textarea[@id='expression']");

		if (isset($data['type'])) {
			$this->zbxTestClickXpathWait("//label[@for='type_1']");
			$type = 'checked';
		}
		else {
			$type = 'unchecked';
		}


		if (isset($data['comments'])) {
			$this->zbxTestInputType('comments', $data['comments']);
		}
		$comments = $this->zbxTestGetValue("//textarea[@id='comments']");

		if (isset($data['url'])) {
			$this->zbxTestInputType('url', $data['url']);
		}
		$url = $this->zbxTestGetValue("//input[@id='url']");

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClickXpathWait("//*[@id='priority_0']/../label");
					break;
				case 'Information':
					$this->zbxTestClickXpathWait("//*[@id='priority_1']/../label");
					break;
				case 'Warning':
					$this->zbxTestClickXpathWait("//*[@id='priority_2']/../label");
					break;
				case 'Average':
					$this->zbxTestClickXpathWait("//*[@id='priority_3']/../label");
					break;
				case 'High':
					$this->zbxTestClickXpathWait("//*[@id='priority_4']/../label");
					break;
				case 'Disaster':
					$this->zbxTestClickXpathWait("//*[@id='priority_5']/../label");
					break;
			}
			$severity = $data['severity'];
		}
		else {
			$severity = 'Not classified';
		}

		if (isset($data['status'])) {
			$this->zbxTestCheckboxSelect('status', false);
			$status = 'unchecked';
		}
		else {
			$status = 'checked';
		}

		if (isset($data['constructor'])) {
			$this->zbxTestClickButtonText('Expression constructor');

			foreach($data['constructor'] as $constructor) {
				if (isset($constructor['errors'])) {
					foreach($constructor['errors'] as $err) {
						$this->zbxTestWaitUntilElementVisible(WebDriverBy::className('msg-bad'));
						$this->zbxTestTextPresent($err);
					}
				}
				else {
					$this->zbxTestAssertElementValue('and_expression', 'And');

					$this->zbxTestAssertElementValue('or_expression', 'Or');

					$this->zbxTestAssertElementValue('replace_expression', 'Replace');
					if (isset($constructor['text'])) {
						foreach($constructor['text'] as $txt) {
							$this->zbxTestTextPresent($txt);
						}
					}
					if (isset($constructor['elements'])) {
						foreach($constructor['elements'] as $elem) {
							$this->zbxTestAssertElementPresentId($elem);
						}
					}
					if (isset($constructor['elementError'])) {
						$this->zbxTestAssertElementPresentXpath('//table[@id="exp_list"]//span[@class="status-red cursor-pointer"]');
					}
					else {
						$this->zbxTestAssertElementNotPresentXpath('//table[@id="exp_list"]//span[@class="status-red cursor-pointer"]');
					}
				}
			}
		}

		if (!isset($data['constructor'])) {
			$this->zbxTestClickWait('add');
			switch ($data['expected']) {
				case TEST_GOOD:
					$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'Trigger added');
					$this->zbxTestCheckTitle('Configuration of triggers');
					$this->zbxTestAssertElementText("//tbody//a[text()='$description']", $description);
					$this->zbxTestAssertElementText("//a[text()='$description']/ancestor::tr/td[4]", $expression);
					break;
				case TEST_BAD:
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
					$this->zbxTestCheckTitle('Configuration of triggers');
					foreach ($data['errors'] as $msg) {
						$msg = str_replace('<', '&lt;', $msg);
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent('Name');
					$this->zbxTestTextPresent('Expression');
					$this->zbxTestTextPresent('Description');
					break;
			}

			if (isset($data['formCheck'])) {
				$this->zbxTestClickLinkTextWait($description);
				$this->zbxTestAssertElementValue('description', $description);
				$this->zbxTestAssertElementValue('expression', $expression);

				if ($type == 'checked') {
					$this->assertTrue($this->zbxTestCheckboxSelected('type_1'));
				}
				else {
					$this->assertTrue($this->zbxTestCheckboxSelected('type_0'));
				}

				$this->zbxTestAssertElementValue('comments', $comments);

				$this->zbxTestAssertElementValue('url', $url);

				switch ($severity) {
					case 'Not classified':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_0'));
						break;
					case 'Information':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_1'));
						break;
					case 'Warning':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_2'));
						break;
					case 'Average':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_3'));
						break;
					case 'High':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_4'));
						break;
					case 'Disaster':
						$this->assertTrue($this->zbxTestCheckboxSelected('priority_5'));
						break;
				}

				if ($status == 'checked') {
					$this->assertTrue($this->zbxTestCheckboxSelected('status'));
				}
				else {
					$this->assertFalse($this->zbxTestCheckboxSelected('status'));
				}
			}
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormTrigger_Teardown() {
		DBrestore_tables('triggers');
	}
}
