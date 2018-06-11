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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';

/**
 * @backup items
 */
class API_JSON_Item extends CZabbixTest {
	public static function inventory_links() {
		$data = [];
		$inventoryFields = getHostInventories();
		$inventoryFieldNumbers = array_keys($inventoryFields);
		foreach ($inventoryFieldNumbers as $nr) {
			$data[] = [
				$nr,
				$nr != 1  // item that has inventory_link == 1 exists in test data
			];
		}
		// few non-existing fields
		$maxNr = max($inventoryFieldNumbers);
		$data[] = [$maxNr + 1, false];
		$data[] = ['string', false];

		return $data;
	}

	/**
	 * @dataProvider inventory_links
	 */
	public function testCItem_create_inventory_item($inventoryFieldNr, $successExpected) {
		$data = [
			'name' => 'Item that populates field '.$inventoryFieldNr,
			'key_' => 'key.test.pop.'.$inventoryFieldNr,
			'hostid' => 10053,
			'type' => 0,
			'value_type' => 3,
			'delay' => '30s',
			'interfaceid' => 10021,
			'inventory_link' => $inventoryFieldNr
		];

		// creating item
		$this->call('item.create', $data, ($successExpected ? null : true));
	}
}
