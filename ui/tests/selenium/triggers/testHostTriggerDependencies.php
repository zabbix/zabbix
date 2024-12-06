<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testTriggerDependencies.php';

/**
 * @backup hosts
 *
 * @onBefore prepareHostTriggersData
 */
class testHostTriggerDependencies extends testTriggerDependencies {

	protected static $hostids;
	protected static $host_druleids;

	public function prepareHostTriggersData() {
		$template_result = CDataHelper::createTemplates([
			[
				'host' => 'Template that linked to host',
				'groups' => ['groupid' => 1],
				'items' => [
					[
						'name' => 'template item for linking',
						'key_' => 'everything_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Drule for linking',
						'key_' => 'linked_drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		$templateids = $template_result['templateids'];
		$template_druleids = $template_result['discoveryruleids'];

		$template_triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger that linked',
				'expression' => 'last(/Template that linked to host/everything_2)=0'
			]
		]);
		$this->assertArrayHasKey('triggerids', $template_triggers);

		$item_prototype  = CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Item prot for linking',
				'key_' => 'linking_prot_[{#KEY}]',
				'hostid' => $templateids['Template that linked to host'],
				'ruleid' => $template_druleids['Template that linked to host:linked_drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype );

		$trigger_prototype = CDataHelper::call('triggerprototype.create', [
			[
				'description' => 'trigger prototype linked{#KEY}',
				'expression' => 'last(/Template that linked to host/linking_prot_[{#KEY}])=0'
			],
			[
				'description' => 'trigger prototype linked update{#KEY}',
				'expression' => 'last(/Template that linked to host/linking_prot_[{#KEY}])=0'
			]
		]);
		$this->assertArrayHasKey('triggerids', $trigger_prototype);

		$host_result = CDataHelper::createHosts([
			[
				'host' => 'Host with linked template',
				'templates' => ['templateid' => $templateids['Template that linked to host']],
				'groups' => [['groupid' => 4]],
				'items' => [
					[
						'name' => 'Host item 2',
						'key_' => 'host_item_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Drule for host with linking',
						'key_' => 'host_linked_drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			],
			[
				'host' => 'Host with everything',
				'templates' => ['templateid' => $templateids['Template that linked to host']],
				'groups' => [['groupid' => 4]],
				'items' => [
					[
						'name' => 'Host item 1',
						'key_' => 'host_item_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Drule for host everything',
						'key_' => 'host_everything_drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			],
			[
				'host' => 'Host for trigger dependency list',
				'groups' => [['groupid' => 4]],
				'items' => [
					[
						'name' => 'Trap1',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'discoveryrules' => [
					[
						'name' => 'Drule for trigger prototypes dependency list',
						'key_' => 'drule_triggers_dependency',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);
		self::$hostids = $host_result['hostids'];
		self::$host_druleids = $host_result['discoveryruleids'];

		$host_triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Host trigger update',
				'expression' => 'last(/Host with everything/host_item_1)=0'
			],
			[
				'description' => 'Host trigger everything',
				'expression' => 'last(/Host with everything/host_item_1)=0'
			],
			[
				'description' => 'Host trigger everything 2',
				'expression' => 'last(/Host with everything/host_item_1)=0'
			],
			[
				'description' => 'Host trigger 2',
				'expression' => 'last(/Host with linked template/host_item_2)=0'
			],
			[
				'description' => 'Host trigger with dependence',
				'expression' => 'last(/Host with everything/host_item_1)=0'
			],
			[
				'description' => 'Trigger for dependency list test 1',
				'expression' => 'last(/Host for trigger dependency list/trap1)=0'
			],
			[
				'description' => 'Trigger for dependency list test 2',
				'expression' => 'last(/Host for trigger dependency list/trap1)=1'
			],
			[
				'description' => 'Trigger for dependency list test 3',
				'expression' => 'last(/Host for trigger dependency list/trap1)=2'
			]
		]);
		$this->assertArrayHasKey('triggerids', $host_triggers);
		$host_triggerid = CDataHelper::getIds('description');

		CDataHelper::call('trigger.update', [
			[
				'triggerid' => $host_triggerid['Host trigger with dependence'],
				'dependencies' => [['triggerid' => $host_triggerid['Host trigger update']]]
			]
		]);

		$host_item_prototype = CDataHelper::call('itemprototype.create', [
			[
				'name' => 'Host Item prot with everything',
				'key_' => 'host_everything_prot_[{#KEY}]',
				'hostid' => self::$hostids['Host with everything'],
				'ruleid' => self::$host_druleids['Host with everything:host_everything_drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			],
			[
				'name' => 'Host Item prot for linking',
				'key_' => 'host_linking_prot_[{#KEY}]',
				'hostid' => self::$hostids['Host with linked template'],
				'ruleid' => self::$host_druleids['Host with linked template:host_linked_drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			],
			[
				'name' => 'Host Item prototype for trigger dependency list',
				'key_' => 'trigger_dep_item[{#KEY}]',
				'hostid' => self::$hostids['Host for trigger dependency list'],
				'ruleid' => self::$host_druleids['Host for trigger dependency list:drule_triggers_dependency'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);
		$this->assertArrayHasKey('itemids', $host_item_prototype);

		$host_trigger_prototype = CDataHelper::call('triggerprototype.create', [
			[
				'description' => 'Host trigger prototype update{#KEY}',
				'expression' => 'last(/Host with everything/host_everything_prot_[{#KEY}])=0'
			],
			[
				'description' => 'Host trigger prot simple{#KEY}',
				'expression' => 'last(/Host with everything/host_everything_prot_[{#KEY}])=0'
			],
			[
				'description' => 'Host trigger prot simple_2{#KEY}',
				'expression' => 'last(/Host with everything/host_everything_prot_[{#KEY}])=0'
			],
			[
				'description' => 'Host trigger prot for linked{#KEY}',
				'expression' => 'last(/Host with linked template/host_linking_prot_[{#KEY}])=0'
			],
			[
				'description' => 'Host trigger prot for linked update{#KEY}',
				'expression' => 'last(/Host with linked template/host_linking_prot_[{#KEY}])=0'
			],
			[
				'description' => '1 Host trigger prototype for dependency list{#KEY}',
				'expression' => 'last(/Host for trigger dependency list/trigger_dep_item[{#KEY}])=0'
			],
			[
				'description' => '2 Host trigger prototype for dependency list{#KEY}',
				'expression' => 'last(/Host for trigger dependency list/trigger_dep_item[{#KEY}])=0'
			],
			[
				'description' => '3 Host trigger prototype for dependency list{#KEY}',
				'expression' => 'last(/Host for trigger dependency list/trigger_dep_item[{#KEY}])=0'
			]
		]);
		$this->assertArrayHasKey('triggerids', $host_trigger_prototype);

		// Add dependence to already discovered trigger for one special scenario.
		DBexecute('INSERT INTO trigger_depends (triggerdepid, triggerid_down, triggerid_up) VALUES (99555, 100069, 100067)');
	}

	public static function getTriggerCreateData() {
		return [
			// #0 dependencies on another trigger on same host.
			[
				[
					'name' => 'Simple trigger',
					'dependencies' => [
						'Host with everything' => ['Host trigger everything']
					],
					'result' => [
						'Host with everything: Host trigger everything'
					]
				]
			],
			// #1 dependencies on 2 triggers from same host.
			[
				[
					'name' => 'Two trigger dependencies',
					'dependencies' => [
						'Host with everything' => ['Host trigger everything', 'Host trigger everything 2']
					],
					'result' => [
						'Host with everything: Host trigger everything',
						'Host with everything: Host trigger everything 2'
					]
				]
			],
			// #2 dependencies on trigger from another host.
			[
				[
					'name' => 'Triggers from another hosts',
					'dependencies' => [
						'Host with linked template' => ['Host trigger 2']
					],
					'result' => [
						'Host with linked template: Host trigger 2'
					]
				]
			],
			// #3 dependencies on trigger from another and same host.
			[
				[
					'name' => 'Two triggers from different',
					'dependencies' => [
						'Host with linked template' => ['Host trigger 2'],
						'Host with everything' => ['Host trigger everything']
					],
					'result' => [
						'Host with linked template: Host trigger 2',
						'Host with everything: Host trigger everything'
					]
				]
			],
			// #4 dependencies on linked trigger.
			[
				[
					'name' => 'Depends on linked trigger',
					'dependencies' => [
						'Host with linked template' => ['Trigger that linked']
					],
					'result' => [
						'Host with linked template: Trigger that linked'
					]
				]
			]
		];
	}

	/**
	 * Create trigger on host with dependencies.
	 *
	 * @dataProvider getTriggerCreateData
	 */
	public function testHostTriggerDependencies_TriggerCreate($data) {
		$this->page->login()->open('zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host with everything'].'&context=host'
		)->waitUntilReady();
		$this->query('button:Create trigger')->one()->click();
		$this->page->waitUntilReady();

		// Creating new trigger - expression is mandatory.
		$this->triggerCreateUpdate($data, 'Trigger added', 'last(/Host with everything/host_item_1)=0');
	}

	public static function getTriggerUpdateData() {
		return [
			// Dependencies on trigger that depends on updated trigger.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Host trigger update',
					'dependencies' => [
						'Host with everything' => ['Host trigger with dependence']
					],
					'error_message' => 'Trigger "Host trigger update" cannot depend on the trigger'.
						' "Host trigger with dependence", because a circular linkage'.
						' ("Host trigger with dependence" -> "Host trigger update" -> "Host trigger with dependence")'.
						' would occur.'
				]
			]
		];
	}

	/**
	 * Update trigger on host with dependencies.
	 *
	 * @dataProvider getTriggerUpdateData
	 * @dataProvider getTriggerCreateData
	 */
	public function testHostTriggerDependencies_TriggerUpdate($data) {
		$this->page->login()->open('zabbix.php?action=trigger.list&filter_hostids%5B0%5D='.self::$hostids['Host with everything'].
				'&context=host'
		)->waitUntilReady();
		$this->query('link:Host trigger update')->one()->click();
		$this->triggerCreateUpdate($data, 'Trigger updated', null, 'Cannot update trigger', 'Host trigger update');
	}

	/**
	 * Update linked trigger on host with dependencies.
	 *
	 * @dataProvider getTriggerCreateData
	 */
	public function testHostTriggerDependencies_LinkedTriggerUpdate($data) {
		$this->page->login()->open('zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host with everything'].'&context=host'
		)->waitUntilReady();
		$this->query('link:Trigger that linked')->one()->click();
		$this->page->waitUntilReady();
		$this->triggerCreateUpdate($data, 'Trigger updated', null, 'Cannot update trigger', 'Trigger that linked');
	}

	public static function getTriggerPrototypeCreateData() {
		return [
			// #0 dependencies on one trigger prototype.
			[
				[
					'name' => 'Depends on one trigger_prot',
					'prototype_dependencies' => [
						'Host trigger prot simple{#KEY}'
					],
					'result' => [
						'Host with everything: Host trigger prot simple{#KEY}'
					]
				]
			],
			// #1 dependencies on two trigger prototype.
			[
				[
					'name' => 'Depends on two trigger_prot',
					'prototype_dependencies' => [
						'Host trigger prot simple{#KEY}',
						'Host trigger prot simple_2{#KEY}'
					],
					'result' => [
						'Host with everything: Host trigger prot simple_2{#KEY}',
						'Host with everything: Host trigger prot simple{#KEY}'
					]
				]
			],
			// #2 dependencies on trigger and trigger prototype.
			[
				[
					'name' => 'Depends on trigger and trigger_prot',
					'dependencies' => [
						'Host with everything' => ['Host trigger everything']
					],
					'prototype_dependencies' => [
						'Host trigger prot simple{#KEY}'
					],
					'result' => [
						'Host with everything: Host trigger everything',
						'Host with everything: Host trigger prot simple{#KEY}'
					]
				]
			]
		];
	}

	/**
	 * Create trigger prototype on host with dependencies.
	 *
	 * @dataProvider getTriggerCreateData
	 * @dataProvider getTriggerPrototypeCreateData
	 */
	public function testHostTriggerDependencies_TriggerPrototypeCreate($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&parent_discoveryid='.
				self::$host_druleids['Host with everything:host_everything_drule'].'&context=host'
		)->waitUntilReady();
		$this->query('button:Create trigger prototype')->one()->click();
		$this->page->waitUntilReady();

		// Creating new trigger prototype - expression is mandatory.
		$this->triggerCreateUpdate($data, 'Trigger prototype added',
				'last(/Host with everything/host_everything_prot_[{#KEY}])=0'
		);
	}

	/**
	 * Update trigger prototype on host with dependencies.
	 *
	 * @dataProvider getTriggerCreateData
	 * @dataProvider getTriggerPrototypeCreateData
	 */
	public function testHostTriggerDependencies_TriggerPrototypeUpdate($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&parent_discoveryid='.
				self::$host_druleids['Host with everything:host_everything_drule'].'&context=host'
		)->waitUntilReady();
		$this->query('link:Host trigger prototype update{#KEY}')->one()->click();
		$this->page->waitUntilReady();
		$this->triggerCreateUpdate($data, 'Trigger prototype updated', null, 'Cannot update trigger prototype',
				'Host trigger prototype update{#KEY}'
		);
	}

	public static function getLinkedTriggerPrototypeUpdateData() {
		return [
			// Dependencies on one correct trigger prototype.
			[
				[
					'prototype_dependencies' => [
						'trigger prototype linked{#KEY}'
					],
					'result' => [
						'Host with everything: trigger prototype linked{#KEY}'
					]
				]
			],
			// Dependencies on trigger prototype and trigger.
			[
				[
					'prototype_dependencies' => [
						'trigger prototype linked{#KEY}'
					],
					'dependencies' => [
						'Host with everything' => ['Host trigger everything']
					],
					'result' => [
						'Host with everything: Host trigger everything',
						'Host with everything: trigger prototype linked{#KEY}'
					]
				]
			]
		];
	}

	/**
	 * Update linked trigger prototype on host with dependencies.
	 *
	 * @dataProvider getLinkedTriggerPrototypeUpdateData
	 * @dataProvider getTriggerCreateData
	 */
	public function testHostTriggerDependencies_LinkedTriggerPrototypeUpdate($data) {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.
				self::$hostids['Host with everything'].'&context=host'
		)->waitUntilReady();
		$this->query('link:Drule for linking')->one()->click();
		$this->page->waitUntilReady();
		$this->query('link:Trigger prototypes')->one()->click();
		$this->page->waitUntilReady();
		$this->query('link:trigger prototype linked update{#KEY}')->one()->click();
		$this->triggerCreateUpdate($data, 'Trigger prototype updated', null, 'Cannot update trigger prototype',
				'trigger prototype linked update{#KEY}'
		);
	}

	/**
	 * Check that discovered trigger has only links (no buttons) in Dependencies tab.
	 */
	public function testHostTriggerDependencies_DiscoveredTrigger() {
		$this->page->login()->open('zabbix.php?action=trigger.list&filter_set=1&'.
				'filter_hostids%5B0%5D=99062&context=host'
		)->waitUntilReady();
		$this->query('link:Discovered trigger one')->one()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$form->selectTab('Dependencies');

		// Dependencies table.
		$table = $form->query('id:dependency-table')->one();

		// Check that discovered trigger doesn't have any Add buttons and can't add new dependencies.
		$this->assertFalse($table->query('xpath:.//button[contains(text(), "Add")]')->exists());

		// Check that link with discovered trigger exists in table.
		$this->assertTrue($table->query('link:Host for triggers filtering: Trigger disabled with tags')->one()->isClickable());

		COverlayDialogElement::find()->one()->close();
	}

	public static function getDependencyListData() {
		return [
			[
				[
					'dependant_trigger' => 'Trigger for dependency list test 1',
					'master_triggers' => [
						'Trigger for dependency list test 2',
						'Trigger for dependency list test 3'
					]
				]
			],
			[
				[
					'dependant_trigger' => '1 Host trigger prototype for dependency list{#KEY}',
					'master_triggers' => [
						'2 Host trigger prototype for dependency list{#KEY}',
						'3 Host trigger prototype for dependency list{#KEY}'
					]
				]
			]
		];
	}

	/**
	 * Check that trigger doesn't present in its own dependency list and
	 * that checked master triggers are disabled in list.
	 *
	 * @dataProvider getDependencyListData
	 */
	public function testHostTriggerDependencies_DependencyList($data) {
		$host_name = 'Host for trigger dependency list';
		$hostid = self::$hostids[$host_name];
		$lldid = self::$host_druleids[$host_name.':drule_triggers_dependency'];

		$this->checkDependencyList($data, $host_name, $hostid, $lldid, 'host');
	}
}
