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
 * @backup drules
 *
 * @onBefore prepareDiscoveryErrorData
 *
 * @dataSource NetworkDiscovery, Proxies
 */
class testPageNetworkDiscovery extends CWebTest {

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

	/**
	 * SQL query which selects all values from table drules.
	 */
	const SQL = 'SELECT * FROM drules';

	const CUSTOM_ERROR_RULE = 'Discovery rule for testing error';
	const SYMBOLS_ERROR_RULE = 'Discovery rule with error (4-byte symbols)';
	const DISABLED_ERROR_RULE = 'Disabled discovery rule for testing error';

	const ERROR_MESSAGES = [
		self::CUSTOM_ERROR_RULE => 'Test error message for a discovery rule.',
		self::SYMBOLS_ERROR_RULE => 'Test error message with symbols: ᵭᵻᵴᶜṌṼẻṜὙ🛑😐🗼🁨☕',
		self::DISABLED_ERROR_RULE => 'Error for discovery rule with disabled status'
	];

	public function prepareDiscoveryErrorData() {
		CDataHelper::call('drule.create', [
			[
				'name' => self::CUSTOM_ERROR_RULE,
				'iprange' => '192.168.1.1-255',
				'status' => DRULE_STATUS_ACTIVE,
				'dchecks' => [
					[
						'type' => SVC_HTTP
					]
				]
			],
			[
				'name' => self::SYMBOLS_ERROR_RULE,
				'iprange' => '192.168.1.1-255',
				'status' => DRULE_STATUS_ACTIVE,
				'dchecks' => [
					[
						'type' => SVC_HTTP
					]
				]
			],
			[
				'name' => self::DISABLED_ERROR_RULE,
				'iprange' => '192.168.1.1-255',
				'status' => DRULE_STATUS_DISABLED,
				'dchecks' => [
					[
						'type' => SVC_HTTP
					]
				]
			]
		]);

		// Add custom error messages using database.
		foreach (array_keys(self::ERROR_MESSAGES) as $rule_name) {
			DBexecute('UPDATE drules SET error='.zbx_dbstr(self::ERROR_MESSAGES[$rule_name]).' WHERE name='.
					zbx_dbstr($rule_name)
			);
		}
	}

