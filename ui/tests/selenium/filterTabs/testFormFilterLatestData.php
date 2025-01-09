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


require_once dirname(__FILE__).'/../common/testFormFilter.php';

/**
 * @backup profiles
 *
 * @onBefore prepareUserData, pepareFilterTabsData
 */
class testFormFilterLatestData extends testFormFilter {

	public $url = 'zabbix.php?action=latest.view';

	protected static $users = [
		'user-delete',
		'user-update'
	];

	private function getTableSelector() {
		return 'xpath://table['.CXPathHelper::fromClass('list-table fixed').']';
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
						'Host groups' => ['Group to check triggers filtering']
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
						'Show details' => true,
						'Name' => '_item'
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
	public function testFormFilterLatestData_CheckCreatedFilter($data) {
		$this->createFilter($data, 'filter-create', 'zabbix', $this->getTableSelector());
		$this->checkFilters($data, $this->getTableSelector());
	}

	public static function getCheckRememberedFilterData() {
		return [
			[
				[
					'Host groups' => ['Zabbix servers'],
					'Hosts' => ['ЗАББИКС Сервер'],
					'Name' => 'Free',
					'Show tags' => '1'
				]
			],
			[
				[
					'Name' => 'Total',
					'Tag display priority' => 'Alfa, Beta',
					'id:tag_name_format_0' => 'Shortened',
					'Show details' => true
				]
			]
		];
	}

	/**
	 * Create and remember new filters.
	 *
	 * @dataProvider getCheckRememberedFilterData
	 */
	public function testFormFilterLatestData_CheckRememberedFilter($data) {
		$this->checkRememberedFilters($data, $this->getTableSelector());
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
}
