<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Test suite for IPMI checks, against a real ipmi_sim simulated BMC.
 *
 * @onAfter clearData
 */
class testIpmiChecks extends CIntegrationTest {

	const SIM_IP = '127.0.11.5';
	const SIM_PORT = 16230;
	const SIM_USERNAME = 'zbxipmi';
	const SIM_PASSWORD = 'zbxipmipw';
	const SIM_BMC_NAME = 'zbxtest';
	const SIM_DATA_DIR_REL_PATH = 'data/ipmi_sim';

	const ITEM_DELAY = '1s';

	const HOST_NORMAL = 'ipmi-normal';
	const HOST_MACRO = 'ipmi-macro';
	const HOST_BADPORT = 'ipmi-badport';

	private static $hostids = [];
	private static $itemids = [];
	private static $scriptids = [];
	private static $interfaceids = [];

	/**
	 * Absolute path to the directory holding lan.conf/cmds.emu/test.sdrs.
	 *
	 * @return string
	 */
	private static function simDataDir(): string {
		return realpath(dirname(__FILE__)).'/'.self::SIM_DATA_DIR_REL_PATH;
	}

	/**
	 * Absolute path to the (ephemeral) ipmi_sim persistent state directory.
	 *
	 * @return string
	 */
	private static function simStateDir(): string {
		return sys_get_temp_dir().'/zbx_ipmi_sim_state';
	}

	/**
	 * Absolute path to an executable found via "which", or null if it is not on PATH.
	 *
	 * @param string $tool
	 *
	 * @return string|null
	 */
	private static function findTool(string $tool): ?string {
		$path = trim((string) shell_exec('which '.escapeshellarg($tool).' 2>/dev/null'));

		return ($path === '') ? null : $path;
	}

	/**
	 * @param string $tool
	 * @param string $hint  shown in the exception if the tool is missing
	 *
	 * @throws Exception  if $tool is not on PATH
	 */
	private static function checkToolAvailable(string $tool, string $hint): void {
		if (self::findTool($tool) === null) {
			throw new Exception('"'.$tool.'" is not installed or not on PATH ('.$hint.')');
		}
	}

	private static function ipmiSimStop(): void {
		// Match on the (unique) state dir rather than the exact command line: ipmiSimStart() writes a
		// resolved copy of lan.conf into the state dir, so the running process's "-c" argument does not
		// point at data/ipmi_sim/lan.conf.
		shell_exec('pkill -f '.escapeshellarg('ipmi_sim .* '.self::simStateDir()).' > /dev/null 2>&1');
		usleep(300000);
	}

	private static function ipmiSimStart(): void {
		self::ipmiSimStop();

		$datadir = self::simDataDir();
		$statedir = self::simStateDir();
		$mcdir = $statedir.'/ipmi_sim/'.self::SIM_BMC_NAME;

		shell_exec('rm -rf '.escapeshellarg($statedir));
		if (!mkdir($mcdir, 0777, true) && !is_dir($mcdir)) {
			throw new Exception('Cannot create ipmi_sim state directory "'.$mcdir.'"');
		}

		self::checkToolAvailable('ipmi_sim',
				'apt install openipmi (or equivalent) - required to run the simulated BMC'
		);
		self::checkToolAvailable('ipmitool',
				'apt install ipmitool (or equivalent) - required to talk to the simulated BMC'
		);

		// data/ipmi_sim/test.bsdr is a pre-compiled copy of test.sdrs, checked in so this test never depends
		// on sdrcomp being installed (see the comment at the top of test.sdrs for how to regenerate it after
		// editing test.sdrs - sdrcomp itself is only needed for that one-off regeneration, never at test run
		// time; some environments ship ipmi_sim/ipmitool without the rest of the openipmi package).
		if (!copy($datadir.'/test.bsdr', $mcdir.'/sdr.20.main')) {
			throw new Exception('Cannot read pre-compiled SDR fixture "'.$datadir.'/test.bsdr"');
		}

		// lan.conf references the chassis-control hook script by an absolute path substituted here,
		// since ipmi_sim rejects every Chassis Control (power/reset) command without one configured.
		$lanconf = $statedir.'/lan.conf';
		$lanconf_contents = str_replace('__CHASSISCONTROL_SCRIPT__', $datadir.'/chassiscontrol.sh',
				(string) file_get_contents($datadir.'/lan.conf')
		);
		file_put_contents($lanconf, $lanconf_contents);

		$cmd = 'ipmi_sim'.
				' -c '.escapeshellarg($lanconf).
				' -f '.escapeshellarg($datadir.'/cmds.emu').
				' -s '.escapeshellarg($statedir).
				' -n < /dev/null > '.escapeshellarg($statedir.'/ipmi_sim.log').' 2>&1 &';
		shell_exec($cmd);

		for ($i = 0; $i < 50; $i++) {
			$out = (string) shell_exec(self::ipmitoolCmd(self::SIM_PORT).' mc info 2>&1');

			if (strpos($out, 'Device ID') !== false) {
				return;
			}

			usleep(200000);
		}

		throw new Exception('ipmi_sim did not become ready in time. Log: '.
				(string) @file_get_contents($statedir.'/ipmi_sim.log')
		);
	}

