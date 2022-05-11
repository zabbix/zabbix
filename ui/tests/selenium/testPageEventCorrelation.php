<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup correlation
 */
class testPageEventCorrelation extends CLegacyWebTest {

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

		foreach (CDBHelper::getAll($sql) as $row) {
			$result[] = reset($row);
		}

		return $result;
	}

	public function testPageEventCorrelation_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestCheckTitle('Event correlation rules');
		$this->zbxTestCheckHeader('Event correlation');
		// Check "Create correlation" button.
		$this->zbxTestAssertElementText("//button[@data-url='zabbix.php?action=correlation.edit']", 'Create correlation');

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

		$this->zbxTestLogin('zabbix.php?action=correlation.list');
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
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
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
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
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
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
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
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($data['name'])));
		// Enable or disable correlation by its link
		$this->zbxTestClickXpathWait("//a[contains(@onclick,'correlationids%5B0%5D=".$id['correlationid']."')]");

		// Check correlation message.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', ($data['make_status'] === ZBX_CORRELATION_ENABLED)
				? 'Correlation enabled' : 'Correlation disabled'
		);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']
				.' AND status ='.$data['make_status'])
		);
	}

	/**
	 * @dataProvider getCorrelationData
	 */
	public function testPageEventCorrelation_SingleEnableDisableByCheckbox($data) {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($data['name'])));
		$this->zbxTestCheckboxSelect('correlationids_'.$id['correlationid']);

		$this->zbxTestClickButton(($data['make_status'] === ZBX_CORRELATION_ENABLED)
				? 'correlation.enable' : 'correlation.disable'
		);
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', ($data['make_status'] === ZBX_CORRELATION_ENABLED)
				? 'Correlation enabled' : 'Correlation disabled'
		);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']
				.' AND status ='.$data['make_status'])
		);
	}

	public function testPageEventCorrelation_DisableAll() {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.enable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlations enabled');
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM correlation WHERE status ='.ZBX_CORRELATION_DISABLED));
	}

	public function testPageEventCorrelation_EnableAll() {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.disable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlations disabled');
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM correlation WHERE status ='.ZBX_CORRELATION_ENABLED));
	}

	public function testPageEventCorrelation_SingleDelete() {
		$name = 'Event correlation for delete';
		$id = DBfetch(DBselect('SELECT correlationid FROM correlation WHERE name='.zbx_dbstr($name)));

		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestCheckboxSelect('correlationids_'.$id['correlationid']);
		$this->zbxTestClickButton('correlation.delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlation deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM correlation WHERE correlationid = '.$id['correlationid']));
	}

	public function testPageEventCorrelation_DeleteAll() {
		$this->zbxTestLogin('zabbix.php?action=correlation.list');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('correlation.delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Correlations deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM correlation'));
	}
}
