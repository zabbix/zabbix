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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralGUI extends CWebTest {

	public static function allValues() {
		return DBdata('SELECT default_theme,dropdown_first_entry,dropdown_first_remember,search_limit,max_in_table,server_check_interval FROM config ORDER BY configid');
	}

	/**
	* @dataProvider allValues
	*/
	public function testFormAdministrationGeneralGUI_CheckLayout($allValues) {

		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestTextPresent([
			'Default theme',
			'Dropdown first entry',
			'remember selected',
			'Limit for search and filter results',
			'Max count of elements to show inside table cell',
			'Enable event acknowledgement',
			'Show warning if Zabbix server is down'
		]);

		$this->zbxTestDropdownHasOptions('default_theme', ['Blue', 'Dark']);
		$this->zbxTestDropdownHasOptions('dropdown_first_entry', ['All', 'None']);

		$this->zbxTestAssertElementPresentId('search_limit');
		$this->zbxTestAssertAttribute('//input[@id="search_limit"]', 'maxlength', '6');
		$this->zbxTestAssertElementPresentId('max_in_table');
		$this->zbxTestAssertAttribute('//input[@id="max_in_table"]','maxlength', '5');

		$this->zbxTestAssertElementPresentId('dropdown_first_remember');
		$this->zbxTestAssertElementPresentId('server_check_interval');

		$this->zbxTestAssertElementPresentId('update');

		$this->zbxTestAssertAttribute("//select[@id='default_theme']/option[@selected='selected']", "value", $allValues['default_theme']);
		$this->zbxTestAssertAttribute("//select[@id='dropdown_first_entry']/option[@selected='selected']", "value", $allValues['dropdown_first_entry']);

		if ($allValues['dropdown_first_remember']) {
			$this->assertTrue($this->zbxTestCheckboxSelected('dropdown_first_remember'));
		}
		if ($allValues['dropdown_first_remember']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('dropdown_first_remember'));
		}

		if ($allValues['server_check_interval']) {
			$this->assertTrue($this->zbxTestCheckboxSelected('server_check_interval'));
		}
		if ($allValues['server_check_interval']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('server_check_interval'));
		}

		$this->zbxTestAssertElementValue('search_limit', $allValues['search_limit']);
		$this->zbxTestAssertElementValue('max_in_table', $allValues['max_in_table']);
	}

	public function testFormAdministrationGeneralGUI_ChangeTheme() {

		$this->zbxTestLogin('adm.gui.php');
		$sql_hash = 'SELECT '.CTestDbHelper::getTableFields('config', ['default_theme']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestDropdownSelect('default_theme', 'Dark');
		$this->zbxTestAssertElementValue('default_theme', 'dark-theme');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Default theme']);
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('dark-theme');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: "Dark" theme can not be selected as default theme: it does not exist in the DB');

		$this->zbxTestDropdownSelect('default_theme', 'Blue');
		$this->zbxTestAssertElementValue('default_theme', 'blue-theme');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Default theme']);
		$sql = 'SELECT default_theme FROM config WHERE default_theme='.zbx_dbstr('blue-theme');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: "Blue" theme can not be selected as default theme: it does not exist in the DB');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_ChangeDropdownFirstEntry() {

		$this->zbxTestLogin('adm.gui.php');
		$sql_hash = 'SELECT configid,refresh_unsupported,work_period,alert_usrgrpid,default_theme,authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,ldap_search_attribute,dropdown_first_remember,discovery_groupid,max_in_table,search_limit,severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,severity_color_5,severity_name_0,severity_name_1,severity_name_2,severity_name_3,severity_name_4,severity_name_5,ok_period,blink_period,problem_unack_color,problem_ack_color,ok_unack_color,ok_ack_color,problem_unack_style,problem_ack_style,ok_unack_style,ok_ack_style,snmptrap_logging FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestDropdownSelect('dropdown_first_entry', 'None');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Dropdown first entry']);
		$sql = 'SELECT dropdown_first_entry FROM config WHERE dropdown_first_entry=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value "None" can not be selected as "dropdown first entry" value');

		$this->zbxTestDropdownSelect('dropdown_first_entry', 'All');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Dropdown first entry']);

		$sql = 'SELECT dropdown_first_entry FROM config WHERE dropdown_first_entry=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Value "All" can not be selected as "dropdown first entry" value');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_ChangeDropdownFirstRemember() {

		$this->zbxTestLogin('adm.gui.php');
		$sql_hash = 'SELECT '.CTestDbHelper::getTableFields('config', ['dropdown_first_remember']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestCheckboxSelect('dropdown_first_remember');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'remember selected']);
		$this->assertTrue($this->zbxTestCheckboxSelected('dropdown_first_remember'));

		$sql = 'SELECT dropdown_first_remember FROM config WHERE dropdown_first_remember=0';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "dropdown_first_remember"');

		$this->zbxTestCheckboxSelect('dropdown_first_remember', false);
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'remember selected']);
		$this->assertFalse($this->zbxTestCheckboxSelected('dropdown_first_remember'));

		$sql = 'SELECT dropdown_first_remember FROM config WHERE dropdown_first_remember=1';
		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "dropdown_first_remember"');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_ChangeSearchLimit() {
		$this->zbxTestLogin('adm.gui.php');
		$sql_hash = 'SELECT '.CTestDbHelper::getTableFields('config', ['search_limit']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestInputType('search_limit', '1000');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Limit for search and filter results']);

		$sql = 'SELECT search_limit FROM config WHERE search_limit=1000';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '1');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Limit for search and filter results']);

		$sql = 'SELECT search_limit FROM config WHERE search_limit=1';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '999999');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Limit for search and filter results']);

		$sql = 'SELECT search_limit FROM config WHERE search_limit=999999';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "search_limit"');

		// Check to enter 0 value
		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '0');
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Page received incorrect data');
		$this->zbxTestTextPresent('Incorrect value "0" for "Limit for search and filter results" field: must be between 1 and 999999.');
		$this->zbxTestTextNotPresent('Configuration updated');

		// Check to enter -1 value
		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('search_limit', '-1');
		$this->zbxTestClickWait('update');

		$this->zbxTestTextPresent(['GUI', 'Limit for search and filter results']);
		$this->zbxTestTextPresent(['Page received incorrect data', 'Incorrect value "-1" for "Limit for search and filter results" field: must be between 1 and 999999.']);
		$this->zbxTestTextNotPresent('Configuration updated');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_ChangeMaxInTable() {
		$sql_hash = 'SELECT '.CTestDbHelper::getTableFields('config', ['max_in_table']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestInputTypeOverwrite('max_in_table', '1000');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent([
			'Configuration updated',
			'GUI',
			'Max count of elements to show inside table cell'
		]);

		$this->assertEquals(1, DBcount('SELECT NULL FROM config WHERE max_in_table=1000'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputType('max_in_table', '1');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent([
			'Configuration updated',
			'GUI',
			'Max count of elements to show inside table cell'
		]);

		$this->assertEquals(1, DBcount('SELECT NULL FROM config WHERE max_in_table=1'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('max_in_table', '99999');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent([
			'Configuration updated',
			'GUI',
			'Max count of elements to show inside table cell'
		]);

		$this->assertEquals(1, DBcount('SELECT NULL FROM config WHERE max_in_table=99999'));

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestInputTypeOverwrite('max_in_table', '-1');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent([
			'Page received incorrect data',
			'Incorrect value "-1" for "Max count of elements to show inside table cell" field: must be between 1 and 99999.',
			'GUI',
			'Max count of elements to show inside table cell'
		]);
		$this->zbxTestTextNotPresent('Configuration updated');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormAdministrationGeneralGUI_EventCheckInterval() {
		$this->zbxTestLogin('adm.gui.php');
		$sql_hash = 'SELECT '.CTestDbHelper::getTableFields('config', ['server_check_interval']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestCheckboxSelect('server_check_interval');
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent('Configuration updated');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestTextPresent('Show warning if Zabbix server is down');
		$this->assertTrue($this->zbxTestCheckboxSelected('server_check_interval'));

		$sql = 'SELECT server_check_interval FROM config WHERE server_check_interval=10';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "server_check_interval"');

		$this->zbxTestDropdownSelectWait('configDropDown', 'GUI');
		$this->zbxTestCheckTitle('Configuration of GUI');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestCheckboxSelect('server_check_interval', false);

		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'GUI', 'Show warning if Zabbix server is down']);
		$this->assertFalse($this->zbxTestCheckboxSelected('server_check_interval'));

		$sql = 'SELECT server_check_interval FROM config WHERE server_check_interval=0';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "server_check_interval"');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}
}
