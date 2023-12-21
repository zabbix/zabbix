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

	public function testPageHostPrototypes_SimpleDelete() {
		$this->page->login()->open('host_prototypes.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for host prototype check:drule'])->waitUntilReady();
		$sql = 'SELECT null FROM hosts WHERE hostid='.self::$prototype_hostids['1 Host prototype monitored discovered {#H}'];

		// Check that host prototype exists in DB and displayed in Host prototype table.
		$this->assertEquals(1, CDBHelper::getCount($sql));
		$this->assertTrue(in_array('1 Host prototype monitored discovered {#H}', $this->getTableColumnData('Name')));

		// Select host prototype and delete it.
		$this->selectTableRows('1 Host prototype monitored discovered {#H}');
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that host prototype doesn't exist in DB and not displayed in Host prototype table.
		$this->assertFalse(in_array('1 Host prototype monitored discovered {#H}', $this->getTableColumnData('Name')));
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

//	const DICROVERY_RULE_ID = 90001;
//	const HOST_PROTOTYPES_COUNT = 8;
//
//	public function testPageHostPrototypes_CheckLayout() {
//		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DICROVERY_RULE_ID.'&context=host');
//		$this->zbxTestCheckTitle('Configuration of host prototypes');
//		$this->zbxTestCheckHeader('Host prototypes');
//
//		$table = $this->query('xpath://form[@name="hosts"]/table[@class="list-table"]')->asTable()->one();
//		$headers = ['', 'Name', 'Templates', 'Create enabled', 'Discover', 'Tags'];
//		$this->assertSame($headers, $table->getHeadersText());
//
//		foreach (['Create enabled', 'Create disabled', 'Delete'] as $button) {
//			$element = $this->query('button', $button)->one();
//			$this->assertTrue($element->isPresent());
//			$this->assertFalse($element->isEnabled());
//		}
//
//		$this->assertTableStats(self::HOST_PROTOTYPES_COUNT);
//
//		// Check tags on the specific host prototype.
//		$tags = $table->findRow('Name', 'Host prototype {#1}')->getColumn('Tags')->query('class:tag')->all();
//		$this->assertEquals(['host_proto_tag_1: value1', 'host_proto_tag_2: value2'], $tags->asText());
//
//		foreach ($tags as $tag) {
//			$tag->click();
//			$hint = $this->query('xpath://div[@data-hintboxid]')
//					->asOverlayDialog()->waitUntilPresent()->all()->last();
//			$this->assertEquals($tag->getText(), $hint->getText());
//			$hint->close();
//		}
//	}
//
//	public static function getSelectedData() {
//		return [
//			[
//				[
//					'item' => 'Discovery rule 1',
//					'hosts' => [
//						'Host prototype {#1}'
//					]
//				]
//			],
//			[
//				[
//					'item' => 'Discovery rule 2',
//					'hosts' => 'all'
//				]
//			],
//			[
//				[
//					'item' => 'Discovery rule 3',
//					'hosts' => [
//						'Host prototype {#7}',
//						'Host prototype {#9}',
//						'Host prototype {#10}'
//					]
//				]
//			]
//		];
//	}
//
//	/**
//	 * Select specified hosts from host prototype page.
//	 *
//	 * @param array $data	test case data from data provider
//	 */
//	private function selectHostPrototype($data) {
//		$discoveryid = DBfetch(DBselect("SELECT itemid FROM items WHERE name=".zbx_dbstr($data['item'])));
//		$this->zbxTestLogin("host_prototypes.php?parent_discoveryid=".$discoveryid['itemid'].'&context=host');
//
//		if ($data['hosts'] === 'all') {
//			$this->zbxTestCheckboxSelect('all_hosts');
//			return;
//		}
//
//		foreach ($data['hosts'] as $host) {
//			$result = DBselect('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));
//			while ($row = DBfetch($result)) {
//				$this->zbxTestCheckboxSelect('group_hostid_'.$row['hostid']);
//			}
//		}
//	}
//
//	/**
//	 * Check specific page action.
//	 * Actions are defined by buttons pressed on page.
//	 *
//	 * @param array  $data		test case data from data provider
//	 * @param string $action	button text (action to be executed)
//	 * @param int    $status	host status to be checked in DB
//	 */
//	protected function checkPageAction($data, $action, $status = null) {
//		// Click on button with required action.
//		if ($action === 'Click on state') {
//			foreach ($data['hosts'] as $host) {
//				$id = DBfetch(DBselect('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($host)));
//				$this->zbxTestClickXpathWait("//a[contains(@onclick,'group_hostid%5B%5D=".$id['hostid']."')]");
//			}
//		}
//		else {
//			$this->selectHostPrototype($data);
//			$this->zbxTestClickButtonText($action);
//			$this->zbxTestAcceptAlert();
//		}
//
//		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
//		$this->zbxTestCheckTitle('Configuration of host prototypes');
//		$this->zbxTestCheckHeader('Host prototypes');
//
//		// Create query part for status (if any).
//		$status_criteria = ($status !== null) ? (' AND status='.$status) : '';
//
//		// Check the results in DB.
//		if ($data['hosts'] === 'all') {
//			$sql = 'SELECT NULL'.
//						' FROM hosts'.
//						' WHERE hostid IN ('.
//							'SELECT hostid'.
//							' FROM host_discovery'.
//							' WHERE parent_itemid IN ('.
//								'SELECT itemid'.
//								' FROM items'.
//								' WHERE name='.zbx_dbstr($data['item']).
//							')'.
//						')';
//		}
//		else {
//			$names = [];
//			foreach ($data['hosts'] as $host) {
//				$names[] = zbx_dbstr($host);
//			}
//
//			$sql = 'SELECT NULL'.
//					' FROM hosts'.
//					' WHERE host IN ('.implode(',', $names).')';
//		}
//
//		$this->assertEquals(0, CDBHelper::getCount($sql.$status_criteria));
//	}
//
//	/**
//	 * @dataProvider getSelectedData
//	 */
//	public function testPageHostPrototypes_DisableSelected($data) {
//		$this->checkPageAction($data, 'Create disabled', HOST_STATUS_MONITORED);
//	}
//
//	/**
//	 * @dataProvider getSelectedData
//	 */
//	public function testPageHostPrototypes_EnableSelected($data) {
//		$this->checkPageAction($data, 'Create enabled', HOST_STATUS_NOT_MONITORED);
//	}
//
//	/**
//	 * @dataProvider getSelectedData
//	 */
//	public function testPageHostPrototypes_DeleteSelected($data) {
//		$this->checkPageAction($data, 'Delete');
//	}
//
//	public static function getHostPrototypeData() {
//		return [
//			[
//				[
//					'item' => 'Discovery rule 1',
//					'hosts' => [
//						'Host prototype {#2}'
//					],
//					'status' => HOST_STATUS_NOT_MONITORED
//				]
//			],
//			[
//				[
//					'item' => 'Discovery rule 1',
//					'hosts' => [
//						'Host prototype {#3}'
//					],
//					'status' => HOST_STATUS_MONITORED
//				]
//			]
//		];
//	}
//
//	/**
//	 * @dataProvider getHostPrototypeData
//	 */
//	public function testPageHostPrototypes_SingleEnableDisable($data) {
//		$discoveryid = DBfetch(DBselect("SELECT itemid FROM items WHERE name=".zbx_dbstr($data['item'])));
//		$this->zbxTestLogin("host_prototypes.php?parent_discoveryid=".$discoveryid['itemid'].'&context=host');
//
//		$this->checkPageAction($data, 'Click on state', $data['status']);
//	}
}
