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

/**
 * @dataSource WebScenarios
 *
 * @onBefore getContextData
 *
 * @backup httptest
 */
class testFormWebScenarioStep extends CWebTest {

	protected static $templateid;
	protected static $update_step = 'step 2 of clone scenario';

	const IMPACTLESS_STEP = 'Первый этап вэб сценария';
	const HOSTID = 40001;
	const TEMPLATE_SCENARIO = 'Template_Web_scenario';
	const UPDATE_SCENARIO = 'Scenario for Clone';
	const CREATE_SCENARIO = 'Scenario for Update';
	const SQL = 'SELECT * FROM httpstep hs INNER JOIN httpstep_field hsf ON hsf.httpstepid = hs.httpstepid';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Get the necessary properties of entities used within this test.
	 */
	public static function getContextData() {
		self::$templateid = CDataHelper::get('WebScenarios.templateid');
	}

	public static function getStepLayoutData() {
		return [
			[
				[
					'context' => 'host'
				]
			],
			[
				[
					'scenario_name' => self::TEMPLATE_SCENARIO,
					'step_name' => 'step 1 of scenario 1',
					'context' => 'host'
				]
			],
			[
				[
					'scenario_name' => self::TEMPLATE_SCENARIO,
					'context' => 'template'
				]
			]
		];
	}

	/**
	 * @dataProvider getStepLayoutData
	 */
	public function testFormWebScenarioStep_CheckLayout($data) {
		$context_id = ($data['context'] === 'host') ? self::HOSTID : self::$templateid;
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.$context_id.'&context='.$data['context'])
				->waitUntilReady();

		// Open the step configuration form for the corresponding web scenario.
		$selector = (array_key_exists('scenario_name', $data)) ? $data['scenario_name'] : self::UPDATE_SCENARIO;

		$this->query('link', $selector)->waitUntilClickable()->one()->click();
		$scenario_form = $this->query('name:webscenario_form')->waitUntilVisible()->asForm()->one();

		$scenario_form->selectTab('Steps');
		$steps_table = $scenario_form->getField('Steps')->asTable();

		// Steps cannot be added to templated web scenario from host, so in this case the existing step is opened.
		if (array_key_exists('step_name', $data)) {
			$steps_table->findRow('Name', $data['step_name'])->getColumn('Name')->click();
		}
		else {
			$steps_table->query('button:Add')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$step_form = $dialog->asForm();

		$title = (array_key_exists('step_name', $data)) ? 'Step of web scenario' : 'New step of web scenario';
		$this->assertEquals($title, $dialog->getTitle());

		$step_fields = [
			'Name' => ['maxlength' => 64],
			'id:url' => [],
			'xpath:(.//table[@id="step-query-fields"]//textarea)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(.//table[@id="step-query-fields"]//textarea)[2]' => ['placeholder' => 'value', 'maxlength' => 255],
			'xpath:(.//table[@id="step-post-fields"]//textarea)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(.//table[@id="step-post-fields"]//textarea)[2]' => ['placeholder' => 'value', 'maxlength' => 65535],
			'Post type' => ['value' => 'Form data'],
			'Raw post' => ['visible' => false],
			'xpath:(.//table[@id="step-variables"]//textarea)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(.//table[@id="step-variables"]//textarea)[2]' => ['placeholder' => 'value', 'maxlength' => 65535],
			'xpath:(.//table[@id="step-headers"]//textarea)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(.//table[@id="step-headers"]//textarea)[2]' => ['placeholder' => 'value', 'maxlength' => 65535],
			'Follow redirects' => ['value' => false],
			'Retrieve mode' => ['value' => 'Body'],
			'Timeout' => ['value' => '15s', 'maxlength' => 255],
			'Required string' => ['placeholder' => 'pattern', 'maxlength' => 255],
			'Required status codes' => ['maxlength' => 255]
		];

		// Differences between step creation form and update form of templated scenario step should be taken into account.
		if (array_key_exists('step_name', $data)) {
			$step_fields['Name'] = ['value' => $data['step_name'], 'enabled' => false, 'maxlength' => 64];
			$step_fields['id:url']['value'] = 'http://zabbix.com';
			$step_fields['Post type']['value'] = 'Raw data';
			$step_fields['Raw post']['visible'] = true;
			$step_fields['Follow redirects']['value'] = true;

			$initial_type = 'Raw data';
			$new_type = 'Form data';
			$buttons = ['Parse', 'Update', 'Cancel'];
			$post_fields = ['Post type', 'Raw post'];

			foreach (['[1]', '[2]'] as $element_index) {
				$xpath = 'xpath:(.//table[@id="step-post-fields"]//textarea)'.$element_index;

				unset($step_fields[$xpath]);
				$this->assertFalse($step_form->query($xpath)->one(false)->isValid());
			}
		}
		else {
			$initial_type = 'Form data';
			$new_type = 'Raw data';
			$buttons = ['Parse', 'Add', 'Cancel'];
			$post_fields = ['Post type', 'Post fields'];
		}

		foreach ($step_fields as $field => $attributes) {
			$value = (array_key_exists('value', $attributes)) ? $attributes['value'] : '';
			$visible = (array_key_exists('visible', $attributes)) ? $attributes['visible'] : true;
			$enabled = (array_key_exists('enabled', $attributes)) ? $attributes['enabled'] : true;

			$this->assertEquals($visible, $step_form->getField($field)->isVisible(), $field.' visibility is not '.$visible);
			$this->assertEquals($value, $step_form->getField($field)->getValue());
			$this->assertTrue($step_form->getField($field)->isEnabled($enabled));

			foreach (['maxlength', 'placeholder'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $step_form->getField($field)->getAttribute($attribute));
				}
			}
		}

