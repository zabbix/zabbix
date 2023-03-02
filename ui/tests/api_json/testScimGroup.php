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
class testScimGroup extends CAPIScimTest {

	private static $data = [
		'userdirectoryids' => [
			'ldap' => null,
			'saml' => null
		],
		'userids' => [
			'ldap_user' => null,
			'user_active' => null,
			'user_inactive' => null
		],
		'usernames' => [
			'ldap_user' => 'dwight.schrute@office.com',
			'user_active' => 'jim.halpert@office.com',
			'user_inactive' => 'pam.beesly@office.com'
		],
		'scim_groupids' => [
			'group_wo_members' => null,
			'group_w_members' => null
		],
		'scim_group_names' => [
			'group_wo_members' => 'office_administration',
			'group_w_members' => 'office_sales'
		],
		'user_scim_groupids' => [
			'user_group_w_members' => null
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

		// Create active user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [
			[
				'username' => self::$data['usernames']['user_active'],
				'userdirectoryid' => self::$data['userdirectoryids']['saml'],
				'name' => 'Jim',
				'surname' => 'Halpert',
				'usrgrps' => [['usrgrpid' => 7]],
				'medias' => [['mediatypeid' => self::$data['mediatypeid'], 'sendto' => '123456789']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['user_active'] = $user['userids'][0];

		// Create inactive user with newly created userdirectoryid for SAML.
		$user = CDataHelper::call('user.create', [			// TODO check at the end if this is still necessary.
			[
				'username' => self::$data['usernames']['user_inactive'],
				'userdirectoryid' => self::$data['userdirectoryids']['saml'],
				'name' => 'Pam',
				'surname' => 'Beesly',
				'usrgrps' => [['usrgrpid' => 9]],
				'medias' => [['mediatypeid' => self::$data['mediatypeid'], 'sendto' => '987654321']],
				'roleid' => 1
			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['user_inactive'] = $user['userids'][0];

		// Create SCIM group without members.
		$group_wo_members = DB::insert('scim_group', [['name' => self::$data['scim_group_names']['group_wo_members']]]);
		$this->assertNotEmpty($group_wo_members);
		self::$data['scim_groupids']['group_wo_members'] = $group_wo_members[0];

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
				'userdirectoryid' => self::$data['userdirectoryids']['ldap'],

			]
		]);
		$this->assertArrayHasKey('userids', $user);
		self::$data['userids']['ldap_user'] = $user['userids'][0];

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
	public function testInvalidGet($group, $expected_error) {
		$group['token'] = self::$data['token']['token'];

		$this->call('group.get', $group, $expected_error);
	}

	public function createValidGetRequest(): array {
		return [
			'Get request with no data (testing connection).' => [
				'group' => [],
				'expected_result' => [
					'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
					'totalResults' => 2,
					'startIndex' => 1,
					'itemsPerPage' => 2,
					'Resources' => [
						[
							'id' => 'group_wo_members',
							'displayName' => 'group_wo_members',
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
	public function testValidGet($group, $expected_result) {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		if (array_key_exists('Resources', $expected_result)) {
			foreach ($expected_result['Resources'] as &$resource) {
				$this->resolveData($resource);
			}
			unset($resource);
		}

		$group['token'] = self::$data['token']['token'];

		$result = $this->call('group.get', $group);

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
					'detail' =>
						'Invalid parameter "/schemas/1": value must be "urn:ietf:params:scim:schemas:core:2.0:Group".',
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
	public function testInvalidPost($group, $expected_error): void {
		$this->resolveData($group);

		$group['token'] = self::$data['token']['token'];

		$this->call('group.post', $group, $expected_error);
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
	public function testValidPost(array $group, array $expected_result): void {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		$group['token'] = self::$data['token']['token'];

		$result = $this->call('group.post', $group);

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
		$db_result_group_data = DBSelect('select name from scim_group where scim_groupid='.
			zbx_dbstr($result['id'])
		);
		$db_result_group = DBFetch($db_result_group_data);

		$this->assertEquals($group['displayName'], $db_result_group['name']);

		if ($group['members'] !== []) {
			// Check that scim group and user relations in database are correct.
			$db_result_user_group_data = DBSelect('select userid from user_scim_group where scim_groupid='.
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
					'detail' =>
						'Invalid parameter "/schemas/1": value must be "urn:ietf:params:scim:schemas:core:2.0:Group".',
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
			'Put request contains non-existing user as a member.' => [		// TODO need to fix Group.php, this is not validated
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
			'Put request contains user as a member that belongs to another userdirectory.' => [  // TODO need to fix Group.php, this is not validated
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
			'Put request is missing "id" parameter.' => [			// TODO In Group need to fix validation rules, so that id is required.
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
	public function testInvalidPut(array $group, array $expected_error) {
		$this->resolveData($group);

		$group['token'] = self::$data['token']['token'];

		$this->call('group.put', $group, $expected_error);
	}

	public function createValidPutRequest(): array {		// TODO, when ZBX-21976 is merged, need to add option to change status active to false or true.
		return [
			'Update SCIM group name without members via PUT request and add new member.' => [ // TODO need to add possibility to change group name
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
			]
		];
	}

	/**
	 * @dataProvider createValidPutRequest
	 */
	public function testValidPut(array $group, array $expected_result): void {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		$group['token'] = self::$data['token']['token'];

		$result = $this->call('group.put', $group);

		// Compare response with expected response.
		$this->assertEquals($expected_result, $result, 'Returned response should match.');

		// Check that scim group in the database is correct.
		$db_result_group_data = DBSelect('select name from scim_group where scim_groupid='.
			zbx_dbstr($result['id'])
		);
		$db_result_group = DBFetch($db_result_group_data);

		$this->assertEquals($group['displayName'], $db_result_group['name']);

		if ($group['members'] !== []) {
			// Check that scim group and user relations in database are correct.
			$db_result_user_group_data = DBSelect('select userid from user_scim_group where scim_groupid='.
				zbx_dbstr($result['id'])
			);
			$db_result_user_group = DBfetchColumn($db_result_user_group_data, 'userid');
			$expected_userids_in_group = array_column($group['members'], 'value');

			$this->assertEquals($expected_userids_in_group, $db_result_user_group);
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
	public function testInvalidDelete(array $group, array $expected_error): void {
		$group['token'] = self::$data['token']['token'];

		$this->call('group.delete', $group, $expected_error);
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
	public function testValidDelete(array $group, array $expected_result): void {
		$this->resolveData($group);
		$this->resolveData($expected_result);

		$group['token'] = self::$data['token']['token'];

		$result = $this->call('group.delete', $group);

		// Compare response with expected response.
		$this->assertEquals($expected_result, $result, 'Returned response should match.');

		// Check that scim group in the database is deleted.
		$db_result_group_data = DBSelect('select name from scim_group where scim_groupid='.
			zbx_dbstr($group['id'])
		);
		$db_result_group = DBFetch($db_result_group_data);

		$this->assertFalse($db_result_group);

		// Check that scim user group relations in database are deleted.
		$db_result_user_group_data = DBSelect('select userid from user_scim_group where scim_groupid='.
			zbx_dbstr($group['id'])
		);
		$db_result_user_group = DBfetchColumn($db_result_user_group_data, 'userid');

		$this->assertEmpty($db_result_user_group);
	}

	/**
	 * Resolves unknown parameters in the input data or expected results.
	 *
	 * @param array $group_data  Data to be resolved.
	 *
	 * @return void
	 */
	public function resolveData(array &$group_data): void {
		foreach ($group_data as $attribute_name => &$attribute_value) {
			if ($attribute_name === 'id' || $attribute_name === 'displayName') {
				$data_key = ($attribute_name === 'id') ? 'scim_groupids' : 'scim_group_names';

				if (array_key_exists($attribute_value, self::$data[$data_key])) {
					$group_data[$attribute_name] = self::$data[$data_key][$attribute_value];
				}
			}
			elseif ($attribute_name === 'members') {
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
		CDataHelper::call('userdirectory.delete', array_values(self::$data['userdirectoryids']));

		// Delete scim groups and its members.
		DB::delete('user_scim_group', ['userid' => array_values(self::$data['user_scim_groupids'])]);
		DB::delete('scim_group', ['scim_groupid' => array_values(self::$data['scim_groupids'])]);

		// Delete token.
		CDataHelper::call('token.delete', [self::$data['token']['tokenid']]);
	}
}

