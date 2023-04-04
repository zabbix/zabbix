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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @dataSource WebScenarios
 *
 * @onBefore getContextData
 *
 * @backup httptest
 */
class testFormWebScenarioStep extends CWebTest {

	private static $hostid;
	private static $templateid;
	private static $update_step = 'step 2 of clone scenario';
	private static $impactless_step = 'Первый этап вэб сценария';

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

	public static function getContextData() {
		self::$hostid = CDataHelper::get('WebScenarios.hostid');
		self::$templateid = CDataHelper::get('WebScenarios.templateid');
	}

	// Returns layout context data
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
		$context_id = ($data['context'] === 'host') ? self::$hostid : self::$templateid;
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.$context_id)->waitUntilReady();

		$selector = (array_key_exists('scenario_name', $data)) ? $data['scenario_name'] : self::UPDATE_SCENARIO;

		$this->query('link', $selector)->waitUntilClickable()->one()->click();
		$scenario_form = $this->query('name:httpForm')->waitUntilVisible()->asForm()->one();

		$scenario_form->selectTab('Steps');
		$steps_table = $scenario_form->getField('Steps')->asTable();


		if (array_key_exists('step_name', $data)) {
			$steps_table->findRow('Name', $data['step_name'])->getColumn('Name')->click();
		}
		else {
			$steps_table->query('button:Add')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$step_form = $dialog->asForm();

		$this->assertEquals('Step of web scenario', $dialog->getTitle());

		$step_fields = [
			'Name' => ['maxlength' => 64],
			'id:url' => [],
			"xpath:(.//table[@data-type='query_fields']//input)[1]" => ['placeholder' => 'name', 'maxlength' => 255],
			"xpath:(.//table[@data-type='query_fields']//input)[2]" => ['placeholder' => 'value', 'maxlength' => 255],
			"xpath:(.//table[@data-type='post_fields']//input)[1]" => ['placeholder' => 'name', 'maxlength' => 255],
			"xpath:(.//table[@data-type='post_fields']//input)[2]" => ['placeholder' => 'value', 'maxlength' => 2000],
			'Post type' => ['value' => 'Form data'],
			'Raw post' => ['visible' => false],
			"xpath:(.//table[@data-type='variables']//input)[1]" => ['placeholder' => 'name', 'maxlength' => 255],
			"xpath:(.//table[@data-type='variables']//input)[2]" => ['placeholder' => 'value', 'maxlength' => 2000],
			"xpath:(.//table[@data-type='headers']//input)[1]" => ['placeholder' => 'name', 'maxlength' => 255],
			"xpath:(.//table[@data-type='headers']//input)[2]" => ['placeholder' => 'value', 'maxlength' => 2000],
			'Follow redirects' => ['value' => false],
			'Retrieve mode' => ['value' => 'Body'],
			'Timeout' => ['value' => '15s', 'maxlength' => 255],
			'Required string' => ['placeholder' => 'pattern', 'maxlength' => 255],
			'Required status codes' => ['maxlength' => 255]
		];

		if (array_key_exists('step_name', $data)) {
			$step_fields['Name'] = ['value' => $data['step_name'], 'enabled' => false, 'maxlength' => 64];
			$step_fields['id:url']['value'] = 'http://zabbix.com';
			$step_fields['Post type']['value'] = 'Raw data';
			$step_fields['Raw post']['visible'] = true;
			$step_fields["xpath:(.//table[@data-type='post_fields']//input)[1]"]['visible'] = false;
			$step_fields["xpath:(.//table[@data-type='post_fields']//input)[2]"]['visible'] = false;
			$step_fields['Follow redirects']['value'] = true;

			$initial_type = 'Raw data';
			$new_type = 'Form data';
			$buttons = ['Parse', 'Update', 'Cancel'];
			$post_fields = ['Post type', 'Post fields'];
		}
		else {
			$initial_type = 'Form data';
			$new_type = 'Raw data';
			$buttons = ['Parse', 'Add', 'Cancel'];
			$post_fields = ['Post type', 'Raw post'];
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

		foreach ([$new_type, $initial_type] as $post_type) {
			$step_form->getField('Post type')->select($post_type);

			foreach ($field_visibility[$post_type] as $post_field => $visible) {
				$this->assertEquals($visible, $step_form->getField($post_field)->isDisplayed());
			}
		}

		$mode_field = $step_form->getField('Retrieve mode');

		// Check that "Post fields" and "Post type" fields are disabled only when Retrieve mode is set to Headers.
		foreach (['Body and headers' => true, 'Headers' => false, 'Body' => true] as $value => $enabled) {
			$mode_field->select($value);

			foreach ($post_fields as $post_field) {
				$this->assertTrue($step_form->getField($post_field)->isEnabled($enabled));
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

		foreach (['Query fields', 'Post fields', 'Variables', 'Headers'] as $table_name) {
			$table = $step_form->getField($table_name)->asTable();
			$row = $table->getRow(0);
			$this->assertSame($table_layout['headers'], $table->getHeadersText());

			// Check that Add button is clickable and tha Remove button is not.
			$add_button = $table->query('button:Add')->one();
			$this->assertTrue($add_button->isClickable());
			$remove_button = $row->query('button:Remove')->one();
			$this->assertFalse($remove_button->isClickable());

			// Check the presence of the draggable icon.
			if ($table_name !== 'Variables') {
				$drag_icon = $row->query('xpath:.//div[contains(@class,"drag-icon")]')->one();
				$this->assertFalse($drag_icon->isEnabled());
			}
			else {
				$this->assertFalse($row->query('xpath:.//div[contains(@class,"drag-icon")]')->one(false)->isValid());
			}

			// Fill in some data in first for and check that Remove buttons and draggable icon became enabled.
			$row->getColumn('Name')->query('xpath:./input')->one()->fill('zabbix');
			$this->assertTrue($remove_button->isClickable());

			// Check that draggable icon becomes enabled when a new row is added.
			if ($table_name !== 'Variables') {
				$this->assertFalse($drag_icon->isEnabled());
				$add_button->click();
				$this->assertTrue($drag_icon->isEnabled());
			}
		}

		// Check the buttons in the web scenario step coniguration form.
		foreach ($buttons as $button) {
			$this->assertTrue($dialog->query('button', $button)->one()->isClickable());
		}
	}

	public static function getWebScenarioStepData() {
		return [
			// Empty name
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => '',
						'id:url' => 'https://zabbix.com'
					],
					'step_error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty space in name
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => '   ',
						'id:url' => 'https://zabbix.com'
					],
					'step_error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Empty URL
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with empty URL',
						'id:url' => ''
					],
					'step_error' => 'Incorrect value for field "url": cannot be empty.'
				]
			],
			// Blank space in URL
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with blank space in URL',
						'id:url' => '   '
					],
					'step_error' => 'Incorrect value for field "url": cannot be empty.'
				]
			],
			// Empty Query field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing query field name',
						'id:url' => 'http://zabbix.com'
					],
					'query fields' => [
						[
							'name' => '',
							'value' => 'query field value'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/query_fields/1/name": cannot be empty.'
				]
			],
			// Empty Post field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing post field name',
						'Post type' => 'Form data',
						'id:url' => 'http://zabbix.com'
					],
					'post fields' => [
						[
							'name' => '',
							'value' => 'post field value'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/posts/1/name": cannot be empty.'
				]
			],
			// Empty Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing variable name',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => '',
							'value' => 'variable field value'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/1/name": cannot be empty.'
				]
			],
			// Variables field name without opening bracket.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing variable nameopening bracket',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => 'name}'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Variables field name without closing bracket.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Step with missing variable name closing bracket',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => '{name'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Misplaced brackets in Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Misplaced brackets in Variables field name',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => '{na}me'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Double brackets in Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Double brackets in Variables field name',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => '{{name}}'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Only brackets in Variables field name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Only brackets in Variables field name',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => '{}'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// Duplicate Variable names.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Duplicate Variable names',
						'id:url' => 'http://zabbix.com'
					],
					'variables' => [
						[
							'name' => '{name}',
							'value' => 'AAA'
						],
						[
							'name' => '{name}',
							'value' => 'BBB'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/variables/2": value (name)=({name}) already exists.'
				]
			],
			// Missing Headers name.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Missing Headers name',
						'id:url' => 'http://zabbix.com'
					],
					'headers' => [
						[
							'name' => '',
							'value' => 'AAA'
						]
					],
					'scenario_error' => 'Invalid parameter "/1/steps/2/headers/1/name": cannot be empty.'
				]
			],
			// Empty timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Empty timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => ''
					],
					'step_error' => 'Incorrect value for field "timeout": cannot be empty.'
				]
			],
			// Empty space in timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Empty space in timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => '   '
					],
					'step_error' => 'Incorrect value for field "timeout": cannot be empty.'
				]
			],
			// Non-numeric timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Non-numeric timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => 'two'
					],
					'step_error' => 'Incorrect value for field "timeout": a time unit is expected.'
				]
			],
			// Negative timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Negative timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => '-5s'
					],
					'step_error' => 'Incorrect value for field "timeout": a time unit is expected.'
				]
			],
			// Zero timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Zero timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => 0
					],
					'step_error' => 'Invalid parameter "timeout": value must be one of 1-3600.'
				]
			],
			// Too big timeout.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big timeout',
						'id:url' => 'http://zabbix.com',
						'Timeout' => 3601
					],
					'step_error' => 'Invalid parameter "timeout": value must be one of 1-3600.'
				]
			],
			// Too big timeout with suffix.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big timeout with suffix',
						'id:url' => 'http://zabbix.com',
						'Timeout' => '2h'
					],
					'step_error' => 'Invalid parameter "timeout": value must be one of 1-3600.'
				]
			],
			// Non-numeric status code.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Non-numeric status code',
						'id:url' => 'http://zabbix.com',
						'Required status codes' => 'AAA'
					],
					'scenario_error' => 'Invalid response code "AAA".'
				]
			],
			// Too big status code.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Name' => 'Too big status code',
						'id:url' => 'http://zabbix.com',
						'Required status codes' => '2150000000'
					],
					'scenario_error' => 'Invalid response code "2150000000".'
				]
			],
			// Minimal step configuration.
			[
				[
					'fields' =>  [
						'Name' => 'Min config step',
						'id:url' => 'http://zabbix.com'
					]
				]
			],
			// Retrieve mode set to Headers - Disabled post fields.
			[
				[
					'fields' =>  [
						'Name' => 'Retrieve headers',
						'id:url' => 'http://zabbix.com',
						'Retrieve mode' => 'Headers'
					]
				]
			],
			// All fields popullated.
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
					'query fields' => [
						[
							'name' => '1st query name- 良い一日を',
							'value' => ''
						],
						[
							'name' => '2nd query name - 良い一日を',
							'value' => '2nd query value - 良い一日を'
						]
					],
					'variables' => [
						[
							'name' => '{1st variable name - 良い一日を}',
							'value' => ''
						],
						[
							'name' => '{the 2nd variable name - 良い一日を}',
							'value' => '2nd variable value - 良い一日を'
						]
					],
					'headers' => [
						[
							'name' => '1st header name - 良い一日を',
							'value' => '1st header value - 良い一日を'
						],
						[
							'name' => '2nd header name - 良い一日を',
							'value' => ''
						]
					]
				]
			],
			// Maximal allowed length of fields except for timeout since maxlength is 255 but maxvalue is 3600.
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
					'query fields' => [
						[
							'name' => STRING_255,
							'value' => STRING_255
						],
						[
							'name' => 'query_name',
							'value' => 'query_value'
						]
					],
					'post fields' => [
						[
							'name' => STRING_255,
							'value' => STRING_2000
						],
						[
							'name' => 'post_field_name',
							'value' => 'post_field_value'
						]
					],
					'variables' => [
						[
							'name' => '{'.substr(STRING_255, 0, 253).'}',
							'value' => STRING_2000
						],
						[
							'name' => '{variable_name}',
							'value' => 'variable_value'
						]
					],
					'headers' => [
						[
							'name' => STRING_255,
							'value' => STRING_2000
						],
						[
							'name' => 'header_name',
							'value' => 'header_value'
						]
					]
				]
			],
			// Trim trailing and leading spaces from step fields.
			[
				[
					'fields' =>  [
						'Name' => '   Step with trailing and leading spaces   ',
						'id:url' => '   http://zabbix.com'   ,
						'Timeout' => '   15   ',
						'Required string' => '   Zabbix   ',
						'Required status codes' => '   404   '
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

	private function checkStepAction($data, $action) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		// An attempt to save form is done only in TEST_BAD scenarios with scenario level errors, so only then hash is needed.
		if (CTestArrayHelper::get($data, 'scenario_error')) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$scenario = ($action === 'update') ? self::UPDATE_SCENARIO : self::CREATE_SCENARIO;
		$scenario_form = $this->getScenarioFormOnStepsTab($scenario);
		$steps_table = $scenario_form->getField('Steps')->asTable();

		$open_step = ($action === 'create') ? 'button:Add' : 'link:'.self::$update_step;
		$steps_table->query($open_step)->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$step_form = $dialog->asForm();

		$step_form->fill($data['fields']);

		foreach (['variables', 'query fields', 'post fields', 'headers'] as $field_name) {
			if (array_key_exists($field_name, $data)) {
				// TODO: Replace the below workaround with the commented line when ZBX-22433 is merged.
//				$form->getField(ucfirst($field_name))->asMultifieldTable()->fill($data[$field_name]);
				$field = $step_form->getField(ucfirst($field_name));

				// Apart from field labels, field data types have underscores instead of spaces.
				$data_type = in_array($field_name, ['query fields', 'post fields'])
					? str_replace(' ', '_', $field_name)
					: $field_name;

				if (count($data[$field_name]) > 1) {
					$field->query('button:Add')->one()->click();
				}

				$i = 1;
				$n = (in_array($field_name, ['variables', 'headers'])) ? 3 : 1;
				foreach ($data[$field_name] as $field_pair) {
					// Resetting 2nd row input element index as Variables Headers tables on Web scenario don't have 2nd rows.
					if (array_search($field_pair, $data[$field_name]) > 0 && in_array($field_name, ['variables', 'headers'])) {
						$n = 1;
					}

					$this->query("xpath:(//table[@data-type=".CXPathHelper::escapeQuotes($data_type)."]//tr[".
							$i."]//input)[".$n."]"
					)->one()->fill($field_pair['name']);

					if (array_key_exists('value', $field_pair)) {
						$this->query("xpath:(//table[@data-type=".CXPathHelper::escapeQuotes($data_type)."]//tr[".
								$i."]//input)[".($n + 1)."]"
						)->one()->fill($field_pair['value']);
					}
					$i++;
				}
			}
		}

		$step_form->submit();

		if ($expected === TEST_BAD) {
			if (CTestArrayHelper::get($data, 'step_error')) {
				$this->assertMessage(TEST_BAD, null, $data['step_error']);
				$dialog->close();
			}
			else {
				$scenario_form->submit();
				$this->assertMessage(TEST_BAD, 'Cannot update web scenario', $data['scenario_error']);
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			}
		}
		else {
			$scenario_form->submit();
			$this->assertMessage(TEST_GOOD, 'Web scenario updated');

			// TODO: add logic to trim headers an variables after ZBX-22433 is merged.
			if (array_key_exists('trim', $data)) {
				$data['fields'] = array_map('trim', $data['fields']);
			}

			if ($action === 'update') {
				self::$update_step = $data['fields']['Name'];
			}

			$this->query('link', $scenario)->waitUntilCLickable()->one()->click();
			$this->page->waitUntilReady();

			$scenario_form->selectTab('Steps');

			// Check values displayed in Steps table
			$step_row = $scenario_form->getField('Steps')->asTable()->findRow('Name', $data['fields']['Name']);
			$this->assertEquals($data['fields']['id:url'], $step_row->getColumn('URL')->getText());

			$column_mappring = [
				'Required' => 'Required string',
				'Status codes' => 'Required status codes'
			];

			foreach (['Timeout' => '15s', 'Required' => '', 'Status codes' => ''] as $column => $value) {
				$actual_field = ($column === 'Timeout') ? $column : $column_mappring[$column];

				if (array_key_exists($actual_field, $data['fields'])) {
					$value = $data['fields'][$actual_field];
				}

				$this->assertEquals($value, $step_row->getColumn($column)->getText());
			}

			$step_row->query('link', $data['fields']['Name'])->one()->click();
			$step_form->invalidate();

			$step_form->checkValue($data['fields']);

			foreach (['variables', 'query fields', 'post fields', 'headers'] as $field_name) {
				if (array_key_exists($field_name, $data)) {
					// TODO: Replace the below workaround with a check via $table->checkValue() when ZBX-22433 is merged.
					$field = $step_form->getField(ucfirst($field_name));
					$this->checkTableField($field, $data[$field_name]);
				}
			}
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

	private function checkImpactlessAction($action) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$scenario_form = $this->getScenarioFormOnStepsTab(self::UPDATE_SCENARIO);
		$steps = $scenario_form->getField('Steps')->asTable();

		if ($action === 'cancel_delete') {
			$steps->findRow('Name', self::$impactless_step)->query('button:Remove')->one()->click();
			$scenario_form->query('button:Cancel')->one()->click();
			$this->page->waitUntilReady();
		}
		else {
			$open_step = ($action === 'cancel_create') ? 'button:Add' : 'link:'.self::$impactless_step;
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

		$form->getField('Steps')->asTable()->findRow('Name', self::$impactless_step)->query('button:Remove')->one()->click();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Web scenario updated');

		$sql = 'SELECT httpstepid FROM httpstep WHERE name='.zbx_dbstr(self::$impactless_step).
				' AND httptestid IN (SELECT httptestid FROM httptest WHERE name='.zbx_dbstr(self::UPDATE_SCENARIO).')';
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	public static function getStepUrlData() {
		return [
			// Plain parsing of URL parameters.
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
			// Unrellated existing and parsed query parameters.
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
			// Duplicate parameters should not be erased during parsing.
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
			// Invalid URL part removed duting parsing
			[
				[
					'url' => 'http://www.zabbix.com/enterprise_ready#test',
					'parsed_query' => [
						['name' => '', 'value' => '']
					],
					'resulting_url' => 'http://www.zabbix.com/enterprise_ready'
				]
			],
			// Empty query parameter name or value.
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
			// Improper query parameter in URL.
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

		$query_table = $step_form->getField('Query fields');

		// Fill in exising query fields if such are present in the data provider.
		if (array_key_exists('existing_query', $data)) {
			$add_button = $query_table->query('button:Add')->one();

			$i = 1;
			foreach ($data['existing_query'] as $existing_query) {
				// Add row in Query fields tableif required
				if (count($data['existing_query']) !== $query_table->query('xpath:.//tr[@class="sortable"]')->all()->count()) {
					$add_button->click();
				}

				$query_table->query("xpath:(.//tr[".$i."]//input)[1]")->one()->fill($existing_query['name']);
				$query_table->query("xpath:(.//tr[".$i."]//input)[2]")->one()->fill($existing_query['value']);

				$i++;
			}
		}

		$step_form->query('button:Parse')->one()->click();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$query_table->invalidate();

			$this->checkTableField($query_table, $data['parsed_query']);
			$this->assertEquals($data['resulting_url'], $url_field->getValue());
		}
		else {
			$this->checkErrorDialog($data['error']);
		}

	}

	public static function getStepPostData() {
		return [
			// Regular conversion to raw data.
			[
				[
					'post' => [
						['name' => 'zab bix', 'value' => 'tes&t']
					],
					'result_raw' => 'zab%20bix=tes%26t'
				]
			],
			// Other languages to raw data.
			[
				[
					'post' => [
						['name' => 'тест', 'value' => '自分との戦い いつも負ける']
					],
					'result_raw' => '%D1%82%D0%B5%D1%81%D1%82=%E8%87%AA%E5%88%86%E3%81%A8%E3%81%AE%E6%88%A6%E3%81%84%20'.
							'%E3%81%84%E3%81%A4%E3%82%82%E8%B2%A0%E3%81%91%E3%82%8B'
				]
			],
			// Special symbols to raw data.
			[
				[
					'post' => [
						['name' => '!@#$%^&*()', 'value' => '!@#$%^&*()']
					],
					'result_raw' => '!%40%23%24%25%5E%26*()=!%40%23%24%25%5E%26*()'
				]
			],
			// Converting 2 post fields to raw data.
			[
				[
					'post' => [
						['name' => 'zabbix', 'value' => 'test'],
						['name' => '&Günter', 'value' => '']
					],
					'result_raw' => 'zabbix=test&%26G%C3%BCnter'
				]
			],
			// Converting raw data to 2 post fields.
			[
				[
					'raw_data' => 'login=Admin&password={{password}.urlencode()}',
					'result_post' => [
						['name' => 'login', 'value' => 'Admin'],
						['name' => 'password', 'value' => '{{password}.urlencode()}']
					]
				]
			],
			// Converting raw data to post fields with encoding and without value.
			[
				[
					'raw_data' => 'log+me+in%24&enter=Sign+in%26',
					'result_post' => [
						['name' => 'log me in$', 'value' => ''],
						['name' => 'enter', 'value' => 'Sign in&']
					]
				]
			],
			// Other languages from raw to post fields.
			[
				[
					'raw_data' => '%E0%A4%B9%E0%A4%B0%E0%A4%B5%E0%A4%B2%E0%A5%87=tap%C4%B1ld%C4%B1',
					'result_post' => [
						['name' => 'हरवले', 'value' => 'tapıldı']
					]
				]
			],
			// Missing name from raw data to form data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => '=value',
					'error' => "Cannot convert POST data:\n\nValues without names are not allowed in form fields."
				]
			],
			// Post data validation percent encoding pair is malformed.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'test=%11',
					'error' => "Cannot convert POST data:\n\nData is not properly encoded."
				]
			],
			// Non-existing charracter when converting from rwa data to post data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'value=%00',
					'error' => "Cannot convert POST data:\n\nData is not properly encoded."
				]
			],
			// Unnecessary "=" symbol in raw data when converting to form data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'name=val=ue',
					'error' => "Cannot convert POST data:\n\nData is not properly encoded."
				]
			],
			// Non-unicode encodings when convertig raw data to post data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => 'value=%EA%EE%EB%E1%E0%F1%EA%E8',
					'error' => "Cannot convert POST data:\n\nURIError: URI malformed"
				]
			],
			// Field name exceeds 255 symbols when converting raw data to post data.
			[
				[
					'expected' => TEST_BAD,
					'raw_data' => '123456789012345'.STRING_255,
					'error' => "Cannot convert POST data:\n\nName of the form field should not exceed 255 characters."
				]
			],
			// Missing name whrn converting post field to raw data.
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
			$post_table = $step_form->getField('Post fields');

			// Add row in Post fields table if required
			if (count($data['post']) > 1) {
				$post_table->query('button:Add')->one()->click();
			}

			$i = 1;
			foreach ($data['post'] as $post) {
				$post_table->query("xpath:(.//tr[".$i."]//input)[1]")->one()->fill($post['name']);
				$post_table->query("xpath:(.//tr[".$i."]//input)[2]")->one()->fill($post['value']);

				$i++;
			}

			$post_type->fill('Raw data');
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			if (array_key_exists('result_raw', $data)) {
				$this->assertEquals($data['result_raw'], $step_form->getField('Raw post')->getValue());
			}
			else {
				$post_fields = $step_form->getField('Post fields');
				$this->checkTableField($post_fields, $data['result_post']);
			}
		}
		else {
			$this->checkErrorDialog($data['error']);
		}
	}

	private function getStepForm($scenario) {
		$scenario_form = $this->getScenarioFormOnStepsTab($scenario);
		$scenario_form->getField('Steps')->query('button:Add')->one()->click();

		return COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
	}

	private function getScenarioFormOnStepsTab($scenario) {
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.self::$hostid)->waitUntilReady();
		$this->query('link:'.$scenario)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$scenario_form = $this->query('id:httpForm')->asForm()->one();
		$scenario_form->selectTab('Steps');

		return $scenario_form;
	}

	private function checkErrorDialog($error) {
		$error_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		$this->assertEquals('Error', $error_dialog->getTitle());
		$this->assertEquals($error, $error_dialog->getContent()->getText());
		$error_dialog->getFooter()->query('button:Ok')->one()->click();
		$error_dialog->waitUntilNotPresent();
	}

	private function checkTableField($table_field, $expected) {
		$obtained_fields = [];
		$i = 0;

		foreach ($table_field->query('xpath:(.//tr[@class="sortable"])')->all() as $table_row) {
			$obtained_fields[$i]['name'] = $table_row->query('xpath:(.//input)[1]')->one()->getValue();
			$obtained_fields[$i]['value'] = $table_row->query('xpath:(.//input)[2]')->one()->getValue();

			$i++;
		}

		$this->assertEquals($expected, $obtained_fields);
	}
}
