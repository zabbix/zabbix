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
 * @backup dashboard, ids
 *
 * @onBefore prepareCreateData
 */
class testAuditDashboard extends testPageReportsAuditValues {

	/**
	 * Id of dashboard.
	 *
	 * @var integer
	 */
	protected static $ids;

	public $created = "dashboard.dashboardid: 159".
			"\ndashboard.name: Audit dashboard".
			"\ndashboard.pages[1468]: Added".
			"\ndashboard.pages[1468].dashboard_pageid: 1468".
			"\ndashboard.pages[1468].widgets[3905]: Added".
			"\ndashboard.pages[1468].widgets[3905].fields[11628]: Added".
			"\ndashboard.pages[1468].widgets[3905].fields[11628].name: tags.tag.0".
			"\ndashboard.pages[1468].widgets[3905].fields[11628].type: 1".
			"\ndashboard.pages[1468].widgets[3905].fields[11628].value: service".
			"\ndashboard.pages[1468].widgets[3905].fields[11628].widget_fieldid: 11628".
			"\ndashboard.pages[1468].widgets[3905].fields[11629]: Added".
			"\ndashboard.pages[1468].widgets[3905].fields[11629].name: tags.operator.0".
			"\ndashboard.pages[1468].widgets[3905].fields[11629].value: 1".
			"\ndashboard.pages[1468].widgets[3905].fields[11629].widget_fieldid: 11629".
			"\ndashboard.pages[1468].widgets[3905].fields[11630]: Added".
			"\ndashboard.pages[1468].widgets[3905].fields[11630].name: tags.value.0".
			"\ndashboard.pages[1468].widgets[3905].fields[11630].type: 1".
			"\ndashboard.pages[1468].widgets[3905].fields[11630].value: zabbix_server".
			"\ndashboard.pages[1468].widgets[3905].fields[11630].widget_fieldid: 11630".
			"\ndashboard.pages[1468].widgets[3905].height: 5".
			"\ndashboard.pages[1468].widgets[3905].type: problems".
			"\ndashboard.pages[1468].widgets[3905].widgetid: 3905".
			"\ndashboard.pages[1468].widgets[3905].width: 12".
			"\ndashboard.userGroups[2]: Added".
			"\ndashboard.userGroups[2].dashboard_usrgrpid: 2".
			"\ndashboard.userGroups[2].usrgrpid: 7".
			"\ndashboard.userid: 1".
			"\ndashboard.users[1]: Added".
			"\ndashboard.users[1].dashboard_userid: 1".
			"\ndashboard.users[1].userid: 1";

	public $updated = "dashboard.auto_start: 1 => 0".
			"\ndashboard.display_period: 30 => 60".
			"\ndashboard.name: Audit dashboard => Updated dashboard name".
			"\ndashboard.pages[1468]: Deleted".
			"\ndashboard.pages[1469]: Added".
			"\ndashboard.pages[1469].dashboard_pageid: 1469".
			"\ndashboard.pages[1469].widgets[3906]: Added".
			"\ndashboard.pages[1469].widgets[3906].height: 3".
			"\ndashboard.pages[1469].widgets[3906].type: clock".
			"\ndashboard.pages[1469].widgets[3906].widgetid: 3906".
			"\ndashboard.pages[1469].widgets[3906].width: 4".
			"\ndashboard.pages[1470]: Added".
			"\ndashboard.pages[1470].dashboard_pageid: 1470".
			"\ndashboard.pages[1470].display_period: 60".
			"\ndashboard.userGroups[2]: Updated".
			"\ndashboard.userGroups[2].permission: 2 => 3";

	public $deleted = 'Description: Updated dashboard name';

	public $resource_name = 'Dashboard';

	public function prepareCreateData() {
		$ids = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Audit dashboard',
				'display_period' => 30,
				'auto_start' => 1,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'problems',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => 1,
										'name' => 'tags.tag.0',
										'value' => 'service'
									],
									[
										'type' => 0,
										'name' => 'tags.operator.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'tags.value.0',
										'value' => 'zabbix_server'
									]
								]
							]
						]
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => '7',
						'permission' => 2
					]
				],
				'users' => [
					[
						'userid' => '1',
						'permission' => 2
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $ids);
		self::$ids = $ids['dashboardids'][0];
	}

	/**
	 * Check audit of created Dashboard.
	 */
	public function testAuditDashboard_Create() {
		$this->checkAuditValues(self::$ids, 'Add');
	}

	/**
	 * Check audit of updated Dashboard.
	 */
	public function testAuditDashboard_Update() {
		CDataHelper::call('dashboard.update', [
			[
				'dashboardid' => self::$ids,
				'name' => 'Updated dashboard name',
				'display_period' => 60,
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'x' => 0,
								'y' => 0,
								'width' => 4,
								'height' => 3
							]
						]
					],
					[
						'display_period' => 60
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => '7',
						'permission' => 3
					]
				]
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}

	/**
	 * Check audit of deleted Dashboard.
	 */
	public function testAuditDashboard_Delete() {
		CDataHelper::call('dashboard.delete', [self::$ids]);

		$this->checkAuditValues(self::$ids, 'Delete');
	}
}
