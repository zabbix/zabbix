<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

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

		$this->login('adm.other.php');
		$this->dropdown_select_wait('configDropDown', 'Other');
		$this->checkTitle('Other configuration parameters');
		$this->ok(array('OTHER CONFIGURATION PARAMETERS', 'Other parameters'));
		$this->assertAttribute("//input[@id='refresh_unsupported']/@value", $allValues['refresh_unsupported']);

		// checkbox "snmptrap_logging"
		if ($allValues['snmptrap_logging']) {
			$this->assertElementPresent("//input[@id='snmptrap_logging' and @checked]");
		}
		if ($allValues['snmptrap_logging']==0) {
			$this->assertElementPresent("//input[@id='snmptrap_logging' and not (@checked)]");

			$this->assertElementPresent('refresh_unsupported');
			$this->assertElementPresent('snmptrap_logging');

			// ckecking presence of drop-down elements
			$this->assertElementPresent('discovery_groupid');
			$this->assertElementPresent('alert_usrgrpid');
		}
	}

	// checking possible values in the drop-down "Group for discovered hosts"
	public function testFormAdministrationGeneralOtherParams_CheckHostGroupsLayout() {

		$this->login('adm.other.php');
		$this->dropdown_select_wait('configDropDown', 'Other');
		$this->checkTitle('Other configuration parameters');
		$this->ok(array('OTHER CONFIGURATION PARAMETERS', 'Other parameters'));

		$sql = 'SELECT groupid FROM groups';
		$hgroups = DBfetchArray(DBselect($sql));
		foreach ($hgroups as $group) {
			$this->assertElementPresent("//select[@id='discovery_groupid']/option[@value='".$group['groupid']."']");
		}
	}

	// checking possible values in the drop-down "User group for database down message"
	public function testFormAdministrationGeneralOtherParams_CheckUserGroupLayout() {

		$this->login('adm.other.php');
		$this->dropdown_select_wait('configDropDown', 'Other');
		$this->checkTitle('Other configuration parameters');
		$this->ok(array('OTHER CONFIGURATION PARAMETERS', 'Other parameters'));

		$sql = 'SELECT usrgrpid FROM usrgrp';
		$usrgrp = DBfetchArray(DBselect($sql));
		foreach ($usrgrp as $usrgroup) {
			$this->assertElementPresent("//select[@id='alert_usrgrpid']/option[@value='".$usrgroup['usrgrpid']."']");
		}

		$this->assertElementPresent("//select[@id='alert_usrgrpid']/option[text()='None']");

	}

	/**
	* @dataProvider RefreshUnsupported
	*/
	public function testFormAdministrationGeneralOtherParams_OtherParams() {

		$this->login('adm.other.php');
		$this->dropdown_select_wait('configDropDown', 'Other');
		$this->checkTitle('Other configuration parameters');
		$this->ok('OTHER CONFIGURATION PARAMETERS');
		$this->ok('Other parameters');

		$this->input_type('refresh_unsupported', '700');
		$this->dropdown_select('discovery_groupid', 'Linux servers');
		$this->dropdown_select('alert_usrgrpid', 'Zabbix administrators');
		$this->checkbox_select('snmptrap_logging');  // 1 - yes, 0 - no
		$this->button_click('save');
		$this->wait();
		$this->ok('Configuration updated');

		$sql = 'SELECT refresh_unsupported FROM config WHERE refresh_unsupported=700';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "refresh_unsupported"');
		$sql = 'SELECT snmptrap_logging FROM config WHERE snmptrap_logging=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "snmptrap_logging"');

		$this->dropdown_select_wait('configDropDown', 'Other');
		$this->checkTitle('Other configuration parameters');
		$this->ok('OTHER CONFIGURATION PARAMETERS');
		$this->ok('Other parameters');

		// trying to enter max possible value
		$this->input_type('refresh_unsupported', '65535');
		$this->dropdown_select('discovery_groupid', 'Linux servers');
		$this->dropdown_select('alert_usrgrpid', 'Enabled debug mode');
		$this->checkbox_unselect('snmptrap_logging');
		$this->button_click('save');
		$this->wait();
		$this->ok('Configuration updated');

		$sql = 'SELECT refresh_unsupported FROM config WHERE refresh_unsupported=65535';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "refresh_unsupported"');
		$sql = 'SELECT snmptrap_logging FROM config WHERE snmptrap_logging=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "snmptrap_logging"');

		// trying to enter value > max_value
		$this->checkTitle('Other configuration parameters');
		$this->ok('OTHER CONFIGURATION PARAMETERS');
		$this->ok('Other parameters');
		$this->input_type('refresh_unsupported', '65536');
		$this->button_click('save');
		$this->wait();
		$this->ok(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Refresh unsupported items (in sec)": must be between 0 and 65535.'));
		// $this->ok('Warning. Incorrect value for field "Refresh unsupported items (in sec)": must be between 0 and 65535.');
	}

}
?>
