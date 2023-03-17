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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup items
 */
class testDependentItems extends CAPITest {

	private static function getItems($hostid, $master_itemid, $prefix, $from, $to) {
		$items = [];

		for ($i = $from; $i <= $to; $i++) {
			$items[] = [
				'hostid' => $hostid,
				'name' => $prefix.'.'.$i,
				'type' => ITEM_TYPE_DEPENDENT,
				'key_' => $prefix.'.'.$i,
				'value_type' => ITEM_VALUE_TYPE_STR,
				'master_itemid' => $master_itemid
			];
		}

		return $items;
	}

	private static function getItemPrototypes($hostid, $ruleid, $master_itemid, $prefix, $from, $to) {
		$items = [];

		for ($i = $from; $i <= $to; $i++) {
			$items[] = [
				'hostid' => $hostid,
				'ruleid' => $ruleid,
				'name' => $prefix.'.'.$i,
				'type' => ITEM_TYPE_DEPENDENT,
				'key_' => $prefix.'.'.$i.'[{#LLD}]',
				'value_type' => ITEM_VALUE_TYPE_STR,
				'master_itemid' => $master_itemid
			];
		}

		return $items;
	}

	private static function getDiscoveryRule($hostid, $master_itemid, $prefix, $from, $to) {
		$items = [];

		for ($i = $from; $i <= $to; $i++) {
			$items[] = [
				'hostid' => $hostid,
				'name' => $prefix.'.'.$i,
				'type' => ITEM_TYPE_DEPENDENT,
				'key_' => $prefix.'.'.$i,
				'value_type' => ITEM_VALUE_TYPE_STR,
				'master_itemid' => $master_itemid
			];
		}

		return $items;
	}