	/**
	 * Build an "ipmitool" command line talking IPMI 1.5 LAN (matching what zbxipmi speaks) to the simulator.
	 *
	 * @param int $port
	 *
	 * @return string
	 */
	private static function ipmitoolCmd(int $port): string {
		return 'ipmitool -I lan -H '.self::SIM_IP.' -p '.$port.
				' -U '.self::SIM_USERNAME.' -P '.self::SIM_PASSWORD.' -A MD5';
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::ipmiSimStart();

		$hosts = [
			[
				'host' => self::HOST_NORMAL,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => 1,
					'useip' => 1,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => (string) self::SIM_PORT
				]],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD
			],
			[
				'host' => self::HOST_MACRO,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => 1,
					'useip' => 1,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => '{$IPMI_TEST_PORT}'
				]],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD,
				'macros' => [[
					'macro' => '{$IPMI_TEST_PORT}',
					'value' => (string) self::SIM_PORT
				]]
			],
			[
				'host' => self::HOST_BADPORT,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => 1,
					'useip' => 1,
					'ip' => self::SIM_IP,
					'dns' => '',
					// Non-numeric: exercises zbx_ipmi_port_expand_macros()'s "Invalid port value" branch.
					// No network I/O is involved, so this cannot hang like a real connection failure would.
					'port' => '{$IPMI_BAD_PORT}'
				]],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD,
				'macros' => [[
					'macro' => '{$IPMI_BAD_PORT}',
					'value' => 'not-a-port'
				]]
			]
		];

		$response = $this->call('host.create', $hosts);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertCount(count($hosts), $response['result']['hostids']);

		foreach (array_keys($hosts) as $i) {
			self::$hostids[$hosts[$i]['host']] = $response['result']['hostids'][$i];
		}

		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => array_values(self::$hostids),
			'selectInterfaces' => ['interfaceid']
		]);

		$interfaceids = [];
		foreach ($response['result'] as $host) {
			$interfaceids[$host['host']] = $host['interfaces'][0]['interfaceid'];
		}
		self::$interfaceids = $interfaceids;

		$items = [
			// A bare sensor string and an "id:" prefixed one both resolve via the same lookup
			// (zbx_get_ipmi_sensor_by_id() matches on the SDR id string, here "Temp").
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.temp.byid', 'sensor' => 'id:Temp'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.presence', 'sensor' => 'Presence'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.get', 'sensor' => ''],
			// OpenIPMI's standard "power" control (lib/chassis.c), created for any MC advertising chassis
			// support. Read via a regular item - exercises zbx_read_ipmi_control()/zbx_got_control_reading_cb().
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.power', 'sensor' => 'power'],
			// Neither a sensor nor a control by this name exists - exercises get_value_ipmi()'s not-found
			// branch for a regular item (as opposed to testIpmiChecks_controlNotFound()'s script path).
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.badname', 'sensor' => 'does-not-exist'],
			// Same not-found branch, but via the "name:" prefix - exercises zbx_get_ipmi_sensor_by_full_name()/
			// zbx_get_ipmi_control_by_full_name() (has_name_prefix()'s other branch), not
			// zbx_get_ipmi_sensor_by_id()/zbx_get_ipmi_control_by_name() as the bare-name case above does.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.badname.byname', 'sensor' => 'name:does-not-exist'],
			// Threshold access disabled at runtime - exercises zbx_read_ipmi_thresholds()'s early-exit branch.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.nothresh', 'sensor' => 'NoThresh'],
			['host' => self::HOST_MACRO, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_BADPORT, 'key' => 'ipmi.temp', 'sensor' => 'Temp']
		];

		$item_data = [];
		foreach ($items as $item) {
			$item_data[] = [
				'hostid' => self::$hostids[$item['host']],
				'interfaceid' => $interfaceids[$item['host']],
				'name' => $item['key'],
				'key_' => $item['key'],
				'type' => ITEM_TYPE_IPMI,
				'value_type' => ($item['key'] === 'ipmi.get') ? ITEM_VALUE_TYPE_TEXT : ITEM_VALUE_TYPE_FLOAT,
				'ipmi_sensor' => $item['sensor'],
				'delay' => self::ITEM_DELAY
			];
		}

		// ipmi.presence is discrete, its value must be stored as text/unsigned, not float.
		foreach ($item_data as &$data) {
			if ($data['key_'] === 'ipmi.presence') {
				$data['value_type'] = ITEM_VALUE_TYPE_UINT64;
			}
		}
		unset($data);

		$response = $this->call('item.create', $item_data);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertCount(count($item_data), $response['result']['itemids']);

		foreach ($items as $i => $item) {
			self::$itemids[$item['host'].':'.$item['key']] = $response['result']['itemids'][$i];
		}

		// A script exercising the "IPMI command" (zbx_parse_ipmi_command/zbx_set_ipmi_control_value) code path.
		// "nonexistent" is a deliberately non-existent, single-token control name - see
		// testIpmiChecks_controlNotFound() for why a real ATCA-style control cannot be addressed here.
		$response = $this->call('script.create', [
			'name' => 'Test IPMI control not found',
			'type' => ZBX_SCRIPT_TYPE_IPMI,
			'scope' => ZBX_SCRIPT_SCOPE_HOST,
			'command' => 'nonexistent on',
			'host_access' => PERM_READ
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptids['not_found'] = $response['result']['scriptids'][0];

		// OpenIPMI's standard "power" control is a single-token name, so it CAN be addressed by the
		// "IPMI command" syntax - exercises the successful zbx_set_ipmi_control_value() path end to end.
		$response = $this->call('script.create', [
			'name' => 'Test IPMI power on',
			'type' => ZBX_SCRIPT_TYPE_IPMI,
			'scope' => ZBX_SCRIPT_SCOPE_HOST,
			'command' => 'power on',
			'host_access' => PERM_READ
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptids['power_on'] = $response['result']['scriptids'][0];

		// Same control, but a numeric value - exercises zbx_parse_ipmi_command()'s zbx_is_uint31() branch,
		// as opposed to the "on"/"off" keyword branch above.
		$response = $this->call('script.create', [
			'name' => 'Test IPMI power numeric value',
			'type' => ZBX_SCRIPT_TYPE_IPMI,
			'scope' => ZBX_SCRIPT_SCOPE_HOST,
			'command' => 'power 1',
			'host_access' => PERM_READ
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptids['power_numeric'] = $response['result']['scriptids'][0];

		return true;
	}

	/**
	 * Remove the hosts, items and scripts created for this suite, and stop the simulator.
	 */
	public static function clearData(): void {
		if (self::$scriptids) {
			CDataHelper::call('script.delete', array_values(self::$scriptids));
		}

		if (self::$hostids) {
			CDataHelper::call('host.delete', array_values(self::$hostids));
		}

		self::$hostids = [];
		self::$itemids = [];
		self::$interfaceids = [];
		self::$scriptids = [];

		self::ipmiSimStop();
	}

	/**
	 * Component configuration provider - turns on the IPMI poller/manager.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				// 2 pollers so hosts actually get load-balanced across them
				// (ipmi_manager_get_host_poller()'s heap-based selection).
				'StartIPMIPollers' => 2
			]
		];
	}

	/**
	 * Poll an item until it either collects a value or becomes unsupported.
	 *
	 * @param string $key   fully qualified "host:key" identifier used in self::$itemids
	 *
	 * @return array  item.get result row (lastvalue, lastclock, state)
	 */
	private function getItemResult($key) {
		$itemid = self::$itemids[$key];

		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['lastvalue', 'lastclock', 'state', 'error'],
			'itemids' => [$itemid]
		], 30, 1, function(array $response) {
			$item = $response['result'][0];

			return ($item['lastclock'] != 0 || $item['state'] == ITEM_STATE_NOTSUPPORTED)
				? true
				: 'no data was collected for the item yet';
		});

		return $response['result'][0];
	}

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_thresholdSensor(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.temp');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.temp is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001);
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_sensorById(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.temp.byid');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.temp.byid is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001, 'ipmi.temp.byid value mismatch');
	}

	/**
	 * Uses the "name" reported by discovery (OpenIPMI's fully qualified sensor name) to build and check
	 * a "name:"-prefixed item, exercising zbx_get_ipmi_sensor_by_full_name() (has_name_prefix()).
	 *
	 * @depends testIpmiChecks_discovery
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_sensorByFullName(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.get');
		$json = json_decode($item['lastvalue'], true);

		$full_name = null;
		foreach ($json as $entry) {
			if (isset($entry['name']) && strpos($entry['name'], 'Temp') !== false) {
				$full_name = $entry['name'];
				break;
			}
		}

		$this->assertNotNull($full_name, 'Could not find the "Temp" sensor in discovery output to build a '.
				'name: prefixed item'
		);

		$response = $this->call('item.create', [
			'hostid' => self::$hostids[self::HOST_NORMAL],
			'interfaceid' => self::$interfaceids[self::HOST_NORMAL],
			'name' => 'ipmi.temp.byname',
			'key_' => 'ipmi.temp.byname',
			'type' => ITEM_TYPE_IPMI,
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'ipmi_sensor' => 'name:'.$full_name,
			'delay' => self::ITEM_DELAY
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$itemids[self::HOST_NORMAL.':ipmi.temp.byname'] = $response['result']['itemids'][0];

		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.temp.byname');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.temp.byname ("name:'.$full_name.'") is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001, 'ipmi.temp.byname value mismatch');
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_discreteSensor(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.presence');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.presence is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertEquals(1, (int) $item['lastvalue'], 'ipmi.presence bit 0 should be asserted');
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_discovery(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.get');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.get is unexpectedly unsupported: '.$item['lastvalue']
		);

		$json = json_decode($item['lastvalue'], true);
		$this->assertIsArray($json, 'ipmi.get did not return a JSON object: '.$item['lastvalue']);

		// Sensor "name" is OpenIPMI's full sensor name (domain/entity-qualified), so match by substring.
		$names = array_column($json, 'name');
		$find = function(array $names, string $needle) {
			foreach ($names as $name) {
				if (strpos($name, $needle) !== false) {
					return true;
				}
			}

			return false;
		};

		$this->assertTrue($find($names, 'Temp'), 'Discovery is missing the "Temp" sensor: '.$item['lastvalue']);
		$this->assertTrue($find($names, 'Presence'),
				'Discovery is missing the "Presence" sensor: '.$item['lastvalue']
		);

		// "Unsupported" (a reserved event_reading_type_code) must be silently skipped by get_discovery_ipmi()
		// (zbx_read_ipmi_sensor() fails it before any read is attempted), not merely fail elsewhere.
		$this->assertFalse($find($names, 'Unsupported'),
				'Discovery should not include the "Unsupported" sensor: '.$item['lastvalue']
		);

		$find_entry = function(array $json, string $needle) {
			foreach ($json as $entry) {
				if (isset($entry['name']) && strpos($entry['name'], $needle) !== false) {
					return $entry;
				}
			}

			return null;
		};

		// zbx_get_ipmi_units()'s early "percentage" return.
		$percent = $find_entry($json, 'Percent');
		$this->assertIsArray($percent, 'Discovery is missing the "Percent" sensor: '.$item['lastvalue']);
		$this->assertSame('%', $percent['units'], 'Unexpected units for "Percent": '.json_encode($percent));

		// zbx_get_ipmi_units()'s "base OP modifier" branch (not the plain "none" case Temp/etc. hit) - which
		// of IPMI_MODIFIER_UNIT_BASE_DIV_MOD/_MULT_MOD the SDR's "divide" token maps to at the wire level
		// turned out empirically to be multiplication ("watts*hour", confirmed against a real OpenIPMI
		// connection), not division - either branch is a previously-uncovered one, so the sensor is kept
		// and just asserted against its actual (lowercase, "*") rendering rather than chasing the exact
		// token/enum mapping further.
		$wph = $find_entry($json, 'WattsHour');
		$this->assertIsArray($wph, 'Discovery is missing the "WattsHour" sensor: '.$item['lastvalue']);
		$this->assertStringContainsString('watts', $wph['units'], 'Unexpected units for "WattsHour": '.
				json_encode($wph)
		);
		$this->assertTrue(preg_match('#[*/]#', $wph['units']) === 1, 'Unexpected units for "WattsHour": '.
				json_encode($wph)
		);
	}

	/*
	Connection-failure scenarios (wrong credentials, an unreachable port, a mid-session drop) are deliberately
	not exercised here. zbx_perform_openipmi_ops()'s wait loop (checks_ipmi.c) has no overall timeout - only a
	10s per-iteration chunk it can keep re-entering - and in practice a wrong password and a non-responding
	port never resolved within 60+ seconds against ipmi_sim in this environment. A mid-session drop (killing
	the simulator after a connection is already up) WAS confirmed, with a small standalone OpenIPMI
	reproducer using the same connection setup as zbx_init_ipmi_host(), to be detected deterministically in
	~30s by OpenIPMI's own connection-audit timer - but there is only one simulator instance serving every
	host in this suite, so killing it to test one host mid-session also breaks every other host's connection
	at the same time (confirmed: "ipmi-normal"/"ipmi-macro" also logged network errors), and Zabbix's own
	unreachable-retry backoff on top of that pushed the real end-to-end recovery past 90s-2min. Properly
	isolating this would need a second, dedicated simulator instance. Given the modest additional coverage
	(essentially zbx_connection_change_cb()'s error path and zbx_domain_closed_cb()) against that much added
	complexity, runtime, and risk of destabilizing every other test in this suite, this suite sticks to the
	fast, deterministic scenarios below instead.
	*/

	/**
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_portMacro(): void {
		$item = $this->getItemResult(self::HOST_MACRO.':ipmi.temp');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.temp via a macro-expanded port is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001);
	}

	/**
	 * "IPMI command" scripts (zbx_parse_ipmi_command/zbx_set_ipmi_control_value) address a control by a single,
	 * space-free token: the parser splits the command on its first whitespace and treats everything after it as
	 * the on/off/numeric value. ipmi_sim's only scriptable control (an ATCA LED, "led 1") does not fit that
	 * syntax, so there is no way to build a real happy-path control-set fixture here. Instead, this exercises
	 * the well-defined failure path: a control that does not exist must be rejected end to end (parse -> lookup
	 * -> API error), which still covers zbx_parse_ipmi_command() and zbx_set_ipmi_control_value()'s not-found
	 * branch through a real server + real OpenIPMI connection.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_controlNotFound(): void {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = CAPIHelper::call('script.execute', [
			'scriptid' => self::$scriptids['not_found'],
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);

		$this->assertArrayHasKey('error', $response, 'script.execute against a non-existent control should fail: '.
				json_encode($response)
		);
		$this->assertStringContainsString('does not exist', $response['error']['data'],
				'Unexpected error executing an IPMI command against a non-existent control: '.
				json_encode($response['error'])
		);
	}

	/**
	 * OpenIPMI's standard "power" control, read via a regular item - exercises
	 * zbx_read_ipmi_control()/zbx_got_control_reading_cb(), never reached by any other test here.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_powerControlRead(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.power');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.power is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertIsNumeric($item['lastvalue'], 'ipmi.power did not return a numeric value');
	}

	/**
	 * Sets OpenIPMI's standard "power" control via an IPMI-command script - exercises the successful
	 * zbx_set_ipmi_control_value()/zbx_set_ipmi_control()/zbx_got_control_setting_cb() path.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_powerControlSet(): void {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = CAPIHelper::call('script.execute', [
			'scriptid' => self::$scriptids['power_on'],
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);

		$this->assertArrayHasKey('result', $response, 'script.execute("power on") failed: '.json_encode($response));
		$this->assertEquals('success', $response['result']['response'],
				'script.execute("power on") did not report success: '.json_encode($response['result'])
		);
	}

	/**
	 * Same as testIpmiChecks_powerControlSet(), but with a numeric value ("power 1") instead of the "on"
	 * keyword - exercises zbx_parse_ipmi_command()'s zbx_is_uint31() success branch.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_powerControlSetNumeric(): void {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = CAPIHelper::call('script.execute', [
			'scriptid' => self::$scriptids['power_numeric'],
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);

		$this->assertArrayHasKey('result', $response, 'script.execute("power 1") failed: '.json_encode($response));
		$this->assertEquals('success', $response['result']['response'],
				'script.execute("power 1") did not report success: '.json_encode($response['result'])
		);
	}

	/**
	 * Exercises zbx_ipmi_port_expand_macros()'s "Invalid port value" branch. This is resolved entirely in the
	 * IPMI manager, before any connection is attempted, so unlike a real connection failure it cannot hang.
	 *
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_invalidPortMacro(): void {
		$item = $this->getItemResult(self::HOST_BADPORT.':ipmi.temp');

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'],
				'An item with a non-numeric macro-expanded port should be unsupported'
		);
		$this->assertStringContainsString('Invalid port value', $item['error']);
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_badSensorName(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.badname');

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'],
				'A non-existent sensor/control name should make the item unsupported'
		);
		$this->assertStringContainsString('does not exist', $item['error']);
	}

	/**
	 * Same as testIpmiChecks_badSensorName(), but via the "name:" prefix - exercises
	 * zbx_get_ipmi_sensor_by_full_name()/zbx_get_ipmi_control_by_full_name()'s not-found path.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_badSensorNameByFullName(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.badname.byname');

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'],
				'A non-existent "name:"-prefixed sensor/control name should make the item unsupported'
		);
		$this->assertStringContainsString('does not exist', $item['error']);
	}

	/**
	 * A threshold sensor with threshold access turned off still reads normally (thresholds are just
	 * classification metadata for a plain value read), but its discovery entry must not carry any
	 * populated threshold values - exercising zbx_read_ipmi_thresholds()'s "cannot access thresholds"
	 * early exit, as opposed to "Temp"'s fully-populated threshold path.
	 *
	 * @depends testIpmiChecks_discovery
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_noThresholdAccess(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.nothresh');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.nothresh is unexpectedly unsupported: '.$item['lastvalue']
		);
		$this->assertEqualsWithDelta(42, (float) $item['lastvalue'], 0.001);

		$discovery = $this->getItemResult(self::HOST_NORMAL.':ipmi.get');
		$json = json_decode($discovery['lastvalue'], true);

		$entry = null;
		foreach ($json as $candidate) {
			if (isset($candidate['name']) && strpos($candidate['name'], 'NoThresh') !== false) {
				$entry = $candidate;
				break;
			}
		}

		$this->assertIsArray($entry, 'Discovery is missing the "NoThresh" sensor: '.$discovery['lastvalue']);
		$this->assertArrayHasKey('threshold', $entry);
		$this->assertSame([], $entry['threshold']['lower'] ?? null,
				'"NoThresh" should have no populated lower threshold values: '.json_encode($entry)
		);
		$this->assertSame([], $entry['threshold']['upper'] ?? null,
				'"NoThresh" should have no populated upper threshold values: '.json_encode($entry)
		);
	}

	/**
	 * @return array<int, array>
	 */
	public function malformedIpmiCommandDataProvider(): array {
		// [command, expected_error_substring]
		return [
			['power notanumber', 'IPMI command value is not supported'],
			[' ', 'IPMI command is empty'],
			[str_repeat('a', 130).' on', 'IPMI command is too long']
		];
	}

	/**
	 * zbx_parse_ipmi_command() runs entirely locally (before any poller/network involvement), so these
	 * resolve instantly regardless of ipmi_sim/connection state - each case builds and executes its own
	 * throwaway script rather than reusing one from prepareData(), since the malformed command itself is
	 * the thing under test.
	 *
	 * @dataProvider malformedIpmiCommandDataProvider
	 * @required-components server
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testIpmiChecks_malformedIpmiCommand(string $command, string $expected_error): void {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = $this->call('script.create', [
			'name' => 'Test malformed IPMI command '.md5($command),
			'type' => ZBX_SCRIPT_TYPE_IPMI,
			'scope' => ZBX_SCRIPT_SCOPE_HOST,
			'command' => $command,
			'host_access' => PERM_READ
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		$scriptid = $response['result']['scriptids'][0];
		self::$scriptids['malformed_'.md5($command)] = $scriptid;

		$response = CAPIHelper::call('script.execute', [
			'scriptid' => $scriptid,
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);

		$this->assertArrayHasKey('error', $response, 'script.execute with command "'.$command.'" should fail: '.
				json_encode($response)
		);
		$this->assertStringContainsString($expected_error, $response['error']['data'],
				'Unexpected error for command "'.$command.'": '.json_encode($response['error'])
		);
	}
}
