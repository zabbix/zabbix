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
class testInheritanceItemPrototype extends CWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	private $discoveryRuleId = 15011;	// 'testInheritanceDiscoveryRule'
	private $discoveryRule = 'testInheritanceDiscoveryRule';

	public function testInheritanceItemPrototype_backup() {
		DBsave_tables('items');
	}

	// returns list of item prototypes from a template
	public static function update() {
		return DBdata(
			'SELECT i.itemid,id.parent_itemid'.
			' FROM items i,item_discovery id'.
			' WHERE i.itemid=id.itemid'.
				' AND i.hostid=15000'.	//	$this->templateid.
				' AND i.flags=2'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceItemPrototype_SimpleUpdate($data) {
		$sqlItems = 'SELECT * FROM items ORDER BY itemid';
		$oldHashItems = DBhash($sqlItems);

		$this->zbxTestLogin('disc_prototypes.php?form=update&itemid='.$data['itemid'].'&parent_discoveryid='.$data['parent_itemid']);
		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('Item prototype updated');

		$this->assertEquals($oldHashItems, DBhash($sqlItems));
	}

	// Returns create data
	public static function create() {
		return array(
			array(
				array(
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceItemPrototype6',
					'key' => 'item-prototype-test6'
				)
			),
			array(
				array(
					'expected' => TEST_BAD,
					'name' => 'testInheritanceItemPrototype5',
					'key' => 'item-prototype-test5',
					'errors' => array(
						'Item prototype "item-prototype-test5" already exists on "Template inheritance test host", inherited from another template'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceItemPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('disc_prototypes.php?form=Create+item+prototype&parent_discoveryid='.$this->discoveryRuleId);

		$this->input_type('name', $data['name']);
		$this->input_type('key', $data['key']);

		$this->zbxTestClickWait('save');
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of item prototypes');
				$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
				$this->zbxTestTextPresent('Item prototype added');

				$itemId = 0;

				// template
				$dbResult = DBselect(
					'SELECT itemid,name,templateid'.
					' FROM items'.
					' WHERE hostid='.$this->templateid.
						' AND key_='.zbx_dbstr($data['key']).
						' AND flags=2'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$itemId = $dbRow['itemid'];
					$this->assertEquals($dbRow['name'], $data['name']);
					$this->assertEquals($dbRow['templateid'], 0);
				}

				$this->assertNotEquals($itemId, 0);

				// host
				$dbResult = DBselect(
					'SELECT key_,name'.
					' FROM items'.
					' WHERE hostid='.$this->hostid.
						' AND templateid='.$itemId.
						' AND flags=2'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$this->assertEquals($dbRow['key_'], $data['key']);
					$this->assertEquals($dbRow['name'], $data['name']);
				}
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of item prototypes');
				$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
				$this->zbxTestTextPresent('ERROR: Cannot add item');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	/**
	 * Restore the original tables.
	 */
	public function testInheritanceItemPrototype_restore() {
		DBrestore_tables('items');
	}
}
