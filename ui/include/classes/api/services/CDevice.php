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
		'offboard' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName = 'device';
	protected $tableAlias = 'd';
	protected $sortColumns = ['deviceid', 'name'];

	public const OUTPUT_FIELDS = ['deviceid', 'userid', 'uuid', 'name', 'status', 'activated_at', 'lastaccess'];

	private const ENROLLMENT_TOKEN_EXPIRATION_TTL = 600;

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
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => DB::getFilterFields('device', self::OUTPUT_FIELDS)],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => DB::getSearchFields('device', self::OUTPUT_FIELDS)],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// Output.
			'output' =>					['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			// Sort and limit.
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['deviceid', 'name']), 'uniq' => true, 'default' => []],
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
			$sql_parts['where'][] = 'd.userid='.self::$userData['userid'];
		}

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

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput'] && in_array('lastaccess', $options['output'])) {
			$sql_parts = $this->addQuerySelect(dbConditionCoalesce('t.lastaccess', 0, 'lastaccess'), $sql_parts);
			$sql_parts['join']['td'] = ['type' => 'left', 'table' => 'token_device', 'using' => 'deviceid'];
			$sql_parts['join']['t'] = ['type' => 'left', 'left_table' => 'td', 'table' => 'token',
				'using' => 'tokenid'
			];
		}

		return $sql_parts;
	}

	/**
	 * @param array  $data
	 *
	 * @return array
	 */
	public function init(array $data = []): array {
		if (self::$userData['username'] === ZBX_GUEST_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->validateInit($data);

		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::DEVICE_LINK_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$uuid = generateUuidV7();

		$result = $server->initDevice($data + ['uuid' => $uuid], self::getAuthIdentifier());

		if ($result === false) {
			self::exception(ZBX_API_ERROR_INTERNAL, $server->getError());
		}

		$enrollment_token = CApiTokenHelper::generateToken();

		$ins_device = [
			'uuid' => $uuid,
			'userid' => $data['userid']
		];

		$deviceids = DB::insertBatch('device', [$ins_device]);

		$deviceid = reset($deviceids);

		$enrollment_token_expires_at = time() + self::ENROLLMENT_TOKEN_EXPIRATION_TTL;

		$ins_device_enrollment_token = [
			'deviceid' => $deviceid,
			'token' => CApiTokenHelper::hashToken($enrollment_token),
			'expires_at' => $enrollment_token_expires_at
		];

		DB::insertBatch('device_enrollment_token', [$ins_device_enrollment_token], false);

		$device = [
			'deviceid' => $deviceid,
			'name' => '',
			'userid' => $data['userid'],
			'uuid'=> $uuid
		];

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_DEVICE, [$device]);

		return [
			'uuid' => $uuid,
			'enrollment_token' => $enrollment_token,
			'enrollment_token_expires_at' => $enrollment_token_expires_at,
			'mobile_enrollment_token' => $result['mobile_enrollment_token'],
			'bridge_adapter_encryption_key' => $result['bridge_adapter_encryption_key'],
			'bridge_enrollment_url' => $result['bridge_enrollment_url']
		];
	}

	private function validateInit(array &$data): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'userid' =>	['type' => API_ID, 'default' => self::$userData['userid']]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (bccomp($data['userid'], self::$userData['userid']) == 0) {
			if (!self::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to manage own devices.'));
			}

			return;
		}

		if (!self::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to manage user devices.'));
		}

		$db_users = API::User()->get([
			'output' => ['username'],
			'selectRole' => ['roleid'],
			'userids' => $data['userid']
		]);

		if (!$db_users) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		if ($db_users[0]['username'] === ZBX_GUEST_USER
				|| !CRoleHelper::checkAccess(CRoleHelper::DEVICES_ACCESS, $db_users[0]['role']['roleid'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('User is not allowed to use linked devices.'));
		}
	}

	/**
	 * @param array  $options
	 *
	 * @return array
	 */
	public function onboard(array $options): array {
		$this->validateOnboard($options, $db_device);

		self::$userData = [
			'userid' => null,
			'userip' => CWebUser::getIp(),
			'username' => 'System',
			'uuid' => $db_device['uuid'],
			'kid' => $options['mobile_encryption_key']['kid'],
			'key' => json_encode($options['mobile_encryption_key'])
		];

		DB::delete('device_enrollment_token', ['deviceid' => $db_device['deviceid']]);

		$token = CApiTokenHelper::generateToken();
		$tokens = [[
			'name' => $db_device['uuid'],
			'userid' => $db_device['userid'],
			'token' => CApiTokenHelper::hashToken($token),
			'auth_scheme' => ZBX_AUTH_SCHEME_DPOP
		]];

		CToken::createForce($tokens, false);

		$ins_token_device = [
			'tokenid' => $tokens[0]['tokenid'],
			'deviceid' => $db_device['deviceid']
		];

		DB::insertBatch('token_device', [$ins_token_device], false);

		CUser::provisionPushMedia($db_device['userid'], $db_device['uuid']);

		$device = [
			'deviceid' => $db_device['deviceid'],
			'name' => $options['name'],
			'status' => ZBX_DEVICE_STATUS_ACTIVATED,
			'push_token' => $options['push_token'],
			'activated_at' => time(),
			'keys' => self::createDeviceKeys($db_device['deviceid'], $options['mobile_identity_key'],
				$options['mobile_encryption_key']
			)
		];

		$db_devices = [$db_device['deviceid'] => array_intersect_key($db_device, $device)];

		self::updateForce([$device], $db_devices);

		return ['token' => $token];
	}

	private function validateOnboard(array $data, ?array &$db_device): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'enrollment_token' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'mobile_identity_key' =>	self::getJwkValidationRules(),
			'mobile_encryption_key' =>	self::getJwkValidationRules(),
			'push_token' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('device', 'name')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!CApiDpopHelper::checkJwkIntegrity($data['mobile_identity_key'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/mobile_identity_key',
				_('failed to parse key material')
			));
		}

		if (!CApiDpopHelper::checkJwkIntegrity($data['mobile_encryption_key'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/mobile_encryption_key',
				_('failed to parse key material')
			));
		}

		$db_enrollment_token = DBfetch(DBselect(
			'SELECT det.deviceid'.
			' FROM device_enrollment_token det'.
			' WHERE '.dbConditionString('det.token', [CApiTokenHelper::hashToken($data['enrollment_token'])]).
				' AND det.expires_at>'.time().
			' FOR UPDATE'
		));

		if (!$db_enrollment_token) {
			self::exception(ZBX_API_ERROR_NO_ENTITY, _s('Invalid parameter "%1$s": %2$s.', '/enrollment_token',
				_('object does not exist')
			));
		}

		$db_device = DBfetch(DBselect(
			'SELECT d.deviceid,d.userid,d.uuid,d.name,d.status'.
			' FROM device d'.
			' WHERE '.dbConditionId('d.deviceid', [$db_enrollment_token['deviceid']])
		));

		$db_device['keys'] = [];
	}

	private static function getJwkValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
			'crv' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'P-256'],
			'kty' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => 'EC'],
			'kid' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'x' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'y' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'use' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
			'alg' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY]
		]];
	}

	private static function createDeviceKeys(string $deviceid, array $mobile_identity_key,
			array $mobile_encryption_key): array {
		$device_keyid = DB::reserveIds('device_key', 2);

		$current_time = time();

		$device_keys = [
			[
				'device_keyid' => $device_keyid,
				'deviceid' => $deviceid,
				'scope' => MOBILE_KEY_SCOPE_IDENTITY,
				'kid' => $mobile_identity_key['kid'],
				'key_' => json_encode($mobile_identity_key),
				'active' => self::DEVICE_KEY_ACTIVE,
				'created_at' => $current_time
			],
			[
				'device_keyid' => bcadd($device_keyid, 1, 0),
				'deviceid' => $deviceid,
				'scope' => MOBILE_KEY_SCOPE_ENCRYPTION,
				'kid' => $mobile_encryption_key['kid'],
				'key_' => json_encode($mobile_encryption_key),
				'active' => self::DEVICE_KEY_ACTIVE,
				'created_at' => $current_time
			]
		];

		DB::insertBatch('device_key', $device_keys, false);

		foreach ($device_keys as &$device_key) {
			unset($device_key['deviceid']);
		}
		unset($device_key);

		return $device_keys;
	}

	public static function updateForce(array $devices, array $db_devices): void {
		$upd_devices = [];

		foreach ($devices as $device) {
			$upd_device = DB::getUpdatedValues('device', $device, $db_devices[$device['deviceid']]);

			if ($upd_device) {
				$upd_devices[] = [
					'values' => $upd_device,
					'where' => ['deviceid' => $device['deviceid']]
				];
			}
		}

		if ($upd_devices) {
			DB::update('device', $upd_devices);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_DEVICE, $devices, $db_devices);
	}

	/**
	 * @param array  $data
	 *
	 * @return array
	 */
	public function offboard(array $data): array {
		if (self::$userData['username'] === ZBX_GUEST_USER) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$this->validateOffboard($data, $db_device);

		$force_delete = array_key_exists('force', $data) && $data['force'];
		unset($data['force']);

		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::DEVICE_LINK_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$result = $server->offboardDevice($data, self::getAuthIdentifier());

		if ($result === false) {
			$error_code = $server->getErrorCode() == CZabbixServer::ERROR_CODE_DEVICE_NOT_FOUND
				? ZBX_API_ERROR_NO_EXTERNAL_ENTITY
				: ZBX_API_ERROR_INTERNAL;

			if ($error_code == ZBX_API_ERROR_INTERNAL || !$force_delete) {
				self::exception($error_code, $server->getError());
			}
		}

		if ($db_device['userid'] != 0) {
			CUser::deprovisionPushMedia($db_device['userid'], $db_device['uuid']);

			$db_token_devices = DB::select('token_device', [
				'output' => [],
				'filter' => ['deviceid' => $db_device['deviceid']],
				'preservekeys' => true
			]);

			$tokenids = array_keys($db_token_devices);

			DB::delete('token_device', ['tokenid' => $tokenids]);
			DB::delete('token', ['tokenid' => $tokenids]);
		}

		DB::delete('device', ['deviceid' => $db_device['deviceid']]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_DEVICE, [$db_device['deviceid'] => $db_device]);

		return $data;
	}

	private function validateOffboard(array &$data, ?array &$db_device): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'uuid' =>	['type' => API_UUID_V7, 'flags' => API_REQUIRED]
		]];

		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
			$api_input_rules['fields'] += [
				'force' =>	['type' => API_BOOLEAN, 'default' => false]
			];
		}

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_devices = $this->get([
			'output' => ['deviceid', 'userid', 'uuid', 'name'],
			'filter' => ['uuid' => $data['uuid']]
		]);

		if (!$db_devices) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_device = $db_devices[0];

		if (bccomp($db_device['userid'], self::$userData['userid']) == 0) {
			if (!self::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to manage own devices.'));
			}
		}
		elseif (!self::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to manage user devices.'));
		}
	}
}
