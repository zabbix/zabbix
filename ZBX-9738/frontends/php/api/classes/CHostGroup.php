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
 * @package API
 */
class CHostGroup extends CZBXAPI {

	protected $tableName = 'groups';
	protected $tableAlias = 'g';

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

		// allowed columns for sorting
		$sortColumns = array('groupid', 'name');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('groups' => 'g.groupid'),
			'from'		=> array('groups' => 'groups g'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'templateids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'maintenanceids'			=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'real_hosts'				=> null,
			'not_proxy_hosts'			=> null,
			'with_hosts_and_templates'	=> null,
			'with_items'				=> null,
			'with_simple_graph_items'	=> null,
			'with_monitored_items'		=> null,
			'with_historical_items'		=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $params);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['groups']);

			$dbTable = DB::getSchema('groups');
			$sqlParts['select']['groupid'] = 'g.groupid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'g.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM rights r'.
				' WHERE g.groupid=r.id'.
					' AND '.dbConditionInt('r.groupid', $userGroups).
				' GROUP BY r.id'.
				' HAVING MIN(r.permission)>='.$permission.
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

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'hg.hostid';
			}
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.hostid', $options['hostids']);
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['triggerid'] = 'f.triggerid';
			}
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

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['graphid'] = 'gi.graphid';
			}
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
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['maintenanceid'] = 'mg.maintenanceid';
			}
			$sqlParts['from']['maintenances_groups'] = 'maintenances_groups mg';
			$sqlParts['where'][] = dbConditionInt('mg.maintenanceid', $options['maintenanceids']);
			$sqlParts['where']['hmh'] = 'g.groupid=mg.groupid';
		}

		// monitored_hosts, real_hosts, templated_hosts, not_proxy_hosts, with_hosts_and_templates
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
			$sqlParts['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}
		elseif (!is_null($options['templated_hosts'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where'][] = 'h.hostid=hg.hostid';
			$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
		}
		elseif (!is_null($options['not_proxy_hosts'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where'][] = 'h.hostid=hg.hostid';
			$sqlParts['where'][] = 'h.status NOT IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')';
		}
		elseif (!is_null($options['with_hosts_and_templates'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hgg'] = 'hg.groupid=g.groupid';
			$sqlParts['where'][] = 'h.hostid=hg.hostid';
			$sqlParts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}

		// with_items, with_monitored_items, with_historical_items, with_simple_graph_items
		if (!is_null($options['with_items'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE hg.hostid=i.hostid'.
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
					')';
		}
		elseif (!is_null($options['with_historical_items'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.status IN ('.ITEM_STATUS_ACTIVE.','.ITEM_STATUS_NOTSUPPORTED.')'.
						' AND i.lastvalue IS NOT NULL'.
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
					' FROM items i,functions f'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
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
					')';
		}

		// with_httptests, with_monitored_httptests
		if (!is_null($options['with_httptests'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM applications a,httptest ht'.
					' WHERE hg.hostid=a.hostid'.
						' AND a.applicationid=ht.applicationid'.
					')';
		}
		elseif (!is_null($options['with_monitored_httptests'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts h,applications a,httptest ht'.
					' WHERE hg.hostid=h.hostid'.
						' AND hg.hostid=a.hostid'.
						' AND a.applicationid=ht.applicationid'.
						' AND h.status='.HOST_STATUS_MONITORED.
						' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
					')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,graphs_items gi'.
					' WHERE hg.hostid=i.hostid'.
						' AND i.itemid=gi.itemid'.
					')';
		}

		if (!is_null($options['with_applications'])) {
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where']['hgg'] = 'g.groupid=hg.groupid';
			$sqlParts['where'][] = 'hg.hostid=a.hostid';
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['groups'] = 'g.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT g.groupid) AS rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('groups g', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('groups g', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'g');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$groupids = array();

		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
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
				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$group['groupid']] = array('groupid' => $group['groupid']);
				}
				else {
					$groupids[$group['groupid']] = $group['groupid'];

					if (!isset($result[$group['groupid']])) {
						$result[$group['groupid']] = array();
					}
					if (!is_null($options['selectTemplates']) && !isset($result[$group['groupid']]['templates'])) {
						$result[$group['groupid']]['templates'] = array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$group['groupid']]['hosts'])) {
						$result[$group['groupid']]['hosts'] = array();
					}

					// hostids
					if (isset($group['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$group['groupid']]['hosts'])) {
							$result[$group['groupid']]['hosts'] = array();
						}
						$result[$group['groupid']]['hosts'][] = array('hostid' => $group['hostid']);
						unset($group['hostid']);
					}

					// graphids
					if (isset($group['graphid'])) {
						if (!isset($result[$group['groupid']]['graphs'])) {
							$result[$group['groupid']]['graphs'] = array();
						}
						$result[$group['groupid']]['graphs'][] = array('graphid' => $group['graphid']);
						unset($group['graphid']);
					}

					// maintenanceids
					if (isset($group['maintenanceid'])) {
						if (!isset($result[$group['groupid']]['maintenanceid'])) {
							$result[$group['groupid']]['maintenances'] = array();
						}
						$result[$group['groupid']]['maintenances'][] = array('maintenanceid' => $group['maintenanceid']);
						unset($group['maintenanceid']);
					}

					// triggerids
					if (isset($group['triggerid'])) {
						if (!isset($result[$group['groupid']]['triggers'])) {
							$result[$group['groupid']]['triggers'] = array();
						}
						$result[$group['groupid']]['triggers'][] = array('triggerid' => $group['triggerid']);
						unset($group['triggerid']);
					}
					$result[$group['groupid']] += $group;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding hosts
		if (!is_null($options['selectHosts'])) {
			$objParams = array(
				'nodeids' => $options['nodeids'],
				'groupids' => $groupids,
				'preservekeys' => true
			);

			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectHosts'];
				$hosts = API::Host()->get($objParams);

				if (!is_null($options['limitSelects'])) {
					order_result($hosts, 'host');
				}

				$count = array();
				foreach ($hosts as $hostid => $host) {
					$hgroups = $host['groups'];
					unset($host['groups']);
					foreach ($hgroups as $group) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$group['groupid']])) {
								$count[$group['groupid']] = 0;
							}
							$count[$group['groupid']]++;

							if ($count[$group['groupid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$group['groupid']]['hosts'][] = $hosts[$hostid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectHosts']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$hosts = API::Host()->get($objParams);
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
		if (!is_null($options['selectTemplates'])) {
			$objParams = array(
				'nodeids' => $options['nodeids'],
				'groupids' => $groupids,
				'preservekeys' => true
			);

			if (is_array($options['selectTemplates']) || str_in_array($options['selectTemplates'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectTemplates'];
				$templates = API::Template()->get($objParams);
				if (!is_null($options['limitSelects'])) {
					order_result($templates, 'host');
				}

				$count = array();
				foreach ($templates as $templateid => $template) {
					$hgroups = $template['groups'];
					unset($template['groups']);

					foreach ($hgroups as $group) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$group['groupid']])) {
								$count[$group['groupid']] = 0;
							}
							$count[$group['groupid']]++;

							if ($count[$group['groupid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$group['groupid']]['templates'][] = $templates[$templateid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectTemplates']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;

				$templates = API::Template()->get($objParams);
				$templates = zbx_toHash($templates, 'groupid');

				foreach ($result as $groupid => $group) {
					if (isset($templates[$groupid])) {
						$result[$groupid]['templates'] = $templates[$groupid]['rowscount'];
					}
					else {
						$result[$groupid]['templates'] = 0;
					}
				}
			}
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Get host group id by name.
	 *
	 * @param array $hostgroupData
	 * @param array $hostgroupData['name']
	 *
	 * @return string|boolean host group id or false if error
	 */
	public function getObjects($hostgroupData) {
		$options = array(
			'filter' => $hostgroupData,
			'output' => API_OUTPUT_EXTEND
		);

		if (isset($hostgroupData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($hostgroupData['node']);
		}
		elseif (isset($hostgroupData['nodeids'])) {
			$options['nodeids'] = $hostgroupData['nodeids'];
		}
		else {
			$options['nodeids'] = get_current_nodeid(false);
		}
		$result = $this->get($options);
		return $result;
	}

	public function exists($object) {
		$keyFields = array('name', 'groupid');

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
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
			if (empty($group['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot create group without name.'));
			}
			if ($this->exists(array('name' => $group['name']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%1$s" already exists.', $group['name']));
			}
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
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		));
		foreach ($groups as $group) {
			if (!isset($updGroups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
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

		$update = array();
		foreach ($groups as $group) {
			if (isset($group['name'])
				&& isset($groupsNames[$group['name']])
				&& !idcmp($groupsNames[$group['name']]['groupid'], $group['groupid'])
			) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group "%1$s" already exists.', $group['name']));
			}

			// prevents updating several groups with same name
			$groupsNames[$group['name']] = array('groupid' => $group['groupid']);

			$update[] = array(
				'values' => array('name' => $group['name']),
				'where' => array('groupid' => $group['groupid'])
			);
		}

		DB::update('groups', $update);

		return array('groupids' => $groupids);
	}

	/**
	 * Delete host groups.
	 *
	 * @param array $groupids
	 *
	 * @return boolean
	 */
	public function delete($groupids) {
		if (empty($groupids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}
		$groupids = zbx_toArray($groupids);

		$options = array(
			'groupids' => $groupids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		);
		$delGroups = $this->get($options);
		foreach ($groupids as $groupid) {
			if (!isset($delGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
			if ($delGroups[$groupid]['internal'] == ZBX_INTERNAL_GROUP) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group "%1$s" is internal and can not be deleted.', $delGroups[$groupid]['name']));
			}
		}

		$dltGroupids = getDeletableHostGroups($groupids);
		if (count($groupids) != count($dltGroupids)) {
			foreach ($groupids as $groupid) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Group "%s" cannot be deleted, because some hosts depend on it.', $delGroups[$groupid]['name']));
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
					_s('Group "%s" cannot be deleted, because it is used in a global script.', $delGroups[$script['groupid']]['name']));
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

		DB::delete('operations', array(
			'operationid' => $delOperationids,
		));

		// host groups
		DB::delete('groups', array(
			'groupid' => $groupids
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
			'groupids' => $groupids,
			'editable' => true,
			'preservekeys' => true
		));
		foreach ($groups as $group) {
			if (!isset($updGroups[$group['groupid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : null;
		$templateids = is_null($templates) ? array() : zbx_objectValues($templates, 'templateid');
		$objectids = array_merge($hostids, $templateids);

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
			'output' => API_OUTPUT_SHORTEN
		));
		foreach ($groupids as $groupid) {
			if (!isset($updGroups[$groupid])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}
		$hostids = isset($data['hostids']) ? zbx_toArray($data['hostids']) : array();
		$templateids = isset($data['templateids']) ? zbx_toArray($data['templateids']) : array();
		$objectidsToUnlink = array_merge($hostids, $templateids);
		if (!empty($objectidsToUnlink)) {
			$unlinkable = getUnlinkableHosts($groupids, $objectidsToUnlink);
			if (count($objectidsToUnlink) != count($unlinkable)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'One of the objects is left without host group.');
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
			'groupids' => $groupIds,
			'editable' => true,
			'preservekeys' => true
		));
		foreach ($groupIds as $groupId) {
			if (!isset($allowedGroups[$groupId])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		// validate allowed hosts
		if (!empty($hostIds)) {
			$allowedHosts = API::Host()->get(array(
				'hostids' => $hostIds,
				'editable' => true,
				'preservekeys' => true
			));
			foreach ($hostIds as $hostId) {
				if (!isset($allowedHosts[$hostId])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
				}

				$workHostIds[$hostId] = $hostId;
			}
		}

		// validate allowed templates
		if (!empty($templateIds)) {
			$allowedTemplates = API::Template()->get(array(
				'templateids' => $templateIds,
				'editable' => true,
				'preservekeys' => true
			));
			foreach ($templateIds as $templateId) {
				if (!isset($allowedTemplates[$templateId])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
				}

				$workHostIds[$templateId] = $templateId;
			}
		}

		// get old records
		$oldRecords = DBfetchArray(DBselect(
			'SELECT *'.
			' FROM hosts_groups hg'.
			' WHERE '.dbConditionInt('hg.groupid', $groupIds)
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
			$unlinkable = getUnlinkableHosts($groupIds, $hostIdsToValidate);

			if (count($unlinkable) != count($hostIdsToValidate)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'One of the objects is left without host group.');
			}
		}

		// save
		DB::replace('hosts_groups', $oldRecords, array_merge($replaceRecords, $newRecords));

		return array('groupids' => $groupIds);
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'groupids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));
		return count($ids) == $count;
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'groupids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return count($ids) == $count;
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific ids are given
		if ($options['groupids'] === null &&
				$options['hostids'] === null &&
				$options['templateids'] === null &&
				$options['graphids'] === null &&
				$options['triggerids'] === null) {

			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}
}
