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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup config, userdirectory, usrgrp
 *
 * @dataSource LoginUsers
 */
class testUsersAuthenticationLdap extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function testUsersAuthenticationLdap_Layout() {
		$form = $this->openLdapForm();
		$this->page->assertHeader('Authentication');
		$this->page->assertTitle('Configuration of authentication');
		$this->assertTrue($form->getField('Enable LDAP authentication')->isEnabled());

		// Check that Update button is clickable and no other buttons present.
		$this->assertTrue($form->query('button:Update')->one()->isClickable());
		$this->assertEquals(1, $form->query('xpath:.//ul[@class="table-forms"]//button')->all()->count());

		// Check LDAP form default values.
		$form->checkValue([
			'Enable LDAP authentication' => false,
			'Enable JIT provisioning' => false,
			'Case-sensitive login' => true,
			'Provisioning period' => '1h'
		]);

		// Check LDAP form fields editability.
		foreach ([false, true] as $status) {
			$form->fill(['Enable LDAP authentication' => $status]);

			foreach (['Enable JIT provisioning', 'Servers', 'Case-sensitive login'] as $label) {
				$this->assertTrue($form->getField($label)->isEnabled($status));
			}
		}

		$this->assertEquals(['Servers'], $form->getRequiredLabels());

		// Check server table's headers.
		$server_table = [
			'Servers' => [
				'id' => 'ldap-servers',
				'headers' => ['Name', 'Host', 'User groups', 'Default', '']
			]
		];

		$this->checkTablesHeaders($server_table, $form);

		// Check 'Provisioning period' field's editability.
		foreach ([false, true] as $jit_status) {
			$form->fill(['Enable JIT provisioning' => $jit_status]);
			$this->assertTrue($form->getField('Provisioning period')->isEnabled($jit_status));
		}

		// Check default server popup fields.
		$form->getFieldContainer('Servers')->query('button:Add')->waitUntilClickable()->one()->click();
		$server_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('New LDAP server', $server_dialog->getTitle());
		$server_form = $server_dialog->asForm();

		$server_fields = [
			'Name' => ['visible' => true, 'maxlength' => 128, 'value' => ''],
			'Host' => ['visible' => true, 'maxlength' => 255, 'value' => ''],
			'Port' => ['visible' => true, 'maxlength' => 5, 'value' => 389],
			'Base DN' => ['visible' => true, 'maxlength' => 255, 'value' => ''],
			'Search attribute' => ['visible' => true, 'maxlength' => 128, 'value' => ''],
			'Bind DN' => ['visible' => true, 'maxlength' => 255, 'value' => ''],
			'Bind password' => ['visible' => true, 'maxlength' => 128, 'value' => ''],
			'Description' => ['visible' => true, 'maxlength' => 65535, 'value' => ''],
			'Configure JIT provisioning' => ['visible' => true, 'value' => false],
			'Advanced configuration' => ['visible' => true, 'value' => false],
			'Group configuration' => ['visible' => false, 'value' => 'memberOf'],
			'Group base DN' => ['visible' => false, 'maxlength' => 255, 'value' => ''],
			'Group name attribute' => ['visible' => false, 'maxlength' => 255, 'value' => ''],
			'Group member attribute' => ['visible' => false, 'maxlength' => 255, 'value' => ''],
			'Reference attribute' => ['visible' => false, 'maxlength' => 255, 'value' => ''],
			'Group filter' => ['visible' => false, 'maxlength' => 255, 'value' => '', 'placeholder' => '(%{groupattr}=%{user})'],
			'User group membership attribute' => ['visible' => false, 'maxlength' => 255, 'value' => '', 'placeholder' => 'memberOf'],
			'User name attribute' => ['visible' => false, 'maxlength' => 255, 'value' => ''],
			'User last name attribute' => ['visible' => false, 'maxlength' => 255, 'value' => ''],
			'User group mapping' => ['visible' => false],
			'Media type mapping' => ['visible' => false ],
			'StartTLS' => ['visible'  => false, 'value' => false],
			'Search filter' => ['visible' => false, 'maxlength' => 255, 'value' => '', 'placeholder' => '(%{attr}=%{user})']
		];

