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

	public const OUTPUT_FIELDS = ['deviceid', 'userid', 'uuid', 'name', 'activated_at', 'lastaccess'];

	private const ENROLLMENT_TOKEN_EXPIRATION_TTL = 600;

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

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput'] && in_array('lastaccess', $options['output'])) {
			$sql_parts = $this->addQuerySelect('t.lastaccess', $sql_parts);
			$sql_parts['join']['td'] = ['table' => 'token_device', 'using' => 'deviceid'];
			$sql_parts['join']['t'] = ['left_table' => 'td', 'table' => 'token', 'using' => 'tokenid'];
		}

		return $sql_parts;
	}

	/**
	 * @param array  $data
	 *
	 * @return array
	 */
	public function init(array $data): array {
		$this->validateInit($data);

		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::DEVICE_LINK_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$uuid = generateUuidV7();
		$server_id = 'server_id'; // todo - replace this mock for Server ID by real method

		$init_device_data = ['serverid' => $server_id, 'uuid' => $uuid];

		$result = $server->initDevice($init_device_data, self::getAuthIdentifier());

		if ($result === false) {
			self::exception(ZBX_API_ERROR_INTERNAL, $server->getError());
		}

		$enrollment_token = CApiTokenHelper::generateToken();

		$deviceid = self::createDevice($data['userid'], $enrollment_token, $uuid);

		$device = [
			'deviceid' => $deviceid,
			'uuid' => $uuid,
			'name' => '',
			'userid' => $data['userid']
		];

		self::addAuditLog(CAudit::ACTION_INIT, CAudit::RESOURCE_DEVICE, [$device]);

		return [
			'uuid' => $uuid,
			'server_id' => $server_id,
			'enrollment_token' => $enrollment_token,
			'mobile_enrollment_token' => $result['mobile_enrollment_token'],
			'bridge_enrollment_key' => $result['bridge_enrollment_key'],
			'enrollment_url' => $result['enrollment_url']
		];
	}

	private function validateInit(array &$data): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'userid' =>	['type' => API_ID, 'default' => self::$userData['userid']]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (bccomp($data['userid'], self::$userData['userid']) != 0) {
			$db_users = API::User()->get([
				'output' => ['userid'],
				'userids' => $data['userid'],
				'editable' => true
			]);

			if (!$db_users) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	private static function createDevice(string $userid, string $enrollment_token, string $uuid): string {
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
			'expires_at' => time() + self::ENROLLMENT_TOKEN_EXPIRATION_TTL
		];

		DB::insertBatch('device_enrollment_token', [$ins_device_enrollment_token], false);

		return $deviceid;
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
			'userip' => CWebUser::getIp(),
			'username' => $db_device['username']
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

		$tokens_data = CToken::generateForce($db_tokens, false);

		$token_data = reset($tokens_data);

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
			'tokenid' => $token_data['tokenid'],
			'deviceid' => $db_device['deviceid']
		];

		DB::insertBatch('token_device', [$ins_device_token]);

		$device = [
			'deviceid' => $db_device['deviceid'],
			'name' => $options['name']
		];

		$db_devices = [
			$db_device['deviceid'] => array_intersect_key($db_device, array_flip(['deviceid', 'name']))
		];

		self::addAuditLog(CAudit::ACTION_ONBOARD, CAudit::RESOURCE_DEVICE, [$device], $db_devices);

		return ['token' => $token_data['token']];
	}

	private function validateOnboard(array $data, ?array &$db_device): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'enrollment_token' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'mobile_identity_key' => self::getJwkValidationRules(),
			'mobile_encryption_key' => self::getJwkValidationRules(),
			'push_token' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!CApiDpopHelper::checkJwkIntegrity($data['mobile_identity_key']) ||
				!CApiDpopHelper::checkJwkIntegrity($data['mobile_encryption_key'])) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		$db_device = DBfetch(DBselect(
			'SELECT d.deviceid,d.uuid,d.userid,d.name,u.name AS username'.
			' FROM device_enrollment_token det,device d,users u'.
			' WHERE det.deviceid=d.deviceid'.
				' AND d.userid=u.userid'.
				' AND '.dbConditionString('det.token', [hash('sha512', $data['enrollment_token'])]).
				' AND det.expires_at>'.time()
		));

		if (!$db_device) {
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}
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
	public function offboard(array $deviceids): array {
		$this->validateOffboard($deviceids, $db_devices);

		self::offboardForce($db_devices);

		return ['deviceids' => $deviceids];
	}

	private function validateOffboard(array $deviceids, ?array &$db_devices): void {
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

	public static function offboardForce(array $db_devices): void {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::DEVICE_LINK_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$sid = self::getAuthIdentifier();

		foreach ($db_devices as $db_device) {
			$server->offboardDevice(['uuid' => $db_device['uuid']], $sid);
		}

		$deviceids = array_keys($db_devices);

		$db_device_tokens = DB::select('token_device', [
			'output' => ['tokenid'],
			'filter' => ['deviceid' => $deviceids],
			'preservekeys' => true
		]);

		$tokenids = array_keys($db_device_tokens);

		DB::delete('token_device', ['tokenid' => $tokenids]);
		DB::delete('token', ['tokenid' => $tokenids]);
		DB::delete('device', ['deviceid' => $deviceids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_DEVICE, $db_devices);
	}
}
