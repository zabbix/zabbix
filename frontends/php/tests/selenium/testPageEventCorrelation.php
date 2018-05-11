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

/**
 * @backup correlation
 */
class testPageEventCorrelation extends CWebTest {

	public function testPageEventCorrelation_CheckLayout() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestCheckHeader('Event correlation');
		// Check Create correlation button
		$this->zbxTestAssertElementText("//button[@id='form']", 'Create correlation');
		// Check table headers
		$this->zbxTestTextPresent(['Name', 'Conditions', 'Operations', 'Status']);
		// Check the correlation names in frontend
		$correlations = DBfetchArray(DBSelect('SELECT name FROM correlation'));
		foreach ($correlations as $correlation) {
			$this->zbxTestTextPresent($correlation['name']);
		}
		// Check table footer to make sure that some results are found
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');
	}

	public function testPageEventCorrelation_FilterByName() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestInputType('filter_name', 'for cancel');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", 'Event correlation for cancel');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageEventCorrelation_FilterByEnabled() {
		$this->zbxTestLogin('correlation.php');
		$this->testPageEventCorrelation_FilterReset();
		$this->zbxTestClickXpath("//ul[@id='filter_status']//label[text()='Enabled']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", 'Event correlation for clone');
		$this->zbxTestAssertElementText("//tbody/tr[2]/td[2]/a", 'Event correlation for delete');
		$this->zbxTestAssertElementText("//tbody/tr[3]/td[2]/a", 'Event correlation for update');
		$this->zbxTestTextPresent('Displaying 3 of 3 found');
	}

	public function testPageEventCorrelation_FilterByDisabled() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickXpath("//ul[@id='filter_status']//label[text()='Disabled']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", 'Event correlation for cancel');
		$this->zbxTestTextPresent('Displaying 1 of 1 found');
	}

	public function testPageEventCorrelation_FilterReset() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageEventCorrelation_SingleEnableDisableByLink(){
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		// Enable correlation
		$this->zbxTestClickXpathWait("//a[contains(@onclick,'correlationid[]=99002')]");
		$this->zbxTestTextPresent('Correlation enabled');
		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = 99002 AND status = 0'));
		// Disable correlation
		$this->zbxTestClickXpathWait("//a[contains(@onclick,'correlationid[]=99002')]");
		$this->zbxTestTextPresent('Correlation disabled');
		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = 99002 AND status = 1'));
	}

	public function testPageEventCorrelation_SingleEnableDisableByCheckbox(){
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		// Enable correlation
		$this->zbxTestCheckboxSelect('g_correlationid_99002');
		$this->zbxTestClickButton('correlation.massenable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestTextPresent('Correlation enabled');
		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = 99002 AND status = 0'));
		// Disable correlation
		$this->zbxTestCheckboxSelect('g_correlationid_99002');
		$this->zbxTestClickButton('correlation.massdisable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestTextPresent('Correlation disabled');
		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = 99002 AND status = 1'));
	}

	public function testPageEventCorrelation_MassEnableDisable() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		// Mass enable correlation
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.massenable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestTextPresent('Correlations enabled');
		$this->assertEquals(4, DBcount('SELECT NULL FROM correlation WHERE status = 0'));
		// Mass disable correlation
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.massdisable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestTextPresent('Correlations disabled');
		$this->assertEquals(4, DBcount('SELECT NULL FROM correlation WHERE status = 1'));
	}

	public function testPageEventCorrelation_SingleDelete() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestCheckboxSelect('g_correlationid_99000');
		$this->zbxTestClickButton('correlation.massdelete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestTextPresent('Selected correlations deleted');
		$this->assertEquals(0, DBcount('SELECT NULL FROM correlation WHERE correlationid = 99000'));
	}

	public function testPageEventCorrelation_MassDelete() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.massdelete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestTextPresent('Selected correlations deleted');
		$this->assertEquals(0, DBcount('SELECT NULL FROM correlation'));
	}
}
