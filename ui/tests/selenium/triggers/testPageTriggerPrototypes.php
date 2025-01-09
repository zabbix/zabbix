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


require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareTriggerPrototypeData
 */
class testPageTriggerPrototypes extends testPagePrototypes {

	public $source = 'trigger';
	public $tag = 'a3 Trigger prototype monitored not discovered_{#KEY}';

	protected $link = 'zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&';
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
		$hostids = $host_result['hostids']['Host for prototype check'];
		self::$host_druleids = $host_result['discoveryruleids']['Host for prototype check:drule'];

		$item_prototype = CDataHelper::call('itemprototype.create', [
			[
				'name' => '1 Item prototype for trigger',
				'key_' => '1_key[{#KEY}]',
				'hostid' => $hostids,
				'ruleid' => self::$host_druleids,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype );

		CDataHelper::call('triggerprototype.create', [
			[
				'description' => '3a Trigger prototype monitored discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'opdata' => '12345',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => '15 Trigger prototype not monitored discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'status' => TRIGGER_STATUS_DISABLED,
				'opdata' => '{#PROT_MAC}',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => '33b4 Trigger prototype not monitored not discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'status' => TRIGGER_STATUS_DISABLED,
				'discover' => TRIGGER_NO_DISCOVER,
				'opdata' => 'test',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'a3 Trigger prototype monitored not discovered_{#KEY}',
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
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'oO75 Trigger prototype for high severity_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'opdata' => '{$TEST}',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Yw Trigger prototype for disaster severity_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'opdata' => '🙂🙃',
				'priority' => TRIGGER_SEVERITY_DISASTER
			]
		]);
		self::$prototype_triggerids = CDataHelper::getIds('description');
		self::$entity_count = count(self::$prototype_triggerids);
	}

	public function testPageTriggerPrototypes_Layout() {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleids)->waitUntilReady();
		$this->checkLayout();
	}

	/**
	 * Sort trigger prototypes by Severity, Name, Create enabled and Discover columns.
	 *
	 * @dataProvider getTriggerPrototypesSortingData
	 */
	public function testPageTriggerPrototypes_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort='.$data['sort'].'&sortorder=ASC&'.
				'parent_discoveryid='.self::$host_druleids)->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getTriggerPrototypesButtonLinkData
	 */
	public function testPageTriggerPrototypes_ButtonLink($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleids)->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getTriggerPrototypesDeleteData
	 */
	public function testPageTriggerPrototypes_Delete($data) {
		$this->page->login()->open($this->link.'parent_discoveryid='.self::$host_druleids)->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$prototype_triggerids[$name];
		}

		$this->checkDelete($data, $ids);
	}
}
