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

	public function sendRequest(array $data, ?string $sessionid) {
		$request = ['request' => 'report.test'];

		if ($sessionid !== null) {
			$request['sid'] = $sessionid;
		}

		return parent::request($request + ['data' => $data]);
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

		$roles = [
			'reader' => ['type' => USER_TYPE_ZABBIX_USER],
			'admin_reader' => ['type' => USER_TYPE_ZABBIX_ADMIN],
			'owner' => ['type' => USER_TYPE_ZABBIX_ADMIN],
			'superadmin' => ['type' => USER_TYPE_SUPER_ADMIN],
			'no_report_action' => [
				'type' => USER_TYPE_ZABBIX_ADMIN,
				'rules' => [
					'actions' => [['name' => 'manage_scheduled_reports', 'status' => ZBX_ROLE_RULE_DISABLED]]
				]
			],
			// Action rule equal to the default is not stored, so the server falls back to actions.default_access.
			'default_denied' => [
				'type' => USER_TYPE_ZABBIX_ADMIN,
				'rules' => [
					'actions.default_access' => ZBX_ROLE_RULE_DISABLED,
					'actions' => [['name' => 'manage_scheduled_reports', 'status' => ZBX_ROLE_RULE_DISABLED]]
				]
			],
			'report_action_only' => [
				'type' => USER_TYPE_ZABBIX_ADMIN,
				'rules' => [
					'actions.default_access' => ZBX_ROLE_RULE_DISABLED,
					'actions' => [['name' => 'manage_scheduled_reports', 'status' => ZBX_ROLE_RULE_ENABLED]]
				]
			],
			'outsider' => ['type' => USER_TYPE_ZABBIX_ADMIN],
			'role_changes' => ['type' => USER_TYPE_ZABBIX_ADMIN],
			'superadmin_no_report_action' => [
				'type' => USER_TYPE_SUPER_ADMIN,
				'rules' => [
					'actions' => [['name' => 'manage_scheduled_reports', 'status' => ZBX_ROLE_RULE_DISABLED]]
				]
			]
		];

		foreach ($roles as $name => $role) {
			$response = $this->call('role.create', ['name' => 'Report test '.$name] + $role);
			self::$roleids[$name] = $response['result']['roleids'][0];
			$response = $this->call('user.create', [
				'username' => 'report_test_'.$name,
				'passwd' => self::PASSWORD,
				'roleid' => $response['result']['roleids'][0],
				// Outsider is not a member of the group the dashboards are shared with.
				'usrgrps' => array_map(static fn($id) => ['usrgrpid' => $id],
					$name === 'outsider' ? [self::$groupids[0]] : self::$groupids
				)
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
		self::$sessions['empty'] = '';
		self::$sessions['missing'] = null;

		$tokens = [
			'token_admin_reader' => ['userid' => self::$userids['admin_reader']],
			'token_reader' => ['userid' => self::$userids['reader']],
			'token_disabled' => ['userid' => self::$userids['admin_reader'], 'status' => ZBX_AUTH_TOKEN_DISABLED],
			'token_expired' => ['userid' => self::$userids['admin_reader'], 'expires_at' => time() - SEC_PER_HOUR]
		];

		foreach ($tokens as $name => $token) {
			$response = $this->call('token.create', ['name' => 'Report test '.$name] + $token);
			$response = $this->call('token.generate', $response['result']['tokenids']);
			self::$sessions[$name] = $response['result'][0]['token'];
		}

		$dashboard_names = [
			'own', 'private', 'public', 'user_read', 'user_write', 'group_read', 'group_write'
		];

		foreach ($dashboard_names as $name) {
			$dashboard = [
				'name' => 'Report test '.$name,
				'userid' => self::$userids[$name === 'own' ? 'admin_reader' : 'owner'],
				'private' => $name === 'public' ? PUBLIC_SHARING : PRIVATE_SHARING,
				'pages' => [[]]
			];
			if (in_array($name, ['user_read', 'user_write'])) {
				$dashboard['users'] = [[
					'userid' => self::$userids['admin_reader'],
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

		$this->call('settings.update', ['url' => 'http://127.0.0.1/report-test']);
	}

	/**
	 * Users (all are members of both user groups, except outsider):
	 *   reader       - User type role, denied by the user type check before the dashboard is checked;
	 *   admin_reader - Admin, owns "own", has direct sharing on "user_*" and group sharing on "group_*";
	 *   owner        - Admin, owns all dashboards except "own";
	 *   superadmin   - Super admin, has access to all dashboards;
	 *   outsider     - Admin, member of "Report test users" only, owns nothing and has no sharing.
	 *
	 * Dashboards:
	 *   own          - private, owned by admin_reader;
	 *   private      - private, owned by owner, not shared;
	 *   public       - public, owned by owner;
	 *   user_read    - private, owned by owner, shared with admin_reader (read);
	 *   user_write   - private, owned by owner, shared with admin_reader (read-write);
	 *   group_read   - private, owned by owner, shared with "Report test dashboard readers" group (read);
	 *   group_write  - private, owned by owner, shared with "Report test dashboard readers" group (read-write);
	 *   missing      - nonexistent dashboard ID.
	 *
	 * Columns: user whose session sends the request, dashboard, whether access is expected, user ID sent in the
	 * legacy "userid" field (spoofing attempt, ignored by server) or null to omit it.
	 */
	public static function dashboardAccessProvider() {
		return [
			'user type denied' => ['reader', 'public', false, null],
			'admin private dashboard denied' => ['admin_reader', 'private', false, null],
			'admin spoofs dashboard owner' => ['admin_reader', 'private', false, 'owner'],
			'admin spoofs superadmin' => ['admin_reader', 'private', false, 'superadmin'],
			'admin own dashboard' => ['admin_reader', 'own', true, null],
			'admin public dashboard' => ['admin_reader', 'public', true, null],
			'admin user read sharing' => ['admin_reader', 'user_read', true, null],
			'admin user write sharing' => ['admin_reader', 'user_write', true, null],
			'admin group read sharing' => ['admin_reader', 'group_read', true, null],
			'admin group write sharing' => ['admin_reader', 'group_write', true, null],
			'admin owner' => ['owner', 'private', true, null],
			'admin without access' => ['owner', 'own', false, 'admin_reader'],
			'superadmin' => ['superadmin', 'private', true, null],
			'admin missing dashboard' => ['admin_reader', 'missing', false, null],
			'outsider public dashboard' => ['outsider', 'public', true, null],
			'outsider user read sharing' => ['outsider', 'user_read', false, null],
			'outsider user write sharing' => ['outsider', 'user_write', false, null],
			'outsider group read sharing' => ['outsider', 'group_read', false, null],
			'outsider group write sharing' => ['outsider', 'group_write', false, null],
			'outsider spoofs sharee' => ['outsider', 'user_read', false, 'admin_reader']
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
		if ($user === 'reader') {
			$error = 'no access to scheduled reports';
		} elseif ($allowed) {
			$error = "No media configured for the report recipient 'report_test_".$user."'";
		} else {
			$error = 'no access to dashboard';
		}

		$this->checkReport($user, $dashboard, $error, $viewer);
	}

	public static function roleRulesProvider() {
		return [
			'manage scheduled reports disabled' => ['no_report_action', false],
			'actions disabled by default' => ['default_denied', false],
			'manage scheduled reports overrides default' => ['report_action_only', true],
			// API enforces the action rule for every user type, including super admin (CReport::ACCESS_RULES).
			'superadmin manage scheduled reports disabled' => ['superadmin_no_report_action', false]
		];
	}

	/**
	 * @dataProvider roleRulesProvider
	 */
	public function testReportTest_roleRules($user, $allowed) {
		$error = $allowed
			? "No media configured for the report recipient 'report_test_".$user."'"
			: 'no access to scheduled reports';

		$this->checkReport($user, 'public', $error);
	}

	public static function tokenProvider() {
		return [
			'token allowed' => ['token_admin_reader', 'public',
				"No media configured for the report recipient 'report_test_admin_reader'"
			],
			'token dashboard denied' => ['token_admin_reader', 'private', 'no access to dashboard'],
			'token user type denied' => ['token_reader', 'public', 'no access to scheduled reports'],
			'disabled token' => ['token_disabled', 'public', 'Permission denied.'],
			'expired token' => ['token_expired', 'public', 'Permission denied.']
		];
	}

	/**
	 * @dataProvider tokenProvider
	 */
	public function testReportTest_tokens($token, $dashboard, $error) {
		$this->checkReport($token, $dashboard, $error);
	}

	/**
	 * Role changes do not terminate sessions, so permissions must be checked on every request.
	 */
	public function testReportTest_roleChanges() {
		$allowed = "No media configured for the report recipient 'report_test_role_changes'";
		$denied = 'no access to scheduled reports';
		$roleid = self::$roleids['role_changes'];

		$this->checkReport('role_changes', 'public', $allowed);

		$this->call('role.update', ['roleid' => $roleid, 'rules' => [
			'actions' => [['name' => 'manage_scheduled_reports', 'status' => ZBX_ROLE_RULE_DISABLED]]
		]]);
		$this->checkReport('role_changes', 'public', $denied);

		$this->call('role.update', ['roleid' => $roleid, 'rules' => [
			'actions' => [['name' => 'manage_scheduled_reports', 'status' => ZBX_ROLE_RULE_ENABLED]]
		]]);
		$this->checkReport('role_changes', 'public', $allowed);

		$this->call('role.update', ['roleid' => $roleid, 'type' => USER_TYPE_ZABBIX_USER]);
		$this->checkReport('role_changes', 'public', $denied);

		$this->call('role.update', ['roleid' => $roleid, 'type' => USER_TYPE_ZABBIX_ADMIN]);
		$this->checkReport('role_changes', 'public', $allowed);

		$this->call('user.update', ['userid' => self::$userids['role_changes'], 'roleid' => self::$roleids['reader']]);
		$this->checkReport('role_changes', 'public', $denied);

		$this->call('user.update', ['userid' => self::$userids['role_changes'], 'roleid' => $roleid]);
		$this->checkReport('role_changes', 'public', $allowed);
	}

	public function testReportTest_invalidSessions() {
		foreach (['inactive', 'unknown', 'malformed', 'empty', 'missing'] as $session) {
			$this->checkReport($session, 'public', 'Permission denied.', 'owner');
		}
	}

	public function testReportTest_revokedSharing() {
		$error = "No media configured for the report recipient 'report_test_admin_reader'";

		$this->checkReport('admin_reader', 'user_read', $error);
		$this->call('dashboard.update', ['dashboardid' => self::$dashboardids['user_read'], 'users' => []]);
		$this->checkReport('admin_reader', 'user_read', 'no access to dashboard', 'owner');
	}

	public function testReportTest_revokedGroupMembership() {
		$error = "No media configured for the report recipient 'report_test_admin_reader'";

		$this->checkReport('admin_reader', 'group_read', $error);
		$this->call('user.update', [
			'userid' => self::$userids['admin_reader'], 'usrgrps' => [['usrgrpid' => self::$groupids[0]]]
		]);
		$this->checkReport('admin_reader', 'group_read', 'no access to dashboard', 'owner');
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
