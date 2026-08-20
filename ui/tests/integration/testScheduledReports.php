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
 * Test suite for scheduled reports.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testScheduledReports extends CIntegrationTest {
	private const SEND_STATUS_SUCCESS = 0;
	private const MOCK_HOST = '127.0.0.1';
	private const MOCK_SCRIPT = __DIR__.'/data/reporting_mock.py';
	private const USER_NAME = 'scheduled_reports_integration';
	private const USER_PASSWORD = '123QWErty!';
	private const RECIPIENTS = [
		'atest@example.com',
		'atest1@example.com',
		'atest2@example.com'
	];

	private static string $mock_log_file;
	private static string $mock_pid_file;
	private static string $mock_run_id;
	private static int $http_port;
	private static int $smtp_port;
	private static array $mediatypeids = [];
	private static ?string $userid = null;
	private static string $frontend_url;

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'StartReportWriters' => 2,
				'WebServiceURL' => 'http://'.self::MOCK_HOST.':'.self::getHttpPort().'/report'
			]
		];
	}

	public function prepareData(): bool {
		$response = $this->call('settings.get', [
			'output' => ['url']
		]);
		self::$frontend_url = $response['result']['url'];

		$this->call('settings.update', [
			'url' => 'http://'.self::MOCK_HOST
		]);

		$media_types = [];
		foreach (['Atest', 'Atest1', 'Atest2'] as $name) {
			$media_types[] = [
				'name' => $name,
				'type' => MEDIA_TYPE_EMAIL,
				'smtp_server' => self::MOCK_HOST,
				'smtp_helo' => 'localhost',
				'smtp_email' => 'zabbix@example.com',
				'smtp_port' => self::getSmtpPort(),
				'smtp_security' => SMTP_SECURITY_NONE,
				'smtp_authentication' => SMTP_AUTHENTICATION_NONE,
				'maxattempts' => 1,
				'attempt_interval' => '1s',
				'status' => MEDIA_TYPE_STATUS_ACTIVE
			];
		}

		$response = $this->call('mediatype.create', $media_types);
		self::$mediatypeids = $response['result']['mediatypeids'];
		$this->assertCount(3, self::$mediatypeids);

		$this->call('mediatype.update', [
			'mediatypeid' => self::$mediatypeids[0],
			'name' => 'Atest temporary'
		]);
		$this->call('mediatype.update', [
			'mediatypeid' => self::$mediatypeids[1],
			'name' => 'Atest'
		]);
		$this->call('mediatype.update', [
			'mediatypeid' => self::$mediatypeids[0],
			'name' => 'Atest1'
		]);

		$response = $this->call('role.get', [
			'output' => ['roleid'],
			'filter' => ['type' => USER_TYPE_SUPER_ADMIN],
			'sortfield' => 'roleid',
			'sortorder' => ZBX_SORT_UP,
			'limit' => 1
		]);
		$this->assertNotEmpty($response['result']);

		$response = $this->call('user.create', [
			'username' => self::USER_NAME,
			'passwd' => self::USER_PASSWORD,
			'roleid' => $response['result'][0]['roleid'],
			'usrgrps' => [['usrgrpid' => 7]]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		self::$userid = $response['result']['userids'][0];

		return true;
	}

	public static function clearData(): void {
		self::stopReportingMock();

		if (isset(self::$mock_pid_file)) {
			@unlink(self::$mock_pid_file);
		}

		if (isset(self::$mock_log_file)) {
			@unlink(self::$mock_log_file);
		}

		CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		CDataHelper::call('settings.update', [
			'url' => self::$frontend_url
		]);

		if (self::$userid !== null) {
			CDataHelper::call('user.delete', [self::$userid]);
			self::$userid = null;
		}

		if (self::$mediatypeids) {
			CDataHelper::call('mediatype.delete', self::$mediatypeids);
			self::$mediatypeids = [];
		}
	}

	public static function mediaDataProvider(): array {
		return [
			'single media type and recipient' => [
				[
					['mediatype_index' => 0, 'recipient' => self::RECIPIENTS[0]]
				],
				[self::RECIPIENTS[0]]
			],
			'multiple recipients using one media type' => [
				[
					['mediatype_index' => 0, 'recipient' => self::RECIPIENTS[0]],
					['mediatype_index' => 0, 'recipient' => self::RECIPIENTS[1]]
				],
				[self::RECIPIENTS[0], self::RECIPIENTS[1]]
			],
			'multiple media types processed out of order' => [
				[
					['mediatype_index' => 1, 'recipient' => self::RECIPIENTS[0]],
					['mediatype_index' => 0, 'recipient' => self::RECIPIENTS[1]],
					['mediatype_index' => 2, 'recipient' => self::RECIPIENTS[2]]
				],
				self::RECIPIENTS
			]
		];
	}

	/**
	 * @dataProvider mediaDataProvider
	 * @onBeforeOnce startReportingMock
	 * @onAfterOnce stopReportingMock
	 */
	public function testScheduledReports_delivery(array $media, array $expected_recipients): void {
		$this->setUserMedia($media);
		file_put_contents(self::$mock_log_file, '');

		[$client, $response] = $this->sendTestReport();

		$this->assertIsArray($response, $client->getError() ?? '');
		$this->assertArrayHasKey('recipients', $response);
		$this->assertCount(count($expected_recipients), $response['recipients']);
		foreach ($response['recipients'] as $recipient) {
			$this->assertSame(self::SEND_STATUS_SUCCESS, $recipient['status']);
		}

		$recipients = array_column($response['recipients'], 'recipient');
		sort($recipients);
		sort($expected_recipients);
		$this->assertSame($expected_recipients, $recipients);

		$mock_log = file_get_contents(self::$mock_log_file);
		preg_match_all('/^RCPT TO:<([^>]+)>$/m', $mock_log, $matches);
		$delivered_recipients = $matches[1];
		sort($delivered_recipients);

		$this->assertSame($expected_recipients, $delivered_recipients);
		$this->assertSame(1, substr_count($mock_log, "REPORT\n"));
		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER,
			'Something unexpected has just happened.', false
		));
	}

	public function testScheduledReports_noMedia(): void {
		$this->setUserMedia([]);

		[$client, $response] = $this->sendTestReport();

		$this->assertFalse($response);
		$this->assertStringContainsString('No media configured for the report recipient',
			$client->getError()
		);
		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER,
			'Something unexpected has just happened.', false
		));
	}

	private function setUserMedia(array $media): void {
		$medias = [];
		foreach ($media as $entry) {
			$medias[] = [
				'mediatypeid' => self::$mediatypeids[$entry['mediatype_index']],
				'sendto' => $entry['recipient']
			];
		}

		$response = $this->call('user.update', [
			'userid' => self::$userid,
			'medias' => $medias
		]);
		$this->assertArrayHasKey('userids', $response['result']);
	}

	private function sendTestReport(): array {
		$this->authorize(self::USER_NAME, self::USER_PASSWORD);

		$this->clearLog(self::COMPONENT_SERVER);

		$client = $this->getClient(self::COMPONENT_SERVER);
		$response = $client->testReport([
			'name' => 'Scheduled report media type ordering',
			'dashboardid' => 1,
			'userid' => 1,
			'period' => ZBX_REPORT_PERIOD_DAY,
			'now' => time(),
			'params' => [
				'subject' => 'Scheduled report integration test',
				'body' => 'Scheduled report integration test'
			]
		], CAPIHelper::getSessionId());

		return [$client, $response];
	}

	public static function startReportingMock(): void {
		self::$mock_log_file = PHPUNIT_COMPONENT_DIR.'reporting_mock_'.self::getMockRunId().'.log';
		self::$mock_pid_file = PHPUNIT_COMPONENT_DIR.'reporting_mock_'.self::getMockRunId().'.pid';

		@unlink(self::$mock_log_file);
		@unlink(self::$mock_pid_file);

		self::executeCommand('python3', [
			self::MOCK_SCRIPT,
			'--host', self::MOCK_HOST,
			'--http-port', (string) self::getHttpPort(),
			'--smtp-port', (string) self::getSmtpPort(),
			'--log-file', self::$mock_log_file,
			'--pid-file', self::$mock_pid_file
		], true);

		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;
		while ((!file_exists(self::$mock_log_file)
				|| strpos(file_get_contents(self::$mock_log_file), 'READY') === false)
				&& time() < $deadline) {
			usleep(100000);
		}

		if (!file_exists(self::$mock_log_file)
				|| strpos(file_get_contents(self::$mock_log_file), 'READY') === false) {
			throw new Exception('Failed to start reporting mock.');
		}
	}

	public static function stopReportingMock(): void {
		if (!isset(self::$mock_pid_file) || !file_exists(self::$mock_pid_file)) {
			return;
		}

		$pid = trim(file_get_contents(self::$mock_pid_file));

		if (ctype_digit($pid) && posix_kill((int) $pid, 0)) {
			posix_kill((int) $pid, SIGTERM);
		}

		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;
		while (file_exists(self::$mock_pid_file) && time() < $deadline) {
			usleep(100000);
		}

		if (file_exists(self::$mock_pid_file)) {
			throw new Exception('Failed to stop reporting mock.');
		}
	}

	private static function getMockRunId(): string {
		if (!isset(self::$mock_run_id)) {
			self::$mock_run_id = self::getHttpPort().'_'.self::getSmtpPort().'_'.date('YmdHis').'_'.
				getmypid();
		}

		return self::$mock_run_id;
	}

	private static function getHttpPort(): int {
		if (!isset(self::$http_port)) {
			self::$http_port = self::reservePort();
		}

		return self::$http_port;
	}

	private static function getSmtpPort(): int {
		if (!isset(self::$smtp_port)) {
			self::$smtp_port = self::reservePort();
		}

		return self::$smtp_port;
	}

	private static function reservePort(): int {
		$socket = stream_socket_server('tcp://'.self::MOCK_HOST.':0', $error_code, $error_message);

		if ($socket === false) {
			throw new Exception('Cannot reserve reporting mock port: '.$error_message);
		}

		$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
		fclose($socket);

		return $port;
	}
}
