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
 * @backup profiles
 */
class testFormFilterProblems extends testFormFilter {

	public $url = 'zabbix.php?action=problem.view';
	public $table_selector = 'class:list-table';

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
						'Hosts' => ['Host for tag permissions']
					],
					'filter' => [
						'Name' => 'Timeselect_1'
					]
				]
			],
			[
				[
					'filter_form' => [
						'Hosts' => ['Host for tag permissions']
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
		$table = $this->query('class:list-table')->asTable()->waitUntilReady()->one();

		// Checking result amount before changing time period.
		$this->assertEquals($table->getRows()->count(), 2);

		if ($data['filter']['Name'] === 'Timeselect_1') {
			// Enable Set custom time period option.
			$filter_container->editProperties();
			$dialog = COverlayDialogElement::find()->asForm()->all()->last()->waitUntilReady();
			$dialog->fill(['Set custom time period' => true, 'From' => '2020-10-23 18:00']);
			$dialog->submit();
			$this->page->waitUntilReady();
		}
		else {
			// Changing time period from timeselector tab.
			$form->fill(['Show' => 'History']);
			$this->query('xpath://a[@class="tabfilter-item-link btn-time"]')->one()->click();
			$this->query('xpath://input[@id="from"]')->one()->fill('2020-10-23 18:00');
			$this->query('id:apply')->one()->click();
			$filter_container->selectTab($data['filter']['Name']);
			$this->query('button:Update')->one()->click();
			$this->page->waitUntilReady();
		}

		// Checking that Show field tabs are disabled or enabled.
		$value = ($data['filter']['Name'] === 'Timeselect_1') ? false : true;
		foreach (['Recent problems', 'Problems'] as $label) {
			$this->assertTrue($form->query('xpath://label[text()="'.$label.'"]/../input')->one()->isEnabled($value));
		}

		$this->assertTrue($this->query('xpath://li[@data-target="tabfilter_timeselector"]')->one()->isEnabled($value));

		// Checking that table result changed.
		$this->assertEquals(1, $table->getRows()->count());
	}
}
