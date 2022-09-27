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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup config
 */
class testAuditlogSettings extends testAuditlogCommon {

	public function testAuditlogSettings_Update() {
		$updated = json_encode([
			'settings.default_lang' => ['update', 'en_GB', 'en_US'],
			'settings.default_theme' => ['update', 'dark-theme', 'blue-theme'],
			'settings.search_limit' => ['update', '500', '1000'],
			'settings.max_overview_table_size' => ['update', '60', '50'],
			'settings.max_in_table' => ['update', '60', '50'],
			'settings.server_check_interval' => ['update', '0', '10'],
			'settings.history_period' => ['update', '48h', '24h'],
			'settings.period_default' => ['update', '2h', '1h'],
			'settings.max_period' => ['update', '1y', '2y'],
			'settings.severity_color_0' => ['update', '97AAB2', '97AAB3'],
			'settings.severity_color_1' => ['update', '7498FF', '7499FF'],
			'settings.severity_color_2' => ['update', 'FFC858', 'FFC859'],
			'settings.severity_color_3' => ['update', 'FFA058', 'FFA059'],
			'settings.severity_color_4' => ['update', 'E97658', 'E97659'],
			'settings.severity_color_5' => ['update', 'E45958', 'E45959'],
			'settings.severity_name_0' => ['update', 'Updated Not classified', 'Not classified'],
			'settings.severity_name_1' => ['update', 'Updated Information', 'Information'],
			'settings.severity_name_2' => ['update', 'Updated Warning', 'Warning'],
			'settings.severity_name_3' => ['update', 'Updated Average', 'Average'],
			'settings.severity_name_4' => ['update', 'Updated High', 'High'],
			'settings.severity_name_5' => ['update', 'Updated Disaster', 'Disaster'],
			'settings.custom_color' => ['update', '1', '0'],
			'settings.ok_period' => ['update', '6m', '5m'],
			'settings.blink_period' => ['update', '3m', '2m'],
			'settings.problem_unack_color' => ['update', 'CC0001', 'CC0000'],
			'settings.problem_ack_color' => ['update', 'CC0001', 'CC0000'],
			'settings.ok_unack_color' => ['update', '009901', '009900'],
			'settings.ok_ack_color' => ['update', '009901', '009900'],
			'settings.problem_unack_style' => ['update', '0', '1'],
			'settings.problem_ack_style' => ['update', '0', '1'],
			'settings.ok_unack_style' => ['update', '0', '1'],
			'settings.ok_ack_style' => ['update', '0', '1'],
			'settings.default_inventory_mode' => ['update', '1', '-1'],
			'settings.snmptrap_logging' => ['update', '0', '1'],
			'settings.login_attempts' => ['update', '4', '5'],
			'settings.login_block' => ['update', '35s', '30s'],
			'settings.validate_uri_schemes' => ['update', '0', '1'],
			'settings.connect_timeout' => ['update', '4s', '3s'],
			'settings.socket_timeout' => ['update', '4s', '3s'],
			'settings.media_type_test_timeout' => ['update', '60s', '65s'],
			'settings.item_test_timeout' => ['update', '50s', '60s'],
			'settings.script_timeout' => ['update', '50s', '60s'],
			'settings.report_test_timeout' => ['update', '50s', '60s']
		]);

		$this->call('settings.update', [
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
			'severity_name_0' => 'Updated Not classified',
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

		$this->getAuditDetails('details', $this->update_actionid, $updated, 1);
	}
}
