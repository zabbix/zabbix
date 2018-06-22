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
 * @backup hosts
 */
class testProxy extends CZabbixTest {

	public static function proxy_delete() {
		return [
			// Check proxy id validation.
			[
				'proxy' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'proxy' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'proxy' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'proxy' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'proxy' => ['99000', '99000'],
				'expected_error' => 'Invalid parameter "/2": value (99000) already exists.'
			],
			[
				'proxy' => ['99000', 'abcd'],
				'expected_error' => 'Invalid parameter "/2": a number is expected.'
			],
			// Check if proxy used in actions.
			[
				'proxy' => ['99003'],
				'expected_error' => 'Proxy "Api active proxy in action" is used by action "API action with proxy".'
			],
			[
				'proxy' => ['99000', '99003'],
				'expected_error' => 'Proxy "Api active proxy in action" is used by action "API action with proxy".'
			],
			// Check if proxy used in host.
			[
				'proxy' => ['99004'],
				'expected_error' => 'Host "API Host monitored with proxy" is monitored with proxy "Api active proxy with host".'
			],
			[
				'proxy' => ['99000', '99004'],
				'expected_error' => 'Host "API Host monitored with proxy" is monitored with proxy "Api active proxy with host".'
			],
			// Check if proxy used in discovery rule.
			[
				'proxy' => ['99006'],
				'expected_error' => 'Proxy "Api active proxy for discovery" is used by discovery rule "API discovery rule for delete with proxy".'
			],
			// Successfully delete proxy.
			[
				'proxy' => ['99000'],
				'expected_error' => null
			],
			[
				'proxy' => ['99001', '99002'],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider proxy_delete
	*/
	public function testProxy_Delete($proxy, $expected_error) {
		$result = $this->call('proxy.delete', $proxy, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['proxyids'] as $id) {
				$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE hostid='.zbx_dbstr($id)));
			}
		}
	}
}
