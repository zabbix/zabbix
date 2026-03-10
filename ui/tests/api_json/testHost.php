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
class testHost extends CAPITest {

	public static $data = [
		'host' => [],
		'hostgroup' => []
	];

	public static function prepareTestData(): void {
		$hostgroups = [];
		// dataProviderInvalidHostCreate
		$hostgroups[] = ['name' => 'API tests hosts group'];
		$result = CDataHelper::call('hostgroup.create', $hostgroups);
		self::$data['hostgroup'] = array_combine(array_column($hostgroups, 'name'), $result['groupids']);

		CDataHelper::call('autoregistration.update', [
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'autoregistration',
			'tls_psk' => 'ec30a947e6776ae9efb77f46aefcba04'
		]);

		self::prepareTestDataHostPskFieldsCreate();
		self::prepareTestDataHostPskFieldsUpdate();
		self::prepareTestDataHostPskFieldsMassUpdate();
	}

	public static function cleanTestData(): void {
		CDataHelper::call('host.delete', array_values(self::$data['host']));
		CDataHelper::call('hostgroup.delete', array_values(self::$data['hostgroup']));
		CDataHelper::call('autoregistration.update', ['tls_accept' => HOST_ENCRYPTION_NONE]);
	}

	public static function resolveIds(array $rows) {
		foreach ($rows as &$value) {
			if (is_array($value)) {
				$value = self::resolveIds($value);
			}
			else {
				// Whitespaces in $key are not trimmed.
				[$api, $key] = sscanf((string) $value, ':%[^: ]:%[^\0]');

				if ($api !== null && $key !== null && array_key_exists($key, self::$data[$api])) {
					$value = self::$data[$api][$key];
				}
			}
		}
		unset($value);

		return $rows;
	}

