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
 * @onBefore prepareTestData
 *
 * @backup hosts
 */

class testProxy extends CAPITest {
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
				'expected_error' => 'Host "API Host monitored with proxy" is monitored by proxy "Api active proxy with host".'
			],
			[
				'proxy' => ['99000', '99004'],
				'expected_error' => 'Host "API Host monitored with proxy" is monitored by proxy "Api active proxy with host".'
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
	public function testProxy_Delete($proxy, $expected_error)
	{
		$result = $this->call('proxy.delete', $proxy, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['proxyids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE hostid=' . zbx_dbstr($id)));
			}
		}
	}

	public static function proxy_get() {
		return [
			// Check successful get.
			[
				'params' => [
					'output' => ['host', 'status', 'description', 'lastaccess', 'tls_connect'],
					'proxyids' => 'proxyid1',
				],
				'expected_result' => [
					'host' => 'Passive proxy with DNS connection',
					'status' => 6,
					'description' => '',
					'lastaccess' => 0,
					'tls_connect' => 1,
					'proxyid' => 'proxyid1'
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['host', 'status', 'description', 'lastaccess', 'tls_connect'],
					'selectInterface' => ['dns', 'ip', 'port', 'useip'],
					'proxyids' => 'proxyid2',
				],
				'expected_result' => [
					'host' => 'Passive proxy with IP address',
					'status' => 6,
					'description' => '',
					'lastaccess' => 0,
					'tls_connect' => 1,
					'proxyid' => 'proxyid2',
					'interface' => [
						'dns' => 'localhost',
						'ip' => '127.0.0.1',
						'port' => '10050',
						'useip' => 1
					],
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['host', 'status', 'description', 'proxy_address', 'auto_compress'],
					'proxyids' => 'proxyid3',
				],
				'expected_result' => [
					'host' => 'Active proxy with description',
					'status' => 5,
					'description' => 'this is a test description for active proxy',
					'proxy_address' => 'loremipsum',
					'auto_compress' => 1,
					'proxyid' => 'proxyid3',
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['host', 'status', 'tls_accept'],
					'proxyids' => 'proxyid4',
				],
				'expected_result' => [
					'host' => 'Active proxy PSK encryption',
					'status' => 5,
					'tls_accept' => 2,
					'proxyid' => 'proxyid4',
				],
				'expected_error' => null
			],
			[
				'params' => [
					'output' => ['host', 'status', 'tls_accept', 'tls_connect', 'version', 'compatibility'],
					'selectInterface' => ['dns', 'ip', 'port', 'useip'],
					'proxyids' => 'proxyid7',
				],
				'expected_result' => [
					'host' => 'Passive proxy with PSK connection to proxy',
					'status' => 6,
					'tls_accept' => 1,
					'tls_connect' => 2,
					'version' => '',
					'compatibility' => 0,
					'proxyid' => 'proxyid7',
					'interface' => [
						'dns' => 'localhost',
						'ip' => '127.0.0.1',
						'port' => '10050',
						'useip' => 0
					],
				],
				'expected_error' => null
			],
			// Check unsuccessful get.
			[
				'params' => [
					'output' => ['host', 'interface'],
					'proxyids' => 'proxyid6',
				],
				'expected_result' => false,
				'expected_error' => 'Invalid parameter "/output/2": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			]
		];
	}

	/**
	 * @dataProvider proxy_get
	 */
	public function testProxy_Get($params, $expected_result, $expected_error) {
		if ($params['proxyids'] === 'proxyid1' || $params['proxyids'] === 'proxyid2'
			|| $params['proxyids'] === 'proxyid3' || $params['proxyids'] === 'proxyid4'
			|| $params['proxyids'] === 'proxyid7' || $params['proxyids'] === 'proxyid6') {
			$params['proxyids'] = (int) self::$data['proxyids'][$params['proxyids']];
		}

		if ($expected_result !== false) {
			if ($expected_result['proxyid'] === 'proxyid1' || $expected_result['proxyid'] === 'proxyid2'
				|| $expected_result['proxyid'] === 'proxyid3' || $expected_result['proxyid'] === 'proxyid4'
				|| $expected_result['proxyid'] === 'proxyid7') {
				$expected_result['proxyid'] = (int) self::$data['proxyids'][$expected_result['proxyid']];
			}
		}

		$result = $this->call('proxy.get', $params, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result'] as $proxy) {
				foreach ($expected_result as $field => $expected_value){
					$this->assertArrayHasKey($field, $proxy, 'Field should be present.');
					$this->assertEquals($proxy[$field], $expected_value, 'Returned value should match.');
				}
			}
		}
	}

	/**
	 * Test data used by tests.
	 */
	protected static $data = [
		'hostgroupid' => null,
		'proxyids' => ['proxyid1', 'proxyid2', 'proxyid3', 'proxyid4', 'proxyid5', 'proxyid6', 'proxyid7', 'proxyid8']
	];

	/**
	 * Prepare data for tests.
	 */
	public function prepareTestData() {
		// Create host group.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'hostgroup proxy test'
			]
		]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$data['hostgroupid'] = $hostgroups['groupids'][0];

		$proxies = CDataHelper::call('proxy.create', [
			// Passive proxy - DNS connection
			[
				'host' => 'Passive proxy with DNS connection',
				'status' => 6,
				'interface' => [
					'useip' => 0,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			// Passive proxy - IP address
			[
				'host' => 'Passive proxy with IP address',
				'status' => 6,
				'interface' => [
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			// Active proxy with address, description and no encryption
			[
				'host' => 'Active proxy with description',
				'status' => 5,
				'proxy_address' => 'loremipsum',
				'description' => 'this is a test description for active proxy'
			],
			// Active proxy with PSK connection
			[
				'host' => 'Active proxy PSK encryption',
				'status' => 5,
				'tls_accept' => 2,
				'tls_psk_identity' => 'loremipsumdolorsitametconsecteturadipiscingelit',
				'tls_psk' => '123123123123123123123123123123123123'
			],
			// Active proxy with certificate connection
			[
				'host' => 'Active proxy Certificate encryption',
				'status' => 5,
				'tls_accept' => 4,
				'tls_issuer' => 'Loremipsum',
				'tls_subject' => ''
			],
			// Active proxy with all three connections from proxy
			[
				'host' => 'Active proxy all connections',
				'status' => 5,
				'tls_accept' => 7,
				'tls_psk_identity' => 'loremipsumdolorsitametconsecteturadipiscingelit',
				'tls_psk' => '123123123123123123123123123123123123',
				'tls_issuer' => 'Loremipsum',
				'tls_subject' => ''
			],
			// Passive proxy with PSK connection
			[
				'host' => 'Passive proxy with PSK connection to proxy',
				'status' => 6,
				'interface' => [
					'useip' => 0,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				],
				'tls_connect' => 2,
				'tls_psk_identity' => 'loremipsumdolorsitametconsecteturadipiscingelit',
				'tls_psk' => '123123123123123123123123123123123123'
			],
			// Passive proxy with Certificate connection
			[
				'host' => 'Passive proxy with Certificate connection to proxy',
				'status' => 6,
				'interface' => [
					'useip' => 0,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				],
				'tls_connect' => 4,
				'tls_issuer' => 'Loremipsum',
				'tls_subject' => 'Loremipsum'
			]
		]);

		$this->assertArrayHasKey('proxyids', $proxies);
		self::$data['proxyids'] = array_combine(self::$data['proxyids'], $proxies['proxyids']);
	}
}
