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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup dashboard, ids
 */
class testAuditlogDashboard extends CAPITest {

	protected static $resourceid;

	public function testAuditlogDashboard_Create() {
		$created = "{\"dashboard.name\":[\"add\",\"Audit dashboard\"],\"dashboard.pages[1933].widgets[5309]\":[\"add".
				"\"],\"dashboard.pages[1933].widgets[5309].type\":[\"add\",\"problems\"],\"dashboard.pages".
				"[1933].widgets[5309].width\":[\"add\",\"12\"],\"dashboard.pages[1933].widgets[5309].height\":[\"add".
				"\",\"5\"],\"dashboard.pages[1933].widgets[5309].fields[15708]\":[\"add\"],\"dashboard.pages".
				"[1933].widgets[5309].fields[15708].type\":[\"add\",\"1\"],\"dashboard.pages[1933].widgets".
				"[5309].fields[15708].name\":[\"add\",\"tags.tag.0\"],\"dashboard.pages[1933].widgets[5309].fields".
				"[15708].value\":[\"add\",\"service\"],\"dashboard.pages[1933].widgets[5309].fields".
				"[15708].widget_fieldid\":[\"add\",\"15708\"],\"dashboard.pages[1933].widgets[5309].fields[15709]".
				"\":[\"add\"],\"dashboard.pages[1933].widgets[5309].fields[15709].name\":[\"add\",\"tags.operator.0".
				"\"],\"dashboard.pages[1933].widgets[5309].fields[15709].value\":[\"add\",\"1\"],".
				"\"dashboard.pages[1933].widgets[5309].fields[15709].widget_fieldid\":[\"add\",\"15709\"],".
				"\"dashboard.pages[1933].widgets[5309].fields[15710]\":[\"add\"],\"dashboard.pages[1933].widgets".
				"[5309].fields[15710].type\":[\"add\",\"1\"],\"dashboard.pages[1933].widgets[5309].fields[15710].name".
				"\":[\"add\",\"tags.value.0\"],\"dashboard.pages[1933].widgets[5309].fields[15710].value\":[\"add".
				"\",\"zabbix_server\"],\"dashboard.pages[1933].widgets[5309].fields[15710].widget_fieldid\":[\"add".
				"\",\"15710\"],\"dashboard.pages[1933].widgets[5309].widgetid\":[\"add\",\"5309\"],".
				"\"dashboard.pages[1933]\":[\"add\"],\"dashboard.pages[1933].dashboard_pageid\":[\"add\",".
				"\"1933\"],\"dashboard.userGroups[2]\":[\"add\"],\"dashboard.userGroups[2].usrgrpid\":[\"add\",".
				"\"7\"],\"dashboard.userGroups[2].dashboard_usrgrpid\":[\"add\",\"2\"],\"dashboard.users[1]\":[\"add".
				"\"],\"dashboard.users[1].userid\":[\"add\",\"1\"],\"dashboard.users[1].dashboard_userid\":[\"add\",".
				"\"1\"],\"dashboard.userid\":[\"add\",\"1\"],\"dashboard.dashboardid\":[\"add\",\"165\"]}";

		$create = $this->call('dashboard.create', [
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

		self::$resourceid = $create['result']['dashboardids'][0];
		$this->sendGetRequest('details', 0, $created);
	}

	public function testAuditlogIconMap_Update() {
		$updated = "{\"dashboard.pages[1933]\":[\"delete\"],\"dashboard.pages[1934].widgets[5310]\":[\"add\"],".
				"\"dashboard.pages[1934]\":[\"add\"],\"dashboard.pages[1935]\":[\"add\"],\"dashboard.name\":[".
				"\"update\",\"Updated dashboard name\",\"Audit dashboard\"],\"dashboard.display_period\":[\"update".
				"\",\"60\",\"30\"],\"dashboard.auto_start\":[\"update\",\"0\",\"1\"],\"dashboard.pages[1934].widgets".
				"[5310].type\":[\"add\",\"clock\"],\"dashboard.pages[1934].widgets[5310].width\":[\"add\",\"4\"],".
				"\"dashboard.pages[1934].widgets[5310].height\":[\"add\",\"3\"],\"dashboard.pages[1934].widgets".
				"[5310].widgetid\":[\"add\",\"5310\"],\"dashboard.pages[1934].dashboard_pageid\":[\"add\",\"1934\"],".
				"\"dashboard.pages[1935].display_period\":[\"add\",\"60\"],\"dashboard.pages[1935].dashboard_pageid".
				"\":[\"add\",\"1935\"],\"dashboard.userGroups[2]\":[\"update\"],\"dashboard.userGroups[2].permission".
				"\":[\"update\",\"3\",\"2\"]}";

		$this->call('dashboard.update', [
			[
				'dashboardid' => self::$resourceid,
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

		$this->sendGetRequest('details', 1, $updated);
	}

	public function testAuditlogIconMap_Delete() {
		$this->call('dashboard.delete', [self::$resourceid]);
		$this->sendGetRequest('resourcename', 2, 'Updated dashboard name');
	}

	private function sendGetRequest($output, $action, $result) {
		$get = $this->call('auditlog.get', [
			'output' => [$output],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'filter' => [
				'resourceid' => self::$resourceid,
				'action' => $action
			]
		]);

		$this->assertEquals($result, $get['result'][0][$output]);
	}
}
