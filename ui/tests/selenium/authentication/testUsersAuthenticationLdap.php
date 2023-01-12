<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		$this->assertTrue($form->getField('Enable LDAP authentication')->isEnabled());

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
			'Name' => ['visible' => true, 'maxlength' => 128, 'value' => '', 'mandatory' => true],
			'Host' => ['visible' => true, 'maxlength' => 255, 'value' => '', 'mandatory' => true],
			'Port' => ['visible' => true, 'maxlength' => 5, 'value' => 389, 'mandatory' => true],
			'Base DN' => ['visible' => true, 'maxlength' => 255, 'value' => '', 'mandatory' => true],
			'Search attribute' => ['visible' => true, 'maxlength' => 128, 'value' => '', 'mandatory' => true],
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
			'User group mapping' => ['visible' => false, 'mandatory' => true],
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

			if (array_key_exists('mandatory', $attributes)) {
				$this->assertStringContainsString('form-label-asterisk', $server_form->getLabel($field)->getAttribute('class'));
			}
		}

		// Check JIT fields (memberOf).
		$server_form->fill(['Configure JIT provisioning' => true]);
		$server_form->query('xpath:.//label[text()="Group configuration"]')->waitUntilVisible()->one();

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
		$server_form->query('xpath:.//label[text()="Group base DN"]')->waitUntilVisible()->one();

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
			'Media type mapping' => 'Map user’s LDAP media attributes (e.g. email) to Zabbix user media for sending '.
					'notifications.'
		];

		foreach ($hintboxes as $field => $text) {
			$server_form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($field).']/a')->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent()->all()->last();
			$this->assertEquals($text, $hint->getText());
			$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();
		}

		// Check group mapping popup.
		$server_form->getFieldContainer('User group mapping')->query('button:Add')->waitUntilClickable()->one()->click();
		$group_mapping_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals('New user group mapping', $group_mapping_dialog->getTitle());
		$group_mapping_form = $group_mapping_dialog->asForm();

		foreach (['LDAP group pattern', 'User groups', 'User role'] as $label) {
			$field = $group_mapping_form->getField($label);
			$this->assertTrue($field->isVisible());
			$this->assertTrue($field->isEnabled());
			$this->assertEquals('', $field->getValue());
			$this->assertStringContainsString('form-label-asterisk', $group_mapping_form->getLabel($label)->getAttribute('class'));
		}

		// Check hint in group mapping popup.
		$group_mapping_form->query('xpath:.//label[text()="LDAP group pattern"]/a')->one()->click();
		$hint = $this->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent()->all()->last();
		$text = "Naming requirements:".
				"\ngroup name must match LDAP group name".
				"\nwildcard patterns with '*' may be used";
		$this->assertEquals($text, $hint->getText());
		$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();

		// Check group mapping popup footer buttons.
		$group_mapping_footer = $group_mapping_dialog->getFooter();
		$this->assertTrue($group_mapping_footer->query('button:Add')->one()->isClickable());
		$group_mapping_footer->query('button:Cancel')->waitUntilClickable()->one()->click();

		// Check media type mapping popup.
		$server_form->getFieldContainer('Media type mapping')->query('button:Add')->waitUntilClickable()->one()->click();
		$media_mapping_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals('New media type mapping', $media_mapping_dialog->getTitle());
		$media_mapping_form = $media_mapping_dialog->asForm();

		foreach (['Name', 'Media type', 'Attribute'] as $label) {
			$field = $media_mapping_form->getField($label);
			$this->assertTrue($field->isVisible());
			$this->assertTrue($field->isEnabled());
			$this->assertStringContainsString('form-label-asterisk',
					$media_mapping_form->getLabel($label)->getAttribute('class')
			);
		}

		// Check default values in  media type mapping popup.
		$media_mapping_form->checkValue(['Name' => '', 'id:mediatypeid' => 'Brevis.one', 'Attribute' => '']);

		// Check media mapping popup footer buttons.
		$media_mapping_footer = $media_mapping_dialog->getFooter();
		$this->assertTrue($media_mapping_footer->query('button:Add')->one()->isClickable());
		$media_mapping_footer->query('button:Cancel')->waitUntilClickable()->one()->click();

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
			// #10 test with correct LDAP settings and credentials.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						'Host' => 'dc-ad-srv.zabbix.sandbox',
						'Base DN' => 'DC=zbx,DC=local',
						'Search attribute' => 'sAMAccountName'
					],
					'test_settings' => [
						'Login' => 'employee',
						'User password' => 'Secret123'
					]
				]
			],
			// #11 test with correct LDAP settings and JIT settings.
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
							'id:mediatypeid' => 'Email',
							'Attribute' => 'mail'
						]
					],
					'test_settings' => [
						'Login' => 'user1',
						'User password' => 'zabbix#33'
					],
					'check_provisioning' =>[
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
							'fields' =>  [
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
							'fields' =>  [
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
							'fields' =>  [
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
							'fields' =>  [
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
							'fields' =>  [
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
							'fields' =>  [
								'Name' => 'ldap_with_jit',
								'Host' => '111.222.333',
								'Port' => '',
								'Base DN' => 'base dn',
								'Search attribute' => 'search attribute',
								'Bind DN' => 'bin dn test',
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
									'id:mediatypeid' => 'Discord',
									'Attribute' => 'test discord'
								],
								[
									'Name' => 'Test iLert mapping',
									'id:mediatypeid' => 'iLert',
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
								'bind_dn' => 'bin dn test',
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
							'fields' =>  [
								'Name' => 'ldap1',
								'Host' => '111.222.444',
								'Port' => '123',
								'Base DN' => 'base dn 1',
								'Search attribute' => 'search attribute 1',
								'Bind DN' => 'bin dn test 1'
							]
						],
						[
							'fields' =>  [
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
			//#4 LDAP server without name.
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
			// #5 Two LDAP servers with same names.
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
			// #6 Using cyrillic in settings.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => 'кириллица',
								'Host' => 'кириллица',
								'Base DN' => 'кириллица',
								'Search attribute' => 'кириллица'
							]
						]
					],
					'db_check' => [
						'userdirectory' => ['name' => 'кириллица'],
						'userdirectory_ldap' => [
							'host' => 'кириллица',
							'port' => '389',
							'base_dn' => 'кириллица',
							'bind_dn' => '',
							'bind_password' => '',
							'search_attribute' => 'кириллица'
						]
					]
				]
			],
			// #7 Using symbols in settings.
			[
				[
					'expected' => TEST_GOOD,
					'servers_settings' => [
						[
							'fields' => [
								'Name' => '@#$%^&*.',
								'Host' => '@#$%^&*.',
								'Base DN' => '@#$%^&*.',
								'Search attribute' => '@#$%^&*.'
							]
						]
					],
					'db_check' => [
						'userdirectory' => ['name' => '@#$%^&*.'],
						'userdirectory_ldap' => [
							'host' => '@#$%^&*.',
							'port' => '389',
							'base_dn' => '@#$%^&*.',
							'bind_dn' => '',
							'bind_password' => '',
							'search_attribute' => '@#$%^&*.'
						]
					]
				]
			],
			// #8 Long values.
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
								'Base DN' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value'.
										'_long_value_long_value_long_value_long_value_long_valong_value_long_value_long_'.
										'value_long_value_long_value_long_value_long_value_long_value_long_value_long_'.
										'value_long_value_long_v',
								'Search attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
										'long_value_long_value_long_value_long_value_long_value_long_va'
							]
						]
					],
					'db_check' => [
						'userdirectory' => [
							'name' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value_long_'.
									'value_long_value_long_value_long_value_long_va'
						],
						'userdirectory_ldap' => [
							'host' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value_long_'.
									'value_long_value_long_value_long_value_long_valong_value_long_value_long_value_long_'.
									'value_long_value_long_value_long_value_long_value_long_value_long_value_long_value_long_v',
							'port' => '389',
							'base_dn' => 'long_value_long_value_long_value_long_value_long_value_long_value_long_value_'.
									'long_value_long_value_long_value_long_value_long_valong_value_long_value_long_value_'.
									'long_value_long_value_long_value_long_value_long_value_long_value_long_value_long_value_long_v',
							'bind_dn' => '',
							'bind_password' => '',
							'search_attribute' => 'long_value_long_value_long_value_long_value_long_value_long_value_'.
								'long_value_long_value_long_value_long_value_long_value_long_va'
						]
					]
				]
			],
			// #9 LDAP server with every field filled.
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
						'userdirectory' => ['name' => 'LDAP'],
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
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 *
	 * Check authentication with LDAP settings.
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
				$ldap_form->query('id:bind_password')->one()->waitUntilVisible();
				$ldap_form->fill(['Bind password' => $ldap['Bind password']]);
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
	 * @param array $data	  data provider
	 * @param string $query  object to click for LDAP creating or updating
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
