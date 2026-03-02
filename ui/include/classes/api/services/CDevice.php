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

	private const TASK_DEVICE_CHECK_DELAY = 1000000; // 1 sec.
	private const TASK_DEVICE_CHECK_TTL = 30;
	private const TASK_DEVICE_STATUS_ERROR = -1;

	public const DEVICE_KEY_SCOPE_IDENTITY = 0;
	public const DEVICE_KEY_SCOPE_ENCRYPTION = 1;

	public const DEVICE_KEY_ACTIVE = 0;

	private const SERVER_ID = 'server_id'; // CSettingsHelper::getPrivate(CSettingsHelper::SERVER_ID)

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
	 * @param string $user['userid']
	 *
	 * @return array
	 */
	public function init(array $user): array {
		$this->validateInit($user, $db_user);

		$enrollment_token = bin2hex(random_bytes(32));
		$uuid = generateUuidV7();
		$time_start = time();

		$taskid = self::makeTaskDeviceData($db_user, $enrollment_token, $uuid, $time_start);

		// checking for task result
		do {
			usleep(self::TASK_DEVICE_CHECK_DELAY);

			$result = DBfetch(DBselect(
				'SELECT t.status,td.mobile_enrollment_token,td.bridge_enrollment_key,td.enrollment_url,'.
					'td.status AS subtask_status,td.info'.
				' FROM task t'.
				' JOIN task_device td ON td.taskid=t.taskid'.
				' WHERE '.dbConditionId('t.taskid', [$taskid]),
				1
			));

			$is_init_process_expired = time() > $time_start + self::TASK_DEVICE_CHECK_TTL;
		}
		while ($result['status'] <= ZBX_TM_STATUS_INPROGRESS && !$is_init_process_expired);

		if ($result['subtask_status'] == self::TASK_DEVICE_STATUS_ERROR || $is_init_process_expired) {
			self::exception(ZBX_API_ERROR_NO_ENTITY,
				self::$userData['type'] == USER_TYPE_SUPER_ADMIN && $result['info'] !== ''
					? $result['info']
					: _('No permissions to referred object or it does not exist!') // todo - what message?
			);
		}

		return [
			'enrollment_token' => $enrollment_token,
			'mobile_enrollment_token' => $result['mobile_enrollment_token'],
			'bridge_enrollment_key' => $result['bridge_enrollment_key'],
			'enrollment_url' => $result['enrollment_url'],
			'device_id' => $uuid,
			'server_id' => self::SERVER_ID // todo - get server_id by method
		];
	}

	/**
	 * @param array  $options
	 * @param string $options['enrollment_token']
	 * @param string $options['mobile_identity_key']
	 * @param string $options['mobile_encryption_key']
	 * @param string $options['push_token']
	 * @param string $options['device_name']
	 *
	 * @return array
	 */
	public function onboard(array $options): array {
		$this->validateOnboard($options, $db_device);

		// Store MIK, MEK.
		$fields = [
			['deviceid' => $db_device['deviceid'], 'scope' => self::DEVICE_KEY_SCOPE_IDENTITY,
				'key' => $options['mobile_identity_key']
			],
			['deviceid' => $db_device['deviceid'], 'scope' => self::DEVICE_KEY_SCOPE_ENCRYPTION,
				'key' => $options['mobile_encryption_key']
			]
		];
		DB::insertBatch('device_key', $fields, false);

		// Generate API token
		$tokens_data = CToken::createForce([[
			'name' => $options['device_name'],
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
			$db_user = self::$userData;
		}
	}

	private static function makeTaskDeviceData(array $db_user, string $enrollment_token, string $uuid,
			int $time_start): string {
		$fields = [
			'userid' => $db_user['userid'],
			'uuid' => $uuid,
			'status' => self::DEVICE_STATUS_NEW
		];

		try {
			DBstart();

			$deviceids = DB::insertBatch('device', [$fields]);

			$deviceid = array_shift($deviceids);

			$fields = [
				'deviceid' => $deviceid,
				'enrollment_token' => hash('sha512', $enrollment_token),
				'enrollment_token_expiration' => $time_start + self::ENROLLMENT_TOKEN_EXPIRATION_TTL
			];

			DB::insertBatch('enrollment_token', [$fields], false);

			$fields = [
				'type' =>ZBX_TM_TASK_ENROLL_DEVICE,
				'status' => ZBX_TM_STATUS_NEW,
				'clock' => $time_start,
				'ttl' => self::TASK_DEVICE_CHECK_TTL
			];

			$taskids = DB::insertBatch('task', [$fields]);

			$taskid = array_shift($taskids);

			$fields = [
				'taskid' => $taskid,
				'deviceid' => $deviceid
			];

			DB::insertBatch('task_device', [$fields], false);

			DBend(true);
		}
		catch (Exception $e) {
			DBend(false);

			self::exception(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		return $taskid;
	}

	private function validateOnboard(array $options, ?array &$db_device): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'enrollment_token' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'mobile_identity_key' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'mobile_encryption_key' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'push_token' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'device_name' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_enrollment_tokens = DB::select('enrollment_token', [
			'output' => ['deviceid', 'enrollment_token_expiration'],
			'filter' => ['enrollment_token' => hash('sha512', $options['enrollment_token'])]
		]);

		if (!$db_enrollment_tokens) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		$db_enrollment_token = reset($db_enrollment_tokens);

		DB::delete('enrollment_token', ['deviceid' => $db_enrollment_token['deviceid']]);

		if ($db_enrollment_token['enrollment_token_expiration'] < time()) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		$db_devices = DB::select('device', [
			'output' => ['deviceid', 'userid'],
			'where' => ['deviceid' => $db_enrollment_token['deviceid']]
		]);

		$db_device = reset($db_devices);
	}
}
