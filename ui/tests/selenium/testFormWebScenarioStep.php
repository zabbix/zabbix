<<?php
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
	private static $update_step = '	step 1 of scenario 1';

	const TEMPLATE_SCENARIO = 'Template_Web_scenario';
	const UPDATE_SCENARIO = 'Scenario for Update';
	const SQL = 'SELECT * FROM httptest_step';


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
			$step_fields['xpath:(.//table[@data-type="post_fields"]//input)[1]']['visible'] = false;
			$step_fields['xpath:(.//table[@data-type="post_fields"]//input)[2]']['visible'] = false;
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

			$this->assertEquals($visible, $step_form->getField($field)->isVisible());
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
					'sep_error' => 'Incorrect value for field "name": cannot be empty.'
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
					'query_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
						'id:url' => 'http://zabbix.com'
					],
					'post_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'header_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
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
					'step_error' => 'Invalid response code "AAA".'
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
					'step_error' => 'Invalid response code "2150000000".'
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
			// All fields popullated.
			[
				[
					'fields' =>  [
						'Name' => 'Min config step',
						'id:url' => '良い一日を',
						'Post type' => 'Raw data',
						'Raw post' => 'name=良い一日を',
						'Follow redirects' => true,
						'Retrieve mode' => 'Body and headers',
						'Timeout' => '33s',
						'Required string' => '良い一日を',
						'Required status codes' => '200,300,401'
					],
					'query_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '1st query name- 良い一日を'
						],
						[
							'name' => '2nd query name - 良い一日を',
							'value' => '2nd query value - 良い一日を'
						]
					],
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{1st variable name - 良い一日を}'
						],
						[
							'name' => '{2nd variable name - 良い一日を}',
							'value' => '2nd variable value - 良い一日を'
						]
					],
					'header_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '1st header name - 良い一日を',
							'value' => '1st header value - 良い一日を'
						],
						[
							'name' => '2nd header name - 良い一日を'
						]
					]
				]
			],
			// Maximal allowed length of fields except for timeout since maxlength is 255 but maxvalue is 3600.
			[
				[
					'fields' =>  [
						'Name' => STRING_255,
						'id:url' => 'URL has no maxlength',
						'Timeout' => '3600s',
						'Required string' => STRING_255,
						'Required status codes' => '200'.str_repeat(',200', 63)
					],
					'query_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => STRING_255,
							'value' => STRING_255
						]
					],
					'post_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => STRING_255,
							'value' => STRING_2000
						]
					],
					'variable_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{'.substr(STRING_255, 0, 253).'}',
							'value' => STRING_2000
						]
					],
					'header_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => STRING_255,
							'value' => STRING_2000
						]
					]
				]
			],
			// Retrieve mode set to Headers - Disabled post fields.
			[
				[
					'fields' =>  [
						'Name' => 'Retrieve heaers',
						'id:url' => 'http://zabbix.com',
						'Retrieve mode' => 'Headers'
					]
				]
			]
		];
	}

	// Convert raw data to form fields + URL parse + Cancel create + Cancel update + Simple update + Remove
}

