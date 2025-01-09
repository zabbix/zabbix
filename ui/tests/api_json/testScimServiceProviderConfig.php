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


require_once dirname(__FILE__) . '/common/CAPIScimTest.php';

/**
 * @onBefore prepareUserData
 *
 * @onAfter clearData
 */
class testScimServiceProviderConfig extends CAPIScimTest {

	private static $data = [
		'userdirectoryid' => [
			'saml' => null
		],
		'tokenids' => [
			'superadmin' => null
		],
		'tokens' => [
			'superadmin' => null
		],
		'mediatypeid' => '3'
	];

	public function prepareUserData(): void {
		// Create userdirectory for SAML.
		$userdirectory_saml = CDataHelper::call('userdirectory.create', [
			'idp_type' => IDP_TYPE_SAML,
			'group_name' => 'groups',
			'idp_entityid' => 'http://www.okta.com/abcdef',
			'sso_url' => 'https://www.okta.com/ghijkl',
			'username_attribute' => 'usrEmail',
			'user_username' => 'user_name',
			'user_lastname' => 'user_lastname',
			'provision_status' => JIT_PROVISIONING_ENABLED,
			'sp_entityid' => '',
			'provision_media' => [
				[
					'name' => 'SMS',
					'mediatypeid' => self::$data['mediatypeid'],
					'attribute' => 'user_mobile'
				]
			],
			'provision_groups' => [
				[
					'name' => 'group_w_members',
					'roleid' => 1,
					'user_groups' => [
						['usrgrpid' => 7]
					]
				]
			],
			'scim_status' => 1
		]);
		$this->assertArrayHasKey('userdirectoryids', $userdirectory_saml);
		self::$data['userdirectoryid']['saml'] = $userdirectory_saml['userdirectoryids'][0];

		CDataHelper::call('authentication.update', [
			'saml_auth_enabled' => ZBX_AUTH_SAML_ENABLED,
			'disabled_usrgrpid' => '9'
		]);

		// Create authorization token to execute requests.
		$tokenid = CDataHelper::call('token.create', [
			[
				'name' => 'Token for Users SCIM requests',
				'userid' => '1'
			]
		]);
		$this->assertArrayHasKey('tokenids', $tokenid);
		self::$data['tokenids']['superadmin'] = $tokenid['tokenids'][0];

		$token = CDataHelper::call('token.generate', [self::$data['tokenids']['superadmin']]);

		$this->assertArrayHasKey('token', $token[0]);

		self::$data['tokens']['superadmin'] = $token[0]['token'];
	}

	public static function createGetRequest(): array {
		return [
			'Get ServiceProviderConfig without any parameters' => [
				'sp_config' => [],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
					'patch' => ['supported' => true],
					'bulk' => [
						'supported' => false,
						'maxOperations' => 0,
						'maxPayloadSize' => 0
					],
					'filter' => [
						'supported' => false,
						'maxResults' => 0
					],
					'changePassword' => ['supported' => false],
					'sort' => ['supported' => false],
					'etag' => ['supported' => false],
					'authenticationSchemes' => [
						'name' => 'OAuth Bearer Token',
						'description' => 'Authentication Scheme using the OAuth Bearer Token Standard',
						'type' => 'oauthbearertoken'
					]
				]
			],
			'Get ServiceProviderConfig with some random parameters' => [
				'sp_config' => ['userName' => 'michael.scott@office.com'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
					'patch' => ['supported' => true],
					'bulk' => [
						'supported' => false,
						'maxOperations' => 0,
						'maxPayloadSize' => 0
					],
					'filter' => [
						'supported' => false,
						'maxResults' => 0
					],
					'changePassword' => ['supported' => false],
					'sort' => ['supported' => false],
					'etag' => ['supported' => false],
					'authenticationSchemes' => [
						'name' => 'OAuth Bearer Token',
						'description' => 'Authentication Scheme using the OAuth Bearer Token Standard',
						'type' => 'oauthbearertoken'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider createGetRequest
	 */
	public function testScimServiceProviderConfig_Get($sp_config, $expected_result) {
		$result = $this->call('serviceproviderconfig.get', $sp_config);

		$this->assertEquals($expected_result, $result, 'Returned response should match.');
	}

	public static function createPostRequest(): array {
		return [
			'ServiceProviderConfig Post' => [
				'sp_config' => [],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'The endpoint does not support the provided method.',
					'status' => 405
				]
			]
		];
	}

	/**
	 * @dataProvider createPostRequest
	 */
	public function testScimServiceProviderConfig_Post($sp_config, $expected_error) {
		CAPIScimHelper::setToken(self::$data['tokens']['superadmin']);
		$this->call('serviceproviderconfig.post', $sp_config, $expected_error);
	}

	public function createPutRequest(): array {
		return [
			'ServiceProviderConfig Put' => [
				'sp_config' => [],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'The endpoint does not support the provided method.',
					'status' => 405
				]
			]
		];
	}

	/**
	 * @dataProvider createPutRequest
	 */
	public function testScimServiceProviderConfig_Put($sp_config, $expected_error) {
		CAPIScimHelper::setToken(self::$data['tokens']['superadmin']);

		$this->call('serviceproviderconfig.put', $sp_config, $expected_error);
	}
	public function createPatchRequest(): array {
		return [
			'ServiceProviderConfig Patch' => [
				'sp_config' => [],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'The endpoint does not support the provided method.',
					'status' => 405
				]
			]
		];
	}

	/**
	 * @dataProvider createPatchRequest
	 */
	public function testScimServiceProviderConfig_Patch(array $sp_config, array $expected_error): void {
		CAPIScimHelper::setToken(self::$data['tokens']['superadmin']);

		$this->call('serviceproviderconfig.patch', $sp_config, $expected_error);
	}

	public function createDeleteRequest(): array {
		return [
			'ServiceProviderConfig Delete' => [
				'sp_config' => [],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'The endpoint does not support the provided method.',
					'status' => 405
				]
			]
		];
	}

	/**
	 * @dataProvider createDeleteRequest
	 */
	public function testScimServiceProviderConfig_Delete($sp_config, $expected_error): void {
		CAPIScimHelper::setToken(self::$data['tokens']['superadmin']);

		$this->call('serviceproviderconfig.delete', $sp_config, $expected_error);
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		// Delete userdirectories.
		CDataHelper::call('authentication.update', ['saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED]);
		CDataHelper::call('userdirectory.delete', array_values(self::$data['userdirectoryid']));

		// Delete token.
		CDataHelper::call('token.delete',  [self::$data['tokenids']['superadmin']]);
	}
}

