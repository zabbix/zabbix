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

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testTemplateInheritance extends CWebTest {

	/**
	 * The name of the test template created in the test data set.
	 *
	 * @var string
	 */
	protected $templateName = 'Inheritance test template';

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $hostName = 'Template inheritance test host';


	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testTemplateInheritance_setup() {
		DBsave_tables('items');
	}

	public function testFormItem_linkHost(){
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);

		$this->zbxTestClickWait('tab_templateTab');

		$this->zbxTestAssertElementPresentId('add_templates_');
		$this->zbxTestInputTypeByXpath('//input[@class="input"]', 'Template App Zabbix Agent');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//span[@class='suggest-found']"));
		$this->zbxTestClickXpath("//span[@class='suggest-found']");
		$this->zbxTestClickWait('add_template');

		$this->zbxTestTextPresent('Template App Zabbix Agent');
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent('Host updated');
	}

	public static function dataCreate() {
	// result, template, itemName, keyName, errorMsg
		return [
			[
				TEST_GOOD,
				'Inheritance test template',
				'Test LLD item1',
				'test-general-item',
				[]
					],
			// Duplicated item on Template inheritance test host
			[
				TEST_BAD,
				'Template App Zabbix Agent',
				'Test LLD item1',
				'test-general-item',
				[
						'Cannot add item',
						'Item "test-general-item" already exists on "Template inheritance test host", inherited from '.
							'another template.'
						]
				],
			// Item added to Template inheritance test host
			[
				TEST_GOOD,
				'Template App Zabbix Agent',
				'Test LLD item2',
				'test-additional-item',
				[]
				]
			];
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormItem_Create($result, $template, $itemName, $keyName, $errorMsgs) {
		$this->zbxTestLogin('templates.php');

		$this->zbxTestClickLinkText($template);
		$this->zbxTestClickLinkTextWait('Items');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('name', $itemName);
		$this->zbxTestInputType('key', $keyName);
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestDropdownSelect('value_type', 'Numeric (unsigned)');
		$this->zbxTestDropdownSelect('data_type', 'Octal');
		$this->zbxTestInputType('units', 'units');
		$this->zbxTestCheckboxSelect('multiplier');
		$this->zbxTestInputType('formula', 3);
		$this->zbxTestInputType('delay', '33');
		$this->zbxTestInputType('history', '54');
		$this->zbxTestInputType('trends', '55');
		$this->zbxTestInputType('description', 'description');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestDropdownSelect('delta', 'Delta (simple change)');

		$this->zbxTestClickWait('add');

		switch ($result) {
			case TEST_GOOD:
				$this->zbxTestTextPresent('Item added');
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestCheckHeader('Items');
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestCheckHeader('Items');
				foreach ($errorMsgs as $msg) {
					$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add item');
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent('Host');
				$this->zbxTestTextPresent('Name');
				$this->zbxTestTextPresent('Key');
				break;
		}

		switch ($result) {
			case TEST_GOOD:
				// check that the inherited item matches the original
				$this->zbxTestOpen('hosts.php');
				$this->zbxTestClickLinkText($this->hostName);
				$this->zbxTestClickLinkTextWait('Items');
				$this->zbxTestCheckHeader('Items');
				$this->zbxTestAssertElementText("//a[text()='".$itemName."']/parent::td", "$template: $itemName");
				$this->zbxTestClickLinkText($itemName);
				$this->zbxTestAssertElementValue('name', $itemName);
				$this->zbxTestAssertElementValue('key', $keyName);
				$this->zbxTestAssertElementValue('typename', 'Simple check');
				$this->zbxTestAssertElementValue('value_type_name', 'Numeric (unsigned)');
				$this->zbxTestAssertElementValue('data_type_name', 'Octal');
				$this->zbxTestAssertElementValue('units', 'units');
				$this->zbxTestAssertElementValue('formula', 3);
				$this->zbxTestAssertElementValue('delay', '33');
				$this->zbxTestAssertElementValue('history', '54');
				$this->zbxTestAssertElementValue('trends', '55');
				$this->zbxTestAssertElementText('//*[@name="description"]', 'description');
				$this->zbxTestAssertElementValue('delta_name', 'Delta (simple change)');
				$this->zbxTestTextPresent('Parent items');
				$this->zbxTestTextPresent($template);
				break;
			case TEST_BAD:
				break;
		}
	}

	public function testFormItem_unlinkHost(){

		$sql = "select hostid from hosts where host='Template App Zabbix Agent';";
		$this->assertEquals(1, DBcount($sql));
		$row = DBfetch(DBselect($sql));
		$hostid = $row['hostid'];

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);

		$this->zbxTestClickWait('tab_templateTab');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::id('unlink_and_clear_'.$hostid));
		$this->zbxTestTextPresent('Template App Zabbix Agent');
		$this->zbxTestClickWait('unlink_and_clear_'.$hostid);
		$this->zbxTestTextNotPresent('Template App Zabbix Agent');

		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Host updated');
	}

	/**
	 * Creates a new trigger on the template and checks that the inherited trigger matches the original.
	 *
	 * @todo
	 */
	public function testTemplateInheritance_CreateTrigger() {
		$this->zbxTestLogin('templates.php');

		// create a trigger
		$this->zbxTestClickLinkText($this->templateName);
		$this->zbxTestClickLinkTextWait('Triggers');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('description', 'Test LLD trigger1');
		$this->zbxTestInputType('expression', '{Inheritance test template:test-general-item.last(0)}=0');
		$this->zbxTestCheckboxSelect('type');
		$this->zbxTestInputType('comments', 'comments');
		$this->zbxTestInputType('url', 'url');
		$this->zbxTestClickXpath("//label[@for='priority_2']");
		$this->zbxTestCheckboxSelect('status', false);

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Trigger added');

		// check that the inherited trigger matches the original
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);
		$this->zbxTestClickLinkTextWait('Triggers');

		$this->zbxTestAssertElementText("//a[text()='Test LLD trigger1']/parent::td", "$this->templateName: Test LLD trigger1");
		$this->zbxTestClickLinkText('Test LLD trigger1');

		$this->zbxTestAssertElementValue('description', 'Test LLD trigger1');
		$this->zbxTestAssertElementValue('expression', '{Template inheritance test host:test-general-item.last(0)}=0');
		$this->assertTrue($this->zbxTestCheckboxSelected('type'));
		$this->zbxTestAssertElementText('//*[@name="comments"]', 'comments');
		$this->zbxTestAssertElementValue('url', 'url');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_2'));
		$this->assertFalse($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestTextPresent('Parent triggers');
	}

	/**
	 * Creates a new graph on the template and checks that the inherited graph matches the original.
	 *
	 * @todo
	 */
	public function testTemplateInheritance_CreateGraph() {
		$this->zbxTestLogin('templates.php');

		// create a graph
		$this->zbxTestClickLinkText($this->templateName);
		$this->zbxTestClickLinkTextWait('Graphs');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('name', 'Test LLD graph1');
		$this->zbxTestInputType('width', '950');
		$this->zbxTestInputType('height', '250');
		$this->zbxTestDropdownSelect('graphtype', 'Normal');
		$this->zbxTestCheckboxSelect('show_legend', false);
		$this->zbxTestCheckboxSelect('show_work_period', false);
		$this->zbxTestCheckboxSelect('show_triggers', false);
		$this->zbxTestCheckboxSelect('visible_percent_left');
		$this->zbxTestCheckboxSelect('visible_percent_right');
		$this->zbxTestInputType('percent_left', '4');
		$this->zbxTestInputType('percent_right', '5');
		$this->zbxTestDropdownSelect('ymin_type', 'Calculated');
		$this->zbxTestDropdownSelect('ymax_type', 'Calculated');
		$this->zbxTestClick('add_item');

		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestClickLinkTextWait('Test LLD item1');
		$this->webDriver->switchTo()->window('');
		$this->zbxTestClick('add');
		$this->zbxTestTextPresent('Graph added');

		// check that the inherited graph matches the original
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);
		$this->zbxTestClickLinkTextWait('Graphs');

		$this->zbxTestAssertElementText("//a[text()='Test LLD graph1']/parent::td", "$this->templateName: Test LLD graph1");
		$this->zbxTestClickLinkText('Test LLD graph1');

		$this->zbxTestAssertElementValue('name', 'Test LLD graph1');
		$this->zbxTestAssertElementValue('width', '950');
		$this->zbxTestAssertElementValue('height', '250');
		$this->zbxTestDrowpdownAssertSelected('graphtype', 'Normal');
		$this->assertFalse($this->zbxTestCheckboxSelected('show_legend'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_work_period'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_triggers'));
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_left'));
		$this->zbxTestAssertElementValue('percent_left', '4.00');
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_right'));
		$this->zbxTestAssertElementValue('percent_right', '5.00');
		$this->zbxTestDrowpdownAssertSelected('ymin_type', 'Calculated');
		$this->zbxTestDrowpdownAssertSelected('ymax_type', 'Calculated');
		$this->zbxTestTextPresent('Parent graphs');
		$this->zbxTestTextPresent($this->hostName.': Test LLD item1');
	}

	/**
	 * Creates a new LLD rule on the template and checks that the inherited LLD rule matches the original.
	 *
	 * @todo match fields for different LLD types
	 * @todo match flexible intervals
	 */
	public function testTemplateInheritance_CreateDiscovery() {
		$this->zbxTestLogin('templates.php');

		// create an LLD rule
		$this->zbxTestClickLinkText($this->templateName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('name', 'Test LLD');
		$this->zbxTestInputType('key', 'test-lld');
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestInputType('delay', '31');
		$this->zbxTestInputType('lifetime', '32');
		$this->zbxTestInputType('description', 'description');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Discovery rule created');

		// check that the inherited rule matches the original
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestAssertElementText("//a[text()='Test LLD']/parent::td", "$this->templateName: Test LLD");
		$this->zbxTestClickLinkText('Test LLD');

		$this->zbxTestAssertElementValue('name', 'Test LLD');
		$this->zbxTestAssertElementValue('key', 'test-lld');
		$this->zbxTestAssertElementValue('typename', 'Simple check');
		$this->zbxTestAssertElementValue('delay', '31');
		$this->zbxTestAssertElementValue('lifetime', '32');
		$this->zbxTestAssertElementText('//*[@name="description"]', 'description');
		$this->assertTrue($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestTextPresent('Parent discovery rules');
		$this->zbxTestTextPresent($this->templateName);
	}

	/**
	 * Creates a new item prototype on the template and checks that the inherited item prototype matches
	 * the original.
	 *
	 * @todo match fields for different item types
	 * @todo match flexible intervals
	 * @todo match value mappings
	 */
	public function testTemplateInheritance_CreateItemPrototype() {
		$this->zbxTestLogin('templates.php');

		// create an item prototype
		$this->zbxTestClickLinkText($this->templateName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait('Test LLD');
		$this->zbxTestClickLinkTextWait('Item prototypes');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('name', 'Test LLD item');
		$this->zbxTestInputType('key', 'test-lld-item');
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestDropdownSelect('value_type', 'Numeric (unsigned)');
		$this->zbxTestDropdownSelect('data_type', 'Octal');
		$this->zbxTestInputType('units', 'units');
		$this->zbxTestCheckboxSelect('multiplier');
		$this->zbxTestInputType('formula', 3);
		$this->zbxTestInputType('delay', '33');
		$this->zbxTestInputType('history', '54');
		$this->zbxTestInputType('trends', '55');
		$this->zbxTestInputType('description', 'description');
		$this->zbxTestDropdownSelect('delta', 'Delta (simple change)');
		$this->zbxTestCheckboxSelect('status', false);

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Item prototype added');
		$this->zbxTestTextPresent('Test LLD item');

		// check that the inherited item prototype matches the original
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);
		$this->zbxTestCheckHeader('Hosts');
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');
		$this->zbxTestClickLinkTextWait('Test LLD');
		$this->zbxTestClickLinkTextWait('Item prototypes');
		$this->zbxTestAssertElementText("//a[text()='Test LLD item']/parent::td", "$this->templateName: Test LLD item");
		$this->zbxTestClickLinkText('Test LLD item');

		$this->zbxTestAssertElementValue('name', 'Test LLD item');
		$this->zbxTestAssertElementValue('key', 'test-lld-item');
		$this->zbxTestAssertElementValue('typename', 'Simple check');
		$this->zbxTestAssertElementValue('value_type_name', 'Numeric (unsigned)');
		$this->zbxTestAssertElementValue('data_type_name', 'Octal');
		$this->zbxTestAssertElementValue('units', 'units');
		$this->zbxTestAssertElementValue('formula', 3);
		$this->zbxTestAssertElementValue('delay', '33');
		$this->zbxTestAssertElementValue('history', '54');
		$this->zbxTestAssertElementValue('trends', '55');
		$this->zbxTestAssertElementText('//*[@name="description"]', 'description');
		$this->zbxTestAssertElementValue('delta_name', 'Delta (simple change)');
		$this->zbxTestTextPresent('Parent items');
		$this->zbxTestTextPresent($this->templateName);
	}

	/**
	 * Creates a new trigger prototype on the template and checks that the inherited trigger prototype matches
	 * the original.
	 *
	 */
	public function testTemplateInheritance_CreateTriggerPrototype() {
		$this->zbxTestLogin('templates.php');

		// create a trigger prototype
		$this->zbxTestClickLinkText($this->templateName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait('Test LLD');
		$this->zbxTestClickLinkTextWait('Trigger prototypes');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('description', 'Test LLD trigger');
		$this->zbxTestInputType('expression', '{Inheritance test template:test-lld-item.last(0)}=0');
		$this->zbxTestCheckboxSelect('type');
		$this->zbxTestInputType('comments', 'comments');
		$this->zbxTestInputType('url', 'url');
		$this->zbxTestClickXpath("//label[@for='priority_2']");
		$this->zbxTestCheckboxSelect('status', false);

		$this->zbxTestClickWait('add');
		$this->zbxTestTextPresent('Trigger prototype added');
		$this->zbxTestTextPresent('Test LLD trigger');

		$sql = "SELECT triggerid FROM triggers WHERE description='Test LLD trigger' AND status='1' AND templateid IS NULL";
		$this->assertEquals(1, DBcount($sql), 'Trigger prototype has not been added into Zabbix DB');

		$sql = "SELECT triggerid FROM triggers WHERE description='Test LLD trigger' AND status='1' AND templateid IS NOT NULL";
		$this->assertEquals(1, DBcount($sql), 'Trigger prototype has not been added into Zabbix DB');

		// check that the inherited trigger prototype matches the original
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait('Test LLD');
		$this->zbxTestClickLinkTextWait('Trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestAssertElementText("//a[text()='Test LLD trigger']/parent::td", "$this->templateName: Test LLD trigger");
		$this->zbxTestClickLinkText('Test LLD trigger');

		$this->zbxTestAssertElementValue('description', 'Test LLD trigger');
		$this->zbxTestAssertElementValue('expression', '{Template inheritance test host:test-lld-item.last(0)}=0');
		$this->assertTrue($this->zbxTestCheckboxSelected('type'));
		$this->zbxTestAssertElementText('//*[@name="comments"]', 'comments');
		$this->zbxTestAssertElementValue('url', 'url');
		$this->assertTrue($this->zbxTestCheckboxSelected('priority_2'));
		$this->assertFalse($this->zbxTestCheckboxSelected('status'));
		$this->zbxTestTextPresent('Parent triggers');
		$this->zbxTestTextPresent($this->templateName);
	}

	/**
	 * Creates a new graph prototype on the template and checks that the inherited graph prototype matches the original.
	 *
	 */
	public function testTemplateInheritance_CreateGraphPrototype() {
		$this->zbxTestLogin('templates.php');

		// create a graph
		$this->zbxTestClickLinkText($this->templateName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait('Test LLD');
		$this->zbxTestClickLinkTextWait('Graph prototypes');
		$this->zbxTestClickWait('form');

		$this->zbxTestInputType('name', 'Test LLD graph');
		$this->zbxTestInputType('width', '950');
		$this->zbxTestInputType('height', '250');
		$this->zbxTestDropdownSelect('graphtype', 'Normal');
		$this->zbxTestCheckboxSelect('show_legend', false);
		$this->zbxTestCheckboxSelect('show_work_period', false);
		$this->zbxTestCheckboxSelect('show_triggers', false);
		$this->zbxTestCheckboxSelect('visible_percent_left');
		$this->zbxTestCheckboxSelect('visible_percent_right');
		$this->zbxTestInputType('percent_left', '4');
		$this->zbxTestInputType('percent_right', '5');
		$this->zbxTestDropdownSelect('ymin_type', 'Calculated');
		$this->zbxTestDropdownSelect('ymax_type', 'Calculated');

		$this->zbxTestClick('add_protoitem');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestClickLinkTextWait('Test LLD item');
		$this->webDriver->switchTo()->window('');
		$this->zbxTestTextPresent($this->templateName.': Test LLD item');

		$this->zbxTestClick('add_item');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestClickLinkTextWait('Test LLD item1');
		$this->webDriver->switchTo()->window('');
		$this->zbxTestTextPresent($this->templateName.': Test LLD item1');

		$this->zbxTestClick('add');
		$this->zbxTestTextPresent('Graph prototype added');
		$this->zbxTestTextPresent('Test LLD graph');

		// check that the inherited graph matches the original
		$this->zbxTestOpen('hosts.php');
		$this->zbxTestClickLinkText($this->hostName);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait('Test LLD');
		$this->zbxTestClickLinkTextWait('Graph prototypes');

		$this->zbxTestAssertElementText("//a[text()='Test LLD graph']/parent::td", "$this->templateName: Test LLD graph");
		$this->zbxTestClickLinkText('Test LLD graph');

		$this->zbxTestAssertElementValue('name', 'Test LLD graph');
		$this->zbxTestAssertElementValue('width', '950');
		$this->zbxTestAssertElementValue('height', '250');
		$this->zbxTestDrowpdownAssertSelected('graphtype', 'Normal');
		$this->assertFalse($this->zbxTestCheckboxSelected('show_legend'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_work_period'));
		$this->assertFalse($this->zbxTestCheckboxSelected('show_triggers'));
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_left'));
		$this->zbxTestAssertElementValue('percent_left', '4.00');
		$this->assertTrue($this->zbxTestCheckboxSelected('visible_percent_right'));
		$this->zbxTestAssertElementValue('percent_right', '5.00');
		$this->zbxTestDrowpdownAssertSelected('ymin_type', 'Calculated');
		$this->zbxTestDrowpdownAssertSelected('ymax_type', 'Calculated');
		$this->zbxTestTextPresent($this->hostName.': Test LLD item');
		$this->zbxTestTextPresent($this->hostName.': Test LLD item1');
		$this->zbxTestTextPresent('Parent graphs');
		$this->zbxTestTextPresent($this->templateName);
	}

	/**
	 * Restore the original tables.
	 */
	public function testTemplateInheritance_teardown() {
		DBrestore_tables('items');
	}
}
