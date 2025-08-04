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

require_once __DIR__.'/../common/testSystemInformation.php';

/**
 * @backup ha_node, config
 *
 * @backupConfig
 */
class testPageReportsSystemInformation extends testSystemInformation {

	public function testPageReportsSystemInformation_checkDisabledHA() {
		$this->page->login()->open('zabbix.php?action=report.status')->waitUntilReady();
		// Remove zabbix version due to unstable screenshot which depends on column width with different version length.
		CElementQuery::getDriver()->executeScript("arguments[0].textContent = '';",
				[$this->query('xpath://table[@class="list-table sticky-header"]/tbody/tr[3]/td[1]')->one()]
		);
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
