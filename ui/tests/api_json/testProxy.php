<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @backup   hosts
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testProxy extends CAPITest {

	public static $proxyids = [];

	public static function prepareTestData(): void {
		// dataProviderInvalidProxyCreate: Field "tls_psk" cannot have different values for same "tls_psk_identity"
		$proxy = [
			'host' => 'test.example.com',
			'status' => HOST_STATUS_PROXY_ACTIVE,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'public',
			'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
		];
		$result = CDataHelper::call('proxy.create', $proxy);
		self::$proxyids += [$proxy['host'] => $result['proxyids'][0]];
	}

	public static function cleanTestData(): void {
		CDataHelper::call('proxy.delete', array_values(self::$proxyids));
	}

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
	public function testProxy_Delete($proxy, $expected_error) {
		$result = $this->call('proxy.delete', $proxy, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['proxyids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM hosts WHERE hostid='.zbx_dbstr($id)));
			}
		}
	}

	public static function dataProviderInvalidProxyCreate() {
		return [
			'Field "tls_psk_identity" is required when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk_identity" is missing.'
			],
			'Field "tls_psk_identity" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk_identity" cannot be set when "tls_connect" is not set to HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_psk_identity' => 'identity'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_psk_identity" is required when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk_identity" is missing.'
			],
			'Field "tls_psk_identity" cannot be empty when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk" is required when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'example'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk" is missing.'
			],
			'Field "tls_psk" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" cannot be set when "tls_connect" is not set to HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk" is required when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'example'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk" is missing.'
			],
			'Field "tls_psk" cannot be empty when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" should have correct format' => [
				'proxy' => [
					[
						'host' => 'example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => 'fb48829a6f9ebbb70294a75ca09167rr'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity"' => [
				'proxy' => [
					[
						'host' => 'bca.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					],
					[
						'host' => 'abc.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => 'fb48829a6f9ebbb70294a75ca0916772'
					]
				],
				'expected_error' => 'Incorrect value for field "/2/tls_psk": another value of tls_psk exists for same tls_psk_identity.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity"' => [
				'proxy' => [
					[
						'host' => 'bca.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Incorrect value for field "/1/tls_psk": another value of tls_psk exists for same tls_psk_identity.'
			]
		];
	}

	public static function dataProviderValidProxyCreate() {
		$interface = [
			'useip' => INTERFACE_USE_DNS,
			'dns' => 'example.com',
			'port' => 123
		];

		return [
			'Create proxies with required fields' => [
				'proxy' => [
					['host' => 'one.example.com', 'status' => HOST_STATUS_PROXY_PASSIVE, 'interface' => $interface],
					['host' => 'two.example.com', 'status' => HOST_STATUS_PROXY_ACTIVE]
				]
			],
			'Create proxies with tls_psk' => [
				'proxy' => [
					[
						'host' => 'three.example.com',
						'status' => HOST_STATUS_PROXY_PASSIVE,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'three.example.com',
						'tls_psk' => '6bc6d37628314e1331a21af0be9b4f22',
						'interface' => $interface
					],
					[
						'host' => 'four.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'four.example.com',
						'tls_psk' => '10c0086085d3323b4f77af52060ecb24'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidProxyCreate
	 * @dataProvider dataProviderValidProxyCreate
	 */
	public function testProxy_Create($proxy, $expected_error = null) {
		$response = $this->call('proxy.create', $proxy, $expected_error);

		if ($expected_error === null) {
			self::$proxyids += array_combine(array_column($proxy, 'host'), $response['result']['proxyids']);
		}
	}
}
