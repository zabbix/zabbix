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
		'onboard' => [],
		'offboard' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'device';
	protected $tableAlias = 'd';
	protected $sortColumns = ['name', 'lastaccess'];

	public const OUTPUT_FIELDS = ['deviceid', 'userid', 'tokenid', 'uuid', 'name', 'status', 'lastaccess'];

	private const DEVICE_STATUS_NEW = 0;
	private const DEVICE_STATUS_ENABLED = 1;

	private const ENROLLMENT_TOKEN_EXPIRATION_TTL = 600;

	private const TASK_DEVICE_INIT_TTL = 30;
	private const TASK_DEVICE_OFFBOARD_TTL = 86400;

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
			'output' =>		  ['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
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
			$options['output'] = self::OUTPUT_FIELDS;
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

		$taskid = self::createTaskInit($deviceid, $time_start);

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

	public function delete(array $deviceids): array {
		$this->validateDelete($deviceids, $db_devices);

		$deviceids = self::deleteForce($db_devices);

		// todo - auditlog

		return ['deviceids' => $deviceids];
	}

	public static function deleteForce(array $db_devices): array {
		self::createTasksOffboard($db_devices);

		$deviceids = array_keys($db_devices);

		DBexecute(
			'DELETE FROM token'.
			' WHERE EXISTS ('.
				'SELECT NULL'.
				' FROM device d'.
				' WHERE token.tokenid=d.tokenid'.
					' AND '.dbConditionId('d.deviceid', $deviceids).
			')'
		);

		DB::delete('device', ['deviceid' => $deviceids]);

		return $deviceids;
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

	private function validateDelete(array $deviceids, ?array &$db_devices): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $deviceids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_devices = $this->get([
			'output' => ['deviceid', 'uuid'],
			'deviceids' => $deviceids,
			'preservekeys' => true
		]);

		if (count($db_devices) != count($deviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
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

	private static function createTaskInit(string $deviceid, int $time_start): string {
		$ins_task = [
			'type' =>ZBX_TM_TASK_ENROLL_DEVICE,
			'status' => ZBX_TM_STATUS_NEW,
			'clock' => $time_start,
			'ttl' => self::TASK_DEVICE_INIT_TTL
		];

		$taskids = DB::insertBatch('task', [$ins_task]);

		$taskid = array_shift($taskids);

		$ins_task_device_init = [
			'taskid' => $taskid,
			'deviceid' => $deviceid
		];

		DB::insertBatch('task_device_init', [$ins_task_device_init], false);

		return $taskid;
	}

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

	private static function createTasksOffboard(array $db_devices): void {
		$device_cnt = count(array_keys($db_devices));
		$taskid = DB::reserveIds('task', $device_cnt);

		$time = time();

		$ins_tasks = [];
		$ins_task_device_offboards = [];

		foreach ($db_devices as $db_device) {
			$ins_tasks[] = [
				'taskid' => $taskid,
				'type' =>ZBX_TM_TASK_OFFBOARD_DEVICE,
				'status' => ZBX_TM_STATUS_NEW,
				'clock' => $time,
				'ttl' => self::TASK_DEVICE_OFFBOARD_TTL
			];

			$ins_task_device_offboards[] = [
				'taskid' => $taskid,
				'uuid' => $db_device['uuid']
			];

			$taskid = bcadd($taskid, 1, 0);
		}

		DB::insertBatch('task', $ins_tasks, false);
		DB::insertBatch('task_device_offboard', $ins_task_device_offboards, false);
	}
}
