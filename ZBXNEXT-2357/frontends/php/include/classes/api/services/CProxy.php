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
 * Class containing methods for operations with proxies.
 *
 * @package API
 */
class CProxy extends CApiService {

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

		$userType = self::$userData['type'];

		$sqlParts = [
			'select'	=> ['hostid' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'proxyids'					=> null,
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
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'selectHosts'				=> null,
			'selectInterface'			=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
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
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = ['COUNT(DISTINCT h.hostid) AS rowscount'];
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function checkInput(&$proxies, $method) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!'));
		}

		$create = ($method == 'create');
		$update = ($method == 'update');

		$proxyIds = zbx_objectValues($proxies, 'proxyid');

		foreach ($proxies as &$proxy) {
			if (isset($proxy['proxyid'])) {
				$proxy['hostid'] = $proxy['proxyid'];
			}
			elseif (isset($proxy['hostid'])) {
				$proxy['proxyid'] = $proxy['hostid'];
			}
		}
		unset($proxy);

		// permissions
		if ($update) {
			$proxyDBfields = ['proxyid' => null];

			$dbProxies = $this->get([
				'output' => ['proxyid', 'hostid', 'host', 'status'],
				'proxyids' => $proxyIds,
				'editable' => true,
				'preservekeys' => true
			]);
		}
		else {
			$proxyDBfields = ['host' => null];
		}

		foreach ($proxies as &$proxy) {
			if (!check_db_fields($proxyDBfields, $proxy)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for proxy "%1$s".', $proxy['host']));
			}

			if ($update) {
				if (!isset($dbProxies[$proxy['proxyid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('No permissions to referred object or it does not exist!'));
				}

				if (isset($proxy['status'])
						&& ($proxy['status'] != HOST_STATUS_PROXY_ACTIVE
						&& $proxy['status'] != HOST_STATUS_PROXY_PASSIVE)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value used for proxy status "%1$s".', $proxy['status']));
				}

				$status = isset($proxy['status']) ? $proxy['status'] : $dbProxies[$proxy['proxyid']]['status'];
			}
			else {
				if (!isset($proxy['status'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No status for proxy.'));
				}
				elseif ($proxy['status'] != HOST_STATUS_PROXY_ACTIVE && $proxy['status'] != HOST_STATUS_PROXY_PASSIVE) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value used for proxy status "%1$s".', $proxy['status']));
				}

				$status = $proxy['status'];
			}

			// host
			if (isset($proxy['host'])) {
				if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $proxy['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect characters used for proxy name "%1$s".', $proxy['host']));
				}

				$proxiesExists = $this->get([
					'output' => ['proxyid'],
					'filter' => ['host' => $proxy['host']]
				]);
				foreach ($proxiesExists as $proxyExists) {
					if ($create || bccomp($proxyExists['proxyid'], $proxy['proxyid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%s" already exists.', $proxy['host']));
					}
				}
			}

			// interface
			if ($status == HOST_STATUS_PROXY_PASSIVE) {
				if ($create && empty($proxy['interface'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('No interface provided for proxy "%s".', $proxy['host']));
				}

				if (isset($proxy['interface'])) {
					if (!is_array($proxy['interface']) || empty($proxy['interface'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('No interface provided for proxy "%s".', $proxy['host']));
					}

					// mark the interface as main to pass host interface validation
					$proxy['interface']['main'] = INTERFACE_PRIMARY;
				}
			}

			// check if hosts exist
			if (!empty($proxy['hosts'])) {
				$hostIds = zbx_objectValues($proxy['hosts'], 'hostid');

				$hosts = API::Host()->get([
					'hostids' => $hostIds,
					'editable' => true,
					'output' => ['hostid', 'proxy_hostid', 'name'],
					'preservekeys' => true
				]);

				if (empty($hosts)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('No permissions to referred object or it does not exist!'));
				}
			}
		}
		unset($proxy);

		// check if any of the affected hosts are discovered
		$hostIds = [];
		foreach ($proxies as $proxy) {
			if (isset($proxy['hosts'])) {
				$hostIds = array_merge($hostIds, zbx_objectValues($proxy['hosts'], 'hostid'));
			}
		}
		$this->checkValidator($hostIds, new CHostNormalValidator([
			'message' => _('Cannot update proxy for discovered host "%1$s".')
		]));
	}

	/**
	 * Create proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function create(array $proxies) {
		$proxies = zbx_toArray($proxies);

		$proxies = $this->convertDeprecatedValues($proxies);

		$this->checkInput($proxies, __FUNCTION__);

		$proxyIds = DB::insert('hosts', $proxies);

		$hostUpdate = [];
		foreach ($proxies as $key => $proxy) {
			if (!empty($proxy['hosts'])) {
				$hostUpdate[] = [
					'values' => ['proxy_hostid' => $proxyIds[$key]],
					'where' => ['hostid' => zbx_objectValues($proxy['hosts'], 'hostid')]
				];
			}

			// create interface
			if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE) {
				$proxy['interface']['hostid'] = $proxyIds[$key];

				if (!API::HostInterface()->create($proxy['interface'])) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Proxy interface creation failed.'));
				}
			}
		}

		DB::update('hosts', $hostUpdate);

		return ['proxyids' => $proxyIds];
	}

	/**
	 * Update proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function update(array $proxies) {
		$proxies = zbx_toArray($proxies);

		$proxies = $this->convertDeprecatedValues($proxies);

		$this->checkInput($proxies, __FUNCTION__);

		$proxyIds = $proxyUpdate = $hostUpdate = [];

		foreach ($proxies as $proxy) {
			$proxyIds[] = $proxy['proxyid'];

			$proxyUpdate[] = [
				'values' => $proxy,
				'where' => ['hostid' => $proxy['proxyid']]
			];

			if (isset($proxy['hosts'])) {
				// unset proxy for all hosts except for discovered hosts
				$hostUpdate[] = [
					'values' => ['proxy_hostid' => 0],
					'where' => [
						'proxy_hostid' => $proxy['proxyid'],
						'flags' => ZBX_FLAG_DISCOVERY_NORMAL
					]
				];

				$hostUpdate[] = [
					'values' => ['proxy_hostid' => $proxy['proxyid']],
					'where' => ['hostid' => zbx_objectValues($proxy['hosts'], 'hostid')]
				];
			}

			// if this is an active proxy - delete it's interface;
			if (isset($proxy['status']) && $proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
				$interfaces = API::HostInterface()->get([
					'hostids' => $proxy['hostid'],
					'output' => ['interfaceid']
				]);
				$interfaceIds = zbx_objectValues($interfaces, 'interfaceid');

				if ($interfaceIds) {
					API::HostInterface()->delete($interfaceIds);
				}
			}

			// update the interface of a passive proxy
			elseif (isset($proxy['interface']) && is_array($proxy['interface'])) {
				$proxy['interface']['hostid'] = $proxy['hostid'];

				$result = isset($proxy['interface']['interfaceid'])
					? API::HostInterface()->update($proxy['interface'])
					: API::HostInterface()->create($proxy['interface']);

				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Proxy interface update failed.'));
				}
			}
		}

		DB::update('hosts', $proxyUpdate);
		DB::update('hosts', $hostUpdate);

		return ['proxyids' => $proxyIds];
	}

	/**
	 * Delete proxy.
	 *
	 * @param array	$proxyIds
	 *
	 * @return array
	 */
	public function delete(array $proxyIds) {
		$this->validateDelete($proxyIds);

		$dbProxies = DBselect(
			'SELECT h.hostid,h.host'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.hostid', $proxyIds)
		);
		$dbProxies = DBfetchArrayAssoc($dbProxies, 'hostid');

		$actionIds = [];

		// get conditions
		$dbActions = DBselect(
			'SELECT DISTINCT c.actionid'.
			' FROM conditions c'.
			' WHERE c.conditiontype='.CONDITION_TYPE_PROXY.
				' AND '.dbConditionString('c.value', $proxyIds)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionIds[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if ($actionIds) {
			DB::update('actions', [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionIds]
			]);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_PROXY,
			'value' => $proxyIds
		]);

		// delete interface
		DB::delete('interface', ['hostid' => $proxyIds]);

		// delete host
		DB::delete('hosts', ['hostid' => $proxyIds]);

		// TODO: remove info from API
		foreach ($dbProxies as $proxy) {
			info(_s('Deleted: Proxy "%1$s".', $proxy['host']));
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_PROXY, '['.$proxy['host'].'] ['.$proxy['hostid'].']');
		}

		return ['proxyids' => $proxyIds];
	}

	/**
	 * Check if proxies can be deleted.
	 *  - only super admin can delete proxy
	 *  - cannot delete proxy if it is used to monitor host
	 *  - cannot delete proxy if it is used in discovery rule
	 *
	 * @param array $proxyIds
	 */
	protected function validateDelete(array $proxyIds) {
		if (empty($proxyIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkPermissions($proxyIds);
		$this->checkUsedInDiscoveryRule($proxyIds);
		$this->checkUsedForMonitoring($proxyIds);
	}

	/**
	 * Check if user has read permissions for proxy.
	 *
	 * @param array $proxyIds
	 *
	 * @return bool
	 */
	public function isReadable(array $proxyIds) {
		if (empty($proxyIds)) {
			return true;
		}

		$proxyIds = array_unique($proxyIds);

		$count = $this->get([
			'proxyids' => $proxyIds,
			'countOutput' => true
		]);

		return (count($proxyIds) == $count);
	}

	/**
	 * Check if user has write permissions for proxy.
	 *
	 * @param array $proxyIds
	 *
	 * @return bool
	 */
	public function isWritable(array $proxyIds) {
		if (empty($proxyIds)) {
			return true;
		}

		$proxyIds = array_unique($proxyIds);

		$count = $this->get([
			'proxyids' => $proxyIds,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($proxyIds) == $count);
	}

	/**
	 * Checks if the given proxies are editable.
	 *
	 * @param array $proxyIds	proxy IDs to check
	 *
	 * @throws APIException		if the user has no permissions to edit proxies or a proxy does not exist
	 */
	protected function checkPermissions(array $proxyIds) {
		if (!$this->isWritable($proxyIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Check if proxy is used in discovery rule.
	 *
	 * @param array $proxyIds
	 */
	protected function checkUsedInDiscoveryRule(array $proxyIds) {
		$dRule = DBfetch(DBselect(
			'SELECT dr.druleid,dr.name,dr.proxy_hostid'.
			' FROM drules dr'.
			' WHERE '.dbConditionInt('dr.proxy_hostid', $proxyIds),
			1
		));
		if ($dRule) {
			$proxy = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.zbx_dbstr($dRule['proxy_hostid'])));

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Proxy "%1$s" is used by discovery rule "%2$s".', $proxy['host'], $dRule['name']));
		}
	}

	/**
	 * Check if proxy is used to monitor hosts.
	 *
	 * @param array $proxyIds
	 */
	protected function checkUsedForMonitoring(array $proxyIds) {
		$host = DBfetch(DBselect(
			'SELECT h.name,h.proxy_hostid'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.proxy_hostid', $proxyIds),
			1
		));
		if ($host) {
			$proxy = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.zbx_dbstr($host['proxy_hostid'])));

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host "%1$s" is monitored with proxy "%2$s".', $host['name'], $proxy['host']));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null && $options['selectInterface'] !== null) {
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
	 * Convert deprecated "interfaces" to "interface".
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	protected function convertDeprecatedValues($proxies) {
		foreach ($proxies as $key => $proxy) {
			if (isset($proxy['interfaces'])) {
				$this->deprecated('Array of "interfaces" is deprecated, use single "interface" instead.');

				$proxy['interface'] = reset($proxy['interfaces']);
				unset($proxy['interfaces']);

				$proxies[$key] = $proxy;
			}
		}

		return $proxies;
	}
}
