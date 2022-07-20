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

	public $updated = "settings.blink_period: 2m => 3m".
			"\nsettings.connect_timeout: 3s => 4s".
			"\nsettings.custom_color: 0 => 1".
			"\nsettings.default_inventory_mode: -1 => 1".
			"\nsettings.default_lang: en_US => en_GB".
			"\nsettings.default_theme: blue-theme => dark-theme".
			"\nsettings.history_period: 24h => 48h".
			"\nsettings.item_test_timeout: 60s => 50s".
			"\nsettings.login_attempts: 5 => 4".
			"\nsettings.login_block: 30s => 35s".
			"\nsettings.max_in_table: 50 => 60".
			"\nsettings.max_overview_table_size: 50 => 60".
			"\nsettings.max_period: 2y => 1y".
			"\nsettings.media_type_test_timeout: 65s => 60s".
			"\nsettings.ok_ack_color: 009900 => 009901".
			"\nsettings.ok_ack_style: 1 => 0".
			"\nsettings.ok_period: 5m => 6m".
			"\nsettings.ok_unack_color: 009900 => 009901".
			"\nsettings.ok_unack_style: 1 => 0".
			"\nsettings.period_default: 1h => 2h".
			"\nsettings.problem_ack_color: CC0000 => CC0001".
			"\nsettings.problem_ack_style: 1 => 0".
			"\nsettings.problem_unack_color: CC0000 => CC0001".
			"\nsettings.problem_unack_style: 1 => 0".
			"\nsettings.report_test_timeout: 60s => 50s".
			"\nsettings.script_timeout: 60s => 50s".
			"\nsettings.search_limit: 1000 => 500".
			"\nsettings.server_check_interval: 10 => 0".
			"\nsettings.severity_color_0: 97AAB3 => 97AAB2".
			"\nsettings.severity_color_1: 7499FF => 7498FF".
			"\nsettings.severity_color_2: FFC859 => FFC858".
			"\nsettings.severity_color_3: FFA059 => FFA058".
			"\nsettings.severity_color_4: E97659 => E97658".
			"\nsettings.severity_color_5: E45959 => E45958".
			"\nsettings.severity_name_0: Not classified => Updated Not slassified".
			"\nsettings.severity_name_1: Information => Updated Information".
			"\nsettings.severity_name_2: Warning => Updated Warning".
			"\nsettings.severity_name_3: Average => Updated Average".
			"\nsettings.severity_name_4: High => Updated High".
			"\nsettings.severity_name_5: Disaster => Updated Disaster".
			"\nsettings.snmptrap_logging: 1 => 0".
			"\nsettings.socket_timeout: 3s => 4s".
			"\nsettings.validate_uri_schemes: 1 => 0";

	public $resource_name = 'Settings';

	/**
	 * Check audit of updated Settings.
	 */
	public function testAuditSettings_Update() {
		CDataHelper::call('settings.update', [
			'default_lang' => 'en_GB',
			'default_theme' => 'dark-theme',
			'search_limit' => '500',
			'max_overview_table_size' => '60',
			'max_in_table' => '60',
			'server_check_interval' => '0',
			'history_period' => '48h',
			'period_default' => '2h',
			'max_period' => '1y',
			'severity_color_0' => '97AAB2',
			'severity_color_1' => '7498FF',
			'severity_color_2' => 'FFC858',
			'severity_color_3' => 'FFA058',
			'severity_color_4' => 'E97658',
			'severity_color_5' => 'E45958',
			'severity_name_0' => 'Updated Not slassified',
			'severity_name_1' => 'Updated Information',
			'severity_name_2' => 'Updated Warning',
			'severity_name_3' => 'Updated Average',
			'severity_name_4' => 'Updated High',
			'severity_name_5' => 'Updated Disaster',
			'custom_color' => '1',
			'ok_period' => '6m',
			'blink_period' => '3m',
			'problem_unack_color' => 'CC0001',
			'problem_ack_color' => 'CC0001',
			'ok_unack_color' => '009901',
			'ok_ack_color' => '009901',
			'problem_unack_style' => '0',
			'problem_ack_style' => '0',
			'ok_unack_style' => '0',
			'ok_ack_style' => '0',
			'default_inventory_mode' => '1',
			'snmptrap_logging' => '0',
			'login_attempts' => '4',
			'login_block' => '35s',
			'validate_uri_schemes' => '0',
			'connect_timeout' => '4s',
			'socket_timeout' => '4s',
			'media_type_test_timeout' => '60s',
			'item_test_timeout' => '50s',
			'script_timeout' => '50s',
			'report_test_timeout' => '50s'
		]);

		$this->checkAuditValues(self::$ids, 'Update');
	}
}
