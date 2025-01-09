<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class AllItemValueTypes {

	const HOST = 'Host for all item value types';

	public static function load() {
		$hosts = CDataHelper::call('host.create', [
			'host' => self::HOST,
			'groups' => [['groupid' => 4]],
			'inventory_mode' => 0,
			'inventory' => [
				'alias' => 'Item_Types_Alias'
			]
		]);
		$hostid = $hosts['hostids'][0];

		CDataHelper::call('discoveryrule.create', [
			'name' => 'LLD rule for item types',
			'key_' => 'lld_rule',
			'hostid' => $hostid,
			'type' => ITEM_TYPE_TRAPPER
		]);
		$lldid = CDataHelper::getIds('name');

		// Create item prototypes.
		$item_prototype_data = [];
		$value_types = [
			'Float' => ITEM_VALUE_TYPE_FLOAT,
			'Character' => ITEM_VALUE_TYPE_STR,
			'Log' => ITEM_VALUE_TYPE_LOG,
			'Unsigned' =>ITEM_VALUE_TYPE_UINT64,
			'Text' => ITEM_VALUE_TYPE_TEXT
		];

		$dependent_items = [
			'Binary' => ITEM_VALUE_TYPE_BINARY,
			'Unsigned_dependent' => ITEM_VALUE_TYPE_UINT64
		];

		foreach ($value_types as $name => $type) {
			$item_prototype_data[] = [
				'hostid' => $hostid,
				'ruleid' => $lldid['LLD rule for item types'],
				'name' => $name.' item prototype',
				'key_' => $name.'_item_prototype_[{#KEY}]',
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $type
			];
		}
		CDataHelper::call('itemprototype.create', $item_prototype_data);
		$simple_itemprototypeids = CDataHelper::getIds('name');

		// Add dependent item prototype.
		$dependent_item_prototype_data = [];

		foreach ($dependent_items as $name => $type) {
			$dependent_item_prototype_data[] = [
				'hostid' => $hostid,
				'ruleid' => $lldid['LLD rule for item types'],
				'name' => $name.' item prototype',
				'key_' => $name.'_item_prototype_[{#KEY}]',
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => $type,
				'master_itemid' => $simple_itemprototypeids['Float item prototype']
			];
		}
		CDataHelper::call('itemprototype.create', $dependent_item_prototype_data);

		// Create items.
		$items_data = [];

		foreach ($value_types as $name => $type) {
			$items_data[] = [
				'hostid' => $hostid,
				'name' => $name.' item',
				'key_' => $name,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $type
			];
		}

		$master_item_data[] = [
			'hostid' => 90001, // Host for host prototype tests
			'name' => 'Master Item for testItemTypeSelection',
			'key_' => 'graph[1]',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_TEXT
		];

		CDataHelper::call('item.create', array_merge_recursive($items_data, $master_item_data));
		$simple_itemids = CDataHelper::getIds('name');

		// Add dependent item.
		$dependent_items_data = [];
		$dependent_items = [
			'Binary' => ITEM_VALUE_TYPE_BINARY,
			'Unsigned_dependent' => ITEM_VALUE_TYPE_UINT64
		];

		foreach ($dependent_items as $name => $type) {
			$dependent_items_data[] = [
				'hostid' => $hostid,
				'name' => $name.' item',
				'key_' => $name,
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => $type,
				'master_itemid' => $simple_itemids['Float item']
			];
		}
		CDataHelper::call('item.create', $dependent_items_data);
		$dependent_itemids = CDataHelper::getIds('name');

		$itemids = array_merge_recursive($simple_itemids, $dependent_itemids);

		return [
			'hostid' => $hostid,
			'itemids' => $itemids
		];
	}
}
