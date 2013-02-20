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

define('TEST_GOOD', 0);
define('TEST_BAD', 1);

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
		DBsave_tables('hosts');
	}


	// returns data for simple template checks
	public static function simple() {
		return array(
			array(
				array('expected' => TEST_GOOD,
					'template' => 'Inheritance test template',
					'host'=> 'Template inheritance test host',
					'hostId' => 30000,
					'itemAdd' => 'itemSimple',
					'itemKey' => 'key-template-simple',
					'triggerAdd' => 'triggerSimple',
					'graphAdd' => 'graphSimple',
					'unlinkCheck' => 'Template App Zabbix Agent',
					'unlinkHostId' => 10050,
					'hostCheck' => true,
					'dbCheck' => true)
			),
			array(
				array('expected' => TEST_GOOD,
					'template' => 'Inheritance test template',
					'host'=> 'Template inheritance test host',
					'hostId' => 30000,
					'itemAdd' => 'itemName',
					'itemKey' => 'key-template-item',
					'graphAdd' => 'graphName',
					'hostCheck' => true)
			),
			array(
				array('expected' => TEST_GOOD,
					'template' => 'Inheritance test template',
					'host'=> 'Template inheritance test host',
					'hostId' => 30000,
					'itemAdd' => 'itemTrigger',
					'itemKey' => 'key-template-trigger',
					'triggerAdd' => 'triggerName',
					'hostCheck' => true,
					'dbCheck' => true)
			)
		);
	}

	/**
	 * @dataProvider simple
	 */
	public function testTemplateInheritance_simpleCreate($data) {
		$this->login('templates.php');

		$template = $data['template'];
		$host = $data['host'];
		$hostId = $data['hostId'];

		// check template is linked to host TODO ask if needs to be checked
		$this->open('hosts.php');
		$this->wait();
		$this->button_click("link=$host");
		$this->wait();
		$this->button_click('tab_templateTab');
		sleep(1);
		$this->ok('Inheritance test template');
		$this->assertElementPresent("unlink_and_clear_$hostId");

		if (isset($data['itemAdd'])) {
			$itemName = $data['itemAdd'];
			$keyName = $data['itemKey'];

			$this->open('templates.php');
			$this->button_click("link=$template");
			$this->wait();
			$this->button_click('link=Items');
			$this->wait();
			$this->button_click('form');
			$this->wait();

			$this->input_type('name', $itemName);
			$this->input_type('key', $data['itemKey']);
			$this->button_click('save');
			$this->wait();

// TODO if item good-check its added/bad-check errors

			if (isset($data['hostCheck'])) {
				$this->open('hosts.php');
				$this->wait();
				$this->button_click("link=$host");
				$this->wait();
				$this->button_click('link=Items');
				$this->wait();

				$this->ok("$template: $itemName");
				$this->button_click("link=$itemName");
				$this->wait();
				$this->assertElementValue('name', $itemName);
				$this->assertElementValue('key', $keyName);
			}

			if (isset($data['dbCheck'])) {
				$result = DBselect("SELECT name, key_, value_type, data_type FROM items where key_ = '".$keyName."'");
				while ($row = DBfetch($result)) {
					$this->assertEquals($row['name'], $itemName);
					$this->assertEquals($row['key_'], $keyName);
				}
			}
		}

		if (isset($data['triggerAdd'])) {
			$this->open('templates.php');
			$this->button_click("link=$template");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Triggers']");
			$this->wait();
			$this->button_click('form');
			$this->wait();

			$description = $data['triggerAdd'];
			$expression = '{'.$template.':'.$keyName.'.last(0)}=0';
			$this->input_type('description', $description);
			$this->input_type('expression', $expression);
			$this->button_click('save');
			$this->wait();

// TODO if trigger good-check its added/bad-check errors

			if (isset($data['hostCheck'])) {
				$this->open('hosts.php');
				$this->wait();
				$this->button_click("link=$host");
				$this->wait();
				$this->button_click("//div[@class='w']//a[text()='Triggers']");
				$this->wait();

				$this->ok("$template: $description");
				$this->button_click("link=$description");
				$this->wait();

				$expressionHost = '{'.$host.':'.$keyName.'.last(0)}=0';
				$this->assertElementValue('description', $description);
				$this->assertElementValue('expression', $expressionHost);
			}

			if (isset($data['dbCheck'])) {
				$result = DBselect("SELECT description FROM triggers where description = '".$description."'");
				while ($row = DBfetch($result)) {
					$this->assertEquals($row['description'], $description);
				}
			}
		}

		if (isset($data['graphAdd'])) {
			$this->open('templates.php');
			$this->button_click("link=$template");
			$this->wait();
			$this->button_click("//div[@class='w']//a[text()='Graphs']");
			$this->wait();
			$this->button_click('form');
			$this->wait();

			$graphName = $data['graphAdd'];
			$this->input_type('name', $graphName);
			$this->zbxLaunchPopup('add_item');
			$this->button_click("link=$itemName");
			$this->selectWindow(null);
			sleep(1);

			$this->button_click('save');
			$this->wait();

// TODO if graph good-check its added/bad-check errors

			if (isset($data['hostCheck'])) {
				$this->open('hosts.php');
				$this->wait();
				$this->button_click("link=$host");
				$this->wait();
				$this->button_click("//div[@class='w']//a[text()='Graphs']");
				$this->wait();

				$this->ok("$template: $graphName");
				$this->button_click("link=$graphName");
				$this->wait();

				$this->assertElementValue('name', $graphName);
				$this->assertElementPresent("//span[text()='$host: $itemName']");
			}

			if (isset($data['dbCheck'])) {
			}
		}

		if (isset($data['unlinkCheck'])) {
			$hostUnlink = $data['unlinkCheck'];
			$unlinkHostId =$data['unlinkHostId'];

			// link host
			$this->button_click("//div[@id='sub_config']//a[text()='Hosts']");
			$this->wait();
			$this->button_click("link=$host");
			$this->wait();

			$this->button_click('tab_templateTab');

			$this->zbxLaunchPopup('//*[@id="add"][@value="Add"]');
			$this->checkbox_select("//*[@value='".$hostUnlink."']");
			$this->button_click('select');
			$this->selectWindow(null);

			$this->wait();
			$this->button_click('save');

			$this->wait();
			$this->ok('Host updated');

			// unlink host
			$this->button_click("link=$host");
			$this->wait();

			$this->button_click('tab_templateTab');
			sleep(1);
			$this->button_click("unlink_and_clear_$unlinkHostId");
			$this->wait();
			$this->button_click('save');

			$this->wait();
			$this->ok('Host updated');
		}
	}
