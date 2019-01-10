<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup items
 */
class testItem extends CAPITest {

	public static function getItemDeleteData() {
		return [
			[
				'item' => ['40072'],
				'data' => [
					'discovered_triggerids' => ['30002'],
					'dependent_item' => ['40074'],
					'dependent_item_disc_triggerids' => ['30004']
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider getItemDeleteData
	*/
	public function testItem_Delete($item, $data, $expected_error) {
		$result = $this->call('item.delete', $item, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['itemids'] as $id) {
				$dbResult = 'SELECT * FROM items WHERE itemid='.zbx_dbstr($id);
				$this->assertEquals(0, CDBHelper::getCount($dbResult));
			}

			// Check that related discovered trigerid is removed with all related data.
			if (array_key_exists('discovered_triggerids', $data)) {
				foreach ($data['discovered_triggerids'] as $id) {
					$dbResult = 'SELECT * FROM triggers WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM functions WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM trigger_discovery WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}

			// Check that dependent item is removed.
			if (array_key_exists('dependent_item', $data)) {
				foreach ($data['dependent_item'] as $id) {
					$dbResult = 'SELECT * FROM items WHERE itemid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}

			// Check that discovered trigger of dependent item is removed with all related data.
			if (array_key_exists('dependent_item_disc_triggerids', $data)) {
				foreach ($data['dependent_item_disc_triggerids'] as $id) {
					$dbResult = 'SELECT * FROM triggers WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM functions WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));

					$dbResult = 'SELECT * FROM trigger_discovery WHERE triggerid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResult));
				}
			}
		}
	}
}
