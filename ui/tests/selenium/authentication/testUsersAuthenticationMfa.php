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


require_once __DIR__.'/../common/testFormAuthentication.php';

/**
 * @backup config
 *
 * @onBefore prepareData
 */
class testUsersAuthenticationMfa extends testFormAuthentication {

	const TOTP_HASH = 'SHA-1';
	const TOTP_LENGTH = '6';
	const DUO_API_HOSTNAME = 'api-3edf651c.test.test';
	const DUO_CLIENT_ID = 'DI6GX0DNF2J21PXVLXBB';
	const DUO_CLIENT_SECRET = 'SNkg6BvonVsNn2EYzAUC';
	const TABLE = 'id:mfa-methods';

	const HASH_SQL = 'SELECT * FROM mfa';

	protected static $edit_totp_id;
	protected static $edit_duo_id;

	/**
	 * Attach MessageBehavior and CTableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class, CTableBehavior::class];
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
		$result = CDataHelper::getIds('name');
		self::$edit_totp_id = $result['TOTP for editing'];
		self::$edit_duo_id = $result['Duo for editing'];
	}

	public function testUsersAuthenticationMfa_Layout() {
		$mfa_form = $this->openFormAndCheckBasics('MFA');
		$this->assertEquals(['Methods'], $mfa_form->getRequiredLabels());

		// Check Method table headers.
		$methods_table = [
			'Methods' => [
				'id' => 'mfa-methods',
				'headers' => ['Name', 'Type', 'User groups', 'Default', 'Action']
			]
		];
		$this->checkTablesHeaders($methods_table, $mfa_form);

		// Check that the Add button gets enabled.
		$add_button = $mfa_form->getFieldContainer('Methods')->query('button:Add')->one();

		foreach ([false, true] as $status) {
			$mfa_form->fill(['Enable multi-factor authentication' => $status]);
			$this->assertTrue($add_button->isEnabled($status));
		}
		// The Update button should still be enabled when MFA is enabled.
		$this->assertTrue($mfa_form->query('button:Update')->one()->isEnabled());

		// Check the fields in the Method modal.
		$add_button->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('New MFA method', $dialog->getTitle());
		$dialog_form = $dialog->asForm();

		$method_field_attributes = [
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

		foreach ($method_field_attributes as $field_label => $attributes) {
			$field = $dialog_form->getField($field_label);
			$this->assertTrue($field->isEnabled());
			$this->assertEquals($attributes['visible'], $field->isVisible());
			$this->assertEquals($attributes['value'], $field->getValue());

			foreach (['maxlength', 'type', 'autocomplete'] as $attribute) {
				if (array_key_exists($attribute, $attributes)) {
					$this->assertEquals($attributes[$attribute], $field->getAttribute($attribute));
				}
			}
		}

		// Check options in dropdowns.
		$dropdown_options = [
			'Type' => ['TOTP', 'Duo Universal Prompt'],
			'Hash function' => ['SHA-1', 'SHA-256', 'SHA-512'],
			'Code length' => ['6', '8']
		];
		foreach ($dropdown_options as $field => $options) {
			$this->assertEquals($options, $dialog_form->getField($field)->getOptions()->asText());
		}

		// Check the mandatory fields when MFA type = TOTP.
		$this->assertEquals(['Name'], $dialog_form->getRequiredLabels());

		// Check the hintbox next to the Name fields.
		$dialog_form->getLabel('Name')->query('xpath:./button[@data-hintbox]')->one()->click();
		$hintbox = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent()->all()->last();
		$this->assertEquals('Shown as the label to all MFA users in authenticator apps.', $hintbox->getText());
		$hintbox->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();

		// Check fields when MFA type = Duo.
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

		// Assert the Add and Cancel buttons.
		$footer = $dialog->getFooter();
		$buttons = $footer->query('button')->all();
		$this->assertEquals(2, $buttons->count());
		$this->assertEquals(['Add', 'Cancel'], $buttons->filter(CElementFilter::CLICKABLE)->asText());
		$dialog->close();
	}

	public function getCreateData() {
		$create_data = [
			'TOTP with default values' => [
				[
					'fields' => [
						'Name' => 'TOTP defaults'
					]
				]
			],
			'TOTP name contains leading and trailing spaces' => [
				[
					'fields' => [
						'Name' => '  TOTP whitespace  '
					],
					'trim' => true
				]
			],
			'Duo standard case' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo standard'
					]
				]
			],
			'Duo fields contain leading and trailing spaces' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => '  Duo whitespace  ',
						'API hostname' => '  some.api.hostname  ',
						'Client ID' => '  test  '
					],
					'trim' => true
				]
			],
			'All fields max length' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => STRING_128,
						'API hostname' => STRING_1024,
						'Client ID' => STRING_32,
						'Client secret' => STRING_64
					]
				]
			],
			'TOTP name already used' => [
				[
					'expected_authentication_form' => TEST_BAD,
					'error' => 'MFA method "Pre-existing TOTP" already exists.',
					'fields' => [
						'Name' => 'Pre-existing TOTP'
					]
				]
			],
			'TOTP name already used - leading and trailing spaces' => [
				[
					'expected_authentication_form' => TEST_BAD,
					'error' => 'MFA method "Pre-existing TOTP" already exists.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => '  Pre-existing TOTP  '
					],
					'trim' => true
				]
			],
			'Duo name already used' => [
				[
					'expected_authentication_form' => TEST_BAD,
					'error' => 'MFA method "Pre-existing Duo" already exists.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Pre-existing Duo'
					]
				]
			],
			/*
			 * There is a bug where the Client secret field is only validated when creating a new record.
			 * ToDo: Move the missing Client secret test to the generic data provider when ZBX-25952 is fixed.
			 */
			'Duo with Client secret field missing' => [
				[
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "client_secret": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo missing Client secret',
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
					'method' => 'Pre-existing TOTP',
					'expected_values' => [
						'Type' => 'TOTP',
						'Name' => 'Pre-existing TOTP',
						'Hash function' => self::TOTP_HASH,
						'Code length' => self::TOTP_LENGTH
					]
				]
			],
			[
				[
					// Simple update Duo.
					'method' => 'Pre-existing Duo',
					'expected_values' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Pre-existing Duo',
						'API hostname' => self::DUO_API_HOSTNAME,
						'Client ID' => self::DUO_CLIENT_ID
					]
				]
			]
		];
	}

	/**
	 * Open an MFA form and save it without making any changes.
	 *
	 * @dataProvider getSimpleUpdateData
	 */
	public function testUsersAuthenticationMfa_SimpleUpdate($data) {
		$mfa_form = $this->openMfaForm();

		// For assertions later.
		$hash_before = CDBHelper::getHash(self::HASH_SQL);

		// Open the edit form.
		$mfa_form->getFieldContainer('Methods')->query('link', $data['method'])->waitUntilClickable()->one()->click();

		// Open the MFA method form for editing and save without making any changes.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Update')->one()->click();
		$dialog->ensureNotPresent();

		$mfa_form->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');

		// Check nothing has changed.
		$mfa_form->invalidate();
		$mfa_form->selectTab('MFA settings');
		$mfa_form->getFieldContainer('Methods')->query('link', $data['method'])->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->asForm()->checkValue($data['expected_values']);
		$dialog->close();

		$this->assertEquals($hash_before, CDBHelper::getHash(self::HASH_SQL));
	}

	public function getUpdateData() {
		$update_data = [
			'TOTP name whitespace' => [
				[
					'fields' => [
						'Name' => '  TOTP update with whitespace  '
					],
					'trim' => true,
					'skip_name_append' => true
				]
			],
			'Duo whitespaces' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => '  Duo update with whitespace  ',
						'API hostname' => '  some.api.hostname  ',
						'Client ID' => '  test  '
					],
					'trim' => true,
					'skip_name_append' => true
				]
			],
			'TOTP name already used' => [
				[
					'expected_authentication_form' => TEST_BAD,
					'error' => 'value (name)=(Pre-existing TOTP) already exists.',
					'fields' => [
						'Name' => 'Pre-existing TOTP'
					],
					'skip_name_append' => true
				]
			],
			'Duo name already used' => [
				[
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
	 *
	 * @onBefore prepareUpdateData
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

		$sql_specific = 'SELECT NULL FROM mfa WHERE name IN (\'TOTP for deletion\', \'Duo for deletion\')';
		$this->assertEquals(2, CDBHelper::getCount($sql_specific));

		$mfa_form = $this->openMfaForm();

		// Remove the records.
		$table = $this->getTable(self::TABLE);
		foreach (['TOTP for deletion', 'Duo for deletion'] as $method_name) {
			$row = $table->findRow('Name', $method_name);
			$row->query('button:Remove')->one()->click();

			// Assert that the deleted method is not visible anymore.
			$this->assertFalse(in_array($method_name, $this->getTableColumnData('Name', self::TABLE)));
		}

		// Save and open MFA settings again.
		$mfa_form->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$mfa_form->invalidate();
		$mfa_form->selectTab('MFA settings');

		// Check that the deleted methods are not present anymore.
		$method_list = $this->getTableColumnData('Name', self::TABLE);
		$this->assertEmpty(array_intersect(['TOTP for deletion', 'Duo for deletion'], $method_list));

		// Verify DB records are deleted.
		$this->assertEquals($method_count_before - 2, CDBHelper::getCount($sql_total));
		$this->assertEquals(0, CDBHelper::getCount($sql_specific));
	}

	public function getCancelData() {
		return [
			'Cancel MFA method form: TOTP creation' => [
				[
					'fields' => [
						'Name' => 'TOTP create cancel'
					]
				]
			],
			'Cancel MFA method form: Duo update' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo update cancel',
						'API hostname' => 'test value',
						'Client ID' => 'some ID'
					],
					'update' => true
				]
			],
			'Cancel Authentication form: Duo creation' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo create cancel'
					],
					'save_mfa_method' => true
				]
			],
			'Cancel Authentication form: TOTP update' => [
				[
					'fields' => [
						'Name' => 'TOTP update cancel',
						'Hash function' => 'SHA-512',
						'Code length' => '8'
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

		// Save the starting state for assertions later.
		$hash_before = CDBHelper::getHash(self::HASH_SQL);
		$table = $this->getTable(self::TABLE);
		$ui_rows_before = $table->getRows()->count();

		// Open the create/edit form.
		$update = CTestArrayHelper::get($data, 'update', false);
		$mfa_type = CTestArrayHelper::get($data, 'fields.Type', 'TOTP');
		$update_link = ($mfa_type === 'TOTP') ? 'link:Pre-existing TOTP' : 'link:Pre-existing Duo';
		$create_update_element = $update ? $update_link : 'button:Add';
		$mfa_form->getFieldContainer('Methods')->query($create_update_element)->waitUntilClickable()->one()->click();

		// Fill in data.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		$fields = $this->setDefaultFieldsData(CTestArrayHelper::get($data, 'fields', []), $update, true);
		$dialog_form->fill($fields);

		// Two different paths depending on if cancelling inside the MFA method form or Authentication form.
		if (CTestArrayHelper::get($data, 'save_mfa_method')) {
			// Create/Update the MFA method without cancelling, but reload the Authentication page without saving.
			$dialog_form->submit();

			// In case of update a warning popup might appear.
			if ($update && $this->page->isAlertPresent()) {
				$this->page->acceptAlert();
			}

			$this->page->refresh()->waitUntilReady();
		}
		else {
			// When cancelling in the Method create/edit form, but saving the Authentication configuration.
			$dialog->query('button:Cancel')->one()->click();
			$dialog->ensureNotPresent();

			// Check the table before saving.
			$this->assertEquals($ui_rows_before, $table->getRows()->count());
			$method_list = $this->getTableColumnData('Name', self::TABLE);
			$this->assertFalse(in_array($data['fields']['Name'], $method_list));

			// In the update scenario reopen the create/edit form and check that the values have not been updated.
			if ($update) {
				$mfa_form->getFieldContainer('Methods')->query($create_update_element)->waitUntilClickable()
						->one()->click();
				$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
				$dialog_form->invalidate();

				$values = [
					'TOTP' => [
						'Type' => 'TOTP',
						'Name' => 'Pre-existing TOTP',
						'Hash function' => self::TOTP_HASH,
						'Code length' => self::TOTP_LENGTH
					],
					'Duo Universal Prompt' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Pre-existing Duo',
						'API hostname' => self::DUO_API_HOSTNAME,
						'Client ID' => self::DUO_CLIENT_ID
					]
				];
				$dialog_form->checkValue($values[$mfa_type]);
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
		$table = $this->getTable(self::TABLE);
		$this->assertEquals($ui_rows_before, $table->getRows()->count());
		$this->assertEquals($hash_before, CDBHelper::getHash(self::HASH_SQL));
	}

	/**
	 * Tests that setting the default MFA method works.
	 */
	public function testUsersAuthenticationMfa_Default() {
		$mfa_form = $this->openMfaForm();
		$table = $this->getTable(self::TABLE);

		// Detect which method is currently set as the default.
		$current_default = $this->getDefaultMethodName($table);
		$method_to_set = ($current_default === 'Pre-existing TOTP') ? 'Pre-existing Duo' : 'Pre-existing TOTP';

		// Click the 'default' radio for a different method.
		$table->findRow('Name', $method_to_set)->getColumn('Default')->query('tag:input')->one()->click();

		// Save and check.
		$mfa_form->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$mfa_form->invalidate();
		$mfa_form->selectTab('MFA settings');
		$table = $this->getTable(self::TABLE);
		$new_default = $this->getDefaultMethodName($table);
		$this->assertEquals($method_to_set, $new_default);
	}

	/**
	 * Deletes all MFA methods with API.
	 */
	public function prepareSaveEmpty() {
		// Disable MFA before deleting all methods.
		CDataHelper::call('authentication.update', ['mfa_status' => MFA_DISABLED]);

		// Get all MFA methods for deletion.
		$methods = CDataHelper::call('mfa.get', []);

		// Delete all MFA methods.
		$mfaids = array_column($methods, 'mfaid');
		CDataHelper::call('mfa.delete', $mfaids);
	}

	/**
	 * Try to save the MFA configuration when MFA is enabled, but no methods are defined. And take mfa forms screenshots.
	 *
	 * @onBefore prepareSaveEmpty
	 */
	public function testUsersAuthenticationMfa_SaveEmpty() {
		$mfa_form = $this->openMfaForm();
		$this->assertScreenshot($this->query('id:tabs')->one(), 'empty_mfa_form');
		$mfa_form->query('button:Update')->one()->click();
		$this->assertMessage(TEST_BAD, 'Cannot update authentication', 'Default MFA method must be specified.');
		CMessageElement::find()->one()->close();

		// Take screenshots.
		$mfa_form->getFieldContainer('Methods')->query('button:Add')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		$this->page->removeFocus();
		// TODO: unstable screenshot on Jenkins. Remove border radius from button element.
		$this->page->getDriver()->executeScript('arguments[0].style.borderRadius=0;',
				[$dialog->query('button:Add')->one()]);
		$this->assertScreenshot($dialog, 'type_totp');
		$dialog_form->fill([
			'Type' => 'Duo Universal Prompt',
			'Name' => 'screenshot',
			'API hostname' => self::DUO_API_HOSTNAME,
			'Client ID' => self::DUO_CLIENT_ID,
			'Client secret' => self::DUO_CLIENT_SECRET
		]);
		$this->page->removeFocus();
		$this->assertScreenshot($dialog, 'type_duo');
		$dialog_form->submit();
		$dialog->ensureNotPresent();
		$this->assertScreenshot($this->query('id:tabs')->one(), 'mfa_form_with_method');
	}

	/**
	 * Performs the testing of create and update scenarios.
	 *
	 * @param array $data    Data from data provider.
	 * @param bool  $update  True if the test performed is an update.
	 */
	protected function testCreateUpdate($data, $update) {
		$mfa_form = $this->openMfaForm();

		// Open the correct MFA method form.
		$update_link = (CTestArrayHelper::get($data, 'fields.Type', 'TOTP') === 'TOTP')
			? 'link:TOTP for editing'
			: 'link:Duo for editing';
		$element = $update ? $update_link : 'button:Add';
		$mfa_form->getFieldContainer('Methods')->query($element)->waitUntilClickable()->one()->click();

		// Fill in data.
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog_form = $dialog->asForm();
		$fields = $this->setDefaultFieldsData(CTestArrayHelper::get($data, 'fields', []), $update,
				CTestArrayHelper::get($data, 'skip_name_append', false)
		);
		// Client secret update is a special case.
		if ($update && array_key_exists('Client secret', $fields)) {
			$dialog_form->query('button:Change client secret')->one()->click();
		}
		$dialog_form->fill($fields)->submit();

		// In the case of update, a warning popup might appear.
		if ($update && (CTestArrayHelper::get($data, 'fields.Hash function')
				|| CTestArrayHelper::get($data, 'fields.Code length'))) {
			$this->page->waitUntilAlertIsPresent();
			$this->assertEquals('After this change, users who have already enrolled in this MFA method will have to'.
					' complete the enrollment process again because TOTP secrets will be reset.', $this->page->getAlertText()
			);
			$this->page->acceptAlert();
		}

		// If a validation error expected in the Method edit form.
		if (CTestArrayHelper::get($data, 'expected_method_form', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Invalid MFA configuration', $data['error']);
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();

			// If an error is expected only when saving the Authentication configuration because an MFA method name already exists.
			if (CTestArrayHelper::get($data, 'expected_authentication_form', TEST_GOOD) === TEST_BAD) {
				// Save changes to the Authentication configuration.
				$this->assertEquals(2, $this->getTable(self::TABLE)->findRows('Name', trim($fields['Name']))->count());
				$mfa_form->query('button:Update')->waitUntilClickable()->one()->click();
				$this->assertMessage(TEST_BAD, 'Cannot update authentication', $data['error']);
			}
			else {
				// Transform the field data to what is expected in UI.
				$fields['Type'] = CTestArrayHelper::get($fields, 'Type', 'TOTP');
				if ($fields['Type'] === 'TOTP') {
					$fields['Hash function'] = CTestArrayHelper::get($fields, 'Hash function', self::TOTP_HASH);
					$fields['Code length'] = CTestArrayHelper::get($fields, 'Code length', self::TOTP_LENGTH);
				}

				// Trim text fields, it is expected that any leading and trailing spaces are not saved.
				if (CTestArrayHelper::get($data, 'trim')) {
					$fields = CTestArrayHelper::trim($fields);
				}

				// Open the Method edit form to verify data is still there.
				$this->checkMethodsTableAndMethodForm($dialog, $fields);
				$mfa_form->query('button:Update')->waitUntilClickable()->one()->click();

				// If no error expected, verify the data after saving.
				$this->page->waitUntilReady();
				$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
				$mfa_form->invalidate();
				$mfa_form->selectTab('MFA settings');
				$this->checkMethodsTableAndMethodForm($dialog, $fields);
			}
		}
	}

	/**
	 * Generic data for create and update data providers.
	 */
	protected function getGenericData() {
		return [
			'TOTP name with special characters' => [
				[
					'fields' => [
						'Name' => '👍©æ<script>alert("hi!")</script>&nbsp;'
					]
				]
			],
			'TOTP with Name field missing' => [
				[
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => ''
					]
				]
			],
			'TOTP SHA-256' => [
				[
					'fields' => [
						'Name' => 'TOTP SHA-256',
						'Hash function' => 'SHA-256'
					]
				]
			],
			'TOTP SHA-512, code length 8' => [
				[
					'fields' => [
						'Name' => 'TOTP SHA-512',
						'Hash function' => 'SHA-512',
						'Code length' => '8'
					]
				]
			],
			'Duo with special characters' => [
				[
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Name: 👍©æ<script>alert("hi!")</script>&nbsp;',
						'API hostname' => 'API: 👍©æ<script>alert("hi!")</script>&nbsp;',
						'Client ID' => '👍©æ<script>alert("hi!")</script>',
						'Client secret' => 'Pass: 👍©æ<script>alert("hi!")</script>&nbsp;'
					]
				]
			],
			'Duo with Name field missing' => [
				[
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => ''
					]
				]
			],
			'Duo with API hostname field missing' => [
				[
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "api_hostname": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo missing API',
						'API hostname' => ''
					]
				]
			],
			'Duo with Client ID field missing' => [
				[
					'expected_method_form' => TEST_BAD,
					'error' => 'Incorrect value for field "clientid": cannot be empty.',
					'fields' => [
						'Type' => 'Duo Universal Prompt',
						'Name' => 'Duo missing Client ID',
						'Client ID' => ''
					]
				]
			]
		];
	}

	/**
	 * Checks that data is displayed as expected in the MFA methods table and inside the Method edit form.
	 *
	 * @param COverlayDialogElement $dialog		The dialog that contains the edit form.
	 * @param array                 $fields		Expected fields data for the record.
	 */
	protected function checkMethodsTableAndMethodForm($dialog, $fields) {
		// Check data in the Method table.
		$table = $this->getTable(self::TABLE);;
		$row = $table->findRow('Name', $fields['Name']);
		$expected_table_data = [
			'Name' => $fields['Name'],
			'Type' => $fields['Type'],
			'User groups' => '0',
			'Action' => 'Remove'
		];
		$row->assertValues($expected_table_data);

		// Open the Method edit form.
		$row->getColumn('Name')->query('xpath:.//a')->one()->click();
		$dialog_form = $dialog->asForm();
		$dialog_form->invalidate();

		// Special case - Duo Client secret field.
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
	 * Populates fields data array with defaults for filling the Method form.
	 *
	 * @param array $fields            Fields data array to populate.
	 * @param bool  $update            Adds suffix to name when updating.
	 * @param bool  $skip_name_append  Skips appending the name field if set to true.
	 *
	 * @return array
	 */
	protected function setDefaultFieldsData($fields, $update = false, $skip_name_append = true) {
		// When creating a Duo method, avoid having to input the field values each time.
		if (!$update && CTestArrayHelper::get($fields, 'Type') === 'Duo Universal Prompt') {
			$fields['API hostname'] = CTestArrayHelper::get($fields, 'API hostname', self::DUO_API_HOSTNAME);
			$fields['Client ID'] = CTestArrayHelper::get($fields, 'Client ID', self::DUO_CLIENT_ID);
			$fields['Client secret'] = CTestArrayHelper::get($fields, 'Client secret', self::DUO_CLIENT_SECRET);
		}

		/*
		 * When updating, the Name field is appended to avoid name conflicts with create scenarios.
		 * But don't append it when the name is not set or explicitly set to empty.
		 * Also don't append when the flag 'skip_name_append' is set - this is for duplicate name scenarios.
		 */
		if ($update && array_key_exists('Name', $fields) && $fields['Name'] !== '' && !$skip_name_append) {
			$fields['Name'] = CTestArrayHelper::get($fields, 'Name', '').' - updated';
		}

		return $fields;
	}

	/**
	 * Logs in and opens the MFA configuration form.
	 *
	 * @return CFormElement
	 */
	protected function openMfaForm() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('MFA settings');
		$form->fill(['Enable multi-factor authentication' => true]);
		return $form;
	}

	/**
	 * Returns the name of the currently set default MFA method.
	 *
	 * @return string
	 */
	protected function getDefaultMethodName($table) {
		return $table->query('xpath:.//tr[.//input[@type="radio" and @checked]]')->one()->asTableRow()
				->getColumn('Name')->getText();
	}
}
