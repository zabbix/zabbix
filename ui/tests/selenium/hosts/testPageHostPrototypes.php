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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareHostPrototypeData
 */
class testPageHostPrototypes extends testPagePrototypes {

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

	public $single_success = 'Host prototype updated';
	public $several_success = 'Host prototypes updated';
	public $sql = 'SELECT null FROM hosts WHERE hostid=';

	protected static $prototype_hostids;
	protected static $hostids;
	protected static $host_druleids;


	public function prepareHostPrototypeData() {
		$host_result = CDataHelper::createHosts([
			[
				'host' => 'Host for host prototype check',
				'groups' => [['groupid' => 4]], // Zabbix server
				'discoveryrules' => [
					[
						'name' => 'Drule for host prototype check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		self::$hostids = $host_result['hostids'];
		self::$host_druleids = $host_result['discoveryruleids'];

		CDataHelper::call('hostprototype.create', [
			[
				'host' => '1 Host prototype monitored discovered {#H}',
				'ruleid' => self::$host_druleids['Host for host prototype check:drule'],
				'groupLinks' =>  [
					[
						'groupid'=> 4 // Zabbix server
					]
				],
				'tags' => [
					[
						'tag' => 'name_1',
						'value' => 'value_1'
					],
					[
						'tag' => 'name_2',
						'value' => 'value_2'
					]
				]
			],
			[
				'host' => '2 Host prototype not monitored discovered {#H}',
				'ruleid' => self::$host_druleids['Host for host prototype check:drule'],
				'groupLinks' =>  [
					[
						'groupid'=> 4 // Zabbix server
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED
			],
			[
				'host' => '3 Host prototype not monitored not discovered {#H}',
				'ruleid' => self::$host_druleids['Host for host prototype check:drule'],
				'groupLinks' =>  [
					[
						'groupid'=> 4 // Zabbix server
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED,
				'discover' => HOST_NO_DISCOVER
			],
			[
				'host' => '4 Host prototype monitored not discovered {#H}',
				'ruleid' => self::$host_druleids['Host for host prototype check:drule'],
				'groupLinks' =>  [
					[
						'groupid'=> 4 // Zabbix server
					]
				],
				'discover' => HOST_NO_DISCOVER
			]
		]);
		self::$prototype_hostids = CDataHelper::getIds('host');
	}

	public function testPageHostPrototypes_Layout() {
		$this->page->login()->open('host_prototypes.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for host prototype check:drule'])->waitUntilReady();

		// Checking Title, Header and Column names.
		$this->page->assertTitle('Configuration of host prototypes');
		$this->page->assertHeader('Host prototypes');
		$this->assertSame(['', 'Name', 'Templates', 'Create enabled', 'Discover', 'Tags'],
				($this->query('class:list-table')->asTable()->one())->getHeadersText()
		);

		$this->assertTableStats(4);

		// Check displayed buttons and their default status after opening host prototype page.
		$buttons = [
			'Create host prototype' => true,
			'Create enabled' => false,
			'Create disabled' => false,
			'Delete' => false
		];

		foreach ($buttons as $button => $status) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($status));
		}

		// Check tags on the specific host prototype.
		$table = $this->query('class:list-table')->asTable()->one();
		$tags = $table->findRow('Name', '1 Host prototype monitored discovered {#H}')
				->getColumn('Tags')->query('class:tag')->all();
		$this->assertEquals(['name_1: value_1', 'name_2: value_2'], $tags->asText());

		// Check hints for tags that appears after clicking on them.
		foreach ($tags as $tag) {
			$tag->click();
			$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last();
			$this->assertEquals($tag->getText(), $hint->getText());
			$hint->close();
		}

		// Check clickable headers.
		foreach (['Name', 'Create enabled', 'Discover'] as $header) {
			$this->assertTrue($table->query('link', $header)->one()->isClickable());
		}
	}

	public static function getSortingData() {
		return [
			// #0 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'name',
					'result' => [
						'1 Host prototype monitored discovered {#H}',
						'2 Host prototype not monitored discovered {#H}',
						'3 Host prototype not monitored not discovered {#H}',
						'4 Host prototype monitored not discovered {#H}'
					]
				]
			],
			// #1 Sort by Create enabled.
			[
				[
					'sort_by' => 'Create enabled',
					'sort' => 'status',
					'result' => [
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			],
			// #2 Sort by Discover.
			[
				[
					'sort_by' => 'Discover',
					'sort' => 'discover',
					'result' => [
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
	 * Sort host prototypes by Name, Create enabled and Discover column.
	 *
	 * @dataProvider getSortingData
	 */
	public function testPageHostPrototypes_Sorting($data) {
		$this->page->login()->open('host_prototypes.php?context=host&sort='.$data['sort'].'&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for host prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	public static function getButtonLinkData() {
		return [
			// #0 Click on Create disabled button.
			[
				[
					'name' => '1 Host prototype monitored discovered {#H}',
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #1 Click on Create enabled button.
			[
				[
					'name' => '2 Host prototype not monitored discovered {#H}',
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #2 Enabled clicking on link in Create enabled column.
			[
				[
					'name' => '3 Host prototype not monitored not discovered {#H}',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #3 Disabled clicking on link in Create enabled column.
			[
				[
					'name' => '4 Host prototype monitored not discovered {#H}',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #4 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '3 Host prototype not monitored not discovered {#H}',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #5 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Host prototype not monitored discovered {#H}',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #6 Enable all host prototypes clicking on Create enabled button.
			[
				[
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'after' => ['Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all host prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No']
				]
			]
		];
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getButtonLinkData
	 */
	public function testPageHostPrototypes_ButtonLink($data) {
		$this->page->login()->open('host_prototypes.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for host prototype check:drule'])->waitUntilReady();
		$this->executeDiscoverEnable($data);
	}

	public static function getDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Host prototype monitored discovered {#H}'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Host prototype not monitored discovered {#H}'],
					'message' => 'Host prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Host prototype not monitored not discovered {#H}',
						'4 Host prototype monitored not discovered {#H}'
					],
					'message' => 'Host prototypes deleted'
				]
			]
		];
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageHostPrototypes_Delete($data) {
		$this->page->login()->open('host_prototypes.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for host prototype check:drule'])->waitUntilReady();

		foreach ($data['name'] as $name) {
			$this->assertEquals(1, CDBHelper::getCount($this->sql.self::$prototype_hostids[$name]));
		}

		$this->executeDelete($data);

		$count = (array_key_exists('cancel', $data)) ? 1 : 0;

		foreach ($data['name'] as $name) {
			$this->assertEquals($count, CDBHelper::getCount($this->sql.self::$prototype_hostids[$name]));
		}
	}
}
