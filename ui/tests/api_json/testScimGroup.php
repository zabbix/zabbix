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
 * @onBefore prepareGroupData
 *
 * @onAfter clearData
 */
class testScimGroup extends CAPIScimTest {

	private static $data = [
		'userdirectoryids' => [
			'ldap' => null,
			'saml' => null
		],
		'userids' => [
			'ldap_user' => null,
			'user_active' => null,
			'user_inactive' => null,
			'admin' => null,
			'user' => null,
			'guest_user' => null
		],
		'usernames' => [
			'ldap_user' => 'dwight.schrute@office.com',
			'user_active' => 'jim.halpert@office.com',
			'user_inactive' => 'pam.beesly@office.com'
		],
		'scim_groupids' => [
			'group_wo_members' => null,
			'group_w_members' => null,
			'group_for_name_change' => null
		],
		'scim_group_names' => [
			'group_wo_members' => 'office_administration',
			'group_w_members' => 'office_sales',
			'group_for_name_change' => 'office_reception'
		],
		'user_scim_groupids' => [
			'user_group_w_members' => null
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
		'mediatypeid' => '3'
	];

	public function prepareGroupData() {
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
					'name' => 'office*',
					'roleid' => 1,
					'user_groups' => [
						['usrgrpid' => 7]
					]
				]
			],
			'scim_status' => 1
		]);
		$this->assertArrayHasKey('userdirectoryids', $userdirectory_saml);
		self::$data['userdirectoryids']['saml'] = $userdirectory_saml['userdirectoryids'][0];

		CDataHelper::call('authentication.update', [
			'saml_auth_enabled' => ZBX_AUTH_SAML_ENABLED,
			'disabled_usrgrpid' => '9'
		]);

		// Create active user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['usernames']['user_active'],
				'passwd' => 'scimuserPassw0rd',
				'name' => 'Jim',
				'surname' => 'Halpert',
				'usrgrps' => [['usrgrpid' => 7]],
				'medias' => [['mediatypeid' => self::$data['mediatypeid'], 'sendto' => '123456789']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['user_active'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryids']['saml']],
			'where' => ['userid' => $user['userids'][0]]
		]);

		// Create inactive user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['usernames']['user_inactive'],
				'passwd' => 'scimuserPassw0rd',
				'name' => 'Pam',
				'surname' => 'Beesly',
				'usrgrps' => [['usrgrpid' => 9]],
				'medias' => [['mediatypeid' => self::$data['mediatypeid'], 'sendto' => '987654321']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['user_inactive'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryids']['saml']],
			'where' => ['userid' => $user['userids'][0]]
		]);

		// Create SCIM group without members.
		$group_wo_members = DB::insert('scim_group', [['name' => self::$data['scim_group_names']['group_wo_members']]]);
		$this->assertNotEmpty($group_wo_members);
		self::$data['scim_groupids']['group_wo_members'] = $group_wo_members[0];

		// Create SCIM group for name change.
		$group_for_name_change = DB::insert('scim_group',
			[['name' => self::$data['scim_group_names']['group_for_name_change']]]
		);
		$this->assertNotEmpty($group_for_name_change);
		self::$data['scim_groupids']['group_for_name_change'] = $group_for_name_change[0];

		// Create SCIM group and add a member to it.
		$group_w_members = DB::insert('scim_group', [['name' => self::$data['scim_group_names']['group_w_members']]]);
		$this->assertNotEmpty($group_w_members);
		self::$data['scim_groupids']['group_w_members'] = $group_w_members[0];

		$user_scim_group = DB::insert('user_scim_group', [[
			'userid' => self::$data['userids']['user_active'],
			'scim_groupid' => self::$data['scim_groupids']['group_w_members']
		]]);
		$this->assertNotEmpty($user_scim_group);
		self::$data['user_scim_groupids']['user_group_w_members'] = $user_scim_group[0];

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
		self::$data['userdirectoryids']['ldap'] = $userdirectory_ldap['userdirectoryids'][0];

		// Create user with newly created userdirectoryid for LDAP.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['usernames']['ldap_user'],
				'passwd' => 'scimuserPassw0rd'
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['ldap_user'] = $user['userids'][0];
		DB::update('users', [
			'values' => ['userdirectoryid' => self::$data['userdirectoryids']['ldap']],
			'where' => ['userid' => $user['userids'][0]]
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
		self::$data['userids']['admin'] = $user['userids'][0];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'user',
				'passwd' => 'testtest123',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['user'] = $user['userids'][0];

		$user = CDataHelper::call('user.create', [
			[
				'username' => 'guest_user',
				'passwd' => 'testtest123',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 4
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['guest_user'] = $user['userids'][0];

		// Create authorization token for each user with different user role for authorization testing.
		$tokenid = CDataHelper::call('token.create', [
			[
				'name' => 'Token for admin',
				'userid' => self::$data['userids']['admin']
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
				'userid' => self::$data['userids']['user']
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
				'userid' => self::$data['userids']['guest_user']
			]
		]);
		$this->assertArrayHasKey('tokenids', $tokenid);
		self::$data['tokenids']['guest_user'] = $tokenid['tokenids'][0];
		$token = CDataHelper::call('token.generate', [self::$data['tokenids']['guest_user']]);
		$this->assertArrayHasKey('token', $token[0]);
		self::$data['tokens']['guest_user'] = $token[0]['token'];
	}

	public function createInvalidGetRequest(): array {
		return [
			'Get request with non exiting SCIM group id' => [
				'group' => ['id' => '123456789'],
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
	public function testScimGroup_GetInvalid($group, $expected_error) {
		$this->call('groups.get', $group, $expected_error);
	}

	public function createValidGetRequest(): array {
		return [
			'Get request with no data (testing connection).' => [
				'group' => [],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
					'totalResults' => 3,
					'startIndex' => 1,
					'itemsPerPage' => 3,
					'Resources' => [
						[
							'id' => 'group_wo_members',
							'displayName' => 'group_wo_members',
							'members' => []
						],
						[
							'id' => 'group_for_name_change',
							'displayName' => 'group_for_name_change',
							'members' => []
						],
						[
							'id' => 'group_w_members',
							'displayName' => 'group_w_members',
							'members' => [
								[
									'value' => 'user_active',
									'display' => 'user_active'
								]
							]
						]
					]
				]
			],
			'Get request with id of existing group, which has no members.' => [
				'group' => ['id' => 'group_wo_members'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'group_wo_members',
					'members' => []
				]
			],
			'Get request with id of existing group, which has a member.' => [
				'group' => ['id' => 'group_w_members'],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'value' => 'user_active',
							'display' => 'user_active'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider createValidGetRequest
	 */
	public function testScimGroup_GetValid($group, $expected_result) {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		if (array_key_exists('Resources', $expected_result)) {
			foreach ($expected_result['Resources'] as &$resource) {
				$this->resolveData($resource);
			}
			unset($resource);
		}

		$result = $this->call('groups.get', $group);

		$this->assertEquals($expected_result, $result, 'Returned response should match.');
	}

	public function createInvalidPostRequest(): array {
		return [
			'Post request is missing parameter "schema".' => [
				'group' => [
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "schemas" is missing.',
					'status' => 400
				]
			],
			'Post request contains empty "schemas" parameter.' => [
				'group' => [
					'schemas' => [],
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/schemas": cannot be empty.',
					'status' => 400
				]
			],
			'Post request contains invalid "schemas" parameter.' => [
				'group' => [
					'schemas' => ['invalid:schema'],
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Incorrect schema was sent in the request.',
					'status' => 400
				]
			],
			'Post request is missing "displayName" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "displayName" is missing.',
					'status' => 400
				]
			],
			'Post request contains empty "displayName" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => '',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/displayName": cannot be empty.',
					'status' => 400
				]
			],
			'Post request contains empty "members"/"value" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => 'user_active',
							'value' => ''
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1/value": a number is expected.',
					'status' => 400
				]
			],
			'Post request is missing "members"/"value" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => 'user_active'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1": the parameter "value" is missing.',
					'status' => 400
				]
			],
			'Post request contains empty "members"/"display" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => '',
							'value' => 'user_active'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1/display": cannot be empty.',
					'status' => 400
				]
			],
			'Post request is missing "members"/"display" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => [
						[
							'value' => 'user_active'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1": the parameter "display" is missing.',
					'status' => 400
				]
			],
			'Post request contains non-existing user as a member.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => 'creed.bratton@office.com',
							'value' => '999999999'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Post request contains user as a member that belongs to another userdirectory.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => 'ldap_user',
							'value' => 'ldap_user'
						]
					]
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
	 * @dataProvider createInvalidPostRequest
	 */
	public function testScimGroup_PostInvalid($group, $expected_error): void {
		$this->resolveData($group);
		$this->call('groups.post', $group, $expected_error);
	}

	public function createValidPostRequest(): array {
		return [
			'Create new SCIM group without members via POST request.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_it',
					'members' => []
				]
			],
			'Create new SCIM group with members via POST request.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_accounting',
					'members' => [
						[
							'display' => 'user_active',
							'value' => 'user_active'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'office_accounting',
					'members' => [
						[
							'display' => 'user_active',
							'value' => 'user_active'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPostRequest
	 */
	public function testScimGroup_PostValid(array $group, array $expected_result): void {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		$result = $this->call('groups.post', $group);

		// Compare response with expected response.
		foreach ($expected_result as $key => $expected) {
			$this->assertArrayHasKey($key, $result);
			$this->assertEquals($expected, $result[$key], 'Returned response should match.');
		}

		// Response should have 'id' value, which is not known for us, if completely new group is created.
		$this->assertArrayHasKey('id', $result);

		if ($result['displayName'] === 'office_it') {
			self::$data['scim_groupids']['new_group_wo_users'] = $result['id'];
		}
		else {
			self::$data['scim_groupids']['new_group_w_users'] = $result['id'];
		}

		// Check that scim group in the database is correct.
		$db_result_group_data = DBSelect('SELECT name FROM scim_group WHERE scim_groupid='.
			zbx_dbstr($result['id'])
		);
		$db_result_group = DBFetch($db_result_group_data);

		$this->assertEquals($group['displayName'], $db_result_group['name']);

		if ($group['members'] !== []) {
			// Check that scim group and user relations in database are correct.
			$db_result_user_group_data = DBSelect('SELECT userid FROM user_scim_group WHERE scim_groupid='.
				zbx_dbstr($result['id'])
			);
			$db_result_user_group = DBFetch($db_result_user_group_data);
			$expected_userids_in_group = array_column($group['members'], 'value');

			$this->assertEquals($expected_userids_in_group, [$db_result_user_group['userid']]);
		}
	}

	public function createInvalidPutRequest(): array {
		return [
			'Put request is missing parameter "schema".' => [
				'group' => [
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "schemas" is missing.',
					'status' => 400
				]
			],
			'Put request contains empty "schemas" parameter.' => [
				'group' => [
					'schemas' => [],
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/schemas": cannot be empty.',
					'status' => 400
				]
			],
			'Put request contains invalid "schemas" parameter.' => [
				'group' => [
					'schemas' => ['invalid:schema'],
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Incorrect schema was sent in the request.',
					'status' => 400
				]
			],
			'Put request is missing "displayName" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "displayName" is missing.',
					'status' => 400
				]
			],
			'Put request contains empty "displayName" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => '',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/displayName": cannot be empty.',
					'status' => 400
				]
			],
			'Put request contains empty "members"/"value" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => 'user_active',
							'value' => ''
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1/value": a number is expected.',
					'status' => 400
				]
			],
			'Put request is missing "members"/"value" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => 'user_active'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1": the parameter "value" is missing.',
					'status' => 400
				]
			],
			'Put request contains empty "members"/"display" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => [
						[
							'display' => '',
							'value' => 'user_active'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1/display": cannot be empty.',
					'status' => 400
				]
			],
			'Put request is missing "members"/"display" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'office_it',
					'members' => [
						[
							'value' => 'user_active'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/members/1": the parameter "display" is missing.',
					'status' => 400
				]
			],
			'Put request contains non-existing user as a member.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'display' => 'creed.bratton@office.com',
							'value' => '999999999'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Put request contains user as a member that belongs to another userdirectory.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'display' => 'ldap_user',
							'value' => 'ldap_user'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Put request is missing "id" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'displayName' => 'group_w_members',
					'members' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "id" is missing.',
					'status' => 400
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidPutRequest
	 */
	public function testScimGroup_PutInvalid(array $group, array $expected_error) {
		$this->resolveData($group);

		$this->call('groups.put', $group, $expected_error);
	}

	public function createValidPutRequest(): array {
		return [
			'Update SCIM group name without members via PUT request and add new member.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'group_wo_members',
					'members' => [
						[
							'display' => 'user_active',
							'value' => 'user_active'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'group_wo_members',
					'members' => [
						[
							'display' => 'user_active',
							'value' => 'user_active'
						]
					]
				]
			],
			'Update SCIM group with members via PUT request - add new member.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'display' => 'user_active',
							'value' => 'user_active'
						],
						[
							'display' => 'user_inactive',
							'value' => 'user_inactive'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'display' => 'user_active',
							'value' => 'user_active'
						],
						[
							'display' => 'user_inactive',
							'value' => 'user_inactive'
						]
					]
				]
			],
			'Update SCIM group - leave the group empty' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'group_wo_members',
					'members' => []
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'group_wo_members',
					'members' => []
				]
			],
			'Update SCIM group - change group name' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'new_name',
					'members' => []
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'new_name',
					'members' => []
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPutRequest
	 */
	public function testScimGroup_PutValid(array $group, array $expected_result): void {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		$result = $this->call('groups.put', $group);

		// Compare response with expected response.
		$this->assertEquals($expected_result, $result, 'Returned response should match.');

		// Check that scim group in the database is correct.
		$db_result_group_data = DBSelect('SELECT name FROM scim_group WHERE scim_groupid='.
			zbx_dbstr($result['id'])
		);
		$db_result_group = DBFetch($db_result_group_data);

		$this->assertEquals($group['displayName'], $db_result_group['name']);

		if ($group['members'] !== []) {
			// Check that scim group and user relations in database are correct.
			$db_result_user_group_data = DBSelect('SELECT userid FROM user_scim_group WHERE scim_groupid='.
				zbx_dbstr($result['id'])
			);
			$db_result_user_group = DBfetchColumn($db_result_user_group_data, 'userid');
			$expected_userids_in_group = array_column($group['members'], 'value');

			$this->assertEquals($expected_userids_in_group, $db_result_user_group);
		}
	}

	public function createInvalidPatchRequest(): array {
		return [
			'Patch request is missing "schema" parameter' => [
				'group' => [
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => '3']
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "schemas" is missing.',
					'status' => 400
				]
			],
			'Patch request contains empty "schema" parameter' => [
				'group' => [
					'schemas' => [],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/schemas": cannot be empty.',
					'status' => 400
				]
			],
			'Patch request is contains incorrect "schema" parameter' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Incorrect schema was sent in the request.',
					'status' => 400
				]
			],
			'Patch request is missing "id" parameter' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "id" is missing.',
					'status' => 400
				]
			],
			'Patch request is missing "Operations" parameter' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "Operations" is missing.',
					'status' => 400
				]
			],
			'Patch request contains empty "Operations" parameter' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => []
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations": cannot be empty.',
					'status' => 400
				]
			],
			'Patch request is missing "Operations"/"path" parameter' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1": the parameter "path" is missing.',
					'status' => 400
				]
			],
			'Patch request contains invalid "Operations"/"path" parameter' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add',
							'path' => 'users'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1/path": value must be one of "members", "externalId",'.
						' "displayName".',
					'status' => 400
				]
			],
			'Patch request is missing "operations"/"op" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1": the parameter "op" is missing.',
					'status' => 400
				]
			],
			'Patch request contains invalid "Operations"/"op" parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'delete',
							'path' => 'members'
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
			'Patch request with displayName path contains incorrect op parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add',
							'path' => 'displayName'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1/op": value must be one of "replace", "Replace".',
					'status' => 400
				]
			],
			'Patch request with displayName path contains incorrect value parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'replace',
							'path' => 'displayName'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1/value": a character string is expected.',
					'status' => 400
				]
			],
			'Patch request with path members has incorrect value parameter - as string.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => '3',
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1/value": an array is expected.',
					'status' => 400
				]
			],
			'Patch request with path members has empty value parameter.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/Operations/1/value": cannot be empty.',
					'status' => 400
				]
			],
			'Patch request with invalid id number.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => '9999999999999',
					'Operations' => [
						[
							'value' => [
								['value' => "3"]
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Patch request with invalid member id number.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => '9999999999999']
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to referred object or it does not exist!',
					'status' => 404
				]
			],
			'Patch request contains user that belongs to other userdirectory.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => 'ldap_user']
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
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
	 * @dataProvider createInvalidPatchRequest
	 */
	public function testScimGroup_PatchInvalid(array $group, array $expected_error): void {
		$this->resolveData($group);
		$this->call('groups.patch', $group, $expected_error);
	}

	public function createValidPatchRequest(): array {
		return [
			'Patch request to change group name' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_for_name_change',
					'Operations' => [
						[
							'value' => 'office_management',
							'op' => 'replace',
							'path' => 'displayName'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_for_name_change',
					'displayName' => 'office_management',
					'members' => []
				]
			],
			'Patch request to remove one member from a group.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_w_members',
					'Operations' => [
						[
							'value' => [
								['value' => 'user_inactive']
							],
							'op' => 'remove',
							'path' => 'members'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => []
				]
			],
			'Patch request to add new member to group.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_w_members',
					'Operations' => [
						[
							'value' => [
								['value' => 'user_inactive']
							],
							'op' => 'add',
							'path' => 'members'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'value' => 'user_inactive',
							'display' => 'user_inactive'
						]
					]
				]
			],
			'Patch request to replace existing members and add only "user_active" member.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_w_members',
					'Operations' => [
						[
							'value' => [
								['value' => 'user_active']
							],
							'op' => 'replace',
							'path' => 'members'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_w_members',
					'displayName' => 'group_w_members',
					'members' => [
						[
							'value' => 'user_active',
							'display' => 'user_active'
						]
					]
				]
			],
			'Patch request to replace group name and add two members to a group.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'value' => [
								['value' => 'user_active']
							],
							'op' => 'add',
							'path' => 'members'
						],
						[
							'value' => [
								['value' => 'user_inactive']
							],
							'op' => 'add',
							'path' => 'members'
						],
						[
							'value' => 'new_group_name',
							'op' => 'replace',
							'path' => 'displayName'
						]
					]
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'new_group_name',
					'members' => [
						[
							'value' => 'user_active',
							'display' => 'user_active'
						],
						[
							'value' => 'user_inactive',
							'display' => 'user_inactive'
						]
					]
				]
			],
			'Patch request to change group name back and remove all the existing users in the group.' => [
				'group' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
					'id' => 'group_wo_members',
					'Operations' => [
						[
							'op' => 'remove',
							'path' => 'members'
						],
						[
							'value' => 'office_administration',
							'op' => 'replace',
							'path' => 'displayName'
						]
					]
				],
				'expected_results' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
					'id' => 'group_wo_members',
					'displayName' => 'group_wo_members',
					'members' => []
				]
			]
		];
	}

	/**
	 * @dataProvider createValidPatchRequest
	 */
	public function testScimGroup_PatchValid(array $group, array $expected_result) {
		$this->resolveData($group, true);
		$this->resolveData($expected_result);

		$result = $this->call('groups.patch', $group);
		CTestArrayHelper::usort($result['members'], ['value']);

		// Compare response with expected response.
		$this->assertEquals($expected_result, $result, 'Returned response should match.');

		foreach ($group['Operations'] as $operation) {
			if ($operation['path'] === 'displayName') {
				// Check that scim group in the database is correct.
				$db_result_group_data = DBSelect('SELECT name FROM scim_group WHERE scim_groupid='.
					zbx_dbstr($result['id'])
				);
				$db_result_group = DBFetch($db_result_group_data);

				$this->assertEquals($operation['value'], $db_result_group['name']);
			}
			elseif ($operation['path'] === 'members') {
				$db_result_user_group_data = DBSelect('SELECT userid FROM user_scim_group WHERE scim_groupid='.
					zbx_dbstr($group['id'])
				);
				$db_result_user_group = DBfetchColumn($db_result_user_group_data, 'userid');

				switch ($operation['op']) {
					case 'add':
						$this->assertContains($operation['value'][0]['value'], $db_result_user_group);
						break;

					case 'remove':
						if (array_key_exists('value', $operation)) {
							$this->assertNotContains($operation['value'][0]['value'], $db_result_user_group);
						}
						else {
							$this->assertEmpty($db_result_user_group);
						}
						break;

					case 'replace':
						$this->assertEquals([$operation['value'][0]['value']], $db_result_user_group);
						break;
				}
			}
		}
	}

	public function createInvalidDeleteRequest(): array {
		return [
			'Delete request is missing group id.' => [
				'group' => [],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'Invalid parameter "/": the parameter "id" is missing.',
					'status' => 400
				]
			]
		];
	}

	/**
	 * @dataProvider createInvalidDeleteRequest
	 */
	public function testScimGroup_DeleteInvalid(array $group, array $expected_error): void {
		$this->call('groups.delete', $group, $expected_error);
	}

	public function createValidDeleteRequest(): array {
		return [
			'Delete SCIM group without members' => [
				'group' => [
					'id' => 'new_group_wo_users'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group']
				]
			],
			'Delete SCIM group with members' => [
				'group' => [
					'id' => 'new_group_w_users'
				],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group']
				]
			]
		];
	}

	/**
	 * @dataProvider createValidDeleteRequest
	 */
	public function testScimGroup_DeleteValid(array $group, array $expected_result): void {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		$result = $this->call('groups.delete', $group);

		// Compare response with expected response.
		$this->assertEquals($expected_result, $result, 'Returned response should match.');

		// Check that scim group in the database is deleted.
		$db_result_group_data = DBSelect('SELECT name FROM scim_group WHERE scim_groupid='.
			zbx_dbstr($group['id'])
		);
		$db_result_group = DBFetch($db_result_group_data);

		$this->assertFalse($db_result_group);

		// Check that scim user group relations in database are deleted.
		$db_result_user_group_data = DBSelect('SELECT userid FROM user_scim_group WHERE scim_groupid='.
			zbx_dbstr($group['id'])
		);
		$db_result_user_group = DBfetchColumn($db_result_user_group_data, 'userid');

		$this->assertEmpty($db_result_user_group);
	}

	public function createInvalidGetAuthentication() {
		return [
			'Admin tries to call SCIM Group GET request' => [
				'group' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.get".',
					'status' => 403
				]
			],
			'User tries to call SCIM Group GET request' => [
				'group' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.get".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM Group GET request' => [
				'group' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.get".',
					'status' => 403
				]
			],
			'Call SCIM Group GET request without token' => [
				'group' => [
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
	public function testScimGroup_AuthenticationGetInvalid($group, $expected_error) {
		$group['token'] = self::$data['tokens'][$group['token']];

		CAPIScimHelper::setToken($group['token']);
		unset($group['token']);

		$this->call('groups.get', $group, $expected_error);
	}

	public function createInvalidPutAuthentication() {
		return [
			'Admin tries to call SCIM Group PUT request' => [
				'group' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.put".',
					'status' => 403
				]
			],
			'User tries to call SCIM Group PUT request' => [
				'group' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.put".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM Group PUT request' => [
				'group' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.put".',
					'status' => 403
				]
			],
			'Call SCIM Group PUT request without token' => [
				'group' => [
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
	public function testScimGroup_AuthenticationPutInvalid($group, $expected_error) {
		$group['token'] = self::$data['tokens'][$group['token']];

		CAPIScimHelper::setToken($group['token']);
		unset($group['token']);

		$this->call('groups.put', $group, $expected_error);
	}

	public function createInvalidPostAuthentication() {
		return [
			'Admin tries to call SCIM Group POST request' => [
				'group' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.post".',
					'status' => 403
				]
			],
			'User tries to call SCIM Group POST request' => [
				'group' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.post".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM Group POST request' => [
				'group' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.post".',
					'status' => 403
				]
			],
			'Call SCIM Group POST request without token' => [
				'group' => [
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
	public function testScimGroup_AuthenticationPostInvalid($group, $expected_error) {
		$group['token'] = self::$data['tokens'][$group['token']];

		CAPIScimHelper::setToken($group['token']);
		unset($group['token']);

		$this->call('groups.post', $group, $expected_error);
	}

	public function createInvalidPatchAuthentication() {
		return [
			'Admin tries to call SCIM Group PATCH request' => [
				'group' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.patch".',
					'status' => 403
				]
			],
			'User tries to call SCIM Group PATCH request' => [
				'group' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.patch".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM Group PATCH request' => [
				'group' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.patch".',
					'status' => 403
				]
			],
			'Call SCIM Group PATCH request without token' => [
				'group' => [
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
	public function testScimGroup_AuthenticationPatchInvalid($group, $expected_error) {
		$group['token'] = self::$data['tokens'][$group['token']];

		CAPIScimHelper::setToken($group['token']);
		unset($group['token']);

		$this->call('groups.patch', $group, $expected_error);
	}

	public function createInvalidDeleteAuthentication() {
		return [
			'Admin tries to call SCIM Group DELETE request' => [
				'group' => [
					'token' => 'admin'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.delete".',
					'status' => 403
				]
			],
			'User tries to call SCIM Group DELETE request' => [
				'group' => [
					'token' => 'user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.delete".',
					'status' => 403
				]
			],
			'Guest tries to call SCIM Group DELETE request' => [
				'group' => [
					'token' => 'guest_user'
				],
				'expected_error' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
					'detail' => 'No permissions to call "groups.delete".',
					'status' => 403
				]
			],
			'Call SCIM Group DELETE request without token' => [
				'group' => [
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
	public function testScimGroup_AuthenticationDeleteInvalid($group, $expected_error) {
		$group['token'] = self::$data['tokens'][$group['token']];

		CAPIScimHelper::setToken($group['token']);
		unset($group['token']);

		$this->call('groups.delete', $group, $expected_error);
	}

	/**
	 * Resolves unknown parameters in the input data or expected results.
	 *
	 * @param array $group_data  Data to be resolved.
	 */
	public function resolveData(array &$group_data): void {
		foreach ($group_data as $attribute_name => &$attribute_value) {
			switch ($attribute_name) {
				case 'id':
				case 'displayName':
					$data_key = ($attribute_name === 'id') ? 'scim_groupids' : 'scim_group_names';

					if (array_key_exists($attribute_value, self::$data[$data_key])) {
						$group_data[$attribute_name] = self::$data[$data_key][$attribute_value];
					}
					break;

				case 'members':
					foreach ($attribute_value as &$member) {
						if (array_key_exists('value', $member)
							&& array_key_exists($member['value'], self::$data['userids'])) {
							$member['value'] = self::$data['userids'][$member['value']];
						}

						if (array_key_exists('display', $member)
							&& array_key_exists($member['display'], self::$data['usernames'])) {
							$member['display'] = self::$data['usernames'][$member['display']];
						}
					}
					unset($member);
					break;

				case 'Operations':
					foreach ($group_data['Operations'] as &$operation) {
						if (array_key_exists('path', $operation) && $operation['path'] === 'members'
							&& array_key_exists('value', $operation) && is_array($operation['value'])) {
							foreach ($operation['value'] as &$value) {
								if (array_key_exists($value['value'], self::$data['userids'])) {
									$value['value'] = self::$data['userids'][$value['value']];
								}
							}
							unset($value);
						}
					}
					unset($operation);
					break;
			}
		}
		unset($attribute_value);
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		// Delete users.
		CDataHelper::call('user.delete', array_values(self::$data['userids']));

		// Delete userdirectories.
		CDataHelper::call('authentication.update', ['saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED]);
		CDataHelper::call('userdirectory.delete', array_values(self::$data['userdirectoryids']));

		// Delete scim groups and its members.
		DB::delete('user_scim_group', ['userid' => array_values(self::$data['user_scim_groupids'])]);
		DB::delete('scim_group', ['scim_groupid' => array_values(self::$data['scim_groupids'])]);

		// Delete token.
		CDataHelper::call('token.delete', [self::$data['tokenids']['superadmin']]);
	}
}

