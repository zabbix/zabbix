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


/**
 * Class containing methods for managing the user's mobile devices.
 */
class CDevice extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'init' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'onboard' => []
	];

	protected $tableName = 'device';
	protected $tableAlias = 'd';
	protected $sortColumns = ['name', 'last_access_time'];

	private array $output_fields = ['deviceid', 'userid', 'uuid', 'name', 'status', 'last_access_time'];

	public const DEVICE_STATUS_NEW = 0;
	public const DEVICE_STATUS_ENABLED = 1;
	public const DEVICE_STATUS_DISABLED = 2;

	private const ENROLLMENT_TOKEN_EXPIRATION_TTL = 600;

	private const TASK_DEVICE_CHECK_TTL = 30;

	public const MOBILE_IDENTITY_KEY = 0;
	public const MOBILE_ENCRYPTION_KEY = 1;

	public const DEVICE_KEY_ACTIVE = 0;

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// output
			'output' =>		  ['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>  ['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>	  ['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>	  ['type' => API_SORTORDER, 'default' => []],
			'limit' =>		  ['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' => ['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		$result = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		$db_devices = [];

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_devices[$row['deviceid']] = $row;
		}

		return $options['preservekeys'] ? $db_devices : array_values($db_devices);
	}

	/**
	 * @param array  $user
	 *
	 * @return array
	 */
	public function init(array $user): array {
		$this->validateInit($user, $db_user);

		$enrollment_token = CApiTokenHelper::generateToken();
		$uuid = generateUuidV7();
		$time_start = time();

		$deviceid = self::createDevice($db_user, $enrollment_token, $uuid, $time_start);

		$taskid = self::createTask($deviceid, $time_start);

		// todo - AuditLog $enrollment_token

		return [
			'enrollment_token' => $enrollment_token,
			'uuid' => $uuid,
			'taskid' => $taskid
		];
	}

	/**
	 * @param array  $options
	 *
	 * @return array
	 */
	public function onboard(array $options): array {
		$this->validateOnboard($options, $db_device);

		self::createDeviceKeys($db_device['deviceid'], $options['mobile_identity_key'],
			$options['mobile_encryption_key']
		);

		$tokens_data = CToken::createForce([[
			'name' => $db_device['uuid'],
			'userid' => $db_device['userid'],
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'auth_type' => ZBX_API_HEADER_AUTHENTICATE_DPOP,
			'expires_at' => 0
		]], $db_device['userid'], false);

		$db_tokens = CToken::generateForce($tokens_data['tokenids'], $db_device['userid'], false);

		$db_token = reset($db_tokens);

		DB::update('device', [
			'values' => ['name' => $options['device_name'], 'tokenid' =>$db_token['tokenid'],
				'push_token' => $options['push_token'], 'status' => self::DEVICE_STATUS_ENABLED
			],
			'where' => ['deviceid' => $db_device['deviceid']]
		]);

		// todo - auditlog

		return ['token' => $db_token['token']];
	}

	private function validateInit(array $user, ?array &$db_user): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'userid' =>	['type' => API_ID]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($user) {
			if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && $user['userid'] != self::$userData['userid']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			$db_users = API::User()->get([
				'output' => ['userid'],
				'userids' => $user['userid'],
				'editable' => true,
				'preservekeys' => true
			]);

			if (!$db_users) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			$db_user = $db_users[$user['userid']];
		}
		else {
			$db_user = ['userid' => self::$userData['userid']];
		}
	}

	private function validateOnboard(array $options, ?array &$db_device): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'enrollment_token' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'mobile_identity_key' => self::getJwkValidationRules(),
			'mobile_encryption_key' => self::getJwkValidationRules(),
			'push_token' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!CApiDpopHelper::checkJwkIntegrity($options['mobile_identity_key']) ||
				!CApiDpopHelper::checkJwkIntegrity($options['mobile_encryption_key'])) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		$db_enrollment_token = DBfetch(DBselect(
			'SELECT et.deviceid'.
			' FROM enrollment_token et'.
			' WHERE '.dbConditionString('et.enrollment_token', [hash('sha512', $options['enrollment_token'])]).
			' AND enrollment_token_expiration>'.time(),
			1
		));

		if (!$db_enrollment_token) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		DB::delete('enrollment_token', ['deviceid' => $db_enrollment_token['deviceid']]);

		$db_devices = DB::select('device', [
			'output' => ['deviceid', 'uuid', 'userid'],
			'filter' => ['deviceid' => $db_enrollment_token['deviceid']]
		]);

		$db_device = reset($db_devices);
	}

	private static function getJwkValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
			'crv' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => ['P-256']],
			'kty' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => ['EC']],
			'kid' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'x' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 32],
			'y' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => 32]
		]];
	}

	private static function createDevice(array $db_user, string $enrollment_token, string $uuid,
			int $time_start): string {
		$ins_device = [
			'userid' => $db_user['userid'],
			'uuid' => $uuid,
			'status' => self::DEVICE_STATUS_NEW
		];

		$deviceids = DB::insertBatch('device', [$ins_device]);

		$deviceid = array_shift($deviceids);

		$ins_enrollment_token = [
			'deviceid' => $deviceid,
			'enrollment_token' => hash('sha512', $enrollment_token),
			'enrollment_token_expiration' => $time_start + self::ENROLLMENT_TOKEN_EXPIRATION_TTL
		];

		DB::insertBatch('enrollment_token', [$ins_enrollment_token], false);

		return $deviceid;
	}

	private static function createTask(string $deviceid, int $time_start): string {
		$ins_task = [
			'type' =>ZBX_TM_TASK_ENROLL_DEVICE,
			'status' => ZBX_TM_STATUS_NEW,
			'clock' => $time_start,
			'ttl' => self::TASK_DEVICE_CHECK_TTL
		];

		$taskids = DB::insertBatch('task', [$ins_task]);

		$taskid = array_shift($taskids);

		$ins_task_device = [
			'taskid' => $taskid,
			'deviceid' => $deviceid
		];

		DB::insertBatch('task_device', [$ins_task_device], false);

		return $taskid;
	}

