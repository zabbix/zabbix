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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationAuthenticationSaml extends CWebTest {

	protected function onBeforeTestSuite() {
		if (!defined('PHPUNIT_SAML_TESTS_ENABLED') || !PHPUNIT_SAML_TESTS_ENABLED) {
			self::markTestSuiteSkipped();
		}
	}

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	public function getSamlData() {
		return [
			// Missing IdP entity ID
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "saml_idp_entityid": cannot be empty.'
				]
			],
			// Missing SSO service URL
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "saml_sso_url": cannot be empty.'
				]
			],
			// Missing Username attribute
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'SP entity ID' => 'SP'
					],
					'error' => 'Incorrect value for field "saml_username_attribute": cannot be empty.'
				]
			],
			// Missing SP entity ID
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSO service URL' => 'SSO',
						'IdP entity ID' => 'IdP',
						'Username attribute' => 'UA'
					],
					'error' => 'Incorrect value for field "saml_sp_entityid": cannot be empty.'
				]
			],
			// Configure SAML with only
			[
				[
					'fields' => [
						'IdP entity ID' => 'IdP',
						'SSO service URL' => 'SSO',
						'Username attribute' => 'UA',
						'SP entity ID' => 'SP'
					]
				]
			],
			// Various UTF-8 characters in SAML settings fields
			[
				[
					'fields' => [
						'IdP entity ID' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SSO service URL' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Username attribute' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SP entity ID' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SLO service URL' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'SP name ID format' => '!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
					]
				]
			],
			// SAML settings with leading and trailing spaces
			[
				[
					'fields' => [
						'IdP entity ID' => '   leading.trailing   ',
						'SSO service URL' => '   leading.trailing   ',
						'Username attribute' => '   leading.trailing   ',
						'SP entity ID' => '   leading.trailing   ',
						'SLO service URL' => '   leading.trailing   ',
						'SP name ID format' => '   leading.trailing   '
					],
					'trim' => true
				]
			],
			// Configure SAML with all possible parameters
			[
				[
					'fields' => [
						'IdP entity ID' => 'IdP_saml_zabbix.com',
						'SSO service URL' => 'SSO_saml_zabbix.com',
						'SLO service URL' => 'SLO_saml_zabbix.com',
						'Username attribute' => 'Username attribute',
						'SP entity ID' => 'SP entity ID',
						'SP name ID format' => 'SP name ID format',
						'Sign' => ['Messages', 'Assertions', 'AuthN requests', 'Logout requests', 'Logout responses'],
						'Encrypt' => ['Name ID', 'Assertions'],
						'Case-sensitive login' => true
					],
					'check_disabled' => true,
					'db_check' => [
						'saml_auth_enabled' => '1',
						'saml_idp_entityid' => 'IdP_saml_zabbix.com',
						'saml_sso_url' => 'SSO_saml_zabbix.com',
						'saml_slo_url' => 'SLO_saml_zabbix.com',
						'saml_username_attribute' => 'Username attribute',
						'saml_sp_entityid' => 'SP entity ID',
						'saml_nameid_format' => 'SP name ID format',
						'saml_sign_messages' => '1',
						'saml_sign_assertions' => '1',
						'saml_sign_authn_requests' => '1',
						'saml_sign_logout_requests' => '1',
						'saml_sign_logout_responses' => '1',
						'saml_encrypt_nameid' => '1',
						'saml_encrypt_assertions' => '1',
						'saml_case_sensitive' => '1'
					]
				]
			]
		];
	}

	/**
	 * @backup config
	 * @dataProvider getSamlData
	 */
	public function testFormAdministrationAuthenticationSaml_Configure($data) {
		$old_hash = CDBHelper::getHash('SELECT * FROM config');
		$this->page->login()->open('zabbix.php?action=authentication.edit');

		// Check that SAML settings are disabled by default and configure SAML authentication.
		$this->configureSamlAuthentication($data['fields'], true);

		// Check SAML settings update messages and, in case of successful update, check that field values were saved.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$form = $this->query('name:form_auth')->asForm()->one();
			$form->selectTab('SAML settings');
			$this->assertTrue($form->getField('Enable SAML authentication')->isChecked());
			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields'] = array_map('trim', $data['fields']);
			}
			$form->checkValue($data['fields']);
			if (array_key_exists('db_check', $data)) {
				$sql = 'SELECT saml_auth_enabled, saml_idp_entityid, saml_sso_url, saml_slo_url, saml_username_attribute,'.
						' saml_sp_entityid, saml_nameid_format, saml_sign_messages, saml_sign_assertions,'.
						' saml_sign_authn_requests, saml_sign_logout_requests, saml_sign_logout_responses,'.
						' saml_encrypt_nameid, saml_encrypt_assertions, saml_case_sensitive'.
						' FROM config';
				$result = CDBHelper::getRow($sql);
				$this->assertEquals($data['db_check'], $result);
			}
		}
	}

	public function testFormAdministrationAuthenticationSaml_CheckStatusChange() {
		$settings = [
			'IdP entity ID' => 'IdP',
			'SSO service URL' => 'SSO',
			'Username attribute' => 'UA',
			'SP entity ID' => 'SP'
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
		$form = $this->query('name:form_auth')->asForm()->one();
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
			// Login as zabbix super admin - case insensitive login
			[
				[
					'username' => 'admin'
				]
			],
			// Login as zabbix super admin - case sensitive login
			[
				[
					'username' => 'Admin',
					'custom_settings' => [
						'Case-sensitive login' => true
					]
				]
			],
			// Login as zabbix user
			[
				[
					'username' => 'user-zabbix'
				]
			],
			// Login as zabbix admin with custom url after login
			[
				[
					'username' => 'admin-zabbix',
					// Remove the 'regular_login' flag when ZBX-17663 is fixed.
					'regular_login' => true,
					'header' => '100 busiest triggers'
				]
			],
			// Login as zabbix admin with pre-defined login url (has higher priority then the configured url after login).
			[
				[
					'username' => 'admin-zabbix',
					'url' => 'zabbix.php?action=service.list',
					// Remove the 'regular_login' flag when ZBX-17663 is fixed.
					'regular_login' => true,
					'header' => 'Services'
				]
			],
			// Regular login
			[
				[
					'username' => 'Admin',
					'regular_login' => true
				]
			],
			// Incorrect IDP
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
			// UID exists only on IDP side.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'Admin2',
					'error_title' => 'You are not logged in',
					'error_details' => 'Incorrect user name or password or account is temporarily blocked.'
				]
			],
			// Login as Admin - case sensitive login - negative test.
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
	 * @backup config
	 * @dataProvider getAuthenticationDetails
	 */
	public function testFormAdministrationAuthenticationSaml_Authenticate($data) {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$settings = [
			'IdP entity ID' => PHPUNIT_IDP_ENTITY_ID,
			'SSO service URL' => PHPUNIT_SSO_SERVICE_URL,
			'SLO service URL' => PHPUNIT_SLO_SERVICE_URL,
			'Username attribute' => PHPUNIT_USERNAME_ATTRIBUTE,
			'SP entity ID' => PHPUNIT_SP_ENTITY_ID,
			'Case-sensitive login' => false
		];
		// Override particcular SAMl settings with values from data provider.
		if (array_key_exists('custom_settings', $data)) {
			foreach ($data['custom_settings'] as $key => $value) {
				$settings[$key] = $value;
			}
		}

		$this->configureSamlAuthentication($settings);

		// Logout and check that SAML authentication was enabled.
		$this->page->logout();
		// Login to a particcular url, if such is defined in data provider.
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
			$link = $this->query('link:Sign in with Single Sign-On (SAML)')->one()->waitUntilClickable()->click();
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
	 */
	private function configureSamlAuthentication($fields, $check_enabled = false) {
		$form = $this->query('name:form_auth')->asForm()->one();
		$form->selectTab('SAML settings');
		// Check that SAML settings are disabled by default.
		if ($check_enabled === true) {
			foreach($fields as $name => $value){
				$this->assertFalse($form->getField($name)->isEnabled());
			}
		}
		$form->getField('Enable SAML authentication')->check();
		$form->fill($fields);
		$form->submit();
		$this->page->waitUntilReady();
	}
}
