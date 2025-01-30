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


require_once dirname(__FILE__).'/../common/testFormAuthentication.php';

/**
 * @backup config, usrgrp
 * @onBefore prepareData
 */
class testUsersAuthenticationMfa extends testFormAuthentication {

	protected const TOTP_HASH = 'SHA-1';
	protected const TOTP_LENGTH = '6';
	protected const DUO_API_HOSTNAME = 'api-3edf651c.test.test';
	protected const DUO_CLIENT_ID = 'DI6GX0DNF2J21PXVLXBB';
	protected const DUO_CLIENT_SECRET = 'SNkg6BvonVsNn2EYzAUC';

	protected static $edit_totp_id;
	protected static $edit_duo_id;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function prepareData() {
		$result = CDataHelper::call('mfa.create', [
			[
				'type' => MFA_TYPE_TOTP,
				'name' => 'Pre-existing TOTP',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => TOTP_CODE_LENGTH_6
			],
			[
				'type' => MFA_TYPE_DUO,
				'name' => 'Pre-existing Duo',
				'api_hostname' => self::DUO_API_HOSTNAME,
				'clientid' => self::DUO_CLIENT_ID,
				'client_secret' => self::DUO_CLIENT_SECRET
			],
			[
				'type' => MFA_TYPE_TOTP,
				'name' => 'TOTP for editing',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => TOTP_CODE_LENGTH_6
			],
			[
				'type' => MFA_TYPE_DUO,
				'name' => 'Duo for editing',
				'api_hostname' => self::DUO_API_HOSTNAME,
				'clientid' => self::DUO_CLIENT_ID,
				'client_secret' => self::DUO_CLIENT_SECRET
			],
			[
				'type' => MFA_TYPE_TOTP,
				'name' => 'TOTP for deletion',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => TOTP_CODE_LENGTH_6
			],
			[
				'type' => MFA_TYPE_DUO,
				'name' => 'Duo for deletion',
				'api_hostname' => self::DUO_API_HOSTNAME,
				'clientid' => self::DUO_CLIENT_ID,
				'client_secret' => self::DUO_CLIENT_SECRET
			]
		]);
		self::$edit_totp_id = $result['mfaids'][2];
		self::$edit_duo_id = $result['mfaids'][3];
	}

	public function testUsersAuthenticationMfa_Layout() {
		$mfa_form = $this->openFormAndCheckBasics('MFA', 'multi-factor');
		$this->assertEquals(['Methods'], $mfa_form->getRequiredLabels());

		// Check method table headers.
		$methods_table = [
			'Methods' => [
				'id' => 'mfa-methods',
				'headers' => ['Name', 'Type', 'User groups', 'Default', 'Action']
			]
		];
		$this->checkTablesHeaders($methods_table, $mfa_form);

		// Check that the add button gets enabled.
		$add_button = $mfa_form->getFieldContainer('Methods')->query('button:Add')->one();
		$update_button = $mfa_form->query('button:Update')->one();

		foreach ([false, true] as $status) {
			$mfa_form->fill(['Enable multi-factor authentication' => $status]);
			$this->assertTrue($add_button->isEnabled($status));
			// The update button should be enabled regardless of whether MFA is enabled.
			$this->assertTrue($update_button->isEnabled());
		}

		// Check the fields in the method modal.
		$mfa_form->getFieldContainer('Methods')->query('button:Add')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('New MFA method', $dialog->getTitle());
		$dialog_form = $dialog->asForm();

		$method_fields = [
			'Type' => ['visible' => true, 'value' => 'TOTP'],
			'Name' => ['visible' => true, 'maxlength' => 128, 'value' => ''],
			'Hash function' => ['visible' => true, 'value' => self::TOTP_HASH],
			'Code length' => ['visible' => true, 'value' => self::TOTP_LENGTH],
			'API hostname' => ['visible' => false, 'maxlength' => 1024, 'value' => ''],
			'Client ID' => ['visible' => false, 'maxlength' => 32, 'value' => ''],
			'Client secret' => ['visible' => false, 'maxlength' => 64, 'value' => '',
				'type' => 'password', 'autocomplete' => 'new-password'
			]
		];

		foreach ($method_fields as $label => $attributes) {
			$field = $dialog_form->getField($label);
			$this->assertTrue($field->isEnabled());
			$this->assertEquals($attributes['visible'], $field->isVisible());

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $field->getValue());
			}

