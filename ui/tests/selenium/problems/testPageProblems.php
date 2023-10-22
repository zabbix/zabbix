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
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';

/**
 * @backup profiles, users
 *
 * @onBefore changeRefreshInterval
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

	/**
	 * Change refresh interval so Problems page doesn't refresh automatically,
	 * and popup dialogs don't disappear.
	 */
	public function changeRefreshInterval() {
		DBexecute('UPDATE users SET refresh=999 WHERE username='.zbx_dbstr('Admin'));
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
		$filter_form->getRequiredLabels([]);

		$fields_values = [
			'Show' => ['value' => 'Recent problems', 'enabled' => true],
			'id:groupids_0_ms' => ['value' => '', 'enabled' => true, 'placeholder' => 'type here to search'],
			'id:hostids_0_ms' => ['value' => '', 'enabled' => true, 'placeholder' => 'type here to search'],
			'id:triggerids_0_ms' => ['value' => '', 'enabled' => true, 'placeholder' => 'type here to search'],
			'Problem' => ['value' => '', 'enabled' => true, 'maxlength' => 255],
			'Not classified' => ['value' => false, 'enabled' => true],
			'Information' => ['value' => false, 'enabled' => true],
			'Warning' => ['value' => false, 'enabled' => true],
			'Average' => ['value' => false, 'enabled' => true],
			'High' => ['value' => false, 'enabled' => true],
			'Disaster' => ['value' => false, 'enabled' => true],
			'name:age_state' => ['value' => false, 'enabled' => true],
			'name:age' => ['value' => 14, 'enabled' => false],
			'Show symptoms' => ['value' => false, 'enabled' => true],
			'Show suppressed problems' => ['value' => false, 'enabled' => true],
			'Acknowledgement status' => ['value' => 'All', 'enabled' => true],
			'id:acknowledged_by_me_0' => ['value' => false, 'enabled' => false],
			'name:inventory[0][field]' => ['value' => 'Type', 'enabled' => true],
			'name:inventory[0][value]' => ['value' => '', 'enabled' => true, 'maxlength' => 255],
			'id:evaltype_0' => ['value' => 'And/Or', 'enabled' => true],
			'name:tags[0][tag]' => ['value' => '', 'enabled' => true, 'placeholder' => 'tag', 'maxlength' => 255],
			'id:tags_00_operator' => ['value' => 'Contains', 'enabled' => true],
			'id:tags_00_value' => ['value' => '', 'enabled' => true, 'placeholder' => 'value', 'maxlength' => 255],
			'Show tags' => ['value' => 3, 'enabled' => true],
			'id:tag_name_format_0' => ['value' => 'Full', 'enabled' => true],
			'Tag display priority' => ['value' => '', 'enabled' => true, 'placeholder' => 'comma-separated list', 'maxlength' => 255],
			'Show operational data' => ['value' => 'None', 'enabled' => true],
			'Compact view' => ['value' => false, 'enabled' => true],
			'Show details' => ['value' => false, 'enabled' => true],
			'id:show_timeline_0' => ['value' => true, 'enabled' => true],
			'id:highlight_row_0' => ['value' => false, 'enabled' => false]
		];

		foreach ($fields_values as $label => $attributes) {
			$field = $filter_form->getField($label);
			$this->assertEquals($attributes['value'], $field->getValue());
			$this->assertTrue($field->isVisible());
			$this->assertTrue($field->isEnabled($attributes['enabled']));

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $field->getAttribute('placeholder'));
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $field->getAttribute('maxlength'));
			}
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
			$this->assertEquals($labels,  $filter_form->getField($field)->asSegmentedRadio()->getLabels()->asText());
		}

		$dropdowns = [
			'name:inventory[0][field]' => ['Type', 'Type (Full details)', 'Name', 'Alias', 'OS', 'OS (Full details)',
					'OS (Short)', 'Serial number A', 'Serial number B', 'Tag', 'Asset tag',  'MAC address A',
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
				'xpath://a[contains(@class, "zi-clock")]' => true
			],
			'Problems' => [
				'xpath://button[@data-action="selectPrevTab"]' => true,
				'xpath://button[@data-action="toggleTabsList"]' => true,
				'xpath://button[@data-action="selectNextTab"]' => true,
				'xpath://button[contains(@class, "js-btn-time-left")]' => false,
				'button:Zoom out' => false,
				'xpath://button[contains(@class, "js-btn-time-right")]' => false,
				'xpath://a[contains(@class, "zi-clock")]' => false
			]
		];

		foreach ($dependant_fields as $show => $checked_elements) {
			$filter_form->fill(['Show' => $show]);

			if ($show === 'History') {
				$age_field->waitUntilNotVisible();
				$fields_values['Show']['value'] = 'History';
				$fields_values['name:age_state']['visible'] = false;
				$fields_values['name:age_state']['enabled'] = false;
				$fields_values['name:age']['visible'] = false;
			}
			else {
				$age_field->waitUntilVisible();
				$fields_values['Show']['value'] = 'Problems';
				$fields_values['name:age_state']['visible'] = true;
				$fields_values['name:age_state']['enabled'] = true;
				$fields_values['name:age']['visible'] = true;
			}

			foreach ($checked_elements as $query => $state) {
				$this->assertTrue($this->query($query)->one()->isEnabled($state));
			}

			foreach ($fields_values as $label => $attributes) {
				$field = $filter_form->getField($label);
				$this->assertTrue($field->isVisible(CTestArrayHelper::get($attributes, 'visible', true)));
				$this->assertTrue($field->isEnabled($attributes['enabled']));
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

			foreach (['Show operational data', 'Show details', 'id:show_timeline_0'] as $field) {
				$this->assertTrue($filter_form->getField($field)->isEnabled(!$state));
			}
			$this->assertTrue($filter_form->getField('id:highlight_row_0')->isEnabled($state));
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
			['label' => 'Show', 'value' => 'Recent problems', 'headers' => ['Recovery time', 'Status', 'Info', 'Host',
					'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			['label' => 'Show', 'value' => 'History', 'headers' => ['Recovery time', 'Status', 'Info', 'Host', 'Problem',
				'Duration', 'Update', 'Actions', 'Tags']
			],
			['label' => 'Show', 'value' => 'Problems', 'headers' => ['Info', 'Host', 'Problem', 'Duration',
					'Update', 'Actions', 'Tags']
			],
			['label' => 'Show tags', 'value' => 'None', 'headers' => ['Info', 'Host', 'Problem', 'Duration',
					'Update', 'Actions']
			],
			['label' => 'Show tags', 'value' => 1, 'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update',
					'Actions', 'Tags']
			],
			['label' => 'Show tags', 'value' => 2, 'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update',
					'Actions', 'Tags']
			],
			['label' => 'Show tags', 'value' => 3, 'headers' => ['Info', 'Host', 'Problem', 'Duration', 'Update',
					'Actions', 'Tags']
			],
			['label' =>'Show operational data', 'value' => 'None', 'headers' => ['Info', 'Host', 'Problem',  'Duration',
					'Update', 'Actions', 'Tags']
			],
			['label' =>'Show operational data', 'value' => 'Separately', 'headers' => ['Info', 'Host', 'Problem',
					'Operational data', 'Duration', 'Update', 'Actions', 'Tags']
			],
			['label' =>'Show operational data', 'value' => 'With problem name', 'headers' => ['Info', 'Host',
					'Problem', 'Duration', 'Update', 'Actions', 'Tags']
			],
			['label' =>'id:show_timeline_0', 'value' => false, 'headers' => [ 'Info', 'Host', 'Problem', 'Duration',
					'Update', 'Actions', 'Tags']
			],
			['label' =>'id:show_timeline_0', 'value' => true, 'headers' => ['Info', 'Host', 'Problem', 'Duration',
					'Update', 'Actions', 'Tags']
			]
		];

		foreach ($dependant_headers as $field) {
			$filter_form->fill([$field['label'] => $field['value']]);
			$filter_form->submit();
			$table->waitUntilReloaded();
			$start_headers = ($field['label'] === 'id:show_timeline_0' && !$field['value'])
				? ['', 'Time', 'Severity']
				: ['', 'Time', '', '', 'Severity'];
			$this->assertEquals(array_merge($start_headers, $field['headers']), $table->getHeadersText());
		}

		// Check that some unfiltered data is displayed in the table.
		$this->assertTableStats(CDBHelper::getCount(
				'SELECT null FROM problem'.
				' WHERE eventid'.
					' NOT IN (SELECT eventid FROM event_suppress)'
		));

		// Check Mass update button.
		$button_state = $this->query('button:Mass update')->one()->isClickable();
		$this->assertEquals(false, $button_state);
		$table->getRow(0)->select();
		$this->assertSelectedCount(1);
		$this->assertEquals(false, $button_state);
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
								'Time' => '2018-08-07 08:05:35 AM',
								'User/Recipient' => 'Admin (Zabbix Administrator)',
								'Action' => '',
								'Message/Command' => '',
								'Status' => '',
								'Info' => ''
							],
							[
								'Time' => '2018-08-06 11:42:06 AM',
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
								'Time' => '2018-08-07 08:05:35 AM',
								'User/Recipient' => 'Admin (Zabbix Administrator)',
								'Action' => '',
								'Message/Command' => '',
								'Status' => '',
								'Info' => ''
							],
							[
								'Time' => '2018-08-06 11:42:06 AM',
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
							'Severity' => 'Warning',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => 'Test trigger with tag',
							'Update' => 'Update',
							'Tags' => 'Service: abc'
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
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #9 "Or" and operator Exists, one tag.
			[
				[
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Trigger for tag permissions MySQL'],
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
					'Tags' => [
						'Type' => 'Or',
						'tags' => [
							['name' => 'Service', 'operator' => 'Exists'],
							['name' => 'Database', 'operator' => 'Exists']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => 'Test trigger to check tag filter on problem page']
					]
				]
			],
			// #12 "And/Or" and operator Does not exist.
			[
				[
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Trigger for tag permissions MySQL'],
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
						'id:show_timeline_0' => false
					],
					'Tags' => [
						'Type' => 'And/Or',
						'tags' => [
							['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium']
						]
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Test trigger with tag'],
						['Problem' => 'Trigger for tag permissions MySQL'],
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'id:show_timeline_0' => false
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
						'Acknowledgement status' => 'Unacknowledged',
						'Host inventory' => [
							'action' => USER_ACTION_UPDATE, 'index' => 0,
							'field' => 'Location latitude', 'value' => '56.95387'
						],
						'Show tags' => 3,
						'id:tag_name_format_0' => 'Shortened',
						'Tag display priority' => 'Tag4',
						'Show operational data' => 'Separately',
						'Show details' => true,
						'id:show_timeline_0' => true,
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
							'Time' => '2020-10-23 12:33:48 PM',
							'' => '',
							'' => '',
							'Severity' => 'Average',
							'Recovery time' => '',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'ЗАББИКС Сервер',
							'Problem' => "Test trigger to check tag filter on problem page\navg(/Test host/proc.num,5m)>100",
							'Operational data' => '*UNKNOWN*',
							'Duration' => '3y',
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
						'Tag display priority' => 'Kappa',
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
						'Tag display priority' => 'Kappa, Beta',
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
						'Tag display priority' => 'Gamma, Kappa, Beta',
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
					'check_suppressed' => "Suppressed till: 09:17 AM\nMaintenance: Maintenance for suppression test"
				]
			],
			// #34 Show timeline.
			[
				[
					'fields' => [
						'Hosts' => 'ЗАББИКС Сервер',
						'Warning' => true,
						'Show timeline' => true
					],
					'result' => [
						['Problem' => 'Test trigger with tag'],
						['' => 'October'],
						['Problem' => 'Fourth test trigger with tag priority'],
						['Problem' => 'Third test trigger with tag priority'],
						['Problem' => 'Second test trigger with tag priority'],
						['Problem' => 'First test trigger with tag priority']
					]
				]
			],
			// #35 Age filter.
			[
				[
					'fields' => [
						'id:age_state_0' => true,
						'name:age' => 999
					],
					'result' => []
				]
			],
			// #36 History.
			[
				[
					'fields' => [
						'Show' => 'History'
					],
					'result' => []
				]
			],
			// #37 Problems.
			[
				[
					'fields' => [
						'Show' => 'Problems',
						'Not classified' => true,
						'id:show_timeline_0' => false
					],
					'result' => [
						['Problem' => 'Trigger for tag permissions Oracle'],
						['Problem' => 'Trigger for tag permissions MySQL'],
						['Problem' => '1_trigger_Not_classified']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageProblems_Filter($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&show_timeline=0&filter_reset=1&sort=clock&sortorder=ASC');
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
		$this->assertTableData($data['result']);

		$problem_count = array_key_exists('fields', $data)
			? (CTestArrayHelper::get($data['fields'], 'Show timeline') ? (count($data['result']) - 1) :  count($data['result']))
			:  count($data['result']);
		$this->assertTableStats($problem_count);

		$dialog = $this->query('xpath://div[@class="overlay-dialogue"]');
		if (array_key_exists('check_trigger_description', $data)) {
			foreach ($data['check_trigger_description'] as $i => $description) {
				$cell = $table->getRow($i)->getColumn('Problem');

				if (!$description) {
					$this->assertFalse($cell->query('xpath:.//button[contains(@class, "zi-alert-with-content")]')->exists());
				}
				else {
					$cell->query('tag:button')->one()->waitUntilClickable()->click();
					$description_dialog = $dialog->one()->waitUntilVisible();
					$this->assertEquals($description, $description_dialog->getText());
					$description_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
					$description_dialog->waitUntilNotPresent();
				}
			}
		}

//		if (array_key_exists('check_actions', $data)) {
//			foreach ($data['check_actions'] as $i => $action) {
//				$cell = $table->getRow($i)->getColumn('Actions');
//				$tick = $cell->query('xpath:.//span[@title="Acknowledged"]');
//
//				if (!$action) {
//					$this->assertFalse($tick->exists());
//				}
//				else {
//					$this->assertTrue($tick->exists());
//					$cell->query('tag:button')->waitUntilClickable()->one()->click();
//					$action_dialog = $dialog->one()->waitUntilVisible();
//					$this->assertTableData($action, 'xpath://div[@class="overlay-dialogue"]//table');
//					$action_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
//					$action_dialog->waitUntilNotPresent();
//				}
//			}
//		}

		if (array_key_exists('check_trigger_dependency', $data)) {
			foreach ($data['check_trigger_dependency'] as $i => $dependency) {
				$arrow = $table->getRow($i)->getColumn('Problem')->query('tag:button');

				if (!$dependency) {
					$this->assertFalse($arrow->exists());
				}
				else {
					$arrow->one()->click();
					$dependency_dialog = $dialog->one()->waitUntilVisible();
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
				$popup = $dialog->one()->waitUntilVisible();
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
			$suppressed_dialog = $dialog->one()->waitUntilVisible();
			$this->assertEquals($data['check_suppressed'], $suppressed_dialog->getText());
			$suppressed_dialog->query('xpath:.//button[@title="Close"]')->one()->click();
			$suppressed_dialog->waitUntilNotPresent();
		}
	}

	public function testPageProblems_ResetButton() {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Check table contents before filtering. Show timeline is true by default, that's why there is one additional row.
		$start_rows_count = $table->getRows()->count() - 1;
		$this->assertTableStats($start_rows_count);
		$start_contents = $table->getRows()->asText();

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

		$reset_count = $table->getRows()->count() - 1;
		$this->assertEquals($start_rows_count, $reset_count);
		$this->assertTableStats($reset_count);
		$this->assertEquals($start_contents, $table->getRows()->asText());
	}
}
