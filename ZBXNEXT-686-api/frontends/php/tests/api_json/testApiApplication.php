<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class API_JSON_Application extends CZabbixTest {

	public function testApplication_backup() {
		DBsave_tables('applications');
	}

	public static function application_create_data() {
		return [
			[
				'application' => [
					'name' => 'application without hostid',
					'hostid' => ''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			[
				'application' => [
					'name' => '',
					'hostid' => '50009'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'application' => [
					'name' => 'application with not existing hostid',
					'hostid' => '123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'name' => 'non existent parametr',
					'hostid' => '50009',
					'flags' => '4'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			[
				'application' => [
					'name' => 'hostid not number',
					'hostid' => 'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			[
				'application' => [
					'name' => 'hostid not number',
					'hostid' => '.'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/hostid": a number is expected.'
			],
			[
				'application' => [
					'name' => 'API application',
					'hostid' => '50009'
				],
				'success_expected' => false,
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
				'success_expected' => false,
				'expected_error' => 'Application "API application" already exists.'
			],
			[
				'application' => [
					'name' => 'API templated application',
					'hostid' => '50009'
				],
				'success_expected' => false,
				'expected_error' => 'Application "API templated application" already exists.'
			],
			[
				'application' => [
					'name' => 'Api discovery application',
					'hostid' => '50009'
				],
				'success_expected' => false,
				'expected_error' => 'Application "Api discovery application" already exists.'
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
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (hostid, name)=(50009, Applications with two identical name) already exists.'
			],
			[
				'application' => [
					[
					'name' => 'Api application create one',
					'hostid' => '50009'
					],
					[
					'name' => 'Api application create two',
					'hostid' => '50009'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'application' => [
					[
					'name' => 'api host application create',
					'hostid' => '50009'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'application' => [
					[
					'name' => 'api template application create',
					'hostid' => '10093'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'application' => [
					[
					'name' => 'УТФ-8',
					'hostid' => '50009'
					]
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_create_data
	*/
	public function testApplication_create($application, $success_expected, $expected_error) {
		$result = $this->api_acall('application.create', $application, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['applicationids'] as $key => $id) {
				$dbResult = DBSelect('select * from applications where applicationid='.$id);
				$dbRow = DBFetch($dbResult);
				$this->assertEquals($dbRow['hostid'], $application[$key]['hostid']);
				$this->assertEquals($dbRow['name'], $application[$key]['name']);
				$this->assertEquals($dbRow['flags'], 0);
			}		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public static function application_template_data() {
		return [
			[
				'template_application' => [
					'name' => 'API create new templated application',
					'hostid' => '50010'
				],
				'different_application_name' => true,
				'host_application_id' => null
			],
			[
				'template_application' => [
					'name' => 'API application',
					'hostid' => '50010'
				],
				'different_application_name' => false,
				'host_application_id' => '366'
			]
		];
	}

	/**
	* @dataProvider application_template_data
	*/
	public function testApplication_template($template_application, $different_application_name, $host_application_id) {
		$result = $this->api_acall('application.create', [$template_application], $debug);

		$this->assertTrue(array_key_exists('result', $result));
		$this->assertFalse(array_key_exists('error', $result));

		$dbResult = DBSelect('select * from applications where applicationid='.$result['result']['applicationids'][0]);
		$dbRow = DBFetch($dbResult);
		$this->assertEquals($dbRow['hostid'], $template_application['hostid']);
		$this->assertEquals($dbRow['name'], $template_application['name']);
		$this->assertEquals($dbRow['flags'], 0);

		$dbResultTemplate = DBSelect('select * from application_template where templateid='.$result['result']['applicationids'][0]);
		$dbRowTemplate = DBFetch($dbResultTemplate);

		if ($different_application_name) {
			$dbResultHost = DBSelect('select * from applications where applicationid='.($result['result']['applicationids'][0]+1));
			$dbRowHost = DBFetch($dbResultHost);
			$this->assertEquals($dbRowHost['name'], $template_application['name']);
			$this->assertEquals($dbRowHost['flags'], 0);

			$this->assertEquals($dbRowTemplate['applicationid'], $result['result']['applicationids'][0]+1);
		}
		else {
			$dbResultHost = DBSelect('select * from applications where applicationid='.$host_application_id);
			$dbRowHost = DBFetch($dbResultHost);
			$this->assertEquals($dbRowHost['name'], $template_application['name']);
			$this->assertEquals($dbRowHost['flags'], 0);

			$this->assertEquals($dbRowTemplate['applicationid'], $host_application_id);
		}
	}

	public static function application_update_data() {
		return [
			[
				'application' => [
					[
					'applicationid' => ''
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/applicationid": a number is expected.'
			],
			[
				'application' => [
					[
					'applicationid' => '367',
					'name' => ''
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			[
				'application' => [
					[
					'applicationid' => '123456',
					'name' => 'application with not existing id'
					]
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					[
					'applicationid' => '367',
					'name' => 'non existent parametr',
					'flags' => '4'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "flags".'
			],
			[
				'application' => [
					[
					'applicationid' => 'abc',
					'name' => 'id not number'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/applicationid": a number is expected.'
			],
			[
				'application' => [
					[
					'applicationid' => '.',
					'name' => 'id not number'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1/applicationid": a number is expected.'
			],
			[
				'application' => [
					[
					'applicationid' => '370',
					'name' => 'Update templated application'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Cannot update templated applications.'
			],
			[
				'application' => [
					[
					'applicationid' => '375',
					'name' => 'Api discovery application update'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Cannot update discovered application "Api discovery application".'
			],
			[
				'application' => [
					[
					'applicationid' => '367',
					'name' => 'API application'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Application "API application" already exists.'
			],
			[
				'application' => [
					[
					'applicationid' => '367',
					'name' => 'update two application'
					],
					[
					'applicationid' => '371',
					'name' => 'update two application'
					]
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (hostid, name)=(50009, update two application) already exists.'
			],
			[
				'application' => [
					'applicationid' => '367',
					'name' => 'application updated'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'application' => [
					'applicationid' => '368',
					'name' => 'api template application updated'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'application' => [
					'applicationid' => '367',
					'name' => 'УТФ-8 обновлённый'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_update_data
	*/
	public function testApplication_update($application, $success_expected, $expected_error) {
		$result = $this->api_acall('application.update', $application, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			$dbResult = DBSelect('select * from applications where applicationid='.$result['result']['applicationids'][0]);
			$dbRow = DBFetch($dbResult);
			$this->assertEquals($dbRow['applicationid'], $application['applicationid']);
			$this->assertEquals($dbRow['name'], $application['name']);
			$this->assertEquals($dbRow['flags'], 0);
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
			foreach ($application as $applications) {
				if (isset($applications['name'])){
					$dbResult = "select * from applications where applicationid=".$applications['applicationid'].
							" and name='".$applications['name']."'";
					$this->assertEquals(0, DBcount($dbResult));
				}
			}
		}
	}

	public static function application_delete_data() {
		return [
			[
				'application' => [
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'application' => [
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'371',
					'flags' => '4'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'application' => [
					'.'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'application' => [
					'371',
					'123456'
				],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'application' => [
					'371',
					'abc'
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'application' => [
					'371',
					''
				],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			[
				'application' => [
					'370'
				],
				'success_expected' => false,
				'expected_error' => 'Cannot delete templated application.'
			],
			[
				'application' => [
					'375'
				],
				'success_expected' => false,
				'expected_error' => 'Cannot delete discovered application "Api discovery application".'
			],
			[
				'application' => [
					'372'
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'application' => [
					'373',
					'374'
				],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_delete_data
	*/
	public function testApplication_delete($application, $success_expected, $expected_error) {
		$result = $this->api_acall('application.delete', $application, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['applicationids'] as $id) {
				$dbResult = 'select * from applications where applicationid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
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
				'success_expected' => false,
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
				'success_expected' => true,
				'expected_error' => null
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
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider application_get_data
	*/
	public function testApplication_get($application, $get_result, $success_expected) {
		$result = $this->api_acall('application.get', $application, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result'] as $name) {
				$this->assertEquals($name['applicationid'], $get_result['applicationid']);
				$this->assertEquals($name['hostid'], $get_result['hostid']);
				$this->assertEquals($name['name'], $get_result['name']);
				$this->assertEquals($name['flags'], $get_result['flags']);
				$this->assertEquals($name['templateids'], $get_result['templateids']);
			}
		}
		else {
			$this->assertTrue(array_key_exists('result', $result));

			$this->assertEquals($result['result'], $get_result);
		}
	}

	public function testApplication_restore() {
		DBrestore_tables('applications');
	}
}
