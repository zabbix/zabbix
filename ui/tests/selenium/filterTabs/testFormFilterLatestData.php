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
 *
 * @onBefore prepareUserData, pepareFilterTabsData
 *
 * @onAfter clearData
 */
class testFormFilterLatestData extends testFormFilter {

	public $url = 'zabbix.php?action=latest.view';

	protected static $users = [
		'user-delete',
		'user-update'
	];

	private function getTableSelector() {
		return 'xpath://table['.CXPathHelper::fromClass('overflow-ellipsis').']';
	}

	/**
	 * Add user for updating.
	 */
	public function prepareUserData() {
		$response = CDataHelper::call('user.create', [
			[
				'username' => 'latest-filter-delete',
				'passwd' => 'Delete_filter_passw0rd',
				'autologin' => 1,
				'autologout' => 0,
				'roleid' => 3,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
			],
			[
				'username' => 'latest-filter-update',
				'passwd' => 'Update_filter_passw0rd',
				'autologin' => 1,
				'autologout' => 0,
				'roleid' => 3,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
			]
		]);

		$this->assertArrayHasKey('userids', $response);
		self::$users['user-delete'] = $response['userids'][0];
		self::$users['user-update'] = $response['userids'][1];
	}

	/**
	 * Function for creating filter tabs in DB.
	 */
	public static function pepareFilterTabsData() {
		// Filters for delete.
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2001, '.
				zbx_dbstr(self::$users['user-delete']).','.zbx_dbstr('web.monitoring.latest.properties').', 0, 0, 0, '.
				zbx_dbstr('{"filter_name":""}').', 3)');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2002, '.
				zbx_dbstr(self::$users['user-delete']).','.zbx_dbstr('web.monitoring.latest.properties').', 2, 0, 0, '.
				zbx_dbstr('{"hostids":["99012"],"filter_name":"Filter_for_delete_2"}').', 3)');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2003, '.
				zbx_dbstr(self::$users['user-delete']).','.zbx_dbstr('web.monitoring.latest.properties').', 1, 0, 0, '.
				zbx_dbstr('{"name":"_item","filter_name":"Filter_for_delete_1","filter_show_counter":1}').', 3)');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2004, '.
				zbx_dbstr(self::$users['user-delete']).','.zbx_dbstr('web.monitoring.latest.properties').', 2, 0, 0, '.
				zbx_dbstr('{"hostids":["99012"],"filter_name":"Filter_for_delete_2"}').', 3)');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2005, '.
				zbx_dbstr(self::$users['user-delete']).','.zbx_dbstr('web.monitoring.latest.properties').', 1, 0, 0, '.
				zbx_dbstr('{"name":"_item","filter_name":"Filter_for_delete_1","filter_show_counter":1}').', 3)');

		// Filter for update.
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2006, '.
				zbx_dbstr(self::$users['user-update']).','.zbx_dbstr('web.monitoring.latest.properties').', 0, 0, 0, '.
				zbx_dbstr('{"filter_name":""}').', 3)');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (2007, '.
				zbx_dbstr(self::$users['user-update']).','.zbx_dbstr('web.monitoring.latest.properties').', 1, 0, 0, '.
				zbx_dbstr('{"groupids":["50011"],"filter_name":"update_tab","filter_show_counter":1}').', 3)');
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
						'Host groups' => ['ZBX6648 All Triggers']
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
						'Show details' => true,
						'Name' => '_item'
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
	public function testFormFilterLatestData_CheckCreatedFilter($data) {
		$this->createFilter($data, 'filter-create', 'zabbix');
		$this->checkFilters($data, $this->getTableSelector());
	}

	/**
	 * Delete created filter.
	 */
	public function testFormFilterLatestData_Delete() {
		$this->deleteFilter('latest-filter-delete', 'Delete_filter_passw0rd');
	}

	/**
	 * Updating filter form.
	 */
	public function testFormFilterLatestData_UpdateForm() {
		$this->updateFilterForm('latest-filter-update', 'Update_filter_passw0rd', $this->getTableSelector());
	}

	/**
	 * Updating saved filter properties.
	 */
	public function testFormFilterLatestData_UpdateProperties() {
		$this->updateFilterProperties('latest-filter-update', 'Update_filter_passw0rd');
	}

	/**
	 * Delete created user data after test.
	 */
	public static function clearUsersData() {
		// Delete Hosts.
		CDataHelper::call('user.delete', [
				self::$data['user-delete'],
				self::$data['user-update']
		]);
	}
}
