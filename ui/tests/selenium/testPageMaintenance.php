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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup maintenances
 *
 * @onBefore prepareMaintenanceData
 */
class testPageMaintenance extends CWebTest {

	use TableTrait;

		/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	protected static $maintenance_sql = 'SELECT * FROM maintenances ORDER BY maintenanceid';
	protected static $approaching_maintenance = 'Approaching maintenance';
	protected static $host_maintenance = 'Maintenance with assigned host';
	protected static $multiple_group_maintenance = 'Maintenance with 2 host groups';
	protected static $filter_name_maintenance = 'Maintenance для фильтра - ʍąɨɲţ€ɲąɲȼ€';
	protected static $active_maintenance = 'Active maintenance';

	public function prepareMaintenanceData() {
		CDataHelper::call('maintenance.create', [
			[
				'name' => self::$approaching_maintenance,
				'maintenance_type' => '1',
				'active_since' => '2017008000',
				'active_till' => '2019600000',
				'groups' => [
					[
						'groupid' => '20'
					]
				],
				'timeperiods' => [
					[
						'period' => '3600',
						'timeperiod_type' => '3',
						'start_time' => '0',
						'every' => '1',
						'dayofweek' => '64'
					]
				]
			],
			[
				'name' => self::$multiple_group_maintenance,
				'maintenance_type' => '1',
				'active_since' => '1388534400',
				'active_till' => '1420070400',
				'groups' => [
					[
						'groupid' => '4'
					],
					[
						'groupid' => '5'
					]
				],
				'timeperiods' => [
					[
						'period' => '3600',
						'timeperiod_type' => '3',
						'start_time' => '0',
						'every' => '1',
						'dayofweek' => '64'
					]
				]
			],
			[
				'name' => self::$host_maintenance,
				'maintenance_type' => '0',
				'active_since' => '1577836800',
				'active_till' => '1577923200',
				'hosts' => [
					[
						'hostid' => '10084',
					]
				],
				'timeperiods' => [
					[
						'period' => '3600',
						'timeperiod_type' => '3',
						'start_time' => '0',
						'every' => '1',
						'dayofweek' => '64'
					]
				]
			],
			[
				'name' => self::$filter_name_maintenance,
				'maintenance_type' => '0',
				'active_since' => '1686009600',
				'active_till' => '1688601600',
				'groups' => [
					[
						'groupid' => '4'
					],
				],
				'timeperiods' => [
					[
						'period' => '3600',
						'timeperiod_type' => '3',
						'start_time' => '0',
						'every' => '1',
						'dayofweek' => '64'
					]
				]
			],
			[
				'name' => self::$active_maintenance,
				'maintenance_type' => '0',
				'active_since' => '1688601600',
				'active_till' => '2019600000',
				'groups' => [
					[
						'groupid' => '4'
					],
				],
				'timeperiods' => [
					[
						'period' => '3600',
						'timeperiod_type' => '3',
						'start_time' => '0',
						'every' => '1',
						'dayofweek' => '64'
					]
				]
			]
		]);
		$maintenanceids = CDataHelper::getIds('name');
	}

