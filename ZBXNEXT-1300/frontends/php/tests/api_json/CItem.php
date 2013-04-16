<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';

class API_JSON_Item extends CZabbixTest {
	public static function inventory_links() {
		$data = array();
		$inventoryFields = getHostInventories();
		$inventoryFieldNumbers = array_keys($inventoryFields);
		foreach ($inventoryFieldNumbers as $nr) {
			$data[] = array(
				$nr,
				$nr != 1  // item that has inventory_link == 1 exists in test data
			);
		}
		// few non-existing fields
		$maxNr = max($inventoryFieldNumbers);
		$data[] = array($maxNr + 1, false);
		$data[] = array('string', false);

		return $data;
	}

	public function testCItem_backup() {
		DBsave_tables('items');
	}

	/**
	 * @dataProvider inventory_links
	 */
	public function testCItem_create_inventory_item($inventoryFieldNr, $successExpected) {
		$debug = null;

		// creating item
		$result = $this->api_acall(
			'item.create',
			array(
				'name' => 'Item that populates field '.$inventoryFieldNr,
				'key_' => 'key.test.pop.'.$inventoryFieldNr,
				'hostid' => 10053,
				'type' => 0,
				'value_type' => 3,
				'delay' => 30,
				'interfaceid' => 10021,
				'inventory_link' => $inventoryFieldNr
			),
			$debug
		);

		if ($successExpected) {
			$this->assertTrue(!array_key_exists('error', $result), 'Chuck Norris: Method returned an error. Result is: '.print_r($result, true)."\nDebug: ".print_r($debug, true));
		}
		else {
			$this->assertTrue(array_key_exists('error', $result), 'Chuck Norris: I was expecting call to fail, but it did not. Result is: '.print_r($result, true)."\nDebug: ".print_r($debug, true));
		}
	}

	public function testCItem_restore() {
		DBrestore_tables('items');
	}
}
