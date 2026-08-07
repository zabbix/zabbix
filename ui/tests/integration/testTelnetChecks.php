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
 * Integration test for TELNET items, using a Python telnet server simulator instead of a real host.
 *
 * Covers the login/password/prompt handshake, command echo stripping, EOL normalization and telnet
 * option negotiation in src/libs/zbxcomms/telnet.c, including the "short Telnet command output" crash
 * fixed by DEV-5055 in telnet_rm_echo().
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testTelnetChecks extends CIntegrationTest {
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
				// DebugLevel 4 makes telnet_read() log every single byte it reads, which noticeably slows
				// the whole server down once several TELNET items are polled - level 3 is plenty to see
				// "finished forced reloading of the configuration cache" and other startup/shutdown lines.
				'DebugLevel' => 3,
				'LogFileSize' => 20
			]
		];
	}

	public function prepareData(): bool {
		$response = $this->call('host.create', [
			'host' => 'telnet_checks_host',
			'interfaces' => [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => self::MOCK_HOST,
				'dns' => '',
				'port' => (string) self::getMockPort()
			],
			'groups' => [
				['groupid' => 4] // Zabbix servers
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

	/**
	 * Creates a TELNET item executing $command against the mock and returns its itemid. The executed
	 * command is the item's "params" field, exactly as the real telnet.run[] check works (see
	 * checks_telnet.c / telnet_run.c). The port must be repeated as the key's 3rd parameter -
	 * checks_telnet.c only takes the address from the host interface; the port always comes from the
	 * key (get_rparam(&request, 2)) and falls back to the hardcoded default telnet port 23 if that key
	 * parameter is empty, regardless of what port the interface itself is configured with.
	 */
	private function createTelnetItem(string $unique_suffix, string $command): string {
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => 'telnet_checks_'.$unique_suffix,
			'key_' => 'telnet.run['.$unique_suffix.',,'.self::getMockPort().']',
			'type' => ITEM_TYPE_TELNET,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'delay' => '1s',
			'interfaceid' => self::$interfaceid,
			'username' => 'zabbix',
			'password' => 'zabbix',
			'params' => $command
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		$itemid = $response['result']['itemids'][0];
		self::$itemids[$unique_suffix] = $itemid;

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		return $itemid;
	}

	/**
	 * Deletes a TELNET item and waits for the config cache reload to take effect before returning.
	 *
	 * The detected shell prompt character in zbx_telnet_login()/telnet_rm_echo() is kept in a
	 * process-wide static in telnet.c, not per-connection state, so two TELNET items using different
	 * prompt characters (e.g. "$" and "#") being polled concurrently on the same poller can clobber
	 * each other's prompt character and make the check hang until timeout. Removing each item as soon
	 * as its test is done, before the next item/mock is set up, keeps at most one TELNET item active on
	 * the host at any time and avoids that cross-talk.
	 */
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
		], 60, 1);

		$this->assertSame($expected_value, $response['result'][0]['value']);
	}

	private function assertTelnetItemError(string $itemid, string $expected_error): void {
		$response = $this->callUntilDataIsPresent('item.get', [
			'output' => ['state', 'error'],
			'itemids' => [$itemid]
		], 60, 1, static function (array $response): bool {
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

	/**
	 * Baseline happy path: command is echoed back by the server, output spans a single line, and the
	 * trailing prompt is stripped correctly. Proves the ordinary case still works.
	 */
	public function testTelnetChecks_commandOutput(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('echo', 'echo test output');
		$this->assertTelnetItemValue($itemid, 'hello world');
		$this->deleteTelnetItem('echo', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * Regression test for DEV-5055: the server replies with only the bare shell prompt (no command
	 * echo, no output at all), so the reply is shorter than both the echoed-command text and the
	 * "\n"/prompt-fragment strings that zbx_telnet_execute() tries to remove from the front of the
	 * buffer. telnet_rm_echo() must bail out via its length guard instead of underflowing its offset
	 * and running memmove() with a huge size. Before the fix, whether this crashed depended on
	 * uncontrollable stack garbage; what this test verifies is that, either way, the current code
	 * handles a short reply safely and returns the correct (empty) result.
	 */
	public function testTelnetChecks_shortCommandOutput(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('short', 'short output cmd');
		$this->assertTelnetItemValue($itemid, '');
		$this->deleteTelnetItem('short', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * Regression test for DEV-5055, multi-line variant: for a command with N embedded newlines,
	 * zbx_telnet_execute() tries to strip one extra "$ " prompt fragment per newline. The mock never
	 * replies to the first two lines of the three-line command below (nothing has been echoed for the
	 * client to read yet), so the only bytes the client ever gets are the bare "$ " sent after the
	 * third line. The first strip attempt consumes that "$ " and drops the logical offset to zero; the
	 * second attempt, driven by the command's second embedded newline, must find nothing left to strip
	 * instead of subtracting from an already-zero offset. This exercises telnet_rm_echo() a second time
	 * on a zero offset, which is the exact case its length guard exists for.
	 */
	public function testTelnetChecks_shortMultilineCommandOutput(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('shortmultiline',
			"multiline command 1\nmultiline command 2\nmultiline command 3");
		$this->assertTelnetItemValue($itemid, '');
		$this->deleteTelnetItem('shortmultiline', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * Covers convert_telnet_to_unix_eol(): CRLF, LF+CR, bare CR and CR+NUL must all normalize to a
	 * single Unix "\n" (CR+NUL is dropped entirely, with no newline emitted in its place).
	 */
	public function testTelnetChecks_eolConversion(): void {
		self::startTelnetMock();

		$itemid = $this->createTelnetItem('eol', 'eol test');
		$this->assertTelnetItemValue($itemid, "CRLF-line\nLFCR-line\nCR-line\nCRNUL-line");
		$this->deleteTelnetItem('eol', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * Prompt character detection in zbx_telnet_login()/telnet_lastchar() is not hardcoded to "$" -
	 * "#" (and by the same logic ">" and "%") must be recognised too.
	 */
	public function testTelnetChecks_alternatePromptChar(): void {
		self::startTelnetMockAltPrompt();

		$itemid = $this->createTelnetItem('altprompt', 'echo test output');
		$this->assertTelnetItemValue($itemid, 'hello world');
		$this->deleteTelnetItem('altprompt', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * The server never sends anything ending in ":" before closing the connection - zbx_telnet_login()
	 * must report "No login prompt." instead of hanging or crashing on EOF.
	 */
	public function testTelnetChecks_noLoginPrompt(): void {
		self::startTelnetMockNoLoginPrompt();

		$itemid = $this->createTelnetItem('nologin', 'echo test output');
		$this->assertTelnetItemError($itemid, self::NO_LOGIN_PROMPT_ERROR);
		$this->deleteTelnetItem('nologin', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * The server presents a login prompt but never a password prompt before closing the connection.
	 */
	public function testTelnetChecks_noPasswordPrompt(): void {
		self::startTelnetMockNoPasswordPrompt();

		$itemid = $this->createTelnetItem('nopassword', 'echo test output');
		$this->assertTelnetItemError($itemid, self::NO_PASSWORD_PROMPT_ERROR);
		$this->deleteTelnetItem('nopassword', $itemid);

		self::stopTelnetMock();
	}

	/**
	 * The server presents login and password prompts but never a recognised shell prompt character
	 * afterwards - zbx_telnet_login() must report "Login failed.".
	 */
	public function testTelnetChecks_loginFailed(): void {
		self::startTelnetMockNoShellPrompt();

		$itemid = $this->createTelnetItem('loginfailed', 'echo test output');
		$this->assertTelnetItemError($itemid, self::LOGIN_FAILED_ERROR);
		$this->deleteTelnetItem('loginfailed', $itemid);

		self::stopTelnetMock();
	}
}
