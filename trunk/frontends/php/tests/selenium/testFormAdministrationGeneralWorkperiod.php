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

class testFormAdministrationGeneralWorkperiod extends CWebTest {

	public static function WorkingTime() {
		return DBdata('SELECT work_period FROM config ORDER BY configid');
	}

	/**
	* @dataProvider WorkingTime
	*/
	public function testFormAdministrationGeneralWorkperiod_CheckLayout($WorkingTime) {
		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestCheckHeader('GUI');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Working time');
		$this->zbxTestCheckTitle('Configuration of working time');
		$this->zbxTestCheckHeader('Working time');

		$this->zbxTestAssertAttribute("//input[@id='work_period']", "maxlength", 255);
		$this->zbxTestAssertAttribute("//input[@id='work_period']", "size", 20);
		$this->zbxTestAssertAttribute("//input[@id='work_period']", "value", $WorkingTime['work_period']);
	}

	public function testFormAdministrationGeneralWorkperiod_SimpleUpdate() {
		$sqlHash = 'SELECT * FROM config ORDER BY configid';
		$oldHash = DBhash($sqlHash);

		$this->zbxTestLogin('adm.workingtime.php');
		$this->zbxTestCheckTitle('Configuration of working time');
		$this->zbxTestCheckHeader('Working time');
		$this->zbxTestDropdownAssertSelected('configDropDown', 'Working time');
		$this->zbxTestClickWait('update');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Configuration updated');

		$this->zbxTestCheckFatalErrors();
		$this->assertEquals($oldHash, DBhash($sqlHash));
	}

	public static function data() {
		return [
			[
				'work_period' => 'test',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7 09:00-24:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '0-7,09:00-24:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-5,09:00-18:00,6-7,10:00-16:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-8,09:00-24:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7,09:00-25:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7,24:00-00:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7,14:00-13:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7,25:00-26:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7,13:60-14:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '1-7',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '09:00-24:00',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '{WORKING_HOURS}',
				'success_expected' => TEST_BAD,
				'error-msg' => 'Field "Working time" is not correct: a time period is expected'
			],
			[
				'work_period' => '{$WORKING_HOURS}',
				'success_expected' => TEST_GOOD,
				'error-msg' => null
			],
			[
				'work_period' => '1-5,09:00-18:00',
				'success_expected' => TEST_GOOD,
				'error-msg' => null
			],
			[
				'work_period' => '1-5,09:00-18:00;5-7,12:00-16:00',
				'success_expected' => TEST_GOOD,
				'error-msg' => null
			]
		];
	}

	/**
	 * @dataProvider data
	 */
	public function testFormAdministrationGeneralWorkperiod_SavingWorkperiod($work_period, $expected, $msg) {
		$this->zbxTestLogin('adm.workingtime.php');
		$this->zbxTestCheckTitle('Configuration of working time');
		$this->zbxTestCheckHeader('Working time');

		$this->zbxTestInputType('work_period', $work_period);
		$this->zbxTestClickWait('update');

		switch ($expected) {
			case TEST_GOOD:
				$this->zbxTestTextNotPresent('Page received incorrect data');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Configuration updated');
				$this->zbxTestCheckFatalErrors();
				$result = DBfetch(DBselect('SELECT work_period FROM config'));
				$this->assertEquals($work_period, $result['work_period']);
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Page received incorrect data');
				$this->zbxTestTextPresent($msg);
				break;
		}
	}
}
