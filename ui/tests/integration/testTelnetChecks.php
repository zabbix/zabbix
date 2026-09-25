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
 * Test suite for Telnet checks.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testTelnetChecks extends CIntegrationTest {
	private const HOST_NAME = 'telnet_checks_host';
	private const HOST_GROUP_ID = 4;
	private const USERNAME = 'zabbix';
	private const PASSWORD = 'zabbix';
	private const ECHO_COMMAND = 'echo test output';
	private const ECHO_OUTPUT = 'hello world';

	private const MOCK_HOST = '127.0.0.1';
	private const MOCK_SCRIPT = __DIR__.'/data/telnet_server_mock.py';

	private const LOGIN_FAILED_ERROR = 'Login failed.';
	private const NO_LOGIN_PROMPT_ERROR = 'No login prompt.';
	private const NO_PASSWORD_PROMPT_ERROR = 'No password prompt.';

	private static string $mock_log_file;
	private static string $mock_pid_file;
	private static string $mock_run_id;
	private static int $mock_port;
	private static string $hostid;
	private static string $interfaceid;
	private static array $itemids = [];

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 3,
				'LogFileSize' => 20
			]
		];
	}

	public function prepareData(): bool {
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				'type' => INTERFACE_TYPE_AGENT,
				'main' => INTERFACE_PRIMARY,
				'useip' => INTERFACE_USE_IP,
				'ip' => self::MOCK_HOST,
				'dns' => '',
				'port' => (string) self::getMockPort()
			],
			'groups' => [
				['groupid' => self::HOST_GROUP_ID]
			],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('host.get', [
			'output' => [],
			'selectInterfaces' => ['interfaceid'],
			'hostids' => self::$hostid
		]);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		return true;
	}

	public static function clearData(): void {
		self::stopTelnetMock();

		if (self::$itemids) {
			CDataHelper::call('item.delete', array_values(self::$itemids));
		}

		if (isset(self::$hostid)) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}
	}

	private function createTelnetItem(string $unique_suffix, string $command): string {
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'telnet_checks_'.$unique_suffix,
			'key_' => 'telnet.run['.$unique_suffix.',,'.self::getMockPort().']',
			'type' => ITEM_TYPE_TELNET,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'delay' => '1s',
			'interfaceid' => self::$interfaceid,
			'username' => self::USERNAME,
			'password' => self::PASSWORD,
			'params' => $command
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		$itemid = $response['result']['itemids'][0];
		self::$itemids[$unique_suffix] = $itemid;

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		return $itemid;
	}

	private function deleteTelnetItem(string $unique_suffix, string $itemid): void {
		$this->call('item.delete', [$itemid]);
		unset(self::$itemids[$unique_suffix]);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
	}

	private function assertTelnetItemValue(string $itemid, string $expected_value): void {
		$response = $this->callUntilDataIsPresent('history.get', [
			'output' => ['value'],
			'itemids' => [$itemid],
			'history' => ITEM_VALUE_TYPE_TEXT,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY);

		$this->assertSame($expected_value, $response['result'][0]['value']);
	}

	private function assertTelnetItemError(string $itemid, string $expected_error): void {
		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['state', 'error'],
			'itemids' => [$itemid]
		], self::WAIT_ITERATIONS, self::WAIT_ITERATION_DELAY, static function (array $response): bool {
			return (int) $response['result'][0]['state'] === ITEM_STATE_NOTSUPPORTED;
		});

		$this->assertSame($expected_error, $response['result'][0]['error']);
	}

	public static function startTelnetMock(array $extra_args = []): void {
		self::$mock_log_file = PHPUNIT_COMPONENT_DIR.'telnet_mock_'.self::getMockRunId().'.log';
		self::$mock_pid_file = PHPUNIT_COMPONENT_DIR.'telnet_mock_'.self::getMockRunId().'.pid';

		@unlink(self::$mock_log_file);
		@unlink(self::$mock_pid_file);

		self::executeCommand('python3', array_merge([
			self::MOCK_SCRIPT,
			'--host', self::MOCK_HOST,
			'--port', (string) self::getMockPort(),
			'--log-file', self::$mock_log_file,
			'--pid-file', self::$mock_pid_file
		], $extra_args), true);

		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;

		while (!file_exists(self::$mock_log_file) && time() < $deadline) {
			usleep(100000); // 100 ms
		}

		if (!file_exists(self::$mock_log_file)) {
			throw new Exception('Failed to wait for telnet mock log file.');
		}
	}

	public static function startTelnetMockAltPrompt(): void {
		self::startTelnetMock(['--prompt-char', '#']);
	}

	public static function startTelnetMockNoLoginPrompt(): void {
		self::startTelnetMock(['--no-login-prompt']);
	}

	public static function startTelnetMockNoPasswordPrompt(): void {
		self::startTelnetMock(['--no-password-prompt']);
	}

	public static function startTelnetMockNoShellPrompt(): void {
		self::startTelnetMock(['--no-shell-prompt']);
	}

	public static function stopTelnetMock(): void {
		if (!isset(self::$mock_pid_file) || !file_exists(self::$mock_pid_file)) {
			return;
		}

		$pid = trim(file_get_contents(self::$mock_pid_file));

		if (ctype_digit($pid) && posix_kill((int) $pid, 0)) {
			posix_kill((int) $pid, SIGTERM);
		}

		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;

		while (file_exists(self::$mock_pid_file) && time() < $deadline) {
		}

		if (file_exists(self::$mock_pid_file)) {
			throw new Exception('Failed to stop telnet mock.');
		}
	}

	private static function getMockRunId(): string {
		if (!isset(self::$mock_run_id)) {
			self::$mock_run_id = self::getMockPort().'_'.date('YmdHis').'_'.getmypid();
		}

		return self::$mock_run_id;
	}

	private static function getMockPort(): int {
		if (!isset(self::$mock_port)) {
			$socket = stream_socket_server('tcp://'.self::MOCK_HOST.':0', $error_code, $error_message);

			if ($socket === false) {
				throw new Exception('Cannot reserve telnet mock port: '.$error_message);
			}

			self::$mock_port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
			fclose($socket);
		}

		return self::$mock_port;
	}

	public function testTelnetChecks_commandOutput(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('echo', self::ECHO_COMMAND);
		$this->assertTelnetItemValue($itemid, self::ECHO_OUTPUT);
		$this->deleteTelnetItem('echo', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_shortCommandOutput(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('short', 'short output cmd');
		$this->assertTelnetItemValue($itemid, '');
		$this->deleteTelnetItem('short', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_shortMultilineCommandOutput(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('shortmultiline',
			"multiline command 1\nmultiline command 2\nmultiline command 3");
		$this->assertTelnetItemValue($itemid, '');
		$this->deleteTelnetItem('shortmultiline', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_eolConversion(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('eol', 'eol test');
		$this->assertTelnetItemValue($itemid, "CRLF-line\nLFCR-line\nCR-line\nCRNUL-line");
		$this->deleteTelnetItem('eol', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_alternatePromptChar(): void {
		self::startTelnetMockAltPrompt();

		$itemid = $this->createTelnetItem('altprompt', self::ECHO_COMMAND);
		$this->assertTelnetItemValue($itemid, self::ECHO_OUTPUT);
		$this->deleteTelnetItem('altprompt', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_noLoginPrompt(): void {
		self::startTelnetMockNoLoginPrompt();

		$itemid = $this->createTelnetItem('nologin', self::ECHO_COMMAND);
		$this->assertTelnetItemError($itemid, self::NO_LOGIN_PROMPT_ERROR);
		$this->deleteTelnetItem('nologin', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_noPasswordPrompt(): void {
		self::startTelnetMockNoPasswordPrompt();

		$itemid = $this->createTelnetItem('nopassword', self::ECHO_COMMAND);
		$this->assertTelnetItemError($itemid, self::NO_PASSWORD_PROMPT_ERROR);
		$this->deleteTelnetItem('nopassword', $itemid);

		self::stopTelnetMock();
	}

	public function testTelnetChecks_loginFailed(): void {
		self::startTelnetMockNoShellPrompt();

		$itemid = $this->createTelnetItem('loginfailed', self::ECHO_COMMAND);
		$this->assertTelnetItemError($itemid, self::LOGIN_FAILED_ERROR);
		$this->deleteTelnetItem('loginfailed', $itemid);

		self::stopTelnetMock();
	}
}
