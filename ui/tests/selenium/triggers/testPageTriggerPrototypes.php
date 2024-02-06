<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareTriggerPrototypeData
 */
class testPageTriggerPrototypes extends testPagePrototypes {

	public $page_name = 'trigger';
	public $entity_count = 6;
	public $tag = '4 Trigger prototype monitored not discovered_{#KEY}';

	protected static $prototype_triggerids;
	protected static $host_druleids;

	public function prepareTriggerPrototypeData() {
		$host_result = CDataHelper::createHosts([
			[
				'host' => 'Host for prototype check',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '161',
						'details' => [
							'version' => 1,
							'community' => 'test'
						]
					]
				],
				'groups' => [['groupid' => 4]], // Zabbix server
				'discoveryrules' => [
					[
						'name' => 'Drule for prototype check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		$hostids = $host_result['hostids'];
		self::$host_druleids = $host_result['discoveryruleids'];

		$item_prototype  = CDataHelper::call('itemprototype.create', [
			[
				'name' => '1 Item prototype for trigger',
				'key_' => '1_key[{#KEY}]',
				'hostid' => $hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype );

		CDataHelper::call('triggerprototype.create', [
			[
				'description' => '1 Trigger prototype monitored discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'opdata' => '12345',
				'priority' => 0
			],
			[
				'description' => '2 Trigger prototype not monitored discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'status' => TRIGGER_STATUS_DISABLED,
				'opdata' => '{#PROT_MAC}',
				'priority' => 1
			],
			[
				'description' => '3 Trigger prototype not monitored not discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'status' => TRIGGER_STATUS_DISABLED,
				'discover' => TRIGGER_NO_DISCOVER,
				'opdata' => 'test',
				'priority' => 2
			],
			[
				'description' => '4 Trigger prototype monitored not discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'discover' => TRIGGER_NO_DISCOVER,
				'tags' => [
					[
						'tag' => 'name_1',
						'value' => 'value_1'
					],
					[
						'tag' => 'name_2',
						'value' => 'value_2'
					]
				],
				'opdata' => '!@#$%^&*',
				'priority' => 3
			],
			[
				'description' => '5 Trigger prototype for high severity_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'opdata' => '{$TEST}',
				'priority' => 4
			],
			[
				'description' => '6 Trigger prototype for disaster severity_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'opdata' => '🙂🙃',
				'priority' => 5
			]
		]);
		self::$prototype_triggerids = CDataHelper::getIds('description');
	}

	public function testPageTriggerPrototypes_Layout() {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&'.
				'parent_discoveryid='.self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->checkLayout();
	}

	/**
	 * Sort trigger prototypes by Severity, Name, Create enabled and Discover columns.
	 *
	 * @dataProvider getTriggersSortingData
	 */
	public function testPageTriggerPrototypes_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort='.$data['sort'].'&sortorder=ASC&'.
				'parent_discoveryid='.self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getTriggersButtonLinkData
	 */
	public function testPageTriggerPrototypes_ButtonLink($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&'.
				'parent_discoveryid='.self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getTriggersDeleteData
	 */
	public function testPageTriggerPrototypes_Delete($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&'.
				'parent_discoveryid='.self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$prototype_triggerids[$name];
		}

		$this->checkDelete($data, $ids);
	}
}
