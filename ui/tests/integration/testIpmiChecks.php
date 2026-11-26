<?php declare(strict_types = 1);
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
 * Test suite for IPMI checks, against an ipmi_sim simulated BMC.
 *
 * The server is started once for the whole suite: every start makes the IPMI pollers open all OpenIPMI domains
 * again, which is slow.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testIpmiChecks extends CIntegrationTest {

	const SIM_IP = '127.0.11.5';
	// Dedicated simulator for testIpmiChecks_reconnect(), which kills and restarts it mid-session - a separate
	// instance keeps that from disturbing every other host's connection.
	const SIM_RECONNECT_IP = '127.0.11.6';
	// Dedicated simulator for testIpmiChecks_discoveryConnectionLoss(), killed mid-session as well.
	const SIM_DISCOVERY_DROP_IP = '127.0.11.7';
	const SIM_USERNAME = 'zbxipmi';
	const SIM_PASSWORD = 'zbxipmipw';
	const SIM_BMC_NAME = 'zbxtest';
	const SIM_DATA_DIR_REL_PATH = 'data/ipmi_sim';

	// Initial readings of the polled simulator sensors, by sensor number (see data/ipmi_sim/cmds.emu). Discrete
	// sensor values are bitmasks of asserted states.
	const SIM_SENSOR_VALUES = [
		1 => 50,	// Temp
		2 => 1,		// Presence: bit 0
		3 => 42,	// NoThresh
		4 => 1,		// Unsupported
		5 => 75,	// Percent
		6 => 30,	// WattsHour
		7 => 1,		// discrete Dup: bit 0
		8 => 33,	// threshold Dup
		9 => 85,	// Hot
		10 => 3,	// MultiState: bits 0 and 1
		11 => 40,	// NoScan
		12 => 1,	// NoScanDisc: bit 0
		14 => 5,	// InfThr: 10^(2*5)
		15 => 20,	// Rate
		16 => 30,	// MulUnit
		18 => 251,	// Negative: -5 in 2s complement
		19 => 25,	// Fraction: 25 * 5 * 10^-1
		20 => 16,	// SixteenCharIdStr
		21 => 7		// Masked: bits 0-2, only bits 0 and 1 are readable
	];

	// "Temp" sensor number, see self::SIM_SENSOR_VALUES.
	const SIM_TEMP_SENSOR_KEY = 1;

	// IPMI sensor types (IPMI v2.0 table 42-3) and event/reading type codes (table 42-1) of the fixture sensors, as
	// reported by discovery.
	const IPMI_SENSOR_TYPE_TEMPERATURE = 0x01;
	const IPMI_SENSOR_TYPE_ENTITY_PRESENCE = 0x25;
	const IPMI_READING_TYPE_THRESHOLD = 0x01;
	const IPMI_READING_TYPE_SENSOR_SPECIFIC = 0x6f;

	// Threshold sensor state bits, OpenIPMI's enum ipmi_thresh_e.
	const IPMI_UPPER_NON_CRITICAL = 3;
	const IPMI_UPPER_CRITICAL = 4;

	// Discrete state bits of an entity presence sensor (IPMI v2.0 table 42-3, sensor type 0x25).
	const IPMI_ENTITY_PRESENT = 0x01;
	const IPMI_ENTITY_ABSENT = 0x02;

	const ITEM_DELAY = '1s';

	// HOST_* are host names (will be created with host.create API).
	const HOST_NORMAL = 'ipmi-normal';
	const HOST_MACRO = 'ipmi-macro';
	const HOST_BADPORT = 'ipmi-badport';
	const HOST_RECONNECT = 'ipmi-reconnect';
	// Same BMC as HOST_NORMAL, but other credentials - each gets its own zbx_ipmi_host_t (and OpenIPMI domain).
	const HOST_ANON = 'ipmi-anon';
	const HOST_ALTAUTH = 'ipmi-altauth';
	// Connection failures, created disabled and only enabled by testIpmiChecks_connectionFailure(), since every
	// poll of them keeps an IPMI poller busy for several seconds.
	const HOST_BADPASS = 'ipmi-badpass';
	const HOST_CLOSEDPORT = 'ipmi-closedport';
	// Only has discovery items, so that discovery is always the request in progress when its BMC is lost. Created
	// disabled and only enabled by testIpmiChecks_discoveryConnectionLoss(), its BMC is not restarted afterwards.
	const HOST_DISCOVERY_DROP = 'ipmi-discovery-drop';
	// Discovery item keys of HOST_DISCOVERY_DROP - several of them keep discoveries running back to back.
	const DISCOVERY_DROP_KEYS = ['ipmi.get', 'ipmi.get[1]', 'ipmi.get[2]', 'ipmi.get[3]'];

	private static $hostids = [];
	private static $itemids = [];
	private static $scriptids = [];
	private static $interfaceids = [];

	// Per-run temporary directory, see simBaseDir().
	private static $sim_base_dir = null;
	// [address => PID] of the simulator instances started by this run.
	private static $sim_pids = [];
	// UDP port of every simulator instance of this run, see simPort().
	private static $sim_port = null;
	// Whether an "item.test" request has already been answered with a value, see testIpmiItem().
	private static $item_test_ready = false;

	/**
	 * Absolute path to the directory holding lan.conf/cmds.emu/test.sdrs.
	 *
	 * @return string
	 */
	private static function simDataDir(): string {
		return realpath(dirname(__FILE__)).'/'.self::SIM_DATA_DIR_REL_PATH;
	}

	/**
	 * Absolute path to the temporary directory holding the state of every simulator instance of this run. It is
	 * created on first use with the PID, time and a random suffix in its name, so that concurrent runs on the same
	 * system never share it, and is removed by ipmiSimCleanup() - also at process exit, should the suite abort.
	 *
	 * @throws Exception  if the directory cannot be created
	 *
	 * @return string
	 */
	private static function simBaseDir(): string {
		if (self::$sim_base_dir === null) {
			$dir = sys_get_temp_dir().'/zbx_ipmi_sim_'.getmypid().'_'.time().'_'.bin2hex(random_bytes(4));

			if (!mkdir($dir, 0700)) {
				throw new Exception('Cannot create ipmi_sim base directory "'.$dir.'"');
			}

			self::$sim_base_dir = $dir;
			register_shutdown_function([self::class, 'ipmiSimCleanup']);
		}

		return self::$sim_base_dir;
	}

	/**
	 * UDP port all simulator instances of this run listen on. ipmi_sim binds its port with SO_REUSEADDR, so a
	 * fixed port would let concurrent runs on the same system silently share it and answer each other's requests.
	 * Instead a port that is free on every address is chosen once per run (kept across simulator restarts, as the
	 * hosts' interfaces refer to it).
	 *
	 * @throws Exception  if no port can be obtained
	 *
	 * @return int
	 */
	private static function simPort(): int {
		if (self::$sim_port === null) {
			self::$sim_port = self::freeUdpPort();
		}

		return self::$sim_port;
	}

	/**
	 * A UDP port that is free on every address at the time of the call.
	 *
	 * @throws Exception  if no port can be obtained
	 *
	 * @return int
	 */
	private static function freeUdpPort(): int {
		$socket = @stream_socket_server('udp://0.0.0.0:0', $errno, $errstr, STREAM_SERVER_BIND);

		if ($socket === false) {
			throw new Exception('Cannot obtain a free UDP port: '.$errstr);
		}

		$name = (string) stream_socket_get_name($socket, false);
		fclose($socket);

		return (int) substr($name, strrpos($name, ':') + 1);
	}

	/**
	 * Absolute path to the (ephemeral) ipmi_sim persistent state directory of a simulator instance.
	 *
	 * @param string $ip  address the instance listens on, one instance per address
	 *
	 * @return string
	 */
	private static function simStateDir(string $ip): string {
		return self::simBaseDir().'/'.str_replace('.', '_', $ip);
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

	/**
	 * @param string $ip  simulator instance, see simStateDir()
	 */
	private static function ipmiSimStop(string $ip): void {
		if (!array_key_exists($ip, self::$sim_pids)) {
			return;
		}

		// Only the process started by this run is signalled, never another run's simulator. Wait for it to exit,
		// so that a restart on the same address and port does not race with it.
		$pid = self::$sim_pids[$ip];
		unset(self::$sim_pids[$ip]);

		shell_exec('kill '.$pid.' > /dev/null 2>&1');

		for ($i = 0; $i < 30; $i++) {
			if (!self::isProcessAlive($pid)) {
				return;
			}

			usleep(100000);
		}

		shell_exec('kill -9 '.$pid.' > /dev/null 2>&1');
		usleep(100000);
	}

	/**
	 * @param int $pid
	 *
	 * @return bool
	 */
	private static function isProcessAlive(int $pid): bool {
		return trim((string) shell_exec('kill -0 '.$pid.' > /dev/null 2>&1 && echo alive')) === 'alive';
	}

	/**
	 * Stop every simulator instance of this run and remove its temporary files. Safe to call more than once.
	 */
	public static function ipmiSimCleanup(): void {
		foreach (array_keys(self::$sim_pids) as $ip) {
			self::ipmiSimStop($ip);
		}

		if (self::$sim_base_dir !== null) {
			shell_exec('rm -rf '.escapeshellarg(self::$sim_base_dir));
			self::$sim_base_dir = null;
		}
	}

	/**
	 * Start (or restart from scratch) a simulator instance serving the data/ipmi_sim fixtures on $ip.
	 *
	 * @param string $ip
	 * @param array  $sensor_values  [sensor number => value] overriding self::SIM_SENSOR_VALUES
	 * @param array  $drop_sdr_ids   id strings of the sensors whose SDRs are left out of the repository
	 */
	private static function ipmiSimStart(string $ip, array $sensor_values = [], array $drop_sdr_ids = []): void {
		self::ipmiSimStop($ip);

		$datadir = self::simDataDir();
		$statedir = self::simStateDir($ip);
		$mcdir = $statedir.'/ipmi_sim/'.self::SIM_BMC_NAME;

		shell_exec('rm -rf '.escapeshellarg($statedir));
		if (!mkdir($mcdir, 0700, true) && !is_dir($mcdir)) {
			throw new Exception('Cannot create ipmi_sim state directory "'.$mcdir.'"');
		}

		self::checkToolAvailable('ipmi_sim',
				'apt install openipmi (or equivalent) - to run the simulated BMC the ipmi_sim program is required'
		);
		self::checkToolAvailable('ipmitool',
				'apt install ipmitool (or equivalent) - required to talk to the simulated BMC'
		);

		// data/ipmi_sim/test.bsdr is a pre-compiled copy of test.sdrs, checked in so this test never depends
		// on sdrcomp being installed (see the comment at the top of test.sdrs for how to regenerate it after
		// editing test.sdrs - sdrcomp itself is only needed for that one-off regeneration, never at test run
		// time).
		$sdrs = @file($datadir.'/test.bsdr');

		if ($sdrs === false) {
			throw new Exception('Cannot read pre-compiled SDR fixture "'.$datadir.'/test.bsdr"');
		}

		// The repository is stored one SDR per line, ending with the id string preceded by its type/length byte,
		// which is always escaped as "\xx" (8-bit ASCII type, 0xc0 | length).
		$sdrs = array_filter($sdrs, function(string $sdr) use ($drop_sdr_ids) {
			foreach ($drop_sdr_ids as $id) {
				if (preg_match('/\\\\[0-9a-f]{2}'.preg_quote($id, '/').'$/', rtrim($sdr, "\n")) === 1) {
					return false;
				}
			}

			return true;
		});

		file_put_contents($mcdir.'/sdr.20.main', implode('', $sdrs));

		// lan.conf references the chassis-control hook script by an absolute path substituted here,
		// since ipmi_sim rejects every Chassis Control (power/reset) command without one configured.
		$lanconf = $statedir.'/lan.conf';
		$lanconf_contents = str_replace(
				['__CHASSISCONTROL_SCRIPT__', '__SIM_ADDR__', '__SIM_PORT__'],
				[$datadir.'/chassiscontrol.sh', $ip, self::simPort()],
				(string) file_get_contents($datadir.'/lan.conf')
		);
		file_put_contents($lanconf, $lanconf_contents);

		// Every readable sensor is polled from a value file in the state directory, see data/ipmi_sim/cmds.emu.
		foreach (array_replace(self::SIM_SENSOR_VALUES, $sensor_values) as $num => $value) {
			file_put_contents($statedir.'/sensor_'.$num, $value."\n");
		}

		$cmds = $statedir.'/cmds.emu';
		file_put_contents($cmds,
				str_replace('__STATE_DIR__', $statedir, (string) file_get_contents($datadir.'/cmds.emu'))
		);

		$cmd = 'ipmi_sim'.
				' -c '.escapeshellarg($lanconf).
				' -f '.escapeshellarg($cmds).
				' -s '.escapeshellarg($statedir).
				' -n < /dev/null > '.escapeshellarg($statedir.'/ipmi_sim.log').' 2>&1 & echo $!';
		$pid = (int) shell_exec($cmd);

		if ($pid <= 0) {
			throw new Exception('Cannot start ipmi_sim');
		}

		self::$sim_pids[$ip] = $pid;

		for ($i = 0; $i < 50; $i++) {
			// Make sure the answer below comes from the instance just started, e.g. not from a previous one
			// still shutting down, while this one failed to start.
			if (!self::isProcessAlive($pid)) {
				unset(self::$sim_pids[$ip]);

				throw new Exception('ipmi_sim exited unexpectedly. Log: '.
						(string) @file_get_contents($statedir.'/ipmi_sim.log')
				);
			}

			$out = (string) shell_exec(self::ipmitoolCmd($ip, self::simPort()).' mc info 2>&1');

			if (strpos($out, 'Device ID') !== false && self::isProcessAlive($pid)) {
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
	 * @param string $ip
	 * @param int    $port
	 *
	 * @return string
	 */
	private static function ipmitoolCmd(string $ip, int $port): string {
		return 'ipmitool -I lan -H '.$ip.' -p '.$port.
				' -U '.self::SIM_USERNAME.' -P '.self::SIM_PASSWORD.' -A MD5';
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData(): bool {
		self::ipmiSimStart(self::SIM_IP);
		self::ipmiSimStart(self::SIM_RECONNECT_IP);
		self::ipmiSimStart(self::SIM_DISCOVERY_DROP_IP);

		do {
			$closed_port = self::freeUdpPort();
		}
		while ($closed_port == self::simPort());

		$hosts = [
			[
				'host' => self::HOST_NORMAL,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => (string) self::simPort()
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
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
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => '{$IPMI_TEST_PORT}'
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD,
				'macros' => [[
					'macro' => '{$IPMI_TEST_PORT}',
					'value' => (string) self::simPort()
				]]
			],
			[
				'host' => self::HOST_BADPORT,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					// Non-numeric: exercises zbx_ipmi_port_expand_macros()'s "Invalid port value" branch.
					// No network I/O is involved, so this cannot hang like a real connection failure would.
					'port' => '{$IPMI_BAD_PORT}'
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD,
				'macros' => [[
					'macro' => '{$IPMI_BAD_PORT}',
					'value' => 'not-a-port'
				]]
			],
			[
				'host' => self::HOST_RECONNECT,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_RECONNECT_IP,
					'dns' => '',
					'port' => (string) self::simPort()
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD
			],
			[
				// Anonymous user (lan.conf user 1) - no authentication, "user" privilege.
				'host' => self::HOST_ANON,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => (string) self::simPort()
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_NONE,
				'ipmi_privilege' => IPMI_PRIVILEGE_USER,
				'ipmi_username' => '',
				'ipmi_password' => ''
			],
			[
				// Default authentication type (let OpenIPMI pick, zbx_init_ipmi_host()'s IPMI_AUTHTYPE_DEFAULT
				// branch) and "operator" privilege.
				'host' => self::HOST_ALTAUTH,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => (string) self::simPort()
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_DEFAULT,
				'ipmi_privilege' => IPMI_PRIVILEGE_OPERATOR,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD
			],
			[
				'host' => self::HOST_BADPASS,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					'port' => (string) self::simPort()
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_NOT_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => 'wrong-password'
			],
			[
				'host' => self::HOST_CLOSEDPORT,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_IP,
					'dns' => '',
					// Nothing listens there.
					'port' => (string) $closed_port
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_NOT_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD
			],
			[
				'host' => self::HOST_DISCOVERY_DROP,
				'interfaces' => [[
					'type' => INTERFACE_TYPE_IPMI,
					'main' => INTERFACE_PRIMARY,
					'useip' => INTERFACE_USE_IP,
					'ip' => self::SIM_DISCOVERY_DROP_IP,
					'dns' => '',
					'port' => (string) self::simPort()
				]],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'status' => HOST_STATUS_NOT_MONITORED,
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD
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
			// Two sensors share the id "Dup" (discrete first, threshold second) - zbx_get_ipmi_sensor_by_id()
			// must prefer the threshold one.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.dup', 'sensor' => 'Dup'],
			// Reading above its upper non-critical/critical thresholds.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.hot', 'sensor' => 'Hot'],
			// Discrete sensor with two state bits asserted at once.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.multistate', 'sensor' => 'MultiState'],
			// Sensor ids are matched case-sensitively, so "temp" must not resolve to "Temp".
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.temp.lowercase', 'sensor' => 'temp'],
			// "name:" only matches OpenIPMI's full sensor name, it must not fall back to the bare id "Temp".
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.temp.nameprefix', 'sensor' => 'name:Temp'],
			// "id:" prefix on a control - falls through to zbx_get_ipmi_control_by_name() like a bare name.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.power.byid', 'sensor' => 'id:power'],
			['host' => self::HOST_MACRO, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_BADPORT, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			// Threshold sensor, discrete sensor and control on the host whose BMC testIpmiChecks_reconnect()
			// restarts - each kind is looked up through its own OpenIPMI id after the reconnect.
			['host' => self::HOST_RECONNECT, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_RECONNECT, 'key' => 'ipmi.presence', 'sensor' => 'Presence'],
			['host' => self::HOST_RECONNECT, 'key' => 'ipmi.power', 'sensor' => 'power'],
			// Discovery output is built from the sensor properties cached when OpenIPMI added the sensor - must be
			// built from the recreated sensors after a reconnect.
			['host' => self::HOST_RECONNECT, 'key' => 'ipmi.get', 'sensor' => ''],
			// Reading type string cached from the sensor, used in the error message.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.unsupported', 'sensor' => 'Unsupported'],
			// OpenIPMI's standard "reset" control (lib/chassis.c) is settable, but not readable - exercises the
			// readability cached from the control.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.reset', 'sensor' => 'reset'],
			// Control by its full name (entity 23.1 is OpenIPMI's chassis entity), see
			// zbx_get_ipmi_control_by_full_name().
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.power.byname', 'sensor' => 'name:(23.1).power'],
			// Readings flagged "scanning disabled".
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.noscan', 'sensor' => 'NoScan'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.noscandisc', 'sensor' => 'NoScanDisc'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.negative', 'sensor' => 'Negative'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.fraction', 'sensor' => 'Fraction'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.masked', 'sensor' => 'Masked'],
			// Longest possible sensor id, and ids differing from it by one character at the end.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.longid', 'sensor' => 'SixteenCharIdStr'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.longid.shorter', 'sensor' => 'SixteenCharIdSt'],
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.longid.longer', 'sensor' => 'SixteenCharIdStrX'],
			// Event-only sensors are not readable and must never be added.
			['host' => self::HOST_NORMAL, 'key' => 'ipmi.eventonly', 'sensor' => 'EventOnly'],
			['host' => self::HOST_ANON, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_ALTAUTH, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_BADPASS, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			['host' => self::HOST_CLOSEDPORT, 'key' => 'ipmi.temp', 'sensor' => 'Temp'],
			// Only the bare "ipmi.get" key may have an empty sensor, the sensor is not used by any discovery key.
			['host' => self::HOST_DISCOVERY_DROP, 'key' => 'ipmi.get', 'sensor' => ''],
			['host' => self::HOST_DISCOVERY_DROP, 'key' => 'ipmi.get[1]', 'sensor' => 'Temp'],
			['host' => self::HOST_DISCOVERY_DROP, 'key' => 'ipmi.get[2]', 'sensor' => 'Temp'],
			['host' => self::HOST_DISCOVERY_DROP, 'key' => 'ipmi.get[3]', 'sensor' => 'Temp']
		];

		$item_data = [];
		foreach ($items as $item) {
			$item_data[] = [
				'hostid' => self::$hostids[$item['host']],
				'interfaceid' => $interfaceids[$item['host']],
				'name' => $item['key'],
				'key_' => $item['key'],
				'type' => ITEM_TYPE_IPMI,
				'value_type' => (strpos($item['key'], 'ipmi.get') === 0) ? ITEM_VALUE_TYPE_TEXT : ITEM_VALUE_TYPE_FLOAT,
				'ipmi_sensor' => $item['sensor'],
				'delay' => self::ITEM_DELAY
			];
		}

		// Discrete sensor values must be stored as unsigned, not float.
		foreach ($item_data as &$data) {
			if (in_array($data['key_'], ['ipmi.presence', 'ipmi.multistate', 'ipmi.noscandisc', 'ipmi.masked'])) {
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

		self::ipmiSimCleanup();
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
				'StartIPMIPollers' => 2,
				// Retry quickly after testIpmiChecks_reconnect() drops its BMC, instead of the default 15s/60s.
				'UnreachableDelay' => 1,
				'UnavailableDelay' => 5,
				// Make testIpmiChecks_connectionFailure() interfaces unavailable (which is when the error is stored)
				// sooner than the default 45s.
				'UnreachablePeriod' => 10
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

	public function testIpmiChecks_thresholdSensor(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.temp');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.temp is unexpectedly unsupported: '.$item['error']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001);
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 */
	public function testIpmiChecks_sensorById(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.temp.byid');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.temp.byid is unexpectedly unsupported: '.$item['error']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001, 'ipmi.temp.byid value mismatch');
	}

	/**
	 * Uses the "name" reported by discovery (OpenIPMI's fully qualified sensor name) to build and check
	 * a "name:"-prefixed item, exercising zbx_get_ipmi_sensor_by_full_name() (has_name_prefix()).
	 *
	 * @depends testIpmiChecks_discovery
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
				'ipmi.temp.byname ("name:'.$full_name.'") is unexpectedly unsupported: '.$item['error']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001, 'ipmi.temp.byname value mismatch');
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 */
	public function testIpmiChecks_discreteSensor(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.presence');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.presence is unexpectedly unsupported: '.$item['error']
		);
		$this->assertEquals(self::IPMI_ENTITY_PRESENT, (int) $item['lastvalue'], 'ipmi.presence should be present');
	}

	/**
	 * @depends testIpmiChecks_thresholdSensor
	 */
	public function testIpmiChecks_discovery(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.get');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.get is unexpectedly unsupported: '.$item['error']
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

	/**
	 * The item state found in the database at start may be left over from a previous run of the suite, so wait for
	 * a value collected by the current server instead of accepting the first "not supported" state seen.
	 */
	public function testIpmiChecks_portMacro(): void {
		$start = time();

		$item = $this->waitForItem(self::HOST_MACRO.':ipmi.temp', function(array $item) use ($start) {
			return ($item['state'] == ITEM_STATE_NORMAL && $item['lastclock'] >= $start)
				? true
				: 'waiting for ipmi.temp via a macro-expanded port, got state:'.$item['state'].' error:"'.
					$item['error'].'"';
		}, 30);

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
	 */
	public function testIpmiChecks_controlNotFound(): void {
		$response = $this->call('script.execute', [
			'scriptid' => self::$scriptids['not_found'],
			'hostid' => self::$hostids[self::HOST_NORMAL]
		], true);

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
	 */
	public function testIpmiChecks_powerControlRead(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.power');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.power is unexpectedly unsupported: '.$item['error']
		);
		$this->assertIsNumeric($item['lastvalue'], 'ipmi.power did not return a numeric value');
	}

	/**
	 * Sets OpenIPMI's standard "power" control via an IPMI-command script - exercises the successful
	 * zbx_set_ipmi_control_value()/zbx_set_ipmi_control()/zbx_got_control_setting_cb() path.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 */
	public function testIpmiChecks_powerControlSet(): void {
		$response = $this->call('script.execute', [
			'scriptid' => self::$scriptids['power_on'],
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);

		$this->assertEquals('success', $response['result']['response'],
				'script.execute("power on") did not report success: '.json_encode($response['result'])
		);
	}

	/**
	 * Same as testIpmiChecks_powerControlSet(), but with a numeric value ("power 1") instead of the "on"
	 * keyword - exercises zbx_parse_ipmi_command()'s zbx_is_uint31() success branch.
	 *
	 * @depends testIpmiChecks_thresholdSensor
	 */
	public function testIpmiChecks_powerControlSetNumeric(): void {
		$response = $this->call('script.execute', [
			'scriptid' => self::$scriptids['power_numeric'],
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);

		$this->assertEquals('success', $response['result']['response'],
				'script.execute("power 1") did not report success: '.json_encode($response['result'])
		);
	}

	/**
	 * Exercises zbx_ipmi_port_expand_macros()'s "Invalid port value" branch. This is resolved entirely in the
	 * IPMI manager, before any connection is attempted, so unlike a real connection failure it cannot hang.
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
	 */
	public function testIpmiChecks_noThresholdAccess(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.nothresh');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'],
				'ipmi.nothresh is unexpectedly unsupported: '.$item['error']
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
	 * Discovery entries whose "id" (the bare SDR id string) is exactly $id.
	 *
	 * @param string $id
	 * @param string $host
	 *
	 * @return array  [entries, raw discovery value]
	 */
	private function getDiscoveryEntriesById(string $id, string $host = self::HOST_NORMAL): array {
		$discovery = $this->getItemResult($host.':ipmi.get');
		$json = json_decode($discovery['lastvalue'], true);
		$this->assertIsArray($json, 'ipmi.get did not return a JSON array: '.$discovery['lastvalue']);

		$entries = array_values(array_filter($json, function(array $entry) use ($id) {
			return isset($entry['id']) && $entry['id'] === $id;
		}));

		return [$entries, $discovery['lastvalue']];
	}

	/**
	 * Fully populated thresholds and the in-range state of "Temp" - add_threshold_ipmi() for every threshold,
	 * as opposed to "NoThresh"'s empty ones.
	 *
	 * @depends testIpmiChecks_discovery
	 */
	public function testIpmiChecks_discoveryThresholds(): void {
		[$entries, $raw] = $this->getDiscoveryEntriesById('Temp');
		$this->assertCount(1, $entries, 'Expected exactly one "Temp" discovery entry: '.$raw);
		$entry = $entries[0];

		$this->assertEquals(['non_crit' => 20, 'crit' => 10, 'non_recover' => 5], $entry['threshold']['lower'],
				'Unexpected lower thresholds for "Temp": '.json_encode($entry)
		);
		$this->assertEquals(['non_crit' => 70, 'crit' => 80, 'non_recover' => 90], $entry['threshold']['upper'],
				'Unexpected upper thresholds for "Temp": '.json_encode($entry)
		);
		$this->assertEquals(0, $entry['state']['state'], '"Temp" should be in range: '.json_encode($entry));
		$this->assertSame('', $entry['state']['text'], '"Temp" should be in range: '.json_encode($entry));
	}

	/**
	 * A reading above several thresholds at once - exercises the threshold state bits and the ", "-joined
	 * "<threshold> - out of range" text in get_discovery_ipmi(). The item itself must still read normally.
	 *
	 * @depends testIpmiChecks_discovery
	 */
	public function testIpmiChecks_thresholdOutOfRange(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.hot');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'ipmi.hot is unexpectedly unsupported: '.
				$item['error']
		);
		$this->assertEqualsWithDelta(85, (float) $item['lastvalue'], 0.001);

		[$entries, $raw] = $this->getDiscoveryEntriesById('Hot');
		$this->assertCount(1, $entries, 'Expected exactly one "Hot" discovery entry: '.$raw);
		$entry = $entries[0];

		// Upper non-critical and upper critical, but not upper non-recoverable.
		$this->assertEquals((1 << self::IPMI_UPPER_NON_CRITICAL) | (1 << self::IPMI_UPPER_CRITICAL),
				$entry['state']['state'],
				'Unexpected threshold state for "Hot": '.json_encode($entry)
		);
		$this->assertSame('upper non-critical - out of range, upper critical - out of range',
				$entry['state']['text'], 'Unexpected threshold state text for "Hot": '.json_encode($entry)
		);
	}

	/**
	 * A discrete sensor with two bits asserted - the item reads the whole bitmask and discovery joins both
	 * state names with ", ".
	 *
	 * @depends testIpmiChecks_discovery
	 */
	public function testIpmiChecks_discreteMultipleStates(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.multistate');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'ipmi.multistate is unexpectedly unsupported: '.
				$item['error']
		);
		$this->assertEquals(self::IPMI_ENTITY_PRESENT | self::IPMI_ENTITY_ABSENT, (int) $item['lastvalue'],
				'ipmi.multistate should have bits 0 and 1 asserted'
		);

		[$entries, $raw] = $this->getDiscoveryEntriesById('MultiState');
		$this->assertCount(1, $entries, 'Expected exactly one "MultiState" discovery entry: '.$raw);
		$entry = $entries[0];

		$this->assertEquals(self::IPMI_ENTITY_PRESENT | self::IPMI_ENTITY_ABSENT, $entry['state']['state'],
				'Unexpected state for "MultiState": '.json_encode($entry)
		);
		$this->assertSame('entity present, entity absent', $entry['state']['text'],
				'Unexpected state text for "MultiState": '.json_encode($entry)
		);
	}

	/**
	 * Two sensors with the same id string, the discrete one first: an item addressing them by id must read the
	 * threshold one (zbx_get_ipmi_sensor_by_id() keeps scanning past a non-threshold match), and discovery
	 * must list both rather than collapse them.
	 *
	 * @depends testIpmiChecks_discovery
	 */
	public function testIpmiChecks_duplicateSensorId(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.dup');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'ipmi.dup is unexpectedly unsupported: '.
				$item['error']
		);
		// 33 is the threshold "Dup", the discrete one would read as 1.
		$this->assertEqualsWithDelta(33, (float) $item['lastvalue'], 0.001,
				'An item addressing a duplicated sensor id should read the threshold sensor'
		);

		[$entries, $raw] = $this->getDiscoveryEntriesById('Dup');
		$this->assertCount(2, $entries, 'Discovery should list both "Dup" sensors: '.$raw);

		$reading_types = array_map(function(array $entry) {
			return (int) $entry['reading']['type'];
		}, $entries);
		sort($reading_types);
		$this->assertSame([self::IPMI_READING_TYPE_THRESHOLD, self::IPMI_READING_TYPE_SENSOR_SPECIFIC], $reading_types,
				'Expected one threshold and one sensor-specific "Dup" sensor: '.$raw
		);
	}

	/**
	 * Sensor/control lookup edge cases that must not resolve to an existing sensor.
	 *
	 * @return array<int, array>
	 */
	public function sensorNotFoundDataProvider(): array {
		return [
			['ipmi.temp.lowercase'],
			['ipmi.temp.nameprefix'],
			['ipmi.longid.shorter'],
			['ipmi.longid.longer'],
			['ipmi.eventonly']
		];
	}

	/**
	 * @dataProvider sensorNotFoundDataProvider
	 */
	public function testIpmiChecks_sensorNotFound(string $key): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':'.$key);

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'], $key.' should be unsupported, got value: '.
				$item['lastvalue']
		);
		$this->assertStringContainsString('does not exist', $item['error']);
	}

	/**
	 * The "id:" prefix also applies to controls - "id:power" must read the same "power" control as the bare
	 * name does.
	 *
	 * @depends testIpmiChecks_powerControlRead
	 */
	public function testIpmiChecks_controlById(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.power.byid');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'ipmi.power.byid is unexpectedly unsupported: '.
				$item['error']
		);
		$this->assertIsNumeric($item['lastvalue'], 'ipmi.power.byid did not return a numeric value');
	}

	/**
	 * @return array<int, array>
	 */
	public function ipmiCommandDataProvider(): array {
		// [command, expected_error_substring or null on success]
		return [
			// "off" keyword branch.
			['power off', null],
			// No value at all defaults to "on".
			['power', null],
			// Tab separator, case-insensitive keyword.
			["power\tON", null],
			// A settable, but not readable control.
			['reset', null],
			// Control by its full name - zbx_set_ipmi_control_value()'s zbx_get_ipmi_control_by_full_name() branch.
			['name:(23.1).power on', null],
			// "name:" requires the full name, the bare control name must not match.
			['name:power on', 'does not exist'],
			// "id:" prefix - same lookup as the bare control name.
			['id:power on', null],
			// Control names are matched case-sensitively.
			['Power on', 'does not exist'],
			// Longest control name zbx_parse_ipmi_command() accepts (ZBX_ITEM_IPMI_SENSOR_LEN) - parsed fine,
			// then simply not found (one character more is rejected, see malformedIpmiCommandDataProvider()).
			[str_repeat('a', 128).' on', 'does not exist']
		];
	}

	/**
	 * IPMI command parsing/lookup edge cases that reach the control lookup (and, on success, the BMC).
	 *
	 * @dataProvider ipmiCommandDataProvider
	 * @depends testIpmiChecks_powerControlSet
	 */
	public function testIpmiChecks_ipmiCommand(string $command, ?string $expected_error): void {
		$response = $this->executeIpmiCommand($command);

		if ($expected_error === null) {
			$this->assertArrayHasKey('result', $response, 'script.execute("'.$command.'") failed: '.
					json_encode($response)
			);
			$this->assertEquals('success', $response['result']['response'],
					'script.execute("'.$command.'") did not report success: '.json_encode($response['result'])
			);
		}
		else {
			$this->assertArrayHasKey('error', $response, 'script.execute("'.$command.'") should fail: '.
					json_encode($response)
			);
			$this->assertStringContainsString($expected_error, $response['error']['data'],
					'Unexpected error for command "'.$command.'": '.json_encode($response['error'])
			);
		}
	}

	/**
	 * Create a throwaway IPMI script with the given command and execute it against HOST_NORMAL.
	 *
	 * @param string $command
	 *
	 * @return array  raw script.execute response
	 */
	private function executeIpmiCommand(string $command): array {
		$response = $this->call('script.create', [
			'name' => 'Test IPMI command '.md5($command),
			'type' => ZBX_SCRIPT_TYPE_IPMI,
			'scope' => ZBX_SCRIPT_SCOPE_HOST,
			'command' => $command,
			'host_access' => PERM_READ
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		$scriptid = $response['result']['scriptids'][0];
		self::$scriptids['command_'.md5($command)] = $scriptid;

		return CAPIHelper::call('script.execute', [
			'scriptid' => $scriptid,
			'hostid' => self::$hostids[self::HOST_NORMAL]
		]);
	}

	/**
	 * @return array<int, array>
	 */
	public function malformedIpmiCommandDataProvider(): array {
		// [command, expected_error_substring]
		return [
			['power notanumber', 'IPMI command value is not supported'],
			[' ', 'IPMI command is empty'],
			[str_repeat('a', 130).' on', 'IPMI command is too long'],
			// One past the longest accepted control name (ZBX_ITEM_IPMI_SENSOR_LEN).
			[str_repeat('a', 129).' on', 'IPMI command is too long']
		];
	}

	/**
	 * zbx_parse_ipmi_command() runs entirely locally (before any poller/network involvement), so these
	 * resolve instantly regardless of ipmi_sim/connection state - each case builds and executes its own
	 * throwaway script rather than reusing one from prepareData(), since the malformed command itself is
	 * the thing under test.
	 *
	 * @dataProvider malformedIpmiCommandDataProvider
	 */
	public function testIpmiChecks_malformedIpmiCommand(string $command, string $expected_error): void {
		$response = $this->executeIpmiCommand($command);

		$this->assertArrayHasKey('error', $response, 'script.execute with command "'.$command.'" should fail: '.
				json_encode($response)
		);
		$this->assertStringContainsString($expected_error, $response['error']['data'],
				'Unexpected error for command "'.$command.'": '.json_encode($response['error'])
		);
	}

	/**
	 * @return array<int, array>
	 */
	public function sensorValueDataProvider(): array {
		// [item key, expected value]
		return [
			// Negative and fractional readings - get_value_ipmi() formats the double OpenIPMI converted.
			['ipmi.negative', -5],
			['ipmi.fraction', 12.5],
			// Longest possible sensor id - zbx_get_sensor_id()/zbx_sensor_id_to_str() buffers.
			['ipmi.longid', 16],
			// Bits 0-2 are asserted, but bit 2 is not readable - zbx_got_discrete_states_cb() must only take
			// the bits ipmi_sensor_discrete_event_readable() allows.
			['ipmi.masked', self::IPMI_ENTITY_PRESENT | self::IPMI_ENTITY_ABSENT]
		];
	}

	/**
	 * @dataProvider sensorValueDataProvider
	 */
	public function testIpmiChecks_sensorValue(string $key, float $expected): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':'.$key);

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], $key.' is unexpectedly unsupported: '.$item['error']);
		$this->assertEqualsWithDelta($expected, (float) $item['lastvalue'], abs($expected) * 1e-9 + 0.001,
				$key.' value mismatch'
		);
	}

	/**
	 * @return array<int, array>
	 */
	public function itemErrorDataProvider(): array {
		// [item key, expected error]
		return [
			// The reading type string is the one cached when the sensor was added.
			['ipmi.unsupported', 'Cannot read sensor "Unsupported". IPMI reading type "invalid" is not supported'],
			// Control readability is cached when the control is added.
			['ipmi.reset', 'control is not readable'],
			// Readings flagged "scanning disabled", threshold and discrete.
			['ipmi.noscan', 'sensor data is not available'],
			['ipmi.noscandisc', 'sensor data is not available']
		];
	}

	/**
	 * Sensors and controls that exist, but cannot be read.
	 *
	 * @dataProvider itemErrorDataProvider
	 */
	public function testIpmiChecks_itemError(string $key, string $expected_error): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':'.$key);

		$this->assertEquals(ITEM_STATE_NOTSUPPORTED, $item['state'], $key.' should be unsupported, got value: '.
				$item['lastvalue']
		);
		$this->assertSame($expected_error, $item['error'], 'Unexpected error for '.$key);
	}

	/**
	 * "name:" prefix on a control - zbx_get_ipmi_control_by_full_name() success path.
	 *
	 * @depends testIpmiChecks_powerControlRead
	 */
	public function testIpmiChecks_controlByFullName(): void {
		$item = $this->getItemResult(self::HOST_NORMAL.':ipmi.power.byname');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'ipmi.power.byname is unexpectedly unsupported: '.
				$item['error']
		);
		$this->assertIsNumeric($item['lastvalue'], 'ipmi.power.byname did not return a numeric value');
	}

	/**
	 * Single discovery entry with the given id.
	 *
	 * @param string $id
	 * @param string $host
	 *
	 * @return array
	 */
	private function getDiscoveryEntry(string $id, string $host = self::HOST_NORMAL): array {
		[$entries, $raw] = $this->getDiscoveryEntriesById($id, $host);
		$this->assertCount(1, $entries, 'Expected exactly one "'.$id.'" discovery entry: '.$raw);

		return $entries[0];
	}

	/**
	 * Sensor properties reported by discovery - type, reading type, units and state names are cached when the
	 * sensor is added.
	 *
	 * @depends testIpmiChecks_discovery
	 */
	public function testIpmiChecks_discoveryProperties(): void {
		$temp = $this->getDiscoveryEntry('Temp');
		$this->assertEquals(['type' => self::IPMI_SENSOR_TYPE_TEMPERATURE, 'text' => 'temperature'], $temp['sensor'],
				json_encode($temp)
		);
		$this->assertEquals(['type' => self::IPMI_READING_TYPE_THRESHOLD, 'text' => 'threshold'], $temp['reading'],
				json_encode($temp)
		);
		$this->assertSame('C', $temp['units'], json_encode($temp));
		$this->assertEqualsWithDelta(50, $temp['value'], 0.001, json_encode($temp));

		$presence = $this->getDiscoveryEntry('Presence');
		$this->assertEquals(['type' => self::IPMI_SENSOR_TYPE_ENTITY_PRESENCE, 'text' => 'entity_presence'],
				$presence['sensor'], json_encode($presence)
		);
		$this->assertEquals(['type' => self::IPMI_READING_TYPE_SENSOR_SPECIFIC, 'text' => 'sensor specific'],
				$presence['reading'], json_encode($presence)
		);
		$this->assertEquals(['state' => self::IPMI_ENTITY_PRESENT, 'text' => 'entity present'], $presence['state'],
				json_encode($presence)
		);
		$this->assertArrayNotHasKey('units', $presence, json_encode($presence));
		$this->assertArrayNotHasKey('threshold', $presence, json_encode($presence));

		// Bit 2 is asserted, but not readable - neither in the state nor in its text.
		$masked = $this->getDiscoveryEntry('Masked');
		$this->assertEquals(['state' => self::IPMI_ENTITY_PRESENT | self::IPMI_ENTITY_ABSENT,
			'text' => 'entity present, entity absent'
		], $masked['state'],
				json_encode($masked)
		);

		// Rate unit, and the other "base OP modifier" operator than "WattsHour" (see testIpmiChecks_discovery()).
		$rate = $this->getDiscoveryEntry('Rate');
		$this->assertSame('C/sec', $rate['units'], json_encode($rate));
		$mul = $this->getDiscoveryEntry('MulUnit');
		$wph = $this->getDiscoveryEntry('WattsHour');
		$this->assertNotSame($wph['units'], $mul['units'], json_encode([$wph, $mul]));
		$this->assertSame(['*', '/'], [strpbrk($wph['units'], '*/')[0], strpbrk($mul['units'], '*/')[0]],
				json_encode([$wph, $mul])
		);

		// An infinite threshold is left out, a finite one of the same sensor is kept.
		$inf = $this->getDiscoveryEntry('InfThr');
		$this->assertEquals(['lower' => ['non_crit' => 1], 'upper' => []], $inf['threshold'], json_encode($inf));
		$this->assertEqualsWithDelta(1e10, $inf['value'], 1, json_encode($inf));

		$this->assertEqualsWithDelta(-5, $this->getDiscoveryEntry('Negative')['value'], 0.001);
		$this->assertEqualsWithDelta(12.5, $this->getDiscoveryEntry('Fraction')['value'], 0.001);
		$this->assertEqualsWithDelta(16, $this->getDiscoveryEntry('SixteenCharIdStr')['value'], 0.001);

		// Readings with scanning disabled, event-only sensors (never added) and unsupported reading types are
		// left out.
		foreach (['NoScan', 'NoScanDisc', 'EventOnly', 'Unsupported'] as $id) {
			[$entries, $raw] = $this->getDiscoveryEntriesById($id);
			$this->assertSame([], $entries, '"'.$id.'" should not be discovered: '.$raw);
		}

		// Every readable, supported sensor of the fixture, each once - "Dup" twice.
		[, $raw] = $this->getDiscoveryEntriesById('');
		$ids = array_count_values(array_column(json_decode($raw, true), 'id'));
		ksort($ids);
		$this->assertSame(['Dup' => 2, 'Fraction' => 1, 'Hot' => 1, 'InfThr' => 1, 'Masked' => 1,
			'MulUnit' => 1, 'MultiState' => 1, 'Negative' => 1, 'NoThresh' => 1, 'Percent' => 1, 'Presence' => 1,
			'Rate' => 1, 'SixteenCharIdStr' => 1, 'Temp' => 1, 'WattsHour' => 1
		], $ids, 'Unexpected discovered sensors: '.$raw);
	}

	/**
	 * Both "Dup" sensors have the same id string, so only their OpenIPMI sensor ids tell them apart when the
	 * reading callbacks look them up - each must get its own value.
	 *
	 * @depends testIpmiChecks_duplicateSensorId
	 */
	public function testIpmiChecks_duplicateSensorIdValues(): void {
		[$entries, $raw] = $this->getDiscoveryEntriesById('Dup');
		$this->assertCount(2, $entries, 'Discovery should list both "Dup" sensors: '.$raw);

		foreach ($entries as $entry) {
			if ($entry['reading']['type'] == self::IPMI_READING_TYPE_THRESHOLD) {
				$this->assertEqualsWithDelta(33, $entry['value'], 0.001, 'Threshold "Dup": '.$raw);
				$this->assertSame('C', $entry['units'], 'Threshold "Dup": '.$raw);
			}
			else {
				$this->assertEquals(['state' => self::IPMI_ENTITY_PRESENT, 'text' => 'entity present'], $entry['state'],
						'Discrete "Dup": '.$raw
				);
			}
		}
	}

	/**
	 * @return array<int, array>
	 */
	public function credentialsDataProvider(): array {
		return [
			[self::HOST_ANON],
			[self::HOST_ALTAUTH]
		];
	}

	/**
	 * Other credentials for the same BMC as HOST_NORMAL - a separate zbx_ipmi_host_t and OpenIPMI domain, whose
	 * callbacks must be routed to it, not to another host of the same IPMI poller.
	 *
	 * @dataProvider credentialsDataProvider
	 */
	public function testIpmiChecks_credentials(string $host): void {
		$item = $this->getItemResult($host.':ipmi.temp');

		$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'ipmi.temp on '.$host.' is unexpectedly unsupported: '.
				$item['error']
		);
		$this->assertEqualsWithDelta(50, (float) $item['lastvalue'], 0.001);
	}

	/**
	 * A BMC that rejects the credentials or does not answer at all: OpenIPMI reports the connection failure to
	 * zbx_connection_change_cb(), which closes the domain - the item must fail with a network error (interface
	 * deactivated, item state left as is), not become unsupported, and the IPMI poller must neither hang nor crash
	 * when the domain of the same zbx_ipmi_host_t is reopened on the next poll. Both hosts are checked at once,
	 * as each of them takes a while to become unavailable.
	 */
	public function testIpmiChecks_connectionFailure(): void {
		$hosts = [self::HOST_BADPASS, self::HOST_CLOSEDPORT];
		$hostids = array_map(function(string $host) {
			return self::$hostids[$host];
		}, $hosts);

		$this->setHostsStatus($hostids, HOST_STATUS_MONITORED);

		try {
			$this->reloadConfigurationCache(self::COMPONENT_SERVER);

			$interfaceids = [];
			foreach ($hosts as $host) {
				$interfaceids[] = self::$interfaceids[$host];
			}

			$response = $this->callUntilDataIsPresent('hostinterface.get', [
				'output' => ['interfaceid', 'available', 'error'],
				'interfaceids' => $interfaceids
			], 90, 1, function(array $response) {
				foreach ($response['result'] as $interface) {
					if ($interface['available'] != INTERFACE_AVAILABLE_FALSE || $interface['error'] === '') {
						return 'waiting for the interfaces to become unavailable, got: '.
								json_encode($response['result']);
					}
				}

				return true;
			});

			foreach ($response['result'] as $interface) {
				$this->assertStringContainsStringIgnoringCase('cannot connect to IPMI host', $interface['error'],
						json_encode($interface)
				);
			}

			foreach ($hosts as $host) {
				$item = $this->call('item.get', [
					'output' => ['lastclock', 'state', 'error'],
					'itemids' => [self::$itemids[$host.':ipmi.temp']]
				])['result'][0];
				$this->assertEquals(ITEM_STATE_NORMAL, $item['state'], 'A network error must not make the item on '.
						$host.' unsupported: '.$item['error']
				);
				$this->assertEquals(0, $item['lastclock'], 'No value should have been collected on '.$host);

				// Setting a control fails the same way - zbx_set_ipmi_control_value() with the domain down.
				$response = $this->call('script.execute', [
					'scriptid' => self::$scriptids['power_on'],
					'hostid' => self::$hostids[$host]
				], true);
				$this->assertStringContainsStringIgnoringCase('cannot connect to IPMI host',
						$response['error']['data'], json_encode($response['error'])
				);
			}

			// The other hosts are still polled.
			$start = time();
			$this->waitForItem(self::HOST_NORMAL.':ipmi.temp', function(array $item) use ($start) {
				return ($item['state'] == ITEM_STATE_NORMAL && $item['lastclock'] >= $start)
					? true
					: 'waiting for a fresh value, got state:'.$item['state'].' error:"'.$item['error'].'"';
			}, 60);
		}
		finally {
			$this->setHostsStatus($hostids, HOST_STATUS_NOT_MONITORED);
			$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		}
	}

	/**
	 * IPMI item test (frontend "Test" button) - values requested by a client instead of the IPMI manager's own
	 * queue, against a host the IPMI pollers may not have seen yet.
	 *
	 * @return array<int, array>
	 */
	public function itemTestDataProvider(): array {
		// [ipmi_sensor, expected value (null - error expected), expected error]
		return [
			['Temp', 50, null],
			['Fraction', 12.5, null],
			['Masked', 3, null],
			['does-not-exist', null, 'does not exist'],
			['reset', null, 'control is not readable'],
			['NoScan', null, 'sensor data is not available']
		];
	}

	/**
	 * @dataProvider itemTestDataProvider
	 */
	public function testIpmiChecks_itemTest(string $sensor, ?float $expected, ?string $expected_error): void {
		$result = $this->testIpmiItem($sensor);

		if ($expected !== null) {
			$this->assertArrayNotHasKey('error', $result['item'], 'Item test failed: '.json_encode($result));
			$this->assertEqualsWithDelta($expected, (float) $result['item']['result'], 0.001, json_encode($result));
		}
		else {
			$this->assertArrayHasKey('error', $result['item'], 'Item test should fail: '.json_encode($result));
			$this->assertStringContainsString($expected_error, $result['item']['error'], json_encode($result));
		}
	}

	/**
	 * Item test of "ipmi.get" (discovery) - also exercises get_discovery_ipmi() through a client request.
	 */
	public function testIpmiChecks_itemTestDiscovery(): void {
		$result = $this->testIpmiItem('', 'ipmi.get', ITEM_VALUE_TYPE_TEXT);

		$this->assertArrayNotHasKey('error', $result['item'], 'Item test failed: '.json_encode($result));
		$json = json_decode($result['item']['result'], true);
		$this->assertIsArray($json, 'Not a JSON array: '.$result['item']['result']);
		$this->assertContains('Temp', array_column($json, 'id'), $result['item']['result']);
	}

	/**
	 * Send an "item.test" request for an IPMI item against the HOST_NORMAL BMC.
	 *
	 * @param string $sensor
	 * @param string $key
	 * @param int    $value_type
	 *
	 * @return array  "item.test" response data
	 */
	private function testIpmiItem(string $sensor, string $key = 'ipmi.test', int $value_type = ITEM_VALUE_TYPE_FLOAT):
			array {
		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$server = new CZabbixServer('localhost', $this->getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				30, 30, ZBX_SOCKET_BYTES_LIMIT, tls_config: ['ACTIVE' => false]
		);

		// The first request may make an IPMI poller open an OpenIPMI domain, which is not ready if the simulator does
		// not answer in time (seen right after the server start) - the request is then answered with an error or
		// with "no value". So the BMC is first required to answer one request, once per suite, before any case is
		// checked.
		if (!self::$item_test_ready) {
			for ($i = 0; $i < 30; $i++) {
				$result = $this->sendIpmiItemTest($server, 'Temp', 'ipmi.test', ITEM_VALUE_TYPE_FLOAT);

				if (is_array($result) && !array_key_exists('error', $result['item'])
						&& $result['item']['result'] !== 'no value') {
					break;
				}

				sleep(1);
			}

			self::$item_test_ready = true;
		}

		$result = $this->sendIpmiItemTest($server, $sensor, $key, $value_type);

		$this->assertIsArray($result, 'item.test failed: '.$server->getError());
		$this->assertArrayHasKey('item', $result, json_encode($result));

		return $result;
	}

	/**
	 * @param CZabbixServer $server
	 * @param string        $sensor
	 * @param string        $key
	 * @param int           $value_type
	 *
	 * @return array|bool  "item.test" response data
	 */
	private function sendIpmiItemTest(CZabbixServer $server, string $sensor, string $key, int $value_type) {
		return $server->testItem([
			'item' => [
				'type' => ITEM_TYPE_IPMI,
				'key' => $key,
				'ipmi_sensor' => $sensor,
				'value_type' => $value_type
			],
			'host' => [
				'tls_connect' => HOST_ENCRYPTION_NONE,
				'proxyid' => '0',
				'ipmi_authtype' => IPMI_AUTHTYPE_MD5,
				'ipmi_privilege' => IPMI_PRIVILEGE_ADMIN,
				'ipmi_username' => self::SIM_USERNAME,
				'ipmi_password' => self::SIM_PASSWORD,
				'interface' => [
					'address' => self::SIM_IP,
					'port' => self::simPort(),
					'useip' => INTERFACE_USE_IP,
					'type' => INTERFACE_TYPE_IPMI
				]
			]
		], CAPIHelper::getSessionId());
	}

	/**
	 * Poll an item until $callback accepts it.
	 *
	 * @param string   $key       fully qualified "host:key" identifier used in self::$itemids
	 * @param callable $callback  receives the item.get row, returns true or a "still waiting" reason string
	 * @param int      $timeout   seconds
	 *
	 * @return array  item.get result row (lastvalue, lastclock, state, error)
	 */
	private function waitForItem(string $key, callable $callback, int $timeout): array {
		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['lastvalue', 'lastclock', 'state', 'error'],
			'itemids' => [self::$itemids[$key]]
		], $timeout, 1, function(array $response) use ($callback) {
			return $callback($response['result'][0]);
		});

		return $response['result'][0];
	}

	/**
	 * When the BMC connection drops while discovery reads the sensors one by one, OpenIPMI closes the domain and
	 * deletes every sensor in the middle of the loop. Discovery must then fail with a network error - returning
	 * the sensors read so far (or none) as a successful value would make LLD treat all the others as lost.
	 *
	 * The connection loss is noticed by whatever OpenIPMI operation an IPMI poller performs at that moment - for
	 * any of its hosts, or while waiting for the next request. So all other hosts are disabled meanwhile and
	 * HOST_DISCOVERY_DROP keeps discoveries running back to back, for the loss to be noticed during a discovery.
	 */
	public function testIpmiChecks_discoveryConnectionLoss(): void {
		$response = $this->call('host.get', [
			'output' => ['hostid'],
			'hostids' => array_values(self::$hostids),
			'filter' => ['status' => HOST_STATUS_MONITORED]
		]);
		$other_hostids = array_column($response['result'], 'hostid');
		$drop_hostid = self::$hostids[self::HOST_DISCOVERY_DROP];

		$this->setHostsStatus($other_hostids, HOST_STATUS_NOT_MONITORED);
		$this->setHostsStatus([$drop_hostid], HOST_STATUS_MONITORED);

		try {
			$this->reloadConfigurationCache(self::COMPONENT_SERVER);

			$sensor_count = null;
			$time_from = null;

			foreach (self::DISCOVERY_DROP_KEYS as $key) {
				$item = $this->waitForItem(self::HOST_DISCOVERY_DROP.':'.$key, function(array $item) {
					$json = ($item['state'] == ITEM_STATE_NORMAL) ? json_decode($item['lastvalue'], true) : null;

					return (is_array($json) && $json !== [])
						? true
						: 'waiting for discovery, got state:'.$item['state'].' value:"'.$item['lastvalue'].
							'" error:"'.$item['error'].'"';
				}, 60);

				$sensor_count = count(json_decode($item['lastvalue'], true));
				// Every value collected before the drop is complete, so the values are checked starting from the
				// complete ones - the drop may be noticed within the same second.
				$time_from = ($time_from === null) ? $item['lastclock'] : min($time_from, $item['lastclock']);
			}

			self::ipmiSimStop(self::SIM_DISCOVERY_DROP_IP);

			$lines = [];
			foreach (self::DISCOVERY_DROP_KEYS as $key) {
				$lines[] = 'IPMI agent item "'.$key.'" on host "'.self::HOST_DISCOVERY_DROP.
						'" failed: first network error';
			}
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $lines, true, 90, 1);

			// Values are written to history asynchronously and none is expected, so there is nothing to poll for.
			sleep(3);

			$itemids = [];
			foreach (self::DISCOVERY_DROP_KEYS as $key) {
				$itemids[] = self::$itemids[self::HOST_DISCOVERY_DROP.':'.$key];
			}

			$response = $this->call('history.get', [
				'output' => ['itemid', 'clock', 'value'],
				'history' => ITEM_VALUE_TYPE_TEXT,
				'itemids' => $itemids,
				'time_from' => $time_from
			]);

			foreach ($response['result'] as $value) {
				$json = json_decode($value['value'], true);

				$this->assertFalse(is_array($json) && count($json) < $sensor_count,
						'Discovery returned '.count($json).' of '.$sensor_count.' sensors as a successful value '.
						'after the BMC connection was lost: '.$value['value']
				);
			}
		}
		finally {
			$this->setHostsStatus([$drop_hostid], HOST_STATUS_NOT_MONITORED);
			$this->setHostsStatus($other_hostids, HOST_STATUS_MONITORED);
			$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		}
	}

	/**
	 * @param array $hostids
	 * @param int   $status
	 */
	private function setHostsStatus(array $hostids, int $status): void {
		if ($hostids) {
			$this->call('host.update', array_map(function($hostid) use ($status) {
				return ['hostid' => $hostid, 'status' => $status];
			}, array_values($hostids)));
		}
	}

	/**
	 * OpenIPMI sensor/control objects are only valid inside OpenIPMI callbacks. When the BMC connection
	 * drops, OpenIPMI closes the domain and destroys every sensor and control (IPMI_DELETED), and recreates them
	 * once the domain is reopened on the same zbx_ipmi_host_t. Everything Zabbix keeps between polls (sensor and
	 * control lookups, readings, control setting, callback data) must survive that, so this kills the dedicated
	 * BMC mid-session and restarts it twice - first without the "Presence" sensor, then with it again - with a
	 * different "Temp" reading each time, and requires every kind of IPMI check on the host to recover with fresh
	 * data - a dangling pointer would crash the poller or keep returning stale data.
	 *
	 * Kept last: it is the slowest test here (OpenIPMI needs ~30s to notice the dead connection) and its
	 * simulator is not shared with any other host.
	 */
	public function testIpmiChecks_reconnect(): void {
		$value_is = function(float $expected) {
			return function(array $item) use ($expected) {
				return ($item['state'] == ITEM_STATE_NORMAL && $item['lastclock'] != 0
						&& abs((float) $item['lastvalue'] - $expected) < 0.001)
					? true
					: 'waiting for value '.$expected.', got state:'.$item['state'].' value:"'.
						$item['lastvalue'].'" error:"'.$item['error'].'"';
			};
		};

		// Connection is up and all three kinds of checks work before the drop.
		$this->waitForItem(self::HOST_RECONNECT.':ipmi.temp', $value_is(50), 30);
		$this->waitForItem(self::HOST_RECONNECT.':ipmi.presence', $value_is(self::IPMI_ENTITY_PRESENT), 30);
		$this->waitForItem(self::HOST_RECONNECT.':ipmi.power', function(array $item) {
			return ($item['state'] == ITEM_STATE_NORMAL && $item['lastclock'] != 0) ? true : 'no power reading yet';
		}, 30);
		$discovery_before = $this->waitForReconnectDiscovery(50);

		// The BMC comes back without the "Presence" sensor and with a different "Temp" reading: every sensor and
		// control is deleted and recreated, except "Presence", whose entry must be gone with the deleted sensor -
		// not left pointing at a sensor id OpenIPMI no longer knows (which would fail to read instead).
		$this->dropReconnectBmc();
		self::ipmiSimStart(self::SIM_RECONNECT_IP, [self::SIM_TEMP_SENSOR_KEY => 60], ['Presence']);

		$this->waitForItem(self::HOST_RECONNECT.':ipmi.temp', $value_is(60), 120);
		$this->waitForItem(self::HOST_RECONNECT.':ipmi.presence', function(array $item) {
			return ($item['state'] == ITEM_STATE_NOTSUPPORTED && strpos($item['error'], 'does not exist') !== false)
				? true
				: 'waiting for "Presence" to disappear, got state:'.$item['state'].' error:"'.$item['error'].'"';
		}, 60);

		$power_clock = time();
		$this->waitForItem(self::HOST_RECONNECT.':ipmi.power', function(array $item) use ($power_clock) {
			return ($item['state'] == ITEM_STATE_NORMAL && $item['lastclock'] >= $power_clock)
				? true
				: 'waiting for a fresh power reading, got state:'.$item['state'].' error:"'.$item['error'].'"';
		}, 60);

		// Setting a control goes through the recreated control as well.
		$response = $this->call('script.execute', [
			'scriptid' => self::$scriptids['power_on'],
			'hostid' => self::$hostids[self::HOST_RECONNECT]
		]);

		$this->assertEquals('success', $response['result']['response'],
				'script.execute("power on") after reconnect did not report success: '.json_encode($response['result'])
		);

		// Discovery is built from the sensor properties cached when the recreated sensors were added: the same
		// sensors, each once (none left over from before the drop), with the same properties and the new value.
		$discovery_reduced = $this->waitForReconnectDiscovery(60);
		$expected = self::discoverySummary($discovery_before);
		unset($expected['Presence']);
		$this->assertSame($expected, self::discoverySummary($discovery_reduced),
				'Discovery after the "Presence" sensor was removed is unexpected'
		);

		// The BMC comes back with the "Presence" sensor again - OpenIPMI gives it a new sensor id, which the item and
		// discovery must find and read like any other sensor added on reconnect.
		$this->dropReconnectBmc();
		self::ipmiSimStart(self::SIM_RECONNECT_IP, [self::SIM_TEMP_SENSOR_KEY => 55]);

		$this->waitForItem(self::HOST_RECONNECT.':ipmi.temp', $value_is(55), 120);
		$this->waitForItem(self::HOST_RECONNECT.':ipmi.presence', $value_is(self::IPMI_ENTITY_PRESENT), 60);

		$discovery_restored = $this->waitForReconnectDiscovery(55);
		$this->assertSame(self::discoverySummary($discovery_before), self::discoverySummary($discovery_restored),
				'Discovery after the "Presence" sensor was restored differs from the original one'
		);
	}

	/**
	 * Stop the testIpmiChecks_reconnect() BMC and wait until the server notices.
	 */
	private function dropReconnectBmc(): void {
		self::ipmiSimStop(self::SIM_RECONNECT_IP);

		$lines = [];
		foreach (['ipmi.temp', 'ipmi.presence', 'ipmi.power', 'ipmi.get'] as $key) {
			$lines[] = 'IPMI agent item "'.$key.'" on host "'.self::HOST_RECONNECT.'" failed: first network error';
		}
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $lines, true, 90, 1);
	}

	/**
	 * Wait for a discovery value of HOST_RECONNECT reporting the given "Temp" reading.
	 *
	 * @param float $temp
	 *
	 * @return array  decoded discovery value
	 */
	private function waitForReconnectDiscovery(float $temp): array {
		$item = $this->waitForItem(self::HOST_RECONNECT.':ipmi.get', function(array $item) use ($temp) {
			$json = ($item['state'] == ITEM_STATE_NORMAL) ? json_decode($item['lastvalue'], true) : null;

			if (is_array($json)) {
				foreach ($json as $entry) {
					if ($entry['id'] === 'Temp' && abs($entry['value'] - $temp) < 0.001) {
						return true;
					}
				}
			}

			return 'waiting for discovery with "Temp" '.$temp.', got state:'.$item['state'].' value:"'.
				$item['lastvalue'].'" error:"'.$item['error'].'"';
		}, 120);

		return json_decode($item['lastvalue'], true);
	}

	/**
	 * Discovery entries without their readings, keyed by id - entries with the same id are listed together.
	 *
	 * @param array $discovery  decoded discovery value
	 *
	 * @return array
	 */
	private static function discoverySummary(array $discovery): array {
		$summary = [];

		foreach ($discovery as $entry) {
			unset($entry['value']);
			$summary[$entry['id']][] = json_encode($entry);
		}

		foreach ($summary as &$entries) {
			sort($entries);
		}
		unset($entries);

		ksort($summary);

		return $summary;
	}
}
