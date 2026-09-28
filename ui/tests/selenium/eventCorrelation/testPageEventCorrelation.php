<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup correlation
 *
 * @onBefore prepareEventData
 */
class testPageEventCorrelation extends CWebTest {

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

	const CORRELATION_SQL = 'SELECT * FROM correlation ORDER BY correlationid';
	const CEP_SQL = 'SELECT NULL FROM cep_rule';
	const EVENT_OLD_OPERATIONS = 'Event correlation for closing old events';
	const EVENT_NEW_OPERATIONS = 'Event correlation for closing new events';
	const EVENT_BOTH_OPERATIONS = 'Both operations';
	const EVENT_HOSTGROUP = 'Event for host group';
	const EVENT_PAIR = 'event tag pair';
	const EVENT_OLD_VALUE = 'Old event tag value';
	const EVENT_NEW_VALUE = 'New event tag value';
	const MULTIPLE_CONDITIONS = 'Conditions';
	const EVENT_WITH_SEVERAL_SPACES = '📌€√Σnt correlation for   filter   and deletion';
	const EVENT_WITHOUT_SEVERAL_SPACES = '📌€√Σnt correlation for filter and deletion';

	public function prepareEventData() {
		CDataHelper::call('correlation.create', [
			[
				'name' => self::EVENT_OLD_OPERATIONS,
				'status' => ZBX_CORRELATION_DISABLED,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND,
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
				'name' => self::EVENT_NEW_OPERATIONS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
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
				'name' => self::EVENT_BOTH_OPERATIONS,
				'status' => ZBX_CORRELATION_DISABLED,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND,
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
				'name' => self::EVENT_HOSTGROUP,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
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
				'name' => self::EVENT_PAIR,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
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
				'name' => self::EVENT_OLD_VALUE,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
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
				'name' => self::EVENT_NEW_VALUE,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
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
				'name' => self::MULTIPLE_CONDITIONS,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_OR,
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
				'name' => self::EVENT_WITH_SEVERAL_SPACES,
				'filter' => [
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
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
						'Name' => self::EVENT_BOTH_OPERATIONS,
						'Type' => 'Event correlation',
						'Conditions' => 'Old event tag name equals old event tag',
						'Time window processing' => '',
						'Operations' => 'Close old events'."\n".'Close new event',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => 'Close problems older than 2 weeks',
						'Type' => 'Complex event processing',
						'Conditions' => 'Tag value dc equals north',
						'Time window processing' => 'Simple',
						'Operations' => 'Execute when Event evicted: Close',
						'Stop after this rule' => 'Enabled',
						'Sort order' => '1',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => self::MULTIPLE_CONDITIONS,
						'Type' => 'Event correlation',
						'Conditions' => 'Old event tag name equals test old event tag'."\n".
								'New event tag name equals test new event tag'."\n".
								'New event host group does not equal Applications'."\n".
								'Value of old event tag event tag old name equals value of new event tag event tag new name'."\n".
								'Value of old event tag old event tag value contains test 1'."\n".
								'Value of new event tag new event tag value does not contain test 2',
						'Time window processing' => '',
						'Operations' => 'Close old events',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
					],
					[
						'Name' => 'Deduplicate repeating events by keeping only the cause problem',
						'Type' => 'Complex event processing',
						'Conditions' => 'Tag value net equals infra',
						'Time window processing' => 'Cause and symptoms grouping',
						'Operations' => 'Execute when Event occurred: Discard',
						'Stop after this rule' => 'Enabled',
						'Sort order' => '1',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => 'Detect missing \'communication\' events',
						'Type' => 'Complex event processing',
						'Conditions' => 'Host group equals remote',
						'Time window processing' => 'Pattern match',
						'Operations' => 'Execute when Event occurred: Set name Risk detected: Communication down'."\n".
								'Execute when Pattern matched: Clone first',
						'Stop after this rule' => 'Enabled',
						'Sort order' => '1',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => 'Detect physical security incident based on event pattern match',
						'Type' => 'Complex event processing',
						'Conditions' => 'Host group equals west',
						'Time window processing' => 'Pattern match',
						'Operations' => 'Execute when Event occurred: Set name Engine issue'."\n".
								'Execute when Pattern matched: Clone first',
						'Stop after this rule' => 'Enabled',
						'Sort order' => '1',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_NEW_OPERATIONS,
						'Type' => 'Event correlation',
						'Conditions' => 'New event tag name equals new event tag',
						'Time window processing' => '',
						'Operations' => 'Close old events',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_OLD_OPERATIONS,
						'Type' => 'Event correlation',
						'Conditions' => 'Old event tag name equals old event tag',
						'Time window processing' => '',
						'Operations' => 'Close new event',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_HOSTGROUP,
						'Type' => 'Event correlation',
						'Conditions' => 'New event host group does not equal Applications',
						'Time window processing' => '',
						'Operations' => 'Close old events',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_PAIR,
						'Type' => 'Event correlation',
						'Conditions' => 'Value of old event tag event tag old name equals value of new event tag event tag new name',
						'Time window processing' => '',
						'Operations' => 'Close old events',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
					],
					[
						'Name' => 'Increase event severity and normalize tags',
						'Type' => 'Complex event processing',
						'Conditions' => 'Host group equals payments',
						'Time window processing' => 'None',
						'Operations' => 'Execute when Event occurred: Set env:prod'."\n".
								'Execute when Event occurred: Increase severity',
						'Stop after this rule' => 'Enabled',
						'Sort order' => '1',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_NEW_VALUE,
						'Type' => 'Event correlation',
						'Conditions' => 'Value of new event tag new event tag value does not contain AAA',
						'Time window processing' => '',
						'Operations' => 'Close new event',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_OLD_VALUE,
						'Type' => 'Event correlation',
						'Conditions' => 'Value of old event tag old event tag value contains 777',
						'Time window processing' => '',
						'Operations' => 'Close old events',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
					],
					[
						'Name' => 'Reduce problem noise by grouping events',
						'Type' => 'Complex event processing',
						'Conditions' => 'Tag value net equals infra'."\n".'Host group equals network',
						'Time window processing' => 'Cause and symptoms grouping',
						'Operations' => 'Execute when Window closed: Close'."\n".'Execute when Window closed: Increase severity',
						'Stop after this rule' => 'Enabled',
						'Sort order' => '1',
						'Status' => 'Disabled',
						'Info' => ''
					],
					[
						'Name' => self::EVENT_WITHOUT_SEVERAL_SPACES,
						'Type' => 'Event correlation',
						'Conditions' => 'Old event tag name equals tag for filter',
						'Time window processing' => '',
						'Operations' => 'Close old events',
						'Stop after this rule' => '',
						'Sort order' => '',
						'Status' => 'Enabled',
						'Info' => ''
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

		$this->page->login()->open('zabbix.php?action=ceprule.list');
		$this->page->assertTitle('Configuration of event processing rules');
		$this->page->assertHeader('Event processing');

		// Check buttons on the Event correlation page.
		$this->assertEquals(4, $this->query('button', ['Create complex event processing', 'Create event correlation',
				'Apply', 'Reset'])->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		$this->assertEquals(0, $this->query('button', ['Enable', 'Disable', 'Delete'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check displaying and hiding the filter.
		$filter = CFilterElement::find()->one();
		$filter_form = $filter->getForm();
		$this->assertEquals('Filter', $filter->getSelectedTabName());
		// Check that filter is expanded by default.
		$this->assertTrue($filter->isExpanded());
		// Check that filter is collapsing/expanding on click.
		foreach ([false, true] as $status) {
			$filter->expand($status);
			$this->assertTrue($filter->isExpanded($status));
		}

		// Check filter labels and default values.
		$this->assertEquals(['Name', 'Status', 'Type'], $filter_form->getLabels()->asText());
		$filter_form->checkValue(['Name' => '', 'Status' => 'All', 'Type' => 'All']);
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
		$this->assertEquals(['', 'Name', 'Type', 'Conditions', 'Time window processing', 'Operations',
				'Stop after this rule', 'Sort order', 'Status', 'Info'], $table->getHeadersText()
		);

		// Check sortable headers.
		$this->assertEquals(['Name', 'Sort order', 'Status'], $table->getHeaders()->query('tag:a')->asText());

		// Check Event correlation table content.
		$this->assertTableHasData($data);

		// Check that the filter is still expanded after page refresh.
		$this->page->refresh()->waitUntilReady();
		$this->assertTrue($filter->isExpanded());
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
						self::EVENT_WITHOUT_SEVERAL_SPACES
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
						self::MULTIPLE_CONDITIONS
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
						self::EVENT_HOSTGROUP
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
						self::EVENT_BOTH_OPERATIONS,
						'Close problems older than 2 weeks',
						'Deduplicate repeating events by keeping only the cause problem',
						'Detect missing \'communication\' events',
						'Detect physical security incident based on event pattern match',
						self::EVENT_NEW_OPERATIONS,
						self::EVENT_OLD_OPERATIONS,
						self::EVENT_HOSTGROUP,
						self::EVENT_PAIR,
						'Increase event severity and normalize tags',
						self::EVENT_NEW_VALUE,
						self::EVENT_OLD_VALUE,
						'Reduce problem noise by grouping events',
						self::EVENT_WITHOUT_SEVERAL_SPACES
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
						self::EVENT_BOTH_OPERATIONS
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
						self::EVENT_NEW_OPERATIONS,
						self::EVENT_OLD_OPERATIONS
					]
				]
			],
			// Multiple spaces in name field.
			[
				[
					'filter' => [
						'Name' => '   '
					],
					'expected' => [self::EVENT_WITHOUT_SEVERAL_SPACES]
				]
			],
			// Search should not be case sensitive.
			[
				[
					'filter' => [
						'Name' => 'Both OPERATIONS'
					],
					'expected' => [
						self::EVENT_BOTH_OPERATIONS
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
						self::MULTIPLE_CONDITIONS,
						self::EVENT_NEW_OPERATIONS,
						self::EVENT_HOSTGROUP,
						self::EVENT_PAIR,
						self::EVENT_NEW_VALUE,
						self::EVENT_OLD_VALUE,
						self::EVENT_WITHOUT_SEVERAL_SPACES
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
						self::EVENT_NEW_OPERATIONS,
						self::EVENT_WITHOUT_SEVERAL_SPACES
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
						self::EVENT_BOTH_OPERATIONS,
						'Close problems older than 2 weeks',
						'Deduplicate repeating events by keeping only the cause problem',
						'Detect missing \'communication\' events',
						'Detect physical security incident based on event pattern match',
						self::EVENT_OLD_OPERATIONS,
						'Increase event severity and normalize tags',
						'Reduce problem noise by grouping events'
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
						self::EVENT_BOTH_OPERATIONS
					]
				]
			],
			// Retrieve Any event correlations with partial name match.
			[
				[
					'filter' => [
						'Name' => 'event',
						'Status' => 'All'
					],
					'expected' => [
						'Deduplicate repeating events by keeping only the cause problem',
						'Detect missing \'communication\' events',
						'Detect physical security incident based on event pattern match',
						self::EVENT_NEW_OPERATIONS,
						self::EVENT_OLD_OPERATIONS,
						self::EVENT_HOSTGROUP,
						self::EVENT_PAIR,
						'Increase event severity and normalize tags',
						self::EVENT_NEW_VALUE,
						self::EVENT_OLD_VALUE,
						'Reduce problem noise by grouping events'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageEventCorrelation_Filter($data) {
		$this->page->login()->open('zabbix.php?action=ceprule.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$table = $this->getTable();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$table->waitUntilReloaded();
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
						self::EVENT_WITHOUT_SEVERAL_SPACES,
						'Reduce problem noise by grouping events',
						self::EVENT_OLD_VALUE,
						self::EVENT_NEW_VALUE,
						'Increase event severity and normalize tags',
						self::EVENT_PAIR,
						self::EVENT_HOSTGROUP,
						self::EVENT_OLD_OPERATIONS,
						self::EVENT_NEW_OPERATIONS,
						'Detect physical security incident based on event pattern match',
						'Detect missing \'communication\' events',
						'Deduplicate repeating events by keeping only the cause problem',
						self::MULTIPLE_CONDITIONS,
						'Close problems older than 2 weeks',
						self::EVENT_BOTH_OPERATIONS
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
						'Disabled',
						'Disabled',
						'Disabled',
						'Disabled',
						'Disabled',
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
		$this->page->login()->open('zabbix.php?action=ceprule.list');
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
					'name' => self::EVENT_BOTH_OPERATIONS
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
					'name' => self::EVENT_WITHOUT_SEVERAL_SPACES
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
					'name' => self::EVENT_WITHOUT_SEVERAL_SPACES
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testPageEventCorrelation_CancelAction($data) {
		$old_hash = CDBHelper::getHash(self::CORRELATION_SQL);

		if (!is_array(CTestArrayHelper::get($data, 'name', []))) {
			$data['name'] = [$data['name']];
		}

		$this->page->login()->open('zabbix.php?action=ceprule.list');

		// Events count that will be selected before Enable/Disable/Delete action.
		$selected_count = (array_key_exists('name', $data))
			? count($data['name'])
			: CDBHelper::getCount(self::CORRELATION_SQL) + CDBHelper::getCount(self::CEP_SQL);

		$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
		$this->assertSelectedCount($selected_count);
		$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();

		$message = $data['action'].' selected complex event processing rule'.($selected_count === 1 ? '?' : 's?');
		$this->assertEquals($message, $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->waitUntilReady();

		$this->assertSelectedCount($selected_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::CORRELATION_SQL));
	}

	public function getStatusData() {
		return [
			[
				[
					'link_button' => true,
					'action' => 'Disable',
					'name' => self::EVENT_WITH_SEVERAL_SPACES
				]
			],
			[
				[
					'link_button' => true,
					'action' => 'Enable',
					'name' => self::EVENT_BOTH_OPERATIONS
				]
			],
			[
				[
					'action' => 'Enable',
					'name' => self::EVENT_OLD_OPERATIONS
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => self::EVENT_NEW_OPERATIONS
				]
			],
			[
				[
					'action' => 'Disable',
					'name' => [
						self::MULTIPLE_CONDITIONS,
						self::EVENT_PAIR
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
						self::EVENT_OLD_VALUE,
						self::EVENT_NEW_VALUE
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
		$this->page->login()->open('zabbix.php?action=ceprule.list');

		// Event correlation(s) count that will be enabled or disabled via button.
		if (!is_array(CTestArrayHelper::get($data, 'name', []))) {
			$data['name'] = [$data['name']];
		}

		$selected_count = (array_key_exists('name', $data))
			? count($data['name'])
			: CDBHelper::getCount(self::CORRELATION_SQL) + CDBHelper::getCount(self::CEP_SQL);
		$plural = ($selected_count === 1) ? '' : 's';

		if (array_key_exists('link_button', $data)) {
			// Disable or enable event via Enabled/Disabled button.
			$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'][0]);
			$row->getColumn('Status')->query('tag:a')->one()->click();
		}
		else {
			$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
			$this->assertSelectedCount($selected_count);
			$this->query('button:'.$data['action'])->one()->waitUntilClickable()->click();

			// Check alert message.
			$this->assertEquals($data['action'].' selected complex event processing rule'.$plural.'?', $this->page->getAlertText());
			$this->page->acceptAlert();
			$this->page->waitUntilReady();
		}

		// Check success message.
		$this->assertMessage(TEST_GOOD, 'Event processing rule'.$plural.' '.lcfirst($data['action']).'d');
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
			$this->assertEquals(CDBHelper::getCount(self::CORRELATION_SQL),
					CDBHelper::getCount('SELECT NULL FROM correlation WHERE status='.$status)
			);
		}

		// Verify that there is no selected event correlations.
		$this->assertSelectedCount(0);
	}

	public function testPageEventCorrelation_Delete() {
		$this->deleteAction([self::EVENT_WITHOUT_SEVERAL_SPACES]);
	}

	public function testPageEventCorrelation_MassDelete() {
		$this->deleteAction();
	}

	/**
	 * Function for delete action.
	 *
	 * @param array $names	event correlation names, if empty delete will perform for all events
	 */
	protected function deleteAction($names = []) {
		$plural = (count($names) === 1) ? '' : 's';
		$all = CDBHelper::getCount(self::CORRELATION_SQL) + CDBHelper::getCount(self::CEP_SQL);
		$this->page->login()->open('zabbix.php?action=ceprule.list');

		// Delete event correlation(s).
		$this->selectTableRows($names);
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals('Delete selected complex event processing rule'.$plural.'?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Check that event correlation(s) is/are deleted.
		$this->assertMessage(TEST_GOOD, 'Event processing rule'.$plural.' deleted');
		$this->assertSelectedCount(0);
		$this->assertTableStats($names === [] ? 0 : $all - count($names));
		$this->assertEquals(0, ($names === [])
			? CDBHelper::getCount(self::CORRELATION_SQL)
			: CDBHelper::getCount('SELECT NULL FROM correlation WHERE name IN ('.CDBHelper::escape($names).')')
		);
	}
}
