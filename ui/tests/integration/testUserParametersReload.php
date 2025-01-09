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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';


/**
 * Test suite for user parameters reload
 *
 * @required-components server, agent, agent2
 * @configurationDataProvider configurationProvider
 * @backup items, history_str, hosts
 * @hosts agentd, agent2
 */
class testUserParametersReload extends CIntegrationTest {

	const ITEM_NAME_01		= 'usrprm01';
	const ITEM_NAME_02		= 'usrprm02';
	const ITEM_NAME_03		= 'usrprm03';

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
			'key' => self::ITEM_NAME_03,
			'component' => self::COMPONENT_AGENT
		],
		[
			'key' => self::ITEM_NAME_01,
			'component' => self::COMPONENT_AGENT2
		],
		[
			'key' => self::ITEM_NAME_02,
			'component' => self::COMPONENT_AGENT2
		],
		[
			'key' => self::ITEM_NAME_03,
			'component' => self::COMPONENT_AGENT2
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "agentd" and "agent2".
		$hosts = [];
		foreach ([self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX, self::COMPONENT_AGENT2 => self::AGENT2_PORT_SUFFIX] as $component => $port) {
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
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::COMPONENT_AGENT
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'Plugins.Uptime.Capacity' => '10'
			]
		];
	}

	/**
	 * Check reloaded user parameters
	 */
	public function testUserParametersReload() {

		// Test single user parameter: usrprm01

		$config = [
			self::COMPONENT_AGENT => [
				'UserParameter' => self::ITEM_NAME_01.',echo singleParam.01'
			],
			self::COMPONENT_AGENT2 => [
				'UserParameter' => self::ITEM_NAME_01.',echo singleParam.01'
			]
		];

		$this->executeUserParamReload($config);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NORMAL, 'singleParam.01');
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NOTSUPPORTED);
		}

		// Test multiple user parameters: usrprm01 and usrprm02

		// Currently multiple identical configuration parameters are not allowed, so use include instead
		$config = [
			self::COMPONENT_AGENT => [
				'UserParameter' => self::ITEM_NAME_01.',echo multipleParams.01',
				'Include' => PHPUNIT_CONFIG_DIR.self::COMPONENT_AGENT.'_usrprm.conf'
			],
			self::COMPONENT_AGENT2 => [
				'UserParameter' => self::ITEM_NAME_01.',echo multipleParams.01',
				'Include' => PHPUNIT_CONFIG_DIR.self::COMPONENT_AGENT2.'_usrprm.conf'
			]
		];

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			if (file_put_contents(PHPUNIT_CONFIG_DIR.'/'.$component.'_usrprm.conf',
					'UserParameter='.self::ITEM_NAME_02.',echo multipleParams.02') === false) {
				throw new Exception('Failed to create include configuration file');
			}
		}

		$this->executeUserParamReload($config);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NORMAL, 'multipleParams.01');
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NORMAL, 'multipleParams.02');
			$this->assertTrue(@unlink(PHPUNIT_CONFIG_DIR.'/'.$component.'_usrprm.conf'));
		}

		// Test user parameters with multiple comas

		$this->executeUserParamReload();

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NOTSUPPORTED);
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NOTSUPPORTED);
		}

		$config = [
			self::COMPONENT_AGENT => [
				'UserParameter' => self::ITEM_NAME_01.',echo A, echo B, echo C'
			],
			self::COMPONENT_AGENT2 => [
				'UserParameter' => self::ITEM_NAME_01.',echo A, echo B, echo C'
			]
		];

		$this->executeUserParamReload($config);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NORMAL, 'A, echo B, echo C');
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NOTSUPPORTED);
		}

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			if (file_put_contents(PHPUNIT_CONFIG_DIR.'/'.$component.'_usrprm.conf',
					'UserParameter='.self::ITEM_NAME_02.',echo multipleParams.02') === false) {
				throw new Exception('Failed to create include configuration file');
			}

			if (file_put_contents(PHPUNIT_CONFIG_DIR.'/'.$component.'_usrprm.conf',
					'UserParameter='.self::ITEM_NAME_03.',echo multipleParams.03', FILE_APPEND | LOCK_EX) === false) {
				throw new Exception('Failed to create include configuration file');
			}
		}

		$this->executeUserParamReload($config);

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->checkItemState($component.':'.self::ITEM_NAME_01, ITEM_STATE_NORMAL, 'A, echo B, echo C');
			$this->checkItemState($component.':'.self::ITEM_NAME_02, ITEM_STATE_NOTSUPPORTED);
			$this->checkItemState($component.':'.self::ITEM_NAME_03, ITEM_STATE_NOTSUPPORTED);
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
			} else {
				$config = $def_config;
			}
			self::prepareComponentConfiguration($component, $config);
			self::reloadUserParameters($component);
		}
	}

	/**
	 * Check item state.
	 *
	 * @param string $name
	 * @param int    $state
	 * @param string $lastvalue
	 */
	public function checkItemState(string $name, int $state, string $lastvalue = null) {
		$wait_iterations = 20;
		$wait_iteration_delay = 1;

		for ($r = 0; $r < $wait_iterations; $r++) {
			$item = $this->call('item.get', [
				'output' => ['state', 'lastvalue'],
				'itemids' => self::$itemids[$name]
			])['result'][0];

			if ($item['state'] == $state && ($state == ITEM_STATE_NOTSUPPORTED || $lastvalue === $item['lastvalue'])) {
				break;
			}

			sleep($wait_iteration_delay);
		}

		$this->assertEquals($state, $item['state'], 'User parameter failed to reload, item name: '.$name);
		if ($state == ITEM_STATE_NORMAL) {
			$this->assertSame($lastvalue, $item['lastvalue'], 'User parameter failed to reload, item name: '.$name);
		}
	}
}
