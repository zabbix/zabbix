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
 */
class testUsersAuthenticationMfa extends testFormAuthentication {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
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
			'Type' => ['visible' => true, 'value' => 'TOTP' ],
			'Name' => ['visible' => true, 'maxlength' => 128, 'value' => ''],
			'Hash function' => ['visible' => true, 'value' => 'SHA-1' ],
			'Code length' => ['visible' => true, 'value' => '6' ],
			'API hostname' => ['visible' => false, 'maxlength' => 1024, 'value' => '' ],
			'Client ID' => ['visible' => false, 'maxlength' => 32, 'value' => '' ],
			'Client secret' => ['visible' => false, 'maxlength' => 64, 'value' => '' ]
		];

		foreach ($method_fields as $label => $attributes) {
			$field = $dialog_form->getField($label);
			$this->assertTrue($field->isEnabled());
			$this->assertEquals($attributes['visible'], $field->isVisible());

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $field->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $field->getAttribute('maxlength'));
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
					// TOTP with Name field missing.
					'expected' => TEST_BAD,
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
		$fields = CTestArrayHelper::get($data, 'fields', []);
		$dialog_form->fill($fields);

		// Fields array with any missing default values.
		$fields_full = $this->setDefaultFields($fields);
		$dialog->query('button:Add')->one()->click();

		// If a validation error expected.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Invalid MFA configuration', $data['error']);
			$dialog->close();
		} else {
			// Verify data before saving.
			$this->checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields_full);

			// Save changes to authentication configuration.
			$mfa_form->query('button:Update')->waitUntilClickable()->one()->click();

			// Verify data after saving.
			$this->page->waitUntilReady();
			$mfa_form->invalidate();
			$mfa_form->selectTab('MFA settings');
			$this->checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields_full);
		}
	}

	/**
	 * Checks that data is displayed as expected in the MFA methods table.
	 *
	 * @param CFormElement          $mfa_form       The form element that contains the method table.
	 * @param COverlayDialogElement $dialog         The dialog that contains the edit form.
	 * @param array                 $fields_full    Expected fields data for the record.
	 */
	protected function checkMethodsTableAndMethodForm($mfa_form, $dialog, $fields_full) {
		// Check data in the table.
		$table = $mfa_form->getFieldContainer('Methods')->query('xpath:.//table')->one()->asTable();
		$row = $table->findRow('Name', $fields_full['Name']);
		$expected_table_data = [
			'Name' => $fields_full['Name'],
			'Type' => $fields_full['Type'],
			'User groups' => "0",
			'Action' => 'Remove'
		];
		$row->assertValues($expected_table_data);

		// Open the edit form and verify fields.
		$row->getColumn('Name')->query('xpath:.//a')->one()->click();
		$dialog_form = $dialog->asForm();
		$dialog_form->invalidate();
		$dialog_form->checkValue($fields_full);
		$dialog->close();
	}

	/**
	 * Returns a fields array populated with default values, used for verifying data.
	 *
	 * @param array $fields    Fields data from data provider.
	 */
	protected function setDefaultFields($fields) {
		$fields['Type'] = CTestArrayHelper::get($fields, 'Type', 'TOTP');

		// Set the defaults if MFA type is TOTP.
		if ($fields['Type'] === 'TOTP') {
			$fields['Hash function'] = CTestArrayHelper::get($fields, 'Hash function', 'SHA-1');
			$fields['Code length'] = CTestArrayHelper::get($fields, 'Code length', '6');
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
