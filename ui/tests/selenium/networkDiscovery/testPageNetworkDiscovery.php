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
 * @backup drules
 *
 * @dataSource NetworkDiscovery
 */
class testPageNetworkDiscovery extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * SQL query which selects all values from table drules.
	 */
	const SQL = 'SELECT * FROM drules';

	/**
	 * Function which checks layout of Network Discovery page.
	 */
	public function testPageNetworkDiscovery_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$this->page->assertTitle('Configuration of discovery rules');
		$this->page->assertHeader('Discovery rules');
		$this->assertEquals(['', 'Name', 'IP range', 'Proxy', 'Interval', 'Checks', 'Status'], $table->getHeadersText());
		$this->assertEquals(['Name', 'Status'], $form->getLabels()->asText());

		$this->assertEquals(['Any', 'Enabled', 'Disabled'], $form->getField('Status')->asSegmentedRadio()
				->getLabels()->asText()
		);

		// Check if default enabled buttons are clickable.
		$buttons = [
			'Create discovery rule' => true,
			'Apply' => true,
			'Reset' => true,
			'Enable' => false,
			'Disable' => false,
			'Delete' => false
		];
		foreach ($buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check if default disabled buttons are not clickable.
		$this->assertEquals(0, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check if filter collapses/ expands.
		foreach (['true', 'false'] as $status) {
			$this->assertTrue($this->query('xpath://li[@aria-expanded='.CXPathHelper::escapeQuotes($status).']')
					->one()->isPresent()
			);
			$this->query('id:ui-id-1')->one()->click();
		}

		// Check if fields "Name" length is as expected.
		$this->assertEquals(255, $form->query('xpath:.//input[@name="filter_name"]')->one()->getAttribute('maxlength'));

		/**
		 * Check if counter displays correct number of rows and check if previously disabled buttons are enabled,
		 * upon selecting discovery rules.
		 */
		$this->assertTableStats(12);
		$this->assertSelectedCount(0);
		$this->query('id:all_drules')->asCheckbox()->one()->check();
		$this->assertSelectedCount(12);
		foreach (['Enable', 'Disable', 'Delete'] as $buttons) {
			$this->assertTrue($this->query('button:'.$buttons)->one()->isEnabled());
		}
	}

	/**
	 * Function which checks sorting by Name column.
	 */
	public function testPageNetworkDiscovery_CheckSorting() {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=ASC')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$column_values = $this->getTableColumnData('Name');

		foreach (['desc', 'asc'] as $sorting) {
			$table->query('xpath:.//a[text()="Name"]')->one()->click();
			$expected = ($sorting === 'asc') ? $column_values : array_reverse($column_values);
			$this->assertEquals($expected, $this->getTableColumnData('Name'));
		}
	}
	public function getFilterData() {
		return [
			[
				[
					'filter' => [
						'Name' => 'network'
					],
					'expected' => [
						'Local network',
						'External network'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => '',
						'Status' => 'Enabled'
					],
					'expected' => [
						'External network',
						'Discovery rule for update',
						'Discovery rule for cancelling scenario'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => '',
						'Status' => 'Disabled'
					],
					'expected' => [
						'Local network',
						'Discovery rule to check delete',
						'Discovery rule for successful deleting',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Disabled discovery rule for update',
						"<img src=\"x\" onerror=\"alert('UWAGA');\"/>"
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => ''
					],
					'expected' => [
						'Local network',
						'External network',
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Discovery rule for successful deleting',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Discovery rule for cancelling scenario',
						'Disabled discovery rule for update',
						"<img src=\"x\" onerror=\"alert('UWAGA');\"/>"
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'RULE'
					],
					'expected' => [
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Discovery rule for successful deleting',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Discovery rule for cancelling scenario',
						'Disabled discovery rule for update'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Disco'
					],
					'expected' => [
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Discovery rule for successful deleting',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Discovery rule for cancelling scenario',
						'Disabled discovery rule for update'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => '/, >, ;....'
					],
					'expected' => [
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => '    '
					],
					'expected' => [
					]
				]
			]
		];
	}

	/**
	 * Check Network Discovery pages filter.
	 *
	 * @dataProvider getFilterData
	 */
	public function testPageNetworkDiscovery_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');
		$this->query('button:Reset')->one()->click();
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected'));
		$this->assertTableStats(12);
	}

	public static function getNetworkDiscoveryActionData () {
		return [
			// Single network discovery change of status to Enabled by link.
			[
				[
					'name' => 'Local network',
					'default' => DRULE_STATUS_DISABLED,
					'link' => true,
					'single' => true
				]
			],
			// Single network discovery change of status to Disabled by link.
			[
				[
					'name' => 'External network',
					'default' => DRULE_STATUS_ACTIVE,
					'link' => true,
					'single' => true
				]
			],
			// Single network discovery's row Enabling.
			[
				[
					'name' => 'External network',
					'single' => true,
					'cancel' => false,
					'action' => 'Enable'
				]
			],
			// Single network discovery's row Disabling.
			[
				[
					'name' => 'External network',
					'single' => true,
					'cancel' => false,
					'action' => 'Disable'
				]
			],
			// Cancel action for single discovery rules row, which started disable action.
			[
				[
					'name' =>'External network',
					'single' => true,
					'cancel' => true,
					'action' => 'Disable'
				]
			],
			// Cancel disable action for single discovery rule.
			[
				[
					'name' => [
						'External network'
					],
					'single' => true,
					'cancel' => true,
					'action' => 'Disable'
				]
			],
			// Cancel delete action for single discovery rule.
			[
				[
					'name' => 'External network',
					'single' => true,
					'cancel' => true,
					'action' => 'Delete'
				]
			],
			// Enable action for multiple discovery rules.
			[
				[
					'name' => [
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Disabled discovery rule for update'
					],
					'action' => 'Enable'
				]
			],
			// Disable action for multiple discovery rules.
			[
				[
					'name' => [
						'Local network',
						'External network',
						'Discovery rule to check delete'
					],
					'action' => 'Disable'
				]
			],
			// Cancel disable action for multiple discovery rules.
			[
				[
					'name' => [
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Disabled discovery rule for update'
					],
					'cancel' => true,
					'action' => 'Disable'
				]
			],
			// Cancel enable action for multiple discovery rules.
			[
				[
					'name' => [
						'Local network',
						'External network',
						'Discovery rule to check delete'
					],
					'cancel' => true,
					'action' => 'Enable'
				]
			],
			// Cancel delete action for multiple discovery rules.
			[
				[
					'name' => [
						'Local network',
						'External network',
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Disabled discovery rule for update',
						"<img src=\"x\" onerror=\"alert('UWAGA');\"/>"
					],
					'cancel' => true,
					'action' => 'Delete'
				]
			],
			// Delete action for single discovery rule.
			[
				[
					'name' => 'External network',
					'single' => true,
					'cancel' => false,
					'action' => 'Delete'
				]
			],
			// Delete action for multiple discovery rules.
			[
				[
					'name' => [
						'Local network',
						'Discovery rule to check delete',
						'Discovery rule for update',
						'Disabled discovery rule for update',
						"<img src=\"x\" onerror=\"alert('UWAGA');\"/>"
					],
					'cancel' => false,
					'action' => 'Delete'
				]
			]
		];
	}

	/**
	 * @dataProvider getNetworkDiscoveryActionData
	 */
	public function testPageNetworkDiscovery_Actions($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();
		$count = CDBHelper::getCount(self::SQL);

		if(array_key_exists('link', $data)) {
			$row = $table->findRow('Name', $data['name']);
			$row->getColumn('Status')->query('xpath:.//a')->one()->click();
			if ($data['default'] === DRULE_STATUS_DISABLED) {
				$this->assertMessage(TEST_GOOD, 'Discovery rule enabled');
				$this->assertEquals('Enabled', $row->getColumnData('Status', DRULE_STATUS_ACTIVE));
			}
			else {
				$this->assertMessage(TEST_GOOD, 'Discovery rule disabled');
				$this->assertEquals('Disabled', $row->getColumnData('Status', DRULE_STATUS_DISABLED));
			}
		}
		else {
			if(array_key_exists('single', $data)) {
				$plural = '';
				$this->selectTableRows($data['name']);
				$this->assertSelectedCount(1);
			}
			else {
				$plural = 's';
				$table->findRows('Name', $data['name'])->select();
				$this->assertSelectedCount(count($data['name']));
			}
			$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();
			$this->assertEquals($data['action'].' selected discovery rule'.$plural.'?', $this->page->getAlertText());

			if(array_key_exists('cancel', $data)) {
				$this->page->dismissAlert();
				$this->page->waitUntilReady();

				if(array_key_exists('single', $data)) {
					$this->assertSelectedCount(1);
				}
				else {
					$this->assertSelectedCount(count($data['name']));
				}
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			}
			else {
				$this->page->acceptAlert();
				$this->page->waitUntilReady();
				$this->assertMessage(TEST_GOOD, 'Discovery rule'.$plural.' '.lcfirst($data['action']).'d');
				CMessageElement::find()->one()->close();
				if ($data['action'] === 'Delete') {
					$this->assertSelectedCount(0);
					$this->assertTableStats($data['single'] === true ? $count - 1 : 0);
					$this->assertEquals(0, ($data['single'] === true)
						? CDBHelper::getCount('SELECT NULL FROM drules WHERE name IN ('.CDBHelper::escape($data['name']).')')
						: CDBHelper::getCount('SELECT NULL FROM drules')
					);
				}
			}
		}
	}
}
