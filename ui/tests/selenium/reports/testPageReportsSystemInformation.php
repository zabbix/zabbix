<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

	public static function getUserData() {
		return [
			[
				[
					'username' => 'Admin'
				]
			],
			[
				[
					'username' => 'admin-zabbix'
				]
			]
		];
	}

	/**
	 * @dataProvider getUserData
	 */
	public function testPageReportsSystemInformation_checkDisabledHA($data) {
		$this->page->userLogin($data['username'], 'zabbix');
		$this->page->open('zabbix.php?action=report.status')->waitUntilReady();

		// Check field that is skipped in screenshot assertion.
		$check_data = [
			[
				'Parameter' => 'Zabbix frontend version',
				'Value' => ZABBIX_VERSION,
				'Details' => ''
			]
		];
		$this->assertTableHasData($check_data);

		// Check that system information export button is clickable.
		$this->assertTrue($this->query('xpath://a[text() = "Export"]')->one()->isClickable());

		// Remove zabbix version due to unstable screenshot which depends on column width with different version length.
		CElementQuery::getDriver()->executeScript("arguments[0].textContent = '';",
				[$this->query('xpath://table[@class="list-table sticky-header rounded-surface"]/tbody/tr[3]/td[1]')->one()]
		);
		$this->assertScreenshotExcept(null, $this->query('xpath://footer')->one(), 'report_without_ha_'.$data['username']);
	}

	/**
	 * @onBeforeOnce prepareHANodeData
	 *
	 * @dataProvider getUserData
	 */
	public function testPageReportsSystemInformation_checkEnabledHA($data) {
		$this->assertEnabledHACluster(null, $data['username']);

		// Check that system information export button is clickable.
		$this->assertTrue($this->query('xpath://a[text() = "Export"]')->one()->isClickable());

		$this->assertScreenshotExcept(null, self::$skip_fields, 'report_with_ha_'.$data['username']);
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
