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

		// Adding test data to the 'alerts' table for testing Reports-> Notifications.
		DBexecute("INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES".
				"(8, 12, 1, 1, 1483275171, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.01.01 12:52:51', 1, 0, '', 1, 0, ''),".
				"(9, 12, 1, 2, 1486039971, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.02.02 12:52:51', 1, 0, '', 1, 0, ''),".
				"(10, 12, 1, 2, 1487030400, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.02.14 00:00:00', 1, 0, '', 1, 0, ''),".
				"(11, 12, 1, ".zbx_dbstr($userids['test-user']).", 1488545571, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.03.03 12:52:51', 1, 0, '', 1, 0, ''),".
				"(12, 12, 1, ".zbx_dbstr($userids['test-user']).", 1488382034, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.03.01 15:27:14', 1, 0, '', 1, 0, ''),".
				"(13, 12, 1, ".zbx_dbstr($userids['test-user']).", 1490701552, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.03.28 11:45:52', 1, 0, '', 1, 0, ''),".
				"(14, 12, 1, 4, 1491310371, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.04.04 12:52:51', 2, 0, '', 1, 0, ''),".
				"(15, 12, 1, 4, 1493096321, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.04.25 04:58:41', 2, 0, '', 1, 0, ''),".
				"(16, 12, 1, 4, 1492456511, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.04.17 19:15:11', 2, 0, '', 1, 0, ''),".
				"(17, 12, 1, 4, 1493585245, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.04.30 23:47:25', 2, 0, '', 1, 0, ''),".
				"(18, 12, 1, 5, 1493988771, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.05.05 12:52:51', 0, 0, '', 1, 0, ''),".
				"(19, 12, 1, 5, 1493693050, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.05.02 02:44:10', 0, 0, '', 1, 0, ''),".
				"(20, 12, 1, 5, 1494674768, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.05.13 11:26:08', 0, 0, '', 1, 0, ''),".
				"(21, 12, 1, 5, 1495924312, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.05.27 22:31:52', 0, 0, '', 1, 0, ''),".
				"(22, 12, 1, 5, 1496256062, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.05.31 21:41:02', 0, 0, '', 1, 0, ''),".
				"(23, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1496753571, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.06.06 12:52:51', 1, 0, '', 1, 1, ''),".
				"(24, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1496524375, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.06.03 21:12:55', 1, 0, '', 1, 1, ''),".
				"(25, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1497731966, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.06.17 20:39:26', 1, 0, '', 1, 1, ''),".
				"(26, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1498160557, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.06.22 19:42:37', 1, 0, '', 1, 1, ''),".
				"(27, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1498501846, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.06.26 18:30:46', 1, 0, '', 1, 1, ''),".
				"(28, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1498759123, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.06.29 17:58:43', 1, 0, '', 1, 1, ''),".
				"(29, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1499431971, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.07 12:52:51', 1, 0, '', 1, 1, ''),".
				"(30, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1498870861, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.01 01:01:01', 1, 0, '', 1, 1, ''),".
				"(31, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1498960922, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.02 02:02:02', 1, 0, '', 1, 1, ''),".
				"(32, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1499050983, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.03 03:03:03', 1, 0, '', 1, 1, ''),".
				"(33, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1499141044, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.04 04:04:04', 1, 0, '', 1, 1, ''),".
				"(34, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1499231105, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.05 05:05:05', 1, 0, '', 1, 1, ''),".
				"(35, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1499321166, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.07.06 06:06:06', 1, 0, '', 1, 1, ''),".
				"(36, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502196771, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.08 12:52:51', 1, 0, '', 1, 1, ''),".
				"(37, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502269749, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.09 09:09:09', 1, 0, '', 1, 1, ''),".
				"(38, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502359810, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.10 10:10:10', 1, 0, '', 1, 1, ''),".
				"(39, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502449871, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.11 11:11:11', 1, 0, '', 1, 1, ''),".
				"(40, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502539932, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.12 12:12:12', 1, 0, '', 1, 1, ''),".
				"(41, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502629993, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.13 13:13:13', 1, 0, '', 1, 1, ''),".
				"(42, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502720054, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.14 14:14:14', 1, 0, '', 1, 1, ''),".
				"(43, 12, 1, ".zbx_dbstr($userids['no-access-to-the-frontend']).", 1502810115, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.08.15 15:15:15', 1, 0, '', 1, 1, ''),".
				"(44, 12, 1, 1, 1504961571, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.09 12:52:51', 1, 0, '', 1, 1, ''),".
				"(45, 12, 1, 1, 1505578576, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.16 16:16:16', 1, 0, '', 1, 1, ''),".
				"(46, 12, 1, 1, 1505668637, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.17 17:17:17', 1, 0, '', 1, 1, ''),".
				"(47, 12, 1, 1, 1505758698, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.18 18:18:18', 1, 0, '', 1, 1, ''),".
				"(48, 12, 1, 1, 1505848759, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.19 19:19:19', 1, 0, '', 1, 1, ''),".
				"(49, 12, 1, 1, 1505938820, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.20 20:20:20', 1, 0, '', 1, 1, ''),".
				"(50, 12, 1, 1, 1506028881, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.21 21:21:21', 1, 0, '', 1, 1, ''),".
				"(51, 12, 1, 1, 1506118942, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.22 22:22:22', 1, 0, '', 1, 1, ''),".
				"(52, 12, 1, 1, 1506209003, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.09.23 23:23:23', 1, 0, '', 1, 1, ''),".
				"(53, 12, 1, 2, 1507639971, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.10 12:52:51', 1, 0, '', 1, 1, ''),".
				"(54, 12, 1, 2, 1508804664, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.24 00:24:24', 1, 0, '', 1, 1, ''),".
				"(55, 12, 1, 2, 1508894725, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.25 01:25:25', 1, 0, '', 1, 1, ''),".
				"(56, 12, 1, 2, 1508984786, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.26 02:26:26', 1, 0, '', 1, 1, ''),".
				"(57, 12, 1, 2, 1509074847, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.27 03:27:27', 1, 0, '', 1, 1, ''),".
				"(58, 12, 1, 2, 1509164908, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.28 04:28:28', 1, 0, '', 1, 1, ''),".
				"(59, 12, 1, 2, 1509254969, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.29 05:29:29', 1, 0, '', 1, 1, ''),".
				"(60, 12, 1, 2, 1509345030, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.30 06:30:30', 1, 0, '', 1, 1, ''),".
				"(61, 12, 1, 2, 1509435091, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.31 07:31:31', 1, 0, '', 1, 1, ''),".
				"(62, 12, 1, 2, 1506846752, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.10.01 08:32:32', 1, 0, '', 1, 1, ''),".
				"(63, 12, 1, ".zbx_dbstr($userids['test-user']).", 1510404771, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.11 12:52:51', 1, 0, '', 1, 1, ''),".
				"(64, 12, 1, ".zbx_dbstr($userids['test-user']).", 1509615213, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.02 09:33:33', 1, 0, '', 1, 1, ''),".
				"(65, 12, 1, ".zbx_dbstr($userids['test-user']).", 1509705274, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.03 10:34:34', 1, 0, '', 1, 1, ''),".
				"(66, 12, 1, ".zbx_dbstr($userids['test-user']).", 1509795335, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.04 11:35:35', 1, 0, '', 1, 1, ''),".
				"(67, 12, 1, ".zbx_dbstr($userids['test-user']).", 1509885396, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.05 12:36:36', 1, 0, '', 1, 1, ''),".
				"(68, 12, 1, ".zbx_dbstr($userids['test-user']).", 1509975457, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.06 13:37:37', 1, 0, '', 1, 1, ''),".
				"(69, 12, 1, ".zbx_dbstr($userids['test-user']).", 1510065518, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.07 14:38:38', 1, 0, '', 1, 1, ''),".
				"(70, 12, 1, ".zbx_dbstr($userids['test-user']).", 1510155579, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.08 15:39:39', 1, 0, '', 1, 1, ''),".
				"(71, 12, 1, ".zbx_dbstr($userids['test-user']).", 1510245640, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.09 16:40:40', 1, 0, '', 1, 1, ''),".
				"(72, 12, 1, ".zbx_dbstr($userids['test-user']).", 1510335701, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.10 17:41:41', 1, 0, '', 1, 1, ''),".
				"(73, 12, 1, ".zbx_dbstr($userids['test-user']).", 1510425762, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.11.11 18:42:42', 1, 0, '', 1, 1, ''),".
				"(74, 12, 1, 4, 1513083171, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.12 12:52:51', 1, 0, '', 1, 1, ''),".
				"(75, 12, 1, 4, 1513107823, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.12 19:43:43', 1, 0, '', 1, 1, ''),".
				"(76, 12, 1, 4, 1513197884, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.13 20:44:44', 1, 0, '', 1, 1, ''),".
				"(77, 12, 1, 4, 1513287945, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.14 21:45:45', 1, 0, '', 1, 1, ''),".
				"(78, 12, 1, 4, 1513378006, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.15 22:46:46', 1, 0, '', 1, 1, ''),".
				"(79, 12, 1, 4, 1513468067, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.16 23:47:47', 1, 0, '', 1, 1, ''),".
				"(80, 12, 1, 4, 1513471728, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.17 00:48:48', 1, 0, '', 1, 1, ''),".
				"(81, 12, 1, 4, 1513561789, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.18 01:49:49', 1, 0, '', 1, 1, ''),".
				"(82, 12, 1, 4, 1513651850, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.19 02:50:50', 1, 0, '', 1, 1, ''),".
				"(83, 12, 1, 4, 1513741911, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.20 03:51:51', 1, 0, '', 1, 1, ''),".
				"(84, 12, 1, 4, 1513831972, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.21 04:52:52', 1, 0, '', 1, 1, ''),".
				"(85, 12, 1, 4, 1513922033, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2017.12.22 05:53:53', 1, 0, '', 1, 1, ''),".
				"(86, 12, 1, 5, 1453524894, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.23 06:54:54', 1, 0, '', 1, 1, ''),".
				"(87, 12, 1, 5, 1453614955, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.24 07:55:55', 1, 0, '', 1, 1, ''),".
				"(88, 12, 1, 5, 1453705016, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.25 08:56:56', 1, 0, '', 1, 1, ''),".
				"(89, 12, 1, 5, 1453795077, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.26 09:57:57', 1, 0, '', 1, 1, ''),".
				"(90, 12, 1, 5, 1453885138, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.27 10:58:58', 1, 0, '', 1, 1, ''),".
				"(91, 12, 1, 5, 1453975199, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.28 11:59:59', 1, 0, '', 1, 1, ''),".
				"(92, 12, 1, 5, 1454061600, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.29 12:00:00', 1, 0, '', 1, 1, ''),".
				"(93, 12, 1, 5, 1454151661, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.30 13:01:01', 1, 0, '', 1, 1, ''),".
				"(94, 12, 1, 5, 1454241722, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.31 14:02:02', 1, 0, '', 1, 1, ''),".
				"(95, 12, 1, 5, 1451653383, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.01 15:03:03', 1, 0, '', 1, 1, ''),".
				"(96, 12, 1, 5, 1451743444, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.02 16:04:04', 1, 0, '', 1, 1, ''),".
				"(97, 12, 1, 5, 1451833505, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.03 17:05:05', 1, 0, '', 1, 1, ''),".
				"(98, 12, 1, 5, 1451923566, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.01.04 18:06:06', 1, 0, '', 1, 1, ''),".
				"(99, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1467734827, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.05 19:07:07', 1, 0, '', 1, 1, ''),".
				"(100, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1467824888, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.06 20:08:08', 1, 0, '', 1, 1, ''),".
				"(101, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1467914949, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.07 21:09:09', 1, 0, '', 1, 1, ''),".
				"(102, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468005010, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.08 22:10:10', 1, 0, '', 1, 1, ''),".
				"(103, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468095071, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.09 23:11:11', 1, 0, '', 1, 1, ''),".
				"(104, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468098732, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.10 00:12:12', 1, 0, '', 1, 1, ''),".
				"(105, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468188793, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.11 01:13:13', 1, 0, '', 1, 1, ''),".
				"(106, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468278854, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.12 02:14:14', 1, 0, '', 1, 1, ''),".
				"(107, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468368915, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.13 03:15:15', 1, 0, '', 1, 1, ''),".
				"(108, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468458976, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.14 04:16:16', 1, 0, '', 1, 1, ''),".
				"(109, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468549037, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.15 05:17:17', 1, 0, '', 1, 1, ''),".
				"(110, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468639098, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.16 06:18:18', 1, 0, '', 1, 1, ''),".
				"(111, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468729159, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.17 07:19:19', 1, 0, '', 1, 1, ''),".
				"(112, 12, 1, ".zbx_dbstr($userids['user-for-blocking']).", 1468819220, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.07.18 08:20:20', 1, 0, '', 1, 1, ''),".
				"(113, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1479540081, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.19 09:21:21', 1, 0, '', 1, 1, ''),".
				"(114, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1479630142, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.20 10:22:22', 1, 0, '', 1, 1, ''),".
				"(115, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1479720203, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.21 11:23:23', 1, 0, '', 1, 1, ''),".
				"(116, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1479810264, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.22 12:24:24', 1, 0, '', 1, 1, ''),".
				"(117, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1479900325, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.23 13:25:25', 1, 0, '', 1, 1, ''),".
				"(118, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1479990386, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.24 14:26:26', 1, 0, '', 1, 1, ''),".
				"(119, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1480080447, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.25 15:27:27', 1, 0, '', 1, 1, ''),".
				"(120, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1480170508, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.26 16:28:28', 1, 0, '', 1, 1, ''),".
				"(121, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1480260569, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.27 17:29:29', 1, 0, '', 1, 1, ''),".
				"(122, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1480350630, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.28 18:30:30', 1, 0, '', 1, 1, ''),".
				"(123, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1480440691, 1, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.29 19:31:31', 1, 0, '', 1, 1, ''),".
				"(124, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1480530752, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.30 20:32:32', 1, 0, '', 1, 1, ''),".
				"(125, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1478201613, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.03 21:33:33', 1, 0, '', 1, 1, ''),".
				"(126, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1478032474, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.01 22:34:34', 1, 0, '', 1, 1, ''),".
				"(127, 12, 1, ".zbx_dbstr($userids['disabled-user']).", 1478122535, 3, 'notificatio.report@zabbix.com', 'PROBLEM: problem', 'Event at 2016.11.02 23:35:35', 1, 0, '', 1, 1, '')"
		);

		return [
			'userids' => $userids, 'usrgrpids' => $usergrpids
		];
	}
}
