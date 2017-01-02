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

class testFormAdministrationGeneralTriggerSeverities extends CWebTest {

	public static function allValues() {
		return DBdata('SELECT severity_name_0,severity_color_0,severity_name_1,severity_color_1,'.
				'severity_name_2,severity_color_2,severity_name_3,severity_color_3,severity_name_4,'.
				'severity_color_4,severity_name_5,severity_color_5 FROM config ORDER BY configid');
	}
	/**
	* @dataProvider allValues
	*/
	public function testFormAdministrationGeneralTriggerSeverities_CheckLayout($allValues) {

		$this->zbxTestLogin('adm.triggerseverities.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger severities');
		$this->zbxTestCheckTitle('Configuration of Zabbix');
		$this->zbxTestTextPresent(array('CONFIGURATION OF ZABBIX', 'Trigger severities', 'Custom severity', 'Colour'));

		$this->assertElementPresent('severity_name_0');
		$this->assertElementPresent('severity_name_1');
		$this->assertElementPresent('severity_name_2');
		$this->assertElementPresent('severity_name_3');
		$this->assertElementPresent('severity_name_4');
		$this->assertElementPresent('severity_name_5');
		$this->assertElementPresent('severity_color_0');
		$this->assertElementPresent('severity_color_1');
		$this->assertElementPresent('severity_color_2');
		$this->assertElementPresent('severity_color_3');
		$this->assertElementPresent('severity_color_4');
		$this->assertElementPresent('severity_color_5');
		$this->assertElementPresent('lbl_severity_color_0');
		$this->assertElementPresent('lbl_severity_color_1');
		$this->assertElementPresent('lbl_severity_color_2');
		$this->assertElementPresent('lbl_severity_color_3');
		$this->assertElementPresent('lbl_severity_color_4');
		$this->assertElementPresent('lbl_severity_color_5');
		$this->assertAttribute("//input[@id='severity_name_0']/@maxlength", '32');
		$this->assertAttribute("//input[@id='severity_name_0']/@size", '20');
		$this->assertAttribute("//input[@id='severity_name_1']/@maxlength", '32');
		$this->assertAttribute("//input[@id='severity_name_1']/@size", '20');
		$this->assertAttribute("//input[@id='severity_name_2']/@maxlength", '32');
		$this->assertAttribute("//input[@id='severity_name_2']/@size", '20');
		$this->assertAttribute("//input[@id='severity_name_3']/@maxlength", '32');
		$this->assertAttribute("//input[@id='severity_name_3']/@size", '20');
		$this->assertAttribute("//input[@id='severity_name_4']/@maxlength", '32');
		$this->assertAttribute("//input[@id='severity_name_4']/@size", '20');
		$this->assertAttribute("//input[@id='severity_name_5']/@maxlength", '32');
		$this->assertAttribute("//input[@id='severity_name_5']/@size", '20');

		// checking values in this report
		$this->assertAttribute("//input[@id='severity_name_0']/@value", $allValues['severity_name_0']);
		$this->assertAttribute("//input[@id='severity_name_1']/@value", $allValues['severity_name_1']);
		$this->assertAttribute("//input[@id='severity_name_2']/@value", $allValues['severity_name_2']);
		$this->assertAttribute("//input[@id='severity_name_3']/@value", $allValues['severity_name_3']);
		$this->assertAttribute("//input[@id='severity_name_4']/@value", $allValues['severity_name_4']);
		$this->assertAttribute("//input[@id='severity_name_5']/@value", $allValues['severity_name_5']);
		$this->assertAttribute("//input[@id='severity_color_0']/@value", $allValues['severity_color_0']);
		$this->assertAttribute("//input[@id='severity_color_1']/@value", $allValues['severity_color_1']);
		$this->assertAttribute("//input[@id='severity_color_2']/@value", $allValues['severity_color_2']);
		$this->assertAttribute("//input[@id='severity_color_3']/@value", $allValues['severity_color_3']);
		$this->assertAttribute("//input[@id='severity_color_4']/@value", $allValues['severity_color_4']);
		$this->assertAttribute("//input[@id='severity_color_5']/@value", $allValues['severity_color_5']);

		// $this->assertAttribute("//input[@id='lbl_severity_color_0']/@title", $allValues['severity_color_0']);
		//$this->assertAttribute("//input[@id='lbl_severity_color_0']/@background-color", $allValues['severity_color_0']);

	}

