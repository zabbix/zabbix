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
			foreach ($result['result']['groupids'] as $key => $id) {
				$dbResult = DBSelect('select * from hstgrp where groupid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $hostgroup[$key]['name']);
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
			foreach ($result['result']['groupids'] as $key => $id) {
				$dbResult = DBSelect('select * from hstgrp where groupid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['name'], $hostgroups[$key]['name']);
				$this->assertEquals($dbRow['flags'], 0);
			}
		}
		else {
			foreach ($hostgroups as $hostgroup) {
				if (array_key_exists('name', $hostgroup) && $hostgroup['name'] !== 'Zabbix servers'){
					$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM hstgrp WHERE name='.zbx_dbstr($hostgroup['name'])));
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
				$this->assertEquals(0, CDBHelper::getCount('select * from hstgrp where groupid='.zbx_dbstr($id)));
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
					'name' => 'API host group update as zabbix admin without peremissions'
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
					'name' => 'API host group update as zabbix user without peremissions'
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
					'monitored_hosts' => true
				],
				'expected_result' => [
					'groupid' => '50005'
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'monitored_hosts' => true,
					'with_monitored_hosts' => true
				],
				'expected_result' => false,
				'expected_error' => 'Parameter "monitored_hosts" is deprecated.'
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'real_hosts' => true
				],
				'expected_result' => [
					'groupid' => '50005'
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'real_hosts' => true,
					'with_hosts' => true
				],
				'expected_result' => false,
				'expected_error' => 'Parameter "real_hosts" is deprecated.'
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'templated_hosts' => true
				],
				'expected_result' => false,
				'expected_error' => 'Invalid parameter "/": unexpected parameter "templated_hosts".'
			],
			[
				'params' => [
					'output' => ['groupid'],
					'groupids' => ['50005', '50006'],
					'with_hosts_and_templates' => true
				],
				'expected_result' => false,
				'expected_error' => 'Invalid parameter "/": unexpected parameter "with_hosts_and_templates".'
			]
		];
	}

	/**
	 * @dataProvider hostgroup_get
	 */
	public function testHostGroup_Get($params, $expected_result, $expected_error) {
		$result = $this->call('hostgroup.get', $params, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result'] as $hostgroup) {
				foreach ($expected_result as $field => $expected_value){
					$this->assertArrayHasKey($field, $hostgroup, 'Field should be present.');
					$this->assertEquals($hostgroup[$field], $expected_value, 'Returned value should match.');
				}
			}
		}
	}
}
