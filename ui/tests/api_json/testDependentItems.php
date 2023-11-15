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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testDependentItems extends CAPITest {

	public static function prepareTestData(): void {
		// Do nothing if test will be skipped.
		if (ZBX_DEPENDENT_ITEM_MAX_COUNT > 299) {
			return;
		}

		DBconnect($error);

		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'dependent.items.tests.template.group']
			],
			'host_groups' => [
				['name' => 'dependent.items.tests.host.group']
			],
			'templates' => [
				[
					'host' => 't.dep',
					'items' => [
						['key_' => 'template.master.item'],
						[
							'key_' => 'template.dependent.item',
							'master_itemid' => ':item:template.master.item'
						],
						[
							'key_' => 'template.dependent.descendant',
							'master_itemid' => ':item:template.dependent.item'
						],
						[
							'key_' => 'template.dependent.level.last',
							'master_itemid' => ':item:template.dependent.descendant'
						],
						['key_' => 'template.overflow.item']
					],
					'lld_rules' => [
						['key_' => 'template.discovery.rule.update'],
						[
							'key_' => 'template.discovery.rule',
							'item_prototypes' => [
								['key_' => 'template.master.item.prototype[{#LLD}]'],
								[
									'key_' => 'template.dependent.item.prototype[{#LLD}]',
									'master_itemid' => ':item_prototype:template.master.item.prototype[{#LLD}]'
								],
								[
									'key_' => 'template.dependent.item.prototype.descendant[{#LLD}]',
									'master_itemid' => ':item_prototype:template.dependent.item.prototype[{#LLD}]'
								],
								[
									'key_' => 'template.dependent.item.prototype.level.last[{#LLD}]',
									'master_itemid' => ':item_prototype:template.dependent.item.prototype.descendant[{#LLD}]'
								],
								['key_' => 'template.overflow.item.prototype[{#LLD}]']
							]
						]
					]
				],
				[
					'host' => 't.mixed.dependencies',
					'items' => [
						['key_' => 'mixed.dependency.master.item'],
						[
							'key_' => 'mixed.dependency.dependent.item',
							'master_itemid' => ':item:mixed.dependency.master.item'
						],
						[
							'key_' => 'mixed.dependency.dependent.descendant',
							'master_itemid' => ':item:mixed.dependency.dependent.item'
						],
						['key_' => 'mixed.dependency.item.overflow']
					],
					'lld_rules' => [
						[
							'key_' => 'mixed.dependency.dependent.rule',
							'master_itemid' => ':item:mixed.dependency.dependent.descendant'
						]
					]
				],
				[
					'host' => 't.dep.2',
					'items' => [
						['key_' => 'i0[t.dep.2]'],
						[
							'key_' => 'i1[t.dep.2]',
							'master_itemid' => ':item:i0[t.dep.2]'
						],
						[
							'key_' => 'i.before.last[t.dep.2]',
							'master_itemid' => ':item:i1[t.dep.2]'
						],
						[
							'key_' => 'i.last[t.dep.2]',
							'master_itemid' => ':item:i.before.last[t.dep.2]'
						]
					]
				],
				[
					'host' => 't.dep.3',
					'items' => [
						['key_' => 'i0[t.dep.3]'],
						[
							'key_' => 'i1[t.dep.3]',
							'master_itemid' => ':item:i0[t.dep.3]'
						],
						[
							'key_' => 'i.before.last[t.dep.3]',
							'master_itemid' => ':item:i1[t.dep.3]'
						]
					],
					'lld_rules' => [
						[
							'key_' => 'lld1[t.dep.3]',
							'item_prototypes' => [
								[
									'key_' => 'i.last[{#LLD}, t.dep.3]',
									'master_itemid' => ':item:i.before.last[t.dep.3]'
								]
							]
						]
					]
				]
			],
			'hosts' => [
				[
					'host' => 'h.dep',
					'items' => [
						['key_' => 'master.item'],
						[
							'key_' => 'dependent.item',
							'master_itemid' => ':item:master.item'
						],
						[
							'key_' => 'dependent.item.descendant',
							'master_itemid' => ':item:dependent.item'
						],
						['key_' => 'i.last[t.dep.2]'],
						[
							'key_' => 'i.after.last[t.dep.2]',
							'master_itemid' => ':item:i.last[t.dep.2]'
						],
						['key_' => 'independent.item']
					],
					'lld_rules' => [
						[
							'key_' => 'discovery.rule',
							'item_prototypes' => [
								[
									'key_' => 'master.item.prototype[{#LLD}]',
									'discovered_items' => [
										['key_' => 'master.item.discovered[eth0]']
									]
								],
								[
									'key_' => 'dependent.item.prototype[{#LLD}]',
									'master_itemid' => ':item_prototype:master.item.prototype[{#LLD}]'
								],
								[
									'key_' => 'dependent.item_prototype.descendant[{#LLD}]',
									'master_itemid' => ':item_prototype:dependent.item.prototype[{#LLD}]'
								]
							]
						],
						[
							'key_' => 'dependent.discovery.rule',
							'master_itemid' => ':item:master.item'
						],
						[
							'key_' => 'lld1[t.dep.3]',
							'item_prototypes' => [
								['key_' => 'i.last[{#LLD}, t.dep.3]'],
								[
									'key_' => 'i.after.last[{#LLD}, t.dep.3]',
									'master_itemid' => ':item_prototype:i.last[{#LLD}, t.dep.3]'
								]
							]
						]
					]
				],
				[
					'host' => 'h.discovered.items',
					'items' => [
						['key_' => 'master.for.discovered.item']
					],
					'lld_rules' => [
						[
							'key_' => 'discovered.items.rule',
							'item_prototypes' => [
								[
									'key_' => 'item.prototype.for.discovered.item[{#LLD}]',
									'master_itemid' => ':item:master.for.discovered.item',
									'discovered_items' => [
										[
											'key_' => 'discovered.dependent.item[eth0]',
											'master_itemid' => ':item:master.for.discovered.item'
										]
									]
								]
							]
						]
					]
				],
				[
					'host' => 'h.dep.other',
					'items' => [
						['key_' => 'master.item.other'],
						[
							'key_' => 'dependent.item.other',
							'master_itemid' => ':item:master.item.other'
						]
					],
					'lld_rules' => [
						[
							'key_' => 'discovery.rule.other',
							'item_prototypes' => [
								['key_' => 'master.item.prototype.other[{#LLD}]'],
								[
									'key_' => 'dependent.item.prototype.other[{#LLD}]',
									'master_itemid' => ':item_prototype:master.item.prototype.other[{#LLD}]'
								]
							]
						],
						[
							'key_' => 'independent.rule',
							'item_prototypes' => [
								['key_' => 'independent.item.prototype[{#LLD}]']
							]
						]
					]
				]
			]
		]);
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function getTestCases() {
		$nonexistent_itemid = 9999;

		return [
			'Simple update master item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:master.item'
				],
				'error' => null
			],
			'Simple update master item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:master.item.prototype[{#LLD}]'
				],
				'error' => null
			],
			'Simple update discovered master item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':discovered_item:master.item.discovered[eth0]'
				],
				'error' => null
			],
			'Simple update dependent item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:dependent.item'
				],
				'error' => null
			],
			'Simple update dependent item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype[{#LLD}]'
				],
				'error' => null
			],
			'Simple update discovered dependent item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':discovered_item:discovered.dependent.item[eth0]'
				],
				'error' => null
			],
			'Simple update dependent discovery rule.' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:discovery.rule'
				],
				'error' => null
			],

			'Set incorrect master_itemid for item (create).' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'dependent.error',
					'hostid' => ':host:h.dep',
					'master_itemid' => $nonexistent_itemid
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:dependent.item',
					'master_itemid' => $nonexistent_itemid
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for item prototype (create).' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'dependent.item.prototype.new',
					'hostid' => ':host:h.dep',
					'master_itemid' => $nonexistent_itemid
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype[{#LLD}]',
					'master_itemid' => $nonexistent_itemid
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.'
			],
			'Set incorrect master_itemid for discovery rule (create).' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'discovery.rule.error',
					'hostid' => ':host:h.dep',
					'master_itemid' => $nonexistent_itemid
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for discovery rule (update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:dependent.discovery.rule',
					'master_itemid' => $nonexistent_itemid
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set master_itemid from other host for item (create).' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'dependent.item.new',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:master.item.other:host:h.dep.other'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],
			'Set master_itemid from other host for item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:dependent.item:host:h.dep',
					'master_itemid' => ':item:master.item.other:host:h.dep.other'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],
			'Set master_itemid from other host for item prototype (create).' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'dependent.item.prototype.other.new[{#LLD}]',
					'hostid' => ':host:h.dep.other',
					'ruleid' => ':lld_rule:discovery.rule.other',
					'master_itemid' => ':item_prototype:master.item.prototype[{#LLD}]:host:h.dep'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other host for item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype.other[{#LLD}]:host:h.dep.other',
					'master_itemid' => ':item_prototype:master.item.prototype[{#LLD}]:host:h.dep'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for item prototype (create).' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'dependent.item.prototype.new[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'master_itemid' => ':item_prototype:master.item.prototype.other[{#LLD}]'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype[{#LLD}]:host:h.dep',
					'master_itemid' => ':item_prototype:master.item.prototype.other[{#LLD}]:host:h.dep.other'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for LLD rule (create).' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'discovery.rule.new',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:master.item.other:host:h.dep.other'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for LLD rule (update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:dependent.discovery.rule:host:h.dep',
					'master_itemid' => ':item:master.item.other:host:h.dep.other'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],

			// Dependencies on discovered items not allowed.
			'Create dependent item, which depends on discovered item.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'dependent.on.master.discovered',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':discovered_item:master.item.discovered[eth0]'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Create dependent item prototype, which depends on discovered item.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'item.prototype.dependent.on.master.discovered[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'master_itemid' => ':discovered_item:master.item.discovered[eth0]'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.'
			],
			'Create dependent discovery rule, which depends on discovered item.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'lld_rule.dependent.on.master.discovered',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':discovered_item:master.item.discovered[eth0]'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],

			'Simple update templated master item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.master.item'
				],
				'error' => null
			],
			'Simple update templated dependent item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.dependent.item'
				],
				'error' => null
			],
			'Simple update templated master item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.master.item.prototype[{#LLD}]'
				],
				'error' => null
			],
			'Simple update templated dependent item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.dependent.item.prototype[{#LLD}]'
				],
				'error' => null
			],
			'Simple update templated dependent discovery rule.' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:template.discovery.rule'
				],
				'error' => null
			],

			'Circular dependency to itself (item.update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:dependent.item',
					'master_itemid' => ':item:dependent.item'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],
			'Circular dependency to itself (itemprototype.update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype[{#LLD}]',
					'master_itemid' => ':item_prototype:dependent.item.prototype[{#LLD}]'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],
			'Circular dependency to itself (discoveryrule.update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:dependent.discovery.rule',
					'master_itemid' => ':lld_rule:dependent.discovery.rule'
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Circular dependency to descendant item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:master.item',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:dependent.item.descendant'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],
			'Circular dependency to descendant item prototypes (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:master.item.prototype[{#LLD}]',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:dependent.item_prototype.descendant[{#LLD}]'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],

			'Set "master_itemid" for not-dependent item (create).' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'item.error',
					'hostid' => ':host:h.dep',
					'type' => ITEM_TYPE_TRAPPER,
					'master_itemid' => ':item:master.item'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent item prototype (create).' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'item.error[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'type' => ITEM_TYPE_TRAPPER,
					'master_itemid' => ':item:master.item'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent discovery rule (create).' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'discovery.rule.2',
					'hostid' => ':host:h.dep',
					'type' => ITEM_TYPE_TRAPPER,
					'master_itemid' => ':item:master.item'
				]),
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],

			'Set "master_itemid" for not-dependent item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:independent.item',
					'master_itemid' => ':item:master.item'
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:independent.item.prototype[{#LLD}]',
					'master_itemid' => ':item:master.item'
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent discovery rule (update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:independent.rule',
					'master_itemid' => ':item:master.item'
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],

			'Check for maximum depth for the items tree (create). Add 4th level.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'hostid' => ':template:t.dep',
					'key_' => 'another.dependency.level',
					'master_itemid' => ':item:template.dependent.level.last'
				]),
				'error' => 'Cannot set dependency for item with key "another.dependency.level" on the master item with key "template.dependent.level.last" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth for the item prototypes tree (create). Add 4th level.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'hostid' => ':template:t.dep',
					'ruleid' => ':lld_rule:template.discovery.rule',
					'key_' => 'another.dependency.level[{#LLD}]',
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.level.last[{#LLD}]'
				]),
				'error' => 'Cannot set dependency for item prototype with key "another.dependency.level[{#LLD}]" on the master item prototype with key "template.dependent.item.prototype.level.last[{#LLD}]" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the discovery rule tree (create). Add 4th level.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'hostid' => ':template:t.dep',
					'key_' => 'another.dependency.level',
					'master_itemid' => ':item:template.dependent.level.last'
				]),
				'error' => 'Cannot set dependency for LLD rule with key "another.dependency.level" on the master item with key "template.dependent.level.last" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the items tree (update). Add 4th level.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.overflow.item',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.dependent.level.last'
				],
				'error' => 'Cannot set dependency for item with key "template.overflow.item" on the master item with key "template.dependent.level.last" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the item prototypes tree (update). Add 4th level.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.overflow.item.prototype[{#LLD}]',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.level.last[{#LLD}]'
				],
				'error' => 'Cannot set dependency for item prototype with key "template.overflow.item.prototype[{#LLD}]" on the master item prototype with key "template.dependent.item.prototype.level.last[{#LLD}]" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the discovery rule tree (update). Add 4th level.' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:template.discovery.rule.update',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.dependent.level.last'
				],
				'error' => 'Cannot set dependency for LLD rule with key "template.discovery.rule.update" on the master item with key "template.dependent.level.last" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the items tree (update). Add 4th level at the top.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.master.item',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.overflow.item'
				],
				'error' => 'Cannot set dependency for item with key "template.master.item" on the master item with key "template.overflow.item" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the mixed tree (update). Add 4th level at the top.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:mixed.dependency.master.item',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:mixed.dependency.item.overflow'
				],
				'error' => 'Cannot set dependency for item with key "mixed.dependency.master.item" on the master item with key "mixed.dependency.item.overflow" on the template "t.mixed.dependencies": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the item prototypes tree (update). Add 4th level at the top.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.master.item.prototype[{#LLD}]',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:template.overflow.item.prototype[{#LLD}]'
				],
				'error' => 'Cannot set dependency for item prototype with key "template.master.item.prototype[{#LLD}]" on the master item prototype with key "template.overflow.item.prototype[{#LLD}]" on the template "t.dep": allowed count of dependency levels would be exceeded.'
			],

			'Check for maximum depth of the items tree (link a template).' => [
				'method' => 'host.update',
				'params' => [
					'hostid' => ':host:h.dep',
					'templates' => [
						'templateid' => ':template:t.dep.2'
					]
				],
				'error' => 'Cannot set dependency for item with key "i.last[t.dep.2]" on the master item with key "i.before.last[t.dep.2]" on the host "h.dep": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the mixed tree (link a template).' => [
				'method' => 'host.update',
				'params' => [
					'hostid' => ':host:h.dep',
					'templates' => [
						'templateid' => ':template:t.dep.3'
					]
				],
				'error' => 'Cannot set dependency for item prototype with key "i.last[{#LLD}, t.dep.3]" on the master item with key "i.before.last[t.dep.3]" on the host "h.dep": allowed count of dependency levels would be exceeded.'
			],

			// 3 dependents exist on template.
			'Check for maximum count of items in the tree on the template level.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItemSet([
					'key_' => 'dependent.item',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.overflow.item'
				], 1, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1),
				'error' => 'Cannot set dependency for item with key "dependent.item.1" on the master item with key "template.overflow.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, no previous dependents.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.1',
						'hostid' => ':template:t.dep',
						'master_itemid' => ':item:template.overflow.item'
					], 1, 100),
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.2',
						'hostid' => ':template:t.dep',
						'master_itemid' => ':item:template.overflow.item'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1)
				),
				'error' => 'Cannot set dependency for item with key "dependent.item.set.1.1" on the master item with key "template.overflow.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, add one set to existing tree.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItemSet([
					'key_' => 'dependent.item',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.descendant'
				], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1),
				'error' => 'Cannot set dependency for item with key "dependent.item.4" on the master item with key "template.dependent.descendant" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, add sets to existing tree.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.1',
						'hostid' => ':template:t.dep',
						'master_itemid' => ':item:template.dependent.descendant'
					], 4, 100),
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.2',
						'hostid' => ':template:t.dep',
						'master_itemid' => ':item:template.dependent.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1)
				),
				'error' => 'Cannot set dependency for item with key "dependent.item.set.1.4" on the master item with key "template.dependent.descendant" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the template level, add one item prototype set.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototypeSet([
					'key_' => 'dependent.item.prototype[{#LLD}]',
					'hostid' => ':template:t.dep',
					'ruleid' => ':lld_rule:template.discovery.rule',
					'master_itemid' => ':item:template.dependent.item'
				], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.4[{#LLD}]" on the master item with key "template.dependent.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the template level, add two item prototype sets.' => [
				'method' => 'itemprototype.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemPrototypeSet([
						'key_' => 'dependent.item.prototype.set.1[{#LLD}]',
						'hostid' => ':template:t.dep',
						'ruleid' => ':lld_rule:template.discovery.rule',
						'master_itemid' => ':item:template.dependent.item'
					], 4, 100),
					CTestDataHelper::prepareItemPrototypeSet([
						'key_' => 'dependent.item.prototype.set.2[{#LLD}]',
						'hostid' => ':template:t.dep',
						'ruleid' => ':lld_rule:template.discovery.rule',
						'master_itemid' => ':item:template.dependent.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1)
				),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.set.1.4[{#LLD}]" on the master item with key "template.dependent.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],

			'Check for maximum count of items in the tree on the template level, fill to max.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.1',
						'hostid' => ':template:t.dep',
						'master_itemid' => ':item:template.dependent.descendant'
					], 4, 100),
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.2',
						'hostid' => ':template:t.dep',
						'master_itemid' => ':item:template.dependent.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT)
				),
				'error' => null
			],
			'Check for maximum count of items in the tree on the template level, adding overflow item.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'overflow.dependent.item',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.item'
				]),
				'error' => 'Cannot set dependency for item with key "overflow.dependent.item" on the master item with key "template.dependent.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the template level, add overflow item prototype.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'overflow.dependent.item.prototype[{#LLD}]',
					'hostid' => ':template:t.dep',
					'ruleid' => ':lld_rule:template.discovery.rule',
					'master_itemid' => ':item:template.dependent.item'
				]),
				'error' => 'Cannot set dependency for item prototype with key "overflow.dependent.item.prototype[{#LLD}]" on the master item with key "template.dependent.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, adding overflow LLD rule.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'overflow.dependent.rule',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.item'
				]),
				'error' => 'Cannot set dependency for LLD rule with key "overflow.dependent.rule" on the master item with key "template.dependent.item" on the template "t.dep": allowed count of dependent items would be exceeded.'
			],

			// 3 dependents exist on host
			'Check for maximum count of items in the tree on the host level.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItemSet([
					'key_' => 'dependent.item',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:master.item'
				], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1),
				'error' => 'Cannot set dependency for item with key "dependent.item.4" on the master item with key "master.item" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the host level, combination.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.1',
						'hostid' => ':host:h.dep',
						'master_itemid' => ':item:dependent.item'
					], 4, 100),
					CTestDataHelper::prepareItemSet([
						'key_' => 'dependent.item.set.2',
						'hostid' => ':host:h.dep',
						'master_itemid' => ':item:dependent.item.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1)
				),
				'error' => 'Cannot set dependency for item with key "dependent.item.set.1.4" on the master item with key "dependent.item" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of discovery rule in the tree on the host level.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRuleSet([
					'key_' => 'dependent.lld.rule',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:master.item'
				], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1),
				'error' => 'Cannot set dependency for LLD rule with key "dependent.lld.rule.4" on the master item with key "master.item" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of discovery rule in the tree on the host level, combination.' => [
				'method' => 'discoveryrule.create',
				'params' => array_merge(
					CTestDataHelper::prepareLldRuleSet([
						'key_' => 'dependent.lld.rule.set.1',
						'hostid' => ':host:h.dep',
						'master_itemid' => ':item:dependent.item'
					], 4, 100),
					CTestDataHelper::prepareLldRuleSet([
						'key_' => 'dependent.lld.rule.set.2',
						'hostid' => ':host:h.dep',
						'master_itemid' => ':item:dependent.item.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1)
				),
				'error' => 'Cannot set dependency for LLD rule with key "dependent.lld.rule.set.1.4" on the master item with key "dependent.item" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the host level.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototypeSet([
					'key_' => 'dependent.item.prototype[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'master_itemid' => ':item:master.item'
				], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.4[{#LLD}]" on the master item with key "master.item" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the host level, combination.' => [
				'method' => 'itemprototype.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemPrototypeSet([
						'key_' => 'dependent.item.prototype.set.1[{#LLD}]',
						'hostid' => ':host:h.dep',
						'ruleid' => ':lld_rule:discovery.rule',
						'master_itemid' => ':item:dependent.item'
					], 4, 100),
					CTestDataHelper::prepareItemPrototypeSet([
						'key_' => 'dependent.item.prototype.set.2[{#LLD}]',
						'hostid' => ':host:h.dep',
						'ruleid' => ':lld_rule:discovery.rule',
						'master_itemid' => ':item:dependent.item.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1)
				),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.set.1.4[{#LLD}]" on the master item with key "dependent.item" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],

			'Check for maximum count of items in the tree on the host level, fill to max.' => [
				'method' => 'itemprototype.create',
				'params' => array_merge(
					CTestDataHelper::prepareItemPrototypeSet([
						'key_' => 'dependent.item.prototype.set.1[{#LLD}]',
						'hostid' => ':host:h.dep',
						'ruleid' => ':lld_rule:discovery.rule',
						'master_itemid' => ':item:dependent.item'
					], 4, 100),
					CTestDataHelper::prepareItemPrototypeSet([
						'key_' => 'dependent.item.prototype.set.2[{#LLD}]',
						'hostid' => ':host:h.dep',
						'ruleid' => ':lld_rule:discovery.rule',
						'master_itemid' => ':item:dependent.item.descendant'
					], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT)
				),
				'error' => null
			],
			'Check for maximum count of items in the tree on the host level, adding overflow item.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'overflow.dependent.item',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:dependent.item.descendant'
				]),
				'error' => 'Cannot set dependency for item with key "overflow.dependent.item" on the master item with key "dependent.item.descendant" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the host level, add overflow item prototype.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'overflow.dependent.item.prototype[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'master_itemid' => ':item:dependent.item.descendant'
				]),
				'error' => 'Cannot set dependency for item prototype with key "overflow.dependent.item.prototype[{#LLD}]" on the master item with key "dependent.item.descendant" on the host "h.dep": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the host level, adding overflow LLD rule.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'overflow.dependent.rule',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:dependent.item.descendant'
				]),
				'error' => 'Cannot set dependency for LLD rule with key "overflow.dependent.rule" on the master item with key "dependent.item.descendant" on the host "h.dep": allowed count of dependent items would be exceeded.'
			]
		];
	}

	/**
	 * @dataProvider getTestCases
	 */
	public function testDependentItems_API(string $method, array $params, ?string $error = null) {
		// Skip tests with the default option ZBX_DEPENDENT_ITEM_MAX_COUNT to prevent long running tests.
		if (ZBX_DEPENDENT_ITEM_MAX_COUNT > 299) {
			self::markTestSkipped('Lower the ZBX_DEPENDENT_ITEM_MAX_COUNT option to run this test.');
		}

		CTestDataHelper::processReferences($method, $params);

		return $this->call($method, $params, $error);
	}
}
