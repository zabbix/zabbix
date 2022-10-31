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
				'type' => 18,			// ITEM_TYPE_DEPENDENT
				'key_' => $prefix.'.'.$i,
				'value_type' => 1,		// ITEM_VALUE_TYPE_STR
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
				'type' => 18,			// ITEM_TYPE_DEPENDENT
				'key_' => $prefix.'.'.$i,
				'value_type' => 1,		// ITEM_VALUE_TYPE_STR
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
				'type' => 18,			// ITEM_TYPE_DEPENDENT
				'key_' => $prefix.'.'.$i,
				'value_type' => 1,		// ITEM_VALUE_TYPE_STR
				'master_itemid' => $master_itemid
			];
		}

		return $items;
	}

	public static function getTestCases() {
		return [
			// Simple update master item.
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			// Simple update master item prototype.
			[
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1018	// dependent.items.template.1:master.item.proto.1
				]
			],
			// Simple update discovered master item.
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2304	// dependent.items.host.7:net.if[eth0]
				]
			],
			// Simple update dependent item.
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1015	// dependent.items.template.1:dependent.item.1.2.2.2
				]
			],
			// Simple update dependent item prototype.
			[
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1032	// dependent.items.template.1:dependent.item.proto.1.2.2.2
				]
			],
			// Simple update discovered dependent item.
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2305	// dependent.items.host.7:net.if.in[eth0]
				]
			],
			// Simple update dependent discovery rule.
			[
				'error' => null,
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1034	// dependent.items.template.1:dependent.discovery.rule.1.1
				]
			],
			// Set incorrect master_itemid for item (create).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'item.create',
				// 1015: dependent.items.host.8
				// 2499: this ID does not exists in the DB
				'request_data' => self::getItems(1015, 2499, 'dependent.item.1', 2, 2)
			],
			// Set incorrect master_itemid for item (update).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2402,		// dependent.items.host.8:dependent.item.1.1
					'master_itemid' => 2499	// this ID does not exists in the DB
				]
			],
			// Set incorrect master_itemid for item prototype (create).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'itemprototype.create',
				// 1015: dependent.items.host.8
				// 2403: dependent.items.host.8:discovery.rule.1
				// 2499: this ID does not exists in the DB
				'request_data' => self::getItemPrototypes(1015, 2403, 2499, 'dependent.item.proto.1', 2, 2)
			],
			// Set incorrect master_itemid for item prototype (update).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2405,		// dependent.items.host.8:dependent.item.proto.1.1
					'master_itemid' => 2499	// this ID does not exists in the DB
				]
			],
			// Set incorrect master_itemid for discovery rule (create).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.create',
				// 1015: dependent.items.host.8
				// 2499: this ID does not exists in the DB
				'request_data' => self::getDiscoveryRule(1015, 2499, 'dependent.discovery.rule.1', 2, 2)
			],
			// Set incorrect master_itemid for discovery rule (update).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2499" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 2409,		// dependent.items.host.8:dependent.discovery.rule.1.1
					'master_itemid' => 2499	// this ID does not exists in the DB
				]
			],
			// Set master_itemid from other host for item (create).
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'item.create',
				// 1015: dependent.items.host.8
				// 2501: dependent.items.host.9:master.item.1
				'request_data' => self::getItems(1015, 2501, 'dependent.item.1', 2, 2)
			],
			// Set master_itemid from other host for item (update).
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 2402,		// dependent.items.host.8:dependent.item.1.1
					'master_itemid' => 2501	// dependent.items.host.9:master.item.1
				]
			],
			// Set master_itemid from other host for item prototype (create).
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'itemprototype.create',
				// 1015: dependent.items.host.8
				// 2403: dependent.items.host.8:discovery.rule.1
				// 2504: dependent.items.host.9:master.item.proto.1
				'request_data' => self::getItemPrototypes(1015, 2403, 2504, 'dependent.item.proto.1', 2, 2)
			],
			// Set master_itemid from other host for item prototype (update).
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2405,		// dependent.items.host.8:dependent.item.proto.1.1
					'master_itemid' => 2504	// dependent.items.host.9:master.item.proto.1
				]
			],
			// Set master_itemid from other discovery rule (create).
			[
				'error' => 'Incorrect value for field "master_itemid": ruleid of dependent item and master item should match.',
				'method' => 'itemprototype.create',
				// 1015: dependent.items.host.8
				// 2403: dependent.items.host.8:discovery.rule.1
				// 2407: dependent.items.host.8:master.item.proto.2
				'request_data' => self::getItemPrototypes(1015, 2403, 2407, 'dependent.item.proto.1', 2, 2)
			],
			// Set master_itemid from other discovery rule (update).
			[
				'error' => 'Incorrect value for field "master_itemid": ruleid of dependent item and master item should match.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2405,		// dependent.items.host.8:dependent.item.proto.1.1
					'master_itemid' => 2407	// dependent.items.host.8:master.item.proto.2
				]
			],
			// Set master_itemid from other host for discovery rule (create).
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'discoveryrule.create',
				// 1015: dependent.items.host.8
				// 2501: dependent.items.host.9:master.item.1
				'request_data' => self::getDiscoveryRule(1015, 2501, 'dependent.discovery.rule.1', 2, 2)
			],
			// Set master_itemid from other host for discovery rule (update).
			[
				'error' => 'Incorrect value for field "master_itemid": hostid of dependent item and master item should match.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 2409,		// dependent.items.host.8:dependent.discovery.rule.1.1
					'master_itemid' => 2501	// dependent.items.host.9:master.item.1
				]
			],
			// Create dependent item, which depends on discovered item.
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2304" does not exist or you have no access to this item.',
				'method' => 'item.create',
				// 1014: dependent.items.host.7
				// 2304: dependent.items.host.7:net.if[eth0]
				'request_data' => self::getItems(1014, 2304, 'item', 1, 1)
			],
			// Create dependent item prototype, which depends on discovered item.
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2304" does not exist or you have no access to this item.',
				'method' => 'itemprototype.create',
				// 1014: dependent.items.host.7
				// 2301: dependent.items.host.7:net.if.discovery
				// 2304: dependent.items.host.7:net.if[eth0]
				'request_data' => self::getItemPrototypes(1014, 2301, 2304, 'item.proto', 1, 1)
			],
			// Create dependent discovery rule, which depends on discovered item.
			[
				'error' => 'Incorrect value for field "master_itemid": Item "2304" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.create',
				// 1014: dependent.items.host.7
				// 2304: dependent.items.host.7:net.if[eth0]
				'request_data' => self::getDiscoveryRule(1014, 2304, 'discovery.rule', 1, 1)
			],
			// Simple update templated master item.
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1301	// dependent.items.host.1:master.item.1
				]
			],
			// Simple update templated master item prototype.
			[
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1318	// dependent.items.host.1:master.item.proto.1
				]
			],
			// Simple update templated dependent item.
			[
				'error' => null,
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1315	// dependent.items.host.1:dependent.item.1.2.2.2
				]
			],
			// Simple update templated dependent item prototype.
			[
				'error' => null,
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1332	// dependent.items.host.1:dependent.item.proto.1.2.2.2
				]
			],
			// Simple update templated dependent discovery rule.
			[
				'error' => null,
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1334	// dependent.items.host.1:dependent.discovery.rule.1.1
				]
			],
			// Circular dependency to itself (update).
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1015,	// dependent.items.template.1:dependent.item.1.2.2.2
					'master_itemid' => 1015
				]
			],
			// Circular dependency to itself (update).
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1032,	// dependent.items.template.1:dependent.item.proto.1.2.2.2
					'master_itemid' => 1032
				]
			],
			// Circular dependency to itself (update).
			[
				'error' => 'Incorrect value for field "master_itemid": Item "1034" does not exist or you have no access to this item.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1034,	// dependent.items.template.1:dependent.discovery.rule.1.1
					'master_itemid' => 1034
				]
			],
			// Circular dependency to between several items (update).
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1003,	// dependent.items.template.1:dependent.item.1.2
					'master_itemid' => 1015
				]
			],
			// Circular dependency to between several item prototypes (update).
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1020,	// dependent.items.template.1:dependent.item.proto.1.2
					'master_itemid' => 1032
				]
			],
			// Set "master_itemid" for not-dependent item (create).
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'item.create',
				'request_data' => [
					'hostid' => 1001,		// dependent.items.template.1
					'name' => 'trap.2',
					'type' => 2,			// ITEM_TYPE_TRAPPER
					'key_' => 'trap.2',
					'value_type' => 1,		// ITEM_VALUE_TYPE_STR
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			// Set "master_itemid" for not-dependent item prototype (create).
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'itemprototype.create',
				'request_data' => [
					'hostid' => 1001,		// dependent.items.template.1
					'ruleid' => 1017,		// dependent.items.template.1:discovery.rule.1
					'name' => 'item.proto.2',
					'type' => 2,			// ITEM_TYPE_TRAPPER
					'key_' => 'item.proto.2',
					'value_type' => 1,		// ITEM_VALUE_TYPE_STR
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			// Set "master_itemid" for not-dependent discovery rule (create).
			[
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
			// Set "master_itemid" for not-dependent item (update).
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1016,		// dependent.items.template.1:trap.1
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			// Set "master_itemid" for not-dependent item prototype (update).
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1033,		// dependent.items.template.1:item.proto.1
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			// Set "master_itemid" for not-dependent discovery rule (update).
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1017,		// dependent.items.template.1:discovery.rule.1
					'master_itemid' => 1001	// dependent.items.template.1:master.item.1
				]
			],
			// Check for maximum depth for the items tree (create). Add 4th level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1008: dependent.items.template.1:dependent.item.1.1.1.1
				'request_data' => self::getItems(1001, 1008, 'dependent.item.1.1.1.1', 1, 1)
			],
			// Check for maximum depth for the item prototypes tree (create). Add 4th level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1025: dependent.items.template.1:dependent.item.proto.1.1.1.1
				'request_data' => self::getItemPrototypes(1001, 1017, 1025, 'dependent.item.1.1.1.1', 1, 1)
			],
			// Check for maximum depth of the discovery rule tree (create). Add 4th level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1008: dependent.items.template.1:dependent.item.1.1.1.1
				'request_data' => self::getDiscoveryRule(1001, 1008, 'dependent.discovery.rule.1.1.1.1', 1, 1)
			],
			// Check for maximum depth of the items tree (update). Add 4th level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1016,		// dependent.items.template.1:trap.1
					'type' => 18,			// ITEM_TYPE_DEPENDENT
					'master_itemid' => 1008	// dependent.items.template.1:dependent.item.1.1.1.1
				]
			],
			// Check for maximum depth of the item prototypes tree (update). Add 4th level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 1033,		// dependent.items.template.1:item.proto.1
					'type' => 18,			// ITEM_TYPE_DEPENDENT
					'master_itemid' => 1025	// dependent.items.template.1:dependent.item.proto.1.1.1.1
				]
			],
			// Check for maximum depth of the discovery rule tree (update). Add 4th level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'discoveryrule.update',
				'request_data' => [
					'itemid' => 1017,		// dependent.items.template.1:discovery.rule.1
					'type' => 18,			// ITEM_TYPE_DEPENDENT
					'master_itemid' => 1008	// dependent.items.template.1:dependent.item.1.1.1.1
				]
			],
			// Check for maximum depth of the items tree (update). Add 4th level at the top.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1702,		// dependent.items.template.4:item.2
					'type' => 18,			// ITEM_TYPE_DEPENDENT
					'master_itemid' => 1701	// dependent.items.template.4:item.1
				]
			],
			// Check for maximum depth of the mixed tree (update). Add 4th level at the top.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 1902,		// dependent.items.template.5:item.2
					'type' => 18,			// ITEM_TYPE_DEPENDENT
					'master_itemid' => 1901	// dependent.items.template.5:item.1
				]
			],
			// Check for maximum depth of the item prototypes tree (update). Add 4th level at the top.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'itemprototype.update',
				'request_data' => [
					'itemid' => 2103,		// dependent.items.template.6:item.proto.2
					'type' => 18,			// ITEM_TYPE_DEPENDENT
					'master_itemid' => 2102	// dependent.items.template.6:item.proto.1
				]
			],
			// Check for maximum depth of the items tree (link a template).
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'template.update',
				'request_data' => [
					'templateid' => 1005,	// dependent.items.template.2
					'hosts' => [
						['hostid' => 1006]	// dependent.items.host.2
					]
				]
			],
			// Check for maximum depth of the mixed tree (link a template).
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'template.update',
				'request_data' => [
					'templateid' => 1005,	// dependent.items.template.2
					'hosts' => [
						['hostid' => 1007]	// dependent.items.host.3
					]
				]
			],
			// Check for maximum count of items in the tree on the template level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1001: dependent.items.template.1:master.item.1
				'request_data' => self::getItems(1001, 1001, 'dependent.item.1', 3, 9987)
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1002: dependent.items.template.1:dependent.item.1.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getItems(1001, 1002, 'dependent.item.1.1', 3, 5495),
					self::getItems(1001, 1003, 'dependent.item.1.2', 3, 4494)
				)
			],
			// Check for maximum count of discovery rule in the tree on the template level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1001: dependent.items.template.1:master.item.1
				'request_data' => self::getDiscoveryRule(1001, 1001, 'dependent.discovery.rule.1', 2, 9986)
			],
			// Check for maximum count of discovery rule in the tree on the template level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1002: dependent.items.template.1:dependent.item.1.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getDiscoveryRule(1001, 1002, 'dependent.discovery.rule.1.1', 1, 5493),
					self::getDiscoveryRule(1001, 1003, 'dependent.discovery.rule.1.2', 1, 4492)
				)
			],
			// Check for maximum count of items in the tree on the host level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				// 1004: dependent.items.host.1
				// 1301: dependent.items.host.1:master.item.1
				'request_data' => self::getItems(1004, 1301, 'dependent.item.1', 3, 9987)
			],
			// Check for maximum count of items in the tree on the host level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				// 1004: dependent.items.host.1
				// 1302: dependent.items.host.1:dependent.item.1.1
				// 1303: dependent.items.host.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getItems(1004, 1302, 'dependent.item.1.1', 3, 5495),
					self::getItems(1004, 1303, 'dependent.item.1.2', 3, 4494)
				)
			],
			// Check for maximum count of discovery rule in the tree on the host level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1004: dependent.items.host.1
				// 1301: dependent.items.host.1:master.item.1
				'request_data' => self::getDiscoveryRule(1004, 1301, 'dependent.discovery.rule.1', 2, 9986)
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1004: dependent.items.host.1
				// 1302: dependent.items.host.1:dependent.item.1.1
				// 1303: dependent.items.host.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getDiscoveryRule(1004, 1302, 'dependent.discovery.rule.1.1', 1, 5493),
					self::getDiscoveryRule(1004, 1303, 'dependent.discovery.rule.1.2', 1, 4492)
				)
			],
			// Check for maximum count of items in the tree on the template level.
			[
				'error' => null,
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1002: dependent.items.template.1:dependent.item.1.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => array_merge(
					self::getItems(1001, 1002, 'dependent.item.1.1', 3, 9494),
					self::getItems(1001, 1003, 'dependent.item.1.2', 3, 494)
				)
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				// 1001: dependent.items.template.1
				// 1003: dependent.items.template.1:dependent.item.1.2
				'request_data' => array_merge(self::getItems(1001, 1003, 'dependent.item.1.2', 495, 9495))
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'discoveryrule.create',
				// 1001: dependent.items.template.1
				// 1001: dependent.items.template.1:master.item.1
				'request_data' => self::getDiscoveryRule(1001, 1001, 'dependent.discovery.rule.1', 2, 2)
			],
			// Check for maximum count of item prototypes in the tree on the template level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1018: dependent.items.template.1:master.item.proto.1
				'request_data' => self::getItemPrototypes(1001, 1017, 1018, 'dependent.item.proto.1', 3, 9988)
			],
			// Check for maximum count of item prototypes in the tree on the template level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1019: dependent.items.template.1:dependent.item.proto.1.1
				// 1020: dependent.items.template.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1001, 1017, 1019, 'dependent.item.proto.1.1', 3, 5495),
					self::getItemPrototypes(1001, 1017, 1020, 'dependent.item.proto.1.2', 3, 4495)
				)
			],
			// Check for maximum count of item prototypes in the tree on the host level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'itemprototype.create',
				// 1004: dependent.items.host.1
				// 1317: dependent.items.template.1:discovery.rule.1
				// 1318: dependent.items.host.1:master.item.proto.1
				'request_data' => self::getItemPrototypes(1004, 1317, 1318, 'dependent.item.proto.1', 3, 9988)
			],
			// Check for maximum count of item prototypes in the tree on the host level.
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'itemprototype.create',
				// 1004: dependent.items.host.1
				// 1317: dependent.items.template.1:discovery.rule.1
				// 1319: dependent.items.host.1:dependent.item.proto.1.1
				// 1320: dependent.items.host.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1004, 1317, 1319, 'dependent.item.proto.1.1', 3, 5495),
					self::getItemPrototypes(1004, 1317, 1320, 'dependent.item.proto.1.2', 3, 4495)
				)
			],
			// Check for maximum count of item prototypes in the tree on the template level.
			[
				'error' => null,
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1019: dependent.items.template.1:dependent.item.proto.1.1
				// 1020: dependent.items.template.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1001, 1017, 1019, 'dependent.item.proto.1.1', 3, 5495),
					self::getItemPrototypes(1001, 1017, 1020, 'dependent.item.proto.1.2', 3, 4494)
				)
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'itemprototype.create',
				// 1001: dependent.items.template.1
				// 1017: dependent.items.template.1:discovery.rule.1
				// 1020: dependent.items.template.1:dependent.item.proto.1.2
				'request_data' => array_merge(
					self::getItemPrototypes(1001, 1017, 1020, 'dependent.item.proto.1.2', 4495, 5494)
				)
			]
		];
	}
	/**
	 * @dataProvider getTestCases
	 */
	public function testDependentItems_Update($error, $method, $request_data) {
		$this->call($method, $request_data, $error);
	}
}
