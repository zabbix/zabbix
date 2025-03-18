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


require_once __DIR__ . '/../../include/CWebTest.php';

/**
 * @backup users
 *
 * @dataSource LoginUsers, UserPermissions
 *
 * @onBefore prepareData
 */
class testFormUser extends CWebTest {

	const SQL = 'SELECT * FROM users';
	const ZABBIX_LDAP_USER = 'John Zabbix';
	const UPDATE_USER = 'Tag-user';
	const UPDATE_PASSWORD = 'Zabbix_Test_123';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public function prepareData() {
		// Create LDAP server.
		CDataHelper::call('userdirectory.create', [
			[
				'idp_type' => IDP_TYPE_LDAP,
				'name' => 'LDAP server #1',
				'host' => 'LDAP host',
				'port' => 389,
				'base_dn' => 'ou=Users,dc=example,dc=org',
				'bind_dn' => 'cn=ldap_search,dc=example,dc=org',
				'bind_password' => 'ldapsecretpassword',
				'search_attribute' => 'uid'
			]
		]);
		$userdirectoryids = CDataHelper::getIds('name');

		// Create group with frontend access -> LDAP and previously created LDAP server.
		CDataHelper::call('usergroup.create', [
			[
				'name' => 'Zabbix LDAP',
				'gui_access' => GROUP_GUI_ACCESS_LDAP,
				'userdirectoryid' => $userdirectoryids['LDAP server #1']
			]
		]);
		$usergrpids = CDataHelper::getIds('name');

		// Create user with frontend access -> LDAP.
		CDataHelper::call('user.create', [
			[
				'username' => self::ZABBIX_LDAP_USER,
				'passwd' => 'test5678',
				'roleid' => '3'
			],
			[
				'username' => 'LDAP change password button check',
				'passwd' => 'test5678',
				'roleid' => '2',
				'usrgrps' => [
					[
						'usrgrpid' => $usergrpids['Zabbix LDAP']
					]
				]
			]
		]);
	}

