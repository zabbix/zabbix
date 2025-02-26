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
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';

/**
 * @backup profiles
 *
 * @onBefore prepareProblemsData
 *
 * @dataSource UserPermissions, WidgetCommunication
 */
class testPageProblems extends CWebTest {

	/**
	 * Attach TagBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:filter-tags_0'
			]
		];
	}

	protected static $time;

	public function prepareProblemsData() {
		/**
		 * Change refresh interval so Problems page doesn't refresh automatically,
		 * and popup dialogs don't disappear.
		 */
		DBexecute('UPDATE users SET refresh=999 WHERE username='.zbx_dbstr('Admin'));

		// Create host group for hosts with item and trigger.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Problems Page']]);

		// Create host.
		$result = CDataHelper::createHosts([
			[
				'host' => 'Host for Problems Page',
				'groups' => [['groupid' => $hostgroups['groupids'][0]]],
				'items' => [
					[
						'name' => 'Age problem item',
						'key_' => 'trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'String in operational data',
						'key_' => 'trap1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_TEXT
					],
					[
						'name' => 'Symbols in Item metric',
						'key_' => 'trap2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_TEXT
					],
					[
						'name' => 'XSS text',
						'key_' => 'trapXSS',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_TEXT
					],
					[
						'name' => 'SQL Injection',
						'key_' => 'trapSQL',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_TEXT
					]
				]
			]
		]);

