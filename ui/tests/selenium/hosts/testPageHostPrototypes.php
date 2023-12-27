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

/**
 * @backup hosts
 *
 * @onBefore prepareHostPrototypeData
 */
class testPageHostPrototypes extends CWebTest {

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

	protected static $hostids;
	protected static $host_druleids;
	protected static $prototype_hostids;

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

		$table = $this->query('class:list-table')->asTable()->one();
		foreach (['desc', 'asc'] as $sorting) {
			$table->query('link', $data['sort_by'])->one()->click();
			$expected = ($sorting === 'asc') ? $data['result'] : array_reverse($data['result']);
			$this->assertEquals($expected, $this->getTableColumnData($data['sort_by']));
		}
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
		$table = $this->query('class:list-table')->asTable()->one();

		// Find host prototype in table by name and check column data before update.
		if (array_key_exists('name', $data)) {
			$row = $table->findRow('Name', $data['name']);
			$this->assertEquals($data['before'], $row->getColumn($data['column_check'])->getText());
		}

		// Click on button or on link in column (Create enabled or Discover).
		if (array_key_exists('button', $data)) {
			// If no Host prototype name in data provider, then select all existing in table host prototypes.
			$selected = (array_key_exists('name', $data)) ? $data['name'] : null;
			$this->selectTableRows($selected);
			$this->query('button', $data['button'])->one()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
		}
		else {
			// Click on link in table.
			$row->getColumn($data['column_check'])->query('link', $data['before'])->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		// Check column value for one host prototypes or for them all.
		if (array_key_exists('name', $data)) {
			$this->assertMessage(TEST_GOOD, 'Host prototype updated');
			$this->assertEquals($data['after'], $row->getColumn($data['column_check'])->getText());
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Host prototypes updated');
			$this->assertTableDataColumn($data['after'], $data['column_check']);
		}
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
	 * @dataProvider getDeleteData
	 */
	public function testPageHostPrototypes_Delete($data) {
		$this->page->login()->open('host_prototypes.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for host prototype check:drule'])->waitUntilReady();
		$sql = 'SELECT null FROM hosts WHERE hostid=';

		// Check that host prototype exists in DB and displayed in Host prototype table.
		foreach ($data['name'] as $name) {
			$this->assertTrue(in_array($name, $this->getTableColumnData('Name')));
			$this->assertEquals(1, CDBHelper::getCount($sql.self::$prototype_hostids[$name]));
		}

		// Select host prototype and click on Delete button.
		$this->selectTableRows($data['name']);
		$this->query('button:Delete')->one()->click();

		// Check that after canceling Delete, host prototype still exists in DB nad displayed in table.
		if (array_key_exists('cancel', $data)) {
			$this->page->dismissAlert();
			foreach ($data['name'] as $name) {
				$this->assertTrue(in_array($name, $this->getTableColumnData('Name')));
				$this->assertEquals(1, CDBHelper::getCount($sql.self::$prototype_hostids[$name]));
			}
		}
		else {
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, $data['message']);

			// Check that host prototype doesn't exist in DB and not displayed in Host prototype table.
			foreach ($data['name'] as $name) {
				$this->assertFalse(in_array($name, $this->getTableColumnData('Name')));
				$this->assertEquals(0, CDBHelper::getCount($sql.self::$prototype_hostids[$name]));
			}
		}
	}
}
