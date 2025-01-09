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
 * @backup config
 */
class testUsersAuthenticationSaml extends testFormAuthentication {

	protected function onBeforeTestSuite() {
		if (!defined('PHPUNIT_SAML_TESTS_ENABLED') || !PHPUNIT_SAML_TESTS_ENABLED) {
			self::markTestSuiteSkipped();
		}
	}

	public function testUsersAuthenticationSaml_Layout() {
		$saml_form = $this->openFormAndCheckBasics('SAML');

		// Check SAML form default values.
		$saml_fields = [
			'Enable JIT provisioning' => ['value' => false, 'visible' => true],
			'IdP entity ID' => ['value' => '', 'visible' => true, 'maxlength' => 1024],
			'SSO service URL' => ['value' => '', 'visible' => true, 'maxlength' => 2048],
			'SLO service URL' => ['value' => '', 'visible' => true, 'maxlength' => 2048],
			'Username attribute' => ['value' => '', 'visible' => true, 'maxlength' => 128],
			'SP entity ID' => ['value' => '', 'visible' => true, 'maxlength' => 1024],
			'SP name ID format' => ['value' => '', 'visible' => true, 'maxlength' => 2048,
					'placeholder' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'
			],
			'id:sign_messages' => ['value' => false, 'visible' => true],
			'id:sign_assertions' => ['value' => false, 'visible' => true],
			'id:sign_authn_requests' => ['value' => false, 'visible' => true],
			'id:sign_logout_requests' => ['value' => false, 'visible' => true],
			'id:sign_logout_responses' => ['value' => false, 'visible' => true],
			'id:encrypt_nameid' => ['value' => false, 'visible' => true],
			'id:encrypt_assertions' => ['value' => false, 'visible' => true],
			'Case-sensitive login' => ['value' => false, 'visible' => true],
			'Configure JIT provisioning' => ['value' => false, 'visible' => true],
			'Group name attribute' => ['value' => '', 'visible' => false, 'maxlength' => 255],
			'User name attribute' => ['value' => '', 'visible' => false, 'maxlength' => 255],
			'User last name attribute' => ['value' => '', 'visible' => false, 'maxlength' => 255],
			'User group mapping' => ['visible' => false],
			'Media type mapping' => ['visible' => false],
			'Enable SCIM provisioning' => ['value' => false, 'visible' => false]
		];

		foreach ($saml_fields as $field => $attributes) {
			$this->assertEquals($attributes['visible'], $saml_form->getField($field)->isVisible());
			$this->assertFalse($saml_form->getField($field)->isEnabled());

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $saml_form->getField($field)->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $saml_form->getField($field)->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $saml_form->getField($field)->getAttribute('placeholder'));
			}
		}

		// Check visible mandatory fields.
		$this->assertEquals(['IdP entity ID', 'SSO service URL', 'Username attribute', 'SP entity ID'],
				$saml_form->getRequiredLabels()
		);

		// Check invisible mandatory field.
		foreach (['Group name attribute', 'User group mapping'] as $manadatory_field) {
			$saml_form->isRequired($manadatory_field);
		}

		// Enable SAML and check that fields become enabled.
		$saml_form->fill(['Enable SAML authentication' => true]);

		foreach (array_keys($saml_fields) as $label) {
			$this->assertTrue($saml_form->getField($label)->isEnabled());
		}

		// Check that JIT fields remain invisible and depend on "Configure JIT" checkbox.
		$jit_fields = array_slice($saml_fields, 16);

		foreach ([false, true] as $jit_status) {
			$saml_form->fill(['Configure JIT provisioning' => $jit_status]);

			foreach (array_keys($jit_fields) as $label) {
				$this->assertTrue($saml_form->getField($label)->isVisible($jit_status));
			}
		}

		$hintboxes = [
			'Media type mapping' => "Map user's SAML media attributes (e.g. email) to Zabbix user media for".
				" sending notifications."
		];

		// Mapping tables headers.
		$mapping_tables = [
			'User group mapping' => [
				'id' => 'saml-group-table',
				'headers' => ['SAML group pattern', 'User groups', 'User role', 'Action']
			],
			'Media type mapping' => [
				'id' => 'saml-media-type-mapping-table',
				'headers' => ['Name', 'Media type', 'Attribute', 'Action']
			]
		];

		$this->checkFormHintsAndMapping($saml_form, $hintboxes, $mapping_tables, 'SAML');
	}

	public function getConfigureValidationData() {
		return [
			// #0 Missing IdP entity ID.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "idp_entityid": cannot be empty.'
				]
			],
			// #1 Missing SSO service URL.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "sso_url": cannot be empty.'
				]
			],
			// #2 Missing Username attribute.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "username_attribute": cannot be empty.'
				]
			],
			// #3 Missing SP entity ID.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA'
					],
					'error' => 'Incorrect value for field "sp_entityid": cannot be empty.'
				]
			],
			// #4 Missing Group name attribute.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP entity',
						'Configure JIT provisioning' => true
					],
					'error' => 'Incorrect value for field "saml_group_name": cannot be empty.'
				]
			],
			// #5 Missing Group name attribute.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP entity',
						'Configure JIT provisioning' => true,
						'Group name attribute' => 'group name attribute'
					],
					'error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
				]
			],
			// #6 Group mapping dialog form validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'Configure JIT provisioning' => true,
						'Group name attribute' => 'group name attribute'
					],
					'User group mapping' => [[]],
					'mapping_error' => 'Invalid user group mapping configuration.',
					'mapping_error_details' => [
						'Field "roleid" is mandatory.',
						'Incorrect value for field "name": cannot be empty.',
						'Field "user_groups" is mandatory.'
					],
					'error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
				]
			],
			// #7 Media mapping dialog form validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP entity',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP',
						'Configure JIT provisioning' => true,
						'Group name attribute' => 'group name attribute'
					],
					'User group mapping' => [[]],
					'mapping_error' => 'Invalid media type mapping configuration.',
					'mapping_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "attribute": cannot be empty.'
					],
					'error' => 'Invalid parameter "/1/provision_groups": cannot be empty.'
				]
			]
		];
	}

	public function getConfigureData() {
		return [
			// #0 Configure SAML with minimal fields.
			[
				[
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'db_check' => [
						'config' => [
							[
								'saml_auth_enabled' => 1,
								'saml_case_sensitive' => 0,
								'saml_jit_status' => 0
							]
						],
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'IdP',
								'sso_url' => 'SSO',
								'username_attribute' => 'UA',
								'sp_entityid' => 'SP'
							]
						]
					]
				]
			],
			// #1 Various UTF-8 characters in SAML settings fields + All possible fields with JIT configuration.
			[
				[
					'Deprovisioned users group' => 'Disabled',
					'fields' => [
						'Enable JIT provisioning' => true,
						'IdP entity ID' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SSO service URL' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SLO service URL' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Username attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SP entity ID' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						// Sign.
						'id:sign_messages' => true,
						'id:sign_assertions' => true,
						'id:sign_authn_requests' => true,
						'id:sign_logout_requests' => true,
						'id:sign_logout_responses' => true,
						// Encrypt.
						'id:encrypt_nameid' => true,
						'id:encrypt_assertions' => true,
						'Case-sensitive login' => true,
						'Configure JIT provisioning' => true,
						'SP name ID format' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Group name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'User name attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'User last name attribute'=> '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Enable SCIM provisioning' => true
					],
					'User group mapping' => [
						[
							'SAML group pattern' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'Media type mapping' => [
						[
							'Name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
							'Media type' => 'Discord',
							'Attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
						]
					],
					'db_check' => [
						'config' => [
							[
								'saml_auth_enabled' => 1,
								'saml_case_sensitive' => 1,
								'saml_jit_status' => 1
							]
						],
						'userdirectory_saml' => [
							[
								'idp_entityid' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'sso_url' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'slo_url' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'username_attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'sp_entityid' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'sign_messages' => 1,
								'sign_assertions' => 1,
								'sign_authn_requests' => 1,
								'sign_logout_requests' => 1,
								'sign_logout_responses' => 1,
								'encrypt_nameid' => 1,
								'encrypt_assertions' => 1,
								'nameid_format' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
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
						],
						'userdirectory_media' => [
							[
								'name' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
								'mediatypeid' => 10,
								'attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
							]
						]
					]
				]
			],
			// #2 SAML settings with leading and trailing spaces.
			[
				[
					'trim' => true,
					'fields' => [
						'IdP entity ID' => '   leading.trailing   ',
						'SSO service URL' => '   leading.trailing   ',
						'SLO service URL' => '   leading.trailing   ',
						'Username attribute' => '   leading.trailing   ',
						'SP entity ID' => '   leading.trailing   ',
						'SP name ID format' => '   leading.trailing   ',
						'Configure JIT provisioning' => true,
						'Group name attribute' => '   leading.trailing   ',
						'User name attribute' => '   leading.trailing   ',
						'User last name attribute'=> '   leading.trailing   '
					],
					'User group mapping' => [
						[
							'SAML group pattern' => '   leading.trailing   ',
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'Media type mapping' => [
						[
							'Name' => '   leading.trailing   ',
							'Media type' => 'Discord',
							'Attribute' => 'leading.trailing'
						]
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'leading.trailing',
								'sso_url' => 'leading.trailing',
								'slo_url' => 'leading.trailing',
								'username_attribute' => 'leading.trailing',
								'sp_entityid' => 'leading.trailing',
								'nameid_format' => 'leading.trailing',
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
			// #3 SAML settings with long values in fields.
			[
				[
					'trim' => true,
					'fields' => [
						'IdP entity ID' => STRING_1024,
						'SSO service URL' => STRING_2048,
						'SLO service URL' => STRING_2048,
						'Username attribute' => STRING_128,
						'SP entity ID' => STRING_1024,
						'SP name ID format' => STRING_2048,
						'Configure JIT provisioning' => true,
						'Group name attribute' => STRING_255,
						'User name attribute' => STRING_255,
						'User last name attribute'=> STRING_255
					],
					'User group mapping' => [
						[
							'SAML group pattern' => STRING_255,
							'User groups' => 'Test timezone',
							'User role' => 'User role'
						]
					],
					'Media type mapping' => [
						[
							// TODO: Change this to 255 long string, if ZBX-22236 is fixed.
							'Name' => '1ong_value_long_value_long_value_long_value_long_value_lon',
							'Media type' => 'Discord',
							'Attribute' => STRING_255
						]
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => STRING_1024,
								'sso_url' => STRING_2048,
								'slo_url' => STRING_2048,
								'username_attribute' => STRING_128,
								'sp_entityid' => STRING_1024,
								'nameid_format' => STRING_2048,
								'group_name' => STRING_255,
								'user_username' => STRING_255,
								'user_lastname' => STRING_255
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
								'name' => '1ong_value_long_value_long_value_long_value_long_value_lon',
								'mediatypeid' => 10,
								'attribute' => STRING_255
							]
						]
					]
				]
			],
			// #4 Configure SAML with all parameters, but no JIT configuration.
			[
				[
					'Deprovisioned users group' => 'Disabled',
					'fields' => [
						'Enable JIT provisioning' => true,
						'IdP entity ID' => 'IdP_saml_zabbix.com',
						'SSO service URL' => 'SSO_saml_zabbix.com',
						'SLO service URL' => 'SLO_saml_zabbix.com',
						'Username attribute' => 'Username attribute',
						'SP entity ID' => 'SP entity ID',
						'SP name ID format' => 'SP name ID format',
						// Sign.
						'id:sign_messages' => true,
						'id:sign_assertions' => true,
						'id:sign_authn_requests' => true,
						'id:sign_logout_requests' => true,
						'id:sign_logout_responses' => true,
						// Encrypt.
						'id:encrypt_nameid' => true,
						'id:encrypt_assertions' => true,
						'Case-sensitive login' => true
					],
					'db_check' => [
						'userdirectory_saml' => [
							[
								'idp_entityid' => 'IdP_saml_zabbix.com',
								'sso_url' => 'SSO_saml_zabbix.com',
								'slo_url' => 'SLO_saml_zabbix.com',
								'username_attribute' => 'Username attribute',
								'sp_entityid' => 'SP entity ID',
								'nameid_format' => 'SP name ID format',
								'sign_messages' => 1,
								'sign_assertions' => 1,
								'sign_authn_requests' => 1,
								'sign_logout_requests' => 1,
								'sign_logout_responses' => 1,
								'encrypt_nameid' => 1,
								'encrypt_assertions' => 1
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getConfigureValidationData
	 */
	public function testUsersAuthenticationSaml_ConfigureValidation($data) {
		$this->testSamlConfiguration($data);
	}

	/**
	 * @backup config
	 *
	 * @dataProvider getConfigureData
	 */
	public function testUsersAuthenticationSaml_Configure($data) {
		$this->testSamlConfiguration($data);
	}

	private function testSamlConfiguration($data) {
		$old_hash = CDBHelper::getHash('SELECT * FROM config');
		$this->page->login()->open('zabbix.php?action=authentication.edit');

		// Check that SAML settings are disabled by default and configure SAML authentication.
		$this->configureSamlAuthentication($data);

		// Check SAML settings update messages and, in case of successful update, check that field values were saved.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update authentication',  $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$form = $this->query('id:authentication-form')->asForm()->one();
			$form->selectTab('SAML settings');
			$this->assertTrue($form->getField('Enable SAML authentication')->isChecked());

			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields'] = array_map('trim', $data['fields']);
			}

			$form->checkValue($data['fields']);

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
	}

	public function testUsersAuthenticationSaml_CheckStatusChange() {
		$settings = [
			'fields' => [
				'IdP entity ID' => 'IdP',
				'SSO service URL' => 'SSO',
				'Username attribute' => 'UA',
				'SP entity ID' => 'SP'
			]
		];
		$this->page->login()->open('zabbix.php?action=authentication.edit');

		$this->configureSamlAuthentication($settings);

		// Logout and check that SAML authentication was enabled.
		$this->page->logout();
		$this->page->open('index.php')->waitUntilReady();
		$link = $this->query('link:Sign in with Single Sign-On (SAML)')->one()->waitUntilClickable();
		$this->assertStringContainsString('index_sso.php', $link->getAttribute('href'));

		// Login and disable SAML authentication.
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('SAML settings');
		$form->getField('Enable SAML authentication')->uncheck();
		$form->submit();

		// Logout and check that SAML authentication was disabled.
		$this->page->logout();
		$this->page->open('index.php')->waitUntilReady();
		$this->assertTrue($this->query('link:Sign in with Single Sign-On (SAML)')->count() === 0, 'Link must not exist.');
	}

	public function getAuthenticationDetails() {
		return [
			// #0 Login as zabbix super admin - case insensitive login.
			[
				[
					'username' => 'admin'
				]
			],
			// #1 Login as zabbix super admin - case sensitive login.
			[
				[
					'username' => 'Admin',
					'custom_settings' => [
						'Case-sensitive login' => true
					]
				]
			],
			// #2 Login as zabbix user.
			[
				[
					'username' => 'user-zabbix'
				]
			],
			// #3 Login as zabbix admin with custom url after login.
			[
				[
					'username' => 'admin-zabbix',
					'header' => 'Top 100 triggers'
				]
			],
			// #4 Login as zabbix admin with pre-defined login url (has higher priority then the configured url after login).
			[
				[
					'username' => 'admin-zabbix',
					'url' => 'zabbix.php?action=service.list',
					'header' => 'Services'
				]
			],
			// #5 Regular login.
			[
				[
					'username' => 'Admin',
					'regular_login' => true
				]
			],
			// #6 Incorrect IDP.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'admin',
					'custom_settings' => [
						'IdP entity ID' => 'metadata'
					],
					'error_title' => 'You are not logged in',
					'error_details' => 'Invalid issuer in the Assertion/Response'
				]
			],
			// #7 UID exists only on IDP side.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'Admin2',
					'error_title' => 'You are not logged in',
					'error_details' => 'Incorrect user name or password or account is temporarily blocked.'
				]
			],
			// #8 Login as Admin - case sensitive login - negative test.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'admin',
					'custom_settings' => [
						'Case-sensitive login' => true
					],
					'error_title' => 'You are not logged in',
					'error_details' => 'Incorrect user name or password or account is temporarily blocked.'
				]
			]
		];
	}

	/**
	 * @ignoreBrowserErrors
	 * This annotation is put here for avoiding the following errors:
	 * /favicon.ico - Failed to load resource: the server responded with a status of 404 (Not Found).
	 *
	 * @backup config
	 *
	 * @dataProvider getAuthenticationDetails
	 */
	public function testUsersAuthenticationSaml_Authenticate($data) {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$settings = [
			'fields' => [
				'IdP entity ID' => PHPUNIT_IDP_ENTITY_ID,
				'SSO service URL' => PHPUNIT_SSO_SERVICE_URL,
				'SLO service URL' => PHPUNIT_SLO_SERVICE_URL,
				'Username attribute' => PHPUNIT_USERNAME_ATTRIBUTE,
				'SP entity ID' => PHPUNIT_SP_ENTITY_ID,
				'Case-sensitive login' => false
			]
		];

		// Override particular SAML settings with values from data provider.
		if (array_key_exists('custom_settings', $data)) {
			foreach ($data['custom_settings'] as $key => $value) {
				$settings['fields'][$key] = $value;
			}
		}

		$this->configureSamlAuthentication($settings);

		// Logout and check that SAML authentication was enabled.
		$this->page->logout();

		// Login to a particular url, if such is defined in data provider.
		if (array_key_exists('url', $data)) {
			$this->page->open($data['url'])->waitUntilReady();
			$this->query('button:Login')->one()->click();
			$this->page->waitUntilReady();
		}
		else {
			$this->page->open('index.php')->waitUntilReady();
		}

		// Login via regular Sing-in form or via SAML.
		if (CTestArrayHelper::get($data, 'regular_login', false)) {
			$this->query('id:name')->waitUntilVisible()->one()->fill($data['username']);
			$this->query('id:password')->one()->fill('zabbix');
			$this->query('button:Sign in')->one()->click();
		}
		else {
			$this->query('link:Sign in with Single Sign-On (SAML)')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->query('id:username')->one()->waitUntilVisible()->fill($data['username']);
			$this->query('id:password')->one()->waitUntilVisible()->fill('zabbix');
			$this->query('button:Login')-> one()->click();
		}

		$this->page->waitUntilReady();

		// Check error message in case of negative test.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['error_title'], $data['error_details']);
			return;
		}
		// Check the header of the page that was displayed to the user after login.
		$header = CTestArrayHelper::get($data, 'header', 'Global view');
		$this->assertEquals($header, $this->query('tag:h1')->one()->getText());

		// Make sure that it is possible to log out.
		$this->query('link:Sign out')->one()->click();
		$this->page->waitUntilReady();
		$this->assertStringContainsString('index.php', $this->page->getCurrentUrl());
	}

	/**
	 * Function checks that SAML settings are disabled by default, if the corresponding flag is specified, enables and
	 * fills SAML settings, and submits the form.
	 *
	 * @param array    $data    data provider
	 */
	private function configureSamlAuthentication($data) {
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('SAML settings');
		$form->getField('Enable SAML authentication')->check();
		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data['fields'], 'Configure JIT provisioning')) {
			$success = (array_key_exists('mapping_error', $data)) ? false : true;

			if (array_key_exists('User group mapping', $data)) {
				$this->setMapping($data['User group mapping'], $form, 'User group mapping', $success);
			}

			if (array_key_exists('Media type mapping', $data)) {
				$this->setMapping($data['Media type mapping'], $form, 'Media type mapping', $success);
			}
		}

		if (array_key_exists('Deprovisioned users group', $data)) {
			$form->selectTab('Authentication');
			$form->fill(['Deprovisioned users group' => 'Disabled']);
		}

		$form->submit();
		$this->page->waitUntilReady();
	}
}
