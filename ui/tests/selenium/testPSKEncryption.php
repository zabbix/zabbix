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


require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup config, config_autoreg_tls
 *
 * @onBefore prepareHostProxyData
 *
 * @onAfter clearAutoregistrationData
 */
class testPSKEncryption extends CWebTest {
	const UPDATE_SAME_HOST = 'A Same host with PSK Encryption';
	const UPDATE_SAME_PROXY = 'A Same proxy with PSK Encryption';
	const HOST_NAME = 'A Host with PSK Encryption';
	const PROXY_NAME = 'A Proxy with PSK Encryption';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function prepareHostProxyData() {
		$groupid = CDataHelper::call('hostgroup.create', [['name' => 'Group for Encryption']])['groupids'][0];

		CDataHelper::call('host.create', [
				[
					'host' => self::HOST_NAME,
					'groups' => [['groupid' => $groupid]],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host_identity',
					'tls_psk' => '41b4d07b27a8efdcc15d4742e03857eba377fe010853a1499b0522df171282cb'
				],
				[
					'host' => self::UPDATE_SAME_HOST,
					'groups' => [['groupid' => $groupid]],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'same_host_identity',
					'tls_psk' => '978d5dfe7ddc50489078860a5c9c902632acf8efb0c88c869e3812a4c1a4de04'
				],
				[
					'host' => 'Existing host with PSK',
					'groups' => [['groupid' => $groupid]],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'existing_host_identity',
					'tls_psk' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
				],
				[
					'host' => '1 Host for mass update',
					'groups' => [['groupid' => $groupid]],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'mass_update_identity',
					'tls_psk' => 'f8f07e658898455778b35108c78ebd7e29dbed87de4a5619447e189dd9300d5e'
				],
				[
					'host' => '2 Host for mass update',
					'groups' => [['groupid' => $groupid]],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'mass_update_identity',
					'tls_psk' => 'f8f07e658898455778b35108c78ebd7e29dbed87de4a5619447e189dd9300d5e'
				],
				[
					'host' => '3 Host for mass update',
					'groups' => [['groupid' => $groupid]]
				],
				[
					'host' => '4 Host for mass update',
					'groups' => [['groupid' => $groupid]]
				],
				[
					'host' => '5 Host for mass update',
					'groups' => [['groupid' => $groupid]],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'new_mass_update_identity',
					'tls_psk' => '715faa6ec090613cf417c7b4212ce260bd821831713e9b213c15bc8c80c0b8c5'
				],
				[
					'host' => '6 Host for mass update',
					'groups' => [['groupid' => $groupid]]
				]
			]
		);

		CDataHelper::call('proxy.create', [
				[
					'name' => self::PROXY_NAME,
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'proxy_identity',
					'tls_psk' => 'a8a8e9a36a77a9383fa172579ecd2b69d3204b4b6762f0671b6eea029376fe01'
				],
				[
					'name' => self::UPDATE_SAME_PROXY,
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'same_proxy_identity',
					'tls_psk' => 'a1b9f8aec63372203063379e5a222adc1970f3a1502a4905d72ead8f607041ab'
				],
				[
					'name' => 'Existing proxy with PSK',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'existing_proxy_identity',
					'tls_psk' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
				]
			]
		);
	}

	public function prepareAutoregistrationData() {
		CDataHelper::call('autoregistration.update',
			[
				'tls_accept' => 3, // Allow both unencrypted and TLS with PSK connections.
				'tls_psk_identity' => 'autoregistration_identity',
				'tls_psk' => 'c1be5e2fc488b0934f8f44be69fac48da9037087ea05d7fac05a702e3370370f'
			]
		);
	}

	public static function clearAutoregistrationData() {
		CDataHelper::call('autoregistration.update',
			[
				'tls_accept' => 1 // Allow only unencrypted connections.
			]
		);
	}

