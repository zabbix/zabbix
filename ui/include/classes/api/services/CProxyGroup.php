<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * Class containing methods for operations with proxy groups.
 */
class CProxyGroup extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'proxy_group';
	protected $tableAlias = 'pg';
	protected $sortColumns = ['proxy_groupid', 'name'];

	public const OUTPUT_FIELDS = ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'description', 'state'];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'proxy_groupids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'proxyids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'state']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'description', 'failover_delay', 'min_online']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			'selectProxies' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', array_diff(CProxy::OUTPUT_FIELDS, ['proxy_groupid'])), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false],
			'nopermissions' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			if ($permission == PERM_READ_WRITE) {
				return $options['countOutput'] ? '0' : [];
			}
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = self::OUTPUT_FIELDS;
		}

		$resource = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		$db_proxy_groups = [];

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_proxy_groups[$row['proxy_groupid']] = $row;
		}

		if ($db_proxy_groups) {
			$db_proxy_groups = $this->addRelatedObjects($options, $db_proxy_groups);
			$db_proxy_groups = $this->unsetExtraFields($db_proxy_groups, ['proxy_groupid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_proxy_groups = array_values($db_proxy_groups);
			}
		}

		return $db_proxy_groups;
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		// proxyids
		if ($options['proxyids'] !== null) {
			$sql_parts['from']['proxy'] = 'proxy p';
			$sql_parts['where'][] = dbConditionId('p.proxyid', $options['proxyids']);
			$sql_parts['where']['pgp'] = 'pg.proxy_groupid=p.proxy_groupid';
		}

		if ($options['filter'] !== null && array_key_exists('state', $options['filter'])
				&& $options['filter']['state'] !== null) {
			$this->dbFilter('proxy_group_rtdata pgr', ['filter' => ['state' => $options['filter']['state']]] + $options,
				$sql_parts
			);

			$sql_parts['left_join']['proxy_group_rtdata'] = [
				'alias' => 'pgr',
				'table' => 'proxy_group_rtdata',
				'using' => 'proxy_groupid'
			];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		return $sql_parts;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput'] && $this->outputIsRequested('state', $options['output'])) {
			$sql_parts = $this->addQuerySelect('pgr.state', $sql_parts);
			$sql_parts['left_join']['proxy_group_rtdata'] = [
				'alias' => 'pgr',
				'table' => 'proxy_group_rtdata',
				'using' => 'proxy_groupid'
			];
			$sql_parts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		return $sql_parts;
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedProxies($options, $result);

		return $result;
	}

	private function addRelatedProxies(array $options, array &$result): void {
		if ($options['selectProxies'] === null) {
			return;
		}

		if ($options['selectProxies'] !== API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['proxies'] = [];
			}
			unset($row);

			$db_proxies = API::Proxy()->get([
				'output' => $this->outputExtend($options['selectProxies'], ['proxy_groupid']),
				'proxy_groupids' => array_keys($result)
			]);

			foreach ($db_proxies as $db_proxy) {
				$result[$db_proxy['proxy_groupid']]['proxies'][] =
					array_diff_key($db_proxy, array_flip(['proxy_groupid']));
			}
		}
		else {
			$db_proxies = DBFetchArrayAssoc(DBselect(
				'SELECT p.proxy_groupid,COUNT(p.proxyid) AS rowscount'.
				' FROM proxy p'.
				' WHERE '.dbConditionId('p.proxy_groupid', array_keys($result)).
				' GROUP BY p.proxy_groupid'
			), 'proxy_groupid');

			foreach ($result as $proxy_groupid => $proxy_group) {
				$result[$proxy_groupid]['proxies'] = array_key_exists($proxy_groupid, $db_proxies)
					? $db_proxies[$proxy_groupid]['rowscount']
					: '0';
			}
		}
	}

	/**
	 * @param array $proxy_groups
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $proxy_groups): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxygroup', __FUNCTION__)
			);
		}

		self::validateCreate($proxy_groups);

		$proxy_groupids = DB::insert('proxy_group', $proxy_groups);
		$proxy_group_rtdata = [];

		foreach ($proxy_groups as $index => &$proxy_group) {
			$proxy_group['proxy_groupid'] = $proxy_groupids[$index];
			$proxy_group_rtdata[] = ['proxy_groupid' => $proxy_groupids[$index]];
		}
		unset($proxy_group);

		DB::insert('proxy_group_rtdata', $proxy_group_rtdata, false);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_PROXY_GROUP, $proxy_groups);

		return ['proxy_groupids' => $proxy_groupids];
	}

	/**
	 * @param array $proxy_groups
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$proxy_groups): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy_group', 'name')],
			'failover_delay' =>	['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '10:'.(15 * SEC_PER_MIN), 'length' => DB::getFieldLength('proxy_group', 'failover_delay')],
			'min_online' =>		['type' => API_NUMBER, 'in' => '1:1000', 'length' => DB::getFieldLength('proxy_group', 'min_online')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy_group', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxy_groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($proxy_groups);
	}

	/**
	 * @param array $proxy_groups
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $proxy_groups): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxygroup', __FUNCTION__)
			);
		}

		$this->validateUpdate($proxy_groups, $db_proxy_groups);

		$upd_proxy_groups = [];

		foreach ($proxy_groups as $proxy_group) {
			$upd_proxy_group = DB::getUpdatedValues('proxy_group', $proxy_group,
				$db_proxy_groups[$proxy_group['proxy_groupid']]
			);

			if ($upd_proxy_group) {
				$upd_proxy_groups[] = [
					'values' => $upd_proxy_group,
					'where' => ['proxy_groupid' => $proxy_group['proxy_groupid']]
				];
			}
		}

		if ($upd_proxy_groups) {
			DB::update('proxy_group', $upd_proxy_groups);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_PROXY_GROUP, $proxy_groups, $db_proxy_groups);

		return ['proxy_groupids' => array_column($proxy_groups, 'proxy_groupid')];
	}

	/**
	 * @param array      $proxy_groups
	 * @param array|null $db_proxy_groups
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$proxy_groups, ?array &$db_proxy_groups): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['proxy_groupid']], 'fields' => [
			'proxy_groupid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxy_groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_proxy_groups = DB::select('proxy_group', [
			'output' => ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'description'],
			'proxy_groupids' => array_column($proxy_groups, 'proxy_groupid'),
			'preservekeys' => true
		]);

		if (count($db_proxy_groups) != count($proxy_groups)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
			'proxy_groupid' =>	['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy_group', 'name')],
			'failover_delay' =>	['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '10:'.(15 * SEC_PER_MIN), 'length' => DB::getFieldLength('proxy_group', 'failover_delay')],
			'min_online' =>		['type' => API_NUMBER, 'in' => '1:1000', 'length' => DB::getFieldLength('proxy_group', 'min_online')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy_group', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxy_groups, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($proxy_groups, $db_proxy_groups);
	}

	/**
	 * @param array      $proxy_groups
	 * @param array|null $db_proxy_groups
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $proxy_groups, ?array $db_proxy_groups = null): void {
		$names = [];

		foreach ($proxy_groups as $proxy_group) {
			if (!array_key_exists('name', $proxy_group)) {
				continue;
			}

			if ($db_proxy_groups === null
					|| $proxy_group['name'] !== $db_proxy_groups[$proxy_group['proxy_groupid']]['name']) {
				$names[] = $proxy_group['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('proxy_group', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy group "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	/**
	 * @param array $proxy_groupids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $proxy_groupids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxygroup', __FUNCTION__)
			);
		}

		self::validateDelete($proxy_groupids, $db_proxy_groups);

		DB::delete('proxy_group_rtdata', ['proxy_groupid' => $proxy_groupids]);
		DB::delete('proxy_group', ['proxy_groupid' => $proxy_groupids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_PROXY_GROUP, $db_proxy_groups);

		return ['proxy_groupids' => $proxy_groupids];
	}

	/**
	 * @param array      $proxy_groupids
	 * @param array|null $db_proxy_groups
	 *
	 * @throws APIException
	 */
	private static function validateDelete(array $proxy_groupids, ?array &$db_proxy_groups): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $proxy_groupids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_proxy_groups = DB::select('proxy_group', [
			'output' => ['proxy_groupid', 'name'],
			'proxy_groupids' => $proxy_groupids,
			'preservekeys' => true
		]);

		if (count($db_proxy_groups) != count($proxy_groupids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkUsedInProxies($db_proxy_groups);
		self::checkUsedInHosts($db_proxy_groups);
	}

	/**
	 * @param array $proxy_groups
	 *
	 * @throws APIException
	 */
	private static function checkUsedInProxies(array $proxy_groups): void {
		$db_proxies = DB::select('proxy', [
			'output' => ['proxy_groupid', 'name'],
			'filter' => ['proxy_groupid' => array_keys($proxy_groups)],
			'limit' => 1
		]);

		if ($db_proxies) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy group "%1$s" is used by proxy "%2$s".',
				$proxy_groups[$db_proxies[0]['proxy_groupid']]['name'], $db_proxies[0]['name']
			));
		}
	}

	/**
	 * @param array $proxy_groups
	 *
	 * @throws APIException
	 */
	private static function checkUsedInHosts(array $proxy_groups): void {
		$db_hosts = DB::select('hosts', [
			'output' => ['proxy_groupid', 'name'],
			'filter' => ['proxy_groupid' => array_keys($proxy_groups)],
			'limit' => 1
		]);

		if ($db_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" is monitored by proxy group "%2$s".',
				$db_hosts[0]['name'], $proxy_groups[$db_hosts[0]['proxy_groupid']]['name']
			));
		}
	}
}
