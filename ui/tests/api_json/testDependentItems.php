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
require_once __DIR__.'/../include/helpers/CMockDataHelper.php';


class testDependentItems extends CAPITest {

	public static function setUpBeforeClass(): void {
		// Do nothing if test will be skipped.
		if (ZBX_DEPENDENT_ITEM_MAX_COUNT > 299) {
			return;
		}

		CMockDataHelper::createObjects([
			[
				'host_group' => [
					['dependent.items.tests.host.group']
				],
				'template_group' => [
					['dependent.items.tests.template.group']
				]
			],
			[
				'template' => [
					['dependent.items.template']
				],
				'item' => [
					['template.master.item'],
					['template.dependent.item', ['master_itemid' => ':item:template.master.item']],
					['template.dependent.descendant', ['master_itemid' => ':item:template.dependent.item']],
					['template.dependent.level.last', ['master_itemid' => ':item:template.dependent.descendant']],
					['template.item.another'],

					// Has items, lld_rule and prototype as dependents.
					['mixed.dependency.master.item'],
					['mixed.dependency.dependent.item', ['master_itemid' => ':item:mixed.dependency.master.item']],
					['mixed.dependency.dependent.descendant', [
						'master_itemid' => ':item:mixed.dependency.dependent.item'
					]]
				],
				'lld_rule' => [
					['template.discovery.rule.update'],
					['mixed.dependency.dependent.rule', [
						'master_itemid' => ':item:mixed.dependency.dependent.descendant'
					]],

					['template.discovery.rule']
				],
				'item_prototype' => [
					['template.master.item.prototype'],
					['template.dependent.item.prototype', [
						'master_itemid' => ':item_prototype:template.master.item.prototype'
					]],
					['template.dependent.item.prototype.descendant', [
						'master_itemid' => ':item_prototype:template.dependent.item.prototype'
					]],
					['template.dependent.item.prototype.level.last', [
						'master_itemid' => ':item_prototype:template.dependent.item.prototype.descendant'
					]],

					['template.dependent.item.prototype.another'],
					['mixed.dependency.item.prototype', [
						'master_itemid' => ':item:mixed.dependency.dependent.descendant'
					]]
				]
			],
			[
				'host' => [
					['dependent.items.host']
				],
				'item' => [
					['independent.item'],

					['master.item'],
					['dependent.item', ['master_itemid' => ':item:master.item']],
					['dependent.item.descendant', ['master_itemid' => ':item:dependent.item']],

					['to.match.template.dependent.level.last'],
					['dependent.item.for.inherit.test', [
						'master_itemid' => ':item:to.match.template.dependent.level.last'
					]]
				],
				'lld_rule' => [
					['independent.rule'],
					['to.match.template.discovery.rule'],
					['discovery.rule'],
					['dependent.discovery.rule', ['master_itemid' => ':item:master.item']]
				],
				'item_prototype' => [
					['master.item.prototype', ['ruleid' => ':lld_rule:discovery.rule']],
					['dependent.item.prototype', [
						'master_itemid' => ':item_prototype:master.item.prototype',
						'ruleid' => ':lld_rule:discovery.rule'
					]],
					['dependent.item_prototype.descendant', [
						'master_itemid' => ':item_prototype:dependent.item.prototype',
						'ruleid' => ':lld_rule:discovery.rule'
					]],
					['independent.item.prototype', [
						'ruleid' => ':lld_rule:discovery.rule'
					]],

					['mixed.dependency.item.prototype', ['ruleid' => ':lld_rule:to.match.template.discovery.rule']],
					['mixed.dependency.item.prototype.descendant', [
						'ruleid' => ':lld_rule:to.match.template.discovery.rule',
						'master_itemid' => ':item_prototype:mixed.dependency.item.prototype'
					]]
				],
				'host_prototype' => [
					['host.prototype.discovery', ['ruleid' => ':lld_rule:discovery.rule']]
				]
			],
			[
				'discovered_host' => [
					['dependent.items.host.discovered', ['parent_hostid' => ':host_prototype:host.prototype.discovery']]
				],
				'item' => [
					['discovered.master']
				],
				'discovered_item' => [
					['master.discovered', ['parent_itemid' => ':item_prototype:master.item.prototype']],
					['dependent.discovered', [
						'parent_itemid' => ':item_prototype:dependent.item.prototype',
						'master_itemid' => ':item:discovered.master'
					]]
				]
			],
			[
				'host' => [
					['dependent.items.host.other']
				],
				'item' => [
					['master.item.other'],
					['dependent.item.other', ['master_itemid' => ':item:master.item.other']]
				],
				'lld_rule' => [
					['discovery.rule.other']
				],
				'item_prototype' => [
					['master.item.prototype.other'],
					['dependent.item.prototype.other', [
						'master_itemid' => ':item_prototype:master.item.prototype.other'
					]]
				]
			]
		]);
	}

