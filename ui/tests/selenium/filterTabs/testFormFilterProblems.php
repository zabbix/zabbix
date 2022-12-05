<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testFormFilter.php';

/**
 * @backup profiles, hosts
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
	 * Time when events were created.
	 *
	 * @var int
	 */
	protected static $two_yeasr_ago_1;
	protected static $two_years_ago_2;

	public $url = 'zabbix.php?action=problem.view&show_timeline=0';
	public $table_selector = 'class:list-table';

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

		// Make timestamp a little less 1 year ago.
		self::$two_yeasr_ago_1 = time()-62985600;

		// Make timestamp a little less than 2 years ago.
		self::$two_years_ago_2 = time()-62983800;

		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (1005500, 0, 0, '.
				zbx_dbstr(self::$triggerids['Filter problems trigger 0']).', '.self::$two_yeasr_ago_1.', 0, 1, '.
				zbx_dbstr('Filter problems trigger 0').', 0)'
		);

		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (1005501, 0, 0, '.
				zbx_dbstr(self::$triggerids['Filter problems trigger 1']).', '.self::$two_years_ago_2.', 0, 1, '.
		zbx_dbstr('Filter problems trigger 1').', 1)'
		);

		// Create problems.
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (1005500, 0, 0, '.
				zbx_dbstr(self::$triggerids['Filter problems trigger 0']).', '.self::$two_yeasr_ago_1.', 0, '.
				zbx_dbstr('Filter problems trigger 0').', 0)'
		);

		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (1005501, 0, 0, '.
				zbx_dbstr(self::$triggerids['Filter problems trigger 1']).', '.self::$two_years_ago_2.', 0, '.
				zbx_dbstr('Filter problems trigger 1').', 1)'
		);

		// Change triggers' state to Problem.
		DBexecute('UPDATE triggers SET value = 1 WHERE description IN ('.zbx_dbstr('Filter problems trigger 0').', '.
				zbx_dbstr('Filter problems trigger 1').')'
		);
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
					'filter' => [
						'Show number of records' => true
					],
					'tab_id' => '1'
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
					],
					'tab_id' => '2'
				]
			],
			// Dataprovider with symbols instead of name.
			[
				[
					'expected' => TEST_GOOD,
					'filter_form' => [
						'Severity' => 'High'
					],
					'filter' => [
						'Name' => '*;%№:?(',
						'Show number of records' => true
					],
					'tab_id' => '3'
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
					],
					'tab_id' => '4'
				]
			],
			// Two dataproviders with same name and options.
			[
				[
					'expected' => TEST_GOOD,
					'filter' => [
						'Name' => 'duplicated_name'
					],
					'tab_id' => '5'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'filter' => [
						'Name' => 'duplicated_name'
					],
					'tab_id' => '6'
				]
			]
		];
	}

	/**
	 * Create and check new filters.
	 *
	 * @dataProvider getCheckCreatedFilterData
	 */
	public function testFormFilterProblems_CheckCreatedFilter($data) {
		$this->createFilter($data, 'filter-create', 'zabbix');
		$this->checkFilters($data, $this->table_selector);
	}

	public static function getCheckRememberedFilterData() {
		return [
			[
				[
					'Hosts' => ['Host for tag permissions'],
					'Not classified' => true,
					'Show tags' => '2'
				]
			],
			[
				[
					'Host groups' => ['Zabbix servers'],
					'Hosts' => ['ЗАББИКС Сервер'],
					'Not classified' => true,
					'Warning' => true,
					'Average' => true,
					'Show tags' => '3',
					'Compact view' => true
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
		$this->checkRememberedFilters($data);
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
		$this->createFilter($data, 'Admin', 'zabbix');
		$filter_container = $this->query('xpath://ul[@class="ui-sortable-container ui-sortable"]')->asFilterTab()->one();
		$formid = $this->query('xpath://a[text()="'.$data['filter']['Name'].'"]/parent::li')->waitUntilVisible()->one()->getAttribute('data-target');
		$form = $this->query('id:'.$formid)->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Checking result amount before changing time period.
		$this->assertEquals($table->getRows()->count(), 2);

		if ($data['filter']['Name'] === 'Timeselect_1') {
			// Enable Set custom time period option.
			$filter_container->editProperties();
			$dialog = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
			$dialog->fill(['Set custom time period' => true, 'From' => 'now-2y']);
			$dialog->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->waitUntilReady();
			$table->waitUntilReloaded();
		}
		else {
			// Changing time period from timeselector tab.
			$form->fill(['Show' => 'History']);
			$this->query('xpath://a[@class="tabfilter-item-link btn-time"]')->one()->click();
			$this->query('xpath://input[@id="from"]')->one()->fill('now-2y');
			$this->query('id:apply')->one()->click();
			$filter_container->selectTab($data['filter']['Name']);
			$this->query('button:Update')->one()->click();
			$this->page->waitUntilReady();
			$table->waitUntilReloaded();
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