//	private static function getTaskDeviceResult(string $taskid, int $time_start): array {
//		do {
//			sleep(self::TASK_DEVICE_CHECK_DELAY);
//
//			$task_result = DBfetch(DBselect(
//				'SELECT t.status,td.mobile_enrollment_token,td.bridge_enrollment_key,td.enrollment_url,'.
//					'td.status AS task_device_status,td.info'.
//				' FROM task t'.
//				' JOIN task_device td ON td.taskid=t.taskid'.
//				' WHERE '.dbConditionId('t.taskid', [$taskid]),
//				1
//			));
//
//			$is_init_process_expired = time() > $time_start + self::TASK_DEVICE_CHECK_TTL;
//		}
//		while ($task_result['status'] <= ZBX_TM_STATUS_INPROGRESS && !$is_init_process_expired);
//
//		if ($is_init_process_expired) {
//			$task_result['task_device_status'] = self::TASK_DEVICE_STATUS_FAILED;
//		}
//
//		return $task_result;
//	}

	private static function createDeviceKeys(string $deviceid, array $mobile_identity_key,
			array $mobile_encryption_key): void {
		$device_keyid = DB::reserveIds('device_key', 2);

		$fields = [
			[
				'device_keyid' => $device_keyid,
				'deviceid' => $deviceid,
				'scope' => self::MOBILE_IDENTITY_KEY,
				'kid' => $mobile_identity_key['kid'],
				'key_' => json_encode($mobile_identity_key)
			],
			[
				'device_keyid' => bcadd($device_keyid, 1, 0),
				'deviceid' => $deviceid,
				'scope' => self::MOBILE_ENCRYPTION_KEY,
				'kid' => $mobile_encryption_key['kid'],
				'key_' => json_encode($mobile_encryption_key)
			]
		];

		DB::insertBatch('device_key', $fields, false);
	}
}
