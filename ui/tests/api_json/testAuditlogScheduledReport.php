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


require_once dirname(__FILE__).'/testAuditlogCommon.php';

/**
 * @backup report, ids
 */
class testAuditlogScheduledReport extends testAuditlogCommon {

	protected static $resourceid;

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

		$created = "{\"report.userid\":[\"add\",\"1\"],".
				"\"report.name\":[\"add\",\"Report for audit\"],".
				"\"report.dashboardid\":[\"add\",\"1\"],".
				"\"report.period\":[\"add\",\"1\"],".
				"\"report.cycle\":[\"add\",\"1\"],".
				"\"report.start_time\":[\"add\",\"43200\"],".
				"\"report.weekdays\":[\"add\",\"31\"],".
				"\"report.active_since\":[\"add\",\"1617235200\"],".
				"\"report.active_till\":[\"add\",\"1630454399\"],".
				"\"report.subject\":[\"add\",\"Weekly report\"],".
				"\"report.message\":[\"add\",\"Report accompanying text\"],".
				"\"report.status\":[\"add\",\"1\"],".
				"\"report.description\":[\"add\",\"Report description\"],".
				"\"report.users[1]\":[\"add\"],".
				"\"report.users[1].userid\":[\"add\",\"1\"],".
				"\"report.users[1].access_userid\":[\"add\",\"1\"],".
				"\"report.users[1].reportuserid\":[\"add\",\"1\"],".
				"\"report.user_groups[1]\":[\"add\"],".
				"\"report.user_groups[1].usrgrpid\":[\"add\",\"7\"],".
				"\"report.user_groups[1].access_userid\":[\"add\",\"0\"],".
				"\"report.user_groups[1].reportusrgrpid\":[\"add\",\"1\"],".
				"\"report.reportid\":[\"add\",\"". self::$resourceid."\"]}";

		$this->sendGetRequest('details', 0, $created, self::$resourceid);
	}

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

		$updated = "{\"report.users[1]\":[\"delete\"],".
				"\"report.user_groups[1]\":[\"delete\"],".
				"\"report.users[2]\":[\"add\"],".
				"\"report.user_groups[2]\":[\"add\"],".
				"\"report.name\":[\"update\",\"Updated report for audit\",\"Report for audit\"],".
				"\"report.dashboardid\":[\"update\",\"2\",\"1\"],".
				"\"report.period\":[\"update\",\"3\",\"1\"],".
				"\"report.cycle\":[\"update\",\"2\",\"1\"],".
				"\"report.start_time\":[\"update\",\"44200\",\"43200\"],".
				"\"report.weekdays\":[\"update\",\"0\",\"31\"],".
				"\"report.active_since\":[\"update\",\"1640995200\",\"1617235200\"],".
				"\"report.active_till\":[\"update\",\"1648771199\",\"1630454399\"],".
				"\"report.subject\":[\"update\",\"Updated subject\",\"Weekly report\"],".
				"\"report.message\":[\"update\",\"Updated message\",\"Report accompanying text\"],".
				"\"report.status\":[\"update\",\"0\",\"1\"],".
				"\"report.description\":[\"update\",\"Updated description\",\"Report description\"],".
				"\"report.users[2].userid\":[\"add\",\"2\"],".
				"\"report.users[2].access_userid\":[\"add\",\"0\"],".
				"\"report.users[2].exclude\":[\"add\",\"1\"],".
				"\"report.users[2].reportuserid\":[\"add\",\"2\"],".
				"\"report.user_groups[2].usrgrpid\":[\"add\",\"8\"],".
				"\"report.user_groups[2].access_userid\":[\"add\",\"1\"],".
				"\"report.user_groups[2].reportusrgrpid\":[\"add\",\"2\"]}";

		$this->sendGetRequest('details', 1, $updated, self::$resourceid);
	}

	public function testAuditlogScheduledReport_Delete() {
		$this->call('report.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'Updated report for audit', self::$resourceid);
	}
}