			foreach (['maxlength', 'type', 'autocomplete'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $field->getAttribute($attribute));
				}
			}
		}

		// Check dropdown options.
		$this->assertEquals(['TOTP', 'Duo Universal Prompt'], $dialog_form->getField('Type')->getOptions()->asText());
		$this->assertEquals(['SHA-1', 'SHA-256', 'SHA-512'],
				$dialog_form->getField('Hash function')->getOptions()->asText()
		);
		$this->assertEquals(['6', '8'], $dialog_form->getField('Code length')->getOptions()->asText());

		// Check mandatory fields when MFA type = TOTP.
		$this->assertEquals(['Name'], $dialog_form->getRequiredLabels());

		// Check the hintbox.
		$dialog_form->getLabel('Name')->query('xpath:./button[@data-hintbox]')->one()->click();
		$hintbox = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent()->all()->last();
		$this->assertEquals('Shown as the label to all MFA users in authenticator apps.', $hintbox->getText());
		$hintbox->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();

		// Check fields when MFA type = DUO.
		$dialog_form->fill(['Type' => 'Duo Universal Prompt']);

		$field_visibility = [
			'Type' => true,
			'Name' => true,
			'Hash function' => false,
			'Code length' => false,
			'API hostname' => true,
			'Client ID' => true,
			'Client secret' => true
		];

		foreach ($field_visibility as $field => $visible) {
			$this->assertTrue($dialog_form->getField($field)->isVisible($visible));
		}

		$this->assertEquals(['Name', 'API hostname', 'Client ID', 'Client secret'], $dialog_form->getRequiredLabels());
	}

	public function getCreateData() {
		$create_data = [
			[
				[
					// TOTP with default values.
					'fields' => [
						'Name' => 'TOTP defaults'
					]
				]
			],
			[
				[
					// TOTP name contains leading and trailing spaces.
					'fields' => [
						'Name' => '  TOTP whitespace  '
					]
				]
			],
			[
				[
					// Duo standart case.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo standart'
					]
				]
			],
			[
				[
					// Duo whitespaces.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => '  Duo whitespace  ',
						'API hostname' => '  some.api.hostname  ',
						'Client ID' => '  test  '
					]
				]
			],
			[
				[
					// All fields max length.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => STRING_128,
						'API hostname' => STRING_1024,
						'Client ID' => STRING_32,
						'Client secret' => STRING_64
					]
				]
			],
			[
				[
					// TOTP name already used.
					'expected_authentication_form' => TEST_BAD,
					'error' => 'MFA method "Pre-existing TOTP" already exists.',
					'fields' => [
						'Name' => 'Pre-existing TOTP'
					],
					'skip_name_append' => true
				]
			],
			[
				[
					// TOTP name already used - leading and trailing spaces.
					'expected_authentication_form' => TEST_BAD,
					'error' => 'MFA method "Pre-existing TOTP" already exists.',
					'fields' => [
						'Name' => '  Pre-existing TOTP  '
					],
					'skip_name_append' => true
				]
			],
			[
				[
					// Duo name already used.
					'expected_authentication_form' => TEST_BAD,
					'error' => 'MFA method "Pre-existing Duo" already exists.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Pre-existing Duo'
					],
					'skip_name_append' => true
				]
			],
			// ToDo: Move the missing secret test to the generic data provider when ZBX-25952 is fixed.
			[
				[
					// Duo with Client secret field missing.
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "client_secret": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo missing API',
						'Client secret' => ''
					]
				]
			]
		];

		return array_merge($create_data, $this->getGenericData());
	}

	/**
	 * Test MFA method creation.
	 *
	 * @dataProvider getCreateData
	 */
	public function testUsersAuthenticationMfa_Create($data) {
		$this->testCreateUpdate($data, false);
	}

	public function getSimpleUpdateData() {
		return [
			[
				[
					// Simple update TOTP.
					'name' => 'Pre-existing TOTP'
				]
			],
			[
				[
					// Simple update Duo.
					'name' => 'Pre-existing Duo'
				]
			]
		];
	}

	/**
	 * Open an MFA form and save it without making any changes.
	 *
	 * @dataProvider getSimpleUpdateData
	 */
	public function testUsersAuthenticationMfa_SimpleUpdate() {
		$mfa_form = $this->openMfaForm();
		$mfa_form->fill(['Enable multi-factor authentication' => true]);

		// For assertions later.
		$hash_before = CDBHelper::getHash('SELECT * FROM mfa');
		$table = $this->selectMethodTable($mfa_form);

		// Open the create or edit form.
		$mfa_form->getFieldContainer('Methods')->query('button:Add')->waitUntilClickable()->one()->click();

		// Open MFA method form for editing and save without making changes.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
	}

	public function getUpdateData() {
		$update_data = [
			[
				[
					// TOTP name whitespace.
					'fields' => [
						'Name' => '  TOTP update with whitespace  '
					],
					'skip_name_append' => true
				]
			],
			[
				[
					// Duo whitespaces.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => '  Duo update with whitespace  ',
						'API hostname' => '  some.api.hostname  ',
						'Client ID' => '  test  '
					],
					'skip_name_append' => true
				]
			],
			[
				[
					// TOTP name already used.
					'expected_authentication_form' => TEST_BAD,
					'error' => 'value (name)=(Pre-existing TOTP) already exists.',
					'fields' => [
						'Name' => 'Pre-existing TOTP'
					],
					'skip_name_append' => true
				]
			],
			[
				[
					// Duo name already used.
					'expected_authentication_form' => TEST_BAD,
					'error' => 'value (name)=(Pre-existing Duo) already exists.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Pre-existing Duo'
					],
					'skip_name_append' => true
				]
			]
		];

		return array_merge($update_data, $this->getGenericData());
	}

	public function prepareUpdateData() {
		CDataHelper::call('mfa.update', [
			[
				'mfaid' => self::$edit_totp_id,
				'type' => MFA_TYPE_TOTP,
				'name' => 'TOTP for editing',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => TOTP_CODE_LENGTH_6
			],
			[
				'mfaid' => self::$edit_duo_id,
				'type' => MFA_TYPE_DUO,
				'name' => 'Duo for editing',
				'api_hostname' => self::DUO_API_HOSTNAME,
				'clientid' => self::DUO_CLIENT_ID,
				'client_secret' => self::DUO_CLIENT_SECRET
			]
		]);
	}

	/**
	 * Test MFA method editing.
	 *
	 * @dataProvider getUpdateData
	 * @onBefore     prepareUpdateData
	 */
	public function testUsersAuthenticationMfa_Update($data) {
		$this->testCreateUpdate($data, true);
	}

	/**
	 * Test MFA method deletion.
	 */
	public function testUsersAuthenticationMfa_Remove() {
		$sql_total = 'SELECT NULL FROM mfa';
		$method_count_before = CDBHelper::getCount($sql_total);

		$sql_specific = "SELECT NULL FROM mfa WHERE name in ('TOTP for deletion', 'Duo for deletion')";
		$this->assertEquals(2, CDBHelper::getCount($sql_specific));

		$mfa_form = $this->openMfaForm();
		$mfa_form->fill(['Enable multi-factor authentication' => true]);

		// Remove the records.
		$table = $this->selectMethodTable($mfa_form);
		foreach(['TOTP for deletion', 'Duo for deletion'] as $method_name) {
			$row = $table->findRow('Name', $method_name);
			$row->query('button:Remove')->one()->click();

			// Assert that the deleted method is not visible anymore.
			$method_list = $this->getMfaNamesFromTable($table);
			$this->assertFalse(in_array($method_name, $method_list));
		}

		// Save and open MFA settings again.
		$mfa_form->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$mfa_form->invalidate();
		$mfa_form->selectTab('MFA settings');

		// Check that the deleted methods are not present.
		$method_list = [];
		$table = $this->selectMethodTable($mfa_form);

		foreach($table->getRows() as $row) {
			$method_list[] = $row->getColumn('Name')->getText();
		}

		foreach(['TOTP for deletion', 'Duo for deletion'] as $method_name) {
			$this->assertFalse(in_array($method_name, $method_list));
		}

		// Verify DB records deleted.
		$this->assertEquals($method_count_before - 2, CDBHelper::getCount($sql_total));
		$this->assertEquals(0, CDBHelper::getCount($sql_specific));
	}

	public function getCancelData() {
		return [
			[
				[
					// Cancel MFA method form: TOTP creation.
					'fields' => [
						'Name' => 'TOTP create cancel'
					]
				]
			],
			[
				[
					// Cancel MFA method form: Duo creation.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo create cancel'
					]
				]
			],
			[
				[
					// Cancel MFA method form: TOTP update.
					'fields' => [
						'Name' => 'TOTP update cancel',
						'Hash function' => 'SHA-512',
						'Code length' => '8'
					],
					'update' => true
				]
			],
			[
				[
					// Cancel MFA method form: Duo update.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo update cancel',
						'API hostname' => 'test value',
						'Client ID' => 'some ID'
					],
					'update' => true
				]
			],
			[
				[
					// Cancel Authentication form: TOTP creation.
					'fields' => [
						'Name' => 'TOTP create cancel'
					],
					'save_mfa_method' => true
				]
			],
			[
				[
					// Cancel Authentication form: Duo creation.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo create cancel'
					],
					'save_mfa_method' => true
				]
			],
			[
				[
					// Cancel Authentication form: TOTP update.
					'fields' => [
						'Name' => 'TOTP update cancel',
						'Hash function' => 'SHA-512',
						'Code length' => '8'
					],
					'update' => true,
					'save_mfa_method' => true
				]
			],
			[
				[
					// Cancel Authentication form: Duo update.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo update cancel',
						'API hostname' => 'test value',
						'Client ID' => 'some ID'
					],
					'update' => true,
					'save_mfa_method' => true
				]
			]
		];
	}

	/**
	 * Test scenarios where either MFA configuration is cancelled, or authentication configuration is not saved.
	 *
	 * @dataProvider getCancelData
	 */
	public function testUsersAuthenticationMfa_Cancel($data) {
		$mfa_form = $this->openMfaForm();
		$mfa_form->fill(['Enable multi-factor authentication' => true]);

		// For assertions later.
		$hash_before = CDBHelper::getHash('SELECT * FROM mfa');
		$table = $this->selectMethodTable($mfa_form);
		$ui_rows_before = $table->getRows()->count();

		// Open the create or edit form.
		$update = CTestArrayHelper::get($data, 'update', false);
		$mfa_type = CTestArrayHelper::get($data, 'fields.Type', 'TOTP');
		$update_action = ($mfa_type === 'TOTP') ? 'link:Pre-existing TOTP' : 'link:Pre-existing Duo';
		$create_update_action = $update ? $update_action : 'button:Add';
		$mfa_form->getFieldContainer('Methods')->query($create_update_action)->waitUntilClickable()->one()->click();

		// Fill in data.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		$fields = $this->setDefaultFieldsData(CTestArrayHelper::get($data, 'fields', []), $update, true);
		$dialog_form->fill($fields);

		// Two different paths depending on if cancelling inside the MFA method form or Authentication form.
		if (CTestArrayHelper::get($data, 'save_mfa_method', false)) {
			// Create/Update the MFA method, but reload the page without saving.
			$button = $update ? 'Update' : 'Add';
			$dialog->query('button', $button)->one()->click();

			// In case of update a warning popup might appear.
			if ($update && $this->page->isAlertPresent()) {
				$this->page->acceptAlert();
			}

			$this->page->refresh()->waitUntilReady();
		} else {
			// When cancelling the MFA edit form, but saving the Authentication as a whole.
			$dialog->query('button:Cancel')->one()->click();

			// Check the table before saving.
			$this->assertEquals($ui_rows_before, $table->getRows()->count());
			$method_list = $this->getMfaNamesFromTable($table);
			$this->assertFalse(in_array($data['fields']['Name'], $method_list));

			// In the update scenario reopen and check that the values are not updated.
			if ($update) {
				$mfa_form->getFieldContainer('Methods')->query($create_update_action)->waitUntilClickable()
						->one()->click();
				$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
				$dialog_form->invalidate();

				$values = ($mfa_type === 'TOTP')
				? [
					'Type' => 'TOTP',
					'Name' => 'Pre-existing TOTP',
					'Hash function' => self::TOTP_HASH,
					'Code length' => self::TOTP_LENGTH
				]
				: [
					'Type' => 'Duo Universal Prompt',
					'Name' => 'Pre-existing Duo',
					'API hostname' => self::DUO_API_HOSTNAME,
					'Client ID' => self::DUO_CLIENT_ID
				];
				$dialog_form->checkValue($values);
				$dialog->close();
			}

			// Save Authentication config and assert that nothing has changed.
			$mfa_form->query('button:Update')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		}

		// The final verification steps are the same for all types of cancellations.
		$mfa_form->invalidate();
		$mfa_form->selectTab('MFA settings');
		$table = $this->selectMethodTable($mfa_form);
		$this->assertEquals($ui_rows_before, $table->getRows()->count());
		$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM mfa'));
	}

	public function testUsersAuthenticationMfa_Default() {
		// ToDo - test setting the default MFA method.
	}

	/**
	 * Since create and update tests are similar, use the same code for both.
	 *
	 * @param array $data      Data from data provider.
	 * @param bool  $update    True if the test performed is an update.
	 */
	protected function testCreateUpdate($data, $update) {
		$mfa_form = $this->openMfaForm();
		$mfa_form->fill(['Enable multi-factor authentication' => true]);

		// Open the correct MFA method form.
		$mfa_type = CTestArrayHelper::get($data, 'fields.Type', 'TOTP');
		$update_action = ($mfa_type === 'TOTP') ? 'link:TOTP for editing' : 'link:Duo for editing';
		$action = $update ? $update_action : 'button:Add';
		$mfa_form->getFieldContainer('Methods')->query($action)->waitUntilClickable()->one()->click();

		// Fill in data.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		$fields = $this->setDefaultFieldsData(CTestArrayHelper::get($data, 'fields', []), $update,
				CTestArrayHelper::get($data, 'skip_name_append', false)
		);
		// Client secret update is a special case.
		if ($update && array_key_exists('Client secret', $fields)) {
			$secret = $fields['Client secret'];
			unset($fields['Client secret']);
		}
		$dialog_form->fill($fields);
		/*
		 * Note, there is a bug where the client secred is not validated here when updating.
		 * ToDo: remove this comment when ZBX-25952 is fixed (the logic should not change,
		 * just something to be aware of).
		 */
		if (isset($secret)) {
			$dialog_form->query('button:Change client secret')->one()->click();
			$dialog_form->fill(['Client secret' => $secret]);
		}

		$button = $update ? 'Update' : 'Add';
		$dialog->query('button', $button)->one()->click();

		// In case of update a warning popup might appear.
		if ($update && $this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}

		// If a validation error expected in the Mehod edit form.
		if (CTestArrayHelper::get($data, 'expected_method_form', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Invalid MFA configuration', $data['error']);
			$dialog->close();
		} else {
			// Open the Method edit for to verify data is still there.
			$this->checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields);

			// Save changes to authentication configuration.
			$mfa_form->query('button:Update')->waitUntilClickable()->one()->click();

			// Check if an error is expected when saving authentication configuration as a whole.
			if (CTestArrayHelper::get($data, 'expected_authentication_form', TEST_GOOD) === TEST_BAD) {
				$this->assertMessage(TEST_BAD, 'Cannot update authentication', $data['error']);
			} else {
				// Verify data after saving.
				$this->page->waitUntilReady();
				$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
				$mfa_form->invalidate();
				$mfa_form->selectTab('MFA settings');
				$this->checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields);
			}
		}
	}

	protected function getGenericData() {
		return [
			[
				[
					// TOTP name with special characters.
					'fields' => [
						'Name' => '👍©æ<script>alert("hi!")</script>&nbsp;'
					]
				]
			],
			[
				[
					// TOTP with Name field missing.
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => ''
					]
				]
			],
			[
				[
					// TOTP SHA-256.
					'fields' => [
						'Name' => 'TOTP SHA-256',
						'Hash function' => 'SHA-256'
					]
				]
			],
			[
				[
					// TOTP SHA-512, code length 8.
					'fields' => [
						'Name' => 'TOTP SHA-512',
						'Hash function' => 'SHA-512',
						'Code length' => '8'
					]
				]
			],
			[
				[
					// Duo special characters.
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Name: 👍©æ<script>alert("hi!")</script>&nbsp;',
						'API hostname' => 'API: 👍©æ<script>alert("hi!")</script>&nbsp;',
						'Client ID' => '👍©æ<script>alert("hi!")</script>',
						'Client secret' => 'Pass: 👍©æ<script>alert("hi!")</script>&nbsp;'
					]
				]
			],
			[
				[
					// Duo with Name field missing.
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => ''
					]
				]
			],
			[
				[
					// Duo with API hostname field missing.
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "api_hostname": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo missing API',
						'API hostname' => ''
					]
				]
			],
			[
				[
					// Duo with Client ID field missing.
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "clientid": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo missing API',
						'Client ID' => '',
					]
				]
			]
		];
	}

	/**
	 * Checks that data is displayed as expected in the MFA methods table.
	 *
	 * @param CFormElement          $mfa_form    The form element that contains the method table.
	 * @param COverlayDialogElement $dialog      The dialog that contains the edit form.
	 * @param array                 $fields      Expected fields data for the record.
	 */
	protected function checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields) {
		// Transform the field data to what is expected in UI.
		$fields['Type'] = CTestArrayHelper::get($fields, 'Type', 'TOTP');
		if ($fields['Type'] === 'TOTP') {
			$fields['Hash function'] = CTestArrayHelper::get($fields, 'Hash function', self::TOTP_HASH);
			$fields['Code length'] = CTestArrayHelper::get($fields, 'Code length', self::TOTP_LENGTH);
		}

		// Trim text fields.
		foreach (['Name', 'API hostname', 'Client ID'] as $field) {
			if (array_key_exists($field, $fields)) {
				$fields[$field] = trim($fields[$field]);
			}
		}

		// Check data in the table.
		$table = $this->selectMethodTable($mfa_form);
		$row = $table->findRow('Name', $fields['Name']);
		$expected_table_data = [
			'Name' => $fields['Name'],
			'Type' => $fields['Type'],
			'User groups' => '0',
			'Action' => 'Remove'
		];
		$row->assertValues($expected_table_data);

		// Open the edit form.
		$row->getColumn('Name')->query('xpath:.//a')->one()->click();
		$dialog_form = $dialog->asForm();
		$dialog_form->invalidate();

		// Special check in the case of DUO client secret.
		if (array_key_exists('Client secret', $fields)) {
			$this->assertTrue($dialog_form->getFieldContainer('Client secret')
					->query('button:Change client secret')->one()->isClickable()
			);
			unset($fields['Client secret']);
		}

		// Verify field data.
		$dialog_form->checkValue($fields);
		$dialog->close();
	}

	/**
	 * Populates fields data array with needed defaults before filling the form.
	 *
	 * @param array $fields              Fields data array to populate.
	 * @param bool  $update              Adds suffix to name when updating.
	 * @param bool  $skip_name_append    Skips appending the name field if set to true.
	 *
	 * @return array    The modified fields data.
	 */
	protected function setDefaultFieldsData($fields, $update, $skip_name_append) {
		// When creating a Duo method, avoid having to input the field values each time.
		if (!$update && CTestArrayHelper::get($fields, 'Type', '') === 'Duo Universal Prompt') {
			$fields['API hostname'] = CTestArrayHelper::get($fields, 'API hostname', self::DUO_API_HOSTNAME);
			$fields['Client ID'] = CTestArrayHelper::get($fields, 'Client ID', self::DUO_CLIENT_ID);
			$fields['Client secret'] = CTestArrayHelper::get($fields, 'Client secret', self::DUO_CLIENT_SECRET);
		}

		/*
		 * When updating, the 'Name' field should be appended to avoid name conflicts with create scenarios.
		 * But don't append when 'Name' is not set or explicitly set to empty.
		 * Also don't append when the flag 'skip_name_append' is set.
		 */
		if ($update && array_key_exists('Name', $fields) && $fields['Name'] !== '' && !$skip_name_append) {
			$fields['Name'] = CTestArrayHelper::get($fields, 'Name', '').' - updated';
		}

		return $fields;
	}

	/**
	 * Logs in and opens the MFA configuration form.
	 *
	 * @return CFormElement    The MFA configuration form.
	 */
	protected function openMfaForm() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('MFA settings');
		return $form;
	}

	/**
	 * This is to only have this selector once.
	 *
	 * @param CFormElement $mfa_form    Container element, the MFA form.
	 */
	protected function selectMethodTable($mfa_form) {
		return $mfa_form->getFieldContainer('Methods')->query('xpath:.//table')->one()->asTable();
	}

	/**
	 * Gets all the MFA method names from the table.
	 *
	 * @param CTableElement $table    MFA method table inside the MFA form.
	 *
	 * @return array    Names of the methods in the table.
	 */
	protected function getMfaNamesFromTable($table) {
		$method_list = [];
		foreach($table->getRows() as $row) {
			$method_list[] = $row->getColumn('Name')->getText();
		}

		return $method_list;
	}
}
