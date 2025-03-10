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

require_once __DIR__.'/../common/testPageApiTokens.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @backup token
 * @backup profiles
 *
 * @onBefore prepareTokenData
 */
class testPageApiTokensUserSettings extends testPageApiTokens {

	public static $timestamp;

	const STATUS_CHANGE_TOKEN = 'Expired token for admin';
	const DELETE_TOKEN = 'Future token for admin';

	public static function prepareTokenData() {
		self::$timestamp = time() + 172800;

		$response = CDataHelper::call('token.create', [
			[
				'name' => 'Future token for admin',
				'userid' => 1,
				'description' => 'admin token to be used in update scenarios',
				'status' => '0',
				'expires_at' => self::$timestamp + 864000
			],
			[
				'name' => 'Expired token for admin',
				'userid' => 1,
				'description' => 'Token to be deleted in the delete scenario',
				'status' => '0',
				'expires_at' => '1609452360'
			],
			[
				'name' => 'Aktīvs токен - 頑張って',
				'userid' => 1,
				'description' => 'Token that is generated for Admin',
				'status' => '1'
			],
			[
				'name' => 'Token that will expire in 2 days',
				'userid' => 1,
				'description' => 'admin token created for filter-create user',
				'status' => '1',
				'expires_at' => self::$timestamp
			]
		]);

		DBexecute('UPDATE token SET created_at=1609452001');

		// Update token "Last accessed" timestamp to be different for each token.
		$i = 1;
		foreach ($response['tokenids'] as $tokenid) {
			DBexecute('UPDATE token SET lastaccess='.(1609452001+$i).' WHERE tokenid='.$tokenid);
			$i++;
		}
	}

	public function testPageApiTokensUserSettings_Layout() {
		$token_data = [
			[
				'Name' => 'Aktīvs токен - 頑張って',
				'Expires at' => 'Never',
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:04',
				'Status' => 'Disabled'

			],
			[
				'Name' => 'Expired token for admin',
				'Expires at' => '2021-01-01 00:06:00',
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:03',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Future token for admin',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp + 864000),
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:02',
				'Status' => 'Enabled'

			],
			[
				'Name' => 'Token that will expire in 2 days',
				'Expires at' => date('Y-m-d H:i:s', self::$timestamp),
				'Created at' => '2021-01-01 00:00:01',
				'Last accessed at' => '2021-01-01 00:00:05',
				'Status' => 'Disabled'

			]
		];

		$this->checkLayout($token_data, 'user settings');
	}

	public function testPageApiTokensUserSettings_ChangeStatus() {
		$this->checkStatusChange('zabbix.php?action=user.token.list', self::STATUS_CHANGE_TOKEN);
	}

	public function getFilterData() {
		return [
			// Exact name match with special symbols.
			[
				[
					'filter' => [
						'Name' => 'Aktīvs токен - 頑張って'
					],
					'expected' => [
						'Aktīvs токен - 頑張って'
					]
				]
			],
			// Partial name match.
			[
				[
					'filter' => [
						'Name' => 'admin'
					],
					'expected' => [
						'Expired token for admin',
						'Future token for admin'
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
						'Expired token for admin',
						'Future token for admin'
					]
				]
			],
			// Filter by name with trailing and leading spaces.
			// TODO Uncomment the below data provider once ZBX-18995 is fixed.
//			[
//				[
//					'filter' => [
//						'Name' => '   oken   '
//					],
//					'expected' => [
//						'Expired token for admin',
//						'Future token for admin',
//						'Token that will expire in 2 days'
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
						'Expired token for admin',
						'Future token for admin'
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
						'Aktīvs токен - 頑張って',
						'Token that will expire in 2 days'
					]
				]
			],
			// Retrieve tokens that will expire in less that 12 days.
			[
				[
					'filter' => [],
					'Expires in less than' => 12,
					'expected' => [
						'Future token for admin',
						'Token that will expire in 2 days'
					]
				]
			],
			// Retrieve tokens that will expire in less that 2 days.
			[
				[
					'filter' => [],
					'Expires in less than' => 2,
					'expected' => [
						'Token that will expire in 2 days'
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
			// Retrieve Enabled tokens that will expire in less that 12 days.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'Expires in less than' => 12,
					'expected' => [
						'Future token for admin'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageApiTokensUserSettings_Filter($data) {
		$this->checkFilter($data, 'user settings');
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'Token that will expire in 2 days',
						'Future token for admin',
						'Expired token for admin',
						'Aktīvs токен - 頑張って'
					]
				]
			],
			[
				[
					'sort_field' => 'Expires at',
					'expected' => [
						'Never',
						'2021-01-01 00:06:00',
						'2 days in the future',
						'12 days in the future'
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
						'2021-01-01 00:00:05'
					]
				]
			],
			[
				[
					'sort_field' => 'Status',
					'expected' => [
						'Enabled',
						'Enabled',
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
	public function testPageApiTokensUserSettings_Sort($data) {
		// Place $timestamp variable value in data provider as the data providers are formed before execution of onBefore.
		if ($data['sort_field'] === 'Expires at') {
			foreach ($data['expected'] as $i => $value) {
				if ($value === '2 days in the future') {
					$data['expected'][$i] = date('Y-m-d H:i:s', self::$timestamp);
				}
				elseif ($value === '12 days in the future') {
					$data['expected'][$i] = date('Y-m-d H:i:s', self::$timestamp + 864000);
				}
			}
		}

		$this->checkSorting($data, 'zabbix.php?action=user.token.list');
	}

	public function testPageApiTokensUserSettings_Delete() {
		$this->checkDelete('zabbix.php?action=user.token.list', self::DELETE_TOKEN);
	}
}
