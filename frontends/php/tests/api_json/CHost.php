<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	public static function host_names() {
		return array(
			array('Test host', true),
			array('Fake host', false),
		);
	}

	public static function dup_template_ids() {
		return array(
			array(
				array(
					'host' => 'Host to test dup ids 1',
					'name' => 'Host visible to test dup ids 1',
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'ip' => '192.168.3.1',
							'dns' => '',
							'port' => 567,
							'main' => 1
						)
					),
					'groups' => array(
						array('groupid' => 5)		/* Discovered hosts */
					),
					'templates' => array(
						array('templateid' => 10047),	/* Template App Zabbix Server */
						array('templateid' => 10050),	/* Template App Zabbix Agent */
						array('templateid' => 10093),	/* Template App FTP Service */
						array('templateid' => 10094),	/* Template App HTTP Service */
						array('templateid' => 10095),	/* Template App HTTPS Service */
						array('templateid' => 10096),	/* Template App IMAP Service */
						array('templateid' => 10097),	/* Template App LDAP Service */
						array('templateid' => 10098),	/* Template App NNTP Service */
						array('templateid' => 10099),	/* Template App NTP Service */
						array('templateid' => 10100),	/* Template App POP Service */
						array('templateid' => 10101),	/* Template App SMTP Service */
						array('templateid' => 10102),	/* Template App SSH Service */
						array('templateid' => 10103)	/* Template App Telnet Service */
					)
				),
				true
			),
			array(
				array(
					'host' => 'Host to test dup ids 2',
					'name' => 'Host visible to test dup ids 2',
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'ip' => '192.168.3.1',
							'dns' => '',
							'port' => 567,
							'main' => 1
						)
					),
					'groups' => array(
						array('groupid' => 5)		/* Discovered hosts */
					),
					'templates' => array(
						array('templateid' => 10050),	/* Template App Zabbix Agent */
						array('templateid' => 10050)	/* Template App Zabbix Agent */
					)
				),
				false
			),
			array(
				array(
					'host' => 'Host to test dup ids 3',
					'name' => 'Host visible to test dup ids 3',
					'interfaces' => array(
						array(
							'type' => INTERFACE_TYPE_AGENT,
							'useip' => INTERFACE_USE_IP,
							'ip' => '192.168.3.1',
							'dns' => '',
							'port' => 567,
							'main' => 1
						)
					),
					'groups' => array(
						array('groupid' => 5)		/* Discovered hosts */
					),
					'templates' => array(
						array('templateid' => 10047),	/* Template App Zabbix Server */
						array('templateid' => 10050),	/* Template App Zabbix Agent */
						array('templateid' => 10050),	/* Template App Zabbix Agent */
						array('templateid' => 10093),	/* Template App FTP Service */
						array('templateid' => 10094),	/* Template App HTTP Service */
						array('templateid' => 10095),	/* Template App HTTPS Service */
						array('templateid' => 10096),	/* Template App IMAP Service */
						array('templateid' => 10097),	/* Template App LDAP Service */
						array('templateid' => 10098),	/* Template App NNTP Service */
						array('templateid' => 10099),	/* Template App NTP Service */
						array('templateid' => 10100),	/* Template App POP Service */
						array('templateid' => 10101),	/* Template App SMTP Service */
						array('templateid' => 10102),	/* Template App SSH Service */
						array('templateid' => 10103)	/* Template App Telnet Service */
					)
				),
				false
			),
		);
	}

	/**
	* @dataProvider host_names
	*/
	public function testCHost_exists($name, $exists) {
		$debug = null;

		$result = $this->api_acall(
			'host.exists',
			array('host' => $name),
			$debug
		);

		$this->assertTrue(!array_key_exists('error', $result), "Chuck Norris: Exists method returned an error. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true));

		$this->assertFalse(
			($result['result'] != $exists),
			"Chuck Norris: Exists method returned wrong result. Result is: ".print_r($result, true)."\nDebug: ".print_r($debug, true)
		);
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
		return array(
			array(
				// request
				array(
					'withInventory' => true,
					'selectInventory' => array('type'),
					'hostids' => 10053
				),
				// expected result
				array(
					'hostid' => 10053,
					'type' => 'Type'
				)
			),
			array(
				// request
				array(
					'withInventory' => true,
					'selectInventory' => array('os', 'tag'),
					'hostids' => 10053
				),
				// expected result
				array(
					'hostid' => 10053,
					'os' => 'OS',
					'tag' => 'Tag'
				)
			),
			array(
				// request
				array(
					'withInventory' => true,
					'selectInventory' => array('blabla'), // non existent field
					'hostids' => 10053
				),
				// expected result
				array(
					'hostid' => 10053
				)
			)
		);
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
