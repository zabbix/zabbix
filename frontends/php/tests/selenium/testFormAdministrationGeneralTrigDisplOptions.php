<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testFormAdministrationGeneralTrigDisplOptions extends CWebTest {

	public static function allValues() {
		return DBdata('SELECT problem_unack_color, problem_unack_style, problem_ack_color, problem_ack_style, ok_unack_color, ok_unack_style,'.
					'ok_ack_color, ok_ack_style, ok_period, blink_period FROM config ORDER BY configid');
	}

	/**
	* @dataProvider allValues
	*/
	public function testFormAdministrationGeneralTrigDisplOptions_Layout($allValues) {

		$this->zbxTestLogin('adm.triggerdisplayoptions.php');
		$this->zbxTestTextPresent(['CONFIGURATION OF ZABBIX', 'Trigger displaying options', 'Colour', 'Blinking', 'Unacknowledged PROBLEM events', 'Acknowledged PROBLEM events', 'Unacknowledged OK events', 'Acknowledged OK events', 'Display OK triggers for', 'On status change triggers blink for']);

		$sql = 'SELECT problem_unack_color, problem_unack_style, problem_ack_color, problem_ack_style, ok_unack_color, ok_unack_style,'.
		'ok_ack_color, ok_ack_style, ok_period, blink_period FROM config ORDER BY configid';

		$this->assertAttribute("//input[@id='problem_unack_color']/@value", $allValues['problem_unack_color']);
		$this->assertAttribute("//input[@id='problem_ack_color']/@value", $allValues['problem_ack_color']);
		$this->assertAttribute("//input[@id='ok_unack_color']/@value", $allValues['ok_unack_color']);
		$this->assertAttribute("//input[@id='ok_ack_color']/@value", $allValues['ok_ack_color']);
		$this->assertAttribute("//input[@id='ok_period']/@value", $allValues['ok_period']);
		$this->assertAttribute("//input[@id='blink_period']/@value", $allValues['blink_period']);

		if ($allValues['problem_unack_style']==1) {
			$this->assertElementPresent("//input[@id='problem_unack_style' and @checked]");
		}

		if ($allValues['problem_unack_style']==0) {
			$this->assertElementPresent("//input[@id='event_ack_enable' and not (@checked)]");
		}

		if ($allValues['problem_ack_style']==1) {
			$this->assertElementPresent("//input[@id='problem_ack_style' and @checked]");
		}

		if ($allValues['problem_ack_style']==0) {
			$this->assertElementPresent("//input[@id='problem_ack_style' and not (@checked)]");
		}

		if ($allValues['ok_unack_style']==1) {
			$this->assertElementPresent("//input[@id='ok_unack_style' and @checked]");
		}
		if ($allValues['ok_unack_style']==0) {
			$this->assertElementPresent("//input[@id='ok_unack_style' and not (@checked)]");
		}

		if ($allValues['ok_ack_style']==1) {
			$this->assertElementPresent("//input[@id='ok_ack_style' and @checked]");
		}
		if ($allValues['ok_ack_style']==0) {
			$this->assertElementPresent("//input[@id='ok_ack_style' and not (@checked)]");
		}
	}

	public function testFormAdministrationGeneralTrigDisplOptions_UpdateTrigDisplOptions() {

		$this->zbxTestLogin('adm.triggerdisplayoptions.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger displaying options');
		$this->zbxTestCheckTitle('Configuration of trigger displaying options');
		$this->zbxTestTextPresent(['CONFIGURATION OF ZABBIX', 'Trigger displaying options', 'Colour', 'Blinking', 'Unacknowledged PROBLEM events', 'Acknowledged PROBLEM events', 'Unacknowledged OK events', 'Acknowledged OK events', 'Display OK triggers for', 'On status change triggers blink for']);

		// hash calculation for not-changed DB fields
		$sqlHash = 'SELECT configid, refresh_unsupported, work_period, alert_usrgrpid, event_ack_enable, event_expire,'.
		'event_show_max, authentication_type, ldap_host, ldap_port, ldap_base_dn, ldap_bind_dn, ldap_bind_password, ldap_search_attribute,'.
		'dropdown_first_entry, dropdown_first_remember, discovery_groupid, max_in_table, search_limit, severity_color_0, severity_color_1,'.
		'severity_color_2, severity_color_3, severity_color_4, severity_color_5, severity_name_0, severity_name_1, severity_name_2,'.
		'severity_name_3, severity_name_4, severity_name_5, snmptrap_logging FROM config ORDER BY configid';

		$oldHash = DBhash($sqlHash);

		$this->input_type('problem_unack_color', 'BB0000');
		$this->input_type('problem_ack_color', 'BB0000');
		$this->input_type('ok_unack_color', '66FF66');
		$this->input_type('ok_ack_color', '66FF66');
		$this->zbxTestCheckboxSelect('problem_unack_style', false);
		$this->zbxTestCheckboxSelect('problem_ack_style', false);
		$this->zbxTestCheckboxSelect('ok_unack_style', false);
		$this->zbxTestCheckboxSelect('ok_ack_style', false);
		$this->input_type('ok_period', '120');
		$this->input_type('blink_period', '120');
		$this->input_type('ok_period', '120');
		$this->input_type('blink_period', '120');

		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'CONFIGURATION OF ZABBIX', 'Trigger displaying options']);

		// checking values in the DB
		$sql = 'SELECT problem_unack_color FROM config WHERE problem_unack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "problem_unack_color"');
		$sql = 'SELECT problem_ack_color FROM config WHERE problem_ack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "problem_ack_color"');
		$sql = 'SELECT ok_unack_color FROM config WHERE ok_unack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "ok_unack_color"');
		$sql = 'SELECT ok_ack_color FROM config WHERE ok_unack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "ok_ack_color"');

		$sql = 'SELECT problem_unack_style FROM config WHERE problem_unack_style=0 AND problem_unack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "problem_unack_style" for unacknowledged PROBLEM events');

		$sql = 'SELECT problem_ack_style FROM config WHERE problem_ack_style=0 AND problem_ack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "problem_ack_style" for acknowledged PROBLEM events');

		$sql = 'SELECT ok_unack_style FROM config WHERE ok_unack_style=0 AND ok_unack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "ok_unack_style" for unacknowledged OK events');

		$sql = 'SELECT ok_ack_style FROM config WHERE ok_ack_style=0 AND ok_ack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "ok_unack_style" for unacknowledged OK events');

		$sql = 'SELECT ok_period FROM config WHERE ok_period=120';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "ok_period" for displaying OK triggers');

		$sql = 'SELECT blink_period FROM config WHERE blink_period=120';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "blink_period"');

		$newHash = DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some other DB fields also changed, but shouldn't.");
	}

	public function testFormAdministrationGeneralTrigDisplOptions_ResetTrigDisplOptions() {

		$this->zbxTestLogin('adm.triggerdisplayoptions.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger displaying options');
		$this->zbxTestCheckTitle('Configuration of trigger displaying options');
		$this->zbxTestTextPresent(['CONFIGURATION OF ZABBIX', 'Trigger displaying options']);

		// hash calculation for the DB fields that should be changed in this report
		$sqlHash = 'SELECT configid, refresh_unsupported, work_period, alert_usrgrpid, event_ack_enable,'.
		'event_expire, event_show_max, authentication_type, ldap_host, ldap_port, ldap_base_dn, ldap_bind_dn, ldap_bind_password,'.
		'ldap_search_attribute, dropdown_first_entry, dropdown_first_remember, discovery_groupid, max_in_table, search_limit, severity_color_0,'.
		'severity_color_1, severity_color_2, severity_color_3, severity_color_4, severity_color_5, severity_name_0, severity_name_1,'.
		'severity_name_2, severity_name_3, severity_name_4, severity_name_5, snmptrap_logging FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->zbxTestClick('resetDefaults');
		$this->zbxTestClick("//button[@type='button']");
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'CONFIGURATION OF ZABBIX', 'Trigger displaying options']);

		$sql = 'SELECT problem_unack_color FROM config WHERE problem_unack_color='.zbx_dbstr('DC0000').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "problem_unack_color"');

		$sql = 'SELECT problem_ack_color FROM config WHERE problem_ack_color='.zbx_dbstr('DC0000').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "problem_ack_color"');

		$sql = 'SELECT ok_unack_color FROM config WHERE ok_unack_color='.zbx_dbstr('00AA00').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "ok_unack_color"');

		$sql = 'SELECT ok_ack_color FROM config WHERE ok_unack_color='.zbx_dbstr('00AA00').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect color in the DB field "ok_ack_color"');

		$sql = 'SELECT problem_unack_style FROM config WHERE problem_unack_style=1 AND problem_unack_color='.zbx_dbstr('DC0000').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "problem_unack_style" for unacknowledged PROBLEM events');

		$sql = 'SELECT problem_ack_style FROM config WHERE problem_ack_style=1 AND problem_ack_color='.zbx_dbstr('DC0000').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "problem_ack_style" for acknowledged PROBLEM events');

		$sql = 'SELECT ok_unack_style FROM config WHERE ok_unack_style=1 AND ok_unack_color='.zbx_dbstr('00AA00').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "ok_unack_style" for unacknowledged OK events');

		$sql = 'SELECT ok_ack_style FROM config WHERE ok_ack_style=1 AND ok_ack_color='.zbx_dbstr('00AA00').'';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "ok_ack_style" for acknowledged OK events');

		$sql = 'SELECT ok_period FROM config WHERE ok_period=1800';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "ok_period" for displaying OK triggers');

		$sql = 'SELECT blink_period FROM config WHERE blink_period=1800';
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect value in the DB field "blink_period"');

		// hash calculation for the DB fields that should be changed in this report
		$newHash=DBhash($sqlHash);
		$this->assertEquals($oldHash, $newHash, "Values in some other DB fields also changed, but shouldn't.");
	}
}
