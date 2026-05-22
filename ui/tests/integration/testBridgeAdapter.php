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
 * Skeleton for bridge-adapter integration coverage.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @onAfter clearData
 */
class testBridgeAdapter extends CIntegrationTest {
	private const ADAPTER_HOST = '127.0.0.1';
	private const ADAPTER_SCRIPT = __DIR__.'/data/bridge_adapter_mock.py';
	private const INIT_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000101';
	private const NOTIFY_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000102';
	private const OFFBOARD_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000103';
	private const UNKNOWN_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000104';
	private const REAL_NOTIFY_HOST = 'bridge_adapter_real_notification_host';
	private const REAL_NOTIFY_ITEM_KEY = 'bridge.adapter.real.notify';
	private const REAL_NOTIFY_SUBJECT = 'Bridge adapter real notification';
	private const REAL_NOTIFY_MESSAGE = 'Bridge adapter real notification message';
	private const MEDIA_SEVERITY_ALL = 63;
	private const PUSH_ALERT_ERROR_NO_PERMISSION = 'No permissions to referred device or it does not exist.';

	private static string $adapter_log_file;
	private static string $adapter_pid_file;
	private static string $adapter_run_id;
	private static int $adapter_port;
	private static array $deviceids = [];
	private static array $actionids = [];
	private static array $triggerids = [];
	private static array $itemids = [];
	private static array $hostids = [];
	private static ?string $push_mediatypeid = null;
	private static ?int $push_mediatype_status = null;
	private static ?string $real_notify_hostid = null;

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'BridgeAdapterURL' => 'http://'.self::ADAPTER_HOST.':'.self::getAdapterPort().'/rpc'
			]
		];
	}

	public function prepareData(): bool {
		$current_time = time();

		$deviceid = DB::reserveIds('device', 2);
		$device_keyid = DB::reserveIds('device_key', 2);
		$db_push_mediatype = DBfetch(DBselect(
			'select mediatypeid,status from media_type where type='.MEDIA_TYPE_PUSH.' order by mediatypeid'
		));

		self::$push_mediatypeid = $db_push_mediatype['mediatypeid'];
		self::$push_mediatype_status = (int) $db_push_mediatype['status'];

		$this->clearPreparedData();

		$response = $this->call('mediatype.update', [
			'mediatypeid' => self::$push_mediatypeid,
			'status' => MEDIA_TYPE_STATUS_ACTIVE
		]);
		$this->assertArrayHasKey('mediatypeids', $response['result']);

		$push_mediatypeid = self::$push_mediatypeid;
		$mobile_key = json_encode([
			'kty' => 'EC',
			'use' => 'enc',
			'alg' => 'ES256',
			'kid' => 'bridge-adapter-integration-encryption-key',
			'crv' => 'P-256',
			'x' => 'OV6uOpawdTTSC6QsLQSv9tCGVDyt3u0ZpdVCD95vogY',
			'y' => 'bnJ86Qyj0HDCrzNo2GOyvOTJA9lCiUEmLUXLcwLZeOQ'
		]);

		DB::insertBatch('device', [
			[
				'deviceid' => $deviceid,
				'userid' => 1,
				'uuid' => self::NOTIFY_DEVICE_UUID,
				'name' => 'Bridge adapter integration notification device',
				'status' => ZBX_DEVICE_STATUS_ACTIVATED,
				'push_token' => 'bridge-adapter-integration-push-token',
				'activated_at' => $current_time
			],
			[
				'deviceid' => bcadd($deviceid, 1, 0),
				'userid' => 1,
				'uuid' => self::OFFBOARD_DEVICE_UUID,
				'name' => 'Bridge adapter integration offboard device',
				'status' => ZBX_DEVICE_STATUS_ACTIVATED,
				'push_token' => 'bridge-adapter-integration-offboard-push-token',
				'activated_at' => $current_time
			]
		], false);

		self::$deviceids = [$deviceid, bcadd($deviceid, 1, 0)];

		DB::insertBatch('device_key', [
			[
				'device_keyid' => $device_keyid,
				'deviceid' => $deviceid,
				'scope' => MOBILE_KEY_SCOPE_ENCRYPTION,
				'kid' => 'bridge-adapter-integration-encryption-key',
				'key_' => $mobile_key,
				'active' => 0,
				'created_at' => $current_time
			],
			[
				'device_keyid' => bcadd($device_keyid, 1, 0),
				'deviceid' => bcadd($deviceid, 1, 0),
				'scope' => MOBILE_KEY_SCOPE_ENCRYPTION,
				'kid' => 'bridge-adapter-integration-offboard-encryption-key',
				'key_' => $mobile_key,
				'active' => 0,
				'created_at' => $current_time
			]
		], false);

		$response = $this->call('user.update', [
			'userid' => 1,
			'medias' => [
				[
					'mediatypeid' => $push_mediatypeid,
					'sendto' => [self::NOTIFY_DEVICE_UUID, self::UNKNOWN_DEVICE_UUID, '*'],
					'active' => MEDIA_STATUS_ACTIVE,
					'severity' => self::MEDIA_SEVERITY_ALL,
					'period' => '1-7,00:00-24:00'
				]
			]
		]);
		$this->assertArrayHasKey('userids', $response['result']);

		return true;
	}

	private function clearPreparedData(): void {
		$response = $this->call('action.get', [
			'output' => [],
			'filter' => ['name' => 'Bridge adapter real notification action']
		]);

		if ($response['result']) {
			$this->call('action.delete', array_column($response['result'], 'actionid'));
		}

		$response = $this->call('host.get', [
			'output' => [],
			'filter' => ['host' => self::REAL_NOTIFY_HOST]
		]);

		if ($response['result']) {
			$this->call('host.delete', array_column($response['result'], 'hostid'));
		}

		self::deleteRealNotificationMedia();

		$deviceids = array_keys(DB::select('device', [
			'output' => [],
			'filter' => ['uuid' => [self::NOTIFY_DEVICE_UUID, self::OFFBOARD_DEVICE_UUID]],
			'preservekeys' => true
		]));

		if ($deviceids) {
			DB::delete('device_key', ['deviceid' => $deviceids]);
			DB::delete('token_device', ['deviceid' => $deviceids]);
			DB::delete('device_enrollment_token', ['deviceid' => $deviceids]);
			DB::delete('device', ['deviceid' => $deviceids]);
		}
	}

	public static function clearData(): void {
		self::clearRealNotificationObjects();
		self::stopBridgeAdapterMock();

		if (isset(self::$adapter_pid_file)) {
			@unlink(self::$adapter_pid_file);
		}

		if (isset(self::$adapter_log_file)) {
			@unlink(self::$adapter_log_file);
		}

		self::deleteRealNotificationMedia();

		if (self::$deviceids) {
			$tokenids = array_keys(DB::select('token_device', [
				'output' => [],
				'filter' => ['deviceid' => self::$deviceids],
				'preservekeys' => true
			]));

			DB::delete('device_enrollment_token', ['deviceid' => self::$deviceids]);
			DB::delete('device_key', ['deviceid' => self::$deviceids]);
			DB::delete('token_device', ['deviceid' => self::$deviceids]);

			if ($tokenids) {
				DB::delete('token', ['tokenid' => $tokenids]);
			}

			DB::delete('device', ['deviceid' => self::$deviceids]);
		}

		if (self::$push_mediatypeid !== null && self::$push_mediatype_status !== null) {
			CDataHelper::call('mediatype.update', [
				'mediatypeid' => self::$push_mediatypeid,
				'status' => self::$push_mediatype_status
			]);
		}
	}

	private static function deleteRealNotificationMedia(): void {
		CDataHelper::call('user.update', [
			'userid' => 1,
			'medias' => []
		]);
	}

	private function getServerClientAndSid(): array {
		$client = $this->getClient(self::COMPONENT_SERVER);

		if (!isset($_SERVER['REMOTE_ADDR'])) {
			$_SERVER['REMOTE_ADDR'] = self::ADAPTER_HOST;
		}

		if (CAPIHelper::getSessionId() === null) {
			$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		return [$client, CAPIHelper::getSessionId()];
	}

	private function createRealNotificationObjects(): void {
		$response = $this->call('host.create', [
			'host' => self::REAL_NOTIFY_HOST,
			'groups' => [
				['groupid' => 4]
			],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostids = $response['result']['hostids'];

		self::$real_notify_hostid = self::$hostids[0];

		$response = $this->call('item.create', [
			[
				'name' => self::REAL_NOTIFY_ITEM_KEY,
				'key_' => self::REAL_NOTIFY_ITEM_KEY,
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => self::$real_notify_hostid,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}'
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$itemids = $response['result']['itemids'];

		$response = $this->call('trigger.create', [
			'description' => 'Bridge adapter real notification trigger',
			'expression' => 'last(/'.self::REAL_NOTIFY_HOST.'/'.self::REAL_NOTIFY_ITEM_KEY.')>5',
			'priority' => TRIGGER_SEVERITY_HIGH
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		self::$triggerids = $response['result']['triggerids'];

		$push_mediatypeid = CDBHelper::getValue(
			'select mediatypeid from media_type where type='.MEDIA_TYPE_PUSH.' order by mediatypeid'
		);

		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerids[0]
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'Bridge adapter real notification action',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'mediatypeid' => $push_mediatypeid,
						'subject' => self::REAL_NOTIFY_SUBJECT,
						'message' => self::REAL_NOTIFY_MESSAGE
					],
					'opmessage_usr' => [
						['userid' => 1]
					]
				]
			],
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_RECOVERY_MESSAGE,
					'opmessage' => [
						'default_msg' => 0,
						'subject' => self::REAL_NOTIFY_SUBJECT,
						'message' => self::REAL_NOTIFY_MESSAGE
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		self::$actionids = $response['result']['actionids'];
	}

	private static function clearRealNotificationObjects(): void {
		if (self::$actionids) {
			CDataHelper::call('action.delete', self::$actionids);
		}

		if (self::$triggerids) {
			CDataHelper::call('trigger.delete', self::$triggerids);
		}

		if (self::$itemids) {
			CDataHelper::call('item.delete', self::$itemids);
		}

		if (self::$hostids) {
			CDataHelper::call('host.delete', self::$hostids);
		}
	}

	public static function startBridgeAdapterMock(): void {
		self::$adapter_log_file = PHPUNIT_COMPONENT_DIR.'bridge_adapter_mock_'.self::getAdapterRunId().'.log';
		self::$adapter_pid_file = PHPUNIT_COMPONENT_DIR.'bridge_adapter_mock_'.self::getAdapterRunId().'.pid';

		@unlink(self::$adapter_log_file);
		@unlink(self::$adapter_pid_file);

		self::executeCommand('python3', [
			self::ADAPTER_SCRIPT,
			'--host', self::ADAPTER_HOST,
			'--port', (string) self::getAdapterPort(),
			'--log-file', self::$adapter_log_file,
			'--pid-file', self::$adapter_pid_file
		], true);

		while (!file_exists(self::$adapter_log_file)) {
		}
	}

	public static function stopBridgeAdapterMock(): void {
		if (!isset(self::$adapter_pid_file) || !file_exists(self::$adapter_pid_file)) {
			return;
		}

		$pid = trim(file_get_contents(self::$adapter_pid_file));

		if (ctype_digit($pid) && posix_kill((int) $pid, 0)) {
			posix_kill((int) $pid, SIGTERM);
		}

		while (file_exists(self::$adapter_pid_file)) {
		}
	}

	private static function getAdapterRunId(): string {
		if (!isset(self::$adapter_run_id)) {
			self::$adapter_run_id = self::getAdapterPort().'_'.date('YmdHis').'_'.getmypid();
		}

		return self::$adapter_run_id;
	}

	private static function getAdapterPort(): int {
		if (!isset(self::$adapter_port)) {
			$socket = stream_socket_server('tcp://'.self::ADAPTER_HOST.':0', $error_code, $error_message);

			if ($socket === false) {
				throw new Exception('Cannot reserve bridge-adapter mock port: '.$error_message);
			}

			self::$adapter_port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
			fclose($socket);
		}

		return self::$adapter_port;
	}

	private function assertAdapterRequest(string $method, callable $predicate): void {
		do {
			foreach ($this->readAdapterRequests() as $request) {
				if ($request['method'] === $method && $predicate($request)) {
					$this->addToAssertionCount(1);
					return;
				}
			}
		}
		while (true);
	}

	private function readAdapterRequests(): array {
		if (!file_exists(self::$adapter_log_file)) {
			return [];
		}

		$requests = [];
		foreach (file(self::$adapter_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$requests[] = json_decode($line, true);
		}

		return $requests;
	}

	private function assertRealNotificationActionLog(bool $recovery): void {
		$expected_alerts = [
			[
				'sendto' => self::UNKNOWN_DEVICE_UUID,
				'status' => ALERT_STATUS_FAILED,
				'error' => self::PUSH_ALERT_ERROR_NO_PERMISSION
			],
			[
				'sendto' => self::NOTIFY_DEVICE_UUID,
				'status' => ALERT_STATUS_SENT,
				'error' => ''
			],
			[
				'sendto' => self::OFFBOARD_DEVICE_UUID,
				'status' => ALERT_STATUS_SENT,
				'error' => ''
			]
		];

		$this->callUntilDataIsPresent('alert.get', [
			'output' => ['sendto', 'status', 'error', 'subject', 'message', 'p_eventid'],
			'actionids' => self::$actionids,
			'sortfield' => 'alertid'
		], 25, 1, static function (array $response) use ($expected_alerts, $recovery): bool {
			return self::hasExpectedRealNotificationActionLog($response['result'], $expected_alerts,
				$recovery
			);
		});
	}

	private static function hasExpectedRealNotificationActionLog(array $alerts, array $expected_alerts,
			bool $recovery): bool {
		$alerts = array_values(array_filter($alerts, static function (array $alert) use ($recovery): bool {
			return $recovery ? $alert['p_eventid'] != 0 : $alert['p_eventid'] == 0;
		}));

		foreach ($expected_alerts as $expected_alert) {
			if (!self::hasExpectedRealNotificationAlert($alerts, $expected_alert)) {
				return false;
			}
		}

		return true;
	}

	private static function hasExpectedRealNotificationAlert(array $alerts, array $expected_alert): bool {
		foreach ($alerts as $alert) {
			if ($alert['sendto'] === $expected_alert['sendto']
					&& (int) $alert['status'] === $expected_alert['status']
					&& $alert['error'] === $expected_alert['error']
					&& $alert['subject'] === self::REAL_NOTIFY_SUBJECT
					&& $alert['message'] === self::REAL_NOTIFY_MESSAGE) {
				return true;
			}
		}

		return false;
	}

	private static function isExpectedRealNotificationRequest(array $request, string $type,
			string $deviceid): bool {
		if (array_keys($request['body']) !== ['jsonrpc', 'method', 'params', 'id']) {
			return false;
		}

		if ($request['body']['jsonrpc'] !== '2.0'
				|| $request['body']['method'] !== 'device.notify'
				|| !is_string($request['body']['id'])) {
			return false;
		}

		$params = $request['body']['params'];

		if (array_keys($params) !== ['to', 'payload', 'priority', 'mobile_encryption_key']) {
			return false;
		}

		$push_tokens = [
			self::NOTIFY_DEVICE_UUID => 'bridge-adapter-integration-push-token',
			self::OFFBOARD_DEVICE_UUID => 'bridge-adapter-integration-offboard-push-token'
		];

		if ($params['to'] !== [
			'push_token' => $push_tokens[$deviceid],
			'device_id' => $deviceid
		]) {
			return false;
		}

		if ($params['priority'] !== TRIGGER_SEVERITY_HIGH || $params['mobile_encryption_key'] !== [
			'kty' => 'EC',
			'use' => 'enc',
			'alg' => 'ES256',
			'kid' => 'bridge-adapter-integration-encryption-key',
			'crv' => 'P-256',
			'x' => 'OV6uOpawdTTSC6QsLQSv9tCGVDyt3u0ZpdVCD95vogY',
			'y' => 'bnJ86Qyj0HDCrzNo2GOyvOTJA9lCiUEmLUXLcwLZeOQ'
		]) {
			return false;
		}

		$payload = $params['payload'];

		if (array_keys($payload) !== [
			'specversion', 'id', 'time', 'type', 'source', 'subject', 'schema', 'data'
		]) {
			return false;
		}

		if ($payload['specversion'] !== '1'
				|| !is_string($payload['id'])
				|| !is_int($payload['time'])
				|| $payload['type'] !== $type
				|| $payload['source'] !== 'zabbix/server'
				|| !preg_match('/^event\/[0-9]+$/', $payload['subject'])
				|| $payload['schema'] !== 'urn:zabbix:server:event:1') {
			return false;
		}

		$data = $payload['data'];

		if (array_keys($data) !== ['title', 'body', 'eventid', 'hostids', 'triggerid', 'userid', 'severity']) {
			return false;
		}

		return $data['title'] === self::REAL_NOTIFY_SUBJECT
			&& $data['body'] === self::REAL_NOTIFY_MESSAGE
			&& is_int($data['eventid'])
			&& $payload['subject'] === 'event/'.$data['eventid']
			&& $data['hostids'] === [(int) self::$real_notify_hostid]
			&& $data['triggerid'] === (int) self::$triggerids[0]
			&& $data['userid'] === 1
			&& $data['severity'] === TRIGGER_SEVERITY_HIGH;
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_init(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_init()', true,
			120, 1
		);

		$this->assertNotFalse($init_response, $client->getError() ?? '');
		$this->assertSame('mock-mobile-enrollment-token', $init_response['mobile_enrollment_token']);
		$this->assertSame('afd196fd-64f4-4a9d-989f-c61f8c5d5f31',
			$init_response['bridge_adapter_encryption_key']['kid']
		);
		$this->assertSame('enc', $init_response['bridge_adapter_encryption_key']['use']);
		$this->assertSame('P-256', $init_response['bridge_adapter_encryption_key']['crv']);
		$this->assertSame('enroll.zabbixmobile.com', $init_response['bridge_enrollment_url']);

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notify(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$mediatypeid = CDBHelper::getValue(
			'select mediatypeid from media_type where type='.MEDIA_TYPE_PUSH.' order by mediatypeid'
		);

		$result = $client->testMediaType([
			'mediatypeid' => $mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertNotFalse($result, $client->getError() ?? '');
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_realNotification(): void {
		$this->createRealNotificationObjects();
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->sendSenderValue(self::REAL_NOTIFY_HOST, self::REAL_NOTIFY_ITEM_KEY, 6, self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.created',
					self::NOTIFY_DEVICE_UUID);
		});
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.created',
					self::OFFBOARD_DEVICE_UUID);
		});
		$this->assertRealNotificationActionLog(false);

		$this->sendSenderValue(self::REAL_NOTIFY_HOST, self::REAL_NOTIFY_ITEM_KEY, 1, self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.recovered',
					self::NOTIFY_DEVICE_UUID);
		});
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.recovered',
					self::OFFBOARD_DEVICE_UUID);
		});
		$this->assertRealNotificationActionLog(true);
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_offboard(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_offboard()', true,
				120, 1);

		$this->assertNotFalse($offboard_response, $client->getError() ?? '');

		$this->assertAdapterRequest('device.deactivate', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::OFFBOARD_DEVICE_UUID;
		});
	}

}