		// Check mandatory fields.
		$this->assertEquals(['Name', 'URL', 'Timeout'], array_values($step_form->getRequiredLabels()));

		// Check Labels in radio buttons.
		$radio_buttons = [
			'Post type' => ['Form data', 'Raw data'],
			'Retrieve mode' => ['Body', 'Headers', 'Body and headers']
		];

		foreach ($radio_buttons as $radio_button => $labels) {
			$this->assertEquals($labels, $step_form->getField($radio_button)->getLabels()->asText());
		}

		// Check that changing the value in Post type field results in displaying either Post fields or Raw post field.
		$field_visibility = [
			'Form data' => ['Post fields' => true, 'Raw post' => false],
			'Raw data' => ['Post fields' => false, 'Raw post' => true]
		];

		// Check visibility of the post related fields based on the initially selected post type.
		foreach ([$new_type, $initial_type] as $post_type) {
			$step_form->getField('Post type')->select($post_type);

			foreach ($field_visibility[$post_type] as $post_field => $visible) {
				$this->assertEquals($visible, $step_form->getField($post_field)->isDisplayed());
			}
		}

		$mode_field = $step_form->getField('Retrieve mode');

		// Check that "Post fields" related fields are disabled only when Retrieve mode is set to Headers.
		foreach (['Body and headers' => true, 'Headers' => false, 'Body' => true] as $value => $enabled) {
			$mode_field->select($value);

			foreach ($post_fields as $post_field) {
				/*
				 * Post fields table has the disabled class even when enabled to disable the drag icon if table has only
				 * one row. Therefore, the state of all four interactable elements is checked induvidually.
				 */
				if ($post_field === 'Post fields') {
					$post_fields_table = $step_form->getField($post_field);

					foreach (['xpath:(.//textarea)[1]', 'xpath:(.//textarea)[2]', 'button:Add', 'button:Remove'] as $element) {
						$this->assertTrue($post_fields_table->query($element)->one()->isEnabled($enabled));
					}
				}
				else {
					$this->assertTrue($step_form->getField($post_field)->isEnabled($enabled));
				}
			}
		}

		// Set Post type to Form data in case of templated web scenario, so that Post field table would be displayed.
		if (array_key_exists('step_name', $data)) {
			$step_form->getField('Post type')->select('Form data');
		}

		// Check the layout of table fields.
		$table_layout = [
			'headers' => ['', 'Name', '', 'Value', '']
		];

