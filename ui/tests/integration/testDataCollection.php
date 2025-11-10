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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * Test suite for data collection using both active and passive agents.
 *
 * @backup history, hosts, host_rtdata, proxy, proxy_rtdata, changelog, settings, config_autoreg_tls, expressions
 * @backup globalmacro, hosts, interface, item_rtdata, items, proxy_history, regexps, ha_node
 */
class testDataCollection extends CIntegrationTest {

	private static $hostids = [];
	private static $itemids = [];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$this->generateCertificates();

		// Create proxy "proxy".
		CDataHelper::call('proxy.create', [
			'name' => 'proxy',
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
		]);

		$proxyids = CDataHelper::getIds('name');

		// Create host "agent", "custom_agent" and "proxy agent".
		$interfaces = [
			'type' => 1,
			'main' => 1,
			'useip' => 1,
			'ip' => '127.0.0.1',
			'dns' => '',
			'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
		];

		$groups = ['groupid' => 4];

		$result = CDataHelper::createHosts([
			[
				'host' => 'agent',
				'interfaces' => $interfaces,
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED,
				'items' => [
					[
						'name' => 'Agent ping',
						'key_' => 'agent.ping',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1s'
					],
					[
						'name' => 'Agent hostname',
						'key_' => 'agent.hostname',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '1s'
					]
				]
			],
			[
				'host' => 'custom_agent',
				'interfaces' => $interfaces,
				'groups' => $groups,
				'status' => HOST_STATUS_NOT_MONITORED,
				'items' => [
					[
						'name' => 'Custom metric 1',
						'key_' => 'custom.metric',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '5s'
					],
					[
						'name' => 'Custom metric 2',
						'key_' => 'custom.metric[custom]',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '10s'
					]
				]
			],
			[
				'host' => 'proxy_agent',
				'interfaces' => $interfaces,
				'groups' => $groups,
				'proxyid' => $proxyids['proxy'],
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'status' => HOST_STATUS_NOT_MONITORED,
				'items' => [
					[
						'name' => 'Agent ping',
						'key_' => 'agent.ping',
						'type' => ITEM_TYPE_ZABBIX_ACTIVE,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => '1s'
					],
					[
						'name' => 'Agent hostname',
						'key_' => 'agent.hostname',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '1s'
					]
				]
			]
		]);

