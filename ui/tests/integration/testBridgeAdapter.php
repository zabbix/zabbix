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
	private const ADAPTER_URL_HOST = 'bridge.example.com';
	private const ADAPTER_SCRIPT = __DIR__.'/data/bridge_adapter_mock.py';
	private const INIT_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000101';
	private const NOTIFY_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000102';
	private const NOTIFY_DEVICE_NAME = 'Bridge adapter integration notification device';
	private const OFFBOARD_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000103';
	private const OFFBOARD_DEVICE_NAME = 'Bridge adapter integration offboard device';
	private const UNKNOWN_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000104';
	private const REAL_NOTIFY_HOST = 'bridge_adapter_real_notification_host';
	private const REAL_NOTIFY_ITEM_KEY = 'bridge.adapter.real.notify';
	private const REAL_NOTIFY_SUBJECT = 'Bridge adapter real notification';
	private const REAL_NOTIFY_MESSAGE = 'Bridge adapter real notification message';
	private const MEDIA_SEVERITY_ALL = 63;
	private const PUSH_ALERT_ERROR_DEVICE_UNKNOWN = 'Cannot deliver notification, device id is not known.';
	private const PUSH_ALERT_ERROR_DEVICE_NOT_ACTIVE =
		'Cannot deliver notification, target device is not in Active state.';
	private const PUSH_TEST_ERROR_DEVICE_NOT_FOUND = 'Cannot find enabled device for push media type test.';
	private const PUSH_ERROR_NOT_CONFIGURED =
		'Cannot deliver mobile device notification, bridge-adapter is not configured.';
	private const PUSH_ERROR_CANNOT_CONNECT =
		'Cannot deliver mobile device notification, cannot connect to bridge-adapter.';
	private const PUSH_ERROR_INVALID_RESPONSE =
		'Cannot deliver mobile device notification, bridge-adapter returned an invalid response.';
	private const PUSH_ERROR_RETURNED_ERROR =
		'Cannot deliver mobile device notification, bridge-adapter returned an error.';
	private const DEVICE_INIT_ERROR_NOT_CONFIGURED =
		'Cannot initialize mobile device, bridge-adapter is not configured.';
	private const DEVICE_INIT_ERROR_INVALID_RESPONSE =
		'Cannot initialize mobile device, bridge-adapter returned an invalid response.';
	private const DEVICE_INIT_ERROR_RETURNED_ERROR =
		'Cannot initialize mobile device, bridge-adapter returned an error.';
	private const DEVICE_INIT_ERROR_DEVICE_LIMIT_EXCEEDED =
		'Cannot add device because the device limit has been reached. Please remove redundant devices, or '.
		'contact your system administrator.';
	private const DEVICE_OFFBOARD_ERROR_NOT_CONFIGURED =
		'Cannot remove mobile device, bridge-adapter is not configured.';
	private const DEVICE_OFFBOARD_ERROR_INVALID_RESPONSE =
		'Cannot remove mobile device, bridge-adapter returned an invalid response.';
	private const DEVICE_OFFBOARD_ERROR_DEVICE_NOT_FOUND =
		'Cannot unlink device. Please contact your system administrator.';
	private const ERROR_MOBILE_DEVICES_DISABLED = 'Mobile devices are disabled.';
	private const ERROR_PERMISSION_DENIED = 'Permission denied.';
	private const LOG_MOBILE_DEVICES_DISABLED_INIT = 'cannot initialize device: mobile devices are disabled';
	private const LOG_MOBILE_DEVICES_DISABLED_NOTIFY =
		'cannot send device notification: mobile devices are disabled';
	private const LOG_MOBILE_DEVICES_DISABLED_OFFBOARD = 'cannot offboard device: mobile devices are disabled';
	private const LOG_BRIDGE_ADAPTER_URL_NOT_SET = '"BridgeAdapterURL" configuration parameter is not set';
	private const LOG_ADAPTER_CONNECTION_REFUSED = 'failed to connect to bridge-adapter';
	private const LOG_ADAPTER_HTTP_ERROR = 'bridge-adapter returned HTTP 500';
	private const LOG_ADAPTER_MALFORMED_RESPONSE = 'invalid bridge-adapter response body';
	private const LOG_ADAPTER_MISSING_JSONRPC_VERSION =
		'missing JSON-RPC version in bridge-adapter response body';
	private const LOG_ADAPTER_INVALID_JSONRPC_VERSION =
		'invalid JSON-RPC version in bridge-adapter response body';
	private const LOG_ADAPTER_OVERSIZED_RESPONSE_INIT =
		'bridge-adapter returned too large response body for device.init request';
	private const LOG_ADAPTER_OVERSIZED_RESPONSE_NOTIFY =
		'bridge-adapter returned too large response body for device.notify request';
	private const LOG_ADAPTER_INCOMPLETE_ERROR = 'incomplete error in bridge-adapter response body';
	private const LOG_ADAPTER_MISSING_RESULT = 'missing result in bridge-adapter response body';
	private const LOG_ADAPTER_MISSING_RESULT_FIELDS =
		'missing enrollment_token/adapter_enc_key/bridge_url in bridge-adapter';
	private const LOG_INIT_PERMISSION_DENIED = 'cannot initialize device: permission denied for userid';
	private const LOG_OFFBOARD_PERMISSION_DENIED = 'cannot offboard device: permission denied for userid';
	private const LOG_OFFBOARD_UNKNOWN_UUID = 'cannot offboard device: failed to resolve device owner by uuid';
	private const LOG_INIT_INVALID_SESSION = 'cannot initialize device: failed to get user from request';
	private const LOG_INIT_MISSING_USERID = 'cannot initialize device: missing userid in request data';
	private const LOG_INIT_MISSING_UUID = 'missing uuid in device.init request';
	private const LOG_OFFBOARD_INVALID_SESSION = 'cannot offboard device: failed to get user from request';
	private const LOG_OFFBOARD_MISSING_UUID = 'cannot offboard device: missing uuid in request data';
	private const DEVICE_NOT_LINKED_UUID = '019dde8a-4040-7000-8000-000000000105';
	private const CROSS_USER_INIT_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000106';
	private const CROSS_USER_OFFBOARD_DEVICE_UUID = '019dde8a-4040-7000-8000-000000000107';
	private const INVALID_SESSION_ID = 'deadbeefdeadbeefdeadbeefdeadbeef';
	private const RESTRICTED_USER_NAME = 'bridge_adapter_restricted';
	private const RESTRICTED_USER_PASSWD = 'BridgeAdapterR3stricted!';
	private const ACKNOWLEDGER_USER_NAME = 'bridge_adapter_acknowledger';
	private const ACKNOWLEDGER_USER_PASSWD = 'BridgeAdapterAcknowledger#1';
	private const SEVERITY_NOTIFY_HOST = 'bridge_adapter_severity_notification_host';
	private const SEVERITY_NOTIFY_ITEM_KEY = 'bridge.adapter.severity.notify';
	private const SEVERITY_NOTIFY_TRIGGER_NAME = 'Bridge adapter severity notification trigger';
	private const SEVERITY_NOTIFY_SEVERITY = TRIGGER_SEVERITY_WARNING;
	private const HOUSEKEEPER_TEST_JTI = 'bridge-adapter-test-jti';

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
	private static ?string $db_extension = null;
	private static ?string $cert_base_dir = null;
	private static ?string $restricted_userid = null;
	private static ?string $restricted_roleid = null;
	private static ?string $acknowledger_userid = null;
	private static ?string $cross_user_offboard_deviceid = null;
	private static array $severity_hostids = [];
	private static array $severity_itemids = [];
	private static array $severity_triggerids = [];
	private static array $severity_actionids = [];

	public function serverConfigurationProvider(): array {
		if (self::detectTLSLibrary() === 'none') {
			return [
				self::COMPONENT_SERVER => [
					'DebugLevel' => 4,
					'LogFileSize' => 20,
					'EnableMobileDevices' => 1,
					'BridgeAdapterURL' => 'http://'.self::ADAPTER_HOST.':'.self::getAdapterPort().'/rpc'
				]
			];
		}

		self::$cert_base_dir = self::generateCertificates();
		$base_dir = self::$cert_base_dir;

		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'EnableMobileDevices' => 1,
				'TLSCAFile' => $base_dir.'zabbix_ca_file.crt',
				'TLSCertFile' => $base_dir.'zabbix_server.crt',
				'TLSKeyFile' => $base_dir.'zabbix_server.key',
				'BridgeAdapterURL' => 'https://'.self::ADAPTER_URL_HOST.':443/rpc',
				'BridgeAdapterConnectTo' => self::ADAPTER_HOST.':'.self::getAdapterPort()
			]
		];
	}

	public function disabledMobileDevicesConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'EnableMobileDevices' => 0
			]
		];
	}

	public function prepareData(): bool {
		$current_time = time();

		$deviceid = DB::reserveIds('device', 2);
		$device_not_linked_id = DB::reserveIds('device', 1);
		$device_keyid = DB::reserveIds('device_key', 2);

		$response = $this->call('mediatype.get', [
			'output' => ['mediatypeid', 'status'],
			'filter' => ['type' => MEDIA_TYPE_PUSH],
			'sortfield' => 'mediatypeid',
			'limit' => 1
		]);
		$this->assertNotEmpty($response['result']);

		self::$push_mediatypeid = $response['result'][0]['mediatypeid'];
		self::$push_mediatype_status = (int) $response['result'][0]['status'];

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
				'name' => self::NOTIFY_DEVICE_NAME,
				'status' => ZBX_DEVICE_STATUS_ACTIVATED,
				'push_token' => 'bridge-adapter-integration-push-token',
				'activated_at' => $current_time
			],
			[
				'deviceid' => bcadd($deviceid, 1, 0),
				'userid' => 1,
				'uuid' => self::OFFBOARD_DEVICE_UUID,
				'name' => self::OFFBOARD_DEVICE_NAME,
				'status' => ZBX_DEVICE_STATUS_ACTIVATED,
				'push_token' => 'bridge-adapter-integration-offboard-push-token',
				'activated_at' => $current_time
			]
		], false);

		self::$deviceids = [$deviceid, bcadd($deviceid, 1, 0), $device_not_linked_id];

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

		DB::insertBatch('device', [
			[
				'deviceid' => $device_not_linked_id,
				'userid' => 1,
				'uuid' => self::DEVICE_NOT_LINKED_UUID,
				'name' => 'Bridge adapter integration not-linked device',
				'status' => 0,
				'push_token' => null,
				'activated_at' => null
			]
		], false);

		$response = $this->call('user.update', [
			'userid' => 1,
			'medias' => [
				[
					'mediatypeid' => $push_mediatypeid,
					'sendto' => [self::NOTIFY_DEVICE_UUID, self::UNKNOWN_DEVICE_UUID,
						self::DEVICE_NOT_LINKED_UUID, '*'],
					'active' => MEDIA_STATUS_ACTIVE,
					'severity' => self::MEDIA_SEVERITY_ALL,
					'period' => '1-7,00:00-24:00'
				]
			]
		]);
		$this->assertArrayHasKey('userids', $response['result']);

		return true;
	}

	/**
	 * Create the restricted role and user on first use.
	 *
	 * The "devices.actions.default_access" rule is only accepted once the server has reported that mobile
	 * devices are enabled, which only happens after the server component is started. prepareData() runs
	 * before that, so this cannot be created there and is instead created lazily by the test cases that
	 * need it.
	 */
	private function ensureRestrictedUser(): void {
		if (self::$restricted_userid !== null) {
			return;
		}

		$response = $this->call('role.create', [
			'name' => 'bridge_adapter_restricted_role',
			'type' => USER_TYPE_ZABBIX_USER,
			'rules' => ['devices.actions.default_access' => ZBX_ROLE_RULE_DISABLED]
		]);
		$this->assertArrayHasKey('roleids', $response['result']);
		self::$restricted_roleid = $response['result']['roleids'][0];

		$response = $this->call('user.create', [
			'username' => self::RESTRICTED_USER_NAME,
			'passwd' => self::RESTRICTED_USER_PASSWD,
			'roleid' => self::$restricted_roleid,
			'usrgrps' => [['usrgrpid' => 8]]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		self::$restricted_userid = $response['result']['userids'][0];
	}

	/**
	 * Creates a device belonging to the restricted (non-super-admin) user, so that a super-admin
	 * session can offboard it to exercise the "manage user" cross-user permission path. There is no
	 * API to create an already-activated device, so this is inserted directly, mirroring prepareData().
	 */
	private function ensureCrossUserOffboardDevice(): void {
		$this->ensureRestrictedUser();

		if (self::$cross_user_offboard_deviceid !== null) {
			return;
		}

		$deviceid = DB::reserveIds('device', 1);

		DB::insertBatch('device', [
			[
				'deviceid' => $deviceid,
				'userid' => self::$restricted_userid,
				'uuid' => self::CROSS_USER_OFFBOARD_DEVICE_UUID,
				'name' => 'Bridge adapter integration cross-user offboard device',
				'status' => ZBX_DEVICE_STATUS_ACTIVATED,
				'push_token' => 'bridge-adapter-integration-cross-user-push-token',
				'activated_at' => time()
			]
		], false);

		self::$cross_user_offboard_deviceid = $deviceid;
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

		// The "device" API is gated on the server having reported mobile devices as enabled, which only
		// happens once the server component is started, so it cannot be used here.
		$deviceids = array_keys(DB::select('device', [
			'output' => [],
			'filter' => ['uuid' => [self::NOTIFY_DEVICE_UUID, self::OFFBOARD_DEVICE_UUID,
				self::DEVICE_NOT_LINKED_UUID]],
			'preservekeys' => true
		]));

		if ($deviceids) {
			DB::delete('device_enrollment_token', ['deviceid' => $deviceids]);
			DB::delete('device_key', ['deviceid' => $deviceids]);
			DB::delete('token_device', ['deviceid' => $deviceids]);
			DB::delete('device', ['deviceid' => $deviceids]);
		}

		$user_response = $this->call('user.get', [
			'output' => ['userid'],
			'filter' => ['username' => self::RESTRICTED_USER_NAME]
		]);

		if ($user_response['result']) {
			$this->call('user.delete', array_column($user_response['result'], 'userid'));
		}

		$role_response = $this->call('role.get', [
			'output' => ['roleid'],
			'filter' => ['name' => 'bridge_adapter_restricted_role']
		]);

		if ($role_response['result']) {
			$this->call('role.delete', array_column($role_response['result'], 'roleid'));
		}

		DB::delete('dpop_jti_cache', ['jti' => self::HOUSEKEEPER_TEST_JTI]);
	}

	public static function clearData(): void {
		self::clearRealNotificationObjects();
		self::clearSeverityNotificationObjects();
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

		if (self::$cross_user_offboard_deviceid !== null) {
			DB::delete('device', ['deviceid' => self::$cross_user_offboard_deviceid]);
			self::$cross_user_offboard_deviceid = null;
		}

		if (self::$restricted_userid !== null) {
			CDataHelper::call('user.delete', [self::$restricted_userid]);
			self::$restricted_userid = null;
		}

		if (self::$restricted_roleid !== null) {
			CDataHelper::call('role.delete', [self::$restricted_roleid]);
			self::$restricted_roleid = null;
		}

		DB::delete('dpop_jti_cache', ['jti' => self::HOUSEKEEPER_TEST_JTI]);
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
				['groupid' => 4] // Zabbix servers
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

		$push_mediatypeid = self::$push_mediatypeid;

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
			],
			'update_operations' => [
				[
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
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		self::$actionids = $response['result']['actionids'];

		// Create a dedicated acknowledger user (not admin/userid=1) so that admin is not excluded
		// from update operation escalation targets due to being the acknowledge author.
		$response = $this->call('role.get', [
			'output' => ['roleid'],
			'filter' => ['type' => USER_TYPE_SUPER_ADMIN],
			'sortfield' => 'roleid',
			'sortorder' => ZBX_SORT_UP,
			'limit' => 1
		]);
		$this->assertNotEmpty($response['result']);

		$super_admin_roleid = $response['result'][0]['roleid'];

		$response = $this->call('user.create', [
			'username' => self::ACKNOWLEDGER_USER_NAME,
			'passwd' => self::ACKNOWLEDGER_USER_PASSWD,
			'roleid' => $super_admin_roleid,
			'usrgrps' => [['usrgrpid' => 7]]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		self::$acknowledger_userid = $response['result']['userids'][0];
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

		if (self::$acknowledger_userid !== null) {
			CDataHelper::call('user.delete', [self::$acknowledger_userid]);
			self::$acknowledger_userid = null;
		}
	}

	/**
	 * Creates a host/item/trigger/action set for a severity other than TRIGGER_SEVERITY_HIGH, with the
	 * message operation using default_msg=1, to prove severity passthrough and default-template
	 * resolution in get_build_push_param.c independently of the custom-subject/message flow already
	 * covered by createRealNotificationObjects().
	 */
	private function createSeverityNotificationObjects(): void {
		$response = $this->call('host.create', [
			'host' => self::SEVERITY_NOTIFY_HOST,
			'groups' => [
				['groupid' => 4] // Zabbix servers
			],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$severity_hostids = $response['result']['hostids'];

		$response = $this->call('item.create', [
			[
				'name' => self::SEVERITY_NOTIFY_ITEM_KEY,
				'key_' => self::SEVERITY_NOTIFY_ITEM_KEY,
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => self::$severity_hostids[0],
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}'
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$severity_itemids = $response['result']['itemids'];

		$response = $this->call('trigger.create', [
			'description' => self::SEVERITY_NOTIFY_TRIGGER_NAME,
			'expression' => 'last(/'.self::SEVERITY_NOTIFY_HOST.'/'.self::SEVERITY_NOTIFY_ITEM_KEY.')>5',
			'priority' => self::SEVERITY_NOTIFY_SEVERITY
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		self::$severity_triggerids = $response['result']['triggerids'];

		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$severity_triggerids[0]
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'Bridge adapter severity notification action',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 1,
						'mediatypeid' => self::$push_mediatypeid
					],
					'opmessage_usr' => [
						['userid' => 1]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		self::$severity_actionids = $response['result']['actionids'];
	}

	private static function clearSeverityNotificationObjects(): void {
		if (self::$severity_actionids) {
			CDataHelper::call('action.delete', self::$severity_actionids);
		}

		if (self::$severity_triggerids) {
			CDataHelper::call('trigger.delete', self::$severity_triggerids);
		}

		if (self::$severity_itemids) {
			CDataHelper::call('item.delete', self::$severity_itemids);
		}

		if (self::$severity_hostids) {
			CDataHelper::call('host.delete', self::$severity_hostids);
		}
	}

	private static function startBridgeAdapterMockInternal(array $extra_args = [], ?bool $tls = null): void {
		$tls ??= (self::detectTLSLibrary() !== 'none');

		self::$adapter_log_file = PHPUNIT_COMPONENT_DIR.'bridge_adapter_mock_'.self::getAdapterRunId().'.log';
		self::$adapter_pid_file = PHPUNIT_COMPONENT_DIR.'bridge_adapter_mock_'.self::getAdapterRunId().'.pid';

		@unlink(self::$adapter_log_file);
		@unlink(self::$adapter_pid_file);

		$tls_args = [];

		if ($tls) {
			if (self::$cert_base_dir === null) {
				self::$cert_base_dir = self::generateCertificates();
			}

			$base_dir = self::$cert_base_dir;
			$tls_args = [
				'--tls', '--mtls',
				'--cert', $base_dir.'bridge_adapter.crt',
				'--key', $base_dir.'bridge_adapter.key',
				'--ca', $base_dir.'zabbix_ca_file.crt'
			];
		}

		self::executeCommand('python3', array_merge([
			self::ADAPTER_SCRIPT,
			'--host', self::ADAPTER_HOST,
			'--port', (string) self::getAdapterPort(),
			'--log-file', self::$adapter_log_file,
			'--pid-file', self::$adapter_pid_file
		], $tls_args, $extra_args), true);

		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;

		while (!file_exists(self::$adapter_log_file) && time() < $deadline) {
		}

		if (!file_exists(self::$adapter_log_file)) {
			throw new Exception('Failed to wait for bridge adapter mock log file.');
		}
	}

	public static function startBridgeAdapterMock(): void {
		self::startBridgeAdapterMockInternal();
	}

	public static function startBridgeAdapterMockWithNotifyError(): void {
		self::startBridgeAdapterMockInternal(['--notify-error']);
	}

	public static function startBridgeAdapterMockWithInitError(): void {
		self::startBridgeAdapterMockInternal(['--init-error']);
	}

	public static function startBridgeAdapterMockWithInitErrorDetail(): void {
		self::startBridgeAdapterMockInternal(['--init-error-detail']);
	}

	public static function startBridgeAdapterMockWithOffboardErrorDetail(): void {
		self::startBridgeAdapterMockInternal(['--offboard-error-detail']);
	}

	public static function startBridgeAdapterMockWithHttpError(): void {
		self::startBridgeAdapterMockInternal(['--status-code', '500']);
	}

	public static function startBridgeAdapterMockWithMalformedJson(): void {
		self::startBridgeAdapterMockInternal(['--malformed-json']);
	}

	public static function startBridgeAdapterMockWithMissingJsonRpcVersion(): void {
		self::startBridgeAdapterMockInternal(['--missing-jsonrpc']);
	}

	public static function startBridgeAdapterMockWithInvalidJsonRpcVersion(): void {
		self::startBridgeAdapterMockInternal(['--invalid-jsonrpc-version']);
	}

	public static function startBridgeAdapterMockWithOversizedResponse(): void {
		self::startBridgeAdapterMockInternal(['--oversized-response']);
	}

	public static function startBridgeAdapterMockWithIncompleteError(): void {
		self::startBridgeAdapterMockInternal(['--incomplete-error']);
	}

	public static function startBridgeAdapterMockWithNoResult(): void {
		self::startBridgeAdapterMockInternal(['--init-no-result']);
	}

	public static function startBridgeAdapterMockWithIncompleteResult(): void {
		self::startBridgeAdapterMockInternal(['--init-incomplete-result']);
	}

	public static function startBridgeAdapterMockNoTls(): void {
		self::startBridgeAdapterMockInternal([], false);
	}

	public static function stopBridgeAdapterMock(): void {
		if (!isset(self::$adapter_pid_file) || !file_exists(self::$adapter_pid_file)) {
			return;
		}

		$pid = trim(file_get_contents(self::$adapter_pid_file));

		if (ctype_digit($pid) && posix_kill((int) $pid, 0)) {
			posix_kill((int) $pid, SIGTERM);
		}

		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;

		while (file_exists(self::$adapter_pid_file) && time() < $deadline) {
		}

		if (file_exists(self::$adapter_pid_file)) {
			throw new Exception('Failed to stop bridge adapter mock.');
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

	/**
	 * Reserves a port that nothing is listening on, to simulate a bridge-adapter that is unreachable.
	 */
	private static function getClosedPort(): int {
		$socket = stream_socket_server('tcp://'.self::ADAPTER_HOST.':0', $error_code, $error_message);

		if ($socket === false) {
			throw new Exception('Cannot reserve a closed bridge-adapter port: '.$error_message);
		}

		$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
		fclose($socket);

		return $port;
	}

	private static function getDBExtension(): ?string {
		if (self::$db_extension === null) {
			self::$db_extension = CDBHelper::getValue("SELECT value_str FROM settings WHERE name='db_extension'");
		}

		return self::$db_extension;
	}

	private static function generateCertificates(): string {
		$base_dir = PHPUNIT_COMPONENT_DIR.'bridge_adapter_cert_'.time().'_'.mt_rand(10000, 99999).'/';

		if (!is_dir($base_dir)) {
			mkdir($base_dir, 0777, true);
		}

		$ca_key = $base_dir.'zabbix_ca_file.key';
		$ca_cert = $base_dir.'zabbix_ca_file.crt';
		$server_key = $base_dir.'zabbix_server.key';
		$server_csr = $base_dir.'zabbix_server.csr';
		$server_cert = $base_dir.'zabbix_server.crt';
		$adapter_key = $base_dir.'bridge_adapter.key';
		$adapter_csr = $base_dir.'bridge_adapter.csr';
		$adapter_cert = $base_dir.'bridge_adapter.crt';
		$adapter_ext = $base_dir.'bridge_adapter.ext';

		file_put_contents($adapter_ext, "subjectAltName=DNS:".self::ADAPTER_URL_HOST."\n");

		self::executeOpenSsl('openssl genrsa -out '.escapeshellarg($ca_key).' 4096');
		self::executeOpenSsl('openssl req -x509 -new -nodes -key '.escapeshellarg($ca_key).
				' -sha256 -days 1 -out '.escapeshellarg($ca_cert).' -subj '.escapeshellarg('/CN=ZabbixCA'));

		self::executeOpenSsl('openssl genrsa -out '.escapeshellarg($server_key).' 2048');
		self::executeOpenSsl('openssl req -new -key '.escapeshellarg($server_key).' -out '.
				escapeshellarg($server_csr).' -subj '.escapeshellarg('/CN=zabbix_server'));
		self::executeOpenSsl('openssl x509 -req -in '.escapeshellarg($server_csr).' -CA '.
				escapeshellarg($ca_cert).' -CAkey '.escapeshellarg($ca_key).' -CAcreateserial -out '.
				escapeshellarg($server_cert).' -days 1 -sha256');

		self::executeOpenSsl('openssl genrsa -out '.escapeshellarg($adapter_key).' 2048');
		self::executeOpenSsl('openssl req -new -key '.escapeshellarg($adapter_key).' -out '.
				escapeshellarg($adapter_csr).' -subj '.escapeshellarg('/CN='.self::ADAPTER_URL_HOST));
		self::executeOpenSsl('openssl x509 -req -in '.escapeshellarg($adapter_csr).' -CA '.
				escapeshellarg($ca_cert).' -CAkey '.escapeshellarg($ca_key).' -CAcreateserial -out '.
				escapeshellarg($adapter_cert).' -days 1 -sha256 -extfile '.escapeshellarg($adapter_ext));

		self::verifyCertificate($ca_cert, $server_cert);
		self::verifyCertificate($ca_cert, $adapter_cert);

		return $base_dir;
	}

	private static function executeOpenSsl(string $command): void {
		$output = [];
		$result = 0;

		exec($command.' 2>&1', $output, $result);

		if ($result !== 0) {
			throw new Exception('Failed to execute OpenSSL command: '.implode("\n", $output));
		}
	}

	private static function verifyCertificate(string $ca_cert, string $cert): void {
		$output = [];
		$result = 0;

		exec('openssl verify -CAfile '.escapeshellarg($ca_cert).' '.escapeshellarg($cert).' 2>&1',
				$output, $result);

		if ($result !== 0) {
			throw new Exception('Certificate verification failed: '.implode("\n", $output));
		}
	}

	private function assertAdapterRequest(string $method, callable $predicate): void {
		$deadline = time() + self::WAIT_ITERATIONS * self::WAIT_ITERATION_DELAY;

		do {
			foreach ($this->readAdapterRequests() as $request) {
				if ($request['method'] === $method && $predicate($request)) {
					$this->addToAssertionCount(1);
					return;
				}
			}
		}
		while (time() < $deadline);

		$this->fail('Failed to wait for bridge adapter request with method "'.$method.'".');
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
				'error' => self::PUSH_ALERT_ERROR_DEVICE_UNKNOWN
			],
			[
				'sendto' => self::DEVICE_NOT_LINKED_UUID,
				'status' => ALERT_STATUS_FAILED,
				'error' => self::PUSH_ALERT_ERROR_DEVICE_NOT_ACTIVE
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
			return $recovery ? $alert['p_eventid'] !== '0' : $alert['p_eventid'] === '0';
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
		$required_body_keys = ['jsonrpc', 'method', 'params', 'id'];

		if (count($required_body_keys) !== count($request['body'])
				|| array_diff_key($request['body'], array_flip($required_body_keys))) {
			return false;
		}

		if ($request['body']['jsonrpc'] !== '2.0'
				|| $request['body']['method'] !== 'device.notify'
				|| !is_string($request['body']['id'])) {
			return false;
		}

		$params = $request['body']['params'];

		$required_params_keys = ['to', 'payload', 'priority', 'mobile_encryption_key'];

		if (count($required_params_keys) !== count($params)
				|| array_diff_key($params, array_flip($required_params_keys))) {
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

		$required_payload_keys = ['specversion', 'id', 'time', 'type', 'source', 'subject', 'dataschema', 'data'];

		if (count($required_payload_keys) !== count($payload)
				|| array_diff_key($payload, array_flip($required_payload_keys))) {
			return false;
		}

		if ($payload['specversion'] !== '1'
				|| !is_string($payload['id'])
				|| !is_string($payload['time'])
				|| $payload['type'] !== $type
				|| $payload['source'] !== 'zabbix/server'
				|| !preg_match('/^event\/[0-9]+$/', $payload['subject'])
				|| $payload['dataschema'] !== 'urn:zabbix:server:event:1') {
			return false;
		}

		$data = $payload['data'];

		$required_data_keys = ['title', 'body', 'eventid', 'hostids', 'triggerid', 'severity'];

		if (count($required_data_keys) !== count($data)
				|| array_diff_key($data, array_flip($required_data_keys))) {
			return false;
		}

		return $data['title'] === self::REAL_NOTIFY_SUBJECT
			&& $data['body'] === self::REAL_NOTIFY_MESSAGE
			&& is_int($data['eventid'])
			&& $payload['subject'] === 'event/'.$data['eventid']
			&& $data['hostids'] === [(int) self::$real_notify_hostid]
			&& $data['triggerid'] === (int) self::$triggerids[0]
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
	 * @configurationDataProvider disabledMobileDevicesConfigurationProvider
	 */
	public function testBridgeAdapter_initDisabledMobileDevices(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_MOBILE_DEVICES_DISABLED_INIT, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::ERROR_MOBILE_DEVICES_DISABLED, $client->getError());
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notify(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
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
	public function testBridgeAdapter_notifyMultipleDevices(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID.','.self::OFFBOARD_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertNotFalse($result, $client->getError() ?? '');
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::OFFBOARD_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyMultipleDevicesPartialFailure(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID.','.self::UNKNOWN_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertFalse($result);
		// The unknown UUID never matched a device row, so no name is known for it - error falls back to the UUID.
		$this->assertSame(self::UNKNOWN_DEVICE_UUID.': '.self::PUSH_TEST_ERROR_DEVICE_NOT_FOUND,
			$client->getError()
		);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithNotifyError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyMultipleDevicesFailureIncludesDeviceName(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID.','.self::OFFBOARD_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertFalse($result);
		// Both devices are known (found in the device table), so their names are appended alongside the UUID.
		$this->assertSame(
			self::NOTIFY_DEVICE_NAME.' ('.self::NOTIFY_DEVICE_UUID.'): '.self::PUSH_ERROR_RETURNED_ERROR."\n".
			self::OFFBOARD_DEVICE_NAME.' ('.self::OFFBOARD_DEVICE_UUID.'): '.self::PUSH_ERROR_RETURNED_ERROR,
			$client->getError()
		);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::OFFBOARD_DEVICE_UUID;
		});
	}

	/**
	 * @configurationDataProvider disabledMobileDevicesConfigurationProvider
	 */
	public function testBridgeAdapter_notifyDisabledMobileDevices(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$mediatypeid = self::$push_mediatypeid;

		$result = $client->testMediaType([
			'mediatypeid' => $mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_MOBILE_DEVICES_DISABLED_NOTIFY, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::ERROR_MOBILE_DEVICES_DISABLED, $client->getError());
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

		$response = $this->call('event.get', [
			'output' => ['eventid'],
			'objectids' => self::$triggerids[0],
			'value' => TRIGGER_VALUE_TRUE,
			'sortfield' => 'eventid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		]);
		$this->assertNotEmpty($response['result']);

		$eventid = $response['result'][0]['eventid'];

		// Acknowledge as a different user so that admin (userid=1) is not excluded as the
		// acknowledge author from the update operation escalation target list.
		$this->authorize(self::ACKNOWLEDGER_USER_NAME, self::ACKNOWLEDGER_USER_PASSWD);
		$this->call('event.acknowledge', [
			'eventids' => [$eventid],
			'action' => ZBX_PROBLEM_UPDATE_MESSAGE,
			'message' => 'Bridge adapter update test message'
		]);
		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.updated',
					self::NOTIFY_DEVICE_UUID);
		});
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.updated',
					self::OFFBOARD_DEVICE_UUID);
		});

		$this->sendSenderValue(self::REAL_NOTIFY_HOST, self::REAL_NOTIFY_ITEM_KEY, 1, self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.resolved',
					self::NOTIFY_DEVICE_UUID);
		});
		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return self::isExpectedRealNotificationRequest($request, 'problem.resolved',
					self::OFFBOARD_DEVICE_UUID);
		});
		$this->assertRealNotificationActionLog(true);
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_realNotificationSeverityAndDefaultMessage(): void {
		$this->createSeverityNotificationObjects();
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->sendSenderValue(self::SEVERITY_NOTIFY_HOST, self::SEVERITY_NOTIFY_ITEM_KEY, 6,
			self::COMPONENT_SERVER
		);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			$params = $request['body']['params'];

			if ($params['to']['device_id'] !== self::NOTIFY_DEVICE_UUID
					|| $params['priority'] !== self::SEVERITY_NOTIFY_SEVERITY) {
				return false;
			}

			$data = $params['payload']['data'];

			return $data['severity'] === self::SEVERITY_NOTIFY_SEVERITY
				&& $data['title'] === self::SEVERITY_NOTIFY_HOST.' - '.self::SEVERITY_NOTIFY_TRIGGER_NAME
				&& strpos($data['body'], 'Started on ') === 0;
		});
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

	/**
	 * @configurationDataProvider disabledMobileDevicesConfigurationProvider
	 */
	public function testBridgeAdapter_offboardDisabledMobileDevices(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_MOBILE_DEVICES_DISABLED_OFFBOARD,
			true, 120, 1);

		$this->assertFalse($offboard_response);
		$this->assertSame(self::ERROR_MOBILE_DEVICES_DISABLED, $client->getError());
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithInitError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_init()', true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_RETURNED_ERROR, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithInitErrorDetail
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterErrorDetail(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_init()', true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_DEVICE_LIMIT_EXCEEDED, $client->getError());
		$this->assertSame(CZabbixServer::ERROR_CODE_NONE, $client->getErrorCode());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithHttpError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterHttpError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_HTTP_ERROR, true, 120, 1);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithMalformedJson
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterMalformedResponse(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_MALFORMED_RESPONSE, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithMissingJsonRpcVersion
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterMissingJsonRpcVersion(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_MISSING_JSONRPC_VERSION, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithInvalidJsonRpcVersion
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterInvalidJsonRpcVersion(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_INVALID_JSONRPC_VERSION, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithOversizedResponse
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterOversizedResponse(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_OVERSIZED_RESPONSE_INIT, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithIncompleteError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterIncompleteError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_INCOMPLETE_ERROR, true, 120, 1);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithNoResult
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterMissingResult(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_MISSING_RESULT, true, 120, 1);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithIncompleteResult
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initAdapterIncompleteResult(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_MISSING_RESULT_FIELDS, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
		});
	}

	public function mobileDevicesEnabledConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'EnableMobileDevices' => 1
			]
		];
	}

	public function noBridgeAdapterUrlConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'EnableMobileDevices' => 1,
				'BridgeAdapterURL' => null,
				'BridgeAdapterConnectTo' => null
			]
		];
	}

	/**
	 * @configurationDataProvider noBridgeAdapterUrlConfigurationProvider
	 */
	public function testBridgeAdapter_initNoBridgeAdapterUrl(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_BRIDGE_ADAPTER_URL_NOT_SET, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_NOT_CONFIGURED, $client->getError());
	}

	/**
	 * @configurationDataProvider mobileDevicesEnabledConfigurationProvider
	 */
	public function testBridgeAdapter_initPermissionDenied(): void {
		[$client] = $this->getServerClientAndSid();

		$this->ensureRestrictedUser();
		$this->authorize(self::RESTRICTED_USER_NAME, self::RESTRICTED_USER_PASSWD);
		$restricted_sid = CAPIHelper::getSessionId();

		$init_response = $client->initDevice([
			'userid' => self::$restricted_userid,
			'uuid' => self::INIT_DEVICE_UUID
		], $restricted_sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_INIT_PERMISSION_DENIED, true,
			120, 1
		);

		$this->assertFalse($init_response);
		$this->assertSame(self::ERROR_PERMISSION_DENIED, $client->getError());

		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
	}

	/**
	 * A non-super-admin managing a device that belongs to neither themselves nor the target userid hits
	 * device_check_permissions()'s immediate deny branch (no role_rule lookup at all), which differs from
	 * testBridgeAdapter_initPermissionDenied() above (restricted user managing their OWN device, denied via
	 * the "devices.actions.default_access" role rule lookup).
	 *
	 * @configurationDataProvider mobileDevicesEnabledConfigurationProvider
	 */
	public function testBridgeAdapter_initManageOtherUserDenied(): void {
		[$client] = $this->getServerClientAndSid();

		$this->ensureRestrictedUser();
		$this->authorize(self::RESTRICTED_USER_NAME, self::RESTRICTED_USER_PASSWD);
		$restricted_sid = CAPIHelper::getSessionId();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], $restricted_sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_INIT_PERMISSION_DENIED, true, 120, 1);

		$this->assertFalse($init_response);
		$this->assertSame(self::ERROR_PERMISSION_DENIED, $client->getError());

		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
	}

	/**
	 * A super-admin session initializing a device for a DIFFERENT (non-super-admin) user exercises the
	 * "devices.actions.manage_user" branch of device_check_permissions(), as opposed to every other init
	 * test which always targets userid=1, the super-admin's own id (the "manage_own" branch).
	 *
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_initManageOtherUser(): void {
		$this->ensureRestrictedUser();

		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => self::$restricted_userid,
			'uuid' => self::CROSS_USER_INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_init()', true, 120, 1);

		$this->assertNotFalse($init_response, $client->getError() ?? '');

		$this->assertAdapterRequest('device.init', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::CROSS_USER_INIT_DEVICE_UUID;
		});
	}

	public function testBridgeAdapter_initInvalidSession(): void {
		[$client] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1,
			'uuid' => self::INIT_DEVICE_UUID
		], self::INVALID_SESSION_ID);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_INIT_INVALID_SESSION, true, 120, 1);

		$this->assertFalse($init_response);
	}

	public function testBridgeAdapter_initMissingUserid(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'uuid' => self::INIT_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_INIT_MISSING_USERID, true, 120, 1);

		$this->assertFalse($init_response);
	}

	public function testBridgeAdapter_initMissingUuid(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$init_response = $client->initDevice([
			'userid' => 1
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_INIT_MISSING_UUID, true, 120, 1);

		$this->assertFalse($init_response);
		$this->assertSame(self::DEVICE_INIT_ERROR_INVALID_RESPONSE, $client->getError());
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithNotifyError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$mediatypeid = self::$push_mediatypeid;

		$result = $client->testMediaType([
			'mediatypeid' => $mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_RETURNED_ERROR, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithHttpError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterHttpError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_HTTP_ERROR, true, 120, 1);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithMalformedJson
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterMalformedResponse(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_MALFORMED_RESPONSE, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithMissingJsonRpcVersion
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterMissingJsonRpcVersion(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_MISSING_JSONRPC_VERSION, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithInvalidJsonRpcVersion
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterInvalidJsonRpcVersion(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_INVALID_JSONRPC_VERSION, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithOversizedResponse
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterOversizedResponse(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_OVERSIZED_RESPONSE_NOTIFY, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	/**
	 * Unlike the matching branches in trapper_device_management.c (device.init/device.offboard),
	 * alerter_process_push()'s "error object present but missing code/message" branch logs nothing before
	 * setting the invalid-response error, so this can only be anchored on the generic function-end line.
	 *
	 * @onBeforeOnce startBridgeAdapterMockWithIncompleteError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_notifyAdapterIncompleteError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true, 120, 1);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.notify', static function (array $request): bool {
			return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
		});
	}

	public function notifyConnectionRefusedConfigurationProvider(): array {
		$closed_port = self::getClosedPort();

		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'EnableMobileDevices' => 1,
				'BridgeAdapterURL' => 'http://'.self::ADAPTER_HOST.':'.$closed_port.'/rpc',
				'BridgeAdapterConnectTo' => self::ADAPTER_HOST.':'.$closed_port,
				'TLSCAFile' => null,
				'TLSCertFile' => null,
				'TLSKeyFile' => null
			]
		];
	}

	/**
	 * @configurationDataProvider notifyConnectionRefusedConfigurationProvider
	 */
	public function testBridgeAdapter_notifyConnectionRefused(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$result = $client->testMediaType([
			'mediatypeid' => self::$push_mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_CONNECTION_REFUSED, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_CANNOT_CONNECT, $client->getError());
	}

	/**
	 * @configurationDataProvider noBridgeAdapterUrlConfigurationProvider
	 */
	public function testBridgeAdapter_notifyNoBridgeAdapterUrl(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$mediatypeid = self::$push_mediatypeid;

		$result = $client->testMediaType([
			'mediatypeid' => $mediatypeid,
			'sendto' => self::NOTIFY_DEVICE_UUID,
			'subject' => 'Bridge adapter integration test',
			'message' => 'Bridge adapter integration test message'
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_BRIDGE_ADAPTER_URL_NOT_SET, true,
			120, 1
		);

		$this->assertFalse($result);
		$this->assertSame(self::PUSH_ERROR_NOT_CONFIGURED, $client->getError());
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_offboardUnknownDevice(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::UNKNOWN_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_OFFBOARD_UNKNOWN_UUID, true, 120, 1);

		$this->assertFalse($offboard_response);
	}

	/**
	 * @configurationDataProvider noBridgeAdapterUrlConfigurationProvider
	 */
	public function testBridgeAdapter_offboardNoBridgeAdapterUrl(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_BRIDGE_ADAPTER_URL_NOT_SET, true,
			120, 1
		);

		$this->assertFalse($offboard_response);
		$this->assertSame(self::DEVICE_OFFBOARD_ERROR_NOT_CONFIGURED, $client->getError());
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithOffboardErrorDetail
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_offboardAdapterErrorDetail(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_offboard()', true,
			120, 1
		);

		$this->assertFalse($offboard_response);
		$this->assertSame(self::DEVICE_OFFBOARD_ERROR_DEVICE_NOT_FOUND, $client->getError());
		$this->assertSame(CZabbixServer::ERROR_CODE_DEVICE_NOT_FOUND, $client->getErrorCode());

		$this->assertAdapterRequest('device.deactivate', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::OFFBOARD_DEVICE_UUID;
		});
	}

	/**
	 * @onBeforeOnce startBridgeAdapterMockWithIncompleteError
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_offboardAdapterIncompleteError(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_ADAPTER_INCOMPLETE_ERROR, true, 120, 1);

		$this->assertFalse($offboard_response);
		$this->assertSame(self::DEVICE_OFFBOARD_ERROR_INVALID_RESPONSE, $client->getError());

		$this->assertAdapterRequest('device.deactivate', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::OFFBOARD_DEVICE_UUID;
		});
	}

	public function noMtlsConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'EnableMobileDevices' => 1,
				'BridgeAdapterURL' => 'http://'.self::ADAPTER_URL_HOST.':80/rpc',
				'BridgeAdapterConnectTo' => self::ADAPTER_HOST.':'.self::getAdapterPort(),
				'TLSCAFile' => null,
				'TLSCertFile' => null,
				'TLSKeyFile' => null
			]
		];
	}

	/**
	 * OFFBOARD_DEVICE_UUID belongs to userid=1, not the restricted user, so this already exercises
	 * device_check_permissions()'s immediate deny branch (non-super-admin managing someone else's
	 * device) - the offboard-side counterpart of testBridgeAdapter_initManageOtherUserDenied() above.
	 *
	 * @configurationDataProvider mobileDevicesEnabledConfigurationProvider
	 */
	public function testBridgeAdapter_offboardPermissionDenied(): void {
		[$client] = $this->getServerClientAndSid();

		$this->ensureRestrictedUser();
		$this->authorize(self::RESTRICTED_USER_NAME, self::RESTRICTED_USER_PASSWD);
		$restricted_sid = CAPIHelper::getSessionId();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], $restricted_sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_OFFBOARD_PERMISSION_DENIED, true,
			120, 1
		);

		$this->assertFalse($offboard_response);
		$this->assertSame(self::ERROR_PERMISSION_DENIED, $client->getError());

		$this->authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
	}

	/**
	 * A super-admin session offboarding a device owned by a DIFFERENT (non-super-admin) user exercises
	 * the "devices.actions.manage_user" success branch of device_check_permissions(), as opposed to every
	 * other offboard test which always targets a device owned by userid=1 (the "manage_own" branch).
	 *
	 * @onBeforeOnce startBridgeAdapterMock
	 * @onAfterOnce stopBridgeAdapterMock
	 */
	public function testBridgeAdapter_offboardManageOtherUser(): void {
		$this->ensureCrossUserOffboardDevice();

		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::CROSS_USER_OFFBOARD_DEVICE_UUID
		], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_offboard()', true,
			120, 1
		);

		$this->assertNotFalse($offboard_response, $client->getError() ?? '');

		$this->assertAdapterRequest('device.deactivate', static function (array $request): bool {
			return $request['body']['params']['device_id'] === self::CROSS_USER_OFFBOARD_DEVICE_UUID;
		});
	}

	public function testBridgeAdapter_offboardInvalidSession(): void {
		[$client] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([
			'uuid' => self::OFFBOARD_DEVICE_UUID
		], self::INVALID_SESSION_ID);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_OFFBOARD_INVALID_SESSION, true, 120, 1);

		$this->assertFalse($offboard_response);
	}

	public function testBridgeAdapter_offboardMissingUuid(): void {
		[$client, $sid] = $this->getServerClientAndSid();

		$offboard_response = $client->offboardDevice([], $sid);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, self::LOG_OFFBOARD_MISSING_UUID, true, 120, 1);

		$this->assertFalse($offboard_response);
	}

	public function noConnectToConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'EnableMobileDevices' => 1,
				'BridgeAdapterURL' => 'http://'.self::ADAPTER_HOST.':'.self::getAdapterPort().'/rpc'
			]
		];
	}

	/**
	 * @configurationDataProvider noConnectToConfigurationProvider
	 */
	public function testBridgeAdapter_noConnectTo(): void {
		self::startBridgeAdapterMockNoTls();

		try {
			[$client, $sid] = $this->getServerClientAndSid();

			$init_response = $client->initDevice([
				'userid' => 1,
				'uuid' => self::INIT_DEVICE_UUID
			], $sid);

			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_init()', true,
				120, 1
			);

			$this->assertNotFalse($init_response, $client->getError() ?? '');

			$this->assertAdapterRequest('device.init', static function (array $request): bool {
				return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
			});

			$mediatypeid = self::$push_mediatypeid;

			$notify_result = $client->testMediaType([
				'mediatypeid' => $mediatypeid,
				'sendto' => self::NOTIFY_DEVICE_UUID,
				'subject' => 'Bridge adapter integration test',
				'message' => 'Bridge adapter integration test message'
			], $sid);

			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true,
				120, 1
			);

			$this->assertNotFalse($notify_result, $client->getError() ?? '');

			$this->assertAdapterRequest('device.notify', static function (array $request): bool {
				return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
			});
		} finally {
			self::stopBridgeAdapterMock();
		}
	}

	/**
	 * @configurationDataProvider noMtlsConfigurationProvider
	 */
	public function testBridgeAdapter_noMtls(): void {
		self::startBridgeAdapterMockNoTls();

		try {
			[$client, $sid] = $this->getServerClientAndSid();

			$init_response = $client->initDevice([
				'userid' => 1,
				'uuid' => self::INIT_DEVICE_UUID
			], $sid);

			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_trapper_device_init()', true,
				120, 1
			);

			$this->assertNotFalse($init_response, $client->getError() ?? '');
			$this->assertArrayHasKey('mobile_enrollment_token', $init_response);
			$this->assertArrayHasKey('bridge_enrollment_url', $init_response);

			$this->assertAdapterRequest('device.init', static function (array $request): bool {
				return $request['body']['params']['device_id'] === self::INIT_DEVICE_UUID;
			});

			$mediatypeid = self::$push_mediatypeid;

			$notify_result = $client->testMediaType([
				'mediatypeid' => $mediatypeid,
				'sendto' => self::NOTIFY_DEVICE_UUID,
				'subject' => 'Bridge adapter integration test',
				'message' => 'Bridge adapter integration test message'
			], $sid);

			self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of alerter_process_push()', true,
				120, 1
			);

			$this->assertNotFalse($notify_result, $client->getError() ?? '');

			$this->assertAdapterRequest('device.notify', static function (array $request): bool {
				return $request['body']['params']['to']['device_id'] === self::NOTIFY_DEVICE_UUID;
			});

			$offboard_response = $client->offboardDevice([
				'uuid' => self::OFFBOARD_DEVICE_UUID
			], $sid);

			self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'End of zbx_trapper_device_offboard()', true, 120, 1
			);

			$this->assertNotFalse($offboard_response, $client->getError() ?? '');

			$this->assertAdapterRequest('device.deactivate', static function (array $request): bool {
				return $request['body']['params']['device_id'] === self::OFFBOARD_DEVICE_UUID;
			});
		} finally {
			self::stopBridgeAdapterMock();
		}
	}

	public function testBridgeAdapter_housekeeperDpopJtiCache(): void {
		if (self::getDBExtension() === ZBX_DB_EXTENSION_TIMESCALEDB) {
			$this->markTestSkipped('Housekeeper timing is unreliable on TimescaleDB runs.');
		}

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of zbx_dc_sync_configuration()', true, 30, 1);

		DB::insertBatch('dpop_jti_cache', [[
			'jti' => self::HOUSEKEEPER_TEST_JTI,
			'expires_at' => time() - 100
		]], false);

		$this->assertSame(1, CDBHelper::getCount(
			'SELECT NULL FROM dpop_jti_cache WHERE jti='.zbx_dbstr(self::HOUSEKEEPER_TEST_JTI)
		));

		$this->executeHousekeeper(self::COMPONENT_SERVER);

		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of housekeeping_dpop_jti_cache()', true,
			30, 1
		);

		$this->assertSame(0, CDBHelper::getCount(
			'SELECT NULL FROM dpop_jti_cache WHERE jti='.zbx_dbstr(self::HOUSEKEEPER_TEST_JTI)
		));
	}

}
