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

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

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
		$this->zbxTestClickWait('link='.$this->hostName);

		$this->zbxTestClick('tab_templateTab');
		$this->zbxTestClick('//*[@id="add"][@value="Add"]');

		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->zbxTestCheckboxSelect('//*[@value="Template App Zabbix Agent"]');
		$this->zbxTestClick('select');

		$this->selectWindow(null);
		$this->wait();
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent('Host updated');
	}

	// Returns all possible item data
	public static function dataCreate() {
	// result, template, itemName, keyName, errorMsg
		return array(
			array(
				ITEM_GOOD,
				'Inheritance test template',
				'Test LLD item1',
				'test-general-item',
				array()
					),
			// Dublicated item on Template inheritance test host
			array(
				ITEM_BAD,
				'Template App Zabbix Agent',
				'Test LLD item1',
				'test-general-item',
				array(
						'ERROR: Cannot add item',
						'Item "test-general-item" already exists on "Template inheritance test host", inherited from '.
							'another template.'
						)
				),
			// Item added to Template inheritance test host
			array(
				ITEM_GOOD,
				'Template App Zabbix Agent',
				'Test LLD item2',
				'test-additional-item',
				array()
				)
			);
	}

	/**
	 * @dataProvider dataCreate
	 */
	public function testFormItem_Create($result, $template, $itemName, $keyName, $errorMsgs) {
		$this->zbxTestLogin('templates.php');

		// create an item
		$this->zbxTestClickWait("link=$template");
		$this->zbxTestClickWait('link=Items');
		$this->zbxTestClickWait('form');

		$this->input_type('name', $itemName);
		$this->input_type('key', $keyName);
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestDropdownSelect('value_type', 'Numeric (unsigned)');
		$this->zbxTestDropdownSelect('data_type', 'Octal');
		$this->input_type('units', 'units');
		$this->zbxTestCheckboxSelect('multiplier');
		$this->input_type('formula', 3);
		$this->input_type('delay', '33');
		$this->input_type('history', '54');
		$this->input_type('trends', '55');
		$this->input_type('description', 'description');
		$this->zbxTestDropdownSelect('delta', 'Delta (simple change)');
		$this->zbxTestDropdownSelect('status','Enabled');

		$this->zbxTestClickWait('save');

		switch ($result) {
			case ITEM_GOOD:
				$this->zbxTestTextPresent('Item added');
				$this->checkTitle('Configuration of items');
				$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
				break;

			case ITEM_BAD:
				$this->checkTitle('Configuration of items');
				$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
				foreach ($errorMsgs as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent('Host');
				$this->zbxTestTextPresent('Name');
				$this->zbxTestTextPresent('Key');
				break;
		}

		switch ($result) {
			case ITEM_GOOD:
				// check that the inherited item matches the original
				$this->zbxTestOpenWait('hosts.php');
				$this->zbxTestClickWait('link='.$this->hostName);
				$this->zbxTestClickWait('link=Items');
				$this->zbxTestTextPresent("$template: $itemName");
				$this->zbxTestClickWait("link=$itemName");
				$this->assertElementValue('name', $itemName);
				$this->assertElementValue('key', $keyName);
				$this->assertElementValue('typename', 'Simple check');
				$this->assertElementValue('value_type_name', 'Numeric (unsigned)');
				$this->assertElementValue('data_type_name', 'Octal');
				$this->assertElementValue('units', 'units');
				$this->assertElementValue('formula', 3);
				$this->assertElementValue('delay', '33');
				$this->assertElementValue('history', '54');
				$this->assertElementValue('trends', '55');
				$this->assertElementText('description', 'description');
				$this->assertElementValue('delta_name', 'Delta (simple change)');
				break;
			case ITEM_BAD:
				break;
		}
	}

	public function testFormItem_unlinkHost(){
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);

		$this->zbxTestClick('tab_templateTab');
		sleep(1);
		$this->zbxTestClickWait('unlink_and_clear_10050');
		$this->zbxTestClickWait('save');

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
		$this->zbxTestClickWait('link='.$this->templateName);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");
		$this->zbxTestClickWait('form');

		$this->input_type('description', 'Test LLD trigger1');
		$this->input_type('expression', '{Inheritance test template:test-general-item.last(0)}=0');
		$this->zbxTestCheckboxSelect('type');
		$this->input_type('comments', 'comments');
		$this->input_type('url', 'url');
		$this->zbxTestClick('severity_label_2');
		$this->zbxTestCheckboxUnselect('status');

		$this->zbxTestClickWait('save');

		// check that the inherited trigger matches the original
		$this->zbxTestOpenWait('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Triggers']");

		$this->zbxTestTextPresent($this->templateName.': Test LLD trigger1');
		$this->zbxTestClickWait('link=Test LLD trigger1');

		$this->assertElementValue('description', 'Test LLD trigger1');
		$this->assertElementValue('expression', '{Template inheritance test host:test-general-item.last(0)}=0');
		$this->assertTrue($this->isChecked('type'));
		$this->assertElementText('comments', 'comments');
		$this->assertElementValue('url', 'url');
		$this->assertTrue($this->isChecked('severity_2'));
		$this->assertFalse($this->isChecked('status'));
	}

	/**
	 * Creates a new graph on the template and checks that the inherited graph matches the original.
	 *
	 * @todo
	 */
	public function testTemplateInheritance_CreateGraph() {
		$this->zbxTestLogin('templates.php');

		// create a graph
		$this->zbxTestClickWait('link='.$this->templateName);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");
		$this->zbxTestClickWait('form');

		$this->input_type('name', 'Test LLD graph1');
		$this->input_type('width', '950');
		$this->input_type('height', '250');
		$this->zbxTestDropdownSelect('graphtype', 'Normal');
		$this->zbxTestCheckboxUnselect('show_legend');
		$this->zbxTestCheckboxUnselect('show_work_period');
		$this->zbxTestCheckboxUnselect('show_triggers');
		$this->zbxTestCheckboxSelect('visible_percent_left');
		$this->input_type('percent_left', '4');
		$this->input_type('percent_right', '5');
		$this->zbxTestCheckboxSelect('visible_percent_right');
		$this->zbxTestDropdownSelect('ymin_type', 'Calculated');
		$this->zbxTestDropdownSelect('ymax_type', 'Calculated');
		$this->zbxTestClick('add_item');

		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->zbxTestClick('link=Test LLD item1');
		$this->selectWindow(null);
		$this->zbxTestClick('save');

		// check that the inherited graph matches the original
		$this->zbxTestOpenWait('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);
		$this->zbxTestClickWait("//div[@class='w']//a[text()='Graphs']");

		$this->zbxTestTextPresent($this->templateName.': Test LLD graph1');
		$this->zbxTestClickWait('link=Test LLD graph1');

		$this->assertElementValue('name', 'Test LLD graph1');
		$this->assertElementValue('width', '950');
		$this->assertElementValue('height', '250');
		$this->assertAttribute('//*[@id="graphtype"]/option[1]/@selected', 'selected');
		$this->assertFalse($this->isChecked('show_legend'));
		$this->assertFalse($this->isChecked('show_work_period'));
		$this->assertFalse($this->isChecked('show_triggers'));
		$this->assertTrue($this->isChecked('visible_percent_left'));
		$this->assertElementValue('percent_left', '4.00');
		$this->assertTrue($this->isChecked('visible_percent_right'));
		$this->assertElementValue('percent_right', '5.00');
		$this->assertAttribute('//*[@id="ymin_type"]/option[1]/@selected', 'selected');
		$this->assertAttribute('//*[@id="ymax_type"]/option[1]/@selected', 'selected');
		$this->zbxTestTextPresent('Template inheritance test host: Test LLD item1');
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
		$this->zbxTestClickWait('link='.$this->templateName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('form');

		$this->input_type('name', 'Test LLD');
		$this->input_type('key', 'test-lld');
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->input_type('delay', '31');
		$this->input_type('lifetime', '32');
		$this->input_type('filter_macro', 'macro');
		$this->input_type('filter_value', 'regexp');
		$this->input_type('description', 'description');
		$this->zbxTestDropdownSelect('status', 'Disabled');

		$this->zbxTestClickWait('save');

		// check that the inherited rule matches the original
		$this->zbxTestOpenWait('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestTextPresent($this->templateName.': Test LLD');
		$this->zbxTestClickWait('link=Test LLD');

		$this->assertElementValue('name', 'Test LLD');
		$this->assertElementValue('key', 'test-lld');
		$this->assertElementValue('typename', 'Simple check');
		$this->assertElementValue('delay', '31');
		$this->assertElementValue('lifetime', '32');
		$this->assertElementValue('filter_macro', 'macro');
		$this->assertElementValue('filter_value', 'regexp');
		$this->assertElementText('description', 'description');
		$this->assertDrowpdownValueText('status', 'Disabled');
	}

	/**
	 * Creates a new trigger prototype on the template and checks that the inherited item prototype matches
	 * the original.
	 *
	 * @todo match fields for different item types
	 * @todo match flexible intervals
	 * @todo match value mappings
	 */
	public function testTemplateInheritance_CreateItemPrototype() {
		$this->zbxTestLogin('templates.php');

		// create an item prototype
		$this->zbxTestClickWait('link='.$this->templateName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Test LLD');
		$this->zbxTestClickWait('link=Item prototypes');
		$this->zbxTestClickWait('form');

		$this->input_type('name', 'Test LLD item');
		$this->input_type('key', 'test-lld-item');
		$this->zbxTestDropdownSelect('type', 'Simple check');
		$this->zbxTestDropdownSelect('value_type', 'Numeric (unsigned)');
		$this->zbxTestDropdownSelect('data_type', 'Octal');
		$this->input_type('units', 'units');
		$this->zbxTestCheckboxSelect('multiplier');
		$this->input_type('formula', 3);
		$this->input_type('delay', '33');
		$this->input_type('history', '54');
		$this->input_type('trends', '55');
		$this->input_type('description', 'description');
		$this->zbxTestDropdownSelect('delta', 'Delta (simple change)');
		$this->zbxTestCheckboxUnselect('status');

		$this->zbxTestClickWait('save');

		// check that the inherited item prototype matches the original
		$this->zbxTestOpenWait('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Test LLD');
		$this->zbxTestClickWait('link=Item prototypes');
		$this->zbxTestTextPresent($this->templateName.': Test LLD item');
		$this->zbxTestClickWait('link=Test LLD item');

		$this->assertElementValue('name', 'Test LLD item');
		$this->assertElementValue('key', 'test-lld-item');
		$this->assertElementValue('typename', 'Simple check');
		$this->assertElementValue('value_type_name', 'Numeric (unsigned)');
		$this->assertElementValue('data_type_name', 'Octal');
		$this->assertElementValue('units', 'units');
		$this->assertElementValue('formula', 3);
		$this->assertElementValue('delay', '33');
		$this->assertElementValue('history', '54');
		$this->assertElementValue('trends', '55');
		$this->assertElementText('description', 'description');
		$this->assertElementValue('delta_name', 'Delta (simple change)');
	}

	/**
	 * Creates a new trigger prototype on the template and checks that the inherited trigger prototype matches
	 * the original.
	 *
	 */
	public function testTemplateInheritance_CreateTriggerPrototype() {
		$this->zbxTestLogin('templates.php');

		// create a trigger prototype
		$this->zbxTestClickWait('link='.$this->templateName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Test LLD');
		$this->zbxTestClickWait('link=Trigger prototypes');
		$this->zbxTestClickWait('form');

		$this->input_type('description', 'Test LLD trigger');
		$this->input_type('expression', '{Inheritance test template:test-lld-item.last(0)}=0');
		$this->zbxTestCheckboxSelect('type');
		$this->input_type('comments', 'comments');
		$this->input_type('url', 'url');
		$this->zbxTestClick('severity_label_2');
		$this->zbxTestCheckboxUnselect('status');

		$this->zbxTestClickWait('save');

		// check that the inherited trigger prototype matches the original
		$this->zbxTestOpenWait('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Test LLD');
		$this->zbxTestClickWait('link=Trigger prototypes');
		$this->zbxTestTextPresent($this->templateName.': Test LLD trigger');
		$this->zbxTestClickWait('link=Test LLD trigger');

		$this->assertElementValue('description', 'Test LLD trigger');
		$this->assertElementValue('expression', '{Template inheritance test host:test-lld-item.last(0)}=0');
		$this->assertTrue($this->isChecked('type'));
		$this->assertElementText('comments', 'comments');
		$this->assertElementValue('url', 'url');
		$this->assertTrue($this->isChecked('severity_2'));
		$this->assertFalse($this->isChecked('status'));
	}

	/**
	 * Creates a new graph prototype on the template and checks that the inherited graph prototype matches the original.
	 *
	 */
	public function testTemplateInheritance_CreateGraphPrototype() {
		$this->zbxTestLogin('templates.php');

		// create a graph
		$this->zbxTestClickWait('link='.$this->templateName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Test LLD');
		$this->zbxTestClickWait('link=Graph prototypes');
		$this->zbxTestClickWait('form');

		$this->input_type('name', 'Test LLD graph');
		$this->input_type('width', '950');
		$this->input_type('height', '250');
		$this->zbxTestDropdownSelect('graphtype', 'Normal');
		$this->zbxTestCheckboxUnselect('show_legend');
		$this->zbxTestCheckboxUnselect('show_work_period');
		$this->zbxTestCheckboxUnselect('show_triggers');
		$this->zbxTestCheckboxSelect('visible_percent_left');
		$this->input_type('percent_left', '4');
		$this->input_type('percent_right', '5');
		$this->zbxTestCheckboxSelect('visible_percent_right');
		$this->zbxTestDropdownSelect('ymin_type', 'Calculated');
		$this->zbxTestDropdownSelect('ymax_type', 'Calculated');

		$this->zbxTestClick('add_protoitem');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->zbxTestClick("//span[text()='Test LLD item']");
		$this->selectWindow(null);
		sleep(1);

		$this->zbxTestClick('add_item');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->zbxTestClick('link=Test LLD item1');
		$this->selectWindow(null);
		sleep(1);

		$this->zbxTestClick('save');

		// check that the inherited graph matches the original
		$this->zbxTestOpenWait('hosts.php');
		$this->zbxTestClickWait('link='.$this->hostName);
		$this->zbxTestClickWait('link=Discovery rules');
		$this->zbxTestClickWait('link=Test LLD');
		$this->zbxTestClickWait('link=Graph prototypes');

		$this->zbxTestTextPresent($this->templateName.': Test LLD graph');
		$this->zbxTestClickWait('link=Test LLD graph');

		$this->assertElementValue('name', 'Test LLD graph');
		$this->assertElementValue('width', '950');
		$this->assertElementValue('height', '250');
		$this->assertAttribute('//*[@id="graphtype"]/option[1]/@selected', 'selected');
		$this->assertFalse($this->isChecked('show_legend'));
		$this->assertFalse($this->isChecked('show_work_period'));
		$this->assertFalse($this->isChecked('show_triggers'));
		$this->assertTrue($this->isChecked('visible_percent_left'));
		$this->assertElementValue('percent_left', '4.00');
		$this->assertTrue($this->isChecked('visible_percent_right'));
		$this->assertElementValue('percent_right', '5.00');
		$this->assertAttribute('//*[@id="ymin_type"]/option[1]/@selected', 'selected');
		$this->assertAttribute('//*[@id="ymax_type"]/option[1]/@selected', 'selected');
		$this->zbxTestTextPresent('Template inheritance test host: Test LLD item');
		$this->zbxTestTextPresent('Template inheritance test host: Test LLD item1');
	}

	/**
	 * Restore the original tables.
	 */
	public function testTemplateInheritance_teardown() {
		DBrestore_tables('items');
	}
}
