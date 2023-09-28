<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class AllItemValueTypes {

	const HOST = 'Host for all item value types';

	public static function load() {
		CDataHelper::call('host.create', [
			'host' => self::HOST,
			'groups' => [['groupid' => 4]],
			'inventory_mode' => 0,
			'inventory' => [
				'alias' => 'Item_Types_Alias'
			]
		]);
		$hostids = CDataHelper::getIds('host');

		// Create items.
		$items_data = [];
		$value_types = [
			'Float' => ITEM_VALUE_TYPE_FLOAT,
			'Character' => ITEM_VALUE_TYPE_STR,
			'Log' => ITEM_VALUE_TYPE_LOG,
			'Unsigned' =>ITEM_VALUE_TYPE_UINT64,
			'Text' => ITEM_VALUE_TYPE_TEXT
		];

		foreach ($value_types as $name => $type) {
			$items_data[] = [
				'hostid' => $hostids[self::HOST],
				'name' => $name.' item',
				'key_' => $name,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $type
			];
		}

		CDataHelper::call('item.create', $items_data	);
		$simple_itemids = CDataHelper::getIds('name');

		// Add dependent item.
		$dependent_items_data = [];
		$dependent_items = [
			'Binary' => ITEM_VALUE_TYPE_BINARY,
			'Unsigned_dependent' => ITEM_VALUE_TYPE_UINT64
		];

		foreach ($dependent_items as $name => $type) {
			$dependent_items_data[] = [
				'hostid' => $hostids[self::HOST],
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

		return $itemids;
	}
}
