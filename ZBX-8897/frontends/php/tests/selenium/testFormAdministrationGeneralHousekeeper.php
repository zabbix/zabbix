<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		$this->assertElementPresent('configDropDown');

		$this->zbxTestCheckTitle('Configuration of housekeeping');
		$this->zbxTestTextPresent(array('CONFIGURATION OF HOUSEKEEPING', 'Housekeeping'));

		// events and alerts

		$this->zbxTestTextPresent('Events and alerts');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->assertVisible('hk_events_mode');
		$this->assertAttribute("//input[@id='hk_events_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Trigger data storage period (in days)');
		$this->assertVisible('hk_events_trigger');
		$this->assertAttribute("//input[@id='hk_events_trigger']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_trigger']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_trigger']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_trigger']/@disabled");

		$this->zbxTestTextPresent('Internal data storage period (in days)');
		$this->assertVisible('hk_events_internal');
		$this->assertAttribute("//input[@id='hk_events_internal']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_internal']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_internal']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_internal']/@disabled");

		$this->zbxTestTextPresent('Network discovery data storage period (in days)');
		$this->assertVisible('hk_events_discovery');
		$this->assertAttribute("//input[@id='hk_events_discovery']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_discovery']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_discovery']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_discovery']/@disabled");

		$this->zbxTestTextPresent('Auto-registration data storage period (in days)');
		$this->assertVisible('hk_events_autoreg');
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_autoreg']/@disabled");

		$this->zbxTestCheckboxSelect('hk_events_mode', false);
		$this->assertElementPresent("//input[@id='hk_events_trigger']/@disabled");
		$this->assertElementPresent("//input[@id='hk_events_internal']/@disabled");
		$this->assertElementPresent("//input[@id='hk_events_discovery']/@disabled");
		$this->assertElementPresent("//input[@id='hk_events_autoreg']/@disabled");

		// IT services

		$this->zbxTestTextPresent('IT services');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->assertVisible('hk_services_mode');
		$this->assertAttribute("//input[@id='hk_services_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->assertVisible('hk_services');
		$this->assertAttribute("//input[@id='hk_services']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_services']/@size", 5);
		$this->assertAttribute("//input[@id='hk_services']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_services']/@disabled");

		$this->zbxTestCheckboxSelect('hk_services_mode', false);
		$this->assertElementPresent("//input[@id='hk_services']/@disabled");

		// audit

		$this->zbxTestTextPresent('Audit');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->assertVisible('hk_audit_mode');
		$this->assertAttribute("//input[@id='hk_audit_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->assertVisible('hk_audit');
		$this->assertAttribute("//input[@id='hk_audit']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_audit']/@size", 5);
		$this->assertAttribute("//input[@id='hk_audit']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_audit']/@disabled");

		$this->zbxTestCheckboxSelect('hk_audit_mode', false);
		$this->assertElementPresent("//input[@id='hk_audit']/@disabled");

		//	user sessions

		$this->zbxTestTextPresent('User sessions');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->assertVisible('hk_sessions_mode');
		$this->assertAttribute("//input[@id='hk_sessions_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->assertVisible('hk_sessions');
		$this->assertAttribute("//input[@id='hk_sessions']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_sessions']/@size", 5);
		$this->assertAttribute("//input[@id='hk_sessions']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_sessions']/@disabled");

		$this->zbxTestCheckboxSelect('hk_sessions_mode', false);
		$this->assertElementPresent("//input[@id='hk_sessions']/@disabled");

		// history

		$this->zbxTestTextPresent('History');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->assertVisible('hk_history_mode');
		$this->assertAttribute("//input[@id='hk_history_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Override item history period');
		$this->assertVisible('hk_history_global');
		$this->assertElementNotPresent("//input[@id='hk_history_global']/@checked");
		$this->assertElementNotPresent("//input[@id='hk_history_global']/@disabled");

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->assertVisible('hk_history');
		$this->assertAttribute("//input[@id='hk_history']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_history']/@size", 5);
		$this->assertAttribute("//input[@id='hk_history']/@value", 90);
		$this->assertElementPresent("//input[@id='hk_history']/@disabled");

		$this->zbxTestCheckboxSelect('hk_history_global');
		$this->assertElementNotPresent("//input[@id='hk_history']/@disabled");

		$this->zbxTestCheckboxSelect('hk_history_mode', false);
		$this->assertElementNotPresent("//input[@id='hk_history_global']/@disabled");
		$this->assertElementNotPresent("//input[@id='hk_history']/@disabled");

		// trends

		$this->zbxTestTextPresent('Trends');
		$this->zbxTestTextPresent('Enable internal housekeeping');
		$this->assertVisible('hk_trends_mode');
		$this->assertAttribute("//input[@id='hk_trends_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Override item trend period');
		$this->assertVisible('hk_trends_global');
		$this->assertElementNotPresent("//input[@id='hk_trends_global']/@checked");
		$this->assertElementNotPresent("//input[@id='hk_trends_global']/@disabled");

		$this->zbxTestTextPresent('Data storage period (in days)');
		$this->assertVisible('hk_trends');
		$this->assertAttribute("//input[@id='hk_trends']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_trends']/@size", 5);
		$this->assertAttribute("//input[@id='hk_trends']/@value", 365);
		$this->assertElementPresent("//input[@id='hk_trends']/@disabled");

		$this->zbxTestCheckboxSelect('hk_trends_global');
		$this->assertElementNotPresent("//input[@id='hk_trends']/@disabled");

		$this->zbxTestCheckboxSelect('hk_trends_mode', false);
		$this->assertElementNotPresent("//input[@id='hk_trends_global']/@disabled");
		$this->assertElementNotPresent("//input[@id='hk_trends']/@disabled");

		// buttons

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('resetDefaults');
		$this->assertAttribute("//input[@id='resetDefaults']/@value", 'Reset defaults');
	}

	public static function update() {
		return array(
			array(
				array(
					'expected' => TEST_GOOD,
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
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'hk_events_mode' => false,
					'hk_services_mode' => false,
					'hk_audit_mode' => false,
					'hk_sessions_mode' => false,
					'hk_history_mode' => false,
					'hk_trends_mode' => false
				)
			),
			array(
				array(
					'expected' => TEST_GOOD,
					'hk_events_mode' => true,
					'hk_services_mode' => true,
					'hk_audit_mode' => true,
					'hk_sessions_mode' => true,
					'hk_history_mode' => true,
					'hk_history_global' => false,
					'hk_trends_mode' => true,
					'hk_trends_global' => false
				)
			),
			array(
				array(
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
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Incorrect value "0" for "Trigger event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Internal event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Network discovery event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Auto-registration event and alert data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "IT service data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "Audit data storage period" field: must be between 1 and 99999.',
						'Incorrect value "0" for "User session data storage period" field: must be between 1 and 99999.',
						'Incorrect value "-1" for "History data storage period" field: must be between 0 and 99999.',
						'Incorrect value "-1" for "Trend data storage period" field: must be between 0 and 99999.'
					)
				)
			),
			array(
				array(
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
				)
			)
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testFormAdministrationGeneralHousekeeper_SimpleUpdate($data) {
		$this->zbxTestLogin('adm.housekeeper.php');

		if (isset($data['resetDefaults'])) {
			$this->zbxTestClick('resetDefaults');
			$this->zbxTestClick("//button[@type='button']/span[text()='Reset defaults']");
		}
		else {
			// events and alerts

			if (isset($data['hk_events_mode'])) {
				$this->zbxTestCheckboxSelect('hk_events_mode', $data['hk_events_mode']);
			}

			if (isset($data['hk_events_trigger'])) {
				$this->input_type('hk_events_trigger', $data['hk_events_trigger']);
			}

			if (isset($data['hk_events_internal'])) {
				$this->input_type('hk_events_internal', $data['hk_events_internal']);
			}

			if (isset($data['hk_events_discovery'])) {
				$this->input_type('hk_events_discovery', $data['hk_events_discovery']);
			}

			if (isset($data['hk_events_autoreg'])) {
				$this->input_type('hk_events_autoreg', $data['hk_events_autoreg']);
			}

			// IT services

			if (isset($data['hk_services_mode'])) {
				$this->zbxTestCheckboxSelect('hk_services_mode', $data['hk_services_mode']);
			}

			if (isset($data['hk_services'])) {
				$this->input_type('hk_services', $data['hk_services']);
			}

			// audit

			if (isset($data['hk_audit_mode'])) {
				$this->zbxTestCheckboxSelect('hk_audit_mode', $data['hk_audit_mode']);
			}

			if (isset($data['hk_audit'])) {
				$this->input_type('hk_audit', $data['hk_audit']);
			}

			// user sessions

			if (isset($data['hk_sessions_mode'])) {
				$this->zbxTestCheckboxSelect('hk_sessions_mode', $data['hk_sessions_mode']);
			}

			if (isset($data['hk_sessions'])) {
				$this->input_type('hk_sessions', $data['hk_sessions']);
			}

			// history

			if (isset($data['hk_history_mode'])) {
				$this->zbxTestCheckboxSelect('hk_history_mode', $data['hk_history_mode']);
			}

			if (isset($data['hk_history_global'])) {
				$this->zbxTestCheckboxSelect('hk_history_global', $data['hk_history_global']);
			}

			if (isset($data['hk_history'])) {
				$this->input_type('hk_history', $data['hk_history']);
			}

			// trends

			if (isset($data['hk_trends_mode'])) {
				$this->zbxTestCheckboxSelect('hk_trends_mode', $data['hk_trends_mode']);
			}

			if (isset($data['hk_trends_global'])) {
				$this->zbxTestCheckboxSelect('hk_trends_global', $data['hk_trends_global']);
			}

			if (isset($data['hk_trends'])) {
				$this->input_type('hk_trends', $data['hk_trends']);
			}
		}

		$this->zbxTestClickWait('save');
		$this->zbxTestCheckTitle('Configuration of housekeeping');
		$this->zbxTestTextPresent('CONFIGURATION OF HOUSEKEEPING');

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
				$this->assertEquals($this->isChecked('hk_events_mode'), $data['hk_events_mode']);
			}

			if (isset($data['hk_events_trigger'])) {
				$this->assertElementValue('hk_events_trigger', $data['hk_events_trigger']);
			}

			if (isset($data['hk_events_internal'])) {
				$this->assertElementValue('hk_events_internal', $data['hk_events_internal']);
			}

			if (isset($data['hk_events_discovery'])) {
				$this->assertElementValue('hk_events_discovery', $data['hk_events_discovery']);
			}

			if (isset($data['hk_events_autoreg'])) {
				$this->assertElementValue('hk_events_autoreg', $data['hk_events_autoreg']);
			}

			// IT services

			if (isset($data['hk_services_mode'])) {
				$this->assertEquals($this->isChecked('hk_services_mode'), $data['hk_services_mode']);
			}

			if (isset($data['hk_services'])) {
				$this->assertElementValue('hk_services', $data['hk_services']);
			}

			// audit

			if (isset($data['hk_audit_mode'])) {
				$this->assertEquals($this->isChecked('hk_audit_mode'), $data['hk_audit_mode']);
			}

			if (isset($data['hk_audit'])) {
				$this->assertElementValue('hk_audit', $data['hk_audit']);
			}

			// user sessions

			if (isset($data['hk_sessions_mode'])) {
				$this->assertEquals($this->isChecked('hk_sessions_mode'), $data['hk_sessions_mode']);
			}

			if (isset($data['hk_sessions'])) {
				$this->assertElementValue('hk_sessions', $data['hk_sessions']);
			}

			// history

			if (isset($data['hk_history_mode'])) {
				$this->assertEquals($this->isChecked('hk_history_mode'), $data['hk_history_mode']);
			}

			if (isset($data['hk_history_global'])) {
				$this->assertEquals($this->isChecked('hk_history_global'), $data['hk_history_global']);
			}

			if (isset($data['hk_history'])) {
				$this->assertElementValue('hk_history', $data['hk_history']);
			}

			// trends

			if (isset($data['hk_trends_mode'])) {
				$this->assertEquals($this->isChecked('hk_trends_mode'), $data['hk_trends_mode']);
			}

			if (isset($data['hk_trends_global'])) {
				$this->assertEquals($this->isChecked('hk_trends_global'), $data['hk_trends_global']);
			}

			if (isset($data['hk_trends'])) {
				$this->assertElementValue('hk_trends', $data['hk_trends']);
			}
		}
	}
}