/*	public function testFormItem_linkHost(){
		$this->login('hosts.php');
		$this->button_click('link='.$this->hostName);
		$this->wait();

		$this->button_click('tab_templateTab');
		$this->button_click('//*[@id="add"][@value="Add"]');

		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->checkbox_select('//*[@value="Template App Zabbix Agent"]');
		$this->button_click('select');

		$this->selectWindow(null);
		$this->wait();
		$this->button_click('save');

		$this->wait();
		$this->ok('Host updated');
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
*/
	/**
	 * @dataProvider dataCreate
	 */
/*	public function testFormItem_Create($result, $template, $itemName, $keyName, $errorMsgs) {
		$this->login('templates.php');

		// create an item
		$this->button_click("link=$template");
		$this->wait();
		$this->button_click('link=Items');
		$this->wait();
		$this->button_click('form');
		$this->wait();

		$this->input_type('name', $itemName);
		$this->input_type('key', $keyName);
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

		switch ($result) {
			case ITEM_GOOD:
				$this->ok('Item added');
				$this->checkTitle('Configuration of items');
				$this->ok('CONFIGURATION OF ITEMS');
				break;

			case ITEM_BAD:
				$this->checkTitle('Configuration of items');
				$this->ok('CONFIGURATION OF ITEMS');
				foreach ($errorMsgs as $msg) {
					$this->ok($msg);
				}
				$this->ok('Host');
				$this->ok('Name');
				$this->ok('Key');
				break;
		}

		switch ($result) {
			case ITEM_GOOD:
				// check that the inherited item matches the original
				$this->open('hosts.php');
				$this->wait();
				$this->button_click('link='.$this->hostName);
				$this->wait();
				$this->button_click('link=Items');
				$this->wait();
				$this->ok("$template: $itemName");
				$this->button_click("link=$itemName");
				$this->wait();
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
		$this->login('hosts.php');
		$this->button_click('link='.$this->hostName);
		$this->wait();

		$this->button_click('tab_templateTab');
		sleep(1);
		$this->button_click('unlink_and_clear_10050');
		$this->wait();
		$this->button_click('save');

		$this->wait();
		$this->ok('Host updated');
	}*/
	/**
	 * Creates a new trigger on the template and checks that the inherited trigger matches the original.
	 *
	 * @todo
	 */
/*	public function testTemplateInheritance_CreateTrigger() {
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
*/
	/**
	 * Creates a new graph on the template and checks that the inherited graph matches the original.
	 *
	 * @todo
	 */
/*	public function testTemplateInheritance_CreateGraph() {
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
*/
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
*/
	/**
	 * Creates a new trigger prototype on the template and checks that the inherited item prototype matches
	 * the original.
	 *
	 * @todo match fields for different item types
	 * @todo match flexible intervals
	 * @todo match value mappings
	 */
/*	public function testTemplateInheritance_CreateItemPrototype() {
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
*/
	/**
	 * Creates a new trigger prototype on the template and checks that the inherited trigger prototype matches
	 * the original.
	 *
	 */
/*	public function testTemplateInheritance_CreateTriggerPrototype() {
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
*/
	/**
	 * Creates a new graph prototype on the template and checks that the inherited graph prototype matches the original.
	 *
	 */
/*	public function testTemplateInheritance_CreateGraphPrototype() {
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
*/
	/**
	 * Restore the original tables.
	 */
	public function testTemplateInheritance_teardown() {
		DBrestore_tables('hosts');
	}
}
