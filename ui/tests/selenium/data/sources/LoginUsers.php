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

class LoginUsers {

	/**
	 * Create data for autotests which use new created users.
	 *
	 * @return array
	 */
	public static function load() {
		CDataHelper::call('usergroup.create', [
			[
				'name' => 'LDAP user group',
				'gui_access' => 2
			]
		]);
		$usergrpids = CDataHelper::getIds('name');

		CDataHelper::call('user.create', [
			[
				'username' => 'LDAP user',
				'passwd' => 'zabbix12345',
				'autologin' => 0,
				'autologout' => 0,
				'lang' => 'default',
				'refresh' => '30',
				'roleid' => 3,
				'theme' => 'default',
				'rows_per_page' => 100,
				'usrgrps' => [
					[
						'usrgrpid' => $usergrpids['LDAP user group']
					]
				]
			],
			[
				'username' => 'disabled-user',
				'passwd' => 'zabbix12345',
				'autologin' => 0,
				'autologout' => 0,
				'lang' => 'en_US',
				'refresh' => '30',
				'theme' => 'default',
				'rows_per_page' => 50,
				'roleid' => 1,
				'usrgrps' => [
					[
						'usrgrpid' => 9
					]
				]
			],
			[
				'username' => 'test-user',
				'passwd' => 'zabbix12345',
				'autologin' => 0,
				'autologout' => 0,
				'lang' => 'en_US',
				'refresh' => '30',
				'theme' => 'default',
				'rows_per_page' => 50,
				'roleid' => 1,
				'usrgrps' => [
					[
						'usrgrpid' => 8
					]
				],
				'medias' => [
					[
						'mediatypeid' => '1',
						'sendto' => [
							'zabbix@zabbix.com'
						],
						'active' => 0,
						'severity' => 60,
						'period' => '1-5,09:00-18:00'
					]
				]
			],
			[
				'username' => 'user-for-blocking',
				'passwd' => 'zabbix12345',
				'autologin' => 0,
				'autologout' => 0,
				'lang' => 'en_US',
				'refresh' => '30',
				'theme' => 'default',
				'rows_per_page' => 50,
				'roleid' => 1,
				'usrgrps' => [
					[
						'usrgrpid' => 8
					]
				]
			],
			[
				'username' => 'no-access-to-the-frontend',
				'passwd' => 'zabbix12345',
				'autologin' => 0,
				'autologout' => 0,
				'lang' => 'en_US',
				'refresh' => '30',
				'theme' => 'default',
				'rows_per_page' => 50,
				'roleid' => 1,
				'usrgrps' => [
					[
						'usrgrpid' => 12
					]
				]
			]
		]);
		$userids = CDataHelper::getIds('username');

		$actions = CDataHelper::call('action.create', [
			[
				'name' => 'Action with user',
				'eventsource' => EVENT_SOURCE_TRIGGERS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'conditions' => []
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_MESSAGE,
						'opmessage' => ['mediatypeid' => 1], // Email.
						'opmessage_usr' => [['userid' => $userids['user-for-blocking']]]
					]
				]
			]
		]);
		$actionid = $actions['actionids'][0];

		// Add Actions to Action Log in database.
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns) VALUES '.
				' (1, 0, 0, 13545, 1329724790, 1, 0, 0);'
		);

		// Adding test data to the 'alerts' table for test /reports/testPageReportsNotifications.
		DBexecute('INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES'.
				'(8, '.zbx_dbstr($actionid).', 1, 1, 1483275171, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.01.01 12:52:51\', 1, 0, \'\', 1, 0, \'\'),'.
				'(9,'.zbx_dbstr($actionid).', 1, 2, 1486039971, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.02.02 12:52:51\', 1, 0, \'\', 1, 0, \'\'),'.
				'(10,'.zbx_dbstr($actionid).', 1, 2, 1487030400, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.02.14 00:00:00\', 1, 0, \'\', 1, 0, \'\'),'.
				'(11,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1488545571, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.03.03 12:52:51\', 1, 0, \'\', 1, 0, \'\'),'.
				'(12,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1488382034, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.03.01 15:27:14\', 1, 0, \'\', 1, 0, \'\'),'.
				'(13,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1490701552, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.03.28 11:45:52\', 1, 0, \'\', 1, 0, \'\'),'.
				'(14,'.zbx_dbstr($actionid).', 1, 40, 1491310371, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.04.04 12:52:51\', 2, 0, \'\', 1, 0, \'\'),'.
				'(15,'.zbx_dbstr($actionid).', 1, 40, 1493096321, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.04.25 04:58:41\', 2, 0, \'\', 1, 0, \'\'),'.
				'(16,'.zbx_dbstr($actionid).', 1, 40, 1492456511, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.04.17 19:15:11\', 2, 0, \'\', 1, 0, \'\'),'.
				'(17,'.zbx_dbstr($actionid).', 1, 40, 1493585245, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.04.30 23:47:25\', 2, 0, \'\', 1, 0, \'\'),'.
				'(18,'.zbx_dbstr($actionid).', 1, 50, 1493988771, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.05.05 12:52:51\', 0, 0, \'\', 1, 0, \'\'),'.
				'(19,'.zbx_dbstr($actionid).', 1, 50, 1493693050, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.05.02 02:44:10\', 0, 0, \'\', 1, 0, \'\'),'.
				'(20,'.zbx_dbstr($actionid).', 1, 50, 1494674768, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.05.13 11:26:08\', 0, 0, \'\', 1, 0, \'\'),'.
				'(21,'.zbx_dbstr($actionid).', 1, 50, 1495924312, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.05.27 22:31:52\', 0, 0, \'\', 1, 0, \'\'),'.
				'(22,'.zbx_dbstr($actionid).', 1, 50, 1496256062, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.05.31 21:41:02\', 0, 0, \'\', 1, 0, \'\'),'.
				'(23,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1496753571, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.06.06 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(24,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1496524375, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.06.03 21:12:55\', 1, 0, \'\', 1, 1, \'\'),'.
				'(25,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1497731966, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.06.17 20:39:26\', 1, 0, \'\', 1, 1, \'\'),'.
				'(26,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1498160557, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.06.22 19:42:37\', 1, 0, \'\', 1, 1, \'\'),'.
				'(27,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1498501846, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.06.26 18:30:46\', 1, 0, \'\', 1, 1, \'\'),'.
				'(28,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1498759123, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.06.29 17:58:43\', 1, 0, \'\', 1, 1, \'\'),'.
				'(29,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1499431971, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.07 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(30,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1498870861, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.01 01:01:01\', 1, 0, \'\', 1, 1, \'\'),'.
				'(31,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1498960922, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.02 02:02:02\', 1, 0, \'\', 1, 1, \'\'),'.
				'(32,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1499050983, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.03 03:03:03\', 1, 0, \'\', 1, 1, \'\'),'.
				'(33,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1499141044, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.04 04:04:04\', 1, 0, \'\', 1, 1, \'\'),'.
				'(34,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1499231105, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.05 05:05:05\', 1, 0, \'\', 1, 1, \'\'),'.
				'(35,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1499321166, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.07.06 06:06:06\', 1, 0, \'\', 1, 1, \'\'),'.
				'(36,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502196771, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.08 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(37,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502269749, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.09 09:09:09\', 1, 0, \'\', 1, 1, \'\'),'.
				'(38,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502359810, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.10 10:10:10\', 1, 0, \'\', 1, 1, \'\'),'.
				'(39,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502449871, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.11 11:11:11\', 1, 0, \'\', 1, 1, \'\'),'.
				'(40,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502539932, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.12 12:12:12\', 1, 0, \'\', 1, 1, \'\'),'.
				'(41,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502629993, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.13 13:13:13\', 1, 0, \'\', 1, 1, \'\'),'.
				'(42,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502720054, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.14 14:14:14\', 1, 0, \'\', 1, 1, \'\'),'.
				'(43,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['no-access-to-the-frontend']).', 1502810115, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.08.15 15:15:15\', 1, 0, \'\', 1, 1, \'\'),'.
				'(44,'.zbx_dbstr($actionid).', 1, 1, 1504961571, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.09 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(45,'.zbx_dbstr($actionid).', 1, 1, 1505578576, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.16 16:16:16\', 1, 0, \'\', 1, 1, \'\'),'.
				'(46,'.zbx_dbstr($actionid).', 1, 1, 1505668637, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.17 17:17:17\', 1, 0, \'\', 1, 1, \'\'),'.
				'(47,'.zbx_dbstr($actionid).', 1, 1, 1505758698, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.18 18:18:18\', 1, 0, \'\', 1, 1, \'\'),'.
				'(48,'.zbx_dbstr($actionid).', 1, 1, 1505848759, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.19 19:19:19\', 1, 0, \'\', 1, 1, \'\'),'.
				'(49,'.zbx_dbstr($actionid).', 1, 1, 1505938820, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.20 20:20:20\', 1, 0, \'\', 1, 1, \'\'),'.
				'(50,'.zbx_dbstr($actionid).', 1, 1, 1506028881, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.21 21:21:21\', 1, 0, \'\', 1, 1, \'\'),'.
				'(51,'.zbx_dbstr($actionid).', 1, 1, 1506118942, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.22 22:22:22\', 1, 0, \'\', 1, 1, \'\'),'.
				'(52,'.zbx_dbstr($actionid).', 1, 1, 1506209003, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.09.23 23:23:23\', 1, 0, \'\', 1, 1, \'\'),'.
				'(53,'.zbx_dbstr($actionid).', 1, 2, 1507639971, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.10 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(54,'.zbx_dbstr($actionid).', 1, 2, 1508804664, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.24 00:24:24\', 1, 0, \'\', 1, 1, \'\'),'.
				'(55,'.zbx_dbstr($actionid).', 1, 2, 1508894725, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.25 01:25:25\', 1, 0, \'\', 1, 1, \'\'),'.
				'(56,'.zbx_dbstr($actionid).', 1, 2, 1508984786, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.26 02:26:26\', 1, 0, \'\', 1, 1, \'\'),'.
				'(57,'.zbx_dbstr($actionid).', 1, 2, 1509074847, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.27 03:27:27\', 1, 0, \'\', 1, 1, \'\'),'.
				'(58,'.zbx_dbstr($actionid).', 1, 2, 1509164908, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.28 04:28:28\', 1, 0, \'\', 1, 1, \'\'),'.
				'(59,'.zbx_dbstr($actionid).', 1, 2, 1509254969, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.29 05:29:29\', 1, 0, \'\', 1, 1, \'\'),'.
				'(60,'.zbx_dbstr($actionid).', 1, 2, 1509345030, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.30 06:30:30\', 1, 0, \'\', 1, 1, \'\'),'.
				'(61,'.zbx_dbstr($actionid).', 1, 2, 1509435091, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.31 07:31:31\', 1, 0, \'\', 1, 1, \'\'),'.
				'(62,'.zbx_dbstr($actionid).', 1, 2, 1506846752, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.10.01 08:32:32\', 1, 0, \'\', 1, 1, \'\'),'.
				'(63,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1510404771, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.11 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(64,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1509615213, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.02 09:33:33\', 1, 0, \'\', 1, 1, \'\'),'.
				'(65,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1509705274, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.03 10:34:34\', 1, 0, \'\', 1, 1, \'\'),'.
				'(66,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1509795335, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.04 11:35:35\', 1, 0, \'\', 1, 1, \'\'),'.
				'(67,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1509885396, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.05 12:36:36\', 1, 0, \'\', 1, 1, \'\'),'.
				'(68,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1509975457, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.06 13:37:37\', 1, 0, \'\', 1, 1, \'\'),'.
				'(69,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1510065518, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.07 14:38:38\', 1, 0, \'\', 1, 1, \'\'),'.
				'(70,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1510155579, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.08 15:39:39\', 1, 0, \'\', 1, 1, \'\'),'.
				'(71,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1510245640, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.09 16:40:40\', 1, 0, \'\', 1, 1, \'\'),'.
				'(72,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1510335701, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.10 17:41:41\', 1, 0, \'\', 1, 1, \'\'),'.
				'(73,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['test-user']).', 1510425762, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.11.11 18:42:42\', 1, 0, \'\', 1, 1, \'\'),'.
				'(74,'.zbx_dbstr($actionid).', 1, 40, 1513083171, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.12 12:52:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(75,'.zbx_dbstr($actionid).', 1, 40, 1513107823, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.12 19:43:43\', 1, 0, \'\', 1, 1, \'\'),'.
				'(76,'.zbx_dbstr($actionid).', 1, 40, 1513197884, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.13 20:44:44\', 1, 0, \'\', 1, 1, \'\'),'.
				'(77,'.zbx_dbstr($actionid).', 1, 40, 1513287945, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.14 21:45:45\', 1, 0, \'\', 1, 1, \'\'),'.
				'(78,'.zbx_dbstr($actionid).', 1, 40, 1513378006, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.15 22:46:46\', 1, 0, \'\', 1, 1, \'\'),'.
				'(79,'.zbx_dbstr($actionid).', 1, 40, 1513468067, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.16 23:47:47\', 1, 0, \'\', 1, 1, \'\'),'.
				'(80,'.zbx_dbstr($actionid).', 1, 40, 1513471728, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.17 00:48:48\', 1, 0, \'\', 1, 1, \'\'),'.
				'(81,'.zbx_dbstr($actionid).', 1, 40, 1513561789, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.18 01:49:49\', 1, 0, \'\', 1, 1, \'\'),'.
				'(82,'.zbx_dbstr($actionid).', 1, 40, 1513651850, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.19 02:50:50\', 1, 0, \'\', 1, 1, \'\'),'.
				'(83,'.zbx_dbstr($actionid).', 1, 40, 1513741911, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.20 03:51:51\', 1, 0, \'\', 1, 1, \'\'),'.
				'(84,'.zbx_dbstr($actionid).', 1, 40, 1513831972, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.21 04:52:52\', 1, 0, \'\', 1, 1, \'\'),'.
				'(85,'.zbx_dbstr($actionid).', 1, 40, 1513922033, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2017.12.22 05:53:53\', 1, 0, \'\', 1, 1, \'\'),'.
				'(86,'.zbx_dbstr($actionid).', 1, 50, 1453524894, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.23 06:54:54\', 1, 0, \'\', 1, 1, \'\'),'.
				'(87,'.zbx_dbstr($actionid).', 1, 50, 1453614955, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.24 07:55:55\', 1, 0, \'\', 1, 1, \'\'),'.
				'(88,'.zbx_dbstr($actionid).', 1, 50, 1453705016, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.25 08:56:56\', 1, 0, \'\', 1, 1, \'\'),'.
				'(89,'.zbx_dbstr($actionid).', 1, 50, 1453795077, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.26 09:57:57\', 1, 0, \'\', 1, 1, \'\'),'.
				'(90,'.zbx_dbstr($actionid).', 1, 50, 1453885138, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.27 10:58:58\', 1, 0, \'\', 1, 1, \'\'),'.
				'(91,'.zbx_dbstr($actionid).', 1, 50, 1453975199, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.28 11:59:59\', 1, 0, \'\', 1, 1, \'\'),'.
				'(92,'.zbx_dbstr($actionid).', 1, 50, 1454061600, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.29 12:00:00\', 1, 0, \'\', 1, 1, \'\'),'.
				'(93,'.zbx_dbstr($actionid).', 1, 50, 1454151661, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.30 13:01:01\', 1, 0, \'\', 1, 1, \'\'),'.
				'(94,'.zbx_dbstr($actionid).', 1, 50, 1454241722, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.31 14:02:02\', 1, 0, \'\', 1, 1, \'\'),'.
				'(95,'.zbx_dbstr($actionid).', 1, 50, 1451653383, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.01 15:03:03\', 1, 0, \'\', 1, 1, \'\'),'.
				'(96,'.zbx_dbstr($actionid).', 1, 50, 1451743444, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.02 16:04:04\', 1, 0, \'\', 1, 1, \'\'),'.
				'(97,'.zbx_dbstr($actionid).', 1, 50, 1451833505, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.03 17:05:05\', 1, 0, \'\', 1, 1, \'\'),'.
				'(98,'.zbx_dbstr($actionid).', 1, 50, 1451923566, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.01.04 18:06:06\', 1, 0, \'\', 1, 1, \'\'),'.
				'(99,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1467734827, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.05 19:07:07\', 1, 0, \'\', 1, 1, \'\'),'.
				'(100,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1467824888, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.06 20:08:08\', 1, 0, \'\', 1, 1, \'\'),'.
				'(101,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1467914949, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.07 21:09:09\', 1, 0, \'\', 1, 1, \'\'),'.
				'(102,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468005010, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.08 22:10:10\', 1, 0, \'\', 1, 1, \'\'),'.
				'(103,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468095071, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.09 23:11:11\', 1, 0, \'\', 1, 1, \'\'),'.
				'(104,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468098732, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.10 00:12:12\', 1, 0, \'\', 1, 1, \'\'),'.
				'(105,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468188793, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.11 01:13:13\', 1, 0, \'\', 1, 1, \'\'),'.
				'(106,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468278854, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.12 02:14:14\', 1, 0, \'\', 1, 1, \'\'),'.
				'(107,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468368915, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.13 03:15:15\', 1, 0, \'\', 1, 1, \'\'),'.
				'(108,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468458976, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.14 04:16:16\', 1, 0, \'\', 1, 1, \'\'),'.
				'(109,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468549037, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.15 05:17:17\', 1, 0, \'\', 1, 1, \'\'),'.
				'(110,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468639098, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.16 06:18:18\', 1, 0, \'\', 1, 1, \'\'),'.
				'(111,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468729159, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.17 07:19:19\', 1, 0, \'\', 1, 1, \'\'),'.
				'(112,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['user-for-blocking']).', 1468819220, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.07.18 08:20:20\', 1, 0, \'\', 1, 1, \'\'),'.
				'(113,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1479540081, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.19 09:21:21\', 1, 0, \'\', 1, 1, \'\'),'.
				'(114,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1479630142, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.20 10:22:22\', 1, 0, \'\', 1, 1, \'\'),'.
				'(115,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1479720203, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.21 11:23:23\', 1, 0, \'\', 1, 1, \'\'),'.
				'(116,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1479810264, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.22 12:24:24\', 1, 0, \'\', 1, 1, \'\'),'.
				'(117,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1479900325, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.23 13:25:25\', 1, 0, \'\', 1, 1, \'\'),'.
				'(118,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1479990386, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.24 14:26:26\', 1, 0, \'\', 1, 1, \'\'),'.
				'(119,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1480080447, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.25 15:27:27\', 1, 0, \'\', 1, 1, \'\'),'.
				'(120,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1480170508, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.26 16:28:28\', 1, 0, \'\', 1, 1, \'\'),'.
				'(121,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1480260569, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.27 17:29:29\', 1, 0, \'\', 1, 1, \'\'),'.
				'(122,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1480350630, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.28 18:30:30\', 1, 0, \'\', 1, 1, \'\'),'.
				'(123,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1480440691, 1, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.29 19:31:31\', 1, 0, \'\', 1, 1, \'\'),'.
				'(124,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1480530752, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.30 20:32:32\', 1, 0, \'\', 1, 1, \'\'),'.
				'(125,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1478201613, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.03 21:33:33\', 1, 0, \'\', 1, 1, \'\'),'.
				'(126,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1478032474, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.01 22:34:34\', 1, 0, \'\', 1, 1, \'\'),'.
				'(127,'.zbx_dbstr($actionid).', 1, '.zbx_dbstr($userids['disabled-user']).', 1478122535, 3, \'notificatio.report@zabbix.com\', \'PROBLEM: problem\', \'Event at 2016.11.02 23:35:35\', 1, 0, \'\', 1, 1, \'\')'
		);

		return [
			'userids' => $userids, 'usrgrpids' => $usergrpids
		];
	}
}
