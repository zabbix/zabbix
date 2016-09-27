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

class API_JSON_Host extends CZabbixTest {

	public static function dup_template_ids() {
		return [
			[
				[
					'host' => 'Host to test dup ids 1',
					'name' => 'Host visible to test dup ids 1',
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'ip' => '192.168.3.1',
							'dns' => '',
							'port' => 567,
							'main' => 1
						]
					],
					'groups' => [
						['groupid' => 5]		/* Discovered hosts */
					],
					'templates' => [
						['templateid' => 10047],	/* Template App Zabbix Server */
						['templateid' => 10050],	/* Template App Zabbix Agent */
						['templateid' => 10093],	/* Template App FTP Service */
						['templateid' => 10094],	/* Template App HTTP Service */
						['templateid' => 10095],	/* Template App HTTPS Service */
						['templateid' => 10096],	/* Template App IMAP Service */
						['templateid' => 10097],	/* Template App LDAP Service */
						['templateid' => 10098],	/* Template App NNTP Service */
						['templateid' => 10099],	/* Template App NTP Service */
						['templateid' => 10100],	/* Template App POP Service */
						['templateid' => 10101],	/* Template App SMTP Service */
						['templateid' => 10102],	/* Template App SSH Service */
						['templateid' => 10103]	/* Template App Telnet Service */
					]
				],
				true
			],
			[
				[
					'host' => 'Host to test dup ids 2',
					'name' => 'Host visible to test dup ids 2',
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'ip' => '192.168.3.1',
							'dns' => '',
							'port' => 567,
							'main' => 1
						]
					],
					'groups' => [
						['groupid' => 5]		/* Discovered hosts */
					],
					'templates' => [
						['templateid' => 10050],	/* Template App Zabbix Agent */
						['templateid' => 10050]	/* Template App Zabbix Agent */
					]
				],
				false
			],
			[
				[
					'host' => 'Host to test dup ids 3',
					'name' => 'Host visible to test dup ids 3',
					'interfaces' => [
						[
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'ip' => '192.168.3.1',
							'dns' => '',
							'port' => 567,
							'main' => 1
						]
					],
					'groups' => [
						['groupid' => 5]		/* Discovered hosts */
					],
					'templates' => [
						['templateid' => 10047],	/* Template App Zabbix Server */
						['templateid' => 10050],	/* Template App Zabbix Agent */
						['templateid' => 10050],	/* Template App Zabbix Agent */
						['templateid' => 10093],	/* Template App FTP Service */
						['templateid' => 10094],	/* Template App HTTP Service */
						['templateid' => 10095],	/* Template App HTTPS Service */
						['templateid' => 10096],	/* Template App IMAP Service */
						['templateid' => 10097],	/* Template App LDAP Service */
						['templateid' => 10098],	/* Template App NNTP Service */
						['templateid' => 10099],	/* Template App NTP Service */
						['templateid' => 10100],	/* Template App POP Service */
						['templateid' => 10101],	/* Template App SMTP Service */
						['templateid' => 10102],	/* Template App SSH Service */
						['templateid' => 10103]	/* Template App Telnet Service */
					]
				],
				false
			],
		];
	}

	/**
	* @dataProvider dup_template_ids
	*/
	public function testCHostDuplicateTemplateIds($request, $successExpected) {
		$debug = null;

		$result = $this->api_acall(
			'host.create',
			$request,
			$debug
		);

		if ($successExpected) {
			$this->assertTrue(
				!array_key_exists('error', $result) || strpos($result['error']['data'], 'Cannot pass duplicate template') === false,
				"Chuck Norris: I was expecting that host.create would not complain on duplicate IDs. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true)
			);
		}
		else {
			$this->assertTrue(
				array_key_exists('error', $result) && strpos($result['error']['data'], 'Cannot pass duplicate template') !== false,
				"Chuck Norris: I was expecting that host.create would complain on duplicate IDs. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true)
			);
		}
	}

	public static function inventoryGetRequests() {
		return [
			[
				// request
				[
					'withInventory' => true,
					'selectInventory' => ['type'],
					'hostids' => 10053
				],
				// expected result
				[
					'hostid' => 10053,
					'type' => 'Type'
				]
			],
			[
				// request
				[
					'withInventory' => true,
					'selectInventory' => ['os', 'tag'],
					'hostids' => 10053
				],
				// expected result
				[
					'hostid' => 10053,
					'os' => 'OS',
					'tag' => 'Tag'
				]
			],
			[
				// request
				[
					'withInventory' => true,
					'selectInventory' => ['blabla'], // non existent field
					'hostids' => 10053
				],
				// expected result
				[
					'hostid' => 10053
				]
			]
		];
	}

	/**
	 * @dataProvider inventoryGetRequests
	 */
	public function testCHostGetInventories($request, $expectedResult) {
		$debug = null;

		$result = $this->api_acall(
			'host.get',
			$request,
			$debug
		);

		$this->assertFalse(
			!isset($result['result'][0]['inventory']) || $result['result'][0]['inventory'] != $expectedResult,
			"Chuck Norris: I was expecting that host.get would return this result in 'inventories' element: ".print_r($expectedResult, true).', but it returned: '.print_r($result, true)." \nDebug: ".print_r($debug, true)
		);
	}
}
