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

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceTriggerPrototype extends CWebTest {

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
	 * The name of the test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'testInheritanceDiscoveryRule';

	/**
	 * The name of the test discovery rule key created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryKey = 'discovery-rule-test';

	/**
	 * The name of the test item prototype within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $item = 'itemDiscovery';

	/**
	 * The name of the test item prototype key within test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $itemKey = 'item-discovery-prototype';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceTriggerPrototype_Setup() {
		DBsave_tables('triggers');
	}

	// Returns update data
	public static function update() {
		return DBdata("select * from triggers t left join functions f on f.triggerid=t.triggerid where f.itemid='23600' and t.description LIKE 'testInheritanceTriggerPrototype%'");
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceTriggerPrototype_SimpleUpdate($data) {
		$description = $data['description'];

		$sqlTriggers = "select * from triggers ORDER BY triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait('link=Trigger prototypes');

		$this->zbxTestClickWait('link='.$description);
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Trigger updated');
		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent(array('CONFIGURATION OF TRIGGER PROTOTYPES', "Trigger prototypes of ".$this->discoveryRule));
		$this->zbxTestTextPresent("$description");

		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}


	public static function create() {
		return array(
			array(
				array(
					'expected' => TRIGGER_BAD,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.',
						'Incorrect value for field "Expression": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Expression": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'expression' => '6 & 0 | 0',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value for field "Name": cannot be empty.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template}',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Incorrect trigger expression. Check expression part starting from "{Inheritance test template}".'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'MyTrigger_sysUptime',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'expressionHost' => '{Template inheritance test host:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '1234567890',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'expressionHost' => '{Template inheritance test host:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'a?aa+',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'expressionHost' => '{Template inheritance test host:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '}aa]a{',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'expressionHost' => '{Template inheritance test host:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '-aaa=%',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'expressionHost' => '{Template inheritance test host:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa,;:',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'expressionHost' => '{Template inheritance test host:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa><.',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa*&_',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'aaa#@!',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => '([)$^',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<0',
					'hostCheck' => true
				)
			),
			array(
				array(
					'expected' => TRIGGER_GOOD,
					'description' => 'MyTrigger_generalCheck',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)}<5',
					'type' => true,
					'comments' => 'Trigger status (expression) is recalculated every time Zabbix server receives new value, if this value is part of this expression. If time based functions are used in the expression, it is recalculated every 30 seconds by a zabbix timer process. ',
					'url' => 'www.zabbix.com',
					'severity' => 'High',
					'status' => false
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:someItem.uptime.last(0)}<0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Trigger prototype expression "{Inheritance test template:someItem.uptime.last(0)}<0" must contain at least one item prototype.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:item-discovery-prototype.somefunc(0)}<0',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Cannot implode expression "{Inheritance test template:item-discovery-prototype.somefunc(0)}<0". Incorrect trigger function "somefunc(0)" provided in expression. Unknown function.'
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:item-discovery-prototype.last(0)} | {#MACRO}',
					'constructor' => array(array(
						'text' => array('A | B', 'A', 'B'),
						'elements' => array('expr_0_59', 'expr_63_70')
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Zabbix host:item-discovery-prototype.last(0)}<0 | 8 & 9',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'OR', 'AND', 'A', 'B', 'C'),
						'elements' => array('expr_0_47', 'expr_51_51', 'expr_55_55')
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:someItem.uptime.last(0)}<0 | 8 & 9 + {Inheritance test template:item-discovery-prototype.last(0)}',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'A', 'B', 'C'),
						'elements' => array('expr_0_52', 'expr_56_56', 'expr_60_123')
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:item-discovery-prototype.lasta(0)}<0 | 8 & 9 + {Inheritance test template:item-discovery-prototype.last(0)}',
					'constructor' => array(array(
						'text' => array('A | (B & C)', 'A', 'B', 'C'),
						'elements' => array('expr_0_62', 'expr_66_66', 'expr_70_133')
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template@:item-discovery-prototype.last(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Inheritance test template@:item-discovery-prototype.last(0)}".'
							),
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:system .uptime.last(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Inheritance test template:system .uptime.last(0)}".'
							),
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:system .uptime.last(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Inheritance test template:system .uptime.last(0)}".'
							),
						)
					)
				)
			),
			array(
				array(
					'expected' => TRIGGER_BAD,
					'description' => 'MyTrigger',
					'expression' => '{Inheritance test template:item-discovery-prototype.lastA(0)}',
					'constructor' => array(array(
						'errors' => array(
							'ERROR: Expression Syntax Error.',
							'Incorrect trigger expression. Check expression part starting from "{Inheritance test template:item-discovery-prototype.lastA(0)}".'
							),
						)
					)
				)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerSimple',
					'expression' => 'default',
					'hostCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerName',
					'expression' => 'default',
					'hostCheck' => true
				)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerRemove',
					'expression' => 'default',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array('expected' => TRIGGER_GOOD,
					'description' => 'triggerNotRemove',
					'expression' => 'default',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true
				)
			),
			array(
				array('expected' => TRIGGER_BAD,
					'description' => 'triggerSimple',
					'expression' => 'default',
					'errors' => array(
						'ERROR: Cannot add trigger',
						'Trigger "triggerSimple" already exists on "Inheritance test template".'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceTriggerPrototype_SimpleCreate($data) {

		$this->zbxTestLogin('templates.php');
		$this->zbxTestClickWait('link='.$this->template);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link='.$this->discoveryRule);
		$this->zbxTestClickWait('link=Trigger prototypes');
		$this->zbxTestClickWait('form');

		if (isset($data['description'])) {
			$this->input_type('description', $data['description']);
			$description = $data['description'];
		}

		if (isset($data['expression'])) {
			switch ($data['expression']) {
				case 'default':
					$expression = '{'.$this->template.':'.$this->itemKey.'.last(0)}=0';
					$this->input_type('expression', $expression);
					break;
				default:
					$expression = $data['expression'];
					$this->input_type('expression', $expression);
					break;
			}
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

		if (isset($data['status'])) {
			$this->zbxTestCheckboxUnselect('status');
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
					$this->assertElementPresent('test_expression');

					$this->assertAttribute("//input[@id='or_expression']/@value", 'OR');
					$this->assertElementPresent("//span[text()='Delete']");

					if (isset($constructor['text'])) {
						foreach($constructor['text'] as $txt) {
							$this->zbxTestTextPresent($txt);
						}
					}
				}
			}
		}

		if (!isset($data['constructor'])) {
			$this->zbxTestClickWait('save');
			switch ($data['expected']) {
				case TRIGGER_GOOD:
					$this->zbxTestTextPresent('Trigger added');
					$this->checkTitle('Configuration of trigger prototypes');
					$this->zbxTestTextPresent(array('CONFIGURATION OF TRIGGER PROTOTYPES', "Trigger prototypes of ".$this->discoveryRule));
					break;
				case TRIGGER_BAD:
					$this->checkTitle('Configuration of trigger prototypes');
					$this->zbxTestTextPresent('CONFIGURATION OF TRIGGER PROTOTYPES');
					$this->assertElementPresent("//div[@id='tab_triggersTab' and text()='Trigger prototype']");
					foreach ($data['errors'] as $msg) {
						$this->zbxTestTextPresent($msg);
					}
					$this->zbxTestTextPresent(array('Name', 'Expression', 'Description'));
					break;
			}
		}

		if (isset($data['hostCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Trigger prototypes");

			$this->zbxTestTextPresent($this->template.": $description");
			$this->zbxTestClickWait("link=$description");

			$this->zbxTestTextPresent('Parent triggers');
			$this->assertElementPresent('link='.$this->template);
			$this->assertElementValue('description', $description);
			if (isset($data['expressionHost'])) {
				$expressionHost = $data['expressionHost'];
				$this->assertElementValue('expression', $expressionHost);
			}
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT description, triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['description'], $description);
				$templateid = $row['triggerid'];
			}
			// host
			$result = DBselect("SELECT description FROM triggers where description = '".$description."'  AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['description'], $description);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT description, triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$templateid = $row['triggerid'];
			}
			$result = DBselect("SELECT triggerid FROM triggers where description = '".$description."'  AND templateid = ".$templateid."");
			while ($row = DBfetch($result)) {
				$triggerId = $row['triggerid'];
			}

			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait('link='.$this->host);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Trigger prototypes");

			$this->zbxTestCheckboxSelect("g_triggerid_$triggerId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent(array('ERROR: Cannot delete triggers', 'Cannot delete templated trigger'));
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT triggerid FROM triggers where description = '".$description."' limit 1");
			while ($row = DBfetch($result)) {
				$triggerId = $row['triggerid'];
			}

			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait('link='.$this->template);
			$this->zbxTestClickWait("link=Discovery rules");
			$this->zbxTestClickWait('link='.$this->discoveryRule);
			$this->zbxTestClickWait("link=Trigger prototypes");

			$this->zbxTestCheckboxSelect("g_triggerid_$triggerId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Triggers deleted');

			$this->zbxTestTextNotPresent($this->template.": $description");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceTriggerPrototype_Teardown() {
		DBrestore_tables('triggers');
	}
}
