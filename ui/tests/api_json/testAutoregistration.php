<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../include/CAPITest.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testAutoregistration extends CAPITest {

	public static $data = [
		'hostgroup' => [],
		'host' => [],
		'proxy' => []
	];

	public static function prepareTestData(): void {
		$hostgroups = [];
		// dataProviderInvalidAutoregistrationWithHostEncryptionUpdate
		$hostgroups[] = ['name' => 'API autoregistration tests hosts group'];
		$result = CDataHelper::call('hostgroup.create', $hostgroups);
		self::$data['hostgroup'] = array_combine(array_column($hostgroups, 'name'), $result['groupids']);

		$hosts = [];
		// dataProviderInvalidAutoregistrationWithHostEncryptionUpdate
		$hosts[] = [
			'host' => 'test.example.com',
			'groups' => [['groupid' => ':hostgroup:API autoregistration tests hosts group']],
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'hostidentity',
			'tls_psk' => '0d59887b3a4f2a2e6866985491699034'
		];
		$result = CDataHelper::call('host.create', self::resolveIds($hosts));
		self::$data['host'] = array_combine(array_column($hosts, 'host'), $result['hostids']);

		$proxies = [];
		// dataProviderInvalidAutoregistrationWithHostEncryptionUpdate
		$proxies[] = [
			'name' => 'test.example.com',
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'proxyidentity',
			'tls_psk' => '486a9e7b43740b3619e42636cb1c24bf'
		];
		$result = CDataHelper::call('proxy.create', $proxies);
		self::$data['proxy'] = array_combine(array_column($proxies, 'name'), $result['proxyids']);

		CDataHelper::call('autoregistration.update', [
			'tls_accept' => HOST_ENCRYPTION_NONE,
			'tls_psk_identity' => '',
			'tls_psk' => ''
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

	public static function dataProviderInvalidAutoregistrationUpdate() {
		return [
			'Cannot set "tls_accept" to HOST_ENCRYPTION_PSK without tls_psk_identity' => [
				'data' => ['tls_accept' => HOST_ENCRYPTION_PSK],
				'expected_error' => 'Invalid parameter "/": the parameter "tls_psk_identity" is missing.'
			],
			'Cannot set "tls_accept" to HOST_ENCRYPTION_PSK without tls_psk' => [
				'data' => ['tls_accept' => HOST_ENCRYPTION_PSK, 'tls_psk_identity' => 'uniquestring'],
				'expected_error' => 'Invalid parameter "/": the parameter "tls_psk" is missing.'
			],
			'Cannot update "tls_psk_identity" with HOST_ENCRYPTION_NONE' => [
				'data' => ['tls_psk_identity' => 'uniquestring'],
				'expected_error' => 'Invalid parameter "/tls_psk_identity": should be empty.'
			],
			'Cannot update "tls_psk" with HOST_ENCRYPTION_NONE' => [
				'data' => ['tls_psk' => 'aeb34298c6616d479d9a7c0c44e7143b'],
				'expected_error' => 'Invalid parameter "/tls_psk": should be empty.'
			]
		];
	}

	public static function dataProviderValidAutoregistrationUpdate() {
		return [
			'Can update "tls_accept" to HOST_ENCRYPTION_NONE' => [
				'data' => ['tls_accept' => HOST_ENCRYPTION_NONE]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidAutoregistrationUpdate
	 * @dataProvider dataProviderValidAutoregistrationUpdate
	 */
	public function testAutoregistration_Update(array $request, $expected_error = null) {
		$this->call('autoregistration.update', $request, $expected_error);
	}

	public static function dataProviderInvalidAutoregistrationWithHostEncryptionUpdate() {
		return [
			'Cannot update "tls_psk_identity" when host uses same "tls_psk_identity" with different tls_psk key' => [
				'data' => ['tls_psk_identity' => 'hostidentity'],
				'expected_error' => 'Invalid parameter "/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Cannot update "tls_psk_identity" when proxy uses same "tls_psk_identity" with different tls_psk key' => [
				'data' => ['tls_psk_identity' => 'proxyidentity'],
				'expected_error' => 'Invalid parameter "/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			]
		];
	}

	public static function dataProviderValidAutoregistrationWithHostEncryptionUpdate() {
		return [
			'Can update "tls_psk"' => [
				'data' => [
					'tls_psk' => '3f19f5a49cf97e5fe1487aa60547d5fc'
				]
			],
			'Can update "tls_psk_identity" and "tls_psk" from existing host' => [
				'data' => [
					'tls_psk_identity' => 'hostidentity',
					'tls_psk' => '0d59887b3a4f2a2e6866985491699034'
				]
			],
			'Can update "tls_psk_identity" and "tls_psk" from existing proxy' => [
				'data' => [
					'tls_psk_identity' => 'proxyidentity',
					'tls_psk' => '486a9e7b43740b3619e42636cb1c24bf'
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidAutoregistrationWithHostEncryptionUpdate
	 * @dataProvider dataProviderValidAutoregistrationWithHostEncryptionUpdate
	 */
	public function testAutoregistrationWithHostEncryption_Update(array $request, $expected_error = null) {
		static $encryption_set = false;

		if (!$encryption_set) {
			CDataHelper::call('autoregistration.update', [
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'autoregistration',
				'tls_psk' => 'ec30a947e6776ae9efb77f46aefcba04'
			]);
			$encryption_set = true;
		}

		$this->call('autoregistration.update', $request, $expected_error);
	}
}
