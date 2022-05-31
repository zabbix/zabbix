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

require_once dirname(__FILE__).'/testPageReportsAuditValues.php';

/**
 * @backup report, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditScheduledReport extends testPageReportsAuditValues {

	/**
	 * Id of action.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "report.active_since: 1617235200".
			"\nreport.active_till: 1630454399".
			"\nreport.cycle: 1".
			"\nreport.dashboardid: 1".
			"\nreport.description: Report description".
			"\nreport.message: Report accompanying text".
			"\nreport.name: Report for audit".
			"\nreport.period: 1".
			"\nreport.reportid: 1".
			"\nreport.start_time: 43200".
			"\nreport.status: 1".
			"\nreport.subject: Weekly report".
			"\nreport.user_groups[1]: Added".
			"\nreport.user_groups[1].access_userid: 0".
			"\nreport.user_groups[1].reportusrgrpid: 1".
			"\nreport.user_groups[1].usrgrpid: 7".
			"\nreport.userid: 1\nreport.users[1]: Added".
			"\nreport.users[1].access_userid: 1".
			"\nreport.users[1].reportuserid: 1".
			"\nreport.users[1].userid: 1".
			"\nreport.users[2]: Added".
			"\nreport.users[2].access_userid: 0".
			"\nreport.users[2].exclude: 1".
			"\nreport.users[2].reportuserid: 2".
			"\nreport.users[2].userid: 2".
			"\nreport.weekdays: 31";

	public $updated = "report.active_since: 1617235200 => 1640995200".
			"\nreport.active_till: 1630454399 => 1648771199".
			"\nreport.cycle: 1 => 2".
			"\nreport.dashboardid: 1 => 2".
			"\nreport.description: Report description => Updated description".
			"\nreport.message: Report accompanying text => Updated message".
			"\nreport.name: Report for audit => Updated report for audit".
			"\nreport.period: 1 => 3".
			"\nreport.start_time: 43200 => 44200".
			"\nreport.status: 1 => 0".
			"\nreport.subject: Weekly report => Updated subject".
			"\nreport.user_groups[1]: Deleted".
			"\nreport.user_groups[2]: Added".
			"\nreport.user_groups[2].access_userid: 1".
			"\nreport.user_groups[2].reportusrgrpid: 2".
			"\nreport.user_groups[2].usrgrpid: 8".
			"\nreport.users[1]: Deleted".
			"\nreport.weekdays: 31 => 0";

	public $deleted = 'Description: Updated report for audit';

	public $resource_name = 'Scheduled report';

	public function prepareCreateData() {
		$ids = CDataHelper::call('report.create', [
			[
				'userid' => '1',
				'name' => 'Report for audit',
				'dashboardid' => '1',
				'period' => '1',
				'cycle' => '1',
				'start_time' => '43200',
				'weekdays' => '31',
				'active_since' => '2021-04-01',
				'active_till' => '2021-08-31',
				'subject' => 'Weekly report',
				'message' => 'Report accompanying text',
				'status' => '1',
				'description' => 'Report description',
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
			]
		]);
		$this->assertArrayHasKey('reportids', $ids);
		self::$ids = $ids['reportids'][0];
	}

	/**
	 * Check audit of created Scheduled report.
	 */
	public function testAuditScheduledReport_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of updated Scheduled report.
	 */
	public function testAuditScheduledReport_Update() {
		CDataHelper::call('report.update', [
			[
				'reportid' => self::$ids,
				'userid' => '1',
				'name' => 'Updated report for audit',
				'dashboardid' => '2',
				'period' => '3',
				'cycle' => '2',
				'start_time' => '44200',
				'weekdays' => '0',
				'active_since' => '2022-01-01',
				'active_till' => '2022-03-31',
				'subject' => 'Updated subject',
				'message' => 'Updated message',
				'status' => '0',
				'description' => 'Updated description',
				'users' => [
					[
						'userid' => '2',
						'access_userid' => '0',
						'exclude' => '1'
					]
				],
				'user_groups' => [
					[
						'usrgrpid' => '8',
						'access_userid' => '1'
					]
				]
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted Scheduled report.
	 */
	public function testAuditScheduledReport_Delete() {
		CDataHelper::call('report.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
