<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @backup hstgrp
 * @backup hosts_groups
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
			foreach ($result['result']['groupids'] as $key => $id) {
				$dbResult = DBSelect('select * from hstgrp where groupid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $templategroup[$key]['name']);
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
			foreach ($result['result']['groupids'] as $key => $id) {
				$dbResult = DBSelect('select * from hstgrp where groupid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $templategroups[$key]['name']);
			}
		}
		else {
			foreach ($templategroups as $templategroup) {
				if (array_key_exists('name', $templategroup) && $templategroup['name'] !== 'Templates'){
					$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM hstgrp WHERE name='.zbx_dbstr($templategroup['name'])));
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
			foreach ($result['result']['groupids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('select * from hstgrp where groupid='.zbx_dbstr($id)));
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
					'name' => 'API template group update as zabbix admin without peremissions'
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
					'name' => 'API template group update as zabbix user without peremissions'
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
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
			foreach ($result['result']['groupids'] as $key => $id) {
				foreach($templategroup['templates'] as $templateid) {
					$dbResult = DBSelect(
						'select * from hosts_groups where groupid=' . zbx_dbstr($id)
						.'and hostid=' .zbx_dbstr($templateid['templateid'])
					);
					$dbRow = DBFetch($dbResult);
					$this->assertEquals($dbRow['groupid'], $templategroup['groups'][$key]['groupid']);
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
			// Check missig parameters.
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
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
			]
		];
	}

	/**
	 * @dataProvider templategroup_massUpdate
	 */
	public function testTemplateGroup_massUpdate($templategroup, $expected_error) {
		$result = $this->call('templategroup.massUpdate', $templategroup, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['groupids'] as $id) {
				if (array_key_exists('templateid', $templategroup['templates'])) {
					$dbResult = DBSelect(
						'select * from hosts_groups where groupid=' . zbx_dbstr($id)
						.'and hostid=' .zbx_dbstr($templategroup['templates']['templateid'])
					);
					$dbRow = DBFetch($dbResult);
					$this->assertEquals($dbRow['groupid'], $templategroup['groups']['groupid']);
				}
				else {
					$dbResult = DBSelect('select * from hosts_groups where groupid=' . zbx_dbstr($id));
					$dbRow = DBFetch($dbResult);
					$this->assertEquals($dbRow['groupid'], false);
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
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'templategroup' => [
					'groupids' => ['52006'],
					'templateids' => ['12345']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'templategroup' => [
					'groupids' => ['52006', '12345'],
					'templateids' => ['50020']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
				'expected_error' => 'Template "API Template" cannot be without template group.'
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
			foreach ($result['result']['groupids'] as $key => $id) {
				foreach($templategroup['templateids'] as $templateid) {
					$dbResult = DBSelect(
						'select * from hosts_groups where groupid=' . zbx_dbstr($id)
						.'and hostid=' .zbx_dbstr($templateid)
					);
					$dbRow = DBFetch($dbResult);
					$this->assertEquals($dbRow['groupid'], false);
				}

			}
		}
	}
}

