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
 * @backup  dashboard, ids
 */
class testAuditlogDashboard extends testAuditlogCommon {
	public function testAuditlogDashboard_Create() {
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
						'usrgrpid' => 7,
						'permission' => 2
					]
				],
				'users' => [
					[
						'userid' => 1,
						'permission' => 2
					]
				]
			]
		]);

		$resourceid = $create['result']['dashboardids'][0];

		$pageid = CDBHelper::getAll('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid='.$resourceid);
		$widgetid = CDBHelper::getAll('SELECT widgetid FROM widget WHERE dashboard_pageid='.$pageid[0]['dashboard_pageid']);
		$fieldid = CDBHelper::getAll('SELECT widget_fieldid FROM widget_field WHERE widgetid ='
				.$widgetid[0]['widgetid'].' ORDER BY widget_fieldid ASC');

		$created = "{\"dashboard.name\":[\"add\",\"Audit dashboard\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid']."]\":[\"add\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid']."].type\":[\"add\",\"problems\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid']."].width\":[\"add\",\"12\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid']."].height\":[\"add\",\"5\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[0]['widget_fieldid']."]\":[\"add\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[0]['widget_fieldid']."].type\":[\"add\",\"1\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[0]['widget_fieldid']."].name\":[\"add\",\"tags.tag.0\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[0]['widget_fieldid']."].value\":[\"add\",\"service\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[0]['widget_fieldid']."].widget_fieldid\":[\"add\",\"".$fieldid[0]['widget_fieldid']."\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[1]['widget_fieldid']."]\":[\"add\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[1]['widget_fieldid']."].name\":[\"add\",\"tags.operator.0\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[1]['widget_fieldid']."].value\":[\"add\",\"1\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[1]['widget_fieldid']."].widget_fieldid\":[\"add\",\"".$fieldid[1]['widget_fieldid']."\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[2]['widget_fieldid']."]\":[\"add\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[2]['widget_fieldid']."].type\":[\"add\",\"1\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[2]['widget_fieldid']."].name\":[\"add\",\"tags.value.0\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[2]['widget_fieldid']."].value\":[\"add\",\"zabbix_server\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].fields[".$fieldid[2]['widget_fieldid']."].widget_fieldid\":[\"add\",\"".$fieldid[2]['widget_fieldid']."\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].widgetid\":[\"add\",\"".$widgetid[0]['widgetid']."\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."]\":[\"add\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].dashboard_pageid\":[\"add\",\"".$pageid[0]['dashboard_pageid']."\"],".
			"\"dashboard.userGroups[2]\":[\"add\"],".
			"\"dashboard.userGroups[2].usrgrpid\":[\"add\",\"7\"],".
			"\"dashboard.userGroups[2].dashboard_usrgrpid\":[\"add\",\"2\"],".
			"\"dashboard.users[1]\":[\"add\"],".
			"\"dashboard.users[1].userid\":[\"add\",\"1\"],".
			"\"dashboard.users[1].dashboard_userid\":[\"add\",\"1\"],".
			"\"dashboard.userid\":[\"add\",\"1\"],".
			"\"dashboard.dashboardid\":[\"add\",\"".$resourceid."\"]}";

		$this->sendGetRequest('details', 0, $created, $resourceid);
	}

	public function testAuditlogDashboard_Update() {
		$this->call('dashboard.update', [
			[
				'dashboardid' => 1,
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
					]
				],
				'userGroups' => [
					[
						'usrgrpid' => 7,
						'permission' => 3
					]
				]
			]
		]);

		$pageid = CDBHelper::getAll('SELECT dashboard_pageid FROM dashboard_page WHERE dashboardid=1');
		$widgetid = CDBHelper::getAll('SELECT widgetid FROM widget WHERE dashboard_pageid='.$pageid[0]['dashboard_pageid']);

		$updated = "{\"dashboard.pages[1]\":[\"delete\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid']."]\":[\"add\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."]\":[\"add\"],".
			"\"dashboard.userGroups[3]\":[\"add\"],".
			"\"dashboard.name\":[\"update\",\"Updated dashboard name\",\"Global view\"],".
			"\"dashboard.display_period\":[\"update\",\"60\",\"30\"],".
			"\"dashboard.auto_start\":[\"update\",\"0\",\"1\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].type\":[\"add\",\"clock\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].width\":[\"add\",\"4\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].height\":[\"add\",\"3\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].widgets[".$widgetid[0]['widgetid'].
					"].widgetid\":[\"add\",\"".$widgetid[0]['widgetid']."\"],".
			"\"dashboard.pages[".$pageid[0]['dashboard_pageid']."].dashboard_pageid\":[\"add\",\"".
					$pageid[0]['dashboard_pageid']."\"],".
			"\"dashboard.userGroups[3].usrgrpid\":[\"add\",\"7\"],".
			"\"dashboard.userGroups[3].permission\":[\"add\",\"3\"],".
			"\"dashboard.userGroups[3].dashboard_usrgrpid\":[\"add\",\"3\"]}";

		$this->sendGetRequest('details', 1, $updated, 1);
	}

	public function testAuditlogDashboard_Delete() {
		$this->call('dashboard.delete', [1]);
		$this->sendGetRequest('resourcename', 2, 'Updated dashboard name', 1);
	}
}
