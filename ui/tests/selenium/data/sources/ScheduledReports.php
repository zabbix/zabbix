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

class ScheduledReports {

	/**
	 * Create data for testFormReports test.
	 *
	 * @return array
	 */
	public static function load() {
		CDataHelper::call('user.create', [
			[
				'username' => 'admin user for testFormScheduledReport',
				'passwd' => 'xibbaz123',
				'roleid' => 2,
				'usrgrps' => [
					[
						'usrgrpid' => 7
					]
				]
			],
			[
				'username' => 'user-recipient of the report',
				'passwd' => 'xibbaz123',
				'roleid' => 2,
				'usrgrps' => [
					[
						'usrgrpid' => 7
					]
				]
			]
		]);
		$userids = CDataHelper::getIds('username');

		CDataHelper::call('report.create', [
			[
				'userid' => '1',
				'name' => 'Report for update',
				'dashboardid' => '1',
				'period' => '1',
				'cycle' => '1',
				'start_time' => '43200', // 12:00
				'weekdays' => '12', // Wednesday and Thursday
				'active_since' => '2025-04-24',
				'active_till' => '2026-04-25',
				'subject' => 'Weekly report',
				'message' => 'Report accompanying text',
				'status' => '0',
				'description' => 'Weekly report description',
				'users' => [
					[
						'userid' => '1',
						'access_userid' => '1',
						'exclude' => '0'
					],
					[
						'userid' => '2',
						'access_userid' => '0',
						'exclude' => '1'
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => '7',
						'access_userid' => '0'
					]
				]
			],
			[
				'userid' => '1',
				'name' => 'Report to update all fields',
				'dashboardid' => '1',
				'period' => '3',
				'cycle' => '1',
				'weekdays' => '12', // Wednesday and Thursday
				'active_till' => '2026-04-25',
				'message' => 'Report text',
				'description' => 'Report description',
				'status' => '1',
				'users' => [
					[
						'userid' => '1',
						'access_userid' => '1',
						'exclude' => '0'
					],
					[
						'userid' => '2',
						'access_userid' => '0',
						'exclude' => '1'
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => '7',
						'access_userid' => '0'
					],
					[
						'usrgrpid' => '12'
					]
				]
			],
			[
				'userid' => '1',
				'name' => 'Report for testFormScheduledReport',
				'dashboardid' => '1',
				'period' => '2',
				'cycle' => '1',
				'weekdays' => '83', // Monday, Tuesday, Friday, Sunday
				'start_time' => '43200', // 15:16
				'active_since' => '2021-07-20',
				'subject' => 'Report subject for testFormScheduledReport',
				'message' => 'Report message text',
				'description' => 'Report description',
				'status' => '1',
				'users' => [
					[
						'userid' => '1',
						'access_userid' => '0',
						'exclude' => '1'
					],
					[
						'userid' => $userids['admin user for testFormScheduledReport'],
						'access_userid' => '1'
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => '8',
						'access_userid' => '1'
					]
				]
			],
			[
				'userid' => $userids['admin user for testFormScheduledReport'],
				'name' => 'Report for delete',
				'dashboardid' => '1',
				'subject' => 'subject for report delete test',
				'message' => 'message for report delete test',
				'users' => [
					[
						'userid' => $userids['user-recipient of the report'],
						'access_userid' => $userids['user-recipient of the report']
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => '7'
					]
				]
			],
			[
				'userid' => $userids['admin user for testFormScheduledReport'],
				'name' => 'Report for filter - owner admin',
				'dashboardid' => '1',
				'period' => '1',
				'cycle' => '1',
				'weekdays' => '31',
				'user_groups' => [
					[
						'usrgrpid' => '7',
						'access_userid' => '0'
					]
				]
			],
			[
				'userid' => $userids['admin user for testFormScheduledReport'],
				'name' => 'Report for filter - expired, owner admin',
				'dashboardid' => '1',
				'active_since' => '2020-04-24',
				'active_till' => '2021-04-25',
				'user_groups' => [
					[
						'usrgrpid' => '7',
						'access_userid' => '0'
					]
				]
			],
			[
				'userid' => '1',
				'name' => 'Report for filter - expired',
				'dashboardid' => '57',
				'active_till' => '2020-01-01',
				'period' => '3',
				'cycle' => '3',
				'user_groups' => [
					[
						'usrgrpid' => '7'
					]
				]
			],
			[
				'userid' => '1',
				'name' => 'Report for filter - enabled',
				'dashboardid' => '57',
				'period' => '3',
				'cycle' => '2',
				'users' => [
					[
						'userid' => '1'
					]
				]
			],
			[
				'userid' => '1',
				'name' => 'Report for filter - disabled',
				'dashboardid' => '57',
				'period' => '2',
				'cycle' => '2',
				'users' => [
					[
						'userid' => '1'
					]
				],
				'status' => '1'
			]
		]);

		return [
			'reportids' => CDataHelper::getIds('name')
		];
	}
}
