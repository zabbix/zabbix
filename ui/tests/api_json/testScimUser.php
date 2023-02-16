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
		],
		'mediatypeid' => '3'
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
					'mediatypeid' => self::$data['mediatypeid'],
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
				'medias' => [['mediatypeid' => self::$data['mediatypeid'], 'sendto' => '123456789']],
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
				'medias' => [['mediatypeid' => self::$data['mediatypeid'], 'sendto' => '987654321']],
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

	public static function createInvalidPostRequest(): array {
		return [
			'Post request with invalid user schema' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['invalid:schema'],
					'active' => true,
					'userName' => 'michael.scott@office.com',
					'user_lastname' => 'Scott',
					'user_name' => 'Michael',
					'user_mobile' => '999999999'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' =>
						'Invalid parameter "/schemas/1": value must be "urn:ietf:params:scim:schemas:core:2.0:User".',
					'status' => 400
				]
			],
			'Post request with missing user schema' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'active' => true,
					'userName' => 'michael.scott@office.com',
					'user_lastname' => 'Scott',
					'user_name' => 'Michael',
					'user_mobile' => '999999999'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "schemas" is missing.',
					'status' => 400
				]
			],
			'Post request with empty user schema' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => [],
					'active' => true,
					'userName' => 'michael.scott@office.com',
					'user_lastname' => 'Scott',
					'user_name' => 'Michael',
					'user_mobile' => '999999999'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/schemas": cannot be empty.',
					'status' => 400
				]
			],
			'Post request with missing userName parameter' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'active' => true,
					'user_lastname' => 'Scott',
					'user_name' => 'Michael',
					'user_mobile' => '999999999'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "userName" is missing.',
					'status' => 400
				]
			],
			'Post request with empty userName parameter' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => '',
					'active' => true,
					'user_lastname' => 'Scott',
					'user_name' => 'Michael',
					'user_mobile' => '999999999'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/userName": cannot be empty.',
					'status' => 400
				]
			],
			'Create user that already exists and belongs to other userdirectory id' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'active' => true,
					'userName' => 'ldap_user',
					'user_lastname' => 'Schrute',
					'user_name' => 'Dwight',
					'user_mobile' => '222222222'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'User with username dwight.schrute@office.com already exists.',
					'status' => 400
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPostRequest
	 */
	public function testInvalidUserPost($user, $expected_error) {
		$this->resolveData($user);

		$user['token'] = self::$data['token']['token'];

		$this->call('user.post', $user, $expected_error);
	}

	public static function createValidPostRequest(): array {
		return [
			'Create new valid user' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'active' => true,
					'userName' => 'michael.scott@office.com',
					'user_lastname' => 'Scott',
					'user_name' => 'Michael',
					'user_mobile' => '999999999'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'active' => true,
					'userName' => 'michael.scott@office.com',
					'name' => 'Michael',
					'surname' => 'Scott',
					'user_mobile' => '999999999'
				]
			],
			'Create valid user, which already exists but was inactive' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'active' => true,
					'userName' => 'saml_user_active',
					'user_lastname' => 'Halpert',
					'user_name' => 'Jim',
					'user_mobile' => '123456789'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'active' => true,
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'name' => 'Jim',
					'surname' => 'Halpert',
					'user_mobile' => '123456789'
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPostRequest
	 */
	public function testValidUserPost($user, $expected_result) {
		$this->resolveData($user);
		$this->resolveData($expected_result);

		$user['token'] = self::$data['token']['token'];

		$result = $this->call('user.post', $user);

		// Compare response with expected response.
		foreach ($expected_result as $key => $expected) {
			$this->assertArrayHasKey($key, $result);
			$this->assertEquals($expected, $result[$key], 'Returned response should match.');
		}

		// Response should have 'id' value, which is not known for us, if completely new user is created.
		$this->assertArrayHasKey('id', $result);

		// If user was inactive before, we know the 'id' and it should not change.
		// If it was new user, 'id' was not know for us and needs to be saved in $data.
		if (array_key_exists('id', $expected_result)) {
			$this->assertEquals($expected_result['id'], $result['id'], 'Returned response should match.');
		}
		else {
			self::$data['userid']['new_user'] = $result['id'];
		}

		// Check that user data in the database is correct.
		$db_result_user_data = DBSelect('select username, name, surname, userdirectoryid from users where userid='.
			zbx_dbstr($result['id'])
		);
		$db_result_user = DBFetch($db_result_user_data);

		$this->assertEquals($user['userName'], $db_result_user['username']);
		$this->assertEquals($user['user_name'], $db_result_user['name']);
		$this->assertEquals($user['user_lastname'], $db_result_user['surname']);
		$this->assertEquals(self::$data['userdirectoryid']['saml'], $db_result_user['userdirectoryid']);

		// Check that user media data in the database is correct.
		$db_result_user_media_data = DBselect('select mediatypeid, sendto from media where userid='.
			zbx_dbstr($result['id'])
		);
		$db_result_user_media = DBfetch($db_result_user_media_data);

		$this->assertEquals($user['user_mobile'], $db_result_user_media['sendto']);
		$this->assertEquals(self::$data['mediatypeid'], $db_result_user_media['mediatypeid']);
	}

	public function createInvalidPutRequest() {
		return [
			'Put request with invalid user schema' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['invalid:schema'],
					'id' => 'saml_user_active',
					'active' => true,
					'userName' => 'saml_user_active',
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' =>
						'Invalid parameter "/schemas/1": value must be "urn:ietf:params:scim:schemas:core:2.0:User".',
					'status' => 400
				]
			],
			'Put request with missing user schema' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'id' => 'saml_user_active',
					'active' => true,
					'userName' => 'saml_user_active',
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "schemas" is missing.',
					'status' => 400
				]
			],
			'Put request with empty user schema' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => [],
					'id' => 'saml_user_active',
					'active' => true,
					'userName' => 'saml_user_active',
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/schemas": cannot be empty.',
					'status' => 400
				]
			],
			'Put request with missing userName parameter' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'id' => 'saml_user_active',
					'active' => true,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "userName" is missing.',
					'status' => 400
				]
			],
			'Put request with empty userName parameter' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => '',
					'id' => 'saml_user_active',
					'active' => true,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/userName": cannot be empty.',
					'status' => 400
				]
			],
			'Put request with missing id parameter' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'active' => true,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "id" is missing.',
					'status' => 400
				]
			],
			'Put request with empty active parameter' => [		// TODO this will be fixed with ZBX-21976
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => null,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/active": a boolean is expected.',
					'status' => 400
				]
			],
			'Put request with not existing id' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => '1111111111111',
					'active' => true,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Put request for user which belongs to another userdirectory' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'ldap_user',
					'id' => 'ldap_user',
					'active' => true,
					'user_lastname' => 'DwightDwight',
					'user_name' => 'Schrute',
					'user_mobile' => '333333333'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'status' => 400
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPutRequest
	 */
	public function testInvalidUserPut($user, $expected_error) {
		$this->resolveData($user);

		if (!array_key_exists('detail', $expected_error)) {
			$expected_error['detail'] = 'The user '.self::$data['userid']['ldap_user'].
				' belongs to another userdirectory.';
		}

		$user['token'] = self::$data['token']['token'];

		$this->call('user.put', $user, $expected_error);
	}

	public function createValidPutRequest() {
		return [
			"Put request to update user's name, surname and mobile phone" => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => true,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => true,
					'surname' => 'JimJim',
					'name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPutRequest
	 */
	public function testValidPutRequest($user, $expected_result) {
		$this->resolveData($user);
		$this->resolveData($expected_result);

		$user['token'] = self::$data['token']['token'];

		$result = $this->call('user.put', $user);

		// Compare response with expected response.
		foreach ($expected_result as $key => $expected) {
			$this->assertArrayHasKey($key, $result);
			$this->assertEquals($expected, $result[$key], 'Returned response should match.');
		}

		// Check that user data in the database is correct.
		$db_result_user_data = DBSelect('select username, name, surname, userdirectoryid from users where userid='.
			zbx_dbstr(self::$data['userid']['saml_user_active'])
		);
		$db_result_user = DBFetch($db_result_user_data);

		$this->assertEquals($user['userName'], $db_result_user['username']);
		$this->assertEquals($user['user_name'], $db_result_user['name']);
		$this->assertEquals($user['user_lastname'], $db_result_user['surname']);
		$this->assertEquals(self::$data['userdirectoryid']['saml'], $db_result_user['userdirectoryid']);

		// Check that user media data in the database is correct.
		$db_result_user_media_data = DBselect('select mediatypeid, sendto from media where userid='.
			zbx_dbstr(self::$data['userid']['saml_user_active'])
		);
		$db_result_user_media = DBfetch($db_result_user_media_data);

		$this->assertEquals($user['user_mobile'], $db_result_user_media['sendto']);
		$this->assertEquals(self::$data['mediatypeid'], $db_result_user_media['mediatypeid']);
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
