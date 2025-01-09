<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup connector, profiles
 *
 * @onBefore prepareData
 *
 */
class testPageConnectors extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	private static $connectors;
	private static $connector_sql = 'SELECT * FROM connector ORDER BY connectorid';
	private static $delete_connector = 'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř';
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
				'name' => 'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř',
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
				'Name' => 'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř',
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
		$connectors_count = count($connectors_data);

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->page->assertHeader('Connectors');
		$this->page->assertTitle('Connectors');

		// Check  enabled/disabled buttons on the Connectors page.
		$this->assertEquals(3, $this->query('button', ['Create connector', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$this->assertEquals(0, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check displaying and hiding the filter.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('xpath://a[text()="Filter"]')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

		// Check filter fields.
		$this->assertEquals(['Name', 'Status'], $filter_form->getLabels()->asText());
		$filter_form->checkValue(['Name' => '', 'Status' => 'Any']);
		$this->assertEquals('255', $filter_form->getField('Name')->getAttribute('maxlength'));

		// Check the count of returned Connectors and the count of selected Connectors.
		$this->assertTableStats($connectors_count);
		$this->assertSelectedCount(0);
		$all_connectors = $this->query('id:all_connectors')->asCheckbox()->one();
		$all_connectors->check();
		$this->assertSelectedCount($connectors_count);

		// Check that buttons became enabled.
		$this->assertEquals(3, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$all_connectors->uncheck();
		$this->assertSelectedCount(0);

		// Check Connectors table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers_text = $table->getHeadersText();

		// Remove empty element from headers array.
		array_shift($headers_text);
		$this->assertSame(['Name', 'Data type', 'Status'], $headers_text);

		// Check which headers are sortable.
		foreach ($table->getHeaders() as $header) {
			if ($header->query('tag:a')->one(false)->isValid()) {
				$sortable[] = $header->getText();
			}
		}
		$this->assertEquals(['Name', 'Data type'], $sortable);

		// Check Connectors table content.
		$this->assertTableHasData($connectors_data);
	}

	public function getFilterData() {
		return [
			// Name with special symbols.
			[
				[
					'filter' => [
						'Name' => '⊏∅∩∩∈©⊤∅Ř'
					],
					'expected' => [
						'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř'
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
						'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř',
						'Default connector',
						'Disabled connector',
						'Enabled connector',
						'Events connector',
						'Item value connector',
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
						'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř'
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
						'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř',
						'Default connector',
						'Enabled connector',
						'Events connector',
						'Item value connector',
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
						'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř',
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
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		// Check that expected Connectors are returned in the list.
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

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
						'Item value connector',
						'Events connector',
						'Enabled connector',
						'Disabled connector',
						'Default connector',
						'Connector для удаления - ⊏∅∩∩∈©⊤∅Ř',
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
		$header = $table->query('link', $data['sort_field'])->one();

		foreach(['desc', 'asc'] as $sorting) {
			$expected = ($sorting === 'desc') ? $data['expected'] : array_reverse($data['expected']);
			$header->click();
			$this->assertTableDataColumn($expected, $data['sort_field']);
		}
	}

	public function getCancelData() {
		return [
			[
				[
					'action' => 'Enable'
				]
			],
			[
				[
					'action' => 'Enable',
					'name' => 'Disabled connector'
				]
			],
			[
				[
					'action' => 'Disable'
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => self::$update_connector
				]
			],
			[
				[
					'action' => 'Delete'
				]
			],
			[
				[
					'action' => 'Delete',
					'name' => self::$delete_connector
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testPageConnectors_CancelAction($data) {
		$old_hash = CDBHelper::getHash(self::$connector_sql);
		if (!is_array(CTestArrayHelper::get($data, 'name', []))) {
			$data['name'] = [$data['name']];
		}

		$this->page->login()->open('zabbix.php?action=connector.list');

		// Connectors count that will be selected before Enable/Disable/Delete action.
		$selected_count = array_key_exists('name', $data) ? count($data['name']) : CDBHelper::getCount(self::$connector_sql);
		$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
		$this->assertSelectedCount($selected_count);
		$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();

		$message = $data['action'].' selected connector'.(count(CTestArrayHelper::get($data, 'name', [])) === 1 ? '?' : 's?' );
		$this->assertEquals($message, $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->waitUntilReady();

		$this->assertSelectedCount((array_key_exists('link_button', $data)) ? 0 : $selected_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$connector_sql));
	}

	public function getStatusData() {
		return [
			[
				[
					'link_button' => true,
					'action' => 'Disable',
					'name' => self::$update_connector
				]
			],
			[
				[
					'link_button' => true,
					'action' => 'Enable',
					'name' => 'Disabled connector'
				]
			],
			[
				[
					'action' => 'Enable',
					'name' => 'ZABBIX'
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => 'Enabled connector'
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => [
						'Events connector',
						'Item value connector'
					]
				]
			],
			[
				[
					'action' => 'Disable'
				]
			],
			[
				[
					'action' => 'Enable',
					'name' => [
						self::$update_connector,
						self::$delete_connector
					]
				]
			],
			[
				[
					'action' => 'Enable'
				]
			]
		];
	}

	/**
	 * @dataProvider getStatusData
	 */
	public function testPageConnectors_ChangeStatus($data) {
		$this->page->login()->open('zabbix.php?action=connector.list');

		// Connectors count that will be enabled or disabled via button.
		if (!is_array(CTestArrayHelper::get($data, 'name', []))) {
			$data['name'] = [$data['name']];
		}
		$selected_count = array_key_exists('name', $data) ? count($data['name']) : CDBHelper::getCount(self::$connector_sql);
		$plural = count(CTestArrayHelper::get($data, 'name', [])) === 1 ? '' : 's';

		if (array_key_exists('link_button', $data)) {
			// Disable or enable Connector via Enabled/Disabled button.
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'][0]);
			$row->getColumn('Status')->query('xpath:.//a')->one()->click();
		}
		else {
			$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
			$this->assertSelectedCount($selected_count);
			$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();
		}

		// Check alert and success message.
		if (!array_key_exists('link_button', $data)) {
			$this->assertEquals($data['action'].' selected connector'.$plural.'?', $this->page->getAlertText());
			$this->page->acceptAlert();
		}
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Connector'.$plural.' '.lcfirst($data['action']).'d');
		CMessageElement::find()->one()->close();

		// Check that status in 'Status' column is correct.
		if (array_key_exists('link_button', $data)) {
			$this->assertEquals($data['action'].'d', $row->getColumn('Status')->getText());
		}

		// Check status in database.
		$status = ($data['action'] === 'Enable') ? ZBX_CONNECTOR_STATUS_ENABLED : ZBX_CONNECTOR_STATUS_DISABLED;
		if (array_key_exists('name', $data)) {
			$this->assertEquals($status, CDBHelper::getValue('SELECT status FROM connector WHERE name IN ('.
					CDBHelper::escape($data['name']).')')
			);
		}
		else {
			$this->assertEquals($selected_count, CDBHelper::getCount('SELECT NULL FROM connector WHERE status='.$status));
		}

		// Verify that there is no selected connectors.
		$this->assertSelectedCount(0);
	}

	public function testPageConnectors_Delete() {
		$this->deleteAction([self::$delete_connector]);
	}

	public function testPageConnectors_MassDelete() {
		$this->deleteAction();
	}

	/**
	 * Function for delete action.
	 *
	 * @param array $names	connector names, if empty delete will perform for all connectors
	 */
	private function deleteAction($names = []) {
		$plural = (count($names) === 1) ? '' : 's';
		$all = CDBHelper::getCount(self::$connector_sql);
		$this->page->login()->open('zabbix.php?action=connector.list');

		// Delete Connector(s).
		$this->selectTableRows($names);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected connector'.$plural.'?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that Connector(s) is/are deleted.
		$this->assertMessage(TEST_GOOD, 'Connector'.$plural.' deleted');
		$this->assertSelectedCount(0);
		$this->assertTableStats($names === [] ? 0 : $all - count($names));
		$this->assertEquals(0, ($names === []) ? CDBHelper::getCount(self::$connector_sql)
				: CDBHelper::getCount('SELECT NULL FROM connector WHERE name IN ('.CDBHelper::escape($names).')')
		);
	}
}
