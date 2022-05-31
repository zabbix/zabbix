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
 * @backup config, ids
 */
class testAuditSettings extends testPageReportsAuditValues {

	/**
	 * Id of settings.
	 *
	 * @var integer
	 */
	protected static $ids = 1;

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

	public $resource_name = 'Settings';

	/**
	 * Check audit of updated Settings.
	 */
	public function testAuditSettings_Update() {
		CDataHelper::call('settings.update', [
			[
//				'default_lang' => 'en_GB',
				'default_theme' => 'dark-theme'
//				'search_limit' => '500',
//				'max_overview_table_size' => '60',
//				'max_in_table' => '60',
//				'server_check_interval' => '0',
//				'history_period' => '48h',
//				'period_default' => '2h',
//				'max_period' => '1y',
//				'severity_color_0' => '97AAB2',
//				'severity_color_1' => '7498FF',
//				'severity_color_2' => 'FFC858',
//				'severity_color_3' => 'FFA058',
//				'severity_color_4' => 'E97658',
//				'severity_color_5' => 'E45958',
//				'severity_name_0' => 'Updated Not slassified',
//				'severity_name_1' => 'Updated Information',
//				'severity_name_2' => 'Updated Warning',
//				'severity_name_3' => 'Updated Average',
//				'severity_name_4' => 'Updated High',
//				'severity_name_5' => 'Updated Disaster',
//				'custom_color' => '1',
//				'ok_period' => '6m',
//				'blink_period' => '3m',
//				'problem_unack_color' => 'CC0001',
//				'problem_ack_color' => 'CC0001',
//				'ok_unack_color' => '009901',
//				'ok_ack_color' => '009901',
//				'problem_unack_style' => '0',
//				'problem_ack_style' => '0',
//				'ok_unack_style' => '0',
//				'ok_ack_style' => '0',
//				'default_inventory_mode' => '1',
//				'snmptrap_logging' => '0',
//				'login_attempts' => '4',
//				'login_block' => '35s',
//				'validate_uri_schemes' => '0',
//				'connect_timeout' => '4s',
//				'socket_timeout' => '4s',
//				'media_type_test_timeout' => '60s',
//				'item_test_timeout' => '50s',
//				'script_timeout' => '50s',
//				'report_test_timeout' => '50s'
			]
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}
}