	public static function getTestCases() {
		$dep_count_overflow = ZBX_DEPENDENT_ITEM_MAX_COUNT + 1;

		return [
			'Simple update master item.' => [
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Simple update master item prototype.' => [
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1018	// dependent.items.template.1:master.item.proto.1
				]
			],
			'Simple update discovered master item.' => [
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2304	// dependent.items.host.7:net.if[eth0]
				]
			],
			'Simple update dependent item.' => [
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1015	// dependent.items.template.1:dependent.item.1.2.2.2
				]
			],
			'Simple update dependent item prototype.' => [
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1032	// dependent.items.template.1:dependent.item.proto.1.2.2.2
				]
			],
			'Simple update discovered dependent item.' => [
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2305	// dependent.items.host.7:net.if.in[eth0]
				]
			],
			'Simple update dependent discovery rule.' => [
				'error' => null,
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1034	// dependent.items.template.1:dependent.discovery.rule.1.1
				]
			],
			'Set incorrect master_itemid for item (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.',
				'method' => 'item.create',
				// 1015: dependent.items.host.8
				// 2499: this ID does not exist in the DB
				'request_data' => self::getItems(1015, 2499, 'dependent.item.1', 2, 2)
			],
			'Set incorrect master_itemid for item (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2402,		// dependent.items.host.8:dependent.item.1.1
					'master_itemid' => 2499	// this ID does not exist in the DB
				]
			],
			'Set incorrect master_itemid for item prototype (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.',
				'method' => 'itemprototype.create',
				// 1015: dependent.items.host.8
				// 2403: dependent.items.host.8:discovery.rule.1
				// 2499: this ID does not exist in the DB
				'request_data' => self::getItemPrototypes(1015, 2403, 2499, 'dependent.item.proto.1', 2, 2)
			],
			'Set incorrect master_itemid for item prototype (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2405,		// dependent.items.host.8:dependent.item.proto.1.1
					'master_itemid' => 2499	// this ID does not exist in the DB
				]
			],
			'Set incorrect master_itemid for discovery rule (create).' => [
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.create',
				// 1015: dependent.items.host.8
				// 2499: this ID does not exist in the DB
				'request_data' => self::getDiscoveryRule(1015, 2499, 'dependent.discovery.rule.1', 2, 2)
			],
			'Set incorrect master_itemid for discovery rule (update).' => [
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 2409,		// dependent.items.host.8:dependent.discovery.rule.1.1
					'master_itemid' => 2499	// this ID does not exist in the DB
				]
			],
			'Set master_itemid from other host for item (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.',
				'method' => 'item.create',
				// 1015: dependent.items.host.8
				// 2501: dependent.items.host.9:master.item.1
				'request_data' => self::getItems(1015, 2501, 'dependent.item.1', 2, 2)
			],
			'Set master_itemid from other host for item (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2402,		// dependent.items.host.8:dependent.item.1.1
					'master_itemid' => 2501	// dependent.items.host.9:master.item.1
				]
			],
			'Set master_itemid from other host for item prototype (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.',
				'method' => 'itemprototype.create',
				// 1015: dependent.items.host.8
				// 2403: dependent.items.host.8:discovery.rule.1
				// 2504: dependent.items.host.9:master.item.proto.1
				'request_data' => self::getItemPrototypes(1015, 2403, 2504, 'dependent.item.proto.1', 2, 2)
			],
			'Set master_itemid from other host for item prototype (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2405,		// dependent.items.host.8:dependent.item.proto.1.1
					'master_itemid' => 2504	// dependent.items.host.9:master.item.proto.1
				]
			],
			'Set master_itemid from other discovery rule (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item prototype ID from another LLD rule.',
				'method' => 'itemprototype.create',
				// 1015: dependent.items.host.8
				// 2403: dependent.items.host.8:discovery.rule.1
				// 2407: dependent.items.host.8:master.item.proto.2
				'request_data' => self::getItemPrototypes(1015, 2403, 2407, 'dependent.item.proto.1', 2, 2)
			],
			'Set master_itemid from other discovery rule (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item prototype ID from another LLD rule.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2405,		// dependent.items.host.8:dependent.item.proto.1.1
					'master_itemid' => 2407	// dependent.items.host.8:master.item.proto.2
				]
			],
			'Set master_itemid from other host for discovery rule (create).' => [
				'error' => 'Incorrect value for field "master_itemid": "hostid" of dependent item and master item should match.',
				'method' => 'discoveryrule.create',
				// 1015: dependent.items.host.8
				// 2501: dependent.items.host.9:master.item.1
				'request_data' => self::getDiscoveryRule(1015, 2501, 'dependent.discovery.rule.1', 2, 2)
			],
			'Set master_itemid from other host for discovery rule (update).' => [
				'error' => 'Incorrect value for field "master_itemid": "hostid" of dependent item and master item should match.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 2409,		// dependent.items.host.8:dependent.discovery.rule.1.1
					'master_itemid' => 2501	// dependent.items.host.9:master.item.1
				]
			],
			'Create dependent item, which depends on discovered item.' => [
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.',
				'method' => 'item.create',
				// 1014: dependent.items.host.7
				// 2304: dependent.items.host.7:net.if[eth0]
				'request_data' => self::getItems(1014, 2304, 'item', 1, 1)
			],
			'Create dependent item prototype, which depends on discovered item.' => [
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.',
				'method' => 'itemprototype.create',
				// 1014: dependent.items.host.7
				// 2301: dependent.items.host.7:net.if.discovery
				// 2304: dependent.items.host.7:net.if[eth0]
				'request_data' => self::getItemPrototypes(1014, 2301, 2304, 'item.proto', 1, 1)
			],
			'Create dependent discovery rule, which depends on discovered item.' => [
				'error' => 'Incorrect value for field "master_itemid": Item "2304" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.create',
				// 1014: dependent.items.host.7
				// 2304: dependent.items.host.7:net.if[eth0]
				'request_data' => self::getDiscoveryRule(1014, 2304, 'discovery.rule', 1, 1)
			],
			'Simple update templated master item.' => [
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1301	// dependent.items.host.1:master.item.1
				]
			],
			'Simple update templated master item prototype.' => [
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1318	// dependent.items.host.1:master.item.proto.1
				]
			],
			'Simple update templated dependent item.' => [
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1315	// dependent.items.host.1:dependent.item.1.2.2.2
				]
			],
			'Simple update templated dependent item prototype.' => [
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1332	// dependent.items.host.1:dependent.item.proto.1.2.2.2
				]
			],
			'Simple update templated dependent discovery rule.' => [
				'error' => null,
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1334	// dependent.items.host.1:dependent.discovery.rule.1.1
				]
			],
			'Circular dependency to itself (update).' => [
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1015,	// dependent.items.template.1:dependent.item.1.2.2.2
					'master_itemid' => 1015
				]
			],
			'Circular dependency to itself (update).' => [
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1032,	// dependent.items.template.1:dependent.item.proto.1.2.2.2
					'master_itemid' => 1032
				]
			],
			'Circular dependency to itself (update).' => [
				'error' => 'Incorrect value for field "master_itemid": Item "1034" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1034,	// dependent.items.template.1:dependent.discovery.rule.1.1
					'master_itemid' => 1034
				]
			],
			'Circular dependency to between several items (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1003,	// dependent.items.template.1:dependent.item.1.2
					'master_itemid' => 1015
				]
			],
			'Circular dependency to between several item prototypes (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1020,	// dependent.items.template.1:dependent.item.proto.1.2
					'master_itemid' => 1032
				]
			],
			'Set "master_itemid" for not-dependent item (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.',
				'method' => 'item.create',
				'request_data' => [
					'hostid' => 1001,		// dependent.items.template.1
					'name' => 'trap.2',
					'type' => ITEM_TYPE_TRAPPER,
					'key_' => 'trap.2',
					'value_type' => ITEM_VALUE_TYPE_STR,
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Set "master_itemid" for not-dependent item prototype (create).' => [
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.',
				'method' => 'itemprototype.create',
				'request_data' => [
					'hostid' => 1001,		// dependent.items.template.1
					'ruleid' => 1017,		// dependent.items.template.1:discovery.rule.1
					'name' => 'item.proto.2',
					'type' => ITEM_TYPE_TRAPPER,
					'key_' => 'item.proto.2[{#LLD}]',
					'value_type' => ITEM_VALUE_TYPE_STR,
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Set "master_itemid" for not-dependent discovery rule (create).' => [
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'discoveryrule.create',
				'request_data' => [
					'hostid' => 1001,		// dependent.items.template.1
					'name' => 'discovery.rule.2',
					'key_' => 'discovery.rule.2',
					'type' => 2,
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Set "master_itemid" for not-dependent item (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1016,		// dependent.items.template.1:trap.1
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Set "master_itemid" for not-dependent item prototype (update).' => [
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1033,		// dependent.items.template.1:item.proto.1
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Set "master_itemid" for not-dependent discovery rule (update).' => [
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1017,		// dependent.items.template.1:discovery.rule.1
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			'Check for maximum depth for the items tree (create). Add 4th level.' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.1.1.1.1" on the master item with key "dependent.item.1.1.1.1" on the template "dependent.items.template.1": allowed count of dependency levels would be exceeded.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1008: dependent.items.template.1:dependent.item.1.1.1.1
				'request_data' => self::getItems(1001, 1008, 'dependent.item.1.1.1.1', 1, 1)
			],
			'Check for maximum depth for the item prototypes tree (create). Add 4th level.' => [
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.1.1.1.1.1[{#LLD}]" on the master item prototype with key "dependent.item.proto.1.1.1.1" on the template "dependent.items.template.1": allowed count of dependency levels would be exceeded.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1025: dependent.items.template.1:dependent.item.proto.1.1.1.1
				'request_data' => self::getItemPrototypes(1001, 1017, 1025, 'dependent.item.1.1.1.1', 1, 1)
			],
			'Check for maximum depth of the discovery rule tree (create). Add 4th level.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1008: dependent.items.template.1:dependent.item.1.1.1.1
				'request_data' => self::getDiscoveryRule(1001, 1008, 'dependent.discovery.rule.1.1.1.1', 1, 1)
			],
			'Check for maximum depth of the items tree (update). Add 4th level.' => [
				'error' => 'Cannot set dependency for item with key "trap.1" on the master item with key "dependent.item.1.1.1.1" on the template "dependent.items.template.1": allowed count of dependency levels would be exceeded.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1016,		// dependent.items.template.1:trap.1
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 1008	// dependent.items.template.1:dependent.item.1.1.1.1
				]
			],
			'Check for maximum depth of the item prototypes tree (update). Add 4th level.' => [
				'error' => 'Cannot set dependency for item prototype with key "item.proto.1" on the master item prototype with key "dependent.item.proto.1.1.1.1" on the template "dependent.items.template.1": allowed count of dependency levels would be exceeded.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1033,		// dependent.items.template.1:item.proto.1
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 1025	// dependent.items.template.1:dependent.item.proto.1.1.1.1
				]
			],
			'Check for maximum depth of the discovery rule tree (update). Add 4th level.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1017,		// dependent.items.template.1:discovery.rule.1
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 1008	// dependent.items.template.1:dependent.item.1.1.1.1
				]
			],
			'Check for maximum depth of the items tree (update). Add 4th level at the top.' => [
				'error' => 'Cannot set dependency for item with key "item.2" on the master item with key "item.1" on the host "dependent.items.host.4": allowed count of dependency levels would be exceeded.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1702,		// dependent.items.template.4:item.2
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 1701	// dependent.items.template.4:item.1
				]
			],
			'Check for maximum depth of the mixed tree (update). Add 4th level at the top.' => [
				'error' => 'Cannot set dependency for item with key "item.2" on the master item with key "item.1" on the host "dependent.items.host.5": allowed count of dependency levels would be exceeded.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1902,		// dependent.items.template.5:item.2
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 1901	// dependent.items.template.5:item.1
				]
			],
			'Check for maximum depth of the item prototypes tree (update). Add 4th level at the top.' => [
				'error' => 'Cannot set dependency for item prototype with key "item.proto.2" on the master item prototype with key "item.proto.1" on the host "dependent.items.host.6": allowed count of dependency levels would be exceeded.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2103,		// dependent.items.template.6:item.proto.2
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 2102	// dependent.items.template.6:item.proto.1
				]
			],
			'Check for maximum depth of the items tree (link a template).' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.1" on the master item with key "master.item.1" on the host "dependent.items.host.2": allowed count of dependency levels would be exceeded.',
				'method' => 'host.update',
				'request_data' => [
					'hostid' => 1006,	// dependent.items.host.2
					'templates' => [
						'templateid' => 1005	// dependent.items.template.2
					]
				]
			],
			'Check for maximum depth of the mixed tree (link a template).' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.1" on the master item with key "master.item.1" on the host "dependent.items.host.3": allowed count of dependency levels would be exceeded.',
				'method' => 'host.update',
				'request_data' => [
					'hostid' => 1007,	// dependent.items.host.3
					'templates' => [
						'templateid' => 1005	// dependent.items.template.2
					]
				]
			],
			'Check for maximum count of items in the tree on the template level.' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.3" on the master item with key "master.item.1" on the template "dependent.items.template.1": allowed count of dependent items would be exceeded.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1001: dependent.items.template.1:master.item.1
				'request_data' => self::getItems(1001, 1001, 'dependent.item.1', 3, $dep_count_overflow - 3)
			],
			'Check for maximum count of items in the tree on the template level, combination.' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.2.3" on the master item with key "dependent.item.1.2" on the template "dependent.items.template.1": allowed count of dependent items would be exceeded.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1002: dependent.items.template.1:dependent.item.1.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getItems(1001, 1002, 'dependent.item.1.1', 3, floor($dep_count_overflow / 2) - 3),
					self::getItems(1001, 1003, 'dependent.item.1.2', 3, ceil($dep_count_overflow / 2) - 3)
				)
			],
			'Check for maximum count of discovery rule in the tree on the template level.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1001: dependent.items.template.1:master.item.1
				'request_data' => self::getDiscoveryRule(1001, 1001, 'dependent.discovery.rule.1', 2, $dep_count_overflow - 2)
			],
			'Check for maximum count of discovery rule in the tree on the template level.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1002: dependent.items.template.1:dependent.item.1.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getDiscoveryRule(1001, 1002, 'dependent.discovery.rule.1.1', 1, floor($dep_count_overflow / 2) - 6),
					self::getDiscoveryRule(1001, 1003, 'dependent.discovery.rule.1.2', 1, ceil($dep_count_overflow / 2))
				)
			],
			'Check for maximum count of items in the tree on the host level.' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.3" on the master item with key "master.item.1" on the host "dependent.items.host.1": allowed count of dependent items would be exceeded.',
				'method' => 'item.create',
				// 1004: dependent.items.host.1
				// 1301: dependent.items.host.1:master.item.1
				'request_data' => self::getItems(1004, 1301, 'dependent.item.1', 3, $dep_count_overflow - 3 - 6)
			],
			'Check for maximum count of items in the tree on the host level, combination.' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.2.3" on the master item with key "dependent.item.1.2" on the host "dependent.items.host.1": allowed count of dependent items would be exceeded.',
				'method' => 'item.create',
				// 1004: dependent.items.host.1
				// 1302: dependent.items.host.1:dependent.item.1.1
				// 1303: dependent.items.host.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getItems(1004, 1302, 'dependent.item.1.1', 3, floor($dep_count_overflow / 2) - 3),
					self::getItems(1004, 1303, 'dependent.item.1.2', 3, ceil($dep_count_overflow / 2) - 3)
				)
			],
			'Check for maximum count of discovery rule in the tree on the host level.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1004: dependent.items.host.1
				// 1301: dependent.items.host.1:master.item.1
				'request_data' => self::getDiscoveryRule(1004, 1301, 'dependent.discovery.rule.1', 2, $dep_count_overflow - 2 - 6)
			],
			'Check for maximum count of discovery rule in the tree on the host level 2.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1004: dependent.items.host.1
				// 1302: dependent.items.host.1:dependent.item.1.1
				// 1303: dependent.items.host.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getDiscoveryRule(1004, 1302, 'dependent.discovery.rule.1.1', 1, floor($dep_count_overflow / 2) - 6),
					self::getDiscoveryRule(1004, 1303, 'dependent.discovery.rule.1.2', 1, ceil($dep_count_overflow / 2))
				)
			],
			'Check for maximum count of items in the tree on the template level, fill to max.' => [
				'error' => null,
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1002: dependent.items.template.1:dependent.item.1.1 (2 dependents)
				// 1003: dependent.items.template.1:dependent.item.1.2 (2 dependents)
				'request_data' => array_merge(
					self::getItems(1001, 1002, 'dependent.item.1.1', 3, floor($dep_count_overflow / 2) - 3),
					self::getItems(1001, 1003, 'dependent.item.1.2', 3, ceil($dep_count_overflow / 2) - 3 - 6 /* 4 existing dependents + parent dependent items */)
				)
			],
			'Check for maximum count of items in the tree on the template level, adding overflow via 2nd item.' => [
				'error' => 'Cannot set dependency for item with key "dependent.item.1.2.30" on the master item with key "dependent.item.1.2" on the template "dependent.items.template.1": allowed count of dependent items would be exceeded.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => self::getItems(1001, 1003, 'dependent.item.1.2', ceil($dep_count_overflow), ceil($dep_count_overflow) + 1)
			],
			'Check for maximum count of items in the tree on the template level, master item.' => [
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1001: dependent.items.template.1:master.item.1
				'request_data' => self::getDiscoveryRule(1001, 1001, 'dependent.discovery.rule.1', 2, $dep_count_overflow - 2 - 6)
			],
			'Check for maximum count of item prototypes in the tree on the template level.' => [
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.proto.1.3[{#LLD}]" on the master item prototype with key "master.item.proto.1" on the template "dependent.items.template.1": allowed count of dependent items would be exceeded.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1018: dependent.items.template.1:master.item.proto.1
				'request_data' => self::getItemPrototypes(1001, 1017, 1018, 'dependent.item.proto.1', 3, $dep_count_overflow - 3)
			],
			'Check for maximum count of item prototypes in the tree on the template level, combination, fail.' => [
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.proto.1.2.3[{#LLD}]" on the master item prototype with key "dependent.item.proto.1.2" on the template "dependent.items.template.1": allowed count of dependent items would be exceeded.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1019: dependent.items.template.1:dependent.item.proto.1.1
				// 1020: dependent.items.template.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1001, 1017, 1019, 'dependent.item.proto.1.1', 3, floor($dep_count_overflow / 2) - 3),
					self::getItemPrototypes(1001, 1017, 1020, 'dependent.item.proto.1.2', 3, ceil($dep_count_overflow / 2) - 3)
				)
			],
			'Check for maximum count of item prototypes in the tree on the host level.' => [
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.proto.1.3[{#LLD}]" on the master item prototype with key "master.item.proto.1" on the host "dependent.items.host.1": allowed count of dependent items would be exceeded.',
				'method' => 'itemprototype.create',
				// 1004: dependent.items.host.1
				// 1317: dependent.items.template.1:discovery.rule.1
				// 1318: dependent.items.host.1:master.item.proto.1
				'request_data' => self::getItemPrototypes(1004, 1317, 1318, 'dependent.item.proto.1', 3, $dep_count_overflow - 3)
			],
			'Check for maximum count of item prototypes in the tree on the host level, combination.' => [
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.proto.1.2.3[{#LLD}]" on the master item prototype with key "dependent.item.proto.1.2" on the host "dependent.items.host.1": allowed count of dependent items would be exceeded.',
				'method' => 'itemprototype.create',
				// 1004: dependent.items.host.1
				// 1317: dependent.items.template.1:discovery.rule.1
				// 1319: dependent.items.host.1:dependent.item.proto.1.1
				// 1320: dependent.items.host.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1004, 1317, 1319, 'dependent.item.proto.1.1', 3, floor($dep_count_overflow / 2) - 3),
					self::getItemPrototypes(1004, 1317, 1320, 'dependent.item.proto.1.2', 3, ceil($dep_count_overflow / 2) - 3)
				)
			],
			'Check for maximum count of item prototypes in the tree on the template level, combination, success.' => [
				'error' => null,
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1 (max occupancy checked here)
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1019: dependent.items.template.1:dependent.item.proto.1.1 (2 dependents)
				// 1020: dependent.items.template.1:dependent.item.proto.1.2 (2 dependents)
				'request_data' => array_merge(
					self::getItemPrototypes(1001, 1017, 1019, 'dependent.item.proto.1.1', 3, floor($dep_count_overflow / 2) - 3 - 2 /* dependents */),
					self::getItemPrototypes(1001, 1017, 1020, 'dependent.item.proto.1.2', 3, ceil($dep_count_overflow / 2) - 3 - 2 /* dependents */ - 1 /*rule itself*/)
				)
			],
			'Check for maximum count of item prototypes in the tree on the template.' => [
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.proto.1.2.11[{#LLD}]" on the master item prototype with key "dependent.item.proto.1.2" on the template "dependent.items.template.1": allowed count of dependent items would be exceeded.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1020: dependent.items.template.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1001, 1017, 1020, 'dependent.item.proto.1.2', floor($dep_count_overflow / 2) - 5 + 1 /* from last above */, floor($dep_count_overflow / 2) - 4 + 1)
				)
			]
		];
	}

	/**
	 * @dataProvider getTestCases
	 */
	public function testDependentItems_main($expected_error, $method, $request_data) {
		static $reg_child_number = '/("dependent[^"]+\.)(\d*)([^"]*")/';

		if ($expected_error === null || strrpos($expected_error, 'allowed count of dependent') === false) {
			return $this->call($method, $request_data, $expected_error);
		}

		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = CAPIHelper::call($method, $request_data);

		$this->assertArrayNotHasKey('result', $response);
		$this->assertArrayHasKey('error', $response);

		/*
		To allow for varying ZBX_DEPENDENT_ITEM_MAX_COUNT, replace specific dependent "child" numbers, e.g.
			dependent.item.proto.1.2.46[{#LLD}] becomes
			dependent.item.proto.1.2.x[{#LLD}].
		*/
		$expected_error = preg_replace($reg_child_number, '$1x$3', $expected_error);
		$received_error = $response['error']['data'];

		if (strrpos($received_error, 'allowed count of dependent') !== false) {
			$received_error = preg_replace($reg_child_number, '$1x$3', $received_error);
		}

		$this->assertSame($expected_error, $received_error);
	}
}
