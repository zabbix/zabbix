<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		return array(
			array(
				array('constructor' => 'open', 'host' => 'Simple form test host'
				)
			),
			array(
				array('constructor' => 'open_close', 'host' => 'Simple form test host'
				)
			),
			array(
				array('constructor' => 'open', 'severity' => 'Warning', 'host' => 'Simple form test host'
				)
			),
			array(
				array('constructor' => 'open_close', 'severity' => 'Disaster', 'host' => 'Simple form test host'
				)
			),
			array(
				array('severity' => 'Not classified', 'host' => 'Simple form test host'
				)
			),
			array(
				array('severity' => 'Information', 'host' => 'Simple form test host'
				)
			),
			array(
				array('severity' => 'Warning', 'host' => 'Simple form test host'
				)
			),
			array(
				array('severity' => 'Average', 'host' => 'Simple form test host'
				)
			),
			array(
				array('severity' => 'High', 'host' => 'Simple form test host'
				)
			),
			array(
				array('severity' => 'Disaster', 'host' => 'Simple form test host'
				)
			),
			array(
				array('constructor' => 'open', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('constructor' => 'open_close', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('constructor' => 'open', 'severity' => 'Warning', 'template' => 'Inheritance test template'
				)
			),
			array(
				array(
					'constructor' => 'open_close',
					'severity' => 'Disaster',
					'template' => 'Inheritance test template'
				)
			),
			array(
				array('severity' => 'Not classified', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('severity' => 'Information', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('severity' => 'Warning', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('severity' => 'Average', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('severity' => 'High', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('severity' => 'Disaster', 'template' => 'Inheritance test template'
				)
			),
			array(
				array('host' => 'Simple form test host', 'form' => 'testFormTrigger1'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTrigger1'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger1',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger2',
					'templatedHost' => true,
					'hostTemplate' => 'Inheritance test template'
				)
			),
			array(
				array(
					'host' => 'Simple form test host',
					'form' => 'testFormTrigger1',
					'constructor' => 'open'
				)
			),
			array(
				array(
					'template' => 'Inheritance test template',
					'form' => 'testInheritanceTrigger1',
					'constructor' => 'open'
				)
			),
			array(
				array(
					'host' => 'Template inheritance test host',
					'form' => 'testInheritanceTrigger1',
					'templatedHost' => true,
					'constructor' => 'open'
				)
			)
		);
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
			$this->zbxTestClickWait('link='.$data['template']);
		}

		if (isset($data['host'])) {
			$this->zbxTestLogin('hosts.php');
			$this->zbxTestClickWait('link='.$data['host']);
		}

		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');

		if (isset($data['form'])) {
			$this->zbxTestClickWait('link='.$data['form']);
		}
		else {
			$this->zbxTestClickWait('form');
		}
		$this->zbxTestCheckTitle('Configuration of triggers');

		if (isset($data['constructor'])) {
			switch ($data['constructor']) {
				case 'open':
					$this->zbxTestClickWait("//span[text()='Expression constructor']");
					break;
				case 'open_close':
					$this->zbxTestClickWait("//span[text()='Expression constructor']");
					$this->zbxTestClickWait("//span[text()='Close expression constructor']");
					break;
			}
		}

		$this->zbxTestTextPresent('Trigger');

		if (isset($data['templatedHost'])) {
			$this->zbxTestTextPresent('Parent triggers');
			if (isset($data['hostTemplate'])) {
				$this->assertElementPresent("//a[text()='".$data['hostTemplate']."']");
			}
		}
		else {
			$this->zbxTestTextNotPresent('Parent triggers');
		}

		$this->zbxTestTextPresent('Name');
		$this->assertVisible('description');
		$this->assertAttribute("//input[@id='description']/@maxlength", '255');
		$this->assertAttribute("//input[@id='description']/@size", '50');

		if (!isset($data['constructor']) || $data['constructor'] == 'open_close') {
			$this->zbxTestTextPresent(array('Expression', 'Expression constructor'));
			$this->assertVisible("//textarea[@id='expression']");
			$this->assertAttribute("//textarea[@id='expression']/@rows", '7');
			if (isset($data['templatedHost'])) {
				$this->assertAttribute("//textarea[@id='expression']/@readonly", 'readonly');
			}

			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Add');
			if (isset($data['templatedHost'])) {
				$this->assertElementPresent("//input[@id='insert']/@disabled");
			}

			$this->assertElementNotPresent('add_expression');
			$this->assertElementNotPresent('insert_macro');
			$this->assertElementNotPresent('exp_list');
		}
		else {
			$this->zbxTestTextPresent('Expression');
			$this->assertVisible('expr_temp');
			$this->assertAttribute("//textarea[@id='expr_temp']/@rows", '7');
			$this->assertAttribute("//textarea[@id='expr_temp']/@readonly", 'readonly');
			$this->zbxTestTextNotPresent('Expression constructor');
			$this->assertNotVisible('expression');

			if (!isset($data['form'])) {
				$this->assertVisible('add_expression');
				$this->assertAttribute("//input[@id='add_expression']/@value", 'Add');
			}
			else {
				$this->assertElementNotPresent('add_expression');
			}

			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Edit');
			if (isset($data['templatedHost'])) {
				$this->assertElementPresent("//input[@id='insert']/@disabled");
			}

			$this->assertVisible('insert_macro');
			$this->assertAttribute("//input[@id='insert_macro']/@value", 'Insert macro');
			if (isset($data['templatedHost'])) {
				$this->assertElementPresent("//input[@id='insert_macro']/@disabled");
			}

			if (!isset($data['templatedHost'])) {
				$this->zbxTestTextPresent(array('Target', 'Expression', 'Error', 'Action', 'Close expression constructor'));
			}
			else {
				$this->zbxTestTextPresent(array('Expression', 'Error', 'Close expression constructor'));
			}
			$this->assertVisible('exp_list');
			$this->zbxTestTextPresent('Close expression constructor');
		}

		$this->zbxTestTextPresent('Multiple PROBLEM events generation');
		$this->assertVisible('type');
		$this->assertAttribute("//input[@id='type']/@type", 'checkbox');

		$this->zbxTestTextPresent('Description');
		$this->assertVisible('comments');
		$this->assertAttribute("//textarea[@id='comments']/@rows", '7');

		$this->zbxTestTextPresent('URL');
		$this->assertVisible('url');
		$this->assertAttribute("//input[@id='url']/@maxlength", '255');
		$this->assertAttribute("//input[@id='url']/@size", '50');

		$this->assertVisible('priority_0');
		$this->assertAttribute("//*[@id='priority_0']/@checked", 'checked');
		$this->assertElementPresent("//*[@id='priority_label_0']/span[text()='Not classified']");
		$this->assertVisible('priority_1');
		$this->assertElementPresent("//*[@id='priority_label_1']/span[text()='Information']");
		$this->assertVisible('priority_2');
		$this->assertElementPresent("//*[@id='priority_label_2']/span[text()='Warning']");
		$this->assertVisible('priority_3');
		$this->assertElementPresent("//*[@id='priority_label_3']/span[text()='Average']");
		$this->assertVisible('priority_4');
		$this->assertElementPresent("//*[@id='priority_label_4']/span[text()='High']");
		$this->assertVisible('priority_5');
		$this->assertElementPresent("//*[@id='priority_label_5']/span[text()='Disaster']");

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClick('priority_0');
					break;
				case 'Information':
					$this->zbxTestClick('priority_1');
					break;
				case 'Warning':
					$this->zbxTestClick('priority_2');
					break;
				case 'Average':
					$this->zbxTestClick('priority_3');
					break;
				case 'High':
					$this->zbxTestClick('priority_4');
					break;
				case 'Disaster':
					$this->zbxTestClick('priority_5');
					break;
			}
		}

		$this->zbxTestTextPresent('Enabled');
		$this->assertVisible('status');
		$this->assertAttribute("//input[@id='status']/@type", 'checkbox');

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('cancel');
		$this->assertAttribute("//input[@id='cancel']/@value", 'Cancel');

		if (isset($data['form'])) {
			$this->assertVisible('clone');
			$this->assertAttribute("//input[@id='clone']/@value", 'Clone');

			$this->assertVisible('delete');
			$this->assertAttribute("//input[@id='delete']/@value", 'Delete');
			if (isset($data['templatedHost'])) {
				$this->assertElementPresent("//input[@id='delete']/@disabled");
			}
		}
		else {
			$this->assertElementNotPresent('clone');
			$this->assertElementNotPresent('delete');
		}

		$this->zbxTestClick('link=Dependencies');
		$this->zbxTestTextPresent(array('Dependencies', 'Name', 'Action', 'No dependencies defined'));
		$this->assertElementPresent('bnt1');
		$this->assertAttribute("//input[@id='bnt1']/@value", 'Add');
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
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->zbxTestClickWait('link='.$data['description']);
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestTextPresent('Trigger updated');
		$this->zbxTestTextPresent($data['description']);
		$this->zbxTestTextPresent('TRIGGERS');

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
		$this->assertEquals($oldHashFunctions, DBhash($sqlFunctions));
	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_BAD,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Incorrect value for field "Expression": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Expression": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'expression' => '6 & 0 | 0',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '6 & 0 | 0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Trigger expression must contain at least one host:key reference.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host}',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Incorrect trigger expression. Check expression part starting from "{Simple form test host}".'
					)
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_simple',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'HTML_symbols&#8704;&forall;&#8734;&ne;&sup;&Eta;&#937;&#958;&pi;&#8194;&mdash;&#8364;&loz;',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'ASCII_characters&#33;&#40;&#51;&#101;&#10;&#25;',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_allFields',
					'type' => true,
					'comments' => 'MyTrigger_allFields -Description textbox for comments',
					'url' => 'MyTrigger_allFields -URL field for link',
					'severity' => 'Disaster',
					'status' => false,
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => '1234567890',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => '0',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'a?aa+',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => '}aa]a{',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => '-aaa=%',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'aaa,;:',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'aaa><.',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'aaa*&_',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'aaa#@!',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => '([)$^',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new value, if this value is part of this expression. If time based functions are used in the expression, it is recalculated every 30 seconds by a zabbix timer process.',
					'url' => 'www.zabbix.com',
					'severity' => 'High',
					'status' => false
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:test-item-reuse.last(0)}<0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Incorrect trigger expression. Host "Zabbix host" does not exist or you have no access to this host.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Incorrect item key "someItem.uptime" provided for trigger expression on "Simple form test host".'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.somefunc(0)}<0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Cannot implode expression "{Simple form test host:test-item-reuse.somefunc(0)}<0". Incorrect trigger function "somefunc(0)" provided in expression. Unknown function.'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.last(0)} | {#MACRO}',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Incorrect trigger expression. Check expression part starting from " {#MACRO}".'
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.last(0)} | {#MACRO}',
					'constructor' => array(array(
						'text' => array('A | B', 'A', 'B'),
						'elements' => array('expr_0_46', 'expr_50_57')
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:test-item-reuse.last(0)}<0 | 8 & 9',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'OR', 'AND', 'A', 'B', 'C'),
						'elements' => array('expr_0_38', 'expr_42_42', 'expr_46_46'),
						'elementError' => true
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0 | 8 & 9 + {Simple form test host:test-item-reuse.last(0)}',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'A', 'B', 'C'),
						'elements' => array('expr_0_48', 'expr_52_52', 'expr_56_106'),
						'elementError' => true
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.lasta(0)}<0 | 8 & 9 + {Simple form test host:test-item-reuse.last(0)}',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'A', 'B', 'C'),
						'elements' => array('expr_0_49', 'expr_53_53', 'expr_57_107'),
						'elementError' => true
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host@:test-item-reuse.last(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host@:test-item-reuse.last(0)}".'),
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:system .uptime.last(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:system .uptime.last(0)}".'),
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:system .uptime.last(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:system .uptime.last(0)}".'),
						)
					)
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.lastA(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Simple form test host:test-item-reuse.lastA(0)}".'),
						)
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testFormTrigger_SimpleCreate($data) {

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestClickWait('form');
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');

		if (isset($data['description'])) {
			$this->input_type('description', $data['description']);
		}
		$description = $this->getValue('description');

		if (isset($data['expression'])) {
			$this->input_type('expression', $data['expression']);
		}
		$expression = $this->getValue('expression');

		if (isset($data['type'])) {
			$this->zbxTestCheckboxSelect('type');
			$type = 'checked';
		}
		else {
			$type = 'unchecked';
		}


		if (isset($data['comments'])) {
			$this->input_type('comments', $data['comments']);
		}
		$comments = $this->getValue('comments');

		if (isset($data['url'])) {
			$this->input_type('url', $data['url']);
		}
		$url = $this->getValue('url');

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClick('priority_0');
					break;
				case 'Information':
					$this->zbxTestClick('priority_1');
					break;
				case 'Warning':
					$this->zbxTestClick('priority_2');
					break;
				case 'Average':
					$this->zbxTestClick('priority_3');
					break;
				case 'High':
					$this->zbxTestClick('priority_4');
					break;
				case 'Disaster':
					$this->zbxTestClick('priority_5');
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
			$this->zbxTestClickWait("//span[text()='Expression constructor']");

			foreach($data['constructor'] as $constructor) {
				if (isset($constructor['errors'])) {
					foreach($constructor['errors'] as $err) {
						$this->zbxTestTextPresent($err);
					}
				}
				else {
					$this->assertAttribute("//input[@id='and_expression']/@value", 'AND');
					$this->assertElementPresent('and_expression');

					$this->assertAttribute("//input[@id='or_expression']/@value", 'OR');
					$this->assertElementPresent('or_expression');

					$this->assertAttribute("//input[@id='replace_expression']/@value", 'Replace');
					$this->assertElementPresent('replace_expression');
					if (isset($constructor['text'])) {
						foreach($constructor['text'] as $txt) {
							$this->zbxTestTextPresent($txt);
						}
					}
					if (isset($constructor['elements'])) {
						foreach($constructor['elements'] as $elem) {
							$this->assertElementPresent($elem);
						}
					}
					if (isset($constructor['elementError'])) {
						$this->assertElementPresent('//img[@alt="expression_errors"]');
					}
					else {
						$this->assertElementPresent('//img[@alt="expression_no_errors"]');
					}
				}
			}
		}

		if (!isset($data['constructor'])) {
			$this->zbxTestClickWait('save');
			switch ($data['expected']) {
				case TEST_GOOD:
					$this->zbxTestTextPresent('Trigger added');
					$this->zbxTestCheckTitle('Configuration of triggers');
					$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
					$this->zbxTestTextPresent(array($description, $expression));
					break;
				case TEST_BAD:
					$this->zbxTestCheckTitle('Configuration of triggers');
					$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent('Name');
					$this->zbxTestTextPresent('Expression');
					$this->zbxTestTextPresent('Description');
					break;
			}

			if (isset($data['formCheck'])) {
				$this->zbxTestClickWait('link='.$description);
				$this->assertAttribute("//input[@id='description']/@value", 'exact:'.$description);
				$exp = $this->getValue('expression');

				$this->assertEquals($exp, $expression);

				if ($type == 'checked') {
					$this->assertAttribute("//input[@id='type']/@checked", 'checked');
				}
				else {
					$this->assertElementNotPresent("//input[@id='type']/@checked");
				}

				$comment = $this->getValue('comments');
				$this->assertEquals($comment, $comments);

				$url_ = $this->getValue('url');
				$this->assertEquals($url_, $url);

				switch ($severity) {
					case 'Not classified':
						$this->assertElementPresent("//label[@id='priority_label_0']/@aria-pressed");
						break;
					case 'Information':
						$this->assertElementPresent("//label[@id='priority_label_1']/@aria-pressed");
						break;
					case 'Warning':
						$this->assertElementPresent("//label[@id='priority_label_2']/@aria-pressed");
						break;
					case 'Average':
						$this->assertElementPresent("//label[@id='priority_label_3']/@aria-pressed");
						break;
					case 'High':
						$this->assertElementPresent("//label[@id='priority_label_4']/@aria-pressed");
						break;
					case 'Disaster':
						$this->assertElementPresent("//label[@id='priority_label_5']/@aria-pressed");
						break;
				}

				if ($status == 'checked') {
					$this->assertAttribute("//input[@id='status']/@checked", 'checked');
				}
				else {
					$this->assertElementNotPresent("//input[@id='status']/@checked");
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
