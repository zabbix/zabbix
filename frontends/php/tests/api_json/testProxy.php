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

class testProxy extends CZabbixTest {

	public static function proxy_delete() {
		return [
			// Check proxy id validation.
			[
				'proxy' => [''],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'proxy' => ['abc'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'proxy' => ['1.1'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'proxy' => ['123456'],
				'success_expected' => false,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'proxy' => ['99000', '99000'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": value (99000) already exists.'
			],
			[
				'proxy' => ['99000', 'abcd'],
				'success_expected' => false,
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			// Check if proxy used in actions.
			[
				'proxy' => ['99003'],
				'success_expected' => false,
				'expected_error' => 'Proxy "Api active proxy in action" is used by action "API action with proxy".'
			],
			[
				'proxy' => ['99000', '99003'],
				'success_expected' => false,
				'expected_error' => 'Proxy "Api active proxy in action" is used by action "API action with proxy".'
			],
			// Check if proxy used in host.
			[
				'proxy' => ['99004'],
				'success_expected' => false,
				'expected_error' => 'Host "API Host monitored with proxy" is monitored with proxy "Api active proxy with host".'
			],
			[
				'proxy' => ['99000', '99004'],
				'success_expected' => false,
				'expected_error' => 'Host "API Host monitored with proxy" is monitored with proxy "Api active proxy with host".'
			],
			// Successfully delete proxy.
			[
				'proxy' => ['99000'],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'proxy' => ['99001', '99002'],
				'success_expected' => true,
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider proxy_delete
	*/
	public function testProxy_Delete($proxy, $success_expected, $expected_error) {
		$result = $this->api_acall('proxy.delete', $proxy, $debug);

		if ($success_expected) {
			$this->assertTrue(array_key_exists('result', $result));
			$this->assertFalse(array_key_exists('error', $result));

			foreach ($result['result']['proxyids'] as $id) {
				$dbResult = 'select * from hosts where hostid='.$id;
				$this->assertEquals(0, DBcount($dbResult));
			}
		}
		else {
			$this->assertFalse(array_key_exists('result', $result));
			$this->assertTrue(array_key_exists('error', $result));

			$this->assertEquals($expected_error, $result['error']['data']);
		}
	}

}
