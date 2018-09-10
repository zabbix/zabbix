<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

/**
 * @backup applications
 */
class testApplication extends CZabbixTest {
	public static function application_create() {
		return [
			[
				'application' => [
					'name' => 'non existent parametr',
					'hostid' => '50009',
					'flags' => '4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			// Check application hostid.
			[
				'application' => [
					'name' => 'application without hostid',
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "hostid" is missing.'
			],
			[
				'application' => [
					'name' => 'application with empty hostid',
					'hostid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			[
				'application' => [
					'name' => 'application with not existing hostid',
					'hostid' => '123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'name' => 'hostid not number',
					'hostid' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			[
				'application' => [
					'name' => 'hostid not number',
					'hostid' => '.'
				],
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			// Check application name.
			[
				'application' => [
					'hostid' => '50009'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			[
				'application' => [
					'name' => '',
					'hostid' => '50009'
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'application' => [
					'name' => 'Suspendisse sagittis euismod consequat. Vivamus pretium, lectus vitae lacinia sodales, metus nisi viverra lectus, vel fermentum dui eros et est. Mauris vitae velit ac massa imperdiet molestie ut sed diam? Quisque vehicula nulla at mauris aliquam, nec condd',
					'hostid' => '50009'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check name duplicates.
			[
				'application' => [
					'name' => 'API application',
					'hostid' => '50009'
				],
				'expected_error' => 'Application "API application" already exists.'
			],
			[
				'application' => [
					[
						'name' => 'One application with existing name',
						'hostid' => '50009'
					],
					[
						'name' => 'API application',
						'hostid' => '50009'
					]
				],
				'expected_error' => 'Application "API application" already exists.'
			],
			[
				'application' => [
					'name' => 'API templated application',
					'hostid' => '50009'
				],
				'expected_error' => 'Application "API templated application" already exists.'
			],
			[
				'application' => [
					'name' => 'API discovery application',
					'hostid' => '50009'
				],
				'expected_error' => 'Application "API discovery application" already exists.'
			],
			[
				'application' => [
					[
						'name' => 'Applications with two identical name',
						'hostid' => '50009'
					],
					[
						'name' => 'Applications with two identical name',
						'hostid' => '50009'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (hostid, name)=(50009, Applications with two identical name) already exists.'
			],
			// Check successfully creation of application.
			[
				'application' => [
					[
						'name' => 'API host application create',
						'hostid' => '50009'
					]
				],
				'expected_error' => null
			],
			[
				'application' => [
					[
						'name' => 'æų',
						'hostid' => '50009'
					],
					[
						'name' => 'УТФ-8/создать',
						'hostid' => '50009'
					]
				],
				'expected_error' => null
			],
			[
				'application' => [
					[
						'name' => 'API template application create',
						'hostid' => '10093'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_create
	*/
	public function testApplication_Create($application, $expected_error) {
		$result = $this->call('application.create', $application, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['applicationids'] as $key => $id) {
				$dbResult = DBSelect('select * from applications where applicationid='.zbx_dbstr($id));
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['hostid'], $application[$key]['hostid']);
				$this->assertEquals($dbRow['name'], $application[$key]['name']);
				$this->assertEquals($dbRow['flags'], 0);
			}
		}
	}

	public static function application_template() {
		return [
			// Check new template application successfully templated to the host.
			[
				'template_application' => [
					'name' => 'API create new templated application',
					'hostid' => '50010'
				],
				'new_template_application' => true,
				'host_application_id' => null,
				'method' => 'application.create'
			],
			// Check that, existing application of host changed to templated.
			[
				'template_application' => [
					'name' => 'API application',
					'hostid' => '50010'
				],
				'new_template_application' => false,
				'host_application_id' => '366',
				'method' => 'application.create'
			],
			// Check that, host templated application name successfully changed after template application name update.
			[
				'template_application' => [
					'applicationid' => '369',
					'name' => 'API templated application update',
				],
				'new_template_application' => false,
				'host_application_id' => '370',
				'method' => 'application.update'
			],
		];
	}

	/**
	* @dataProvider application_template
	*/
	public function testApplication_CreateTemplated($template_application, $new_template_application, $host_application_id, $method) {
		$result = $this->call($method, [$template_application]);

		$dbResult = DBSelect('select * from applications where applicationid='.zbx_dbstr($result['result']['applicationids'][0]));
		$dbRow = DBFetch($dbResult);
		if (array_key_exists('hostid', $template_application)) {
			$this->assertEquals($dbRow['hostid'], $template_application['hostid']);
		}
		$this->assertEquals($dbRow['name'], $template_application['name']);
		$this->assertEquals($dbRow['flags'], 0);

		$dbResultTemplate = DBSelect('select * from application_template where templateid='.zbx_dbstr($result['result']['applicationids'][0]));
		$dbRowTemplate = DBFetch($dbResultTemplate);

		if ($new_template_application) {
			$this->assertEquals($dbRowTemplate['applicationid'], $result['result']['applicationids'][0]+1);

			$dbResultHost = DBSelect('select * from applications where applicationid='.zbx_dbstr($result['result']['applicationids'][0]+1));
			$dbRowHost = DBFetch($dbResultHost);
			$this->assertEquals($dbRowHost['name'], $template_application['name']);
			$this->assertEquals($dbRowHost['flags'], 0);
		}
		else {
			$this->assertEquals($dbRowTemplate['applicationid'], $host_application_id);

			$dbResultHost = DBSelect('select * from applications where applicationid='.zbx_dbstr($host_application_id));
			$dbRowHost = DBFetch($dbResultHost);
			$this->assertEquals($dbRowHost['name'], $template_application['name']);
			$this->assertEquals($dbRowHost['flags'], 0);
		}
	}

	public static function application_update() {
		return [
			[
				'application' => [
					[
						'applicationid' => '367',
						'name' => 'non existent parametr',
						'flags' => '4'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			// Check application id.
			[
				'application' => [
					[
						'name' => 'without application id'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "applicationid" is missing.'
			],
			[
				'application' => [
					[
						'applicationid' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/applicationid": a number is expected.'
			],
			[
				'application' => [
					[
						'applicationid' => '123456',
						'name' => 'application with not existing id'
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					[
						'applicationid' => 'abc',
						'name' => 'id not number'
					]
				],
				'expected_error' => 'Invalid parameter "/1/applicationid": a number is expected.'
			],
			[
				'application' => [
					[
						'applicationid' => '0.0',
						'name' => 'invalid application id'
					]
				],
				'expected_error' => 'Invalid parameter "/1/applicationid": a number is expected.'
			],
			[
				'application' => [
					[
						'applicationid' => '367',
						'name' => 'update the same application id1'
					],
					[
						'applicationid' => '367',
						'name' => 'update the same application id2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (applicationid)=(367) already exists.'
			],
			// Check templated and discovered applications.
			[
				'application' => [
					[
						'applicationid' => '370',
						'name' => 'Update templated application'
					]
				],
				'expected_error' => 'Cannot update templated applications.'
			],
			[
				'application' => [
					[
						'applicationid' => '375',
						'name' => 'API discovery application update'
					]
				],
				'expected_error' => 'Cannot update discovered application "API discovery application".'
			],
			// Check application name.
			[
				'application' => [
					[
						'applicationid' => '367',
						'name' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'application' => [
					[
						'applicationid' => '367',
						'name' => 'Suspendisse sagittis euismod consequat. Vivamus pretium, lectus vitae lacinia sodales, metus nisi viverra lectus, vel fermentum dui eros et est. Mauris vitae velit ac massa imperdiet molestie ut sed diam? Quisque vehicula nulla at mauris aliquam, nec condd'
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			// Check name duplicates.
			[
				'application' => [
					[
						'applicationid' => '367',
						'name' => 'API application'
					]
				],
				'expected_error' => 'Application "API application" already exists.'
			],
			[
				'application' => [
					[
						'applicationid' => '367',
						'name' => 'update two the same application name'
					],
					[
						'applicationid' => '371',
						'name' => 'update two the same application name'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (hostid, name)=(50009, update two the same application name) already exists.'
			],
			// Check successfully update.
			[
				'application' => [
					'applicationid' => '367',
					'name' => '☺'
				],
				'expected_error' => null
			],
			[
				'application' => [
					'applicationid' => '368',
					'name' => 'API template application updated'
				],
				'expected_error' => null
			],
			[
				'application' => [
					'applicationid' => '367',
					'name' => 'УТФ-8 обновлённый'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_update
	*/
	public function testApplication_Update($applications, $expected_error) {
		$result = $this->call('application.update', $applications, $expected_error);

		if ($expected_error === null) {
			$dbResult = DBSelect('select * from applications where applicationid='.zbx_dbstr($result['result']['applicationids'][0]));
			$dbRow = DBFetch($dbResult);
			$this->assertEquals($dbRow['applicationid'], $applications['applicationid']);
			$this->assertEquals($dbRow['name'], $applications['name']);
			$this->assertEquals($dbRow['flags'], 0);
		}
		else {
			foreach ($applications as $application) {
				if (array_key_exists('name', $application) && $application['name'] != 'API application'){
					$dbResult = 'select * from applications where name='.zbx_dbstr($application['name']);
					$this->assertEquals(0, DBcount($dbResult));
				}
			}
		}
	}

	public static function application_delete() {
		return [
			[
				'application' => [
					''
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'application' => [
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'abc'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'application' => [
					'0.0'
				],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'application' => [
					'371',
					'123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'371',
					'.'
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'application' => [
					'371',
					''
				],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'application' => [
					'371',
					'371'
				],
				'expected_error' => 'Invalid parameter "/2": value (371) already exists.'
			],
			[
				'application' => [
					'370'
				],
				'expected_error' => 'Cannot delete templated application.'
			],
			[
				'application' => [
					'375'
				],
				'expected_error' => 'Cannot delete discovered application "API discovery application".'
			],
			[
				'application' => [
					'372'
				],
				'expected_error' => null
			],
			[
				'application' => [
					'373',
					'374'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_delete
	*/
	public function testApplication_delete($application, $expected_error) {
		$result = $this->call('application.delete', $application, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['applicationids'] as $id) {
				$dbResult = 'select * from applications where applicationid='.zbx_dbstr($id);
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
	}

	public static function application_get_data() {
		return [
			[
				'application' => [
					'applicationids' => '123456'
				],
				'get_result' =>[
				],
				'expected_error' => true,
			],
			[
				'application' => [
					'applicationids' => '374'
				],
				'get_result' => [
					'applicationid' => '374',
					'hostid' => '50009',
					'name' => 'API application for items',
					'flags' => '0',
					'templateids'=> []
				],
				'expected_error' => false
			],
			[
				'application' => [
					'applicationids' => '207'
				],
				'get_result' => [
					'applicationid' => '207',
					'hostid' => '10001',
					'name' => 'Zabbix agent',
					'flags' => '0',
					'templateids'=> ['206']
				],
				'expected_error' => false
			]
		];
	}

	/**
	* @dataProvider application_get_data
	*/
	public function testApplication_get($application, $get_result, $expected_error) {
		$result = $this->call('application.get', $application);

		if ($expected_error === false) {
			foreach ($result['result'] as $name) {
				$this->assertEquals($name['applicationid'], $get_result['applicationid']);
				$this->assertEquals($name['hostid'], $get_result['hostid']);
				$this->assertEquals($name['name'], $get_result['name']);
				$this->assertEquals($name['flags'], $get_result['flags']);
				$this->assertEquals($name['templateids'], $get_result['templateids']);
			}
		}
		else {
			$this->assertEquals($result['result'], $get_result);
		}
	}

	public static function application_user_permissions() {
		return [
			[
				'method' => 'application.create',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'application' => [
						'name' => 'API host application create as zabbix admin',
						'hostid' => '10084'
					],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'application.update',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'application' => [
					'applicationid' => '376',
					'name' => 'API application update as zabbix admin',
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'application.delete',
				'user' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'application' => [
					'376'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'application.create',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'application' => [
						'name' => 'API host application create as zabbix user',
						'hostid' => '10084'
					],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'application.update',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'application' => [
					'applicationid' => '376',
					'name' => 'API application update as zabbix user',
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'application.delete',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'application' => [
					'376'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	* @dataProvider application_user_permissions
	*/
	public function testApplication_UserPermissions($method, $user, $application, $expected_error) {
		$this->authorize($user['user'], $user['password']);
		$this->call($method, $application, $expected_error);
	}
}
