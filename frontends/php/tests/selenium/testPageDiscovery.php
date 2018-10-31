<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageDiscovery extends CWebTest {
	public function testPageDiscovery_CheckLayout() {
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');

		$this->zbxTestCheckHeader('Discovery rules');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(['Name', 'IP range', 'Interval', 'Checks', 'Status']);
		$this->zbxTestTextPresent(['Enable', 'Disable', 'Delete']);
	}

	// returns all discovery rules
	public static function allRules() {
		return DBdata('SELECT druleid,name FROM drules');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_SimpleUpdate($drule) {
		$sqlDRules = 'SELECT * FROM drules WHERE druleid='.$drule['druleid'];
		$sqlDChecks = 'SELECT * FROM dchecks WHERE druleid='.$drule['druleid'].' ORDER BY dcheckid';
		$oldHashDRules = DBhash($sqlDRules);
		$oldHashDChecks = DBhash($sqlDChecks);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestClickLinkText($drule['name']);
		$this->zbxTestClickWait('update');

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');
		$this->zbxTestTextPresent($drule['name']);

		$this->assertEquals($oldHashDRules, DBhash($sqlDRules));
		$this->assertEquals($oldHashDChecks, DBhash($sqlDChecks));
	}

	/**
	 * @dataProvider allRules
	 * @backup drules
	 */
	public function testPageDiscovery_MassDelete($drule) {
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('g_druleid_'.$drule['druleid']);
		$this->zbxTestClickButton('drule.massdelete');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules deleted');

		$this->assertEquals(0, DBcount('SELECT * FROM drules WHERE druleid='.$drule['druleid']));
		$this->assertEquals(0, DBcount('SELECT * FROM dchecks WHERE druleid='.$drule['druleid']));
	}

	public function testPageDiscovery_MassDisableAll() {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_ACTIVE);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->zbxTestClickButton('drule.massdisable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules disabled');

		$this->assertEquals(0, DBcount('SELECT * FROM drules WHERE status='.DRULE_STATUS_ACTIVE));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassDisable($drule) {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_ACTIVE.' WHERE druleid='.$drule['druleid']);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('g_druleid_'.$drule['druleid']);
		$this->zbxTestClickButton('drule.massdisable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule disabled');

		$this->assertEquals(1, DBcount(
			'SELECT *'.
			' FROM drules'.
			' WHERE druleid='.$drule['druleid'].
				' AND status='.DRULE_STATUS_DISABLED
		));
	}

	public function testPageDiscovery_MassEnableAll() {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_DISABLED);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->zbxTestClickButton('drule.massenable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules enabled');

		$this->assertEquals(0, DBcount('SELECT * FROM drules WHERE status='.DRULE_STATUS_DISABLED));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassEnable($drule) {
		DBexecute('UPDATE drules SET status='.DRULE_STATUS_DISABLED.' WHERE druleid='.$drule['druleid']);

		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('g_druleid_'.$drule['druleid']);
		$this->zbxTestClickButton('drule.massenable');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule enabled');

		$this->assertEquals(1, DBcount(
			'SELECT *'.
			' FROM drules'.
			' WHERE druleid='.$drule['druleid'].
				' AND status='.DRULE_STATUS_ACTIVE
		));
	}
}
