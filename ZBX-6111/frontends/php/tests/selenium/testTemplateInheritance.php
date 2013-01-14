<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

	/**
	 * Creates a new item on the template and checks that the item matches the original.
	 *
	 * @todo
	 */
	public function testTemplateInheritance_CreateItem() {
		$this->login('templates.php');

		// create an item
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click('link=Items');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD item1');
		$this->input_type('key', 'test-general-item');
		$this->dropdown_select('type', 'Simple check');
		$this->dropdown_select('value_type', 'Numeric (unsigned)');
		$this->dropdown_select('data_type', 'Octal');
		$this->input_type('units', 'units');
		$this->checkbox_select('multiplier');
		$this->input_type('formula', 3);
		$this->input_type('delay', '33');
		$this->input_type('history', '54');
		$this->input_type('trends', '55');
		$this->input_type('description', 'description');
		$this->dropdown_select('delta', 'Delta (simple change)');
		$this->dropdown_select('status','Enabled');

		$this->button_click('save');
		$this->wait();

		// check that the inherited item matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click('link=Items');
		$this->wait();
		$this->ok($this->templateName.': Test LLD item1');
		$this->button_click('link=Test LLD item1');
		$this->wait();
		$this->assertElementValue('name', 'Test LLD item1');
		$this->assertElementValue('key', 'test-general-item');
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
	 * Creates a new trigger on the template and checks that the inherited trigger matches the original.
	 *
	 * @todo
	 */
	public function testTemplateInheritance_CreateTrigger() {
		$this->login('templates.php');

		// create a trigger
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Triggers']");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('description', 'Test LLD trigger1');
		$this->input_type('expression', '{Inheritance test template:test-general-item.last(0)}=0');
		$this->checkbox_select('type');
		$this->input_type('comments', 'comments');
		$this->input_type('url', 'url');
		$this->button_click('severity_label_2');
		$this->checkbox_unselect('status');

		$this->button_click('save');
		$this->wait();

		// check that the inherited trigger matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Triggers']");
		$this->wait();

		$this->ok($this->templateName.': Test LLD trigger1');
		$this->button_click('link=Test LLD trigger1');
		$this->wait();

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
		$this->login('templates.php');

		// create a graph
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Graphs']");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD graph1');
		$this->input_type('width', '950');
		$this->input_type('height', '250');
		$this->dropdown_select('graphtype', 'Normal');
		$this->checkbox_unselect('legend');
		$this->checkbox_unselect('showworkperiod');
		$this->checkbox_unselect('showtriggers');
		$this->checkbox_select('visible_percent_left');
		$this->input_type('percent_left', '4');
		$this->input_type('percent_right', '5');
		$this->checkbox_select('visible_percent_right');
		$this->dropdown_select('ymin_type', 'Calculated');
		$this->dropdown_select('ymax_type', 'Calculated');
		$this->button_click('add_item');

		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click('link=Test LLD item1');
		$this->selectWindow(null);
		$this->button_click('save');

		// check that the inherited graph matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Graphs']");
		$this->wait();

		$this->ok($this->templateName.': Test LLD graph1');
		$this->button_click('link=Test LLD graph1');
		$this->wait();

		$this->assertElementValue('name', 'Test LLD graph1');
		$this->assertElementValue('width', '950');
		$this->assertElementValue('height', '250');
		$this->assertAttribute('//*[@id="graphtype"]/option[1]/@selected', 'selected');
		$this->assertFalse($this->isChecked('legend'));
		$this->assertFalse($this->isChecked('showworkperiod'));
		$this->assertFalse($this->isChecked('showtriggers'));
		$this->assertTrue($this->isChecked('visible_percent_left'));
		$this->assertElementValue('percent_left', '4.00');
		$this->assertTrue($this->isChecked('visible_percent_right'));
		$this->assertElementValue('percent_right', '5.00');
		$this->assertAttribute('//*[@id="ymin_type"]/option[1]/@selected', 'selected');
		$this->assertAttribute('//*[@id="ymax_type"]/option[1]/@selected', 'selected');
		$this->ok('Template inheritance test host: Test LLD item1');
	}

	/**
	 * Creates a new LLD rule on the template and checks that the inherited LLD rule matches the original.
	 *
	 * @todo match fields for different LLD types
	 * @todo match flexible intervals
	 */
	public function testTemplateInheritance_CreateDiscovery() {
		$this->login('templates.php');

		// create an LLD rule
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD');
		$this->input_type('key', 'test-lld');
		$this->dropdown_select('type', 'Simple check');
		$this->input_type('delay', '31');
		$this->input_type('lifetime', '32');
		$this->input_type('filter_macro', 'macro');
		$this->input_type('filter_value', 'regexp');
		$this->input_type('description', 'description');
		$this->dropdown_select('status', 'Disabled');

		$this->button_click('save');
		$this->wait();

		// check that the inherited rule matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->ok($this->templateName.': Test LLD');
		$this->button_click('link=Test LLD');
		$this->wait();

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
		$this->login('templates.php');

		// create an item prototype
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('link=Test LLD');
		$this->wait();
		$this->button_click('link=Item prototypes');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD item');
		$this->input_type('key', 'test-lld-item');
		$this->dropdown_select('type', 'Simple check');
		$this->dropdown_select('value_type', 'Numeric (unsigned)');
		$this->dropdown_select('data_type', 'Octal');
		$this->input_type('units', 'units');
		$this->checkbox_select('multiplier');
		$this->input_type('formula', 3);
		$this->input_type('delay', '33');
		$this->input_type('history', '54');
		$this->input_type('trends', '55');
		$this->input_type('description', 'description');
		$this->dropdown_select('delta', 'Delta (simple change)');
		$this->checkbox_unselect('status');

		$this->button_click('save');
		$this->wait();

		// check that the inherited item prototype matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('link=Test LLD');
		$this->wait();
		$this->button_click('link=Item prototypes');
		$this->wait();
		$this->ok($this->templateName.': Test LLD item');
		$this->button_click('link=Test LLD item');
		$this->wait();

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
		$this->login('templates.php');

		// create a trigger prototype
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('link=Test LLD');
		$this->wait();
		$this->button_click('link=Trigger prototypes');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('description', 'Test LLD trigger');
		$this->input_type('expression', '{Inheritance test template:test-lld-item.last(0)}=0');
		$this->checkbox_select('type');
		$this->input_type('comments', 'comments');
		$this->input_type('url', 'url');
		$this->button_click('severity_label_2');
		$this->checkbox_unselect('status');

		$this->button_click('save');
		$this->wait();

		// check that the inherited trigger prototype matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('link=Test LLD');
		$this->wait();
		$this->button_click('link=Trigger prototypes');
		$this->wait();
		$this->ok($this->templateName.': Test LLD trigger');
		$this->button_click('link=Test LLD trigger');
		$this->wait();

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
		$this->login('templates.php');

		// create a graph
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('link=Test LLD');
		$this->wait();
		$this->button_click('link=Graph prototypes');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD graph');
		$this->input_type('width', '950');
		$this->input_type('height', '250');
		$this->dropdown_select('graphtype', 'Normal');
		$this->checkbox_unselect('legend');
		$this->checkbox_unselect('showworkperiod');
		$this->checkbox_unselect('showtriggers');
		$this->checkbox_select('visible_percent_left');
		$this->input_type('percent_left', '4');
		$this->input_type('percent_right', '5');
		$this->checkbox_select('visible_percent_right');
		$this->dropdown_select('ymin_type', 'Calculated');
		$this->dropdown_select('ymax_type', 'Calculated');

		$this->button_click('add_protoitem');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click("//span[text()='Test LLD item']");
		$this->selectWindow(null);
		sleep(1);

		$this->button_click('add_item');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->button_click('link=Test LLD item1');
		$this->selectWindow(null);
		sleep(1);

		$this->button_click('save');

		// check that the inherited graph matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->button_click('link='.$this->hostName);
		$this->wait();
		$this->button_click('link=Discovery rules');
		$this->wait();
		$this->button_click('link=Test LLD');
		$this->wait();
		$this->button_click('link=Graph prototypes');
		$this->wait();

		$this->ok($this->templateName.': Test LLD graph');
		$this->button_click('link=Test LLD graph');
		$this->wait();

		$this->assertElementValue('name', 'Test LLD graph');
		$this->assertElementValue('width', '950');
		$this->assertElementValue('height', '250');
		$this->assertAttribute('//*[@id="graphtype"]/option[1]/@selected', 'selected');
		$this->assertFalse($this->isChecked('legend'));
		$this->assertFalse($this->isChecked('showworkperiod'));
		$this->assertFalse($this->isChecked('showtriggers'));
		$this->assertTrue($this->isChecked('visible_percent_left'));
		$this->assertElementValue('percent_left', '4.00');
		$this->assertTrue($this->isChecked('visible_percent_right'));
		$this->assertElementValue('percent_right', '5.00');
		$this->assertAttribute('//*[@id="ymin_type"]/option[1]/@selected', 'selected');
		$this->assertAttribute('//*[@id="ymax_type"]/option[1]/@selected', 'selected');
		$this->ok('Template inheritance test host: Test LLD item');
		$this->ok('Template inheritance test host: Test LLD item1');
	}

	/**
	 * Checks all error messages and inccorect entries for a new item on the inheritance template.
	 *
	 */
	public function testTemplateInheritance_ItemError(){
		$this->login('templates.php');

		// create an item
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click('link=Items');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD itemErr');
		$this->input_type('key', 'test-error-item');
		$this->dropdown_select('type', 'Simple check');
		$this->dropdown_select('value_type', 'Numeric (unsigned)');
		$this->dropdown_select('data_type', 'Octal');
		$this->input_type('units', 'units');
		$this->checkbox_select('multiplier');
		$this->input_type('formula', 3);
		$this->input_type('delay', '33');
		$this->input_type('history', '54');
		$this->input_type('trends', '55');
		$this->input_type('description', 'description');
		$this->dropdown_select('delta', 'Delta (simple change)');
		$this->dropdown_select('status','Enabled');
		$this->button_click('save');
		$this->wait();

		$this->button_click('form');
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Name": cannot be empty.');

		$this->input_type('name', 'Test LLD itemErr');
		$this->input_type('key', 'test-error-item');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot add item');
		$this->ok('Item with key "test-error-item" already exists on "Inheritance test template".');

		$this->input_type('key', '');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Key": cannot be empty.');
		$this->input_type('key', 'test-error-item');

		$this->input_type('delay', 'error');
		$this->button_click('save');
		$this->wait();
		$this->nok('Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.');

		$this->input_type('delay', '86401');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Update interval (in sec)": must be between 0 and 86400.');
		$this->input_type('delay', '0');

		$this->input_type('new_delay_flex_delay','50000');
		$this->input_type('new_delay_flex_period','1-11,00:00-24:00');
		$this->button_click('add_delay_flex');
		$this->wait();
		$this->ok('ERROR: Invalid time period');
		$this->ok('Incorrect time period "1-11,00:00-24:00".');

		$this->input_type('new_delay_flex_delay','50000');
		$this->input_type('new_delay_flex_period','1-7,00:00-25:00');
		$this->button_click('add_delay_flex');
		$this->wait();
		$this->ok('ERROR: Invalid time period');
		$this->ok('Incorrect time period "1-7,00:00-25:00".');

		$this->input_type('new_delay_flex_period','1-7,24:00-09:00');
		$this->button_click('add_delay_flex');
		$this->wait();
		$this->ok('ERROR: Invalid time period');
		$this->ok('Incorrect time period "1-7,24:00-09:00" start time must be less than end time.');
	}

	/**
	 * Checks all error messages and inccorect entries for a new trigger on the inheritance template.
	 *
	 */
	public function testTemplateInheritance_TriggerError(){
		$this->login('templates.php');

		// create a trigger
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Triggers']");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Name": cannot be empty.');

		$this->input_type('description', 'Test LLD triggerErr');
		$this->input_type('expression', '');
		$this->checkbox_select('type');
		$this->input_type('comments', 'comments');
		$this->input_type('url', 'url');
		$this->button_click('severity_label_2');
		$this->checkbox_unselect('status');
		$this->button_click('save');
		$this->wait();

		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "expression": cannot be empty.');

		$this->input_type('expression', '123');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot add trigger');
		$this->ok('Trigger expression must contain at least one host:key reference.');

		$this->input_type('expression', 'abcd');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot add trigger');
		$this->ok('Incorrect trigger expression. Check expression part starting from "abcd".');
	}

	/**
	 * Checks error messages and inccorect entries for a new graph on the inheritance template.
	 *
	 */
	public function testTemplateInheritance_GraphError(){
		$this->login('templates.php');

		// create a graph
		$this->button_click('link='.$this->templateName);
		$this->wait();
		$this->button_click("//div[@class='w']//a[text()='Graphs']");
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', 'Test LLD graphErr');
		$this->dropdown_select('graphtype', 'Normal');
		$this->checkbox_unselect('legend');
		$this->checkbox_unselect('showworkperiod');
		$this->checkbox_unselect('showtriggers');
		$this->checkbox_select('visible_percent_left');
		$this->input_type('percent_left', '4');
		$this->input_type('percent_right', '5');
		$this->checkbox_select('visible_percent_right');
		$this->dropdown_select('ymin_type', 'Calculated');
		$this->dropdown_select('ymax_type', 'Calculated');
		$this->button_click('add_item');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		sleep(1);
		$this->button_click('link=Test LLD itemErr');
		$this->selectWindow(null);
		$this->button_click('save');
		$this->wait();

		$this->button_click('form');
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Name": cannot be empty.');

		$this->input_type('name', 'Test LLD graphErr');
		$this->input_type('width', '-1');
		$this->input_type('height', '-2');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Width (min:20, max:65535)": must be between 20 and 65535.');
		$this->ok('Warning. Incorrect value for field "Height (min:20, max:65535)": must be between 20 and 65535.');

		$this->input_type('width', '65536');
		$this->input_type('height', '19');
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Page received incorrect data');
		$this->ok('Warning. Incorrect value for field "Width (min:20, max:65535)": must be between 20 and 65535.');
		$this->ok('Warning. Incorrect value for field "Height (min:20, max:65535)": must be between 20 and 65535.');

		$this->input_type('width', 'a');
		$this->input_type('height', 'b');
		$this->button_click('save');
		$this->wait();
		$this->assertElementValue('width', '0');
		$this->assertElementValue('height', '0');

		$this->input_type('width', '900');
		$this->input_type('height', '200');
		$this->button_click('add_item');
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		sleep(1);
		$this->button_click('link=Test LLD itemErr');
		$this->selectWindow(null);
		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot add graph');
		$this->ok('Graph with name "Test LLD graphErr" already exists in graphs or graph prototypes');
	}

	/**
	 * Restore the original tables.
	 */
	public function testTemplateInheritance_teardown() {
		DBrestore_tables('items');
	}
}
