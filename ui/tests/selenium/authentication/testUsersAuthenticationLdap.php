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


require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../common/testFormAuthentication.php';

/**
 * @backup config, userdirectory, usrgrp
 *
 * @dataSource LoginUsers
 */
class testUsersAuthenticationLdap extends testFormAuthentication {

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

	public function testUsersAuthenticationLdap_Layout() {
		$ldap_form = $this->openFormAndCheckBasics('LDAP');

		// Check LDAP form default values.
		$ldap_form->checkValue([
			'Enable LDAP authentication' => false,
			'Enable JIT provisioning' => false,
			'Case-sensitive login' => true,
			'Provisioning period' => '1h'
		]);

		// Check LDAP form fields editability.
		foreach ([false, true] as $status) {
			$ldap_form->fill(['Enable LDAP authentication' => $status]);

			foreach (['Enable JIT provisioning', 'Servers', 'Case-sensitive login'] as $label) {
				$this->assertTrue($ldap_form->getField($label)->isEnabled($status));
			}
		}

		$this->assertEquals(['Servers'], $ldap_form->getRequiredLabels());

		// Check server table's headers.
		$server_table = [
			'Servers' => [
				'id' => 'ldap-servers',
				'headers' => ['Name', 'Host', 'User groups', 'Default', 'Action']
			]
		];

		$this->checkTablesHeaders($server_table, $ldap_form);

		// Check 'Provisioning period' field's editability.
		foreach ([false, true] as $jit_status) {
			$ldap_form->fill(['Enable JIT provisioning' => $jit_status]);
			$this->assertTrue($ldap_form->getField('Provisioning period')->isEnabled($jit_status));
		}

		// Check default server popup fields.
		$ldap_form->getFieldContainer('Servers')->query('button:Add')->waitUntilClickable()->one()->click();
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

		foreach ($server_fields as $label => $attributes) {
			$field = $server_form->getField($label);
			$this->assertEquals($attributes['visible'], $field->isVisible());
			$this->assertTrue($field->isEnabled());

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $field->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $field->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $field->getAttribute('placeholder'));
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

		foreach ($jit_fields_memberOf as $label => $visible) {
			$field = $server_form->getField($label);
			$this->assertEquals($visible, $field->isVisible());
			$this->assertTrue($field->isEnabled());
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
		$server_form->query('xpath:.//label[text()="StartTLS"]')->waitUntilVisible();
		$this->assertTrue($server_form->getField('Search filter')->isVisible());

		$hintboxes = [
			'Group configuration' => 'memberOf is a preferable way to configure groups because it is faster. '.
					'Use groupOfNames if your LDAP server does not support memberOf or group filtering is required.',
			'Reference attribute' => 'Use %{ref} in group filter to reference value of this user attribute.',
			'Media type mapping' => "Map user's LDAP media attributes (e.g. email) to Zabbix user media for sending".
					" notifications."
		];

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

		$this->checkFormHintsAndMapping($server_form, $hintboxes, $mapping_tables, 'LDAP');

		// Check footer buttons in Server form and close it.
		$this->checkFooterButtons($server_dialog, ['Add', 'Test', 'Cancel']);
		$server_dialog->close();
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
						'Host' => PHPUNIT_LDAP_HOST ,
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid'
					],
					'test_settings' => [
						'Login' => PHPUNIT_LDAP_USERNAME,
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
						'Host' => PHPUNIT_LDAP_HOST,
						'Base DN' => 'DC=zbx,DC=local',
						'Search attribute' => 'sAMAccountName',
						'Bind DN' => ' CN=Admin,OU=Users,OU=Zabbix,DC=zbx,DC=local',
						'Bind password' => PHPUNIT_LDAP_BIND_PASSWORD,
						'Description' => 'Test description',
						'Advanced configuration' => true,
						'StartTLS' => true,
						'Search filter' => 'filter'
					],
					'test_settings' => [
						'Login' => PHPUNIT_LDAP_USERNAME,
						'User password' => PHPUNIT_LDAP_USER_PASSWORD
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
						'Host' => PHPUNIT_LDAP_HOST,
						'Base DN' => 'dc=zbx,dc=local',
						'Search attribute' => 'uid',
						'Bind DN' => 'cn=admin,dc=zbx,dc=local',
						'Bind password' => PHPUNIT_LDAP_BIND_PASSWORD,
						'Configure JIT provisioning' => true,
						'Group configuration' => 'groupOfNames',
						'Group base DN' => 'ou=Groups,ou=Zabbix,dc=zbx,dc=local',
						'Group name attribute' => 'cn',
						'Group member attribute' => 'memberUid',
						'Reference attribute' => '%{ref}',
						'Group filter' => '(%{groupattr}=%{user})',
						'User name attribute' => 'uid',
						'User last name attribute' => 'sn'
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
							'Name' => 'mail',
							'Media type' => 'SMS',
							'Attribute' => 'mobile'
						]
					],
					'test_settings' => [
						'Login' => PHPUNIT_LDAP_USERNAME,
						'User password' => PHPUNIT_LDAP_USER_PASSWORD
					],
					'check_provisioning' => [
						'role' => 'Super admin role',
						'groups' => 'Zabbix administratorsGuests',
						'medias' => 'mail'
					]
				]
			]
		];
	}

	/**
	 * Test LDAP settings.
	 *
	 * @dataProvider getTestData
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
			$test_form_dialog->asForm()->fill($data['test_settings'])->submit();
			$test_form_dialog->waitUntilReady();
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
				$this->assertEquals($text, $test_form_dialog->query('id:provisioning_'.$id)->waitUntilVisible()
						->one()->getText()
				);
			}
		}

		$test_form_dialog->query('button:Cancel')->waitUntilClickable()->one()->click();
		$test_form_dialog->waitUntilNotVisible();
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
		$this->assertEquals(['Name', 'Host', 'User groups', 'Default', 'Action'], $table->getHeadersText());

		// Check that LDAP server added in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM userdirectory_ldap'));

		// Check that the last server can't be removed while LDAP authentication is still on.
		$table->query('button:Remove')->one()->click();
		$form->submit();
		$this->assertMessage(TEST_BAD, 'Cannot update authentication', 'At least one LDAP server must exist');
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM userdirectory_ldap'));

		// Uncheck LDAP authentication and try saving again. Make sure the server is not deleted from DB before saving.
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
		$table->findRow('Host', $hostname)->getColumn('Action')->query('button:Remove')->one()->click();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$new_hostname = CDBHelper::getValue('SELECT host FROM userdirectory_ldap udl INNER JOIN config co ON '.
				'udl.userdirectoryid = co.ldap_userdirectoryid');

		// Check that old LDAP server (by host name) is not default now.
		$this->assertNotEquals($hostname, $new_hostname);
	}

	public function getUpdateData() {
		return [
			// #0 Update LDAP with empty strings.
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
			// #1 Update LDAP with empty strings except host.
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
			// #2 Update LDAP with empty strings except host and Base DN.
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
			// #3 Update LDAP with empty strings in name only.
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
			// #4 Update LDAP with changing Bind password.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'updated_name',
								'Host' => 'updated_host',
								'Port' => 777,
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
							['name' => 'updated_name', 'description' => 'updated_description']
						],
						'userdirectory_ldap' => [
							[
								'host' => 'updated_host',
								'port' => 777,
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
			// #5 Update LDAP with adding JIT (memberOf).
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'ldap_with_jit_memberOf',
								'Host' => '111.222.666',
								'Port' => 1234,
								'Base DN' => 'new base dn',
								'Search attribute' => 'new search attribute',
								'Bind DN' => 'new bind dn test',
								'Description' => 'new test description with jit',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'memberOf',
								'Group name attribute' => 'new test group name attribute',
								'User group membership attribute' => 'new test group membership',
								'User name attribute' => 'new user name attribute',
								'User last name attribute' => 'new user last name'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => 'NEW updated group pattern',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => 'ldap_with_jit_memberOf', 'description' => 'new test description with jit', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '111.222.666',
								'port' => 1234,
								'base_dn' => 'new base dn',
								'bind_dn' => 'new bind dn test',
								'search_attribute' => 'new search attribute',
								'group_name' => 'new test group name attribute',
								'group_membership' => 'new test group membership',
								'user_username' => 'new user name attribute',
								'user_lastname' => 'new user last name'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'NEW updated group pattern',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						]
					]
				]
			],
			// #6 Update LDAP with adding JIT (groupOfNames).
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'ldap_with_jit_groupOfNames',
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
							['name' => 'ldap_with_jit_groupOfNames', 'description' => 'test description with jit', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '111.222.333',
								'port' => 0,
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
			// #7 Update LDAP with JIT from memberOf to groupOfNames.
			[
				[
					'expected' => TEST_GOOD,
					'start_ldap' => [
						'Name' => 'test_update_memberOf',
						'Host' => '111.020.050',
						'Port' => 888,
						'Base DN' => 'test_update_memberOf',
						'Search attribute' => 'test_update_memberOf',
						'Bind DN' => 'test_update_memberOf',
						'Description' => 'test_update_memberOf',
						'Configure JIT provisioning' => true,
						'Group configuration' => 'memberOf',
						'Group name attribute' => 'test_update_memberOf',
						'User group membership attribute' => 'test_update_memberOf',
						'User name attribute' => 'test_update_memberOf',
						'User last name attribute' => 'test_update_memberOf'
					],
					'start_group_mapping' => [
						[
							'LDAP group pattern' => 'NEW group pattern',
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'test_update_to_groupOfNames',
								'Host' => '111.030.060',
								'Base DN' => 'test_update_to_groupOfNames',
								'Search attribute' => 'test_update_to_groupOfNames',
								'Bind DN' => 'test_update_to_groupOfNames',
								'Description' => 'test_update_to_groupOfNames',
								'Group configuration' => 'groupOfNames',
								'Group base DN' => 'test_update_to_groupOfNames',
								'Group name attribute' => 'test_update_to_groupOfNames',
								'Group member attribute' => 'test_update_to_groupOfNames',
								'Reference attribute' => 'test_update_to_groupOfNames',
								'Group filter' => 'test_update_to_groupOfNames',
								'User name attribute' => 'test_update_to_groupOfNames',
								'User last name attribute' => 'test_update_to_groupOfNames'
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => 'test_update_to_groupOfNames', 'description' => 'test_update_to_groupOfNames', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '111.030.060',
								'port' => 888,
								'base_dn' => 'test_update_to_groupOfNames',
								'bind_dn' => 'test_update_to_groupOfNames',
								'search_attribute' => 'test_update_to_groupOfNames',
								'group_basedn' => 'test_update_to_groupOfNames',
								'group_name' => 'test_update_to_groupOfNames',
								'group_member' => 'test_update_to_groupOfNames',
								'user_ref_attr' => 'test_update_to_groupOfNames',
								'group_filter' => 'test_update_to_groupOfNames',
								'user_username' => 'test_update_to_groupOfNames',
								'user_lastname' => 'test_update_to_groupOfNames'
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
						]
					]
				]
			]
		];
	}

	/**
	 * Update LDAP server settings.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testUsersAuthenticationLdap_Update($data) {
		if (CDBHelper::getCount('SELECT * FROM userdirectory_ldap') === 0) {
			$server_settings['servers_settings'][0]['fields'] = (CTestArrayHelper::get($data, 'start_ldap',
					[
						'Name' => 'test_update',
						'Host' => 'test_update',
						'Base DN' => 'test_update',
						'Bind password' => 'test_password',
						'Search attribute' => 'test_update'
					]
			));

			if (array_key_exists('start_group_mapping', $data)) {
				$server_settings['servers_settings'][0]['User group mapping'] =	$data['start_group_mapping'];
			}

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
				$all_rows = CDBHelper::getAll('SELECT * FROM '.$table.' LIMIT '.count($rows));
				foreach ($rows as $i => $row) {
					foreach ($row as $key => $value) {
						$this->assertEquals($value, $all_rows[$i][$key]);
					}
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

	public function getCreateValidationData() {
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
							]
						]
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Invalid user group mapping configuration.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #8 Group mapping dialog form validation.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'LDAP',
								'Host' => 'test',
								'Base DN' => 'test',
								'Search attribute' => 'tets',
								'Configure JIT provisioning' => true
							],
							'User group mapping' => [[]]
						]
					],
					'mapping_error' => 'Invalid user group mapping configuration.',
					'mapping_error_details' => [
						'Field "roleid" is mandatory.',
						'Incorrect value for field "name": cannot be empty.',
						'Field "user_groups" is mandatory.'
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Invalid user group mapping configuration.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			// #9 Media mapping dialog form validation.
			[
				[
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'LDAP',
								'Host' => 'test no media',
								'Base DN' => 'test no media',
								'Search attribute' => 'tets no media',
								'Configure JIT provisioning' => true
							],
							'Media type mapping' => [[]]
						]
					],
					'mapping_error' => 'Invalid media type mapping configuration.',
					'mapping_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "attribute": cannot be empty.'
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Invalid user group mapping configuration.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			]
		];
	}

	public function getCreateData() {
		return [
			// #0 Using cyrillic symbols in fields (groupOfNames).
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
								'User last name attribute' => 'кириллица',
								'Advanced configuration' => true,
								'Search filter' => 'кириллица'
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
									'Attribute' => 'кириллица'
								],
								[
									'Name' => 'кириллица2',
									'Media type' => 'iLert',
									'Attribute' => 'кириллица'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => 'кириллица', 'description' => 'кириллица', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => 'кириллица',
								'port' => 389,
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
								'user_lastname' => 'кириллица',
								'search_filter' => 'кириллица'
							]
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
								'attribute' => 'кириллица'
							],
							[
								'name' => 'кириллица2',
								'mediatypeid' => 22,
								'attribute' => 'кириллица'
							]
						]
					]
				]
			],
			// #1 Using cyrillic symbols in fields (memberOf).
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
								'Configure JIT provisioning' => true,
								'Group name attribute' => 'кириллица',
								'User group membership attribute' => 'кириллица',
								'User name attribute' => 'кириллица',
								'User last name attribute' => 'кириллица'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => 'кириллица',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => 'кириллица', 'description' => '', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => 'кириллица',
								'port' => 389,
								'base_dn' => 'кириллица',
								'search_attribute' => 'кириллица',
								'group_name' => 'кириллица',
								'user_username' => 'кириллица',
								'user_lastname' => 'кириллица'
							]
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
						]
					]
				]
			],
			// #2 Using symbols in settings (groupOfNames).
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Host' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Base DN' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Search attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Bind DN' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Bind password' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Description' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Group name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Group member attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Reference attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Group filter' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'User name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'User last name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Advanced configuration' => true,
								'Search filter' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => '~`!@#$%^7*()_+=/1',
									'Media type' => 'Discord',
									'Attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
								],
								[
									'Name' => '~`!@#$%^7*()_+=/2',
									'Media type' => 'iLert',
									'Attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ', 'description' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'port' => '389',
								'base_dn' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'bind_dn' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'bind_password' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'search_attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'group_basedn' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'group_name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'group_member' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_ref_attr' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'group_filter' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_username' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_lastname' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'search_filter' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
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
								'attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							],
							[
								'name' => '~`!@#$%^7*()_+=/2',
								'mediatypeid' => 22,
								'attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							]
						]
					]
				]
			],
			// #3 Using symbols in settings (memberOf).
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Host' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Base DN' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Search attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'Configure JIT provisioning' => true,
								'Group name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'User group membership attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'User name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'User last name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ', 'description' => '', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'port' => 389,
								'base_dn' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'search_attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'group_name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_username' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'user_lastname' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						]
					]
				]
			],
			// #4 Checking trim of the leading and trailing settings (groupOfNames).
			[
				[
					'expected' => TEST_GOOD,
					'trim' => true,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '   leading.trailing   ',
								'Host' => '   leading.trailing   ',
								'Base DN' => '   leading.trailing   ',
								'Search attribute' => '   leading.trailing   ',
								'Bind DN' => '   leading.trailing   ',
								'Bind password' => '   leading.trailing   ',
								'Description' => '   leading.trailing   ',
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => '   leading.trailing   ',
								'Group name attribute' => '   leading.trailing   ',
								'Group member attribute' => '   leading.trailing   ',
								'Reference attribute' => '   leading.trailing   ',
								'Group filter' => '   leading.trailing   ',
								'User name attribute' => '   leading.trailing   ',
								'User last name attribute' => '   leading.trailing   ',
								'Advanced configuration' => true,
								'Search filter' => '   leading.trailing   '
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => '   leading.trailing   ',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => '   leading.trailing   ',
									'Media type' => 'Discord',
									'Attribute' => '   leading.trailing   '
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => 'leading.trailing', 'description' => 'leading.trailing', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => 'leading.trailing',
								'port' => 389,
								'base_dn' => 'leading.trailing',
								'bind_dn' => 'leading.trailing',
								'bind_password' => '   leading.trailing   ',
								'search_attribute' => 'leading.trailing',
								'group_basedn' => 'leading.trailing',
								'group_name' => 'leading.trailing',
								'group_member' => 'leading.trailing',
								'user_ref_attr' => '   leading.trailing   ',
								'group_filter' => 'leading.trailing',
								'user_username' => 'leading.trailing',
								'user_lastname' => 'leading.trailing',
								'search_filter' => 'leading.trailing'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'leading.trailing',
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
								'name' => 'leading.trailing',
								'mediatypeid' => 10,
								'attribute' => 'leading.trailing'
							]
						]
					]
				]
			],
			// #5 Checking trim of the leading and trailing settings (memberOf).
			[
				[
					'expected' => TEST_GOOD,
					'trim' => true,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '   leading.trailing   ',
								'Host' => '   leading.trailing   ',
								'Base DN' => '   leading.trailing   ',
								'Search attribute' => '   leading.trailing   ',
								'Configure JIT provisioning' => true,
								'Group name attribute' => '   leading.trailing   ',
								'User group membership attribute' => '   leading.trailing   ',
								'User name attribute' => '   leading.trailing   ',
								'User last name attribute' => '   leading.trailing   '
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => '   leading.trailing   ',
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							['name' => 'leading.trailing', 'description' => '', 'provision_status' => 1]
						],
						'userdirectory_ldap' => [
							[
								'host' => 'leading.trailing',
								'port' => 389,
								'base_dn' => 'leading.trailing',
								'search_attribute' => 'leading.trailing',
								'group_name' => 'leading.trailing',
								'user_username' => 'leading.trailing',
								'user_lastname' => 'leading.trailing'
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => 'leading.trailing',
								'roleid' => 1
							]
						],
						'userdirectory_usrgrp' => [
							[
								'usrgrpid' => 92
							]
						]
					]
				]
			],
			// #6 Long values.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => STRING_128,
								'Host' => STRING_255,
								'Port' => 65535,
								'Base DN' => STRING_255,
								'Search attribute' => STRING_255,
								'Bind password' => STRING_128,
								'Bind DN' => STRING_255,
								'Description' => STRING_6000,
								'Configure JIT provisioning' => true,
								'Group configuration' => 'groupOfNames',
								'Group base DN' => STRING_255,
								'Group name attribute' => STRING_255,
								'Group member attribute' => STRING_255,
								'Reference attribute' => STRING_255,
								'Group filter' => STRING_255,
								'User name attribute' => STRING_255,
								'User last name attribute' => STRING_255,
								'Advanced configuration' => true,
								'StartTLS' => true,
								'Search filter' => STRING_255
							],
							'User group mapping' => [
								[
									'LDAP group pattern' => STRING_255,
									'User groups' => 'Test timezone',
									'User role' => 'User role'
								]
							],
							'Media type mapping' => [
								[
									'Name' => '1ong_value_long_value_long_value_long_value_long_value_long_valu',
									'Media type' => 'Discord',
									'Attribute' => STRING_255
								],
								[
									'Name' => '2ong_value_long_value_long_value_long_value_long_value_long_valu',
									'Media type' => 'iLert',
									'Attribute' => STRING_255
								]
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							[
								'name' => STRING_128,
								'description' => STRING_6000,
								'provision_status' => 1
							]
						],
						'userdirectory_ldap' => [
							[
								'host' => STRING_255,
								'port' => 65535,
								'base_dn' => STRING_255,
								'bind_dn' => STRING_255,
								'bind_password' => STRING_128,
								'search_attribute' => STRING_128,
								'group_basedn' => STRING_255,
								'group_name' => STRING_255,
								'group_member' => STRING_255,
								'user_ref_attr' => STRING_255,
								'group_filter' => STRING_255,
								'user_username' => STRING_255,
								'user_lastname' => STRING_255,
								'start_tls' => 1,
								'search_filter' => STRING_255
							]
						],
						'userdirectory_idpgroup' => [
							[
								'name' => STRING_255,
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
								'name' => '1ong_value_long_value_long_value_long_value_long_value_long_valu',
								'mediatypeid' => 10,
								'attribute' => STRING_255
							],
							[
								'name' => '2ong_value_long_value_long_value_long_value_long_value_long_valu',
								'mediatypeid' => 22,
								'attribute' => STRING_255
							]
						]
					]
				]
			],
			// #7 LDAP server with every field filled (no JIT).
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'LDAP',
								'Host' => 'ipa.demo1.freeipa.org',
								'Port' => 389,
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
						'userdirectory' => [
							['name' => 'LDAP', 'description' => 'description',  'provision_status' => 0]
						],
						'userdirectory_ldap' => [
							[
								'host' => 'ipa.demo1.freeipa.org',
								'port' => 389,
								'base_dn' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
								'bind_dn' => 'uid=admin,cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
								'bind_password' => 'Secret123',
								'search_attribute' => 'uid',
								'start_tls' => 1,
								'search_filter' => 'filter'
							]
						]
					]
				]
			],
			// #8 LDAP server with every field filled with JIT (groupOfNames).
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
								'Group filter' => 'create test group filter',
								'User name attribute' => 'create user name attribute',
								'User last name attribute' => 'create user last name',
								'Advanced configuration' => true,
								'StartTLS' => true,
								'Search filter' => 'search filter'
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
								'user_lastname' => 'create user last name',
								'start_tls' => true,
								'search_filter' => 'search filter'
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
			],
			// #9 Two LDAP servers with different names.
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
							['name' => 'ldap1'],
							['name' => 'ldap2']
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
	 * @dataProvider getCreateValidationData
	 */
	public function testUsersAuthenticationLdap_CreateValidation($data) {
		$this->testLdapCreate($data);
	}

	/**
	 * @backup config
	 *
	 * @dataProvider getCreateData
	 */
	public function testUsersAuthenticationLdap_Create($data) {
		$this->testLdapCreate($data);
	}

	private function testLdapCreate($data) {
		$this->checkLdap($data, 'button:Add');

		// Check error messages.
		if (CTestArrayHelper::get($data, 'expected', TEST_BAD) === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');

			// Check LDAP configuration in DB.
			foreach ($data['db_check'] as $table => $rows) {
				foreach ($rows as $i => $row) {
					if (CTestArrayHelper::get($data, 'trim', false)) {
						$rows = array_map('trim', $row);
					}

					$sql = 'SELECT '.implode(",", array_keys($row)).' FROM '.$table.' LIMIT 1 OFFSET '.$i;
					$this->assertEquals([$row], CDBHelper::getAll($sql));
				}
			}
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
	 * @param string $auth    default authentication field value
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
	 * @param string $data	    data provider
	 * @param string $query     object to click for LDAP creating or updating
	 * @param string $values    simple LDAP server values
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
				$success = (array_key_exists('mapping_error', $data)) ? false : true;

				if (array_key_exists('User group mapping', $ldap)) {
					$this->setMapping($ldap['User group mapping'], $ldap_form, 'User group mapping', $success);
				}

				if (array_key_exists('Media type mapping', $ldap)) {
					$this->setMapping($ldap['Media type mapping'], $ldap_form, 'Media type mapping', $success);
				}
			}

			// Check error message in ldap creation form.
			if (array_key_exists('mapping_error', $data)) {
				$this->assertMessage(TEST_BAD, $data['mapping_error'], $data['mapping_error_details']);
				COverlayDialogElement::find()->all()->last()->query('button:Cancel')->one()->click();
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
				COverlayDialogElement::find()->all()->last()->close();
			}
		}

		$form->submit();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}
	}
}
