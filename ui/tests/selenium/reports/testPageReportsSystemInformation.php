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

require_once dirname(__FILE__).'/../common/testSystemInformation.php';

/**
 * @backup ha_node, config
 *
 * @backupConfig
 */
class testPageReportsSystemInformation extends testSystemInformation {

	public function testPageReportsSystemInformation_checkDisabledHA() {
		$this->page->login()->open('zabbix.php?action=report.status')->waitUntilReady();
		$this->assertScreenshotExcept(null, $this->query('xpath://footer')->one(), 'report_without_ha');
	}

	/**
	 * @onBefore prepareHANodeData
	 */
	public function testPageReportsSystemInformation_checkEnabledHA() {
		$this->assertEnabledHACluster();
		$this->assertScreenshotExcept(null, self::$skip_fields, 'report_with_ha');
	}

	/**
	 * Function checks that zabbix server status is updated after failover delay passes and frontend config is re-validated.
	 *
	 * @depends testPageReportsSystemInformation_checkEnabledHA
	 *
	 * @onBefore changeFailoverDelay
	 */
	public function testPageReportsSystemInformation_CheckServerStatus() {
		$this->assertServerStatusAfterFailover();
	}
}
