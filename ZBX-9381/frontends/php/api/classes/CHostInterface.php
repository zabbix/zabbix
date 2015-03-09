<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Class containing methods for operations with host interfaces.
 *
 * @package API
 */
class CHostInterface extends CZBXAPI {

	protected $tableName = 'interface';
	protected $tableAlias = 'hi';
	protected $sortColumns = array('interfaceid', 'dns', 'ip');

	/**
	 * Get interface data.
	 *
	 * @param array   $options
	 * @param array   $options['nodeids']		Node IDs
	 * @param array   $options['hostids']		Interface IDs
	 * @param boolean $options['editable']		only with read-write permission. Ignored for SuperAdmins
	 * @param boolean $options['selectHosts']	select Interface hosts
	 * @param boolean $options['selectItems']	select Items
	 * @param int     $options['count']			count Interfaces, returned column name is rowscount
	 * @param string  $options['pattern']		search hosts by pattern in Interface name
	 * @param int     $options['limit']			limit selection
	 * @param string  $options['sortfield']		field to sort by
	 * @param string  $options['sortorder']		sort order
	 *
	 * @return array|boolean Interface data as array or false if error
	 */
	public function get(array $options = array()) {
		$result = array();
		$nodeCheck = false;
		$userType = self::$userData['type'];
		$userId = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('interface' => 'hi.interfaceid'),
			'from'		=> array('interface' => 'interface hi'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'interfaceids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userId);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE hi.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.$permission.
				')';
		}

		// nodeids
		$nodeids = is_null($options['nodeids']) ? get_current_nodeid() : $options['nodeids'];

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);
			$sqlParts['where']['interfaceid'] = dbConditionInt('hi.interfaceid', $options['interfaceids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'hi.interfaceid', $nodeids);
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sqlParts['select']['hostid'] = 'hi.hostid';
			$sqlParts['where']['hostid'] = dbConditionInt('hi.hostid', $options['hostids']);

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'hi.hostid', $nodeids);
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['select']['itemid'] = 'i.itemid';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'hi.interfaceid=i.interfaceid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'i.itemid', $nodeids);
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['select']['triggerid'] = 'f.triggerid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'hi.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';

			if (!$nodeCheck) {
				$nodeCheck = true;
				$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'f.triggerid', $nodeids);
			}
		}

		// node check, should last, after all ****IDS checks
		if (!$nodeCheck) {
			$sqlParts['where'] = sqlPartDbNode($sqlParts['where'], 'hi.interfaceid', $nodeids);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('interface hi', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('interface hi', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($interface = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $interface;
				}
				else {
					$result = $interface['rowscount'];
				}
			}
			else {
				if (!isset($result[$interface['interfaceid']])) {
					$result[$interface['interfaceid']] = array();
				}

				// itemids
				if (isset($interface['itemid']) && is_null($options['selectItems'])) {
					if (!isset($result[$interface['interfaceid']]['items'])) {
						$result[$interface['interfaceid']]['items'] = array();
					}
					$result[$interface['interfaceid']]['items'][] = array('itemid' => $interface['itemid']);
					unset($interface['itemid']);
				}
				$result[$interface['interfaceid']] += $interface;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, array('hostid'), $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Is interface exist.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function exists(array $object) {
		$keyFields = array('interfaceid', 'hostid', 'ip', 'dns');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('interfaceid'),
			'nopermissions' => true,
			'limit' => 1
		);

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}

		$objs = $this->get($options);

		return !empty($objs);
	}

	/**
	 * Check interfaces input.
	 *
	 * @param array  $interfaces
	 * @param string $method
	 */
	public function checkInput(array &$interfaces, $method) {
		$update = ($method == 'update');

		// permissions
		if ($update) {
			$interfaceDBfields = array('interfaceid' => null);
			$dbInterfaces = $this->get(array(
				'output' => API_OUTPUT_EXTEND,
				'interfaceids' => zbx_objectValues($interfaces, 'interfaceid'),
				'editable' => true,
				'preservekeys' => true
			));
		}
		else {
			$interfaceDBfields = array(
				'hostid' => null,
				'ip' => null,
				'dns' => null,
				'useip' => null,
				'port' => null,
				'main' => null
			);
		}

		$dbHosts = API::Host()->get(array(
			'output' => array('host'),
			'hostids' => zbx_objectValues($interfaces, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		));

		$dbProxies = API::Proxy()->get(array(
			'output' => array('host'),
			'proxyids' => zbx_objectValues($interfaces, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		));

		foreach ($interfaces as &$interface) {
			if (!check_db_fields($interfaceDBfields, $interface)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if ($update) {
				if (!isset($dbInterfaces[$interface['interfaceid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				$dbInterface = $dbInterfaces[$interface['interfaceid']];
				if (isset($interface['hostid']) && bccomp($dbInterface['hostid'], $interface['hostid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot switch host for interface.'));
				}

				$interface['hostid'] = $dbInterface['hostid'];

				// we check all fields on "updated" interface
				$updInterface = $interface;
				$interface = zbx_array_merge($dbInterface, $interface);
			}
			else {
				if (!isset($dbHosts[$interface['hostid']]) && !isset($dbProxies[$interface['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				if (isset($dbProxies[$interface['hostid']])) {
					$interface['type'] = INTERFACE_TYPE_UNKNOWN;
				}
				elseif (!isset($interface['type'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to method.'));
				}
			}

			if (zbx_empty($interface['ip']) && zbx_empty($interface['dns'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('IP and DNS cannot be empty for host interface.'));
			}

			if ($interface['useip'] == INTERFACE_USE_IP && zbx_empty($interface['ip'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Interface with DNS "%1$s" cannot have empty IP address.', $interface['dns']));
			}

			if ($interface['useip'] == INTERFACE_USE_DNS && zbx_empty($interface['dns'])) {
				if ($dbHosts && !empty($dbHosts[$interface['hostid']]['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Interface with IP "%1$s" cannot have empty DNS name while having "Use DNS" property on "%2$s".',
							$interface['ip'],
							$dbHosts[$interface['hostid']]['host']
					));
				}
				elseif ($dbProxies && !empty($dbProxies[$interface['hostid']]['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Interface with IP "%1$s" cannot have empty DNS name while having "Use DNS" property on "%2$s".',
							$interface['ip'],
							$dbProxies[$interface['hostid']]['host']
					));
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Interface with IP "%1$s" cannot have empty DNS name.', $interface['ip']));
				}
			}

			if (isset($interface['dns'])) {
				$this->checkDns($interface);
			}
			if (isset($interface['ip'])) {
				$this->checkIp($interface);
			}
			if (isset($interface['port']) || $method == 'create') {
				$this->checkPort($interface);
			}

			if ($update) {
				$interface = $updInterface;
			}
		}
		unset($interface);

		// check if any of the affected hosts are discovered
		if ($update) {
			$interfaces = $this->extendObjects('interface', $interfaces, array('hostid'));
		}
		$this->checkValidator(zbx_objectValues($interfaces, 'hostid'), new CHostNormalValidator(array(
			'message' => _('Cannot update interface for discovered host "%1$s".')
		)));
	}

	/**
	 * Add interfaces.
	 *
	 * @param array $interfaces multidimensional array with Interfaces data
	 *
	 * @return array
	 */
	public function create(array $interfaces) {
		$interfaces = zbx_toArray($interfaces);

		$this->checkInput($interfaces, __FUNCTION__);
		$this->checkMainInterfacesOnCreate($interfaces);

		$interfaceIds = DB::insert('interface', $interfaces);

		return array('interfaceids' => $interfaceIds);
	}

	/**
	 * Update interfaces.
	 *
	 * @param array $interfaces multidimensional array with Interfaces data
	 *
	 * @return array
	 */
	public function update(array $interfaces) {
		$interfaces = zbx_toArray($interfaces);

		$this->checkInput($interfaces, __FUNCTION__);
		$this->checkMainInterfacesOnUpdate($interfaces);

		$data = array();
		foreach ($interfaces as $interface) {
			$data[] = array(
				'values' => $interface,
				'where' => array('interfaceid' => $interface['interfaceid'])
			);
		}
		DB::update('interface', $data);

		return array('interfaceids' => zbx_objectValues($interfaces, 'interfaceid'));
	}

	protected function clearValues(array $interface) {
		if (isset($interface['port']) && $interface['port'] != '') {
			$interface['port'] = ltrim($interface['port'], '0');

			if ($interface['port'] == '') {
				$interface['port'] = 0;
			}
		}

		return $interface;
	}

	/**
	 * Delete interfaces.
	 * Interface cannot be deleted if it's main interface and exists other interface of same type on same host.
	 * Interface cannot be deleted if it is used in items.
	 *
	 * @param array $interfaceids
	 *
	 * @return array
	 */
	public function delete(array $interfaceids) {
		if (empty($interfaceids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$dbInterfaces = $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'interfaceids' => $interfaceids,
			'editable' => true,
			'preservekeys' => true
		));
		foreach ($interfaceids as $interfaceId) {
			if (!isset($dbInterfaces[$interfaceId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkMainInterfacesOnDelete($interfaceids);

		DB::delete('interface', array('interfaceid' => $interfaceids));

		return array('interfaceids' => $interfaceids);
	}

	public function massAdd(array $data) {
		$interfaces = zbx_toArray($data['interfaces']);
		$hosts = zbx_toArray($data['hosts']);

		$insertData = array();
		foreach ($interfaces as $interface) {
			foreach ($hosts as $host) {
				$newInterface = $interface;
				$newInterface['hostid'] = $host['hostid'];

				$insertData[] = $newInterface;
			}
		}

		$interfaceIds = $this->create($insertData);

		return array('interfaceids' => $interfaceIds);
	}

	protected function validateMassRemove(array $data) {
		// check permissions
		$this->checkHostPermissions($data['hostids']);

		// check interfaces
		foreach ($data['interfaces'] as $interface) {
			if (!isset($interface['dns']) || !isset($interface['ip']) || !isset($interface['port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			$this->checkDns($interface);
			$this->checkIp($interface);
			$this->checkPort($interface);

			// check main interfaces
			$interfacesToRemove = API::getApi()->select($this->tableName(), array(
				'output' => array('interfaceid'),
				'filter' => array(
					'hostid' => $data['hostids'],
					'ip' => $interface['ip'],
					'dns' => $interface['dns'],
					'port' => $interface['port']
				)
			));
			if ($interfacesToRemove) {
				$this->checkMainInterfacesOnDelete(zbx_objectValues($interfacesToRemove, 'interfaceid'));
			}
		}
	}

	/**
	 * Remove hosts from interfaces.
	 *
	 * @param array $data
	 * @param array $data['interfaceids']
	 * @param array $data['hostids']
	 * @param array $data['templateids']
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$data['interfaces'] = zbx_toArray($data['interfaces']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$this->validateMassRemove($data);

		foreach ($data['interfaces'] as $interface) {
			DB::delete('interface', array(
				'hostid' => $data['hostids'],
				'ip' => $interface['ip'],
				'dns' => $interface['dns'],
				'port' => $interface['port']
			));
		}

		return array('interfaceids' => zbx_objectValues($data['interfaces'], 'interfaceid'));
	}

	/**
	 * Replace existing interfaces with input interfaces.
	 *
	 * @param $host
	 */
	public function replaceHostInterfaces(array $host) {
		if (isset($host['interfaces']) && !is_null($host['interfaces'])) {
			$host['interfaces'] = zbx_toArray($host['interfaces']);

			$this->checkHostInterfaces($host['interfaces'], $host['hostid']);

			$interfacesToDelete = API::HostInterface()->get(array(
				'hostids' => $host['hostid'],
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'nopermissions' => true
			));

			$interfacesToAdd = array();
			$interfacesToUpdate = array();

			foreach ($host['interfaces'] as $interface) {
				$interface['hostid'] = $host['hostid'];

				if (!isset($interface['interfaceid'])) {
					$interfacesToAdd[] = $interface;
				}
				elseif (isset($interfacesToDelete[$interface['interfaceid']])) {
					$interfacesToUpdate[] = $interface;
					unset($interfacesToDelete[$interface['interfaceid']]);
				}
			}

			if ($interfacesToUpdate) {
				API::HostInterface()->checkInput($interfacesToUpdate, 'update');

				$data = array();
				foreach ($interfacesToUpdate as $interface) {
					$data[] = array(
						'values' => $interface,
						'where' => array('interfaceid' => $interface['interfaceid'])
					);
				}
				DB::update('interface', $data);
			}

			if ($interfacesToAdd) {
				$this->checkInput($interfacesToAdd, 'create');
				DB::insert('interface', $interfacesToAdd);
			}

			if ($interfacesToDelete) {
				$this->delete(zbx_objectValues($interfacesToDelete, 'interfaceid'));
			}
		}
	}

	/**
	 * Validates the "dns" field.
	 *
	 * @throws APIException if the field is invalid.
	 *
	 * @param array $interface
	 */
	protected function checkDns(array $interface) {
		if (zbx_strlen($interface['dns']) > 64) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_n(
					'Maximum DNS name length is %1$d characters, "%2$s" is %3$d character.',
					'Maximum DNS name length is %1$d characters, "%2$s" is %3$d characters.',
					64,
					$interface['dns'],
					zbx_strlen($interface['dns'])
				)
			);
		}

		if (!empty($interface['dns']) && !preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/', $interface['dns'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect interface DNS parameter "%s" provided.', $interface['dns']));
		}
	}

	/**
	 * Validates the "ip" field.
	 *
	 * @throws APIException if the field is invalid.
	 *
	 * @param array $interface
	 */
	protected function checkIp(array $interface) {
		if (!zbx_empty($interface['ip']) && !validate_ip($interface['ip'], $arr)
				&& !preg_match('/^'.ZBX_PREG_MACRO_NAME_FORMAT.'$/i', $interface['ip'])
				&& !preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/i', $interface['ip'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect interface IP parameter "%s" provided.', $interface['ip']));
		}
	}

	/**
	 * Validates the "port" field.
	 *
	 * @throws APIException if the field is empty or invalid.
	 *
	 * @param array $interface
	 */
	protected function checkPort(array $interface) {
		if (!isset($interface['port']) || zbx_empty($interface['port'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Port cannot be empty for host interface.'));
		}
		elseif (!validatePortNumberOrMacro($interface['port'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect interface port "%s" provided.', $interface['port']));
		}
	}

	/**
	 * Checks if the current user has access to the given hosts. Assumes the "hostid" field is valid.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts
	 *
	 * @param array $hostIds	an array of host IDs
	 */
	protected function checkHostPermissions(array $hostIds) {
		if (!API::Host()->isWritable($hostIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	private function checkHostInterfaces(array $interfaces, $hostid) {
		$interfacesWithMissingData = array();

		foreach ($interfaces as $interface) {
			if (!isset($interface['type'], $interface['main'])) {
				$interfacesWithMissingData[] = $interface['interfaceid'];
			}
		}

		if ($interfacesWithMissingData) {
			$dbInterfaces = API::HostInterface()->get(array(
				'interfaceids' => $interfacesWithMissingData,
				'output' => array('main', 'type'),
				'preservekeys' => true,
				'nopermissions' => true
			));
		}

		foreach ($interfaces as $id => $interface) {
			if (isset($interface['interfaceid']) && isset($dbInterfaces[$interface['interfaceid']])) {
				$interfaces[$id] = array_merge($interface, $dbInterfaces[$interface['interfaceid']]);
			}
			$interfaces[$id]['hostid'] = $hostid;
		}

		$this->checkMainInterfaces($interfaces);
	}

	private function checkMainInterfacesOnCreate(array $interfaces) {
		$hostIds = array();
		foreach ($interfaces as $interface) {
			$hostIds[$interface['hostid']] = $interface['hostid'];
		}

		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => $hostIds,
			'output' => array('hostid', 'main', 'type'),
			'preservekeys' => true,
			'nopermissions' => true
		));
		$interfaces = array_merge($dbInterfaces, $interfaces);

		$this->checkMainInterfaces($interfaces);
	}

	private function checkMainInterfacesOnUpdate(array $interfaces) {
		$interfaceidsWithoutHostIds = array();

		// gather all hostids where interfaces should be checked
		foreach ($interfaces as $interface) {
			if (isset($interface ['type']) || isset($interface['main'])) {
				if (isset($interface['hostid'])) {
					$hostids[$interface['hostid']] = $interface['hostid'];
				}
				else {
					$interfaceidsWithoutHostIds[] = $interface['interfaceid'];
				}
			}
		}

		// gather missing host ids
		$hostIds = array();
		if ($interfaceidsWithoutHostIds) {
			$dbResult = DBselect('SELECT DISTINCT i.hostid FROM interface i WHERE '.dbConditionInt('i.interfaceid', $interfaceidsWithoutHostIds));
			while ($hostData = DBfetch($dbResult)) {
				$hostIds[$hostData['hostid']] = $hostData['hostid'];
			}
		}

		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => $hostIds,
			'output' => array('hostid', 'main', 'type'),
			'preservekeys' => true,
			'nopermissions' => true
		));

		// update interfaces from DB with data that will be updated.
		foreach ($interfaces as $interface) {
			if (isset($dbInterfaces[$interface['interfaceid']])) {
				$dbInterfaces[$interface['interfaceid']] = array_merge(
					$dbInterfaces[$interface['interfaceid']],
					$interfaces[$interface['interfaceid']]
				);
			}
		}

		$this->checkMainInterfaces($dbInterfaces);
	}

	private function checkMainInterfacesOnDelete(array $interfaceIds) {
		$this->checkIfInterfaceHasItems($interfaceIds);

		$hostids = array();
		$dbResult = DBselect('SELECT DISTINCT i.hostid FROM interface i WHERE '.dbConditionInt('i.interfaceid', $interfaceIds));
		while ($hostData = DBfetch($dbResult)) {
			$hostids[$hostData['hostid']] = $hostData['hostid'];
		}

		$dbInterfaces = API::HostInterface()->get(array(
			'hostids' => $hostids,
			'output' => array('hostid', 'main', 'type'),
			'preservekeys' => true,
			'nopermissions' => true
		));

		foreach ($interfaceIds as $interfaceId) {
			unset($dbInterfaces[$interfaceId]);
		}

		$this->checkMainInterfaces($dbInterfaces);
	}

	/**
	 * Check if main interfaces are correctly set for every interface type.
	 * Each host must either have only one main interface for each interface type, or have no interface of that type at all.
	 *
	 * @param array $interfaces
	 */
	private function checkMainInterfaces(array $interfaces) {
		$interfaceTypes = array();
		foreach ($interfaces as $interface) {
			if (!isset($interfaceTypes[$interface['hostid']])) {
				$interfaceTypes[$interface['hostid']] = array();
			}

			if (!isset($interfaceTypes[$interface['hostid']][$interface['type']])) {
				$interfaceTypes[$interface['hostid']][$interface['type']] = array('main' => 0, 'all' => 0);
			}

			if ($interface['main'] == INTERFACE_PRIMARY) {
				$interfaceTypes[$interface['hostid']][$interface['type']]['main']++;
			}
			else {
				$interfaceTypes[$interface['hostid']][$interface['type']]['all']++;
			}
		}

		foreach ($interfaceTypes as $interfaceHostId => $interfaceType) {
			foreach ($interfaceType as $type => $counters) {
				if ($counters['all'] && !$counters['main']) {
					$host = API::Host()->get(array(
						'hostids' => $interfaceHostId,
						'output' => array('name'),
						'preservekeys' => true,
						'nopermissions' => true
					));
					$host = reset($host);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('No default interface for "%1$s" type on "%2$s".', hostInterfaceTypeNumToName($type), $host['name']));
				}

				if ($counters['main'] > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Host cannot have more than one default interface of the same type.'));
				}
			}
		}
	}

	private function checkIfInterfaceHasItems(array $interfaceIds) {
		$items = API::Item()->get(array(
			'output' => array('name'),
			'selectHosts' => array('name'),
			'interfaceids' => $interfaceIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'limit' => 1
		));

		foreach ($items as $item) {
			$host = reset($item['hosts']);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Interface is linked to item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null && $options['selectHosts'] !== null) {
			$sqlParts = $this->addQuerySelect('hi.hostid', $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$interfaceIds = array_keys($result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'interfaceid', 'hostid');
			$hosts = API::Host()->get(array(
				'output' => $options['selectHosts'],
				'nodeids' => $options['nodeids'],
				'hosts' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding items
		if ($options['selectItems'] !== null) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = API::Item()->get(array(
					'output' => $this->outputExtend('items', array('itemid', 'interfaceid'), $options['selectItems']),
					'nodeids' => $options['nodeids'],
					'interfaceids' => $interfaceIds,
					'nopermissions' => true,
					'preservekeys' => true,
					'filter' => array('flags' => null)
				));
				$relationMap = $this->createRelationMap($items, 'interfaceid', 'itemid');

				$items = $this->unsetExtraFields($items, array('interfaceid', 'itemid'), $options['selectItems']);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::Item()->get(array(
					'nodeids' => $options['nodeids'],
					'interfaceids' => $interfaceIds,
					'nopermissions' => true,
					'filter' => array('flags' => null),
					'countOutput' => true,
					'groupCount' => true
				));
				$items = zbx_toHash($items, 'interfaceid');
				foreach ($result as $interfaceId => $interface) {
					$result[$interfaceId]['items'] = isset($items[$interfaceId]) ? $items[$interfaceId]['rowscount'] : 0;
				}
			}
		}

		return $result;
	}
}
