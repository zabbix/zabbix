<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @backup connector, profiles
 *
 * @onBefore prepareData
 *
 */
class testPageConnectors extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	private static $connectors;
	private static $delete_connector = '©∅∩∩∈©⊤∅Ř для удаления';
	private static $update_connector = 'Update connector';

	public static function prepareData() {
		$response = CDataHelper::call('connector.create', [
			[
				'name' => 'Default connector',
				'url' => '{$URL}'
			],
			[
				'name' => 'Connector with Advanced configuration',
				'url' => 'https://zabbix.com:82/v1/history'
			],
			[
				'name' => 'Item value connector',
				'url' => 'http://zabbix.com:82/v1/history',
				'data_type' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES
			],
			[
				'name' => 'Events connector',
				'url' => 'http://zabbix.com:82/v1/events',
				'data_type' => ZBX_CONNECTOR_DATA_TYPE_EVENTS
			],
			[
				'name' => '©∅∩∩∈©⊤∅Ř для удаления',
				'url' => '{$URL}'
			],
			[
				'name' => 'Update connector',
				'url' => '{$URL}'
			],
			[
				'name' => 'Disabled connector',
				'url' => '{$URL}',
				'status' => ZBX_CONNECTOR_STATUS_DISABLED
			],
			[
				'name' => 'Enabled connector',
				'url' => '{$URL}',
				'status' => ZBX_CONNECTOR_STATUS_ENABLED
			],
			[
				'name' => 'ZABBIX',
				'url' => '{$URL}',
				'data_type' => ZBX_CONNECTOR_DATA_TYPE_EVENTS,
				'status' => ZBX_CONNECTOR_STATUS_DISABLED
			]
		]);
		self::$connectors = $response['connectorids'];
	}

	public function testPageConnectors_Layout() {
		$connectors_data = [
			[
				'Name' => 'Connector with Advanced configuration',
				'Data type' => 'Item values',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'Default connector',
				'Data type' => 'Item values',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'Disabled connector',
				'Data type' => 'Item values',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'Enabled connector',
				'Data type' => 'Item values',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'Events connector',
				'Data type' => 'Events',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'Item value connector',
				'Data type' => 'Item values',
				'Status' => 'Enabled'
			],
			[
				'Name' => '©∅∩∩∈©⊤∅Ř для удаления',
				'Data type' => 'Item values',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'Update connector',
				'Data type' => 'Item values',
				'Status' => 'Enabled'
			],
			[
				'Name' => 'ZABBIX',
				'Data type' => 'Events',
				'Status' => 'Disabled'
			]
		];

		$reference_headers = [
			'Name' => true,
			'Data type' => true,
			'Status' => false
		];

		$form_buttons = [
			'Create connector' => true,
			'Apply' => true,
			'Reset' => true,
			'Enable' => false,
			'Disable' => false,
			'Delete' => false
		];

		$connectors_count = count($connectors_data);
		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->page->assertHeader('Connectors');
		$this->page->assertTitle('Connectors');

		// Check status of buttons on the Connectors page.
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
		$this->assertEquals(['Name', 'Status'], $filter_form->getLabels()->asText());

		// Check the count of returned Connectors and the count of selected Connectors.
		$this->assertTableStats($connectors_count);
		$selected_count = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_count->getText());
		$all_connectors = $this->query('id:all_connectors')->asCheckbox()->one();
		$all_connectors->set(true);
		$this->assertEquals($connectors_count.' selected', $selected_count->getText());

		// Check that buttons became enabled.
		foreach (['Enable', 'Disable', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isClickable());
		}

		$all_connectors->set(false);
		$this->assertEquals('0 selected', $selected_count->getText());

		// Check Connectors table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers_text = $table->getHeadersText();

		// Remove empty element from headers array.
		array_shift($headers_text);
		$this->assertSame(array_keys($reference_headers), $headers_text);

		// Check which headers are sortable.
		foreach ($reference_headers as $header => $sortable) {
			$xpath = 'xpath:.//th/a[text()='.CXPathHelper::escapeQuotes($header).']';
			if ($sortable) {
				$this->assertTrue($table->query($xpath)->one()->isClickable());
			}
			else {
				$this->assertFalse($table->query($xpath)->one(false)->isValid());
			}
		}

		// Check Connectors table content.
		$this->assertTableData($connectors_data);
	}

	public function testPageConnectors_ChangeStatus() {
		$this->page->login()->open('zabbix.php?action=connector.list');

		// Disable Connector.
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', self::$update_connector);
		$status = $row->getColumn('Status')->query('xpath:.//a')->one();
		$status->click();
		// Check that Connector is disabled.
		$this->checkConnectorStatus($row, 'disabled', self::$update_connector);

		// Enable Connector.
		$status->click();

		// Check Connector enabled.
		$this->checkConnectorStatus($row, 'enabled', self::$update_connector);

		// Disable Connector via button.
		foreach (['Disable' => 'disabled', 'Enable' => 'enabled'] as $button => $status) {
			$row->select();
			$this->query('button', $button)->one()->waitUntilClickable()->click();
			$this->checkConnectorStatus($row, $status, self::$update_connector);
		}
	}

	/**
	 * Check the status of the Connector in the Connector list table.
	 *
	 * @param CTableRow	$row		Table row that contains the Connector with changed status.
	 * @param string	$expected	Flag that determines if the Connector should be enabled or disabled.
	 * @param string	$connector	Connectors name.
	 */
	private function checkConnectorStatus($row, $expected, $connector) {
		if ($expected === 'enabled') {
			$message_title = 'Connector enabled';
			$column_status = 'Enabled';
			$db_status = '1';
		}
		else {
			$message_title = 'Connector disabled';
			$column_status = 'Disabled';
			$db_status = '0';
		}

		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $message_title);
		CMessageElement::find()->one()->close();
		$this->assertEquals($column_status, $row->getColumn('Status')->getText());
		$this->assertEquals($db_status, CDBHelper::getValue('SELECT status FROM connector WHERE name='.zbx_dbstr($connector)));
	}

	public function getFilterData() {
		return [
			// Name with special symbols.
			[
				[
					'filter' => [
						'Name' => '©∅∩∩∈©⊤∅Ř'
					],
					'expected' => [
						'©∅∩∩∈©⊤∅Ř для удаления'
					]
				]
			],
			// Exact match for field Name.
			[
				[
					'filter' => [
						'Name' => 'Default connector'
					],
					'expected' => [
						'Default connector'
					]
				]
			],
			// Partial match for field Name.
			[
				[
					'filter' => [
						'Name' => 'value'
					],
					'expected' => [
						'Item value connector'
					]
				]
			],
			// Space in search field Name.
			[
				[
					'filter' => [
						'Name' => ' '
					],
					'expected' => [
						'Connector with Advanced configuration',
						'Default connector',
						'Disabled connector',
						'Enabled connector',
						'Events connector',
						'Item value connector',
						'©∅∩∩∈©⊤∅Ř для удаления',
						'Update connector'
					]
				]
			],
			// Partial name match with space between.
			[
				[
					'filter' => [
						'Name' => 'я у'
					],
					'expected' => [
						'©∅∩∩∈©⊤∅Ř для удаления'
					]
				]
			],
			// Partial name match with spaces on the sides.
			[
				[
					'filter' => [
						'Name' => ' with '
					],
					'expected' => [
						'Connector with Advanced configuration'
					]
				]
			],
			// Search should not be case sensitive.
			[
				[
					'filter' => [
						'Name' => 'Item VALUE connector'
					],
					'expected' => [
						'Item value connector'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'zabbix'
					],
					'expected' => [
						'ZABBIX'
					]
				]
			],
			// Wrong name in filter field "Name".
			[
				[
					'filter' => [
						'Name' => 'No data should be returned'
					]
				]
			],
			// Retrieve only Enabled connectors.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'expected' => [
						'Connector with Advanced configuration',
						'Default connector',
						'Enabled connector',
						'Events connector',
						'Item value connector',
						'©∅∩∩∈©⊤∅Ř для удаления',
						'Update connector'
					]
				]
			],
			// Retrieve only Enabled connector with partial name match.
			[
				[
					'filter' => [
						'Name' => 'upd',
						'Status' => 'Enabled'
					],
					'expected' => [
						'Update connector'
					]
				]
			],
			// Apply filtering by status with no results in output.
			[
				[
					'filter' => [
						'Name' => 'Disabled connector',
						'Status' => 'Enabled'
					]
				]
			],
			// Retrieve only Disabled connectors.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'Disabled connector',
						'ZABBIX'
					]
				]
			],
			// Retrieve only Disabled connector with partial name match.
			[
				[
					'filter' => [
						'Name' => 'bb',
						'Status' => 'Disabled'
					],
					'expected' => [
						'ZABBIX'
					]
				]
			],
			// Retrieve Any connector with partial name match.
			[
				[
					'filter' => [
						'Name' => 'connector',
						'Status' => 'Any'
					],
					'expected' => [
						'Connector with Advanced configuration',
						'Default connector',
						'Disabled connector',
						'Enabled connector',
						'Events connector',
						'Item value connector',
						'Update connector'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function  testPageConnectors_Filter($data) {
		$this->page->login()->open('zabbix.php?action=connector.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		if (array_key_exists('expected', $data)) {
			// Using column Name check that only the expected Connectors are returned in the list.
			$this->assertTableDataColumn($data['expected']);
		}
		else {
			// Check that 'No data found.' string is returned if no results are expected.
			$this->assertTableData();
		}

		// Reset filter due to not influence further tests.
		$this->query('button:Reset')->one()->click();
		$this->assertTableStats(count(self::$connectors));
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'ZABBIX',
						'Update connector',
						'©∅∩∩∈©⊤∅Ř для удаления',
						'Item value connector',
						'Events connector',
						'Enabled connector',
						'Disabled connector',
						'Default connector',
						'Connector with Advanced configuration'
					]
				]
			],
			[
				[
					'sort_field' => 'Data type',
					'expected' => [
						'Item values',
						'Item values',
						'Item values',
						'Item values',
						'Item values',
						'Item values',
						'Item values',
						'Events',
						'Events'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSortData
	 */
	public function testPageConnectors_Sort($data) {
		$this->page->login()->open('zabbix.php?action=connector.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="'.$data['sort_field'].'"]')->one();

		foreach(['desc', 'asc'] as $sorting) {
			$expected = ($sorting === 'desc') ? $data['expected'] : array_reverse($data['expected']);
			$header->click();
			$this->assertTableDataColumn($expected, $data['sort_field']);
		}
	}

	public function testPageConnectors_Delete() {
		$this->deleteAction(false);
	}

	public function testPageConnectors_MassDelete() {
		$this->deleteAction(true);
	}

	/**
	 * Function for delete action.
	 *
	 * @param boolean $all	if true delete will perform for all connectors.
	 */
	private function deleteAction($all) {
		$this->page->login()->open('zabbix.php?action=connector.list');

		// Delete Connector.
		if ($all) {
			$this->query('id:all_connectors')->asCheckbox()->one()->set(true);
		}
		else {
			$this->selectTableRows(self::$delete_connector);
		}

		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that Connector is deleted.
		$this->assertMessage(TEST_GOOD, $all ? 'Connectors deleted' : 'Connector deleted');

		$this->assertEquals(0, $all ? CDBHelper::getCount('SELECT connectorid FROM connector')
			: CDBHelper::getCount('SELECT connectorid FROM connector WHERE name='.zbx_dbstr(self::$delete_connector)));
	}
}
