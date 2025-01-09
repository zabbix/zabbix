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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup report
 */
class testAuditlogScheduledReport extends testAuditlogCommon {

	/**
	 * Created scheduled report id
	 */
	protected static $resourceid;

	/**
	 * Created scheduled reports user group id (before update)
	 */
	protected static $before_usrgrp;

	/**
	 * Created scheduled reports user id (before update)
	 */
	protected static $before_user;

	public function testAuditlogScheduledReport_Create() {
		$create = $this->call('report.create', [
			[
				'userid' => 1,
				'name' => 'Report for audit',
				'dashboardid' => 1,
				'period' => 1,
				'cycle' => 1,
				'start_time' => 43200,
				'weekdays' => 31,
				'active_since' => '2021-04-01',
				'active_till' => '2021-08-31',
				'subject' => 'Weekly report',
				'message' => 'Report accompanying text',
				'status' => 1,
				'description' => 'Report description',
				'users' => [
					[
						'userid' => 1,
						'access_userid' => 1,
						'exclude' => 0
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => 7,
						'access_userid' => 0
					]
				]
			]
		]);

		self::$resourceid = $create['result']['reportids'][0];
		self::$before_usrgrp = CDBHelper::getRow('SELECT reportusrgrpid FROM report_usrgrp WHERE reportid='.
				zbx_dbstr(self::$resourceid)
		);
		self::$before_user = CDBHelper::getRow('SELECT reportuserid FROM report_user WHERE reportid='.
				zbx_dbstr(self::$resourceid)
		);

		$created = json_encode([
			'report.userid' => ['add', '1'],
			'report.name' => ['add', 'Report for audit'],
			'report.dashboardid' => ['add', '1'],
			'report.period' => ['add', '1'],
			'report.cycle' => ['add', '1'],
			'report.start_time' => ['add', '43200'],
			'report.weekdays' => ['add', '31'],
			'report.active_since' => ['add', '1617235200'],
			'report.active_till' => ['add', '1630454399'],
			'report.subject' => ['add', 'Weekly report'],
			'report.message' => ['add', 'Report accompanying text'],
			'report.status' => ['add', '1'],
			'report.description' => ['add', 'Report description'],
			'report.users['.self::$before_user['reportuserid'].']' => ['add'],
			'report.users['.self::$before_user['reportuserid'].'].userid' => ['add', '1'],
			'report.users['.self::$before_user['reportuserid'].'].access_userid' => ['add', '1'],
			'report.users['.self::$before_user['reportuserid'].'].reportuserid' => ['add', self::$before_user['reportuserid']],
			'report.user_groups['.self::$before_usrgrp['reportusrgrpid'].']' => ['add'],
			'report.user_groups['.self::$before_usrgrp['reportusrgrpid'].'].usrgrpid' => ['add', '7'],
			'report.user_groups['.self::$before_usrgrp['reportusrgrpid'].'].reportusrgrpid'
				=> ['add', self::$before_usrgrp['reportusrgrpid']],
			'report.reportid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogScheduledReport_Create
	 */
	public function testAuditlogScheduledReport_Update() {
		$this->call('report.update', [
			[
				'reportid' => self::$resourceid,
				'userid' => 1,
				'name' => 'Updated report for audit',
				'dashboardid' => 2,
				'period' => 3,
				'cycle' => 2,
				'start_time' => 44200,
				'weekdays' => 0,
				'active_since' => '2022-01-01',
				'active_till' => '2022-03-31',
				'subject' => 'Updated subject',
				'message' => 'Updated message',
				'status' => 0,
				'description' => 'Updated description',
				'users' => [
					[
						'userid' => 2,
						'access_userid' => 0,
						'exclude' => 1
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => 8,
						'access_userid' => 1
					]
				]
			]
		]);

		$usrgrp = CDBHelper::getRow('SELECT reportusrgrpid FROM report_usrgrp WHERE reportid='.zbx_dbstr(self::$resourceid));
		$user = CDBHelper::getRow('SELECT reportuserid FROM report_user WHERE reportid='.zbx_dbstr(self::$resourceid));

		$updated = json_encode([
			'report.users['.self::$before_user['reportuserid'].']' => ['delete'],
			'report.user_groups['.self::$before_usrgrp['reportusrgrpid'].']' => ['delete'],
			'report.users['.$user['reportuserid'].']' => ['add'],
			'report.user_groups['.$usrgrp['reportusrgrpid'].']' => ['add'],
			'report.name' => ['update', 'Updated report for audit', 'Report for audit'],
			'report.dashboardid' => ['update', '2', '1'],
			'report.period' => ['update', '3', '1'],
			'report.cycle' => ['update', '2', '1'],
			'report.start_time' => ['update', '44200', '43200'],
			'report.weekdays' => ['update', '0', '31'],
			'report.active_since' => ['update', '1640995200', '1617235200'],
			'report.active_till' => ['update', '1648771199', '1630454399'],
			'report.subject' => ['update', 'Updated subject', 'Weekly report'],
			'report.message' => ['update', 'Updated message', 'Report accompanying text'],
			'report.status' => ['update', '0', '1'],
			'report.description' => ['update', 'Updated description', 'Report description'],
			'report.users['.$user['reportuserid'].'].userid' => ['add', '2'],
			'report.users['.$user['reportuserid'].'].exclude' => ['add', '1'],
			'report.users['.$user['reportuserid'].'].reportuserid' => ['add', $user['reportuserid']],
			'report.user_groups['.$usrgrp['reportusrgrpid'].'].usrgrpid' => ['add', '8'],
			'report.user_groups['.$usrgrp['reportusrgrpid'].'].access_userid' => ['add', '1'],
			'report.user_groups['.$usrgrp['reportusrgrpid'].'].reportusrgrpid' => ['add', $usrgrp['reportusrgrpid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid);
	}

	/**
	 * @depends testAuditlogScheduledReport_Create
	 */
	public function testAuditlogScheduledReport_Delete() {
		$this->call('report.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated report for audit', self::$resourceid);
	}
}
