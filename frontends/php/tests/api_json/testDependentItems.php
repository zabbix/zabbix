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
class testDependentItems extends CAPITest {

	public static function getUpdateData() {
		return [
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40554,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40553
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40569,
					'master_itemid' => 40573
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40575,
					'master_itemid' => 40574
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40575,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40574
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'template.update',
				'request_data' => [
					'templateid' => 99010,
					'hosts' => [
						['hostid' => 99009]
					]
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'itemprototype.create',
				'request_data' => [
					'name' => 'test',
					'key_' => 'di_max_levels',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'delay' => '30s',
					'hostid' => 99009,
					'ruleid' => 90006,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40578
				]
			]
		];
	}

	/**
	* @dataProvider getUpdateData
	*/
	public function testDependentItems_Update($error, $method, $request_data) {
		$this->call($method, $request_data, $error);
	}

	public static function getCreateData() {
		$items = [];
		$prototypes = [];

		for ($index = 3; $index < 1000; $index++) {
			$items[] = [
				'name' => 'dependent_'.$index,
				'key_' => 'dependent_'.$index,
				'hostid' => 99010,
				'interfaceid' => null,
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0,
				'history' => '90d',
				'status' => ITEM_STATUS_ACTIVE,
				'params' => '',
				'description' => '',
				'flags' => 0,
				'master_itemid' => 40581
			];

			$prototypes[] = [
				'name' => 'dependent_prototype_'.$index,
				'key_' => 'dependent_prototype_'.$index,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '30s',
				'hostid' => 99009,
				'ruleid' => 90006,
				'type' => ITEM_TYPE_DEPENDENT,
				'master_itemid' => 40567
			];
		}

		return [
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				'request_data' => $items
			],
			[
				'error' => null,
				'method' => 'item.create',
				'request_data' => array_slice($items, 1)
			],
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40581,
					'name' => 'updated master item'
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'itemprototype.create',
				'request_data' => [
					'name' => 'test',
					'key_' => 'di_max_levels',
					'value_type' => ITEM_VALUE_TYPE_UINT64,
					'delay' => '30s',
					'hostid' => 99010,
					'ruleid' => 90007,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40581
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'itemprototype.create',
				'request_data' => [
					'hostid' => 99010,
					'ruleid' => 90007,
				] + reset($prototypes)
			],
			[
				'error' => null,
				'method' => 'itemprototype.create',
				'request_data' => array_slice($prototypes, 1)
			]
		];
	}

	/**
	* @dataProvider getCreateData
	*/
	public function testDependentItems_Create($error, $method, $request_data) {
		$this->call($method, $request_data, $error);
	}
}
