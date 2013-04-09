<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

class testFormAdministrationGeneralGUI extends CWebTest {

	public static function allValues() {
		return DBdata('SELECT default_theme,dropdown_first_entry,dropdown_first_remember,search_limit,max_in_table,event_ack_enable,event_expire,event_show_max FROM config ORDER BY configid');
	}

	/**
	* @dataProvider allValues
	*/
	public function testFormAdministrationGeneralGUI_CheckLayout($allValues) {

		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->zbxTestTextPresent(array('Default theme', 'Dropdown first entry', 'remember selected', 'Search/Filter elements limit', 'Max count of elements to show inside table cell', 'Enable event acknowledges', 'Show events not older than (in days)', 'Max count of events per trigger to show'));

		$this->assertElementPresent('default_theme');
		$this->assertElementPresent("//select[@id='default_theme']/option[text()='Original blue']");
		$this->assertElementPresent("//select[@id='default_theme']/option[text()='Black & Blue']");
		$this->assertElementPresent("//select[@id='default_theme']/option[text()='Dark orange']");

		$this->assertElementPresent('dropdown_first_entry');
		$this->assertElementPresent("//select[@id='dropdown_first_entry']/option[text()='All']");
		$this->assertElementPresent("//select[@id='dropdown_first_entry']/option[text()='None']");

		$this->assertElementPresent('dropdown_first_remember');

		$this->assertElementPresent('search_limit');
		$this->assertAttribute("//input[@id='search_limit']/@maxlength", '6');
		$this->assertAttribute("//input[@id='search_limit']/@size", '6');

		$this->assertElementPresent('max_in_table');
		$this->assertAttribute("//input[@id='max_in_table']/@maxlength", '5');
		$this->assertAttribute("//input[@id='max_in_table']/@size", '5');

		$this->assertElementPresent('event_ack_enable');

		$this->assertElementPresent('event_expire');
		$this->assertAttribute("//input[@id='event_expire']/@maxlength", '255');
		$this->assertAttribute("//input[@id='event_expire']/@size", '5');

		$this->assertElementPresent('event_show_max');
		$this->assertAttribute("//input[@id='event_show_max']/@maxlength", '255');
		$this->assertAttribute("//input[@id='event_show_max']/@size", '5');
		$this->assertElementPresent('save');

		$this->assertAttribute("//select[@id='default_theme']/option[@selected='selected']/@value", $allValues['default_theme']);
		$this->assertAttribute("//select[@id='dropdown_first_entry']/option[@selected='selected']/@value", $allValues['dropdown_first_entry']);
		if ($allValues['dropdown_first_remember']) {
			$this->assertElementPresent("//input[@id='dropdown_first_remember' and @checked]");
		}
		if ($allValues['dropdown_first_remember']==0) {
			$this->assertElementPresent("//input[@id='dropdown_first_remember' and not (@checked)]");
		}
		$this->assertAttribute("//input[@id='search_limit']/@value", $allValues['search_limit']);
		$this->assertAttribute("//input[@id='max_in_table']/@value", $allValues['max_in_table']);

		if ($allValues['event_ack_enable']) {
			$this->assertElementPresent("//input[@id='event_ack_enable' and @checked]");
		}
		if ($allValues['event_ack_enable']==0) {
			$this->assertElementPresent("//input[@id='event_ack_enable' and not (@checked)]");
		}
		$this->assertAttribute("//input[@id='event_expire']/@value", $allValues['event_expire']);
		$this->assertAttribute("//input[@id='event_show_max']/@value", $allValues['event_show_max']);
	}

