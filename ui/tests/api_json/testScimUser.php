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
class testScimUser extends CAPIScimTest {

	private static $data = [
		'userdirectoryid' => [
			'ldap' => null,
			'saml' => null
		],
		'userid' => [
			'ldap_user' => null,
			'saml_user_active' => null,
			'saml_user_inactive' => null,
			'saml_user_only_username' => null,
			'saml_user_with_media' => null,
			'admin' => null,
			'user' => null,
			'guest_user' => null
		],
		'username' => [
			'ldap_user' => 'dwight.schrute@office.com',
			'saml_user_active' => 'jim.halpert@office.com',
			'saml_user_inactive' => 'pam.beesly@office.com',
			'saml_user_only_username' => 'andy.bernard@office.com',
			'saml_user_with_media' => 'bob.schrute@office.com'
		],
		'tokenids' => [
			'superadmin' => null,
			'admin' => null,
			'user' => null,
			'guest_user' => null
		],
		'tokens' => [
			'admin' => null,
			'user' => null,
			'guest_user' => null,
			'no_token' => null
		],
		'mediatypeid' => [
			'SMS' => '3',
			'Email' => '1'
		],
		'scim_groupids' => [
			'group_w_members' => null
		],
		'user_scim_groupids' => [
			'user_group_w_members' => null
		]
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
					'mediatypeid' => self::$data['mediatypeid']['SMS'],
					'attribute' => 'user_mobile'
				],
				[
					'name' => 'Email',
					'mediatypeid' => self::$data['mediatypeid']['Email'],
					'attribute' => 'user_email'
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

		// Create active user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['saml_user_active'],
				'passwd' => 'scimuserPassw0rd',
				'name' => 'Jim',
				'surname' => 'Halpert',
				'usrgrps' => [['usrgrpid' => 7]],
				'medias' => [['mediatypeid' => self::$data['mediatypeid']['SMS'], 'sendto' => '123456789']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['saml_user_active'] = $user['userids'][0];
		$userdirectory = CDataHelper::call('userdirectory.get', [
			'output' => ['userdirectoryid'],
			'selectProvisionMedia' => ['userdirectory_mediaid', 'mediatypeid'],
			'userdirectoryids' => [self::$data['userdirectoryid']['saml']]
		])[0];
		$userdirectory_mediaids = array_column($userdirectory['provision_media'], 'userdirectory_mediaid', 'mediatypeid');
		DB::update('media', [
			'values' => ['userdirectory_mediaid' => $userdirectory_mediaids[self::$data['mediatypeid']['SMS']]],
			'where' => ['userid' => $user['userids'][0], 'mediatypeid' => self::$data['mediatypeid']['SMS']]
		]);
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryid']['saml']],
			'where' => ['userid' => $user['userids'][0]]
		]);

		// Create inactive user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['saml_user_inactive'],
				'passwd' => 'scimuserPassw0rd',
				'name' => 'Pam',
				'surname' => 'Beesly',
				'usrgrps' => [['usrgrpid' => 9]],
				'medias' => [['mediatypeid' => self::$data['mediatypeid']['SMS'], 'sendto' => '987654321']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['saml_user_inactive'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryid']['saml']],
			'where' => ['userid' => $user['userids'][0]]
		]);

