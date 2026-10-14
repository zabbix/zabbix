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
require_once dirname(__FILE__).'/../../include/classes/validators/CApiInputValidator.php';

/**
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testTrapperAuthentication extends CIntegrationTest {
	private const USERNAME = 'trapper_authentication_superadmin';
	private const PASSWORD = 'TrapperAuthentication123!';
	private const NONEXISTENT_ID = '999999999999';

	private static $usrgrpids = [];
	private static $roleid;
	private static $userid;
	private static $credentials = [];
	private static $hostgroupid;
	private static $hostid;
	private static $itemid;
	private static $scriptid;

	public function prepareData() {
		$response = $this->call('hostgroup.create', [
			'name' => 'Trapper authentication host group'
		]);
		self::$hostgroupid = $response['result']['groupids'][0];

		$response = $this->call('usergroup.create', [
			['name' => 'Trapper authentication enabled group'],
			['name' => 'Trapper authentication disabled group']
		]);
		self::$usrgrpids = $response['result']['usrgrpids'];

		// Super admin passes the user type checks of every request, so a denial can only come from the group.
		$response = $this->call('role.create', [
			'name' => 'Trapper authentication super admin role',
			'type' => USER_TYPE_SUPER_ADMIN,
			'rules' => ['api.access' => ZBX_ROLE_RULE_ENABLED]
		]);
		self::$roleid = $response['result']['roleids'][0];

		$response = $this->call('user.create', [
			'username' => self::USERNAME,
			'passwd' => self::PASSWORD,
			'roleid' => self::$roleid,
			'autologout' => '0',
			'usrgrps' => [
				['usrgrpid' => self::$usrgrpids[0]],
				['usrgrpid' => self::$usrgrpids[1]]
			]
		]);
		self::$userid = $response['result']['userids'][0];

		// Log in without replacing the administrator session used to update the group.
		$response = CAPIHelper::callRaw([
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => ['username' => self::USERNAME, 'password' => self::PASSWORD],
			'id' => 1
		]);
		$this->assertArrayHasKey('result', $response);
		self::$credentials['session'] = $response['result'];

		$response = $this->call('token.create', [
			'name' => 'Trapper authentication token',
			'userid' => self::$userid
		]);
		$response = $this->call('token.generate', $response['result']['tokenids']);
		self::$credentials['token'] = $response['result'][0]['token'];

		$response = $this->call('host.create', [
			'host' => 'trapper_authentication_host',
			'groups' => [['groupid' => self::$hostgroupid]]
		]);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Trapper authentication item',
			'key_' => 'trapper.authentication',
			'hostid' => self::$hostid,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'delay' => '0'
		]);
		self::$itemid = $response['result']['itemids'][0];

		return true;
	}

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'EnableGlobalScripts' => 1,
				// Authentication result is logged by get_user_from_json() on debug level.
				'DebugLevel' => 4,
				'LogFileSize' => 0,
				// report.test is only authenticated when report manager is running; web service is not contacted.
				'StartReportWriters' => 1,
				'WebServiceURL' => 'http://127.0.0.1:1/report'
			]
		];
	}

	public static function clearData(): void {
		if (self::$scriptid !== null) {
			CDataHelper::call('script.delete', [self::$scriptid]);
			self::$scriptid = null;
		}

		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
			self::$hostid = null;
			self::$itemid = null;
		}

		if (self::$userid !== null) {
			// Deleting the user also removes the user's sessions and tokens.
			CDataHelper::call('user.delete', [self::$userid]);
			self::$userid = null;
			self::$credentials = [];
		}

		if (self::$roleid !== null) {
			CDataHelper::call('role.delete', [self::$roleid]);
			self::$roleid = null;
		}

		if (self::$usrgrpids) {
			CDataHelper::call('usergroup.delete', self::$usrgrpids);
			self::$usrgrpids = [];
		}

		if (self::$hostgroupid !== null) {
			CDataHelper::call('hostgroup.delete', [self::$hostgroupid]);
			self::$hostgroupid = null;
		}
	}

	public static function authenticationDataProvider() {
		// The last element is the error expected after the request has passed authentication, null on success.
		$requests = [
			'item.test' => ['testItem', [[
				'item' => ['value' => '42', 'value_type' => ITEM_VALUE_TYPE_TEXT, 'steps' => []]
			]], null],
			'expressions.evaluate' => ['expressionsEvaluate', [['expressions' => ['1=1']]], null],
			'status.get' => ['getStatus', [], null],
			'queue.get' => ['getQueue', [CZabbixServer::QUEUE_OVERVIEW], null],
			'command' => ['executeScript', [], null],
			'history.push' => ['pushHistory', [], null],
			'alert.send' => ['testMediaType', [[
				'mediatypeid' => self::NONEXISTENT_ID,
				'sendto' => 'trapper_authentication',
				'subject' => 'Trapper authentication',
				'message' => 'Trapper authentication'
			]], 'Cannot find the specified media type.'],
			'report.test' => ['testReport', [['name' => 'Trapper authentication']], 'cannot find tag: dashboardid'],
			'cep.rule.reset' => ['resetCepRule', [['cep_ruleid' => 'invalid']], 'Cannot parse request tag: cep_ruleid.']
		];

		$data = [];
		foreach (['session', 'token'] as $credential_type) {
			foreach ($requests as $request => [$method, $params, $error]) {
				$data[$credential_type.' '.$request] = [$credential_type, $method, $params, $error];
			}
		}

		return $data;
	}

	/**
	 * Sends the request and checks that the server logged the expected authentication result for it.
	 */
	private function sendTrapperRequest(CZabbixServer $server, $method, $credential, $params, bool $granted) {
		$result = $this->sendRequest($server, $method, $credential, $params);

		// Log is read incrementally, so only the line logged for this request can match.
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of get_user_from_json():'.($granted ? 'SUCCEED' : 'FAIL'), true, 5, 1
		);

		return $result;
	}

	private function sendRequest(CZabbixServer $server, $method, $credential, $params) {
		if ($method === 'executeScript') {
			if (self::$scriptid === null) {
				$response = $this->call('script.create', [
					'name' => 'Trapper authentication script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'echo trapper_authentication_ok',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER
				]);
				self::$scriptid = $response['result']['scriptids'][0];
				$this->reloadConfigurationCacheAndWaitForLogLine();
			}

			return $server->executeScript(self::$scriptid, $credential, self::$hostid);
		}

		if ($method === 'pushHistory') {
			return $server->pushHistory([[
				'itemid' => self::$itemid,
				'value' => 'trapper_authentication_ok'
			]], $credential);
		}

		return $server->$method(...array_merge($params, [$credential]));
	}

	private function assertAuthenticatedTrapperRequest(CZabbixServer $server, $method, $credential, $params,
			?string $error): void {
		$result = $this->sendTrapperRequest($server, $method, $credential, $params, true);

		if ($error !== null) {
			// The request is expected to fail, but only after passing the authentication.
			$this->assertFalse($result);
			$this->assertSame($error, $server->getError());

			return;
		}

		$this->assertNotFalse($result, $server->getError() ?? '');

		if ($method === 'executeScript') {
			$this->assertSame('trapper_authentication_ok', trim($result));
		}
		elseif ($method === 'pushHistory') {
			$this->assertEquals(self::$itemid, $result[0]['itemid']);
			$this->assertArrayNotHasKey('error', $result[0]);
		}
	}

	/**
	 * @dataProvider authenticationDataProvider
	 */
	public function testTrapperAuthentication_disabledGroup($credential_type, $method, $params, $error) {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$server = new CZabbixServer('127.0.0.1',
			$this->getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
			5, 5, ZBX_SOCKET_BYTES_LIMIT, tls_config: ['ACTIVE' => false]
		);
		$credential = self::$credentials[$credential_type];

		$this->assertAuthenticatedTrapperRequest($server, $method, $credential, $params, $error);

		// One disabled group must deny access even while the other group remains enabled.
		$this->call('usergroup.update', [
			'usrgrpid' => self::$usrgrpids[1],
			'users_status' => GROUP_STATUS_DISABLED
		]);

		$this->assertEquals(ZBX_SESSION_ACTIVE, CDBHelper::getValue(
			'SELECT status FROM sessions WHERE sessionid='.zbx_dbstr(self::$credentials['session'])
		));
		$this->assertFalse($this->sendTrapperRequest($server, $method, $credential, $params, false));
		$this->assertSame('Permission denied.', $server->getError());

		$this->call('usergroup.update', [
			'usrgrpid' => self::$usrgrpids[1],
			'users_status' => GROUP_STATUS_ENABLED
		]);

		$this->assertAuthenticatedTrapperRequest($server, $method, $credential, $params, $error);
	}
}
