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

/**
 * @backup config
 */
class testFormAdministrationGeneralOtherParams extends CWebTest {

	public static function allValues() {

		return DBdata('SELECT refresh_unsupported, snmptrap_logging FROM config ORDER BY configid');
	}

	public static function allGroups() {

		return DBdata('SELECT name FROM groups ORDER BY groupid');
	}

	public static function AlertUsrgrpid() {

		return DBdata('SELECT * FROM usrgrp ORDER BY usrgrpid');
	}

	/**
	* @dataProvider AllValues
	*/
	public function testFormAdministrationGeneralOtherParams_CheckLayout($allValues) {

		$this->zbxTestLogin('adm.other.php');
		$this->zbxTestDropdownAssertSelected('configDropDown', 'Other');
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');
		$this->zbxTestAssertElementValue('refresh_unsupported', $allValues['refresh_unsupported']);

		// checkbox "snmptrap_logging"
		if ($allValues['snmptrap_logging']) {
			$this->assertTrue($this->zbxTestCheckboxSelected('snmptrap_logging'));
		}
		if ($allValues['snmptrap_logging']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('snmptrap_logging'));

			$this->zbxTestAssertElementPresentId('refresh_unsupported');
			$this->zbxTestAssertElementPresentId('snmptrap_logging');
			$this->zbxTestAssertElementPresentId('default_inventory_mode');

			// ckecking presence of drop-down elements
			$this->zbxTestAssertElementPresentId('discovery_groupid');
			$this->zbxTestAssertElementPresentId('alert_usrgrpid');
		}
	}

	// checking possible values in the drop-down "Group for discovered hosts"
	public function testFormAdministrationGeneralOtherParams_CheckHostGroupsLayout() {

		$this->zbxTestLogin('adm.other.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Other');
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');

		$sql = 'SELECT groupid FROM groups';
		$hgroups = DBfetchArray(DBselect($sql));
		foreach ($hgroups as $group) {
			$this->zbxTestAssertElementPresentXpath("//select[@id='discovery_groupid']/option[@value='".$group['groupid']."']");
		}
	}

	// checking possible values in the drop-down "User group for database down message"
	public function testFormAdministrationGeneralOtherParams_CheckUserGroupLayout() {

		$this->zbxTestLogin('adm.other.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Other');
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');

		$sql = 'SELECT usrgrpid FROM usrgrp';
		$usrgrp = DBfetchArray(DBselect($sql));
		foreach ($usrgrp as $usrgroup) {
			$this->zbxTestAssertElementPresentXpath("//select[@id='alert_usrgrpid']/option[@value='".$usrgroup['usrgrpid']."']");
		}

		$this->zbxTestDropdownHasOptions('alert_usrgrpid', ['None']);

	}

	public function testFormAdministrationGeneralOtherParams_OtherParams() {
		$this->zbxTestLogin('adm.other.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Other');
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');

		$this->zbxTestInputType('refresh_unsupported', '700');
		$this->zbxTestDropdownSelect('discovery_groupid', 'Linux servers');
		$this->zbxTestDropdownSelect('alert_usrgrpid', 'Zabbix administrators');
		$this->zbxTestCheckboxSelect('snmptrap_logging');  // 1 - yes, 0 - no
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Configuration updated');

		$sql = "SELECT refresh_unsupported FROM config WHERE refresh_unsupported='700'";
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT snmptrap_logging FROM config WHERE snmptrap_logging=1';
		$this->assertEquals(1, DBcount($sql));

		$this->zbxTestDropdownSelectWait('configDropDown', 'Other');
		$this->zbxTestCheckTitle('Other configuration parameters');

		// trying to enter max possible value
		$this->zbxTestInputTypeOverwrite('refresh_unsupported', '86400');
		$this->zbxTestDropdownSelect('discovery_groupid', 'Linux servers');
		$this->zbxTestDropdownSelect('alert_usrgrpid', 'Enabled debug mode');
		$this->zbxTestCheckboxSelect('snmptrap_logging', false);
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Configuration updated');

		$sql = "SELECT refresh_unsupported FROM config WHERE refresh_unsupported='86400'";
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT snmptrap_logging FROM config WHERE snmptrap_logging=0';
		$this->assertEquals(1, DBcount($sql));

		// trying to enter value > max_value
		$this->zbxTestCheckTitle('Other configuration parameters');
		$this->zbxTestCheckHeader('Other configuration parameters');
		$this->zbxTestInputTypeOverwrite('refresh_unsupported', '86401');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot update configuration');
		$this->zbxTestTextPresent('Invalid refresh of unsupported items: must be between "0" and "86400"');
	}
}