	public function testFormAdministrationGeneralGUI_ChangeTheme() {

		$this->zbxTestLogin('adm.gui.php');
		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->zbxTestDropdownSelect('default_theme', 'Black & Blue');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Default theme'));
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('darkblue');

		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: "Black and Blue" theme can not be selected as default theme: it does not exist in the DB');

		$this->zbxTestDropdownSelect('default_theme', 'Dark orange');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Default theme'));
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('darkorange');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: "Dark orange" theme can not be selected as default theme: it does not exist in the DB');

		$this->zbxTestDropdownSelect('default_theme', 'Original blue');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('originalblue');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: "Original blue" theme can not be selected as default theme: it does not exist in the DB');

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some other DB fields also changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralGUI_ChangeDropdownFirstEntry() {

		$this->zbxTestLogin('adm.gui.php');
		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->zbxTestDropdownSelect('dropdown_first_entry', 'None');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Dropdown first entry'));
		$sql = 'SELECT dropdown_first_entry FROM config WHERE dropdown_first_entry=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value "None" can not be selected as "dropdown first entry" value');

		$this->zbxTestDropdownSelect('dropdown_first_entry', 'All');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Dropdown first entry'));

		$sql = 'SELECT dropdown_first_entry FROM config WHERE dropdown_first_entry=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value "All" can not be selected as "dropdown first entry" value');

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some other DB fields also changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralGUI_ChangeDropdownFirstRemember() {

		$this->zbxTestLogin('adm.gui.php');
		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->zbxTestCheckboxSelect('dropdown_first_remember');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'remember selected'));

		$sql = 'SELECT dropdown_first_remember FROM config WHERE dropdown_first_remember=0';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "dropdown_first_remember"');

		$this->zbxTestCheckboxUnselect('dropdown_first_remember');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'remember selected'));

		$sql = 'SELECT dropdown_first_remember FROM config WHERE dropdown_first_remember=1';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "dropdown_first_remember"');

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralGUI_ChangeSearchLimit() {
		$this->zbxTestLogin('adm.gui.php');
		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->input_type('search_limit', '1000');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Search/Filter elements limit'));

		$sql = 'SELECT search_limit FROM config WHERE search_limit=1000';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('search_limit', '1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Search/Filter elements limit'));

		$sql = 'SELECT search_limit FROM config WHERE search_limit=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('search_limit', '999999');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Search/Filter elements limit'));

		$sql = 'SELECT search_limit FROM config WHERE search_limit=999999';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		// Check to enter 0 value
		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent('CONFIGURATION OF GUI');
		$this->zbxTestTextPresent('GUI');
		$this->input_type('search_limit', '0');
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Search/Filter elements limit'));
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Search/Filter elements limit": must be between 1 and 999999.'));

		// Check to enter -1 value
		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('search_limit', '-1');
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Search/Filter elements limit'));
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Search/Filter elements limit": must be between 1 and 999999.'));

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralGUI_ChangeMaxInTable() {

		$this->zbxTestLogin('adm.gui.php');
		$this->input_type('max_in_table', '1000');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Max count of elements to show inside table cell'));

		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$sql = 'SELECT max_in_table FROM config WHERE max_in_table=1000';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "max_in_table"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('max_in_table', '1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Max count of elements to show inside table cell'));

		$sql = 'SELECT max_in_table FROM config WHERE max_in_table=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "max_in_table"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('max_in_table', '99999');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Max count of elements to show inside table cell'));

		$sql = 'SELECT max_in_table FROM config WHERE max_in_table=99999';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "max_in_table"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('max_in_table', '-1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Max count of elements to show inside table cell": must be between 1 and 99999.'));

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Max count of elements to show inside table cell'));

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");

	}

	public function testFormAdministrationGeneralGUI_EventAckEnable() {
		$this->zbxTestLogin('adm.gui.php');
		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_expire,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->zbxTestCheckboxSelect('event_ack_enable');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');
		$this->zbxTestTextPresent('CONFIGURATION OF GUI');
		$this->zbxTestTextPresent('GUI');
		$this->zbxTestTextPresent('Enable event acknowledges');

		$sql = 'SELECT event_ack_enable FROM config WHERE event_ack_enable=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_ack_enable"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->zbxTestCheckboxUnselect('event_ack_enable');

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Enable event acknowledges'));

		$sql = 'SELECT event_ack_enable FROM config WHERE event_ack_enable=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_ack_enable"');

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralGUI_EventExpire() {
		// 1-99999

		$this->zbxTestLogin('adm.gui.php');

		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,event_show_max,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->input_type('event_expire', '99999');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Show events not older than (in days)'));

		$sql = 'SELECT event_expire FROM config WHERE event_expire=99999';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_expire"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent('CONFIGURATION OF GUI');
		$this->zbxTestTextPresent('GUI');
		$this->input_type('event_expire', '1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');
		$this->zbxTestTextPresent('CONFIGURATION OF GUI');
		$this->zbxTestTextPresent('GUI');
		$this->zbxTestTextPresent('Show events not older than (in days)');

		$sql = 'select event_expire from config where event_expire=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_expire"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('event_expire', '100000');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Show events not older than (in days)": must be between 1 and 99999.'));

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Show events not older than (in days)'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('event_expire', '0');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Show events not older than (in days)": must be between 1 and 99999.'));

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Show events not older than (in days)'));

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");

	}

	public function testFormAdministrationGeneralGUI_EventShowMax() {
		$this->zbxTestLogin('adm.gui.php');
		$this->input_type('event_show_max', '99999');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Max count of events per trigger to show'));

		$sqlHash = 'SELECT configid,alert_history,event_history,refresh_unsupported,work_period,alert_usrgrpid,event_ack_enable,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_entry,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$sql = 'SELECT event_show_max FROM config WHERE event_show_max=99999';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_show_max"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', ));
		$this->input_type('event_show_max', '1');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('Configuration updated', 'CONFIGURATION OF GUI', 'GUI', 'Max count of events per trigger to show'));

		$sql = 'SELECT event_show_max FROM config WHERE event_show_max=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "event_show_max"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('event_show_max', '100000');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Max count of events per trigger to show": must be between 1 and 99999.'));

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Max count of events per trigger to show'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->checkTitle('Configuration of GUI');
		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI'));
		$this->input_type('event_show_max', '0');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent(array('ERROR: Page received incorrect data', 'Warning. Incorrect value for field "Max count of events per trigger to show": must be between 1 and 99999.'));

		$this->zbxTestTextPresent(array('CONFIGURATION OF GUI', 'GUI', 'Max count of events per trigger to show'));

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some DB fields changed, but shouldn't.");

	}
}