	public function testFormAdministrationGeneralTriggerSeverities_ChangeTriggerSeverities() {

		$this->zbxTestLogin('adm.triggerseverities.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger severities');
		$this->zbxTestCheckTitle('Configuration of Zabbix');
		$this->zbxTestTextPresent(array('CONFIGURATION OF ZABBIX', 'Trigger severities', 'Custom severity', 'Colour'));
		$this->zbxTestTextPresent('Custom severity names affect all locales and require manual translation!');

		$this->input_type('severity_name_0', 'Not classified2');
		$this->input_type('severity_name_1', 'Information2');
		$this->input_type('severity_name_2', 'Warning2');
		$this->input_type('severity_name_3', 'Average2');
		$this->input_type('severity_name_4', 'High2');
		$this->input_type('severity_name_5', 'Disaster2');

		$this->zbxTestClick('lbl_severity_color_5');
		$this->zbxTestClick("//div[@id='' and @onclick='set_color(\"880000\");']");

		$this->zbxTestClick('lbl_severity_color_4');
		$this->zbxTestClick("//div[@id='' and @onclick='set_color(\"FF3333\");']");

		$this->zbxTestClick('lbl_severity_color_3');
		$this->zbxTestClick("//div[@id='' and @onclick='set_color(\"FF6666\");']");

		$this->zbxTestClick('lbl_severity_color_2');
		$this->zbxTestClick("//div[@id='' and @onclick='set_color(\"DDDD00\");']");

		$this->zbxTestClick('lbl_severity_color_1');
		$this->zbxTestClick("//div[@id='' and @onclick='set_color(\"00CCCC\");']");

		$this->zbxTestClick('lbl_severity_color_0');
		$this->zbxTestClick("//div[@id='' and @onclick='set_color(\"999999\");']");

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');

		$sql = 'SELECT severity_name_0 FROM config WHERE severity_name_0='.zbx_dbstr('Not classified2');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_0"');

		$sql = 'SELECT severity_name_1 FROM config WHERE severity_name_1='.zbx_dbstr('Information2');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_1"');

		$sql = 'SELECT severity_name_2 FROM config WHERE severity_name_2='.zbx_dbstr('Warning2');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_2"');

		$sql = 'SELECT severity_name_3 FROM config WHERE severity_name_3='.zbx_dbstr('Average2');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_3"');

		$sql = 'SELECT severity_name_4 FROM config WHERE severity_name_4='.zbx_dbstr('High2');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_4"');

		$sql = 'SELECT severity_name_5 FROM config WHERE severity_name_5='.zbx_dbstr('Disaster2');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_5"');

		// checking severity colors in the DB

		$sql = 'SELECT severity_color_0 FROM config where severity_color_0='.zbx_dbstr('999999');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_0"');

		$sql = 'SELECT severity_color_1 FROM config WHERE severity_color_1='.zbx_dbstr('00CCCC');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_1"');

		$sql = 'SELECT severity_color_2 FROM config WHERE severity_color_2='.zbx_dbstr('DDDD00');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_2"');

		$sql = 'SELECT severity_color_3 FROM config WHERE severity_color_3='.zbx_dbstr('FF6666');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_3"');

		$sql = 'SELECT severity_color_4 FROM config WHERE severity_color_4='.zbx_dbstr('FF3333');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_4"');

		$sql = 'SELECT severity_color_5 FROM config WHERE severity_color_5='.zbx_dbstr('880000');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_5"');
	}

	public function testFormAdministrationGeneralTriggerSeverities_ResetDefaults() {

		$this->zbxTestLogin('adm.triggerseverities.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger severities');
		$this->zbxTestCheckTitle('Configuration of Zabbix');
		$this->zbxTestTextPresent(
			array(
				'CONFIGURATION OF ZABBIX',
				'Trigger severities',
				'Custom severity',
				'Colour',
				'Custom severity names affect all locales and require manual translation!'
			)
		);
		$this->zbxTestClick('resetDefaults');
		$this->zbxTestClick("//button[@type='button']");
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Configuration updated');
		$this->zbxTestTextPresent('CONFIGURATION OF ZABBIX');
		$this->zbxTestTextPresent('Trigger severities');

		// checking that values were reset in the DB
		$sql = 'SELECT severity_name_0 FROM config WHERE severity_name_0='.zbx_dbstr('Not classified');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_0"');

		$sql = 'SELECT severity_name_1 FROM config WHERE severity_name_1='.zbx_dbstr('Information');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_1"');

		$sql = 'SELECT severity_name_2 FROM config WHERE severity_name_2='.zbx_dbstr('Warning');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_2"');

		$sql = 'SELECT severity_name_3 FROM config WHERE severity_name_3='.zbx_dbstr('Average');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_3"');

		$sql = 'SELECT severity_name_4 FROM config WHERE severity_name_4='.zbx_dbstr('High');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_4"');

		$sql = 'SELECT severity_name_5 FROM config WHERE severity_name_5='.zbx_dbstr('Disaster');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity name in the DB field "severity_name_5"');


		$sql = 'SELECT severity_color_0 FROM config WHERE severity_color_0='.zbx_dbstr('DBDBDB');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_0"');

		$sql = 'SELECT severity_color_1 FROM config WHERE severity_color_1='.zbx_dbstr('D6F6FF');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_2"');

		$sql = 'SELECT severity_color_2 FROM config WHERE severity_color_2='.zbx_dbstr('FFF6A5');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_3"');

		$sql = 'SELECT severity_color_3 FROM config WHERE severity_color_3='.zbx_dbstr('FFB689');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_3"');

		$sql = 'SELECT severity_color_4 FROM config WHERE severity_color_4='.zbx_dbstr('FF9999');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_4"');

		$sql = 'SELECT severity_color_5 FROM config WHERE severity_color_5='.zbx_dbstr('FF3838');
		$this->assertEquals(1, DBcount($sql), 'Chuck Norris: Incorrect severity color in the DB field "severity_color_5"');

// TODO: can also check that trigger severities have NOT been reset after clicking Cancel in the "Reset confirmation" dialog box after clicking "Reset defaults" button

	}
}
