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
class testTemplateGroup extends CAPITest {
	public static function templategroup_create() {
		return [
			[
				'templategroup' => [
					'name' => 'non existent parameter',
					'flags' => '4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			// Check templategroup name.
			[
				'templategroup' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'templategroup' => [
					'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check for duplicated template groups names.
			[
				'templategroup' => [
					'name' => 'API template group 1'
				],
				'expected_error' => 'Template group "API template group 1" already exists.'
			],
			[
				'templategroup' => [
					[
						'name' => 'One template group with existing name'
					],
					[
						'name' => 'API template group 1'
					]
				],
				'expected_error' => 'Template group "API template group 1" already exists.'
			],
			[
				'templategroup' => [
					[
						'name' => 'Template groups with two identical names'
					],
					[
						'name' => 'Template groups with two identical names'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(Template groups with two identical names) already exists.'
			],
			// Check successfully create.
			[
				'templategroup' => [
					[
						'name' => 'API template group create'
					]
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
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
				'templategroup' => [
					[
						'name' => 'АПИ шаблон группа УТФ-8'
					]
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
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
	 * @dataProvider templategroup_create
	 */
	public function testTemplateGroup_Create($templategroup, $expected_error) {
		$result = $this->call('templategroup.create', $templategroup, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $index => $groupid) {
				$dbRow = CDBHelper::getRow('SELECT * FROM hstgrp WHERE groupid='.$groupid);
				$this->assertEquals($dbRow['name'], $templategroup[$index]['name']);
			}
		}
	}

	public static function templategroup_update() {
		return [
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => 'non existent parameter',
						'flags' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			// Check groupid.
			[
				'templategroup' => [
					[
						'name' => 'without groupid'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "groupid" is missing.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '',
						'name' => 'empty groupid'
					]
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '123456',
						'name' => 'groupid with not existing id'
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'templategroup' => [
					[
						'groupid' => 'abc',
						'name' => 'id not number'
					]
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '0.0',
						'name' => 'æųæų'
					]
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			// Check name.
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => 'Phasellus imperdiet sapien sed justo elementum, quis maximus ipsum iaculis! Proin egestas, felis non efficitur molestie, nulla risus facilisis nisi, sed consectetur lorem mauris non arcu. Aliquam hendrerit massa vel metus maximus consequat. Sed condimen256'
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => 'Templates'
					]
				],
				'expected_error' => 'Template group "Templates" already exists.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => 'API update two template groups with the same names'
					],
					[
						'groupid' => '52002',
						'name' => 'API update two template groups with the same names'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(API update two template groups with the same names) already exists.'
			],
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => 'update template group twice1'
					],
					[
						'groupid' => '52001',
						'name' => 'update template group twice2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (groupid)=(52001) already exists.'
			],
			// Check successfully update.
			[
				'templategroup' => [
					[
						'groupid' => '52001',
						'name' => 'API template group updated'
					]
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					[
						'groupid' => '52002',
						'name' => 'API two template groups updated'
					],
					[
						'groupid' => '52003',
						'name' => 'Апи УТФ-8 обновлённый'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider templategroup_update
	 */
	public function testTemplateGroup_Update($templategroups, $expected_error) {
		$result = $this->call('templategroup.update', $templategroups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $index => $groupid) {
				$dbRow = CDBHelper::getRow('SELECT name FROM hstgrp WHERE groupid='.$groupid);
				$this->assertEquals($dbRow['name'], $templategroups[$index]['name']);
			}
		}
		else {
			foreach ($templategroups as $templategroup) {
				if (array_key_exists('name', $templategroup) && $templategroup['name'] !== 'Templates') {
					$this->assertEquals(0,
						CDBHelper::getCount('SELECT * FROM hstgrp WHERE name='.zbx_dbstr($templategroup['name']))
					);
				}
			}
		}
	}

	public static function templategroup_delete() {
		return [
			[
				'templategroup' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'templategroup' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'templategroup' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'templategroup' => [
					'.'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'templategroup' => [
					'52004',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'templategroup' => [
					'52004',
					'abc'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'templategroup' => [
					'52004',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'templategroup' => [
					'52004',
					'52004'
				],
				'expected_error' => 'Invalid parameter "/2": value (52004) already exists.'
			],
			[
				'templategroup' => [
					'52005',
					'52006'
				],
				'expected_error' => 'Template "API Template 2" cannot be without template group.'
			],
			[
				'templategroup' => [
					'52004'
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'52007',
					'52008'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider templategroup_delete
	 */
	public function testTemplateGroup_Delete($templategroups, $expected_error) {
		$result = $this->call('templategroup.delete', $templategroups, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $groupid) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM hstgrp WHERE groupid='.zbx_dbstr($groupid)));
			}
		}
	}

	public static function templategroup_user_permission() {
		return [
			[
				'method' => 'templategroup.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'templategroup' => [
					'name' => 'API template group create as zabbix admin'
				],
				'expected_error' => 'No permissions to call "templategroup.create".'
			],
			[
				'method' => 'templategroup.update',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'templategroup' => [
					'groupid' => '52001',
					'name' => 'API template group update as zabbix admin without permissions'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'templategroup.delete',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'templategroup' => [
					'52001'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'templategroup.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'templategroup' => [
					'name' => 'API template group create as zabbix user'
				],
				'expected_error' => 'No permissions to call "templategroup.create".'
			],
			[
				'method' => 'templategroup.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'templategroup' => [
					'groupid' => '52001',
					'name' => 'API template group update as zabbix user without permissions'
				],
				'expected_error' => 'No permissions to call "templategroup.update".'
			],
			[
				'method' => 'templategroup.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'templategroup' => [
					'52002'
				],
				'expected_error' => 'No permissions to call "templategroup.delete".'
			],
			[
			'method' => 'templategroup.massAdd',
			'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
			'templategroup' => [
				'groups' => [
					['groupid' => '52002'],
					['groupid' => '52003']
				],
				'templates' => [
					['templateid' => '10358'],
					['templateid' => '10362']
				]
			],
			'expected_error' => 'No permissions to call "templategroup.massAdd".'
			],
			[
				'method' => 'templategroup.massUpdate',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'templategroup' => [
					'groups' => [
						['groupid' => '52002'],
						['groupid' => '52003']
					],
					'templates' => []
				],
				'expected_error' => 'No permissions to call "templategroup.massUpdate".'
			],
			[
				'method' => 'templategroup.massRemove',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'templategroup' => [
					'groupids' => ['52005'],
					'templates' => ['50020']
				],
				'expected_error' => 'No permissions to call "templategroup.massRemove".'
			]
		];
	}

	/**
	 * @dataProvider templategroup_user_permission
	 */
	public function testTemplateGroup_UserPermissions($method, $user, $templategroups, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $templategroups, $expected_error);
	}

	public static function templategroup_massAdd() {
		return [
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '52002'],
						['groupid' => '52003']
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "templates" is missing.'
			],
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '5200222']
					],
					'templates' => [
						['templateid' => '10358'],
						['templateid' => '10362']
					]
				],
				'expected_error' => 'Invalid parameter "/groups/1": object does not exist, or you have no permissions to it.'
			],
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '52002'],
						['groupid' => '52003']
					],
					'templates' => [
						['templateid' => '1035888']
					]
				],
				'expected_error' => 'Invalid parameter "/templates/1": object does not exist, or you have no permissions to it.'
			],
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '52002'],
						['groupid' => '52003']
					],
					'hosts' => [
						['hostid' => '1035888']
					]
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "hosts".'
			],
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '52002'],
						['groupid' => '52003']
					],
					'templates' => [
						['hostid' => '1035888']
					]
				],
				'expected_error' => 'Invalid parameter "/templates/1": unexpected parameter "hostid".'
			],
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '']
					],
					'templates' => [
						['templateid' => '10358'],
						['templateid' => '10362']
					]
				],
				'expected_error' => 'Invalid parameter "/groups/1/groupid": a number is expected.'
			],
			// Check successfully create.
			[
				'templategroup' => [
					'groups' => [
						['groupid' => '52002'],
						['groupid' => '52003']
					],
					'templates' => [
						['templateid' => '10358'],
						['templateid' => '10362']
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider templategroup_massAdd
	 */
	public function testTemplateGroup_massAdd($templategroup, $expected_error) {
		$result = $this->call('templategroup.massAdd', $templategroup, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $index => $groupid) {
				foreach($templategroup['templates'] as $template) {
					$dbRow = CDBHelper::getRow(
						'SELECT groupid'.
						' FROM hosts_groups'.
						' WHERE groupid='.$groupid.
							' AND hostid='.$template['templateid']
					);
					$this->assertEquals($dbRow['groupid'], $templategroup['groups'][$index]['groupid']);
				}

			}
		}
	}

	public static function templategroup_massUpdate() {
		return [
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '52005',
						'name' => 'non existent parameter'
					],
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => 'Invalid parameter "/groups/1": unexpected parameter "name".'
			],
			// Check missing parameters.
			[
				'templategroup' => [
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "groups" is missing.'
			],
			[
				'templategroup' => [
					'groups' => [],
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => 'Invalid parameter "/groups": cannot be empty.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '52005'
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "templates" is missing.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => ''
					],
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => 'Invalid parameter "/groups/1/groupid": a number is expected.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => 'Invalid parameter "/groups/1": object does not exist, or you have no permissions to it.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '52005'
					],
					'templates' => [
						'templateid' => '12345'
					]
				],
				'expected_error' => 'Invalid parameter "/templates/1": object does not exist, or you have no permissions to it.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '50013'
					],
					'templates' => [
						'templateid' => '50020'
					]
				],
				'expected_error' => 'Template "API Template" cannot be without template group.'
			],
			// Check successfully update.
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '52005'
					],
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '50013'
					],
					'templates' => []
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '50013'
					],
					'templates' => [
						'templateid' => '50010'
					]
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '52005'
					],
					'templates' => [
						'templateid' => '50020'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider templategroup_massUpdate
	 */
	public function testTemplateGroup_massUpdate($templategroup, $expected_error) {
		$result = $this->call('templategroup.massUpdate', $templategroup, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $groupid) {
				if (array_key_exists('templateid', $templategroup['templates'])) {
					$dbRow = CDBHelper::getRow(
						'SELECT groupid'.
						' FROM hosts_groups'.
						' WHERE groupid='.$groupid.
							' AND hostid='.$templategroup['templates']['templateid']
					);
					$this->assertEquals($dbRow['groupid'], $templategroup['groups']['groupid']);
				}
				else {
					$dbRow = CDBHelper::getRow('SELECT NULL FROM hosts_groups WHERE groupid='.$groupid);
					$this->assertSame($dbRow, false);
				}
			}
		}
	}

	public static function templategroup_massRemove() {
		return [
			[
				'templategroup' => [
					'groupids' => [],
					'templateids' => ['50020']
				],
				'expected_error' => 'Invalid parameter "/groupids": cannot be empty.'
			],
			[
				'templategroup' => [
					'groupids' => ['52006'],
					'templateids' => []
				],
				'expected_error' => 'Invalid parameter "/templateids": cannot be empty.'
			],
			[
				'templategroup' => [
					'groupids' => [''],
					'templateids' => ['50020']
				],
				'expected_error' => 'Invalid parameter "/groupids/1": a number is expected.'
			],
			[
				'templategroup' => [
					'groupids' => ['52006'],
					'templateids' => ['']
				],
				'expected_error' => 'Invalid parameter "/templateids/1": a number is expected.'
			],
			[
				'templategroup' => [
					'groupids' => ['12345'],
					'templateids' => ['50020']
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groupids' => ['52006'],
					'templateids' => ['12345']
				],
				'expected_error' => 'Invalid parameter "/templateids/1": object does not exist, or you have no permissions to it.'
			],
			[
				'templategroup' => [
					'groupids' => ['52006', '12345'],
					'templateids' => ['50020']
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groupids' => ['52006', ''],
					'templateids' => ['50020']
				],
				'expected_error' => 'Invalid parameter "/groupids/2": a number is expected.'
			],
			[
				'templategroup' => [
					'groupids' => ['52005'],
					'templateids' => ['50010']
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groupids' => ['52002'],
					'templateids' => ['10358']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider templategroup_massRemove
	 */
	public function testTemplateGroup_massRemove($templategroup, $expected_error) {
		$result = $this->call('templategroup.massRemove', $templategroup, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $groupid) {
				foreach($templategroup['templateids'] as $templateid) {
					$dbRow = CDBHelper::getRow(
						'SELECT NULL'.
						' FROM hosts_groups'.
						' WHERE groupid='.$groupid.
							' AND hostid='.$templateid
					);
					$this->assertSame($dbRow, false);
				}
			}
		}
	}

	public static function templategroup_Propagate() {
		return [
			// Check groupid
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'permissions' => true
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'templategroup' => [
					'permissions' => true
				],
				'expected_error' => 'Invalid parameter "/": the parameter "groups" is missing.'
			],
			[
				'templategroup' => [
					'groups' => [],
					'permissions' => true
				],
				'expected_error' => 'Invalid parameter "/groups": cannot be empty.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => ''
					],
					'permissions' => true
				],
				'expected_error' => 'Invalid parameter "/groups/1/groupid": a number is expected.'
			],
			// Check permissions parameter
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'permissions' => false
				],
				'expected_error' => 'Parameter "permissions" must be enabled.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '12345'
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "permissions" is missing.'
			],
			[
				'templategroup' => [
					'groups' => [
						'groupid' => '12345'
					],
					'permissions' => ''
				],
				'expected_error' => 'Invalid parameter "/permissions": a boolean is expected.'
			],
			// Check successful propagation
			[
				'templategroup' => [
					'groups' => [
						['groupid' => 'groupid_1']
					],
					'permissions' => true
				],
				'expected_error' => null
			],
			[
				'templategroup' => [
					'groups' => [
						['groupid' => 'groupid_3'],
						['groupid' => 'groupid_5']
					],
					'permissions' => true
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider templategroup_propagate
	 */
	public function testTemplateGroup_propagate($templategroups, $expected_error) {
		if ($expected_error === null) {
			foreach($templategroups['groups'] as &$templategroup) {
				$templategroup['groupid'] = self::$data['groupids'][$templategroup['groupid']];
			}
			unset($templategroup);
		}

		$result = $this->call('templategroup.propagate', $templategroups, $expected_error);

		if ($expected_error === null) {
			$db_rights_row = CDBHelper::getAll('SELECT * FROM rights WHERE groupid='.self::$data['usrgrpid']);
			$rights_groupids = array_column($db_rights_row, 'id');
			$rights_groupids = array_flip($rights_groupids);

			foreach ($result['result']['groupids'] as $groupid) {
				$db_template_groups_row = CDBHelper::getRow('SELECT * FROM hstgrp WHERE groupid='.$groupid);
				$group_name = $db_template_groups_row['name'].'/%';
				$db_subgroups_row = CDBHelper::getAll('SELECT * FROM hstgrp WHERE name LIKE '.zbx_dbstr($group_name));
				$groupids = [];
				$groupids[] = $db_template_groups_row['groupid'];
				$groupids = array_merge($groupids, array_column($db_subgroups_row, 'groupid'));
			}

			foreach ($groupids as $groupid) {
				$this->assertArrayHasKey($groupid, $rights_groupids);
			}
		}
	}

	/**
	 * Test data used by test.
	 */
	protected static $data = [
		'groupids' => ['groupid_1', 'groupid_2', 'groupid_3', 'groupid_4', 'groupid_5', 'groupid_6'],
		'usrgrpid' => null
	];

	/**
	 * Prepare data for tests. Set permissions to template groups.
	 */
	public function prepareTestData() {
		$response = CDataHelper::call('templategroup.create', [
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
			['name' => 'API template group propagate test']
		]);
		$this->assertArrayHasKey('usrgrpids', $response);
		self::$data['usrgrpid'] = $response['usrgrpids'][0];

		$response = CDataHelper::call('usergroup.update', [
			[
				'usrgrpid' => self::$data['usrgrpid'],
				'templategroup_rights' => [
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
				]
			]
		]);
		$this->assertArrayHasKey('usrgrpids', $response);
	}
}
