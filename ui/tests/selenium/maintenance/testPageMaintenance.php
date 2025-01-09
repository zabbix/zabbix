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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup maintenances
 *
 * @onBefore prepareMaintenanceData
 *
 * @dataSource HostTemplateGroups
 */
class testPageMaintenance extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const MAINTENANCE_SQL = 'SELECT * FROM maintenances ORDER BY maintenanceid';
	const APPROACHING_MAINTENANCE = 'Approaching maintenance';
	const HOST_MAINTENANCE = 'Maintenance with assigned host';
	const MULTIPLE_GROUPS_MAINTENANCE = 'Maintenance with 2 host groups';
	const FILTER_NAME_MAINTENANCE = 'Maintenance для фильтра - ʍąɨɲţ€ɲąɲc€🙂';
	const ACTIVE_MAINTENANCE = 'Active maintenance';
	const DESCRIPTION_MAINTENANCE = 'Description maintenance';
	const MAINTENANCE_WITH_DATA = 'Maintenance period 1 (data collection)';
	const MAINTENANCE_NO_DATA = 'Maintenance period 2 (no data collection)';
	const MAINTENANCE_UPDATE = 'Maintenance for update (data collection)';
	const ZABBIX_SERVERS_GROUPID = 4;

	public function prepareMaintenanceData() {
		CDataHelper::call('maintenance.create', [
			[
				'name' => self::APPROACHING_MAINTENANCE,
				'maintenance_type' => MAINTENANCE_TYPE_NODATA,
				'active_since' => 2017008000,
				'active_till' => 2019600000,
				'groups' => [['groupid' => CDataHelper::get('HostTemplateGroups.hostgroups.Group for Maintenance')]],
				'timeperiods' => [[]]
			],
			[
				'name' => self::MULTIPLE_GROUPS_MAINTENANCE,
				'maintenance_type' => MAINTENANCE_TYPE_NODATA,
				'active_since' => 1388534400,
				'active_till' => 1420070400,
				'groups' => [
					[
						'groupid' => self::ZABBIX_SERVERS_GROUPID
					],
					[
						'groupid' => 5 // "Discovered hosts" group
					]
				],
				'timeperiods' => [[]]
			],
			[
				'name' => self::HOST_MAINTENANCE,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1577836800,
				'active_till' => 1577923200,
				'hosts' => [['hostid' => 10084]],
				'timeperiods' => [[]]
			],
			[
				'name' => self::FILTER_NAME_MAINTENANCE,
				'maintenance_type' =>  MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1686009600,
				'active_till' => 1688601600,
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'timeperiods' => [[]]
			],
			[
				'name' => self::ACTIVE_MAINTENANCE,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1688601600,
				'active_till' => 2019600000,
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'timeperiods' => [[]]
			],
			[
				'name' => self::DESCRIPTION_MAINTENANCE,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1640995200,
				'active_till' => 1640998800,
				'description' => 'Test description of the maintenance',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'timeperiods' => [[]]
			],
			[
				'name' => self::MAINTENANCE_WITH_DATA,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1294760280,
				'active_till' => 1294846680,
				'description' => 'Test description 1',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'timeperiods' => [
					[
						'period' => 184200,
						'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'start_date' => 1294760340
					],
					[
						'period' => 93780,
						'timeperiod_type' => TIMEPERIOD_TYPE_DAILY,
						'start_time' => 43200,
						'every' => 2
					],
					[
						'period' => 300,
						'timeperiod_type' => TIMEPERIOD_TYPE_WEEKLY,
						'start_time' => 85800,
						'every' => 2,
						'dayofweek' => 85
					],
					[
						'period' => 183840,
						'timeperiod_type' => TIMEPERIOD_TYPE_MONTHLY,
						'start_time' => 84600,
						'every' => 1,
						'month' => 1365,
						'day' => 15
					],
					[
						'period' => 1800,
						'timeperiod_type' => TIMEPERIOD_TYPE_MONTHLY,
						'start_time' => 84600,
						'every' => 1,
						'month' => 2730,
						'dayofweek' => 85
					]
				]
			],
			[
				'name' => self::MAINTENANCE_NO_DATA,
				'maintenance_type' => MAINTENANCE_TYPE_NODATA,
				'active_since' => 1294760280,
				'active_till' => 1294846680,
				'description' => 'Test description 2',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'timeperiods' => [
					[
						'period' => 184200,
						'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'start_date' => 1294760340
					],
					[
						'period' => 93780,
						'timeperiod_type' => TIMEPERIOD_TYPE_DAILY,
						'start_time' => 43200,
						'every' => 2
					],
					[
						'period' => 300,
						'timeperiod_type' => TIMEPERIOD_TYPE_WEEKLY,
						'start_time' => 85800,
						'every' => 2,
						'dayofweek' => 85
					],
					[
						'period' => 183840,
						'timeperiod_type' => TIMEPERIOD_TYPE_MONTHLY,
						'start_time' => 37500,
						'every' => 1,
						'month' => 1365,
						'day' => 15
					],
					[
						'period' => 1800,
						'timeperiod_type' => TIMEPERIOD_TYPE_MONTHLY,
						'start_time' => 84600,
						'every' => 1,
						'month' => 2730,
						'dayofweek' => 85
					]
				]
			],
			[
				'name' => self::MAINTENANCE_UPDATE,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1534885200,
				'active_till' => 1534971600,
				'description' => 'Test description update',
				'groups' => [['groupid' => self::ZABBIX_SERVERS_GROUPID]],
				'tags_evaltype' => 2,
				'tags' => [
					['tag' => 'Tag1', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => 'A'],
					['tag' => 'Tag2', 'operator' => MAINTENANCE_TAG_OPERATOR_EQUAL, 'value' => 'B']
				],
				'timeperiods' => [
					[
						'period' => 90000,
						'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'start_date' => 1534950000
					]
				]
			]
		]);
	}

	public function getMaintenanceData() {
		return [
			[
				[
					[
						'Name' => 'Active maintenance',
						'Type' => 'With data collection',
						'Active since' => '2023-07-06 03:00',
						'Active till' => '2033-12-31 02:00',
						'State' => 'Active',
						'Description' => ''
					],
					[
						'Name' => 'Approaching maintenance',
						'Type' => 'No data collection',
						'Active since' => '2033-12-01 02:00',
						'Active till' => '2033-12-31 02:00',
						'State' => 'Approaching',
						'Description' => ''
					],
					[
						'Name' => 'Description maintenance',
						'Type' => 'With data collection',
						'Active since' => '2022-01-01 02:00',
						'Active till' => '2022-01-01 03:00',
						'State' => 'Expired',
						'Description' => 'Test description of the maintenance'
					],
					[
						'Name' => 'Maintenance with 2 host groups',
						'Type' => 'No data collection',
						'Active since' => '2014-01-01 02:00',
						'Active till' => '2015-01-01 02:00',
						'State' => 'Expired',
						'Description' => ''
					],
					[
						'Name' => 'Maintenance with assigned host',
						'Type' => 'With data collection',
						'Active since' => '2020-01-01 02:00',
						'Active till' => '2020-01-02 02:00',
						'State' => 'Expired',
						'Description'=> ''
					],
					[
						'Name' => 'Maintenance для фильтра - ʍąɨɲţ€ɲąɲc€🙂',
						'Type' => 'With data collection',
						'Active since' => '2023-06-06 03:00',
						'Active till' => '2023-07-06 03:00',
						'State' => 'Expired',
						'Description' => ''
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getMaintenanceData
	 */
	public function testPageMaintenance_Layout($data) {
		$maintenances = CDBHelper::getCount(self::MAINTENANCE_SQL);
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of maintenance periods');
		$this->page->assertHeader('Maintenance periods');

		// Check buttons.
		$this->assertEquals(4, $this->query('button', ['Create maintenance period', 'Apply', 'Reset', 'Select'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);
		$this->assertFalse($this->query('button', 'Delete')->one()->isEnabled());

		// Check rows in the table.
		$this->assertTableHasData($data);

		// Get filter element.
		$filter = CFilterElement::find()->one();
		$form = $filter->getForm();

		// Check filter expanding/collapsing.
		$this->assertTrue($filter->isExpanded());
		foreach ([false, true] as $state) {
			$filter->expand($state);
			// Leave the page and reopen the previous page to make sure the filter state is still saved.
			$this->page->open('zabbix.php?action=host.list')->waitUntilReady();
			$this->page->open('zabbix.php?action=maintenance.list')->waitUntilReady();
			$this->assertTrue($filter->isExpanded($state));
		}

		$this->assertEquals(['Host groups', 'Name', 'State'], $form->getLabels()->asText());
		$this->assertEquals('type here to search', $form->getField('id:filter_groups__ms')
				->getAttribute('placeholder')
		);
		$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));
		$this->assertEquals(['Any', 'Active', 'Approaching', 'Expired'], $form->getField('State')->getLabels()
				->asText()
		);
		$form->checkValue(['Host groups' => '', 'Name' => '', 'State' => 'Any']);

		// Check table headers and sortable headers.
		$table = $this->getTable();
		$this->assertEquals(['Name', 'Type', 'Active since', 'Active till'], $table->getSortableHeaders()->asText());
		$this->assertEquals(['', 'Name', 'Type', 'Active since', 'Active till', 'State', 'Description'],
				$table->getHeadersText()
		);

		// Check the selected amount.
		$this->assertTableStats($maintenances);
		$this->assertSelectedCount(0);
		$this->selectTableRows();
		$this->assertSelectedCount($maintenances);

		// Check that delete button became clickable.
		$this->assertTrue($this->query('button:Delete')->one()->isClickable());

		// Reset filter and check that maintenances are unselected.
		$form->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->assertSelectedCount(0);
	}

	public function getFilterData() {
		return [
			// #0 View results for one host group.
			[
				[
					'filter' => [
						'Host groups' => 'Discovered hosts'
					],
					'expected' => [
						self::MULTIPLE_GROUPS_MAINTENANCE
					]
				]
			],
			// #1 View results for two host groups.
			[
				[
					'filter' => [
						'Host groups' => ['Discovered hosts', 'Zabbix servers']
					],
					'expected' => [
						self::ACTIVE_MAINTENANCE,
						self::DESCRIPTION_MAINTENANCE,
						'Maintenance for update (data collection)',
						'Maintenance period 1 (data collection)',
						'Maintenance period 2 (no data collection)',
						self::MULTIPLE_GROUPS_MAINTENANCE,
						self::HOST_MAINTENANCE,
						self::FILTER_NAME_MAINTENANCE
					]
				]
			],
			// #2 Name with 2 empty spaces.
			[
				[
					'filter' => [
						'Name' => '  '
					]
				]
			],
			// #3 Name with special symbols.
			[
				[
					'filter' => [
						'Name' => 'ʍąɨɲţ€ɲąɲc€🙂'
					],
					'expected' => [
						self::FILTER_NAME_MAINTENANCE
					]
				]
			],
			// #4 Search by description.
			[
				[
					'filter' => [
						'Name' => 'Test description of the maintenance'
					]
				]
			],
			// #5 State - Active.
			[
				[
					'filter' => [
						'State' => 'Active'
					],
					'expected' => [
						self::ACTIVE_MAINTENANCE,
						'Maintenance for Host availability widget',
						'Maintenance for suppression test'
					]
				]
			],
			// #6 State - Approaching.
			[
				[
					'filter' => [
						'State' => 'Approaching'
					],
					'expected' => [
						self::APPROACHING_MAINTENANCE
					]
				]
			],
			// #7 State - Expired.
			[
				[
					'filter' => [
						'State' => 'Expired'
					],
					'expected' => [
						self::DESCRIPTION_MAINTENANCE,
						'Maintenance for host group testing',
						'Maintenance for update (data collection)',
						'Maintenance period 1 (data collection)',
						'Maintenance period 2 (no data collection)',
						self::MULTIPLE_GROUPS_MAINTENANCE,
						self::HOST_MAINTENANCE,
						self::FILTER_NAME_MAINTENANCE
					]
				]
			],
			// #8 State - Any.
			[
				[
					'filter' => [
						'State' => 'Any'
					],
					'expected' => [
						self::ACTIVE_MAINTENANCE,
						self::APPROACHING_MAINTENANCE,
						self::DESCRIPTION_MAINTENANCE,
						'Maintenance for Host availability widget',
						'Maintenance for host group testing',
						'Maintenance for suppression test',
						'Maintenance for update (data collection)',
						'Maintenance period 1 (data collection)',
						'Maintenance period 2 (no data collection)',
						self::MULTIPLE_GROUPS_MAINTENANCE,
						self::HOST_MAINTENANCE,
						self::FILTER_NAME_MAINTENANCE
					]
				]
			],
			// #9 Combined filters.
			[
				[
					'filter' => [
						'Name' => 'Host',
						'State' => 'Expired',
						'Host groups' => 'Zabbix servers'
					],
					'expected' => [
						self::MULTIPLE_GROUPS_MAINTENANCE,
						self::HOST_MAINTENANCE
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageMaintenance_Filter($data) {
		$this->page->login()->open('zabbix.php?action=maintenance.list&sort=name&sortorder=ASC');
		$form = CFilterElement::find()->one()->getForm();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		// Check that expected maintenances are returned in the list.
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

		// Check the displaying amount.
		$this-> assertTableStats(count(CTestArrayHelper::get($data, 'expected', [])));

		// Reset filter to not influence further tests.
		$this->query('button:Reset')->one()->click();
	}

	public function testPageMaintenance_Sort() {
		$this->page->login()->open('zabbix.php?action=maintenance.list&sortorder=DESC');
		$table = $this->getTable();

		foreach (['Name', 'Active since', 'Active till'] as $column) {
			$values = $this->getTableColumnData($column);
			natcasesort($values);

			if ($column === 'Type') {
				$values = array_reverse($values);
			}

			foreach ([$values, array_reverse($values)] as $sorted_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($sorted_values, $column);
			}
		}
	}

	public function testPageMaintenance_CancelDelete() {
		$this->cancelDelete([self::ACTIVE_MAINTENANCE]);
	}

	public function testPageMaintenance_CancelMassDelete() {
		$this->cancelDelete();
	}

	public function getDeleteData() {
		return [
			// Delete 1 maintenance.
			[
				[
					'expected' => TEST_GOOD,
					'name' => [self::APPROACHING_MAINTENANCE]
				]
			],
			// Delete 2 maintenances.
			[
				[
					'expected' => TEST_GOOD,
					'name' => [self::MULTIPLE_GROUPS_MAINTENANCE, self::HOST_MAINTENANCE]
				]
			],
			// Delete all maintenances.
			[
				[
					'expected' => TEST_GOOD
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageMaintenance_Delete($data) {
		$this->page->login()->open('zabbix.php?action=maintenance.list');
		// Maintenance count that will be selected before delete action.
		$count_names = count(CTestArrayHelper::get($data, 'name', []));
		$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Maintenance period'.(($count_names === 1) ? '' : 's').' deleted');
		$this->assertSelectedCount(0);

		$all = CDBHelper::getCount(self::MAINTENANCE_SQL);
		$db_check = $count_names > 0
				? CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name IN ('.CDBHelper::escape($data['name']).')')
				: $all;
		$this->assertEquals(0, $db_check);

		$this->assertTableStats($all);
	}

	protected function cancelDelete($maintenances = []) {
		$old_hash = CDBHelper::getHash(self::MAINTENANCE_SQL);
		// Maintenance count that will be selected before delete action.
		$maintenance_count = ($maintenances === []) ? CDBHelper::getCount(self::MAINTENANCE_SQL) : count($maintenances);

		$this->page->login()->open('zabbix.php?action=maintenance.list');
		$this->selectTableRows($maintenances);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected maintenance period'.(($maintenance_count > 1) ? 's?' : '?'),
				$this->page->getAlertText()
		);
		$this->page->dismissAlert();
		$this->page->waitUntilReady();
		$this->assertSelectedCount($maintenance_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::MAINTENANCE_SQL));
	}
}
