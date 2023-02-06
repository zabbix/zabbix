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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @onBefore  prepareTestData
 *
 * @backup config
 */
class testAuthentication extends CAPITest {

	public static function authentication_get_data() {
		return [
			'Test getting authentication general data' => [
				'authentication' => [
					'output' => ['authentication_type', 'passwd_min_length', 'passwd_check_rules', 'http_auth_enabled',
						'http_login_form', 'http_strip_domains', 'http_case_sensitive', 'ldap_configured',
						'ldap_userdirectoryid', 'saml_auth_enabled', 'saml_idp_entityid', 'saml_sso_url', 'saml_slo_url',
						'saml_username_attribute', 'saml_sp_entityid', 'saml_nameid_format', 'saml_sign_messages',
						'saml_sign_assertions', 'saml_sign_authn_requests', 'saml_sign_logout_requests',
						'saml_sign_logout_responses', 'saml_encrypt_nameid', 'saml_encrypt_assertions',
						'saml_case_sensitive'
					]
				],
				'get_result' => [
					// General fields.
					'authentication_type' => [ZBX_AUTH_INTERNAL, ZBX_AUTH_LDAP],
					'passwd_min_length' => ['min' => 1, 'max' => 70],
					'passwd_check_rules' => [
						'min' => 0x00,
						'max' => (PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE)
					],

					// HTTP auth fields.
					'http_auth_enabled' => [ZBX_AUTH_HTTP_DISABLED, ZBX_AUTH_HTTP_ENABLED],
					'http_login_form' => [ZBX_AUTH_FORM_ZABBIX, ZBX_AUTH_FORM_HTTP],
					'http_strip_domains' => '',
					'http_case_sensitive' => [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE],

					// LDAP fields.
					'ldap_configured' =>	[ZBX_AUTH_LDAP_DISABLED, ZBX_AUTH_LDAP_ENABLED],

					// SAML fields.
					'saml_auth_enabled' => [ZBX_AUTH_SAML_DISABLED, ZBX_AUTH_SAML_ENABLED],
					'saml_idp_entityid' => '',
					'saml_sso_url' => '',
					'saml_slo_url' => '',
					'saml_username_attribute' => '',
					'saml_sp_entityid' => '',
					'saml_nameid_format' =>	'',
					'saml_sign_messages' =>	[0, 1],
					'saml_sign_assertions' => [0, 1],
					'saml_sign_authn_requests' => [0, 1],
					'saml_sign_logout_requests' => [0, 1],
					'saml_sign_logout_responses' => [0, 1],
					'saml_encrypt_nameid' => [0, 1],
					'saml_encrypt_assertions' => [0, 1],
					'saml_case_sensitive' => [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider authentication_get_data
	 */
	public function testAuthentication_Get($authentication, $get_result, $expected_error) {
		$result = $this->call('authentication.get', $authentication);

		if ($expected_error === null) {
			$result = $result['result'];
			// General fields.
			$this->assertContains($result['authentication_type'], $get_result['authentication_type']);
			$this->assertGreaterThanOrEqual($get_result['passwd_min_length']['min'], $result['passwd_min_length']);
			$this->assertLessThanOrEqual($get_result['passwd_min_length']['max'], $result['passwd_min_length']);
			$this->assertGreaterThanOrEqual($get_result['passwd_check_rules']['min'], $result['passwd_check_rules']);
			$this->assertLessThanOrEqual($get_result['passwd_check_rules']['max'], $result['passwd_check_rules']);

			// HTTP auth fields.
			$this->assertContains($result['http_auth_enabled'], $get_result['http_auth_enabled']);
			$this->assertContains($result['http_login_form'], $get_result['http_login_form']);
			$this->assertContains('http_strip_domains', array_keys($result));
			$this->assertContains($result['http_case_sensitive'], $get_result['http_case_sensitive']);

			// LDAP fields.
			$this->assertContains($result['ldap_configured'], $get_result['ldap_configured']);

			// SAML fields.
			$this->assertContains($result['saml_auth_enabled'], $get_result['saml_auth_enabled']);
			$this->assertContains('saml_idp_entityid', array_keys($result));
			$this->assertContains('saml_sso_url', array_keys($result));
			$this->assertContains('saml_slo_url', array_keys($result));
			$this->assertContains('saml_username_attribute', array_keys($result));
			$this->assertContains('saml_sp_entityid', array_keys($result));
			$this->assertContains('saml_nameid_format', array_keys($result));
			$this->assertContains($result['saml_sign_messages'], $get_result['saml_sign_messages']);
			$this->assertContains($result['saml_sign_assertions'], $get_result['saml_sign_assertions']);
			$this->assertContains($result['saml_sign_authn_requests'], $get_result['saml_sign_authn_requests']);
			$this->assertContains($result['saml_sign_logout_requests'], $get_result['saml_sign_logout_requests']);
			$this->assertContains($result['saml_sign_logout_responses'], $get_result['saml_sign_logout_responses']);
			$this->assertContains($result['saml_encrypt_nameid'], $get_result['saml_encrypt_nameid']);
			$this->assertContains($result['saml_encrypt_assertions'], $get_result['saml_encrypt_assertions']);
			$this->assertContains($result['saml_case_sensitive'], $get_result['saml_case_sensitive']);
		}
	}

	public static function authentication_update_data_invalid() {
		return [
			// Invalid general auth tests.
			'Test invalid authentication type' => [
				'authentication' => [
					'authentication_type' => 999
				],
				'expected_error' => 'Invalid parameter "/authentication_type": value must be one of '.
					implode(', ', [ZBX_AUTH_INTERNAL, ZBX_AUTH_LDAP]).'.'
			],
			'Test invalid password min length' => [
				'authentication' => [
					'passwd_min_length' => 999
				],
				'expected_error' => 'Invalid parameter "/passwd_min_length": value must be one of 1-70.'
			],
			'Test invalid password rules' => [
				'authentication' => [
					'passwd_check_rules' => 999
				],
				'expected_error' => 'Invalid parameter "/passwd_check_rules": value must be one of 0-'.
					(PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE).'.'
			],

			// Invalid HTTP auth tests.
			'Test invalid HTTP auth' => [
				'authentication' => [
					'http_auth_enabled' => 999
				],
				'expected_error' => 'Invalid parameter "/http_auth_enabled": value must be one of '.
					implode(', ', [ZBX_AUTH_HTTP_DISABLED, ZBX_AUTH_HTTP_ENABLED]).'.'
			],
			'Test invalid HTTP form' => [
				'authentication' => [
					'http_login_form' => 999
				],
				'expected_error' => 'Invalid parameter "/http_login_form": value must be one of '.
					implode(', ', [ZBX_AUTH_FORM_ZABBIX, ZBX_AUTH_FORM_HTTP]).'.'
			],
			'Test invalid case sensitive for HTTP auth' => [
				'authentication' => [
					'http_case_sensitive' => 999
				],
				'expected_error' => 'Invalid parameter "/http_case_sensitive": value must be one of '.
					implode(', ', [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE]).'.'
			],

			// Invalid LDAP auth tests.
			'Test invalid LDAP auth' => [
				'authentication' => [
					'ldap_configured' => 999
				],
				'expected_error' => 'Invalid parameter "/ldap_configured": value must be one of '.
					implode(', ', [ZBX_AUTH_LDAP_DISABLED, ZBX_AUTH_LDAP_ENABLED]).'.'
			],
			'Test invalid userdirectoryid' => [
				'authentication' => [
					'ldap_userdirectoryid' => 'userdirectory_invalidid_1'
				],
				'expected_error' => 'Invalid parameter "/ldap_userdirectoryid": referred object does not exist.'
			],
			'Cannot set default authentication ldap when ldap is disabled' => [
				'authentication' => [
					'authentication_type' => ZBX_AUTH_LDAP,
					'ldap_configured' => ZBX_AUTH_LDAP_DISABLED
				],
				'expected_error' => 'Incorrect value for field "/authentication_type": LDAP must be enabled.'
			],

			// Invalid SAML auth tests.
			'Test invalid SAML auth' => [
				'authentication' => [
					'saml_auth_enabled' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_auth_enabled": value must be one of '.
					implode(', ', [ZBX_AUTH_SAML_DISABLED, ZBX_AUTH_SAML_ENABLED]).'.'
			],
			'Test invalid SAML IdP entity ID' => [
				'authentication' => [
					'saml_idp_entityid' => ''
				],
				'expected_error' => 'Invalid parameter "/saml_idp_entityid": cannot be empty.'
			],
			'Test invalid SAML SSO service URL' => [
				'authentication' => [
					'saml_sso_url' => ''
				],
				'expected_error' => 'Invalid parameter "/saml_sso_url": cannot be empty.'
			],
			'Test invalid SAML Username attribute' => [
				'authentication' => [
					'saml_username_attribute' => ''
				],
				'expected_error' => 'Invalid parameter "/saml_username_attribute": cannot be empty.'
			],
			'Test invalid SAML SP entity ID' => [
				'authentication' => [
					'saml_sp_entityid' => ''
				],
				'expected_error' => 'Invalid parameter "/saml_sp_entityid": cannot be empty.'
			],
			'Test invalid SAML Sign messages' => [
				'authentication' => [
					'saml_sign_messages' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_sign_messages": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign assertions' => [
				'authentication' => [
					'saml_sign_assertions' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_sign_assertions": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign authN requests' => [
				'authentication' => [
					'saml_sign_authn_requests' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_sign_authn_requests": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign logout requests' => [
				'authentication' => [
					'saml_sign_logout_requests' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_sign_logout_requests": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Sign logout responses' => [
				'authentication' => [
					'saml_sign_logout_responses' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_sign_logout_responses": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Encrypt name ID' => [
				'authentication' => [
					'saml_encrypt_nameid' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_encrypt_nameid": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid SAML Encrypt assertions' => [
				'authentication' => [
					'saml_encrypt_assertions' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_encrypt_assertions": value must be one of '.
					implode(', ', [0, 1]).'.'
			],
			'Test invalid case sensitive for SAML auth' => [
				'authentication' => [
					'saml_case_sensitive' => 999
				],
				'expected_error' => 'Invalid parameter "/saml_case_sensitive": value must be one of '.
					implode(', ', [ZBX_AUTH_CASE_INSENSITIVE, ZBX_AUTH_CASE_SENSITIVE]).'.'
			]
		];
	}

	public static function authentication_update_data_valid() {
		return [
			// Cannot test valid authentication change, because that will log out the current user.

			// Valid general auth tests.
			'Test valid password min length' => [
				'authentication' => [
					'passwd_min_length' => 32
				],
				'expected_error' => null
			],
			'Test valid password rules' => [
				'authentication' => [
					'passwd_check_rules' => (PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL)
				],
				'expected_error' => null
			],

			// Valid HTTP auth tests.
			'Test valid HTTP auth' => [
				'authentication' => [
					'http_auth_enabled' => ZBX_AUTH_HTTP_ENABLED
				],
				'expected_error' => null
			],
			'Test valid HTTP form' => [
				'authentication' => [
					'http_login_form' => ZBX_AUTH_FORM_HTTP
				],
				'expected_error' => null
			],
			'Test update remove domains' => [
				'authentication' => [
					'http_strip_domains' => 'text.string'
				],
				'expected_error' => null
			],
			'Test valid case sensitive for HTTP auth' => [
				'authentication' => [
					'http_case_sensitive' => ZBX_AUTH_CASE_SENSITIVE
				],
				'expected_error' => null
			],

			// Valid LDAP auth tests.
			'Test valid LDAP auth' => [
				'authentication' => [
					'ldap_configured' => ZBX_AUTH_LDAP_ENABLED
				],
				'expected_error' => null
			],
			'Test userdirectory can be set as default server' => [
				'authentication' => [
					'ldap_configured' => ZBX_AUTH_LDAP_ENABLED,
					'ldap_userdirectoryid' => 'userdirectory_1'
				],
				'expected_error' => null
			],
			// Valid SAML auth tests.
			'Test valid SAML auth' => [
				'authentication' => [
					'saml_auth_enabled' => ZBX_AUTH_SAML_ENABLED
				],
				'expected_error' => null
			],
			'Test valid SAML IdP entity ID' => [
				'authentication' => [
					'saml_idp_entityid' => 'saml.idp.entity.id'
				],
				'expected_error' => null
			],
			'Test valid SAML SSO service URL' => [
				'authentication' => [
					'saml_sso_url' => 'saml.sso.url'
				],
				'expected_error' => null
			],
			'Test valid SAML SLO service URL' => [
				'authentication' => [
					'saml_slo_url' => 'saml.slo.url'
				],
				'expected_error' => null
			],
			'Test valid SAML Username attribute' => [
				'authentication' => [
					'saml_username_attribute' => 'saml.username.attribute'
				],
				'expected_error' => null
			],
			'Test valid SAML SP entity ID' => [
				'authentication' => [
					'saml_sp_entityid' => 'saml.sp.entityid'
				],
				'expected_error' => null
			],
			'Test valid SAML SP name ID format' => [
				'authentication' => [
					'saml_nameid_format' => 'saml.nameid.format'
				],
				'expected_error' => null
			],
			'Test valid SAML Sign messages' => [
				'authentication' => [
					'saml_sign_messages' => 1
				],
				'expected_error' => null
			],
			'Test valid SAML Sign assertions' => [
				'authentication' => [
					'saml_sign_assertions' => 1
				],
				'expected_error' => null
			],
			'Test valid SAML Sign authN requests' => [
				'authentication' => [
					'saml_sign_authn_requests' => 1
				],
				'expected_error' => null
			],
			'Test valid SAML Sign logout requests' => [
				'authentication' => [
					'saml_sign_logout_requests' => 1
				],
				'expected_error' => null
			],
			'Test valid SAML Sign logout responses' => [
				'authentication' => [
					'saml_sign_logout_responses' => 1
				],
				'expected_error' => null
			],
			'Test valid SAML Encrypt name ID' => [
				'authentication' => [
					'saml_encrypt_nameid' => 1
				],
				'expected_error' => null
			],
			'Test valid SAML Encrypt assertions' => [
				'authentication' => [
					'saml_encrypt_assertions' => 1
				],
				'expected_error' => null
			],
			'Test valid case sensitive for SAML auth' => [
				'authentication' => [
					'saml_case_sensitive' => ZBX_AUTH_CASE_SENSITIVE
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider authentication_update_data_invalid
	 * @dataProvider authentication_update_data_valid
	 */
	public function testAuthentication_Update($authentication, $expected_error) {
		if (array_key_exists('ldap_userdirectoryid', $authentication)) {
			$authentication['ldap_userdirectoryid'] = static::$data[$authentication['ldap_userdirectoryid']];
		}

		if ($expected_error === null) {
			// Before updating, collect old authentication data.
			$fields = '';
			foreach ($authentication as $field => $value) {
				$fields = 'c.'.$field.',';
			}
			$fields = substr($fields, 0, -1);
			$sql = 'SELECT '.$fields.' FROM config c WHERE c.configid=1';

			$db_authentication = CDBHelper::getAll($sql)[0];
			$this->call('authentication.update', $authentication, $expected_error);
			$db_upd_authentication = CDBHelper::getAll($sql)[0];

			$updated = array_intersect_key($authentication, $db_upd_authentication);
			$unchanged = array_diff_key($db_upd_authentication, $authentication);

			// Check if field values have been updated.
			foreach ($updated as $field => $value) {
				if (is_numeric($value)) {
					$this->assertEquals($value, $db_upd_authentication[$field]);
				}
				else {
					$this->assertSame($value, $db_upd_authentication[$field]);
				}
			}

			// Check if fields that were not given, remain the same.
			foreach ($unchanged as $field => $value) {
				if (is_numeric($value)) {
					$this->assertEquals($value, $db_authentication[$field]);
				}
				else {
					$this->assertSame($value, $db_authentication[$field]);
				}
			}
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('authentication.update', $authentication, $expected_error);
		}
	}

	/**
	 * Test data used by test.
	 */
	protected static $data = [
		'userdirectory_1' => null,
		'userdirectory_invalidid_1' => 999
	];

	/**
	 * Prepare data for tests. Create user, group, userdirectory.
	 */
	public function prepareTestData() {
		$response = CDataHelper::call('userdirectory.create', [
			['name' => 'LDAP #1', 'host' => 'ldap.forumsys.com', 'port' => 389, 'base_dn' => 'dc=example,dc=com', 'search_attribute' => 'uid']
		]);
		$this->assertArrayHasKey('userdirectoryids', $response);
		self::$data['userdirectory_1'] = reset($response['userdirectoryids']);
	}
}
