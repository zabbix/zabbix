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

	const DUO_DEFAULT_API_HOSTNAME = 'api-3edf651c.test.test';
	const DUO_DEFAULT_CLIENT_ID = 'DI6GX0DNF2J21PXVLXBB';
	const DUO_DEFAULT_CLIENT_SECRET = 'SNkg6BvonVsNn2EYzAUC';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function prepareData() {
		CDataHelper::call('mfa.create', [
			[
				'type' => MFA_TYPE_TOTP,
				'name' => 'Pre-existing TOTP',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => TOTP_CODE_LENGTH_6
			],
			[
				'type' => MFA_TYPE_DUO,
				'name' => 'Pre-existing Duo',
				'api_hostname' => self::DUO_DEFAULT_API_HOSTNAME,
				'clientid' => self::DUO_DEFAULT_CLIENT_ID,
				'client_secret' => self::DUO_DEFAULT_CLIENT_SECRET
			]
		]);
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
			'Hash function' => ['visible' => true, 'value' => 'SHA-1'],
			'Code length' => ['visible' => true, 'value' => '6'],
			'API hostname' => ['visible' => false, 'maxlength' => 1024, 'value' => ''],
			'Client ID' => ['visible' => false, 'maxlength' => 32, 'value' => '' ],
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
		return [
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
					'error' => 'Incorrect value for field "name": cannot be empty.'
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
					// TOTP name already used.
					'expected_authentication_form' => TEST_BAD,
					'fields' => [
						'Name' => 'Pre-existing TOTP'
					],
					'error' => 'MFA method "Pre-existing TOTP" already exists.'
				]
			],
			[
				[
					// TOTP name already used - leading and trailing spaces.
					'expected_authentication_form' => TEST_BAD,
					'fields' => [
						'Name' => '  Pre-existing TOTP  '
					],
					'error' => 'MFA method "Pre-existing TOTP" already exists.'
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
			],
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
			],
			[
				[
					// Duo name already used.
					'expected_authentication_form' => TEST_BAD,
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Pre-existing Duo'
					],
					'error' => 'MFA method "Pre-existing Duo" already exists.'
				]
			]
		];
	}

	/**
	 * Test MFA method creation.
	 *
	 * @dataProvider  getCreateData
	 */
	public function testUsersAuthenticationMfa_Create($data) {
		$mfa_form = $this->openMfaForm();
		$mfa_form->fill(['Enable multi-factor authentication' => true]);
		$mfa_form->getFieldContainer('Methods')->query('button:Add')->waitUntilClickable()->one()->click();

		// Fill in data.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		$fields = $this->setDefaultFieldsData(CTestArrayHelper::get($data, 'fields', []));
		$dialog_form->fill($fields);

		$dialog->query('button:Add')->one()->click();

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

	/**
	 * Checks that data is displayed as expected in the MFA methods table.
	 *
	 * @param CFormElement          $mfa_form    The form element that contains the method table.
	 * @param COverlayDialogElement $dialog      The dialog that contains the edit form.
	 * @param array                 $fields      Expected fields data for the record.
	 */
	protected function checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields) {
		// Transform the field data so what is expected in UI.
		$fields['Type'] = CTestArrayHelper::get($fields, 'Type', 'TOTP');
		if ($fields['Type'] === 'TOTP') {
			$fields['Hash function'] = CTestArrayHelper::get($fields, 'Hash function', 'SHA-1');
			$fields['Code length'] = CTestArrayHelper::get($fields, 'Code length', '6');
		}

		// Trim text fields.
		foreach (['Name', 'API hostname', 'Client ID'] as $field) {
			if (array_key_exists($field, $fields)) {
				$fields[$field] = trim($fields[$field]);
			}
		}

		// Check data in the table.
		$table = $mfa_form->getFieldContainer('Methods')->query('xpath:.//table')->one()->asTable();
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
					->query('button:Change client secret')->one()->isClickable());
			unset($fields['Client secret']);
		}

		// Verify field data.
		$dialog_form->checkValue($fields);
		$dialog->close();
	}

	/**
	 * Populates field data array with the repetitive data for Duo.
	 *
	 * @fields array $fields    Fields data array to populate.
	 */
	protected function setDefaultFieldsData($fields) {
		if (CTestArrayHelper::get($fields, 'Type', '') === 'Duo Universal Prompt') {
			$fields['API hostname'] = CTestArrayHelper::get($fields, 'API hostname', self::DUO_DEFAULT_API_HOSTNAME);
			$fields['Client ID'] = CTestArrayHelper::get($fields, 'Client ID', self::DUO_DEFAULT_CLIENT_ID);
			$fields['Client secret'] = CTestArrayHelper::get($fields, 'Client secret', self::DUO_DEFAULT_CLIENT_SECRET);
		}

		return $fields;
	}

	/**
	 * Logs in and opens the MFA configuration form.
	 */
	protected function openMfaForm() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('MFA settings');
		return $form;
	}
}
