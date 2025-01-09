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
 * @dataSource EntitiesTags
 * @dataSource Services
 *
 * @backup services
 *
 * @onBefore prepareServicesData
 */
class testFormServicesServices extends CWebTest {

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

	const UPDATE = true;
	const EDIT_BUTTON_PATH = 'xpath:.//button[@title="Edit"]';

	private static $service_sql = 'SELECT * FROM services ORDER BY serviceid';
	private static $update_service = 'Update service';
	private static $delete_service = 'Service for delete';
	private static $serviceids;

	public static function prepareServicesData() {
		self::$serviceids = CDataHelper::get('Services.serviceids');
	}

	/**
	 * Check Service create form layout.
	 */
	public function testFormServicesServices_Layout() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->query('id:list_mode')->one()->asSegmentedRadio()->waitUntilVisible()->select('Edit');
		$this->query('button:Create service')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:service-form')->asForm()->one();

		// Check tabs available in the form.
		$this->assertEquals(json_encode(['Service', 'Tags', 'Child services']), json_encode($form->getTabs()));

		$service_labels = [
			'Name' => true,
			'Parent services' => true,
			'Problem tags' => true,
			'Sort order (0->999)' => true,
			'Status calculation rule' => true,
			'Description' => true,
			'Advanced configuration' => true,
			'Additional rules' => false,
			'Status propagation rule' => false,
			'Weight' => false
		];

		// Check layout at Service tab.
		$hidden_fields = [];
		foreach ($service_labels as $label => $visible) {
			$this->assertEquals($visible, $form->getField($label)->isDisplayed());

			if (!$visible) {
				$hidden_fields[] = $label;
			}
		}

		// Check advanced configuration default closed state.
		$form->checkValue(['Advanced configuration' => false]);

		// Open "Advanced configuration" block and check that corresponding fields are now visible.
		$form->fill(['Advanced configuration' => true]);

		foreach ($hidden_fields as $label) {
			$this->assertTrue($form->getLabel($label)->isDisplayed());
		}

		// Check Problem tags table headers.
		$problem_tags_table = $form->query('id', 'problem_tags')->asMultifieldTable()->one();
		$this->assertSame(['Name', 'Operation', 'Value', ''], $problem_tags_table->getHeadersText());

		// Check Problem tags table fields.
		$problem_tags_table->checkValue([['tag' => '', 'operator' => 'Equals', 'value' => '']]);

		// Check table tags placeholders.
		foreach (['tag', 'value'] as $placeholder) {
			$this->assertEquals($placeholder, $problem_tags_table->query('id:problem_tags_0_'.$placeholder)
					->one()->getAttribute('placeholder')
			);
		}

		// Check Status rules table headers.
		$status_rules_table = $form->query('id', 'status_rules')->asMultifieldTable()->one();
		$this->assertSame(['Name', 'Actions'], $status_rules_table->getHeadersText());

