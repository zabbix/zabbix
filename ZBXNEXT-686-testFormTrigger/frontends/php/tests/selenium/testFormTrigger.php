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

define('TRIGGER_GOOD', 0);
define('TRIGGER_BAD', 1);

class testFormTrigger extends CWebTest {

	/**
	 * The name of the Simple form test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	// returns all possible trigger data
	public static function triggerData() {
		return array(
			array(
				array('constructor' => 'open')
			),
			array(
				array('constructor' => 'open_close')
			),
			array(
				array('constructor' => 'open', 'severity' => 'Warning')
			),
			array(
				array('constructor' => 'open_close', 'severity' => 'Disaster')
			),
			array(
				array('severity' => 'Not classified')
			),
			array(
				array('severity' => 'Information')
			),
			array(
				array('severity' => 'Warning')
			),
			array(
				array('severity' => 'Average')
			),
			array(
				array('severity' => 'High')
			),
			array(
				array('severity' => 'Disaster')
			)
		);
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testFormTrigger_setup() {
		DBsave_tables('triggers');
	}

	/**
	 * @dataProvider triggerData
	 */
	public function testFormTrigger_CheckLayout($data) {

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->checkTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of triggers');

		if (isset($data['constructor'])) {
			switch ($data['constructor']) {
				case 'open':
					$this->zbxTestClick("//span[text()='Expression constructor']");
					sleep(1);
					break;
				case 'open_close':
					$this->zbxTestClick("//span[text()='Expression constructor']");
					sleep(1);
					$this->zbxTestClick("//span[text()='Close expression constructor']");
					sleep(1);
					break;
			}
		}

		$this->zbxTestTextPresent('Trigger');
		$this->zbxTestTextPresent('Name');
		$this->assertVisible('description');
		$this->assertAttribute("//input[@id='description']/@maxlength", '255');
		$this->assertAttribute("//input[@id='description']/@size", '50');

		if (!(isset($data['constructor'])) || $data['constructor'] == 'open_close') {
			$this->zbxTestTextPresent(array('Expression', 'Expression constructor'));
			$this->assertVisible('expression');
			$this->assertAttribute("//textarea[@id='expression']/@rows", '7');
			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Add');

			$this->assertElementNotPresent('add_expression');
			$this->assertElementNotPresent('insert_macro');
			$this->assertElementNotPresent('exp_list');
		} else {
			$this->zbxTestTextPresent('Expression');
			$this->assertVisible('expr_temp');
			$this->assertAttribute("//textarea[@id='expr_temp']/@rows", '7');
			$this->assertAttribute("//textarea[@id='expr_temp']/@readonly", 'readonly');
			$this->zbxTestTextNotPresent('Expression constructor');
			$this->assertNotVisible('expression');

			$this->assertVisible('add_expression');
			$this->assertAttribute("//input[@id='add_expression']/@value", 'Add');

			$this->assertVisible('insert');
			$this->assertAttribute("//input[@id='insert']/@value", 'Edit');

			$this->assertVisible('insert_macro');
			$this->assertAttribute("//input[@id='insert_macro']/@value", 'Insert macro');

			$this->zbxTestTextPresent(array('Target', 'Expression', 'Error', 'Action', 'Close expression constructor'));
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

		$this->assertVisible('severity_0');
		$this->assertAttribute("//*[@id='severity_0']/@checked", 'checked');
		$this->assertElementPresent("//*[@id='severity_label_0']/span[text()='Not classified']");
		$this->assertVisible('severity_1');
		$this->assertElementPresent("//*[@id='severity_label_1']/span[text()='Information']");
		$this->assertVisible('severity_2');
		$this->assertElementPresent("//*[@id='severity_label_2']/span[text()='Warning']");
		$this->assertVisible('severity_3');
		$this->assertElementPresent("//*[@id='severity_label_3']/span[text()='Average']");
		$this->assertVisible('severity_4');
		$this->assertElementPresent("//*[@id='severity_label_4']/span[text()='High']");
		$this->assertVisible('severity_5');
		$this->assertElementPresent("//*[@id='severity_label_5']/span[text()='Disaster']");

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClick('severity_0');
					break;
				case 'Information':
					$this->zbxTestClick('severity_1');
					break;
				case 'Warning':
					$this->zbxTestClick('severity_2');
					break;
				case 'Average':
					$this->zbxTestClick('severity_3');
					break;
				case 'High':
					$this->zbxTestClick('severity_4');
					break;
				case 'Disaster':
					$this->zbxTestClick('severity_5');
					break;
				default:
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

		$this->zbxTestClick('link=Dependencies');
		$this->zbxTestTextPresent(array('Dependencies', 'Name', 'Action', 'No dependencies defined'));
		$this->assertElementPresent('bnt1');
		$this->assertAttribute("//input[@id='bnt1']/@value", 'Add');
	}

	// Returns list of triggers
	public static function allTriggers() {
		return DBdata("select * from triggers where description LIKE 'testFormTrigger%'");
	}

	/**
	 * @dataProvider allTriggers
	 */
	public function testFormTrigger_simpleCreate($data) {
		$description = $data['description'];

		$sqlTriggers = "select * from triggers";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->zbxTestClickWait('link='.$description);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of triggers');
		$this->zbxTestTextPresent('Trigger updated');
		$this->zbxTestTextPresent("$description");
		$this->zbxTestTextPresent('TRIGGERS');

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

	// Returns all possible item data
	public static function dataCreate() {
		return array(
			array(
				array(
					'expected' => TRIGGER_BAD,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Name": cannot be empty.',
						'Warning. Incorrect value for field "expression": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "expression": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'expression' => '6 & 0 | 0',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_GOOD,
					'description' => 'MyTrigger_sysUptime',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '1234567890',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'a?aa+',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '}aa]a{',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '-aaa=%',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa,;:',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa><.',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa*&_',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa#@!',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '([)$^',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<0',
					'formCheck' => true,
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => '{Simple form test host:test-item-reuse.last(0)}<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new value, if this value is part of this expression. If time based functions are used in the expression, it is recalculated every 30 seconds by a zabbix timer process. ',
					'url' => 'www.zabbix.com',
					'severity' => 'High',
					'status' => false,
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.somefunc(0)}<0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Cannot implode expression "{Simple form test host:test-item-reuse.somefunc(0)}<0". Incorrect trigger function "somefunc" provided in expression. Unknown function.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.last(0)} | {#MACRO}',
					'constructor' => array(array(
						'text' => array('A | B', 'A', 'B'),
						'elements' => array('expr_0_32', 'expr_36_43')
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:test-item-reuse.last(0)}<0 | 8 & 9',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'OR', 'AND', 'A', 'B', 'C'),
						'elements' => array('expr_0_36', 'expr_40_40', 'expr_44_44'),
						'elementError' => true
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:someItem.uptime.last(0)}<0 | 8 & 9 + {Simple form test host:test-item-reuse.last(0)}',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'A', 'B', 'C'),
						'elements' => array('expr_0_36', 'expr_40_40', 'expr_44_80'),
						'elementError' => true
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Simple form test host:test-item-reuse.lasta(0)}<0 | 8 & 9 + {Simple form test host:test-item-reuse.last(0)}',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'A', 'B', 'C'),
						'elements' => array('expr_0_35', 'expr_39_39', 'expr_43_79'),
						'elementError' => true
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
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
					'expected' => TRIGGER_BAD,
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
	 * @dataProvider dataCreate
	 */
	public function testFormTrigger_Create($data) {

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->host);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->checkTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestClickWait('form');
		$this->checkTitle('Configuration of triggers');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');


		if (isset($data['description'])) {
			$this->input_type('description', $data['description']);
			$description = $data['description'];
		}

		if (isset($data['expression'])) {
			$this->input_type('expression', $data['expression']);
			$expression = $data['expression'];
		}

		if (isset($data['type'])) {
			$this->zbxTestCheckboxSelect('type');
		}

		if (isset($data['comments'])) {
			$this->input_type('comments', $data['comments']);;
		}

		if (isset($data['url'])) {
			$this->input_type('url', $data['url']);;
		}

		if (isset($data['severity'])) {
			switch ($data['severity']) {
				case 'Not classified':
					$this->zbxTestClick('severity_0');
					break;
				case 'Information':
					$this->zbxTestClick('severity_1');
					break;
				case 'Warning':
					$this->zbxTestClick('severity_2');
					break;
				case 'Average':
					$this->zbxTestClick('severity_3');
					break;
				case 'High':
					$this->zbxTestClick('severity_4');
					break;
				case 'Disaster':
					$this->zbxTestClick('severity_5');
					break;
			}
		}

		if (isset($data['status'])) {
			$this->zbxTestCheckboxUnselect('status');
		}

		if (isset($data['constructor'])) {
			$this->zbxTestClick("//span[text()='Expression constructor']");
			sleep(1);

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
				case TRIGGER_GOOD:
				$this->zbxTestTextPresent('Trigger added');
				$this->checkTitle('Configuration of triggers');
				$this->zbxTestTextPresent('CONFIGURATION OF TRIGGERS');
				break;
				case TRIGGER_BAD:
				$this->checkTitle('Configuration of triggers');
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
				$this->zbxTestClickWait("link=$description");
				$this->assertAttribute("//input[@id='description']/@value", 'exact:'.$description);
				$this->assertTrue(true, "//text()='$expression'");
			}
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormTrigger_teardown() {
		DBrestore_tables('triggers');
	}
}
