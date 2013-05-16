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

define('HOUSEKEEPER_GOOD', 0);
define('HOUSEKEEPER_BAD', 1);

class testFormAdministrationGeneralHousekeeper extends CWebTest {

	// Returns layout data
	public static function layout() {
		return array(
			array(
				array('hk_events_mode' => 'unchecked')
			),
			array(
				array('hk_services_mode' => 'unchecked')
			),
			array(
				array('hk_audit_mode' => 'unchecked')
			),
			array(
				array('hk_sessions_mode' => 'unchecked')
			),
			array(
				array('hk_history_mode' => 'unchecked')
			),
			array(
				array('hk_history_global' => 'checked')
			),
			array(
				array('hk_trends_mode' => 'unchecked')
			),
			array(
				array('hk_trends_global' => 'checked')
			)
		);
	}

	/**
	 * @dataProvider layout
	 */
	public function testFormAdministrationGeneralHousekeeper_CheckLayout($data) {

		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent(array('CONFIGURATION OF HOUSEKEEPER', 'Housekeeper'));

		if (isset($data['hk_events_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_events_mode');
		}

		if (isset($data['hk_services_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_services_mode');
		}

		if (isset($data['hk_audit_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_audit_mode');
		}

		if (isset($data['hk_sessions_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_sessions_mode');
		}

		if (isset($data['hk_history_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_history_mode');
		}

		if (isset($data['hk_history_global'])) {
			$this->zbxTestCheckboxSelect('hk_history_global');
		}

		if (isset($data['hk_trends_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_trends_mode');
		}

		if (isset($data['hk_trends_global'])) {
			$this->zbxTestCheckboxSelect('hk_trends_global');
		}

		$this->zbxTestTextPresent('Events and alerts');
		$this->zbxTestTextPresent('Enable housekeeping');
		$this->assertVisible('hk_events_mode');
		$this->assertAttribute("//input[@id='hk_events_mode']/@checked", 'checked');

		$this->zbxTestTextPresent('Keep trigger data for (in days)');
		$this->assertVisible('hk_events_trigger');
		$this->assertAttribute("//input[@id='hk_events_trigger']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_trigger']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_trigger']/@value", 365);
		if (isset($data['hk_events_mode'])) {
			$this->assertElementPresent("//input[@id='hk_events_trigger']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_events_trigger']/@disabled");
		}

		$this->zbxTestTextPresent('Keep internal data for (in days)');
		$this->assertVisible('hk_events_internal');
		$this->assertAttribute("//input[@id='hk_events_internal']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_internal']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_internal']/@value", 365);
		if (isset($data['hk_events_mode'])) {
			$this->assertElementPresent("//input[@id='hk_events_internal']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_events_internal']/@disabled");
		}

		$this->zbxTestTextPresent('Keep network discovery data for (in days)');
		$this->assertVisible('hk_events_discovery');
		$this->assertAttribute("//input[@id='hk_events_discovery']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_discovery']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_discovery']/@value", 365);
		if (isset($data['hk_events_mode'])) {
			$this->assertElementPresent("//input[@id='hk_events_discovery']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_events_discovery']/@disabled");
		}

		$this->zbxTestTextPresent('Keep auto-registration data for (in days)');
		$this->assertVisible('hk_events_autoreg');
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@size", 5);
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@value", 365);
		if (isset($data['hk_events_mode'])) {
			$this->assertElementPresent("//input[@id='hk_events_autoreg']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_events_autoreg']/@disabled");
		}

		$this->zbxTestTextPresent('IT services');
		$this->zbxTestTextPresent('Enable housekeeping');
		$this->assertVisible('hk_services_mode');
		$this->assertAttribute("//input[@id='hk_services_mode']/@checked", 'checked');
		$this->zbxTestTextPresent('Keep data for (in days)');
		$this->assertVisible('hk_services');
		$this->assertAttribute("//input[@id='hk_services']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_services']/@size", 5);
		$this->assertAttribute("//input[@id='hk_services']/@value", 365);
		if (isset($data['hk_services_mode'])) {
			$this->assertElementPresent("//input[@id='hk_services']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_services']/@disabled");
		}

		$this->zbxTestTextPresent('Audit');
		$this->zbxTestTextPresent('Enable housekeeping');
		$this->assertVisible('hk_audit_mode');
		$this->assertAttribute("//input[@id='hk_audit_mode']/@checked", 'checked');
		$this->zbxTestTextPresent('Keep data for (in days)');
		$this->assertVisible('hk_services');
		$this->assertAttribute("//input[@id='hk_audit']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_audit']/@size", 5);
		$this->assertAttribute("//input[@id='hk_audit']/@value", 365);
		if (isset($data['hk_audit_mode'])) {
			$this->assertElementPresent("//input[@id='hk_audit']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_audit']/@disabled");
		}

		$this->zbxTestTextPresent('User sessions');
		$this->zbxTestTextPresent('Enable housekeeping');
		$this->assertVisible('hk_sessions_mode');
		$this->assertAttribute("//input[@id='hk_sessions_mode']/@checked", 'checked');
		$this->zbxTestTextPresent('Keep data for (in days)');
		$this->assertVisible('hk_sessions');
		$this->assertAttribute("//input[@id='hk_sessions']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_sessions']/@size", 5);
		$this->assertAttribute("//input[@id='hk_sessions']/@value", 365);
		if (isset($data['hk_sessions_mode'])) {
			$this->assertElementPresent("//input[@id='hk_sessions']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_sessions']/@disabled");
		}

		$this->zbxTestTextPresent('History');
		$this->zbxTestTextPresent('Enable housekeeping');
		$this->assertVisible('hk_history_mode');
		$this->assertAttribute("//input[@id='hk_history_mode']/@checked", 'checked');
		$this->zbxTestTextPresent('Override item history period');
		$this->assertVisible('hk_history_global');
		$this->assertElementNotPresent("//input[@id='hk_history_global']/@checked");
		if (isset($data['hk_history_mode'])) {
			$this->assertElementPresent("//input[@id='hk_history_global']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_history_global']/@disabled");
		}

		$this->zbxTestTextPresent('Keep data for (in days)');
		$this->assertVisible('hk_history');
		$this->assertAttribute("//input[@id='hk_history']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_history']/@size", 5);
		$this->assertAttribute("//input[@id='hk_history']/@value", 90);
		if (!isset($data['hk_history_global'])) {
			$this->assertElementPresent("//input[@id='hk_history']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_history']/@disabled");
		}

		$this->zbxTestTextPresent('Trends');
		$this->zbxTestTextPresent('Enable housekeeping');
		$this->assertVisible('hk_trends_mode');
		$this->assertAttribute("//input[@id='hk_trends_mode']/@checked", 'checked');
		$this->zbxTestTextPresent('Override item trend period');
		$this->assertVisible('hk_history_global');
		$this->assertElementNotPresent("//input[@id='hk_trends_global']/@checked");
		if (isset($data['hk_trends_mode'])) {
			$this->assertElementPresent("//input[@id='hk_trends_global']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_trends_global']/@disabled");
		}

		$this->zbxTestTextPresent('Keep data for (in days)');
		$this->assertVisible('hk_trends');
		$this->assertAttribute("//input[@id='hk_trends']/@maxlength", 5);
		$this->assertAttribute("//input[@id='hk_trends']/@size", 5);
		$this->assertAttribute("//input[@id='hk_trends']/@value", 365);
		if (!isset($data['hk_trends_global'])) {
			$this->assertElementPresent("//input[@id='hk_trends']/@disabled");
		}
		else {
			$this->assertElementNotPresent("//input[@id='hk_trends']/@disabled");
		}

		$this->assertVisible('save');
		$this->assertAttribute("//input[@id='save']/@value", 'Save');

		$this->assertVisible('resetDefaults');
		$this->assertAttribute("//input[@id='resetDefaults']/@value", 'Reset defaults');
	}

	// Returns update data
	public static function update() {
		return array(
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_events_mode' => 'unchecked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_services_mode' => 'unchecked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_audit_mode' => 'unchecked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_sessions_mode' => 'unchecked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_history_mode' => 'unchecked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_history_global' => 'checked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_trends_mode' => 'unchecked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_trends_global' => 'checked',
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => 100,
					'hk_events_internal' => 200,
					'hk_events_discovery' => 300,
					'hk_events_autoreg' => 400,
					'hk_services' => 500,
					'hk_audit' => 600,
					'hk_sessions' => 700,
					'hk_history' => 800,
					'hk_trends' => 900,
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => 1,
					'hk_events_internal' => 1,
					'hk_events_discovery' => 1,
					'hk_events_autoreg' => 1,
					'hk_services' => 1,
					'hk_audit' => 1,
					'hk_sessions' => 1,
					'hk_history' => 1,
					'hk_trends' => 1,
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_GOOD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => 99999,
					'hk_events_internal' => 99999,
					'hk_events_discovery' => 99999,
					'hk_events_autoreg' => 99999,
					'hk_services' => 99999,
					'hk_audit' => 99999,
					'hk_sessions' => 99999,
					'hk_history' => 99999,
					'hk_trends' => 99999,
					'formCheck' => true,
					'dbCheck' => true
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_BAD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => '-100',
					'hk_events_internal' => '-200',
					'hk_events_discovery' => '-300',
					'hk_events_autoreg' => '-400',
					'hk_services' => '-500',
					'hk_audit' => '-600',
					'hk_sessions' => '-700',
					'hk_history' => '-800',
					'hk_trends' => '-900',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trigger event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep internal event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep network discovery event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep auto-registration event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep IT service data for (in days)": must be between 1 and 99999.'
					)
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_BAD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => 'abc',
					'hk_events_internal' => 'abc',
					'hk_events_discovery' => 'abc',
					'hk_events_autoreg' => 'abc',
					'hk_services' => 'abc',
					'hk_audit' => 'abc',
					'hk_sessions' => 'abc',
					'hk_history' => 'abc',
					'hk_trends' => 'abc',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trigger event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep internal event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep network discovery event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep auto-registration event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep IT service data for (in days)": must be between 1 and 99999.'
					)
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_BAD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => '',
					'hk_events_internal' => '',
					'hk_events_discovery' => '',
					'hk_events_autoreg' => '',
					'hk_services' => '',
					'hk_audit' => '',
					'hk_sessions' => '',
					'hk_history' => '',
					'hk_trends' => '',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trigger event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep internal event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep network discovery event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep auto-registration event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep IT service data for (in days)": must be between 1 and 99999.'
					)
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_BAD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => ' ',
					'hk_events_internal' => ' ',
					'hk_events_discovery' => ' ',
					'hk_events_autoreg' => ' ',
					'hk_services' => ' ',
					'hk_audit' => ' ',
					'hk_sessions' => ' ',
					'hk_history' => ' ',
					'hk_trends' => ' ',
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trigger event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep internal event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep network discovery event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep auto-registration event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep IT service data for (in days)": must be between 1 and 99999.'
					)
				)
			),
			array(
				array(
					'expected' => HOUSEKEEPER_BAD,
					'hk_history_global' => 'checked',
					'hk_trends_global' => 'checked',
					'hk_events_trigger' => 0,
					'hk_events_internal' => 0,
					'hk_events_discovery' => 0,
					'hk_events_autoreg' => 0,
					'hk_services' => 0,
					'hk_audit' => 0,
					'hk_sessions' => 0,
					'hk_history' => 0,
					'hk_trends' => 0,
					'errors' => array(
						'ERROR: Page received incorrect data',
						'Warning. Incorrect value for field "Keep trigger event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep internal event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep network discovery event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep auto-registration event and alert data for (in days)": must be between 1 and 99999.',
						'Warning. Incorrect value for field "Keep IT service data for (in days)": must be between 1 and 99999.'
					)
				)
			)
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testFormAdministrationGeneralHousekeeper_SimpleUpdate($data) {

		if (isset($data['dbCheck'])) {
			DBsave_tables('config');
		}

		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent(array('CONFIGURATION OF HOUSEKEEPER', 'Housekeeper'));

		if (isset($data['hk_events_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_events_mode');
			$hk_events_mode = 'unchecked';
		}
		else {
			$hk_events_mode = 'checked';
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

		if (isset($data['hk_services_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_services_mode');
			$hk_services_mode = 'unchecked';
		}
		else {
			$hk_services_mode = 'checked';
		}

		if (isset($data['hk_services'])) {
			$this->input_type('hk_services', $data['hk_services']);
		}

		if (isset($data['hk_audit_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_audit_mode');
			$hk_audit_mode = 'unchecked';
		}
		else {
			$hk_audit_mode = 'checked';
		}

		if (isset($data['hk_audit'])) {
			$this->input_type('hk_audit', $data['hk_audit']);
		}

		if (isset($data['hk_sessions_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_sessions_mode');
			$hk_sessions_mode = 'unchecked';
		}
		else {
			$hk_sessions_mode = 'checked';
		}

		if (isset($data['hk_sessions'])) {
			$this->input_type('hk_sessions', $data['hk_sessions']);
		}

		if (isset($data['hk_history_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_history_mode');
			$hk_history_mode = 'unchecked';
		}
		else {
			$hk_history_mode = 'checked';
		}

		if (isset($data['hk_history_global'])) {
			$this->zbxTestCheckboxSelect('hk_history_global');
			$hk_history_global = 'checked';
		}
		else {
			$hk_history_global = 'unchecked';
		}

		if (isset($data['hk_history'])) {
			$this->input_type('hk_history', $data['hk_history']);
		}

		if (isset($data['hk_trends_mode'])) {
			$this->zbxTestCheckboxUnselect('hk_trends_mode');
			$hk_trends_mode = 'unchecked';
		}
		else {
			$hk_trends_mode = 'checked';
		}

		if (isset($data['hk_trends_global'])) {
			$this->zbxTestCheckboxSelect('hk_trends_global');
			$hk_trends_global = 'checked';
		}
		else {
			$hk_trends_global = 'unchecked';
		}

		if (isset($data['hk_trends'])) {
			$this->input_type('hk_trends', $data['hk_trends']);
		}

		$hk_events_trigger = $this->getValue('hk_events_trigger');
		$hk_events_internal = $this->getValue('hk_events_internal');
		$hk_events_discovery = $this->getValue('hk_events_discovery');
		$hk_events_autoreg = $this->getValue('hk_events_autoreg');
		$hk_sessions = $this->getValue('hk_sessions');
		$hk_services = $this->getValue('hk_services');
		$hk_audit = $this->getValue('hk_audit');
		$hk_history = $this->getValue('hk_history');
		$hk_trends = $this->getValue('hk_trends');

		$this->zbxTestClickWait('save');
		$expected = $data['expected'];

		switch ($expected) {
			case HOUSEKEEPER_GOOD:
				$this->zbxTestTextPresent('Configuration updated');
				$this->checkTitle('Configuration of housekeeper');
				$this->zbxTestTextPresent(array('CONFIGURATION OF HOUSEKEEPER', 'Housekeeper'));
				break;

			case HOUSEKEEPER_BAD:
				$this->zbxTestTextNotPresent('Configuration updated');
				$this->checkTitle('Configuration of housekeeper');
				foreach ($data['errors'] as $msg) {
					$this->zbxTestTextPresent($msg);
				}
				break;
		}

		if (isset($data['formCheck'])) {

			if (isset($data['hk_events_mode'])) {
				$this->assertElementNotPresent("//input[@id='hk_events_mode']/@checked");
			}
			else {
				$this->assertElementPresent("//input[@id='hk_events_mode']/@checked");
			}

			if (isset($data['hk_services_mode'])) {
				$this->assertElementNotPresent("//input[@id='hk_services_mode']/@checked");
			}
			else {
				$this->assertElementPresent("//input[@id='hk_services_mode']/@checked");
			}

			if (isset($data['hk_audit_mode'])) {
				$this->assertElementNotPresent("//input[@id='hk_audit_mode']/@checked");
			}
			else {
				$this->assertElementPresent("//input[@id='hk_audit_mode']/@checked");
			}

			if (isset($data['hk_sessions_mode'])) {
				$this->assertElementNotPresent("//input[@id='hk_sessions_mode']/@checked");
			}
			else {
				$this->assertElementPresent("//input[@id='hk_sessions_mode']/@checked");
			}

			if (isset($data['hk_history_mode'])) {
				$this->assertElementNotPresent("//input[@id='hk_history_mode']/@checked");
			}
			else {
				$this->assertElementPresent("//input[@id='hk_history_mode']/@checked");
			}

			if (isset($data['hk_history_global'])) {
				$this->assertElementPresent("//input[@id='hk_history_global']/@checked");
			}
			else {
				$this->assertElementNotPresent("//input[@id='hk_history_global']/@checked");
			}

			if (isset($data['hk_trends_mode'])) {
				$this->assertElementNotPresent("//input[@id='hk_trends_mode']/@checked");
			}
			else {
				$this->assertElementPresent("//input[@id='hk_trends_mode']/@checked");
			}

			if (isset($data['hk_trends_global'])) {
				$this->assertElementPresent("//input[@id='hk_trends_global']/@checked");
			}
			else {
				$this->assertElementNotPresent("//input[@id='hk_trends_global']/@checked");
			}
		}

		if (isset($data['dbCheck'])) {
			$result = DBselect("SELECT * FROM config");
			while ($row = DBfetch($result)) {
				switch($row['hk_events_mode']) {
					case 1:
						$hk_events_modeDB = 'checked';
						break;
					case 0:
						$hk_events_modeDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_events_mode, $hk_events_modeDB);

				$hk_events_triggerDB = $row['hk_events_trigger'];
				$this->assertEquals($hk_events_trigger, $hk_events_triggerDB);

				$hk_events_internalDB = $row['hk_events_internal'];
				$this->assertEquals($hk_events_internal, $hk_events_internalDB);

				$hk_events_discoveryDB = $row['hk_events_discovery'];
				$this->assertEquals($hk_events_discovery, $hk_events_discoveryDB);

				$hk_events_autoregDB = $row['hk_events_autoreg'];
				$this->assertEquals($hk_events_autoreg, $hk_events_autoregDB);

				switch($row['hk_services_mode']) {
					case 1:
						$hk_services_modeDB = 'checked';
						break;
					case 0:
						$hk_services_modeDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_services_mode, $hk_services_modeDB);

				$hk_servicesDB = $row['hk_services'];
				$this->assertEquals($hk_services, $hk_servicesDB);

				switch($row['hk_audit_mode']) {
					case 1:
						$hk_audit_modeDB = 'checked';
						break;
					case 0:
						$hk_audit_modeDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_audit_mode, $hk_audit_modeDB);

				$hk_auditDB = $row['hk_audit'];
				$this->assertEquals($hk_audit, $hk_auditDB);

				switch($row['hk_sessions_mode']) {
					case 1:
						$hk_sessions_modeDB = 'checked';
						break;
					case 0:
						$hk_sessions_modeDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_sessions_mode, $hk_sessions_modeDB);

				$hk_sessionsDB = $row['hk_sessions'];
				$this->assertEquals($hk_sessions, $hk_sessionsDB);

				switch($row['hk_history_mode']) {
					case 1:
						$hk_history_modeDB = 'checked';
						break;
					case 0:
						$hk_history_modeDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_history_mode, $hk_history_modeDB);

				switch($row['hk_history_global']) {
					case 1:
						$hk_history_globalDB = 'checked';
						break;
					case 0:
						$hk_history_globalDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_history_global, $hk_history_globalDB);

				$hk_historyDB = $row['hk_history'];
				$this->assertEquals($hk_history, $hk_historyDB);

				switch($row['hk_trends_mode']) {
					case 1:
						$hk_trends_modeDB = 'checked';
						break;
					case 0:
						$hk_trends_modeDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_trends_mode, $hk_trends_modeDB);

				switch($row['hk_trends_global']) {
					case 1:
						$hk_trends_globalDB = 'checked';
						break;
					case 0:
						$hk_trends_globalDB = 'unchecked';
						break;
				}
				$this->assertEquals($hk_trends_global, $hk_trends_globalDB);

				$hk_trendsDB = $row['hk_trends'];
				$this->assertEquals($hk_trends, $hk_trendsDB);
			}

			DBrestore_tables('config');
		}
	}

	public function testFormAdministrationGeneralHousekeeper_ResetDefaults() {
		DBsave_tables('config');

		$this->zbxTestLogin('adm.gui.php');
		$this->assertElementPresent('configDropDown');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Housekeeper');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent(array('CONFIGURATION OF HOUSEKEEPER', 'Housekeeper'));

		$sqlConfig = "SELECT * FROM config ORDER BY configid";
		$oldHashConfig = DBhash($sqlConfig);

		$this->zbxTestClick('resetDefaults');
		sleep(1);
		$this->assertVisible("//div[@class='ui-dialog ui-widget ui-widget-content ui-corner-all']");
		$this->zbxTestClick("//div[@class='ui-dialog ui-widget ui-widget-content ui-corner-all']/div/div/button[1]");
		$this->zbxTestClickWait('save');

		$this->zbxTestTextPresent('Configuration updated');
		$this->checkTitle('Configuration of housekeeper');
		$this->zbxTestTextPresent(array('CONFIGURATION OF HOUSEKEEPER', 'Housekeeper'));

		$this->assertElementPresent("//input[@id='hk_events_mode']/@checked");
		$this->assertVisible('hk_events_trigger');
		$this->assertAttribute("//input[@id='hk_events_trigger']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_trigger']/@disabled");

		$this->assertVisible('hk_events_internal');
		$this->assertAttribute("//input[@id='hk_events_internal']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_internal']/@disabled");

		$this->assertVisible('hk_events_discovery');
		$this->assertAttribute("//input[@id='hk_events_discovery']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_discovery']/@disabled");

		$this->assertVisible('hk_events_autoreg');
		$this->assertAttribute("//input[@id='hk_events_autoreg']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_events_autoreg']/@disabled");

		$this->assertElementPresent("//input[@id='hk_services_mode']/@checked");
		$this->assertVisible('hk_services');
		$this->assertAttribute("//input[@id='hk_services']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_services']/@disabled");

		$this->assertElementPresent("//input[@id='hk_audit_mode']/@checked");
		$this->assertVisible('hk_audit');
		$this->assertAttribute("//input[@id='hk_audit']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_audit']/@disabled");

		$this->assertElementPresent("//input[@id='hk_sessions_mode']/@checked");
		$this->assertVisible('hk_sessions');
		$this->assertAttribute("//input[@id='hk_sessions']/@value", 365);
		$this->assertElementNotPresent("//input[@id='hk_sessions']/@disabled");

		$this->assertElementPresent("//input[@id='hk_history_mode']/@checked");
		$this->assertElementNotPresent("//input[@id='hk_history_global']/@checked");
		$this->assertVisible('hk_history');
		$this->assertElementPresent("//input[@id='hk_history']/@disabled");

		$this->assertElementPresent("//input[@id='hk_trends_mode']/@checked");
		$this->assertElementNotPresent("//input[@id='hk_trends_global']/@checked");
		$this->assertVisible('hk_trends');
		$this->assertElementPresent("//input[@id='hk_trends']/@disabled");

		$this->assertEquals($oldHashConfig, DBhash($sqlConfig), "Values in some DB fields changed, but shouldn't.");
		DBrestore_tables('config');
	}
}
