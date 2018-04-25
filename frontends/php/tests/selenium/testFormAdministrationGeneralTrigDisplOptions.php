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
		$this->zbxTestCheckHeader('Trigger displaying options');
		$this->zbxTestTextPresent(
			[
				'Trigger displaying options',
				'blinking',
				'Unacknowledged PROBLEM events',
				'Acknowledged PROBLEM events',
				'Unacknowledged RESOLVED events',
				'Acknowledged RESOLVED events',
				'Display OK triggers for',
				'On status change triggers blink for'
			]
		);

		$sql = 'SELECT problem_unack_color, problem_unack_style, problem_ack_color, problem_ack_style, ok_unack_color, ok_unack_style,'.
		'ok_ack_color, ok_ack_style, ok_period, blink_period FROM config ORDER BY configid';

		$this->zbxTestAssertElementValue('problem_unack_color', $allValues['problem_unack_color']);
		$this->zbxTestAssertElementValue('problem_ack_color', $allValues['problem_ack_color']);
		$this->zbxTestAssertElementValue('ok_unack_color', $allValues['ok_unack_color']);
		$this->zbxTestAssertElementValue('ok_ack_color', $allValues['ok_ack_color']);
		$this->zbxTestAssertElementValue('ok_period', $allValues['ok_period']);
		$this->zbxTestAssertElementValue('blink_period', $allValues['blink_period']);

		$this->zbxTestAssertElementPresentXpath("//input[@id='problem_unack_color'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='problem_ack_color'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='ok_unack_color'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='ok_ack_color'][@disabled]");

		if ($allValues['problem_unack_style']==1) {
			$this->assertTrue($this->zbxTestCheckboxSelected('problem_unack_style'));
		}

		if ($allValues['problem_unack_style']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('problem_unack_style'));
		}

		if ($allValues['problem_ack_style']==1) {
			$this->assertTrue($this->zbxTestCheckboxSelected('problem_ack_style'));
		}

		if ($allValues['problem_ack_style']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('problem_ack_style'));
		}

		if ($allValues['ok_unack_style']==1) {
			$this->assertTrue($this->zbxTestCheckboxSelected('ok_unack_style'));
		}
		if ($allValues['ok_unack_style']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('ok_unack_style'));
		}

		if ($allValues['ok_ack_style']==1) {
			$this->assertTrue($this->zbxTestCheckboxSelected('ok_ack_style'));
		}
		if ($allValues['ok_ack_style']==0) {
			$this->assertFalse($this->zbxTestCheckboxSelected('ok_ack_style'));
		}
	}

	public function testFormAdministrationGeneralTrigDisplOptions_UpdateTrigDisplOptions() {

		$this->zbxTestLogin('adm.triggerdisplayoptions.php');
		$this->zbxTestCheckTitle('Configuration of trigger displaying options');
		$this->zbxTestCheckHeader('Trigger displaying options');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger displaying options');
		$this->zbxTestTextPresent(['Trigger displaying options', 'blinking', 'Unacknowledged PROBLEM events', 'Acknowledged PROBLEM events', 'Unacknowledged RESOLVED events', 'Acknowledged RESOLVED events', 'Display OK triggers for', 'On status change triggers blink for']);

		// hash calculation for not-changed DB fields
		$sql_hash = 'SELECT '.DB::getTableFields('config', ['problem_unack_color', 'problem_ack_color', 'ok_unack_color', 'ok_ack_color', 'problem_unack_style', 'problem_ack_style', 'ok_unack_style', 'ok_ack_style', 'ok_period', 'blink_period']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestCheckboxSelect('custom_color');

		$this->zbxTestInputType('problem_unack_color', 'BB0000');
		$this->zbxTestInputType('problem_ack_color', 'BB0000');
		$this->zbxTestInputType('ok_unack_color', '66FF66');
		$this->zbxTestInputType('ok_ack_color', '66FF66');
		$this->zbxTestCheckboxSelect('problem_unack_style', false);
		$this->zbxTestCheckboxSelect('problem_ack_style', false);
		$this->zbxTestCheckboxSelect('ok_unack_style', false);
		$this->zbxTestCheckboxSelect('ok_ack_style', false);
		$this->zbxTestInputType('ok_period', '120');
		$this->zbxTestInputType('blink_period', '120');

		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'Trigger displaying options']);

		// checking values in the DB
		$sql = 'SELECT problem_unack_color FROM config WHERE problem_unack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT problem_ack_color FROM config WHERE problem_ack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT ok_unack_color FROM config WHERE ok_unack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT ok_ack_color FROM config WHERE ok_unack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT problem_unack_style FROM config WHERE problem_unack_style=0 AND problem_unack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT problem_ack_style FROM config WHERE problem_ack_style=0 AND problem_ack_color='.zbx_dbstr('BB0000');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT ok_unack_style FROM config WHERE ok_unack_style=0 AND ok_unack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT ok_ack_style FROM config WHERE ok_ack_style=0 AND ok_ack_color='.zbx_dbstr('66FF66');
		$this->assertEquals(1, DBcount($sql));
		$sql = "SELECT ok_period FROM config WHERE ok_period='120'";
		$this->assertEquals(1, DBcount($sql));
		$sql = "SELECT blink_period FROM config WHERE blink_period='120'";
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public static function ok_period() {
		return [
			[[
				'expected' => TEST_BAD,
				'period' => ' ',
				'error_msg' => 'Invalid displaying of OK triggers: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => 's',
				'error_msg' => 'Invalid displaying of OK triggers: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '1.5',
				'error_msg' => 'Invalid displaying of OK triggers: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '{$BAD}',
				'error_msg' => 'Invalid displaying of OK triggers: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '1441m',
				'error_msg' => 'Invalid displaying of OK triggers: must be between "0" and "86400".'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '2d',
				'error_msg' => 'Invalid displaying of OK triggers: must be between "0" and "86400".'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '86401',
				'error_msg' => 'Invalid displaying of OK triggers: must be between "0" and "86400".'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '1y',
				'error_msg' => 'Invalid displaying of OK triggers: a time unit is expected.'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '0'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '86400s'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '1440m'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '1h'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '1d'
			]]
		];
	}

	/**
	 * @dataProvider ok_period
	 */
	public function testFormAdministrationGeneralTrigDisplOptions_OKPeriod($data) {
	$this->zbxTestLogin('adm.triggerdisplayoptions.php');

		$this->zbxTestInputTypeOverwrite('ok_period', $data['period']);
		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good' , 'Configuration updated');
				$this->zbxTestCheckHeader('Trigger displaying options');
				$this->zbxTestCheckFatalErrors();
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update configuration');
				$this->zbxTestTextPresent($data['error_msg']);
				$this->zbxTestCheckHeader('Trigger displaying options');
				$this->zbxTestCheckFatalErrors();
				break;
		}
	}

	public static function blink_period() {
		return [
			[[
				'expected' => TEST_BAD,
				'period' => ' ',
				'error_msg' => 'Invalid blinking on trigger status change: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => 's',
				'error_msg' => 'Invalid blinking on trigger status change: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '1.5',
				'error_msg' => 'Invalid blinking on trigger status change: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '{$BAD}',
				'error_msg' => 'Invalid blinking on trigger status change: a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '1441m',
				'error_msg' => 'Invalid blinking on trigger status change: must be between "0" and "86400".'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '2d',
				'error_msg' => 'Invalid blinking on trigger status change: must be between "0" and "86400".'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '86401',
				'error_msg' => 'Invalid blinking on trigger status change: must be between "0" and "86400".'
			]],
			[[
				'expected' => TEST_BAD,
				'period' => '1y',
				'error_msg' => 'Invalid blinking on trigger status change: a time unit is expected.'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '0'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '86400s'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '1440m'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '1h'
			]],
			[[
				'expected' => TEST_GOOD,
				'period' => '1d'
			]]
		];
	}

	/**
	 * @dataProvider blink_period
	 */
	public function testFormAdministrationGeneralTrigDisplOptions_BlinkPeriod($data) {
	$this->zbxTestLogin('adm.triggerdisplayoptions.php');

		$this->zbxTestInputTypeOverwrite('blink_period', $data['period']);
		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good' , 'Configuration updated');
				$this->zbxTestCheckHeader('Trigger displaying options');
				$this->zbxTestCheckFatalErrors();
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update configuration');
				$this->zbxTestTextPresent($data['error_msg']);
				$this->zbxTestCheckHeader('Trigger displaying options');
				$this->zbxTestCheckFatalErrors();
				break;
		}
	}

	public function testFormAdministrationGeneralTrigDisplOptions_ResetTrigDisplOptions() {

		$this->zbxTestLogin('adm.triggerdisplayoptions.php');
		$this->zbxTestCheckTitle('Configuration of trigger displaying options');
		$this->zbxTestCheckHeader('Trigger displaying options');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger displaying options');

		// hash calculation for the DB fields that should be changed in this report
		$sql_hash = 'SELECT '.DB::getTableFields('config', ['problem_unack_style', 'problem_ack_style', 'ok_unack_style', 'ok_ack_style', 'ok_period', 'blink_period']).' FROM config ORDER BY configid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestClick('resetDefaults');
		$this->zbxTestClickXpath("//div[@id='overlay_dialogue']//button[text()='Reset defaults']");
		$this->zbxTestClickWait('update');
		$this->zbxTestTextPresent(['Configuration updated', 'Trigger displaying options']);

		$sql = 'SELECT problem_unack_style FROM config WHERE problem_unack_style=1';
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT problem_ack_style FROM config WHERE problem_ack_style=1';
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT ok_unack_style FROM config WHERE ok_unack_style=1';
		$this->assertEquals(1, DBcount($sql));
		$sql = 'SELECT ok_ack_style FROM config WHERE ok_ack_style=1';
		$this->assertEquals(1, DBcount($sql));

		$sql = "SELECT ok_period FROM config WHERE ok_period='30m'";
		$this->assertEquals(1, DBcount($sql));
		$sql = "SELECT blink_period FROM config WHERE blink_period='30m'";
		$this->assertEquals(1, DBcount($sql));

		// hash calculation for the DB fields that should be changed in this report
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}
}
