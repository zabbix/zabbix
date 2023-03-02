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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup connector, profiles
 *
 * @onBefore prepareConnectorsData
 */
class testFormConnectors extends CWebTest {

	use TableTrait;

	private static $connector_sql = 'SELECT * FROM connector ORDER BY connectorid';
	private static $default_connector = 'Default connector';
	private static $update_connector = 'Update connector';
	private static $delete_connector = 'Delete connector';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public static function prepareConnectorsData() {
		CDataHelper::call('connector.create', [
			[
				'name' => self::$default_connector,
				'url' => '{$URL}'
			],
			[
				'name' => self::$update_connector,
				'url' => '{$URL}'
			],
			[
				'name' => self::$delete_connector,
				'url' => '{$URL}'
			],
		]);
	}

	/**
	 * Check Connector create form layout.
	 */
	public function testFormConnectors_Layout() {
		$default_labels = [
			'fields' => [
				'Name',
				'Protocol',
				'Data type',
				'URL',
				'Tag filter',
				'HTTP authentication',
				'Advanced configuration',
				'Max records per message',
				'Concurrent sessions',
				'Attempts',
				'Timeout',
				'HTTP proxy',
				'SSL verify peer',
				'SSL verify host',
				'SSL certificate file',
				'SSL key file',
				'SSL key password',
				'Description',
				'Enabled'
			],
			'required' => ['Name', 'URL','Max records per message', 'Concurrent sessions', 'Attempts', 'Timeout'],
			'default' => [
				'id:protocol' => '0',
				'Data type' => 'Item values',
				'Tag filter' => 'And/Or',
				'HTTP authentication' => 'None',
				'Max records per message' => 'Unlimited',
				'Concurrent sessions' => '1',
				'Attempts' => '1',
				'Timeout' => '5s',
				'SSL verify peer' => true,
				'SSL verify host' => true,
				'Enabled' => true
			]
		];

		$http_auth = [
			'None' => [
				'fields' => []
			],
			'Basic' => [
				'fields' => ['Username', 'Password']
			],
			'NTLM' => [
				'fields' => ['Username', 'Password']
			],
			'Kerberos' => [
				'fields' => ['Username', 'Password']
			],
			'Digest' => [
				'fields' => ['Username', 'Password']
			],
			'Bearer' => [
				'fields' => ['Bearer token'],
				'required' => ['Bearer token']
			]
		];

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('button:Create connector')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('New connector', $dialog->getTitle());
		$form = $dialog->query('id:connector-form')->asForm()->one();

		// Check advanced configuration default value.
		$this->assertFalse($form->query('id:advanced_configuration')->asCheckbox()->one()->isChecked());
		$form->query('id:advanced_configuration')->asCheckbox()->one()->set(true);

		// Check default values.
		$form->checkValue($default_labels['default']);

		foreach ($http_auth as $auth => $auth_fields) {
			$form->fill(['HTTP authentication' => $auth]);

			// Check visible fields.
			$this->compareArrays(array_merge($default_labels['fields'], $auth_fields['fields']),
				$form->getLabels(CElementFilter::VISIBLE)->asText()
			);
			if ($auth === 'Bearer') {
				// Check required fields.
				$this->compareArrays(array_merge($default_labels['required'], $auth_fields['required']),
						$form->getRequiredLabels()
				);
			}

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
				'maxlength' => '64'
			],
			'Password' => [
				'maxlength' => '64'
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
			],
		];
		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		// Check that both 'Cancel' and 'Add' footer buttons are present and clickable.
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		$this->query('button:Cancel')->one()->click();
	}

	public static function getConnectorsData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'URL' => '{$URL}'

					],
					'error' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector without URL',
						'URL' => ''

					],
					'error' => [
						'Incorrect value for field "url": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unacceptable URL',
						'URL' => 'dns://zabbix.com:82/v1/history'

					],
					'error' => [
						'Invalid parameter "/1/url": unacceptable URL.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unacceptable URL',
						'URL' => 'message://zabbix.com:82/v1/history'

					],
					'error' => [
						'Invalid parameter "/1/url": unacceptable URL.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Connector with unacceptable URL',
						'URL' => '?'

					],
					'error' => [
						'Invalid parameter "/1/url": unacceptable URL.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'URL' => ''

					],
					'error' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "url": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty bearer token',
						'URL' => 'https://zabbix.com:82/v1/history',
						'HTTP authentication' => 'Bearer'
					],
					'error' => [
						'Incorrect value for field "token": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty bearer token',
						'URL' => 'https://zabbix.com:82/v1/history',
						'HTTP authentication' => 'Bearer',
						'Bearer token' => ' '
					],
					'error' => [
						'Incorrect value for field "token": cannot be empty.'
					]
				]
			],
			// Check validation for 'Concurrent sessions' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with value that is less than 1',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '0'
					],
					'error' => [
						'Incorrect value for field "max_senders": value must be no less than "1".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with empty value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => ''
					],
					'error' => [
						'Incorrect value "" for "max_senders" field.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with incorrect value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '@'
					],
					'error' => [
						'Incorrect value "@" for "max_senders" field.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Concurrent session check with greater than "100" value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Concurrent sessions' => '101'
					],
					'error' => [
						'Incorrect value for field "max_senders": value must be no greater than "100".'
					]
				]
			],
			// Check validation for 'Attempts' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts range check',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '0'
					],
					'error' => [
						'Incorrect value for field "max_attempts": value must be no less than "1".'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts check with empty value',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => ''
					],
					'error' => [
						'Incorrect value "" for "max_attempts" field.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Attempts range check',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Attempts' => '6'
					],
					'error' => [
						'Incorrect value for field "max_attempts": value must be no greater than "5".'
					]
				]
			],
			// 'Timeout' field validation checks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty timeout field',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => ''
					],
					'error' => [
						'Incorrect value for field "timeout": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '0'
					],
					'error' => [
						'Invalid parameter "/1/timeout": value must be one of 1-60.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '61s'
					],
					'error' => [
						'Invalid parameter "/1/timeout": value must be one of 1-60.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => '1h'
					],
					'error' => [
						'Invalid parameter "/1/timeout": value must be one of 1-60.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Timeout with invalid parameter',
						'URL' => '{$URL}',
						'Advanced configuration' => true,
						'Timeout' => STRING_255
					],
					'error' => [
						'Invalid parameter "/1/timeout": a time unit is expected.'
					]
				]
			],
			// Tags validation checks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty tag name',
						'URL' => '{$URL}',
						'id:tags_0_tag' => '',
						'id:tags_0_value' => 'value'
					],
					'error' => [
						'Invalid parameter "/1/tags/1/tag": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty tag name with gap',
						'URL' => '{$URL}',
						'id:tags_0_tag' => ' ',
						'id:tags_0_value' => 'value'
					],
					'error' => [
						'Invalid parameter "/1/tags/1/tag": cannot be empty.'
					]
				]
			],
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
					'error' => [
						'Invalid parameter "/1/tags/2/tag": cannot be empty.'
					]
				]
			],
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
					'error' => [
						'Invalid parameter "/1/tags/2": value (tag, operator, value)=(tag, 0, value) already exists.'
					]
				]
			],
			// Custom 'Max records per message' validation check.
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
					'error' => [
						'Incorrect value "2147483648" for "max_records" field.'
					]
				]
			],
			// Checks with multiple errors.
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
					'error' => [
						'Incorrect value for field "max_senders": value must be no less than "1".',
						'Incorrect value for field "max_attempts": value must be no less than "1".'
					]
				]
			],
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
					'error' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "url": cannot be empty.',
						'Incorrect value "2147483648" for "max_records" field.',
						'Incorrect value for field "max_senders": value must be no less than "1".',
						'Incorrect value for field "max_attempts": value must be no greater than "5".'
					]
				]
			],
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
					'error' => [
						'Incorrect value for field "token": cannot be empty.',
						'Incorrect value for field "timeout": cannot be empty.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple create with mandatory fields',
						'URL' => '{$URL}'
					]
				]
			],
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
						'Max records per message' => 'Custom',
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
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple create with changed fields and Bearer macro',
						'URL' => 'https://zabbix.com:82/v1/events',
						'HTTP authentication' => 'Bearer',
						'Bearer token' => '{$TOKEN}',
						'Description' => 'bearer token'
					]
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
		$old_hash = CDBHelper::getHash(self::$connector_sql);

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('link', self::$update_connector)->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->waitUntilReady()->one()->asForm()->submit();

		$this->assertMessage(TEST_GOOD, 'Connector updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$connector_sql));
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
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::$connector_sql);
		}

		$this->page->login()->open('zabbix.php?action=connector.list');

		if ($update) {
			$this->query('link', self::$update_connector)->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create connector')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:connector-form')->asForm()->one();
		$this->assertEquals(($update ? 'Connector' : 'New connector'), $dialog->getTitle());

		// Add a prefix to the name of the Connector in case of update scenario to avoid duplicate names.
		if ($update && CTesTArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$data['fields']['Name'] = 'Update: '.$data['fields']['Name'];
		}

		if (array_key_exists('tags', $data)) {
			$form->query('xpath://table[@id="tags"]//button[text()="Add"]')->one()->click();
		}

		$form->fill($data['fields']);
		$values = $form->getValues();
		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, ($update ? 'Cannot update connector' : 'Cannot create connector'), $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$connector_sql));
			$dialog->close();
		}
		else {
			$this->assertMessage(TEST_GOOD, ($update ? 'Connector updated' : 'Connector created'));

			$db_data = CDBHelper::getColumn('SELECT * FROM connector', 'name');
			$this->assertTrue(in_array($data['fields']['Name'], $db_data));

			if ($update) {
				$this->assertFalse(in_array(self::$update_connector, $db_data));
				self::$update_connector = $data['fields']['Name'];
			}

			$this->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();
			$form->invalidate();
			$this->assertEquals($values, $form->getValues());
			$dialog->close();
		}
	}

	public function testFormConnectors_CancelCreate() {
		$this->checkCancellationAction();
	}

	public function testFormConnectors_CancelUpdate() {
		$this->checkCancellationAction('update');
	}

	/**
	 * Check cancellation of create and update actions
	 *
	 * @param string	$action		action to be checked
	 */
	public function checkCancellationAction($action = 'create') {
		$new_values = [
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
		];

		$old_hash = CDBHelper::getHash(self::$connector_sql);
		$location = ($action === 'create') ? 'button:Create connector' : 'link:'.self::$update_connector;

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query($location)->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->fill($new_values);
		$dialog->query('button:Cancel')->one()->click();

		$dialog->ensureNotPresent();
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$connector_sql));
	}

	/**
	 * Function for checking connector cloning with only changed name.
	 */
	public function testFormConnectors_Clone() {
		foreach ([self::$update_connector, self::$delete_connector] as $connector) {
			$this->page->login()->open('zabbix.php?action=connector.list');
			$this->query('link', $connector)->one()->click();

			$dialog = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
			$values = $dialog->getFields()->asValues();
			$this->query('button:Clone')->waitUntilClickable()->one()->click();

			$dialog->invalidate();
			$dialog->fill(['Name' => 'Cloned_'.$connector]);
			$dialog->submit();

			$this->assertMessage(TEST_GOOD, 'Connector created');
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr($connector)));
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr('Cloned_'.$connector)));

			$this->query('link', 'Cloned_'.$connector)->one()->click();
			$cloned_values = $dialog->getFields()->asValues();
			$this->assertEquals('Cloned_'.$connector, $cloned_values['Name']);

			// Field Name removed from arrays.
			unset($cloned_values['Name']);
			unset($values['Name']);
			$this->assertEquals($values, $cloned_values);
			COverlayDialogElement::find()->one()->close();
		}
	}

	public function testFormConnectors_Delete() {
		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('link', self::$delete_connector)->waitUntilClickable()->one()->click();

		// Click on the Delete button in the opened Connector configuration dialog.
		COverlayDialogElement::find()->waitUntilReady()->one()->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Connector deleted');
		$this->assertFalse($this->query('link', self::$delete_connector)->one(false)->isValid());

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM connector WHERE name='.zbx_dbstr(self::$delete_connector)));
	}

	/**
	 * Modify the URI scheme validation rules and check the result for the URL type in connector form.
	 */
	public function testFormConnectors_UriScheme() {
		$invalid_schemes = ['dns://zabbix.com', 'message://zabbix.com'];
		$default_valid_schemes = ['http://zabbix.com', 'https://zabbix.com', 'ftp://zabbix.com', 'file://zabbix.com',
			'mailto://zabbix.com', 'tel://zabbix.com', 'ssh://zabbix.com'
		];

		$this->page->login()->open('zabbix.php?action=connector.list');
		$this->query('link', self::$default_connector)->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->query('id:connector-form')->asForm()->one();
		$dialog->close();

		// Check default URI scheme rules: http, https, ftp, file, mailto, tel, ssh.
		$this->assertUriScheme($form, $default_valid_schemes);
		$this->assertUriScheme($form, $invalid_schemes, TEST_BAD);

		// Change valid URI schemes on "Other configuration parameters" page.
		$this->page->open('zabbix.php?action=miscconfig.edit');
		$config_form = $this->query('name:otherForm')->asForm()->waitUntilVisible()->one();
		$config_form->fill(['Valid URI schemes' => 'dns,message']);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->open('zabbix.php?action=connector.list');
		$this->assertUriScheme($form, $default_valid_schemes, TEST_BAD);
		$this->assertUriScheme($form, $invalid_schemes);

		// Disable URI scheme validation.
		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$config_form->fill(['Validate URI schemes' => false]);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->open('zabbix.php?action=connector.list');
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
		foreach ($data as $scheme) {
			$this->query('link', self::$default_connector)->one()->click();
			$form->fill(['URL' => $scheme]);
			$form->submit();
			$message = CMessageElement::find()->one();

			if ($expected === TEST_GOOD) {
				$this->assertMessage(TEST_GOOD, 'Connector updated');
				$message->close();
			}
			else {
				$this->assertMessage(TEST_BAD, 'Cannot update connector', 'Invalid parameter "/1/url": unacceptable URL.');
				$message->close();
				COverlayDialogElement::find()->one()->close();
			}
		}
	}

	/**
	 * Sort arrays and compare if they are equal.
	 *
	 * @param array $expected	expected fields from data provider
	 * @param array $actual		actual fields on page
	 */
	private function compareArrays($expected, $actual) {
		sort($expected);
		sort($actual);
		$this->assertEquals(json_encode($expected), json_encode($actual));
	}
}
