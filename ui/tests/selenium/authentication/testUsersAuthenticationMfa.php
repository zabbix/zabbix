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
					'fields' => [
						'Type' => 'TOTP',
						'Name' => 'Default TOTP'
					]
				]
			],
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

		// Fill in data and save changes.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		if (CTestArrayHelper::get($data, 'fields', false)) {
			$dialog_form->fill($data['fields']);
		}
		$dialog->query('button:Add')->one()->click();
		$mfa_form->query('button:Update')->one()->click();

		// ToDo: verify the data before saving. In the table AND in the form.

		// Verify data.
		$this->page->waitUntilReady();
		$mfa_form->invalidate();
		$mfa_form->selectTab('MFA settings');

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
