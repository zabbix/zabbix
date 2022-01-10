<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testInheritanceItemPrototype extends CLegacyWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	private $discoveryRuleId = 15011;	// 'testInheritanceDiscoveryRule'
	private $discoveryRule = 'testInheritanceDiscoveryRule';

	// returns list of item prototypes from a template
	public static function update() {
		return CDBHelper::getDataProvider(
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
		$oldHashItems = CDBHelper::getHash($sqlItems);

		$this->zbxTestLogin('disc_prototypes.php?form=update&context=host&itemid='.$data['itemid'].'&parent_discoveryid='.
				$data['parent_itemid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('Item prototype updated');

		$this->assertEquals($oldHashItems, CDBHelper::getHash($sqlItems));
	}

	// Returns create data
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceItemPrototype6',
					'key' => 'item-prototype-test6'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceItemPrototype5',
					'key' => 'item-prototype-test5',
					'errors' => [
						'Item prototype "item-prototype-test5" already exists on "Template inheritance test host", inherited from another template'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceItemPrototype_SimpleCreate($data) {
		$this->zbxTestLogin('disc_prototypes.php?form=Create+item+prototype&context=host&parent_discoveryid='.$this->discoveryRuleId);

		$this->zbxTestInputType('name', $data['name']);
		$this->assertEquals($data['name'], $this->zbxTestGetValue("//input[@id='name']"));
		$this->zbxTestInputType('key', $data['key']);
		$this->assertEquals($data['key'], $this->zbxTestGetValue("//input[@id='key']"));

		$this->zbxTestClickWait('add');
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item prototype added');
				$this->zbxTestTextPresent($data['name']);

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
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add item');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}
}