		// Create trigger based on item.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for Age problem',
				'expression' => 'last(/Host for Problems Page/trap)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Trigger for Age problem 1 day',
				'expression' => 'last(/Host for Problems Page/trap)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'manual_close' => 1
			],
			[
				'description' => 'Filled opdata with macros',
				'expression' => 'last(/Host for Problems Page/trap)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'opdata' => 'Operational data - {ITEM.LASTVALUE}, {ITEM.LASTVALUE1}, {ITEM.LASTVALUE2}'
			],
			[
				'description' => 'Symbols in Item metric',
				'expression' => 'last(/Host for Problems Page/trap2)<>""',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'XSS code in Item metric',
				'expression' => 'last(/Host for Problems Page/trapXSS)<>""',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'SQL Injection Item metric',
				'expression' => 'last(/Host for Problems Page/trapSQL)<>""',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Trigger for Age problem 1 month',
				'expression' => 'last(/Host for Problems Page/trap)=0',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'manual_close' => 1
			],
			[
				'description' => 'Trigger for String problem',
				'expression' => 'last(/Host for Problems Page/trap1)<>""',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'manual_close' => 1
			],
			[
				'description' => 'Two trigger expressions',
				'expression' => 'last(/Host for Problems Page/trap1)<>"" and last(/Host for Problems Page/trapSQL)<>""',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'No operational data popup',
				'expression' => 'last(/Host for Problems Page/trap1)<>"" and last(/Host for Problems Page/trapSQL)<>""',
				'priority' => TRIGGER_SEVERITY_AVERAGE,
				'opdata' => 'No popup "],*,a[x=": "],*,a[x="/\|\'/æ㓴🍭🍭'
			]
		]);

		// Create event with recent age.
		self::$time = time();

		$items_data = [
			'Host for Problems Page:trap' => [
				[0, 20, 150],
				[self::$time - 2, self::$time - 1, self::$time]
			],
			'Host for Problems Page:trap1' => [
				['Text', 'Essay', 'ParagraphParagraphParagraphParagraph'],
				[self::$time - 2, self::$time - 1, self::$time]
			],
			'Host for Problems Page:trapXSS' => [['<script>alert("TEST");</script>'], self::$time],
			'Host for Problems Page:trapSQL' => [['105\'; --DROP TABLE Users'], self::$time],
			'Host for Problems Page:trap2' => [['"],*,a[x=": "],*,a[x="/\|\'/æ㓴♥"'], self::$time]
		];
		foreach ($items_data as $item_name => $values) {
			CDataHelper::addItemData($result['itemids'][$item_name], $values[0], $values[1]);
		}

		$trigger_data = [
			'Trigger for Age problem' => ['clock' => self::$time],
			'Trigger for Age problem 1 day' => ['clock' => self::$time - 86400],
			'Trigger for Age problem 1 month' => ['clock' => self::$time - 2.628e+6],
			'No operational data popup' => ['clock' => self::$time]
		];
		foreach ($trigger_data as $trigger_name => $clock) {
			CDBHelper::setTriggerProblem($trigger_name, TRIGGER_VALUE_TRUE, $clock);
		}
		CDBHelper::setTriggerProblem(['Symbols in Item metric', 'Filled opdata with macros', 'XSS code in Item metric',
				'SQL Injection Item metric', 'Trigger for String problem', 'Two trigger expressions'
		]);

		$dayid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr('Trigger for Age problem 1 day'));
		$monthid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr('Trigger for Age problem 1 month'));

		// Close problems to check time selector filter tab.
		foreach ([$dayid, $monthid] as $eventid) {
			CDataHelper::call('event.acknowledge', [
				'eventids' => $eventid,
				'action' => 1,
				'message' => 'Closed problem'
			]);
		}
	}

	public function testPageProblems_Layout() {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1');
		$this->page->assertTitle('Problems');
		$this->page->assertHeader('Problems');

		$filter_tab = CFilterElement::find()->one()->setContext(CFilterElement::CONTEXT_LEFT);
		$this->assertTrue($filter_tab->isExpanded());

		// Check that filter is collapsing/expanding on click.
		foreach ([false, true] as $status) {
			$filter_tab->expand($status);
			$this->assertTrue($filter_tab->isExpanded($status));
		}

		$filter_form = $filter_tab->getForm();
		$this->assertEquals(['Show', 'Host groups', 'Hosts', 'Triggers', 'Problem', 'Severity', 'Age less than',
				'Show symptoms', 'Show suppressed problems', 'Acknowledgement status', 'Host inventory',
				'Tags', 'Show tags', 'Tag display priority', 'Show operational data', 'Compact view',
				'Show details'], $filter_form->getLabels()->asText()
		);

		// Check complicated labels.
		foreach (['By me', 'Tag name', 'Show timeline', 'Highlight whole row'] as $label) {
			$this->assertTrue($filter_form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')
					->one()->isVisible()
			);
		}

		$this->assertEquals([], $filter_form->getRequiredLabels());

		$fields_values = [
			'Show' => ['value' => 'Recent problems'],
			'id:groupids_0_ms' => ['value' => '', 'placeholder' => 'type here to search'],
			'id:hostids_0_ms' => ['value' => '', 'placeholder' => 'type here to search'],
			'id:triggerids_0_ms' => ['value' => '', 'placeholder' => 'type here to search'],
			'Problem' => ['value' => '', 'maxlength' => 255],
			'Not classified' => ['value' => false],
			'Information' => ['value' => false],
			'Warning' => ['value' => false],
			'Average' => ['value' => false],
			'High' => ['value' => false],
			'Disaster' => ['value' => false],
			'name:age_state' => ['value' => false],
			'name:age' => ['value' => 14, 'enabled' => false],
			'Show symptoms' => ['value' => false],
			'Show suppressed problems' => ['value' => false],
			'Acknowledgement status' => ['value' => 'All'],
			'id:acknowledged_by_me_0' => ['value' => false, 'enabled' => false],
			'name:inventory[0][field]' => ['value' => 'Type'],
			'name:inventory[0][value]' => ['value' => '', 'maxlength' => 255],
			'id:evaltype_0' => ['value' => 'And/Or'],
			'name:tags[0][tag]' => ['value' => '', 'placeholder' => 'tag', 'maxlength' => 255],
			'id:tags_00_operator' => ['value' => 'Contains'],
			'id:tags_00_value' => ['value' => '', 'placeholder' => 'value', 'maxlength' => 255],
			'Show tags' => ['value' => 3],
			'id:tag_name_format_0' => ['value' => 'Full'],
			'Tag display priority' => ['value' => '', 'placeholder' => 'comma-separated list', 'maxlength' => 255],
			'Show operational data' => ['value' => 'None'],
			'Compact view' => ['value' => false],
			'Show details' => ['value' => false],
			'Show timeline' => ['value' => true],
			'Highlight whole row' => ['value' => false, 'enabled' => false]
		];

		foreach ($fields_values as $label => $attributes) {
			$field = $filter_form->getField($label);
			$this->assertEquals($attributes['value'], $field->getValue());
			$this->assertTrue($field->isVisible());
			$this->assertTrue($field->isEnabled(CTestArrayHelper::get($attributes, 'enabled', true)));

			foreach (['placeholder', 'maxlength'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $field->getAttribute($attribute));
				}
			}
		}

		// Check empty overlays.
		foreach (['Hosts', 'Triggers'] as $field) {
			$overlay = $filter_form->getField($field)->edit();
			$this->assertEquals($field, $overlay->getTitle());
			$this->assertEquals("Filter is not set\nUse the filter to display results",
					$overlay->query('class:no-data-message')->one()->getText()
			);
			$overlay->close();
		}

		$segmented_radios = [
			'Show' => ['Recent problems', 'Problems', 'History'],
			'Acknowledgement status' => ['All', 'Unacknowledged', 'Acknowledged'],
			'Tags' => ['And/Or', 'Or'],
			'Show tags' => ['None', 1, 2, 3],
			'id:tag_name_format_0' => ['Full', 'Shortened', 'None'],
			'Show operational data' => ['None', 'Separately', 'With problem name']
		];

		foreach ($segmented_radios as $field => $labels) {
			$this->assertEquals($labels, $filter_form->getField($field)->asSegmentedRadio()->getLabels()->asText());
		}

		$dropdowns = [
			'name:inventory[0][field]' => ['Type', 'Type (Full details)', 'Name', 'Alias', 'OS', 'OS (Full details)',
					'OS (Short)', 'Serial number A', 'Serial number B', 'Tag', 'Asset tag', 'MAC address A',
					'MAC address B', 'Hardware', 'Hardware (Full details)', 'Software', 'Software (Full details)',
					'Software application A', 'Software application B', 'Software application C', 'Software application D',
					'Software application E', 'Contact', 'Location', 'Location latitude', 'Location longitude',
					'Notes', 'Chassis', 'Model', 'HW architecture', 'Vendor', 'Contract number', 'Installer name',
					'Deployment status', 'URL A', 'URL B', 'URL C', 'Host networks', 'Host subnet mask', 'Host router',
					'OOB IP address', 'OOB subnet mask', 'OOB router', 'Date HW purchased', 'Date HW installed',
					'Date HW maintenance expires', 'Date HW decommissioned', 'Site address A', 'Site address B',
					'Site address C', 'Site city', 'Site state / province', 'Site country', 'Site ZIP / postal',
					'Site rack location', 'Site notes', 'Primary POC name', 'Primary POC email', 'Primary POC phone A',
					'Primary POC phone B', 'Primary POC cell', 'Primary POC screen name', 'Primary POC notes',
					'Secondary POC name', 'Secondary POC email', 'Secondary POC phone A', 'Secondary POC phone B',
					'Secondary POC cell', 'Secondary POC screen name', 'Secondary POC notes'
			],
			'id:tags_00_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain']
		];

		foreach ($dropdowns as $field => $options) {
			$this->assertEquals($options, $filter_form->getField($field)->asDropdown()->getOptions()->asText());
		}

		// Check how filter form changes depending on 'Show' field settings.
		$age_field = $filter_form->getField('Age less than');

		$dependant_fields = [
			'History' => [
				'xpath://button[@data-action="selectPrevTab"]' => true,
				'xpath://button[@data-action="toggleTabsList"]' => true,
				'xpath://button[@data-action="selectNextTab"]' => true,
				'xpath://button[contains(@class, "js-btn-time-left")]' => true,
				'button:Zoom out' => true,
				'xpath://button[contains(@class, "js-btn-time-right")]' => false,
				'xpath://a[text()="Last 1 hour"]' => true
			],
			'Problems' => [
				'xpath://button[@data-action="selectPrevTab"]' => true,
				'xpath://button[@data-action="toggleTabsList"]' => true,
				'xpath://button[@data-action="selectNextTab"]' => true,
				'xpath://button[contains(@class, "js-btn-time-left")]' => false,
				'button:Zoom out' => false,
				'xpath://button[contains(@class, "js-btn-time-right")]' => false,
				'xpath://a[text()="Last 1 hour"]' => false
			]
		];

		foreach ($dependant_fields as $show => $checked_elements) {
			$filter_form->fill(['Show' => $show]);

			if ($show === 'History') {
				$age_field->waitUntilNotVisible();
				$fields_values['Show']['value'] = 'History';
				$attribute_status = false;
			}
			else {
				$age_field->waitUntilVisible();
				$fields_values['Show']['value'] = 'Problems';
				$attribute_status = true;
			}

			$fields_values['name:age_state']['visible'] = $attribute_status;
			$fields_values['name:age_state']['enabled'] = $attribute_status;
			$fields_values['name:age']['visible'] = $attribute_status;

			foreach ($checked_elements as $query => $state) {
				$this->assertTrue($this->query($query)->one()->isEnabled($state));
			}

			foreach ($fields_values as $label => $attributes) {
				$field = $filter_form->getField($label);
				$this->assertTrue($field->isVisible(CTestArrayHelper::get($attributes, 'visible', true)));
				$this->assertTrue($field->isEnabled(CTestArrayHelper::get($attributes, 'enabled', true)));
			}
		}

		// Check Age field editability.
		foreach ([false, true] as $state) {
			$filter_form->fill(['id:age_state_0' => $state]);
			$this->assertTrue($filter_form->getField('name:age')->isEnabled($state));
		}

		// Acknowledgement status field editability.
		foreach (['All' => false, 'Unacknowledged' => false, 'Acknowledged' => true] as $label => $status) {
			$filter_form->fill(['Acknowledgement status' => $label]);
			$this->assertTrue($filter_form->getField('id:acknowledged_by_me_0')->isEnabled($status));
		}

		// Tags fields editability.
		foreach (['None' => false, 1 => true, 2 => true, 3 => true] as $label => $status) {
			$filter_form->fill(['Show tags' => $label]);

			foreach (['id:tag_name_format_0', 'Tag display priority'] as $field) {
				$this->assertTrue($filter_form->getField($field)->isEnabled($status));
			}
		}

		// Show operational data and checkboxes dependency.
		foreach ([true, false] as $state) {
			$filter_form->fill(['Compact view' => $state]);

			foreach (['Show operational data', 'Show details', 'Show timeline'] as $field) {
				$this->assertTrue($filter_form->getField($field)->isEnabled(!$state));
			}
			$this->assertTrue($filter_form->getField('Highlight whole row')->isEnabled($state));
		}

		$this->assertEquals(3, $filter_tab->query('button', ['Save as', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check Problems table layout.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['Time', 'Severity', 'Host', 'Problem'], $table->getSortableHeaders()->asText());

		$this->query('button:Reset')->waitUntilClickable()->one()->click();
		$table->waitUntilReloaded();

		$dependant_headers = [
			[
				'label' => 'Show',
				'value' => 'Recent problems',
				'headers' => ['Recovery time', 'Status', 'Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show',
				'value' => 'History',
				'headers' => ['Recovery time', 'Status', 'Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show',
				'value' => 'Problems',
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show tags',
				'value' => 'None',
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions']
			],
			[
				'label' => 'Show tags',
				'value' => 1,
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show tags',
				'value' => 2,
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show tags',
				'value' => 3,
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show operational data',
				'value' => 'None',
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show operational data',
				'value' => 'Separately',
				'headers' => ['Info', 'Host', 'Problem', 'Operational data', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show operational data',
				'value' => 'With problem name',
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show timeline',
				'value' => false,
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			[
				'label' => 'Show timeline',
				'value' => true,
				'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			]
		];

		foreach ($dependant_headers as $field) {
			$filter_form->fill([$field['label'] => $field['value']]);
			$filter_form->submit();
			$table->waitUntilReloaded();
			$start_headers = ($field['label'] === 'Show timeline' && !$field['value'])
				? ['', 'Time', 'Severity']
				: ['', 'Time', '', '', 'Severity'];
			$this->assertEquals(array_merge($start_headers, $field['headers']), $table->getHeadersText());
		}

		// Check that some unfiltered data is displayed in the table.
		$this->assertTableStats(CDBHelper::getCount(
				'SELECT null FROM problem'.
				' WHERE cause_eventid IS NULL'.
				' AND eventid'.
					' NOT IN (SELECT eventid FROM event_suppress)'
		));

		// Check Mass update button.
		$mass_update_button = $this->query('button:Mass update')->one();
		$this->assertFalse($mass_update_button->isClickable());
		$table->getRow(0)->select();
		$this->assertSelectedCount(1);
		$mass_update_button->waitUntilClickable();
	}

	public static function getFilterData() {
		return [
			// #0 Host group filter - empty result.
			[
				[
					'fields' => [
						'Host groups' => 'Empty group'
					],
					'result' => []
				]
			],
			// #1 Host group filter result.
			[
				[
					'fields' => [
						'Host groups' => 'Another group to check Overview'
					],
					'result' => [
						[
							'Severity' => 'Average',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => '4_Host_to_check_Monitoring_Overview',
							'Problem' => '4_trigger_Average',
							'Update' => 'Update',
							'Tags' => ''
						]
					]
				]
			],
			// #2 Host filter result with trigger description and action check.
			[
				[
					'fields' => [
						'Hosts' => '3_Host_to_check_Monitoring_Overview'
					],
					'result' => [
						[
							'Severity' => 'Average',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => '3_Host_to_check_Monitoring_Overview',
							'Problem' => '3_trigger_Average',
							'Update' => 'Update',
							'Tags' => ''
						]
					],
					'check_trigger_description' => [
						'Macro - resolved, URL - clickable: 3_Host_to_check_Monitoring_Overview, https://zabbix.com'
					],
					'check_actions' => [
						[
							[
								'Time' => '2018-08-07 11:05:35',
								'User/Recipient' => 'Admin (Zabbix Administrator)',
								'Action' => '',
								'Message/Command' => '',
								'Status' => '',
								'Info' => ''
							],
							[
								'Time' => '2018-08-06 14:42:06',
								'User/Recipient' => '',
								'Action' => '',
								'Message/Command' => '',
								'Status' => '',
								'Info' => ''
							]
						]
					]
				]
			],
			// #3 Trigger filter.
			[
				[
					'fields' => [
						'Triggers' => ['Trigger_for_suppression', '2_trigger_Information'],
						'Show suppressed problems' => true
					],
					'result' => [
						[
							'Severity' => 'Average',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'Host for suppression',
							'Problem' => 'Trigger_for_suppression',
							'Update' => 'Update',
							'Tags' => 'SupTag: A'
						],
						[
							'Severity' => 'Information',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => '1_Host_to_check_Monitoring_Overview',
							'Problem' => '2_trigger_Information',
							'Update' => 'Update',
							'Tags' => ''
						]
					],
					'check_trigger_description' => [
						false,
						'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact'
					],
					'check_actions' => [
						false,
						[
							[
								'Time' => '2018-08-07 11:05:35',
								'User/Recipient' => 'Admin (Zabbix Administrator)',
								'Action' => '',
								'Message/Command' => '',
								'Status' => '',
								'Info' => ''
							],
							[
								'Time' => '2018-08-06 14:42:06',
								'User/Recipient' => '',
								'Action' => '',
								'Message/Command' => '',
								'Status' => '',
								'Info' => ''
							]
						]
					]
				]
			],
			// #4 And/Or tag operator.
			[
				[
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Contains', 'value' => ''],
							['name' => 'Database', 'operator' => 'Contains', 'value' => '']
						]
					],
					'result' => [
						[
							'Severity' => 'Average',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => 'Test trigger to check tag filter on problem page',
							'Update' => 'Update',
							'Tags' => 'DatabaseService: abcservice: abcdef'
						]
					]
				]
			],
			// #5 Or tag operator.
			[
				[
					'fields' => [
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Contains', 'value' => ''],
							['name' => 'Database', 'operator' => 'Contains', 'value' => '']
						]
					],
					'result' => [
						[
							'Severity' => 'Not classified',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'Host for tag permissions',
							'Problem' => 'Trigger for tag permissions Oracle',
							'Update' => 'Update',
							'Tags' => 'Service: Oracle'
						],
						[
							'Severity' => 'Not classified',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'Host for tag permissions',
							'Problem' => 'Trigger for tag permissions MySQL',
							'Update' => 'Update',
							'Tags' => 'Service: MySQL'
						],
						[
							'Severity' => 'Warning',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => 'Test trigger with tag',
							'Update' => 'Update',
							'Tags' => 'Service: abc'
						],
						[
							'Severity' => 'Average',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => 'Test trigger to check tag filter on problem page',
							'Update' => 'Update',
							'Tags' => 'DatabaseService: abcservice: abcdef'
						]
					]
				]
			],
			// #6 Tag operator Contains.
			[
				[
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'service', 'operator' => 'Contains', 'value' => 'abc']
						]
					],
					'result' => [
						[
							'Severity' => 'Average',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => 'Test trigger to check tag filter on problem page',
							'Update' => 'Update',
							'Tags' => 'service: abcdefDatabaseService: abc'
						]
					]
				]
			],
			// #7 Tag operator Equals.
			[
				[
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'service', 'operator' => 'Equals', 'value' => 'abc']
						]
					],
					'result' => []
				]
			],
			// #8 "And/Or" and operator Exists, one tag.
			[
				[
					'fields' => [
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #9 "Or" and operator Exists, one tag.
			[
				[
					'fields' => [
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #10 "And/Or" and operator Exists, two tags.
			[
				[
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists'],
							['name' => 'Database', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #11 "Or" and operator Exists, two tags.
			[
				[
					'fields' => [
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists'],
							['name' => 'Database', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #12 "And/Or" and operator Does not exist.
			[
				[
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Alpha', 'operator' => 'Does not exist']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority']
					]
				]
			],
			// #13 "Or" and operator Does not exist.
			[
				[
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Alpha', 'operator' => 'Does not exist']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority']
					]
				]
			],
			// #14 "And/Or" and operator Does not exist, two tags.
			[
				[
					'fields' => [
						'Host groups' => ['Host group for tag permissions', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Does not exist'],
							['name' => 'Database', 'operator' => 'Does not exist']
						]
					],
					'result' => [
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority']
					]
				]
			],
			// #15 "Or" and operator Does not exist, two tags.
			[
				[
					'fields' => [
						'Host groups' => ['Host group for tag permissions', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Does not exist'],
							['name' => 'Database', 'operator' => 'Does not exist']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority']
					]
				]
			],
			// #16 "And/Or" and operator Does not equal.
			[
				[
					'fields' => [
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium']
						]
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem'],
						['Problem' => 'trigger on host 3'],
						['Problem' => 'trigger on host 2'],
						['Problem' => 'trigger on host 1'],
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Trigger for Age problem 1 day'],
						['Problem' => 'Trigger for Age problem 1 month'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority'],
						['Problem' => '4_trigger_Average'],
						['Problem' => '3_trigger_Average'],
						['Problem' => '2_trigger_Information'],
						['Problem' => '1_trigger_Disaster'],
						['Problem' => '1_trigger_High'],
						['Problem' => '1_trigger_Average'],
						['Problem' => '1_trigger_Warning'],
						['Problem' => '1_trigger_Not_classified']
					]
				]
			],
			// #17 "And/Or" and operator Does not equal.
			[
				[
					'fields' => [
						'Host groups' => 'Host group for tag permissions'
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Does not equal', 'value' => 'MySQL']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle']
					]
				]
			],
			// #18 "And/Or" and operator Does not equal, two tags.
			[
				[
					'fields' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium'],
							['name' => 'Beta', 'operator' => 'Does not equal', 'value' => 'b']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority']
					]
				]
			],
			// #19 "Or" and operator Does not equal, two tags.
			[
				[
					'fields' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Does not equal', 'value' => 'abc'],
							['name' => 'Database', 'operator' => 'Does not equal']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Inheritance trigger with tags'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority']
					],
					'check_trigger_dependency' => [false, 'Trigger disabled with tags', false, false, false]
				]
			],
			// #20 "And/Or" and operator Does not contain, one tag.
			[
				[
					'fields' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Inheritance trigger with tags'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority']

					]
				]
			],
			// #21 "Or" and operator Does not contain, one tag.
			[
				[
					'fields' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Inheritance trigger with tags'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority']
					]
				]
			],
			// #22 "And/Or" and operator Does not contain, two tags.
			[
				[
					'fields' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a'] ,
							['name' => 'Delta', 'operator' => 'Does not contain', 'value' => 'd']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Inheritance trigger with tags'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority']
					]
				]
			],
			// #23 "Or" and operator Does not contain, two tags.
			[
				[
					'fields' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Show timeline' => false
					],
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a'] ,
							['name' => 'Delta', 'operator' => 'Does not contain', 'value' => 'd']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page'],
						['Problem' => 'Inheritance trigger with tags'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority']
					]
				]
			],
			// #24 Filter by all filter fields. Show tags = 3, Shortened.
			[
				[
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Triggers' => ['Test trigger to check tag filter on problem page', 'Test trigger with tag'],
						'Problem' => 'Test trigger',
						'High' => true,
						'Average' => true,
						'Show symptoms' => true,
						'Acknowledgement status' => 'Unacknowledged',
						'Host inventory' => [
							'action' => USER_ACTION_UPDATE, 'index' => 0,
							'field' => 'Location latitude', 'value' => '56.95387'
						],
						'Show tags' => 3,
						'id:tag_name_format_0' => 'Shortened',
						'Tag display priority' => 'Tag4',
						'Show operational data' => 'Separately',
						'Show details' => true
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Contains', 'value' => 'abc']
						]
					],
					'result' => [
						[
							'' => '',
							'Time' => '2020-10-23 15:33:48',
							'' => '',
							'' => '',
							'Severity' => 'Average',
							'Recovery time' => '',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => "Test trigger to check tag filter on problem page\navg(/Test host/proc.num,5m)>100",
							'Operational data' => '*UNKNOWN*',
							'Update' => 'Update',
							'Actions' => '',
							'Tags' => 'TagSer: abcDat'
						]
					],
					'check_tags' => [
						'Tag' => 'Tag4',
						'Ser: abc' => 'Service: abc',
						'Dat' => 'Database',
						'...' => 'DatabaseService: abcservice: abcdefTag4Tag5: 5'
					]
				]
			],
			// #25 Show tags = 2, Full.
			[
				[
					'fields' => [
						'Problem' => 'Test trigger',
						'Average' => true,
						'Show tags' => 2,
						'id:tag_name_format_0' => 'Full'
					],
					'result' => [
						[
							'Problem' => 'Test trigger to check tag filter on problem page',
							'Tags' => 'DatabaseService: abc'
						]
					],
					'check_tags' => [
						'Database' => 'Database',
						'Service: abc' => 'Service: abc',
						'...' => 'DatabaseService: abcservice: abcdefTag4Tag5: 5'
					]
				]
			],
			// #26 Show tags = 1, None.
			[
				[
					'fields' => [
						'Problem' => 'Test trigger',
						'Average' => true,
						'Show tags' => 1,
						'id:tag_name_format_0' => 'None'
					],
					'result' => [
						[
							'Problem' => 'Test trigger to check tag filter on problem page',
							'Tags' => 'abc'
						]
					],
					'check_tags' => [
						'abc' => 'Service: abc',
						'...' => 'DatabaseService: abcservice: abcdefTag4Tag5: 5'
					]
				]
			],
			// #27 Show tags = None. Tags column absence is being checked in _Layout.
			[
				[
					'fields' => [
						'Problem' => 'Test trigger',
						'Average' => true,
						'Show tags' => 'None'
					],
					'result' => [
						[
							'Problem' => 'Test trigger to check tag filter on problem page'
						]
					]
				]
			],
			// #28 Tags priority check 1.
			[
				[
					'fields' => [
						'Problem' => 'test trigger with tag priority',
						'Show tags' => 3,
						'Tag display priority' => 'Kappa'
					],
					'result' => [
						[
							'Problem' => 'Fourth test trigger with tag priority',
							'Tags' => 'Delta: tEta: eGamma: g'
						],
						[
							'Problem' => 'Third test trigger with tag priority',
							'Tags' => 'Kappa: kAlpha: aIota: i'
						],
						[
							'Problem' => 'Second test trigger with tag priority',
							'Tags' => 'Beta: bEpsilon: eEta: e'
						],
						[
							'Problem' => 'First test trigger with tag priority',
							'Tags' => 'Alpha: aBeta: bDelta: d'
						]
					]
				]
			],
			// #29 Tags priority check 2.
			[
				[
					'fields' => [
						'Problem' => 'test trigger with tag priority',
						'Show tags' => 3,
						'Tag display priority' => 'Kappa, Beta'
					],
					'result' => [
						[
							'Problem' => 'Fourth test trigger with tag priority',
							'Tags' => 'Delta: tEta: eGamma: g'
						],
						[
							'Problem' => 'Third test trigger with tag priority',
							'Tags' => 'Kappa: kAlpha: aIota: i'
						],
						[
							'Problem' => 'Second test trigger with tag priority',
							'Tags' => 'Beta: bEpsilon: eEta: e'
						],
						[
							'Problem' => 'First test trigger with tag priority',
							'Tags' => 'Beta: bAlpha: aDelta: d'
						]
					]
				]
			],
			// #30 Tags priority check 3.
			[
				[
					'fields' => [
						'Problem' => 'test trigger with tag priority',
						'Show tags' => 3,
						'Tag display priority' => 'Gamma, Kappa, Beta'
					],
					'result' => [
						[
							'Problem' => 'Fourth test trigger with tag priority',
							'Tags' => 'Gamma: gDelta: tEta: e'
						],
						[
							'Problem' => 'Third test trigger with tag priority',
							'Tags' => 'Kappa: kAlpha: aIota: i'
						],
						[
							'Problem' => 'Second test trigger with tag priority',
							'Tags' => 'Beta: bEpsilon: eEta: e'
						],
						[
							'Problem' => 'First test trigger with tag priority',
							'Tags' => 'Gamma: gBeta: bAlpha: a'
						]
					]
				]
			],
			// #31 Test result with 2 tags, and then result after removing one tag.
			[
				[
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Equals', 'value' => 'abc'],
							['name' => 'service', 'operator' => 'Contains', 'value' => 'abc']
						]
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Test trigger to check tag filter on problem page']
					],
					'removed_tag_result' => [
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #32 Suppressed problem not shown.
			[
				[
					'fields' => [
						'Hosts' => 'Host for suppression',
						'Show suppressed problems' => false
					],
					'result' => []
				]
			],
			// #33 Suppressed problem is shown.
			[
				[
					'fields' => [
						'Hosts' => 'Host for suppression',
						'Show suppressed problems' => true
					],
					'result' => [
						[
							'Severity' => 'Average',
							'Recovery time' => '',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'Host for suppression',
							'Problem' => 'Trigger_for_suppression'
						]
					],
					'check_suppressed' => "Suppressed till: 12:17\nMaintenance: Maintenance for suppression test"
				]
			],
			// #34 Show timeline.
			[
				[
					'fields' => [
						'Hosts' => 'ЗАББИКС Сервер',
						'Warning' => true
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['' => 'October'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority']
					],
					'table_timeline' => true
				]
			],
			// #35 Age filter - 999.
			[
				[
					'fields' => [
						'id:age_state_0' => true,
						'name:age' => 999,
						'Show timeline' => false
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem'],
						['Problem' => 'trigger on host 3'],
						['Problem' => 'trigger on host 2'],
						['Problem' => 'trigger on host 1'],
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Trigger for Age problem 1 day'],
						['Problem' => 'Trigger for Age problem 1 month']
					]
				]
			],
			// #36 Age filter - 0.
			[
				[
					'fields' => [
						'id:age_state_0' => true,
						'name:age' => 0
					],
					'result' => []
				]
			],
			// #37 Age filter - 1.
			[
				[
					'fields' => [
						'id:age_state_0' => true,
						'name:age' => 10,
						'Show timeline' => false
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem'],
						['Problem' => 'trigger on host 3'],
						['Problem' => 'trigger on host 2'],
						['Problem' => 'trigger on host 1'],
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Trigger for Age problem 1 day']
					]
				]
			],
			// #38 History.
			[
				[
					'fields' => [
						'Show' => 'History',
						'Show timeline' => false
					],
					'time_selector' => [
						'link' => 'Last 1 day'
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem'],
						['Problem' => 'trigger on host 3'],
						['Problem' => 'trigger on host 2'],
						['Problem' => 'trigger on host 1'],
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL']
					]
				]
			],
			// #39 Problems.
			[
				[
					'fields' => [
						'Show' => 'Problems',
						'Not classified' => true,
						'Show timeline' => false
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => '1_trigger_Not_classified']
					]
				]
			],
			// #40 Unacknowledged.
			[
				[
					'fields' => [
						'Severity' => 'Warning',
						'Acknowledgement status' => 'Unacknowledged',
						'Show timeline' => false
					],
					'result' => [
						['Problem' => 'trigger on host 2'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority'],
						['Problem' => '1_trigger_Warning']
					]
				]
			],
			// #41 Acknowledged.
			[
				[
					'fields' => [
						'Acknowledgement status' => 'Acknowledged',
						'Show timeline' => false
					],
					'result' => [
						['Problem' => '4_trigger_Average'],
						['Problem' => '3_trigger_Average'],
						['Problem' => '2_trigger_Information']
					]
				]
			],
			// #42 Acknowledged by me.
			[
				[
					'fields' => [
						'Acknowledgement status' => 'Acknowledged',
						'id:acknowledged_by_me_0' => true,
						'Show timeline' => false
					],
					'result' => [
						['Problem' => '3_trigger_Average'],
						['Problem' => '2_trigger_Information']
					]
				]
			],
			// #43 Compact view.
			[
				[
					'fields' => [
						'Problem' => '1_trigger_Disaster',
						'Compact view' => true
					],
					'result' => [
						['Problem' => '1_trigger_Disaster']
					]
				]
			],
			// #44 Highlight whole row.
			[
				[
					'fields' => [
						'Problem' => '1_trigger_Average',
						'Compact view' => true,
						'Highlight whole row' => true
					],
					'result' => [
						['Problem' => '1_trigger_Average']
					]
				]
			],
			// #45 Time selector 1 day.
			[
				[
					'fields' => [
						'Show' => 'History',
						'Hosts' => 'Host for Problems Page',
						'Show timeline' => false
					],
					'time_selector' => [
						'id:from' => 'now-1d',
						'id:to' => 'now'
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem']
					]
				]
			],
			// #46 Time selector 2 weeks.
			[
				[
					'fields' => [
						'Show' => 'History',
						'Hosts' => 'Host for Problems Page',
						'Show timeline' => false
					],
					'time_selector' => [
						'id:from' => 'now-2w',
						'id:to' => 'now'
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem'],
						['Problem' => 'Trigger for Age problem 1 day']
					]
				]
			],
			// #47 Time selector Last 1 year.
			[
				[
					'fields' => [
						'Show' => 'History',
						'Hosts' => 'Host for Problems Page',
						'Show timeline' => false
					],
					'time_selector' => [
						'link' => 'Last 1 year'
					],
					'result' => [
						['Problem' => 'Two trigger expressions'],
						['Problem' => 'Trigger for String problem'],
						['Problem' => 'SQL Injection Item metric'],
						['Problem' => 'XSS code in Item metric'],
						['Problem' => 'Filled opdata with macros'],
						['Problem' => 'Symbols in Item metric'],
						['Problem' => 'No operational data popup'],
						['Problem' => 'Trigger for Age problem'],
						['Problem' => 'Trigger for Age problem 1 day'],
						['Problem' => 'Trigger for Age problem 1 month']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageProblems_Filter($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1&sort=clock&sortorder=ASC');
		$form = CFilterElement::find()->one()->getForm();
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();

		if (array_key_exists('Tags', $data)) {
			$form->fill(['id:evaltype_0' => $data['Tags']['Type']]);
			$this->setTags($data['Tags']['tags']);
		}

		if (array_key_exists('fields', $data)) {
			$form->fill($data['fields']);
		}

		$form->submit();
		$table->waitUntilReloaded();

		if (array_key_exists('time_selector', $data)) {
			$this->query('xpath://a[contains(@class, "zi-clock")]')->waitUntilClickable()->one()->click();
			$this->query('class:time-quick-range')->waitUntilVisible()->one();
			$form = $this->query('class:filter-container')->asForm(['normalized' => true])->one();
			$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();

			if (CTestArrayHelper::get($data['time_selector'], 'link')) {
				$form->query('link', $data['time_selector']['link'])->waitUntilClickable()->one()->click();
			}
			else {
				$form->fill($data['time_selector']);
			}

			$this->query('button:Apply')->one()->click();
			$this->page->waitUntilReady();
			$table->waitUntilReloaded();
		}

		$this->page->waitUntilReady();
		$this->assertTableData($data['result']);

		// Check "Compact view" and "Highlight whole row" filter checkboxes.
		$compact_selector = 'xpath://table[contains(@class, "compact-view")]';
		$highlight_selector = 'xpath://tr[contains(@class, "-bg")]';
		if (array_key_exists('fields', $data) && CTestArrayHelper::get($data['fields'], 'Compact view', false)) {
			$this->assertTrue($this->query($compact_selector)->exists());

			$this->assertEquals(CTestArrayHelper::get($data['fields'], 'Highlight whole row', false),
					$this->query($highlight_selector)->exists()
			);
		}
		else {
			foreach ([$compact_selector, $highlight_selector] as $selector) {
				$this->assertFalse($this->query($selector)->exists());
			}
		}

		// If Show timeline = true, it adds one more row to the result table.
		$this->assertTableStats(CTestArrayHelper::get($data, 'table_timeline')
			? count($data['result']) - 1
			: count($data['result'])
		);

		$dialog_selector = 'xpath://div[@class="overlay-dialogue wordbreak"]';
		if (array_key_exists('check_trigger_description', $data)) {
			foreach ($data['check_trigger_description'] as $i => $description) {
				$cell = $table->getRow($i)->getColumn('Problem');

				if (!$description) {
					$this->assertFalse($cell->query('xpath:.//button[contains(@class, "zi-alert-with-content")]')->exists());
				}
				else {
					$cell->query('tag:button')->waitUntilClickable()->one()->click();
					$description_dialog = $this->query($dialog_selector)->waitUntilVisible()->one();
					$this->assertEquals($description, $description_dialog->getText());
					$description_dialog->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
					$description_dialog->waitUntilNotPresent();
				}
			}
		}

		if (array_key_exists('check_actions', $data)) {
			foreach ($data['check_actions'] as $i => $action) {
				$cell = $table->getRow($i)->getColumn('Actions');
				$tick = $cell->query('xpath:.//span[@title="Acknowledged"]');

				if (!$action) {
					$this->assertFalse($tick->exists());
				}
				else {
					$this->assertTrue($tick->exists());
					$cell->query('tag:button')->waitUntilClickable()->one()->forceClick();
					$action_dialog = $this->query($dialog_selector)->asOverlayDialog()->waitUntilReady()->one();
					$this->assertTableData($action, $dialog_selector.'//table');
					$action_dialog->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
					$action_dialog->waitUntilNotPresent();
				}
			}
		}

		if (array_key_exists('check_trigger_dependency', $data)) {
			foreach ($data['check_trigger_dependency'] as $i => $dependency) {
				$arrow = $table->getRow($i)->getColumn('Problem')->query('tag:button');

				if (!$dependency) {
					$this->assertFalse($arrow->exists());
				}
				else {
					$arrow->one()->click();
					$dependency_dialog = $this->query($dialog_selector)->one()->waitUntilVisible();
					$this->assertEquals("Depends on\n".$dependency, $dependency_dialog->getText());
					$dependency_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
					$dependency_dialog->waitUntilNotPresent();
				}
			}
		}

		if (array_key_exists('check_tags', $data)) {
			foreach ($data['check_tags'] as $tag => $text) {
				$selector = ($tag === '...')
					? 'xpath:.//button[@class="btn-icon zi-more"]'
					: 'xpath:.//span[text()='.CXPathHelper::escapeQuotes($tag).']';
				$table->getRow(0)->getColumn('Tags')->query($selector)->one()->click();
				$popup = $this->query($dialog_selector)->one()->waitUntilVisible();
				$this->assertEquals($text, $popup->getText());
				$popup->query('xpath:.//button[@title="Close"]')->one()->click();
				$popup->waitUntilNotPresent();
			}
		}

		if (array_key_exists('removed_tag_result', $data)) {
			$form->query('name:tags[0][remove]')->one()->click();
			$form->submit();
			$table->waitUntilReloaded();
			$this->assertTableData($data['removed_tag_result']);
			$this->assertTableStats(count($data['removed_tag_result']));
		}

		if (array_key_exists('check_suppressed', $data)) {
			$table->getRow(0)->getColumn('Info')->query('tag:button')->one()->click();
			$suppressed_dialog = $this->query($dialog_selector)->one()->waitUntilVisible();
			$this->assertEquals($data['check_suppressed'], $suppressed_dialog->getText());
			$suppressed_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
			$suppressed_dialog->waitUntilNotPresent();
		}
	}

	public static function getFilterForOperationalData() {
		return [
			'String in operational data' => [
				[
					'filter' => [
						'Problem' => 'Trigger for String problem',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'String in operational data',
							'metric' => 'ParagraphParagraphParagraphParagraph',
							'truncated' => 'ParagraphParagraphPa...',
							'button' => 'History',
							'header' => 'Host for Problems Page: String in operational data'
						]
					]
				]
			],
			'Number in operational data' => [
				[
					'filter' => [
						'Problem' => 'Trigger for Age problem',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'Age problem item',
							'metric' => '150',
							'button' => 'Graph',
							'header' => 'Host for Problems Page: Age problem item'
						]
					]
				]
			],
			'Macro expansion and operational text' => [
				[
					'custom data' => 'Operational data - 150, 150, *UNKNOWN*',
					'filter' => [
						'Problem' => 'Filled opdata with macros',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'Age problem item',
							'metric' => '150',
							'button' => 'Graph',
							'header' => 'Host for Problems Page: Age problem item'
						]
					]
				]
			],
			'ASCII symbols in metric' => [
				[
					'filter' => [
						'Problem' => 'Symbols in Item metric',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'Symbols in Item metric',
							'metric' => '"],*,a[x=": "],*,a[x="/\|\'/æ㓴♥"',
							'truncated' => '"],*,a[x=": "],*,a[x...',
							'button' => 'History',
							'header' => 'Host for Problems Page: Symbols in Item metric'
						]
					]
				]
			],
			'XSS code in Item metric' => [
				[
					'filter' => [
						'Problem' => 'XSS code in Item metric',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'XSS text',
							'metric' => '<script>alert("TEST");</script>',
							'truncated' => '<script>alert("TEST"...',
							'button' => 'History',
							'header' => 'Host for Problems Page: XSS text'
						]
					]
				]
			],
			'SQL injection in Item metric' => [
				[
					'filter' => [
						'Problem' => 'SQL Injection Item metric',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'SQL Injection',
							'metric' => '105\'; --DROP TABLE Users',
							'truncated' => '105\'; --DROP TABLE U...',
							'button' => 'History',
							'header' => 'Host for Problems Page: SQL Injection'
						]
					]
				]
			],
			'Two metrics in operational data pop-up window' => [
				[
					'filter' => [
						'Problem' => 'Two trigger expressions',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'String in operational data',
							'metric' => 'ParagraphParagraphParagraphParagraph',
							'truncated' => 'ParagraphParagraphPa...',
							'button' => 'History',
							'header' => 'Host for Problems Page: String in operational data'
						],
						[
							'item' => 'SQL Injection',
							'metric' => '105\'; --DROP TABLE Users',
							'truncated' => '105\'; --DROP TABLE U...',
							'button' => 'History',
							'header' => 'Host for Problems Page: SQL Injection'
						]
					]
				]
			],
			'Filled opdata with macros' => [
				[
					'custom data' => 'Operational data - 150, 150, *UNKNOWN*',
					'filter' => [
						'Problem' => 'Filled opdata with macros',
						'Show operational data' => 'Separately'
					],
					'popup rows' => [
						[
							'item' => 'Age problem item',
							'metric' => '150',
							'button' => 'Graph',
							'header' => 'Host for Problems Page: Age problem item'
						]
					]
				]
			],
			'Operational data with problem name, no popup' => [
				[
					'filter' => [
						'Problem' => 'Two trigger expressions',
						'Show operational data' => 'With problem name'
					],
					'popup rows' => []
				]
			],
			'No popup in operational data column' => [
				[
					'custom data' => 'No popup "],*,a[x=": "],*,a[x="/\|\'/æ㓴🍭🍭',
					'filter' => [
						'Problem' => 'No operational data popup',
						'Show operational data' => 'Separately'
					],
					'popup rows' => []
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterForOperationalData
	 */
	public function testPageProblems_OperationalData($data){
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1&sort=clock&sortorder=ASC');
		$form = CFilterElement::find()->one()->getForm();
		$table = $this->query('class:list-table')->asTable()->waitUntilPresent()->one();

		$form->fill($data['filter'])->submit();
		$table->waitUntilReloaded();

		$column = ($data['filter']['Show operational data'] === 'With problem name') ? 'Problem' : 'Operational data';
		$opdata_column = $table->findRow('Problem', CTestArrayHelper::get($data, 'Problem',
				$data['filter']['Problem']))->getColumn($column);

		// Collect metrics from all items in trigger expression.
		$metrics = [];
		foreach ($data['popup rows'] as $popup_row) {
			$metrics[] = CTestArrayHelper::get($popup_row, 'truncated', $popup_row['metric']);
		}

		// Check operation data in table on page.
		$data_in_column = CTestArrayHelper::get($data, 'custom data', implode(', ', $metrics));
		if ($data['filter']['Show operational data'] === 'With problem name') {
			$data_in_column = ($data_in_column === '')
					? $data['filter']['Problem']
					: $data['filter']['Problem'].' ('.$data_in_column.')';
		}
		$this->assertEquals($data_in_column, $opdata_column->getText());

		// Check data in popup.
		foreach ($data['popup rows'] as $i => $popup_row) {
			$metric_in_column = CTestArrayHelper::get($popup_row, 'truncated', $popup_row['metric']);
			$opdata_column->query('link', $metric_in_column)->waitUntilClickable()->one()->click();
			$popup = $this->query('css:.overlay-dialogue.wordbreak')->asOverlayDialog()->one()->waitUntilVisible();
			$popup_table = $popup->asTable();
			// Check expected popup rows number.
			$this->assertEquals(count($data['popup rows']), $popup_table->getRows()->count());

			$row = $popup_table->getRow($i);
			$row->assertValues([$popup_row['item'], date('Y-m-d H:i:s', self::$time),
					$popup_row['metric'], $popup_row['button']]
			);

			// Check correct graph or history link.
			$row->query('link', $popup_row['button'])->one()->click();
			$this->page->waitUntilReady();
			$this->page->assertHeader($popup_row['header']);
			$this->page->open('zabbix.php?action=problem.view')->waitUntilReady();
		}
	}

	public function testPageProblems_ResetButton() {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Check table contents before filtering. Set false "Show timeline" because it makes table complicated.
		$form->fill(['Show timeline' => false]);
		$form->submit();
		$table->waitUntilReloaded();
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableColumnData('Problem');

		// Filter some problems.
		$form->invalidate();
		$form->fill(['Hosts' => '3_Host_to_check_Monitoring_Overview']);
		$form->submit();
		$table->waitUntilReloaded();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// Checking that filtered Problem matches expected.
		$this->assertTableDataColumn(['3_trigger_Average'], 'Problem');

		// After pressing reset button, check that previous problems are displayed again.
		$this->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();

		$form->fill(['Show timeline' => false]);
		$form->submit();
		$table->waitUntilReloaded();

		$reset_count = $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_count);
		$this->assertTableStats($reset_count);
		$this->assertEquals($start_contents, $this->getTableColumnData('Problem'));
	}
}
