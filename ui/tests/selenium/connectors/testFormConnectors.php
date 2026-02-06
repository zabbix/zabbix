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
 * @backup connector, profiles
 *
 * @onBefore prepareConnectorsData
 */
class testFormConnectors extends CWebTest {

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

	const CONNECTOR_SQL = 'SELECT * FROM connector ORDER BY connectorid';
	const DEFAULT_CONNECTOR = 'Default connector';
	const DELETE_CONNECTOR = 'Delete connector';
	protected static $update_connector = 'Update connector';

	public static function prepareConnectorsData() {
		CDataHelper::call('connector.create', [
			[
				'name' => self::DEFAULT_CONNECTOR,
				'url' => '{$URL}'
			],
			[
				'name' => self::$update_connector,
				'url' => '{$URL}'
			],
			[
				'name' => self::DELETE_CONNECTOR,
				'url' => '{$URL}'
			]
		]);
	}

	/**
	 * Check Connector create form layout.
	 */
	public function testFormConnectors_Layout() {
		$default_labels = [
			'fields' => ['Name', 'Protocol', 'Data type', 'URL', 'Tag filter', 'Type of information', 'HTTP authentication',
				'Advanced configuration', 'Max records per message', 'Concurrent sessions', 'Attempts', 'Attempt interval',
				'Timeout', 'HTTP proxy', 'SSL verify peer',	'SSL verify host', 'SSL certificate file', 'SSL key file',
				'SSL key password', 'Description', 'Enabled'
			],
			'advanced_fields' => ['Max records per message', 'Concurrent sessions', 'Attempts','Timeout', 'HTTP proxy',
				'SSL verify peer', 'SSL verify host', 'SSL certificate file', 'SSL key file', 'SSL key password'
			],
			'required' => ['Name', 'URL', 'Type of information', 'Max records per message', 'Concurrent sessions',
				'Attempts', 'Attempt interval', 'Timeout'
			],
			'default' => [
				'Data type' => 'Item values',
				'Tag filter' => 'And/Or',
				'Numeric (unsigned)' => true,
				'Numeric (float)' => true,
				'Character' => true,
				'Log' => true,
				'Text' => true,
				'Binary' => false,
				'HTTP authentication' => 'None',
				'Max records per message' => 'Unlimited',
				'Concurrent sessions' => '1',
				'Attempts' => '1',
				'Attempt interval' => '5s',
				'Timeout' => '5s',
				'SSL verify peer' => true,
				'SSL verify host' => true,
				'Enabled' => true
			]
		];

		$http_auth = [
			'None' => [],
			'Basic' => ['Username', 'Password'],
			'NTLM' => ['Username', 'Password'],
			'Kerberos' => ['Username', 'Password'],
			'Digest' => ['Username', 'Password'],
			'Bearer' => ['Bearer token']
		];

		$this->page->login()->open('zabbix.php?action=connector.list');

		// Check title for create/update form.
		$this->query('link', self::DEFAULT_CONNECTOR)->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Connector', $dialog->getTitle());
		$dialog->close();

		$this->query('button:Create connector')->waitUntilClickable()->one()->click();
		$form = $dialog->asForm();
		$this->assertEquals('New connector', $dialog->getTitle());

		// Check advanced configuration default value.
		$form->checkValue(['Advanced configuration' => false]);

		// Check that the 'Advanced configuration' additional fields are hidden.
		foreach ($default_labels['advanced_fields'] as $label) {
			$this->assertFalse($form->getLabel($label)->isDisplayed());
		}

		$form->fill(['Advanced configuration' => true]);

		// Check default values.
		$form->checkValue($default_labels['default']);
		$this->assertEquals('Zabbix Streaming Protocol v1.0', $form->getField('Protocol')->getText());

		foreach ($http_auth as $auth => $auth_fields) {
			$form->fill(['HTTP authentication' => $auth]);

			// Check visible fields.
			$this->assertEqualsCanonicalizing(array_merge($default_labels['fields'], $auth_fields),
					$form->getLabels(CElementFilter::VISIBLE)->asText()
			);

			// Check required fields.
			$this->assertEqualsCanonicalizing(array_merge($default_labels['required'], ($auth === 'Bearer') ? $auth_fields : []),
					$form->getRequiredLabels()
			);

			// Reset the value of the "HTTP authentication" field to avoid issues with the following checks.
			$form->fill(['HTTP authentication' => 'None']);
		}

		// Check Custom value.
		$form->fill(['Max records per message' => 'Custom']);
		$this->assertEquals(0, $form->getField('id:max_records')->getValue());

		// Check tags table fields.
		$tag_filter_table = $form->query('id:tags')->asMultifieldTable()->one();
		$tag_filter_table->checkValue([['tag' => '', 'operator' => 'Equals', 'value' => '']]);

		// Check that tags table buttons are present.
		$this->assertEquals(2, $tag_filter_table->query('button', ['Remove', 'Add'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		// Check tags table dropdown options.
		$this->assertSame(['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
				$form->getField('name:tags[0][operator]')->getOptions()->asText()
		);

		// Check radio buttons and their labels.
		$radio_buttons = [
			'Data type' => ['Item values', 'Events'],
			'Tag filter' => ['And/Or', 'Or'],
			'Max records per message' => ['Unlimited', 'Custom']
		];
		foreach ($radio_buttons as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		$inputs = [
			'Name' => [
				'maxlength' => '255'
			],
			'URL' => [
				'maxlength' => '2048'
			],
			'id:tags_0_tag' => [
				'maxlength' => '255',
				'placeholder' => 'tag'
			],
			'id:tags_0_value' => [
				'maxlength' => '255',
				'placeholder' => 'value'
			],
			'Bearer token' => [
				'maxlength' => '128'
			],
			'Username' => [
				'maxlength' => '255'
			],
			'Password' => [
				'maxlength' => '255'
			],
			'id:max_records' => [
				'maxlength' => '10'
			],
			'Concurrent sessions' => [
				'maxlength' => '3'
			],
			'Attempts' => [
				'maxlength' => '1'
			],
			'Attempt interval' => [
				'maxlength' => '32'
			],
			'Timeout' => [
				'maxlength' => '255'
			],
			'HTTP proxy' => [
				'maxlength' => '255',
				'placeholder' => '[protocol://][user[:password]@]proxy.example.com[:port]'
			],
			'SSL certificate file' => [
				'maxlength' => '255'
			],
			'SSL key file' => [
				'maxlength' => '255'
			],
			'SSL key password' => [
				'maxlength' => '64'
			],
			'Description' => [
				'maxlength' => '65535'
			]
		];
		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		/**
		* Check that 'Attempt interval' and 'Type of information' fields state changes
		* if 'Attempts' and 'Data type' match criteria (Attempts > 1, Data type !== Item values).
		*/
		foreach (['Events' => false, 'Item values' => true] as $data_type => $state) {
			$form->fill(['Data type' => $data_type]);
			$this->assertEquals($state, $form->getField('Type of information')->isDisplayed());
		}

		foreach (['2' => true, '1' => false, '9' => true, '0' => false] as $attempts => $state) {
			$form->getField('Attempts')->fill($attempts)->fireEvent('input');
			$this->assertEquals($state, $form->getField('Attempt interval')->isEnabled());
			$form->checkValue(['Attempt interval' => '5s']);
		}

		// Check that both 'Cancel' and 'Add' footer buttons are present and clickable.
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		$dialog->close();
	}

	public static function getConnectorsData() {
		return [
			// #0
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'URL' => '{$URL}'
					],
					'inline_errors' => [
						'Name' => 'This field cannot be empty.'
					]
				]
			],
			// #1
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector without URL',
						'URL' => ''
					],
					'inline_errors' => [
						'URL' => 'This field cannot be empty.'
					]
				]
			],
			// #2
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unacceptable URL',
						'URL' => 'dns://zabbix.com:82/v1/history'
					],
					'inline_errors' => [
						'URL' => 'Unacceptable URL.'
					]
				]
			],
			// #3
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unacceptable URL',
						'URL' => 'message://zabbix.com:82/v1/history'
					],
					'inline_errors' => [
						'URL' => 'Unacceptable URL.'
					]
				]
			],
			// #4
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unacceptable URL',
						'URL' => '?'
					],
					'inline_errors' => [
						'URL' => 'Unacceptable URL.'
					]
				]
			],
			// #5
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'URL' => ''
					],
					'inline_errors' => [
						'Name' => 'This field cannot be empty.',
						'URL' => 'This field cannot be empty.'
					]
				]
			],
			// #6
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty bearer token',
						'URL' => 'https://zabbix.com:82/v1/history',
						'HTTP authentication' => 'Bearer'
					],
					'inline_errors' => [
						'Bearer token' => 'This field cannot be empty.'
					]
				]
			],
			// #7
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty bearer token',
						'URL' => 'https://zabbix.com:82/v1/history',
						'HTTP authentication' => 'Bearer',
						'Bearer token' => ' '
					],
					'inline_errors' => [
						'Bearer token' => 'This field cannot be empty.'
					]
				]
			],
			// #8 'Type of information' field validation when all related checkboxes are unchecked.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unchecked Type of information checkboxes',
						'URL' => '{$URL}',
						'Numeric (unsigned)' => false,
						'Numeric (float)' => false,
						'Character' => false,
						'Log' => false,
						'Text' => false,
						'Binary' => false
					],
					'inline_errors' => [
						'Type of information' => 'At least one type of information must be selected.'
					]
				]
			],
			// #9 Check validation for 'Concurrent sessions' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with value that is less than 1',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '0'
					],
					'inline_errors' => [
						'Concurrent sessions' => 'This value must be no less than "1".'
					]
				]
			],
			// #10
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with empty value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => ''
					],
					'inline_errors' => [
						'Concurrent sessions' => 'This value is not a valid integer.'
					]
				]
			],
			// #11
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with incorrect value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '@'
					],
					'inline_errors' => [
						'Concurrent sessions' => 'This value is not a valid integer.'
					]
				]
			],
			// #12
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with greater than "100" value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '101'
					],
					'inline_errors' => [
						'Concurrent sessions' => 'This value must be no greater than "100".'
					]
				]
			],
			// #13 Check validation for 'Attempts' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts range check',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '0'
					],
					'inline_errors' => [
						'Attempts' => 'This value must be no less than "1".'
					]
				]
			],
			// #14
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts check with empty value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => ''
					],
					'inline_errors' => [
						'Attempts' => 'This value is not a valid integer.'
					]
				]
			],
			// #15
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts range check',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '6'
					],
					'inline_errors' => [
						'Attempts' => 'This value must be no greater than "5".'
					]
				]
			],
			// #16 'Attempt interval' field validation checks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with empty attempt interval field',
						'Attempt interval' => ''
					],
					'inline_errors' => [
						'Attempt interval' => 'This field cannot be empty.'
					]
				]
			],
			// #17
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with incorrect value for attempt interval field',
						'Attempt interval' => ' '
					],
					'inline_errors' => [
						'Attempt interval' => 'This field cannot be empty.'
					]
				]
			],
			// #18
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with incorrect value for attempt interval field',
						'Attempt interval' => '🔔'
					],
					'inline_errors' => [
						'Attempt interval' => 'A time unit is expected.'
					]
				]
			],
			// #19
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with invalid parameter for attempt interval field',
						'Attempt interval' => '1m'
					],
					'inline_errors' => [
						'Attempt interval' => 'Value must be between 0 and 10s.'
					]
				]
			],
			// #20
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with invalid parameter for attempt interval field',
						'Attempt interval' => '-1s'
					],
					'inline_errors' => [
						'Attempt interval' => 'A time unit is expected.'
					]
				]
			],
			// #21
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with invalid parameter for attempt interval field',
						'Attempt interval' => '11s'
					],
					'inline_errors' => [
						'Attempt interval' => 'Value must be between 0 and 10s.'
					]
				]
			],
			// #22
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'Name' => 'Connector with invalid parameter for attempt interval field',
						'Attempt interval' => '11111111111111111111111111111111'
					],
					'inline_errors' => [
						'Attempt interval' => 'Value must be between 0 and 10s.'
					]
				]
			],
			// #23 'Timeout' field validation checks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty timeout field',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => ''
					],
					'inline_errors' => [
						'Timeout' => 'This field cannot be empty.'
					]
				]
			],
			// #24
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '0'
					],
					'inline_errors' => [
						'Timeout' => 'Value must be between 1s and 60s (1m).'
					]
				]
			],
			// #25
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '61s'
					],
					'inline_errors' => [
						'Timeout' => 'Value must be between 1s and 60s (1m).'
					]
				]
			],
			// #26
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '1h'
					],
					'inline_errors' => [
						'Timeout' => 'Value must be between 1s and 60s (1m).'
					]
				]
			],
			// #27
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => STRING_255
					],
					'inline_errors' => [
						'Timeout' => 'A time unit is expected.'
					]
				]
			],
			// #28 Tags validation checks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty tag name',
						'URL' => '{$URL}',
						'id:tags_0_tag' => '',
						'id:tags_0_value' => 'value'
					],
					'inline_errors' => [
						'id:tags_0_tag' => 'This field cannot be empty.'
					]
				]
			],
			// #29
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty tag name with gap',
						'URL' => '{$URL}',
						'id:tags_0_tag' => ' ',
						'id:tags_0_value' => 'value'
					],
					'inline_errors' => [
						'id:tags_0_tag' => 'This field cannot be empty.'
					]
				]
			],
			// #30
			[
				[
					'expected' => TEST_BAD,
					'tags' => true,
					'fields' => [
						'Name' => 'Empty name for second tag',
						'URL' => '{$URL}',
						'id:tags_0_tag' => 'tag',
						'id:tags_0_value' => 'value',
						'id:tags_1_tag' => '',
						'id:tags_1_value' => 'value'
					],
					'inline_errors' => [
						'id:tags_1_tag' => 'This field cannot be empty.'
					]
				]
			],
			// #31
			[
				[
					'expected' => TEST_BAD,
					'tags' => true,
					'fields' => [
						'Name' => 'Two identical tags',
						'URL' => '{$URL}',
						'id:tags_0_tag' => 'tag',
						'id:tags_0_value' => 'value',
						'id:tags_1_tag' => 'tag',
						'id:tags_1_value' => 'value'
					],
					'inline_errors' => [
						'id:tags_1_tag' => 'Tag filter name, operator and value combination is not unique.'
					]
				]
			],
			// #32 Custom 'Max records per message' validation check.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Incorrect value for max records field',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Max records per message' => 'Custom',
						'id:max_records' => '2147483648'
					],
					'inline_errors' => [
						'id:max_records' => 'This value is not a valid integer.'
					]
				]
			],
			// #33 Checks with multiple errors.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts and Concurrent sessions incorrect values',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '0',
						'Attempts' => '0'
					],
					'inline_errors' => [
						'Concurrent sessions' => 'This value must be no less than "1".',
						'Attempts' => 'This value must be no less than "1".'
					]
				]
			],
			// #34
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'URL' => '',
						'Advanced configuration' => true,
						'Max records per message' => 'Custom',
						'id:max_records' => '2147483648',
						'Concurrent sessions' => '0',
						'Attempts' => '6'
					],
					'inline_errors' => [
						'Name' => 'This field cannot be empty.',
						'URL' => 'This field cannot be empty.',
						'id:max_records' => 'This value is not a valid integer.',
						'Concurrent sessions' => 'This value must be no less than "1".',
						'Attempts' => 'This value must be no greater than "5".'
					]
				]
			],
			// #35
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty bearer token and timeout',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'HTTP authentication' => 'Bearer',
						'Timeout' => ''
					],
					'inline_errors' => [
						'Bearer token' => 'This field cannot be empty.',
						'Timeout' => 'This field cannot be empty.'
					]
				]
			],
			// #36
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple create with mandatory fields',
						'URL' => '{$URL}'
					]
				]
			],
			// #37
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'One type of information',
						'URL' => '{$URL}',
						'Numeric (unsigned)' => false,
						'Numeric (float)' => false,
						'Character' => false,
						'Log' => true,
						'Text' => false,
						'Binary' => false
					]
				]
			],
			// #38
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Two types of information',
						'URL' => '{$URL}',
						'Numeric (unsigned)' => true,
						'Numeric (float)' => false,
						'Character' => false,
						'Log' => true,
						'Text' => false,
						'Binary' => false
					]
				]
			],
			// #39
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'All types of information',
						'URL' => '{$URL}',
						'Numeric (unsigned)' => true,
						'Numeric (float)' => true,
						'Character' => true,
						'Log' => true,
						'Text' => true,
						'Binary' => true
					]
				]
			],
			// #40
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple create with changed fields and Basic auth',
						'URL' => '{$URL}',
						'Data type' => 'Events',
						'Tag filter' => 'Or',
						'id:tags_0_tag' => 'tag contains',
						'name:tags[0][operator]' => 'Contains',
						'id:tags_0_value' => 'value',
						'HTTP authentication' => 'Basic',
						'Username' => 'username',
						'Password' => 'password',
						'Description' => 'hello world!',
						'Enabled' => false
					]
				]
			],
			// #41
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'With advanced configuration and long strings',
						'URL' => STRING_2048,
						'Data type' => 'Item values',
						'Tag filter' => 'And/Or',
						'id:tags_0_tag' => STRING_255,
						'name:tags[0][operator]' => 'Does not contain',
						'id:tags_0_value' => STRING_255,
						'HTTP authentication' => 'NTLM',
						'Username' => STRING_64,
						'Password' => STRING_64,
						'Advanced configuration' => true,
						'id:max_records_mode' => 'Custom',
						'id:max_records' => ZBX_MAX_INT32,
						'Concurrent sessions' => '100',
						'Attempts' => '5',
						'Timeout' => '60',
						'HTTP proxy' => STRING_255,
						'SSL verify peer' => false,
						'SSL verify host' => false,
						'SSL certificate file' => STRING_255,
						'SSL key file' => STRING_255,
						'SSL key password' => STRING_64,
						'Description' => 'long string check',
						'Enabled' => true
					]
				]
			],
			// #42
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '   Test trailing spaces   ',
						'URL' => '  {$URL}  ',
						'Data type' => 'Item values',
						'id:tags_0_tag' => ' tag tag  ',
						'name:tags[0][operator]' => 'Does not contain',
						'id:tags_0_value' => ' tag value ',
						'HTTP authentication' => 'NTLM',
						'Username' => ' {$USERNAME}  ',
						'Advanced configuration' => true,
						'id:max_records_mode' => 'Custom',
						'id:max_records' => '  1  ',
						'Concurrent sessions' => ' 1 ',
						'Timeout' => ' 60 ',
						'HTTP proxy' => '  proxy  ',
						'SSL certificate file' => '  {$SSL_CERT}  ',
						'SSL key file' => '  {$SSL_KEY}  ',
						'Description' => '  trim check  '
					],
					'trim' => true
				]
			],
			// #43
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'With advanced configuration and supported macros',
						'URL' => '{$URL}',
						'id:tags_0_tag' => 'exists',
						'name:tags[0][operator]' => 'Exists',
						'HTTP authentication' => 'Kerberos',
						'Username' => '{$USERNAME}',
						'Password' => '{$PASSWORD}',
						'Advanced configuration' => true,
						'Timeout' => '{$TIMEOUT}',
						'HTTP proxy' => '{$PROXY}',
						'SSL certificate file' => '{$SSL_CERT}',
						'SSL key file' => '{$SSL_KEY}',
						'SSL key password' => '{$SSL_PASSWORD}',
						'Description' => 'with macros'
					]
				]
			],
			// #44
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Advanced configuration and SSL',
						'URL' => '{$URL}',
						'id:tags_0_tag' => 'does not exist',
						'name:tags[0][operator]' => 'Does not exist',
						'HTTP authentication' => 'Digest',
						'Username' => '',
						'Password' => '',
						'Advanced configuration' => true,
						'SSL verify peer' => true,
						'SSL verify host' => true,
						'SSL certificate file' => '/home/zabbix/',
						'SSL key file' => 'server.key',
						'SSL key password' => 'password',
						'Description' => ''
					]
				]
			],
			// #45
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Advanced configuration and one minute timeout',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '1m'
					]
				]
			],
			// #46
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Advanced configuration and 60 seconds timeout',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '60s'
					]
				]
			],
			// #47
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '🚩Advanced configuration🚩 with custom attempt interval => 0',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'URL' => '{$URL}',
						'Attempt interval' => '0'
					]
				]
			],
			// #48
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '🚩Advanced configuration🚩 with custom attempt interval => 0s',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'URL' => '{$URL}',
						'Attempt interval' => '0s'
					]
				]
			],
			// #49
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '🚩Advanced configuration🚩 with custom attempt interval => 10',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'URL' => '{$URL}',
						'Attempt interval' => '10'
					]
				]
			],
			// #50
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '🚩Advanced configuration🚩 with custom attempt interval => 10s',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'URL' => '{$URL}',
						'Attempt interval' => '10s'
					]
				]
			],
			// #51
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '🚩Advanced configuration🚩 with custom attempt interval and leading and trailing spaces',
						'Advanced configuration' => true,
						'Attempts' => '2',
						'URL' => '{$URL}',
						'Attempt interval' => '  0s  '
					],
					'trim' => true
				]
			],
			// #52
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple create with changed fields and Bearer token',
						'URL' => 'https://zabbix.com:82/v1/events',
						'Data type' => 'Events',
						'Tag filter' => 'Or',
						'id:tags_0_tag' => 'does not equal',
						'name:tags[0][operator]' => 'Does not equal',
						'id:tags_0_value' => 'value',
						'HTTP authentication' => 'Bearer',
						'Bearer token' => STRING_128,
						'Description' => 'bearer token'
					]
				]
			],
			// #53
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple create with changed fields, trim and Bearer macro',
						'URL' => 'https://zabbix.com:82/v1/events',
						'HTTP authentication' => 'Bearer',
						'Bearer token' => '  {$TOKEN}  ',
						'Description' => 'bearer token'
					],
					'trim' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getConnectorsData
	 */
	public function testFormConnectors_Create($data) {
		$this->checkConnectorForm($data);
	}

	public function testFormConnectors_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::CONNECTOR_SQL);

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('link', self::$update_connector)->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->waitUntilReady()->one()->asForm()->submit();

		$this->assertMessage(TEST_GOOD, 'Connector updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::CONNECTOR_SQL));
	}

	/**
	 * @dataProvider getConnectorsData
	 */
	public function testFormConnectors_Update($data) {
		$this->checkConnectorForm($data, true);
	}

	/**
	 * Check Connector creation or update form validation and successful submission.
	 *
	 * @param boolean $update	updating is performed
	 */
	public function checkConnectorForm($data, $update = false) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::CONNECTOR_SQL);
		}

		$this->page->login()->open('zabbix.php?action=connector.list');

		if ($update) {
			$this->query('link', self::$update_connector)->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create connector')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();

		// Add a prefix to the name of the Connector in case of update scenario to avoid duplicate names.
		if ($update && $data['expected'] === TEST_GOOD) {
			$data['fields']['Name'] = CTestArrayHelper::get($data, 'trim', false)
				? '    Update: '.$data['fields']['Name']
				: 'Update: '.$data['fields']['Name'];
		}

		if (array_key_exists('tags', $data)) {
			$form->query('xpath://table[@id="tags"]//button[text()="Add"]')->one()->click();
		}

		$form->fill($data['fields']);
		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertInlineError($form, $data['inline_errors']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::CONNECTOR_SQL));
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();

			// Trim trailing and leading spaces before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data = CTestArrayHelper::trim($data);
			}

			$this->assertMessage(TEST_GOOD, ($update ? 'Connector updated' : 'Connector created'));

			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr($data['fields']['Name'])));

			// Trim spaces in the middle of a name after DB check; spaces in links are trimmed.
			$name = CTestArrayHelper::get($data, 'trim', false)
				? preg_replace('/\s+/', ' ', $data['fields']['Name'])
				: $data['fields']['Name'];

			if ($update) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr(self::$update_connector)));
				self::$update_connector = $name;
			}

			$this->query('link', $name)->waitUntilClickable()->one()->click();
			$form->invalidate();

			// Open "Advanced configuration" block if it was filled with data.
			if (CTestArrayHelper::get($data, 'fields.Advanced configuration', false)) {
				// After form submit "Advanced configuration" is closed.
				$form->checkValue(['Advanced configuration' => false]);
				$form->fill(['Advanced configuration' => true]);
			}

			$form->checkValue($data['fields']);
			$dialog->close();
		}
	}

	public function getCancellationData() {
		return [
			[
				[
					'action' => 'Create'
				]
			],
			[
				[
					'action' => 'Update',
					'name' => self::DEFAULT_CONNECTOR,
					'new_values' => [
						'Name' => 'New zabbix test connector',
						'URL' => 'http://zabbix.com:82/v1/history',
						'HTTP authentication' => 'Bearer',
						'Bearer token' => STRING_128,
						'Advanced configuration' => true,
						'SSL verify peer' => false,
						'SSL verify host' => false,
						'SSL certificate file' => '/home/zabbix/',
						'SSL key file' => '{$KEY}',
						'SSL key password' => '{$PASSWORD}',
						'Description' => 'cancellation of create and update actions',
						'Enabled' => false
					]
				]
			],
			[
				[
					'action' => 'Clone',
					'name' => self::DEFAULT_CONNECTOR
				]
			],
			[
				[
					'action' => 'Delete',
					'name' => self::DELETE_CONNECTOR,
					'alert_message' => 'Delete selected connector?'
				]
			]
		];
	}

	/**
	 * Check cancellation of create, update, clone and delete actions.
	 *
	 * @dataProvider getCancellationData
	 */
	public function testFormConnectors_CancelAction($data) {
		$old_hash = CDBHelper::getHash(self::CONNECTOR_SQL);

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query(($data['action'] === 'Create') ? 'button:Create connector' : 'link:'.$data['name'])->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		// Fill in data for 'Update' scenario.
		if ($data['action'] === 'Update') {
			$form->fill($data['new_values']);
		}

		if ($data['action'] === 'Clone' || ($data['action'] === 'Delete')) {
			$dialog->getFooter()->query('button', $data['action'])->one()->click();

			// Check alert message and dismiss it.
			if ($data['action'] === 'Delete') {
				$this->assertEquals($data['alert_message'], $this->page->getAlertText());
				$this->page->dismissAlert();
			}
		}

		$dialog->query('button:Cancel')->one()->click();

		$dialog->ensureNotPresent();
		$this->assertEquals($old_hash, CDBHelper::getHash(self::CONNECTOR_SQL));
	}

	public static function getCloneData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'Name' => self::DEFAULT_CONNECTOR
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => self::DELETE_CONNECTOR,
					'inline_errors' => [
						'Name' => 'This object already exists.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 *
	 * Function for checking connector cloning.
	 */
	public function testFormConnectors_Clone($data) {
		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('link', $data['Name'])->one()->click();

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$values = $form->getValues();

		$this->query('button:Clone')->waitUntilClickable()->one()->click();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$new_name = 'Cloned_'.$data['Name'];
			$form->invalidate();
			$form->fill(['Name' => $new_name]);
			$form->submit();

			$this->assertMessage(TEST_GOOD, 'Connector created');
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr($data['Name'])));
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr($new_name)));

			$values['Name'] = $new_name;
			$this->query('link', $new_name)->one()->click();
			$this->assertEquals($values, $form->getValues());
		}
		else {
			$form->submit();
			$this->assertInlineError($form, $data['inline_errors']);
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr($data['Name'])));
		}

		COverlayDialogElement::find()->one()->close();
	}

	public function testFormConnectors_Delete() {
		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('link', self::DELETE_CONNECTOR)->waitUntilClickable()->one()->click();

		// Click on the Delete button in the opened Connector configuration dialog.
		COverlayDialogElement::find()->waitUntilReady()->one()->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->assertEquals('Delete selected connector?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Connector deleted');
		$this->assertFalse($this->query('link', self::DELETE_CONNECTOR)->one(false)->isValid());

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr(self::DELETE_CONNECTOR)));
	}

	/**
	 * Modify the URI scheme validation rules and check the result for the URL type in connector form.
	 */
	public function testFormConnectors_UriScheme() {
		$invalid_schemes = ['dns://zabbix.com', 'message://zabbix.com'];
		$default_valid_schemes = ['http://zabbix.com', 'https://zabbix.com', 'ftp://zabbix.com', 'file://zabbix.com',
			'mailto://zabbix.com', 'tel://zabbix.com', 'ssh://zabbix.com'
		];

		$this->page->login()->open('zabbix.php?action=connector.list')->waitUntilReady();
		$this->query('link', self::DEFAULT_CONNECTOR)->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$dialog->close();

		// Check default URI scheme rules: http, https, ftp, file, mailto, tel, ssh.
		$this->assertUriScheme($form, $default_valid_schemes);
		$this->assertUriScheme($form, $invalid_schemes, TEST_BAD);

		// Change valid URI schemes on "Other configuration parameters" page.
		$this->page->open('zabbix.php?action=miscconfig.edit');
		$config_form = $this->query('name:otherForm')->asForm()->waitUntilVisible()->one();
		$config_form->fill(['id:validate_uri_schemes' => true, 'id:uri_valid_schemes' => 'dns,message']);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->open('zabbix.php?action=connector.list');
		$this->assertUriScheme($form, $default_valid_schemes, TEST_BAD);
		$this->assertUriScheme($form, $invalid_schemes);

		// Disable URI scheme validation.
		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$config_form->fill(['id:validate_uri_schemes' => false]);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->open('zabbix.php?action=connector.list')->waitUntilReady();
		$this->assertUriScheme($form, array_merge($default_valid_schemes, $invalid_schemes));
	}

	/**
	 * Fill in the URL field to check the URI scheme validation rules.
	 *
	 * @param CFormElement $form	form element of connector
	 * @param array $data			url field data
	 * @param string $expected		expected result after connector form submit, TEST_GOOD or TEST_BAD
	 */
	private function assertUriScheme($form, $data, $expected = TEST_GOOD) {
		$this->query('link', self::DEFAULT_CONNECTOR)->waitUntilClickable()->one()->click();

		foreach ($data as $scheme) {
			$form->fill(['URL' => $scheme]);
			$form->submit();

			if ($expected === TEST_GOOD) {
				$this->assertMessage(TEST_GOOD, 'Connector updated');
				CMessageElement::find()->one()->close();
				$this->query('link', self::DEFAULT_CONNECTOR)->one()->click();
			}
			else {
				$this->assertInlineError($form, ['URL' => 'Unacceptable URL.']);
			}
		}

		COverlayDialogElement::find()->waitUntilReady()->one()->close();
	}
}
