<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormFilter.php';

/**
 * @backup profiles
 */
class testFormFilterHosts extends testFormFilter {

	public static function getCheckCreatedFilterData() {
		return [
			[
				[
					'filter' => [
						'Name' => '',
						'Show number of records' => true
					],
					'error_message' => 'Incorrect value for field "filter_name": cannot be empty.'
				]
			],
			[
				[
					'filter' => [
						'Name' => ''
					],
					'error_message' => 'Incorrect value for field "filter_name": cannot be empty.'
				]
			],
			// Dataprovider with 1 space instead of name.
			[
				[
					'filter' => [
						'Name' => ' '
					],
					'error_message' => 'Incorrect value for field "filter_name": cannot be empty.'
				]
			],
			// Dataprovider with default name
			[
				[
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
					'filter_form' => [
						'Host groups' => ['Group to check Overview']
					],
					'filter' => [
						'Name' => 'кирилица'
					],
					'tab_id' => '4'
				]
			],
			// Two dataproviders with same name and options.
			[
				[
					'filter' => [
						'Name' => 'duplicated_name'
					],
					'tab_id' => '5'
				]
			],
			[
				[
					'filter' => [
						'Name' => 'duplicated_name'
					],
					'tab_id' => '6'
				]
			],
		];
	}

	/**
	 * @dataProvider getCheckCreatedFilterData
	 *
	 * Create and check new filters.
	 */
	public function testFormFilterHosts_CheckCreatedFilter($data) {
		$this->checkFilters($data, 'zabbix.php?action=host.view');
	}

	/**
	 * @depends  testFormFilterHosts_CheckCreatedFilter
	 *
	 * Delete created filter.
	 */
	public function testFormFilterHosts_Delete() {
		$this->deleteFilter('zabbix.php?action=host.view&filter_rst=1');
	}

	public static function getUpdateFormData() {
		return [
			[
				[
					'filter_form' => [
						'Host groups' => ['Group to check Overview']
					],
					'filter' => [
						'Name' => 'update_filter_form',
						'Show number of records' => true
					],
					'tab_id' => '1'
				]
			]
		];
	}

	/**
	 * @backup-once profiles
	 * @dataProvider getUpdateFormData
	 *
	 * Updating filter form.
	 */
	public function testFormFilterHosts_UpdateForm($data) {
		$this->updateFilterForm($data, 'zabbix.php?action=host.view&filter_rst=1');
	}

	public static function getUpdatePropertiesData() {
		return [
			[
				[
					'filter' => [
						'Name' => 'update_filter_properties'
					],
					'tab_id' => '1'
				]
			]
		];
	}

	/**
	 * @backup-once profiles
	 * @dataProvider getUpdatePropertiesData
	 *
	 * Updating saved filter properties.
	 */
	public function testFormFilterHosts_UpdateProperties($data) {
		$this->updateFilterProperties($data, 'zabbix.php?action=host.view&filter_rst=1');
	}
}
