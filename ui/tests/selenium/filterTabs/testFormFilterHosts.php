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
					],
					'tab_id' => '1'
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
					],
					'tab_id' => '2'
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
	public function testFormFilterHosts_CheckCreatedFilter($data) {
		$this->createFilter($data, 'filter-create', 'zabbix');
		$this->checkFilters($data, $this->table_selector);
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