		foreach ($server_fields as $field => $attributes) {
			$this->assertEquals($attributes['visible'], $server_form->getField($field)->isVisible());
			$this->assertTrue($server_form->getField($field)->isEnabled());

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $server_form->getField($field)->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $server_form->getField($field)->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $server_form->getField($field)->getAttribute('placeholder'));
			}
		}

		// Check visible mandatory fields.
		$this->assertEquals(['Name', 'Host', 'Port', 'Base DN', 'Search attribute'],
				$server_form->getRequiredLabels()
		);

		// Check invisible mandatory field.
		$server_form->isRequired('User group mapping');

		// Check JIT fields (memberOf).
		$server_form->fill(['Configure JIT provisioning' => true]);
		$server_form->query('xpath:.//label[text()="Group configuration"]')->waitUntilVisible();

		$jit_fields_memberOf = [
			'Group base DN' => false,
			'Group name attribute' => true,
			'Group member attribute' => false,
			'Reference attribute' => false,
			'Group filter' => false,
			'User group membership attribute' => true,
			'User name attribute' => true,
			'User last name attribute' => true,
			'User group mapping' => true,
			'Media type mapping' => true
		];

		foreach ($jit_fields_memberOf as $field => $visible) {
			$this->assertEquals($visible, $server_form->getField($field)->isVisible());
			$this->assertTrue($server_form->getField($field)->isEnabled());
		}

		// Check JIT fields (groupOfNames).
		$server_form->fill(['Group configuration' => 'groupOfNames']);
		$server_form->query('xpath:.//label[text()="Group base DN"]')->waitUntilVisible();

		$jit_fields_groupOfNames = [
			'Group base DN' => true,
			'Group name attribute' => true,
			'Group member attribute' => true,
			'Reference attribute' => true,
			'Group filter' => true,
			'User group membership attribute' => false,
			'User name attribute' => true,
			'User last name attribute' => true,
			'User group mapping' => true,
			'Media type mapping' => true
		];

		foreach ($jit_fields_groupOfNames as $field => $visible) {
			$this->assertEquals($visible, $server_form->getField($field)->isVisible());
			$this->assertTrue($server_form->getField($field)->isEnabled());
		}

		// Check Advanced fields.
		$server_form->fill(['Advanced configuration' => true]);
		$server_form->query('xpath:.//label[text()="StartTLS"]')->waitUntilVisible()->one();
		$this->assertTrue($server_form->getField('Search filter')->isVisible());

		// Open hintboxes and compare text.
		$hintboxes = [
			'Group configuration' => 'memberOf is a preferable way to configure groups because it is faster. '.
					'Use groupOfNames if your LDAP server does not support memberOf or group filtering is required.',
			'Reference attribute' => 'Use %{ref} in group filter to reference value of this user attribute.',
			'Media type mapping' => "Map user's LDAP media attributes (e.g. email) to Zabbix user media for sending".
					" notifications."
		];

		$this->checkHints($hintboxes, $server_form);

		// Check mapping tables headers.
		$mapping_tables = [
			'User group mapping' => [
				'id' => 'ldap-user-groups-table',
				'headers' => ['LDAP group pattern', 'User groups', 'User role', 'Action']
			],
			'Media type mapping' => [
				'id' => 'ldap-media-type-mapping-table',
				'headers' => ['Name', 'Media type', 'Attribute', 'Action']
			]
		];

		$this->checkTablesHeaders($mapping_tables, $server_form);

		// Check group mapping popup.
		$group_mapping_dialog = $this->checkMappingDialog('User group mapping', 'New user group mapping', $server_form,
				['LDAP group pattern', 'User groups', 'User role']
		);

		// Check hint in group mapping popup.
		$this->checkHints(['LDAP group pattern' => "Naming requirements:\ngroup name must match LDAP group name".
				"\nwildcard patterns with '*' may be used"], $group_mapping_dialog->asForm()
		);

		// Check Groups mapping footer buttons.
		$this->checkFooterButtons($group_mapping_dialog, ['Add', 'Cancel']);

		// Close Group mapping dialog.
		$group_mapping_dialog->getFooter()->query('button:Cancel')->waitUntilClickable()->one()->click();

		// Check media type mapping popup.
		$media_mapping_dialog = $this->checkMappingDialog('Media type mapping', 'New media type mapping',
				$server_form, ['Name', 'Media type', 'Attribute']
		);

		// Check Media mapping footer buttons.
		$this->checkFooterButtons($media_mapping_dialog, ['Add', 'Cancel']);

		// Close Media mapping dialog.
		$media_mapping_dialog->getFooter()->query('button:Cancel')->waitUntilClickable()->one()->click();

		// Check footer buttons.
		$this->checkFooterButtons($server_dialog, ['Add', 'Test', 'Cancel']);

		$server_dialog->close();
	}

	/**
	 * Check buttons in dialog footer.
	 *
	 * @param array           $tables    given tables
	 * @param CFormElement    $form      given form
	 */
	private function checkTablesHeaders($tables, $form) {
		foreach ($tables as $name => $attributes) {
			$this->assertEquals($attributes['headers'], $form->getFieldContainer($name)
					->query('id', $attributes['id'])->asTable()->waitUntilVisible()->one()->getHeadersText()
			);
		}
	}

	/**
	 * Check buttons in dialog footer.
	 *
	 * @param COverlayDialogElement    $dialog     given dialog
	 * @param array                    $buttons    checked buttons array
	 */
	private function checkFooterButtons($dialog, $buttons) {
		$footer = $dialog->getFooter();

		// Check that there are correct buttons count in the footer.
		$this->assertEquals(count($buttons), $footer->query('xpath:.//button')->all()->count());

		// Check that all footer buttons are clickable.
		$this->assertEquals(count($buttons), $footer->query('button', $buttons)->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
	}

	/**
	 * Check mapping dialog contents.
	 *
	 * @param string          $field	 field which mapping is checked
	 * @param string          $title     title in dialog
	 * @param CFormElement    $form      LDAP form
	 * @param array           $labels    labels in mapping form
	 */
	private function checkMappingDialog($field, $title, $form, $labels) {
		$form->getFieldContainer($field)->query('button:Add')->waitUntilClickable()->one()->click();
		$mapping_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals($title, $mapping_dialog->getTitle());
		$mapping_form = $mapping_dialog->asForm();

		foreach ($labels as $label) {
			$mapping_field = $mapping_form->getField($label);
			$this->assertTrue($mapping_field->isVisible());
			$this->assertTrue($mapping_field->isEnabled());
			$this->assertEquals($labels, $mapping_form->getRequiredLabels());
		}

		$values = ($field === 'Media type mapping')
			? ['Name' => '', 'Media type' => 'Brevis.one', 'Attribute' => '']
			: ['LDAP group pattern' => '', 'User groups' => '', 'User role' => ''];

		$mapping_form->checkValue($values);

		// Check group mapping popup footer buttons.
		$this->assertTrue($mapping_dialog->getFooter()->query('button:Add')->one()->isClickable());

		return $mapping_dialog;
	}

	/**
	 * Check hints for labels in form.
	 *
	 * @param array			   $hintboxes	label which hint is checked
	 * @param CFormElement     $form        given form
	 */
	private function checkHints($hintboxes, $form) {
		foreach ($hintboxes as $field => $text) {
			$form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($field).']/a')->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent()->all()->last();
			$this->assertEquals($text, $hint->getText());
			$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
		}
	}

	public function getTestData() {
		return [
			// #0 test without Host, Base DN and Search attribute.
			[
				[
					'servers_settings' => [],
					'test_error' => 'Invalid LDAP configuration',
					'test_error_details' => [
						'Incorrect value for field "host": cannot be empty.',
						'Incorrect value for field "base_dn": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					]
				]
			],
			// #1 test without Base DN and Search attribute.
			[
				[
					'servers_settings' => [
						'Host' => 'ldap.forumsys.com'
					],
					'test_error' => 'Invalid LDAP configuration',
					'test_error_details' => [
						'Incorrect value for field "base_dn": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					]
				]
			],
			// #2 test without Search attribute.
			[
				[
					'servers_settings' => [
						'Host' => 'ldap.forumsys.com',
						'Base DN' => 'dc=example,dc=com'
					],
					'test_error' => 'Invalid LDAP configuration',
					'test_error_details' => [
						'Incorrect value for field "search_attribute": cannot be empty.'
					]
				]
			],
			// #3 test with empty credentials.
			[
				[
					'servers_settings' => [
						'Host' => 'ldap.forumsys.com',
						'Base DN' => 'dc=example,dc=com',
						'Search attribute' => 'uid'
					],
					'test_settings' => [
						'Login' => '',
						'User password' => ''
					],
					'test_error' => 'Invalid LDAP configuration',
					'test_error_details' => [
						'Incorrect value for field "test_username": cannot be empty.',
						'Incorrect value for field "test_password": cannot be empty.'
					]
				]
			],
			// #4 test with empty password field.
			[
				[
					'servers_settings' => [
						'Host' => 'ldap.forumsys.com',
						'Base DN' => 'dc=example,dc=com',
						'Search attribute' => 'uid'
					],
					'test_settings' => [
						'Login' => 'galieleo',
						'User password' => ''
					],
					'test_error' => 'Invalid LDAP configuration',
					'test_error_details' => [
						'Incorrect value for field "test_password": cannot be empty.'
					]
				]
			],
			// #5 test with empty username field.
			[
				[
					'servers_settings' => [
						'Host' => 'ldap.forumsys.com',
						'Base DN' => 'dc=example,dc=com',
						'Search attribute' => 'uid'
					],
					'test_settings' => [
						'Login' => '',
						'User password' => 'password'
					],
					'test_error' => 'Invalid LDAP configuration',
					'test_error_details' => [
						'Incorrect value for field "test_username": cannot be empty.'
					]
				]
			],
			// #6 test with incorrect username and password values.
			[
				[
					'servers_settings' => [
						'Host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid'
					],
					'test_settings' => [
						'Login' => 'test',
						'User password' => 'test'
					],
					'test_error' => 'Login failed',
					'test_error_details' => [
						'Incorrect user name or password or account is temporarily blocked.'
					]
				]
			],
			// #7 test with incorrect LDAP settings.
			[
				[
					'servers_settings' => [
						'Host' => 'test',
						'Base DN' => 'test',
						'Search attribute' => 'test'
					],
					'test_settings' => [
						'Login' => 'test',
						'User password' => 'test'
					],
					'test_error' => 'Login failed',
					'test_error_details' => [
						'Cannot bind anonymously to LDAP server.'
					]
				]
			],
			// #8 test with all available values.
			[
				[
					'servers_settings' => [
						'Name' => 'Test Name',
						'Host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid',
						'Bind DN' => 'test_DN',
						'Bind password' => 'test_password',
						'Description' => 'Test description',
						'Advanced configuration' => true,
						'StartTLS' => true,
						'Search filter' => 'filter'
					],
					'test_settings' => [
						'Login' => 'employee',
						'User password' => 'Secret123'
					],
					'test_error' => 'Login failed',
					'test_error_details' => [
						'Starting TLS failed.'
					]
				]
			],
			// #9 test with Bind DN and Bind password.
			[
				[
					'servers_settings' => [
						'Name' => 'Test Name',
						'Host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid',
						'Bind DN' => 'test_DN',
						'Bind password' => 'test_password',
						'Description' => 'Test description'
					],
					'test_settings' => [
						'Login' => 'employee',
						'User password' => 'Secret123'
					],
					'test_error' => 'Login failed',
					'test_error_details' => [
						'Cannot bind to LDAP server.'
					]
				]
			],
			// #10 test with correct LDAP settings and JIT settings.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						'Host' => 'dc-ad-srv.zabbix.sandbox',
						'Base DN' => 'DC=zbx,DC=local',
						'Search attribute' => 'sAMAccountName',
						'Bind DN' => 'CN=Admin,OU=Users,OU=Zabbix,DC=zbx,DC=local',
						'Bind password' => 'zabbix#33',
						'Configure JIT provisioning' => true,
						'Group configuration' => 'memberOf',
						'Group name attribute' => 'CN',
						'User group membership attribute' => 'memberof',
						'User name attribute' => 'mail'
					],
					'User group mapping' => [
						[
							'LDAP group pattern' => 'Zabbix admins',
							'User groups' => 'Zabbix administrators',
							'User role' => 'Super admin role'
						],
						[
							'LDAP group pattern' => 'Zabbix users',
							'User groups' => 'Guests',
							'User role' => 'Guest role'
						]
					],
					'Media type mapping' => [
						[
							'Name' => 'Email',
							'Media type' => 'Email',
							'Attribute' => 'mail'
						]
					],
					'test_settings' => [
						'Login' => 'user1',
						'User password' => 'zabbix#33'
					],
					'check_provisioning' => [
						'role' => 'Super admin role',
						'groups' => 'Zabbix administratorsGuests',
						'medias' => 'Email'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getTestData
	 *
	 * Test LDAP settings.
	 */
	public function testUsersAuthenticationLdap_Test($data) {
		$form = $this->openLdapForm();
		$form->fill(['Enable LDAP authentication' => true]);
		$form->query('button:Add')->waitUntilCLickable()->one()->click();
		$server_form_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$server_form = $server_form_dialog->asForm();
		$server_form->fill($data['servers_settings']);

		if (CTestArrayHelper::get($data['servers_settings'], 'Configure JIT provisioning')) {
			if (array_key_exists('User group mapping', $data)) {
				$this->setMapping($data['User group mapping'], $server_form, 'User group mapping');
			}

			if (array_key_exists('Media type mapping', $data)) {
				$this->setMapping($data['Media type mapping'], $server_form, 'Media type mapping');
			}
		}

		$this->query('button:Test')->waitUntilClickable()->one()->click();
		$test_form_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();

		// Fill login and user password in Test authentication form.
		if (array_key_exists('test_settings', $data)) {
			$test_form = $test_form_dialog->asForm();
			$test_form->fill($data['test_settings'])->submit()->waitUntilReady();
		}

		// Check error messages testing LDAP settings.
		if (CTestArrayHelper::get($data, 'expected', TEST_BAD) === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Login successful');
		}
		else {
			$this->assertMessage(TEST_BAD, $data['test_error'], $data['test_error_details']);
		}

		if (array_key_exists('check_provisioning', $data)) {
			foreach ($data['check_provisioning'] as $id => $text) {
				$this->assertEquals($text, $test_form_dialog->query('id:provisioning_'.$id)->waitUntilVisible()->one()->getText());
			}
		}

		$test_form_dialog->query('button:Cancel')->waitUntilClickable()->one()->click();
		$server_form_dialog->close();
	}

	/**
	 * Check that remove button works.
	 */
	public function testUsersAuthenticationLdap_Remove() {
		$form = $this->openLdapForm();
		$table = $form->query('id:ldap-servers')->asTable()->one();

		// Add new LDAP server if it is not present.
		if ($table->getRows()->count() === 0) {
			$this->setLdap([], 'button:Add', 'atest');
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$form->selectTab('LDAP settings');
		}

		// Check headers.
		$this->assertEquals(['Name', 'Host', 'User groups', 'Default', ''], $table->getHeadersText());

		// Check that LDAP server added in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM userdirectory_ldap'));

		// Click on remove button and check that LDAP server NOT removed from DB.
		$table->query('button:Remove')->one()->click();
		$this->query('id:ldap_auth_enabled')->asCheckbox()->one()->set(false);
		$this->assertEquals(1, CDBHelper::getCount('SELECT 1 FROM userdirectory_ldap'));

		// Submit changes and check that LDAP server removed.
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM userdirectory_ldap'));
	}

	/**
	 * Check default LDAP server change.
	 */
	public function testUsersAuthenticationLdap_Default() {
		$form = $this->openLdapForm();
		$this->page->assertHeader('Authentication');
		$this->page->assertTitle('Configuration of authentication');

		$table = $form->query('id:ldap-servers')->asTable()->one();

		// To check default we need at least 2 LDAP servers.
		for ($i = 0; $i <=1; $i++) {
			if ($table->getRows()->count() >= 2) {
				break;
			}

			$this->setLdap([], 'button:Add', 'test_'.$i);
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$form->selectTab('LDAP settings');
		}

		foreach ($table->getRows() as $row) {
			$radio = $row->getColumn('Default');
			$user_directoryid = CDBHelper::getValue('SELECT userdirectoryid FROM userdirectory_ldap WHERE host='
					.zbx_dbstr($row->getColumn('Host')->getText())
			);

			// Check if LDAP server is set as Default.
			if ($radio->query('name:ldap_default_row_index')->one()->isAttributePresent('checked') === true) {
				$this->assertEquals($user_directoryid, CDBHelper::getValue('SELECT ldap_userdirectoryid FROM config'));
			}
			else {
				// Set another LDAP server as default.
				$this->assertNotEquals($user_directoryid, CDBHelper::getValue('SELECT ldap_userdirectoryid FROM config'));
				$radio->query('name:ldap_default_row_index')->one()->click();
				$form->submit();
				$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
				$this->assertEquals($user_directoryid, CDBHelper::getValue('SELECT ldap_userdirectoryid FROM config'));
			}
		}

		// Default LDAP server host name.
		$hostname = CDBHelper::getValue('SELECT host FROM userdirectory_ldap WHERE userdirectoryid IN '.
				'(SELECT ldap_userdirectoryid FROM config)'
		);

		$form->selectTab('LDAP settings');

		// Find default LDAP server, delete it and check that another LDAP server set as default.
		$table->findRow('Host', $hostname)->getColumn('')->query('button:Remove')->one()->click();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$new_hostname = CDBHelper::getValue('SELECT host FROM userdirectory_ldap udl INNER JOIN config co ON '.
				'udl.userdirectoryid = co.ldap_userdirectoryid');

		// Check that old LDAP server (by host name) is not default now.
		$this->assertNotEquals($hostname, $new_hostname);
	}

	public function getUpdateData() {
		return [
			// 0.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '',
								'Host' => '',
								'Base DN' => '',
								'Port' => '',
								'Search attribute' => ''
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "host": cannot be empty.',
						'Incorrect value for field "base_dn": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					]
				]
			],
			// 1.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '',
								'Host' => 'updated_host',
								'Base DN' => '',
								'Search attribute' => ''
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "base_dn": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					]
				]
			],
			// 2.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '',
								'Host' => 'updated_host',
								'Base DN' => 'updated_dn',
								'Search attribute' => ''
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					]
				]
			],
			// 3.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '',
								'Host' => 'updated_host',
								'Base DN' => 'updated_dn',
								'Search attribute' => 'updated_search'
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.'
					]
				]
			],
			// 4.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'updated_name',
								'Host' => 'updated_host',
								'Port' => '777',
								'Base DN' => 'updated_dn',
								'Search attribute' => 'updated_search',
								'Bind DN' => 'updated_bin_dn',
								'Description' => 'updated_description',
								'Advanced configuration' => true,
								'StartTLS' => true,
								'Search filter' => 'search_filter'
							],
							'Bind password' => 'test_password'
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => '', 'description' => ''],
							['name' => 'updated_name', 'description' => 'updated_description']
						],
						'userdirectory_ldap' => [
							[
								'host' => 'updated_host',
								'port' => '777',
								'base_dn' => 'updated_dn',
								'bind_password' => 'test_password',
								'search_attribute' => 'updated_search',
								'bind_dn' => 'updated_bin_dn',
								'start_tls' => '1',
								'search_filter' => 'search_filter'
							]
						]
					]
				]
			],
			// 5.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'ldap_with_jit',
								'Host' => '111.222.333',
								'Port' => '',
								'Base DN' => 'base dn',
								'Search attribute' => 'search attribute',
								'Bind DN' => 'bind dn test',
								'Description' => 'test description with jit',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => 'test group base dn',
								'Group name attribute' => 'test group name attribute',
								'Group member attribute' => 'test group member',
								'Reference attribute' => 'test reference attribute',
								'Group filter' => 'test group filter',
								'User name attribute' => 'user name attribute',
								'User last name attribute' => 'user last name'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => 'NEW group pattern',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => 'Test Discord mapping',
									'Media type' => 'Discord',
									'Attribute' => 'test discord'
								],
								[
									'Name' => 'Test iLert mapping',
									'Media type' => 'iLert',
									'Attribute' => 'test iLert'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => '', 'description' => '', 'provision_status' => 0],
							['name' => 'ldap_with_jit', 'description' => 'test description with jit', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '111.222.333',
								'port' => '0',
								'base_dn' => 'base dn',
								'bind_dn' => 'bind dn test',
								'search_attribute' => 'search attribute',
								'group_basedn' => 'test group base dn',
								'group_name' => 'test group name attribute',
								'group_member' => 'test group member',
								'user_ref_attr' => 'test reference attribute',
								'group_filter' => 'test group filter',
								'user_username' => 'user name attribute',
								'user_lastname' => 'user last name'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'NEW group pattern',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => 'Test Discord mapping',
								'mediatypeid' => 10,
								'attribute' => 'test discord'
							],
							[
								'name' => 'Test iLert mapping',
								'mediatypeid' => 22,
								'attribute' => 'test iLert'
							]
						]
					]
				]
			],
			// 6 Two LDAP servers.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'ldap1',
								'Host' => '111.222.444',
								'Port' => '123',
								'Base DN' => 'base dn 1',
								'Search attribute' => 'search attribute 1',
								'Bind DN' => 'bin dn test 1'
							]
						],
						[
							'fields' => [
								'Name' => 'ldap2',
								'Host' => '111.222.555',
								'Port' => '999',
								'Base DN' => 'base dn 2',
								'Search attribute' => 'search attribute 2',
								'Bind DN' => 'bin dn test 2'
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							[
								'name' => ''
							],
							[
								'name' => 'ldap1'
							],
							[
								'name' => 'ldap2'
							]
						],
						'userdirectory_ldap' => [
							[
								'host' => '111.222.444',
								'port' => '123',
								'base_dn' => 'base dn 1',
								'search_attribute' => 'search attribute 1',
								'bind_dn' => 'bin dn test 1'
							],
							[
								'host' => '111.222.555',
								'port' => '999',
								'base_dn' => 'base dn 2',
								'search_attribute' => 'search attribute 2',
								'bind_dn' => 'bin dn test 2'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 *
	 * Update LDAP server settings.
	 */
	public function testUsersAuthenticationLdap_Update($data) {
		if (CDBHelper::getCount('SELECT * FROM userdirectory_ldap') === 0) {
			$server_settings['servers_settings'][0]['fields'] = [
				'Name' => 'test_update',
				'Host' => 'test_update',
				'Base DN' => 'test_update',
				'Search attribute' => 'test_update'
			];

			$this->checkLdap($server_settings, 'button:Add');
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		}

		if (!array_key_exists('expected', $data)) {
			$hash_before = CDBHelper::getHash('SELECT * FROM userdirectory_ldap');
		}

		$this->checkLdap($data, 'xpath://table[@id="ldap-servers"]//a[contains(text(), "test_")]');
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');

		if (!array_key_exists('expected', $data)) {
			$this->assertEquals($hash_before, CDBHelper::getHash('SELECT * FROM userdirectory_ldap'));
		}
		else {
			foreach ($data['db_check'] as $table => $rows) {
				foreach ($rows as $i => $row) {
					$sql = 'SELECT '.implode(",", array_keys($row)).' FROM '.$table.' LIMIT 1 OFFSET '.$i;
					$this->assertEquals([$row], CDBHelper::getAll($sql));
				}
			}

			$form = $this->openLdapForm();
			$table = $form->query('id:ldap-servers')->asTable()->one();

			foreach ($table->query('button:Remove')->all() as $button) {
				$button->click();
			}

			$form->fill(['Enable LDAP authentication' => false]);
			$form->submit();

			if ($this->page->isAlertPresent()) {
				$this->page->acceptAlert();
			}
		}
	}

	public function getCreateData() {
		return [
			// #0 Only default authentication added.
			[
				[
					'error' => 'Incorrect value for field "authentication_type": LDAP is not configured.'
				]
			],
			// #1 LDAP server without any parameters.
			[
				[
					'servers_settings' => [
						[
							'fields' => []
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "host": cannot be empty.',
						'Incorrect value for field "base_dn": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #2 LDAP server without name, Base DN and Search attribute.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Host' => 'ipa.demo1.freeipa.org'
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "base_dn": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #3 LDAP server without name and search attribute.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Host' => 'ipa.demo1.freeipa.org',
								'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org'
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #4 LDAP server without name.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Host' => 'ipa.demo1.freeipa.org',
								'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
								'Search attribute' => 'uid'
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #5 LDAP server with too big integer in Port.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'TEST',
								'Host' => 'ipa.demo1.freeipa.org',
								'Port' => 99999,
								'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
								'Search attribute' => 'uid'
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "port": value must be no greater than "65535".'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #6 Two LDAP servers with same names.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'TEST',
								'Host' => 'ldap.forumsys.com',
								'Base DN' => 'dc=example,dc=com',
								'Search attribute' => 'uid'
							]
						],
						[
							'fields' => [
								'Name' => 'TEST',
								'Host' => 'ldap.forumsys.com',
								'Base DN' => 'dc=example,dc=com',
								'Search attribute' => 'uid'
							]
						]
					],
					'dialog_submit' => true,
					'error' => 'Invalid parameter "/2": value (name)=(TEST) already exists.'
				]
			],
			// #7 LDAP server with JIT, but without Group mapping.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'LDAP',
								'Host' => 'test',
								'Port' => '001',
								'Base DN' => 'test',
								'Search attribute' => 'tets',
								'Configure JIT provisioning' => true
							],
							'ldap_error' => 'Invalid LDAP configuration',
							'ldap_error_details' => [
								'Invalid user group mapping configuration.'
							],
							'error' => 'At least one LDAP server must exist.'
						]
					]
				]
			],
			// #8 Using cyrillic in settings.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'кириллица',
								'Host' => 'кириллица',
								'Base DN' => 'кириллица',
								'Search attribute' => 'кириллица',
								'Bind DN' => 'кириллица',
								'Description' => 'кириллица',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => 'кириллица',
								'Group name attribute' => 'кириллица',
								'Group member attribute' => 'кириллица',
								'Reference attribute' => 'кириллица',
								'Group filter' => 'кириллица',
								'User name attribute' => 'кириллица',
								'User last name attribute' => 'кириллица'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => 'кириллица',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => 'кириллица1',
									'Media type' => 'Discord',
									'Attribute' => 'test discord'
								],
								[
									'Name' => 'кириллица2',
									'Media type' => 'iLert',
									'Attribute' => 'test iLert'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => ['name' => 'кириллица'],
						'userdirectory_ldap' => [
							'host' => 'кириллица',
							'port' => '389',
							'base_dn' => 'кириллица',
							'bind_dn' => 'кириллица',
							'bind_password' => '',
							'search_attribute' => 'кириллица',
							'group_basedn' => 'кириллица',
							'group_name' => 'кириллица',
							'group_member' => 'кириллица',
							'user_ref_attr' => 'кириллица',
							'group_filter' => 'кириллица',
							'user_username' => 'кириллица',
							'user_lastname' => 'кириллица'
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'кириллица',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => 'кириллица1',
								'mediatypeid' => 10,
								'attribute' => 'test discord'
							],
							[
								'name' => 'кириллица2',
								'mediatypeid' => 22,
								'attribute' => 'test iLert'
							]
						]
					]
				]
			],
			// #9 Using symbols in settings.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '~`!@#$%^7*()_+=/',
								'Host' => '~`!@#$%^7*()_+=/',
								'Base DN' => '~`!@#$%^7*()_+=/',
								'Search attribute' => '~`!@#$%^7*()_+=/',
								'Bind DN' => '~`!@#$%^7*()_+=/',
								'Bind password' => '~`!@#$%^7*()_+=/',
								'Description' => '~`!@#$%^7*()_+=/',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => '~`!@#$%^7*()_+=/',
								'Group name attribute' => '~`!@#$%^7*()_+=/',
								'Group member attribute' => '~`!@#$%^7*()_+=/',
								'Reference attribute' => '~`!@#$%^7*()_+=/',
								'Group filter' => '~`!@#$%^7*()_+=/',
								'User name attribute' => '~`!@#$%^7*()_+=/',
								'User last name attribute' => '~`!@#$%^7*()_+=/'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => '~`!@#$%^7*()_+=/',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => '~`!@#$%^7*()_+=/1',
									'Media type' => 'Discord',
									'Attribute' => 'test discord'
								],
								[
									'Name' => '~`!@#$%^7*()_+=/2',
									'Media type' => 'iLert',
									'Attribute' => 'test iLert'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => ['name' => '~`!@#$%^7*()_+=/'],
						'userdirectory_ldap' => [
							'host' => '~`!@#$%^7*()_+=/',
							'port' => '389',
							'base_dn' => '~`!@#$%^7*()_+=/',
							'bind_dn' => '~`!@#$%^7*()_+=/',
							'bind_password' => '~`!@#$%^7*()_+=/',
							'search_attribute' => '~`!@#$%^7*()_+=/',
							'group_basedn' => '~`!@#$%^7*()_+=/',
							'group_name' => '~`!@#$%^7*()_+=/',
							'group_member' => '~`!@#$%^7*()_+=/',
							'user_ref_attr' => '~`!@#$%^7*()_+=/',
							'group_filter' => '~`!@#$%^7*()_+=/',
							'user_username' => '~`!@#$%^7*()_+=/',
							'user_lastname' => '~`!@#$%^7*()_+=/'
						],
						'userdirectory_idpgroup' => [
							[
								'name' => '~`!@#$%^7*()_+=/',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => '~`!@#$%^7*()_+=/1',
								'mediatypeid' => 10,
								'attribute' => 'test discord'
							],
							[
								'name' => '~`!@#$%^7*()_+=/2',
								'mediatypeid' => 22,
								'attribute' => 'test iLert'
							]
						]
					]
				]
			],
			// #10 Long values.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
										'_long_value_long_value_long_value_long_value_long_va',
								'Host' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
										'_long_value_long_value_long_value_long_value_long_valong_value_long_value_long'.
										'_value_long_value_long_value_long_value_long_value_long_value_long_value_long_'.
										'value_long_value_long_v',
								'Port' => 65535,
								'Base DN' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
										'_long_value_long_value_long_value_long_value_long_valong_value_long_value_long_'.
										'value_long_value_long_value_long_value_long_value_long_value_long_value_long_'.
										'value_long_value_long_v',
								'Search attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Bind DN' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Description' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Group name attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Group member attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Reference attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'Group filter' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'User name attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'User last name attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									// TODO: Change this to 255 long string, if ZBX-22236 is fixed.
									'Name' => '1ong_value_long_value_long_value_long_value_long_value_lon',
									'Media type' => 'Discord',
									'Attribute' => 'test discord'
								],
								[
									// TODO: Change this to 255 long string, if ZBX-22236 is fixed.
									'Name' => '2ong_value_long_value_long_value_long_value_long_value_lon',
									'Media type' => 'iLert',
									'Attribute' => 'test iLert'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							'name' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
									'_long_value_long_value_long_value_long_value_long_va',
							'description' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
									'_long_value_long_value_long_value_long_value_long_va'
						],
						'userdirectory_ldap' => [
							'host' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
										'_long_value_long_value_long_value_long_value_long_valong_value_long_value_long',
							'port' => 65535,
							'base_dn' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
										'_long_value_long_value_long_value_long_value_long_valong_value_long_value_long_'.
										'value_long_value_long_value_long_value_long_value_long_value_long_value_long_'.
										'value_long_value_long_v',
							'bind_dn' => '',
							'bind_password' => '',
							'search_attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'group_basedn' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'group_name' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'group_member' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'user_ref_attr' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'group_filter' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'user_username' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
							'user_lastname' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va'
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								// TODO: Change this to 255 long string, if ZBX-22236 is fixed.
								'name' => '1ong_value_long_value_long_value_long_value_long_value_lon',
								'mediatypeid' => 10,
								'attribute' => 'test discord'
							],
							[
								// TODO: Change this to 255 long string, if ZBX-22236 is fixed.
								'name' => '2ong_value_long_value_long_value_long_value_long_value_lon',
								'mediatypeid' => 22,
								'attribute' => 'test iLert'
							]
						]
					]
				]
			],
			// #11 LDAP server with every field filled (no JIT).
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'LDAP',
								'Host' => 'ipa.demo1.freeipa.org',
								'Port' => '389',
								'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
								'Search attribute' => 'uid',
								'Bind DN' => 'uid=admin,cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
								'Bind password' => 'Secret123',
								'Description' => 'description',
								'Advanced configuration' => true,
								'StartTLS' => true,
								'Search filter' => 'filter'
							]
						]
					],
					'db_check' => [
						'userdirectory' => ['name' => 'LDAP', 'description' => 'description'],
						'userdirectory_ldap' => [
							'host' => 'ipa.demo1.freeipa.org',
							'port' => '389',
							'base_dn' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
							'bind_dn' => 'uid=admin,cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
							'bind_password' => 'Secret123',
							'search_attribute' => 'uid'
						]
					]
				]
			],
			// #12 LDAP server with every field filled with JIT.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'create_ldap_with_jit',
								'Host' => '111.222.444',
								'Port' => '',
								'Base DN' => 'create base dn',
								'Search attribute' => 'create search attribute',
								'Bind DN' => 'create bin dn test',
								'Description' => 'create test description with jit',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => 'create test group base dn',
								'Group name attribute' => 'create test group name attribute',
								'Group member attribute' => 'create test group member',
								'Reference attribute' => 'create test reference attribute',
								'Group filter' => 'create est group filter',
								'User name attribute' => 'create user name attribute',
								'User last name attribute' => 'create user last name'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => 'create group pattern',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => 'Create Test Discord mapping',
									'Media type' => 'Discord',
									'Attribute' => 'test discord'
								],
								[
									'Name' => 'Create Test iLert mapping',
									'Media type' => 'iLert',
									'Attribute' => 'test iLert'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => '', 'description' => '', 'provision_status' => 0],
							['name' => 'create_ldap_with_jit', 'description' => 'create test description with jit', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '111.222.444',
								'port' => '0',
								'base_dn' => 'create base dn',
								'bind_dn' => 'create bin dn test',
								'search_attribute' => 'create search attribute',
								'group_basedn' => 'create test group base dn',
								'group_name' => 'create test group name attribute',
								'group_member' => 'create test group member',
								'user_ref_attr' => 'create test reference attribute',
								'group_filter' => 'create test group filter',
								'user_username' => 'create user name attribute',
								'user_lastname' => 'create user last name'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'create group pattern',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						],
						'userdirectory_media' => [
							[
								'name' => 'Create Test Discord mapping',
								'mediatypeid' => 10,
								'attribute' => 'test discord'
							],
							[
								'name' => 'Create Test iLert mapping',
								'mediatypeid' => 22,
								'attribute' => 'test iLert'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Check authentication with LDAP settings.
	 *
	 * @dataProvider getCreateData
	 */
	public function testUsersAuthenticationLdap_Create($data) {
		$this->checkLdap($data, 'button:Add');

		// Check error messages.
		if (CTestArrayHelper::get($data, 'expected', TEST_BAD) === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');

			// Check DB configuration.
			$sql = 'SELECT host, port, base_dn, bind_dn, bind_password, search_attribute '.
					'FROM userdirectory_ldap '.
					'WHERE userdirectoryid IN ('.
						'SELECT userdirectoryid'.
						' FROM userdirectory '.
						' WHERE name ='.zbx_dbstr($data['db_check']['userdirectory']['name']).
					')';

			$this->assertEquals($data['db_check']['userdirectory_ldap'], CDBHelper::getRow($sql));
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot update authentication', $data['error']);
		}
	}

	/**
	 * Check that User Group value in table changes after adding LDAP server to any user group.
	 */
	public function testUsersAuthenticationLdap_UserGroups() {
		$form = $this->openLdapForm();
		$table = $form->query('id:ldap-servers')->asTable()->one();

		// Add new LDAP server if it is not present.
		if ($table->getRows()->count() === 0) {
			$this->setLdap([], 'button:Add', 'atest');
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$form->selectTab('LDAP settings');
		}

		// Check that there is no User groups with added LDAP server.
		$row = $table->getRow(0);
		$ldap_name = $row->getColumn('Name')->getText();
		$this->assertEquals('0', $row->getColumn('User groups')->getText());

		// Open existing User group and change it LDAP server.
		$usrgrpid = CDataHelper::get('LoginUsers.usrgrpids.LDAP user group');
		$this->page->open('zabbix.php?action=usergroup.edit&usrgrpid='.$usrgrpid)->waitUntilReady();
		$this->query('name:userdirectoryid')->asDropdown()->one()->fill($ldap_name);
		$this->query('button:Update')->one()->click();

		// Check that value in table is changed and display that there exists group with LDAP server.
		$this->page->open('zabbix.php?action=authentication.edit')->waitUntilReady();
		$form->selectTab('LDAP settings');
		$this->assertEquals('1', $row->getColumn('User groups')->getText());
		$this->assertFalse($this->query('xpath://button[text()="Remove"][1]')->one()->isEnabled());
	}

	/**
	 * Function for opening LDAP configuration form.
	 *
	 * @param string $auth		default authentication field value
	 */
	private function openLdapForm($auth = 'Internal') {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->fill(['Default authentication' => $auth]);
		$form->selectTab('LDAP settings');

		return $form;
	}

	/**
	 * Fill and submit LDAP server settings.
	 *
	 * @param string $data			   data provider
	 * @param string $query			   object to click for LDAP creating or updating
	 * @param string $values		   simple LDAP server values
	 */
	private function setLdap($data, $query, $values = null) {
		$form = $this->query('id:authentication-form')->asForm()->one();

		// Select LDAP setting tab if it is not selected.
		if ($form->getSelectedTab() !== 'LDAP settings') {
			$form->selectTab('LDAP settings');
		}

		// Open and fill LDAP settings form.
		$this->query('id:ldap_auth_enabled')->asCheckbox()->one()->set(true);
		if ($values !== null) {
			$data['servers_settings'][0]['fields'] = [
					'Name' => $values,
					'Host' => $values,
					'Base DN' => $values,
					'Search attribute' => $values
			];
		}

		// Fill LDAP server form.
		foreach ($data['servers_settings'] as $i => $ldap) {
			if ($i > 0) {
				$query = 'button:Add';
			}

			$form->query($query)->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$ldap_form = $dialog->asForm();
			$ldap_form->fill($ldap['fields']);

			if (array_key_exists('Bind password', $ldap)) {
				$ldap_form->getFieldContainer('Bind password')->query('button:Change password')->waitUntilClickable()
						->one()->click();
				$ldap_form->query('id:bind_password')->one()->waitUntilVisible()->fill($ldap['Bind password']);
			}

			if (CTestArrayHelper::get($ldap['fields'], 'Configure JIT provisioning')) {
				if (array_key_exists('User group mapping', $ldap)) {
					$this->setMapping($ldap['User group mapping'], $ldap_form, 'User group mapping');
				}

				if (array_key_exists('Media type mapping', $ldap)) {
					$this->setMapping($ldap['Media type mapping'], $ldap_form, 'Media type mapping');
				}
			}

			$ldap_form->submit();

			if (CTestArrayHelper::get($data, 'expected') === TEST_GOOD || CTestArrayHelper::get($data, 'dialog_submit')) {
				$dialog->ensureNotPresent();
			}
		}
	}

	/**
	 * Create or update LDAP server values.
	 *
	 * @param array     $data	  data provider
	 * @param string    $query    object to click for LDAP creating or updating
	 */
	private function checkLdap($data, $query) {
		$form = $this->openLdapForm('LDAP');

		// Configuration at 'LDAP settings' tab.
		if (array_key_exists('servers_settings', $data)) {
			$this->setLdap($data, $query);

			// Check error message in ldap creation form.
			if (array_key_exists('ldap_error', $data)) {
				$this->assertMessage(TEST_BAD, $data['ldap_error'], $data['ldap_error_details']);
				COverlayDialogElement::find()->one()->close();
			}
		}

		$form->submit();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}
	}

	/**
	 * Set mapping for LDAP server.
	 *
	 * @param array            $data	 given mapping
	 * @param CFormElement     $form     LDAP form
	 * @param string           $field    mapping field which is being filled
	 */
	private function setMapping($data, $form, $field) {
		foreach ($data as $mapping) {
			$form->getFieldContainer($field)->query('button:Add')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$param_form = $dialog->asForm();
			$param_form->fill($mapping);
			$param_form->submit();
			$dialog->waitUntilNotVisible();
		}
	}
}
