<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/items.inc.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup triggers
 */
class testFormTriggerPrototype extends CWebTest {

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
	protected $host = 'Simple form test host';

	/**
	 * The name of the form test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testFormDiscoveryRule';

	/**
	 * The name of the form test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRuleTemplate = 'testInheritanceDiscoveryRule';

	/**
	 * The name of the test discovery rule key created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryKey = 'discovery-rule-form';

	/**
	 * The name of the test item prototype within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'testFormItemReuse';

	/**
	 * The name of the test item prototype key within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKey = 'item-prototype-reuse';

	// Returns layout data
	public static function layout() {
		return [
			[
				['constructor' => 'open', 'host' => 'Simple form test host']
			],
			[
				['constructor' => 'open_close', 'host' => 'Simple form test host']
			],
			[
				['constructor' => 'open', 'severity' => 'Warning', 'host' => 'Simple form test host']
			],
			[
				['constructor' => 'open_close', 'severity' => 'Disaster', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Not classified', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Information', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Warning', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Average', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'High', 'host' => 'Simple form test host']
			],
			[
				['severity' => 'Disaster', 'host' => 'Simple form test host']
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1',
					'constructor' => 'open_close'
				]
			],
			[
				[
					'host' => 'Simple form test host',
					'form' => 'testFormTriggerPrototype1'
				]
			],
			[
				['constructor' => 'open', 'template' => 'Inheritance test template']
			],
			[
				['constructor' => 'open_close', 'template' => 'Inheritance test template']
			],
			[
				['constructor' => 'open', 'severity' => 'Warning', 'template' => 'Inheritance test template']
			],
			[
				[
					'constructor' => 'open_close',
					'severity' => 'Disaster',
					'template' => 'Inheritance test template'
				]
			],
			[
				['severity' => 'Not classified', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Information', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Warning', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Average', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'High', 'template' => 'Inheritance test template']
			],
			[
				['severity' => 'Disaster', 'template' => 'Inheritance test template']
			],
			[
				['host' => 'Simple form test host', 'form' => 'testFormTriggerPrototype1']
			],
			[
				['template' => 'Inheritance test template', 'form' => 'testInheritanceTriggerPrototype1']
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				]
			],
			[
				[
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTriggerPrototype1',
					'constructor' => 'open'
				]
			],
			[
				[
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTriggerPrototype1',
					'templatedHost' => true,
					'constructor' => 'open'
				]
			]
		];
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormTriggerPrototype_CheckLayout($data) {

		if (isset($data['template'])) {
			$this->zbxTestLogin('templates.php');
			$this->zbxTestClickLinkTextWait($data['template']);
			$discoveryRule = $this->discoveryRuleTemplate;
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickLinkTextWait($data['host']);
			if (!isset($data['templatedHost'])) {
				$discoveryRule = $this->discoveryRule;
			}
			else {
				$discoveryRule = $this->discoveryRuleTemplate;
			}
		}

		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($discoveryRule);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent($discoveryRule);

		if (isset($data['form'])) {
			$this->zbxTestClickLinkTextWait($data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestAssertElementPresentXpath("//a[@id='tab_triggersTab' and text()='Trigger prototype']");

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

		$this->zbxTestTextPresent('Trigger prototype');

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
		$this->zbxTestAssertVisibleXpath("//input[@name='description']");
		$this->zbxTestAssertAttribute("//input[@name='description']", 'maxlength', 255);
		$this->zbxTestAssertAttribute("//input[@name='description']", 'size', 20);

		if (!(isset($data['constructor'])) || $data['constructor'] == 'open_close') {
			$this->zbxTestTextPresent(['Expression', 'Expression constructor']);
			$this->zbxTestAssertVisibleXpath("//textarea[@id='expression']");
			$this->zbxTestAssertAttribute("//textarea[@id='expression']", 'rows', 7);
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//textarea[@id='expression']", 'readonly');
			}

			$this->zbxTestAssertVisibleXpath("//button[@name='insert']");
			$this->zbxTestAssertElementText("//button[@name='insert']", 'Add');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertAttribute("//button[@name='insert']", 'disabled');
			}

			$this->zbxTestAssertElementNotPresentXpath("//li[@id='expression_row']//button[contains(@onclick, 'add_expression')]");
			$this->zbxTestAssertElementNotPresentId('insert_macro');
		}
		else {
			$this->zbxTestTextPresent('Expression');
			$this->zbxTestAssertVisibleId('expr_temp');
			$this->zbxTestAssertAttribute("//textarea[@id='expr_temp']", 'rows', 7);
			$this->zbxTestAssertAttribute("//textarea[@id='expr_temp']", 'readonly');
			$this->zbxTestTextPresent('Close expression constructor');
			$this->zbxTestAssertNotVisibleId('expression');

			if (!isset($data['form'])) {
				$this->zbxTestAssertVisibleXpath("//li[@id='expression_row']//button[contains(@onclick, 'add_expression') and text()='Add']");
			}
			else {
				$this->zbxTestAssertElementNotPresentXpath("//li[@id='expression_row']//button[contains(@onclick, 'add_expression')]");
			}

			$this->zbxTestAssertVisibleXpath("//button[@name='insert']");
			$this->zbxTestAssertElementText("//button[@name='insert']", 'Edit');
			if (isset($data['templatedHost'])) {
				$this->zbxTestAssertElementPresentXpath("//button[@name='insert'][@disabled]");
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

		$this->zbxTestAssertElementPresentId('priority_0');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_0'));
		$this->zbxTestAssertElementText("//*[@id='priority_0']/../label", 'Not classified');
		$this->zbxTestAssertElementPresentId('priority_1');
		$this->zbxTestAssertElementText("//*[@id='priority_1']/../label", 'Information');
		$this->zbxTestAssertElementPresentId('priority_2');
		$this->zbxTestAssertElementText("//*[@id='priority_2']/../label", 'Warning');
		$this->zbxTestAssertElementPresentId('priority_3');
		$this->zbxTestAssertElementText("//*[@id='priority_3']/../label", 'Average');
		$this->zbxTestAssertElementPresentId('priority_4');
		$this->zbxTestAssertElementText("//*[@id='priority_4']/../label", 'High');
		$this->zbxTestAssertElementPresentId('priority_5');
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

		$this->zbxTestTextPresent('Create enabled');
		$this->zbxTestAssertElementPresentId('status');
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
			}
		}
		else {
			$this->zbxTestAssertElementNotPresentId('clone');
			$this->zbxTestAssertElementNotPresentId('update');
			$this->zbxTestAssertElementNotPresentId('delete');
		}

		$this->zbxTestTabSwitch('Dependencies');
		$this->zbxTestTextPresent(['Dependencies', 'Name', 'Action']);
		$this->zbxTestAssertElementPresentId('add_dep_trigger');
		$this->zbxTestAssertElementText("//button[@id='add_dep_trigger']", 'Add');
		$this->zbxTestAssertElementPresentId('add_dep_trigger_prototype');
		$this->zbxTestAssertElementText("//button[@id='add_dep_trigger_prototype']", 'Add prototype');

	}

	// Returns update data
	public static function update() {
		return DBdata("select * from triggers t left join functions f on f.triggerid=t.triggerid where f.itemid='23804' and t.description LIKE 'testFormTriggerPrototype%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testFormTriggerPrototype_SimpleUpdate($data) {
		$description = $data['description'];

		$sqlTriggers = "select * from triggers ORDER BY triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');

		$this->zbxTestClickLinkTextWait($description);
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype updated');
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent($this->discoveryRule);
		$this->zbxTestTextPresent($description);

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

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
					'expression' => '6 and 0 or 0',
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
					'expression' => '{Simple form test host}',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect trigger expression. Check expression part starting from "{Simple form test host}".'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_sysUptime',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '1234567890',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'a?aa+',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '}aa]a{',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '-aaa=%',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa,;:',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa><.',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa*&_',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'aaa#@!',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => '([)$^',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<0',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new value, if this value is part of this expression. If time based functions are used in the expression, it is recalculated every 30 seconds by a zabbix timer process. ',
					'url' => 'http://www.zabbix.com',
					'severity' => 'High',
					'status' => false,
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_CheckUrl',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)}<5',
					'url' => 'index.php',
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger_CheckWrongUrl',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0',
					'url' => 'www.zabbix.com',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Wrong value for url field.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect item key "someItem.uptime" provided for trigger expression on "Simple form test host".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:item-prototype-reuse.somefunc(0)}<0',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Incorrect trigger function "somefunc(0)" provided in expression. Unknown function.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:item-prototype-reuse.last(0)} or {#MACRO}',
					'constructor' => [[
						'text' => ['A or B', 'A', 'B'],
						'elements' => ['expr_0_51', 'expr_56_63']
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:item-prototype-reuse.last(0)}<0 or 8 and 9',
					'constructor' => [[
						'text' => ['A or (B and C)', 'Or', 'And', 'A', 'B', 'C'],
						'elements' => ['expr_0_43', 'expr_48_48', 'expr_54_54'],
						'elementError' => true
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0 or 8 and 9 + {Simple form test host:item-prototype-reuse.last(0)}',
					'constructor' => [[
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_48', 'expr_53_53', 'expr_59_114'],
						'elementError' => true
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:item-prototype-reuse.lasta(0)}<0 or 8 and 9 + {Simple form test host:item-prototype-reuse.last(0)}',
					'constructor' => [[
						'text' => ['A or (B and C)', 'A', 'B', 'C'],
						'elements' => ['expr_0_54', 'expr_59_59', 'expr_65_120'],
						'elementError' => true
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host@:item-prototype-reuse.last(0)}',
					'constructor' => [[
						'errors' => [
							'Expression syntax error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host@:item-prototype-reuse.last(0)}".'],
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
					'expression' => '{Simple form test host:item-prototype-reuse.lastA(0)}',
					'constructor' => [[
						'errors' => [
							'Expression syntax error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:item-prototype-reuse.lastA(0)}".'],
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerSimple',
					'expression' => 'default',
					'formCheck' =>true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerName',
					'expression' => 'default'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'description' => 'triggerRemove',
					'expression' => 'default',
					'formCheck' =>true,
					'dbCheck' => true,
					'remove' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'description' => 'triggerName',
					'expression' => 'default',
					'error_msg' => 'Cannot add trigger prototype',
					'errors' => [
						'Trigger prototype "triggerName" already exists on "Simple form test host".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTriggerPrototype_SimpleCreate($data) {

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Trigger prototypes');
		$this->zbxTestClickWait('form');

		if (isset($data['description'])) {
			$this->zbxTestInputTypeByXpath("//input[@name='description']", $data['description']);
			$description = $data['description'];
		}

		if (isset($data['expression'])) {
			switch ($data['expression']) {
				case 'default':
					$expression = '{'.$this->host.':'.$this->itemKey.'.last(0)}=0';
					$this->zbxTestInputType('expression', $expression);
					break;
				default:
					$expression = $data['expression'];
					$this->zbxTestInputType('expression', $expression);
					break;
			}
		}

		if (isset($data['type'])) {
			$this->zbxTestCheckboxSelect('type_1');
		}

		if (isset($data['comments'])) {
			$this->zbxTestInputType('comments', $data['comments']);;
		}

		if (isset($data['url'])) {
			$this->zbxTestInputType('url', $data['url']);;
		}

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

		if (isset($data['status'])) {
			$this->zbxTestCheckboxSelect('status', false);
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
					$this->zbxTestAssertElementPresentXpath("//button[@name='test_expression']");

					$this->zbxTestAssertVisibleXpath("//li[@id='expression_row']//button[contains(@onclick, 'and_expression') and text()='And']");
					$this->zbxTestAssertVisibleXpath("//li[@id='expression_row']//button[contains(@onclick, 'or_expression') and text()='Or']");
					$this->zbxTestAssertElementPresentXpath("//button[text()='Remove']");
					if (isset($constructor['elements'])) {
						foreach($constructor['elements'] as $elem) {
							$this->zbxTestAssertElementPresentId($elem);
						}
					}
					if (isset($constructor['elementError'])) {
						$this->zbxTestAssertElementPresentXpath('//span[@class="icon-info status-red"]');
					}
					else {
						$this->zbxTestAssertElementNotPresentXpath('//span[@class="icon-info status-red"]');
					}

					if (isset($constructor['text'])) {
						foreach($constructor['text'] as $txt) {
							$this->zbxTestTextPresent($txt);
						}
					}
				}
			}
		}

		if (!isset($data['constructor'])) {
			$this->zbxTestClickWait('add');
			switch ($data['expected']) {
				case TEST_GOOD:
					$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototype added');
					$this->zbxTestCheckTitle('Configuration of trigger prototypes');
					$this->zbxTestAssertElementText("//tbody//a[text()='$description']", $description);
					$this->zbxTestAssertElementText("//a[text()='$description']/ancestor::tr/td[4]", $expression);
					$this->zbxTestTextPresent($this->discoveryRule);
					break;
				case TEST_BAD:
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
					$this->zbxTestCheckTitle('Configuration of trigger prototypes');
					$this->zbxTestAssertElementPresentXpath("//a[@id='tab_triggersTab' and text()='Trigger prototype']");
					foreach ($data['errors'] as $msg) {
						$msg = str_replace('<', '&lt;', $msg);
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(['Name', 'Expression', 'Description']);
					break;
			}
		}

		if (isset($data['formCheck'])) {
			$this->zbxTestOpen('hosts.php');
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Discovery rules');
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
			$this->zbxTestClickLinkTextWait('Trigger prototypes');

			$this->zbxTestClickLinkTextWait($description);
			$this->zbxTestAssertElementValue('expression', $expression);
			$getName = $this->zbxTestGetValue("//input[@name='description']");
			$this->assertEquals($getName, $description);
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT description FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['description'], $description);
			}
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT description, triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$triggerId = $row['triggerid'];
			}

			$this->zbxTestOpen('hosts.php');
			$this->zbxTestClickLinkTextWait($this->host);
			$this->zbxTestClickLinkTextWait('Discovery rules');
			$this->zbxTestClickLinkTextWait($this->discoveryRule);
			$this->zbxTestClickLinkTextWait('Trigger prototypes');

			$this->zbxTestCheckboxSelect("g_triggerid_$triggerId");
			$this->zbxTestClickButton('triggerprototype.massdelete');

			$this->zbxTestAcceptAlert();

			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Trigger prototypes deleted');
			$this->assertEquals(0, DBcount("SELECT triggerid FROM triggers where description = '".$description."'"));
		}
	}
}
