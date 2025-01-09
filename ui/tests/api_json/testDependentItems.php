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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testDependentItems extends CAPITest {

	private const DEPENDENT_ITEM_TEST_COUNT = 300;

	public static function prepareTestData(): void {
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
							'key_' => 'template.dependent.item.level1',
							'master_itemid' => ':item:template.master.item'
						],
						[
							'key_' => 'template.dependent.item.level2',
							'master_itemid' => ':item:template.dependent.item.level1'
						],
						[
							'key_' => 'template.dependent.item.level3',
							'master_itemid' => ':item:template.dependent.item.level2'
						]
					],
					'lld_rules' => [
						[
							'key_' => 'template.discovery.rule',
							'item_prototypes' => [
								['key_' => 'template.master.item.prototype[{#LLD}]'],
								[
									'key_' => 'template.dependent.item.prototype.level1[{#LLD}]',
									'master_itemid' => ':item_prototype:template.master.item.prototype[{#LLD}]'
								],
								[
									'key_' => 'template.dependent.item.prototype.level2[{#LLD}]',
									'master_itemid' => ':item_prototype:template.dependent.item.prototype.level1[{#LLD}]'
								],
								[
									'key_' => 'template.dependent.item.prototype.level3[{#LLD}]',
									'master_itemid' => ':item_prototype:template.dependent.item.prototype.level2[{#LLD}]'
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
							'key_' => 'dependent.item.level1',
							'master_itemid' => ':item:master.item'
						],
						[
							'key_' => 'dependent.item.level2',
							'master_itemid' => ':item:dependent.item.level1'
						],
						[
							'key_' => 'dependent.item.level3',
							'master_itemid' => ':item:dependent.item.level2'
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
									'key_' => 'dependent.item.prototype.level1[{#LLD}]',
									'master_itemid' => ':item_prototype:master.item.prototype[{#LLD}]'
								],
								[
									'key_' => 'dependent.item.prototype.level2[{#LLD}]',
									'master_itemid' => ':item_prototype:dependent.item.prototype.level1[{#LLD}]'
								],
								[
									'key_' => 'dependent.item.prototype.level3[{#LLD}]',
									'master_itemid' => ':item_prototype:dependent.item.prototype.level2[{#LLD}]'
								]
							]
						],
						[
							'key_' => 'dependent.discovery.rule',
							'master_itemid' => ':item:master.item'
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
						['key_' => 'master.item.other']
					],
					'lld_rules' => [
						[
							'key_' => 'discovery.rule.other',
							'item_prototypes' => [
								['key_' => 'master.item.prototype.other[{#LLD}]'],
								[
									'key_' => 'dependent.item.prototype.other.level1[{#LLD}]',
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
					'itemid' => ':item:dependent.item.level1'
				],
				'error' => null
			],
			'Simple update dependent item prototype.' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype.level1[{#LLD}]'
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
					'itemid' => ':item:dependent.item.level1',
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
					'itemid' => ':item_prototype:dependent.item.prototype.level1[{#LLD}]',
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
					'itemid' => ':item:dependent.item.level1:host:h.dep',
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
					'itemid' => ':item_prototype:dependent.item.prototype.other.level1[{#LLD}]:host:h.dep.other',
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
					'itemid' => ':item_prototype:dependent.item.prototype.level1[{#LLD}]:host:h.dep',
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
					'itemid' => ':item:template.dependent.item.level1'
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
					'itemid' => ':item_prototype:template.dependent.item.prototype.level1[{#LLD}]'
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
					'itemid' => ':item:dependent.item.level1',
					'master_itemid' => ':item:dependent.item.level1'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],
			'Circular dependency to itself (itemprototype.update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:dependent.item.prototype.level1[{#LLD}]',
					'master_itemid' => ':item_prototype:dependent.item.prototype.level1[{#LLD}]'
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
					'master_itemid' => ':item:dependent.item.level2'
				],
				'error' => 'Invalid parameter "/1/master_itemid": circular item dependency is not allowed.'
			],
			'Circular dependency to descendant item prototypes (update).' => [
				'method' => 'itemprototype.update',
				'params' => [
					'itemid' => ':item_prototype:master.item.prototype[{#LLD}]',
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => ':item_prototype:dependent.item.prototype.level2[{#LLD}]'
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

			'Create 4th level dependent item on the template level.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'template.dependent.item.level4',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.item.level3'
				]),
				'error' => null
			],
			'Create 4th level dependent item prototype on the template level.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'template.dependent.item.prototype.level4[{#LLD}]',
					'hostid' => ':template:t.dep',
					'ruleid' => ':lld_rule:template.discovery.rule',
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.level3[{#LLD}]'
				]),
				'error' => null
			],
			'Create 4th level dependent LLD rule on the template level.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'template.dependent.lld.rule.level4',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.item.level3'
				]),
				'error' => null
			],
			'Create 4th level dependent item on the host level.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItem([
					'key_' => 'dependent.item.level4',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:dependent.item.level3'
				]),
				'error' => null
			],
			'Create 4th level dependent item prototype on the host level' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototype([
					'key_' => 'template.dependent.item.prototype.level4[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'master_itemid' => ':item_prototype:dependent.item.prototype.level3[{#LLD}]'
				])
			],
			'Create 4th level dependent LLD rule on the host level.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRule([
					'key_' => 'dependent.lld.rule.level4',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:dependent.item.level3'
				]),
				'error' => null
			],
			'Create many items (on the template level) which depends on the same master item.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItemSet([
					'key_' => 'template.dependent.item.set.1',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.item.level2'
				], 1, self::DEPENDENT_ITEM_TEST_COUNT),
				'error' => null
			],
			'Create many item prototypes (on the template level) which depends on the same master item prototype.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototypeSet([
					'key_' => 'template.dependent.item.prototype.set[{#LLD}]',
					'hostid' => ':template:t.dep',
					'ruleid' => ':lld_rule:template.discovery.rule',
					'master_itemid' => ':item_prototype:template.dependent.item.prototype.level2[{#LLD}]'
				], 1, self::DEPENDENT_ITEM_TEST_COUNT),
				'error' => null
			],
			'Create many LLD rules (on the template level) which depends on the same master item.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRuleSet([
					'key_' => 'template.dependent.lld.rule.set',
					'hostid' => ':template:t.dep',
					'master_itemid' => ':item:template.dependent.item.level2'
				], 1, self::DEPENDENT_ITEM_TEST_COUNT),
				'error' => null
			],
			'Create many items (on the host level) which depends on the same master item.' => [
				'method' => 'item.create',
				'params' => CTestDataHelper::prepareItemSet([
					'key_' => 'dependent.item.set',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:dependent.item.level2'
				], 1, self::DEPENDENT_ITEM_TEST_COUNT),
				'error' => null
			],
			'Create many item prototypes (on the host level) which depends on the same master item prototype.' => [
				'method' => 'itemprototype.create',
				'params' => CTestDataHelper::prepareItemPrototypeSet([
					'key_' => 'dependent.item.prototype.set[{#LLD}]',
					'hostid' => ':host:h.dep',
					'ruleid' => ':lld_rule:discovery.rule',
					'master_itemid' => ':item_prototype:dependent.item.prototype.level2[{#LLD}]'
				], 1, self::DEPENDENT_ITEM_TEST_COUNT),
				'error' => null
			],
			'Create many LLD rules (on the host level) which depends on the same master item.' => [
				'method' => 'discoveryrule.create',
				'params' => CTestDataHelper::prepareLldRuleSet([
					'key_' => 'dependent.lld.rule.set',
					'hostid' => ':host:h.dep',
					'master_itemid' => ':item:dependent.item.level2'
				], 1, self::DEPENDENT_ITEM_TEST_COUNT),
				'error' => null
			]
		];
	}

	/**
	 * @dataProvider getTestCases
	 */
	public function testDependentItems_API(string $method, array $params, ?string $error = null) {
		$api_object = substr($method, 0, strpos($method, '.'));
		$params = array_key_exists(0, $params) ? $params : [$params];

		foreach ($params as &$param) {
			if ($api_object === 'host') {
				CTestDataHelper::convertHostReferences($param);
			}
			elseif ($api_object === 'item') {
				CTestDataHelper::convertItemReferences($param);
			}
			elseif ($api_object === 'itemprototype') {
				CTestDataHelper::convertItemPrototypeReferences($param);
			}
			elseif ($api_object === 'discoveryrule') {
				CTestDataHelper::convertLldRuleReferences($param);
			}
		}
		unset($param);

		return $this->call($method, $params, $error);
	}
}
