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

require_once __DIR__.'/../common/testFormFilter.php';

/**
 * @backup profiles
 */
class testFormFilterHosts extends testFormFilter {

	public $url = 'zabbix.php?action=host.view';
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
						'Host groups' => ['Empty group']
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
						'Name' => 'non_exist'
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
						'Name' => 'non_exist'
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
						'Severity' => 'Not classified'
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
					],
					// Should be added previous 5 filter tabs from data provider.
					'tab' => '6'
				]
			]
		];
	}

	/**
	 * Create and check new filters.
	 *
	 * @dataProvider getCheckCreatedFilterData
	 */
	public function testFormFilterHosts_CheckCreatedFilter($data) {
		$this->createFilter($data, 'filter-create', 'zabbix');
		$this->checkFilters($data, $this->table_selector);
	}

	public static function getCheckRememberedFilterData() {
		return [
			[
				[
					'Name' => 'Test name',
					'Host groups' => ['Zabbix servers'],
					'IP' => '192.168.10.1',
					'DNS' => 'test.name',
					'Port' => '10055',
					'Average' => true,
					'Warning' => true
				]
			],
			[
				[
					'Port' => '10050',
					'Not classified' => true,
					'Information' => true,
					'Status' => 'Enabled',
					'Show suppressed problems' => true
				]
			]
		];
	}

	/**
	 * Create and remember new filters.
	 *
	 * @dataProvider getCheckRememberedFilterData
	 */
	public function testFormFilterHosts_CheckRememberedFilter($data) {
		$this->checkRememberedFilters($data);
	}

	/**
	 * Delete created filter.
	 */
	public function testFormFilterHosts_Delete() {
		$this->deleteFilter('filter-delete', 'zabbix');
	}

	/**
	 * Updating filter form.
	 */
	public function testFormFilterHosts_UpdateForm() {
		$this->updateFilterForm('filter-update', 'zabbix', $this->table_selector);
	}

	/**
	 * Updating saved filter properties.
	 */
	public function testFormFilterHosts_UpdateProperties() {
		$this->updateFilterProperties('filter-update', 'zabbix');
	}
}
