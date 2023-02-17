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
//			'ldap' => null,
			'saml' => null
		],
		'userids' => [
//			'ldap_user' => null,
			'user_active' => null,
			'user_inactive' => null
		],
		'usernames' => [
//			'ldap_user' => 'dwight.schrute@office.com',
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

	public function resolveData(array &$group_data): void {
		foreach ($group_data as $key => &$data) {
			if ($key === 'id' || $key === 'displayName') {
				$data_key = ($key === 'id') ? 'scim_groupids' : 'scim_group_names';

				$group_data[$key] = self::$data[$data_key][$data];
			}
			elseif ($key === 'members') {
				foreach ($data as &$member) {
					$member['value'] = self::$data['userids'][$member['value']];
					$member['display'] = self::$data['usernames'][$member['display']];
				}
			}
		}
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