		// Check the layout of the value pair field tables.
		foreach (['Query fields', 'Post fields', 'Variables', 'Headers'] as $table_name) {
			$table = $step_form->getField($table_name)->asTable();
			$row = $table->getRow(0);
			$this->assertSame($table_layout['headers'], $table->getHeadersText());

			// Check that Add button is clickable and the Remove button is not.
			$add_button = $table->query('button:Add')->one();
			$this->assertTrue($add_button->isClickable());
			$remove_button = $row->query('button:Remove')->one();
			$this->assertTrue($remove_button->isClickable());
			$remove_button->click();
			$this->assertFalse($remove_button->isClickable());

			// Check the presence of the draggable icon.
			if ($table_name === 'Variables') {
				$this->assertFalse($table->query('class:sortable sortable-disabled')->one(false)->isValid());
			}
			else {
				$this->assertEquals('sortable sortable-disabled',
						$table->query('xpath:.//tbody')->one()->getAttribute('class')
				);
			}

			// Fill in some data in first row and check that Remove buttons and draggable icon became enabled.
			foreach(['Name', 'Value'] as $column) {
				$row->getColumn($column)->query('xpath:./textarea')->one()->fill('zabbix');
			}
			$this->assertTrue($row->query('button:Remove')->one()->isClickable());

			// Check that draggable icon becomes enabled when a new row is added.
			if ($table_name !== 'Variables') {
				$this->assertEquals('sortable sortable-disabled',
						$table->query('xpath:.//tbody')->one()->getAttribute('class')
				);
				$add_button->click();
				$this->assertEquals('sortable', $table->query('xpath:.//tbody')->one()->getAttribute('class'));
			}
		}

		// Check the buttons in the web scenario step configuration form.
		foreach ($buttons as $button) {
			$this->assertTrue($dialog->query('button', $button)->one()->isClickable());
		}