	public static function tearDownAfterClass(): void {
		CMockDataHelper::cleanUp();
	}

	public static function getTestCases() {
		$default_ids = [
			'hostid' => ':host:dependent.items.host',
			'masterid' => ':item:master.item',
			'templateid' => ':template:dependent.items.template',
			'ruleid' => ':lld_rule:discovery.rule',
			'nonexistent_itemid' => 9999
		];

		return [
			'Simple update master item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => $default_ids['masterid']
				],
				'error' => null
			],
			'Simple update master item on template.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.master.item'
				],
				'error' => null
			],
			'Simple update master item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:master.item.prototype'
				],
				'error' => null
			],
			'Simple update discovered master item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':discovered_item:master.discovered'
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
					'itemid' => ':item_prototype:dependent.item.prototype'
				],
				'error' => null
			],
			'Simple update discovered dependent item.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':discovered_item:dependent.discovered'
				],
				'error' => null
			],
			'Simple update dependent discovery rule.' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => $default_ids['ruleid']
				],
				'error' => null
			],

			'Set incorrect master_itemid for item (create).' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['dependent.error', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => $default_ids['nonexistent_itemid']]
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:dependent.item',
					'master_itemid' => $default_ids['nonexistent_itemid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for item prototype (create).' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItemPrototype(['dependent.item.prototype.new', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => $default_ids['nonexistent_itemid']]
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype',
					'master_itemid' => $default_ids['nonexistent_itemid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.'
			],
			'Set incorrect master_itemid for discovery rule (create).' => [
				'method' => 'discoveryrule.create',
				'params' => CMockDataHelper::mockLldRule(['discovery.rule.error', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => $default_ids['nonexistent_itemid']]
				]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set incorrect master_itemid for discovery rule (update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:dependent.discovery.rule',
					'master_itemid' => $default_ids['nonexistent_itemid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Set master_itemid from other host for item (create).' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['dependent.item.new', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => ':item:master.item.other']
				]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],
			'Set master_itemid from other host for item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:dependent.item',
					'master_itemid' => ':item:master.item.other'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],
			'Set master_itemid from other host for item prototype (create).' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['dependent.item.prototype.other.new', [
					'hostid' => ':host:dependent.items.host.other',
					'ruleid' => ':lld_rule:discovery.rule.other',
					'master_itemid' => ':item_prototype:master.item.prototype'
				]]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other host for item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype.other',
					'master_itemid' => ':item_prototype:master.item.prototype'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for item prototype (create).' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['dependent.item.prototype.new', [
					'hostid' => $default_ids['hostid'],
					'ruleid' => $default_ids['ruleid'],
					'master_itemid' => ':item_prototype:master.item.prototype.other'
				]]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype',
					'master_itemid' => ':item_prototype:master.item.prototype.other'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item/item prototype ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for LLD rule (create).' => [
				'method' => 'discoveryrule.create',
				'params' => CMockDataHelper::mockLldRule(['discovery.rule.new', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => ':item:master.item.other'
				]]),
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],
			'Set master_itemid from other discovery rule for LLD rule (update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:dependent.discovery.rule',
					'master_itemid' => ':item:master.item.other'
				],
				'error' => 'Invalid parameter "/1/master_itemid": cannot be an item ID from another host or template.'
			],

			// Dependencies on discovered items not allowed.
			'Create dependent item, which depends on discovered item.' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['dependent.on.master.discovered', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => ':discovered_item:master.discovered'
				]]),
				'error' => 'Invalid parameter "/1/master_itemid": an item ID is expected.'
			],
			'Create dependent item prototype, which depends on discovered item.' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['item.prototype.dependent.on.master.discovered', [
					'hostid' => $default_ids['hostid'],
					'ruleid' => $default_ids['ruleid'],
					'master_itemid' => ':discovered_item:master.discovered'
				]]),
				'error' => 'Invalid parameter "/1/master_itemid": an item/item prototype ID is expected.'
			],
			'Create dependent discovery rule, which depends on discovered item.' => [
				'method' => 'discoveryrule.create',
				'params' => CMockDataHelper::mockLldRule(['lld_rule.dependent.on.master.discovered', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => ':discovered_item:master.discovered'
				]]),
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
					'itemid' => ':item_prototype:template.master.item.prototype'
				],
				'error' => null
			],
			'Simple update templated dependent item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.dependent.item.prototype'
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
					'itemid' => ':item_prototype:dependent.item.prototype',
					'master_itemid' => ':item_prototype:dependent.item.prototype'
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
					'itemid' => $default_ids['masterid'],
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:dependent.item.descendant'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],
			'Circular dependency to descendant item prototypes (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:master.item.prototype',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:dependent.item_prototype.descendant'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],

			'Set "master_itemid" for not-dependent item (create).' => [
				'method' => 'item.create',
				'params' => [
					'hostid' => $default_ids['hostid'],
					'name' => 'item.error',
					'type' => ITEM_TYPE_TRAPPER,
					'key_' => 'item.error',
					'value_type' => ITEM_VALUE_TYPE_STR,
					'master_itemid' => $default_ids['masterid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent item prototype (create).' => [
				'method' => 'itemprototype.create',
				'params' =>  [
					'hostid' => $default_ids['hostid'],
					'ruleid' => $default_ids['ruleid'],
					'name' => 'item.error',
					'type' => ITEM_TYPE_TRAPPER,
					'key_' => 'item.error[{#LLD}]',
					'value_type' => ITEM_VALUE_TYPE_STR,
					'master_itemid' => $default_ids['masterid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent discovery rule (create).' => [
				'method' => 'discoveryrule.create',
				'params' => [
					'hostid' => $default_ids['hostid'],
					'name' => 'discovery.rule.2',
					'key_' => 'discovery.rule.2',
					'type' => 2,
					'master_itemid' => $default_ids['masterid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],

			'Set "master_itemid" for not-dependent item (update).' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:independent.item',
					'master_itemid' => $default_ids['masterid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent item prototype (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:independent.item.prototype',
					'master_itemid' => $default_ids['masterid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],
			'Set "master_itemid" for not-dependent discovery rule (update).' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:independent.rule',
					'master_itemid' => $default_ids['masterid']
				],
				'error' => 'Invalid parameter "/1/master_itemid": value must be 0.'
			],

			'Check for maximum depth for the items tree (create). Add 4th level.' => [
				'method' => 'item.create',
				'params' => [
					'hostid' => $default_ids['templateid'],
					'name' => 'another.dependency.level',
					'key_' => 'another.dependency.level',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.dependent.level.last',
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				'error' => 'Cannot set dependency for item with key "another.dependency.level" on the master item with key "template.dependent.level.last" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth for the item prototypes tree (create). Add 4th level.' => [
				'method' => 'itemprototype.create',
				'params' => [
					'hostid' => $default_ids['templateid'],
					'ruleid' => ':lld_rule:template.discovery.rule',
					'name' => 'another.dependency.level[{#LLD}]',
					'key_' => 'another.dependency.level[{#LLD}]',
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_STR,
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.level.last'
				],
				'error' => 'Cannot set dependency for item prototype with key "another.dependency.level[{#LLD}]" on the master item prototype with key "template.dependent.item.prototype.level.last[{#LLD}]" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the discovery rule tree (create). Add 4th level.' => [
				'method' => 'discoveryrule.create',
				'params' => [
					'hostid' => $default_ids['templateid'],
					'name' => 'another.dependency.level',
					'key_' => 'another.dependency.level',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.dependent.level.last'
				],
				'error' => 'Cannot set dependency for LLD rule with key "another.dependency.level" on the master item with key "template.dependent.level.last" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the items tree (update). Add 4th level.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.item.another',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.dependent.level.last'
				],
				'error' => 'Cannot set dependency for item with key "template.item.another" on the master item with key "template.dependent.level.last" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the item prototypes tree (update). Add 4th level.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.dependent.item.prototype.another',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.level.last'
				],
				'error' => 'Cannot set dependency for item prototype with key "template.dependent.item.prototype.another[{#LLD}]" on the master item prototype with key "template.dependent.item.prototype.level.last[{#LLD}]" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the discovery rule tree (update). Add 4th level.' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:template.discovery.rule.update',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.dependent.level.last'
				],
				'error' => 'Cannot set dependency for LLD rule with key "template.discovery.rule.update" on the master item with key "template.dependent.level.last" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the items tree (update). Add 4th level at the top.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:template.master.item',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.item.another'
				],
				'error' => 'Cannot set dependency for item with key "template.master.item" on the master item with key "template.item.another" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the mixed tree (update). Add 4th level at the top.' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:mixed.dependency.master.item',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item:template.item.another'
				],
				'error' => 'Cannot set dependency for item with key "mixed.dependency.master.item" on the master item with key "template.item.another" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],
			'Check for maximum depth of the item prototypes tree (update). Add 4th level at the top.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:template.master.item.prototype',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.another'
				],
				'error' => 'Cannot set dependency for item prototype with key "template.master.item.prototype[{#LLD}]" on the master item prototype with key "template.dependent.item.prototype.another[{#LLD}]" on the template "dependent.items.template": allowed count of dependency levels would be exceeded.'
			],

			'Step to update a master item on host to match name of deep descendent on template for next case' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:to.match.template.dependent.level.last',
					'key_' => 'template.dependent.level.last'
				],
				'error' => null
			],
			'Check for maximum depth of the items tree (link a template).' => [
				'method' => 'host.update',
				'params' => [
					'hostid' => $default_ids['hostid'],
					'templates' => [
						'templateid' => $default_ids['templateid']
					]
				],
				'error' => 'Cannot set dependency for item with key "template.dependent.level.last" on the master item with key "template.dependent.descendant" on the host "dependent.items.host": allowed count of dependency levels would be exceeded.'
			],
			'Step to revert rename for previous case' => [
				'method' => 'item.update',
				'params' => [
					'itemid' => ':item:to.match.template.dependent.level.last',
					'key_' => 'to.match.template.dependent.level.last'
				],
				'error' => null
			],
			'Step to update LLD rule on host to match one on template.' => [
				'method' => 'discoveryrule.update',
				'params' => [
					'itemid' => ':lld_rule:to.match.template.discovery.rule',
					'key_' => 'template.discovery.rule'
				]
			],
			'Check for maximum depth of the mixed tree (link a template).' => [
				'method' => 'host.update',
				'params' => [
					'hostid' => $default_ids['hostid'],
					'templates' => [
						'templateid' => $default_ids['templateid']
					]
				],
				'error' => 'Cannot set dependency for item prototype with key "mixed.dependency.item.prototype[{#LLD}]" on the master item with key "mixed.dependency.dependent.descendant" on the host "dependent.items.host": allowed count of dependency levels would be exceeded.'
			],

			// 3 dependents exist on template.
			'Check for maximum count of items in the tree on the template level.' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['dependent.item', [
						'hostid' => $default_ids['templateid'],
						'master_itemid' => ':item:template.item.another'
					], 1, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
				]),
				'error' => 'Cannot set dependency for item with key "dependent.item.1" on the master item with key "template.item.another" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, no previous dependents.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CMockDataHelper::mockItem(['dependent.item.set.1', [
							'hostid' => $default_ids['templateid'],
							'master_itemid' => ':item:template.item.another'
						], 1, 100
					]),
					CMockDataHelper::mockItem(['dependent.item.set.2', [
							'hostid' => $default_ids['templateid'],
							'master_itemid' => ':item:template.item.another'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
					])
				),
				'error' => 'Cannot set dependency for item with key "dependent.item.set.1.1" on the master item with key "template.item.another" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, add one set to existing tree.' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['dependent.item', [
					'hostid' => $default_ids['templateid'],
					'master_itemid' => ':item:template.dependent.descendant'
				], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1]),
				'error' => 'Cannot set dependency for item with key "dependent.item.4" on the master item with key "template.dependent.descendant" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, add sets to existing tree.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CMockDataHelper::mockItem(['dependent.item.set.1', [
							'hostid' => $default_ids['templateid'],
							'master_itemid' => ':item:template.dependent.descendant'
						], 4, 100
					]),
					CMockDataHelper::mockItem(['dependent.item.set.2', [
							'hostid' => $default_ids['templateid'],
							'master_itemid' => ':item:template.dependent.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
					])
				),
				'error' => 'Cannot set dependency for item with key "dependent.item.set.1.4" on the master item with key "template.dependent.descendant" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the template level, add one item prototype set.' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['dependent.item.prototype', [
						'hostid' => $default_ids['templateid'],
						'ruleid' => ':lld_rule:template.discovery.rule',
						'master_itemid' => ':item:template.dependent.item'
					], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
				]),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.4[{#LLD}]" on the master item with key "template.dependent.item" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the template level, add two item prototype sets.' => [
				'method' => 'itemprototype.create',
				'params' => array_merge(
					CMockDataHelper::mockItemPrototype(['dependent.item.prototype.set.1', [
							'hostid' => $default_ids['templateid'],
							'ruleid' => ':lld_rule:template.discovery.rule',
							'master_itemid' => ':item:template.dependent.item'
						], 4, 100
					]),
					CMockDataHelper::mockItemPrototype(['dependent.item.prototype.set.2', [
							'hostid' => $default_ids['templateid'],
							'ruleid' => ':lld_rule:template.discovery.rule',
							'master_itemid' => ':item:template.dependent.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
					])
				),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.set.1.4[{#LLD}]" on the master item with key "template.dependent.item" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],

			'Check for maximum count of items in the tree on the template level, fill to max.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CMockDataHelper::mockItem(['dependent.item.set.1', [
							'hostid' => $default_ids['templateid'],
							'master_itemid' => ':item:template.dependent.descendant'
						], 4, 100
					]),
					CMockDataHelper::mockItem(['dependent.item.set.2', [
							'hostid' => $default_ids['templateid'],
							'master_itemid' => ':item:template.dependent.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT
					])
				),
				'error' => null
			],
			'Check for maximum count of items in the tree on the template level, adding overflow item.' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['overflow.dependent.item', [
					'hostid' => $default_ids['templateid'],
					'master_itemid' => ':item:template.dependent.item'
				]]),
				'error' => 'Cannot set dependency for item with key "overflow.dependent.item" on the master item with key "template.dependent.item" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the template level, add overflow item prototype.' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['overflow.dependent.item.prototype', [
					'hostid' => $default_ids['templateid'],
					'ruleid' => ':lld_rule:template.discovery.rule',
					'master_itemid' => ':item:template.dependent.item'
				]]),
				'error' => 'Cannot set dependency for item prototype with key "overflow.dependent.item.prototype[{#LLD}]" on the master item with key "template.dependent.item" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the template level, adding overflow LLD rule.' => [
				'method' => 'discoveryrule.create',
				'params' => CMockDataHelper::mockLldRule(['overflow.dependent.rule', [
					'hostid' => $default_ids['templateid'],
					'master_itemid' => ':item:template.dependent.item'
				]]),
				'error' => 'Cannot set dependency for LLD rule with key "overflow.dependent.rule" on the master item with key "template.dependent.item" on the template "dependent.items.template": allowed count of dependent items would be exceeded.'
			],

			// 3 dependents exist on host
			'Check for maximum count of items in the tree on the host level.' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['dependent.item', [
						'hostid' => $default_ids['hostid'],
						'master_itemid' => ':item:master.item'
					], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
				]),
				'error' => 'Cannot set dependency for item with key "dependent.item.4" on the master item with key "master.item" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the host level, combination.' => [
				'method' => 'item.create',
				'params' => array_merge(
					CMockDataHelper::mockItem(['dependent.item.set.1', [
							'hostid' => $default_ids['hostid'],
							'master_itemid' => ':item:dependent.item'
						], 4, 100
					]),
					CMockDataHelper::mockItem(['dependent.item.set.2', [
							'hostid' => $default_ids['hostid'],
							'master_itemid' => ':item:dependent.item.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
					])
				),
				'error' => 'Cannot set dependency for item with key "dependent.item.set.1.4" on the master item with key "dependent.item" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of discovery rule in the tree on the host level.' => [
				'method' => 'discoveryrule.create',
				'params' => CMockDataHelper::mockLldRule(['dependent.lld.rule', [
						'hostid' => $default_ids['hostid'],
						'master_itemid' => ':item:master.item'
					], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
				]),
				'error' => 'Cannot set dependency for LLD rule with key "dependent.lld.rule.4" on the master item with key "master.item" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of discovery rule in the tree on the host level, combination.' => [
				'method' => 'discoveryrule.create',
				'params' => array_merge(
					CMockDataHelper::mockLldRule(['dependent.lld.rule.set.1', [
							'hostid' => $default_ids['hostid'],
							'master_itemid' => ':item:dependent.item'
						], 4, 100
					]),
					CMockDataHelper::mockLldRule(['dependent.lld.rule.set.2', [
							'hostid' => $default_ids['hostid'],
							'master_itemid' => ':item:dependent.item.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
					])
				),
				'error' => 'Cannot set dependency for LLD rule with key "dependent.lld.rule.set.1.4" on the master item with key "dependent.item" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the host level.' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['dependent.item.prototype', [
						'hostid' => $default_ids['hostid'],
						'ruleid' => $default_ids['ruleid'],
						'master_itemid' => ':item:master.item'
					], 4, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
				]),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.4[{#LLD}]" on the master item with key "master.item" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the host level, combination.' => [
				'method' => 'itemprototype.create',
				'params' => array_merge(
					CMockDataHelper::mockItemPrototype(['dependent.item.prototype.set.1', [
							'hostid' => $default_ids['hostid'],
							'ruleid' => $default_ids['ruleid'],
							'master_itemid' => ':item:dependent.item'
						], 4, 100
					]),
					CMockDataHelper::mockItemPrototype(['dependent.item.prototype.set.2', [
							'hostid' => $default_ids['hostid'],
							'ruleid' => $default_ids['ruleid'],
							'master_itemid' => ':item:dependent.item.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT + 1
					])
				),
				'error' => 'Cannot set dependency for item prototype with key "dependent.item.prototype.set.1.4[{#LLD}]" on the master item with key "dependent.item" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],

			'Check for maximum count of items in the tree on the host level, fill to max.' => [
				'method' => 'itemprototype.create',
				'params' => array_merge(
					CMockDataHelper::mockItemPrototype(['dependent.item.prototype.set.1', [
							'hostid' => $default_ids['hostid'],
							'ruleid' => $default_ids['ruleid'],
							'master_itemid' => ':item:dependent.item'
						], 4, 100
					]),
					CMockDataHelper::mockItemPrototype(['dependent.item.prototype.set.2', [
							'hostid' => $default_ids['hostid'],
							'ruleid' => $default_ids['ruleid'],
							'master_itemid' => ':item:dependent.item.descendant'
						], 101, ZBX_DEPENDENT_ITEM_MAX_COUNT
					])
				),
				'error' => null
			],
			'Check for maximum count of items in the tree on the host level, adding overflow item.' => [
				'method' => 'item.create',
				'params' => CMockDataHelper::mockItem(['overflow.dependent.item', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => ':item:dependent.item.descendant'
				]]),
				'error' => 'Cannot set dependency for item with key "overflow.dependent.item" on the master item with key "dependent.item.descendant" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of item prototypes in the tree on the host level, add overflow item prototype.' => [
				'method' => 'itemprototype.create',
				'params' => CMockDataHelper::mockItemPrototype(['overflow.dependent.item.prototype', [
					'hostid' => $default_ids['hostid'],
					'ruleid' => $default_ids['ruleid'],
					'master_itemid' => ':item:dependent.item.descendant'
				]]),
				'error' => 'Cannot set dependency for item prototype with key "overflow.dependent.item.prototype[{#LLD}]" on the master item with key "dependent.item.descendant" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
			],
			'Check for maximum count of items in the tree on the host level, adding overflow LLD rule.' => [
				'method' => 'discoveryrule.create',
				'params' => CMockDataHelper::mockLldRule(['overflow.dependent.rule', [
					'hostid' => $default_ids['hostid'],
					'master_itemid' => ':item:dependent.item.descendant'
				]]),
				'error' => 'Cannot set dependency for LLD rule with key "overflow.dependent.rule" on the master item with key "dependent.item.descendant" on the host "dependent.items.host": allowed count of dependent items would be exceeded.'
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

		CMockDataHelper::processReferences($params);

		return $this->call($method, $params, $error);
	}
}
