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
 * @backup tplgrp
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
				$dbResult = DBSelect('select * from tplgrp where groupid='.zbx_dbstr($id));
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
						'name' => 'API template group 2'
					]
				],
				'expected_error' => 'Template group "API template group 2" already exists.'
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
				$dbResult = DBSelect('select * from tplgrp where groupid='.zbx_dbstr($id));
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
				$this->assertEquals(0, CDBHelper::getCount('select * from tplgrp where groupid='.zbx_dbstr($id)));
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
}

