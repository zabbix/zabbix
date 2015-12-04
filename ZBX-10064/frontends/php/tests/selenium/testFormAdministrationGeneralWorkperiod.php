<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class testFormAdministrationGeneralWorkperiod extends CWebTest {

	public static function WorkingTime() {
		return DBdata('SELECT work_period FROM config ORDER BY configid');
	}
	/**
	* @dataProvider WorkingTime
	*/
	public function testFormAdministrationGeneralWorkperiod_CheckLayout($WorkingTime) {

		$this->zbxTestLogin('adm.workingtime.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Working time');
		$this->checkTitle('Configuration of working time');
		$this->zbxTestTextPresent(array('CONFIGURATION OF WORKING TIME', 'Working time', 'Working time'));
		$this->assertElementPresent('work_period');
		$this->assertAttribute("//input[@id='work_period']/@maxlength", '255');
		$this->assertAttribute("//input[@id='work_period']/@size", '50');
		$this->assertAttribute("//input[@id='work_period']/@value", $WorkingTime['work_period']);

	}

	public function testFormAdministrationGeneralWorkperiod_SavingWorkperiod() {

		$this->zbxTestLogin('adm.workingtime.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Working time');
		$this->checkTitle('Configuration of working time');
		$this->zbxTestTextPresent(array('CONFIGURATION OF WORKING TIME', 'Working time'));

		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,alert_usrgrpid,'.
				'event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,'.
				'ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,'.
				'ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,'.
				'max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,'.
				'severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,'.
				'severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,'.
				'blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,'.
				'problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging'.
				' FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->input_type('work_period', '1-7,09:00-20:00');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');

		$result = DBselect('SELECT work_period FROM config');
		if ($row = DBfetch($result)) {
			$this->assertEquals('1-7,09:00-20:00', $row['work_period'], 'Incorrect value in the DB field "work_period"');
		};

		$newHash=DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some other DB fields also changed, but shouldn't.");

		// checking also for the following error: ERROR: Configuration was not updated | Incorrect working time: "1-8,09:00-25:00".
		$this->zbxTestDropdownSelectWait('configDropDown', 'Working time');
		$this->checkTitle('Configuration of working time');
		$this->zbxTestTextPresent('CONFIGURATION OF WORKING TIME');
		$this->zbxTestTextPresent('Working time');
		$this->input_type('work_period', '1-8,09:00-25:00');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Cannot update configuration', 'Incorrect working time.'));

		// trying to save empty work period
		$this->zbxTestDropdownSelectWait('configDropDown', 'Working time');
		$this->checkTitle('Configuration of working time');
		$this->zbxTestTextPresent(array('CONFIGURATION OF WORKING TIME', 'Working time'));
		$this->input_type('work_period', '');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Cannot update configuration', 'Incorrect working time.'));
	}
}
