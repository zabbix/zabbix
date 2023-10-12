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
 * @backup profiles
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

	public function testPageProblems_Layout() {
		$this->page->login()->open('zabbix.php?action=problem.view&show_timeline=0&filter_reset=1');
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

			if (CTestArrayHelper::get($attributes, 'placeholder')) {
				$this->assertEquals($attributes['placeholder'], $field->getAttribute('placeholder'));
			}

			if (CTestArrayHelper::get($attributes, 'maxlength')) {
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
		$this->assertEquals(false, $button_state);
	}

	public static function getFilterData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Host groups' => 'Empty group'
					],
					'result' => []
				]
			],
			// #1.
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
			// #2.
			[
				[
					'fields' => [
						'Host' => '3_Host_to_check_Monitoring_Overview'
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
					'check_trigger_description' => [true],
					'caheck_actions' => [true]
				]
			],
			// #3.
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
							'Host' => '3_Host_to_check_Monitoring_Overview',
							'Problem' => '3_trigger_Average',
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
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageProblems_Filter($data) {
		$this->page->login()->open('zabbix.php?action=problem.view&show_timeline=0&filter_reset=1');
		$form = CFilterElement::find()->one()->getForm();
		$table = $this->query('class:list-table')->waitUntilPresent()->one();

		if (CTestArrayHelper::get($data, 'Tags')) {
			$form->fill(['id:filter_evaltype' => $data['tag_options']['type']]);
			$this->setTags($data['tag_options']['tags']);
		}

		if (CTestArrayHelper::get($data, 'fields')) {
			$form->fill($data['fields']);
		}

		$form->submit();
		$table->waitUntilReloaded();

		$this->assertTableData($data['result']);
	}

	/**
	 * Search problems by "AND" or "OR" tag options
	 */
	public function testPageProblems_1FilterByTagsOptionAndOr() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		// Check the default tag filter option AND and tag value option Contains
		$result_form = $this->query('xpath://form[@name="problem"]')->one();
		$this->zbxTestClickButtonText('Reset');
		$result_form->waitUntilReloaded();
		$this->assertTrue($this->zbxTestCheckboxSelected('evaltype_00'));
		$form = $this->query('id:tabfilter_0')->asForm()->waitUntilPresent()->one();
		$this->zbxTestDropdownAssertSelected('tags_00_operator', 'Contains');

		// Select "AND" option and two tag names with partial "Contains" value match
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('Service');
		$this->query('name:tags_add')->one()->click();
		$form->query('name:tags[1][tag]')->one()->clear()->sendKeys('Database');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tags select to "OR" option
		$this->zbxTestClickXpath('//label[@for="evaltype_20"]');
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/a', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[4]/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 4 of 4 found');
	}

	/**
	 * Search problems by partial or exact tag value match
	 */
	public function testPageProblems_2FilterByTagsOptionContainsEquals() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$result_form = $this->query('xpath://form[@name="problem"]')->one();
		$this->zbxTestClickButtonText('Reset');
		$result_form->waitUntilReloaded();
		$form = $this->query('id:tabfilter_0')->asForm()->one();

		// Search by partial "Contains" tag value match
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('service');
		$form->query('name:tags[0][value]')->one()->clear()->sendKeys('abc');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tag value filter to "Equals"
		$this->zbxTestDropdownSelect('tags_00_operator', 'Equals');
		$this->query('name:filter_apply')->one()->click();
		$this->zbxTestAssertElementText('//tbody/tr[@class="nothing-to-show"]/td', 'No data found.');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');
	}

	/**
	 * Search problems by partial and exact tag value match and then remove one
	 */
	public function testPageProblems_3FilterByTagsOptionContainsEqualsAndRemoveOne() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$result_form = $this->query('xpath://form[@name="problem"]')->one();
		$this->zbxTestClickButtonText('Reset');
		$result_form->waitUntilReloaded();
		$form = $this->query('id:tabfilter_0')->asForm()->one();

		// Select tag option "OR" and exact "Equals" tag value match
		$this->zbxTestClickXpath('//label[@for="evaltype_20"]');
		$this->zbxTestDropdownSelect('tags_00_operator', 'Equals');

		// Filter by two tags
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('Service');
		$form->query('name:tags[0][value]')->one()->clear()->sendKeys('abc');
		$this->query('name:tags_add')->one()->click();
		$form->query('name:tags[1][tag]')->one()->clear()->sendKeys('service');
		$form->query('name:tags[1][value]')->one()->clear()->sendKeys('abc');

		// Search and check result
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr[1]/td[10]/a', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 2 of 2 found');

		// Remove first tag option
		$this->zbxTestClickXpath('//button[@name="tags[0][remove]"]');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
	}

	public static function getFilterByTagsExceptContainsEqualsData() {
		return [
			// "And" and "And/Or" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Trigger for tag permissions Oracle'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Trigger for tag permissions Oracle'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists'],
						['name' => 'Database', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Exists'],
						['name' => 'Database', 'operator' => 'Exists']
					],
					'expected_problems' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Trigger for tag permissions Oracle'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Does not exist'],
						['name' => 'Database', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Does not exist'],
						['name' => 'Database', 'operator' => 'Does not exist']
					],
					'absent_problems' => [
						'Trigger for tag permissions Oracle',
						'Test trigger with tag',
						'Trigger for tag permissions MySQL',
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium']
					],
					'absent_problems' => [
						'Inheritance trigger with tags'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium']
					],
					'absent_problems' => [
						'Inheritance trigger with tags'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'server', 'operator' => 'Does not equal', 'value' => 'selenium'],
						['name' => 'Beta', 'operator' => 'Does not equal', 'value' => 'b']
					],
					'absent_problems' => [
						'Inheritance trigger with tags',
						'Second test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Service', 'operator' => 'Does not equal', 'value' => 'abc'],
						['name' => 'Database', 'operator' => 'Does not equal']
					],
					'absent_problems' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a'],
						['name' => 'Delta', 'operator' => 'Does not contain', 'value' => 'd']
					],
					'absent_problems' => [
						'Third test trigger with tag priority',
						'First test trigger with tag priority'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						['name' => 'Alpha', 'operator' => 'Does not contain', 'value' => 'a'],
						['name' => 'Delta', 'operator' => 'Does not contain', 'value' => 'd']
					],
					'absent_problems' => [
						'First test trigger with tag priority'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterByTagsExceptContainsEqualsData
	 */
	public function testPageProblems_4FilterByTagsExceptContainsEquals($data) {
		$this->page->login()->open('zabbix.php?show_timeline=0&action=problem.view&sort=name&sortorder=ASC');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:evaltype_0' => $data['evaluation_type']]);
		$this->setTags($data['tags']);
		$table = $this->query('class:list-table')->waitUntilPresent()->asTable()->one();
		$this->query('name:filter_apply')->one()->click();
		$table->waitUntilReloaded();

		// We remove from the result list templates that is not displayed there.
		if (array_key_exists('absent_problems', $data)) {
			$filtering = $this->getTableColumnData('Problem');
			foreach ($data['absent_problems'] as $absence) {
				if (($key = array_search($absence, $filtering))) {
					unset($filtering[$key]);
				}
			}
			$filtering = array_values($filtering);
			$this->assertTableDataColumn($filtering, 'Problem');
		}
		else {
			$this->assertTableDataColumn($data['expected_problems'], 'Problem');
		}

		// Reset filter due to not influence further tests.
		$this->query('name:filter_reset')->one()->click();
	}

	/**
	 * Search by all options in filter
	 */
	public function testPageProblems_5FilterByAllOptions() {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$form = $this->query('id:tabfilter_0')->asForm()->one();
		$this->zbxTestClickButtonText('Reset');
		$form->waitUntilReloaded();

		// Select host group
		$this->zbxTestClickButtonMultiselect('groupids_0');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestCheckboxSelect('item_4');
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Select host
		$this->zbxTestClickButtonMultiselect('hostids_0');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		$this->zbxTestClickWait('spanid10084');

		// Select trigger
		$this->zbxTestClickButtonMultiselect('triggerids_0');
		$this->zbxTestLaunchOverlayDialog('Triggers');
		$this->zbxTestCheckboxSelect("item_'99250'");
		$this->zbxTestCheckboxSelect("item_'99251'");
		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Select"]');

		// Type problem name
		$this->zbxTestInputType('name_0', 'Test trigger');

		// Select average, high and disaster severities
		$this->query('name:zbx_filter')->asForm()->one()->getField('Severity')->fill(['Average', 'High', 'Disaster']);

		// Add tag
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('Service');
		$form->query('name:tags[0][value]')->one()->clear()->sendKeys('abc');
		// Check Acknowledgement status.
		$this->zbxTestCheckboxSelect('acknowledgement_status_1_0');
		// Check Show details
		$this->zbxTestCheckboxSelect('details_0');
		// Apply filter and check result
		$table = $this->query('xpath://table[@class="list-table"]')->asTable()->one();
		$this->query('name:filter_apply')->one()->click();
		$table->waitUntilReloaded();
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestClickButtonText('Reset');
	}

	public function testPageProblems_ShowTags() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$form = $this->query('id:tabfilter_0')->asForm()->one()->waitUntilVisible();
		$result_form = $this->query('xpath://form[@name="problem"]')->one();
		$this->zbxTestClickButtonText('Reset');
		$result_form->waitUntilReloaded();

		// Check Show tags NONE
		$form->query('name:tags[0][tag]')->one()->clear()->sendKeys('service');
		$this->zbxTestClickXpath('//label[@for="show_tags_00"]');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		// Check result
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestAssertElementNotPresentXpath('//thead/tr/th[text()="Tags"]');

		// Check Show tags 1
		$this->zbxTestClickXpath('//label[@for="show_tags_10"]');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();

		// Check Tags column in result
		$this->zbxTestAssertVisibleXpath('//thead/tr/th[text()="Tags"]');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestTextNotVisible('Database');
		$this->zbxTestTextNotVisible('Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');

		// Check Show tags 2
		$this->zbxTestClickXpath('//label[@for="show_tags_20"]');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		// Check tags in result
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[2]', 'Database');
		$this->zbxTestTextNotVisible('Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');
		// Check Show More tags hint button
		$this->zbxTestAssertVisibleXpath('//tr/td[14]/button['.CXPathHelper::fromClass('zi-more').']');

		// Check Show tags 3
		$this->zbxTestClickXpath('//label[@for="show_tags_30"]');
		$this->query('name:filter_apply')->one()->click();
		$result_form->waitUntilReloaded();
		// Check tags in result
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'service: abcdef');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[2]', 'Database');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[3]', 'Service: abc');
		$this->zbxTestTextNotVisible('Tag4');
		$this->zbxTestTextNotVisible('Tag5: 5');
		// Check Show More tags hint button
		$this->zbxTestAssertVisibleXpath('//tr/td[14]/button['.CXPathHelper::fromClass('zi-more').']');
	}

	public function getTagPriorityData() {
		return [
			// Check tag priority.
			[
				[
					'tag_priority' => 'Kappa',
					'show_tags' => '3',
					'sorting' => [
						'First test trigger with tag priority' => ['Alpha: a', 'Beta: b', 'Delta: d'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a', 'Iota: i'],
						'Fourth test trigger with tag priority' => ['Delta: t', 'Eta: e', 'Gamma: g']
					]
				]
			],
			[
				[
					'tag_priority' => 'Kappa, Beta',
					'show_tags' => '3',
					'sorting' => [
						'First test trigger with tag priority' => ['Beta: b', 'Alpha: a', 'Delta: d'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a', 'Iota: i'],
						'Fourth test trigger with tag priority' => ['Delta: t', 'Eta: e', 'Gamma: g']
					]
				]
			],
			[
				[
					'tag_priority' => 'Gamma, Kappa, Beta',
					'show_tags' => '3',
					'sorting' => [
						'First test trigger with tag priority' => ['Gamma: g','Beta: b', 'Alpha: a'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a', 'Iota: i'],
						'Fourth test trigger with tag priority' => ['Gamma: g','Delta: t', 'Eta: e']
					]
				]
			],
			// Check tag name format.
			[
				[
					'tag_priority' => 'Gamma, Kappa, Beta',
					'show_tags' => '3',
					'tag_name_format' => 'Shortened',
					'sorting' => [
						'First test trigger with tag priority' => ['Gam: g','Bet: b', 'Alp: a'],
						'Second test trigger with tag priority' => ['Bet: b', 'Eps: e', 'Eta: e'],
						'Third test trigger with tag priority' => ['Kap: k', 'Alp: a', 'Iot: i'],
						'Fourth test trigger with tag priority' => ['Gam: g','Del: t', 'Eta: e']
					]
				]
			],
			[
				[
					'tag_priority' => 'Gamma, Kappa, Beta',
					'show_tags' => '3',
					'tag_name_format' => 'None',
					'sorting' => [
						'First test trigger with tag priority' => ['g','b', 'a'],
						'Second test trigger with tag priority' => ['b', 'e', 'e'],
						'Third test trigger with tag priority' => ['k', 'a', 'i'],
						'Fourth test trigger with tag priority' => ['g','t', 'e']
					]
				]
			],
			// Check tags count.
			[
				[
					'tag_priority' => 'Kappa',
					'show_tags' => '2',
					'sorting' => [
						'First test trigger with tag priority' => ['Alpha: a', 'Beta: b'],
						'Second test trigger with tag priority' => ['Beta: b', 'Epsilon: e'],
						'Third test trigger with tag priority' => ['Kappa: k', 'Alpha: a'],
						'Fourth test trigger with tag priority' => ['Delta: t', 'Eta: e']
					]
				]
			],
			[
				[
					'tag_priority' => 'Kappa',
					'show_tags' => '1',
					'sorting' => [
						'First test trigger with tag priority' => ['Alpha: a'],
						'Second test trigger with tag priority' => ['Beta: b'],
						'Third test trigger with tag priority' => ['Kappa: k'],
						'Fourth test trigger with tag priority' => ['Delta: t']
					]
				]
			],
			[
				[
					'show_tags' => '0'
				]
			]
		];
	}

	/**
	 * @dataProvider getTagPriorityData
	 */
	public function testPageProblems_TagPriority($data) {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$table = $this->query('xpath://form[@name="problem"]')->one();
		$this->zbxTestClickButtonText('Reset');
		$table->waitUntilReloaded();
		$this->zbxTestInputType('name_0', 'trigger with tag priority');

		if (array_key_exists('show_tags', $data)) {
			$this->zbxTestClickXpath('//label[@for="show_tags_'.$data['show_tags'].'0"]');
		}

		if (array_key_exists('tag_priority', $data)) {
			$this->zbxTestInputType('tag_priority_0', $data['tag_priority']);
		}

		if (array_key_exists('tag_name_format', $data)) {
			$this->zbxTestClickXpath('//ul[@id="tag_name_format_0"]//label[text()="'.$data['tag_name_format'].'"]');
		}

		$this->query('name:filter_apply')->one()->click();
		$table->waitUntilReloaded();

		// Check tag priority sorting.
		if (array_key_exists('sorting', $data)) {
			foreach ($data['sorting'] as $problem => $tags) {
				$tags_priority = [];
				$get_tags_rows = $this->webDriver->findElements(WebDriverBy::xpath('//a[text()="'.$problem.'"]/../../td/span[@class="tag"]'));
				foreach ($get_tags_rows as $row) {
					$tags_priority[] = $row->getText();
				}
				$this->assertEquals($tags, $tags_priority);
			}
		}

		if ($data['show_tags'] === '0') {
			$this->zbxTestAssertElementNotPresentXpath('//th[text()="Tags"]');
			$this->zbxTestAssertElementPresentXpath('//input[@id="tag_priority_0"][@disabled]');
			$this->zbxTestAssertElementPresentXpath('//input[contains(@id, "tag_name_format_")][@disabled]');
		}
	}

	public function testPageProblems_SuppressedProblems() {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$result_form = $this->query('xpath://form[@name="problem"]')->one();
		$this->zbxTestClickButtonText('Reset');
		$result_form->waitUntilReloaded();

		$this->zbxTestClickButtonMultiselect('hostids_0');
		$this->zbxTestLaunchOverlayDialog('Hosts');
		COverlayDialogElement::find()->one()->waitUntilReady()->setDataContext('Host group for suppression');

		$this->zbxTestClickLinkTextWait('Host for suppression');
		COverlayDialogElement::ensureNotPresent();
		$this->query('name:filter_apply')->one()->waitUntilClickable()->click();
		$result_form->waitUntilReloaded();

		$this->zbxTestTextNotPresent('Trigger_for_suppression');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');

		$this->zbxTestCheckboxSelect('show_suppressed_0');
		$this->query('name:filter_apply')->one()->click();

		$this->zbxTestAssertElementText('//tbody/tr/td[10]/a', 'Trigger_for_suppression');
		$this->zbxTestAssertElementText('//tbody/tr/td[14]/span[1]', 'SupTag: A');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');

		// Click on suppression icon and check text in hintbox.
		$this->zbxTestClickXpathWait('//tbody/tr/td[8]/div/button['.CXPathHelper::fromClass('zi-eye-off').']');
		$this->zbxTestAssertElementText('//div[@data-hintboxid]', 'Suppressed till: 12:17 Maintenance: Maintenance for suppression test');
	}
}