	public function getLayoutData() {
		return [
			[
				[
					'role' => '',
					'required' => ['Username', 'Password', 'Password (once again)', 'Refresh', 'Rows per page'],
					'default' => [
						'Username' => '',
						'Name' => '',
						'Last name' => '',
						'Groups' => '',
						'Password' => '',
						'Password (once again)' => '',
						'Language' => 'System default',
						'Time zone' => CDateTimeHelper::getTimeZoneFormat('System default'), // For local tests change system default 'Europe/Riga' to 'UTC'.
						'Theme' => 'System default',
						'Auto-login' => false,
						'id:autologout_visible' => false,
						'id:autologout' => '15m',
						'Refresh' => '30s',
						'Rows per page' => '50',
						'URL (after login)' => ''
					],
					'disabled' => ['id:autologout'],
					'enabled_buttons' => ['Add', 'Cancel', 'Select'],
					'hintbox_warning' => [
						'Language' => 'You are not able to choose some of the languages,'.
								' because locales for them are not installed on the web server.'
					]
				]
			],
			[
				[
					'user' => 'guest',
					'role' => 'Guest role',
					'required' => ['Username', 'Refresh', 'Rows per page'],
					'default' => [
						'Username' => 'guest',
						'Name' => '',
						'Last name' => '',
						'Groups' => ['Disabled', 'Guests', 'Internal'],
						'Refresh' => '30s',
						'Rows per page' => '50',
						'URL (after login)' => ''
					],
					// TODO: xpath should be replaced after ZBX-23936 fix.
					'disabled' => ['Username', 'button:Change password', 'xpath:.//button[@id="label-lang"]/..',
						'xpath:.//button[@id="label-timezone"]/..', 'xpath:.//button[@id="label-theme"]/..'
					],
					'disabled_values' => [
						'id:label-lang' => 'System default',
						'id:label-timezone' => CDateTimeHelper::getTimeZoneFormat('System default'),
						'id:label-theme' => 'System default'
					],
					'enabled_buttons' => ['Update', 'Delete', 'Cancel', 'Select'],
					'hintbox_warning' => [
						'Password' => 'Password can only be changed for users using the internal Zabbix authentication.'
					]
				]
			],
			[
				[
					'user' => 'Admin',
					'role' => 'Super admin role',
					'required' => ['Username', 'Current password', 'Password', 'Password (once again)', 'Refresh', 'Rows per page'],
					'default' => [
						'Username' => 'Admin',
						'Name' => 'Zabbix',
						'Last name' => 'Administrator',
						'Groups' => ['Internal', 'Zabbix administrators'],
						'Current password' => '',
						'Password' => '',
						'Password (once again)' => '',
						'Language' => 'System default',
						'Time zone' => CDateTimeHelper::getTimeZoneFormat('System default'),
						'Theme' => 'System default',
						'Auto-login' => true,
						'id:autologout_visible' => false,
						'id:autologout' => '15m',
						'Refresh' => '30s',
						'Rows per page' => '100',
						'URL (after login)' => ''
					],
					'disabled' => ['id:autologout', 'button:Delete'],
					'enabled_buttons' => ['Update', 'Cancel', 'Select'],
					'hintbox_warning' => [
						'Language' => 'You are not able to choose some of the languages,'.
								' because locales for them are not installed on the web server.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormUser_Layout($data) {
		$this->page->login()->open('zabbix.php?action=user.list');
		$user = CTestArrayHelper::get($data, 'user', 'new');

		if ($user === 'new') {
			$this->query('button:Create user')->one()->click();
		}
		else {
			$this->query('link', $user)->waitUntilVisible()->one()->click();
		}

		$this->page->assertTitle('Configuration of users');
		$this->page->assertHeader('Users');

		// Check tabs available in the form.
		$form = $this->query('name:user_form')->asForm()->one();
		$this->assertEquals(['User', 'Media', 'Permissions'], $form->getTabs());

		// Check default values.
		if ($user === 'Admin') {
			foreach (['id:current_password', 'id:password1', 'id:password2'] as $field) {
				$this->assertFalse($form->query($field)->one(false)->isValid());
			}

			$form->query('button:Change password')->one()->click();
			$this->assertFalse($form->query('button:Change password')->one(false)->isValid());
		}

		$form->checkValue($data['default']);

		foreach ($data['disabled'] as $locator) {
			$field = $form->getField($locator);
			$this->assertTrue($field->isDisplayed());
			$this->assertFalse($field->isEnabled());
		}

		if (array_key_exists('disabled_values', $data)) {
			foreach ($data['disabled_values'] as $element => $value) {
				$this->assertEquals($value, $form->query($element)->one()->getText());
			}
		}
		else {
			$inputs = [
				'Username' => [
					'maxlength' => '100'
				],
				'Name' => [
					'maxlength' => '100'
				],
				'Last name' => [
					'maxlength' => '100'
				],
				'id:user_groups__ms' => [
					'placeholder' => 'type here to search'
				],
				'Password' => [
					'maxlength' => '255'
				],
				'Password (once again)' => [
					'maxlength' => '255'
				],
				'Refresh' => [
					'maxlength' => '32'
				],
				'Rows per page' => [
					'maxlength' => '6'
				],
				'URL (after login)' => [
					'maxlength' => '2048'
				]
			];
			foreach ($inputs as $field => $attributes) {
				$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
			}

			if ($user === 'Admin') {
				$this->assertTrue($form->getField('Current password')->isAttributePresent(['maxlength' => '255']));
			}

			$form->getLabel('Password')->query('xpath:.//button[@data-hintbox]')->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilReady();
			$help_message = "Password requirements:\n".
					"must be at least 8 characters long\n".
					"must not contain user's name, surname or username\n".
					"must not be one of common or context-specific passwords";
			$this->assertEquals($help_message, $hint->one()->getText());
			$hint->query('class:btn-overlay-close')->one()->click();

			$info_message = 'Password is not mandatory for non internal authentication type.';
			$this->assertEquals($info_message, $form->query('xpath:.//div[contains(text(), '.
					CXPathHelper::escapeQuotes($info_message).')]')->one()->getText()
			);
		}

		// Check hintbox contains correct text message.
		foreach ($data['hintbox_warning'] as $field => $text) {
			$form->getField($field)->query('xpath:./..//button[@data-hintbox]')->one()->waitUntilClickable()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilReady()->one();
			$this->assertEquals($text, $hint->getText());
			$hint->close();
		}

		// Check required fields.
		$this->assertEquals($data['required'], $form->getRequiredLabels());

		// Check that buttons are present and clickable.
		$this->assertEquals(count($data['enabled_buttons']), $form->query('button', $data['enabled_buttons'])->all()
				->filter(CElementFilter::CLICKABLE)->count()
		);

		// Check Media tab layout.
		$form->selectTab('Media');
		$this->assertEquals(['Media'], array_values($form->getLabels(CElementFilter::VISIBLE)->asText()));
		$media_tab = $form->query('id:mediaTab')->one();
		$media_table = $media_tab->asTable();

		$this->assertEquals(['Type', 'Send to', 'When active', 'Use if severity', 'Status', 'Actions'],
				$media_table->getHeadersText()
		);

		$add_button = $media_tab->query('button:Add')->one();
		$this->assertTrue($add_button->isClickable());

		// Check that Media tab buttons are present.
		if ($user === 'Admin') {
			$buttons = ['Update', 'Cancel'];
		}
		elseif ($user === 'guest') {
			$buttons = ['Update', 'Delete', 'Cancel'];
		}
		else {
			$buttons = ['Add', 'Cancel'];
		}
		$this->assertEquals(count($buttons), $this->query('class:tfoot-buttons')->one()->query('button', $buttons)->all()
				->filter(CElementFilter::CLICKABLE)->count()
		);

		$add_button->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('Media', $dialog->getTitle());
		$dialog_form = $dialog->asForm();

		$modal_form = [
			'fields' => ['Type', 'Send to', 'When active', 'Use if severity', 'Enabled'],
			'default' => [
				'Type' => 'Reference webhook',
				'Send to' => '',
				'When active' => '1-7,00:00-24:00',
				'id:severity_0' => true,
				'id:severity_1' => true,
				'id:severity_2' => true,
				'id:severity_3' => true,
				'id:severity_4' => true,
				'id:severity_5' => true,
				'Enabled' => true
			],
			'buttons' => ['Add', 'Cancel']
		];
		$this->assertEquals($modal_form['fields'], array_values($dialog_form->getLabels(CElementFilter::VISIBLE)->asText()));
		$dialog_form->checkValue($modal_form['default']);
		$this->assertEquals(2, $dialog->getFooter()->query('button', $modal_form['buttons'])->all()
				->filter(CElementFilter::CLICKABLE)->count()
		);
		$this->assertEquals(['Send to', 'When active'], $dialog_form->getRequiredLabels());
		$dialog->close();

		// Check Permissions tab layout.
		$form->selectTab('Permissions');
		$form->checkValue($data['role']);

		if ($user === 'Admin') {
			$this->assertFalse($form->getField('Role')->asMultiselect()->isEnabled());
			$this->assertFalse($form->isRequired('Role'));
		}
		else {
			$this->assertTrue($form->getField('id:roleid')->isEnabled());
			$this->assertTrue($form->isRequired('Role'));
		}

		if ($data['role'] === '') {
			$this->assertTrue($form->getField('id:roleid_ms')->isAttributePresent(['placeholder' => 'type here to search']));
		}
	}

	public function getCreateData() {
		return [
			// Username is already taken by another user.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Admin',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'User with username "Admin" already exists.'
				]
			],
			// Empty 'Username' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => '',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "username": cannot be empty.'
				]
			],
			// Space as 'Username' field value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => '   ',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "username": cannot be empty.'
				]
			],
			// Empty 'Role' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test1',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot add user',
					'error_details' => 'Field "roleid" is mandatory.'
				]
			],
			// Empty mandatory fields
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => ''
					],
					'error_title' => 'Cannot add user',
					'error_details' => [
						'Incorrect value for field "username": cannot be empty.',
						'Field "roleid" is mandatory.'
					]
				]
			],
			// 'Password' fields not specified.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test2',
						'Groups' => 'Zabbix administrators'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "Password": cannot be empty.'
				]
			],
			// Empty 'Password (once again)' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test3',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// Empty 'Password' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test4',
						'Groups' => 'Zabbix administrators',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// 'Password' and 'Password (once again)' do not match.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test5',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'tEST5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// Empty 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test6',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => ''
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "refresh": cannot be empty.'
				]
			],
			// Digits in value of the 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test7',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '123abc'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// Value of the 'Refresh' field too large.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test8',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '3601'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test_2h',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '2h'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test_61m',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '61m'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			// Non-time unit value in 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test9',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '00000000000001'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// 'Rows per page' field equal to '0'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test10',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Rows per page' => '0'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// Non-numeric value of 'Rows per page' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test11',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Rows per page' => 'abc123'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// 'Autologout' below minimal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test12',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '89'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test12_1m',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '1m'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// 'Autologout' above maximal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test13',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '86401'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test13_1441m',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '1441m'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test13_25h',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '25h'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// 'Autologout' with a non-numeric value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test14',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => 'ninety'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": a time unit is expected.'
				]
			],
			// 'Autologout' with an empty value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test15',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => ''
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "autologout": cannot be empty.'
				]
			],
			// URL unacceptable.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test16',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'URL (after login)' => 'javascript:alert(123);'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// Incorrect URL protocol.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test19',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'URL (after login)' => 'snmp://zabbix.com'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// Creating user by specifying only mandatory parameters.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Mandatory_user',
						'Groups' => 'Guests',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Guest role'
				]
			],
			// Creating a user with optional parameters specified (including autologout) using Cyrillic charatcers.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Оверлорд',
						'Name' => 'Антон Антонович',
						'Last name' => 'Антонов',
						'Groups' => ['Zabbix administrators'],
						'Password' => 'абвгдеЁж',
						'Password (once again)' => 'абвгдеЁж',
						'Theme' => 'High-contrast dark',
						'Auto-login' => false,
						'Refresh' => '0',
						'Rows per page' => '999999',
						'URL (after login)' => 'https://zabbix.com'
					],
					'role' => 'Admin role',
					'check_form' => true
				]
			],
			// Creating a user with punctuation symbols in password and optional parameters specified.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Detailed user',
						'Name' => 'Bugs',
						'Last name' => 'Bunny',
						'Groups' => [
							'Selenium user group in configuration',
							'Zabbix administrators'
						],
						'Password' => '!@#$%^&*()_+',
						'Password (once again)' => '!@#$%^&*()_+',
						'Language' => 'English (en_US)',
						'Theme' => 'Dark',
						'Auto-login' => true,
						'Refresh' => '3600s',
						'Rows per page' => '1',
						'URL (after login)' => 'sysmaps.php'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '1d'
					],
					'role' => 'Admin role',
					'check_form' => true,
					'check_user' => true
				]
			],
			// Creating user without a user group.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'No_usergroup',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Super admin role'
				]
			],
			// Verification that field password is not mandatory for users with LDAP authentication.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'LDAP_user',
						'Groups' => 'LDAP user group'
					],
					'role' => 'Super admin role'
				]
			],
			// Verification that field password is not mandatory for users with no access to frontend.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'No_frontend_user',
						'Groups' => 'No access to the frontend'
					],
					'role' => 'User role'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormUser_Create($data) {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=user.edit');
		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form->fill($data['fields']);

		if (array_key_exists('auto_logout', $data)) {
			$this->setAutoLogout($data['auto_logout']);
		}

		if (array_key_exists('role', $data)) {
			$form->selectTab('Permissions');
			$form->fill(['Role' => $data['role']]);
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Verify that the user was created.
		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['error_title'], $data['error_details']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'User added');
			$this->assertEquals(1, CDBHelper::getCount('SELECT userid FROM users WHERE username='.zbx_dbstr($data['fields']['Username'])));
		}

		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->assertFormFields($data);
		}

		if (CTestArrayHelper::get($data, 'check_user', false)) {
			$this->assertUserParameters($data);
		}
	}

	/**
	 * Check the field values after creating or updating user.
	 */
	private function assertFormFields($data) {
		$userid = CDBHelper::getValue('SELECT userid FROM users WHERE username='.zbx_dbstr($data['fields']['Username']));
		$this->page->open('zabbix.php?action=user.edit&userid='.$userid);
		$form_update = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();

		// Verify that fields are updated.
		$check_fields = ['Username', 'Name', 'Last name', 'Language', 'Theme', 'Refresh', 'Rows per page', 'URL (after login)'];
		foreach ($check_fields as $field_name) {
			if (array_key_exists($field_name, $data['fields'])) {
				$this->assertEquals($data['fields'][$field_name], $form_update->getField($field_name)->getValue());
			}
		}

		$this->assertEquals($data['fields']['Groups'], $form_update->getField('Groups')->getSelected());

		if (CTestArrayHelper::get($data, 'auto_logout.checked', false)) {
			$this->assertTrue($form_update->getField('Auto-login')->isChecked(false));
		}
		else {
			$this->assertTrue($form_update->getField('Auto-login')->isChecked($data['fields']['Auto-login']));
		}

		if (array_key_exists('role', $data)) {
			$form_update->selectTab('Permissions');
			$this->assertEquals([$data['role']], $form_update->getField('Role')->getSelected());
		}
	}

	/**
	 * Login as user and check user profile parameters in UI.
	 */
	private function assertUserParameters($data) {
		try {
			$this->page->logout();
			// Log in with the created or updated user.
			$password = CTestArrayHelper::get($data['fields'], 'Password', $data['fields']['Password'] = self::UPDATE_PASSWORD);
			$this->page->userLogin($data['fields']['Username'], $password);
			// Verification of URL after login.
			$this->assertStringContainsString($data['fields']['URL (after login)'], $this->page->getCurrentURL());
			// Verification of the number of rows per page parameter.
			$rows = $this->query('name:frm_maps')->asTable()->waitUntilVisible()->one()->getRows();
			$this->assertEquals($data['fields']['Rows per page'], $rows->count());

			// Verification of default theme.
			$db_theme = CDBHelper::getValue('SELECT theme FROM users WHERE username='.zbx_dbstr($data['fields']['Username']));
			$color = $this->query('tag:body')->one()->getCSSValue('background-color');
			$stylesheet = $this->query('xpath://link[@rel="stylesheet"]')->one();
			$parts = explode('/', $stylesheet->getAttribute('href'));
			$file_time = explode('?', end($parts));
			$file = $file_time[0];

			if ($data['fields']['Theme'] === 'Dark') {
				$this->assertEquals('dark-theme', $db_theme);
				$this->assertEquals('dark-theme.css', $file);
				$this->assertEquals('rgba(14, 16, 18, 1)', $color);
			}
			else if ($data['fields']['Theme'] === 'High-contrast light') {
				$this->assertEquals('hc-light', $db_theme);
				$this->assertEquals('hc-light.css', $file);
				$this->assertEquals('rgba(255, 255, 255, 1)', $color);
			}

			$this->page->logout();
		}
		catch (Exception $e) {
			$this->page->logout();
			throw $e;
		}
	}

	public function getUpdateData() {
		return [
			// #0 Incorrect current password.
			[
				[
					'expected' => TEST_BAD,
					'user_to_update' => 'Admin',
					'fields' => [
						'Current password' => 'azazabbix',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect current password.'
				]
			],
			// #1 Current password with spaces.
			[
				[
					'expected' => TEST_BAD,
					'user_to_update' => 'Admin',
					'fields' => [
						'Current password' => ' zabbix ',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect current password.'
				]
			],
			// #2 Empty current password.
			[
				[
					'expected' => TEST_BAD,
					'user_to_update' => 'Admin',
					'fields' => [
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "Current password": cannot be empty'
				]
			],
			// #3 Empty password fields for user without user group.
			[
				[
					'expected' => TEST_BAD,
					'user_to_update' => self::ZABBIX_LDAP_USER,
					'fields' => [
						'Password' => '',
						'Password (once again)' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "Password": cannot be empty'
				]
			],
			// #4 Username is already taken by another user.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Admin'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'User with username "Admin" already exists.'
				]
			],
			// #5 Empty 'Username' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "username": cannot be empty.'
				]
			],
			// #6 Empty 'Password (once again)' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Password' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// #7 Empty 'Password' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// #8 LDAP user with empty repeated password.
			[
				[
					'expected' => TEST_BAD,
					'user_to_update' => 'LDAP user',
					'fields' => [
						'Password' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// #9 Updating user from "No access to the frontend" group without filling in password.
			[
				[
					'expected' => TEST_BAD,
					'user_to_update' => 'no-access-to-the-frontend',
					'fields' => [
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// #10 'Password' and 'Password (once again)' do not match.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'teST5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// #11 Empty 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "refresh": cannot be empty.'
				]
			],
			// #12 Digits in value of the 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '123abc'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// #13 Value of the 'Refresh' field too large.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '3601'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			// #14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '61m'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			// #15 Non time unit value in 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '00000000000001'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// #16 'Rows per page' field equal to '0'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Rows per page' => '0'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// #17 Non-numeric value of 'Rows per page' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Rows per page' => 'abc123'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// #18 'Autologout' below minimal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '89'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// #19 'Autologout' above maximal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '86401'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// #20.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '1m'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// #21.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '1441m'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '25h'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// #23 'Autologout' with a non-numeric value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => 'ninety'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": a time unit is expected.'
				]
			],
			// #24 'Autologout' with an empty value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "autologout": cannot be empty.'
				]
			],
			// #25 URL unacceptable.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL (after login)' => 'javascript:alert(123);'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// #26 Incorrect URL protocol.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL (after login)' => 'snmp://zabbix.com'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// #27 Updating LDAP user with empty password fields.
			[
				[
					'expected' => TEST_GOOD,
					'user_to_update' => 'LDAP user',
					'fields' => [
						'Username' => 'LDAP user updated',
						'Password' => '',
						'Password (once again)' => ''
					]
				]
			],
			// #28 Updating user from "No access to the frontend" group using empty password fields.
			[
				[
					'expected' => TEST_GOOD,
					'user_to_update' => 'no-access-to-the-frontend',
					'fields' => [
						'Username' => 'no-access-to-the-frontend-updated',
						'Password' => '',
						'Password (once again)' => ''
					]
				]
			],
			// #29 Updating user from "LDAP" group.
			[
				[
					'expected' => TEST_GOOD,
					'user_to_update' => 'LDAP change password button check',
					'fields' => [
						'Username' => 'LDAP change password button check - updated'
					]
				]
			],
			// #30 Updating all fields (except password) of an existing user.
			[
				[
					'expected' => TEST_GOOD,
					'user_to_update' => 'disabled-user',
					'fields' => [
						'Username' => 'Updated_user_1',
						'Name' => 'Test_Name',
						'Last name' => 'Test_Surname',
						'Groups' => [
							'Selenium user group in configuration'
						],
						'Language' => 'English (en_US)',
						'Theme' => 'Dark',
						'Auto-login' => true,
						'Refresh' => '60m',
						'Rows per page' => '1',
						'URL (after login)' => 'sysmaps.php'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '24h'
					],
					'check_form' => true
				]
			],
			// #31.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Updated_user',
						'Name' => 'Road',
						'Last name' => 'Runner',
						'Groups' => [],
						'Language' => 'English (en_US)',
						'Theme' => 'High-contrast light',
						'Auto-login' => true,
						'Refresh' => '1h',
						'Rows per page' => '1',
						'URL (after login)' => 'sysmaps.php'
					],
					'check_form' => true,
					'check_user' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 */
	public function testFormUser_Update($data) {
		$update_user = CTestArrayHelper::get($data, 'user_to_update', self::UPDATE_USER);

		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $update_user)->waitUntilVisible()->one()->click();

		// Update user parameters.
		$form = $this->query('name:user_form')->asForm()->one();

		if (array_key_exists('Password', $data['fields']) || array_key_exists('Password (once again)', $data['fields'])) {
			$form->query('button:Change password')->one()->click();
		}

		if ($update_user === 'LDAP change password button check') {
			$this->assertFalse($form->query('button:Change password')->one()->isClickable());
			$hintbox = 'Password can only be changed for users using the internal Zabbix authentication.';
			$this->assertEquals($hintbox, $this->query('xpath://button[contains(@data-hintbox-contents, '.
					CXPathHelper::escapeQuotes($hintbox).')]')->one()->getAttribute('data-hintbox-contents')
			);
		}

		if ($update_user === 'Admin') {
			$this->assertTrue($form->query('id:current_password')->one()->isVisible());
		}
		else {
			$this->assertFalse($form->query('id:current_password')->one(false)->isValid());
		}

		$form->fill($data['fields']);

		if (array_key_exists('auto_logout', $data)) {
			$this->setAutoLogout($data['auto_logout']);
		}

		$form->submit();

		if (array_key_exists('Password', $data['fields']) && array_key_exists('Password (once again)', $data['fields'])) {
			if ($update_user === 'LDAP user' || $update_user === 'no-access-to-the-frontend' || $update_user === self::ZABBIX_LDAP_USER) {
				$this->assertFalse($this->page->isAlertPresent());
			}
			else {
				$this->assertTrue($this->page->isAlertPresent());
				$this->assertEquals('In case of successful password change user will be logged out of all active sessions. Continue?',
					$this->page->getAlertText()
				);
				$this->page->acceptAlert();
			}
		}

		$this->page->waitUntilReady();

		// Verify if the user was updated.
		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['error_title'], $data['error_details']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'User updated');
			$this->assertEquals(1, CDBHelper::getCount('SELECT userid FROM users WHERE username='.zbx_dbstr($data['fields']['Username'])));
		}

		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->assertFormFields($data);
		}

		if (CTestArrayHelper::get($data, 'check_user', false)) {
			$this->assertUserParameters($data);
		}
	}

	/**
	 * Test update without any modification of user data.
	 */
	public function testFormUser_SimpleUpdate() {
		$sql_hash = 'SELECT * FROM users ORDER BY userid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', 'test-user')->waitUntilVisible()->one()->click();

		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form->submit();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('User updated', $message->getTitle());

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	public function getPasswordUpdateData() {
		return [
			[
				[
					'username' => 'user-zabbix',
					'old_password' => 'test5678',
					'new_password' => 'test5678_new',
					'error_message' => 'Incorrect user name or password or account is temporarily blocked.',
					'attempt_message' => '1 failed login attempt logged. Last failed attempt was from'
				]
			],
			[
				[
					'username' => 'Admin',
					'old_password' => 'zabbix',
					'new_password' => 'test6789_new',
					'error_message' => 'Incorrect user name or password or account is temporarily blocked.',
					'attempt_message' => '1 failed login attempt logged. Last failed attempt was from'
				]
			]
		];
	}

	/**
	 * @dataProvider getPasswordUpdateData
	 *
	 * Test user password change and sign in with new password.
	 */
	public function testFormUser_PasswordUpdate($data) {
		$update_user = CTestArrayHelper::get($data, 'username', 'Admin');
		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $update_user)->waitUntilVisible()->one()->click();
		$form_update = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form_update->query('button:Change password')->one()->click();

		// Change user password and log out.
		if ($update_user === 'Admin') {
			$form_update->fill(['Current password' => $data['old_password']]);
		}

		$form_update->fill([
			'Password' => $data['new_password'],
			'Password (once again)' => $data['new_password']
		]);
		$form_update->submit();

		$this->assertTrue($this->page->isAlertPresent());
		$this->assertEquals('In case of successful password change user will be logged out of all active sessions. Continue?',
				$this->page->getAlertText()
		);
		$this->page->acceptAlert();

		try {
			$this->page->logout();

			// Attempt to sign in with old password.
			$this->page->userLogin($data['username'], $data['old_password'], TEST_BAD);
			$message = $this->query('class:red')->one()->getText();
			$this->assertEquals($message, $data['error_message']);

			// Sign in with new password.
			$this->page->userLogin($data['username'], $data['new_password']);
			$attempt_message = CMessageElement::find()->one();
			$this->assertTrue($attempt_message->hasLine($data['attempt_message']));
			$this->page->logout();
		}
		catch (\Exception $e) {
			// Logout to execute remaining tests.
			$this->page->logout();
			throw $e;
		}
	}

	public function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => self::ZABBIX_LDAP_USER
					]
				]
			],
			// Attempt to delete internal user guest.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'guest',
					'error_details' => 'Cannot delete Zabbix internal user "guest", try disabling that user.'
				]
			],
			// Attempt to delete a user that owns a map.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'user-zabbix',
					'parameters' => [
						'DB_table' => 'sysmaps',
						'column' => 'name',
						'value' => 'Local network'
					],
					'error_details' => 'User "user-zabbix" is map "Local network" owner.'
				]
			],
			// Attempt to delete a user that owns a dashboard.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'test-timezone',
					'error_details' => 'User "test-timezone" is dashboard "Testing share dashboard" owner.'
				]
			],
			// Attempt to delete a user that is mentioned in an action.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'user-for-blocking',
					'error_details' => 'User "user-for-blocking" is used in "Action with user" action.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormUser_Delete($data) {
		// Defined required variables.
		if (array_key_exists('username', $data)) {
			$username = $data['username'];
		}
		else {
			$username = $data['fields']['Username'];
		}

		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $username)->one()->click();
		$userid = CDBHelper::getValue('SELECT userid FROM users WHERE username='.zbx_dbstr($username));

		// Link user with map, action to validate user deletion.
		if (array_key_exists('parameters', $data)) {
			DBexecute(
					'UPDATE '.$data['parameters']['DB_table'].' SET userid ='.zbx_dbstr($userid).
					' WHERE '.$data['parameters']['column'].'='.zbx_dbstr($data['parameters']['value'])
			);
		}

		// Attempt to delete the user from user update view and verify result.
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Validate if the user was deleted.
		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot delete user', $data['error_details']);
			$this->assertEquals(1, CDBHelper::getCount('SELECT userid FROM users WHERE username='.zbx_dbstr($username)));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'User deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT userid FROM users WHERE username='.zbx_dbstr($data['fields']['Username'])));
		}
	}

	/**
	 * Check that user can't delete oneself.
	 */
	public function testFormUser_SelfDeletion() {
		$this->page->login()->open('zabbix.php?action=user.edit&userid=1');
		$this->assertTrue($this->query('button:Delete')->waitUntilVisible()->one()->isEnabled(false));
	}

	public function testFormUser_Cancel() {
		$data = [
			'Username' => 'user-cancel',
			'Password' => 'zabbix',
			'Password (once again)' => 'zabbix',
			'Groups' => 'Guests'
		];
		$sql_users = 'SELECT * FROM users ORDER BY userid';
		$user_hash = CDBHelper::getHash($sql_users);
		$this->page->login()->open('zabbix.php?action=user.edit');

		// Check cancellation when creating users.
		$form_create = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form_create->fill($data);
		$this->query('button:Cancel')->one()->click();
		$cancel_url = $this->page->getCurrentURL();
		$this->assertStringContainsString('zabbix.php?action=user.list', $cancel_url);
		$this->assertEquals($user_hash, CDBHelper::getHash($sql_users));

		// Check Cancellation when updating users.
		$this->page->open('zabbix.php?action=user.edit&userid=1');
		$this->query('id:name')->one()->fill('Boris');
		$this->query('button:Cancel')->one()->click();
		$this->assertEquals($user_hash, CDBHelper::getHash($sql_users));
	}

	private function setAutoLogout($data) {
		$form = $this->query('name:user_form')->asForm()->one();
		$auto_logout = $form->getFieldContainer('Auto-logout');
		$auto_logout->query('id:autologout_visible')->asCheckbox()->one()->set($data['checked']);
		if (array_key_exists('value', $data)) {
			$auto_logout->query('id:autologout')->one()->overwrite($data['value']);
		}
		// Verify that Auto-login is unchecked after setting Auto-logout.
		$this->assertTrue($form->getField('Auto-login')->isChecked(false));
	}
}
