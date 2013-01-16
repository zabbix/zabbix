<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @package API
 */
class CProxy extends CZBXAPI {

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';
	protected $sortColumns = array('hostid', 'host', 'status');

	/**
	 * Get Proxy data
	 *
	 * @param array $options
	 * @param array $options['nodeids']
	 * @param array $options['proxyids']
	 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
	 * @param int $options['count'] returns value in rowscount
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['sortfield']
	 * @param string $options['sortorder']
	 * @return array|boolean
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];

		$sqlParts = array(
			'select'	=> array('hostid' => 'h.hostid'),
			'from'		=> array('hosts' => 'hosts h'),
			'where'		=> array('h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'selectHosts'				=> null,
			'selectInterfaces'			=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			if ($permission == PERM_READ_WRITE) {
				return array();
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
			$sqlParts['select'] = array('count(DISTINCT h.hostid) as rowscount');
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($proxy = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $proxy['rowscount'];
			}
			else {
				$proxy['proxyid'] = $proxy['hostid'];
				unset($proxy['hostid']);

				if (!isset($result[$proxy['proxyid']])) {
					$result[$proxy['proxyid']]= array();
				}

				$result[$proxy['proxyid']] += $proxy;
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

	protected function checkInput(&$proxies, $method) {
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
			$proxyDBfields = array('proxyid'=> null);
			$dbProxies = $this->get(array(
				'output' => array('proxyid', 'hostid', 'host', 'status'),
				'proxyids' => $proxyIds,
				'editable' => true,
				'preservekeys' => true
			));
		}
		else {
			$proxyDBfields = array('host' => null);
		}

		foreach ($proxies as &$proxy) {
			if (!check_db_fields($proxyDBfields, $proxy)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for proxy "%s".', $proxy['host']));
			}

			if ($update) {
				if (!isset($dbProxies[$proxy['proxyid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				if (isset($proxy['status']) && ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE)) {
					if ($dbProxies[$proxy['proxyid']]['status'] == $proxy['status']) {
						unset($proxy['status']);
					}
					elseif (!isset($proxy['interfaces'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces provided for proxy "%s".', $proxy['host']));
					}
				}
			}
			else {
				if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				if ($proxy['status'] == HOST_STATUS_PROXY_PASSIVE && !isset($proxy['interfaces'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces provided for proxy "%s".', $proxy['host']));
				}
			}

			if (isset($proxy['interfaces'])) {
				if (!is_array($proxy['interfaces']) || empty($proxy['interfaces'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces for proxy "%s".', $proxy['host']));
				}
				elseif (count($proxy['interfaces']) > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Too many interfaces provided for proxy "%s".', $proxy['host']));
				}

				$interface = reset($proxy['interfaces']);
				if (preg_match('/^(0{1,3}\.){3,3}0{1,3}$/', $interface['ip'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect IP for passive proxy "%1$s".', $interface['ip']));
				}

				// mark the interface as main to pass host interface validation
				$proxy['interfaces'][0]['main'] = INTERFACE_PRIMARY;
			}

			if (isset($proxy['host'])) {
				if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $proxy['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect characters used for Proxy name "%s".', $proxy['host']));
				}

				$proxiesExists = $this->get(array(
					'filter' => array('host' => $proxy['host'])
				));
				foreach ($proxiesExists as $proxyExists) {
					if (!$update || (bccomp($proxyExists['proxyid'], $proxy['proxyid']) != 0)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Proxy "%s" already exists.', $proxy['host']));
					}
				}
			}
		}
		unset($proxy);
	}

	public function create($proxies) {
		$proxies = zbx_toArray($proxies);

		$this->checkInput($proxies, __FUNCTION__);

		$proxyids = DB::insert('hosts', $proxies);

		$hostUpdate = array();
		foreach ($proxies as $pnum => $proxy) {
			if (!isset($proxy['hosts'])) {
				continue;
			}

			$hostids = zbx_objectValues($proxy['hosts'], 'hostid');
			$hostUpdate[] = array(
				'values' => array('proxy_hostid' => $proxyids[$pnum]),
				'where' => array('hostid' => $hostids)
			);

			if ($proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
				continue;
			}

			// create the interface
			$proxy['interfaces'][0]['hostid'] = $proxyids[$pnum];
			$result = API::HostInterface()->create($proxy['interfaces']);
			if (!$result) {
				self::exception(ZBX_API_ERROR_INTERNAL, _('Proxy interface creation failed.'));
			}
		}

		DB::update('hosts', $hostUpdate);

		return array('proxyids' => $proxyids);
	}

	public function update($proxies) {
		$proxies = zbx_toArray($proxies);
		$proxyids = array();

		$this->checkInput($proxies, __FUNCTION__);

		$proxyUpdate = array();
		$hostUpdate = array();

		foreach ($proxies as $proxy) {
			$proxyids[] = $proxy['proxyid'];

			$proxyUpdate[] = array(
				'values' => $proxy,
				'where' => array('hostid' => $proxy['proxyid'])
			);

			if (!isset($proxy['hosts'])) {
				continue;
			}

			$hostUpdate[] = array(
				'values' => array('proxy_hostid' => 0),
				'where' => array('proxy_hostid' => $proxy['proxyid'])
			);

			$hostids = zbx_objectValues($proxy['hosts'], 'hostid');
			$hostUpdate[] = array(
				'values' => array('proxy_hostid' => $proxy['proxyid']),
				'where' => array('hostid' => $hostids)
			);

			// if this is an active proxy - delete it's interface;
			if (isset($proxy['status']) && $proxy['status'] == HOST_STATUS_PROXY_ACTIVE) {
				$interfaces = API::HostInterface()->get(array(
					'output' => API_OUTPUT_REFER,
					'hostids' => $proxy['hostid']
				));
				$interfaceids = zbx_objectValues($interfaces, 'interfaceid');
				if ($interfaceids) {
					API::HostInterface()->delete($interfaceids);
				}
			}
			// update the interface of a passive proxy
			elseif (isset($proxy['interfaces']) && is_array($proxy['interfaces'])) {
				$proxy['interfaces'][0]['hostid'] = $proxy['hostid'];

				if (isset($proxy['interfaces'][0]['interfaceid'])) {
					$result = API::HostInterface()->update($proxy['interfaces']);
				}
				else {
					$result = API::HostInterface()->create($proxy['interfaces']);
				}

				if (!$result) {
					self::exception(ZBX_API_ERROR_INTERNAL, _('Proxy interface update failed.'));
				}
			}
		}

		DB::update('hosts', $proxyUpdate);
		DB::update('hosts', $hostUpdate);

		return array('proxyids' => $proxyids);
	}

	/**
	 * Delete Proxy.
	 *
	 * @param array $proxies
	 *
	 * @return array
	 */
	public function delete(array $proxies) {
		$proxies = zbx_toArray($proxies);
		$this->validateDelete($proxies);

		$proxyIds = zbx_objectValues($proxies, 'proxyid');

		$dbProxies = DBselect(
			'SELECT h.hostid,h.host'.
					' FROM hosts h'.
					' WHERE '.dbConditionInt('h.hostid', $proxyIds));
		$dbProxies = DBfetchArrayAssoc($dbProxies, 'hostid');

		$actionids = array();
		// get conditions
		$dbActions = DBselect(
			'SELECT DISTINCT c.actionid'.
			' FROM conditions c'.
			' WHERE c.conditiontype='.CONDITION_TYPE_PROXY.
				' AND '.dbConditionString('c.value', $proxyIds)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if (!empty($actionids)) {
			$update = array(
				'values' => array('status' => ACTION_STATUS_DISABLED),
				'where' => array('actionid' => $actionids)
			);
			DB::update('actions', $update);
		}

		// delete action conditions
		DB::delete('conditions', array(
			'conditiontype' => CONDITION_TYPE_PROXY,
			'value' => $proxyIds
		));

		// interfaces
		DB::delete('interface', array('hostid' => $proxyIds));

		// delete host
		DB::delete('hosts', array('hostid' => $proxyIds));

		// TODO: remove info from API
		foreach ($dbProxies as $proxy) {
			info(_s('Deleted: Proxy "%1$s".', $proxy['host']));
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_PROXY, '['.$proxy['host'].'] ['.$proxy['hostid'].']');
		}

		return array('proxyids' => $proxyIds);
	}

