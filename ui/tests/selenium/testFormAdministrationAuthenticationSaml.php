<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

/**
 * @backup config
 */
class testFormAdministrationAuthenticationSaml extends CWebTest {

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
						'Case sensitive login' => true
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

		// Enable SAML authentication and then fill and submit SAML settings form.
		$form = $this->query('name:form_auth')->asForm()->one();
		$form->selectTab('SAML settings');

		// Check that SAML authentication fields are disabled if "Enable SAML authentication" checkbox is not set.
		if (CTestArrayHelper::get($data, 'check_disabled', false)) {
			foreach($data['fields'] as $name => $value){
				$this->assertTrue($form->getField($name)->isEnabled(false));
			}
		}

		$form->getField('Enable SAML authentication')->check();
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check SAML settings update messages and, in case of successful update, check that field values were saved.
		$message = CMessageElement::find()->one();
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertTrue($message->isBad());
			$this->assertEquals($data['error'], $message->getTitle());
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
		else {
			$this->assertTrue($message->isGood());
			$this->assertEquals('Authentication settings updated', $message->getTitle());

			$form->invalidate();
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

	public function testFormAdministrationAuthenticationSaml_EnableDisable() {
		$settings = [
			'IdP entity ID' => 'IdP',
			'SSO service URL' => 'SSO',
			'Username attribute' => 'UA',
			'SP entity ID' => 'SP'
		];
		$this->page->login()->open('zabbix.php?action=authentication.edit');

		// Enable SAML authentication.
		$form = $this->query('name:form_auth')->asForm()->one();
		$form->selectTab('SAML settings');
		$form->getField('Enable SAML authentication')->check();
		$form->fill($settings);
		$form->submit();
		// Logout and check that SAML authentication was enabled.
		$this->page->logout();
		$this->page->open('index.php')->waitUntilReady();
		$link = $this->query('link:Sign in with Single Sign-On (SAML)')->one()->waitUntilClickable();
		$this->assertContains('index_sso.php', $link->getAttribute('href'));
		// Login and disable SAML authentication.
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form->invalidate();
		$form->selectTab('SAML settings');
		$form->getField('Enable SAML authentication')->uncheck();
		$form->submit();
		// Logout and check that SAML authentication was disabled.
		$this->page->logout();
		$this->page->open('index.php')->waitUntilReady();
		$this->assertTrue($this->query('link:Sign in with Single Sign-On (SAML)')->count() === 0, 'Link must not exist.');
	}
}
