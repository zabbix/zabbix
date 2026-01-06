<?php
/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

	public static $data = [
		'proxy' => [],
		'host' => [],
		'hostgroup' => []
	];

	public static function prepareTestData(): void {
		$proxies = [];
		// dataProviderInvalidProxyCreate: Field "tls_psk" cannot have different values for same "tls_psk_identity"
		$proxies[] = [
			'host' => 'test.example.com',
			'status' => HOST_STATUS_PROXY_ACTIVE,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'public',
			'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
		];

		// dataProviderInvalidProxyUpdate, dataProviderValidProxyUpdate
		$proxies[] = [
			'host' => 'psk1.example.com',
			'status' => HOST_STATUS_PROXY_ACTIVE,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'example.com',
			'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
		];
		$proxies[] = [
			'host' => 'psk2.example.com',
			'status' => HOST_STATUS_PROXY_ACTIVE,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'example.com',
			'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
		];
		$proxies[] = [
			'host' => 'psk3.example.com',
			'status' => HOST_STATUS_PROXY_PASSIVE,
			'tls_connect' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'psk3.example.com',
			'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85',
			'interface' => [
				'useip' => INTERFACE_USE_DNS,
				'dns' => 'example.com',
				'port' => 123
			]
		];

		$result = CDataHelper::call('proxy.create', $proxies);
		self::$data['proxy'] += array_combine(array_column($proxies, 'host'), $result['proxyids']);

		$hostgroups = [];
		// dataProviderInvalidProxyCreate, dataProviderInvalidProxyUpdate
		$hostgroups[] = ['name' => 'API tests hosts group'];
		$result = CDataHelper::call('hostgroup.create', $hostgroups);
		self::$data['hostgroup'] += array_combine(array_column($hostgroups, 'name'), $result['groupids']);

		$hosts = [];
		// dataProviderInvalidProxyCreate, dataProviderInvalidProxyUpdate
		$hosts[] = [
			'host' => 'psk4.example.com',
			'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
			'tls_connect' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'psk4.example.com',
			'tls_psk' => '142e0f6c07c1d2099d0606157a0bdaca'
		];
		$result = CDataHelper::call('host.create', self::resolveIds($hosts));
		self::$data['host'] += array_combine(array_column($hosts, 'host'), $result['hostids']);

		CDataHelper::call('autoregistration.update', [
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'autoregistration',
			'tls_psk' => 'ec30a947e6776ae9efb77f46aefcba04'
		]);
	}

	public static function cleanTestData(): void {
		CDataHelper::call('proxy.delete', array_values(self::$data['proxy']));
		CDataHelper::call('host.delete', array_values(self::$data['host']));
		CDataHelper::call('hostgroup.delete', array_values(self::$data['hostgroup']));
		CDataHelper::call('autoregistration.update', ['tls_accept' => HOST_ENCRYPTION_NONE]);
	}

	private static function resolveIds(array $rows) {
		foreach ($rows as &$value) {
			if (is_array($value)) {
				$value = self::resolveIds($value);
			}
			else {
				// Whitespaces in $key are not trimmed.
				[, $api, $key] = explode(':', (string) $value, 3) + ['', '', ''];

				if ($api !== '' && $key !== '' && array_key_exists($key, self::$data[$api])) {
					$value = self::$data[$api][$key];
				}
			}
		}
		unset($value);

		return $rows;
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
				'expected_error' => 'Invalid parameter "/2/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" with database check' => [
				'proxy' => [
					[
						'host' => 'bca.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all proxies and hosts' => [
				'proxy' => [
					[
						'host' => 'bca.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'psk4.example.com',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all proxies and autoregistration' => [
				'proxy' => [
					[
						'host' => 'bca.example.com',
						'status' => HOST_STATUS_PROXY_ACTIVE,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'autoregistration',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
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
	public function testProxy_Create($proxies, $expected_error = null) {
		$response = $this->call('proxy.create', $proxies, $expected_error);

		if ($expected_error === null) {
			self::$data['proxy'] += array_combine(array_column($proxies, 'host'), $response['result']['proxyids']);
		}
	}

	public static function dataProviderInvalidProxyUpdate() {
		return [
			'Field "tls_psk_identity" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					['proxyid' => ':proxy:psk1.example.com', 'tls_psk_identity' => '']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk_identity"' => [
				'proxy' => [
					['proxyid' => ':proxy:psk3.example.com', 'tls_psk_identity' => 'example.com']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'proxy' => [
					['proxyid' => ':proxy:psk1.example.com', 'tls_psk' => '']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk"' => [
				'proxy' => [
					['proxyid' => ':proxy:psk1.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all proxies and hosts' => [
				'proxy' => [
					['proxyid' => ':proxy:psk1.example.com', 'tls_psk_identity' => 'psk4.example.com']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all proxies and autoregistration' => [
				'proxy' => [
					['proxyid' => ':proxy:psk1.example.com', 'tls_psk_identity' => 'autoregistration']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			]
		];
	}

	public static function dataProviderValidProxyUpdate() {
		return [
			'Can change "tls_psk_identity" and "tls_psk"' => [
				'proxy' => [
					['proxyid' => ':proxy:psk1.example.com', 'tls_psk_identity' => 'psk3.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85']
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidProxyUpdate
	 * @dataProvider dataProviderValidProxyUpdate
	 */
	public function testProxy_Update($proxies, $expected_error = null) {
		$this->call('proxy.update', self::resolveIds($proxies), $expected_error);
	}
}
