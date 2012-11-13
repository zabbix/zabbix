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
?>
<?php
/**
 * @package API
 */
class CApplication extends CZBXAPI {

	protected $tableName = 'applications';

	protected $tableAlias = 'a';

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
		$sortColumns = array('applicationid', 'name');
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

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
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = 'hg.hostid=a.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS('.
								' SELECT hgg.groupid'.
								' FROM hosts_groups hgg,rights rr,users_groups gg'.
								' WHERE hgg.hostid=hg.hostid'.
									' AND rr.id=hgg.groupid'.
									' AND rr.groupid=gg.usrgrpid'.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['groupid'] = 'hg.groupid';
			}
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['ahg'] = 'a.hostid=hg.hostid';
			$sqlParts['where'][] = DBcondition('hg.groupid', $options['groupids']);

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

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['hostid'] = 'a.hostid';
			}
			$sqlParts['where']['hostid'] = DBcondition('a.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['hostid'] = 'a.hostid';
			}
		}

		// expandData
		if (!is_null($options['expandData'])) {
			$sqlParts['select']['host'] = 'h.host';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['ah'] = 'a.hostid=h.hostid';
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['itemid'] = 'ia.itemid';
			}
			$sqlParts['from']['items_applications'] = 'items_applications ia';
			$sqlParts['where'][] = DBcondition('ia.itemid', $options['itemids']);
			$sqlParts['where']['aia'] = 'a.applicationid=ia.applicationid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['applicationid'] = 'a.applicationid';
			}
			$sqlParts['where'][] = DBcondition('a.applicationid', $options['applicationids']);
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

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['apps'] = 'a.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT a.applicationid) as rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('applications a', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('applications a', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'a');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$applicationids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['group'])) {
			$sqlWhere .= ' GROUP BY '.implode(',', $sqlParts['group']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('a.applicationid', $nodeids).
					$sqlWhere.
				$sqlGroup.
				$sqlOrder;
		$res = DBselect($sql, $sqlLimit);
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
				$applicationids[$application['applicationid']] = $application['applicationid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$application['applicationid']] = array('applicationid' => $application['applicationid']);
				}
				else {
					if (!isset($result[$application['applicationid']])) {
						$result[$application['applicationid']]= array();
					}

					if (!is_null($options['selectHosts']) && !isset($result[$application['applicationid']]['hosts'])) {
						$result[$application['applicationid']]['hosts'] = array();
					}

					if (!is_null($options['selectItems']) && !isset($result[$application['applicationid']]['items'])) {
						$result[$application['applicationid']]['items'] = array();
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
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding objects
		// adding hosts
		if ($options['selectHosts'] !== null && (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs))) {
			$objParams = array(
				'output' => $options['selectHosts'],
				'applicationids' => $applicationids,
				'nopermissions' => 1,
				'templated_hosts' => true,
				'preservekeys' => 1
			);
			$hosts = API::Host()->get($objParams);
			foreach ($hosts as $hostid => $host) {
				$iapplications = $host['applications'];
				unset($host['applications']);
				foreach ($iapplications as $application) {
					$result[$application['applicationid']]['hosts'][] = $host;
				}
			}
		}

		// adding objects
		// adding items
		if (!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselectsAllowedOutputs)) {
			$objParams = array(
				'output' => $options['selectItems'],
				'applicationids' => $applicationids,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$items = API::Item()->get($objParams);
			foreach ($items as $itemid => $item) {
				$iapplications = $item['applications'];
				unset($item['applications']);
				foreach ($iapplications as $application) {
					$result[$application['applicationid']]['items'][] = $item;
				}
			}
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
			'output' => API_OUTPUT_SHORTEN,
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
	 * Add Applications
	 *
	 * @param array $applications
	 * @param array $app_data['name']
	 * @param array $app_data['hostid']
	 * @return array
	 */
	public function create($applications) {
		$applications = zbx_toArray($applications);
		$this->checkInput($applications, __FUNCTION__);
		$this->createReal($applications);
		$this->inherit($applications);
		return array('applicationids' => zbx_objectValues($applications, 'applicationid'));
	}

	/**
	 * Update Applications
	 *
	 * @param array $applications
	 * @param array $app_data['name']
	 * @param array $app_data['hostid']
	 * @return array
	 */
	public function update($applications) {
		$applications = zbx_toArray($applications);
		$this->checkInput($applications, __FUNCTION__);
		$this->updateReal($applications);
		$this->inherit($applications);
		return array('applicationids' => zbx_objectValues($applications, 'applicationids'));
	}

	protected function createReal(&$applications) {
		if (empty($applications)) {
			return true;
		}
		$applicationids = DB::insert('applications', $applications);

		foreach ($applications as $anum => $application) {
			$applications[$anum]['applicationid'] = $applicationids[$anum];
		}

		// TODO: REMOVE info
		$applicationsCreated = $this->get(array(
			'applicationids' => $applicationids,
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'nopermissions' => 1
		));
		foreach ($applicationsCreated as $applicationCreated) {
			$host = reset($applicationCreated['hosts']);
			info(_s('Created: Application "%1$s" on "%2$s".', $applicationCreated['name'], $host['name']));
		}
	}

	protected function updateReal($applications) {
		$update = array();
		foreach ($applications as $application) {
			$update[] = array(
				'values' => $application,
				'where' => array('applicationid' => $application['applicationid'])
			);
		}
		DB::update('applications', $update);

		// TODO: REMOVE info
		$applicationsUpd = $this->get(array(
			'applicationids' => zbx_objectValues($applications, 'applicationid'),
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
		));
		foreach ($applicationsUpd as $applicationUpd) {
			$host = reset($applicationUpd['hosts']);
			info(_s('Updated: Application "%1$s" on "%2$s".', $applicationUpd['name'], $host['name']));
		}
	}

	/**
	 * Delete Applications
	 *
	 * @param array $applicationids
	 * @return array
	 */
	public function delete($applicationids, $nopermissions = false) {
		$applicationids = zbx_toArray($applicationids);

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
			$dbApplications = DBselect('SELECT a.applicationid FROM applications a WHERE '.DBcondition('a.templateid', $parentApplicationids));
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
				' WHERE '.DBcondition('ht.applicationid', $applicationids);
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
		return array('applicationids' => $applicationids);
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
			'filter' => array('flags' => null)
		));

		foreach ($items as $num => $item) {
			if (!isset($allowedItems[$item['itemid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$linkedDb = DBselect(
			'SELECT ia.itemid, ia.applicationid'.
			' FROM items_applications ia'.
			' WHERE '.DBcondition('ia.itemid', $itemids).
				' AND '.DBcondition('ia.applicationid', $applicationids)
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
						' AND '.DBcondition('a2.applicationid', $applicationids)
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

	protected function inherit($applications, $hostids = null) {
		if (empty($applications)) {
			return $applications;
		}
		$applications = zbx_toHash($applications, 'applicationid');

		$chdHosts = API::Host()->get(array(
			'output' => array('hostid', 'host'),
			'templateids' => zbx_objectValues($applications, 'hostid'),
			'hostids' => $hostids,
			'preservekeys' => 1,
			'nopermissions' => 1,
			'templated_hosts' => 1
		));
		if (empty($chdHosts)) {
			return true;
		}

		$insertApplications = array();
		$updateApplications = array();

		foreach ($chdHosts as $hostid => $host) {
			$templateids = zbx_toHash($host['templates'], 'templateid');

			// skip applications not from parent templates of current host
			$parentApplications = array();
			foreach ($applications as $parentApplicationId => $parentApplication) {
				if (isset($templateids[$parentApplication['hostid']])) {
					$parentApplications[$parentApplicationId] = $parentApplication;
				}
			}

			// check existing items to decide insert or update
			$exApplications = $this->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $hostid,
				'preservekeys' => true,
				'nopermissions' => true
			));

			$exApplicationsNames = zbx_toHash($exApplications, 'name');
			$exApplicationsTpl = zbx_toHash($exApplications, 'templateid');

			foreach ($parentApplications as $parentApplicationId => $parentApplication) {
				$exApplication = null;

				// update by templateid
				if (isset($exApplicationsTpl[$parentApplicationId])) {
					$exApplication = $exApplicationsTpl[$parentApplicationId];
				}

				// update by name
				if (isset($parentApplication['name']) && isset($exApplicationsNames[$parentApplication['name']])) {
					$exApplication = $exApplicationsNames[$parentApplication['name']];
					if ($exApplication['templateid'] > 0 && !idcmp($exApplication['templateid'], $parentApplication['applicationid'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Application "%1$s" already exists for host "%2$s".', $exApplication['name'], $host['name']));
					}
				}

				$newApplication = $parentApplication;
				$newApplication['hostid'] = $host['hostid'];
				$newApplication['templateid'] = $parentApplication['applicationid'];

				if ($exApplication) {
					$newApplication['applicationid'] = $exApplication['applicationid'];
					$updateApplications[] = $newApplication;
				}
				else {
					$insertApplications[] = $newApplication;
				}
			}
		}
		$this->createReal($insertApplications);
		$this->updateReal($updateApplications);
		$inheritedApplications = array_merge($insertApplications, $updateApplications);
		$this->inherit($inheritedApplications);
		return true;
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$options = array(
			'hostids' => $data['hostids'],
			'editable' => 1,
			'preservekeys' => 1,
			'templated_hosts' => 1,
			'output' => API_OUTPUT_SHORTEN
		);
		$allowedHosts = API::Host()->get($options);
		foreach ($data['hostids'] as $hostid) {
			if (!isset($allowedHosts[$hostid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}
		$options = array(
			'templateids' => $data['templateids'],
			'preservekeys' => 1,
			'output' => API_OUTPUT_SHORTEN
		);
		$allowedTemplates = API::Template()->get($options);
		foreach ($data['templateids'] as $templateid) {
			if (!isset($allowedTemplates[$templateid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		$options = array(
			'hostids' => $data['templateids'],
			'preservekeys' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$applications = $this->get($options);
		$this->inherit($applications, $data['hostids']);

		return true;
	}
}
?>