		self::$hostids = $result['hostids'];
		self::$itemids = $result['itemids'];

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'UnreachablePeriod' => 5,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1,
				'DebugLevel' => 4
			],
			self::COMPONENT_AGENT => [
				'Hostname' => 'agent',
				'ServerActive' => '127.0.0.1'
			]
		];
	}

	/**
	 * Test if server will disable agent checks if agent is not accessible.
	 *
	 * @required-components server
	 * @configurationDataProvider agentConfigurationProvider
	 * @hosts agent
	 */
	public function testDataCollection_checkHostAvailability() {
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'temporarily disabling Zabbix agent checks on host "agent": interface unavailable'
		);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'update interface set');
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'commit;');

		$this->reloadConfigurationCache();
		sleep(5);

		$data = $this->call('hostinterface.get', [
			'output' => ['available'],
			'hostids' => self::$hostids['agent'],
			'filter' => [
				'type' => 1,
				'main' => 1
			]
		]);

		$this->assertTrue(is_array($data['result']));
		$this->assertEquals(1, count($data['result']));
		$this->assertEquals(INTERFACE_AVAILABLE_FALSE, $data['result'][0]['available']);
	}

	/**
	 * Test if both active and passive agent checks are processed.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider agentConfigurationProvider
	 * @hosts agent
	 */
	public function testDataCollection_checkAgentData() {
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, [
			'enabling Zabbix agent checks on host "agent": interface became available',
			'resuming Zabbix agent checks on host "agent": connection restored'
		]);

		$passive_data = $this->call('history.get', [
			'itemids'	=> self::$itemids['agent:agent.ping'],
			'history'	=> ITEM_VALUE_TYPE_UINT64
		]);

		foreach ($passive_data['result'] as $item) {
			$this->assertEquals(1, $item['value']);
		}

		// Retrieve history data from API as soon it is available.
		$active_data = $this->callUntilDataIsPresent('history.get', [
			'itemids'	=> self::$itemids['agent:agent.hostname'],
			'history'	=> ITEM_VALUE_TYPE_TEXT
		]);

		foreach ($active_data['result'] as $item) {
			$this->assertEquals('agent', $item['value']);
		}
	}

	/**
	 * Test if custom active checks are processed.
	 *
	 * @required-components server
	 * @hosts custom_agent
	 */
	public function testDataCollection_checkCustomActiveChecks() {
		$host = 'custom_agent';
		$items = [];

		// Retrieve item data from API.
		$response = $this->call('item.get', [
			'hostids'	=> self::$hostids['custom_agent'],
			'output'	=> ['itemid', 'name', 'key_', 'type', 'value_type']
		]);

		foreach ($response['result'] as $item) {
			if ($item['type'] != ITEM_TYPE_ZABBIX_ACTIVE) {
				continue;
			}

			$items[$item['key_']] = $item;
		}

		$values = [];
		$clock = time() - 1;

		$checks = $this->getActiveAgentChecks($host);
		foreach ($checks as $i => $check) {
			$matches = null;
			$value = (preg_match('/^.*\[(.*)\]$/', $check['key'], $matches) === 1) ? $matches[1] : microtime();

			$this->assertArrayHasKey($check['key'], $items);

			$values[$items[$check['key']]['itemid']] = [
				'host' => $host,
				'key' => $check['key'],
				'value' => $value,
				'clock' => $clock,
				'ns' => $i
			];

			unset($items[$check['key']]);
		}

		$this->assertEmpty($items);
		$this->sendAgentValues(array_values($values));

		// Retrieve history data from API as soon it is available.
		$data = $this->callUntilDataIsPresent('history.get', [
			'itemids'	=> array_keys($values),
			'history'	=> ITEM_VALUE_TYPE_TEXT
		]);

		foreach ($data['result'] as $item) {
			$value = $values[$item['itemid']];

			foreach (['value', 'clock', 'ns'] as $field) {
				$this->assertEquals($value[$field], $item[$field]);
			}
		}
	}

	/**
	 * Generate CA, server and agent certificates for TLS testing.
	 */
	private function generateCertificates() {

		$baseDir = '/tmp/zabbix_cert/';
		if (!is_dir($baseDir)) {
			mkdir($baseDir, 0777, true);
		}

		$baseDir = '/tmp/zabbix_cert/';
		$caKey = $baseDir . 'zabbix_ca_file.key';
		$caCert = $baseDir . 'zabbix_ca_file.crt';
		$serverKey = $baseDir . 'zabbix_server.key';
		$serverCsr = $baseDir . 'zabbix_server.csr';
		$serverCert = $baseDir . 'zabbix_server.crt';
		$agentKey = $baseDir . 'zabbix_agentd.key';
		$agentCsr = $baseDir . 'zabbix_agentd.csr';
		$agentCert = $baseDir . 'zabbix_agentd.crt';
		$proxyKey = $baseDir . 'zabbix_proxy.key';
		$proxyCsr = $baseDir . 'zabbix_proxy.csr';
		$proxyCert = $baseDir . 'zabbix_proxy.crt';

		// CA
		shell_exec("openssl genrsa -out $caKey 4096");
		shell_exec("openssl req -x509 -new -nodes -key $caKey -sha256 -days 3650 -out $caCert -subj '/CN=ZabbixCA'");

		// Server
		shell_exec("openssl genrsa -out $serverKey 2048");
		shell_exec("openssl req -new -key $serverKey -out $serverCsr -subj '/CN=zabbix_server'");
		shell_exec("openssl x509 -req -in $serverCsr -CA $caCert -CAkey $caKey -CAcreateserial -out $serverCert -days 365 -sha256");

		// Agent
		shell_exec("openssl genrsa -out $agentKey 2048");
		shell_exec("openssl req -new -key $agentKey -out $agentCsr -subj '/CN=zabbix_agent'");
		shell_exec("openssl x509 -req -in $agentCsr -CA $caCert -CAkey $caKey -CAcreateserial -out $agentCert -days 365 -sha256");

		// Proxy
		shell_exec("openssl genrsa -out $proxyKey 2048");
		shell_exec("openssl req -new -key $proxyKey -out $proxyCsr -subj '/CN=zabbix_proxy'");
		shell_exec("openssl x509 -req -in $proxyCsr -CA $caCert -CAkey $caKey -CAcreateserial -out $proxyCert -days 365 -sha256");

		$serverVerify = shell_exec("openssl verify -CAfile $caCert $serverCert");
		$agentVerify = shell_exec("openssl verify -CAfile $caCert $agentCert");
		$proxyVerify = shell_exec("openssl verify -CAfile $caCert $proxyCert");

		$this->assertStringContainsString('OK', $serverVerify, 'Server certificate verification failed');
		$this->assertStringContainsString('OK', $agentVerify, 'Agent certificate verification failed');
		$this->assertStringContainsString('OK', $proxyVerify, 'Proxy certificate verification failed');
	}

	/**
	 * Update proxy host to use TLS certificates.
	 */
	private function updateProxyHostTLS() {
		$proxy = $this->call('proxy.get', [
			'filter' => ['name' => 'proxy'],
			'output' => ['proxyid']
		]);
		if (empty($proxy['result'])) {
			throw new Exception('Proxy not found');
		}
		$proxyid = $proxy['result'][0]['proxyid'];

		$response = $this->call('proxy.update', [
			'proxyid' => $proxyid,
			'tls_connect' => 1,
			'tls_accept' => 4, // certificate
			'tls_issuer' => 'CN=ZabbixCA',
			'tls_subject' => 'CN=zabbix_proxy'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		$this->assertEquals(1, count($response['result']['proxyids']));
	}

	/**
	 * Update agent host to use TLS certificates.
	 */
	private function updateAgentHostTLS() {
		$agentHost = $this->call('host.get', [
			'filter' => ['host' => 'proxy_agent'],
			'output' => ['hostid']
		]);
		if (empty($agentHost['result'])) {
			throw new Exception('Agent host not found');
		}
		$hostid = $agentHost['result'][0]['hostid'];

		$response = $this->call('host.update', [
			'hostid' => $hostid,
			'tls_connect' => 4, // certificate
			'tls_accept' => 4,
			'tls_issuer' => 'CN=ZabbixCA',
			'tls_subject' => 'CN=zabbix_agent'
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertEquals(1, count($response['result']['hostids']));
	}

	/**
	 * Component configuration provider for proxy related tests.
	 *
	 * @return array
	 */
	public function proxyConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'UnreachablePeriod' => 5,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1,
				'DebugLevel' => 4,
				'TLSCAFile' => '/tmp/zabbix_cert/zabbix_ca_file.crt',
				'TLSCertFile' => '/tmp/zabbix_cert/zabbix_server.crt',
				'TLSKeyFile' => '/tmp/zabbix_cert/zabbix_server.key'
			],
			self::COMPONENT_PROXY => [
				'UnreachablePeriod' => 5,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1,
				'DebugLevel' => 4,
				'Hostname' => 'proxy',
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'TLSConnect' => 'cert',
				'TLSAccept' => 'cert',
				'TLSCAFile' => '/tmp/zabbix_cert/zabbix_ca_file.crt',
				'TLSServerCertIssuer' => 'CN=ZabbixCA',
				'TLSServerCertSubject' => 'CN=zabbix_server',
				'TLSCertFile' => '/tmp/zabbix_cert/zabbix_proxy.crt',
				'TLSKeyFile' => '/tmp/zabbix_cert/zabbix_proxy.key'
			],
			self::COMPONENT_AGENT => [
				'Hostname' => 'proxy_agent',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
				'TLSConnect' => 'cert',
				'TLSAccept' => 'cert',
				'TLSCAFile' => '/tmp/zabbix_cert/zabbix_ca_file.crt',
				'TLSCertFile' => '/tmp/zabbix_cert/zabbix_agentd.crt',
				'TLSKeyFile' => '/tmp/zabbix_cert/zabbix_agentd.key',
				'TLSServerCertIssuer' => 'CN=ZabbixCA',
				'TLSServerCertSubject' => 'CN=zabbix_proxy'
			]
		];
	}

	/**
	 * Test if both active and passive agent checks are processed.
	 *
	 * @required-components server, proxy, agent
	 * @configurationDataProvider proxyConfigurationProvider
	 * @hosts proxy_agent
	 */
	public function testDataCollection_checkProxyData() {

		$this->updateAgentHostTLS();
		$this->updateProxyHostTLS();

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy "proxy"');
		self::waitForLogLineToBePresent(self::COMPONENT_PROXY, 'received configuration data from server');
		self::waitForLogLineToBePresent(self::COMPONENT_PROXY, [
			'enabling Zabbix agent checks on host "proxy_agent": interface became available',
			'resuming Zabbix agent checks on host "proxy_agent": connection restored'
		]);

		$passive_data = $this->call('history.get', [
			'itemids'	=> self::$itemids['proxy_agent:agent.ping'],
			'history'	=> ITEM_VALUE_TYPE_UINT64
		]);

		foreach ($passive_data['result'] as $item) {
			$this->assertEquals(1, $item['value']);
		}

		// Retrieve history data from API as soon it is available.
		$active_data = $this->callUntilDataIsPresent('history.get', [
			'itemids'	=> self::$itemids['proxy_agent:agent.hostname'],
			'history'	=> ITEM_VALUE_TYPE_TEXT
		]);

		foreach ($active_data['result'] as $item) {
			$this->assertEquals('proxy_agent', $item['value']);
		}
	}

	/**
	 * Test incremental pre-processing manager cache refresh.
	 *
	 * @required-components server
	 */
	public function testDataCollection_preprocManagerCacheRefresh() {
		$response = $this->call('host.create', [
			[
				'host' => 'trapper_host',
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'hostid' => $hostid,
			'name' => 'trap',
			'key_' => 'trap',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'preprocessing' => [[
				'params' => '100',
				'type' => 1,
				'error_handler' => 1,
				'error_handler_params' => ''
			]]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$itemid = $response['result']['itemids'][0];

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$this->sendSenderValue('trapper_host', 'trap', 1, self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('history.get', [
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1,
			'itemids' => [$itemid]
		], 60, 1);
		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(1, count($response['result']));
		$this->assertArrayHasKey('value', $response['result'][0]);
		$this->assertEquals(100, $response['result'][0]['value']);

		$response = $this->call('item.update', [
			'itemid' => $itemid,
			'preprocessing' => [[
				'params' => '200',
				'type' => 1,
				'error_handler' => 1,
				'error_handler_params' => ''
			]]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "finished forced reloading of the configuration cache", true, 60, 1);

		$this->sendSenderValue('trapper_host', 'trap', 2, self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('history.get', [
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1,
			'itemids' => [$itemid]
		], 60, 1);
		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(1, count($response['result']));
		$this->assertArrayHasKey('value', $response['result'][0]);
		$this->assertEquals(400, $response['result'][0]['value']);
	}

	/**
	 * Test not-supported check in preprocessing.
	 *
	 * @required-components server
	 */
	public function testDataCollection_preprocNotsupportedCheck() {
		$response = $this->call('host.create', [
			[
				'host' => 'ssh_host',
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "finished forced reloading of the configuration cache", true, 60, 1);

		$response = $this->call('host.get', [
			'output' => ['host'],
			'selectInterfaces' => ['interfaceid'],
			'hostids' => $hostid
		]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		$response = $this->call('item.create', [
			'hostid' => $hostid,
			'name' => 'ssh',
			'key_' => 'ssh.run[]',
			'type' => ITEM_TYPE_SSH,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '5s',
			'interfaceid'  => $interfaceid,
			'username' => 'test',
			'params' => 'echo test',
			'preprocessing' => [[
				'params' => "0\na",
				'type' => 26,
				'error_handler' => 3,
				'error_handler_params' => '111'
			],[
				'params' => "1\na",
				'type' => 26,
				'error_handler' => 3,
				'error_handler_params' => '222'
			],[
				'params' => "0\nb",
				'type' => 26,
				'error_handler' => 2,
				'error_handler_params' => '333'
			],[
				'params' => "-1",
				'type' => 26,
				'error_handler' => 2,
				'error_handler_params' => '444'
			]]
		]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$itemid = $response['result']['itemids'][0];

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "finished forced reloading of the configuration cache", true, 60, 1);

		$response = $this->callUntilDataIsPresent('history.get', [
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1,
			'itemids' => [$itemid]
		], 60, 1);

		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(1, count($response['result']));
		$this->assertArrayHasKey('value', $response['result'][0]);
		$this->assertEquals(444, $response['result'][0]['value']);
	}
}
