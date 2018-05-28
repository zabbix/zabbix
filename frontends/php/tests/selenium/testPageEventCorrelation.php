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
		$headers = ['', 'Name', 'Conditions', 'Operations', 'Status'];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//thead/tr/th'));
		foreach ($elements as $element) {
			$get_headers[] = $element->getText();
		}
		$this->assertEquals($headers, $get_headers);

		// Check the correlation names in frontend
		$correlations = DBfetchArray(DBSelect('SELECT name FROM correlation'));
		foreach ($correlations as $correlation) {
			$correlation_names[] = $correlation['name'];
		}
		$this->zbxTestTextPresent($correlation_names);

		// Check table footer to make sure that results are found
		$i = DBcount('SELECT NULL FROM correlation');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$i.' of '.$i.' found');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');
	}

	public function testPageEventCorrelation_FilterByName() {
		$name = 'Event correlation for cancel';

		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestInputType('filter_name', 'for cancel');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $name);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 1 of 1 found');
		// Other correlation names are not displayed on frontend
		$correlations = DBfetchArray(DBSelect('SELECT name FROM correlation WHERE name<>'.zbx_dbstr($name)));
		foreach ($correlations as $correlation) {
			$correlation_names[] = $correlation['name'];
		}
		$this->zbxTestTextNotPresent($correlation_names);
	}

	public function testPageEventCorrelation_FilterByEnabled() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickXpath("//ul[@id='filter_status']//label[text()='Enabled']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();

		// Get correlation names from UI after filtering
		$correlations_from_ui = $this->getCorrelationNamesFromTable();

		// Get enabled correlation names from DB
		$sql = 'SELECT name FROM correlation WHERE status='.ZBX_CORRELATION_ENABLED.' ORDER BY name';
		$correlations_from_db = DBfetchArray(DBSelect($sql));
		foreach ($correlations_from_db as $correlation) {
			$correlation_names[] = $correlation['name'];
		}
		// Compare result from DB and UI
		$this->assertEquals($correlations_from_ui, $correlation_names);

		$count = DBcount($sql);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$count.' of '.$count.' found');
	}

	public function testPageEventCorrelation_FilterByDisabled() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickXpath("//ul[@id='filter_status']//label[text()='Disabled']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();

		// Get correlation names from UI after filtering
		$correlations_from_ui = $this->getCorrelationNamesFromTable();

		// Get disabled correlation names from DB
		$sql = 'SELECT name FROM correlation WHERE status='.ZBX_CORRELATION_DISABLED.' ORDER BY name';
		$correlations_from_db = DBfetchArray(DBSelect($sql));
		foreach ($correlations_from_db as $correlation) {
			$correlation_names[] = $correlation['name'];
		}
		// Compare result from DB and UI
		$this->assertEquals($correlations_from_ui, $correlation_names);

		$count = DBcount($sql);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$count.' of '.$count.' found');
	}

	public function testPageEventCorrelation_FilterReset() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckHeader('Event correlation');
		$this->zbxTestInputType('filter_name', 'NONE');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementText("//tr[@class='nothing-to-show']", 'No data found.');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');

		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestAssertElementNotPresentXpath("//tr[@class='nothing-to-show']");
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	/**
	 * Get correlation names from table in UI
	 */
	private function getCorrelationNamesFromTable() {
		$elements = $this->webDriver->findElements(WebDriverBy::xpath('//tbody/tr/td[2]/a'));
		foreach ($elements as $element) {
			$correlations[] = $element->getText();
		}
		sort($correlations);
		return $correlations;
	}

	public static function getCorrelationData() {
		return [
			[
				[
					'name' => 'Event correlation for cancel',
					'make_status' => ZBX_CORRELATION_ENABLED
				]
			],
			[
				[
					'name' => 'Event correlation for cancel',
					'make_status' => ZBX_CORRELATION_DISABLED
				]
			]
		];
	}

	/**
	 * @dataProvider getCorrelationData
	 */
	public function testPageEventCorrelation_SingleEnableDisableByLink($data) {
		$this->zbxTestLogin('correlation.php');
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($data['name'])));
		// Enable or disable correlation by its link
		$this->zbxTestClickXpathWait("//a[contains(@onclick,'correlationid[]=".$id['correlationid']."')]");

		// Check enabled correlation message
		if ($data['make_status']===ZBX_CORRELATION_ENABLED) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation enabled');
		}
		// Check disabled correlation message
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation disabled');
		}

		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']
						.' AND status ='.$data['make_status']));
	}

	/**
	 * @dataProvider getCorrelationData
	 */
	public function testPageEventCorrelation_SingleEnableDisableByCheckbox($data) {
		$this->zbxTestLogin('correlation.php');
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($data['name'])));
		$this->zbxTestCheckboxSelect('g_correlationid_'.$id['correlationid']);

		// Enable correlation
		if ($data['make_status']===ZBX_CORRELATION_ENABLED) {
			$this->zbxTestClickButton('correlation.massenable');
			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation enabled');
		}
		// Disable correlation
		else {
			$this->zbxTestClickButton('correlation.massdisable');
			$this->zbxTestAcceptAlert();
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation disabled');
		}
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']
						.' AND status ='.$data['make_status']));
	}

	public function testPageEventCorrelation_DisableAll() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.massenable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlations enabled');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount('SELECT NULL FROM correlation WHERE status ='.ZBX_CORRELATION_DISABLED));
	}

	public function testPageEventCorrelation_EnableAll() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.massdisable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlations disabled');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount('SELECT NULL FROM correlation WHERE status ='.ZBX_CORRELATION_ENABLED));
	}

	public function testPageEventCorrelation_SingleDelete() {
		$name = 'Event correlation for delete';
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($name)));

		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckboxSelect('g_correlationid_'.$id['correlationid']);
		$this->zbxTestClickButton('correlation.massdelete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Selected correlations deleted');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']));
	}

	public function testPageEventCorrelation_DeleteAll() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.massdelete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Selected correlations deleted');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount('SELECT NULL FROM correlation'));
	}

}
