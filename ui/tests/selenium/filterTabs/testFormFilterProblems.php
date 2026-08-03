<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../common/testFormFilter.php';

/**
 * @backup profiles, hosts
 *
 * @dataSource UserPermissions, WidgetCommunication, MonitoringOverview
 *
 * @onBefore prepareProblemsData
 */
class testFormFilterProblems extends testFormFilter {

	/**
	 * Id of the host with problems.
	 *
	 * @var integer
	 */
	protected static $hostid;

	/**
	 * Ids of the triggers for problems.
	 *
	 * @var array
	 */
	protected static $triggerids;

	/**
	 * Current time.
	 *
	 * @var int
	 */
	protected static $time;

	public $url = 'zabbix.php?action=problem.view&show_timeline=0';
	public $table_selector = 'id:datatable-problems';

	public function prepareProblemsData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Problems Filter']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for Problems Filter',
			'groups' => [['groupid' => $groupid]]
		]);

		$this->assertArrayHasKey('hostids', $hosts);
		self::$hostid = $hosts['hostids'][0];

		// Create items on previously created host.
		$item_names = ['float', 'char'];

		$items_data = [];
		foreach ($item_names as $i => $item) {
			$items_data[] = [
				'hostid' => self::$hostid,
				'name' => $item,
				'key_' => $item,
				'type' => 2,
				'value_type' => $i
			];
		}

		$items = CDataHelper::call('item.create', $items_data);
		$this->assertArrayHasKey('itemids', $items);

		// Create triggers based on items.
		$triggers_data = [];
		foreach ($item_names as $i => $item) {
			$triggers_data[] = [
				'description' => 'Filter problems trigger '.$i,
				'expression' => 'last(/Host for Problems Filter/'.$item.')=0',
				'priority' => $i
			];
		}

		$triggers = CDataHelper::call('trigger.create', $triggers_data);
		$this->assertArrayHasKey('triggerids', $triggers);
		self::$triggerids = CDataHelper::getIds('description');

		// Create events and problems.
		self::$time = time();
		$trigger_data = [
			[
				'name' => 'Filter problems trigger 0',
				'time' => self::$time - 62985600 // now - 729 days.
			],
			[
				'name' => 'Filter problems trigger 1',
				'time' => self::$time - 62983800 // now - 728 days 23 hours and 30 minutes.
			]
		];

		foreach ($trigger_data as $params) {
			CDBHelper::setTriggerProblem($params['name'], TRIGGER_VALUE_TRUE, ['clock' => $params['time']]);
		}

		// Change width of Info column so that it would be possible to filter by Tags header without horizontal scrolling.
		$layout = '{"columns":[{"id":"time","resized":true,"width":"9.6%"},{"id":"severity","resized":true,"width":"8.38%"},'.
				'{"id":"recovery","resized":true,"width":"10.2%"},{"id":"status","resized":true,"width":"7.9%"},'.
				'{"id":"info","resized":true,"width":"7.9%"},{"id":"host","resized":true,"width":"7.9%"},'.
				'{"id":"problem","resized":true,"width":"10.2%"},{"id":"duration","resized":true,"width":"8.3%"},'.
				'{"id":"update","resized":true,"width":"4.8%"},{"id":"actions","resized":true,"width":"8%"},'.
				'{"id":"opdata"},{"id":"tags","resized":true,"width":"9%"},{"id":"tagvalue"}],'.
				'"options":{"compact_view":"0","highlight_row":"0"}}';

		$this->updateDatatableLayout($layout, 'web.monitoring.problem.datatable');
	}

	public static function getCheckCreatedFilterData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'filter' => [
						'Name' => '',
						'Show number of records' => true
					],
					'error_message' => 'Incorrect value for field "filter_name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'filter' => [
						'Name' => ''
					],
					'error_message' => 'Incorrect value for field "filter_name": cannot be empty.'
				]
			],
			// Dataprovider with 1 space instead of name.
			[
				[
					'expected' => TEST_BAD,
					'filter' => [
						'Name' => ' '
					],
					'error_message' => 'Incorrect value for field "filter_name": cannot be empty.'
				]
			],
			// Dataprovider with default name
			[
				[
					'expected' => TEST_GOOD,
					'filter_form' => [
						'Hosts' => ['Host for tag permissions']

					],
					'header_settings' => [
						'Time' => [
							'Show timeline' => true
						]
					],
					'filter' => [
						'Show number of records' => true
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'filter_form' => [
						'Problem' => 'non_exist'
					],
					'filter' => [
						'Name' => 'simple_name'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'filter_form' => [
						'Problem' => 'non_exist'
					],
					'filter' => [
						'Name' => 'simple_name and 0 records',
						'Show number of records' => true
					]
				]
			],
			// Dataprovider with symbols instead of name.
			[
				[
					'expected' => TEST_GOOD,
					'filter_form' => [
						'Severity' => 'High'
					],
					'header_settings' => [
						'Time' => [
							'Show timeline' => false
						]
					],
					'filter' => [
						'Name' => '*;%№:?(',
						'Show number of records' => true
					]
				]
			],
			// Dataprovider with name as cyrillic.
			[
				[
					'expected' => TEST_GOOD,
					'filter_form' => [
						'Host groups' => ['Group to check Overview']
					],
					'filter' => [
						'Name' => 'кириллица'
					]
				]
			],
			// Two dataproviders with same name and options.
			[
				[
					'expected' => TEST_GOOD,
					'filter' => [
						'Name' => 'duplicated_name'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'filter' => [
						'Name' => 'duplicated_name'
					]
				],
				// Should be added previous 5 filter tabs from data provider.
				'tab' => '6'
			]
		];
	}

	/**
	 * Create and check new filters.
	 *
	 * @dataProvider getCheckCreatedFilterData
	 */
	public function testFormFilterProblems_CheckCreatedFilter($data) {
		$this->createFilter($data, 'filter-create', 'zabbix', $this->table_selector);

		if (array_key_exists('header_settings', $data)) {
			$this->changeLayoutFromHeader($data['header_settings']);
		}
		$this->checkFilters($data, $this->table_selector);
	}

	public static function getCheckRememberedFilterData() {
		return [
			[
				[
					'filter' => [
						'Hosts' => ['Host for triggers filtering'],
						'Average' => true
					],
					'header_settings' => [
						'Tags' => [
							'Number of tags' => '1'
						]
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => ['Zabbix servers'],
						'Hosts' => ['ЗАББИКС Сервер'],
						'Not classified' => true,
						'Warning' => true,
						'Average' => true
					],
					'header_settings' => [
						'Tags' => [
							'Number of tags' => '3'
						]
					]
				]
			]
		];
	}

	/**
	 * Create and remember new filters.
	 *
	 * @dataProvider getCheckRememberedFilterData
	 */
	public function testFormFilterProblems_CheckRememberedFilter($data) {
		$this->checkRememberedFilters($data, $this->table_selector);
	}

	/**
	 * Delete filters.
	 */
	public function testFormFilterProblems_Delete() {
		$this->deleteFilter('filter-delete', 'zabbix');
	}

	/**
	 * Updating filter form.
	 */
	public function testFormFilterProblems_UpdateForm() {
		$this->updateFilterForm('filter-update', 'zabbix', $this->table_selector);
	}

	/**
	 * Updating saved filter properties.
	 */
	public function testFormFilterProblems_UpdateProperties() {
		$this->updateFilterProperties('filter-update', 'zabbix');
	}


	public static function getCustomTimePeriodData() {
		return [
			[
				[
					'filter_form' => [
						'Hosts' => ['Host for Problems Filter']
					],
					'filter' => [
						'Name' => 'Timeselect_1'
					]
				]
			],
			[
				[
					'filter_form' => [
						'Hosts' => ['Host for Problems Filter']
					],
					'filter' => [
						'Name' => 'Timeselect_2'
					]
				]
			]
		];
	}

	/**
	 * Time period check from saved filter properties and timeselector.
	 *
	 * @dataProvider getCustomTimePeriodData
	 */
	public function testFormFilterProblems_TimePeriod($data) {
		$this->createFilter($data, 'Admin', 'zabbix', $this->table_selector);
		$filter = CFilterElement::find()->one()->setContext(CFilterElement::CONTEXT_LEFT);
		$form = $filter->getForm();
		$table = $this->query($this->table_selector)->asDatatable()->one()->waitUntilReady();

		// Checking result amount before changing time period.
		$this->assertEquals($table->getRows()->count(), 2);

		if ($data['filter']['Name'] === 'Timeselect_1') {
			// Enable Set custom time period option.
			$filter->editProperties();
			$dialog = COverlayDialogElement::get('Filter properties')->asForm();
			$dialog->fill(['Override time period selector' => true, 'From' => 'now-2y']);
			$dialog->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->waitUntilReady();
			$table->waitUntilReady();
		}
		else {
			// Changing time period from timeselector tab.
			$form->fill(['Show' => 'History']);
			$filter->setContext(CFilterElement::CONTEXT_RIGHT);
			$filter->selectTab('Last 1 hour');
			$this->query('xpath://input[@id="from"]')->one()->fill('now-2y');
			$filter->query('id:apply')->one()->click();
			$filter->setContext(CFilterElement::CONTEXT_LEFT)->selectTab($data['filter']['Name']);
			$this->query('button:Update')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
			$table->waitUntilReady();
		}

		// Checking that Show field tabs are disabled or enabled.
		$value = ($data['filter']['Name'] === 'Timeselect_1') ? false : true;
		foreach (['Recent problems', 'Problems'] as $label) {
			$this->assertTrue($form->query('xpath://label[text()="'.$label.'"]/../input')->one()->isEnabled($value));
		}

		$this->assertTrue($this->query('xpath://li[@data-target="tabfilter_timeselector"]')->one()->isEnabled($value));

		// Checking that table result changed.
		$this->assertEquals(2, $table->getRows()->count());
	}
}