		// Create user with only username with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['saml_user_only_username'],
				'passwd' => 'scimuserPassw0rd',
				'usrgrps' => [['usrgrpid' => 9]],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['saml_user_only_username'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryid']['saml']],
			'where' => ['userid' => $user['userids'][0]]
		]);

		// Create SAML provisioned user with not provisioned media.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['username']['saml_user_with_media'],
				'passwd' => 'scimuserPassw0rd',
				'medias' => [
					['mediatypeid' => self::$data['mediatypeid']['Email'], 'sendto' => ['example@example.com']],
					['mediatypeid' => self::$data['mediatypeid']['SMS'], 'sendto' => '987654321'],
					['mediatypeid' => self::$data['mediatypeid']['SMS'], 'sendto' => 'provisioned']
				],
				'usrgrps' => [['usrgrpid' => 9]],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['saml_user_with_media'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryid']['saml']],
			'where' => ['userid' => $user['userids'][0]]
		]);
		DB::update('media', [
			'values' => ['userdirectory_mediaid' => $userdirectory_mediaids[self::$data['mediatypeid']['SMS']]],
			'where' => ['userid' => $user['userids'][0], 'sendto' => 'provisioned']
		]);

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
				'passwd' => 'scimuserPassw0rd'
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['ldap_user'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryid']['ldap']],
			'where' => ['userid' => $user['userids'][0]]
		]);

		// Create SCIM group and add a members to it.
		$group_w_members = DB::insert('scim_group', [['name' => 'group_w_members']]);
		$this->assertNotEmpty($group_w_members);
		self::$data['scim_groupids']['group_w_members'] = $group_w_members[0];

		$user_scim_groups = DB::insert('user_scim_group', [
			[
				'userid' => self::$data['userid']['saml_user_active'],
				'scim_groupid' => self::$data['scim_groupids']['group_w_members']
			],
			[
				'userid' => self::$data['userid']['saml_user_only_username'],
				'scim_groupid' => self::$data['scim_groupids']['group_w_members']
			]
		]);
		$this->assertNotEmpty($user_scim_groups);

		self::$data['user_scim_groupids']['member_saml_user_active'] = $user_scim_groups[0];
		self::$data['user_scim_groupids']['member_saml_only_username'] = $user_scim_groups[1];

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
		CAPIScimHelper::setToken($token[0]['token']);

		// Create users with different user roles for authorization testing.
		$user = CDataHelper::call('user.create', [
			[
				'username' => 'admin',
				'passwd' => 'testtest123',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 2
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['admin'] = $user['userids'][0];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'user',
				'passwd' => 'testtest123',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['user'] = $user['userids'][0];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'guest_user',
				'passwd' => 'testtest123',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 4
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userid']['guest_user'] = $user['userids'][0];

		// Create authorization token for each user with different user role for authorization testing.
		$tokenid = CDataHelper::call('token.create', [
			[
				'name' => 'Token for admin',
				'userid' => self::$data['userid']['admin']
			]
		]);
		$this->assertArrayHasKey('tokenids', $tokenid);
		self::$data['tokenids']['admin'] = $tokenid['tokenids'][0];
		$token = CDataHelper::call('token.generate', [self::$data['tokenids']['admin']]);
		$this->assertArrayHasKey('token', $token[0]);
		self::$data['tokens']['admin'] = $token[0]['token'];

		$tokenid = CDataHelper::call('token.create', [
			[
				'name' => 'Token for user',
				'userid' => self::$data['userid']['user']
			]
		]);
		$this->assertArrayHasKey('tokenids', $tokenid);
		self::$data['tokenids']['user'] = $tokenid['tokenids'][0];
		$token = CDataHelper::call('token.generate', [self::$data['tokenids']['user']]);
		$this->assertArrayHasKey('token', $token[0]);
		self::$data['tokens']['user'] = $token[0]['token'];

		$tokenid = CDataHelper::call('token.create', [
			[
				'name' => 'Token for guest',
				'userid' => self::$data['userid']['guest_user']
			]
		]);
		$this->assertArrayHasKey('tokenids', $tokenid);
		self::$data['tokenids']['guest_user'] = $tokenid['tokenids'][0];
		$token = CDataHelper::call('token.generate', [self::$data['tokenids']['guest_user']]);
		$this->assertArrayHasKey('token', $token[0]);
		self::$data['tokens']['guest_user'] = $token[0]['token'];
	}

	public static function createInvalidGetRequest(): array {
		return [
			'Get User by userName which already is linked to other userdirectory' => [
				'user' => ['userName' => 'ldap_user'],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'User with username dwight.schrute@office.com already exists.',
					'status' => 400
				]
			],
			'Get non existing user by user id' => [
				'user' => ['id' => '9999999'],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Get User by id which is already linked to other userdirectory' => [
				'user' => ['id' => 'ldap_user'],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidGetRequest
	 */
	public function testScimUser_GetInvalid($user, $expected_error) {
		$this->resolveData($user);

		$this->call('users.get', $user, $expected_error);
	}

	public static function createValidGetRequest(): array {
		return [
			'Get Users without any parameters (checking connection)' => [
				'user' => [],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
					'totalResults' => 4,
					'startIndex' => 1,
					'itemsPerPage' => 4,
					'Resources' => [
						[
							'id' 		=> 'saml_user_active',
							'userName'	=> 'saml_user_active',
							'active'	=> true,
							'name' => ['givenName' => '', 'familyName' => '']
						],
						[
							'id' 		=> 'saml_user_inactive',
							'userName'	=> 'saml_user_inactive',
							'active'	=> true,
							'name' => ['givenName' => '', 'familyName' => '']
						],
						[
							'id' 		=> 'saml_user_only_username',
							'userName'	=> 'saml_user_only_username',
							'active'	=> true,
							'name' => ['givenName' => '', 'familyName' => '']
						],
						[
							'id' 		=> 'saml_user_with_media',
							'userName'	=> 'saml_user_with_media',
							'active'	=> true,
							'name' => ['givenName' => '', 'familyName' => '']
						]
					]
				]
			],
			'Get User by userName which does not exist in Zabbix yet' => [
				'user' => ['userName' => 'michael.scott@office.com'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'totalResults' => 0,
					'Resources' => []
				]
			],
			'Get User by userName which exist in Zabbix and has the same userdirectoryid' => [
				'user' => ['userName' => 'saml_user_active'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'id' 		=> 'saml_user_active',
					'userName'	=> 'saml_user_active',
					'active'	=> true,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			],
			'Get User by userName which exist in Zabbix, has the same userdirectoryid, is in disabled group' => [
				'user' => ['userName' => 'saml_user_inactive'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'totalResults' => 0,
					'Resources' => []
				]
			],
			'Get User by userid which exist in Zabbix and has the same userdirectoryid' => [
				'user' => ['id' => 'saml_user_active'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'id' 		=> 'saml_user_active',
					'userName'	=> 'saml_user_active',
					'active'	=> true,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			]
		];
	}

	/**
	 * @dataProvider createValidGetRequest
	 */
	public function testScimUser_GetValid($user, $expected_result) {
		$this->resolveData($user);
		$this->resolveData($expected_result);

		if (array_key_exists('Resources', $expected_result)) {
			foreach ($expected_result['Resources'] as &$resource) {
				$this->resolveData($resource);
			}
			unset($resource);
		}

		$result = $this->call('users.get', $user);

		if ($result && array_key_exists('Resources', $expected_result) && $expected_result['Resources']) {
			$result['Resources'] = array_column($result['Resources'], null, 'id');
			$expected_result['Resources'] = array_column($expected_result['Resources'], null, 'id');
		}

		$this->assertEquals($expected_result, $result, 'Returned response should match.');
	}

	public static function createInvalidPostRequest(): array {
		return [
			'Post request with invalid user schema' => [
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
					'detail' => 'Incorrect schema was sent in the request.',
					'status' => 400
				]
			],
			'Post request with missing user schema' => [
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
			'Post request with empty user schema' => [
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
			'Post request with missing userName parameter' => [
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
			'Post request with empty userName parameter' => [
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
	public function testScimUser_PostInvalid($user, $expected_error) {
		$this->resolveData($user);

		$this->call('users.post', $user, $expected_error);
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
					'name' => ['givenName' => '', 'familyName' => ''],
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
					'name' => ['givenName' => '', 'familyName' => ''],
					'surname' => 'Halpert',
					'user_mobile' => '123456789'
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPostRequest
	 */
	public function testScimUser_PostValid($user, $expected_result) {
		$this->resolveData($user);
		$this->resolveData($expected_result);

		$result = $this->call('users.post', $user);

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
		$db_result_user_data = DBSelect('SELECT username, name, surname, userdirectoryid FROM users WHERE userid='.
			zbx_dbstr($result['id'])
		);
		$db_result_user = DBFetch($db_result_user_data);

		$this->assertEquals($user['userName'], $db_result_user['username']);
		$this->assertEquals($user['user_name'], $db_result_user['name']);
		$this->assertEquals($user['user_lastname'], $db_result_user['surname']);
		$this->assertEquals(self::$data['userdirectoryid']['saml'], $db_result_user['userdirectoryid']);

		// Check that user media data in the database is correct.
		$db_result_user_media_data = DBselect('SELECT mediatypeid, sendto FROM media WHERE userid='.
			zbx_dbstr($result['id'])
		);
		$db_result_user_media = DBfetch($db_result_user_media_data);

		$this->assertEquals($user['user_mobile'], $db_result_user_media['sendto']);
		$this->assertEquals(self::$data['mediatypeid']['SMS'], $db_result_user_media['mediatypeid']);
	}

	public function createInvalidPutRequest() {
		return [
			'Put request with invalid user schema' => [
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
					'detail' => 'Incorrect schema was sent in the request.',
					'status' => 400
				]
			],
			'Put request with missing user schema' => [
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
			'Put request with empty user schema' => [
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
			'Put request with missing userName parameter' => [
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
			'Put request with empty userName parameter' => [
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
			'Put request with missing id parameter' => [
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
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPutRequest
	 */
	public function testScimUser_PutInvalid($user, $expected_error) {
		$this->resolveData($user);

		$this->call('users.put', $user, $expected_error);
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
					'name' => ['givenName' => '', 'familyName' => ''],
					'user_mobile' => '5555555'
				]
			],
			"Put request to update user's attribute active from true to false." => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => false,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => false,
					'surname' => 'JimJim',
					'name' => ['givenName' => '', 'familyName' => ''],
					'user_mobile' => '5555555'
				]
			],
			"Put request to update user's attribute 'active' from false to true and pass 'groups' parameter." => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => true,
					'user_lastname' => 'JimJim',
					'user_name' => 'HalperHalpert',
					'user_mobile' => '5555555',
					// Attribute 'update' is added only to know that this specific test case checks user's 'active'
					// attribute change.
					'update' => 'active'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_active',
					'id' => 'saml_user_active',
					'active' => true,
					'surname' => 'JimJim',
					'name' => ['givenName' => '', 'familyName' => ''],
					'user_mobile' => '5555555'
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPutRequest
	 */
	public function testScimUser_PutValid($user, $expected_result) {
		$this->resolveData($user);
		$this->resolveData($expected_result);

		$result = $this->call('users.put', $user);

		// Compare response with expected response.
		foreach ($expected_result as $key => $expected) {
			$this->assertArrayHasKey($key, $result);
			$this->assertEquals($expected, $result[$key], 'Returned response should match.');
		}

		// Check that user data in the database is correct.
		$db_result_user_data = DBSelect('SELECT username, name, surname, userdirectoryid FROM users WHERE userid='.
			zbx_dbstr(self::$data['userid']['saml_user_active'])
		);
		$db_result_user = DBFetch($db_result_user_data);

		$this->assertEquals($user['userName'], $db_result_user['username']);
		$this->assertEquals($user['user_name'], $db_result_user['name']);
		$this->assertEquals($user['user_lastname'], $db_result_user['surname']);
		$this->assertEquals(self::$data['userdirectoryid']['saml'], $db_result_user['userdirectoryid']);

		// Check that user media data in the database is correct.
		$db_result_user_media_data = DBselect('SELECT mediatypeid, sendto FROM media WHERE userid='.
			zbx_dbstr(self::$data['userid']['saml_user_active'])
		);
		$db_result_user_media = DBfetch($db_result_user_media_data);

		$this->assertEquals($user['user_mobile'], $db_result_user_media['sendto']);
		$this->assertEquals(self::$data['mediatypeid']['SMS'], $db_result_user_media['mediatypeid']);

		// Check group mappings when user 'active' attribute is changed.
		if ($user['active'] === false || array_key_exists('update', $user)) {
			// Check that user data is still present in 'user_scim_group' table.
			$db_result_user_scim_group_data = DBselect('SELECT * FROM user_scim_group WHERE userid='.
				zbx_dbstr(self::$data['userid']['saml_user_active'])
			);
			$db_result_user_scim_group = DBfetch($db_result_user_scim_group_data);
			$this->assertEquals(self::$data['scim_groupids']['group_w_members'],
				$db_result_user_scim_group['scim_groupid']
			);

			// Check that user is added to 'Disabled' group or added back to its mapped group.
			$db_result_user_groups_data = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.
				zbx_dbstr(self::$data['userid']['saml_user_active'])
			);
			$db_result_user_groups = DBfetch($db_result_user_groups_data);
			$usrgrp = $user['active'] === false ? '9' : '7';

			$this->assertEquals($usrgrp, $db_result_user_groups['usrgrpid']);
		}
	}

	public function createInvalidPatchRequest(): array {
		return [
			'Patch request with missing schemas parameter' => [
				'user' => [
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "schemas" is missing.',
					'status' => 400
				]
			],
			'Patch request with invalid schemas parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Incorrect schema was sent in the request.',
					'status' => 400
				]
			],
			'Patch request with empty schemas parameter' => [
				'user' => [
					'schemas' => [],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/schemas": cannot be empty.',
					'status' => 400
				]
			],
			'Patch request with missing id parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "id" is missing.',
					'status' => 400
				]
			],
			'Patch request with non-existing id parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => '1111111111111',
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Patch request with missing Operations parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "Operations" is missing.',
					'status' => 400
				]
			],
			'Patch request with empty Operations parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations": cannot be empty.',
					'status' => 400
				]
			],
			'Patch request is missing "Operations"/"path" parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'Add',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1": the parameter "path" is missing.',
					'status' => 400
				]
			],
			'Patch request is missing "Operations"/"op" parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'path'=> 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1": the parameter "op" is missing.',
					'status' => 400
				]
			],
			'Patch request has invalid "Operations"/"op" parameter' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'Delete',
							'path'=> 'user_name',
							'value' => 'Andy'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1/op": value must be one of "add", "remove", '.
						'"replace", "Add", "Remove", "Replace".',
					'status' => 400
				]
			],
			'Patch request has with "Operations/op" "add" is missing "value" parameter.' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'add',
							'path'=> 'user_name'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1": the parameter "value" is missing.',
					'status' => 400
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPatchRequest
	 */
	public function testScimUser_PatchInvalid(array $user, array $expected_error): void {
		$this->resolveData($user);

		$this->call('users.patch', $user, $expected_error);
	}

	public function createValidPatchRequest(): array {
		return [
			'Patch request to add user name, user lastname' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'userName' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_name',
							'value' => 'Andy'
						],
						[
							'op' => 'Add',
							'path'=> 'user_lastname',
							'value' => 'Bernard'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_only_username',
					'id' => 'saml_user_only_username',
					'active' => true,
					'surname' => 'Bernard',
					'name' => ['givenName' => '', 'familyName' => '']
				]
			],
			'Patch request to update user status active from true to false' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'replace',
							'path' => 'active',
							'value' => false
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_only_username',
					'id' => 'saml_user_only_username',
					'active' => false,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			],
			'Patch request to update user status active from false to true' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_only_username',
					'Operations' => [
						[
							'op' => 'replace',
							'path' => 'active',
							'value' => true
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_only_username',
					'id' => 'saml_user_only_username',
					'active' => true,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			],
			'Patch request to add new user media to user already existing media' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_with_media',
					'userName' => 'saml_user_with_media',
					'Operations' => [
						[
							'op' => 'Add',
							'path' => 'user_mobile',
							'value' => '123456789'
						],
						[
							'op' => 'Add',
							'path'=> 'user_email',
							'value' => 'updated.email@example.com'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_with_media',
					'id' => 'saml_user_with_media',
					'active' => true,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			],
			'Patch request to update one user media added on previous step' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_with_media',
					'userName' => 'saml_user_with_media',
					'Operations' => [
						[
							'op' => 'Replace',
							'path' => 'user_mobile',
							'value' => '555'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_with_media',
					'id' => 'saml_user_with_media',
					'active' => true,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			],
			'Patch request to remove user media updated on previous step' => [
				'user' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'saml_user_with_media',
					'userName' => 'saml_user_with_media',
					'Operations' => [
						[
							'op' => 'Remove',
							'path' => 'user_mobile'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
					'userName' => 'saml_user_with_media',
					'id' => 'saml_user_with_media',
					'active' => true,
					'name' => ['givenName' => '', 'familyName' => '']
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPatchRequest
	 */
	public function testScimUser_PatchValid(array $user, array $expected_result): void {
		$this->resolveData($user);
		$this->resolveData($expected_result);
		$not_provisioned_medias = DBfetchArray(DBselect(
			'SELECT mediaid,sendto'.
			' FROM media m'.
			' WHERE '.dbConditionId('m.userid', [$user['id']]).
				' AND userdirectory_mediaid IS NULL'
		));
		$not_provisioned_medias = array_column($not_provisioned_medias, 'sendto', 'mediaid');

		$result = $this->call('users.patch', $user);

		// Compare result with expected result.
		foreach ($expected_result as $key => $expected) {
			$this->assertArrayHasKey($key, $result);
			$this->assertEquals($expected, $result[$key], 'Returned response should match.');
		}

		// Check that user data in the database is correct.
		$db_result_user_data = DBSelect('SELECT username, name, surname, userdirectoryid FROM users WHERE userid='.
			$user['id']
		);
		$db_result_user = DBFetch($db_result_user_data);
		$db_medias = DBfetchArray(DBselect(
			'SELECT m.mediaid,m.sendto,mp.attribute'.
			' FROM media m'.
			' LEFT JOIN userdirectory_media mp ON mp.userdirectory_mediaid=m.userdirectory_mediaid'.
			' WHERE '.dbConditionId('m.userid', [$user['id']])
		));
		$updated_medias = $db_medias;
		$active = [];

		foreach ($user['Operations'] as $operation) {
			switch ($operation['path']) {
				case 'userName':
					$this->assertEquals($operation['value'], $db_result_user['username']);
					break;

				case 'user_name':
					$this->assertEquals($operation['value'], $db_result_user['name']);
					break;

				case 'user_lastname':
					$this->assertEquals($operation['value'], $db_result_user['surname']);
					break;

				case 'active':
					$active = $operation;
					break;

				case 'user_mobile':
				case 'user_email':
					$op = strtolower($operation['op']);

					foreach ($updated_medias as $i => $updated_media) {
						if ($updated_media['attribute'] !== $operation['path']) {
							continue;
						}

						if ($op === 'replace' || $op === 'add') {
							$updated_medias[$i]['sendto'] = $operation['value'];
						}
						else {
							unset($updated_medias[$i]);
						}
					}

					break;
			}
		}

		foreach ($db_medias as $db_media) {
			// Database field null value is converted to string '0'.
			if ($db_media['attribute'] === '0' && array_key_exists($db_media['mediaid'], $not_provisioned_medias)
					&& $db_media['sendto'] === $not_provisioned_medias[$db_media['mediaid']]) {
				unset($not_provisioned_medias[$db_media['mediaid']]);
			}
		}

		$this->assertEquals([], $not_provisioned_medias);
		$this->assertEquals($db_medias, $updated_medias);
		$this->assertEquals(self::$data['userdirectoryid']['saml'], $db_result_user['userdirectoryid']);

		// Check group mappings when user 'active' attribute is changed.
		if ($active) {
			// Check that user data is still present in 'user_scim_group' table.
			$db_result_user_scim_group_data = DBselect('SELECT * FROM user_scim_group WHERE userid='.
				zbx_dbstr(self::$data['userid']['saml_user_only_username'])
			);
			$db_result_user_scim_group = DBfetch($db_result_user_scim_group_data);

			$this->assertEquals(self::$data['scim_groupids']['group_w_members'],
				$db_result_user_scim_group['scim_groupid']
			);

			// Check that user is added to 'Disabled' group or added back to its mapped group.
			$db_result_user_groups_data = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.
				zbx_dbstr(self::$data['userid']['saml_user_only_username'])
			);
			$db_result_user_groups = DBfetch($db_result_user_groups_data);
			$usrgrp = $active['value'] === false ? '9' : '7';

			$this->assertEquals($usrgrp, $db_result_user_groups['usrgrpid']);
		}
	}

	public function createInvalidDeleteRequest(): array {
		return [
			'Delete request with missing id parameter' => [
				'user' => [],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "id" is missing.',
					'status' => 400
				]
			],
			'Delete request with not existing id' => [
				'user' => ['id' => '1111111111111'],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Delete request for user which belongs to another userdirectory' => [
				'user' => ['id' => 'ldap_user'],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidDeleteRequest
	 */
	public function testScimUser_DeleteInvalid($user, $expected_error): void {
		$this->resolveData($user);

		$this->call('users.delete', $user, $expected_error);
	}

	public function createValidDeleteRequest(): array {
		return [
			'Delete existing user' => [
				'user' => ['id' => 'new_user'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User']
				]
			]
		];
	}

	/**
	 * @dataProvider createValidDeleteRequest
	 */
	public function testScimUser_DeleteValid($user, $expected_result) {
		$this->resolveData($user);

		$result = $this->call('users.delete', $user);

		// Compare response with expected response.
		$this->assertEquals($expected_result, $result, 'Returned response should match.');

		// Check that user is present in the database and does not have role.
		$db_result_user_data = DBSelect('SELECT roleid, userdirectoryid FROM users WHERE userid='.
			zbx_dbstr(self::$data['userid']['new_user'])
		);
		$db_result_user = DBFetch($db_result_user_data);

		$this->assertEquals('0', $db_result_user['roleid']);
		$this->assertEquals(self::$data['userdirectoryid']['saml'], $db_result_user['userdirectoryid']);

		// Check that user data is removed from 'user_scim_group' table.
		$db_result_user_scim_group_data = DBselect('SELECT * FROM user_scim_group WHERE userid='.
			zbx_dbstr(self::$data['userid']['new_user'])
		);
		$db_result_user_scim_group = DBfetch($db_result_user_scim_group_data);
		$this->assertEmpty($db_result_user_scim_group, 'User should not have any entries in "user_scim_group" table.');

		// Check that user is added to 'Disabled' group.
		$db_result_user_groups_data = DBselect('SELECT usrgrpid FROM users_groups WHERE userid='.
			zbx_dbstr(self::$data['userid']['new_user'])
		);
		$db_result_user_groups = DBfetch($db_result_user_groups_data);
		$this->assertEquals('9', $db_result_user_groups['usrgrpid']);

		$db_media_count = DB::select('media', [
			'filter' => ['userid' => self::$data['userid']['new_user']],
			'countOutput' => true
		]);
		$this->assertEquals(0, $db_media_count, 'User should not have any media');
	}

	public function createInvalidGetAuthentication() {
		return [
			'Admin tries to call SCIM User GET request' => [
				'user' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.get".',
					'status' => 403
				]
			],
			'User tries to call SCIM User GET request' => [
				'user' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.get".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM User GET request' => [
				'user' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.get".',
					'status' => 403
				]
			],
			'Call SCIM User GET request without token' => [
				'user' => [
					'token' => 'no_token'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Not authorized.',
					'status' => 403
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidGetAuthentication
	 */
	public function testScimUser_AuthenticationGetInvalid($user, $expected_error) {
		$user['token'] = self::$data['tokens'][$user['token']];

		CAPIScimHelper::setToken($user['token']);
		unset($user['token']);

		$this->call('users.get', $user, $expected_error);
	}

	public function createInvalidPutAuthentication() {
		return [
			'Admin tries to call SCIM User PUT request' => [
				'user' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.put".',
					'status' => 403
				]
			],
			'User tries to call SCIM User PUT request' => [
				'user' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.put".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM User PUT request' => [
				'user' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.put".',
					'status' => 403
				]
			],
			'Call SCIM User PUT request without token' => [
				'user' => [
					'token' => 'no_token'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Not authorized.',
					'status' => 403
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPutAuthentication
	 */
	public function testScimUser_AuthenticationPutInvalid($user, $expected_error) {
		$user['token'] = self::$data['tokens'][$user['token']];

		CAPIScimHelper::setToken($user['token']);
		unset($user['token']);

		$this->call('users.put', $user, $expected_error);
	}

	public function createInvalidPostAuthentication() {
		return [
			'Admin tries to call SCIM User POST request' => [
				'user' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.post".',
					'status' => 403
				]
			],
			'User tries to call SCIM User POST request' => [
				'user' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.post".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM User POST request' => [
				'user' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.post".',
					'status' => 403
				]
			],
			'Call SCIM User POST request without token' => [
				'user' => [
					'token' => 'no_token'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Not authorized.',
					'status' => 403
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPostAuthentication
	 */
	public function testScimUser_AuthenticationPostInvalid($user, $expected_error) {
		$user['token'] = self::$data['tokens'][$user['token']];

		CAPIScimHelper::setToken($user['token']);
		unset($user['token']);

		$this->call('users.post', $user, $expected_error);
	}

	public function createInvalidPatchAuthentication() {
		return [
			'Admin tries to call SCIM User PATCH request' => [
				'user' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.patch".',
					'status' => 403
				]
			],
			'User tries to call SCIM User PATCH request' => [
				'user' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.patch".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM User PATCH request' => [
				'user' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.patch".',
					'status' => 403
				]
			],
			'Call SCIM User PATCH request without token' => [
				'user' => [
					'token' => 'no_token'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Not authorized.',
					'status' => 403
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPatchAuthentication
	 */
	public function testScimUser_AuthenticationPatchInvalid($user, $expected_error) {
		$user['token'] = self::$data['tokens'][$user['token']];

		CAPIScimHelper::setToken($user['token']);
		unset($user['token']);

		$this->call('users.patch', $user, $expected_error);
	}

	public function createInvalidDeleteAuthentication() {
		return [
			'Admin tries to call SCIM User DELETE request' => [
				'user' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.delete".',
					'status' => 403
				]
			],
			'User tries to call SCIM User DELETE request' => [
				'user' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.delete".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM User DELETE request' => [
				'user' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "users.delete".',
					'status' => 403
				]
			],
			'Call SCIM User DELETE request without token' => [
				'user' => [
					'token' => 'no_token'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Not authorized.',
					'status' => 403
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidDeleteAuthentication
	 */
	public function testScimUser_AuthenticationDeleteInvalid($user, $expected_error) {
		$user['token'] = self::$data['tokens'][$user['token']];

		CAPIScimHelper::setToken($user['token']);
		unset($user['token']);

		$this->call('users.delete', $user, $expected_error);
	}

	/**
	 * Accepts test data and returns data with substituted ids and userNames from the database.
	 *
	 * @param array $user_data
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
		CDataHelper::call('authentication.update', ['saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED]);
		CDataHelper::call('userdirectory.delete', array_values(self::$data['userdirectoryid']));

		// Delete scim groups.
		DB::delete('scim_group', ['scim_groupid' => array_values(self::$data['scim_groupids'])]);

		// Delete token.
		CDataHelper::call('token.delete',  [self::$data['tokenids']['superadmin']]);
	}
}
