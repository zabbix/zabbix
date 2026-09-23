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

require_once __DIR__.'/../include/CIntegrationTest.php';

class CReportTestClient extends CZabbixClient {

	public function sendRequest(array $data, string $sessionid) {
		return parent::request([
			'request' => 'report.test',
			'sid' => $sessionid,
			'data' => $data
		]);
	}
}

/**
 * Test dashboard permissions for report.test requests sent directly to the server.
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testReportTest extends CIntegrationTest {

	private const PASSWORD = 'ReportTest123!';
	private static $userids = [];
	private static $sessions = [];
	private static $dashboardids = [];
	private static $groupids = [];
	private static $roleids = [];
	private static $frontend_url;

	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'StartReportWriters' => 1,
				'FrontendAllowedIP' => '127.0.0.1',
				'WebServiceURL' => 'http://127.0.0.1:1/report'
			]
		];
	}

	public function prepareData() {
		$response = $this->call('settings.get', ['output' => ['url']]);
		self::$frontend_url = $response['result']['url'];

		$response = $this->call('usergroup.create', [
			['name' => 'Report test users'],
			['name' => 'Report test dashboard readers']
		]);
		self::$groupids = $response['result']['usrgrpids'];

		foreach (['reader' => USER_TYPE_ZABBIX_USER, 'owner' => USER_TYPE_ZABBIX_ADMIN,
				'superadmin' => USER_TYPE_SUPER_ADMIN] as $name => $type) {
			$response = $this->call('role.create', ['name' => 'Report test '.$name, 'type' => $type]);
			self::$roleids[$name] = $response['result']['roleids'][0];
			$response = $this->call('user.create', [
				'username' => 'report_test_'.$name,
				'passwd' => self::PASSWORD,
				'roleid' => $response['result']['roleids'][0],
				'usrgrps' => array_map(static fn($id) => ['usrgrpid' => $id], self::$groupids)
			]);
			self::$userids[$name] = $response['result']['userids'][0];

			$response = $this->callRaw(json_encode([
				'jsonrpc' => '2.0',
				'method' => 'user.login',
				'params' => ['username' => 'report_test_'.$name, 'password' => self::PASSWORD],
				'id' => 1
			]));
			$this->checkResult($response);
			self::$sessions[$name] = $response['result'];
		}

		$response = $this->callRaw(json_encode([
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => ['username' => PHPUNIT_LOGIN_NAME, 'password' => PHPUNIT_LOGIN_PWD],
			'id' => 1
		]));
		$this->checkResult($response);
		self::$sessions['inactive'] = $response['result'];
		$response = $this->callRaw(json_encode([
			'jsonrpc' => '2.0',
			'method' => 'user.logout',
			'params' => [],
			'id' => 1
		]), self::$sessions['inactive']);
		$this->checkResult($response);
		self::$sessions['unknown'] = bin2hex(random_bytes(16));
		self::$sessions['malformed'] = 'invalid';

		$dashboard_names = [
			'own', 'private', 'public', 'user_read', 'user_write', 'group_read', 'group_write'
		];

		foreach ($dashboard_names as $name) {
			$dashboard = [
				'name' => 'Report test '.$name,
				'userid' => self::$userids[$name === 'own' ? 'reader' : 'owner'],
				'private' => $name === 'public' ? PUBLIC_SHARING : PRIVATE_SHARING,
				'pages' => [[]]
			];
			if (in_array($name, ['user_read', 'user_write'])) {
				$dashboard['users'] = [[
					'userid' => self::$userids['reader'],
					'permission' => $name === 'user_read' ? PERM_READ : PERM_READ_WRITE
				]];
			}
			if (in_array($name, ['group_read', 'group_write'])) {
				$dashboard['userGroups'] = [[
					'usrgrpid' => self::$groupids[1],
					'permission' => $name === 'group_read' ? PERM_READ : PERM_READ_WRITE
				]];
			}
			$response = $this->call('dashboard.create', $dashboard);
			self::$dashboardids[$name] = $response['result']['dashboardids'][0];
		}
		self::$dashboardids['missing'] = '0';

		// A nonempty URL allows job creation; no frontend or SMTP service is needed.
		$this->call('settings.update', ['url' => 'http://127.0.0.1/report-test']);
	}

	public static function dashboardAccessProvider() {
		return [
			'private dashboard denied' => ['reader', 'private', false, 'reader'],
			'spoof dashboard owner' => ['reader', 'private', false, 'owner'],
			'spoof superadmin' => ['reader', 'private', false, 'superadmin'],
			'private dashboard without userid' => ['reader', 'private', false, null],
			'own dashboard' => ['reader', 'own', true, null],
			'public dashboard' => ['reader', 'public', true, null],
			'user read sharing' => ['reader', 'user_read', true, null],
			'user write sharing' => ['reader', 'user_write', true, null],
			'group read sharing' => ['reader', 'group_read', true, null],
			'group write sharing' => ['reader', 'group_write', true, null],
			'legacy userid accepted' => ['reader', 'public', true, 'reader'],
			'spoofed userid ignored' => ['reader', 'public', true, 'owner'],
			'admin owner' => ['owner', 'private', true, null],
			'admin without access' => ['owner', 'own', false, 'reader'],
			'superadmin' => ['superadmin', 'private', true, null],
			'missing dashboard' => ['reader', 'missing', false, 'owner']
		];
	}

	private function checkReport($session, $dashboard, $error, $viewer = null) {
		$data = [
			'name' => 'Report test permissions',
			'dashboardid' => self::$dashboardids[$dashboard],
			'period' => ZBX_REPORT_PERIOD_DAY,
			'now' => time(),
			'params' => ['subject' => 'Report test', 'body' => 'Report test']
		];
		if ($viewer !== null) {
			$data['userid'] = self::$userids[$viewer];
		}

		$client = new CReportTestClient('127.0.0.1',
			$this->getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
			5,
			10,
			ZBX_SOCKET_BYTES_LIMIT,
			['ACTIVE' => false]
		);
		$this->clearLog(self::COMPONENT_SERVER);
		$this->assertFalse($client->sendRequest($data, self::$sessions[$session]));

		if ($error !== 'Permission denied.') {
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'trapper got \'{"request":"report.test"', false, 10, 1);
		}

		$this->assertSame($error, $client->getError());

		if (str_starts_with($error, 'No media configured')) {
			$log_line = 'In rm_writer_process_job() url:http://127.0.0.1/report-test/zabbix.php'.
				'?action=dashboard.print&dashboardid='.self::$dashboardids[$dashboard];

			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $log_line, false, 10, 1);
		}
	}

	/**
	 * @dataProvider dashboardAccessProvider
	 */
	public function testReportTest_dashboardAccess($user, $dashboard, $allowed, $viewer) {
		$error = $allowed
			? "No media configured for the report recipient 'report_test_".$user."'"
			: 'no access to dashboard';
		$this->checkReport($user, $dashboard, $error, $viewer);
	}

	public function testReportTest_invalidSessions() {
		foreach (['inactive', 'unknown', 'malformed'] as $session) {
			$this->checkReport($session, 'public', 'Permission denied.', 'owner');
		}
	}

	public function testReportTest_revokedSharing() {
		$error = "No media configured for the report recipient 'report_test_reader'";

		$this->checkReport('reader', 'user_read', $error);
		$this->call('dashboard.update', ['dashboardid' => self::$dashboardids['user_read'], 'users' => []]);
		$this->checkReport('reader', 'user_read', 'no access to dashboard', 'owner');
	}

	public function testReportTest_revokedGroupMembership() {
		$error = "No media configured for the report recipient 'report_test_reader'";

		$this->checkReport('reader', 'group_read', $error);
		$this->call('user.update', [
			'userid' => self::$userids['reader'], 'usrgrps' => [['usrgrpid' => self::$groupids[0]]]
		]);
		$this->checkReport('reader', 'group_read', 'no access to dashboard', 'owner');
	}

	public static function clearData(): void {
		if (self::$dashboardids) {
			$dashboardids = array_values(array_diff_key(self::$dashboardids, ['missing' => true]));

			CDataHelper::call('dashboard.delete', $dashboardids);
		}
		if (self::$userids) {
			CDataHelper::call('user.delete', array_values(self::$userids));
		}
		if (self::$roleids) {
			CDataHelper::call('role.delete', array_values(self::$roleids));
		}
		if (self::$groupids) {
			CDataHelper::call('usergroup.delete', self::$groupids);
		}
		if (self::$frontend_url !== null) {
			CDataHelper::call('settings.update', ['url' => self::$frontend_url]);
		}
	}
}
