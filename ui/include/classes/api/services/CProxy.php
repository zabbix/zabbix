<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with proxies.
 */
class CProxy extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'proxy';
	protected $tableAlias = 'p';
	protected $sortColumns = ['proxyid', 'name', 'mode'];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$output_fields = ['proxyid', 'name', 'mode', 'description', 'lastaccess', 'tls_connect', 'tls_accept',
			'tls_issuer', 'tls_subject', 'allowed_addresses', 'version', 'compatibility', 'address',
			'port'
		];

		/*
		 * For internal calls, it is possible to get the write-only fields if they were specified in output.
		 * Specify write-only fields in output only if they will not appear in debug mode.
		 */
		if (APP::getMode() !== APP::EXEC_MODE_API) {
			$output_fields[] = 'tls_psk_identity';
			$output_fields[] = 'tls_psk';
		}

		$host_fields = ['hostid', 'proxyid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
			'ipmi_password', 'maintenanceid', 'maintenance_status', 'maintenance_type', 'maintenance_from', 'name',
			'flags', 'description', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'inventory_mode',
			'active_available'
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'proxyids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'mode', 'lastaccess', 'version', 'compatibility']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'description']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectHosts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $host_fields), 'default' => null],
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

		$sql_parts = [
			'select'	=> ['proxyid' => 'p.proxy'],
			'from'		=> ['proxy' => 'proxy p'],
			'where'		=> ['mode' => 'p.mode IN ('.PROXY_MODE_ACTIVE.','.PROXY_MODE_PASSIVE.')'],
			'order'		=> []
		];

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			if ($permission == PERM_READ_WRITE) {
				return $options['countOutput'] ? '0' : [];
			}
		}

		$count_output = $options['countOutput'];

		if ($count_output) {
			$options['output'] = ['proxyid'];
			$options['countOutput'] = false;
		}
		elseif ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $output_fields;
		}

		// proxyids
		if ($options['proxyids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('p.proxyid', $options['proxyids']);
		}

		// filter
		if ($options['filter'] === null) {
			$options['filter'] = [];
		}

		$this->dbFilter('proxy p', $options, $sql_parts);

		$rt_filter = [];
		foreach (['lastaccess', 'version', 'compatibility'] as $field) {
			if (array_key_exists($field, $options['filter']) && $options['filter'][$field] !== null) {
				$rt_filter[$field] = $options['filter'][$field];
			}
		}

		if ($rt_filter) {
			$this->dbFilter('proxy_rtdata pr', ['filter' => $rt_filter] + $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('proxy p', $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$resource = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_proxies = [];

		while ($row = DBfetch($resource)) {
			$db_proxies[$row['proxyid']] = $row;
		}

		if ($count_output) {
			return (string) count($db_proxies);
		}

		if ($db_proxies) {
			$db_proxies = $this->addRelatedObjects($options, $db_proxies);
			$db_proxies = $this->unsetExtraFields($db_proxies, ['proxyid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_proxies = array_values($db_proxies);
			}
		}

		return $db_proxies;
	}

	/**
	 * @param array $proxies
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $proxies): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxy', __FUNCTION__)
			);
		}

		self::validateCreate($proxies);

		$proxyids = DB::insert('proxy', $proxies);
		$proxy_rtdata = [];

		foreach ($proxies as $index => &$proxy) {
			$proxy['proxyid'] = $proxyids[$index];
			$proxy_rtdata[] = ['proxyid' => $proxyids[$index]];
		}
		unset($proxy);

		DB::insert('proxy_rtdata', $proxy_rtdata, false);
		self::updateHosts($proxies);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_PROXY, $proxies);

		return ['proxyids' => $proxyids];
	}

	/**
	 * @param array $proxies
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $proxies): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxy', __FUNCTION__)
			);
		}

		$this->validateUpdate($proxies, $db_proxies);

		$upd_proxies = [];

		foreach ($proxies as $proxy) {
			$upd_proxy = DB::getUpdatedValues('proxy', $proxy, $db_proxies[$proxy['proxyid']]);

			if ($upd_proxy) {
				$upd_proxies[] = [
					'values' => $upd_proxy,
					'where' => ['proxyid' => $proxy['proxyid']]
				];
			}
		}

		if ($upd_proxies) {
			DB::update('proxy', $upd_proxies);
		}

		self::updateHosts($proxies, $db_proxies);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_PROXY, $proxies, $db_proxies);

		return ['proxyids' => array_column($proxies, 'proxyid')];
	}

	/**
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 */
	private static function updateHosts(array &$proxies, array $db_proxies = null): void {
		$upd_hosts = [];

		foreach ($proxies as &$proxy) {
			if (!array_key_exists('hosts', $proxy)) {
				continue;
			}

			$db_hosts = $db_proxies !== null ? $db_proxies[$proxy['proxyid']]['hosts'] : [];

			foreach ($proxy['hosts'] as $host) {
				if (!array_key_exists($host['hostid'], $db_hosts)) {
					$upd_hosts[$host['hostid']] = [
						'values' => ['proxyid' => $proxy['proxyid']],
						'where' => ['hostid' => $host['hostid']]
					];
				}
				else {
					unset($db_hosts[$host['hostid']]);
				}
			}

			foreach ($db_hosts as $db_host) {
				if (!array_key_exists($db_host['hostid'], $upd_hosts)) {
					$upd_hosts[$db_host['hostid']] = [
						'values' => ['proxyid' => 0],
						'where' => ['hostid' => $db_host['hostid']]
					];
				}
			}
		}
		unset($proxy);

		if ($upd_hosts) {
			DB::update('hosts', array_values($upd_hosts));
		}
	}

	/**
	 * @param array $proxyids
	 *
	 * @return array
	 */
	public function delete(array $proxyids): array {
		$this->validateDelete($proxyids, $db_proxies);

		DB::delete('proxy_rtdata', ['proxyid' => $proxyids]);
		DB::delete('proxy', ['proxyid' => $proxyids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_PROXY, $db_proxies);

		return ['proxyids' => $proxyids];
	}

	/**
	 * @param array      $proxyids
	 * @param array|null $db_proxies
	 *
	 * @throws APIException
	 */
	private function validateDelete(array &$proxyids, ?array &$db_proxies): void {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxy', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $proxyids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_proxies = $this->get([
			'output' => ['proxyid', 'name'],
			'proxyids' => $proxyids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($proxyids) != count($db_proxies)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkUsedInDiscovery($db_proxies);
		self::checkUsedInHosts($db_proxies);
		self::checkUsedInActions($db_proxies);
	}

	/**
	 * Check if proxy is used in network discovery rule.
	 *
	 * @param array  $proxies
	 * @param string $proxies[<proxyid>]['host']
	 *
	 * @throws APIException
	 */
	private static function checkUsedInDiscovery(array $proxies): void {
		$db_drules = DB::select('drules', [
			'output' => ['proxyid', 'name'],
			'filter' => ['proxyid' => array_keys($proxies)],
			'limit' => 1
		]);

		if ($db_drules) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%1$s" is used by discovery rule "%2$s".',
				$proxies[$db_drules[0]['proxyid']]['name'], $db_drules[0]['name']
			));
		}
	}

	/**
	 * Check if proxy is used to monitor hosts.
	 *
	 * @param array  $proxies
	 * @param string $proxies[<proxyid>]['host']
	 *
	 * @throws APIException
	 */
	private static function checkUsedInHosts(array $proxies): void {
		$db_hosts = DB::select('hosts', [
			'output' => ['proxyid', 'name'],
			'filter' => ['proxyid' => array_keys($proxies)],
			'limit' => 1
		]);

		if ($db_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" is monitored by proxy "%2$s".',
				$db_hosts[0]['name'], $proxies[$db_hosts[0]['proxyid']]['name']
			));
		}
	}

	/**
	 * Check if proxy is used in actions.
	 *
	 * @param array  $proxies
	 * @param string $proxies[<proxyid>]['host']
	 *
	 * @throws APIException
	 */
	private static function checkUsedInActions(array $proxies): void {
		$db_actions = DBfetchArray(DBselect(
			'SELECT a.name,c.value AS proxyid'.
			' FROM actions a,conditions c'.
			' WHERE a.actionid=c.actionid'.
				' AND c.conditiontype='.CONDITION_TYPE_PROXY.
				' AND '.dbConditionString('c.value', array_keys($proxies)),
			1
		));

		if ($db_actions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%1$s" is used by action "%2$s".',
				$proxies[$db_actions[0]['proxyid']]['name'], $db_actions[0]['name']
			));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		$upcased_index = array_search($tableAlias.'.name_upper', $sqlParts['select']);

		if ($upcased_index !== false) {
			unset($sqlParts['select'][$upcased_index]);
		}

		if (!$options['countOutput']) {

			$host_rtdata = false;
			foreach (['lastaccess', 'version', 'compatibility'] as $field) {
				if ($this->outputIsRequested($field, $options['output'])) {
					$sqlParts = $this->addQuerySelect('pr.'.$field, $sqlParts);
					$host_rtdata = true;
				}

				if (is_array($options['filter']) && array_key_exists($field, $options['filter'])) {
					$host_rtdata = true;
				}
			}

			if ($host_rtdata) {
				$sqlParts['left_join'][] = ['alias' => 'pr', 'table' => 'proxy_rtdata', 'using' => 'proxyid'];
				$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$proxyIds = array_keys($result);

		// selectHosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$hosts = API::Host()->get([
				'output' => $this->outputExtend($options['selectHosts'], ['hostid', 'proxyid']),
				'proxyids' => $proxyIds,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($hosts, 'proxyid', 'hostid');
			$hosts = $this->unsetExtraFields($hosts, ['proxyid', 'hostid'], $options['selectHosts']);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		return $result;
	}

	/**
	 * @param array $proxies
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$proxies): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('proxy', 'name')],
			'mode' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PROXY_MODE_ACTIVE, PROXY_MODE_PASSIVE])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'description')],
			'allowed_addresses' =>		['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS, 'length' => DB::getFieldLength('proxy', 'allowed_addresses')],
			'hosts' =>				['type' => API_OBJECTS, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'address' => 			['type' => API_ADDRESS, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('proxy', 'address')],
			'port' =>				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('proxy', 'port')],
			'tls_connect' =>		['type' => API_MULTIPLE, 'default' => HOST_ENCRYPTION_NONE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE])]
			]],
			'tls_accept' =>			['type' => API_MULTIPLE, 'default' => HOST_ENCRYPTION_NONE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE)],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE]
			]],
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($proxies);
		self::checkHosts($proxies);
		self::checkProxyAddress($proxies, 'create');
	}

	/**
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 *
	 * @throws APIException
	 */
	private static function checkDuplicates(array $proxies, array $db_proxies = null): void {
		$names = [];

		foreach ($proxies as $proxy) {
			if (!array_key_exists('name', $proxy)) {
				continue;
			}

			if ($db_proxies === null || $proxy['name'] !== $db_proxies[$proxy['proxyid']]['name']) {
				$names[] = $proxy['name'];
			}
		}

		if (!$names) {
			return;
		}

		$options = [
			'output' => ['name'],
			'filter' => [
				'name' => $names,
				'mode' => [PROXY_MODE_ACTIVE, PROXY_MODE_PASSIVE]
			]
		];
		$duplicate = DBfetch(DBselect(DB::makeSql('proxy', $options), 1));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%1$s" already exists.', $duplicate['name']));
		}
	}

	/**
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 *
	 * @throws APIException
	 */
	private static function checkHosts(array $proxies, array $db_proxies = null): void {
		$hostids = [];

		foreach ($proxies as $proxy) {
			if (!array_key_exists('hosts', $proxy)) {
				continue;
			}

			$proxy_hostids = array_column($proxy['hosts'], null, 'hostid');
			$db_proxy_hostids = $db_proxies !== null
				? array_column($db_proxies[$proxy['proxyid']]['hosts'], null, 'hostid')
				: [];

			$hostids += array_diff_key($proxy_hostids, $db_proxy_hostids);
		}

		if (!$hostids) {
			return;
		}

		$db_hosts = API::Host()->get([
			'output' => ['hostid', 'host', 'flags'],
			'hostids' => array_keys($hostids),
			'editable' => true
		]);

		if (count($db_hosts) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($db_hosts as $db_host) {
			if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot update proxy for discovered host "%1$s".', $db_host['host'])
				);
			}
		}
	}

	/**
	 * @param array $proxies
	 *
	 * @throws APIException
	 */
	private static function checkProxyAddress(array &$proxies, string $method): void {
		foreach ($proxies as $i => &$proxy) {
			if ($proxy['mode'] == PROXY_MODE_PASSIVE) {
				$proxy += ['allowed_addresses' => ''];

				if ($proxy['allowed_addresses'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/allowed_addresses', _('should be empty'))
					);
				}

				if ($method === 'create' && !array_key_exists('address', $proxy)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('the parameter "%1$s" is missing', 'address')
					));
				}

				if ($method === 'create' && !array_key_exists('port', $proxy)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('the parameter "%1$s" is missing', 'port')
					));
				}

				$field_names = ['address', 'port'];

				foreach ($field_names as $field_name) {
					if (!array_key_exists($field_name, $proxy)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							'/'.($i + 1), _s('the parameter "%1$s" is missing', $field_name)
						));
					}

					if ($proxy[$field_name] === '') {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							'/'.($i + 1).'/'.$field_name, _('cannot be empty')
						));
					}
				}
			}
			else {
				$proxy += ['address' => '', 'port' => ''];

				if ($proxy['address'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/address', _('should be empty'))
					);
				}
				if ($proxy['port'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/port', _('should be empty'))
					);
				}
			}
		}
		unset($proxy);
	}


	/**
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$proxies, ?array &$db_proxies): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['name']], 'fields' => [
			'proxyid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'mode' =>				['type' => API_INT32, 'in' => implode(',', [PROXY_MODE_ACTIVE, PROXY_MODE_PASSIVE])],
			'name' =>				['type' => API_H_NAME, 'length' => DB::getFieldLength('proxy', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'description')],
			'allowed_addresses' =>		['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS, 'length' => DB::getFieldLength('proxy', 'allowed_addresses')],
			'hosts' =>				['type' => API_OBJECTS, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'address' => 			['type' => API_ADDRESS, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('proxy', 'address')],
			'port' =>				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('proxy', 'port')]
			]
		];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_proxies = $this->get([
			'output' => ['proxyid', 'name', 'mode', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject',
				'description', 'allowed_addresses','address','port'
			],
			'proxyids' => array_column($proxies, 'proxyid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($proxies) != count($db_proxies)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$proxies = $this->extendObjectsByKey($proxies, $db_proxies, 'proxyid', ['mode']);

		foreach ($proxies as &$proxy) {
			if ($proxy['mode'] == PROXY_MODE_PASSIVE) {
				$proxy += [
					'tls_connect' => $db_proxies[$proxy['proxyid']]['tls_connect'],
					'tls_accept' => HOST_ENCRYPTION_NONE
				];
			}
			else {
				$proxy += [
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_accept' => $db_proxies[$proxy['proxyid']]['tls_accept']
				];
			}
		}
		unset($proxy);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'tls_connect' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE],
									['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE])]
			]],
			'tls_accept' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE)],
									['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Load PSK data directly from the DB, since the API won't return secret data.
		$proxies_psk_fields = DB::select($this->tableName(), [
			'output' => ['tls_psk_identity', 'tls_psk'],
			'proxyids' => array_keys($db_proxies),
			'preservekeys' => true
		]);

		foreach ($proxies_psk_fields as $proxyid => $psk_fields) {
			$db_proxies[$proxyid] += $psk_fields;
		}

		foreach ($proxies as &$proxy) {
			if (($proxy['mode'] == PROXY_MODE_PASSIVE && $proxy['tls_connect'] != HOST_ENCRYPTION_PSK)
					|| ($proxy['mode'] == PROXY_MODE_ACTIVE
						&& ($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) == 0)) {
				if ($db_proxies[$proxy['proxyid']]['tls_psk_identity'] !== '') {
					$proxy += ['tls_psk_identity' => ''];
				}

				if ($db_proxies[$proxy['proxyid']]['tls_psk'] !== '') {
					$proxy += ['tls_psk' => ''];
				}
			}
			if (($proxy['mode'] == PROXY_MODE_PASSIVE && $proxy['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE)
					|| ($proxy['mode'] == PROXY_MODE_ACTIVE
						&& ($proxy['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == 0)) {
				$proxy += ['tls_issuer' => '', 'tls_subject' => ''];
			}
		}

		unset($proxy);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['else' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('proxy', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'mode', 'in' => PROXY_MODE_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('proxy', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::addAffectedObjects($proxies, $db_proxies);
		self::checkDuplicates($proxies, $db_proxies);
		self::checkHosts($proxies, $db_proxies);
		self::checkProxyAddress($proxies, 'update');
	}

	/**
	 * @param array $proxies
	 * @param array $db_proxies
	 */
	private static function addAffectedObjects(array $proxies, array &$db_proxies): void {
		$proxyids = ['hosts' => []];

		foreach ($proxies as $proxy) {
			if (array_key_exists('hosts', $proxy)) {
				$proxyids['hosts'][] = $proxy['proxyid'];
				$db_proxies[$proxy['proxyid']]['hosts'] = [];
			}
		}

		if ($proxyids['hosts']) {
			$options = [
				'output' => ['hostid', 'proxyid'],
				'filter' => ['proxyid' => $proxyids['hosts']]
			];
			$db_hosts = DBselect(DB::makeSql('hosts', $options));

			while ($db_host = DBfetch($db_hosts)) {
				$db_proxies[$db_host['proxyid']]['hosts'][$db_host['hostid']] = [
					'hostid' => $db_host['hostid']
				];
			}
		}
	}
}
