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
 * @backup ha_node, profiles
 *
 * @backupConfig
 *
 * @onBefore prepareUsersData
 */
class testPageReportsSystemInformation extends testSystemInformation {

	/**
	 * Function checks which information Super admin users see on system information page.
	 * Other roles are not checked since they don't have permissions to view this page.
	 * Note: in this case data is checked without running server.
	 */
	public function testPageReportsSystemInformation_checkDataByRoleWithoutRunningServer() {
		$data = [
			'available_fields' => [
				[
					'Parameter' => 'Zabbix server is running',
					'Value' => 'No',
					'Details' => 'localhost:10051'
				],
				[
					'Parameter' => 'Zabbix server version',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Zabbix frontend version',
					'Value' => ZABBIX_VERSION,
					'Details' => ''
				],
				[
					'Parameter' => 'Number of hosts (enabled/disabled)',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of templates',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of items (enabled/disabled/not supported)',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of triggers (enabled/disabled [problem/ok])',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of users (online)',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Required server performance, new values per second',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'High availability cluster',
					'Value' => 'Disabled',
					'Details' => ''
				]
			]
		];
		$this->assertAvailableDataByUserRole($data);
	}

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
	 * Function checks which information Super admin users see on system information page.
	 * Other roles are not checked since they don't have permissions to view this page.
	 * Note: in this case data is checked with running server.
	 *
	 * @depends testPageReportsSystemInformation_checkEnabledHA
	 */
	public function testPageReportsSystemInformation_checkDataByRoleWithRunningServer() {
		global $DB;

		$data = [
			'available_fields' => [
				[
					'Parameter' => 'Zabbix server is running',
					'Value' => 'Yes',
					'Details' => $DB['SERVER'].':0'
				],
				[
					'Parameter' => 'Zabbix server version',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Zabbix frontend version',
					'Value' => ZABBIX_VERSION,
					'Details' => ''
				],
				[
					'Parameter' => 'Number of hosts (enabled/disabled)',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of templates',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of items (enabled/disabled/not supported)',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of triggers (enabled/disabled [problem/ok])',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Number of users (online)',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'Required server performance, new values per second',
					'Value' => '',
					'Details' => ''
				],
				[
					'Parameter' => 'High availability cluster',
					'Value' => 'Enabled',
					'Details' => 'Fail-over delay: 1 minute'
				]
			]
		];
		$this->assertAvailableDataByUserRole($data);
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
