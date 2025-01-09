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

require_once dirname(__FILE__).'/../common/testPageApiTokens.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup token
 * @backup profiles
 *
 * @onBefore prepareTokenData
 */
class testPageApiTokensAdministrationGeneral extends testPageApiTokens {

	public static $timestamp;

	const STATUS_CHANGE_TOKEN = 'Admin: expired token for admin';
	const DELETE_TOKEN = 'filter-create: future token for filter-create';

	public static function prepareTokenData() {
		self::$timestamp = time() + 172800;

		$response = CDataHelper::call('token.create', [
			[
				'name' => 'Admin: future token for admin',
				'userid' => 1,
				'description' => 'admin token to be used in update scenarios',
				'status' => '0',
				'expires_at' => '1798754399'
			],
			[
				'name' => 'Admin: expired token for admin',
				'userid' => 1,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1609452360'
			],
			[
				'name' => 'Admin: aktīvs токен - 頑張って',
				'userid' => 1,
				'description' => 'Token that is generated for Admin',
				'status' => '1'
			],
			[
				'name' => 'Admin: token for filter-create',
				'userid' => 92,
				'description' => 'admin token created for filter-create user',
				'status' => '1',
				'expires_at' => self::$timestamp
			],
			[
				'name' => 'filter-create: future token for filter-create',
				'userid' => 92,
				'description' => 'filter-create token created for filter-create user',
				'status' => '0',
				'expires_at' => '1798754399'
			],
			[
				'name' => 'filter-create: expired token for filter-create',
				'userid' => 92,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1609452360'
			],
			[
				'name' => 'filter-create: aktīvs токен - 頑張って',
				'userid' => 92,
				'description' => 'Token that is generated for filter-create',
				'status' => '1'
			],
			[
				'name' => 'filter-create: token for Admin',
				'userid' => 1,
				'description' => 'filter-create token created for Admin user',
				'status' => '1',
				'expires_at' => self::$timestamp
			]
		]);

		DBexecute('UPDATE token SET creator_userid=92 WHERE name LIKE \'filter-create: %\'');
		DBexecute('UPDATE token SET created_at=1609452001');

		// Update token "Last accessed" timestamp to be different for each token.
		$i = 1;
		foreach ($response['tokenids'] as $tokenid) {
			DBexecute('UPDATE token SET lastaccess='.(1609452001+$i).' WHERE tokenid='.$tokenid);
			$i++;
		}
	}

