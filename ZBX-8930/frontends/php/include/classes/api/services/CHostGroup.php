<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class containing methods for operations with host groups.
 *
 * @package API
 */
class CHostGroup extends CApiService {

	protected $tableName = 'groups';
	protected $tableAlias = 'g';
	protected $sortColumns = array('groupid', 'name');

	/**
	 * Get host groups.
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	public function get($params) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('groups' => 'g.groupid'),
			'from'		=> array('groups' => 'groups g'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'groupids'					=> null,
			'hostids'					=> null,
			'templateids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'maintenanceids'			=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'real_hosts'				=> null,
			'with_hosts_and_templates'	=> null,
			'with_items'				=> null,
			'with_simple_graph_items'	=> null,
			'with_monitored_items'		=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers'	=> null,
			'with_httptests'			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'with_applications'			=> null,
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
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'selectGroupDiscovery'		=> null,
			'selectDiscoveryRule'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $params);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM rights r'.
				' WHERE g.groupid=r.id'.
					' AND '.dbConditionInt('r.groupid', $userGroups).
				' GROUP BY r.id'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			$sqlParts['where']['groupid'] = dbConditionInt('g.groupid', $options['groupids']);
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

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.hostid', $options['hostids']);
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['gi'] = 'graphs_items gi';
			$sqlParts['from']['i'] = 'items i';
			$sqlParts['from']['hg'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['from']['maintenances_groups'] = 'maintenances_groups mg';
			$sqlParts['where'][] = dbConditionInt('mg.maintenanceid', $options['maintenanceids']);
			$sqlParts['where']['hmh'] = 'g.groupid=mg.groupid';
		}

		// monitored_hosts, real_hosts, templated_hosts, with_hosts_and_templates
		if (!is_null($options['monitored_hosts'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts h'.
					' WHERE hg.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_MONITORED.
					')';
		}
		elseif (!is_null($options['real_hosts'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where'][] = 'h.hostid=hg.hostid';
			$sqlParts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}
		elseif (!is_null($options['templated_hosts'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where'][] = 'h.hostid=hg.hostid';
			$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		}
		elseif (!is_null($options['with_hosts_and_templates'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where'][] = 'h.hostid=hg.hostid';
			$sqlParts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}

		// with_items, with_monitored_items, with_simple_graph_items
		if (!is_null($options['with_items'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_monitored_items'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,hosts h'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.hostid=h.hostid'.
						' AND h.status='.HOST_STATUS_MONITORED.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_simple_graph_items'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.value_type IN ('.ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64.')'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with_triggers, with_monitored_triggers
		if (!is_null($options['with_triggers'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,functions f,triggers t'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_monitored_triggers'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,hosts h,functions f,triggers t'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.hostid=h.hostid'.
						' AND i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND h.status='.HOST_STATUS_MONITORED.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND t.status='.TRIGGER_STATUS_ENABLED.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with_httptests, with_monitored_httptests
		if (!is_null($options['with_httptests'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM httptest ht'.
					' WHERE hg.hostid=ht.hostid'.
					')';
		}
		elseif (!is_null($options['with_monitored_httptests'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM httptest ht'.
					' WHERE hg.hostid=ht.hostid'.
						' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
					')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,graphs_items gi,graphs g'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.itemid=gi.itemid'.
						' AND gi.graphid=g.graphid'.
						' AND g.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		if (!is_null($options['with_applications'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'hg.hostid=a.hostid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('groups g', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('groups g', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($group = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $group;
				}
				else {
					$result = $group['rowscount'];
				}
			}
			else {
				$result[$group['groupid']] = $group;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Get host groups by name.
	 *
	 * @deprecated	As of version 2.4, use get method instead.
	 *
	 * @param array $hostgroupData
	 * @param array $hostgroupData['name']
	 *
	 * @return array
	 */
	public function getObjects(array $hostgroupData) {
		$this->deprecated('hostgroup.getobjects method is deprecated.');

		return $this->get(array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => $hostgroupData
		));
	}

	/**
	 * Check if host group exists.
	 *
	 * @deprecated	As of version 2.4, use get method instead.
	 *
	 * @param array	$object
	 *
	 * @return bool
	 */
	public function exists(array $object) {
		$this->deprecated('hostgroup.exists method is deprecated.');

		$hostGroup = $this->get(array(
			'output' => array('groupid'),
			'filter' => zbx_array_mintersect(array('name', 'groupid'), $object),
			'limit' => 1
		));

		return (bool) $hostGroup;
	}

	/**
	 * Create host groups.
	 *
	 * @param array $groups array with host group names
	 * @param array $groups['name']
	 *
	 * @return array
	 */
	public function create(array $groups) {
		$groups = zbx_toArray($groups);

		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create host groups.'));
		}

		foreach ($groups as $group) {
			if (!isset($group['name']) || zbx_empty($group['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Host group name cannot be empty.'));
			}
		}

		// check host name duplicates
		$collectionValidator = new CCollectionValidator(array(
			'uniqueField' => 'name',
			'messageDuplicate' => _('Host group "%1$s" already exists.')
		));
		$this->checkValidator($groups, $collectionValidator);

		$dbHostGroups = API::getApiService()->select($this->tableName(), array(
			'output' => array('name'),
			'filter' => array('name' => zbx_objectValues($groups, 'name')),
			'limit' => 1
		));

		if ($dbHostGroups) {
			$dbHostGroup = reset($dbHostGroups);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%1$s" already exists.', $dbHostGroup['name']));
		}

		$groupids = DB::insert('groups', $groups);

		return array('groupids' => $groupids);
	}

	/**
	 * Update host groups.
	 *
	 * @param array $groups
	 * @param array $groups[0]['name'], ...
	 * @param array $groups[0]['groupid'], ...
	 *
	 * @return boolean
	 */
	public function update(array $groups) {
		$groups = zbx_toArray($groups);
		$groupids = zbx_objectValues($groups, 'groupid');

		if (empty($groups)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// permissions
		$updGroups = $this->get(array(
			'groupids' => $groupids,
			'editable' => true,
			'output' => array('groupid', 'flags'),
			'preservekeys' => true
		));
		foreach ($groups as $group) {
			if (!isset($updGroups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// name duplicate check
		$groupsNames = $this->get(array(
			'filter' => array('name' => zbx_objectValues($groups, 'name')),
			'output' => array('groupid', 'name'),
			'editable' => true,
			'nopermissions' => true
		));
		$groupsNames = zbx_toHash($groupsNames, 'name');

		$updateDiscoveredValidator = new CUpdateDiscoveredValidator(array(
			'messageAllowed' => _('Cannot update a discovered host group.')
		));

		$update = array();
		foreach ($groups as $group) {
			if (isset($group['name'])) {
				if (zbx_empty($group['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Host group name cannot be empty.'));
				}

				// cannot update discovered host groups
				$this->checkPartialValidator($group, $updateDiscoveredValidator, $updGroups[$group['groupid']]);

				if (isset($groupsNames[$group['name']])
						&& !idcmp($groupsNames[$group['name']]['groupid'], $group['groupid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%1$s" already exists.', $group['name']));
				}

				$update[] = array(
					'values' => array('name' => $group['name']),
					'where' => array('groupid' => $group['groupid'])
				);
			}

			// prevents updating several groups with same name
			$groupsNames[$group['name']] = array('groupid' => $group['groupid']);
		}

		DB::update('groups', $update);

		return array('groupids' => $groupids);
	}

	/**
	 * Delete host groups.
	 *
	 * @param array $groupids
	 * @param bool 	$nopermissions
	 *
	 * @return array
	 */
	public function delete(array $groupids, $nopermissions = false) {
		if (empty($groupids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}
		sort($groupids);

		$delGroups = $this->get(array(
			'groupids' => $groupids,
			'editable' => true,
			'output' => array('groupid', 'name', 'internal'),
			'preservekeys' => true,
			'nopermissions' => $nopermissions
		));
		foreach ($groupids as $groupid) {
			if (!isset($delGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
			if ($delGroups[$groupid]['internal'] == ZBX_INTERNAL_GROUP) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host group "%1$s" is internal and can not be deleted.', $delGroups[$groupid]['name']));
			}
		}

		// check if a group is used in a group prototype
		$groupPrototype = DBFetch(DBselect(
			'SELECT groupid'.
			' FROM group_prototype gp'.
			' WHERE '.dbConditionInt('groupid', $groupids),
			1
		));
		if ($groupPrototype) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Group "%1$s" cannot be deleted, because it is used by a host prototype.',
					$delGroups[$groupPrototype['groupid']]['name']
				)
			);
		}

		$dltGroupids = getDeletableHostGroupIds($groupids);
		if (count($groupids) != count($dltGroupids)) {
			foreach ($groupids as $groupid) {
				if (isset($dltGroupids[$groupid])) {
					continue;
				}
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host group "%1$s" cannot be deleted, because some hosts depend on it.',
						$delGroups[$groupid]['name']
					)
				);
			}
		}

		$dbScripts = API::Script()->get(array(
			'groupids' => $groupids,
			'output' => array('scriptid', 'groupid'),
			'nopermissions' => true
		));

		if (!empty($dbScripts)) {
			foreach ($dbScripts as $script) {
				if ($script['groupid'] == 0) {
					continue;
				}
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host group "%1$s" cannot be deleted, because it is used in a global script.',
						$delGroups[$script['groupid']]['name']
					)
				);
			}
		}

		// delete screens items
		$resources = array(
			SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
			SCREEN_RESOURCE_HOSTS_INFO,
			SCREEN_RESOURCE_TRIGGERS_INFO,
			SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
			SCREEN_RESOURCE_DATA_OVERVIEW
		);
		DB::delete('screens_items', array(
			'resourceid' => $groupids,
			'resourcetype' => $resources
		));

		// delete sysmap element
		if (!empty($groupids)) {
			DB::delete('sysmaps_elements', array('elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP, 'elementid' => $groupids));
		}

		// disable actions
		// actions from conditions
		$actionids = array();
		$dbActions = DBselect(
			'SELECT DISTINCT c.actionid'.
			' FROM conditions c'.
			' WHERE c.conditiontype='.CONDITION_TYPE_HOST_GROUP.
				' AND '.dbConditionString('c.value', $groupids)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		// actions from operations
		$dbActions = DBselect(
			'SELECT DISTINCT o.actionid'.
			' FROM operations o,opgroup og'.
			' WHERE o.operationid=og.operationid'.
				' AND '.dbConditionInt('og.groupid', $groupids)
		);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if (!empty($actionids)) {
			$update = array();
			$update[] = array(
				'values' => array('status' => ACTION_STATUS_DISABLED),
				'where' => array('actionid' => $actionids)
			);
			DB::update('actions', $update);
		}

		// delete action conditions
		DB::delete('conditions', array(
			'conditiontype' => CONDITION_TYPE_HOST_GROUP,
			'value' => $groupids
		));

		// delete action operation commands
		$operationids = array();
		$dbOperations = DBselect(
			'SELECT DISTINCT og.operationid'.
			' FROM opgroup og'.
			' WHERE '.dbConditionInt('og.groupid', $groupids)
		);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}
		DB::delete('opgroup', array(
			'groupid' => $groupids
		));

		// delete empty operations
		$delOperationids = array();
		$dbOperations = DBselect(
			'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
				' AND NOT EXISTS (SELECT NULL FROM opgroup og WHERE o.operationid=og.operationid)'
		);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', array('operationid' => $delOperationids));

		DB::delete('groups', array('groupid' => $groupids));

		DB::delete('profiles', array(
			'idx' => 'web.dashconf.groups.groupids',
			'value_id' => $groupids
		));

		DB::delete('profiles', array(
			'idx' => 'web.dashconf.groups.hide.groupids',
			'value_id' => $groupids
		));

		// TODO: remove audit
		foreach ($groupids as $groupid) {
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST_GROUP, $groupid, $delGroups[$groupid]['name'], 'groups', null, null);
		}

		return array('groupids' => $groupids);
	}

	/**
	 * Add hosts to host groups. All hosts are added to all host groups.
	 *
	 * @param array $data
	 * @param array $data['groups']
	 * @param array $data['hosts']
	 * @param array $data['templates']
	 *
	 * @return boolean
	 */
	public function massAdd(array $data) {
		$groups = zbx_toArray($data['groups']);
		$groupids = zbx_objectValues($groups, 'groupid');

		$updGroups = $this->get(array(
			'output' => array('groupid'),
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		));
		foreach ($groups as $group) {
			if (!isset($updGroups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');
		$objectids = array_merge($hostids, $templateids);

		// check if any of the hosts are discovered
		$this->checkValidator($hostids, new CHostNormalValidator(array(
			'message' => _('Cannot update groups for discovered host "%1$s".')
		)));

		$linked = array();
		$linkedDb = DBselect(
			'SELECT hg.hostid,hg.groupid'.
			' FROM hosts_groups hg'.
			' WHERE '.dbConditionInt('hg.hostid', $objectids).
				' AND '.dbConditionInt('hg.groupid', $groupids)
		);
		while ($pair = DBfetch($linkedDb)) {
			$linked[$pair['groupid']][$pair['hostid']] = 1;
		}

		$insert = array();
		foreach ($groupids as $groupid) {
			foreach ($objectids as $hostid) {
				if (isset($linked[$groupid][$hostid])) {
					continue;
				}
				$insert[] = array('hostid' => $hostid, 'groupid' => $groupid);
			}
		}
		DB::insert('hosts_groups', $insert);
		return array('groupids' => $groupids);
	}

	/**
	 * Remove hosts from host groups.
	 *
	 * @param array $data
	 * @param array $data['groupids']
	 * @param array $data['hostids']
	 * @param array $data['templateids']
	 *
	 * @return boolean
	 */
	public function massRemove(array $data) {
		$groupids = zbx_toArray($data['groupids'], 'groupid');

		$updGroups = $this->get(array(
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true,
			'output' => array('groupid')
		));
		foreach ($groupids as $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
		$hostids = isset($data['hostids']) ? zbx_toArray($data['hostids']) : array();
		$templateids = isset($data['templateids']) ? zbx_toArray($data['templateids']) : array();

		// check if any of the hosts are discovered
		$this->checkValidator($hostids, new CHostNormalValidator(array(
			'message' => _('Cannot update groups for discovered host "%1$s".')
		)));

		$objectidsToUnlink = array_merge($hostids, $templateids);
		if (!empty($objectidsToUnlink)) {
			$unlinkable = getUnlinkableHostIds($groupids, $objectidsToUnlink);
			if (count($objectidsToUnlink) != count($unlinkable)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('One of the objects is left without a host group.'));
			}

			DB::delete('hosts_groups', array(
				'hostid' => $objectidsToUnlink,
				'groupid' => $groupids
			));
		}
		return array('groupids' => $groupids);
	}

	/**
	 * Update host groups with new hosts (rewrite).
	 *
	 * @param array $data
	 * @param array $data['groups']
	 * @param array $data['hosts']
	 * @param array $data['templates']
	 *
	 * @return array
	 */
	public function massUpdate(array $data) {
		$groupIds = array_unique(zbx_objectValues(zbx_toArray($data['groups']), 'groupid'));
		$hostIds = array_unique(zbx_objectValues(isset($data['hosts']) ? zbx_toArray($data['hosts']) : null, 'hostid'));
		$templateIds = array_unique(zbx_objectValues(isset($data['templates']) ? zbx_toArray($data['templates']) : null, 'templateid'));

		$workHostIds = array();

		// validate permission
		$allowedGroups = $this->get(array(
			'output' => array('groupid'),
			'groupids' => $groupIds,
			'editable' => true,
			'preservekeys' => true
		));
		foreach ($groupIds as $groupId) {
			if (!isset($allowedGroups[$groupId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// validate allowed hosts
		if (!empty($hostIds)) {
			if (!API::Host()->isWritable($hostIds)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// check if any of the hosts are discovered
			$this->checkValidator($hostIds, new CHostNormalValidator(array(
				'message' => _('Cannot update groups for discovered host "%1$s".')
			)));

			$workHostIds = zbx_toHash($hostIds);
		}

		// validate allowed templates
		if (!empty($templateIds)) {
			$allowedTemplates = API::Template()->get(array(
				'output' => array('templateid'),
				'templateids' => $templateIds,
				'editable' => true,
				'preservekeys' => true
			));
			foreach ($templateIds as $templateId) {
				if (!isset($allowedTemplates[$templateId])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				$workHostIds[$templateId] = $templateId;
			}
		}

		// get old records
		// skip discovered hosts
		$oldRecords = DBfetchArray(DBselect(
			'SELECT *'.
			' FROM hosts_groups hg,hosts h'.
			' WHERE '.dbConditionInt('hg.groupid', $groupIds).
				' AND hg.hostid=h.hostid'.
				' AND h.flags='.ZBX_FLAG_DISCOVERY_NORMAL
		));

		// calculate new records
		$replaceRecords = array();
		$newRecords = array();
		$hostIdsToValidate = array();

		foreach ($groupIds as $groupId) {
			$groupRecords = array();
			foreach ($oldRecords as $oldRecord) {
				if ($oldRecord['groupid'] == $groupId) {
					$groupRecords[] = $oldRecord;
				}
			}

			// find records for replace
			foreach ($groupRecords as $groupRecord) {
				if (isset($workHostIds[$groupRecord['hostid']])) {
					$replaceRecords[] = $groupRecord;
				}
			}

			// find records for create
			$groupHostIds = zbx_toHash(zbx_objectValues($groupRecords, 'hostid'));
			$newHostIds = array_diff($workHostIds, $groupHostIds);
			if ($newHostIds) {
				foreach ($newHostIds as $newHostId) {
					$newRecords[] = array(
						'groupid' => $groupId,
						'hostid' => $newHostId
					);
				}
			}

			// find records for delete
			$deleteHostIds = array_diff($groupHostIds, $workHostIds);
			if ($deleteHostIds) {
				foreach ($deleteHostIds as $deleteHostId) {
					$hostIdsToValidate[$deleteHostId] = $deleteHostId;
				}
			}
		}

		// validate hosts without groups
		if ($hostIdsToValidate) {
			$unlinkable = getUnlinkableHostIds($groupIds, $hostIdsToValidate);

			if (count($unlinkable) != count($hostIdsToValidate)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('One of the objects is left without a host group.'));
			}
		}

		// save
		DB::replace('hosts_groups', $oldRecords, array_merge($replaceRecords, $newRecords));

		return array('groupids' => $groupIds);
	}

	/**
	 * Check if user has read permissions for host groups.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isReadable(array $ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'groupids' => $ids,
			'countOutput' => true
		));
		return count($ids) == $count;
	}

	/**
	 * Check if user has write permissions for host groups.
	 *
	 * @param array $ids
	 *
	 * @return bool
	 */
	public function isWritable(array $ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'groupids' => $ids,
			'editable' => true,
			'countOutput' => true
		));

		return count($ids) == $count;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$groupIds = array_keys($result);
		sort($groupIds);

		// adding hosts
		if ($options['selectHosts'] !== null) {
			if ($options['selectHosts'] !== API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'groupid', 'hostid', 'hosts_groups');
				$hosts = API::Host()->get(array(
					'output' => $options['selectHosts'],
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'host');
				}
				$result = $relationMap->mapMany($result, $hosts, 'hosts', $options['limitSelects']);
			}
			else {
				$hosts = API::Host()->get(array(
					'groupids' => $groupIds,
					'countOutput' => true,
					'groupCount' => true
				));
				$hosts = zbx_toHash($hosts, 'groupid');
				foreach ($result as $groupid => $group) {
					if (isset($hosts[$groupid])) {
						$result[$groupid]['hosts'] = $hosts[$groupid]['rowscount'];
					}
					else {
						$result[$groupid]['hosts'] = 0;
					}
				}
			}
		}

		// adding templates
		if ($options['selectTemplates'] !== null) {
			if ($options['selectTemplates'] !== API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'groupid', 'hostid', 'hosts_groups');
				$hosts = API::Template()->get(array(
					'output' => $options['selectTemplates'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'host');
				}
				$result = $relationMap->mapMany($result, $hosts, 'templates', $options['limitSelects']);
			}
			else {
				$hosts = API::Template()->get(array(
					'groupids' => $groupIds,
					'countOutput' => true,
					'groupCount' => true
				));
				$hosts = zbx_toHash($hosts, 'groupid');
				foreach ($result as $groupid => $group) {
					if (isset($hosts[$groupid])) {
						$result[$groupid]['templates'] = $hosts[$groupid]['rowscount'];
					}
					else {
						$result[$groupid]['templates'] = 0;
					}
				}
			}
		}

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			// discovered items
			$discoveryRules = DBFetchArray(DBselect(
				'SELECT gd.groupid,hd.parent_itemid'.
					' FROM group_discovery gd,group_prototype gp,host_discovery hd'.
					' WHERE '.dbConditionInt('gd.groupid', $groupIds).
					' AND gd.parent_group_prototypeid=gp.group_prototypeid'.
					' AND gp.hostid=hd.hostid'
			));
			$relationMap = $this->createRelationMap($discoveryRules, 'groupid', 'parent_itemid');

			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding group discovery
		if ($options['selectGroupDiscovery'] !== null) {
			$groupDiscoveries = API::getApiService()->select('group_discovery', array(
				'output' => $this->outputExtend($options['selectGroupDiscovery'], array('groupid')),
				'filter' => array('groupid' => $groupIds),
				'preservekeys' => true
			));
			$relationMap = $this->createRelationMap($groupDiscoveries, 'groupid', 'groupid');

			$groupDiscoveries = $this->unsetExtraFields($groupDiscoveries, array('groupid'),
				$options['selectGroupDiscovery']
			);
			$result = $relationMap->mapOne($result, $groupDiscoveries, 'groupDiscovery');
		}

		return $result;
	}
}
