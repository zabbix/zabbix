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

define('ITEM_GOOD', 0);
define('ITEM_BAD', 1);

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 */
class testInheritanceItem extends CWebTest {

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testInheritanceItem_setup() {
		DBsave_tables('hosts');
	}

	public static function simple() {
		return array(
			array(
				array('expected' => ITEM_GOOD,
					'itemName' => 'itemSimple',
					'itemKey' => 'key-template-simple',
					'hostCheck' => true,
					'dbCheck' => true)
			),
			array(
				array('expected' => ITEM_GOOD,
					'itemName' => 'itemName',
					'itemKey' => 'key-template-item',
					'hostCheck' => true)
			),
			array(
				array('expected' => ITEM_GOOD,
					'itemName' => 'itemTrigger',
					'itemKey' => 'key-template-trigger',
					'hostCheck' => true,
					'dbCheck' => true,
					'remove' => true)
			),
			array(
				array('expected' => ITEM_GOOD,
					'itemName' => 'itemRemove',
					'itemKey' => 'key-template-remove',
					'hostCheck' => true,
					'dbCheck' => true,
					'hostRemove' => true,
					'remove' => true)
			),
			array(
				array('expected' => ITEM_BAD,
					'itemName' => 'itemInheritance',
					'itemKey' => 'key-item-inheritance',
					'errors' => array(
						'ERROR: Cannot add item',
						'Item with key "key-item-inheritance" already exists on "Inheritance test template".')
				)
			)
		);
	}

	/**
	 * @dataProvider simple
	 */
	public function testInheritanceItem_simpleCreate($data) {
		$this->zbxTestLogin('templates.php');

		$template = 'Inheritance test template';
		$host = 'Template inheritance test host';

		$itemName = $data['itemName'];
		$keyName = $data['itemKey'];

		$this->zbxTestClickWait("link=$template");
		$this->zbxTestClickWait('link=Items');
		$this->zbxTestClickWait('form');

		$this->input_type('name', $itemName);
		$this->input_type('key', $keyName);
		$this->zbxTestClickWait('save');

		switch ($data['expected']) {
			case ITEM_GOOD:
				$this->zbxTestTextPresent('Item added');
				$this->checkTitle('Configuration of items');
				$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
				break;

			case ITEM_BAD:
				$this->checkTitle('Configuration of items');
				$this->zbxTestTextPresent('CONFIGURATION OF ITEMS');
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				$this->zbxTestTextPresent(array('Host', 'Name', 'Key'));
				break;
		}

		if (isset($data['hostCheck'])) {
			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait("link=$host");
			$this->zbxTestClickWait('link=Items');

			$this->zbxTestTextPresent("$template: $itemName");
			$this->zbxTestClickWait("link=$itemName");
			$this->assertElementValue('name', $itemName);
			$this->assertElementValue('key', $keyName);
		}

		if (isset($data['dbCheck'])) {
			// template
			$result = DBselect("SELECT name, key_, hostid FROM items where name = '".$itemName."' limit 1");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
				$hostid = $row['hostid'] + 1;
			}
			// host
			$result = DBselect("SELECT name, key_ FROM items where name = '".$itemName."'  AND hostid = ".$hostid."");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $itemName);
				$this->assertEquals($row['key_'], $keyName);
			}
		}

		if (isset($data['hostRemove'])) {
			$result = DBselect("SELECT hostid FROM items where name = '".$itemName."' limit 1");
			while ($row = DBfetch($result)) {
				$hostid = $row['hostid'] + 1;
			}
			$result = DBselect("SELECT name, key_, itemid FROM items where name = '".$itemName."'  AND hostid = ".$hostid."");
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('hosts.php');
			$this->zbxTestClickWait("link=$host");
			$this->zbxTestClickWait('link=Items');

			$this->zbxTestCheckboxSelect("group_itemid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent(array('ERROR: Cannot delete items', 'Cannot delete templated item.'));
		}

		if (isset($data['remove'])) {
			$result = DBselect("SELECT itemid FROM items where name = '".$itemName."' limit 1");
			while ($row = DBfetch($result)) {
				$itemId = $row['itemid'];
			}

			$this->zbxTestOpenWait('templates.php');
			$this->zbxTestClickWait("link=$template");
			$this->zbxTestClickWait('link=Items');

			$this->zbxTestCheckboxSelect("group_itemid_$itemId");
			$this->zbxTestDropdownSelect('go', 'Delete selected');
			$this->zbxTestClick('goButton');

			$this->getConfirmation();
			$this->wait();
			$this->zbxTestTextPresent('Items deleted');
			$this->zbxTestTextNotPresent("$template: $itemName");
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceItem_teardown() {
		DBrestore_tables('hosts');
	}
}
