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


require_once dirname(__FILE__).'/../include/CAPIScimTest.php';

/**
 * @onBefore prepareUserData
 *
 * @onAfter clearData
 */
class testScimUser extends CAPIScimTest {

	private static $data = [
		'userdirectoryid' => [
			'ldap' => null,
			'saml' => null
		],
		'userid' => [
			'ldap_user' => null,
			'saml_user_active' => null,
			'saml_user_inactive' => null
		],
		'username' => [
			'ldap_user' => 'dwight.schrute@office.com',
			'saml_user_active' => 'jim.halpert@office.com',
			'saml_user_inactive' => 'pam.beesly@office.com'
		],
		'token' => [
			'tokenid' => null,
			'token' => null
		]
	];

	public function prepareUserData() {
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
					'mediatypeid' => '1',
					'attribute' => 'user_mobile'
				]
			],
			'provision_groups' => [
				[
					'name' => 'group name',
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

		// Create active user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['saml_user_active'],
				'userdirectoryid' => self::$data['userdirectoryid']['saml'],
				'name' => 'Jim',
				'surname' => 'Halpert',
				'usrgrps' => [['usrgrpid' => 7]],
				'medias' => [['mediatypeid' => '3', 'sendto' => '123456789']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['saml_user_active'] = $user['userids'][0];

		// Create inactive user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['saml_user_inactive'],
				'userdirectoryid' => self::$data['userdirectoryid']['saml'],
				'name' => 'Pam',
				'surname' => 'Beesly',
				'usrgrps' => [['usrgrpid' => 9]],
				'medias' => [['mediatypeid' => '3', 'sendto' => '987654321']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['saml_user_inactive'] = $user['userids'][0];

		// Create userdirectory for LDAP.
		$userdirectory_ldap = CDataHelper::call('userdirectory.create', [
			'idp_type' => IDP_TYPE_LDAP,
			'name' => 'LDAP',
			'host' => 'test',
			'port' => 389,
			'base_dn' => 'test',
			'search_attribute' => 'test'
		]);
		$this->assertArrayHasKey('userdirectoryids', $userdirectory_ldap);
		self::$data['userdirectoryid']['ldap'] = $userdirectory_ldap['userdirectoryids'][0];

		// Create user with newly created userdirectoryid for LDAP.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['ldap_user'],
				'userdirectoryid' => self::$data['userdirectoryid']['ldap'],

			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['ldap_user'] = $user['userids'][0];

		// Create authorization token to execute requests.
		$tokenid = CDataHelper::call('token.create', [
			[
				'name' => 'Token for Users SCIM requests',
				'userid' => '1'
			]
		]);
		$this->assertArrayHasKey('tokenids', $tokenid);
		static::$data['token']['tokenid'] = $tokenid['tokenids'][0];

		$token = CDataHelper::call('token.generate', [static::$data['token']['tokenid']]);

		$this->assertArrayHasKey('token', $token[0]);
		static::$data['token']['token'] = $token[0]['token'];
	}

	public static function createValidGetRequest(): array {
		return [
			'Get Users without any parameters (checking connection)' => [
				'user' => [],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
					'totalResults' => 2,
					'startIndex' => 1,
					'itemsPerPage' => 2,
					'Resources' => [
						[
							'id' 		=> 'saml_user_active',
							'userName'	=> 'saml_user_active',
							'active'	=> true,
							'name' => '',
							'surname' => ''
						],
						[
							'id' 		=> 'saml_user_inactive',
							'userName'	=> 'saml_user_inactive',
							'active'	=> true,
							'name' => '',
							'surname' => ''
						]
					]
				],
				'expected_error' => null
			],
			'Get User by userName which does not exist in Zabbix yet' => [
				'user' => ['userName' => 'michael.scott@office.com'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'totalResults' => 0,
					'Resources' => []
				],
				'expected_error' => null
			],
			'Get User by userName which exist in Zabbix and has the same userdirectoryid' => [
				'user' => ['userName' => 'saml_user_active'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'id' 		=> 'saml_user_active',
					'userName'	=> 'saml_user_active',
					'active'	=> true,
					'name' => '',
					'surname' => ''
				],
				'expected_error' => null
			],
			'Get User by userName which exist in Zabbix, has the same userdirectoryid, is in disabled group' => [
				'user' => ['userName' => 'saml_user_inactive'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'totalResults' => 0,
					'Resources' => []
				],
				'expected_error' => null
			],
			'Get User by userid which exist in Zabbix and has the same userdirectoryid' => [
				'user' => ['id' => 'saml_user_active'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'id' 		=> 'saml_user_active',
					'userName'	=> 'saml_user_active',
					'active'	=> true,
					'name' => '',
					'surname' => ''
				],
				'expected_error' => null
			]
		];
	}

	public static function createInvalidGetRequest(): array {
		return [
			'Get User by userName which already is linked to other userdirectory' => [
				'user' => ['userName' => 'ldap_user'],
				'expected_result' => null,
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'User with username dwight.schrute@office.com already exists.',
					'status' => 400
				]
			],
			'Get non existing user by user id' => [
				'user' => ['id' => '9999999'],
				'expected_result' => null,
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Get User by id which is already linked to other userdirectory' => [
				'user' => ['id' => 'ldap_user'],
				'expected_result' => null,
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'status' => 400
				]
			]
		];
	}

	/**
	 * @dataProvider createValidGetRequest
	 * @dataProvider createInvalidGetRequest
	 */
	public function testUserGet($user, $expected_result, $expected_error) {
		// Special case with user that belongs to other userdirectory.
		if (array_key_exists('id', $user) && $user['id'] === 'ldap_user') {
			$expected_error['detail'] = 'The user '.self::$data['userid']['ldap_user'].
				' belongs to another userdirectory.';
		}

		$this->resolveData($user);

		if ($expected_result !== null) {
			$this->resolveData($expected_result);

			if (array_key_exists('Resources', $expected_result)) {
				foreach ($expected_result['Resources'] as &$resource) {
					$this->resolveData($resource);
				}
				unset($resource);
			}
		}

		$user['token'] = self::$data['token']['token'];

		$result = $this->call('user.get', $user, $expected_error);

		if ($expected_result !== null) {
			$this->assertEquals($expected_result, $result, 'Returned response should match.');
		}
	}

	/**
	 * Accepts test data and returns data with substituted ids and userNames from the database.
	 *
	 * @param array $user_data
	 * @return void
	 */
	public function resolveData(array &$user_data): void {
		foreach ($user_data as $key => $data) {
			if ($key === 'id' || $key === 'userName') {
				$data_key = ($key === 'id') ? 'userid' : 'username';

				if (array_key_exists($data, self::$data[$data_key])) {
					$user_data[$key] = self::$data[$data_key][$data];
				}
			}
		}
	}


	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		// Delete users.
		CDataHelper::call('user.delete', array_values(self::$data['userid']));

		// Delete userdirectories.
		CDataHelper::call('userdirectory.delete', array_values(self::$data['userdirectoryid']));

		// Delete token.
		CDataHelper::call('token.delete', [self::$data['token']['tokenid']]);
	}
}
