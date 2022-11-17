<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	 * Get proxy data.
	 *
	 * @param array  $options
	 * @param array  $options['proxyids']
	 * @param bool   $options['editable']	only with read-write permission. Ignored for SuperAdmins
	 * @param int    $options['count']		returns value in rowscount
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['sortfield']
	 * @param string $options['sortorder']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['hostid' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'proxyids'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'selectHosts'				=> null,
			'selectInterface'			=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			if ($permission == PERM_READ_WRITE) {
				return [];
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);
			$sqlParts['where'][] = dbConditionInt('h.hostid', $options['proxyids']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['hostid'] = 'h.hostid';
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['select']['status'] = 'h.status';
			$sqlParts['select']['lastaccess'] = 'h.lastaccess';
		}

		// countOutput
		if ($options['countOutput']) {
			$options['sortfield'] = '';
			$sqlParts['select'] = ['COUNT(DISTINCT h.hostid) AS rowscount'];
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		/*
		 * Cleaning the output from write-only properties.
		 */
		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = array_diff(array_keys(DB::getSchema($this->tableName())['fields']),
				['tls_psk_identity', 'tls_psk', 'name_upper']
			);
		}
		/*
		* For internal calls of API method, is possible to get the write-only fields if they were specified in output.
		* Specify write-only fields in output only if they will not appear in debug mode.
		*/
		elseif (is_array($options['output']) && APP::getMode() === APP::EXEC_MODE_API) {
			$options['output'] = array_diff($options['output'], ['tls_psk_identity', 'tls_psk']);
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($proxy = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $proxy['rowscount'];
			}
			else {
				$proxy['proxyid'] = $proxy['hostid'];
				unset($proxy['hostid']);

				$result[$proxy['proxyid']] = $proxy;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid', 'name_upper'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function create(array $proxies) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'proxy', __FUNCTION__)
			);
		}

		$this->validateCreate($proxies);

		$proxyids = DB::insert('hosts', $proxies);

		foreach ($proxies as $index => &$proxy) {
			$proxy['proxyid'] = $proxyids[$index];
		}
		unset($proxy);

		self::updateInterfaces($proxies, __FUNCTION__);
		self::updateHosts($proxies, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_PROXY, $proxies);

		return ['proxyids' => $proxyids];
	}

	/**
	 * Update proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function update(array $proxies) {
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

		self::updateInterfaces($proxies, __FUNCTION__, $db_proxies);
		self::updateHosts($proxies, __FUNCTION__, $db_proxies);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_PROXY, $proxies, $db_proxies);

		return ['proxyids' => array_column($proxies, 'proxyid')];
	}

	/**
	 * Update table "interface".
	 *
	 * @static
	 *
	 * @param array      $proxies
	 * @param string     $method
	 * @param null|array $db_proxies
	 */
	private static function updateInterfaces(array &$proxies, string $method, array $db_proxies = null): void {
		$ins_interfaces = [];
		$upd_interfaces = [];
		$del_interfaceids = [];

		foreach ($proxies as &$proxy) {
			if (!array_key_exists('interface', $proxy)) {
				continue;
			}

			$db_interface = ($method == 'update') ? $db_proxies[$proxy['proxyid']]['interface'] : [];

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
	 * Update table "hosts".
	 *
	 * @static
	 *
	 * @param array      $proxies
	 * @param string     $method
	 * @param null|array $db_proxies
	 */
	private static function updateHosts(array &$proxies, string $method, array $db_proxies = null): void {
		$upd_hosts = [];

		foreach ($proxies as &$proxy) {
			if (!array_key_exists('hosts', $proxy)) {
				continue;
			}

			$db_hosts = ($method == 'update') ? $db_proxies[$proxy['proxyid']]['hosts'] : [];

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
	 * @param array	$proxyids
	 *
	 * @return array
	 */
	public function delete(array $proxyids) {
		$this->validateDelete($proxyids, $db_proxies);

		DB::delete('interface', ['hostid' => $proxyids]);
		DB::delete('hosts', ['hostid' => $proxyids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_PROXY, $db_proxies);

		return ['proxyids' => $proxyids];
	}

	/**
	 * @param array $proxyids
	 * @param array $db_proxies
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$proxyids, array &$db_proxies = null) {
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

		$this->checkUsedInDiscovery($db_proxies);
		$this->checkUsedInHosts($db_proxies);
		$this->checkUsedInActions($db_proxies);
	}

	/**
	 * Check if proxy is used in network discovery rule.
	 *
	 * @param array  $proxies
	 * @param string $proxies[<proxyid>]['host']
	 */
	private function checkUsedInDiscovery(array $proxies) {
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
	 */
	protected function checkUsedInHosts(array $proxies) {
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
	 */
	private function checkUsedInActions(array $proxies) {
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

		if (!$options['countOutput'] && $options['selectInterface'] !== null) {
			$sqlParts = $this->addQuerySelect('h.hostid', $sqlParts);
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
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $proxies
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$proxies) {
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
	 * Check for unique proxy names.
	 *
	 * @static
	 *
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 *
	 * @throws APIException if proxy names are not unique.
	 */
	protected static function checkDuplicates(array $proxies, array $db_proxies = null): void {
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
	 * Check for valid hosts.
	 *
	 * @static
	 *
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 *
	 * @throws APIException if hosts are not valid.
	 */
	protected static function checkHosts(array $proxies, array $db_proxies = null): void {
		$hostids = [];

		foreach ($proxies as $proxy) {
			if (!array_key_exists('hosts', $proxy)) {
				continue;
			}

			$proxy_hostids = array_column($proxy['hosts'], null, 'hostid');
			$db_proxy_hostids = ($db_proxies !== null)
				? array_column($db_proxies[$proxy['proxyid']]['hosts'], null, 'hostid')
				: [];

			$hostids += array_diff_key($proxy_hostids, $db_proxy_hostids);
		}

		if (!$hostids) {
			return;
		}

		// Check if host exists.
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
	 * Check for valid proxy address field.
	 *
	 * @static
	 *
	 * @param array $proxies
	 *
	 * @throws APIException if proxy addresses are not valid.
	 */
	protected static function checkProxyAddress(array &$proxies): void {
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
	 * Check for valid interface.
	 *
	 * @static
	 *
	 * @param array  $proxies
	 * @param string $method
	 *
	 * @throws APIException if proxy interface is not valid.
	 */
	protected static function checkInterface(array &$proxies, string $method): void {
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
	 * Validates the input parameters for the update() method.
	 *
	 * @param array      $proxies
	 * @param array|null $db_proxies
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$proxies, array &$db_proxies = null) {
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
	 * Add the existing hosts and host interfaces to $db_proxies whether these are affected by the update.
	 *
	 * @static
	 *
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
