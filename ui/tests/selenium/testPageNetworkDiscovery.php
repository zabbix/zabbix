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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageNetworkDiscovery extends CLegacyWebTest {
	public function testPageNetworkDiscovery_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');

		$this->zbxTestCheckHeader('Discovery rules');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(['Name', 'IP range', 'Proxy', 'Interval', 'Checks', 'Status']);
		$this->zbxTestTextPresent(['Enable', 'Disable', 'Delete']);
	}

	// returns all discovery rules
	public static function allRules() {
		return CDBHelper::getDataProvider('SELECT druleid,name FROM drules');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageNetworkDiscovery_SimpleUpdate($drule) {
		$sqlDRules = 'SELECT * FROM drules WHERE druleid='.$drule['druleid'];
		$sqlDChecks = 'SELECT * FROM dchecks WHERE druleid='.$drule['druleid'].' ORDER BY dcheckid';
		$oldHashDRules = CDBHelper::getHash($sqlDRules);
		$oldHashDChecks = CDBHelper::getHash($sqlDChecks);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestClickLinkText($drule['name']);
		$this->zbxTestClickWait('update');

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');
		$this->zbxTestTextPresent($drule['name']);

		$this->assertEquals($oldHashDRules, CDBHelper::getHash($sqlDRules));
		$this->assertEquals($oldHashDChecks, CDBHelper::getHash($sqlDChecks));
	}

	/**
	 * @dataProvider allRules
	 * @backup drules
	 */
	public function testPageNetworkDiscovery_MassDelete($drule) {
		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('druleids_'.$drule['druleid']);
		$this->zbxTestClickButton('discovery.delete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE druleid='.$drule['druleid']));
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM dchecks WHERE druleid='.$drule['druleid']));
		$this->page->logout();
	}

	public function testPageNetworkDiscovery_MassDisableAll() {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_ACTIVE);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->zbxTestClickButton('discovery.disable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules disabled');

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE status='.DRULE_STATUS_ACTIVE));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageNetworkDiscovery_MassDisable($drule) {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_ACTIVE.' WHERE druleid='.$drule['druleid']);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('druleids_'.$drule['druleid']);
		$this->zbxTestClickButton('discovery.disable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule disabled');

		$this->assertEquals(1, CDBHelper::getCount(
			'SELECT *'.
			' FROM drules'.
			' WHERE druleid='.$drule['druleid'].
				' AND status='.DRULE_STATUS_DISABLED
		));
	}

	public function testPageNetworkDiscovery_MassEnableAll() {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_DISABLED);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->zbxTestClickButton('discovery.enable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules enabled');

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE status='.DRULE_STATUS_DISABLED));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageNetworkDiscovery_MassEnable($drule) {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_DISABLED.' WHERE druleid='.$drule['druleid']);

		$this->zbxTestLogin('zabbix.php?action=discovery.list');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('druleids_'.$drule['druleid']);
		$this->zbxTestClickButton('discovery.enable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule enabled');

		$this->assertEquals(1, CDBHelper::getCount(
			'SELECT *'.
			' FROM drules'.
			' WHERE druleid='.$drule['druleid'].
				' AND status='.DRULE_STATUS_ACTIVE
		));
	}
}