	public static function host_delete() {
		return [
			[
				'hostids' => [
					'61001'
				],
				'expected_error' => 'Cannot delete host "maintenance_has_only_host" because maintenance "maintenance_has_only_host" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61001',
					'61003'
				],
				'expected_error' => 'Cannot delete host "maintenance_has_only_host" because maintenance "maintenance_has_only_host" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61003'
				],
				'expected_error' => null
			],
			[
				'hostids' => [
					'61004',
					'61005'
				],
				'expected_error' => 'Cannot delete hosts "maintenance_host_1", "maintenance_host_2" because maintenance "maintenance_two_hosts" must contain at least one host or host group.'
			],
			[
				'hostids' => [
					'61004'
				],
				'expected_error' => null
			]
		];
	}

	public static function host_create() {
		return [
			[
				'request' => [
					'groups' => ['5'],
					'host' => 'new host 1'
				],
				'expected_error' => "Incorrect value for field \"groups\": the parameter \"groupid\" is missing."
			],
			[
				'request' => [
					'groups' => [
						'groupid' => 4
					],
					'host' => 'new host 4',
					'interfaces' => ''
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			[
				'request' => [
					'groups' => [
						'groupid' => 4
					],
					'host' => 'new host 5',
					'interfaces' => 'string'
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			[
				'request' => [
					'groups' => [
						'groupid' => 4
					],
					'host' => 'new host 6',
					'interfaces' => 10
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			]
		];
	}

	public static function prepareTestDataHostPskFieldsCreate() {
		$hosts = [
			[
				'host' => 'test.example.com',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'public',
				'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
			]
		];
		$result = CDataHelper::call('host.create', self::resolveIds($hosts));
		self::$data['host'] += array_combine(array_column($hosts, 'host'), $result['hostids']);
	}

	public static function dataProviderInvalidHostCreate() {
		return [
			'Field "tls_psk_identity" is required when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk_identity" is missing.'
			],
			'Field "tls_psk_identity" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk_identity" cannot be set when "tls_connect" is not set to HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_psk_identity' => 'identity'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_psk_identity" is required when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk_identity" is missing.'
			],
			'Field "tls_psk_identity" cannot be empty when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk" is required when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'example'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk" is missing.'
			],
			'Field "tls_psk" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" cannot be set when "tls_connect" is not set to HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk" is required when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'example'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk" is missing.'
			],
			'Field "tls_psk" cannot be empty when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" should have correct format' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => 'fb48829a6f9ebbb70294a75ca09167rr'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity"' => [
				'host' => [
					[
						'host' => 'bca.example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					],
					[
						'host' => 'abc.example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => 'fb48829a6f9ebbb70294a75ca0916772'
					]
				],
				'expected_error' => 'Invalid parameter "/2/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" with database check' => [
				'host' => [
					[
						'host' => 'bca.example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all hosts and autoregistration' => [
				'host' => [
					[
						'host' => 'bca.example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'autoregistration',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			]
		];
	}

	public static function dataProviderValidHostCreate() {
		return [
			'Create host without "interfaces"' => [
				'host' => [
					[
						'host' => 'new host 2',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']]
					]
				]
			],
			'Create host with "interfaces" set to empty array' => [
				'host' => [
					[
						'host' => 'new host 3',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'interfaces' => []
					]
				]
			],
			'Create hosts with "tls_psk"' => [
				'host' => [
					[
						'host' => 'three.example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'three.example.com',
						'tls_psk' => '6bc6d37628314e1331a21af0be9b4f22'
					],
					[
						'host' => 'four.example.com',
						'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
						'tls_accept' => HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'four.example.com',
						'tls_psk' => '10c0086085d3323b4f77af52060ecb24'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider host_create
	 * @dataProvider dataProviderInvalidHostCreate
	 * @dataProvider dataProviderValidHostCreate
	 */
	public function testHost_Create($request, $expected_error = null) {
		$response = $this->call('host.create', self::resolveIds($request), $expected_error);

		if ($expected_error === null) {
			self::$data['host'] += array_combine(array_column($request, 'host'), $response['result']['hostids']);
		}
	}

	/**
	 * @dataProvider host_delete
	 */
	public function testHost_Delete($hostids, $expected_error) {
		$result = $this->call('host.delete', $hostids, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['hostids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('select * from hosts where hostid='.zbx_dbstr($id)));
			}
		}
	}

	public static function host_select_tags() {
		return [
			'Get test host tag as extend' => [
				'params' => [
					'hostids' => [50032],
					'selectTags' => 'extend'
				],
				'expected_result' => [
					'tags' => [
						['tag' => 'b', 'value' => 'b']
					]
				]
			],
			'Get test host tag excluding value' => [
				'params' => [
					'hostids' => [50032],
					'selectTags' => ['tag']
				],
				'expected_result' => [
					'tags' => [
						['tag' => 'b']
					]
				]
			],
			'Get test host tag excluding name' => [
				'params' => [
					'hostids' => [50032],
					'selectTags' => ['value']
				],
				'expected_result' => [
					'tags' => [
						['value' => 'b']
					]
				]
			]
		];
	}

	/**
	* @dataProvider host_select_tags
	*/
	public function testHost_SelectTags($params, $expected_result) {
		$result = $this->call('host.get', $params);

		foreach ($result['result'] as $host) {
			foreach ($expected_result as $field => $expected_value){
				$this->assertArrayHasKey($field, $host, 'Field should be present.');
				$this->assertEquals($host[$field], $expected_value, 'Returned value should match.');
			}
		}
	}

	public static function dataHostFieldPresence() {
		return [
			'Check if {"output": "extend"} includes "inventory_mode" and excludes write-only properties' => [
				'request' => [
					'output' => 'extend',
					'hostids' => ['99013']
				],
				'expected_result' => [
					'hostid' => '99013',
					'inventory_mode' => '-1',

					// Write-only properties.
					'tls_psk_identity' => null,
					'tls_psk' => null,
					'name_upper' => null
				]
			],
			'Check it is not possible to select write-only fields' => [
				'request' => [
					'output' => ['host', 'tls_psk', 'tls_psk_identity', 'name_upper'],
					'hostids' => ['99013']
				],
				'expected_result' => [
					'hostid' => '99013',

					// Sample of unspecified property.
					'inventory_mode' => null,

					// Write-only properties.
					'tls_psk_identity' => null,
					'tls_psk' => null,
					'name_upper' => null
				]
			],
			'Check direct request of inventory_mode and other properties' => [
				'request' => [
					'output' => ['inventory_mode', 'tls_connect', 'name', 'name_upper'],
					'hostids' => ['99013']
				],
				'expected_result' => [
					'hostid' => '99013',
					'inventory_mode' => '-1',

					// Samples of other specified properties.
					'tls_connect' => '1',
					'name' => 'Host OS - Windows',
					'name_upper' => null
				]
			]
		];
	}

	/**
	 * @dataProvider dataHostFieldPresence
	 */
	public function testHost_FieldPresenceAndExclusion($request, $expected_result) {
		$result = $this->call('host.get', $request, null);

		foreach ($result['result'] as $host) {
			foreach ($expected_result as $key => $value) {
				if ($value !== null) {
					$this->assertArrayHasKey($key, $host, 'Key '.$key.' should be present in host output.');
					$this->assertEquals($value, $host[$key], 'Value should match.');
				}
				else {
					$this->assertArrayNotHasKey($key, $host, 'Key '.$key.' should NOT be present in host output');
				}
			}
		}
	}

	public static function prepareTestDataHostPskFieldsUpdate() {
		$hosts = [
			[
				'host' => 'psk1.example.com',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'example.com',
				'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
			],
			[
				'host' => 'psk2.example.com',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'example.com',
				'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
			],
			[
				'host' => 'psk3.example.com',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_connect' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'psk3.example.com',
				'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85'
			],
			[
				'host' => 'psk4.example.com',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_connect' => HOST_ENCRYPTION_NONE
			]
		];

		$result = CDataHelper::call('host.create', self::resolveIds($hosts));
		self::$data['host'] += array_combine(array_column($hosts, 'host'), $result['hostids']);
	}

	public static function dataProviderInvalidHostUpdate() {
		return [
			'Field "tls_psk_identity" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => '']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk_identity"' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk_identity' => 'example.com']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk' => '']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all hosts and autoregistration' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'autoregistration']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk_identity" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk_identity' => 'psk3.example.com', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_psk_identity" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_psk_identity' => 'psk4.example.com', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_issuer" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_issuer' => 'psk4.example.com', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Field "tls_issuer" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_issuer' => 'psk4.example.com', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Field "tls_subject" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_subject' => 'psk4.example.com', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Field "tls_subject" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_subject' => 'psk4.example.com', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			]
		];
	}

	public static function dataProviderValidHostUpdate() {
		return [
			'Can update "tls_psk_identity" and "tls_psk"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'psk4.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85']
				]
			],
			'Can update "tls_psk"' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk' => '11111111111111111111111111111111']
				]
			],
			'Can update "tls_psk_identity"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'psk5.example.com']
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidHostUpdate
	 * @dataProvider dataProviderValidHostUpdate
	 */
	public function testHost_Update($hosts, $expected_error = null) {
		$this->call('host.update', self::resolveIds($hosts), $expected_error);
	}

	public static function prepareTestDataHostPskFieldsMassUpdate() {
		$hosts = [
			[
				'host' => 'host.massupdate.psk1',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'host.massupdate.psk1',
				'tls_psk' => '85fbc6f14e967d7e75b12da395ca9b46'
			],
			[
				'host' => 'host.massupdate.psk2',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_connect' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'host.massupdate.psk2',
				'tls_psk' => 'dc773e30385b5248b67c29988812d876'
			],
			[
				'host' => 'host.massupdate.psk3',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'host.massupdate.psk3',
				'tls_psk' => '6e801121c08cee058677d3b99e888740'
			],
			[
				'host' => 'host.massupdate.psk4',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'host.massupdate.psk3',
				'tls_psk' => '6e801121c08cee058677d3b99e888740'
			],
			[
				'host' => 'host.massupdate.psk5',
				'groups' => [['groupid' => ':hostgroup:API tests hosts group']],
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'host.massupdate.psk5',
				'tls_psk' => '3ea2412335c350dcb8a0e76d1152f372'
			]
		];

		$result = CDataHelper::call('host.create', self::resolveIds($hosts));
		self::$data['host'] += array_combine(array_column($hosts, 'host'), $result['hostids']);
	}

	public static function dataProviderInvalidHostPskFieldsMassUpdate() {
		return [
			'Field "tls_accept" is required when "tls_connect" is set' => [
				[
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1'],
						['hostid' => ':host:host.massupdate.psk2']
					]
				],
				'Both "tls_connect" and "tls_accept" fields must be specified when changing settings of connection encryption.'
			],
			'Field "tls_connect" is required when "tls_accept" is set' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1']
					]
				],
				'Both "tls_connect" and "tls_accept" fields must be specified when changing settings of connection encryption.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk_identity"' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk1',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1'],
						['hostid' => ':host:host.massupdate.psk2']
					]
				],
				'Both "tls_psk_identity" and "tls_psk" fields must be specified when changing the PSK for connection encryption.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk"' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk' => '11111111111111111111111111111111',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk3']
					]
				],
				'Both "tls_psk_identity" and "tls_psk" fields must be specified when changing the PSK for connection encryption.'
			],
			'Field "tls_psk" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_PSK' => [
				[
					'tls_psk' => '12111111111111111111111111111121',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_psk": value must be empty.'
			],
			'Field "tls_psk_identity" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_PSK' => [
				[
					'tls_psk_identity' => 'host.massupdate.psk5',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_psk_identity": value must be empty.'
			],
			'Field "tls_issuer" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				[
					'tls_issuer' => 'host.massupdate.psk5',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_issuer": value must be empty.'
			],
			'Field "tls_subject" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				[
					'tls_subject' => 'host.massupdate.psk5',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_subject": value must be empty.'
			]
		];
	}

	public static function dataProviderValidHostPskFieldsMassUpdate() {
		return [
			'Can update "tls_psk_identity" and "tls_psk"' => [
				[
					'tls_psk_identity' => 'host.massupdate',
					'tls_psk' => 'a296c2411feb2b730bea9742307fba01',
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1'],
						['hostid' => ':host:host.massupdate.psk2']
					]
				]
			],
			'Can update "tls_psk" and "tls_psk_identity" for multiple hosts having same values of "tls_psk"' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'massupdate.tls_psk_identity',
					'tls_psk' => '11111111111111111111111111111111',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk3'],
						['hostid' => ':host:host.massupdate.psk4']
					]
				]
			],
			'Can update certificate fields for multiple hosts' => [
				[
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_issuer' => 'abc',
					'tls_subject' => 'def',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk3'],
						['hostid' => ':host:host.massupdate.psk4']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidHostPskFieldsMassUpdate
	 * @dataProvider dataProviderValidHostPskFieldsMassUpdate
	 */
	public function testHostPskFields_MassUpdate($data, $expected_error = null) {
		$data = self::resolveIds($data);

		if (array_key_exists('hosts', $data)) {
			$data['hosts'] = self::resolveIds($data['hosts']);
		}

		$this->call('host.massupdate', $data, $expected_error);
	}
}
