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
 * @backup correlation
 *
 * @onBefore prepareEventData
 */
class testPageEventCorrelation extends CWebTest {

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

	private static $correlation_sql = 'SELECT * FROM correlation ORDER BY correlationid';
	private static $event_old_operations = 'Event correlation for closing old events';
	private static $event_new_operations = 'Event correlation for closing new events';
	private static $event_both_operations = 'Both operations';
	private static $event_hostgroup = 'Event for host group';
	private static $event_pair = 'event tag pair';
	private static $event_old_value = 'Old event tag value';
	private static $event_new_value = 'New event tag value';
	private static $multiple_conditions = 'Conditions';
	private static $event_for_filter = '€√Σnt correlation for filter and deletion';

	public function prepareEventData() {
		CDataHelper::call('correlation.create', [
			[
				'name' => self::$event_old_operations,
				'status' => ZBX_CORRELATION_DISABLED,
				'filter' => [
					'evaltype' => '1',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'old event tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_NEW
					]
				]
			],
			[
				'name' => self::$event_new_operations,
				'filter' => [
					'evaltype' => '0',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG,
							'tag' => 'new event tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => self::$event_both_operations,
				'status' => ZBX_CORRELATION_DISABLED,
				'filter' => [
					'evaltype' => '1',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'old event tag'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_NEW
					],
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => self::$event_hostgroup,
				'filter' => [
					'evaltype' => '0',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
							'groupid' => '19',
							'operator' => '1'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => self::$event_pair,
				'filter' => [
					'evaltype' => '0',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
							'oldtag' => 'event tag old name',
							'newtag' => 'event tag new name'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => self::$event_old_value,
				'filter' => [
					'evaltype' => '0',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
							'tag' => 'old event tag value',
							'value' => '777',
							'operator' => '2'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => self::$event_new_value,
				'filter' => [
					'evaltype' => '0',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
							'tag' => 'new event tag value',
							'value' => 'AAA',
							'operator' => '3'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_NEW
					]
				]
			],
			[
				'name' => self::$multiple_conditions,
				'filter' => [
					'evaltype' => '2',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG,
							'tag' => 'test new event tag'
						],
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'test old event tag'
						],
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
							'groupid' => '19',
							'operator' => '1'
						],
						[
							'type' => ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
							'oldtag' => 'event tag old name',
							'newtag' => 'event tag new name'
						],
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
							'tag' => 'old event tag value',
							'value' => 'test 1',
							'operator' => '2'
						],
						[
							'type' => ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE,
							'tag' => 'new event tag value',
							'value' => 'test 2',
							'operator' => '3'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			],
			[
				'name' => self::$event_for_filter,
				'filter' => [
					'evaltype' => '0',
					'conditions' => [
						[
							'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
							'tag' => 'tag for filter'
						]
					]
				],
				'operations' => [
					[
						'type' => ZBX_CORR_OPERATION_CLOSE_OLD
					]
				]
			]
		]);
	}

