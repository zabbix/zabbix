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

class testFormAdministrationGeneralHousekeeper extends CWebTest {

	public function testFormAdministrationGeneralHousekeeper_CheckLayout() {
		$this->zbxTestLogin('adm.housekeeper.php');
		$this->zbxTestAssertElementPresentId('configDropDown');

		$this->zbxTestCheckTitle('Configuration of housekeeping');
		$this->zbxTestCheckHeader('Housekeeping');

		// events and alerts

		$this->zbxTestTextPresent('Events and alerts');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->zbxTestAssertElementPresentId('hk_events_mode');
		$this->assertTrue($this->zbxTestCheckboxSelected('hk_events_mode'));

		$this->zbxTestTextPresent('Trigger data storage period (in days)');
		$this->zbxTestAssertElementPresentId('hk_events_trigger');
		$this->zbxTestAssertAttribute("//input[@id='hk_events_trigger']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_events_trigger']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_events_trigger'][@disabled]");

		$this->zbxTestTextPresent('Internal data storage period (in days)');
		$this->zbxTestAssertElementPresentId('hk_events_internal');
		$this->zbxTestAssertAttribute("//input[@id='hk_events_internal']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_events_internal']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_events_internal'][@disabled]");

		$this->zbxTestTextPresent('Network discovery data storage period (in days)');
		$this->zbxTestAssertElementPresentId('hk_events_discovery');
		$this->zbxTestAssertAttribute("//input[@id='hk_events_discovery']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_events_discovery']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_events_discovery'][@disabled]");

		$this->zbxTestTextPresent('Auto-registration data storage period (in days)');
		$this->zbxTestAssertElementPresentId('hk_events_autoreg');
		$this->zbxTestAssertAttribute("//input[@id='hk_events_autoreg']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_events_autoreg']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_events_autoreg'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_events_mode', false);
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_events_trigger'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_events_internal'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_events_discovery'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_events_autoreg'][@disabled]");

		// IT services

		$this->zbxTestTextPresent('IT services');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->zbxTestAssertElementPresentId('hk_services_mode');
		$this->assertTrue($this->zbxTestCheckboxSelected('hk_services_mode'));

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->zbxTestAssertElementPresentId('hk_services');
		$this->zbxTestAssertAttribute("//input[@id='hk_services']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_services']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_services'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_services_mode', false);
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_services'][@disabled]");

		// audit

		$this->zbxTestTextPresent('Audit');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->zbxTestAssertElementPresentId('hk_audit_mode');
		$this->assertTrue($this->zbxTestCheckboxSelected('hk_audit_mode'));

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->zbxTestAssertElementPresentId('hk_audit');
		$this->zbxTestAssertAttribute("//input[@id='hk_audit']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_audit']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_audit'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_audit_mode', false);
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_audit'][@disabled]");

		//	user sessions

		$this->zbxTestTextPresent('User sessions');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->zbxTestAssertElementPresentId('hk_sessions_mode');
		$this->assertTrue($this->zbxTestCheckboxSelected('hk_sessions_mode'));

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->zbxTestAssertVisibleId('hk_sessions');
		$this->zbxTestAssertAttribute("//input[@id='hk_sessions']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_sessions']", "value", 365);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_sessions'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_sessions_mode', false);
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_sessions'][@disabled]");

		// history

		$this->zbxTestTextPresent('History');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->zbxTestAssertElementPresentId('hk_history_mode');
		$this->assertTrue($this->zbxTestCheckboxSelected('hk_history_mode'));

		$this->zbxTestTextPresent('Override item history period');
		$this->zbxTestAssertElementPresentId('hk_history_global');
		$this->assertFalse($this->zbxTestCheckboxSelected('hk_history_global'));

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->zbxTestAssertVisibleId('hk_history');
		$this->zbxTestAssertAttribute("//input[@id='hk_history']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_history']", "value", 90);
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_history'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_history_global');
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_history'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_history_mode', false);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_history_global'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_history'][@disabled]");

		// trends

		$this->zbxTestTextPresent('Trends');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->zbxTestAssertElementPresentId('hk_trends_mode');
		$this->assertTrue($this->zbxTestCheckboxSelected('hk_trends_mode'));

		$this->zbxTestTextPresent('Override item trend period');
		$this->zbxTestAssertElementPresentId('hk_trends_global');
		$this->assertFalse($this->zbxTestCheckboxSelected('hk_trends_global'));

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->zbxTestAssertVisibleId('hk_trends');
		$this->zbxTestAssertAttribute("//input[@id='hk_trends']", "maxlength", 5);
		$this->zbxTestAssertAttribute("//input[@id='hk_trends']", "value", 365);
		$this->zbxTestAssertElementPresentXpath("//input[@id='hk_trends'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_trends_global');
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_trends'][@disabled]");

		$this->zbxTestCheckboxSelect('hk_trends_mode', false);
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_trends_global'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='hk_trends'][@disabled]");

		// buttons

		$this->zbxTestAssertVisibleId('update');
		$this->zbxTestAssertElementValue('update', 'Update');

		$this->zbxTestAssertVisibleId('resetDefaults');
		$this->zbxTestAssertElementText("//button[@id='resetDefaults']", "Reset defaults");
	}

	public static function update() {
		return [
			[
				[
					'expected' => TEST_GOOD,
//					'resetDefaults' => true,
					'hk_events_mode' => true,
					'hk_events_trigger' => 101,
					'hk_events_internal' => 102,
					'hk_events_discovery' => 103,
					'hk_events_autoreg' => 104,
					'hk_services_mode' => true,
					'hk_services' => 105,
					'hk_audit_mode' => true,
					'hk_audit' => 107,
					'hk_sessions_mode' => true,
					'hk_sessions' => 108,
					'hk_history_mode' => true,
					'hk_history_global' => true,
					'hk_history' => 109,
					'hk_trends_mode' => true,
					'hk_trends_global' => true,
					'hk_trends' => 110
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'hk_events_mode' => false,
					'hk_services_mode' => false,
					'hk_audit_mode' => false,
					'hk_sessions_mode' => false,
					'hk_history_mode' => false,
					'hk_trends_mode' => false
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'hk_events_mode' => true,
					'hk_services_mode' => true,
					'hk_audit_mode' => true,
					'hk_sessions_mode' => true,
					'hk_history_mode' => true,
					'hk_history_global' => false,
					'hk_trends_mode' => true,
					'hk_trends_global' => false
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'hk_events_mode' => true,
					'hk_events_trigger' => 0,
					'hk_events_internal' => 0,
					'hk_events_discovery' => 0,
					'hk_events_autoreg' => 0,
					'hk_services_mode' => true,
					'hk_services' => 0,
					'hk_audit_mode' => true,
					'hk_audit' => 0,
					'hk_sessions_mode' => true,
					'hk_sessions' => 0,
					'hk_history_mode' => true,
					'hk_history_global' => true,
					'hk_history' => -1,
					'hk_trends_mode' => true,
					'hk_trends_global' => true,
					'hk_trends' => -1,
					'errors' => [
						'Page received incorrect data',
						'Incorrect value "0" for "Trigger event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Internal event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Network discovery event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Auto-registration event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "IT service data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Audit data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "User session data storage period" field: must be between 1 and 99999.',
						'Incorrect value "-1" for "History data storage period" field: must be between 0 and 99999.',
						'Incorrect value "-1" for "Trend data storage period" field: must be between 0 and 99999.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'resetDefaults' => true,
					'hk_events_mode' => true,
					'hk_events_trigger' => 365,
					'hk_events_internal' => 365,
					'hk_events_discovery' => 365,
					'hk_events_autoreg' => 365,
					'hk_services_mode' => true,
					'hk_services' => 365,
					'hk_audit_mode' => true,
					'hk_audit' => 365,
					'hk_sessions_mode' => true,
					'hk_sessions' => 365,
					'hk_history_mode' => true,
					'hk_history_global' => false,
					'hk_trends_mode' => true,
					'hk_trends_global' => false
				]
			]
		];
	}

	/**
	 * @dataProvider update
	 */
	public function testFormAdministrationGeneralHousekeeper_SimpleUpdate($data) {
		$this->zbxTestLogin('adm.housekeeper.php');

		if (isset($data['resetDefaults'])) {
			$this->zbxTestClick('resetDefaults');
			$this->zbxTestClickXpath("//div[@id='overlay_dialogue']//button[text()='Reset defaults']");
		}
		else {
			// events and alerts

			if (isset($data['hk_events_mode'])) {
				$this->zbxTestCheckboxSelect('hk_events_mode', $data['hk_events_mode']);
			}

			if (isset($data['hk_events_trigger'])) {
				$this->zbxTestInputTypeOverwrite('hk_events_trigger', $data['hk_events_trigger']);
			}

			if (isset($data['hk_events_internal'])) {
				$this->zbxTestInputTypeOverwrite('hk_events_internal', $data['hk_events_internal']);
			}

			if (isset($data['hk_events_discovery'])) {
				$this->zbxTestInputTypeOverwrite('hk_events_discovery', $data['hk_events_discovery']);
			}

			if (isset($data['hk_events_autoreg'])) {
				$this->zbxTestInputTypeOverwrite('hk_events_autoreg', $data['hk_events_autoreg']);
			}

			// IT services

			if (isset($data['hk_services_mode'])) {
				$this->zbxTestCheckboxSelect('hk_services_mode', $data['hk_services_mode']);
			}

			if (isset($data['hk_services'])) {
				$this->zbxTestInputTypeOverwrite('hk_services', $data['hk_services']);
			}

			// audit

			if (isset($data['hk_audit_mode'])) {
				$this->zbxTestCheckboxSelect('hk_audit_mode', $data['hk_audit_mode']);
			}

			if (isset($data['hk_audit'])) {
				$this->zbxTestInputTypeOverwrite('hk_audit', $data['hk_audit']);
			}

			// user sessions

			if (isset($data['hk_sessions_mode'])) {
				$this->zbxTestCheckboxSelect('hk_sessions_mode', $data['hk_sessions_mode']);
			}

			if (isset($data['hk_sessions'])) {
				$this->zbxTestInputTypeOverwrite('hk_sessions', $data['hk_sessions']);
			}

			// history

			if (isset($data['hk_history_mode'])) {
				$this->zbxTestCheckboxSelect('hk_history_mode', $data['hk_history_mode']);
			}

			if (isset($data['hk_history_global'])) {
				$this->zbxTestCheckboxSelect('hk_history_global', $data['hk_history_global']);
			}

			if (isset($data['hk_history'])) {
				$this->zbxTestInputTypeOverwrite('hk_history', $data['hk_history']);
			}

			// trends

			if (isset($data['hk_trends_mode'])) {
				$this->zbxTestCheckboxSelect('hk_trends_mode', $data['hk_trends_mode']);
			}

			if (isset($data['hk_trends_global'])) {
				$this->zbxTestCheckboxSelect('hk_trends_global', $data['hk_trends_global']);
			}

			if (isset($data['hk_trends'])) {
				$this->zbxTestInputTypeOverwrite('hk_trends', $data['hk_trends']);
			}
		}

		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of housekeeping');
		$this->zbxTestCheckHeader('Housekeeping');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestTextPresent('Configuration updated');
				break;

			case TEST_BAD:
				$this->zbxTestTextNotPresent('Configuration updated');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}

		if ($data['expected'] == TEST_GOOD) {
			// events and alerts

			if (isset($data['hk_events_mode'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_events_mode'), $data['hk_events_mode']);
			}

			if (isset($data['hk_events_trigger'])) {
				$this->zbxTestAssertElementValue('hk_events_trigger', $data['hk_events_trigger']);
			}

			if (isset($data['hk_events_internal'])) {
				$this->zbxTestAssertElementValue('hk_events_internal', $data['hk_events_internal']);
			}

			if (isset($data['hk_events_discovery'])) {
				$this->zbxTestAssertElementValue('hk_events_discovery', $data['hk_events_discovery']);
			}

			if (isset($data['hk_events_autoreg'])) {
				$this->zbxTestAssertElementValue('hk_events_autoreg', $data['hk_events_autoreg']);
			}

			// IT services

			if (isset($data['hk_services_mode'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_services_mode'), $data['hk_services_mode']);
			}

			if (isset($data['hk_services'])) {
				$this->zbxTestAssertElementValue('hk_services', $data['hk_services']);
			}

			// audit

			if (isset($data['hk_audit_mode'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_audit_mode'), $data['hk_audit_mode']);
			}

			if (isset($data['hk_audit'])) {
				$this->zbxTestAssertElementValue('hk_audit', $data['hk_audit']);
			}

			// user sessions

			if (isset($data['hk_sessions_mode'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_sessions_mode'), $data['hk_sessions_mode']);
			}

			if (isset($data['hk_sessions'])) {
				$this->zbxTestAssertElementValue('hk_sessions', $data['hk_sessions']);
			}

			// history

			if (isset($data['hk_history_mode'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_history_mode'), $data['hk_history_mode']);
			}

			if (isset($data['hk_history_global'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_history_global'), $data['hk_history_global']);
			}

			if (isset($data['hk_history'])) {
				$this->zbxTestAssertElementValue('hk_history', $data['hk_history']);
			}

			// trends

			if (isset($data['hk_trends_mode'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_trends_mode'), $data['hk_trends_mode']);
			}

			if (isset($data['hk_trends_global'])) {
				$this->assertEquals($this->zbxTestCheckboxSelected('hk_trends_global'), $data['hk_trends_global']);
			}

			if (isset($data['hk_trends'])) {
				$this->zbxTestAssertElementValue('hk_trends', $data['hk_trends']);
			}
		}
	}
}