	public function getMaintenanceData() {
		return [
			[
				[
					[
						'Name' => 'Maintenance period 1 (data collection)',
						'Maintenance type' => 'With data collection',
						'Active since' => '2011-01-11 17:38',
						'Active till' => '2011-01-12 17:38',
						'Host group' => 'Zabbix servers'
					],
					[
						'Name' => 'Maintenance for update (data collection)',
						'Maintenance type' => 'With data collection',
						'Active since' => '2018-08-22 00:00',
						'Active till' => '2018-08-23 00:00',
						'Host group' => 'Zabbix servers'
					],
					[
						'Name' => 'Maintenance for suppression test',
						'Maintenance type' => 'With data collection',
						'Active since' => '2018-08-23 00:00',
						'Active till' => '2038-01-18 00:00',
						'Host group' => 'Host for suppression'
					],
					[
						'Name' => 'Maintenance for Host availability widget',
						'Maintenance type' => 'With data collection',
						'Active since' => '2018-08-23 00:00',
						'Active till' => '2038-01-18 00:00',
						'Host group' => 'Group in maintenance for Host availability widget'
					],
					[
						'Name' => 'Maintenance period 2 (no data collection)',
						'Maintenance type' => 'No data collection',
						'Active since' => '2011-01-11 17:38',
						'Active till' => '2011-01-12 17:38',
						'Host group' => 'Zabbix servers'
					],
					[
						'Name' => 'Approaching maintenance',
						'Maintenance type' => 'No data collection',
						'Active since' => '2033-12-01 00:00',
						'Active till' => '2033-12-31 00:00',
						'Host group' => 'Databases'
					],
					[
						'Name' => 'Maintenance with assigned host',
						'Maintenance type' => 'With data collection',
						'Active since' => '2020-01-01 00:00',
						'Active till' => '2020-01-02 00:00',
						'Hosts' => 'ЗАББИКС Сервер'
					],
					[
						'Name' => 'Maintenance with 2 host groups',
						'Maintenance type' => 'No data collection',
						'Active since' => '2014-01-01 00:00',
						'Active till' => '2015-01-01 00:00',
						'Host group' => ['Zabbix servers', 'Discovered hosts']
					],
					[
						'Name' => 'Maintenance для фильтра - ʍąɨɲţ€ɲąɲȼ€',
						'Maintenance type' => 'With data collection',
						'Active since' => '2023-06-06 00:00',
						'Active till' => '2023-07-06 00:00',
						'Host group' => 'Zabbix servers'
					],
					[
						'Name' => 'Active maintenance',
						'Maintenance type' => 'With data collection',
						'Active since' => '2023-07-06 00:00',
						'Active till' => '2033-12-31 00:00',
						'Host group' => 'Zabbix servers'
					],
				]
			]
		];
	}


	/**
	* @dataProvider getMaintenanceData()
	*/
	public function testPageMaintenance_CheckLayout($data) {
		$maintenances = count($data);
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

	// Check page's title and header.
		$this->page->assertTitle('Configuration of maintenance periods');
		$this->page->assertHeader('Maintenance periods');

	// Check buttons
		$buttons = [
			'Create maintenance period' => true,
			'Apply' => true,
			'Reset' => true,
			'Select' => true,
			'Delete' => false
		];
		foreach ($buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

	// Check filter expanding/collapsing.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('link:Filter')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

	// Check filter fields.
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$this->assertEquals(['Host groups', 'Name', 'State'],
		$form->getLabels()->asText()
		);

	// Name validation - TODO: DEV-2568
		$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));

	// State check
		$this->assertEquals(['Any', 'Active', 'Approaching', 'Expired'], $form->getField('State')->asSegmentedRadio()
				->getLabels()->asText()
		);

	// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name', 'Type', 'Active since', 'Active till', 'State', 'Description'], $table->getHeadersText());

	// Check the selected amount.
		$this->assertTableStats($maintenances);
		$this->assertSelectedCount(0);
		$all_maintenances = $this->query('id:all_maintenances')->asCheckbox()->one();
		$all_maintenances->check();
		$this->assertSelectedCount($maintenances);

	// Check that delete button became clickable.
		$this->assertTrue($this->query('button:Delete')->one()->isClickable());

