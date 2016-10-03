<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Class containing methods for operations with hosts.
 *
 * @package API
 */
class CHost extends CHostGeneral {

	protected $sortColumns = array('hostid', 'host', 'name', 'status');

	/**
	 * Get host data.
	 *
	 * @param array         $options
	 * @param array         $options['nodeids']                  Node IDs
	 * @param array         $options['groupids']                 HostGroup IDs
	 * @param array         $options['hostids']                  Host IDs
	 * @param boolean       $options['monitored_hosts']          only monitored Hosts
	 * @param boolean       $options['templated_hosts']          include templates in result
	 * @param boolean       $options['with_items']               only with items
	 * @param boolean       $options['with_monitored_items']     only with monitored items
	 * @param boolean       $options['with_triggers']            only with triggers
	 * @param boolean       $options['with_monitored_triggers']  only with monitored triggers
	 * @param boolean       $options['with_httptests']           only with http tests
	 * @param boolean       $options['with_monitored_httptests'] only with monitored http tests
	 * @param boolean       $options['with_graphs']              only with graphs
	 * @param boolean       $options['editable']                 only with read-write permission. Ignored for SuperAdmins
	 * @param boolean       $options['selectGroups']             select HostGroups
	 * @param boolean       $options['selectItems']              select Items
	 * @param boolean       $options['selectTriggers']           select Triggers
	 * @param boolean       $options['selectGraphs']             select Graphs
	 * @param boolean       $options['selectApplications']       select Applications
	 * @param boolean       $options['selectMacros']             select Macros
	 * @param boolean|array $options['selectInventory']          select Inventory
	 * @param boolean       $options['withInventory']            select only hosts with inventory
	 * @param int           $options['count']                    count Hosts, returned column name is rowscount
	 * @param string        $options['pattern']                  search hosts by pattern in Host name
	 * @param string        $options['extendPattern']            search hosts by pattern in Host name, ip and DNS
	 * @param int           $options['limit']                    limit selection
	 * @param string        $options['sortfield']                field to sort by
	 * @param string        $options['sortorder']                sort order
	 *
	 * @return array|boolean Host data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = array(
			'select'	=> array('hosts' => 'h.hostid'),
			'from'		=> array('hosts' => 'hosts h'),
			'where'		=> array('flags' => 'h.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'templateids'				=> null,
			'interfaceids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'maintenanceids'			=> null,
			'graphids'					=> null,
			'applicationids'			=> null,
			'dserviceids'				=> null,
			'httptestids'				=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'proxy_hosts'				=> null,
			'with_items'				=> null,
			'with_monitored_items'		=> null,
			'with_simple_graph_items'	=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers'	=> null,
			'with_httptests'			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'with_applications'			=> null,
			'withInventory'				=> null,
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
			'selectGroups'				=> null,
			'selectParentTemplates'		=> null,
			'selectItems'				=> null,
			'selectDiscoveries'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectMacros'				=> null,
			'selectScreens'				=> null,
			'selectInterfaces'			=> null,
			'selectInventory'			=> null,
			'selectHttpTests'           => null,
			'selectDiscoveryRule'		=> null,
			'selectHostDiscovery'		=> null,
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

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE h.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.$permission.
					')';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sqlParts['where']['hostid'] = dbConditionInt('h.hostid', $options['hostids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['groupid'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			$sqlParts['select']['proxy_hostid'] = 'h.proxy_hostid';
			$sqlParts['where'][] = dbConditionInt('h.proxy_hostid', $options['proxyids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['select']['parent_templateid'] = 'ht.templateid as parent_templateid';
			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['templateids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['select']['interfaceid'] = 'hi.interfaceid';
			$sqlParts['from']['interface'] = 'interface hi';
			$sqlParts['where'][] = dbConditionInt('hi.interfaceid', $options['interfaceids']);
			$sqlParts['where']['hi'] = 'h.hostid=hi.hostid';
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['select']['itemid'] = 'i.itemid';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['select']['triggerid'] = 'f.triggerid';
			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			$sqlParts['select']['httptestid'] = 'ht.httptestid';
			$sqlParts['from']['httptest'] = 'httptest ht';
			$sqlParts['where'][] = dbConditionInt('ht.httptestid', $options['httptestids']);
			$sqlParts['where']['aht'] = 'ht.hostid=h.hostid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['select']['graphid'] = 'gi.graphid';
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			$sqlParts['select']['applicationid'] = 'a.applicationid';
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where'][] = dbConditionInt('a.applicationid', $options['applicationids']);
			$sqlParts['where']['ah'] = 'a.hostid=h.hostid';
		}

		// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['select']['dserviceid'] = 'ds.dserviceid';
			$sqlParts['from']['dservices'] = 'dservices ds';
			$sqlParts['from']['interface'] = 'interface i';
			$sqlParts['where'][] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dsh'] = 'ds.ip=i.ip';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['select']['maintenanceid'] = 'mh.maintenanceid';
			$sqlParts['from']['maintenances_hosts'] = 'maintenances_hosts mh';
			$sqlParts['where'][] = dbConditionInt('mh.maintenanceid', $options['maintenanceids']);
			$sqlParts['where']['hmh'] = 'h.hostid=mh.hostid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['maintenanceid'] = 'mh.maintenanceid';
			}
		}

		// monitored_hosts, templated_hosts
		if (!is_null($options['monitored_hosts'])) {
			$sqlParts['where']['status'] = 'h.status='.HOST_STATUS_MONITORED;
		}
		elseif (!is_null($options['templated_hosts'])) {
			$sqlParts['where']['status'] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}
		elseif (!is_null($options['proxy_hosts'])) {
			$sqlParts['where']['status'] = 'h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')';
		}
		else {
			$sqlParts['where']['status'] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}

		// with_items, with_monitored_items, with_simple_graph_items
		if (!is_null($options['with_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE h.hostid=i.hostid'.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_monitored_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE h.hostid=i.hostid'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_simple_graph_items'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i'.
					' WHERE h.hostid=i.hostid'.
						' AND i.value_type IN ('.ITEM_VALUE_TYPE_FLOAT.','.ITEM_VALUE_TYPE_UINT64.')'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with_triggers, with_monitored_triggers
		if (!is_null($options['with_triggers'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,functions f,triggers t'.
					' WHERE h.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}
		elseif (!is_null($options['with_monitored_triggers'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,functions f,triggers t'.
					' WHERE h.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND f.triggerid=t.triggerid'.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND t.status='.TRIGGER_STATUS_ENABLED.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with_httptests, with_monitored_httptests
		if (!empty($options['with_httptests'])) {
			$sqlParts['where'][] = 'EXISTS (SELECT NULL FROM httptest ht WHERE ht.hostid=h.hostid)';
		}
		elseif (!empty($options['with_monitored_httptests'])) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM httptest ht'.
				' WHERE h.hostid=ht.hostid'.
					' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
				')';
		}

		// with_graphs
		if (!is_null($options['with_graphs'])) {
			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM items i,graphs_items gi,graphs g'.
					' WHERE i.hostid=h.hostid'.
						' AND i.itemid=gi.itemid '.
						' AND gi.graphid=g.graphid'.
						' AND g.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
		}

		// with applications
		if (!is_null($options['with_applications'])) {
			$sqlParts['from']['applications'] = 'applications a';
			$sqlParts['where'][] = 'a.hostid=h.hostid';
		}

		// withInventory
		if (!is_null($options['withInventory']) && $options['withInventory']) {
			$sqlParts['where'][] = ' h.hostid IN ('.
					' SELECT hin.hostid'.
					' FROM host_inventory hin'.
					')';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);

			if (zbx_db_search('interface hi', $options, $sqlParts)) {
				$sqlParts['from']['interface'] = 'interface hi';
				$sqlParts['where']['hi'] = 'h.hostid=hi.hostid';
			}
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);

			if ($this->dbFilter('interface hi', $options, $sqlParts)) {
				$sqlParts['from']['interface'] = 'interface hi';
				$sqlParts['where']['hi'] = 'h.hostid=hi.hostid';
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($host = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $host;
				}
				else {
					$result = $host['rowscount'];
				}
			}
			else {
				if (!isset($result[$host['hostid']])) {
					$result[$host['hostid']] = array();
				}

				// groupids
				if (isset($host['groupid']) && is_null($options['selectGroups'])) {
					if (!isset($result[$host['hostid']]['groups'])) {
						$result[$host['hostid']]['groups'] = array();
					}

					$result[$host['hostid']]['groups'][] = array('groupid' => $host['groupid']);
					unset($host['groupid']);
				}

				// templateids
				if (isset($host['parent_templateid'])) {
					if (!isset($result[$host['hostid']]['templates'])) {
						$result[$host['hostid']]['templates'] = array();
					}

					$result[$host['hostid']]['templates'][] = array(
						'templateid' => $host['parent_templateid'],
						'hostid' => $host['parent_templateid']
					);
					unset($host['parent_templateid']);
				}

				// triggerids
				if (isset($host['triggerid']) && is_null($options['selectTriggers'])) {
					if (!isset($result[$host['hostid']]['triggers'])) {
						$result[$host['hostid']]['triggers'] = array();
					}

					$result[$host['hostid']]['triggers'][] = array('triggerid' => $host['triggerid']);
					unset($host['triggerid']);
				}

				// interfaceids
				if (isset($host['interfaceid']) && is_null($options['selectInterfaces'])) {
					if (!isset($result[$host['hostid']]['interfaces'])) {
						$result[$host['hostid']]['interfaces'] = array();
					}

					$result[$host['hostid']]['interfaces'][] = array('interfaceid' => $host['interfaceid']);
					unset($host['interfaceid']);
				}

				// itemids
				if (isset($host['itemid']) && is_null($options['selectItems'])) {
					if (!isset($result[$host['hostid']]['items'])) {
						$result[$host['hostid']]['items'] = array();
					}

					$result[$host['hostid']]['items'][] = array('itemid' => $host['itemid']);
					unset($host['itemid']);
				}

				// graphids
				if (isset($host['graphid']) && is_null($options['selectGraphs'])) {
					if (!isset($result[$host['hostid']]['graphs'])) {
						$result[$host['hostid']]['graphs'] = array();
					}

					$result[$host['hostid']]['graphs'][] = array('graphid' => $host['graphid']);
					unset($host['graphid']);
				}

				// graphids
				if (isset($host['applicationid'])) {
					if (!isset($result[$host['hostid']]['applications'])) {
						$result[$host['hostid']]['applications'] = array();
					}

					$result[$host['hostid']]['applications'][] = array('applicationid' => $host['applicationid']);
					unset($host['applicationid']);
				}

				// httptestids
				if (isset($host['httptestid'])) {
					if (!isset($result[$host['hostid']]['httptests'])) {
						$result[$host['hostid']]['httptests'] = array();
					}

					$result[$host['hostid']]['httptests'][] = array('httptestid' => $host['httptestid']);
					unset($host['httptestid']);
				}

				// dserviceids
				if (isset($host['dserviceid'])) {
					if (!isset($result[$host['hostid']]['dservices'])) {
						$result[$host['hostid']]['dservices'] = array();
					}

					$result[$host['hostid']]['dservices'][] = array('dserviceid' => $host['dserviceid']);
					unset($host['dserviceid']);
				}

				// maintenanceids
				if (isset($host['maintenanceid'])) {
					if (!isset($result[$host['hostid']]['maintenances'])) {
						$result[$host['hostid']]['maintenances'] = array();
					}

					if ($host['maintenanceid'] > 0) {
						$result[$host['hostid']]['maintenances'][] = array('maintenanceid' => $host['maintenanceid']);
					}
				}

				$result[$host['hostid']] += $host;
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
	 * Get Host ID by Host name
	 *
	 * @param array $host_data
	 * @param string $host_data['host']
	 *
	 * @return int|boolean
	 */
	public function getObjects($hostData) {
		$options = array(
			'filter' => $hostData,
			'output' => API_OUTPUT_EXTEND
		);

		if (isset($hostData['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($hostData['node']);
		}
		elseif (isset($hostData['nodeids'])) {
			$options['nodeids'] = $hostData['nodeids'];
		}

		$result = $this->get($options);

		return $result;
	}

	public function exists($object) {
		$keyFields = array(
			array(
				'hostid',
				'host',
				'name'
			)
		);

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('hostid'),
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

	protected function checkInput(&$hosts, $method) {
		$create = ($method == 'create');
		$update = ($method == 'update');

		$hostDBfields = $update ? array('hostid' => null) : array('host' => null);

		if ($update) {
			$dbHosts = $this->get(array(
				'output' => array('hostid', 'host', 'flags'),
				'hostids' => zbx_objectValues($hosts, 'hostid'),
				'editable' => true,
				'preservekeys' => true
			));
		}
		else {
			$groupids = array();

			foreach ($hosts as $host) {
				if (!isset($host['groups'])) {
					continue;
				}
				$groupids = array_merge($groupids, zbx_objectValues($host['groups'], 'groupid'));
			}

			if ($groupids) {
				$dbGroups = API::HostGroup()->get(array(
					'output' => array('groupid'),
					'groupids' => $groupids,
					'editable' => true,
					'preservekeys' => true
				));
			}
		}

		foreach ($hosts as $host) {
			// validate mandatory fields
			if (!check_db_fields($hostDBfields, $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Wrong fields for host "%s".', isset($host['host']) ? $host['host'] : '')
				);
			}

			if ($update) {
				$hostId = $host['hostid'];

				// validate host permissions
				if (!isset($dbHosts[$hostId])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('No permissions to referred object or it does not exist!')
					);
				}

				if (isset($host['groups']) && (!is_array($host['groups']) || !$host['groups'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host "%1$s" cannot be without host group.', $dbHosts[$hostId]['host'])
					);
				}
			}
			else {
				if (!isset($host['groups']) || !is_array($host['groups']) || !$host['groups']) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host "%1$s" cannot be without host group.', $host['host'])
					);
				}

				foreach ($host['groups'] as $group) {
					if (!isset($dbGroups[$group['groupid']])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('No permissions to referred object or it does not exist!')
						);
					}
				}
			}
		}

		$inventoryFields = getHostInventories();
		$inventoryFields = zbx_objectValues($inventoryFields, 'db_field');

		$hostNames = array();
		foreach ($hosts as &$host) {
			if (isset($host['inventory']) && !empty($host['inventory'])) {
				if (isset($host['inventory_mode']) && $host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
				}

				$fields = array_keys($host['inventory']);
				foreach ($fields as $field) {
					if (!in_array($field, $inventoryFields)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect inventory field "%s".', $field));
					}
				}
			}

			$updateDiscoveredValidator = new CUpdateDiscoveredValidator(array(
				'allowed' => array('hostid', 'status', 'inventory'),
				'messageAllowedField' => _('Cannot update "%1$s" for a discovered host.')
			));

			if ($update) {
				// cannot update certain fields for discovered hosts
				$this->checkPartialValidator($host, $updateDiscoveredValidator, $dbHosts[$host['hostid']]);
			}
			else {
				// if visible name is not given or empty it should be set to host name
				if (!isset($host['name']) || zbx_empty(trim($host['name']))) {
					$host['name'] = $host['host'];
				}

				if (!isset($host['interfaces'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces for host "%s".', $host['host']));
				}
			}

			if (isset($host['interfaces'])) {
				if (!is_array($host['interfaces']) || empty($host['interfaces'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('No interfaces for host "%s".', $host['host']));
				}
			}

			if (isset($host['host'])) {
				// Check if host name isn't longer than 64 chars
				if (zbx_strlen($host['host']) > 64) {
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_n(
							'Maximum host name length is %1$d characters, "%2$s" is %3$d character.',
							'Maximum host name length is %1$d characters, "%2$s" is %3$d characters.',
							64,
							$host['host'],
							zbx_strlen($host['host'])
						)
					);
				}

				if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $host['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect characters used for host name "%s".', $host['host']));
				}

				if (isset($hostNames['host'][$host['host']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate host. Host with the same host name "%s" already exists in data.', $host['host']));
				}

				$hostNames['host'][$host['host']] = $update ? $host['hostid'] : 1;
			}

			if (isset($host['name'])) {
				if ($update) {
					// if visible name is empty replace it with host name
					if (zbx_empty(trim($host['name']))) {
						if (!isset($host['host'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Visible name cannot be empty if host name is missing.'));
						}
						$host['name'] = $host['host'];
					}
				}

				// Check if visible name isn't longer than 64 chars
				if (zbx_strlen($host['name']) > 64) {
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_n(
							'Maximum visible host name length is %1$d characters, "%2$s" is %3$d character.',
							'Maximum visible host name length is %1$d characters, "%2$s" is %3$d characters.',
							64,
							$host['name'],
							zbx_strlen($host['name'])
						)
					);
				}

				if (isset($hostNames['name'][$host['name']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate host. Host with the same visible name "%s" already exists in data.', $host['name']));
				}
				$hostNames['name'][$host['name']] = $update ? $host['hostid'] : 1;
			}
		}
		unset($host);

		if ($update || $create) {
			if (isset($hostNames['host']) || isset($hostNames['name'])) {
				$filter = array();
				if (isset($hostNames['host'])) {
					$filter['host'] = array_keys($hostNames['host']);
				}
				if (isset($hostNames['name'])) {
					$filter['name'] = array_keys($hostNames['name']);
				}

				$options = array(
					'output' => array('hostid', 'host', 'name'),
					'filter' => $filter,
					'searchByAny' => true,
					'nopermissions' => true,
					'preservekeys' => true
				);

				$hostsExists = $this->get($options);

				foreach ($hostsExists as $hostExists) {
					if (isset($hostNames['host'][$hostExists['host']])) {
						if (!$update || bccomp($hostExists['hostid'], $hostNames['host'][$hostExists['host']]) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host with the same name "%s" already exists.', $hostExists['host']));
						}
					}

					if (isset($hostNames['name'][$hostExists['name']])) {
						if (!$update || bccomp($hostExists['hostid'], $hostNames['name'][$hostExists['name']]) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host with the same visible name "%s" already exists.', $hostExists['name']));
						}
					}
				}

				$templatesExists = API::Template()->get($options);

				foreach ($templatesExists as $templateExists) {
					if (isset($hostNames['host'][$templateExists['host']])) {
						if (!$update || bccomp($templateExists['templateid'], $hostNames['host'][$templateExists['host']]) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template with the same name "%s" already exists.', $templateExists['host']));
						}
					}

					if (isset($hostNames['name'][$templateExists['name']])) {
						if (!$update || bccomp($templateExists['templateid'], $hostNames['name'][$templateExists['name']]) != 0) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template with the same visible name "%s" already exists.', $templateExists['name']));
						}
					}
				}
			}
		}

		return $update ? $dbHosts : $hosts;
	}

	/**
	 * Add Host
	 *
	 * @param array  $hosts multidimensional array with Hosts data
	 * @param string $hosts ['host'] Host name.
	 * @param array  $hosts ['groups'] array of HostGroup objects with IDs add Host to.
	 * @param int    $hosts ['port'] Port. OPTIONAL
	 * @param int    $hosts ['status'] Host Status. OPTIONAL
	 * @param int    $hosts ['useip'] Use IP. OPTIONAL
	 * @param string $hosts ['dns'] DNS. OPTIONAL
	 * @param string $hosts ['ip'] IP. OPTIONAL
	 * @param int    $hosts ['proxy_hostid'] Proxy Host ID. OPTIONAL
	 * @param int    $hosts ['ipmi_authtype'] IPMI authentication type. OPTIONAL
	 * @param int    $hosts ['ipmi_privilege'] IPMI privilege. OPTIONAL
	 * @param string $hosts ['ipmi_username'] IPMI username. OPTIONAL
	 * @param string $hosts ['ipmi_password'] IPMI password. OPTIONAL
	 *
	 * @return boolean
	 */
	public function create($hosts) {
		$hosts = zbx_toArray($hosts);
		$hostids = array();

		$this->checkInput($hosts, __FUNCTION__);

		foreach ($hosts as $host) {
			$hostid = DB::insert('hosts', array($host));
			$hostids[] = $hostid = reset($hostid);

			$host['hostid'] = $hostid;

			// save groups
			// groups must be added before calling massAdd() for permission validation to work
			$groupsToAdd = array();
			foreach ($host['groups'] as $group) {
				$groupsToAdd[] = array(
					'hostid' => $hostid,
					'groupid' => $group['groupid']
				);
			}
			DB::insert('hosts_groups', $groupsToAdd);

			$options = array();
			$options['hosts'] = $host;

			if (isset($host['templates']) && !is_null($host['templates'])) {
				$options['templates'] = $host['templates'];
			}

			if (isset($host['macros']) && !is_null($host['macros'])) {
				$options['macros'] = $host['macros'];
			}

			if (isset($host['interfaces']) && !is_null($host['interfaces'])) {
				$options['interfaces'] = $host['interfaces'];
			}

			$result = API::Host()->massAdd($options);
			if (!$result) {
				self::exception();
			}

			if (!empty($host['inventory'])) {
				$hostInventory = $host['inventory'];
				$hostInventory['hostid'] = $hostid;
				$hostInventory['inventory_mode'] = HOST_INVENTORY_MANUAL;
			}
			else {
				$hostInventory = array();
			}

			if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] != HOST_INVENTORY_DISABLED) {
				$hostInventory['hostid'] = $hostid;
				$hostInventory['inventory_mode'] = $host['inventory_mode'];
			}

			if ($hostInventory) {
				DB::insert('host_inventory', array($hostInventory), false);
			}
		}

		return array('hostids' => $hostids);
	}

	/**
	 * Update Host.
	 *
	 * @param array  $hosts multidimensional array with Hosts data
	 * @param string $hosts ['host'] Host name.
	 * @param int    $hosts ['port'] Port. OPTIONAL
	 * @param int    $hosts ['status'] Host Status. OPTIONAL
	 * @param int    $hosts ['useip'] Use IP. OPTIONAL
	 * @param string $hosts ['dns'] DNS. OPTIONAL
	 * @param string $hosts ['ip'] IP. OPTIONAL
	 * @param int    $hosts ['proxy_hostid'] Proxy Host ID. OPTIONAL
	 * @param int    $hosts ['ipmi_authtype'] IPMI authentication type. OPTIONAL
	 * @param int    $hosts ['ipmi_privilege'] IPMI privilege. OPTIONAL
	 * @param string $hosts ['ipmi_username'] IPMI username. OPTIONAL
	 * @param string $hosts ['ipmi_password'] IPMI password. OPTIONAL
	 * @param string $hosts ['groups'] groups
	 *
	 * @return boolean
	 */
	public function update($hosts) {
		$hosts = zbx_toArray($hosts);
		$hostids = zbx_objectValues($hosts, 'hostid');

		$this->checkInput($hosts, __FUNCTION__);

		// fetch fields required to update host inventory
		$inventories = array();
		foreach ($hosts as $host) {
			if (isset($host['inventory'])) {
				$inventory = $host['inventory'];
				$inventory['hostid'] = $host['hostid'];

				$inventories[] = $inventory;
			}
		}
		$inventories = $this->extendObjects('host_inventory', $inventories, array('inventory_mode'));
		$inventories = zbx_toHash($inventories, 'hostid');

		$macros = array();
		foreach ($hosts as &$host) {
			if (isset($host['macros'])) {
				$macros[$host['hostid']] = $host['macros'];

				unset($host['macros']);
			}
		}
		unset($host);

		if ($macros) {
			API::UserMacro()->replaceMacros($macros);
		}

		foreach ($hosts as $host) {
			// extend host inventory with the required data
			if (isset($host['inventory']) && $host['inventory']) {
				$inventory = $inventories[$host['hostid']];

				// if no host inventory record exists in the DB, it's disabled
				if (!isset($inventory['inventory_mode'])) {
					$inventory['inventory_mode'] = HOST_INVENTORY_DISABLED;
				}

				$host['inventory'] = $inventory;
			}

			API::HostInterface()->replaceHostInterfaces($host);
			unset($host['interfaces']);

			$data = $host;
			$data['hosts'] = $host;
			$result = $this->massUpdate($data);

			if (!$result) {
				self::exception(ZBX_API_ERROR_INTERNAL, _('Host update failed.'));
			}
		}

		return array('hostids' => $hostids);
	}

	/**
	 * Additionally allows to create new interfaces on hosts.
	 *
	 * Checks write permissions for hosts.
	 *
	 * Additional supported $data parameters are:
	 * - interfaces - an array of interfaces to create on the hosts
	 * - templates  - an array of templates to link to the hosts, overrides the CHostGeneral::massAdd()
	 *                'templates' parameter
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massAdd(array $data) {
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : array();
		$hostIds = zbx_objectValues($hosts, 'hostid');

		// check permissions
		if (!$this->isWritable($hostIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		// add new interfaces
		if (!empty($data['interfaces'])) {
			API::HostInterface()->massAdd(array(
				'hosts' => $data['hosts'],
				'interfaces' => zbx_toArray($data['interfaces'])
			));
		}

		// rename the "templates" parameter to the common "templates_link"
		if (isset($data['templates'])) {
			$data['templates_link'] = $data['templates'];
			unset($data['templates']);
		}

		$data['templates'] = array();

		return parent::massAdd($data);
	}

	/**
	 * Mass update hosts.
	 *
	 * @param array  $hosts								multidimensional array with Hosts data
	 * @param array  $hosts['hosts']					Array of Host objects to update
	 * @param string $hosts['fields']['host']			Host name.
	 * @param array  $hosts['fields']['groupids']		HostGroup IDs add Host to.
	 * @param int    $hosts['fields']['port']			Port. OPTIONAL
	 * @param int    $hosts['fields']['status']			Host Status. OPTIONAL
	 * @param int    $hosts['fields']['useip']			Use IP. OPTIONAL
	 * @param string $hosts['fields']['dns']			DNS. OPTIONAL
	 * @param string $hosts['fields']['ip']				IP. OPTIONAL
	 * @param int    $hosts['fields']['proxy_hostid']	Proxy Host ID. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_authtype']	IPMI authentication type. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_privilege']	IPMI privilege. OPTIONAL
	 * @param string $hosts['fields']['ipmi_username']	IPMI username. OPTIONAL
	 * @param string $hosts['fields']['ipmi_password']	IPMI password. OPTIONAL
	 *
	 * @return boolean
	 */
	public function massUpdate($data) {
		$hosts = zbx_toArray($data['hosts']);
		$inputHostIds = zbx_objectValues($hosts, 'hostid');
		$hostids = array_unique($inputHostIds);

		sort($hostids);

		$updHosts = $this->get(array(
			'hostids' => $hostids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
		));
		foreach ($hosts as $host) {
			if (!isset($updHosts[$host['hostid']])) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		// check if hosts have at least 1 group
		if (isset($data['groups']) && empty($data['groups'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No groups for hosts.'));
		}

		/*
		 * Update hosts properties
		 */
		if (isset($data['name'])) {
			if (count($hosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update visible host name.'));
			}
		}

		if (isset($data['host'])) {
			if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/', $data['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect characters used for host name "%s".', $data['host']));
			}

			if (count($hosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update host name.'));
			}

			$curHost = reset($hosts);

			$hostExists = $this->get(array(
				'filter' => array('host' => $curHost['host']),
				'output' => array('hostid'),
				'editable' => true,
				'nopermissions' => true
			));
			$hostExist = reset($hostExists);
			if ($hostExist && (bccomp($hostExist['hostid'], $curHost['hostid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" already exists.', $data['host']));
			}

			// can't add host with the same name as existing template
			if (API::Template()->exists(array('host' => $curHost['host']))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template "%1$s" already exists.', $curHost['host']));
			}
		}

		if (isset($data['groups'])) {
			$updateGroups = $data['groups'];
		}

		if (isset($data['interfaces'])) {
			$updateInterfaces = $data['interfaces'];
		}

		if (isset($data['templates_clear'])) {
			$updateTemplatesClear = zbx_toArray($data['templates_clear']);
		}

		if (isset($data['templates'])) {
			$updateTemplates = $data['templates'];
		}

		if (isset($data['macros'])) {
			$updateMacros = $data['macros'];
		}

		// second check is necessary, because import incorrectly inputs unset 'inventory' as empty string rather than null
		if (isset($data['inventory']) && $data['inventory']) {
			if (isset($data['inventory_mode']) && $data['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
			}

			$updateInventory = $data['inventory'];
			$updateInventory['inventory_mode'] = null;
		}

		if (isset($data['inventory_mode'])) {
			if (!isset($updateInventory)) {
				$updateInventory = array();
			}
			$updateInventory['inventory_mode'] = $data['inventory_mode'];
		}

		if (isset($data['status'])) {
			$updateStatus = $data['status'];
		}

		unset($data['hosts'], $data['groups'], $data['interfaces'], $data['templates_clear'], $data['templates'],
			$data['macros'], $data['inventory'], $data['inventory_mode'], $data['status']);

		if (!zbx_empty($data)) {
			DB::update('hosts', array(
				'values' => $data,
				'where' => array('hostid' => $hostids)
			));
		}

		if (isset($updateStatus)) {
			updateHostStatus($hostids, $updateStatus);
		}

		/*
		 * Update interfaces
		 */
		if (isset($updateInterfaces)) {
			$hostInterfaces = API::HostInterface()->get(array(
				'hostids' => $hostids,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => true,
				'nopermissions' => true
			));

			$this->massRemove(array(
				'hostids' => $hostids,
				'interfaces' => $hostInterfaces
			));
			$this->massAdd(array(
				'hosts' => $hosts,
				'interfaces' => $updateInterfaces
			));
		}

		if (isset($updateTemplatesClear)) {
			$templateidsClear = zbx_objectValues($updateTemplatesClear, 'templateid');

			if ($updateTemplatesClear) {
				$this->massRemove(array('hostids' => $hostids, 'templateids_clear' => $templateidsClear));
			}
		}
		else {
			$templateidsClear = array();
		}

		/*
		 * Update template linkage
		 */
		if (isset($updateTemplates)) {
			$hostTemplates = API::Template()->get(array(
				'hostids' => $hostids,
				'output' => array('templateid'),
				'preservekeys' => true
			));

			$hostTemplateids = array_keys($hostTemplates);
			$newTemplateids = zbx_objectValues($updateTemplates, 'templateid');

			$templatesToDel = array_diff($hostTemplateids, $newTemplateids);
			$templatesToDel = array_diff($templatesToDel, $templateidsClear);

			if ($templatesToDel) {
				$result = $this->massRemove(array(
					'hostids' => $hostids,
					'templateids' => $templatesToDel
				));
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template'));
				}
			}

			$result = $this->massAdd(array(
				'hosts' => $hosts,
				'templates' => $updateTemplates
			));
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template'));
			}
		}

		// macros
		if (isset($updateMacros)) {
			DB::delete('hostmacro', array('hostid' => $hostids));

			$this->massAdd(array(
				'hosts' => $hosts,
				'macros' => $updateMacros
			));
		}

		/*
		 * Inventory
		 */
		if (isset($updateInventory)) {
			// disabling inventory
			if ($updateInventory['inventory_mode'] == HOST_INVENTORY_DISABLED) {
				$sql = 'DELETE FROM host_inventory WHERE '.dbConditionInt('hostid', $hostids);
				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete inventory.'));
				}
			}
			// changing inventory mode or setting inventory fields
			else {
				$existingInventoriesDb = DBfetchArrayAssoc(DBselect(
					'SELECT hostid,inventory_mode'.
					' FROM host_inventory'.
					' WHERE '.dbConditionInt('hostid', $hostids)
				), 'hostid');

				// check existing host inventory data
				$automaticHostIds = array();
				if ($updateInventory['inventory_mode'] === null) {
					foreach ($hostids as $hostId) {
						// if inventory is disabled for one of the updated hosts, throw an exception
						if (!isset($existingInventoriesDb[$hostId])) {
							$host = get_host_by_hostid($hostId);
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Inventory disabled for host "%1$s".', $host['host']
							));
						}
						// if inventory mode is set to automatic, save its ID for later usage
						elseif ($existingInventoriesDb[$hostId]['inventory_mode'] == HOST_INVENTORY_AUTOMATIC) {
							$automaticHostIds[] = $hostId;
						}
					}
				}

				$inventoriesToSave = array();
				foreach ($hostids as $hostId) {
					$hostInventory = $updateInventory;
					$hostInventory['hostid'] = $hostId;

					// if no 'inventory_mode' has been passed, set inventory 'inventory_mode' from DB
					if ($updateInventory['inventory_mode'] === null) {
						$hostInventory['inventory_mode'] = $existingInventoriesDb[$hostId]['inventory_mode'];
					}

					$inventoriesToSave[$hostId] = $hostInventory;
				}

				// when updating automatic inventory, ignore fields that have items linked to them
				if ($updateInventory['inventory_mode'] == HOST_INVENTORY_AUTOMATIC
						|| ($updateInventory['inventory_mode'] === null && $automaticHostIds)) {

					$itemsToInventories = API::item()->get(array(
						'output' => array('inventory_link', 'hostid'),
						'hostids' => $automaticHostIds ? $automaticHostIds : $hostids,
						'nopermissions' => true
					));

					$inventoryFields = getHostInventories();
					foreach ($itemsToInventories as $hinv) {
						// 0 means 'no link'
						if ($hinv['inventory_link'] != 0) {
							$inventoryName = $inventoryFields[$hinv['inventory_link']]['db_field'];
							unset($inventoriesToSave[$hinv['hostid']][$inventoryName]);
						}
					}
				}

				// save inventory data
				foreach ($inventoriesToSave as $inventory) {
					$hostId = $inventory['hostid'];
					if (isset($existingInventoriesDb[$hostId])) {
						DB::update('host_inventory', array(
							'values' => $inventory,
							'where' => array('hostid' => $hostId)
						));
					}
					else {
						DB::insert('host_inventory', array($inventory), false);
					}
				}
			}
		}

		/*
		 * Update host and host group linkage. This procedure should be done the last because user can unlink
		 * him self from a group with write permissions leaving only read premissions. Thus other procedures, like
		 * host-template linkage, inventory update, macros update, must be done before this.
		 */
		if (isset($updateGroups)) {
			$updateGroups = zbx_toArray($updateGroups);

			$hostGroups = API::HostGroup()->get(array(
				'output' => array('groupid'),
				'hostids' => $hostids
			));
			$hostGroupids = zbx_objectValues($hostGroups, 'groupid');
			$newGroupids = zbx_objectValues($updateGroups, 'groupid');

			$groupsToAdd = array_diff($newGroupids, $hostGroupids);
			if ($groupsToAdd) {
				$this->massAdd(array(
					'hosts' => $hosts,
					'groups' => zbx_toObject($groupsToAdd, 'groupid')
				));
			}

			$groupidsToDel = array_diff($hostGroupids, $newGroupids);
			if ($groupidsToDel) {
				$this->massRemove(array(
					'hostids' => $hostids,
					'groupids' => $groupidsToDel
				));
			}
		}

		return array('hostids' => $inputHostIds);
	}

	/**
	 * Additionally allows to remove interfaces from hosts.
	 *
	 * Checks write permissions for hosts.
	 *
	 * Additional supported $data parameters are:
	 * - interfaces  - an array of interfaces to delete from the hosts
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$hostids = zbx_toArray($data['hostids']);

		// check permissions
		if (!$this->isWritable($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		if (isset($data['interfaces'])) {
			$options = array(
				'hostids' => $hostids,
				'interfaces' => zbx_toArray($data['interfaces'])
			);
			API::HostInterface()->massRemove($options);
		}

		// rename the "templates" parameter to the common "templates_link"
		if (isset($data['templateids'])) {
			$data['templateids_link'] = $data['templateids'];
			unset($data['templateids']);
		}

		$data['templateids'] = array();

		return parent::massRemove($data);
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $hostIds
	 * @param bool 	$nopermissions
	 *
	 * @return void
	 */
	protected function validateDelete(array $hostIds, $nopermissions = false) {
		if (empty($hostIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		if (!$nopermissions) {
			$this->checkPermissions($hostIds);
		}
	}

	/**
	 * Delete Host
	 *
	 * @param string|array 	$hostIds
	 * @param bool			$nopermissions
	 *
	 * @return array|boolean
	 */
	public function delete($hostIds, $nopermissions = false) {
		$hostIds = zbx_toArray($hostIds);

		// deprecated input support
		if ($hostIds && is_array($hostIds[0])) {
			$this->deprecated('Passing objects is deprecated, use an array of IDs instead.');
			foreach ($hostIds as $host) {
				if (!check_db_fields(array('hostid' => null), $host)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No host ID given.'));
				}
			}
			$hostIds = zbx_objectValues($hostIds, 'hostid');
		}

		$this->validateDelete($hostIds, $nopermissions);

		// delete the discovery rules first
		$delRules = API::DiscoveryRule()->get(array(
			'hostids' => $hostIds,
			'nopermissions' => true,
			'preservekeys' => true
		));
		if ($delRules) {
			API::DiscoveryRule()->delete(array_keys($delRules), true);
		}

		// delete the items
		$delItems = API::Item()->get(array(
			'templateids' => $hostIds,
			'output' => array('itemid'),
			'nopermissions' => true,
			'preservekeys' => true
		));
		if ($delItems) {
			API::Item()->delete(array_keys($delItems), true);
		}

// delete web tests
		$delHttptests = array();
		$dbHttptests = get_httptests_by_hostid($hostIds);
		while ($dbHttptest = DBfetch($dbHttptests)) {
			$delHttptests[$dbHttptest['httptestid']] = $dbHttptest['httptestid'];
		}
		if (!empty($delHttptests)) {
			API::HttpTest()->delete($delHttptests, true);
		}


// delete screen items
		DB::delete('screens_items', array(
			'resourceid' => $hostIds,
			'resourcetype' => SCREEN_RESOURCE_HOST_TRIGGERS
		));

// delete host from maps
		if (!empty($hostIds)) {
			DB::delete('sysmaps_elements', array(
				'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
				'elementid' => $hostIds
			));
		}

// disable actions
// actions from conditions
		$actionids = array();
		$sql = 'SELECT DISTINCT actionid'.
				' FROM conditions'.
				' WHERE conditiontype='.CONDITION_TYPE_HOST.
				' AND '.dbConditionString('value', $hostIds);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

// actions from operations
		$sql = 'SELECT DISTINCT o.actionid'.
				' FROM operations o, opcommand_hst oh'.
				' WHERE o.operationid=oh.operationid'.
				' AND '.dbConditionInt('oh.hostid', $hostIds);
		$dbActions = DBselect($sql);
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
			'conditiontype' => CONDITION_TYPE_HOST,
			'value' => $hostIds
		));

// delete action operation commands
		$operationids = array();
		$sql = 'SELECT DISTINCT oh.operationid'.
				' FROM opcommand_hst oh'.
				' WHERE '.dbConditionInt('oh.hostid', $hostIds);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('opcommand_hst', array(
			'hostid' => $hostIds,
		));

// delete empty operations
		$delOperationids = array();
		$sql = 'SELECT DISTINCT o.operationid'.
				' FROM operations o'.
				' WHERE '.dbConditionInt('o.operationid', $operationids).
				' AND NOT EXISTS(SELECT oh.opcommand_hstid FROM opcommand_hst oh WHERE oh.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', array(
			'operationid' => $delOperationids,
		));

		$hosts = API::Host()->get(array(
			'output' => array(
				'hostid',
				'name'
			),
			'hostids' => $hostIds,
			'nopermissions' => true
		));

// delete host inventory
		DB::delete('host_inventory', array('hostid' => $hostIds));

// delete host applications
		DB::delete('applications', array('hostid' => $hostIds));

// delete host
		DB::delete('hosts', array('hostid' => $hostIds));

// TODO: remove info from API
		foreach ($hosts as $host) {
			info(_s('Deleted: Host "%1$s".', $host['name']));
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, $host['hostid'], $host['name'], 'hosts', NULL, NULL);
		}

		// remove Monitoring > Latest data toggle profile values related to given hosts
		DB::delete('profiles', array('idx' => 'web.latest.toggle_other', 'idx2' => $hostIds));

		return array('hostids' => $hostIds);
	}

	/**
	 * Check if user has read permissions for host.
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
			'nodeids' => get_current_nodeid(true),
			'hostids' => $ids,
			'templated_hosts' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Check if user has write permissions for host.
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
			'nodeids' => get_current_nodeid(true),
			'hostids' => $ids,
			'editable' => true,
			'templated_hosts' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// only apply the node option if no specific ids are given
		if ($options['hostids'] === null &&
				$options['proxyids'] === null &&
				$options['templateids'] === null &&
				$options['interfaceids'] === null &&
				$options['itemids'] === null &&
				$options['triggerids'] === null &&
				$options['maintenanceids'] === null &&
				$options['graphids'] === null &&
				$options['applicationids'] === null &&
				$options['dserviceids'] === null &&
				$options['httptestids'] === null &&
				$options['groupids'] === null) {

			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$hostids = array_keys($result);

		// adding inventories
		if ($options['selectInventory'] !== null) {
			$relationMap = $this->createRelationMap($result, 'hostid', 'hostid');
			$inventory = API::getApi()->select('host_inventory', array(
				'output' => $options['selectInventory'],
				'filter' => array('hostid' => $hostids),
				'nodeids' => get_current_nodeid(true)
			));
			$result = $relationMap->mapOne($result, zbx_toHash($inventory, 'hostid'), 'inventory');
		}

		// adding hostinterfaces
		if ($options['selectInterfaces'] !== null) {
			if ($options['selectInterfaces'] != API_OUTPUT_COUNT) {
				$interfaces = API::HostInterface()->get(array(
					'output' => $this->outputExtend('interface', array('hostid', 'interfaceid'), $options['selectInterfaces']),
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				));

				// we need to order interfaces for proper linkage and viewing
				order_result($interfaces, 'interfaceid', ZBX_SORT_UP);

				$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');

				$interfaces = $this->unsetExtraFields($interfaces, array('hostid', 'interfaceid'), $options['selectInterfaces']);
				$result = $relationMap->mapMany($result, $interfaces, 'interfaces', $options['limitSelects']);
			}
			else {
				$interfaces = API::HostInterface()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));

				$interfaces = zbx_toHash($interfaces, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['interfaces'] = isset($interfaces[$hostid]) ? $interfaces[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding screens
		if ($options['selectScreens'] !== null) {
			if ($options['selectScreens'] != API_OUTPUT_COUNT) {
				$screens = API::TemplateScreen()->get(array(
					'output' => $this->outputExtend('screens', 'hostid', $options['selectScreens']),
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true
				));
				if (!is_null($options['limitSelects'])) {
					order_result($screens, 'name');
				}

				// inherited screens do not have a unique screenid, so we're building a map using array keys
				$relationMap = new CRelationMap();
				foreach ($screens as $key => $screen) {
					$relationMap->addRelation($screen['hostid'], $key);
				}

				$screens = $this->unsetExtraFields($screens, array('hostid'), $options['selectScreens']);
				$result = $relationMap->mapMany($result, $screens, 'screens', $options['limitSelects']);
			}
			else {
				$screens = API::TemplateScreen()->get(array(
					'nodeids' => $options['nodeids'],
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				));
				$screens = zbx_toHash($screens, 'hostid');

				foreach ($result as $hostid => $host) {
					$result[$hostid]['screens'] = isset($screens[$hostid]) ? $screens[$hostid]['rowscount'] : 0;
				}
			}
		}

		// adding discovery rule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			// discovered items
			$discoveryRules = DBFetchArray(DBselect(
				'SELECT hd.hostid,hd2.parent_itemid'.
					' FROM host_discovery hd,host_discovery hd2'.
					' WHERE '.dbConditionInt('hd.hostid', $hostids).
					' AND hd.parent_hostid=hd2.hostid'
			));
			$relationMap = $this->createRelationMap($discoveryRules, 'hostid', 'parent_itemid');

			$discoveryRules = API::DiscoveryRule()->get(array(
				'output' => $options['selectDiscoveryRule'],
				'nodeids' => $options['nodeids'],
				'itemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			));
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding host discovery
		if ($options['selectHostDiscovery'] !== null) {
			$hostDiscoveries = API::getApi()->select('host_discovery', array(
				'output' => $this->outputExtend('host_discovery', array('hostid'), $options['selectHostDiscovery']),
				'filter' => array('hostid' => $hostids),
				'preservekeys' => true,
				'nodeids' => get_current_nodeid(true)
			));
			$relationMap = $this->createRelationMap($hostDiscoveries, 'hostid', 'hostid');

			$hostDiscoveries = $this->unsetExtraFields($hostDiscoveries, array('hostid'),
				$options['selectHostDiscovery']
			);
			$result = $relationMap->mapOne($result, $hostDiscoveries, 'hostDiscovery');
		}

		return $result;
	}

	/**
	 * Checks if all of the given hosts are available for writing.
	 *
	 * @throws APIException     if a host is not writable or does not exist
	 *
	 * @param array $hostIds
	 */
	protected function checkPermissions(array $hostIds) {
		if (!$this->isWritable($hostIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