	/**
	 * Function which checks layout of Network Discovery page.
	 */
	public function testPageNetworkDiscovery_Layout() {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$this->page->assertTitle('Configuration of discovery rules');
		$this->page->assertHeader('Discovery rules');
		$this->assertEquals(['', 'Name', 'IP range', 'Proxy', 'Interval', 'Checks', 'Status', 'Info'],
				$table->getHeadersText()
		);
		$this->assertEquals(['Name'], $table->getSortableHeaders()->asText());
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
		$filter_tab = CFilterElement::find()->one()->setContext(CFilterElement::CONTEXT_RIGHT);
		$this->assertTrue($filter_tab->isExpanded());

		// Check that filter is collapsing/expanding on click.
		foreach ([false, true] as $status) {
			$filter_tab->expand($status);
			$this->assertTrue($filter_tab->isExpanded($status));
		}

		// Check if fields "Name" length is as expected.
		$this->assertEquals(255, $form->query('xpath:.//input[@name="filter_name"]')->one()->getAttribute('maxlength'));

		// Check the presence and data of the error hintboxes for a specific rows.
		foreach ([self::CUSTOM_ERROR_RULE, self::SYMBOLS_ERROR_RULE] as $rule_name) {
			$table->findRow('Name', $rule_name)->getColumn('Info')->query('button')->one()->click();
			$hintbox = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent();

			$this->assertEquals(self::ERROR_MESSAGES[$rule_name], $hintbox->one()->getText());
			$hintbox->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click()->waitUntilNotPresent();
		}

		// Check that correct amount of hintboxes is present in the table.
		$this->assertEquals(2, $table->query('class:rel-container')->all()->count());

		/**
		 * Check if counter displays correct number of rows and check if previously disabled buttons are enabled,
		 * upon selecting discovery rules.
		 */
		$rules_count = CDBHelper::getCount('SELECT * FROM drules');
		$this->assertTableStats($rules_count);
		$this->assertSelectedCount(0);
		$this->query('id:all_drules')->asCheckbox()->one()->check();
		$this->assertSelectedCount($rules_count);

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
						self::SYMBOLS_ERROR_RULE,
						'Discovery rule for update',
						self::CUSTOM_ERROR_RULE,
						'Discovery rule for proxy delete test',
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
						self::DISABLED_ERROR_RULE,
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
						self::SYMBOLS_ERROR_RULE,
						'Discovery rule to check delete',
						'Discovery rule for update',
						self::CUSTOM_ERROR_RULE,
						'Discovery rule for successful deleting',
						'Discovery rule for proxy delete test',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Discovery rule for cancelling scenario',
						'Disabled discovery rule for update',
						self::DISABLED_ERROR_RULE,
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
						self::SYMBOLS_ERROR_RULE,
						'Discovery rule to check delete',
						'Discovery rule for update',
						self::CUSTOM_ERROR_RULE,
						'Discovery rule for successful deleting',
						'Discovery rule for proxy delete test',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Discovery rule for cancelling scenario',
						'Disabled discovery rule for update',
						self::DISABLED_ERROR_RULE
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Disco'
					],
					'expected' => [
						self::SYMBOLS_ERROR_RULE,
						'Discovery rule to check delete',
						'Discovery rule for update',
						self::CUSTOM_ERROR_RULE,
						'Discovery rule for successful deleting',
						'Discovery rule for proxy delete test',
						'Discovery rule for deleting, used in Action',
						'Discovery rule for deleting, check used in Action',
						'Discovery rule for clone',
						'Discovery rule for changing checks',
						'Discovery rule for cancelling scenario',
						'Disabled discovery rule for update',
						self::DISABLED_ERROR_RULE
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => '    '
					],
					'expected' => []
				]
			],
			[
				[
					'filter' => [
						'Name' => '/, >, ;....'
					],
					'expected' => []
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
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected'));
		$this->assertTableStats(count($data['expected']));
		$this->query('button:Reset')->one()->click();
	}

	/**
	 * Check Network Discovery pages reset buttons functionality.
	 */
	public function testPageNetworkDiscovery_ResetButton() {
		$this->page->login()->open('zabbix.php?action=discovery.list&sort=name&sortorder=DESC');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableColumnData('Name');

		// Filling fields with needed discovery rules information.
		$form->fill(['id:filter_name' => 'External network']);
		$form->submit();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// Checking that filtered discovery rule matches expected.
		$this->assertTableDataColumn(['External network']);

		// After pressing reset button, check that previous discovery rules are displayed again.
		$form->query('button:Reset')->one()->click();

		$reset_count =  $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_count);
		$this->assertTableStats($reset_count);
		$this->assertEquals($start_contents, $this->getTableColumnData('Name'));
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
			// Single network discovery with error change of status to Enabled by link.
			[
				[
					'name' => self::DISABLED_ERROR_RULE,
					'default' => DRULE_STATUS_DISABLED,
					'link' => true,
					'single' => true,
					'hintbox' => self::ERROR_MESSAGES[self::DISABLED_ERROR_RULE]
				]
			],
			// Single network discovery with error change of status to Disabled by link.
			[
				[
					'name' => self::SYMBOLS_ERROR_RULE,
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
					'action' => 'Enable'
				]
			],
			// Single network discovery's row Disabling.
			[
				[
					'name' => 'External network',
					'single' => true,
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
			// Disable action for discovery rule with error hintbox.
			[
				[
					'name' => self::CUSTOM_ERROR_RULE,
					'single' => true,
					'action' => 'Disable'
				]
			],
			// Enable action for discovery rule with error hintbox.
			[
				[
					'name' => self::CUSTOM_ERROR_RULE,
					'single' => true,
					'action' => 'Enable',
					'hintbox' => self::ERROR_MESSAGES[self::CUSTOM_ERROR_RULE]
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
					'action' => 'Delete'
				]
			],
			// Delete action for discovery rule which is used in action.
			[
				[
					'name' => [
						'Discovery rule for deleting, used in Action'
					],
					'single' => true,
					'action' => 'Delete',
					'error' => 'Cannot delete discovery rule "Discovery rule for deleting, used in Action": '.
							'action "Action with discovery rule" uses this discovery rule.'
				]
			],
			// Delete action for discovery rule check which is used in action.
			[
				[
					'name' => [
						'Discovery rule for deleting, check used in Action'
					],
					'single' => true,
					'action' => 'Delete',
					'error' => 'Cannot delete discovery check "Telnet (15)" of discovery rule '.
							'"Discovery rule for deleting, check used in Action"'.
							': action "Action with discovery check" uses this discovery check.'
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

		if (array_key_exists('link', $data)) {
			$row = $table->findRow('Name', $data['name']);
			$row->getColumn('Status')->query('xpath:.//a')->one()->click();

			$this->assertMessage(TEST_GOOD, 'Discovery rule '.($data['default'] ? 'enabled' : 'disabled'));
			$this->assertEquals($data['default'] ? 'Enabled' : 'Disabled',
					$row->getColumnData('Status', $data['default'] ? DRULE_STATUS_ACTIVE : DRULE_STATUS_DISABLED)
			);

			if ($data['default'] === DRULE_STATUS_DISABLED && array_key_exists('hintbox', $data)) {
				$this->assertEquals($data['hintbox'], $table->findRow('Name', $data['name'])->getColumn('Info')
						->query('button')->one()->getAttribute('data-hintbox-contents')
				);
			}
			else {
				$this->assertFalse($table->findRow('Name', $data['name'])->getColumn('Info')
						->query('class:rel-container')->exists()
				);
			}
		}
		else {
			$this->selectTableRows($data['name']);
			$this->assertSelectedCount(CTestArrayHelper::get($data, 'single', false) ? 1 : count($data['name']));
			$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();
			$this->assertEquals($data['action'].' selected discovery '.
					(CTestArrayHelper::get($data, 'single', false) ? 'rule' : 'rules').'?',
					$this->page->getAlertText()
			);

			if (array_key_exists('cancel', $data)) {
				$this->page->dismissAlert();
				$this->page->waitUntilReady();
				$this->assertSelectedCount((array_key_exists('single', $data) ? 1 : count($data['name'])));
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			}
			else {
				$this->page->acceptAlert();
				$this->page->waitUntilReady();

				if (array_key_exists('error', $data)) {
					$this->assertMessage(TEST_BAD, 'Cannot delete discovery rule', $data['error']);
				}
				else {
					$this->assertMessage(TEST_GOOD, 'Discovery '.
							(CTestArrayHelper::get($data, 'single', false) ? 'rule' : 'rules').
							' '.lcfirst($data['action']).'d'
					);
				}

				if (array_key_exists('hintbox', $data)) {
					$this->assertEquals($data['hintbox'], $table->findRow('Name', $data['name'])->getColumn('Info')
							->query('button')->one()->getAttribute('data-hintbox-contents')
					);
				}

				if ($data['action'] === 'Disable') {
					$names = (array_key_exists('single', $data)) ? [$data['name']] : $data['name'];

					foreach ($names as $name) {
						$this->assertFalse($table->findRow('Name', $name)->getColumn('Info')
								->query('class:rel-container')->exists()
						);
					}
				}

				CMessageElement::find()->one()->close();

				if ($data['action'] === 'Delete' && !array_key_exists('error', $data)) {
					$this->assertSelectedCount(0);
					$this->assertTableStats(CTestArrayHelper::get($data, 'single', false) === true
						? $count - 1
						: $count - count($data['name'])
					);
					if (CTestArrayHelper::get($data, 'single', false) === true) {
						$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE name IN ('.
								CDBHelper::escape($data['name']).')')
						);
					}
					else {
						$this->assertEquals($count - count($data['name']), CDBHelper::getCount('SELECT *FROM drules'));
					}
				}
			}
		}
	}
}
