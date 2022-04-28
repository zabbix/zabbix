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
 * @backup config, userdirectory
 */
class testFormAdministrationAuthenticationLdap extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	public function getLdapData() {
		return [
			[
				[
					'error' => 'Incorrect value for field "authentication_type": LDAP is not configured.'
				]
			],
			[
				[
					'ldap_settings' => [],
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
			[
				[
					'ldap_settings' => [
						'Host' => 'ldap.forumsys.com'
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
			[
				[
					'ldap_settings' => [
						'Host' => 'ldap.forumsys.com',
						'Base DN' => 'dc=example,dc=com'
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "search_attribute": cannot be empty.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			[
				[
					'ldap_settings' => [
						'Host' => 'ldap.forumsys.com',
						'Base DN' => 'dc=example,dc=com',
						'Search attribute' => 'uid'
					],
					'ldap_error' => 'Invalid LDAP configuration',
					'ldap_error_details' => [
						'Incorrect value for field "name": cannot be empty.'
					],
					'error' => 'At least one LDAP server must exist.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'ldap_settings' => [
						'Name' => 'LDAP',
						'Host' => 'ldap.forumsys.com',
						'Port' => '389',
						'Base DN' => 'dc=example,dc=com',
						'Search attribute' => 'uid',
						'Bind DN' => 'cn=read-only-admin,dc=example,dc=com',
						'Bind password' => 'password'
					],
					'db_check' => [
						'name' => 'LDAP',
						'host' => 'ldap.forumsys.com',
						'port' => '389',
						'base_dn' => 'dc=example,dc=com',
						'bind_dn' => 'cn=read-only-admin,dc=example,dc=com',
						'bind_password' => 'password',
						'search_attribute' => 'uid'
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

		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->fill(['Default authentication' => 'LDAP']);

		// Configuration at 'LDAP settings' tab.
		if (array_key_exists('ldap_settings', $data)) {
			$form->selectTab('LDAP settings');
			$this->query('id:ldap_configured')->asCheckbox()->one()->check();
			$form->query('button:Add')->one()->click();
			$ldap_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
			$ldap_form->fill($data['ldap_settings'])->submit();

			// Check error message in ldap creation form.
			if (array_key_exists('ldap_error', $data)) {
				$this->assertMessage(TEST_BAD, $data['ldap_error'], $data['ldap_error_details']);
			}
		}

		$form->submit();
		$this->page->acceptAlert();

		if (CTestArrayHelper::get($data, 'expected', TEST_BAD) === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			// Check DB configuration.
			$sql = 'SELECT name, host, port, base_dn, bind_dn, bind_password, search_attribute FROM userdirectory';
			$result = CDBHelper::getRow($sql);
			$this->assertEquals($data['db_check'], $result);
		}
		else {
			$this->assertMessage(TEST_BAD, $data['error']);
		}
	}
}
