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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareTriggerPrototypeData
 */
class testPageTriggerPrototypes extends testPagePrototypes {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class,
			CMessageBehavior::class
		];
	}

	public $single_success = 'Trigger prototype updated';
	public $several_success = 'Trigger prototypes updated';
	public $sql = 'SELECT null FROM triggers WHERE triggerid=';

	public $headers = ['', 'Severity', 'Name', 'Operational data', 'Expression', 'Create enabled', 'Discover', 'Tags'];
	public $page_name = 'trigger';
	public $amount = 6;
	public $buttons = [
		'Create enabled' => false,
		'Create disabled' => false,
		'Mass update' => false,
		'Delete' => false,
		'Create trigger prototype' => true
	];
	public $tag = '4 Trigger prototype monitored not discovered_{#KEY}';
	public $clickable_headers = ['Severity', 'Name', 'Create enabled', 'Discover'];

	protected static $prototype_triggerids;
	protected static $hostids;
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
		self::$hostids = $host_result['hostids'];
		self::$host_druleids = $host_result['discoveryruleids'];

		$item_prototype  = CDataHelper::call('itemprototype.create', [
			[
				'name' => '1 Item prototype for trigger',
				'key_' => '1_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
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
				'status' => ITEM_STATUS_DISABLED,
				'opdata' => '{#PROT_MAC}',
				'priority' => 1
			],
			[
				'description' => '3 Trigger prototype not monitored not discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'status' => ITEM_STATUS_DISABLED,
				'discover' => ITEM_NO_DISCOVER,
				'opdata' => 'test',
				'priority' => 2
			],
			[
				'description' => '4 Trigger prototype monitored not discovered_{#KEY}',
				'expression' => 'last(/Host for prototype check/1_key[{#KEY}])=0',
				'discover' => ITEM_NO_DISCOVER,
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
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->layout();
	}

	public static function getSortingData() {
		return [
			// #0 Sort by Severity.
			[
				[
					'sort_by' => 'Severity',
					'sort' => 'priority',
					'result' => [
						'Not classified',
						'Information',
						'Warning',
						'Average',
						'High',
						'Disaster'
					]
				]
			],
			// #1 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'description',
					'result' => [
						'1 Trigger prototype monitored discovered_{#KEY}',
						'2 Trigger prototype not monitored discovered_{#KEY}',
						'3 Trigger prototype not monitored not discovered_{#KEY}',
						'4 Trigger prototype monitored not discovered_{#KEY}',
						'5 Trigger prototype for high severity_{#KEY}',
						'6 Trigger prototype for disaster severity_{#KEY}'
					]
				]
			],
			// #2 Sort by Create enabled.
			[
				[
					'sort_by' => 'Create enabled',
					'sort' => 'status',
					'result' => [
						'Yes',
						'Yes',
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			],
			// #3 Sort by Discover.
			[
				[
					'sort_by' => 'Discover',
					'sort' => 'discover',
					'result' => [
						'Yes',
						'Yes',
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			]
		];
	}

	/**
	 * Sort trigger prototypes.
	 *
	 * @dataProvider getSortingData
	 */
	public function testPageTriggerPrototypes_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort='.$data['sort'].'&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	public static function getButtonLinkData() {
		return [
			// #0 Click on Create disabled button.
			[
				[
					'name' => '1 Trigger prototype monitored discovered_{#KEY}',
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #1 Click on Create enabled button.
			[
				[
					'name' => '2 Trigger prototype not monitored discovered_{#KEY}',
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #2 Enabled clicking on link in Create enabled column.
			[
				[
					'name' => '3 Trigger prototype not monitored not discovered_{#KEY}',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #3 Disabled clicking on link in Create enabled column.
			[
				[
					'name' => '4 Trigger prototype monitored not discovered_{#KEY}',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #4 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '3 Trigger prototype not monitored not discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #5 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Trigger prototype not monitored discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #6 Enable all trigger prototypes clicking on Create enabled button.
			[
				[
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'after' => ['Yes', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all trigger prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No', 'No', 'No']
				]
			]
		];
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getButtonLinkData
	 */
	public function testPageTriggerPrototypes_ButtonLink($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeDiscoverEnable($data);
	}

	public static function getDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Trigger prototype monitored discovered_{#KEY}'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Trigger prototype not monitored discovered_{#KEY}'],
					'message' => 'Trigger prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Trigger prototype not monitored not discovered_{#KEY}',
						'4 Trigger prototype monitored not discovered_{#KEY}'
					],
					'message' => 'Trigger prototypes deleted'
				]
			]
		];
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageTriggerPrototypes_Delete($data) {
		$this->page->login()->open('zabbix.php?action=trigger.prototype.list&context=host&sort=description&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();

		foreach ($data['name'] as $name) {
			$this->assertEquals(1, CDBHelper::getCount($this->sql.self::$prototype_triggerids[$name]));
		}

		$this->executeDelete($data);

		$count = (array_key_exists('cancel', $data)) ? 1 : 0;

		foreach ($data['name'] as $name) {
			$this->assertEquals($count, CDBHelper::getCount($this->sql.self::$prototype_triggerids[$name]));
		}
	}
}
