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

	public const OUTPUT_FIELDS = ['deviceid', 'userid', 'uuid', 'name', 'lastaccess'];

	private const STATUS_NEW = 0;
	private const STATUS_ENABLED = 1;
	private const STATUS_DISABLED = 2;

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
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_merge(DB::getFilterFields('device', self::OUTPUT_FIELDS), ['lastaccess'])],
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

		if ($options['filter'] !== null && array_key_exists('lastaccess', $options['filter'])
				&& $options['filter']['lastaccess'] !== null) {
			$sql_parts['join']['t'] = ['type' => 'left', 'table' => 'token', 'using' => 'tokenid'];
			$this->dbFilter('token t', ['filter' => ['lastaccess' => $options['filter']['lastaccess']]] + $options,
				$sql_parts
			);
		}

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput'] && in_array('lastaccess', $options['output'])) {
			$sql_parts = $this->addQuerySelect('t.lastaccess', $sql_parts);
			$sql_parts['join']['t'] = ['type' => 'left', 'table' => 'token', 'using' => 'tokenid'];
		}

		return $sql_parts;
	}

	public function init(array $user = []): array {
//		$device = ['deviceid' => '1', 'uuid' => '123', 'name' => '', 'userid' => '1'];

//		self::addAuditLog(CAudit::ACTION_INIT, CAudit::RESOURCE_DEVICE, [$device]);

		return [
			'deviceid',
			'uuid',
			'taskid',
			'enrollment_token'
		];
	}

	public function onboard(array $options): array {
//		$devices = [['deviceid' => '1', 'name' => 'Device 1']];
//		$db_devices = ['1' => ['deviceid' => '1', 'name' => '']];

//		self::addAuditLog(CAudit::ACTION_ONBOARD, CAudit::RESOURCE_DEVICE, $devices, $db_devices);

		return ['token'];
	}

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
			'output' => ['deviceid', 'name'],
			'deviceids' => $deviceids,
			'preservekeys' => true
		]);

		if (count($db_devices) != count($deviceids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	public static function deleteForce(array $db_devices): void {
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

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_DEVICE, $db_devices);
	}
}
