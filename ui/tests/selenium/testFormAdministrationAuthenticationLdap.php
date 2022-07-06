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

/**
 * @backup config
 */
class testFormAdministrationAuthenticationLdap extends CWebTest {

	public function getLdapData() {
		return [
			[
				[
					'error' => 'Incorrect value for field "authentication_type": LDAP is not configured.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Enable LDAP authentication' => true
					],
					'error' => 'Incorrect value for field "ldap_host": cannot be empty.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Enable LDAP authentication' => true,
						'LDAP host' => 'ipa.demo1.freeipa.org'
					],
					'error' => 'Incorrect value for field "ldap_base_dn": cannot be empty.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Enable LDAP authentication' => true,
						'LDAP host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org'
					],
					'error' => 'Incorrect value for field "ldap_search_attribute": cannot be empty.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Enable LDAP authentication' => true,
						'LDAP host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid'
					],
					'error' => 'Login name or password is incorrect.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Enable LDAP authentication' => true,
						'LDAP host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid',
						'Login' => 'admin'
					],
					'error' => 'Login name or password is incorrect.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Enable LDAP authentication' => true,
						'LDAP host' => 'ipa.demo1.freeipa.org',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid',
						'Bind password' => 'Secret123'
					],
					'error' => 'Incorrect value for field "ldap_bind_dn": cannot be empty.'
				]
			],
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'ldap_settings' => [
						'Enable LDAP authentication' => true,
						'LDAP host' => 'ipa.demo1.freeipa.org',
						'Port' => '389',
						'Base DN' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Search attribute' => 'uid',
						'Bind DN' => 'uid=admin,cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'Case-sensitive login' => true,
						'Bind password' => 'Secret123',
						'Login' => 'admin',
						'User password' => 'Secret123'
					],
					'db_check' => [
						'authentication_type' => '1',
						'ldap_host' => 'ipa.demo1.freeipa.org',
						'ldap_port' => '389',
						'ldap_base_dn' => 'cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'ldap_bind_dn' => 'uid=admin,cn=users,cn=accounts,dc=demo1,dc=freeipa,dc=org',
						'ldap_bind_password' => 'Secret123',
						'ldap_search_attribute' => 'uid',
						'http_auth_enabled' => '0',
						'http_login_form' => '0',
						'http_strip_domains' => '',
						'http_case_sensitive' => '1',
						'ldap_configured' => '1',
						'ldap_case_sensitive' => '1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLdapData
	 *
	 * Check authentication with LDAP settings.
	 */
	public function testFormAdministrationAuthenticationLdap_CheckSettings($data) {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$this->assertEquals('Authentication', $this->query('tag:h1')->one()->getText());
		$this->page->assertTitle('Configuration of authentication');

		$form = $this->query('name:form_auth')->asForm()->one();
		$form->fill(['Default authentication' => 'LDAP']);

		// Configuration at 'LDAP settings' tab.
		if (array_key_exists('ldap_settings', $data)) {
			$form->selectTab('LDAP settings');
			$form->fill($data['ldap_settings']);
		}

		$form->submit();
		$this->page->acceptAlert();

		$message = CMessageElement::find()->one();
		if (array_key_exists('error', $data)) {
			$this->assertTrue($message->isBad());
			$this->assertEquals($data['error'], $message->getTitle());
		}
		else {
			$this->assertTrue($message->isGood());
			$this->assertEquals('Authentication settings updated', $message->getTitle());
			// Check DB configuration.
			$sql = 'SELECT authentication_type,ldap_host,ldap_port,ldap_base_dn,ldap_bind_dn,ldap_bind_password,'.
					'ldap_search_attribute,http_auth_enabled,http_login_form,http_strip_domains,http_case_sensitive,'.
					'ldap_configured,ldap_case_sensitive'.
					' FROM config';
			$result = CDBHelper::getRow($sql);
			$this->assertEquals($data['db_check'], $result);
		}
	}
}