		$dialog->close();
	}

	public static function getWebScenarioStepData() {
		return [
			// #0 Empty name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => '',
						'id:url' => 'https://zabbix.com'
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// #1 Empty space in name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => '   ',
						'id:url' => 'https://zabbix.com'
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// #2 Empty URL.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with empty URL',
						'id:url' => ''
					],
					'error' => 'Incorrect value for field "url": cannot be empty.'
				]
			],
			// #3 Blank space in URL.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with blank space in URL',
						'id:url' => '   '
					],
					'error' => 'Incorrect value for field "url": cannot be empty.'
				]
			],
			// #4 Empty Query field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing query field name',
						'id:url' => 'http://zabbix.com'
					],
					'Query fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '',
							'value' => 'query field value'
						]
					],
					'error' => 'Incorrect value for field "query_fields/1/name": cannot be empty.'
				]
			],
			// #5 Empty Post field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing post field name',
						'Post type' => 'Form data',
						'id:url' => 'http://zabbix.com'
					],
					'Post fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '',
							'value' => 'post field value'
						]
					],
					'error' => 'Incorrect value for field "post_fields/1/name": cannot be empty.'
				]
			],
			// #6 Empty Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing variable name',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '',
							'value' => 'variable field value'
						]
					],
					'error' => 'Incorrect value for field "variables/1/name": cannot be empty.'
				]
			],
			// #7 Variables field name without opening bracket.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing variable nameopening bracket',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => 'name}'
						]
					],
					'error' => 'Incorrect value for field "variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #8 Variables field name without closing bracket.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing variable name closing bracket',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{name'
						]
					],
					'error' => 'Incorrect value for field "variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #9 Misplaced brackets in Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Misplaced brackets in Variables field name',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{na}me'
						]
					],
					'error' => 'Incorrect value for field "variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #10 Double brackets in Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Double brackets in Variables field name',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{{name}}'
						]
					],
					'error' => 'Incorrect value for field "variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #11 Only brackets in Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Only brackets in Variables field name',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{}'
						]
					],
					'error' => 'Incorrect value for field "variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #12 Duplicate Variable names.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Duplicate Variable names',
						'id:url' => 'http://zabbix.com'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{name}',
							'value' => 'AAA'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '{name}',
							'value' => 'BBB'
						]
					],
					'error' => 'Incorrect value for field "variables/2": value (name)=({name}) already exists.'
				]
			],
			// #13 Missing Headers name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Missing Headers name',
						'id:url' => 'http://zabbix.com'
					],
					'Headers' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '',
							'value' => 'AAA'
						]
					],
					'error' => 'Incorrect value for field "headers/1/name": cannot be empty.'
				]
			],
			// #14 Empty timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Empty timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => ''
					],
					'error' => 'Incorrect value for field "timeout": cannot be empty.'
				]
			],
			// #15 Empty space in timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Empty space in timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => '   '
					],
					'error' => 'Incorrect value for field "timeout": cannot be empty.'
				]
			],
			// #16 Non-numeric timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Non-numeric timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => 'two'
					],
					'error' => 'Incorrect value for field "timeout": a time unit is expected.'
				]
			],
			// #17 Negative timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Negative timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => '-5s'
					],
					'error' => 'Incorrect value for field "timeout": a time unit is expected.'
				]
			],
			// #18 Zero timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Zero timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => 0
					],
					'error' => 'Incorrect value for field "timeout": value must be one of 1-3600.'
				]
			],
			// #19 Too big timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => 3601
					],
					'error' => 'Incorrect value for field "timeout": value must be one of 1-3600.'
				]
			],
			// #20 Too big timeout with suffix.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big timeout with suffix',
						'id:url' => 'http://zabbix.com',
						'Timeout' => '2h'
					],
					'error' => 'Incorrect value for field "timeout": value must be one of 1-3600.'
				]
			],
			// #21 Non-numeric status code.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Non-numeric status code',
						'id:url' => 'http://zabbix.com',
						'Required status codes' => 'AAA'
					],
					'error' => 'Invalid response code "AAA".'
				]
			],
			// #22 Too big status code.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big status code',
						'id:url' => 'http://zabbix.com',
						'Required status codes' => '2150000000'
					],
					'error' => 'Invalid response code "2150000000".'
				]
			],
			// #23 Minimal step configuration.
			[
				[
					'fields' =>  [
						'Name' => 'Min config step',
						'id:url' => 'http://zabbix.com'
					]
				]
			],
			// #24 Retrieve mode set to Headers - Disabled post fields.
			[
				[
					'fields' =>  [
						'Name' => 'Retrieve headers',
						'id:url' => 'http://zabbix.com',
						'Retrieve mode' => 'Headers'
					]
				]
			],
			// #25 All fields populated.
			[
				[
					'fields' =>  [
						'Name' => 'All possible fields specified',
						'id:url' => '良い一日を',
						'Retrieve mode' => 'Body and headers',
						'Post type' => 'Raw data',
						'Raw post' => 'name=良い一日を',
						'Follow redirects' => true,
						'Timeout' => '33s',
						'Required string' => '良い一日を',
						'Required status codes' => '200,300,401'
					],
					'Query fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '1st query name- 良い一日を',
							'value' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '2nd query name - 良い一日を',
							'value' => '2nd query value - 良い一日を'
						]
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{1st variable name - 良い一日を}',
							'value' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '{the 2nd variable name - 良い一日を}',
							'value' => '2nd variable value - 良い一日を'
						]
					],
					'Headers' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '1st header name - 良い一日を',
							'value' => '1st header value - 良い一日を'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '2nd header name - 良い一日を',
							'value' => ''
						]
					]
				]
			],
			// #26 Maximal fields lengths except for pair field values and timeout(since maxlength is 255 but maxvalue is 3600).
			[
				[
					'fields' =>  [
						'Name' => STRING_64,
						'id:url' => 'URL has no maxlength',
						'Post type' => 'Form data',
						'Timeout' => '3600s',
						'Required string' => STRING_255,
						'Required status codes' => '200'.str_repeat(',200', 63)
					],
					'Query fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => STRING_255,
							'value' => STRING_255
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => 'query_name',
							'value' => 'query_value'
						]
					],
					'Post fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => STRING_255,
							'value' => STRING_6000
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => 'post_field_name',
							'value' => 'post_field_value'
						]
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{'.substr(STRING_255, 0, 253).'}',
							'value' => STRING_6000
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '{variable_name}',
							'value' => 'variable_value'
						]
					],
					'Headers' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => STRING_255,
							'value' => STRING_6000
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => 'header_name',
							'value' => 'header_value'
						]
					]
				]
			],
			// #27 Trim trailing and leading spaces from step fields.
			[
				[
					'fields' =>  [
						'Name' => '   Step with trailing and leading spaces   ',
						'id:url' => '   http://zabbix.com'   ,
						'Timeout' => '   15   ',
						'Required string' => '   Zabbix   ',
						'Required status codes' => '   404   '
					],
					'Query fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '   trim query   ',
							'value' => '   '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '   2nd trim query name - 良い一日を   ',
							'value' => '   2nd trim query value - 良い一日を   '
						]
					],
					'Post fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '   trim post field name   ',
							'value' => '   trim post field value   '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '   2nd trim post field name   ',
							'value' => '   '
						]
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '   {1st trim variable name - 良い一日を}   ',
							'value' => '   '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '   {2nd trim variable name - 良い一日を}   ',
							'value' => '   2nd trim variable value - 良い一日を   '
						]
					],
					'Headers' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '   1st trim header name - 良い一日を   ',
							'value' => '   1st trim header value - 良い一日を   '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '   2nd trim header name - 良い一日を   ',
							'value' => '   '
						]
					],
					'trim' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getWebScenarioStepData
	 */
	public function testFormWebScenarioStep_Create($data) {
		$this->checkStepAction($data, 'create');
	}

	/**
	 * @dataProvider getWebScenarioStepData
	 */
	public function testFormWebScenarioStep_Update($data) {
		$this->checkStepAction($data, 'update');
	}

	/**
	 * Perform create or update action for a web scenario step and check the result.
	 *
	 * @param array		$data		data provider
	 * @param string	$action		action to be performed
	 */
	protected function checkStepAction($data, $action) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		// Open the web scenario step configuration form in create or update mode depending on the executed action.
		$scenario = ($action === 'update') ? self::UPDATE_SCENARIO : self::CREATE_SCENARIO;
		$scenario_form = $this->getScenarioFormOnStepsTab($scenario);
		$steps_table = $scenario_form->getField('Steps')->asTable();

		$open_step = ($action === 'create') ? 'button:Add' : 'link:'.self::$update_step;
		$steps_table->query($open_step)->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$step_form = $dialog->asForm();

		$step_form->fill($data['fields']);

		foreach (['Variables', 'Query fields', 'Post fields', 'Headers'] as $field_name) {
			if (array_key_exists($field_name, $data)) {
				$pair_field = $step_form->getField($field_name)->asMultifieldTable();

				if ($pair_field->query('class:form_row')->all()->count() !== count($data[$field_name])) {
					$pair_field->query('button:Add')->one()->click();
				}

				$pair_field->fill($data[$field_name]);
			}
		}

		$step_form->submit();

		if ($expected === TEST_BAD) {
			// There are step form level errors and scenario form level errors. They are checked differently.
			$this->assertMessage(TEST_BAD, 'Cannot '.$action.' web scenario step', $data['error']);
			$dialog->close();
		}
		else {
			$scenario_form->submit();
			$this->assertMessage(TEST_GOOD, 'Web scenario updated');

			if (array_key_exists('trim', $data)) {
				$data['fields'] = array_map('trim', $data['fields']);

				foreach (['Query fields', 'Post fields', 'Variables', 'Headers'] as $pair_field_type) {
					foreach ($data[$pair_field_type] as &$pair_field) {
						$pair_field = array_map('trim', $pair_field);
					}
				}
			}

			if ($action === 'update') {
				self::$update_step = $data['fields']['Name'];
			}

			$this->query('link', $scenario)->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			$scenario_form->selectTab('Steps');

			// Check values displayed in Steps table.
			$step_row = $scenario_form->getField('Steps')->asTable()->findRow('Name', $data['fields']['Name']);
			$this->assertEquals($data['fields']['id:url'], $step_row->getColumn('URL')->getText());

			$column_mapping = [
				'Required' => 'Required string',
				'Status codes' => 'Required status codes'
			];

			foreach (['Timeout' => '15s', 'Required' => '', 'Status codes' => ''] as $column => $value) {
				$actual_field = ($column === 'Timeout') ? $column : $column_mapping[$column];

				if (array_key_exists($actual_field, $data['fields'])) {
					$value = $data['fields'][$actual_field];
				}

				$this->assertEquals($value, $step_row->getColumn($column)->getText());
			}

			// Check the values in the web scenario step configuration form.
			$step_row->query('link', $data['fields']['Name'])->one()->click();
			$step_form->invalidate();

			$step_form->checkValue($data['fields']);

			foreach (['Variables', 'Query fields', 'Post fields', 'Headers'] as $field_name) {
				if (array_key_exists($field_name, $data)) {
					foreach ($data[$field_name] as &$field_array) {
						unset($field_array['action'], $field_array['index']);
					}

					$step_form->getField($field_name)->asMultifieldTable()->checkValue($data[$field_name]);
				}
			}

			COverlayDialogElement::find()->one()->close();
		}
	}

	public function testFormWebScenarioStep_SimpleUpdate() {
		$this->checkImpactlessAction('simple_update');
	}

	public function testFormWebScenarioStep_CancelCreate() {
		$this->checkImpactlessAction('cancel_create');
	}

	public function testFormWebScenarioStep_CancelUpdate() {
		$this->checkImpactlessAction('cancel_update');
	}

	public function testFormWebScenarioStep_CancelDelete() {
		$this->checkImpactlessAction('cancel_delete');
	}

	/**
	 * Check different action cancellation and update without applying any changes.
	 *
	 * @param string	$action		action to be checked
	 */
	protected function checkImpactlessAction($action) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$scenario_form = $this->getScenarioFormOnStepsTab(self::UPDATE_SCENARIO);
		$steps = $scenario_form->getField('Steps')->asTable();

		if ($action === 'cancel_delete') {
			$steps->findRow('Name', self::IMPACTLESS_STEP)->query('button:Remove')->one()->click();
			$scenario_form->query('button:Cancel')->one()->click();
			$this->page->waitUntilReady();
		}
		else {
			$open_step = ($action === 'cancel_create') ? 'button:Add' : 'link:'.self::IMPACTLESS_STEP;
			$steps->query($open_step)->waitUntilClickable()->one()->click();

			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$step_form = $dialog->asForm();

			if ($action === 'simple_update') {
				$step_form->submit();
			}
			else {
				$values = [
					'Name' => $action.' step',
					'id:url' => 'http://'.$action.'.lv',
					'Retrieve mode' => 'Headers'
				];

				$step_form->fill($values);
				$dialog->query('button:Cancel')->one()->click();
			}

			$dialog->ensureNotPresent();

			$scenario_form->submit();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Web scenario updated');
		}
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function testFormWebScenarioStep_Remove() {
		$form = $this->getScenarioFormOnStepsTab(self::UPDATE_SCENARIO);

		$form->getField('Steps')->asTable()->findRow('Name', self::IMPACTLESS_STEP)->query('button:Remove')->one()->click();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Web scenario updated');

		$sql = 'SELECT httpstepid FROM httpstep WHERE name='.zbx_dbstr(self::IMPACTLESS_STEP).
				' AND httptestid IN (SELECT httptestid FROM httptest WHERE name='.zbx_dbstr(self::UPDATE_SCENARIO).')';
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public static function getStepUrlData() {
		return [
			// #0 Plain parsing of URL parameters.
			[
				[
					'url' => 'https://intranet.zabbix.com/secure/admin.jspa?login=admin&password=s00p3r%24ecr3%26',
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'resulting_url' => 'https://intranet.zabbix.com/secure/admin.jspa'
				]
			],
			// #1 Unrelated existing and parsed query parameters.
			[
				[
					'url' => 'https://intranet.zabbix.com/secure/admin.jspa?password=s00p3r%24ecr3%26',
					'existing_query' => [
						['name' => 'login', 'value' => 'admin']
					],
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'password', 'value' => 's00p3r$ecr3&']
					],
					'resulting_url' => 'https://intranet.zabbix.com/secure/admin.jspa'
				]
			],
			// #2 Duplicate parameters should not be erased during parsing.
			[
				[
					'url' => 'https://intranet.zabbix.com/secure/admin.jspa?login=user&password=a123%24bcd4%26',
					'existing_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'login', 'value' => 'user'],
						['name' => 'password', 'value' => 'password']
					],
					'parsed_query' => [
						['name' => 'login', 'value' => 'admin'],
						['name' => 'login', 'value' => 'user'],
						['name' => 'password', 'value' => 'password'],
						['name' => 'login', 'value' => 'user'],
						['name' => 'password', 'value' => 'a123$bcd4&']
					],
					'resulting_url' => 'https://intranet.zabbix.com/secure/admin.jspa'
				]
			],
			// #3 Invalid URL part removed during parsing.
			[
				[
					'url' => 'https://www.zabbix.com/enterprise_ready#test',
					'parsed_query' => [
						['name' => '', 'value' => '']
					],
					'resulting_url' => 'https://www.zabbix.com/enterprise_ready'
				]
			],
			// #4 Empty query parameter name or value.
			[
				[
					'url' => 'https://intranet.zabbix.com/secure/admin.jspa?=admin&password=',
					'parsed_query' => [
						['name' => '', 'value' => 'admin'],
						['name' => 'password', 'value' => '']
					],
					'resulting_url' => 'https://intranet.zabbix.com/secure/admin.jspa'
				]
			],
			// #5 Improper query parameter in URL.
			[
				[
					'expected' => TEST_BAD,
					'url' => 'http://localhost/zabbix/index.php?test=%11',
					'error' => "Failed to parse URL.\n\nURL is not properly encoded."
				]
			]
		];
	}

	/**
	 * @dataProvider getStepUrlData
	 */
	public function testFormWebScenarioStep_ParseUrl($data) {
		$step_form = $this->getStepForm(self::UPDATE_SCENARIO);
		$url_field = $step_form->getField('id:url');
		$url_field->fill($data['url']);
		$query_table = $step_form->getField('Query fields')->asMultifieldTable();

		// Fill in existing query fields if such are present in the data provider.
		if (array_key_exists('existing_query', $data)) {
			$query_table->fill($data['existing_query']);
		}

		$step_form->query('button:Parse')->one()->click();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$query_table->invalidate();
			$query_table->checkValue($data['parsed_query']);
			$this->assertEquals($data['resulting_url'], $url_field->getValue());
		}
		else {
			$this->checkErrorDialog($data['error']);
		}

		COverlayDialogElement::find()->one()->close();
	}

	public static function getStepPostData() {
		return [
			// #0 Regular conversion to raw data.
			[
				[
					'post' => [
						['name' => 'zab bix', 'value' => 'tes&t']
					],
					'result_raw' => 'zab%20bix=tes%26t'
				]
			],
			// #1 Other languages to raw data.
			[
				[
					'post' => [
						['name' => 'тест', 'value' => '自分との戦い いつも負ける']
					],
					'result_raw' => '%D1%82%D0%B5%D1%81%D1%82=%E8%87%AA%E5%88%86%E3%81%A8%E3%81%AE%E6%88%A6%E3%81%84%20'.
							'%E3%81%84%E3%81%A4%E3%82%82%E8%B2%A0%E3%81%91%E3%82%8B'
				]
			],
			// #2 Special symbols to raw data.
			[
				[
					'post' => [
						['name' => '!@#$%^&*()', 'value' => '!@#$%^&*()']
					],
					'result_raw' => '!%40%23%24%25%5E%26*()=!%40%23%24%25%5E%26*()'
				]
			],
			// #3 Converting 2 post fields to raw data.
			[
				[
					'post' => [
						['name' => 'zabbix', 'value' => 'test'],
						['name' => '&Günter', 'value' => '']
					],
					'result_raw' => 'zabbix=test&%26G%C3%BCnter'
				]
			],
			// #4 Converting raw data to 2 post fields.
			[
				[
					'raw_data' => 'login=Admin&password={{password}.urlencode()}',
					'result_post' => [
						['name' => 'login', 'value' => 'Admin'],
						['name' => 'password', 'value' => '{{password}.urlencode()}']
					]
				]
			],
			// #5 Converting raw data to post fields with encoding and without value.
			[
				[
					'raw_data' => 'log+me+in%24&enter=Sign+in%26',
					'result_post' => [
						['name' => 'log me in$', 'value' => ''],
						['name' => 'enter', 'value' => 'Sign in&']
					]
				]
			],
			// #6 Other languages from raw to post fields.
			[
				[
					'raw_data' => '%E0%A4%B9%E0%A4%B0%E0%A4%B5%E0%A4%B2%E0%A5%87=tap%C4%B1ld%C4%B1',
					'result_post' => [
						['name' => 'हरवले', 'value' => 'tapıldı']
					]
				]
			],
			// #7 Missing name from raw data to form data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => '=value',
					'error' => "Cannot convert POST data:\n\nValues without names are not allowed in form fields."
				]
			],
			// #8 Post data validation percent encoding pair is malformed.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'test=%11',
					'error' => "Cannot convert POST data:\n\nData is not properly encoded."
				]
			],
			// #9 Non-existing character when converting from raw data to post data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'value=%00',
					'error' => "Cannot convert POST data:\n\nData is not properly encoded."
				]
			],
			// #10 Unnecessary "=" symbol in raw data when converting to form data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'name=val=ue',
					'error' => "Cannot convert POST data:\n\nData is not properly encoded."
				]
			],
			// #11 Non-unicode encodings when converting raw data to post data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'value=%EA%EE%EB%E1%E0%F1%EA%E8',
					'error' => "Cannot convert POST data:\n\nURIError: URI malformed"
				]
			],
			// #12 Field name exceeds 255 symbols when converting raw data to post data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => '123456789012345'.STRING_255,
					'error' => "Cannot convert POST data:\n\nName of the form field should not exceed 255 characters."
				]
			],
			// #13 Missing name when converting post field to raw data.
			[
				[
					'expected' => TEST_BAD,
					'post' => [
						['name' => '', 'value' => '!@#$%^&*()']
					],
					'error' => "Cannot convert POST data:\n\nValues without names are not allowed in form fields."
				]
			]
		];
	}

	/**
	 * @dataProvider getStepPostData
	 */
	public function testFormWebScenarioStep_ConvertPostData($data) {
		$step_form = $this->getStepForm(self::UPDATE_SCENARIO);
		$post_type = $step_form->getField('Post type');

		if (array_key_exists('raw_data', $data)) {
			$post_type->fill('Raw data');
			$step_form->getField('Raw post')->fill($data['raw_data']);

			$post_type->fill('Form data');
		}
		else {
			$post_table = $step_form->getField('Post fields')->asMultifieldTable();
			$post_table->fill($data['post']);
			$post_type->fill('Raw data');
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			if (array_key_exists('result_raw', $data)) {
				$this->assertEquals($data['result_raw'], $step_form->getField('Raw post')->getValue());
			}
			else {
				$post_fields = $step_form->getField('Post fields')->asMultifieldTable();
				$post_fields->checkValue($data['result_post']);
			}
		}
		else {
			$this->checkErrorDialog($data['error']);
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Return web scenario step configuration form for the specified web scenario.
	 *
	 * @param	string	$scenario	name of web scenario for which to open step configuration form
	 *
	 * @return	CFormElement
	 */
	protected function getStepForm($scenario) {
		$scenario_form = $this->getScenarioFormOnStepsTab($scenario);
		$scenario_form->getField('Steps')->query('button:Add')->one()->click();

		return COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
	}

	/**
	 * Return web scenario configuration form with opened Steps tab.
	 *
	 * @param	string	$scenario	Name of the scenario to be opened
	 *
	 * @return	CFormElement
	 */
	protected function getScenarioFormOnStepsTab($scenario) {
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host')
				->waitUntilReady();
		$this->query('link:'.$scenario)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$scenario_form = $this->query('name:webscenario_form')->asForm()->one();
		$scenario_form->selectTab('Steps');

		return $scenario_form;
	}

	/**
	 * Check the content of the error dialog that appears when parsing URL or converting post data.
	 *
	 * @param string	$error	expected error message text
	 */
	protected function checkErrorDialog($error) {
		$error_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		$this->assertEquals('Error', $error_dialog->getTitle());
		$this->assertEquals($error, $error_dialog->getContent()->getText());
		$error_dialog->getFooter()->query('button:Ok')->one()->click();
		$error_dialog->waitUntilNotPresent();
	}
}
