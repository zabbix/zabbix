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

/**
 * @backup usrgrp
 *
 * @dataSource LoginUsers, ScheduledReports
 *
 * @onBefore prepareMfaHostgroupData
 */
class testFormUserGroups extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	protected static $user_group = 'Selenium user group';

	/**
	 * Create test data for "Multi-factor authentication" field in user group form.
	 */
	public function prepareMfaHostgroupData() {
		CDataHelper::call('mfa.create', [
			[
				'type' => MFA_TYPE_TOTP,
				'name' => 'User groups TOTP',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => '6'
			],
			[
				'type' => MFA_TYPE_DUO,
				'name' => 'User groups DUO',
				'api_hostname' => 'API hostname 1',
				'clientid' => 'client_id_123',
				'client_secret' => 'secret'
			]
		]);

		CDataHelper::call('authentication.update', ['mfa_status' => MFA_ENABLED]);
	}

	public function testFormUserGroups_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=usergroup.list');
		$this->query('button:Create user group')->waitUntilClickable()->one()->click();

		$this->page->assertHeader('User groups');
		$this->page->assertTitle('Configuration of user groups');
		$form = $this->query('id:user-group-form')->asForm()->one();
		$this->assertEquals(['User group', 'Template permissions', 'Host permissions', 'Problem tag filter'], $form->getTabs());
		$this->assertEquals('User group', $form->getSelectedTab());

		$this->assertEquals(['Group name', 'Users', 'Frontend access', 'LDAP Server', 'Multi-factor authentication',
				'Enabled', 'Debug mode'], $form->getLabels(CElementFilter::VISIBLE)->asText()
		);
		$this->assertEquals(['Group name'], $form->getRequiredLabels());

		$default_values = [
			'Group name' => '',
			'Users' => '',
			'Frontend access' => 'System default',
			'LDAP Server' => 'Default',
			'Multi-factor authentication' => 'Default',
			'Enabled' => true,
			'Debug mode' => false
		];
		$form->checkValue($default_values);

		$this->assertEquals(64, $form->getField('Group name')->getAttribute('maxlength'));

		$dropdowns = [
			'Frontend access' => ['System default', 'Internal', 'LDAP', 'Disabled'],
			'LDAP Server' => ['Default'],
			'Multi-factor authentication' => ['User groups DUO', 'User groups TOTP','Disabled', 'Default']
		];

		foreach ($dropdowns as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getOptions()->asText());
		}

		foreach ([MFA_ENABLED, MFA_DISABLED] as $mfa_status) {
			if ($mfa_status) {
				// Check that warning icon is not visible if MFA is enabled.
				$this->assertFalse($form->query('id:mfa-warning')->one()->isDisplayed(), 'Warning icon should not be visible.');
			}
			else {
				// Disable MFA and reload the page.
				CDataHelper::call('authentication.update', ['mfa_status' => $mfa_status]);
				$this->page->refresh()->waitUntilReady();
				$form->invalidate();

				// Check that, when MFA is disabled, the default value on MFA field is "Disabled".
				$mfa_field = $form->getField('Multi-factor authentication');
				$this->assertEquals('Disabled', $mfa_field->getValue());

				// Change the value of the MFA field, and the appeared warning icon and its hint.
				$mfa_field->fill('Default');
				$warning_icon = $form->query('id:mfa-warning')->waitUntilVisible()->one();
				$warning_icon->click();
				$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
						->waitUntilReady()->one();
				$this->assertEquals('Multi-factor authentication is disabled system-wide.', $hint->getText());
				$hint->close();
			}
		}

		// Check the default layout in other tabs.
		$tabs = [
			'Template permissions' => ['Template groups', 'Permissions', 'Action'],
			'Host permissions' => ['Host groups', 'Permissions', 'Action'],
			'Problem tag filter' => ['Host groups', 'Tags', 'Action']
		];

		foreach ($tabs as $tab => $table_headers) {
			$form->selectTab($tab);
			$this->assertEquals(['Permissions'], array_values($form->getLabels(CElementFilter::VISIBLE)->asText()));

			// Each of the three tabs have "Permissions" field, so this field ir retrieved using the exact label element.
			$table = $form->getFieldByLabelElement($form->getLabel('Permissions'))->asTable();
			$this->assertEquals($table_headers, $table->getHeadersText());
			$this->assertEquals(['Add'], $table->query('tag:button')->all()->filter(CElementFilter::CLICKABLE)->asText());
		}

		// Check the action buttons below the form.
		$this->assertEquals(['Add', 'Cancel'], $this->query('class:tfoot-buttons')->one()->query('tag:button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		// Check that Frontend access, MFA and Enabled fields are read only for Zabbix Administrators.
		$form->query('button:Cancel')->one()->click();
		$this->page->waitUntilReady();
		$this->query('link:Zabbix administrators')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$form->invalidate();
		$admin_fields = [
			'Group name' => [
				'value' => 'Zabbix administrators'
			],
			'Users' => [
				'value' => ['Admin (Zabbix Administrator)', 'admin-zabbix', 'admin user for testFormScheduledReport',
					'filter-create', 'filter-delete', 'filter-update', 'http-auth-admin', 'user-recipient of the report',
					'user-zabbix'
				]
			],
			'Frontend access' => [
				'value' => 'System default',
				'class' => 'text-field green'
			],
			'Multi-factor authentication' => [
				'value' => 'Disabled',
				'class' => 'text-field green'
			],
			'Enabled' => [
				'value' => 'Enabled',
				'class' => 'text-field green'
			],
			'Debug mode' => [
				'value' => false
			]
		];

		foreach ($admin_fields as $field_name => $parameters) {
			$field = $form->getField($field_name);

			if (array_key_exists('class', $parameters)) {
				// Check that read-only fields are represented as text, and check their text.
				$this->assertEquals($parameters['value'], $field->getText());
				$this->assertTrue($field->query('xpath:./span[@class='.CXPathHelper::escapeQuotes($parameters['class']).']')
						->one(false)->isValid()
				);
			}
			else {
				// Check value of the interactable fields and make sure they are enabled.
				$this->assertEquals($parameters['value'], $field->getValue());
				$this->assertTrue($field->isEnabled());
			}
		}
	}

	public static function getCommonData() {
		return [
			// #0 Empty space in name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => ' '
					],
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			// #1 Already existing user.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Zabbix administrators'
					],
					'duplicate' => true,
					'error' => 'User group "Zabbix administrators" already exists.'
				]
			],
			// #2 Adding the current user to a disabled group.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Selenium test add admin in disabled group',
						'Users' => 'Admin',
						'Enabled' => false
					],
					'error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
				]
			],
			// #3 Adding the current user to a group without GUI access.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => 'Selenium test add admin in group with disabled GUI access',
						'Users' => 'Admin',
						'Frontend access' => 'Disabled'
					],
					'error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
				]
			],
			// #4 Group with trailing and leading spaces in name.
			[
				[
					'fields' => [
						'Group name' => '   User group with trailing and leading spaces   '
					],
					'trim_name' => true
				]
			],
			// #5 Group with XSS in name.
			[
				[
					'fields' => [
						'Group name' => '<body onload=alert(\'User group\')>;'
					]
				]
			],
			// #6 Group with all parameters from "User group" tab listed.
			[
				[
					'fields' => [
						'Group name' => 'User group %#$@^%$&%^🙈😂😱*&^(*_))}{|" with symbols',
						'Users' => 'test-user',
						'Frontend access' => 'LDAP',
						'Enabled' => false,
						'Debug mode' => true,
						'Multi-factor authentication' => 'User groups TOTP'
					]
				]
			]
		];
	}

	public static function getCreateData() {
		return [
			// Empty name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group name' => ''
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCommonData
	 * @dataProvider getCreateData
	 */
	public function testFormUserGroups_Create($data) {
		$this->executeAction($data);
	}

	/**
	 * @dataProvider getCommonData
	 */
	public function testFormUserGroups_Update($data) {
		$this->executeAction($data, true);
	}

	/**
	 * Check User group creation or update.
	 *
	 * @param array     $data     data provider
	 * @param boolean   $update   flag that determines whether the tested scenario is an update scenario
	 */
	protected function executeAction($data, $update = false) {
		// Add word "update" to update cases to ensure uniqueness. Only for cases where name validation is not checked.
		if ($update && !in_array($data['fields']['Group name'], [' ', 'Zabbix administrators'])) {
			$data['fields']['Group name'] = str_replace('group', 'group update', $data['fields']['Group name']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM usrgrp');
		}

		$this->page->login()->open('zabbix.php?action=usergroup.list')->waitUntilReady();

		$button_selector = ($update) ? 'link:'.self::$user_group : 'button:Create user group';
		$this->query($button_selector)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$form = $this->query('id:user-group-form')->waitUntilVisible()->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$message = ($update) ? 'User group updated' : 'User group added';
			$this->assertMessage(TEST_GOOD, $message);

			// Only the user group name field is trimmed, since it is the only input element in the form.
			if (CTestArrayHelper::get($data, 'trim_name')) {
				$data['fields']['Group name'] = trim($data['fields']['Group name']);
			}

			// Once success message is there the name of the group to be updated in next cases needs to be saved.
			if ($update) {
				self::$user_group = $data['fields']['Group name'];
			}

			$this->assertEquals(1, CDBHelper::getCount('SELECT usrgrpid FROM usrgrp WHERE name='.
					zbx_dbstr($data['fields']['Group name'])
			));

			// Check that the previously entered values are saved.
			$this->query('link', $data['fields']['Group name'])->one()->click();
			$form->invalidate();
			$form->checkValue($data['fields']);
		}
		else {
			$message = ($update) ? 'Cannot update user group' : 'Cannot add user group';
			$this->assertMessage(TEST_BAD, $message, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM usrgrp'));
		}
	}

	public static function getDeleteData() {
		return [
			[
				[
					'name' => 'Disabled'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error' => 'User group "Zabbix administrators" is used in'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium user group in scripts',
					'error' => 'User group "Selenium user group in scripts" is used in script "Selenium script".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium user group in configuration',
					'error' => 'User group "Selenium user group in configuration" is used in configuration for database down messages.'
				]
			],
			[
				[
					'name' => 'User group %#$@^%$&%^🙈😂😱*&^(*_))}{|" with symbols'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormUserGroups_Delete($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM usrgrp');
		}

		$this->page->login()->open('zabbix.php?action=usergroup.list')->waitUntilReady();

		// Locate row first, as the user group name may coincides with group actions for other groups ('Internal', 'Disabled').
		$this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name'])->query('link', $data['name'])
				->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$this->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->assertEquals('Delete selected group?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'User group deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM usrgrp WHERE name='.zbx_dbstr($data['name'])));
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot delete user group', $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM usrgrp'));
		}
	}
}
