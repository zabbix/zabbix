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

	/**
	 * Get text of elements by xpath.
	 *
	 * @param string $xpath	xpath selector
	 *
	 * @return array
	 */
	private function getTextOfElements($xpath) {
		$result = [];

		$elements = $this->webDriver->findElements(WebDriverBy::xpath($xpath));

		foreach ($elements as $element) {
			$result[] = $element->getText();
		}

		return $result;
	}

	private function getDbColumn($sql) {
		$result = [];

		foreach (DBfetchArray(DBSelect($sql)) as $row) {
			$result[] = reset($row);
		}

		return $result;
	}

	public function testPageEventCorrelation_CheckLayout() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestCheckHeader('Event correlation');
		// Check "Create correlation" button.
		$this->zbxTestAssertElementText("//button[@id='form']", 'Create correlation');

		// Check table headers.
		$this->assertEquals(['', 'Name', 'Conditions', 'Operations', 'Status'],
				$this->getTextOfElements("//thead/tr/th")
		);

		// Check the correlation names in frontend
		$corelations = $this->getDbColumn('SELECT name FROM correlation');
		$this->zbxTestTextPresent($corelations);

		// Check table footer to make sure that results are found
		$i = count($corelations);
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
		$this->zbxTestTextNotPresent($this->getDbColumn('SELECT name FROM correlation WHERE name<>'.zbx_dbstr($name)));
	}

	public function testPageEventCorrelation_FilterByEnabled() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickXpath("//ul[@id='filter_status']//label[text()='Enabled']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();

		// Get correlation names from UI after filtering
		$correlations_from_ui = $this->getTextOfElements('//tbody/tr/td[2]/a');
		sort($correlations_from_ui);

		// Get enabled correlation names from DB
		$correlation_names = $this->getDbColumn('SELECT name FROM correlation WHERE status='.ZBX_CORRELATION_ENABLED
				.' ORDER BY name'
		);

		// Compare result from DB and UI
		$this->assertEquals($correlations_from_ui, $correlation_names);

		$count = count($correlation_names);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$count.' of '.$count.' found');
	}

	public function testPageEventCorrelation_FilterByDisabled() {
		$this->zbxTestLogin('correlation.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickXpath("//ul[@id='filter_status']//label[text()='Disabled']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestWaitForPageToLoad();

		// Get correlation names from UI after filtering
		$correlations_from_ui = $this->getTextOfElements('//tbody/tr/td[2]/a');
		sort($correlations_from_ui);

		// Get disabled correlation names from DB
		$correlation_names = $this->getDbColumn('SELECT name FROM correlation WHERE status='.ZBX_CORRELATION_DISABLED
				.' ORDER BY name'
		);

		// Compare result from DB and UI
		$this->assertEquals($correlations_from_ui, $correlation_names);

		$count = count($correlation_names);
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
		$this->zbxTestAssertElementNotPresentXpath("//tr[@class='nothing-to-show']");
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
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

		// Check correlation message.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', ($data['make_status'] === ZBX_CORRELATION_ENABLED)
				? 'Correlation enabled' : 'Correlation disabled'
		);

		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']
				.' AND status ='.$data['make_status'])
		);
	}

	/**
	 * @dataProvider getCorrelationData
	 */
	public function testPageEventCorrelation_SingleEnableDisableByCheckbox($data) {
		$this->zbxTestLogin('correlation.php');
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($data['name'])));
		$this->zbxTestCheckboxSelect('g_correlationid_'.$id['correlationid']);

		$this->zbxTestClickButton(($data['make_status'] === ZBX_CORRELATION_ENABLED)
				? 'correlation.massenable' : 'correlation.massdisable'
		);
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', ($data['make_status'] === ZBX_CORRELATION_ENABLED)
				? 'Correlation enabled' : 'Correlation disabled'
		);

		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(1, DBcount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']
				.' AND status ='.$data['make_status'])
		);
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
