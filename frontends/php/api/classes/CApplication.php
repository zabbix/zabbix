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
 * API application class.
 */
class CApplication extends CZBXAPI {

	protected $tableName = 'applications';
	protected $tableAlias = 'a';
	protected $sortColumns = array('applicationid', 'name');

	/**
	* Get Applications data
	*
	* @param array $options
	* @param array $options['itemids']
	* @param array $options['hostids']
	* @param array $options['groupids']
	* @param array $options['triggerids']
	* @param array $options['applicationids']
	* @param boolean $options['status']
	* @param boolean $options['editable']
	* @param boolean $options['count']
	* @param string $options['pattern']
	* @param int $options['limit']
	* @param string $options['order']
	* @return array|int item data as array or false if error
	*/
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('apps' => 'a.applicationid'),
			'from'		=> array('applications' => 'applications a'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'itemids'					=> null,
			'applicationids'			=> null,
			'templated'					=> null,
			'editable'					=> null,
			'inherited' 				=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'exludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'expandData'				=> null,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE a.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.$permission.
					')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['ahg'] = 'a.hostid=hg.hostid';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['select']['hostid'] = 'a.hostid';
			$sqlParts['where']['hostid'] = dbConditionInt('a.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'a.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['select']['itemid'] = 'ia.itemid';
			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where'][] = dbConditionInt('ia.itemid', $options['itemids']);
			$sqlParts['where']['aia'] = 'a.applicationid=ia.applicationid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['select']['applicationid'] = 'a.applicationid';
			$sqlParts['where'][] = dbConditionInt('a.applicationid', $options['applicationids']);
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ah'] = 'a.hostid=h.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'a.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'a.templateid IS NULL';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('applications a', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('applications a', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		// output
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($application = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $application;
				}
				else {
					$result = $application['rowscount'];
				}
			}
			else {
				if (!isset($result[$application['applicationid']])) {
					$result[$application['applicationid']]= array();
				}

				// hostids
				if (isset($application['hostid']) && is_null($options['selectHosts'])) {
					if (!isset($result[$application['applicationid']]['hosts'])) {
						$result[$application['applicationid']]['hosts'] = array();
					}
					$result[$application['applicationid']]['hosts'][] = array('hostid' => $application['hostid']);
				}

				// itemids
				if (isset($application['itemid']) && is_null($options['selectItems'])) {
					if (!isset($result[$application['applicationid']]['items'])) {
						$result[$application['applicationid']]['items'] = array();
					}
					$result[$application['applicationid']]['items'][] = array('itemid' => $application['itemid']);
					unset($application['itemid']);
				}

				$result[$application['applicationid']] += $application;
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

	public function exists($object) {
		$keyFields = array(array('hostid', 'host'), 'name');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('applicationid'),
			'nopermissions' => 1,
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

	public function checkInput(&$applications, $method) {
		if (empty($applications)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

		// permissions
		if ($update || $delete) {
			$itemDbFields = array('applicationid' => null);
			$dbApplications = $this->get(array(
				'output' => API_OUTPUT_EXTEND,
				'applicationids' => zbx_objectValues($applications, 'applicationid'),
				'editable' => 1,
				'preservekeys' => 1
			));
		}
		else {
			$itemDbFields = array('name' => null, 'hostid' => null);
			$dbHosts = API::Host()->get(array(
				'output' => array('hostid', 'host', 'status'),
				'hostids' => zbx_objectValues($applications, 'hostid'),
				'templated_hosts' => 1,
				'editable' => 1,
				'preservekeys' => 1
			));
		}

		if ($update){
			$applications = $this->extendObjects($this->tableName(), $applications, array('name'));
		}

		foreach ($applications as &$application) {
			if (!check_db_fields($itemDbFields, $application)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function'));
			}

			// check permissions by hostid
			if ($create) {
				if (!isset($dbHosts[$application['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
			}

			// check permissions by applicationid
			if ($delete || $update) {
				if (!isset($dbApplications[$application['applicationid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
			}

			// check for "templateid", because it is not allowed
			if (array_key_exists('templateid', $application)) {
				if ($update) {
					$error = _s('Cannot update "templateid" for application "%1$s".', $application['name']);
				}
				else {
					$error = _s('Cannot set "templateid" for application "%1$s".', $application['name']);
				}
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			// check on operating with templated applications
			if ($delete || $update) {
				if ($dbApplications[$application['applicationid']]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot interact templated applications');
				}
			}

			if ($update) {
				if (!isset($application['hostid'])) {
					$application['hostid'] = $dbApplications[$application['applicationid']]['hostid'];
				}
			}

			// check existence
			if ($update || $create) {
				$applicationsExists = $this->get(array(
					'output' => API_OUTPUT_EXTEND,
					'filter' => array(
						'hostid' => $application['hostid'],
						'name' => $application['name']
					),
					'nopermissions' => 1
				));
				foreach ($applicationsExists as $applicationExists) {
					if (!$update || (bccomp($applicationExists['applicationid'], $application['applicationid']) != 0)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Application "%1$s" already exists.', $application['name']));
					}
				}
			}
		}
		unset($application);
	}

	/**
	 * Create new applications.
	 *
	 * @param array $applications

	 * @return array
	 */
	public function create(array $applications) {
		$applications = zbx_toArray($applications);
		$this->checkInput($applications, __FUNCTION__);

		$appManager = new CApplicationManager();
		$applications = $appManager->create($applications);
		$appManager->inherit($applications);

		return array('applicationids' => zbx_objectValues($applications, 'applicationid'));
	}

	/**
	 * Update applications.
	 *
	 * @param array $applications
	 *
	 * @return array
	 */
	public function update(array $applications) {
		$applications = zbx_toArray($applications);
		$this->checkInput($applications, __FUNCTION__);

		$appManager = new CApplicationManager();
		$appManager->update($applications);
		$appManager->inherit($applications);

		return array('applicationids' => zbx_objectValues($applications, 'applicationid'));
	}

	/**
	 * Delete Applications
	 *
	 * @param array $applicationids
	 * @return array
	 */
	public function delete($applicationids, $nopermissions = false) {
		$applicationids = zbx_toArray($applicationids);
		$delApplicationIds = $applicationids;
		// TODO: remove $nopermissions hack
		$options = array(
			'applicationids' => $applicationids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'selectHosts' => array('name', 'hostid')
		);
		$delApplications = $this->get($options);

		if (!$nopermissions) {
			foreach ($applicationids as $applicationid) {
				if (!isset($delApplications[$applicationid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				if ($delApplications[$applicationid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, 'Cannot delete templated application.');
				}
			}
		}

		$parentApplicationids = $applicationids;
		$childApplicationids = array();
		do {
			$dbApplications = DBselect('SELECT a.applicationid FROM applications a WHERE '.dbConditionInt('a.templateid', $parentApplicationids));
			$parentApplicationids = array();
			while ($dbApplication = DBfetch($dbApplications)) {
				$parentApplicationids[] = $dbApplication['applicationid'];
				$childApplicationids[$dbApplication['applicationid']] = $dbApplication['applicationid'];
			}
		} while (!empty($parentApplicationids));

		$options = array(
			'applicationids' => $childApplicationids,
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => array('name', 'hostid')
		);
		$delApplicationChilds = $this->get($options);
		$delApplications = zbx_array_merge($delApplications, $delApplicationChilds);
		$applicationids = array_merge($applicationids, $childApplicationids);

		// check if app is used by web scenario
		$sql = 'SELECT ht.name,ht.applicationid'.
				' FROM httptest ht'.
				' WHERE '.dbConditionInt('ht.applicationid', $applicationids);
		$res = DBselect($sql);
		if ($info = DBfetch($res)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Application "%1$s" used by scenario "%2$s" and can\'t be deleted.', $delApplications[$info['applicationid']]['name'], $info['name']));
		}

		DB::delete('applications', array('applicationid' => $applicationids));

		// TODO: remove info from API
		foreach ($delApplications as $delApplication) {
			$host = reset($delApplication['hosts']);
			info(_s('Deleted: Application "%1$s" on "%2$s".', $delApplication['name'], $host['name']));
		}
		return array('applicationids' => $delApplicationIds);
	}

	/**
	 * Add Items to applications
	 *
	 * @param array $data
	 * @param array $data['applications']
	 * @param array $data['items']
	 * @return boolean
	 */
	public function massAdd($data) {
		if (empty($data['applications'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$applications = zbx_toArray($data['applications']);
		$items = zbx_toArray($data['items']);
		$applicationids = zbx_objectValues($applications, 'applicationid');
		$itemids = zbx_objectValues($items, 'itemid');

		// validate permissions
		$appOptions = array(
			'applicationids' => $applicationids,
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$allowedApplications = $this->get($appOptions);
		foreach ($applications as $application) {
			if (!isset($allowedApplications[$application['applicationid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$allowedItems = API::Item()->get(array(
			'itemids' => $itemids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'filter' => array(
				'flags' => array(
					ZBX_FLAG_DISCOVERY_NORMAL,
					ZBX_FLAG_DISCOVERY_CHILD,
					ZBX_FLAG_DISCOVERY_CREATED
				)
			)
		));

		foreach ($items as $num => $item) {
			if (!isset($allowedItems[$item['itemid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$linkedDb = DBselect(
			'SELECT ia.itemid,ia.applicationid'.
			' FROM items_applications ia'.
			' WHERE '.dbConditionInt('ia.itemid', $itemids).
				' AND '.dbConditionInt('ia.applicationid', $applicationids)
		);
		while ($pair = DBfetch($linkedDb)) {
			$linked[$pair['applicationid']] = array($pair['itemid'] => $pair['itemid']);
		}
		$appsInsert = array();
		foreach ($applicationids as $applicationid) {
			foreach ($itemids as $inum => $itemid) {
				if (isset($linked[$applicationid]) && isset($linked[$applicationid][$itemid])) {
					continue;
				}
				$appsInsert[] = array(
					'itemid' => $itemid,
					'applicationid' => $applicationid
				);
			}
		}
		DB::insert('items_applications', $appsInsert);

		foreach ($itemids as $inum => $itemid) {
			$dbChilds = DBselect('SELECT i.itemid,i.hostid FROM items i WHERE i.templateid='.$itemid);
			while ($child = DBfetch($dbChilds)) {
				$dbApps = DBselect(
					'SELECT a1.applicationid'.
					' FROM applications a1,applications a2'.
					' WHERE a1.name=a2.name'.
						' AND a1.hostid='.$child['hostid'].
						' AND '.dbConditionInt('a2.applicationid', $applicationids)
				);
				$childApplications = array();
				while ($app = DBfetch($dbApps)) {
					$childApplications[] = $app;
				}
				$result = $this->massAdd(array('items' => $child, 'applications' => $childApplications));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot add items.');
				}
			}
		}
		return array('applicationids'=> $applicationids);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['countOutput'] === null) {
			// expandData
			if (!is_null($options['expandData'])) {
				$sqlParts['select']['host'] = 'h.host';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['ah'] = 'a.hostid=h.hostid';
			}

			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('a.hostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'applicationid', 'hostid');
			$hosts = API::Host()->get(array(
				'output' => $options['selectHosts'],
				'hostids' => $relationMap->getRelatedIds(),
				'nopermissions' => 1,
				'templated_hosts' => true,
				'preservekeys' => 1
			));
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding items
		if ($options['selectItems'] !== null && $options['selectItems'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'applicationid', 'itemid', 'items_applications');
			$items = API::Item()->get(array(
				'output' => $options['selectItems'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			));
			$result = $relationMap->mapMany($result, $items, 'items');
		}

		return $result;
	}


}