	public function getEventData() {
		return [
			[
				[
					[
						'Name' => self::$event_old_operations,
						'Conditions' => 'Old event tag name equals old event tag',
						'Operations' => 'Close new event',
						'Status' => 'Disabled'
					],
					[
						'Name' => self::$event_new_operations,
						'Conditions' => 'New event tag name equals new event tag',
						'Operations' => 'Close old events',
						'Status' => 'Enabled'
					],
					[
						'Name' => self::$event_both_operations,
						'Conditions' => 'Old event tag name equals old event tag',
						'Operations' => 'Close old events'."\n".'Close new event',
						'Status' => 'Disabled'
					],
					[
						'Name' => self::$event_hostgroup,
						'Conditions' => 'New event host group does not equal Applications',
						'Operations' => 'Close old events',
						'Status' => 'Enabled'
					],
					[
						'Name' => self::$event_pair,
						'Conditions' => 'Value of old event tag event tag old name equals value of new event tag event tag new name',
						'Operations' => 'Close old events',
						'Status' => 'Enabled'
					],
					[
						'Name' => self::$event_old_value,
						'Conditions' => 'Value of old event tag old event tag value contains 777',
						'Operations' => 'Close old events',
						'Status' => 'Enabled'
					],
					[
						'Name' => self::$event_new_value,
						'Conditions' => 'Value of new event tag new event tag value does not contain AAA',
						'Operations' => 'Close new event',
						'Status' => 'Enabled'
					],
					[
						'Name' => self::$multiple_conditions,
						'Conditions' => 'Old event tag name equals test old event tag'."\n".
								'New event tag name equals test new event tag'."\n".
								'New event host group does not equal Applications'."\n".
								'Value of old event tag event tag old name equals value of new event tag event tag new name'."\n".
								'Value of old event tag old event tag value contains test 1'."\n".
								'Value of new event tag new event tag value does not contain test 2',
						'Operations' => 'Close old events',
						'Status' => 'Enabled'
					],
					[
						'Name' => self::$event_for_filter,
						'Conditions' => 'Old event tag name equals tag for filter',
						'Operations' => 'Close old events',
						'Status' => 'Enabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getEventData
	 */
	public function testPageEventCorrelation_Layout($data) {
		$event_count = count($data);

		$this->page->login()->open('zabbix.php?action=correlation.list');
		$this->page->assertTitle('Event correlation rules');
		$this->page->assertHeader('Event correlation');

		// Check buttons on the Event correlation page.
		$this->assertEquals(3, $this->query('button', ['Create event correlation', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$this->assertEquals(0, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check displaying and hiding the filter.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('link:Filter')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

		// Check filter labels and default values.
		$this->assertEquals(['Name', 'Status'], $filter_form->getLabels()->asText());
		$filter_form->checkValue(['Name' => '', 'Status' => 'Any']);
		$this->assertEquals('255', $filter_form->getField('Name')->getAttribute('maxlength'));

		// Check the count of returned events and the count of selected events.
		$this->assertTableStats($event_count);
		$this->assertSelectedCount(0);
		$all_events = $this->query('id:all_items')->asCheckbox()->one();
		$all_events->check();
		$this->assertSelectedCount($event_count);

		// Check that buttons became enabled.
		$this->assertEquals(3, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$all_events->uncheck();
		$this->assertSelectedCount(0);

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name', 'Conditions', 'Operations', 'Status'], $table->getHeadersText());

		// Check sortable headers.
		$this->assertEquals(['Name', 'Status'], $table->getHeaders()->query('tag:a')->asText());

		// Check Event correlation table content.
		$this->assertTableHasData($data);
	}

	public function getFilterData() {
		return [
			// Name with special symbols.
			[
				[
					'filter' => [
						'Name' => '€√Σnt'
					],
					'expected' => [
						self::$event_for_filter
					]
				]
			],
			// Exact match for field Name.
			[
				[
					'filter' => [
						'Name' => 'Conditions'
					],
					'expected' => [
						self::$multiple_conditions
					]
				]
			],
			// Partial match for field Name.
			[
				[
					'filter' => [
						'Name' => 'host'
					],
					'expected' => [
						self::$event_hostgroup
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
						self::$event_both_operations,
						self::$event_new_operations,
						self::$event_old_operations,
						self::$event_hostgroup,
						self::$event_pair,
						self::$event_new_value,
						self::$event_old_value,
						self::$event_for_filter
					]
				]
			],
			// Partial name match with space between.
			[
				[
					'filter' => [
						'Name' => 'h o'
					],
					'expected' => [
						self::$event_both_operations
					]
				]
			],
			// Partial name match with spaces on the sides.
			[
				[
					'filter' => [
						'Name' => ' closing '
					],
					'expected' => [
						self::$event_new_operations,
						self::$event_old_operations
					]
				]
			],
			// Search should not be case sensitive.
			[
				[
					'filter' => [
						'Name' => 'Both OPERATIONS'
					],
					'expected' => [
						self::$event_both_operations
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
			// Retrieve only Enabled event correlations.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'expected' => [
						self::$multiple_conditions,
						self::$event_new_operations,
						self::$event_hostgroup,
						self::$event_pair,
						self::$event_new_value,
						self::$event_old_value,
						self::$event_for_filter
					]
				]
			],
			// Retrieve only Enabled event correlations with partial name match.
			[
				[
					'filter' => [
						'Name' => 'relation',
						'Status' => 'Enabled'
					],
					'expected' => [
						self::$event_new_operations,
						self::$event_for_filter
					]
				]
			],
			// Apply filtering by status with no results in output.
			[
				[
					'filter' => [
						'Name' => 'Disabled event correlation',
						'Status' => 'Enabled'
					]
				]
			],
			// Retrieve only Disabled event correlations.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						self::$event_both_operations,
						self::$event_old_operations
					]
				]
			],
			// Retrieve only Disabled event correlation with partial name match.
			[
				[
					'filter' => [
						'Name' => 'opera',
						'Status' => 'Disabled'
					],
					'expected' => [
						self::$event_both_operations
					]
				]
			],
			// Retrieve Any event correlations with partial name match.
			[
				[
					'filter' => [
						'Name' => 'event',
						'Status' => 'Any'
					],
					'expected' => [
						self::$event_new_operations,
						self::$event_old_operations,
						self::$event_hostgroup,
						self::$event_pair,
						self::$event_new_value,
						self::$event_old_value
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageEventCorrelation_Filter($data) {
		$this->page->login()->open('zabbix.php?action=correlation.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		// Check that expected Event correlations are returned in the list.
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

		// Reset filter due to not influence further tests.
		$this->query('button:Reset')->one()->click();
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						self::$event_for_filter,
						self::$event_old_value,
						self::$event_new_value,
						self::$event_pair,
						self::$event_hostgroup,
						self::$event_old_operations,
						self::$event_new_operations,
						self::$multiple_conditions,
						self::$event_both_operations
					]
				]
			],
			[
				[
					'sort_field' => 'Status',
					'expected' => [
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Enabled',
						'Disabled',
						'Disabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSortData
	 */
	public function testPageEventCorrelation_Sort($data) {
		$this->page->login()->open('zabbix.php?action=correlation.list');
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
					'name' => self::$event_both_operations
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
					'name' => self::$event_for_filter
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
					'name' => self::$event_for_filter
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testPageEventCorrelation_CancelAction($data) {
		$old_hash = CDBHelper::getHash(self::$correlation_sql);
		if (!is_array(CTestArrayHelper::get($data, 'name', []))) {
			$data['name'] = [$data['name']];
		}

		$this->page->login()->open('zabbix.php?action=correlation.list');

		// Events count that will be selected before Enable/Disable/Delete action.
		$selected_count = array_key_exists('name', $data) ? count($data['name']) : CDBHelper::getCount(self::$correlation_sql);
		$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
		$this->assertSelectedCount($selected_count);
		$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();

		$message = $data['action'].' selected event correlation'.(count(CTestArrayHelper::get($data, 'name', [])) === 1 ? '?' : 's?' );
		$this->assertEquals($message, $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->waitUntilReady();

		$this->assertSelectedCount((array_key_exists('link_button', $data)) ? 0 : $selected_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$correlation_sql));
	}

	public function getStatusData() {
		return [
			[
				[
					'link_button' => true,
					'action' => 'Disable',
					'name' => self::$event_for_filter
				]
			],
			[
				[
					'link_button' => true,
					'action' => 'Enable',
					'name' => self::$event_both_operations
				]
			],
			[
				[
					'action' => 'Enable',
					'name' => self::$event_old_operations
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => self::$event_new_operations
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => [
						self::$multiple_conditions,
						self::$event_pair
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
						self::$event_old_value,
						self::$event_new_value
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
	public function testPageEventCorrelation_ChangeStatus($data) {
		$this->page->login()->open('zabbix.php?action=correlation.list');

		// Event correlation(s) count that will be enabled or disabled via button.
		if (!is_array(CTestArrayHelper::get($data, 'name', []))) {
			$data['name'] = [$data['name']];
		}

		$selected_count = array_key_exists('name', $data) ? count($data['name']) : CDBHelper::getCount(self::$correlation_sql);
		$plural = count(CTestArrayHelper::get($data, 'name', [])) === 1 ? '' : 's';

		if (array_key_exists('link_button', $data)) {
			// Disable or enable event via Enabled/Disabled button.
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'][0]);
			$row->getColumn('Status')->query('xpath:.//a')->one()->click();
		}
		else {
			$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
			$this->assertSelectedCount($selected_count);
			$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();

			// Check alert message.
			$this->assertEquals($data['action'].' selected event correlation'.$plural.'?', $this->page->getAlertText());
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
		}

		// Check success message.
		$this->assertMessage(TEST_GOOD, 'Event correlation'.$plural.' '.lcfirst($data['action']).'d');
		CMessageElement::find()->one()->close();

		// Check that status in 'Status' column is correct.
		if (array_key_exists('link_button', $data)) {
			$this->assertEquals($data['action'].'d', $row->getColumn('Status')->getText());
		}

		// Check status in database.
		$status = ($data['action'] === 'Enable') ? ZBX_CORRELATION_ENABLED : ZBX_CORRELATION_DISABLED;
		if (array_key_exists('name', $data)) {
			$this->assertEquals($status, CDBHelper::getValue('SELECT status FROM correlation WHERE name IN ('.
					CDBHelper::escape($data['name']).')')
			);
		}
		else {
			$this->assertEquals($selected_count, CDBHelper::getCount('SELECT NULL FROM correlation WHERE status='.$status));
		}

		// Verify that there is no selected event correlations.
		$this->assertSelectedCount(0);
	}

	public function testPageEventCorrelation_Delete() {
		$this->deleteAction([self::$event_for_filter]);
	}

	public function testPageEventCorrelation_MassDelete() {
		$this->deleteAction();
	}

	/**
	 * Function for delete action.
	 *
	 * @param array $names	event correlation names, if empty delete will perform for all events
	 */
	private function deleteAction($names = []) {
		$plural = (count($names) === 1) ? '' : 's';
		$all = CDBHelper::getCount(self::$correlation_sql);
		$this->page->login()->open('zabbix.php?action=correlation.list');

		// Delete event correlation(s).
		$this->selectTableRows($names);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected event correlation'.$plural.'?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that event correlation(s) is/are deleted.
		$this->assertMessage(TEST_GOOD, 'Event correlation'.$plural.' deleted');
		$this->assertSelectedCount(0);
		$this->assertTableStats($names === [] ? 0 : $all - count($names));
		$this->assertEquals(0, ($names === []) ? CDBHelper::getCount(self::$correlation_sql)
			: CDBHelper::getCount('SELECT NULL FROM correlation WHERE name IN ('.CDBHelper::escape($names).')'));
	}
}
