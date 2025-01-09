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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @onBefore prepareTestData
 *
 * @backup hstgrp
 */
class testHostGroup extends CAPITest {

	public static function hostgroup_create() {
		return [
			[
				'hostgroup' => [
					'name' => 'non existent parameter',
					'flags' => '4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			// Check hostgroup name.
			[
				'hostgroup' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'hostgroup' => [
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check for duplicated host groups names.
			[
				'hostgroup' => [
					'name' => 'Zabbix servers'
				],
				'expected_error' => 'Host group "Zabbix servers" already exists.'
			],
			[
				'hostgroup' => [
					[
						'name' => 'One host group with existing name'
					],
					[
						'name' => 'Zabbix servers'
					]
				],
				'expected_error' => 'Host group "Zabbix servers" already exists.'
			],
			[
				'hostgroup' => [
					[
						'name' => 'Host groups with two identical names'
					],
					[
						'name' => 'Host groups with two identical names'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(Host groups with two identical names) already exists.'
			],
			// Check successfully create.
			[
				'hostgroup' => [
					[
						'name' => 'API host group create'
					]
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
						'name' => '☺'
					],
					[
						'name' => 'æų'
					]
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
						'name' => 'АПИ хост группа УТФ-8'
					]
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
						'name' => 'API'
					],
					[
						'name' => 'API/Nested'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider hostgroup_create
	*/
	public function testHostGroup_Create($hostgroup, $expected_error) {
		$result = $this->call('hostgroup.create', $hostgroup, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $index => $groupid) {
				$dbRow = CDBHelper::getRow('SELECT name,flags FROM hstgrp WHERE groupid='.$groupid);
				$this->assertEquals($dbRow['name'], $hostgroup[$index]['name']);
				$this->assertEquals($dbRow['flags'], 0);
			}
		}
	}

	public static function hostgroup_update() {
		return [
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'non existent parameter',
					'flags' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			// Check groupid.
			[
				'hostgroup' => [
					[
					'name' => 'without groupid'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "groupid" is missing.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '',
					'name' => 'empty groupid'
					]
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '123456',
					'name' => 'groupid with not existing id'
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					[
					'groupid' => 'abc',
					'name' => 'id not number'
					]
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '0.0',
					'name' => 'æųæų'
					]
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			// Check name.
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256'
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'Zabbix servers'
					]
				],
				'expected_error' => 'Host group "Zabbix servers" already exists.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'API update two host group with the same names'
					],
					[
					'groupid' => '50006',
					'name' => 'API update two host group with the same names'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(API update two host group with the same names) already exists.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'update host group twice1'
					],
					[
					'groupid' => '50005',
					'name' => 'update host group twice2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (groupid)=(50005) already exists.'
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50015',
					'name' => 'API updated discovered group'
					]
				],
				'expected_error' => 'Cannot update a discovered host group "API discovery group {#HV.NAME}".'
			],
			// Check successfully update.
			[
				'hostgroup' => [
					[
					'groupid' => '50005',
					'name' => 'API host group updated'
					]
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					[
					'groupid' => '50006',
					'name' => 'API internal host group updated'
					],
					[
					'groupid' => '50005',
					'name' => 'Апи УТФ-8 обновлённый'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider hostgroup_update
	*/
	public function testHostGroup_Update($hostgroups, $expected_error) {
		$result = $this->call('hostgroup.update', $hostgroups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $index => $groupid) {
				$dbRow = CDBHelper::getRow('SELECT name,flags FROM hstgrp WHERE groupid='.$groupid);
				$this->assertEquals($dbRow['name'], $hostgroups[$index]['name']);
				$this->assertEquals($dbRow['flags'], 0);
			}
		}
		else {
			foreach ($hostgroups as $hostgroup) {
				if (array_key_exists('name', $hostgroup) && $hostgroup['name'] !== 'Zabbix servers'){
					$this->assertEquals(0,
						CDBHelper::getCount('SELECT * FROM hstgrp WHERE name='.zbx_dbstr($hostgroup['name']))
					);
				}
			}
		}
	}

	public static function hostgroup_delete() {
		return [
			[
				'hostgroup' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostgroup' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostgroup' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'hostgroup' => [
					'50008',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					'50008',
					'abc'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'hostgroup' => [
					'50008',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'hostgroup' => [
					'50008',
					'50008'
				],
				'expected_error' => 'Invalid parameter "/2": value (50008) already exists.'
			],
			[
				'hostgroup' => [
					'50014'
				],
				'expected_error' => 'Group "API group for host prototype" cannot be deleted, because it is used by a host prototype.'
			],
			[
				'hostgroup' => [
					'50005',
					'50012'
				],
				'expected_error' => 'Host "API Host" cannot be without host group.'
			],
			[
				'hostgroup' => [
					'50009'
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					'50010',
					'50011'
				],
				'expected_error' => null
			],
			// maintenance related
			[
				'hostgroup' => [
					'62002'
				],
				'expected_error' => 'Cannot delete host group "maintenance_has_only_group" because maintenance "maintenance_has_only_group" must contain at least one host or host group.'
			],
			[
				'hostgroup' => [
					'62002',
					'62003'
				],
				'expected_error' => 'Cannot delete host group "maintenance_has_only_group" because maintenance "maintenance_has_only_group" must contain at least one host or host group.'
			],
			[
				'hostgroup' => [
					'62003'
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					'62004',
					'62005'
				],
				'expected_error' => 'Cannot delete host groups "maintenance_group_1", "maintenance_group_2" because maintenance "maintenance_two_groups" must contain at least one host or host group.'
			],
			[
				'hostgroup' => [
					'62004'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider hostgroup_delete
	*/
	public function testHostGroup_Delete($hostgroups, $expected_error) {
		$result = $this->call('hostgroup.delete', $hostgroups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM hstgrp WHERE groupid='.zbx_dbstr($id)));
			}
		}
	}

	public static function hostgroup_user_permission() {
		return [
			[
				'method' => 'hostgroup.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'hostgroup' => [
					'name' => 'API host group create as zabbix admin'
				],
				'expected_error' => 'No permissions to call "hostgroup.create".'
			],
			[
				'method' => 'hostgroup.update',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'hostgroup' => [
					'groupid' => '50005',
					'name' => 'API host group update as zabbix admin without permissions'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'hostgroup.delete',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'hostgroup' => [
					'50008'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'hostgroup.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'valuemap' => [
					'name' => 'API host group create as zabbix user'
				],
				'expected_error' => 'No permissions to call "hostgroup.create".'
			],
			[
				'method' => 'hostgroup.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'hostgroup' => [
					'groupid' => '50005',
					'name' => 'API host group update as zabbix user without permissions'
				],
				'expected_error' => 'No permissions to call "hostgroup.update".'
			],
			[
				'method' => 'hostgroup.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'hostgroup' => [
					'50008'
				],
				'expected_error' => 'No permissions to call "hostgroup.delete".'
			]
		];
	}

	/**
	* @dataProvider hostgroup_user_permission
	*/
	public function testHostGroup_UserPermissions($method, $user, $hostgroups, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $hostgroups, $expected_error);
	}

	public static function hostgroup_get() {
		return [
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'with_monitored_hosts' => true
				],
				'expected_result' => [
					['groupid' => '50005']
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'with_hosts' => true
				],
				'expected_result' => [
					['groupid' => '50005']
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'templated_hosts' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "templated_hosts".'
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'with_hosts_and_templates' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "with_hosts_and_templates".'
			]
		];
	}

	/**
	 * @dataProvider hostgroup_get
	 */
	public function testHostGroup_Get($params, $expected_result, $expected_error) {
		$result = $this->call('hostgroup.get', $params, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertCount(count($expected_result), $result['result']);

		foreach ($result['result'] as $hostgroup) {
			$expected_hostgroup = array_shift($expected_result);

			foreach ($expected_hostgroup as $field => $expected_value){
				$this->assertArrayHasKey($field, $hostgroup, 'Field '.$field.' should be present.');
				$this->assertEquals($hostgroup[$field], $expected_value, 'Returned value should match.');
			}
		}
	}

	public static function hostgroup_Propagate() {
		return [
			// Check groupid
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'permissions' => true
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'hostgroup' => [
					'permissions' => true
				],
				'expected_error' => 'Invalid parameter "/": the parameter "groups" is missing.'
			],
			[
				'hostgroup' => [
					'groups' => [],
					'permissions' => true
				],
				'expected_error' => 'Invalid parameter "/groups": cannot be empty.'
			],
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => ''
					],
					'permissions' => true
				],
				'expected_error' => 'Invalid parameter "/groups/1/groupid": a number is expected.'
			],
			// Check permissions parameter
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'permissions' => false
				],
				'expected_error' => 'At least one parameter "permissions" or "tag_filters" must be enabled.'
			],
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => '12345'
					]
				],
				'expected_error' => 'At least one parameter "permissions" or "tag_filters" must be enabled.'
			],
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'permissions' => ''
				],
				'expected_error' => 'Invalid parameter "/permissions": a boolean is expected.'
			],
			// Check tag_filters parameter
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'tag_filters' => false
				],
				'expected_error' => 'At least one parameter "permissions" or "tag_filters" must be enabled.'
			],
			[
				'hostgroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'tag_filters' => ''
				],
				'expected_error' => 'Invalid parameter "/tag_filters": a boolean is expected.'
			],
			// Check successful propagation
			[
				'hostgroup' => [
					'groups' => [
						['groupid' => 'groupid_1']
					],
					'permissions' => true
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					'groups' => [
						['groupid' => 'groupid_1']
					],
					'tag_filters' => true
				],
				'expected_error' => null
			],
			[
				'hostgroup' => [
					'groups' => [
						['groupid' => 'groupid_3'],
						['groupid' => 'groupid_5']
					],
					'permissions' => true,
					'tag_filters' => true
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider hostgroup_propagate
	 */
	public function testHostGroup_propagate($hostgroups, $expected_error) {
		if ($expected_error === null) {
			foreach($hostgroups['groups'] as &$hostgroup) {
				$hostgroup['groupid'] = self::$data['groupids'][$hostgroup['groupid']];
			}
			unset($hostgroup);
		}

		$result = $this->call('hostgroup.propagate', $hostgroups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $groupid) {
				$db_host_groups_row = CDBHelper::getRow('SELECT groupid,name FROM hstgrp WHERE groupid='.$groupid);
				$group_name = $db_host_groups_row['name'].'/%';
				$db_subgroups_row = CDBHelper::getAll(
					'SELECT groupid FROM hstgrp WHERE name LIKE '.zbx_dbstr($group_name)
				);
				$groupids = [];
				$groupids[] = $db_host_groups_row['groupid'];
				$groupids = array_merge($groupids, array_column($db_subgroups_row, 'groupid'));
			}

			if (array_key_exists('permissions', $hostgroups)) {
				$db_rights_row = CDBHelper::getAll('SELECT id FROM rights WHERE groupid='.self::$data['usrgrpid']);
				$rights_groupids = array_flip(array_column($db_rights_row, 'id'));
				foreach ($groupids as $groupid) {
					$this->assertArrayHasKey($groupid, $rights_groupids);
				}
			}

			if (array_key_exists('tag_filters', $hostgroups)) {
				$db_tag_filters_row = CDBHelper::getAll(
					'SELECT * FROM tag_filter WHERE usrgrpid='.self::$data['usrgrpid']
				);
				$tag_filters_groupids = array_flip(array_column($db_tag_filters_row, 'groupid'));
				foreach ($groupids as $groupid) {
					$this->assertArrayHasKey($groupid, $tag_filters_groupids);
				}
			}
		}
	}

	/**
	 * Test data used by tests.
	 */
	protected static $data = [
		'groupids' => ['groupid_1', 'groupid_2', 'groupid_3', 'groupid_4', 'groupid_5', 'groupid_6'],
		'usrgrpid' => null
	];

	/**
	 * Prepare data for tests. Set permissions to host groups.
	 */
	public function prepareTestData() {
		$response = CDataHelper::call('hostgroup.create', [
			['name' => 'Propagate group 1'],
			['name' => 'Propagate group 1/Group 1'],
			['name' => 'Propagate group 2'],
			['name' => 'Propagate group 2/Group 1'],
			['name' => 'Propagate group 3'],
			['name' => 'Propagate group 3/Group 1']
		]);
		$this->assertArrayHasKey('groupids', $response);
		self::$data['groupids'] = array_combine(self::$data['groupids'], $response['groupids']);

		$response = CDataHelper::call('usergroup.create', [
			['name' => 'API host group propagate test']
		]);
		$this->assertArrayHasKey('usrgrpids', $response);
		self::$data['usrgrpid'] = $response['usrgrpids'][0];

		$response = CDataHelper::call('usergroup.update', [
			[
				'usrgrpid' => self::$data['usrgrpid'],
				'hostgroup_rights' => [
					[
						'id' => self::$data['groupids']['groupid_1'],
						'permission' => 3
					],
					[
						'id' => self::$data['groupids']['groupid_3'],
						'permission' => 3
					],
					[
						'id' => self::$data['groupids']['groupid_5'],
						'permission' => 3
					]
				],
				'tag_filters' => [
					[
						'groupid' => self::$data['groupids']['groupid_1'],
						'tag' => 'Tag',
						'value' => 'Value'
					],
					[
						'groupid' => self::$data['groupids']['groupid_3'],
						'tag' => 'Tag',
						'value' => 'Value'
					],
					[
						'groupid' => self::$data['groupids']['groupid_5'],
						'tag' => 'Tag',
						'value' => 'Value'
					]
				]
			]
		]);
		$this->assertArrayHasKey('usrgrpids', $response);
	}
}
