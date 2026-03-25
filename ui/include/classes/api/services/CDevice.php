<?php declare(strict_types = 0);
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
 * Device API implementation.
 */
class CDevice extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'init' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'onboard' => [],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'device';
	protected $tableAlias = 'd';
	protected $sortColumns = ['deviceid', 'name'];

	public const OUTPUT_FIELDS = ['deviceid', 'userid', 'uuid', 'name', 'created_at', 'lastaccess'];

	private const ENROLLMENT_TOKEN_EXPIRATION_TTL = 600;
	private const TASK_DEVICE_INIT_TTL = 30;
	private const TASK_DEVICE_OFFBOARD_TTL = 86400;

	private const STATUS_NEW = 0;
	private const STATUS_ENABLED = 1;

	public const MOBILE_IDENTITY_KEY = 0;
	public const MOBILE_ENCRYPTION_KEY = 1;

	public const DEVICE_KEY_ACTIVE = 0;

	/**
	 * @return array|string
	 */
	public function get(array $options = []) {
		self::validateGet($options);

		$resource = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		$db_devices = [];

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_devices[$row['deviceid']] = $row;
		}

		if ($db_devices) {
			$db_devices = $this->unsetExtraFields($db_devices, ['deviceid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_devices = array_values($db_devices);
			}
		}

		return $db_devices;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filters.
			'deviceids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'roleids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'userids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'usrgrpids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_merge(DB::getFilterFields('device', self::OUTPUT_FIELDS), ['created_at', 'lastaccess'])],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => DB::getSearchFields('device', self::OUTPUT_FIELDS)],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// Output.
			'output' =>					['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			// Sort and limit.
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['deviceid', 'field']), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// Flags.
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sql_parts['where']['userid'] = 'd.userid='.self::$userData['userid'];
		}

		$sql_parts['where'][] = 'd.status='.self::STATUS_ENABLED;

		if ($options['roleids'] !== null) {
			$sql_parts['join']['u'] = ['table' => 'users', 'using' => 'userid'];
			$sql_parts['where'][] = dbConditionId('u.roleid', $options['roleids']);
		}

		if ($options['userids'] !== null) {
			$sql_parts['where'][] = dbConditionId('d.userid', $options['userids']);
		}

		if ($options['usrgrpids'] !== null) {
			$sql_parts['join']['ug'] = ['table' => 'users_groups', 'using' => 'userid'];
			$sql_parts['where'][] = dbConditionId('ug.usrgrpid', $options['usrgrpids']);
		}

		if ($options['filter'] !== null) {
			$token_filter = array_intersect_key($options['filter'], array_flip(['created_at', 'lastaccess']));

			if ($token_filter) {
				$sql_parts['join']['t'] = ['type' => 'left', 'table' => 'token', 'using' => 'tokenid'];
				$this->dbFilter('token t', ['filter' => $token_filter] + $options, $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput']) {
			$token_output = array_intersect($options['output'], ['created_at', 'lastaccess']);

			if ($token_output) {
				$sql_parts['join']['t'] = ['type' => 'left', 'table' => 'token', 'using' => 'tokenid'];

				foreach ($token_output as $field) {
					$sql_parts = $this->addQuerySelect('t.'.$field, $sql_parts);
				}
			}
		}

		return $sql_parts;
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

		$deviceid = self::createDevice($db_user['userid'], $enrollment_token, $uuid, $time_start);

		$taskid = self::createInitTask($deviceid, $time_start);

		$device = [
			'deviceid' => $deviceid,
			'uuid' => $uuid,
			'name' => '',
			'userid' => $db_user['userid']
		];

		self::addAuditLog(CAudit::ACTION_INIT, CAudit::RESOURCE_DEVICE, [$device]);

		return [
			'uuid' => $uuid,
			'taskid' => $taskid,
			'enrollment_token' => $enrollment_token
		];
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
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_users = API::User()->get([
				'output' => ['userid'],
				'userids' => $user['userid'],
				'editable' => true,
				'preservekeys' => true
			]);

			if (!$db_users) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_user = $db_users[$user['userid']];
		}
		else {
			$db_user = ['userid' => self::$userData['userid']];
		}
	}

	private static function createDevice(string $userid, string $enrollment_token, string $uuid,
			int $time_start): string {
		$ins_device = [
			'userid' => $userid,
			'uuid' => $uuid,
			'status' => self::STATUS_NEW
		];

		$deviceids = DB::insertBatch('device', [$ins_device]);

		$deviceid = reset($deviceids);

		$ins_device_enrollment_token = [
			'deviceid' => $deviceid,
			'token' => hash('sha512', $enrollment_token),
			'expires_at' => $time_start + self::ENROLLMENT_TOKEN_EXPIRATION_TTL
		];

		DB::insertBatch('device_enrollment_token', [$ins_device_enrollment_token], false);

		return $deviceid;
	}

	private static function createInitTask(string $deviceid, int $time_start): string {
		$ins_task = [
			'type' =>ZBX_TM_TASK_INIT_DEVICE,
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

	/**
	 * @param array  $options
	 *
	 * @return array
	 */
	public function onboard(array $options): array {
		$this->validateOnboard($options, $db_device);

		self::$userData = [
			'userid' => $db_device['userid'],
			'uuid' => $db_device['uuid'],
			'kid' => $options['mobile_encryption_key']['kid'],
			'key' => json_encode($options['mobile_encryption_key'])
		];

		DB::delete('device_enrollment_token', ['deviceid' => $db_device['deviceid']]);

		self::createDeviceKeys($db_device['deviceid'], $options['mobile_identity_key'],
			$options['mobile_encryption_key']
		);

		$tokens_data = CToken::createForce([[
			'name' => $db_device['uuid'],
			'userid' => $db_device['userid'],
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'auth_scheme' => ZBX_AUTH_SCHEME_DPOP,
			'expires_at' => 0
		]], $db_device['userid'], false);

		$db_tokens = DB::select('token', [
			'output' => ['tokenid', 'name', 'token', 'creator_userid'],
			'tokenids' => $tokens_data['tokenids'],
			'preservekeys' => true
		]);

		$db_tokens = CToken::generateForce($db_tokens, false);

		$db_token = reset($db_tokens);

		DB::update('device', [
			'values' => [
				'name' => $options['name'],
				'status' => self::STATUS_ENABLED,
				'push_token' => $options['push_token'],
				'activated_at' => time()
			],
			'where' => ['deviceid' => $db_device['deviceid']]
		]);

		$ins_device_token = [
			'tokenid' => $db_token['tokenid'],
			'deviceid' => $db_device['deviceid']
		];

		DB::insertBatch('device_token', [$ins_device_token]);

		$device = [
			'deviceid' => $db_device['deviceid'],
			'name' => $options['name']
		];

		$db_devices = [
			$db_device['deviceid'] => array_intersect_key($db_device, array_flip(['deviceid', 'name']))
		];

		self::addAuditLogByUser($db_device['userid'], CWebUser::getIp(), $db_device['username'], CAudit::ACTION_ONBOARD,
			CAudit::RESOURCE_DEVICE, [$device], $db_devices
		);

		return ['token' => $db_token['token']];
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

		$db_device_enrollment_token = DBfetch(DBselect(
			'SELECT det.deviceid'.
			' FROM device_enrollment_token det'.
			' WHERE '.dbConditionString('det.token', [hash('sha512', $options['enrollment_token'])]).
				' AND det.expires_at>'.time()
		));

		if (!$db_device_enrollment_token) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		$db_device = DBfetch(DBselect(
			'SELECT d.deviceid,d.uuid,d.userid,d.name,u.name AS username'.
			' FROM device d'.
			' JOIN users u ON d.userid = u.userid'.
			' WHERE '.dbConditionId('d.deviceid', [$db_device_enrollment_token['deviceid']])
		));
	}

	private static function getJwkValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => [
			'crv' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => 'P-256'],
			'kty' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => 'EC'],
			'kid' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'x' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'y' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];
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

	/**
	 * @param array $deviceids
	 *
	 * @return array
	 */
	public function delete(array $deviceids): array {
		$this->validateDelete($deviceids, $db_devices);

		self::deleteForce($db_devices);

		return ['deviceids' => $deviceids];
	}

	private function validateDelete(array $deviceids, ?array &$db_devices): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $deviceids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_devices = $this->get([
			'output' => ['deviceid', 'uuid', 'name'],
			'deviceids' => $deviceids,
			'preservekeys' => true
		]);

		if (count($db_devices) != count($deviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	public static function deleteForce(array $db_devices): void {
		self::createOffboardTasks($db_devices);

		$deviceids = array_keys($db_devices);

		$db_device_tokens = DB::select('device_token', [
			'output' => ['tokenid'],
			'filter' => ['deviceid' => $deviceids],
			'preservekeys' => true
		]);

		$tokenids = array_keys($db_device_tokens);

		DB::delete('device_token', ['tokenid' => $tokenids]);
		DB::delete('token', ['tokenid' => $tokenids]);
		DB::delete('device', ['deviceid' => $deviceids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_DEVICE, $db_devices);
	}

	private static function createOffboardTasks(array $db_devices): void {
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