	/**
	 * Check if proxies can be deleted.
	 *  - only super admin can delete proxy
	 *  - cannot delete proxy if it is used to monitor host
	 *  - cannot delete proxy if it is used in discovery rule
	 *
	 * @param array $proxies
	 */
	protected function validateDelete(array $proxies) {
		if (empty($proxies)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkPermissions();
		$this->checkUsedInDiscoveryRule($proxies);
		$this->checkUsedForMonitoring($proxies);
	}

	/**
	 * Check if user has read permissions for proxy.
	 *
	 * @param array $proxyids
	 * @return bool
	 */
	public function isReadable(array $proxyids) {
		if (empty($proxyids)) {
			return true;
		}

		$proxyids = array_unique($proxyids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'proxyids' => $proxyids,
			'countOutput' => true
		));

		return (count($proxyids) == $count);
	}

	/**
	 * Check if user has write permissions for proxy.
	 *
	 * @param array $proxyids
	 * @return bool
	 */
	public function isWritable(array $proxyids) {
		if (empty($proxyids)) {
			return true;
		}

		$proxyids = array_unique($proxyids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'proxyids' => $proxyids,
			'editable' => true,
			'countOutput' => true
		));

		return (count($proxyids) == $count);
	}

	/**
	 * Check permission for proxies.
	 * Only super admin have write access for proxies.
	 */
	protected function checkPermissions() {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only super admins can delete proxies.'));
		}
	}

	/**
	 * Check if proxy is used in discovery rule.
	 *
	 * @param array $proxies
	 */
	protected function checkUsedInDiscoveryRule(array $proxies) {
		$dRule = DBfetch(DBselect(
			'SELECT dr.druleid,dr.name,dr.proxy_hostid'.
					' FROM drules dr'.
					' WHERE '.dbConditionInt('dr.proxy_hostid', zbx_objectValues($proxies, 'proxyid')), 1));
		if ($dRule) {
			$proxy = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.$dRule['proxy_hostid']));

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Proxy "%1$s" is used by discovery rule "%2$s".', $proxy['host'], $dRule['name']));
		}
	}

	/**
	 * Check if proxy is used to monitor hosts.
	 *
	 * @param array $proxies
	 */
	protected function checkUsedForMonitoring(array $proxies) {
		$host = DBfetch(DBselect(
			'SELECT h.name,h.proxy_hostid'.
					' FROM hosts h'.
					' WHERE '.dbConditionInt('h.proxy_hostid', zbx_objectValues($proxies, 'proxyid')), 1));
		if ($host) {
			$proxy = DBfetch(DBselect('SELECT h.host FROM hosts h WHERE h.hostid='.$host['proxy_hostid']));
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host "%1$s" is monitored with proxy "%2$s".', $host['name'], $proxy['host']));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			if ($options['selectInterfaces'] !== null) {
				$sqlParts = $this->addQuerySelect('h.hostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$proxyIds = array_keys($result);

		// selectHosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$hosts = API::Host()->get(array(
				'output' => $this->outputExtend('hosts', array('hostid', 'proxy_hostid'), $options['selectHosts']),
				'nodeids' => $options['nodeids'],
				'proxyids' => $proxyIds,
				'preservekeys' => true
			));

			$relationMap = $this->createRelationMap($hosts, 'proxy_hostid', 'hostid');

			$hosts = $this->unsetExtraFields($hosts, array('proxy_hostid', 'hostid'), $options['selectHosts']);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding hostinterfaces
		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
			$interfaces = API::HostInterface()->get(array(
				'output' => $this->outputExtend('interface', array('interfaceid', 'hostid'), $options['selectInterfaces']),
				'nodeids' => $options['nodeids'],
				'hostids' => $proxyIds,
				'nopermissions' => true,
				'preservekeys' => true
			));

			$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');

			$interfaces = $this->unsetExtraFields($interfaces, array('hostid', 'interfaceid'), $options['selectInterfaces']);
			$result = $relationMap->mapMany($result, $interfaces, 'interfaces');
		}

		return $result;
	}
}
