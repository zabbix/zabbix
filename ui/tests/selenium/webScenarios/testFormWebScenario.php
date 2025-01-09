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
class testFormWebScenario extends CWebTest {

	protected static $templateid;
	protected static $template_name;
	protected static $template_scenarioid;
	protected static $delete_scenarioid;
	protected static $update_scenario = 'Scenario for Update';

	const HOSTID = 40001;
	const TEMPLATE_SCENARIO = 'Template_Web_scenario';
	const DELETE_SCENARIO = 'Scenario for Delete';
	const SQL = 'SELECT * FROM httptest h LEFT JOIN httptest_field hf ON hf.httptestid = h.httptestid ORDER BY h.httptestid, hf.httptest_fieldid';
	const CLONE_SCENARIO = 'Scenario for Clone';

	protected static $all_fields = [
		'scenario_fields' => [
			'Name' => 'All fields specified',
			'Update interval' => '6h',
			'Attempts' => 7,
			'Agent' => 'other ...',
			'User agent string' => 'My super puper agent string 良い一日を',
			'HTTP proxy' => '良い一日を',
			'Enabled' => false
		],
		'auth_fields' => [
			'HTTP authentication' => 'Basic',
			'User' => '!@#$%^&*()_+=-良い一日を',
			'Password' => '!@#$%^&*()_+=-良い一日を',
			'SSL verify peer' => true,
			'SSL verify host' => true,
			'SSL certificate file' => '!@#$%^&*()_+=-良い一日を',
			'SSL key file' => '!@#$%^&*()_+=-良い一日を',
			'SSL key password' => '!@#$%^&*()_+=-良い一日を'
		],
		'variables' => [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'name' => '{!@#$%^&*()_+=-良い一日を}',
				'value' => '!@#$%^&*()_+=-良い一日を'
			],
			[
				'name' => '{xyz}',
				'value' => ''
			]
		],
		'headers' => [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'name' => 'OneTwoThree',
				'value' => ''
			],
			[
				'name' => '!@#$%^&*()_+=-良い一日を',
				'value' => '!@#$%^&*()_+=-良い一日を'
			]
		],
		'tags' => [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'tag' => 'tag',
				'value' => 'value'
			],
			[
				'tag' => '仕事で良い一日を過ごしてください',
				'value' => '!@#$%^&*()_+'
			]
		]
	];

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
		self::$template_name = CDataHelper::get('WebScenarios.template_name');
		self::$template_scenarioid = CDataHelper::get('WebScenarios.httptestids.'.self::TEMPLATE_SCENARIO);
		self::$delete_scenarioid = CDataHelper::get('WebScenarios.httptestids.'.self::DELETE_SCENARIO);
	}

	public static function getLayoutData() {
		return [
			[
				[
					'context' => 'host'
				]
			],
			[
				[
					'scenario_name' => self::TEMPLATE_SCENARIO,
					'context' => 'host'
				]
			],
			[
				[
					'context' => 'template'
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormWebScenario_CheckLayout($data) {
		$context_id = ($data['context'] === 'host') ? self::HOSTID : self::$templateid;
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.$context_id.'&context='.
				$data['context'])->waitUntilReady();

		$selector = (array_key_exists('scenario_name', $data))
			? 'link:'.$data['scenario_name']
			: 'button:Create web scenario';

		$this->query($selector)->waitUntilClickable()->one()->click();
		$form = $this->query('name:webscenario_form')->waitUntilVisible()->asForm()->one();

		$this->page->assertHeader('Web monitoring');
		$this->page->assertTitle('Configuration of web monitoring');

		// Check tabs available in the form.
		$this->assertEquals(['Scenario', 'Steps', 'Tags', 'Authentication'], $form->getTabs());

		$scenario_fields = [
			'Name' => ['autofocus' => 'true', 'maxlength' => 64],
			'Update interval' => ['value' => '1m', 'maxlength' => 255],
			'Attempts' => ['value' => 1, 'maxlength' => 2],
			'Agent' => ['value' => 'Zabbix'],
			'id:agent_other' => ['visible' => false, 'maxlength' => 255],
			'HTTP proxy' => ['placeholder' => '[protocol://][user[:password]@]proxy.example.com[:port]', 'maxlength' => 255],
			'xpath:(//table[@id="variables"]//textarea)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(//table[@id="variables"]//textarea)[2]' => ['placeholder' => 'value', 'maxlength' => 65535],
			'xpath:(//table[@id="headers"]//textarea)[1]' => ['placeholder' => 'name', 'maxlength' => 255],
			'xpath:(//table[@id="headers"]//textarea)[2]' => ['placeholder' => 'value', 'maxlength' => 65535],
			'Enabled' => ['value' => true]
		];

		// Substitute inherited web scenario specific fields and check Parent web scenario field.
		if (array_key_exists('scenario_name', $data)) {
			$scenario_fields['Name'] = ['value' => $data['scenario_name'], 'enabled' => false, 'maxlength' => 64];
			$scenario_fields['Agent']['value'] = 'Internet Explorer 10';

			$parent_field = $form->getField('Parent web scenarios');
			$this->assertTrue($parent_field->isCLickable());

			// Check that link in "Parent web scenarios" field leads to config of this web scenario on template.
			$this->assertEquals('httpconf.php?form=update&hostid='.self::$templateid.'&httptestid='.self::$template_scenarioid.
					'&context=template', $parent_field->query('link', self::$template_name)->one()->getAttribute('href')
			);
		}

		$this->checkFieldAttributes($form, $scenario_fields);
		$this->assertEquals(['Name', 'Update interval', 'Attempts'], array_values($form->getRequiredLabels()));

		$agents = [
			'Microsoft Edge 80', 'Microsoft Edge 44', 'Internet Explorer 11', 'Internet Explorer 10', 'Internet Explorer 9',
			'Internet Explorer 8', 'Firefox 73 (Windows)', 'Firefox 73 (Linux)', 'Firefox 73 (macOS)', 'Chrome 80 (Windows)',
			'Chrome 80 (Linux)', 'Chrome 80 (macOS)', 'Chrome 80 (iOS)', 'Chromium 80 (Linux)', 'Opera 67 (Windows)',
			'Opera 67 (Linux)', 'Opera 67 (macOS)', 'Safari 13 (macOS)', 'Safari 13 (iPhone)', 'Safari 13 (iPad)',
			'Safari 13 (iPod Touch)', 'Zabbix', 'Lynx 2.8.8rel.2', 'Links 2.8', 'Googlebot 2.1', 'other ...'
		];

		// Check the agents dropdown options.
		$this->assertEquals($agents, $form->getField('Agent')->getOptions()->asText());

		// Check that "User agent string" field is displayed only when Agent is set to other.
		$user_string = $form->getField('User agent string');
		$this->assertFalse($user_string->isDisplayed());
		$form->getField('Agent')->select('other ...');
		$this->assertTrue($user_string->isDisplayed());
		$this->assertTrue($user_string->isEnabled());

		// Check layout of Variables and Headers tables.
		$table_layout = [
			'headers' => ['', 'Name', '', 'Value', '']
		];

		foreach (['Variables' => false, 'Headers' => true] as $table_name => $row_draggable) {
			$table = $form->getField($table_name)->asTable();
			$row = $table->getRow(0);
			$this->assertSame($table_layout['headers'], $table->getHeadersText());

			// Check that Add button is clickable and that Remove button is not.
			$add_button = $table->query('button:Add')->one();
			$this->assertTrue($add_button->isClickable());
			$remove_button = $row->query('button:Remove')->one();
			$this->assertTrue($remove_button->isClickable());
			$remove_button->click();
			$this->assertFalse($remove_button->isClickable());

			// Check the presence of the draggable icon.
			if ($row_draggable) {
				$this->assertEquals('sortable sortable-disabled',
						$table->query('xpath:.//tbody')->one()->getAttribute('class')
				);
			}
			else {
				$this->assertFalse($row->query('xpath:.//div[contains(@class,"drag-icon")]')->one(false)->isValid());
			}

			// Fill in some data in first row and check that Remove buttons and draggable icon became enabled.
			foreach(['Name', 'Value'] as $column) {
				$row->getColumn($column)->query('xpath:./textarea')->one()->fill('zabbix');
			}
			$this->assertTrue($row->query('button:Remove')->one()->isClickable());

			// Check that draggable icon becomes enabled when a new row is added.
			if ($row_draggable) {
				$this->assertEquals('sortable sortable-disabled',
						$table->query('xpath:.//tbody')->one()->getAttribute('class')
				);
				$add_button->click();
				$this->assertEquals('sortable', $table->query('xpath:.//tbody')->one()->getAttribute('class'));
			}
		}

		// Check Steps tab.
		$form->selectTab('Steps');
		$this->assertTrue($form->isRequired('Steps'));
		$steps_table = $form->getField('Steps')->asTable();

		$this->assertEquals(['', '', 'Name', 'Timeout', 'URL', 'Required', 'Status codes', 'Action'],
				$steps_table->getHeadersText()
		);

		// Adding steps to a template scenario from host should not be possible (the Add button should be hidden).
		if (array_key_exists('scenario_name', $data)) {
			$this->assertFalse($steps_table->query('xpath:.//button')->one(false)->isValid());
		}
		else {
			$this->assertEquals(['Add'], $steps_table->query('xpath:.//button')->all()->asText());
		}

		// Check Tags tab.
		$form->selectTab('Tags');
		$tag_types = $form->getField('id:show_inherited_tags')->asSegmentedRadio();
		$this->assertEquals(['Scenario tags', 'Inherited and scenario tags'], $tag_types->getLabels()->asText());
		$this->assertEquals('Scenario tags', $tag_types->getSelected());

		$tags_table = $form->query('class:tags-table')->one()->asTable();

		// Check tags table headers when viewing scenario tags.
		$this->assertEquals(['Name', 'Value', ''], $tags_table->getHeaders()->asText());

		// Check table tags placeholders and length.
		foreach (['tag' => 255, 'value' => 255] as $placeholder => $length) {
			$this->assertEquals($length, $tags_table->query('id:tags_0_'.$placeholder)->one()->getAttribute('maxlength'));
			$this->assertEquals($placeholder, $tags_table->query('id:tags_0_'.$placeholder)->one()->getAttribute('placeholder'));
		}

		// Switch to Inherited and scenario tags and check tags table headers.
		$tag_types->select('Inherited and scenario tags');
		$tags_table->invalidate();
		$this->assertEquals(['Name', 'Value', '', 'Parent templates'], $tags_table->getHeaders()->asText());

		// Check Authentication tab.
		$form->selectTab('Authentication');
		$authentication_fields = [
			'HTTP authentication' => ['value' => 'None'],
			'User' => ['visible' => false, 'maxlength' => 255],
			'Password' => ['visible' => false, 'maxlength' => 255],
			'SSL verify peer' => ['value' => false],
			'SSL verify host' => ['value' => false],
			'SSL certificate file' => ['maxlength' => 255],
			'SSL key file' => ['maxlength' => 255],
			'SSL key password' => ['maxlength' => 64]
		];

		$this->checkFieldAttributes($form, $authentication_fields);

		$auth_field = $form->getField('HTTP authentication');
		$this->assertEquals(['None', 'Basic', 'NTLM', 'Kerberos', 'Digest'], $auth_field->getOptions()->asText());

		$user_field = $form->getField('User');
		$password_field = $form->getField('Password');

		foreach (['Basic', 'NTLM', 'Kerberos'] as $auth_type) {
			$auth_field->select($auth_type);

			foreach ([$user_field, $password_field] as $field) {
				$this->assertTrue($field->isDisplayed());
				$this->assertTrue($field->isEnabled());
			}
		}

		// Check the presence and clickability of control buttons.
		$expected_buttons = (array_key_exists('scenario_name', $data))
			? ['Update' => true, 'Clone' => true, 'Clear history and trends' => true, 'Delete' => false, 'Cancel' => true]
			: ['Add' => true, 'Cancel' => true];

		$footer_buttons = $form->query('class:form-grid-actions')->one()->query('tag:button')->all();
		$this->assertEquals(count($expected_buttons), $footer_buttons->count());

		foreach ($footer_buttons as $footer_button) {
			$button_text = $footer_button->getText();
			$this->assertEquals($expected_buttons[$button_text], $footer_button->isClickable());
		}
	}

	/**
	 * Check the values of corresponding field element attributes based on the passed reference array.
	 *
	 * @param CFormElement	$form		form element where the passed field attributes should be checked
	 * @param array			$fields		array of fields and their attributes to be checked
	 */
	protected function checkFieldAttributes($form, $fields) {
		foreach ($fields as $field => $attributes) {
			$value = (array_key_exists('value', $attributes)) ? $attributes['value'] : '';
			$visible = (array_key_exists('visible', $attributes)) ? $attributes['visible'] : true;
			$enabled = (array_key_exists('enabled', $attributes)) ? $attributes['enabled'] : true;

			$this->assertEquals($visible, $form->getField($field)->isVisible());
			$this->assertEquals($value, $form->getField($field)->getValue());
			$this->assertTrue($form->getField($field)->isEnabled($enabled));

			foreach (['maxlength', 'placeholder', 'autofocus'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $form->getField($field)->getAttribute($attribute));
				}
			}
		}
	}

	public static function getWebScenarioData() {
		return [
			// #0 Empty name.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => ''
					],
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// #1 Empty space in name.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => '   '
					],
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// #2 Missing steps.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Missing steps'
					],
					'no_steps' => true,
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Field "Steps" is mandatory.'
				]
			],
			// #3 Negative update interval.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Negative update interval',
						'Update interval' => '-1'
					],
					'error_details' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// #4 Zero update interval.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Zero update interval',
						'Update interval' => 0
					],
					'error_details' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// #5 Too big update interval.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Too big update interval',
						'Update interval' => 86401
					],
					'error_details' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// #6 Too big update interval with suffix.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Too big update interval with suffix',
						'Update interval' => '1441h'
					],
					'error_details' => 'Invalid parameter "/1/delay": value must be one of 1-86400.'
				]
			],
			// #7 Negative number of retries.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Negative number of retries',
						'Attempts' => '-1'
					],
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Incorrect value "-1" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// #8 Zero retries.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Zero retries',
						'Attempts' => 0
					],
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Incorrect value "0" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// #9 Too high number of retries.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Too high number of retries',
						'Attempts' => 11
					],
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Incorrect value "11" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// #10 Non-numeric number of retries.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Too high number of retries',
						'Attempts' => 'aa'
					],
					'error_title' => 'Page received incorrect data',
					'error_details' => 'Incorrect value "0" for "Attempts" field: must be between 1 and 10.'
				]
			],
			// #11 Variable without brackets.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Variable name without brackets'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => 'abc',
							'value' => ''
						]
					],
					'error_details' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #12 Variable without opening bracket.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Variable name without opening bracket'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => 'abc}',
							'value' => 'abc'
						]
					],
					'error_details' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #13 Variable without closing bracket.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Variable name without closing bracket'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{abc',
							'value' => ''
						]
					],
					'error_details' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #14 Variable with misplaced brackets.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Variable with misplaced brackets'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{}abc',
							'value' => '!@#$%^&*()_+=-良い一日を'
						]
					],
					'error_details' => 'Invalid parameter "/1/variables/1/name": is not enclosed in {} or is malformed.'
				]
			],
			// #15 Duplicate variable names.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Duplicate variable names'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '{abc}',
							'value' => '123'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '{abc}',
							'value' => '987'
						]
					],
					'error_details' => 'Invalid parameter "/1/variables/2": value (name)=({abc}) already exists.'
				]
			],
			// #16 Missing variable name.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Missing variable name'
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => ' ',
							'value' => '123'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '{abc}',
							'value' => '987'
						]
					],
					'error_details' => 'Invalid parameter "/1/variables/1/name": cannot be empty.'
				]
			],
			// #17 Headers - empty name.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Missing header name'
					],
					'Headers' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '',
							'value' => '123'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => 'abc',
							'value' => '987'
						]
					],
					'error_details' => 'Invalid parameter "/1/headers/1/name": cannot be empty.'
				]
			],
			// #18 Duplicate web scenario name.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => self::TEMPLATE_SCENARIO
					],
					'error_details' => 'Web scenario "'.self::TEMPLATE_SCENARIO.'" already exists.'
				]
			],
			// #19 Missing tag name.
			[
				[
					'expected' => TEST_BAD,
					'scenario_fields' => [
						'Name' => 'Missing tag name'
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '',
							'value' => 'value'
						]
					],
					'error_details' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
				]
			],
			// #20 Minimal config.
			[
				[
					'expected' => TEST_GOOD,
					'scenario_fields' => [
						'Name' => 'Min required configuration'
					]
				]
			],
			// #21 Web scenario with basic auth with populated username and empty password.
			[
				[
					'expected' => TEST_GOOD,
					'scenario_fields' => [
						'Name' => 'No password'
					],
					'auth_fields' => [
						'HTTP authentication' => 'Basic',
						'User' => 'Passwordless_user',
						'Password' => ''
					]
				]
			],
			// #22 Web scenario with kerberos auth with empty username and populated password.
			[
				[
					'expected' => TEST_GOOD,
					'scenario_fields' => [
						'Name' => 'No username'
					],
					'auth_fields' => [
						'HTTP authentication' => 'Kerberos',
						'User' => '',
						'Password' => 'Userless_password'
					]
				]
			],
			// #23 All possible fields specified.
			[
				[
					'expected' => TEST_GOOD,
					'scenario_fields' => self::$all_fields['scenario_fields'],
					'auth_fields' => self::$all_fields['auth_fields'],
					'Variables' => self::$all_fields['variables'],
					'Headers' => self::$all_fields['headers'],
					'tags' => self::$all_fields['tags']
				]
			],
			// #24 Maximal possible value length in input elements except for update interval and variables and header values.
			[
				[
					'expected' => TEST_GOOD,
					'scenario_fields' => [
						'Name' => STRING_64,
						'Update interval' => '999',
						'Attempts' => 10,
						'Agent' => 'other ...',
						'User agent string' => STRING_255,
						'HTTP proxy' => STRING_255
					],
					'auth_fields' => [
						'HTTP authentication' => 'Basic',
						'User' => STRING_64,
						'Password' => STRING_64,
						'SSL certificate file' => STRING_255,
						'SSL key file' => STRING_255,
						'SSL key password' => STRING_64
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
							'name' => '{the 2nd variable}',
							'value' => '2nd variable value'
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
							'name' => 'the 2nd header',
							'value' => 'the value of the 2nd header'
						]
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => STRING_255,
							'value' => STRING_255
						]
					]
				]
			],
			// #25 Trim trailing and leading spaces in fields.
			[
				[
					'expected' => TEST_GOOD,
					'scenario_fields' => [
						'Name' => '   Trim this name   ',
						'Update interval' => '     1d    ',
						'Attempts' => '9 ',
						'Agent' => 'other ...',
						'User agent string' => '    My super puper trimmed agent string 良い一日を    ',
						'HTTP proxy' => '    Trimmed proxy     '
					],
					'auth_fields' => [
						'HTTP authentication' => 'NTLM',
						'User' => '   Trimmed user   ',
						'Password' => '   NOT trimmed password   ',
						'SSL certificate file' => '   Trimmed SSL cert filename   ',
						'SSL key file' => '   Trimmed SSL key filename   ',
						'SSL key password' => '   NOT trimmed SSL key password   '
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '   tag   ',
							'value' => '   value   '
						]
					],
					'Variables' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '   {!@#$%^&*()_+=-良い一日を}   ',
							'value' => '   !@#$%^&*()_+=-良い一日を   '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '  {abc}  ',
							'value' => '   '
						]
					],
					'Headers' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'name' => '   OneTwoThree   ',
							'value' => '   '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'name' => '   !@#$%^&*()_+=-良い一日を   ',
							'value' => '   !@#$%^&*()_+=-良い一日を   '
						]
					],
					'trim' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getWebScenarioData
	 */
	public function testFormWebScenario_Create($data) {
		$this->checkAction($data);
	}

	/**
	 * @dataProvider getWebScenarioData
	 */
	public function testFormWebScenario_Update($data) {
		$this->checkAction($data, 'update');
	}

	public function testFormWebScenario_SimpleUpdate() {
		$this->checkImpactlessAction('simple_update');
	}

	public function testFormWebScenario_CancelCreate() {
		$this->checkImpactlessAction('cancel_create');
	}

	public function testFormWebScenario_CancelUpdate() {
		$this->checkImpactlessAction('cancel_update');
	}

	public function testFormWebScenario_CancelClone() {
		$this->checkImpactlessAction('cancel_clone');
	}

	public function testFormWebScenario_CancelDelete() {
		$this->checkImpactlessAction('cancel_delete');
	}

	public function testFormWebScenario_Delete() {
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host')
				->waitUntilReady();
		$this->query('link', self::DELETE_SCENARIO)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$this->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->assertEquals('Delete web scenario?', $this->page->getAlertText());
		$this->page->acceptAlert();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Web scenario deleted');

		foreach (['httptest', 'httptest_field', 'httptestitem', 'httpstep'] as $table) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM '.$table.' WHERE httptestid='.self::$delete_scenarioid));
		}
	}

	public function testFormWebScenario_Clone() {
		$clone_name = 'Clone of '.self::CLONE_SCENARIO;
		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host')
				->waitUntilReady();
		$this->query('link', self::CLONE_SCENARIO)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$form = $this->query('name:webscenario_form')->asForm()->one();

		// Get initial field values before cloning. Tags need to be handled separately as their table has no label.
		$fields = $form->getFields()->asValues();

		foreach (['Variables', 'Headers'] as $field_name) {
			$table_fields[$field_name] = $form->getField($field_name)->asMultifieldTable()->getValue();
		}

		$form->query('button:Clone')->one()->click();
		$form->invalidate();
		$form->getField('Name')->fill('Clone of '.self::CLONE_SCENARIO);
		$form->submit();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Web scenario added');

		$this->query('link', $clone_name)->one()->click();
		$this->page->waitUntilReady();
		$form->invalidate();

		$fields['Name'] = $clone_name;

		$form->checkValue($fields);

		foreach (['Variables', 'Headers'] as $field_name) {
			$this->assertEquals($table_fields[$field_name], $form->getField($field_name)->asMultifieldTable()->getValue());
		}
	}

	/**
	 * Check different action cancellation and update without applying any changes.
	 *
	 * @param string	$action		action to be checked
	 */
	protected function checkImpactlessAction($action) {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host')
				->waitUntilReady();

		switch ($action) {
			case 'simple_update':
				$this->query('link:'.self::CLONE_SCENARIO)->waitUntilClickable()->one()->click();
				$this->query('name:webscenario_form')->asForm()->one()->submit();
				$this->page->waitUntilReady();
				$this->assertMessage(TEST_GOOD, 'Web scenario updated');
				break;

			case 'cancel_create':
			case 'cancel_update':
			case 'cancel_clone':
				$button = ($action === 'cancel_create') ? 'button:Create web scenario' : 'link:'.self::CLONE_SCENARIO;
				$this->query($button)->waitUntilClickable()->one()->click();
				$this->page->waitUntilReady();

				if ($action === 'cancel_clone') {
					$this->query('button:Clone')->waitUntilClickable()->one()->click();
					$this->page->waitUntilReady();
				}

				$form = $this->query('name:webscenario_form')->asForm()->one();
				$this->fillScenarioForm(self::$all_fields, $form);
				$form->query('button:Cancel')->one()->click();
				$this->page->waitUntilReady();
				break;

			case 'cancel_delete':
				$this->query('link', self::DELETE_SCENARIO)->waitUntilClickable()->one()->click();
				$this->page->waitUntilReady();

				$this->query('button:Delete')->waitUntilClickable()->one()->click();
				$this->page->dismissAlert();
				$this->query('button:Cancel')->one()->click();
				$this->page->waitUntilReady();
				break;
		}

		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Perform create or update action and check the result.
	 *
	 * @param array		$data		data provider
	 * @param string	$action		action to be performed
	 */
	protected function checkAction($data, $action = 'create') {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host')
				->waitUntilReady();

		$selector = ($action === 'create') ? 'button:Create web scenario' : 'link:'.self::$update_scenario;
		$this->query($selector)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$form = $this->query('name:webscenario_form')->asForm()->one();

		// Add postfix to scenario name in case of update scenario except for empty name and template scenario update cases.
		if ($action === 'update' && !in_array($data['scenario_fields']['Name'], ['', '   ', self::TEMPLATE_SCENARIO])) {
			$data['scenario_fields']['Name'] = ($data['scenario_fields']['Name'] === STRING_64)
				? $data['scenario_fields']['Name'] = substr(STRING_64, 0, 57).' update'
				: $data['scenario_fields']['Name'].((array_key_exists('trim', $data)) ? ' update   ' : ' update');
		}

		$this->fillScenarioForm($data, $form, $action);
		$form->submit();

		if ($expected === TEST_BAD) {
			// In case if something goes wrong and the scenario gets updated, then it shouldn't impact following update cases.
			if ($action === 'update' && CMessageElement::find()->one()->isGood()) {
				self::$update_scenario = $data['scenario_fields']['Name'];
			}

			$message_title = CTestArrayHelper::get($data, 'error_title', ($action === 'create')
					? 'Cannot add web scenario'
					: 'Cannot update web scenario'
			);
			$this->assertMessage(TEST_BAD, $message_title, $data['error_details']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			$message_title = ($action === 'create') ? 'Web scenario added' : 'Web scenario updated';
			$this->assertMessage(TEST_GOOD, $message_title);

			if ($action === 'update') {
				self::$update_scenario = $data['scenario_fields']['Name'];
			}

			if (array_key_exists('trim', $data)) {
				$skip_fields = ['Password', 'SSL key password'];

				foreach (['scenario_fields', 'Variables', 'Headers', 'auth_fields', 'tags'] as $tab_fields) {
					$original_fields = $data[$tab_fields];

					if ($tab_fields === 'tags') {
						foreach ($data[$tab_fields] as &$tag) {
							$tag = array_map('trim', $tag);
						}
					}
					elseif (in_array($tab_fields, ['Variables', 'Headers'])) {
						foreach ($data[$tab_fields] as &$pair_field) {
							$pair_field = array_map('trim', $pair_field);
						}
					}
					else {
						$data[$tab_fields] = array_map('trim', $data[$tab_fields]);
					}

					// Return the original values to the fields that shouldn't be trimmed.
					foreach ($skip_fields as $skip_field) {
						if (array_key_exists($skip_field, $data[$tab_fields])) {
							$data[$tab_fields][$skip_field] = $original_fields[$skip_field];
						}
					}
				}
			}

			// Open web scenario - in web scenario list multiple consequent spaces are displayed as a single space.
			$link = (array_key_exists('trim', $data))
				? preg_replace('!\s+!', ' ', $data['scenario_fields']['Name'])
				: $data['scenario_fields']['Name'];

			$this->query('link', $link)->waitUntilCLickable()->one()->click();
			$this->page->waitUntilReady();

			$form->invalidate();
			$form->checkValue($data['scenario_fields']);

			foreach (['Variables', 'Headers'] as $field_name) {
				if (array_key_exists($field_name, $data)) {
					foreach ($data[$field_name] as &$field_array) {
						unset($field_array['action'], $field_array['index']);
					}

					$form->getField($field_name)->asMultifieldTable()->checkValue($data[$field_name]);
				}
			}

			if (array_key_exists('tags', $data)) {
				unset($data['tags'][0]['action'], $data['tags'][0]['index']);

				$form->selectTab('Tags');
				$form->query('class:tags-table')->asMultifieldTable()->one()->checkValue($data['tags']);
			}

			if (array_key_exists('auth_fields', $data)) {
				$form->selectTab('Authentication');
				$form->checkValue($data['auth_fields']);
			}
		}
	}

	/**
	 * Fill in the Scenario tab of the web scenario configuration form.
	 *
	 * @param array			$data	data provider
	 * @param CFormElement	$form	form that should be filled in
	 * @param string		$action	type of action being performed (create or update)
	 */
	protected function fillScenarioForm($data, $form, $action = 'update') {
		$form->fill($data['scenario_fields']);

		foreach (['Variables', 'Headers'] as $field_name) {
			if (array_key_exists($field_name, $data)) {
				$pair_field = $form->getField($field_name)->asMultifieldTable();

				if ($pair_field->query('class:form_row')->all()->count() !== count($data[$field_name])) {
					$pair_field->query('button:Add')->one()->click();
				}

				$pair_field->fill($data[$field_name]);
			}
		}

		// Add a step to the web scenario in create scenarios except case when step absence is checked.
		if (!CTestArrayHelper::get($data, 'no_steps') && $action === 'create') {
			$form->selectTab('Steps');
			$form->getField('Steps')->query('button:Add')->waitUntilClickable()->one()->click();

			$step_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$step_form = $step_dialog->asForm();
			$step_form->fill(['Name' => 'Step1', 'id:url' => 'https://zabbix.com']);
			$step_form->submit();
			$step_dialog->ensureNotPresent();
		}
		elseif (CTestArrayHelper::get($data, 'no_steps') && $action === 'update') {
			$form->selectTab('Steps');
			$form->getField('Steps')->query('button:Remove')->waitUntilClickable()->one()->click();
		}

		if (array_key_exists('tags', $data)) {
			$form->selectTab('Tags');
			$tags_table = $form->query('class:tags-table')->asMultifieldTable()->one();

			if ($action === 'update' && $tags_table->query('id:tags_1_remove')->one(false)->isValid()) {
				$tags_table->query('id:tags_1_remove')->one(false)->click();
			}

			$form->query('class:tags-table')->asMultifieldTable()->one()->fill($data['tags']);
		}

		if (array_key_exists('auth_fields', $data)) {
			$form->selectTab('Authentication');
			$form->fill($data['auth_fields']);
		}
	}
}