		// Check Service tab fields' maxlengths.
		$service_tab_limits = [
			'Name' => 128,
			'Sort order (0->999)' => 3,
			'id:problem_tags_0_tag' => 255,
			'id:problem_tags_0_value' => 255,
			'Weight' => 7
		];
		foreach ($service_tab_limits as $field => $max_length) {
			$this->assertEquals($max_length, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check Sort order  and Weight default value.
		foreach (['Sort order (0->999)', 'Weight'] as $field) {
			$this->assertEquals(0, $form->getField($field)->getValue());
		}

		$dropdowns = [
			'name:problem_tags[0][operator]' => [
				'values' => ['Equals', 'Contains'],
				'default' => 'Equals'
			],
			'Status calculation rule' => [
				'values' => [
					'Most critical of child services',
					'Most critical if all children have problems',
					'Set status to OK'
				],
				'default' => 'Most critical of child services'
			],
			'Status propagation rule' => [
				'values' => [
					'As is',
					'Increase by',
					'Decrease by',
					'Ignore this service',
					'Fixed status'
				],
				'default' => 'As is'
			]
		];

		$this->checkDropdowns($dropdowns, $form);

		// Check radio buttons that are displayed for certain status propagation rules.
		$radio_buttons = [
			[
				'propagation_rule' => 'Increase by',
				'locator' => 'id:propagation_value_number',
				'values' => ['1', '2', '3', '4', '5'],
				'default' => '1'
			],
			[
				'propagation_rule' => 'Decrease by',
				'locator' => 'id:propagation_value_number',
				'values' => ['1', '2', '3', '4', '5'],
				'default' => '1'
			],
			[
				'propagation_rule' => 'Fixed status',
				'locator' => 'id:propagation_value_status',
				'values' => ['OK', 'Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
				'default' => 'OK'
			]
		];
		$propagation_rule_field = $form->getField('Status propagation rule')->asDropdown();

		foreach ($radio_buttons as $radio_params) {
			$propagation_rule_field->select($radio_params['propagation_rule']);
			$radio_element = $form->query($radio_params['locator'])->waitUntilVisible()->asSegmentedRadio()->one();
			$this->assertEquals($radio_params['default'], $radio_element->getText());
			$this->assertEquals($radio_params['values'], $radio_element->getLabels()->asText());
		}

		// Check the "New advanced rule" dialog layout.
		$form->getFieldContainer('Additional rules')->query('button:Add')->waitUntilClickable()->one()->click();
		$rules_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$rules_form = $rules_dialog->asForm();

		// Check dialog title.
		$this->assertEquals('New additional rule', $rules_dialog->getTitle());

		// Check the initially displayed fields in "New additional rule" dialog.
		$this->assertEquals(['Set status to', 'Condition', 'N', 'Status'], $rules_form->getLabels()->asText());

		// Check default and possible values of dropdown elements.
		$rules_dropdowns = [
			'Set status to' => [
				'values' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
				'default' => 'Not classified'
			],
			'Condition' => [
				'values' => [
					'If at least N child services have Status status or above',
					'If at least N% of child services have Status status or above',
					'If less than N child services have Status status or below',
					'If less than N% of child services have Status status or below',
					'If weight of child services with Status status or above is at least W',
					'If weight of child services with Status status or above is at least N%',
					'If weight of child services with Status status or below is less than W',
					'If weight of child services with Status status or below is less than N%'
				],
				'default' => 'If at least N child services have Status status or above'
			],
			'Status' => [
				'values' => ['OK', 'Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
				'default' => 'OK'
			]
		];

		$this->checkDropdowns($rules_dropdowns, $rules_form);

		// Check field "N" value and maxlength.
		$n_field = $rules_form->query('name:limit_value')->one();
		$this->assertEquals(7, $n_field->getAttribute('maxlength'));
		$this->assertEquals(1, $n_field->getValue());

		// Change the condition and check that N field was replaced with W field.
		$condition_field = $rules_form->getField('Condition');
		$condition_field->select('If weight of child services with Status status or above is at least W');

		$rules_form->invalidate();
		$this->assertEquals(['Set status to', 'Condition', 'W', 'Status'], $rules_form->getLabels()->asText());

		// Change the condition and check that "%" symbol is displayed when N is being counted in percentage.
		$condition_field->select('If weight of child services with Status status or above is at least N%');
		$this->assertTrue($rules_form->query('xpath:.//span[text()="%"]')->one()->isDisplayed());

		$rules_dialog->query('button:Cancel')->one()->click();

		// Check hint-box.
		$form->query('id:algorithm-not-applicable-warning')->one()->click();
		$hint = $form->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent();
		$hintbox = 'Status calculation rule and additional rules are only applicable if child services exist.';
		$this->assertEquals($hintbox, $hint->one()->getText());

		// Close the hint-box.
		$hint->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$hint->waitUntilNotPresent();

		// Check layout at Tags tab.
		$form->selectTab('Tags');
		$tags_tab = $form->query('id:tags-tab')->one();

		// Check Tags tab labels.
		$this->assertTrue($tags_tab->query('xpath:.//label[text()="Tags"]')->one()->isValid());

		// Check Tags default empty row and headers.
		$tags_tab->query('class:tags-table')->asMultifieldTable()->one()->checkValue([['Name' => '', 'Value' => '']]);

		// Check table tags placeholders and length.
		foreach (['tag' => 255, 'value' => 255] as $placeholder => $length) {
			$this->assertEquals($length, $tags_tab->query('id:tags_0_'.$placeholder)->one()->getAttribute('maxlength'));
			$this->assertEquals($placeholder, $tags_tab->query('id:tags_0_'.$placeholder)->one()->getAttribute('placeholder'));
		}

		// Check layout at Child services tab.
		$form->selectTab('Child services');
		$service_tab = $form->query('id:child-services-tab')->one();

		$filter = $service_tab->query('id:children-filter')->one();
		$this->assertEquals(255, $filter->query('id:children-filter-name')->one()->getAttribute('maxlength'));

		foreach (['Filter', 'Reset'] as $button) {
			$this->assertTrue($filter->query('button', $button)->one()->isClickable());
		}

		// Check Child services tab labels.
		$this->assertTrue($service_tab->query('xpath:.//label[text()="Child services"]')->one()->isValid());

		// Check Child services table header labels.
		$this->assertSame(['Service', 'Problem tags', 'Action'],
				$service_tab->query('id:children')->asTable()->one()->getHeadersText()
		);

		$form->getFieldContainer('Child services')->query('button:Add')->waitUntilClickable()->one()->click();
		$children_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		// Check popup title.
		$this->assertEquals('Add child services', $children_dialog->getTitle());

		// Check input fields maxlength.
		$this->assertEquals(255, $children_dialog->query('id:services-filter-name')->one()->getAttribute('maxlength'));

		// Check "select all" checkbox default value.
		$this->assertFalse($children_dialog->query('id:serviceid_all')->asCheckbox()->one()->isChecked());

		// Enter and submit filtering data.
		$children_dialog->query('id:services-filter-name')->one()->fill('Parent for 2 levels of child services');
		$this->assertTrue($children_dialog->query('button:Cancel')->one()->isCLickable());
		$children_dialog->query('button:Filter')->waitUntilClickable()->one()->click();
		$children_dialog->waitUntilReady();
		$children_dialog->invalidate();

		// Check filtering result.
		$result = [
			[
				'Name' => 'Parent for 2 levels of child services',
				'Tags' => 'test: test123',
				'Problem tags' => ''
			]
		];
		$this->assertTableData($result, 'xpath://div[@data-dialogueid="services"]//table[@class="list-table"]');

		// Check filtering reset.
		$children_dialog->query('button:Reset')->one()->waitUntilClickable()->click();
		$children_dialog->waitUntilReady();

		// Check possible children count in table.
		$this->assertEquals(CDBHelper::getCount('SELECT null FROM services'), $children_dialog->query('class:list-table')
				->asTable()->one()->getRows()->count()
		);

		foreach (['Add', 'Cancel'] as $button) {
			$this->assertTrue($dialog->getFooter()->query('button', $button)->one()->isClickable());
		}

		$children_dialog->close();
		$dialog->close();
	}

	public function getServicesData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ''
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Letters in sort order',
						'Sort order (0->999)' => 'zab'
					],
					'error' => 'Incorrect value "zab" for "sortorder" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative sort order',
						'Sort order (0->999)' => '-1'
					],
					'error' => 'Incorrect value for field "sortorder": value must be no less than "0".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty sort order',
						'Sort order (0->999)' => ''
					],
					'error' => 'Incorrect value "" for "sortorder" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-numeric weight',
						'Advanced configuration' => true,
						'Weight' => 'abc'
					],
					'error' => 'Incorrect value "abc" for "weight" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative weight',
						'Advanced configuration' => true,
						'Weight' => '-2'
					],
					'error' => 'Incorrect value for field "weight": value must be no less than "0".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Excessive weight',
						'Advanced configuration' => true,
						'Weight' => '9999999'
					],
					'error' => 'Incorrect value for field "weight": value must be no greater than "1000000".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-numeric N in additional rules',
						'Advanced configuration' => true
					],
					'additional_rules' => [
						[
							'Set status to' => 'Average',
							'Condition' => 'If at least N child services have Status status or above',
							'name:limit_value' => 'two',
							'Status' => 'Average'
						]
					],
					'error' => 'Incorrect value "two" for "limit_value" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative N in additional rules',
						'Advanced configuration' => true
					],
					'additional_rules' => [
						[
							'Set status to' => 'Warning',
							'Condition' => 'If at least N% of child services have Status status or above',
							'name:limit_value' => '-66',
							'Status' => 'High'
						]
					],
					'error' => 'Incorrect value for field "limit_value": value must be no less than "1".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'N more than 100% in additional rules',
						'Advanced configuration' => true
					],
					'additional_rules' => [
						[
							'Set status to' => 'Information',
							'Condition' => 'If at least N% of child services have Status status or above',
							'name:limit_value' => 101,
							'Status' => 'Disaster'
						]
					],
					'error' => 'Incorrect value for field "N": value must be no greater than "100".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'W is equal to 0 in additional rules',
						'Advanced configuration' => true
					],
					'additional_rules' => [
						[
							'Set status to' => 'Information',
							'Condition' => 'If weight of child services with Status status or above is at least W',
							'name:limit_value' => 0,
							'Status' => 'Not classified'
						]
					],
					'error' => 'Incorrect value for field "limit_value": value must be no less than "1".'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Min sort order',
						'Sort order (0->999)' => '0'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Max sort order, weight, etc',
						'Sort order (0->999)' => '999',
						'Advanced configuration' => true,
						'Status propagation rule' => 'Increase by',
						'id:propagation_value_number' => '5',
						'Weight' => '1000000'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Intermediate values in sort order',
						'Sort order (0->999)' => '10',
						'Advanced configuration' => true,
						'Status propagation rule' => 'Fixed status',
						'id:propagation_value_status' => 'OK',
						'Weight' => '5'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Fixed status',
						'Advanced configuration' => true,
						'Status propagation rule' => 'Fixed status',
						'id:propagation_value_status' => 'Not classified',
						'Weight' => '0'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Service with multiple additional rules',
						'Advanced configuration' => true
					],
					'additional_rules' => [
						[
							'Set status to' => 'Not classified',
							'Condition' => 'If at least N child services have Status status or above',
							'name:limit_value' => 1,
							'Status' => 'Disaster'
						],
						[
							'Set status to' => 'Information',
							'Condition' => 'If at least N% of child services have Status status or above',
							'name:limit_value' => 50,
							'Status' => 'High'
						],
						[
							'Set status to' => 'Warning',
							'Condition' => 'If less than N child services have Status status or below',
							'name:limit_value' => 3,
							'Status' => 'Average'
						],
						[
							'Set status to' => 'Average',
							'Condition' => 'If less than N% of child services have Status status or below',
							'name:limit_value' => 35,
							'Status' => 'Warning'
						],
						[
							'Set status to' => 'High',
							'Condition' => 'If weight of child services with Status status or above is at least W',
							'name:limit_value' => 20,
							'Status' => 'Information'
						],
						[
							'Set status to' => 'Disaster',
							'Condition' => 'If weight of child services with Status status or above is at least N%',
							'name:limit_value' => 66,
							'Status' => 'Not classified'
						],
						[
							'Set status to' => 'Disaster',
							'Condition' => 'If weight of child services with Status status or below is less than W',
							'name:limit_value' => 21,
							'Status' => 'Information'
						],
						[
							'Set status to' => 'Information',
							'Condition' => 'If weight of child services with Status status or below is less than N%',
							'name:limit_value' => 67,
							'Status' => 'Average'
						]
					],
					'rule_strings' => [
						'Not classified - If at least 1 child service has Disaster status or above',
						'Information - If at least 50% of child services have High status or above',
						'Warning - If less than 3 child services have Average status or below',
						'Average - If less than 35% of child services have Warning status or below',
						'High - If weight of child services with Information status or above is at least 20',
						'Disaster - If weight of child services with Not classified status or above is at least 66%',
						'Disaster - If weight of child services with Information status or below is less than 21',
						'Information - If weight of child services with Average status or below is less than 67%'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Service with children'
					],
					'children' => [
						'Child services' => [
							'Service' => 'Child 1',
							'Problem tags' => '',
							'Action' => 'Remove'
						]
					]
				]
			],
			// Check that you can create service with already existing name.
			[
				[
					'fields' => [
						'Name' => 'Service for duplicate check'
					],
					'duplicate' => true
				]
			],
			// This case should always be last, otherwise update scenario won't work.
			[
				[
					'fields' => [
						'Name' => 'With parent',
						'Parent services' => 'Parent for 2 levels of child services'
					],
					'update_duplicate' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getServicesData
	 *
	 * @backupOnce services
	 */
	public function testFormServicesServices_Create($data) {
		$this->checkForm($data);
	}

	public function getUpdateAdditionalRulesData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update rule: Non-numeric N in additional rules',
						'Advanced configuration' => true
					],
					'existing_rule' => 'High - If at least 50% of child services have Average status or above',
					'additional_rules' => [
						[
							'Set status to' => 'High',
							'Condition' => 'If at least N child services have Status status or above',
							'name:limit_value' => 'five',
							'Status' => 'High'
						]
					],
					'error' => 'Incorrect value "five" for "limit_value" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update rule: Negative N in additional rules',
						'Advanced configuration' => true
					],
					'existing_rule' => 'High - If at least 50% of child services have Average status or above',
					'additional_rules' => [
						[
							'Set status to' => 'Warning',
							'Condition' => 'If at least N% of child services have Status status or above',
							'name:limit_value' => '-66',
							'Status' => 'High'
						]
					],
					'error' => 'Incorrect value for field "limit_value": value must be no less than "1".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update rule: N more than 100% in additional rules',
						'Advanced configuration' => true
					],
					'existing_rule' => 'High - If at least 50% of child services have Average status or above',
					'additional_rules' => [
						[
							'Set status to' => 'Information',
							'Condition' => 'If at least N% of child services have Status status or above',
							'name:limit_value' => 101,
							'Status' => 'Disaster'
						]
					],
					'error' => 'Incorrect value for field "N": value must be no greater than "100".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Update rule: W is equal to 0 in additional rules',
						'Advanced configuration' => true
					],
					'existing_rule' => 'High - If at least 50% of child services have Average status or above',
					'additional_rules' => [
						[
							'Set status to' => 'Information',
							'Condition' => 'If weight of child services with Status status or above is at least W',
							'name:limit_value' => 0,
							'Status' => 'Not classified'
						]
					],
					'error' => 'Incorrect value for field "limit_value": value must be no less than "1".'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Update additional rule',
						'Advanced configuration' => true
					],
					'existing_rule' => 'High - If at least 50% of child services have Average status or above',
					'additional_rules' => [
						[
							'Set status to' => 'Disaster',
							'Condition' => 'If at least N child services have Status status or above',
							'name:limit_value' => 5,
							'Status' => 'Disaster'
						]
					],
					'rule_strings' => [
						'Disaster - If at least 5 child services have Disaster status or above'
					]
				]
			],
			// Additional rule is removed if the data provider contains the rule to be changed and doesn't have the substitute.
			[
				[
					'fields' => [
						'Name' => 'Remove additional rule',
						'Advanced configuration' => true
					],
					'existing_rule' => 'Disaster - If weight of child services with Warning status or below is less than 33%'
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateAdditionalRulesData
	 * @dataProvider getServicesData
	 */
	public function testFormServicesServices_Update($data) {
		$this->checkForm($data, self::UPDATE);
	}

	/**
	 * Function for checking Service create or update form, successful submitting and validation.
	 *
	 * @param array	    $data      data provider
	 * @param boolean	$update    true if it is update scenario, false if create(default)
	 */
	private function checkForm($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::$service_sql);
		}

		// Open service form depending on create or update scenario.
		$this->page->login()->open('zabbix.php?action=service.list.edit');
		if ($update) {
			$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();
			$table->findRow('Name', self::$update_service, true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()
					->one()->click();
		}
		else {
			$this->query('button:Create service')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:service-form')->asForm()->one()->waitUntilReady();
		$form->fill($data['fields']);

		// Remove additional rule if no substitute rules are defined in data provider or edit rule if such exists.
		if (array_key_exists('existing_rule', $data)) {
			$button = (array_key_exists('additional_rules', $data)) ? 'Edit' : 'Remove';

			foreach ($form->getField('Additional rules')->asTable()->getRows() as $existing_row) {
				if ($existing_row->getColumn('Name')->getText() === $data['existing_rule']) {
					$existing_row->query('button', $button)->one()->click();

					// If a row was deleted, check that  its no longer present.
					if ($button === 'Remove') {
						$this->assertFalse($existing_row->isPresent());
					}
				}
			}
		}

		foreach (CTestArrayHelper::get($data, 'additional_rules', []) as $rule_fields) {
			// Click Add button to add new rule. For editing additional rules the Additional rule dialog is already opened.
			if (!array_key_exists('existing_rule', $data)) {
				$form->getFieldContainer('Additional rules')->query('button:Add')->waitUntilClickable()->one()->click();
			}
			$rules_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$rules_form = $rules_dialog->asForm();
			$rules_form->fill($rule_fields);
			$rules_form->submit();

			if ($expected === TEST_BAD) {
				$this->assertMessage(TEST_BAD, null, $data['error']);
				$rules_dialog->close();
				$dialog->close();

				return;
			}

			$rules_form->waitUntilNotPresent();
		}

		if (array_key_exists('children', $data)) {
			$form->selectTab('Child services');

			$service = $form->getFieldContainer('Child services');
			$service->query('button:Add')->waitUntilClickable()->one()->click();
			$children_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$table = $children_dialog->query('class:list-table')->asTable()->waitUntilVisible()->one();
			$table->findRow('Name', $data['children']['Child services']['Service'])->select();
			$children_dialog->query('button:Select')->waitUntilClickable()->one()->click();
			$this->assertTableData([$data['children']['Child services']], 'id:children');
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$service_sql));
			$dialog->close();
		}
		else {
			$this->assertMessage(TEST_GOOD, ($update ? 'Service updated' : 'Service created'));
			$count = (array_key_exists('duplicate', $data)) ? 2 : 1;
			$this->assertEquals($count, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($data['fields']['Name']))
			);

			if ($update) {
				// In update scenario check that old name actually changed.
				$expected_count = (array_key_exists('update_duplicate', $data)) ? 1 : 0;
				$this->assertEquals($expected_count, CDBHelper::getCount('SELECT * FROM services WHERE name='.
						zbx_dbstr(self::$update_service))
				);

				//  Write new name to global variable for using it in next case.
				self::$update_service = $data['fields']['Name'];
			}

			// Open just created or updated Service and check that all fields present correctly in form.
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();

			// If it is child service, we need to open parent firstly.
			if (array_key_exists('Parent services', $data['fields'])) {
				$table->findRow('Name', $data['fields']['Parent services'], true)
						->query('link', $data['fields']['Parent services'])->waitUntilClickable()->one()->click();
			}

			if (array_key_exists('children', $data)) {
				$row = $table->findRow('Name', $data['fields']['Name'], true);

				$this->assertEquals($data['fields']['Name'].' '.count($data['children']),
						$row->getColumn('Name')->getText()
				);

				$row->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
				$form->selectTab('Child services');
				$this->assertTableData([$data['children']['Child services']], 'id:children');
			}
			else {
				// There are 3 tables with class list-table, so it is specified that is should be in the service list.
				$table = $this->query('xpath://form[@name="service_list"]//table')->asTable()->one()->waitUntilPresent();
				$table->findRow('Name', $data['fields']['Name'], true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()
						->one()->click();
				COverlayDialogElement::find()->one()->waitUntilReady();
			}
			$form->invalidate();

			// Open "Advanced configuration" block if it was filled with data.
			if (CTestArrayHelper::get($data, 'fields.Advanced configuration', false)) {
				// After form submit "Advanced configuration" is closed.
				$form->checkValue(['Advanced configuration' => false]);
				$form->fill(['Advanced configuration' => true]);
			}
			$form->checkValue($data['fields']);

			// Check that added/updated rules are present, and that removed rules are missing in configuration form.
			if (array_key_exists('rule_strings', $data) || array_key_exists('existing_rule', $data)) {
				$existing_rules = [];

				foreach ($form->getField('Additional rules')->asTable()->getRows() as $existing_row) {
					$existing_rules[] = $existing_row->getColumn('Name')->getText();
				}

				// If string should be present is determined by the presence of strings to be checked in the data provider.
				if (array_key_exists('rule_strings', $data)) {
					foreach ($data['rule_strings'] as $string) {
						$this->assertTrue(in_array($string, $existing_rules));
					}
				}
				else {
					$this->assertFalse(in_array($data['existing_rule'], $existing_rules));
				}
			}

			COverlayDialogElement::find()->one()->close();
		}
	}

	public static function getCloneData() {
		return [
			// Service with children.
			[
				[
					'name' => 'Clone parent',
					'children' => [
						'Child services' => [
							[
								'Service' => 'Clone child 1',
								'Problem tags' => 'problem_tag_clone: problem_value_clone',
								'Action' => 'Remove'
							],
							[
								'Service' => 'Clone child 2',
								'Problem tags' => '',
								'Action' => 'Remove'
							],
							[
								'Service' => 'Clone child 3',
								'Problem tags' => 'test1: value1',
								'Action' => 'Remove'
							]

						]
					]
				]
			],
			// Service with parent.
			[
				[
					'name' => 'Clone child 1',
					'parent' => 'Clone parent'
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormServicesServices_Clone($data) {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();

		if (CTestArrayHelper::get($data, 'parent')) {
			$table->findRow('Name', $data['parent'], true)->query('link', $data['parent'])
					->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
		}

		$table->findRow('Name', $data['name'], true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$original_values = $form->getFields()->asValues();

		// Check Child services before cloning.
		if (CTestArrayHelper::get($data, 'children')) {
			$form->selectTab('Child services');
			$this->assertTableData($data['children']['Child services'], 'id:children');
		}

		$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
		$dialog->waitUntilReady();
		$form->invalidate();
		$form->selectTab('Service');
		$name = 'New cloned name'.microtime();
		$form->fill(['Name' => $name]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service created');

		foreach([$name, $data['name']] as $service_name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($service_name)));
		}

		// Check cloned service saved form.
		$table->findRow('Name', $name, true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();
		$form->invalidate();
		$original_values['Name'] = $name;

		// If the date changed since the data source was executed, "Created at" for clone will differ from the original.
		$original_values['Created at'] = date('Y-m-d', strtotime('today'));

		$this->assertEquals($original_values, $form->getFields()->asValues());

		// Check Child services were not cloned.
		if (CTestArrayHelper::get($data, 'children')) {
			$form->selectTab('Child services');
			$this->assertEquals('', $form->query('xpath:.//table[@id="children"]/tbody')->one()->getText());
		}

		COverlayDialogElement::find()->one()->close();
	}

	public static function getCancelData() {
		return [
			// Service without children.
			[
				[
					'button_query' => 'xpath:.//button[@title="Close"]'
				]
			],
			// Service with children.
			[
				[
					'button_query' => 'button:Cancel'
				]
			]
		];
	}

	/**
	 * Test for cancelling form with changes.
	 *
	 * @dataProvider getCancelData
	 */
	public function testFormServicesServices_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::$service_sql);

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();
		$table->findRow('Name', 'Simple actions service', true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()
				->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->asForm()->fill([
			'Name' => 'Updated name',
			'Parent services' => 'Parent for deletion from row',
			'Sort order (0->999)' => '85',
			'Advanced configuration' => true,
			'Status propagation rule' => 'Increase by',
			'id:propagation_value_number' => '4',
			'Weight' => '9'
		]);

		$dialog->query($data['button_query'])->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$this->assertEquals($old_hash, CDBHelper::getHash(self::$service_sql));
	}

	public static function getSimpleUpdateData() {
		return [
			// Service without children.
			[
				[
					'name' => 'Simple actions service'
				]
			],
			// Service with children.
			[
				[
					'name' => 'Parent for 2 levels of child services'
				]
			]
		];
	}

	/**
	 * Test for updating service form without any changes.
	 *
	 * @dataProvider getSimpleUpdateData
	 */
	public function testFormServicesServices_SimpleUpdate($data) {
		$old_hash = CDBHelper::getHash(self::$service_sql);

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();
		$table->findRow('Name', $data['name'], true)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->query('id:service-form')->asForm()->one()->waitUntilReady()->submit();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Service updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$service_sql));
	}

	public function getCreateChildData() {
		return [
			[
				[
					'parent' => 'Service with problem tags',
					'enabled' => false
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'circular' => true,
					'parent' => 'Child 2',
					'fields' => [
						'Name' => 'Circular dependency'
					],
					'Child services' => 'Parent for deletion from row',
					'error' => 'Services form a circular dependency.'
				]
			],
			[
				[
					'parent' => 'Parent for child creation',
					'fields' => [
						'Name' => 'With parent without tags'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateChildData
	 */
	public function testFormServicesServices_CreateChild($data) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::$service_sql);
		}

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();

		if (CTestArrayHelper::get($data, 'circular', false)) {
			$table->findRow('Name', $data['Child services'], true)->query('link', $data['Child services'])
					->waitUntilClickable()->one()->click();
		}

		if (CTestArrayHelper::get($data, 'enabled', true)) {
			// Find necessary row and then find Add child button right in that row.
			$table->findRow('Name', $data['parent'], true)->query('xpath:.//button[@title="Add child service"]')
					->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();
			$form->fill($data['fields']);

			// Go to child services tab and add child there to create circular dependency.
			if (CTestArrayHelper::get($data, 'circular', false)) {
				$form->selectTab('Child services');
				$service = $form->getFieldContainer('Child services');
				$service->query('button:Add')->waitUntilClickable()->one()->click();
				$children_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$children_dialog->query('link', $data['Child services'])->waitUntilReady()->one()->click();
			}

			$form->submit();
			$this->page->waitUntilReady();
		}
		else {
			$this->assertFalse($table->findRow('Name', $data['parent'], true)
					->query('xpath:.//button[@title="Add child service"]')->one()->isClickable()
			);
			return;
		}

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$service_sql));

			$dialog->close();
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Service created');
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($data['fields']['Name']))
			);

			// Check that child is present in frontend.
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();

			// Check that Parent in table has Children count now.
			$this->assertEquals($data['parent'].' 1',
					$table->findRow('Name', $data['parent'], true)->getColumn('Name')->getText()
			);

			// Firstly click on parent.
			$table->findRow('Name', $data['parent'], true)->query('link', $data['parent'])
					->waitUntilClickable()->one()->click();
			$table->findRow('Name', $data['fields']['Name'])->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()
					->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asForm()->one();

			// Check that all form fields were saved correctly.
			$form->checkValue($data['fields']);

			// Check parent field separately, because it was not present in data[fields] array.
			$this->assertEquals([$data['parent']], $form->getField('Parent services')->getValue());
			$dialog->close();
		}
	}

	public function testFormServicesServices_DeleteChild() {
		$parent = 'Parent for deletion from row';
		$child = 'Child 2';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();

		$this->query('id:tab_info')->one()->waitUntilVisible()->query("xpath:.//button[".
				CXPathHelper::fromClass('js-edit-service')."]")->one()->waitUntilClickable()->click();

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->selectTab('Child services');

		// Go to "Child services" tab and find row by particular Service name in Children table.
		$service_table = $form->getFieldContainer('Child services')->asTable();
		$service_table->findRow('Service', $child, true)->query('button:Remove')->waitUntilClickable()->one()->click();

		// Make sure that Name disappeared right after removing.
		$this->assertFalse($service_table->query("xpath:.//table[@id='children']//td[contains(text(),".
				CXPathHelper::escapeQuotes($child).')]')->exists());
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'Service updated');

		// Check "No data found" text in table under Parent.
		$this->assertTableData([]);

		foreach ([$parent, $child] as $name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($name)));
		}

		// Check that service linking is disappeared from DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services_links WHERE serviceupid='.
				zbx_dbstr(self::$serviceids['Parent for deletion from row']).' AND servicedownid ='.
				zbx_dbstr(self::$serviceids['Child 2']))
		);
	}

	public function testFormServicesServices_DeleteParent() {
		$parent = 'Parent for child deletion from row';
		$child = 'Child 1';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$table->findRow('Name', $child, true)->query("xpath:.//button[".CXPathHelper::fromClass('js-edit-service-list')."]")
				->one()->waitUntilClickable()->click();

		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$form->getField('Parent services')->asMultiselect()->clear();
		$form->submit();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service updated');
		$this->assertTableData();

		foreach ([$parent, $child] as $name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($name)));
		}

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services_links WHERE serviceupid='.
				zbx_dbstr(self::$serviceids['Parent for child deletion from row']).' AND servicedownid ='.
				zbx_dbstr(self::$serviceids['Child 1']))
		);
	}

	public function testFormServicesServices_DeleteService() {
		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
		$table->findRow('Name', self::$delete_service)->query(self::EDIT_BUTTON_PATH)->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();
		$dialog->ensureNotPresent();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service deleted');
		$this->assertFalse($this->query('link', self::$delete_service)->one(false)->isValid());

		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr(self::$delete_service)));
	}

	/**
	 * Check all possible options and the default option for the provided dropdown element.
	 *
	 * @param array			$dropdowns	array with reference values of dropdown parameters
	 * @param CFormElement	$form		form that contains the dropdown elements under attention
	 */
	private function checkDropdowns($dropdowns, $form) {
		foreach ($dropdowns as $field => $options) {
			$dropdown = $form->getField($field);

			// Check default dropdown value.
			$this->assertEquals($options['default'], $dropdown->getText());

			// Check all possible dropdown values.
			$this->assertSame($options['values'], $dropdown->getOptions()->asText());
		}
	}
}
