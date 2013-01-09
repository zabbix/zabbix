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
	public function testFormAdministrationGeneralMacros_setup() {
		DBsave_tables('items');
	}

	/**
	 * Creates a new item on the template and checks that the inherited item matches the original.
	 *
	 * @todo implement the test
	 */
	public function testTemplateInheritance_CreateItem() {
		$this->markTestIncomplete();
	}

	/**
	 * Creates a new trigger on the template and checks that the inherited trigger matches the original.
	 *
	 * @todo implement the test
	 */
	public function testTemplateInheritance_CreateTrigger() {
		$this->markTestIncomplete();
	}

	/**
	 * Creates a new graph on the template and checks that the inherited graph matches the original.
	 *
	 * @todo implement the test
	 */
	public function testTemplateInheritance_CreateGraph() {
		$this->markTestIncomplete();
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
		$this->click('link='.$this->templateName);
		$this->wait();
		$this->click('link=Discovery rules');
		$this->wait();
		$this->click('form');
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

		$this->click('save');
		$this->wait();

		// check that the inherited rule matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->click('link='.$this->hostName);
		$this->wait();
		$this->click('link=Discovery rules');
		$this->wait();
		$this->ok($this->templateName.': Test LLD');
		$this->click('link=Test LLD');
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
	 * Creates a new trigger prototype on the template and checks that the inherited trigger prototype matches
	 * the original.
	 *
	 * @todo match fields for different item types
	 * @todo match flexible intervals
	 * @todo match value mappings
	 */
	public function testTemplateInheritance_CreateItemPrototype() {
		$this->login('templates.php');

		// create an item prototype
		$this->click('link='.$this->templateName);
		$this->wait();
		$this->click('link=Discovery rules');
		$this->wait();
		$this->click('link=Test LLD');
		$this->wait();
		$this->click('link=Item prototypes');
		$this->wait();
		$this->click('form');
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

		$this->click('save');
		$this->wait();

		// check that the inherited item prototype matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->click('link='.$this->hostName);
		$this->wait();
		$this->click('link=Discovery rules');
		$this->wait();
		$this->click('link=Test LLD');
		$this->wait();
		$this->click('link=Item prototypes');
		$this->wait();
		$this->ok($this->templateName.': Test LLD item');
		$this->click('link=Test LLD item');
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
		$this->assertDrowpdownValueText('delta_name', 'Delta (simple change)');
	}

	/**
	 * Creates a new trigger prototype on the template and checks that the inherited trigger prototype matches
	 * the original.
	 */
	public function testTemplateInheritance_CreateTriggerPrototype() {
		$this->login('templates.php');

		// create a trigger prototype
		$this->click('link='.$this->templateName);
		$this->wait();
		$this->click('link=Discovery rules');
		$this->wait();
		$this->click('link=Test LLD');
		$this->wait();
		$this->click('link=Trigger prototypes');
		$this->wait();
		$this->click('form');
		$this->wait();

		$this->input_type('description', 'Test LLD trigger');
		$this->input_type('expression', '{Inheritance test template:test-lld-item.last(0)}=0');
		$this->checkbox_select('type');
		$this->input_type('comments', 'comments');
		$this->input_type('url', 'url');
		$this->click('severity_label_2');
		$this->checkbox_unselect('status');

		$this->click('save');
		$this->wait();

		// check that the inherited trigger prototype matches the original
		$this->open('hosts.php');
		$this->wait();
		$this->click('link='.$this->hostName);
		$this->wait();
		$this->click('link=Discovery rules');
		$this->wait();
		$this->click('link=Test LLD');
		$this->wait();
		$this->click('link=Trigger prototypes');
		$this->wait();
		$this->ok($this->templateName.': Test LLD trigger');
		$this->click('link=Test LLD trigger');
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
	 * @todo implement the test
	 */
	public function testTemplateInheritance_CreateGraphPrototype() {
		$this->markTestIncomplete();
	}

	/**
	 * Restore the original tables.
	 */
	public function testFormAdministrationGeneralMacros_teardown() {
		DBrestore_tables('items');
	}

}
