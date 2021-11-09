<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';


/**
 * Test suite for user parameters reload
 *
 * @required-components server, agent, agent2
 * @configurationDataProvider serverConfigurationProvider, agentConfigurationProvider
 * @backup items, history_str
 * @hosts agentd, agent2
 */
class testUserParametersReload extends CIntegrationTest {

	const USER_PARAM_RELOAD_DELAY 	= 10;

	const ITEM_NAME_01		= 'usrprm01';
	const ITEM_NAME_02		= 'usrprm02';

	private static $hostids = [];
	private static $itemids = [];

	// List of items to check.
	private static $items = [
		[
			'key' => self::ITEM_NAME_01,
			'component' => self::COMPONENT_AGENT
		],
		[
			'key' => self::ITEM_NAME_02,
			'component' => self::COMPONENT_AGENT
		],
		[
			'key' => self::ITEM_NAME_01,
			'component' => self::COMPONENT_AGENT2
		],
		[
			'key' => self::ITEM_NAME_02,
			'component' => self::COMPONENT_AGENT2
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "agentd" and "agent2".
		$hosts = [];
		foreach ([self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX, self::COMPONENT_AGENT2 => 53] as $component => $port) {
			$hosts[] = [
				'host' => $component,
				'interfaces' => [
					[
						'type' => 1,
						'main' => 1,
						'useip' => 1,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => PHPUNIT_PORT_PREFIX.$port
					]
				],
				'groups' => [
					[
						'groupid' => 4
					]
				],
				'status' => HOST_STATUS_NOT_MONITORED
			];
		}

		$response = $this->call('host.create', $hosts);
		$this->assertArrayHasKey('hostids', $response['result']);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $i => $name) {
			$this->assertArrayHasKey($i, $response['result']['hostids']);
			self::$hostids[$name] = $response['result']['hostids'][$i];
		}

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => array_values(self::$hostids),
			'selectInterfaces' => ['interfaceid']
		]);

		$interfaceids = [];
		foreach ($response['result'] as $host) {
			$interfaceids[$host['host']] = $host['interfaces'][0]['interfaceid'];
		}

		// Create items.
		$items = [];
		foreach (self::$items as $item) {
			$data = [
				'name' => $item['key'],
				'key_' => $item['key'],
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_STR,
				'delay' => '1s'
			];

			$items[] = array_merge($data, [
				'hostid' => self::$hostids[$item['component']],
				'interfaceid' => $interfaceids[$item['component']]
			]);
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));

		// Get item IDs
		$itemids = $response['result']['itemids'];
		foreach (self::$items as $i => $value) {
			$name = $value['key'];
			self::$itemids[$value['component'].':'.$name] = $itemids[$i];
		}

		return true;
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'UnreachablePeriod' => 25,
				'UnavailableDelay' => 15,
				'UnreachableDelay' => 5
			]
		];
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::COMPONENT_AGENT,
				'UserParameter' => self::ITEM_NAME_01.',echo 01'
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'ListenPort' => PHPUNIT_PORT_PREFIX.'53',
				'Plugins.Uptime.Capacity' => '10',
				'UserParameter' => self::ITEM_NAME_01.',echo 01'
			]
		];
	}

	/**
	 * Check reloaded user parameters (usrprm01 only)
	 */
	public function testUserParametersReload_singleParam() {
		$config = [
			self::COMPONENT_AGENT => [
				'UserParameter' => self::ITEM_NAME_01.',echo 01'
			],
			self::COMPONENT_AGENT2 => [
				'UserParameter' => self::ITEM_NAME_01.',echo 01'
			]
		];

		$this->executeUserParamReload($config);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NORMAL);
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NOTSUPPORTED);
		}
	}

	/**
	 * Check reloaded user parameters (usrprm01 and usrprm01)
	 */
	public function testUserParametersReload_multipleParams() {
		// Currently multiple identical configuration parameters are not allowed, so use include instead
		$config = [
			self::COMPONENT_AGENT => [
				'UserParameter' => self::ITEM_NAME_01.',echo 01',
				'Include' => PHPUNIT_CONFIG_DIR.self::COMPONENT_AGENT.'_usrprm.conf'
			],
			self::COMPONENT_AGENT2 => [
				'UserParameter' => self::ITEM_NAME_01.',echo 01',
				'Include' => PHPUNIT_CONFIG_DIR.self::COMPONENT_AGENT2.'_usrprm.conf'
			]
		];

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			if (file_put_contents(PHPUNIT_CONFIG_DIR.'/'.$component.'_usrprm.conf', 'UserParameter='.self::ITEM_NAME_02.',echo 02') === false) {
				throw new Exception('Failed to create include configuration file');
			}
		}

		$this->executeUserParamReload($config);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NORMAL);
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NORMAL);
			$this->assertTrue(@unlink(PHPUNIT_CONFIG_DIR.'/'.$component.'_usrprm.conf'));
		}
	}

	/**
	 * Check reloaded user parameters (no user parameters)
	 */
	public function testUserParametersReload_noParams() {
		$this->executeUserParamReload();

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NOTSUPPORTED);
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NOTSUPPORTED);
		}
	}

	/**
	 * Update configuration file and reload user parameters.
	 *
	 * @param array $config		user parameters
	 */
	public function executeUserParamReload($config = null) {
		$def_config = self::getDefaultComponentConfiguration();

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			if ($config !== null) {
				$config[$component] = array_merge($config[$component], $def_config[$component]);
			}
			else {
				$config = $def_config;
			}
			$this->prepareComponentConfiguration($component, $config);
			$this->reloadUserParameters($component);
		}

		sleep(self::USER_PARAM_RELOAD_DELAY);
	}

	/**
	 * Reload user parameters.
	 *
	 * @static
	 *
	 * @param string $component	component
	 */
	public static function reloadUserParameters($component) {
		$return = null;
		$output = null;
		$suffix = ' -R userparameter_reload > /dev/null 2>&1';

		exec(PHPUNIT_BINARY_DIR.'zabbix_'.$component.$suffix, $output, $return);

		if ($return !== 0) {
			throw new Exception('Failed to reload user parameters');
		}

		return $output;
	}

	/**
	 * Check item state.
	 *
	 * @param string $name		item name
	 * @param integer $state	item state
	 */
	public function checkItemState($name, $state) {
		$response = $this->call('item.get', [
			'itemids' => self::$itemids[$name],
			'output' => ['state']
		]);

		$this->assertEquals($state, $response['result'][0]['state'], 'User parameter failed to reload, item name: '.$name);
	}
}
