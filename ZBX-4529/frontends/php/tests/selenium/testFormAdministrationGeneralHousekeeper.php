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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testFormAdministrationGeneralHousekeeper extends CWebTest {

	public static function allValues() {
		return DBdata('SELECT alert_history,event_history FROM config ORDER BY configid');
	}

	/**
	* @dataProvider allValues
	*/
	public function testFormAdministrationGeneralHousekeeper_CheckLayout($allValues) {
		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent(array('Housekeeper', 'Do not keep actions older than (in days)', 'Do not keep events older than (in days)'));
		$this->assertElementPresent('configDropDown');
		$this->assertElementPresent('alert_history');
		$this->assertElementPresent('event_history');
		$this->assertElementPresent('save');
		$this->assertAttribute("//input[@id='alert_history']/@maxlength", '5');
		$this->assertAttribute("//input[@id='alert_history']/@size", '5');
		$this->assertAttribute("//input[@id='event_history']/@maxlength", '5');
		$this->assertAttribute("//input[@id='event_history']/@size", '5');
		$this->assertAttribute("//input[@id='alert_history']/@value", $allValues['alert_history']);
		$this->assertAttribute("//input[@id='event_history']/@value", $allValues['event_history']);
	}

	public function testFormAdministrationGeneralHousekeeper_AlertHistory() {
		// 0-65535

		$this->zbxTestLogin('adm.housekeeper.php');
		$this->input_type('alert_history', '0');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep actions older than (in days)'));

		$sqlHash = 'SELECT configid,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$sql = 'SELECT alert_history FROM config WHERE alert_history=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "alert_history"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent('Housekeeper');
		$this->input_type('alert_history', '65535');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep actions older than (in days)'));

		$sql = 'SELECT alert_history FROM config WHERE alert_history=65535';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "alert_history"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent('Housekeeper');
		$this->input_type('alert_history', '-1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Do not keep actions older than (in days)": must be between 0 and 65535.', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep actions older than (in days)'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent('Housekeeper');
		$this->input_type('alert_history', '65536');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Do not keep actions older than (in days)": must be between 0 and 65535.', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper'));

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralHousekeeper_EventHistory() {
		// 0-65535

		$this->zbxTestLogin('adm.housekeeper.php');
		$this->input_type('event_history', '0');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep events older than (in days)'));

		$sqlHash = 'select configid,alert_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging from config order by configid';
		$oldHash = DBhash($sqlHash);

		$sql = 'SELECT event_history FROM config WHERE event_history=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_history"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent('Housekeeper');
		$this->input_type('event_history', '65535');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep events older than (in days)'));

		$sql = 'SELECT event_history FROM config WHERE event_history=65535';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_history"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent('Housekeeper');
		$this->input_type('event_history', '-1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Do not keep events older than (in days)": must be between 0 and 65535.', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep events older than (in days)'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent('Housekeeper');
		$this->input_type('event_history', '65536');
		$this->zbxTestClickWait('save');
		//$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "event_history".', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep events older than (in days)'));
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Do not keep events older than (in days)": must be between 0 and 65535.', 'CONFIGURATION OF HOUSEKEEPER', 'Housekeeper', 'Do not keep events older than (in days)'));

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");
	}
}