	// Reset filter and check that maintenances are unselected.
		$filter_form->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());
	}

	public function getFilterData() {
		return [
			// #1 View results for one host group.
			[
				[
					'filter' => [
						'Host groups' => 'Discovered hosts'
					],
					'expected' => [
						self::$multiple_group_maintenance
					]
				]
			],
			// #2 View results for two host groups.
			[
				[
					'filter' => [
						'Host groups' => [
							'Discovered hosts',
							'Zabbix servers'
							]
					],
					'expected' => [
						self::$active_maintenance,
						'Maintenance for update (data collection)',
						'Maintenance period 1 (data collection)',
						'Maintenance period 2 (no data collection)',
						self::$multiple_group_maintenance,
						self::$host_maintenance,
						self::$filter_name_maintenance
					]
				]
			],
			// #3 Name with 2 empty spaces.
			[
				[
					'filter' => [
						'Name' => '  '
					]
				]
			],
			// #4 Name with special symbols.
			[
				[
					'filter' => [
						'Name' => 'ʍąɨɲţ€ɲąɲȼ€'
					],
					'expected' => [
						self::$filter_name_maintenance
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
						self::$active_maintenance,
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
						self::$approaching_maintenance
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
						'Maintenance for update (data collection)',
						'Maintenance period 1 (data collection)',
						'Maintenance period 2 (no data collection)',
						self::$multiple_group_maintenance,
						self::$host_maintenance,
						self::$filter_name_maintenance
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
						self::$active_maintenance,
						self::$approaching_maintenance,
						'Maintenance for Host availability widget',
						'Maintenance for suppression test',
						'Maintenance for update (data collection)',
						'Maintenance period 1 (data collection)',
						'Maintenance period 2 (no data collection)',
						self::$multiple_group_maintenance,
						self::$host_maintenance,
						self::$filter_name_maintenance
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
						self::$multiple_group_maintenance,
						self::$host_maintenance
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
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		// Check that expected maintenances are returned in the list.
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

		// Check the displaying amount
		$maintenance_count = count((CTestArrayHelper::get($data, 'expected', [])));
		$displayed_amount_text = $this-> query('class:table-stats')->one()->getText();
		$this->assertEquals('Displaying '.$maintenance_count.' of '.$maintenance_count.' found', $displayed_amount_text);

		// Reset filter due to not influence further tests.
		$this->query('button:Reset')->one()->click();
	}

	public function testPageMaintenance_Sort() {
		$this->page->login()->open('zabbix.php?action=maintenance.list&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['Name', 'Active since', 'Active till'] as $column) {
			$values = $this->getTableColumnData($column);

			$values_asc = $values;
			$values_desc = $values;

			// Sort column contents ascending.
			usort($values_asc, function($a, $b) {
				return strcasecmp($a, $b);
			});

			// Sort column contents descending.
			usort($values_desc, function($a, $b) {
				return strcasecmp($b, $a);
			});

			// Check ascending and descending sorting in column.
			foreach ([$values_asc, $values_desc] as $reference_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($reference_values, $column);
			}
		}

	// Type has reverse sorting
		foreach(['Type'] as $column){
			$values = $this->getTableColumnData($column);

			$values_asc = $values;
			$values_desc = $values;

			// Sort column contents ascending.
			usort($values_asc, function($a, $b) {
				return strcasecmp($b, $a);
			});

			// Sort column contents descending.
			usort($values_desc, function($a, $b) {
				return strcasecmp($a, $b);
			});

			// Check ascending and descending sorting in column.
			foreach ([$values_asc, $values_desc] as $reference_values) {
				$table->query('link', $column)->waitUntilClickable()->one()->click();
				$table->waitUntilReloaded();
				$this->assertTableDataColumn($reference_values, $column);

			}
		}
	}

	public function getDeleteData() {
		return [
			// Delete 1 maintenance
			[
				[
					'expected' => TEST_GOOD,
					'name' => [
						self::$approaching_maintenance
					]
				]
			],
			// Delete 2 maintenances
			[
				[
					'expected' => TEST_GOOD,
					'name' => [
						self::$multiple_group_maintenance,
						self::$host_maintenance
					]
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
		$maintenances = (array_key_exists('name', $data))
				? count($data['name'])
				: CDBHelper::getCount(self::$maintenance_sql);
		$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, ($maintenances > 1) ? 'Maintenance periods deleted' : 'Maintenance period deleted');
		$this->assertSelectedCount(0);
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name IN ('.
					CDBHelper::escape($data['name']).')')
			);
	}
		public function testPageintenance_CancelDelete() {
		$this->cancelDelete([self::$active_maintenance]);
	}
		public function testPageMaintenance_CancelMassDelete() {
		$this->cancelDelete();
	}

	private function cancelDelete($maintenances = []) {
		$old_hash = CDBHelper::getHash(self::$maintenance_sql);

		$this->page->login()->open('zabbix.php?action=maintenance.list');
		$this->selectTableRows($maintenances);

		// Maintenance count that will be selected before delete action.
		$maintenance_count = ($maintenances === []) ? CDBHelper::getCount(self::$maintenance_sql) : count($maintenances);

		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals(($maintenance_count > 1) ? 'Delete selected maintenance periods?' : 'Delete selected maintenance period?', $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->waitUntilReady();

		$this->assertSelectedCount($maintenance_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$maintenance_sql));
	}
}
