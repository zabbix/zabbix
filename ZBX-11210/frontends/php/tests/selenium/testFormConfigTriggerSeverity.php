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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormConfigTriggerSeverity extends CWebTest {
	// Data provider
	public static function providerTriggerSeverity() {
		// array of data, saveResult, db fields value
		// if saveResult is false. values should not change
		$data = [
			[
				[
					'severity_name_0' => 'sev 0',
					'severity_color_0' => '000000',
					'severity_name_1' => 'sev 1',
					'severity_color_1' => '111111',
					'severity_name_2' => 'sev 2',
					'severity_color_2' => '222222',
					'severity_name_3' => 'sev 3',
					'severity_color_3' => '333333',
					'severity_name_4' => 'sev 4',
					'severity_color_4' => '444444',
					'severity_name_5' => 'sev 5',
					'severity_color_5' => '555555',
				],
				true,
				[
					'severity_name_0' => 'sev 0',
					'severity_color_0' => '000000',
					'severity_name_1' => 'sev 1',
					'severity_color_1' => '111111',
					'severity_name_2' => 'sev 2',
					'severity_color_2' => '222222',
					'severity_name_3' => 'sev 3',
					'severity_color_3' => '333333',
					'severity_name_4' => 'sev 4',
					'severity_color_4' => '444444',
					'severity_name_5' => 'sev 5',
					'severity_color_5' => '555555',
				]
			],
			[
				[
					'severity_name_0' => '',
				],
				false,
				null
			],
			[
				[
					'severity_color_0' => '',
				],
				false,
				null
			],
			[
				[
					'severity_color_0' => 'ccc',
				],
				false,
				null
			],
			[
				[
					'severity_color_0' => 'yuttrt',
				],
				false,
				null
			],
			[
				[
					'severity_color_0' => '1234567',
				],
				true,
				[
					'severity_color_0' => '123456',
				],
			],
			[
				[
					'severity_name_0' => 'iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii more than 32 chars',
				],
				true,
				[
					'severity_name_0' => 'iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii',
				],
			],
		];
		return $data;
	}


	public function testFormTriggerSeverity_Layout() {
		$this->zbxTestLogin('adm.triggerseverities.php');
		$this->zbxTestCheckTitle('Configuration of trigger severities');
		$this->zbxTestCheckHeader('Trigger severities');

		$this->zbxTestDropdownSelectWait('configDropDown', 'Trigger severities');

		$this->zbxTestTextPresent(['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']);
		$this->zbxTestAssertElementPresentId('severity_name_0');
		$this->zbxTestAssertElementPresentId('severity_color_0');
		$this->zbxTestAssertElementPresentId('lbl_severity_color_0');
		$this->zbxTestAssertElementPresentId('severity_name_1');
		$this->zbxTestAssertElementPresentId('severity_color_1');
		$this->zbxTestAssertElementPresentId('lbl_severity_color_1');
		$this->zbxTestAssertElementPresentId('severity_name_2');
		$this->zbxTestAssertElementPresentId('severity_color_2');
		$this->zbxTestAssertElementPresentId('lbl_severity_color_2');
		$this->zbxTestAssertElementPresentId('severity_name_3');
		$this->zbxTestAssertElementPresentId('severity_color_3');
		$this->zbxTestAssertElementPresentId('lbl_severity_color_3');
		$this->zbxTestAssertElementPresentId('severity_name_4');
		$this->zbxTestAssertElementPresentId('severity_color_4');
		$this->zbxTestAssertElementPresentId('lbl_severity_color_4');
		$this->zbxTestAssertElementPresentId('severity_name_5');
		$this->zbxTestAssertElementPresentId('severity_color_5');
		$this->zbxTestAssertElementPresentId('lbl_severity_color_5');
		$this->zbxTestAssertElementPresentId('update');

		$this->zbxTestAssertElementPresentXpath('//div[@class="overlay-dialogue"]/div[@class="color-picker"]');
		$this->zbxTestAssertNotVisibleXpath('//div[@class="color-picker"]');
		$this->zbxTestClick('lbl_severity_color_0');
		$this->zbxTestAssertVisibleXpath('//div[@class="color-picker"]');
	}

	public function testFormTriggerSeverity_backup() {
		DBsave_tables('config');
	}

	/**
	 * @dataProvider providerTriggerSeverity
	 */
	public function testFormTriggerSeverity_Update($data, $resultSave, $DBvalues) {
		$this->zbxTestLogin('adm.triggerseverities.php');
		$this->zbxTestCheckTitle('Configuration of trigger severities');
		$this->zbxTestCheckHeader('Trigger severities');

		foreach ($data as $field => $value) {
			$this->zbxTestInputType($field, $value);
		}

		$sql = 'SELECT '.implode(', ', array_keys($data)).' FROM config';
		if (!$resultSave) {
			$DBhash = DBhash($sql);
		}

		$this->zbxTestClickWait('update');

		if ($resultSave) {
			$this->zbxTestTextPresent('Configuration updated');

			$dbres = DBfetch(DBselect($sql));
			foreach ($dbres as $field => $value) {
				$this->assertEquals($value, $DBvalues[$field], "Value for '$field' was not updated.");
			}
		}
		else {
			$this->zbxTestTextPresent('Page received incorrect data');
			$this->assertEquals($DBhash, DBhash($sql), "DB fields changed after unsuccessful save.");
		}

	}

	public function testFormTriggerSeverity_restore() {
		DBrestore_tables('config');
	}

}