	public function testPageApiTokensAdministrationGeneral_Layout() {
		$token_data = [
			[
				'Name' => 'Admin: aktīvs токен - 頑張って',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => 'Never',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:04',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'Admin: expired token for admin',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => '2021-01-01 00:06:00',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:03',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Admin: future token for admin',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => '2026-12-31 23:59:59',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:02',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Admin: token for filter-create',
				'User' => 'filter-create',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp),
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'Admin (Zabbix Administrator)',
				'Last accessed at' => '2021-01-01 00:00:05',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'filter-create: aktīvs токен - 頑張って',
				'User' => 'filter-create',
				'Expires at' => 'Never',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:08',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'filter-create: expired token for filter-create',
				'User' => 'filter-create',
				'Expires at' => '2021-01-01 00:06:00',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:07',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'filter-create: future token for filter-create',
				'User' => 'filter-create',
				'Expires at' => '2026-12-31 23:59:59',
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:06',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'filter-create: token for Admin',
				'User' => 'Admin (Zabbix Administrator)',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp),
				'Created at' => '2021-01-01 00:00:01',
				'Created by user' => 'filter-create',
				'Last accessed at' => '2021-01-01 00:00:09',
				'Status' => 'Disabled'

			]
		];

		$this->checkLayout($token_data, 'administration');
	}

	public function testPageApiTokensAdministrationGeneral_ChangeStatus() {
		$this->checkStatusChange('zabbix.php?action=token.list', self::STATUS_CHANGE_TOKEN);
	}

	public function getFilterData() {
		return [
			// Exact name match with special symbols.
			[
				[
					'filter' => [
						'Name' => 'Admin: aktīvs токен - 頑張って'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って'
					]
				]
			],
			// Partial name match.
			[
				[
					'filter' => [
						'Name' => 'Admin:'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create'
					]
				]
			],
			// Partial name match with space in between.
			[
				[
					'filter' => [
						'Name' => 'ken fo'
					],
					'expected' => [
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Filter by name with trailing and leading spaces.
			// TODO Uncomment the below data provider once ZBX-18995 is fixed.
//			[
//				[
//					'filter' => [
//						'Name' => '   future token   '
//					],
//					'expected' => [
//						'Admin: future token for admin',
//						'filter-create: future token for filter-create'
//					]
//				]
//			],
			// Wrong name in filter field "Name".
			[
				[
					'filter' => [
						'Name' => 'No data should be returned'
					],
					'no_data' => true
				]
			],
			// Retrieve only Enabled tokens.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'expected' => [
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create'
					]
				]
			],
			// Retrieve only Disabled tokens.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created by "filter-create" user.
			[
				[
					'filter' => [
						'Created by users' => 'filter-create'
					],
					'expected' => [
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created by "Admin" user.
			[
				[
					'filter' => [
						'Created by users' => 'Admin'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create'
					]
				]
			],
			// Retrieve tokens that were created by one of the "filter-create" or "Admin" users.
			[
				[
					'filter' => [
						'Created by users' => ['Admin', 'filter-create']
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created by "guest" user.
			[
				[
					'filter' => [
						'Created by users' => 'guest'
					],
					'no_data' => true
				]
			],
			// Retrieve only tokens that were created for "Admin" user.
			[
				[
					'filter' => [
						'Users' => 'Admin'
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created for "filter-create" user.
			[
				[
					'filter' => [
						'Users' => 'filter-create'
					],
					'expected' => [
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create'
					]
				]
			],
			// Retrieve tokens that were created for one of the "filter-create" or "Admin" users.
			[
				[
					'filter' => [
						'Users' => ['Admin', 'filter-create']
					],
					'expected' => [
						'Admin: aktīvs токен - 頑張って',
						'Admin: expired token for admin',
						'Admin: future token for admin',
						'Admin: token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'filter-create: expired token for filter-create',
						'filter-create: future token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve only tokens that were created for "guest" user.
			[
				[
					'filter' => [
						'Users' => 'guest'
					],
					'no_data' => true
				]
			],
			// Retrieve tokens that will expire in less that 2 days.
			[
				[
					'filter' => [],
					'Expires in less than' => 2,
					'expected' => [
						'Admin: token for filter-create',
						'filter-create: token for Admin'
					]
				]
			],
			// Retrieve tokens that will expire in less that 1 day.
			[
				[
					'filter' => [],
					'Expires in less than' => 1,
					'no_data' => true
				]
			],
			// Retrieve tokens created for "filter-create" user that will expire in less that 2 days.
			[
				[
					'filter' => [
						'Users' => 'filter-create'
					],
					'Expires in less than' => 2,
					'expected' => [
						'Admin: token for filter-create'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageApiTokensAdministrationGeneral_Filter($data) {
		$this->checkFilter($data, 'administration');
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'filter-create: token for Admin',
						'filter-create: future token for filter-create',
						'filter-create: expired token for filter-create',
						'filter-create: aktīvs токен - 頑張って',
						'Admin: token for filter-create',
						'Admin: future token for admin',
						'Admin: expired token for admin',
						'Admin: aktīvs токен - 頑張って'
					]
				]
			],
			[
				[
					'sort_field' => 'User',
					'expected' => [
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'filter-create',
						'filter-create',
						'filter-create',
						'filter-create'
					]
				]
			],
			[
				[
					'sort_field' => 'Expires at',
					'expected' => [
						'Never',
						'Never',
						'2021-01-01 00:06:00',
						'2021-01-01 00:06:00',
						'change_to_timestamp',
						'change_to_timestamp',
						'2026-12-31 23:59:59',
						'2026-12-31 23:59:59'
					]
				]
			],
			[
				[
					'sort_field' => 'Created by user',
					'expected' => [
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'Admin (Zabbix Administrator)',
						'filter-create',
						'filter-create',
						'filter-create',
						'filter-create'
					]
				]
			],
			[
				[
					'sort_field' => 'Last accessed at',
					'expected' => [
						'2021-01-01 00:00:02',
						'2021-01-01 00:00:03',
						'2021-01-01 00:00:04',
						'2021-01-01 00:00:05',
						'2021-01-01 00:00:06',
						'2021-01-01 00:00:07',
						'2021-01-01 00:00:08',
						'2021-01-01 00:00:09'
					]
				]
			],
			[
				[
					'sort_field' => 'Status',
					'expected' => [
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Disabled',
						'Disabled',
						'Disabled',
						'Disabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSortData
	 */
	public function testPageApiTokensAdministrationGeneral_Sort($data) {
		// Place $timestamp variable value in data provider as the data providers are formed before execution of onBefore.
		if ($data['sort_field'] === 'Expires at') {
			foreach ($data['expected'] as $i => $value) {
				if ($value === 'change_to_timestamp') {
					$data['expected'][$i] = date('Y-m-d H:i:s', self::$timestamp);
				}
			}
		}

		$this->checkSorting($data, 'zabbix.php?action=token.list');
	}

	public function testPageApiTokensAdministrationGeneral_Delete() {
		$this->checkDelete('zabbix.php?action=token.list', self::DELETE_TOKEN);
	}
}
