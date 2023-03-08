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

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';
	protected $sortColumns = ['hostid', 'host', 'status'];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = []) {
		$output_fields = ['proxyid', 'host', 'status', 'description', 'lastaccess', 'tls_connect', 'tls_accept',
			'tls_issuer', 'tls_subject', 'proxy_address', 'auto_compress', 'version', 'compatibility'
		];

		/*
		 * For internal calls, it is possible to get the write-only fields if they were specified in output.
		 * Specify write-only fields in output only if they will not appear in debug mode.
		 */
		if (APP::getMode() !== APP::EXEC_MODE_API) {
			$output_fields[] = 'tls_psk_identity';
			$output_fields[] = 'tls_psk';
		}

		$host_fields = ['hostid', 'proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
			'ipmi_password', 'maintenanceid', 'maintenance_status', 'maintenance_type', 'maintenance_from', 'name',
			'flags', 'description', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'inventory_mode',
			'active_available'
		];
		$interface_fields = ['interfaceid', 'hostid', 'main', 'type', 'useip', 'ip', 'dns', 'port', 'available',
			'error', 'errors_from', 'disable_until'
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'proxyids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['host', 'status', 'lastaccess', 'version', 'compatibility']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['host', 'description']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectHosts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $host_fields), 'default' => null],
			'selectInterface' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $interface_fields), 'default' => null],
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
			'select'	=> ['hostid' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['status' => 'h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'],
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
			$sql_parts['where'][] = dbConditionInt('h.hostid', $options['proxyids']);
		}

		// filter
		if ($options['filter'] === null) {
			$options['filter'] = [];
		}

		$this->dbFilter('hosts h', $options, $sql_parts);

		$rt_filter = [];
		foreach (['lastaccess', 'version', 'compatibility'] as $field) {
			if (array_key_exists($field, $options['filter']) && $options['filter'][$field] !== null) {
				$rt_filter[$field] = $options['filter'][$field];
			}
		}

		if ($rt_filter) {
			$this->dbFilter('host_rtdata hr', ['filter' => $rt_filter] + $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('hosts h', $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$resource = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_proxies = [];

		while ($row = DBfetch($resource)) {
			$row['proxyid'] = $row['hostid'];
			unset($row['hostid']);

			$db_proxies[$row['proxyid']] = $row;
		}

		if ($count_output) {
			return (string) count($db_proxies);
		}

		if ($db_proxies) {
			$db_proxies = $this->addRelatedObjects($options, $db_proxies);
			$db_proxies = $this->unsetExtraFields($db_proxies, ['proxyid', 'name_upper'], $options['output']);

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

		$proxyids = DB::insert('hosts', $proxies);
		$host_rtdata = [];

		foreach ($proxies as $index => &$proxy) {
			$proxy['proxyid'] = $proxyids[$index];
			$host_rtdata[] = ['hostid' => $proxyids[$index]];
		}
		unset($proxy);

		DB::insert('host_rtdata', $host_rtdata, false);
		self::updateInterfaces($proxies);
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
			$upd_proxy = DB::getUpdatedValues('hosts', $proxy, $db_proxies[$proxy['proxyid']]);

			if ($upd_proxy) {
				$upd_proxies[] = [
					'values' => $upd_proxy,
					'where' => ['hostid' => $proxy['proxyid']]
				];
			}
		}

		if ($upd_proxies) {
			DB::update('hosts', $upd_proxies);
		}

		self::updateInterfaces($proxies, $db_proxies);
		self::updateHosts($proxies, $db_proxies);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_PROXY, $proxies, $db_proxies);

		return ['proxyids' => array_column($proxies, 'proxyid')];
	}

	/**
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 */
	private static function updateInterfaces(array &$proxies, array $db_proxies = null): void {
		$ins_interfaces = [];
		$upd_interfaces = [];
		$del_interfaceids = [];

		foreach ($proxies as &$proxy) {
			if (!array_key_exists('interface', $proxy)) {
				continue;
			}

			$db_interface = $db_proxies !== null ? $db_proxies[$proxy['proxyid']]['interface'] : [];

			if ($proxy['interface']) {
				if ($db_interface) {
					$upd_interface = DB::getUpdatedValues('interface', $proxy['interface'], $db_interface);
					$proxy['interface']['interfaceid'] = $db_interface['interfaceid'];

					if ($upd_interface) {
						$upd_interfaces[] = [
							'values' => $upd_interface,
							'where' => ['interfaceid' => $db_interface['interfaceid']]
						];
					}
				}
				else {
					$ins_interfaces[] = $proxy['interface'] + ['hostid' => $proxy['proxyid']];
				}
			}
			elseif ($db_interface) {
				$del_interfaceids[] = $db_interface['interfaceid'];
			}
		}
		unset($proxy);

		if ($ins_interfaces) {
			$interfaceids = DB::insert('interface', $ins_interfaces);
		}

		if ($upd_interfaces) {
			DB::update('interface', $upd_interfaces);
		}

		if ($del_interfaceids) {
			DB::delete('interface', ['interfaceid' => $del_interfaceids]);
		}

		foreach ($proxies as &$proxy) {
			if (!array_key_exists('interface', $proxy)) {
				continue;
			}

			if ($proxy['status'] != HOST_STATUS_PROXY_ACTIVE && !array_key_exists('interfaceid', $proxy['interface'])) {
				$proxy['interface']['interfaceid'] = array_shift($interfaceids);
			}
		}
		unset($proxy);
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
						'values' => ['proxy_hostid' => $proxy['proxyid']],
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
						'values' => ['proxy_hostid' => 0],
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

		DB::delete('host_rtdata', ['hostid' => $proxyids]);
		DB::delete('interface', ['hostid' => $proxyids]);
		DB::delete('hosts', ['hostid' => $proxyids]);

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
			'output' => ['proxyid', 'host'],
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
			'output' => ['proxy_hostid', 'name'],
			'filter' => ['proxy_hostid' => array_keys($proxies)],
			'limit' => 1
		]);

		if ($db_drules) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%1$s" is used by discovery rule "%2$s".',
				$proxies[$db_drules[0]['proxy_hostid']]['host'], $db_drules[0]['name']
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
			'output' => ['proxy_hostid', 'name'],
			'filter' => ['proxy_hostid' => array_keys($proxies)],
			'limit' => 1
		]);

		if ($db_hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" is monitored by proxy "%2$s".',
				$db_hosts[0]['name'], $proxies[$db_hosts[0]['proxy_hostid']]['host']
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
			'SELECT a.name,c.value AS proxy_hostid'.
			' FROM actions a,conditions c'.
			' WHERE a.actionid=c.actionid'.
				' AND c.conditiontype='.CONDITION_TYPE_PROXY.
				' AND '.dbConditionString('c.value', array_keys($proxies)),
			1
		));

		if ($db_actions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%1$s" is used by action "%2$s".',
				$proxies[$db_actions[0]['proxy_hostid']]['host'], $db_actions[0]['name']
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
			if ($options['selectInterface'] !== null) {
				$sqlParts = $this->addQuerySelect('h.hostid', $sqlParts);
			}

			$host_rtdata = false;
			foreach (['lastaccess', 'version', 'compatibility'] as $field) {
				if ($this->outputIsRequested($field, $options['output'])) {
					$sqlParts = $this->addQuerySelect('hr.'.$field, $sqlParts);
					$host_rtdata = true;
				}

				if (is_array($options['filter']) && array_key_exists($field, $options['filter'])) {
					$host_rtdata = true;
				}
			}

			if ($host_rtdata) {
				$sqlParts['left_join'][] = ['alias' => 'hr', 'table' => 'host_rtdata', 'using' => 'hostid'];
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
				'output' => $this->outputExtend($options['selectHosts'], ['hostid', 'proxy_hostid']),
				'proxyids' => $proxyIds,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($hosts, 'proxy_hostid', 'hostid');
			$hosts = $this->unsetExtraFields($hosts, ['proxy_hostid', 'hostid'], $options['selectHosts']);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding host interface
		if ($options['selectInterface'] !== null && $options['selectInterface'] != API_OUTPUT_COUNT) {
			$interfaces = API::HostInterface()->get([
				'output' => $this->outputExtend($options['selectInterface'], ['interfaceid', 'hostid']),
				'hostids' => $proxyIds,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');
			$interfaces = $this->unsetExtraFields($interfaces, ['hostid', 'interfaceid'], $options['selectInterface']);
			$result = $relationMap->mapOne($result, $interfaces, 'interface');

			foreach ($result as $key => $proxy) {
				if (!empty($proxy['interface'])) {
					$result[$key]['interface'] = $proxy['interface'];
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $proxies
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$proxies): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['host']], 'fields' => [
			'host' =>				['type' => API_H_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hosts', 'host')],
			'status' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE])],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'description')],
			'proxy_address' =>		['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS, 'length' => DB::getFieldLength('hosts', 'proxy_address')],
			'hosts' =>				['type' => API_OBJECTS, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'interface' =>			['type' => API_OBJECT, 'fields' => [
				'useip' => 				['type' => API_INT32, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
				'ip' => 				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
				'dns' =>				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
				'port' =>				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'port')]
			]],
			'tls_connect' =>		['type' => API_MULTIPLE, 'default' => HOST_ENCRYPTION_NONE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE])]
			]],
			'tls_accept' =>			['type' => API_MULTIPLE, 'default' => HOST_ENCRYPTION_NONE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE)],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE]
			]],
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
									]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($proxies);
		self::checkHosts($proxies);
		self::checkProxyAddress($proxies);
		self::checkInterface($proxies, 'create');
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
			if (!array_key_exists('host', $proxy)) {
				continue;
			}

			if ($db_proxies === null || $proxy['host'] !== $db_proxies[$proxy['proxyid']]['host']) {
				$names[] = $proxy['host'];
			}
		}

		if (!$names) {
			return;
		}

		$options = [
			'output' => ['host'],
			'filter' => [
				'host' => $names,
				'status' => [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]
			]
		];
		$duplicate = DBfetch(DBselect(DB::makeSql('hosts', $options), 1));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%1$s" already exists.', $duplicate['host']));
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
	private static function checkProxyAddress(array &$proxies): void {
		foreach ($proxies as $i => &$proxy) {
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
				$proxy += ['proxy_address' => ''];

				if ($proxy['proxy_address'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/proxy_address', _('should be empty'))
					);
				}
			}
		}
		unset($proxy);
	}

	/**
	 * @param array  $proxies
	 * @param string $method
	 *
	 * @throws APIException
	 */
	private static function checkInterface(array &$proxies, string $method): void {
		foreach ($proxies as $i => &$proxy) {
			if ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
				$proxy += ['interface' => []];

				if ($proxy['interface']) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/interface', _('should be empty'))
					);
				}
			}
			else {
				if ($method === 'create' && !array_key_exists('interface', $proxy)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
						_s('the parameter "%1$s" is missing', 'interface')
					));
				}

				if (array_key_exists('interface', $proxy)) {
					$proxy['interface'] += ['useip' => INTERFACE_USE_IP];
					$field_names = [($proxy['interface']['useip'] == INTERFACE_USE_IP) ? 'ip' : 'dns', 'port'];

					foreach ($field_names as $field_name) {
						if (!array_key_exists($field_name, $proxy['interface'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i + 1).'/interface', _s('the parameter "%1$s" is missing', $field_name)
							));
						}

						if ($proxy['interface'][$field_name] === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i + 1).'/interface/'.$field_name, _('cannot be empty')
							));
						}
					}

					$proxy['interface']['type'] = INTERFACE_TYPE_UNKNOWN;
					$proxy['interface']['main'] = INTERFACE_PRIMARY;
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['proxyid']], 'fields' => [
			'proxyid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'status' =>				['type' => API_INT32, 'in' => implode(',', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE])],
			'host' =>				['type' => API_H_NAME, 'length' => DB::getFieldLength('hosts', 'host')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'description')],
			'proxy_address' =>		['type' => API_IP_RANGES, 'flags' => API_ALLOW_DNS, 'length' => DB::getFieldLength('hosts', 'proxy_address')],
			'hosts' =>				['type' => API_OBJECTS, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'interface' =>			['type' => API_OBJECT, 'fields' => [
				'useip' => 				['type' => API_INT32, 'in' => implode(',', [INTERFACE_USE_DNS, INTERFACE_USE_IP])],
				'ip' => 				['type' => API_IP, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'ip')],
				'dns' =>				['type' => API_DNS, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'dns')],
				'port' =>				['type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('interface', 'port')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_proxies = $this->get([
			'output' => ['proxyid', 'host', 'status', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject',
				'description', 'proxy_address'
			],
			'proxyids' => array_column($proxies, 'proxyid'),
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($proxies) != count($db_proxies)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$proxies = $this->extendObjectsByKey($proxies, $db_proxies, 'proxyid', ['status']);

		foreach ($proxies as &$proxy) {
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
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
									['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE],
									['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE])]
			]],
			'tls_accept' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE)],
									['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $proxies, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Load PSK data directly from the DB, since the API won't return secret data.
		$proxies_psk_fields = DB::select($this->tableName(), [
			'output' => ['tls_psk_identity', 'tls_psk'],
			'hostids' => array_keys($db_proxies),
			'preservekeys' => true
		]);

		foreach ($proxies_psk_fields as $hostid => $psk_fields) {
			$db_proxies[$hostid] += $psk_fields;
		}

		foreach ($proxies as &$proxy) {
			if (($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && $proxy['tls_connect'] != HOST_ENCRYPTION_PSK)
					|| ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE
						&& ($proxy['tls_accept'] & HOST_ENCRYPTION_PSK) == 0)) {
				if ($db_proxies[$proxy['proxyid']]['tls_psk_identity'] !== '') {
					$proxy += ['tls_psk_identity' => ''];
				}

				if ($db_proxies[$proxy['proxyid']]['tls_psk'] !== '') {
					$proxy += ['tls_psk' => ''];
				}
			}
			if (($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && $proxy['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE)
					|| ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE
						&& ($proxy['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) == 0)) {
				$proxy += ['tls_issuer' => '', 'tls_subject' => ''];
			}
		}
		unset($proxy);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['else' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_PSK], 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0; }, 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]]
			]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_PASSIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'tls_connect', 'in' => HOST_ENCRYPTION_CERTIFICATE], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '']
										]],
										['if' => ['field' => 'status', 'in' => HOST_STATUS_PROXY_ACTIVE], 'type' => API_MULTIPLE, 'rules' => [
											['if' => static function ($data) { return ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0; }, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
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
		self::checkProxyAddress($proxies);
		self::checkInterface($proxies, 'update');
	}

	/**
	 * @param array $proxies
	 * @param array $db_proxies
	 */
	private static function addAffectedObjects(array $proxies, array &$db_proxies): void {
		$proxyids = ['hosts' => [], 'interface' => []];

		foreach ($proxies as $proxy) {
			if (array_key_exists('hosts', $proxy)) {
				$proxyids['hosts'][] = $proxy['proxyid'];
				$db_proxies[$proxy['proxyid']]['hosts'] = [];
			}

			$proxyids['interface'][] = $proxy['proxyid'];
			$db_proxies[$proxy['proxyid']]['interface'] = [];
		}

		if ($proxyids['hosts']) {
			$options = [
				'output' => ['hostid', 'proxy_hostid'],
				'filter' => ['proxy_hostid' => $proxyids['hosts']]
			];
			$db_hosts = DBselect(DB::makeSql('hosts', $options));

			while ($db_host = DBfetch($db_hosts)) {
				$db_proxies[$db_host['proxy_hostid']]['hosts'][$db_host['hostid']] = [
					'hostid' => $db_host['hostid']
				];
			}
		}

		$options = [
			'output' => ['interfaceid', 'hostid', 'type', 'main', 'useip', 'ip', 'dns', 'port'],
			'filter' => ['hostid' => $proxyids['interface']]
		];
		$db_interfaces = DBselect(DB::makeSql('interface', $options));

		while ($db_interface = DBfetch($db_interfaces)) {
			$db_proxies[$db_interface['hostid']]['interface'] = array_diff_key($db_interface, array_flip(['hostid']));
		}
	}
}
