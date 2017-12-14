<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * Tests for "Configuration -> Maintenance".
 *
 * Forms:
 * - Create maintenance.
 * - Clone maintenance.
 * - Delete maintenance.
 *
 * @backup maintenances
 */
class testFormMaintenance extends CWebTest {

	/**
	 * Create maintenace period "DEV-718 Test maintenance".
	 */
	public function testFormMaintenance_Create() {
		$this->zbxTestLogin('maintenance.php?ddreset=1');
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestCheckHeader('Maintenance periods');
		$this->zbxTestClick('form');
		$this->zbxTestWaitForPageToLoad();

		// Fill "name" field value.
		$this->zbxTestInputTypeOverwrite('mname', 'DEV-718 Test maintenance');

		// "Periods" tab.
		$this->zbxTestTabSwitchById('tab_periodsTab', 'Periods');
		// Add "One time only" maintenance period.
		$this->zbxTestClickButtonText('New');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr/td',
			'One time only'
		);
		// Add "Daily" maintenance period.
		$this->zbxTestClickButtonText('New');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestDropdownSelectWait('new_timeperiod_timeperiod_type', 'Daily');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[2]/td',
			'Daily'
		);
		// Add "Weekly" maintenance period with "Monday" and "Sunday".
		$this->zbxTestClickButtonText('New');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestDropdownSelectWait('new_timeperiod_timeperiod_type', 'Weekly');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestClick('new_timeperiod_dayofweek_mo');
		$this->zbxTestClick('new_timeperiod_dayofweek_su');
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[3]/td',
			'Weekly'
		);
		$text = $this->zbxTestGetText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[3]/td[2]');
		$this->assertRegexp('/Monday/', $text);
		$this->assertRegexp('/Sunday/', $text);
		// Add "Monthly" maintenace period with "January" and "November".
		$this->zbxTestClickButtonText('New');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestDropdownSelectWait('new_timeperiod_timeperiod_type', 'Monthly');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestClick('new_timeperiod_month_jan');
		$this->zbxTestClick('new_timeperiod_month_nov');
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestAssertElementText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[4]/td',
			'Monthly'
		);
		$text = $this->zbxTestGetText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[4]/td[2]');
		$this->assertRegexp('/January/', $text);
		$this->assertRegexp('/November/', $text);

		// "Hosts & Groups" tab.
		$this->zbxTestTabSwitchById('tab_hostTab', 'Hosts & Groups');
		$this->zbxTestDropdownSelect('groupids_right', 'Zabbix servers');
		$this->zbxTestClickXpath('(//button[@id=\'add\'])[2]');

		// Create maintenance.
		$this->zbxTestClickXpath('(//button[@id=\'add\'])[3]');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Maintenance added');
	}

	/**
	 * Update "DEV-718 Test maintenance" maintenance.
	 */
	public function testFormMaintenance_Update() {
		$this->zbxTestLogin('maintenance.php?ddreset=1');
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestCheckHeader('Maintenance periods');
		// Filter by "Dev-718 Test maintenance'.
		$this->zbxTestInputTypeOverwrite('filter_name', 'DEV-718 Test maintenance');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickLinkText('DEV-718 Test maintenance');
		$this->zbxTestWaitForPageToLoad();

		// "Maintenance" tab.
		$this->zbxTestClickXpath('//label[contains(text(), \'No data collection\')]');
		// "Periods" tab.
		$this->zbxTestTabSwitchById('tab_periodsTab', 'Periods');
		// Remove "One time only".
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[4]');
		$this->zbxTestWaitForPageToLoad();
		// Edit "Weekly".
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[5]');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClick('new_timeperiod_dayofweek_we');
		$this->zbxTestClick('new_timeperiod_dayofweek_fr');
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[9]');
		$this->zbxTestWaitForPageToLoad();
		$text = $this->zbxTestGetText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[2]/td[2]');
		$this->assertRegexp('/Monday/', $text);
		$this->assertRegexp('/Wednesday/', $text);
		$this->assertRegexp('/Friday/', $text);
		$this->assertRegexp('/Sunday/', $text);
		// Edit "Monthly".
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[7]');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClick('new_timeperiod_month_sep');
		$this->zbxTestClick('new_timeperiod_month_jun');
		$this->zbxTestClickXpath('//label[contains(text(), \'Day of week\')]');
		$this->zbxTestClickXpath('//label[contains(text(), \'Wednesday\')]');
		$this->zbxTestClickXpath('(//button[@type=\'button\'])[9]');
		$this->zbxTestWaitForPageToLoad();
		$text = $this->zbxTestGetText('//ul[@id=\'maintenancePeriodFormList\']/li/div[2]/div/table/tbody/tr[3]/td[2]');
		$this->assertRegexp('/Wednesday/', $text);
		$this->assertRegexp('/January/', $text);
		$this->assertRegexp('/June/', $text);
		$this->assertRegexp('/September/', $text);
		$this->assertRegexp('/November/', $text);

		$this->zbxTestClick('update');
		$this->zbxTestWaitForPageToLoad();

		// Wait until message "Maintenance updated" is shown.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Maintenance updated');
		$this->zbxTestAssertElementText('//td[3]', 'No data collection');
		// Reset filter.
		$this->zbxTestClickButtonText('Reset');
	}

	/**
	 * Clone maintenance period "DEV-718 Test maintenance" to "DEV-718 Test maintenance (clone)".
	 */
	public function testFormMaintenance_Clone() {
		$this->zbxTestLogin('maintenance.php?ddreset=1');
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestCheckHeader('Maintenance periods');

		// Find item to clone.
		$this->zbxTestClickLinkText('DEV-718 Test maintenance');
		$this->zbxTestWaitForPageToLoad();

		// Click on element with id "clone".
		$this->zbxTestClick('clone');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestInputTypeOverwrite('mname', 'DEV-718 Test maintenance (clone)');

		// Create maintenance.
		$this->zbxTestClickXpath('(//button[@id=\'add\'])[3]');
		$this->zbxTestWaitForPageToLoad();

		// Wait until message "Maintenance added" is shown.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Maintenance added');
	}

	/**
	 * Delete cloned maintenance "DEV-718 Test maintenance (clone)".
	 */
	public function testFormMaintenance_Delete() {
		$this->zbxTestLogin('maintenance.php?ddreset=1');
		$this->zbxTestCheckTitle('Configuration of maintenance periods');
		$this->zbxTestCheckHeader('Maintenance periods');

		$this->zbxTestClickLinkText('DEV-718 Test maintenance (clone)');
		$this->zbxTestWaitForPageToLoad();

		// Click on element with id "delete" and accept alert.
		$this->zbxTestClickAndAcceptAlert('delete');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Maintenance deleted');
	}
}
