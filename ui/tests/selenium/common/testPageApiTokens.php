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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * Base class for API tokens page function tests.
 */
class testPageApiTokens extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	/**
	 * Function that checks the layout of the API token list in Administration or User settings section.
	 *
	 * @param array $token_data		Reference array with expected content of the API tokens list table.
	 * @param string $source		Section from which the scenario is executed.
	 */
	public function checkLayout($token_data, $source) {
		if ($source === 'user settings') {
			$url = 'zabbix.php?action=user.token.list';
			$filter_fields = ['Name', 'Expires in less than', 'Status'];
			$tokens_count = CDBHelper::getCount('SELECT tokenid FROM token WHERE userid=1');
			$reference_headers = ['Name', 'Expires at', 'Created at', 'Last accessed at', 'Status'];
		}
		else {
			$url = 'zabbix.php?action=token.list';
			$filter_fields = ['Name', 'Users', 'Expires in less than', 'Created by users', 'Status'];
			$tokens_count = CDBHelper::getCount('SELECT tokenid FROM token');
			$reference_headers = ['Name', 'User', 'Expires at', 'Created at', 'Created by user', 'Last accessed at', 'Status'];
		}

		// Open API tokens page and check header.
		$this->page->login()->open($url);
		$this->page->assertHeader('API tokens');

		// Check status of buttons on the API tokens page.
		$form_buttons = [
			'Create API token' => true,
			'Enable' => false,
			'Disable' => false,
			'Delete' => false
		];

		foreach ($form_buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check displaying and hiding the filter.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('xpath://a[contains(text(), "Filter")]')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

		// Check that all filter fields are present.
		$this->assertEquals($filter_fields, $filter_form->getLabels()->asText());

		// Check the count of returned tokens and the count of selected tokens.
		$this->assertTableStats($tokens_count);
		$selected_count = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_count->getText());
		$all_tokens = $this->query('id:all_tokens')->one()->asCheckbox();
		$all_tokens->set(true);
		$this->assertEquals($tokens_count.' selected', $selected_count->getText());

		// Check that buttons became enabled.
		foreach (['Enable', 'Disable', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled());
		}

		$all_tokens->set(false);
		$this->assertEquals('0 selected', $selected_count->getText());

		// Check tokens table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();

		// Remove empty element from headers array.
		array_shift($headers);
		$this->assertSame($reference_headers, $headers);

		foreach ($headers as $header) {
			$text = CXPathHelper::escapeQuotes($header);
			if ($header === 'Created at') {
				$this->assertFalse($table->query('xpath:.//a[text()='.$text.']')->one(false)->isValid());
			}
			else {
				$this->assertTrue($table->query('xpath:.//a[contains(text(), '.$text.')]')->one()->isClickable());
			}
		}

		// Check parameters of tokens in the token list table.
		$this->assertTableData($token_data);
	}

	/**
	 * Function to checks token status change from API tokens list view.
	 *
	 * @param string $url		URL of the view with the API tokens list.
	 * @param string $token		The name of the token which status is going to be changed.
	 */
	public function checkStatusChange($url, $token) {
		$this->page->login()->open($url);

		// Disable API token.
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $token);
		$status = $row->getColumn('Status')->query('xpath:.//a')->one();
		$status->click();
		// Check token disabled.
		$this->checkTokenStatus($row, 'disabled', $token);

		// Enable API token.
		$status->click();

		// Check token enabled.
		$this->checkTokenStatus($row, 'enabled', $token);

		// Disable API token via button.
		foreach (['Disable' => 'disabled', 'Enable' => 'enabled'] as $button => $status) {
			$row->select();
			$this->query('button', $button)->one()->waitUntilClickable()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			$this->checkTokenStatus($row, $status, $token);
		}
	}

	/**
	 * Function that checks the status of the token in the API token list.
	 *
	 * @param CTableRow	$row		Table row that contains the token with changes status.
	 * @param string	$expected	Flag that determines if the token should be enabled or disabled.
	 * @param string	$token		Token name which status was changed.
	 */
	private function checkTokenStatus($row, $expected, $token) {
		if ($expected === 'enabled') {
			$message_title = 'API token enabled';
			$column_status = 'Enabled';
			$db_status = '0';
		}
		else {
			$message_title = 'API token disabled';
			$column_status = 'Disabled';
			$db_status = '1';
		}

		$this->assertMessage(TEST_GOOD, $message_title );
		$this->assertEquals($column_status, $row->getColumn('Status')->getText());
		$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM token WHERE name='.zbx_dbstr($token)));
	}

	/**
	 * Function that checks filtering of API tokens list.
	 *
	 * @param array	 $data		Data provider.
	 * @param string $source	Section from which the scenario is executed.
	 */
	public function checkFilter($data, $source) {
		if ($source === 'administration') {
			$url = 'zabbix.php?action=token.list';
			$sql = 'SELECT tokenid FROM token';
		}
		else {
			$url = 'zabbix.php?action=user.token.list';
			$sql = 'SELECT tokenid FROM token WHERE userid=1';
		}
		$this->page->login()->open($url);

		// Apply and submit the filter from data provider.
		$form = $this->query('name:zbx_filter')->asForm()->one();

		if (array_key_exists('Expires in less than', $data)) {
			$form->query('xpath:.//label[@for="filter-expires-state"]/span')->asCheckbox()->one()->set(true);
			$form->query('xpath:.//input[@id="filter-expires-days"]')->one()->fill($data['Expires in less than']);
		}

		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'no_data')) {
			$this->assertTableData();
		}
		else {
			// Using token name check that only the expected filters are returned in the list.
			$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected'));
		}

		// Reset the filter and check that all API tokens are displayed.
		$this->query('button:Reset')->one()->click();
		$this->assertTableStats(CDBHelper::getCount($sql));
	}

	/**
	 * Function that check sorting tokens by columns in the API tokens list table.
	 *
	 * @param array  $data	Data provider.
	 * @param string $url	URL of the view with the API tokens list.
	 */
	public function checkSorting($data, $url) {
		$this->page->login()->open($url);
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="'.$data['sort_field'].'"]')->one();

		foreach(['asc', 'desc'] as $sorting) {
			$expected = ($sorting === 'asc') ? $data['expected'] : array_reverse($data['expected']);
			$values = [];

			$header->click();
			foreach ($table->getRows() as $row) {
				$values[] = $row->getColumn($data['sort_field'])->getText();
			}
			$this->assertEquals($expected, $values);
		}
	}

	/**
	 * Function that check deletion of API tokens from the list.
	 *
	 * @param string $url		URL of the view with the API tokens list.
	 * @param string $token		Name of the token to be deleted.
	 */
	public function checkDelete($url, $token) {
		$this->page->login()->open($url);

		// Delete API token.
		$this->query('class:list-table')->asTable()->one()->findRow('Name', $token)->select();
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that token is deleted from DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT tokenid FROM token WHERE name='.zbx_dbstr($token)));
	}
}