	/**
	 * Data provider for creating new and updating existing Autoregistarion PSK configuration.
	 */
	public static function getAutoregistrationData() {
		return [
			// Same identity as host, different PSK.
			'Host idendity, new PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_host_identity',
						'PSK' => '125d996afb640473665c0a22c0f2746fac7f45f9d78df0d222ca5b2ffa1e93a7'
					],
					'db_query' => 'SELECT * FROM config_autoreg_tls'
				]
			],
			// Same identity as host, same PSK.
			'Host idendity, host PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			],
			// Different identity as host, same PSK.
			'New idendity, host PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'new_changed_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			],
			// Same identity as proxy, different PSK.
			'Proxy idendity, new PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_proxy_identity',
						'PSK' => 'f1b834e8dc4e1ebc1c16f6d9507e8f72c494a6c59fc5acbd251d906b0822cf45'
					],
					'db_query' => 'SELECT * FROM config_autoreg_tls'
				]
			],
			// Same identity as proxy, same PSK.
			'Proxy idendity, proxy PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_proxy_identity',
						'PSK' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
					]
				]
			],
			// New proxy identity, same PSK.
			'New idendity, proxy PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'new_changed_proxy_identity',
						'PSK' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
					]
				]
			],
			// Unique identity and PSK.
			'New idendity, new PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'unique_autoregistration_identity',
						'PSK' => '53133e56aa72a3b1f1d0d9fa6ecc0a39d5b6d50f73e1806a586a4d2e1d323fb5'
					]
				]
			]
		];
	}

	/**
	 * Test for creating new Autoregistration PSK confing. Configuration resets after every data case.
	 *
	 * @dataProvider getAutoregistrationData
	 *
	 * @onAfter clearAutoregistrationData
	 */
	public function testPSKEncryption_CreateAutoregistration($data) {
		$this->checkEncryption($data);
	}

	public static function getUpdateSameObjectData() {
		return [
			'Same utoregistration identity, new PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'autoregistration_identity',
						'PSK' => '520a493eeb6aec90fdad504c53c1a1da2121fc09fc993c2bda7d9dcb8ab12de8'
					]
				]
			],
			'New identity, old autoregistration PSK' => [
				[
					'object' => 'configuration',
					'url' => 'zabbix.php?action=autoreg.edit',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'new_autoregistration_identity',
						'PSK' => '520a493eeb6aec90fdad504c53c1a1da2121fc09fc993c2bda7d9dcb8ab12de8'
					]
				]
			],
			'Same host identity, new PSK' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'same_host_identity',
						'PSK' => '58d9efcfdb41a6b69fc8804f5d78a6bda84afd6aeecf9055ef3167e780edd002'
					]
				]
			],
			'New identity, same host PSK' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'new_host_identity',
						'PSK' => '58d9efcfdb41a6b69fc8804f5d78a6bda84afd6aeecf9055ef3167e780edd002'
					]
				]
			],
			'Same proxy identity, new PSK' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with the same host identity and PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'same_proxy_identity',
						'PSK' => '4ad8fbe21255942603baab3533b033952a1244aa7ec1c6bb137987a832e73e5e'
					]
				]
			],
			'New identity, same proxy PSK' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with the same host identity and PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'new_proxy_identity',
						'PSK' => '4ad8fbe21255942603baab3533b033952a1244aa7ec1c6bb137987a832e73e5e'
					]
				]
			]
		];
	}

	/**
	 * Function for testing encryption update in Autoregistration, Hosts and Proxies.
	 *
	 * @dataProvider getUpdateSameObjectData
	 *
	 * @onBeforeOnce prepareAutoregistrationData
	 */
	public function testPSKEncryption_UpdateSameObject($data) {
		$this->checkEncryption($data, true, true);
	}

	public static function getHostProxyData() {
		return [
			'Host: Identity as on other host but different PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with same other host identity and different PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_host_identity',
						'PSK' => '4bb1c1e78832eab6b2f0b4da155705bbbe6fd761ac3b01e88169910ce57348a1'
					],
					'db_query' => 'SELECT * FROM hosts',
					'message_parameter' => '/1/tls_psk'
				]
			],
			'Host: Identity and PSK same as on other host' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with same host identity and PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'Connections to host' => 'PSK',
						'PSK identity' => 'existing_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			],
			'Host: PSK as on other host but different identity' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with different host identity and same PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'Connections to host' => 'PSK',
						'PSK identity' => 'changed_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			],
			'Host: Identity as on proxy but different PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with same proxy identity and different PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_proxy_identity',
						'PSK' => '457dd99d4f11bbcf4a48dd318a509b8a0dadbd254c925ed5e7122203470b7e07'
					],
					'db_query' => 'SELECT * FROM hosts',
					'message_parameter' => '/1/tls_psk'
				]
			],
			'Host: Identity and PSK as on proxy' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with same proxy identity and same PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_proxy_identity',
						'PSK' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
					]
				]
			],
			'Host: PSK as on proxy but different identity' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with different proxy identity and same PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'changed_proxy_identity',
						'PSK' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
					]
				]
			],
			'Host: Identity as in autoregistration but different PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with same autoregistration identity and different PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'Connections to host' => 'PSK',
						'PSK identity' => 'autoregistration_identity',
						'PSK' => 'a45d7f0a6f06f4bd2bea4e4d96b164729316d77dc1a7c787636a2b17010210f8'
					],
					'db_query' => 'SELECT * FROM hosts',
					'message_parameter' => '/1/tls_psk'
				]
			],
			'Host: Identity and PSK as in autoregistration config' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with same autoregistration identity and same PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'autoregistration_identity',
						'PSK' => 'c1be5e2fc488b0934f8f44be69fac48da9037087ea05d7fac05a702e3370370f'
					]
				]
			],
			'Host: PSK as in autoregistration but different identity' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with different autoregistration identity and same PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'changed_autoregistration_identity',
						'PSK' => 'c1be5e2fc488b0934f8f44be69fac48da9037087ea05d7fac05a702e3370370f'
					]
				]
			],
			'Host: New unique identity and PSK' => [
				[
					'object' => 'host',
					'url' => 'zabbix.php?action=host.list',
					'fields' => [
						'Host name' => 'Host with unique identity and PSK',
						'Host groups' => 'Zabbix servers'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'unique_host_identity',
						'PSK' => 'e5a528253adb45b4cabb46e87618e1621cce2a029758e206213345efea1a52a0'
					]
				]
			],
			'Proxy: dentity as on host but different PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with same host identity and different PSK',
						'Proxy mode' => 'Active'
					],
					'psk_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'existing_host_identity',
						'PSK' => '4bb1c1e78832eab6b2f0b4da155705bbbe6fd761ac3b01e88169910ce57348a1'
					],
					'db_query' => 'SELECT * FROM proxy',
					'message_parameter' => '/1/tls_psk'
				]
			],
			'Proxy: PSK and identity as on host' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with same host identity and same PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'existing_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			],
			'Proxy: PSK as on host but different identity' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with different host identity and same PSK',
						'Proxy mode' => 'Active'
					],
					'psk_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'changed_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			],
			'Proxy: identity as on other proxy but different PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with same other proxy identity and different PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'existing_proxy_identity',
						'PSK' => '457dd99d4f11bbcf4a48dd318a509b8a0dadbd254c925ed5e7122203470b7e07'
					],
					'db_query' => 'SELECT * FROM proxy',
					'message_parameter' => '/1/tls_psk'
				]
			],
			'Proxy: PSK and identity as on other proxy' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with same other proxy identity and same PSK',
						'Proxy mode' => 'Active'
					],
					'psk_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'existing_proxy_identity',
						'PSK' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
					]
				]
			],
			'Proxy: PSK as on other proxy but different identity' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with different identity and same PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'changed_proxy_identity',
						'PSK' => 'ce1885032dc2808e4ddf462ef60f1672beccb2d0068759921c9b17d034c8478e'
					]
				]
			],
			'Proxy: identity as in autoregistration but different PSK' => [
				[
					'expected' => TEST_BAD,
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with same autoregistration identity and different PSK',
						'Proxy mode' => 'Active'
					],
					'psk_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'autoregistration_identity',
						'PSK' => 'a45d7f0a6f06f4bd2bea4e4d96b164729316d77dc1a7c787636a2b17010210f8'
					],
					'db_query' => 'SELECT * FROM proxy',
					'message_parameter' => '/1/tls_psk'
				]
			],
			'Proxy: identity and PSK as in autoregistration config' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with same autoregistration identity and same PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'autoregistration_identity',
						'PSK' => 'c1be5e2fc488b0934f8f44be69fac48da9037087ea05d7fac05a702e3370370f'
					]
				]
			],
			'Proxy: PSK as in autoregistration but different identity' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with different autoregistration identity and same PSK',
						'Proxy mode' => 'Active'
					],
					'psk_fields' => [
						'id:tls_accept_psk' => true,
						'PSK identity' => 'changed_autoregistration_identity',
						'PSK' => 'c1be5e2fc488b0934f8f44be69fac48da9037087ea05d7fac05a702e3370370f'
					]
				]
			],
			'Proxy: new unique identity and PSK' => [
				[
					'object' => 'proxy',
					'url' => 'zabbix.php?action=proxy.list',
					'fields' => [
						'Proxy name' => 'Proxy with Unique identity and PSK',
						'Proxy mode' => 'Passive'
					],
					'psk_fields' => [
						'Connections to proxy' => 'PSK',
						'PSK identity' => 'unique_proxy_identity',
						'PSK' => '2a1497ce3748b7cbb311c73cc084cac211298820db48127d942c7f3dea555d1c'
					]
				]
			]
		];
	}

	/**
	 * Test function for adding encryption to Hosts or Proxies.
	 *
	 * @dataProvider getHostProxyData
	 *
	 * @onBeforeOnce prepareAutoregistrationData
	 */
	public function testPSKEncryption_CreateHostProxy($data) {
		$this->checkEncryption($data);
	}

	/**
	 * Test function for changing encryption on all objects - Hosts, Proxies, Autoregistariong config.
	 *
	 * @dataProvider getHostProxyData
	 * @dataProvider getAutoregistrationData
	 *
	 * @onBeforeOnce prepareAutoregistrationData
	 */
	public function testPSKEncryption_UpdateAll($data) {
		$this->checkEncryption($data, true);
	}

	/**
	 * Function for testing encryption fields in Autoregistration, Hosts and Proxies.
	 *
	 * @param array   $data      data provider
	 * @param boolean $update    true if encryption is being updated, false - if creating new
	 * @param boolean $same      true if encryption is changed on the same object, false if on other object
	 */
	public function checkEncryption($data, $update = false, $same = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($data['db_query']);
		}

		$this->page->login()->open($data['url'])->waitUntilReady();
		if ($data['object'] !== 'configuration') {
			if ($update) {
				$object = $same
					? ($data['object'] === 'host' ? self::UPDATE_SAME_HOST : self::UPDATE_SAME_PROXY)
					: ($data['object'] === 'host' ? self::HOST_NAME : self::PROXY_NAME);

				$this->query('link', $object)->waitUntilClickable()->one()->click();
			}
			else {
				$this->query('button', 'Create '.$data['object'])->waitUntilClickable()->one()->click();
			}

			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $dialog->asForm();
		}
		else {
			$form = $this->query('id:autoreg-form')->asForm()->waitUntilVisible()->one();
		}

		if (array_key_exists('fields', $data)) {
			if (!$update) {
				$form->fill($data['fields']);
			}

			// Proxy mode influences 'Encryption' tab fields editability.
			if (CTestArrayHelper::get($data['fields'], 'Proxy mode')) {
				$form->fill(['Proxy mode' => $data['fields']['Proxy mode']]);
			}
		}

		if ($data['object'] !== 'configuration') {
			$form->selectTab('Encryption');
			$form->invalidate();
		}

		if ($update) {
			if ($data['object'] === 'proxy') {
				if (array_key_exists('Connections to proxy', $data['psk_fields'])) {
					$form->fill(['Connections to proxy' => $data['psk_fields']['Connections to proxy']]);
				}

				if (CTestArrayHelper::get($data['psk_fields'], 'id:tls_accept_psk')) {
					$form->fill(['id:tls_accept_psk' => $data['psk_fields']['id:tls_accept_psk']]);
				}
			}

			$form->query('button:Change PSK')->waitUntilClickable()->one()->click();
		}

		$form->fill($data['psk_fields']);
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$message = ($data['object'] === 'configuration')
				? 'Cannot update configuration'
				: ('Cannot '.($update ? 'update ' : 'add ').$data['object']);
			$parameter = CTestArrayHelper::get($data, 'message_parameter', '/tls_psk');
			$this->assertMessage(TEST_BAD, $message,
					'Invalid parameter "'.$parameter.'": another tls_psk value is already associated with given tls_psk_identity.'
			);
			$this->assertEquals($old_hash, CDBHelper::getHash($data['db_query']));

			if ($data['object'] !== 'configuration') {
				$dialog->close();
			}
		}
		else {
			if ($data['object'] !== 'configuration') {
				$dialog->waitUntilNotVisible();
				$success_message = (ucfirst($data['object']).($update ? ' updated' : ' added'));
			}
			else {
				$success_message = 'Configuration updated';
			}

			$this->assertMessage(TEST_GOOD, $success_message);
		}
	}

	public static function getMassUpdateData() {
		return [
			// Update two hosts without encryption to existing identity but wrong PSK.
			'No encrypton: host identity, new PSK' => [
				[
					'expected' => TEST_BAD,
					'hosts' => [
						'3 Host for mass update',
						'4 Host for mass update'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_host_identity',
						'PSK' => '6f91263b9129cf70ad9705115ce3d67863fccd5b361b05bed0b699f44731743a'
					]
				]
			],
			// Update two hosts with encryption to existing identity but wrong PSK.
			'With encryption: host identity, new PSK' => [
				[
					'expected' => TEST_BAD,
					'hosts' => [
						'1 Host for mass update',
						'2 Host for mass update'
					],
					'psk_fields' => [
						'Connections to host' => 'PSK',
						'PSK identity' => 'existing_host_identity',
						'PSK' => '6f91263b9129cf70ad9705115ce3d67863fccd5b361b05bed0b699f44731743a'
					]
				]
			],
			// Update two hosts (one with encryption, one - no encryption) to existing identity but wrong PSK.
			'Mixed encryption: host identity, wrong PSK' => [
				[
					'expected' => TEST_BAD,
					'hosts' => [
						'1 Host for mass update',
						'3 Host for mass update'
					],
					'psk_fields' => [
						'Connections to host' => 'PSK',
						'PSK identity' => 'existing_host_identity',
						'PSK' => '6f91263b9129cf70ad9705115ce3d67863fccd5b361b05bed0b699f44731743a'
					]
				]
			],
			// Update two hosts (one with encryption, one - no encryption) to same identity and same PSK.
			'Mixed encryption: host identity, host PSK' => [
				[
					'hosts' => [
						'1 Host for mass update',
						'4 Host for mass update'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'mass_update_identity',
						'PSK' => 'f8f07e658898455778b35108c78ebd7e29dbed87de4a5619447e189dd9300d5e'
					]
				]
			],
			// Update two hosts (one with encryption, one - no encryption) to same identity and new PSK.
			'Mixed encryption: host identity, new PSK' => [
				[
					'hosts' => [
						'5 Host for mass update',
						'6 Host for mass update'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'new_mass_update_identity',
						'PSK' => '8a89ec355beb83d9a4a78db5a9d495139ac70b6bafcc2930b8fed0aee7f13bc4'
					]
				]
			],
			// Update three hosts with encryption to same identity and new PSK.
			'With encryption: host identity, new PSK' => [
				[
					'hosts' => [
						'1 Host for mass update',
						'5 Host for mass update',
						'6 Host for mass update'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'new_mass_update_identity',
						'PSK' => 'd7b3d893fba18fbea076ba5e849ae71d8ae17bef88ccd946cf889ffa2ca7c213'
					]
				]
			],
			// Update two hosts with encryption to "No encryption".
			'With encryption: no encryption' => [
				[
					'hosts' => [
						'1 Host for mass update',
						'4 Host for mass update'
					],
					'psk_fields' => [
						'Connections to host' => 'No encryption',
						'id:tls_in_psk' => false
					]
				]
			],
			// Update two hosts without encryption to new existing host identity and PSK.
			'No encryption: host identity and PSK' => [
				[
					'hosts' => [
						'1 Host for mass update',
						'4 Host for mass update'
					],
					'psk_fields' => [
						'id:tls_in_psk' => true,
						'PSK identity' => 'existing_host_identity',
						'PSK' => '7c2583ef04d25c5a63f8b857d608b90e8fe63e6ddc6492af5d639d5fd8bc0573'
					]
				]
			]
		];
	}

	/**
	 * Test for changing PSK encryption in hosts mass update.
	 *
	 * @dataProvider getMassUpdateData
	 */
	public function testPSKEncryption_MassUpdate($data) {
		$db_query = 'SELECT * FROM hosts';
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($db_query);
		}

		$this->page->login()->open('zabbix.php?action=host.list')->waitUntilReady();
		$table = $this->query('xpath://table[@class="list-table"]')->asTable()->one();
		$table->findRows('Name', $data['hosts'])->select();

		// Open mass update form.
		$this->query('button:Mass update')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$form->selectTab('Encryption');
		$form->invalidate();
		$form->getLabel('Connections')->click();
		$this->query('id:tls_connect')->one()->waitUntilClickable();
		$form->fill($data['psk_fields']);
		$dialog->query('button:Update')->one()->waitUntilClickable()->click();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update hosts',
					'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			);
			$this->assertEquals($old_hash, CDBHelper::getHash($db_query));
			$dialog->close();
		}
		else {
			$dialog->waitUntilNotVisible();
			$this->assertMessage(TEST_GOOD, 'Hosts updated');
		}
	}
}

