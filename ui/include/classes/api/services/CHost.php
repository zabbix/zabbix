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
 * Class containing methods for operations with hosts.
 */
class CHost extends CHostGeneral {

	protected $sortColumns = ['hostid', 'host', 'name', 'status'];

	/**
	 * Get host data.
	 *
	 * @param array         $options
	 * @param array         $options['groupids']                           Select hosts by group IDs.
	 * @param array         $options['hostids']                            Select hosts by host IDs.
	 * @param array         $options['templateids']                        Select hosts by template IDs.
	 * @param array         $options['interfaceids']                       Select hosts by interface IDs.
	 * @param array         $options['itemids']                            Select hosts by item IDs.
	 * @param array         $options['triggerids']                         Select hosts by trigger IDs.
	 * @param array         $options['maintenanceids']                     Select hosts by maintenance IDs.
	 * @param array         $options['graphids']                           Select hosts by graph IDs.
	 * @param array         $options['dserviceids']                        Select hosts by discovery service IDs.
	 * @param array         $options['httptestids']                        Select hosts by web scenario IDs.
	 * @param bool          $options['monitored_hosts']                    Return only monitored hosts.
	 * @param bool          $options['templated_hosts']                    Include templates in result.
	 * @param bool          $options['proxy_hosts']                        Include proxies in result.
	 * @param bool          $options['with_items']                         Select hosts only with items.
	 * @param bool          $options['with_item_prototypes']               Select hosts only with item prototypes.
	 * @param bool          $options['with_simple_graph_items']            Select hosts only with items suitable for graphs.
	 * @param bool          $options['with_simple_graph_item_prototypes']  Select hosts only with item prototypes suitable for graphs.
	 * @param bool          $options['with_monitored_items']               Select hosts only with monitored items.
	 * @param bool          $options['with_triggers']                      Select hosts only with triggers.
	 * @param bool          $options['with_monitored_triggers']            Select hosts only with monitored triggers.
	 * @param bool          $options['with_httptests']                     Select hosts only with http tests.
	 * @param bool          $options['with_monitored_httptests']           Select hosts only with monitored http tests.
	 * @param bool          $options['with_graphs']                        Select hosts only with graphs.
	 * @param bool          $options['with_graph_prototypes']              Select hosts only with graph prototypes.
	 * @param bool          $options['withProblemsSuppressed']             Select hosts that have suppressed problems. (null - all, true - only suppressed, false - unsuppressed)
	 * @param bool          $options['editable']                           Select hosts only with read-write permission. Ignored for Super admins.
	 * @param bool          $options['nopermissions']                      Select hosts by ignoring all permissions. Only available inside API calls.
	 * @param bool          $options['evaltype']                           Operator for tag filter 0 - AND/OR; 2 - OR.
	 * @param bool          $options['tags']                               Select hosts by given tags.
	 * @param bool          $options['severities']                         Select hosts that have only problems with given severities.
	 * @param bool          $options['inheritedTags']                      Select hosts that have given tags also in their linked templates.
	 * @param string|array  $options['selectGroups']                       Deprecated! Return a "groups" property with host groups data that the host belongs to.
	 * @param string|array  $options['selectHostGroups']                   Return a "hostgroups" property with host groups data that the host belongs to.
	 * @param string|array  $options['selectParentTemplates']              Return a "parentTemplates" property with templates that the host is linked to.
	 * @param string|array  $options['selectItems']                        Return an "items" property with host items.
	 * @param string|array  $options['selectDiscoveries']                  Return a "discoveries" property with host low-level discovery rules.
	 * @param string|array  $options['selectTriggers']                     Return a "triggers" property with host triggers.
	 * @param string|array  $options['selectGraphs']                       Return a "graphs" property with host graphs.
	 * @param string|array  $options['selectMacros']                       Return a "macros" property with host macros.
	 * @param string|array  $options['selectDashboards']                   Return a "dashboards" property with host dashboards.
	 * @param string|array  $options['selectInterfaces']                   Return an "interfaces" property with host interfaces.
	 * @param string|array  $options['selectInventory']                    Return an "inventory" property with host inventory data.
	 * @param string|array  $options['selectHttpTests']                    Return an "httpTests" property with host web scenarios.
	 * @param string|array  $options['selectDiscoveryRule']                Return a "discoveryRule" property with the low-level discovery rule that created the host (from host prototype in VMware monitoring).
	 * @param string|array  $options['selectHostDiscovery']                Return a "hostDiscovery" property with host discovery object data.
	 * @param string|array  $options['selectTags']                         Return a "tags" property with host tags.
	 * @param string|array  $options['selectInheritedTags']                Return an "inheritedTags" property with tags that are on templates which are linked to host.
	 * @param bool          $options['countOutput']                        Return host count as output.
	 * @param bool          $options['groupCount']                         Group the host count.
	 * @param bool          $options['preservekeys']                       Return host IDs as array keys.
	 * @param string        $options['sortfield']                          Field to sort by.
	 * @param string        $options['sortorder']                          Sort order.
	 * @param int           $options['limit']                              Limit selection.
	 * @param int           $options['limitSelects']                       Limits the number of records returned by subselects.
	 *
	 * @return array|boolean Host data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['hosts' => 'h.hostid'],
			'from'		=> ['hosts' => 'hosts h'],
			'where'		=> ['flags' => 'h.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'							=> null,
			'hostids'							=> null,
			'proxyids'							=> null,
			'templateids'						=> null,
			'interfaceids'						=> null,
			'itemids'							=> null,
			'triggerids'						=> null,
			'maintenanceids'					=> null,
			'graphids'							=> null,
			'dserviceids'						=> null,
			'httptestids'						=> null,
			'monitored_hosts'					=> null,
			'templated_hosts'					=> null,
			'proxy_hosts'						=> null,
			'with_items'						=> null,
			'with_item_prototypes'				=> null,
			'with_simple_graph_items'			=> null,
			'with_simple_graph_item_prototypes'	=> null,
			'with_monitored_items'				=> null,
			'with_triggers'						=> null,
			'with_monitored_triggers'			=> null,
			'with_httptests'					=> null,
			'with_monitored_httptests'			=> null,
			'with_graphs'						=> null,
			'with_graph_prototypes'				=> null,
			'withProblemsSuppressed'			=> null,
			'editable'							=> false,
			'nopermissions'						=> null,
			// filter
			'evaltype'							=> TAG_EVAL_TYPE_AND_OR,
			'tags'								=> null,
			'severities'						=> null,
			'inheritedTags'						=> false,
			'filter'							=> null,
			'search'							=> null,
			'searchInventory'					=> null,
			'searchByAny'						=> null,
			'startSearch'						=> false,
			'excludeSearch'						=> false,
			'searchWildcardsEnabled'			=> false,
			// output
			'output'							=> API_OUTPUT_EXTEND,
			'selectGroups'						=> null,
			'selectHostGroups'					=> null,
			'selectParentTemplates'				=> null,
			'selectItems'						=> null,
			'selectDiscoveries'					=> null,
			'selectTriggers'					=> null,
			'selectGraphs'						=> null,
			'selectMacros'						=> null,
			'selectDashboards'					=> null,
			'selectInterfaces'					=> null,
			'selectInventory'					=> null,
			'selectHttpTests'					=> null,
			'selectDiscoveryRule'				=> null,
			'selectHostDiscovery'				=> null,
			'selectTags'						=> null,
			'selectInheritedTags'				=> null,
			'selectValueMaps'					=> null,
			'countOutput'						=> false,
			'groupCount'						=> false,
			'preservekeys'						=> false,
			'sortfield'							=> '',
			'sortorder'							=> '',
			'limit'								=> null,
			'limitSelects'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		$this->validateGet($options);

		$this->checkDeprecatedParam($options, 'selectGroups');

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM hosts_groups hgg'.
						' JOIN rights r'.
							' ON r.id=hgg.groupid'.
								' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE h.hostid=hgg.hostid'.
					' GROUP BY hgg.hostid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
						' AND MAX(r.permission)>='.zbx_dbstr($permission).
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

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=h.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['groupid'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			$sqlParts['where'][] = dbConditionId('h.proxy_hostid', $options['proxyids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['from']['hosts_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['templateids']);
			$sqlParts['where']['hht'] = 'h.hostid=ht.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['templateid'] = 'ht.templateid';
			}
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['left_join']['interface'] = ['alias' => 'hi', 'table' => 'interface', 'using' => 'hostid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];

			$sqlParts['where'][] = dbConditionInt('hi.interfaceid', $options['interfaceids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// httptestids
		if (!is_null($options['httptestids'])) {
			zbx_value2array($options['httptestids']);

			$sqlParts['from']['httptest'] = 'httptest ht';
			$sqlParts['where'][] = dbConditionInt('ht.httptestid', $options['httptestids']);
			$sqlParts['where']['aht'] = 'ht.hostid=h.hostid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
		}

		// dserviceids
		if (!is_null($options['dserviceids'])) {
			zbx_value2array($options['dserviceids']);

			$sqlParts['from']['dservices'] = 'dservices ds';
			$sqlParts['from']['interface'] = 'interface i';
			$sqlParts['where'][] = dbConditionInt('ds.dserviceid', $options['dserviceids']);
			$sqlParts['where']['dsh'] = 'ds.ip=i.ip';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['dserviceid'] = 'ds.dserviceid';
			}
		}

		// maintenanceids
		if (!is_null($options['maintenanceids'])) {
			zbx_value2array($options['maintenanceids']);

			$sqlParts['from']['maintenances_hosts'] = 'maintenances_hosts mh';
			$sqlParts['where'][] = dbConditionInt('mh.maintenanceid', $options['maintenanceids']);
			$sqlParts['where']['hmh'] = 'h.hostid=mh.hostid';

			if ($options['groupCount']) {
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

		// with_items, with_simple_graph_items, with_monitored_items
		if ($options['with_items'] !== null
				|| $options['with_simple_graph_items'] !== null
				|| $options['with_monitored_items'] !== null) {

			if ($options['with_items'] !== null) {
				$where_and =
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]);
			}
			elseif ($options['with_monitored_items'] !== null) {
				$where_and =
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
					' AND '.dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]);
			}
			elseif ($options['with_simple_graph_items'] !== null) {
				$where_and =
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
					' AND '.dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]).
					' AND '.dbConditionInt('i.value_type', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			}

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i'.
				' WHERE h.hostid=i.hostid'.
					$where_and.
				')';
		}

		// with_item_prototypes, with_simple_graph_item_prototypes
		if ($options['with_item_prototypes'] !== null || $options['with_simple_graph_item_prototypes'] !== null) {
			if ($options['with_item_prototypes'] !== null) {
				$where_and =
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			}
			elseif ($options['with_simple_graph_item_prototypes'] !== null) {
				$where_and =
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
					' AND '.dbConditionInt('i.status', [ITEM_STATUS_ACTIVE]).
					' AND '.dbConditionInt('i.value_type', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
			}

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i'.
				' WHERE h.hostid=i.hostid'.
					$where_and.
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
		if ($options['with_graphs'] !== null) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i,graphs_items gi,graphs g'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=gi.itemid '.
					' AND gi.graphid=g.graphid'.
					' AND '.dbConditionInt('g.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
				')';
		}

		// with_graph_prototypes
		if ($options['with_graph_prototypes'] !== null) {
			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM items i,graphs_items gi,graphs g'.
				' WHERE i.hostid=h.hostid'.
					' AND i.itemid=gi.itemid '.
					' AND gi.graphid=g.graphid'.
					' AND '.dbConditionInt('g.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
				')';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hosts h', $options, $sqlParts);

			if (zbx_db_search('interface hi', $options, $sqlParts)) {
				$sqlParts['left_join']['interface'] = ['alias' => 'hi', 'table' => 'interface', 'using' => 'hostid'];
				$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
			}
		}

		// search inventory
		if ($options['searchInventory'] !== null) {
			$sqlParts['from']['host_inventory'] = 'host_inventory hii';
			$sqlParts['where']['hii'] = 'h.hostid=hii.hostid';

			zbx_db_search('host_inventory hii',
				[
					'search' => $options['searchInventory'],
					'startSearch' => $options['startSearch'],
					'excludeSearch' => $options['excludeSearch'],
					'searchWildcardsEnabled' => $options['searchWildcardsEnabled'],
					'searchByAny' => $options['searchByAny']
				],
				$sqlParts
			);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('hosts h', $options, $sqlParts);

			if (array_key_exists('hostid', $options['filter'])) {
				unset($options['filter']['hostid']);
			}

			if ($this->dbFilter('interface hi', $options, $sqlParts)) {
				$sqlParts['left_join']['interface'] = ['alias' => 'hi', 'table' => 'interface', 'using' => 'hostid'];
				$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
			}

			if (array_key_exists('active_available', $options['filter'])
					&& $options['filter']['active_available'] !== null) {
				$this->dbFilter('host_rtdata hr', ['filter' => [
						'active_available' => $options['filter']['active_available']
					]] + $options,
					$sqlParts
				);
			}
		}

		if ($options['tags'] !== null && $options['tags']) {
			if ($options['inheritedTags']) {
				$sqlParts['left_join'][] = ['alias' => 'ht2', 'table' => 'hosts_templates', 'using' => 'hostid'];
				$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];

				$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'h',
					'host_tag', 'hostid', 'ht2.templateid'
				);
			}
			else {
				$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'h',
					'host_tag', 'hostid'
				);
			}
		}

		// limit
		if (!zbx_ctype_digit($options['limit']) || !$options['limit']) {
			$options['limit'] = null;
		}

		/*
		 * Cleaning the output from write-only properties.
		 */
		$write_only_keys = ['tls_psk_identity', 'tls_psk', 'name_upper'];

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$all_keys = array_keys(DB::getSchema($this->tableName())['fields']);
			$all_keys[] = 'inventory_mode';
			$all_keys[] = 'active_available';
			$options['output'] = array_diff($all_keys, $write_only_keys);
		}
		/*
		* For internal calls of API method, is possible to get the write-only fields if they were specified in output.
		* Specify write-only fields in output only if they will not appear in debug mode.
		*/
		elseif (is_array($options['output']) && APP::getMode() === APP::EXEC_MODE_API) {
			$options['output'] = array_diff($options['output'], $write_only_keys);
		}

		$sqlParts = $this->applyQueryFilterOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

		// Return count or grouped counts via direct SQL count.
		if ($options['countOutput'] && !$this->requiresPostSqlFiltering($options)) {
			$res = DBselect(self::createSelectQueryFromParts($sqlParts), $options['limit']);
			while ($host = DBfetch($res)) {
				if ($options['groupCount']) {
					$result[] = $host;
				}
				else {
					$result = $host['rowscount'];
				}
			}

			return $result;
		}

		$result = zbx_toHash($this->customFetch(self::createSelectQueryFromParts($sqlParts), $options), 'hostid');

		// Return count for post SQL filtered result sets.
		if ($options['countOutput']) {
			return (string) count($result);
		}

		// Hosts share table with host prototypes. Therefore remove host unrelated fields.
		if ($this->outputIsRequested('discover', $options['output'])) {
			foreach ($result as &$row) {
				unset($row['discover']);
			}

			unset($row);
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['name_upper'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if ($options['filter'] && array_key_exists('inventory_mode', $options['filter'])) {
			if ($options['filter']['inventory_mode'] !== null) {
				$inventory_mode_query = (array) $options['filter']['inventory_mode'];

				$inventory_mode_where = [];
				$null_position = array_search(HOST_INVENTORY_DISABLED, $inventory_mode_query);

				if ($null_position !== false) {
					unset($inventory_mode_query[$null_position]);
					$inventory_mode_where[] = 'hinv.inventory_mode IS NULL';
				}

				if ($null_position === false || $inventory_mode_query) {
					$inventory_mode_where[] = dbConditionInt('hinv.inventory_mode', $inventory_mode_query);
				}

				$sqlParts['where'][] = (count($inventory_mode_where) > 1)
					? '('.implode(' OR ', $inventory_mode_where).')'
					: $inventory_mode_where[0];
			}
		}

		return $sqlParts;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		$upcased_index = array_search($tableAlias.'.name_upper', $sqlParts['select']);

		if ($upcased_index !== false) {
			unset($sqlParts['select'][$upcased_index]);
		}

		if (!$options['countOutput'] && $this->outputIsRequested('inventory_mode', $options['output'])) {
			$sqlParts['select']['inventory_mode'] =
				dbConditionCoalesce('hinv.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode');
		}

		if ((!$options['countOutput'] && $this->outputIsRequested('inventory_mode', $options['output']))
				|| ($options['filter'] && array_key_exists('inventory_mode', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'hinv', 'table' => 'host_inventory', 'using' => 'hostid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if ((!$options['countOutput'] && $this->outputIsRequested('active_available', $options['output']))
				|| (is_array($options['filter']) && array_key_exists('active_available', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'hr', 'table' => 'host_rtdata', 'using' => 'hostid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('inventory_mode', $options['output'])) {
				$sqlParts['select']['inventory_mode'] =
					dbConditionCoalesce('hinv.inventory_mode', HOST_INVENTORY_DISABLED, 'inventory_mode');
			}

			if ($this->outputIsRequested('active_available', $options['output'])) {
				$sqlParts = $this->addQuerySelect('hr.active_available', $sqlParts);
			}
		}

		return $sqlParts;
	}

	/**
	 * Add host.
	 *
	 * @param array  $hosts                                 An array with hosts data.
	 * @param string $hosts[]['host']                       Host technical name.
	 * @param string $hosts[]['name']                       Host visible name (optional).
	 * @param array  $hosts[]['groups']                     An array of host group objects with IDs that host will be
	 *                                                      added to.
	 * @param int    $hosts[]['status']                     Status of the host (optional).
	 * @param array  $hosts[]['interfaces']                 An array of host interfaces data.
	 * @param int    $hosts[]['interfaces']['type']         Interface type.
	 * @param int    $hosts[]['interfaces']['main']         Is this the default interface to use.
	 * @param string $hosts[]['interfaces']['ip']           Interface IP (optional).
	 * @param int    $hosts[]['interfaces']['port']         Interface port (optional).
	 * @param int    $hosts[]['interfaces']['useip']        Interface should use IP (optional).
	 * @param string $hosts[]['interfaces']['dns']          Interface should use DNS (optional).
	 * @param int    $hosts[]['interfaces']['details']      Interface additional fields (optional).
	 * @param int    $hosts[]['proxy_hostid']               ID of the proxy that is used to monitor the host (optional).
	 * @param int    $hosts[]['ipmi_authtype']              IPMI authentication type (optional).
	 * @param int    $hosts[]['ipmi_privilege']             IPMI privilege (optional).
	 * @param string $hosts[]['ipmi_username']              IPMI username (optional).
	 * @param string $hosts[]['ipmi_password']              IPMI password (optional).
	 * @param array  $hosts[]['tags']                       An array of tags (optional).
	 * @param string $hosts[]['tags'][]['tag']              Tag name.
	 * @param string $hosts[]['tags'][]['value']            Tag value.
	 * @param array  $hosts[]['inventory']                  An array of host inventory data (optional).
	 * @param array  $hosts[]['macros']                     An array of host macros (optional).
	 * @param string $hosts[]['macros'][]['macro']          Host macro (required if "macros" is set).
	 * @param array  $hosts[]['templates']                  An array of template objects with IDs that will be linked
	 *                                                      to host (optional).
	 * @param string $hosts[]['templates'][]['templateid']  Template ID (required if "templates" is set).
	 * @param string $hosts[]['tls_connect']                Connections to host (optional).
	 * @param string $hosts[]['tls_accept']                 Connections from host (optional).
	 * @param string $hosts[]['tls_psk_identity']           PSK identity (required if "PSK" type is set).
	 * @param string $hosts[]['tls_psk']                    PSK (required if "PSK" type is set).
	 * @param string $hosts[]['tls_issuer']                 Certificate issuer (optional).
	 * @param string $hosts[]['tls_subject']                Certificate subject (optional).
	 *
	 * @return array
	 */
	public function create($hosts) {
		$this->validateCreate($hosts);

		$hosts_groups = [];
		$hosts_tags = [];
		$hosts_interfaces = [];
		$hosts_inventory = [];
		$templates_hostids = [];

		$hosts_rtdata = [];

		$hostids = DB::insert('hosts', $hosts);

		foreach ($hosts as $index => &$host) {
			$host['hostid'] = $hostids[$index];
			$hosts_rtdata[$index] = ['hostid' => $hostids[$index]];

			foreach ($host['groups'] as $group) {
				$hosts_groups[] = [
					'hostid' => $host['hostid'],
					'groupid' => $group['groupid']
				];
			}

			if (array_key_exists('tags', $host)) {
				foreach (zbx_toArray($host['tags']) as $tag) {
					$hosts_tags[] = ['hostid' => $host['hostid']] + $tag;
				}
			}

			if (array_key_exists('interfaces', $host)) {
				foreach (zbx_toArray($host['interfaces']) as $interface) {
					$hosts_interfaces[] = ['hostid' => $host['hostid']] + $interface;
				}
			}

			if (array_key_exists('templates', $host)) {
				foreach (zbx_toArray($host['templates']) as $template) {
					$templates_hostids[$template['templateid']][] = $host['hostid'];
				}
			}

			$host_inventory = [];
			if (array_key_exists('inventory', $host) && $host['inventory']) {
				$host_inventory = $host['inventory'];
				$host_inventory['inventory_mode'] = HOST_INVENTORY_MANUAL;
			}

			if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] != HOST_INVENTORY_DISABLED) {
				$host_inventory['inventory_mode'] = $host['inventory_mode'];
			}

			if (array_key_exists('inventory_mode', $host_inventory)) {
				$hosts_inventory[] = ['hostid' => $host['hostid']] + $host_inventory;
			}
		}
		unset($host);

		DB::insertBatch('hosts_groups', $hosts_groups);

		if ($hosts_tags) {
			DB::insert('host_tag', $hosts_tags);
		}

		if ($hosts_interfaces) {
			API::HostInterface()->create($hosts_interfaces);
		}

		$this->createHostMacros($hosts);

		while ($templates_hostids) {
			$templateid = key($templates_hostids);
			$link_hostids = reset($templates_hostids);
			$link_templateids = [$templateid];
			unset($templates_hostids[$templateid]);

			foreach ($templates_hostids as $templateid => $hostids) {
				if ($link_hostids === $hostids) {
					$link_templateids[] = $templateid;
					unset($templates_hostids[$templateid]);
				}
			}

			self::link($link_templateids, $link_hostids);
		}

		if ($hosts_inventory) {
			DB::insert('host_inventory', $hosts_inventory, false);
		}

		DB::insertBatch('host_rtdata', $hosts_rtdata, false);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST, $hosts);

		return ['hostids' => array_column($hosts, 'hostid')];
	}

	/**
	 * Creates user macros for hosts.
	 *
	 * @param array  $hosts
	 * @param array  $hosts[]['hostid']
	 * @param array  $hosts[]['macros']             (optional)
	 */
	private function createHostMacros(array $hosts): void {
		$ins_hostmacros = [];

		foreach ($hosts as $host) {
			if (array_key_exists('macros', $host)) {
				foreach ($host['macros'] as $macro) {
					$ins_hostmacros[] = ['hostid' => $host['hostid']] + $macro;
				}
			}
		}

		if ($ins_hostmacros) {
			DB::insert('hostmacro', $ins_hostmacros);
		}
	}

	/**
	 * Update host.
	 *
	 * @param array  $hosts                                       An array with hosts data.
	 * @param string $hosts[]['hostid']                           Host ID.
	 * @param string $hosts[]['host']                             Host technical name (optional).
	 * @param string $hosts[]['name']                             Host visible name (optional).
	 * @param array  $hosts[]['groups']                           An array of host group objects with IDs that host will be replaced to.
	 * @param int    $hosts[]['status']                           Status of the host (optional).
	 * @param array  $hosts[]['interfaces']                       An array of host interfaces data to be replaced.
	 * @param int    $hosts[]['interfaces']['type']               Interface type.
	 * @param int    $hosts[]['interfaces']['main']               Is this the default interface to use.
	 * @param string $hosts[]['interfaces']['ip']                 Interface IP (optional).
	 * @param int    $hosts[]['interfaces']['port']               Interface port (optional).
	 * @param int    $hosts[]['interfaces']['useip']              Interface should use IP (optional).
	 * @param string $hosts[]['interfaces']['dns']                Interface should use DNS (optional).
	 * @param int    $hosts[]['interfaces']['details']            Interface additional fields (optional).
	 * @param int    $hosts[]['proxy_hostid']                     ID of the proxy that is used to monitor the host (optional).
	 * @param int    $hosts[]['ipmi_authtype']                    IPMI authentication type (optional).
	 * @param int    $hosts[]['ipmi_privilege']                   IPMI privilege (optional).
	 * @param string $hosts[]['ipmi_username']                    IPMI username (optional).
	 * @param string $hosts[]['ipmi_password']                    IPMI password (optional).
	 * @param array  $hosts[]['tags']                             An array of tags (optional).
	 * @param string $hosts[]['tags'][]['tag']                    Tag name.
	 * @param string $hosts[]['tags'][]['value']                  Tag value.
	 * @param array  $hosts[]['inventory']                        An array of host inventory data (optional).
	 * @param array  $hosts[]['macros']                           An array of host macros (optional).
	 * @param string $hosts[]['macros'][]['macro']                Host macro (required if "macros" is set).
	 * @param array  $hosts[]['templates']                        An array of template objects with IDs that will be linked to host (optional).
	 * @param string $hosts[]['templates'][]['templateid']        Template ID (required if "templates" is set).
	 * @param array  $hosts[]['templates_clear']                  Templates to unlink and clear from the host (optional).
	 * @param string $hosts[]['templates_clear'][]['templateid']  Template ID (required if "templates" is set).
	 * @param string $hosts[]['tls_connect']                      Connections to host (optional).
	 * @param string $hosts[]['tls_accept']                       Connections from host (optional).
	 * @param string $hosts[]['tls_psk_identity']                 PSK identity (required if "PSK" type is set).
	 * @param string $hosts[]['tls_psk']                          PSK (required if "PSK" type is set).
	 * @param string $hosts[]['tls_issuer']                       Certificate issuer (optional).
	 * @param string $hosts[]['tls_subject']                      Certificate subject (optional).
	 *
	 * @return array
	 */
	public function update($hosts) {
		$hosts = $this->validateUpdate($hosts, $db_hosts);

		$inventories = [];
		foreach ($hosts as &$host) {
			// If visible name is not given or empty it should be set to host name.
			if (array_key_exists('host', $host) && (!array_key_exists('name', $host) || trim($host['name']) === '')) {
				$host['name'] = $host['host'];
			}

			// Fetch fields required to update host inventory.
			if (array_key_exists('inventory', $host)) {
				$inventory = $host['inventory'];
				$inventory['hostid'] = $host['hostid'];

				$inventories[] = $inventory;
			}
		}
		unset($host);

		$inventories = $this->extendObjects('host_inventory', $inventories, ['inventory_mode']);
		$inventories = zbx_toHash($inventories, 'hostid');

		$this->updateHostMacros($hosts, $db_hosts);

		foreach ($hosts as &$host) {
			unset($host['macros']);
		}
		unset($host);

		$hosts = $this->extendObjectsByKey($hosts, $db_hosts, 'hostid', ['tls_connect', 'tls_accept', 'tls_issuer',
			'tls_subject', 'tls_psk_identity', 'tls_psk'
		]);

		foreach ($hosts as $host) {
			// Extend host inventory with the required data.
			if (array_key_exists('inventory', $host) && $host['inventory']) {
				// If inventory mode is HOST_INVENTORY_DISABLED, database record is not created.
				if (array_key_exists('inventory_mode', $inventories[$host['hostid']])
						&& ($inventories[$host['hostid']]['inventory_mode'] == HOST_INVENTORY_MANUAL
							|| $inventories[$host['hostid']]['inventory_mode'] == HOST_INVENTORY_AUTOMATIC)) {
					$host['inventory'] = $inventories[$host['hostid']];
				}
			}

			$data = $host;
			$data['hosts'] = ['hostid' => $host['hostid']];
			$result = $this->massUpdate($data);

			if (!$result) {
				self::exception(ZBX_API_ERROR_INTERNAL, _('Host update failed.'));
			}
		}

		$this->updateTags($hosts, $db_hosts);

		return ['hostids' => array_column($hosts, 'hostid')];
	}

	/**
	 * Updates user macros for hosts.
	 *
	 * @param array  $hosts
	 * @param array  $hosts[]['hostid']
	 * @param array  $hosts[]['macros']             (optional)
	 * @param array  $db_hosts
	 * @param array  $db_hosts[<hostid>]['macros']  An array of host macros indexed by hostmacroid.
	 */
	private function updateHostMacros(array $hosts, array $db_hosts): void {
		$ins_hostmacros = [];
		$upd_hostmacros = [];
		$del_hostmacroids = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('macros', $host)) {
				continue;
			}

			$db_host = $db_hosts[$host['hostid']];

			foreach ($host['macros'] as $hostmacro) {
				if (array_key_exists('hostmacroid', $hostmacro)) {
					$db_hostmacro = $db_host['macros'][$hostmacro['hostmacroid']];
					unset($db_host['macros'][$hostmacro['hostmacroid']]);

					$upd_hostmacro = DB::getUpdatedValues('hostmacro', $hostmacro, $db_hostmacro);

					if ($upd_hostmacro) {
						$upd_hostmacros[] = [
							'values' => $upd_hostmacro,
							'where' => ['hostmacroid' => $hostmacro['hostmacroid']]
						];
					}
				}
				else {
					$ins_hostmacros[] = $hostmacro + ['hostid' => $host['hostid']];
				}
			}

			$del_hostmacroids = array_merge($del_hostmacroids, array_keys($db_host['macros']));
		}

		if ($del_hostmacroids) {
			DB::delete('hostmacro', ['hostmacroid' => $del_hostmacroids]);
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		if ($ins_hostmacros) {
			DB::insert('hostmacro', $ins_hostmacros);
		}
	}

	/**
	 * @param array $hosts
	 * @param array $db_hosts
	 */
	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		parent::addAffectedObjects($hosts, $db_hosts);
		self::addAffectedTemplates($hosts, $db_hosts);
	}

	/**
	 * Update table "hosts_templates" and change objects of linked or unlinked templates on target hosts.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected static function updateTemplates(array &$hosts, array $db_hosts = null): void {
		parent::updateTemplates($hosts, $db_hosts);

		$ins_links = [];
		$del_links = [];
		$del_links_clear = [];

		foreach ($hosts as $host) {
			if (!array_key_exists('templates', $host) && !array_key_exists('templates_clear', $host)) {
				continue;
			}

			$db_templates = $db_hosts !== null
				? array_column($db_hosts[$host['hostid']]['templates'], null, 'templateid')
				: [];

			if (array_key_exists('templates', $host)) {
				foreach ($host['templates'] as $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						unset($db_templates[$template['templateid']]);
					}
					else {
						$ins_links[$template['templateid']][] = $host['hostid'];
					}
				}

				$templates_clear = array_key_exists('templates_clear', $host)
					? array_column($host['templates_clear'], null, 'templateid')
					: [];

				foreach ($db_templates as $del_template) {
					if (array_key_exists($del_template['templateid'], $templates_clear)) {
						$del_links_clear[$del_template['templateid']][] = $host['hostid'];
					}
					else {
						$del_links[$del_template['templateid']][] = $host['hostid'];
					}
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as $template) {
					$del_links_clear[$template['templateid']][] = $host['hostid'];
				}
			}
		}

		while ($del_links_clear) {
			$templateid = key($del_links_clear);
			$hostids = reset($del_links_clear);
			$templateids = [$templateid];
			unset($del_links_clear[$templateid]);

			foreach ($del_links_clear as $templateid => $_hostids) {
				if ($_hostids === $hostids) {
					$templateids[] = $templateid;
					unset($del_links_clear[$templateid]);
				}
			}

			self::unlinkTemplatesObjects($templateids, $hostids, true);
		}

		while ($del_links) {
			$templateid = key($del_links);
			$hostids = reset($del_links);
			$templateids = [$templateid];
			unset($del_links[$templateid]);

			foreach ($del_links as $templateid => $_hostids) {
				if ($_hostids === $hostids) {
					$templateids[] = $templateid;
					unset($del_links[$templateid]);
				}
			}

			self::unlinkTemplatesObjects($templateids, $hostids);
		}

		while ($ins_links) {
			$templateid = key($ins_links);
			$hostids = reset($ins_links);
			$templateids = [$templateid];
			unset($ins_links[$templateid]);

			foreach ($ins_links as $templateid => $_hostids) {
				if ($_hostids === $hostids) {
					$templateids[] = $templateid;
					unset($ins_links[$templateid]);
				}
			}

			self::linkTemplatesObjects($templateids, $hostids);
		}
	}

	/**
	 * Unlink or clear objects of given templates from given hosts.
	 *
	 * @param array      $templateids
	 * @param array|null $hostids
	 * @param bool       $clear
	 */
	public static function unlinkTemplatesObjects(array $templateids, array $hostids = null,
			bool $clear = false): void {
		$flags = $clear
			? [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE]
			: [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE];

		// triggers
		$db_triggers = DBselect(
			'SELECT DISTINCT f.triggerid'.
			' FROM functions f,items i'.
			' WHERE f.itemid=i.itemid'.
				' AND '.dbConditionId('i.hostid', $templateids)
		);

		$tpl_triggerids = DBfetchColumn($db_triggers, 'triggerid');
		$upd_triggers = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		if ($tpl_triggerids) {
			$sql_distinct = ($hostids !== null) ? ' DISTINCT' : '';
			$sql_from = ($hostids !== null) ? ',functions f,items i' : '';
			$sql_where = ($hostids !== null)
				? ' AND t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND '.dbConditionId('i.hostid', $hostids)
				: '';

			$db_triggers = DBSelect(
				'SELECT'.$sql_distinct.' t.triggerid,t.flags'.
				' FROM triggers t'.$sql_from.
				' WHERE '.dbConditionId('t.templateid', $tpl_triggerids).
					' AND '.dbConditionInt('t.flags', $flags).
					$sql_where
			);

			while ($db_trigger = DBfetch($db_triggers)) {
				if ($clear) {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']] = true;
				}
				else {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']] = [
						'values' => ['templateid' => 0],
						'where' => ['triggerid' => $db_trigger['triggerid']]
					];
				}
			}
		}

		if ($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]) {
			if ($clear) {
				CTriggerManager::delete(array_keys($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]));
			}
			else {
				DB::update('triggers', $upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]);
			}
		}

		if ($upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			if ($clear) {
				CTriggerPrototypeManager::delete(array_keys($upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
			}
			else {
				DB::update('triggers', $upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			}
		}

		// graphs
		$db_tpl_graphs = DBselect(
			'SELECT DISTINCT g.graphid'.
			' FROM graphs g,graphs_items gi,items i'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=i.itemid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('g.flags', $flags)
		);

		$tpl_graphids = [];

		while ($db_tpl_graph = DBfetch($db_tpl_graphs)) {
			$tpl_graphids[] = $db_tpl_graph['graphid'];
		}

		if ($tpl_graphids) {
			$upd_graphs = [
				ZBX_FLAG_DISCOVERY_NORMAL => [],
				ZBX_FLAG_DISCOVERY_PROTOTYPE => []
			];

			$sql = ($hostids !== null)
				? 'SELECT DISTINCT g.graphid,g.flags'.
					' FROM graphs g,graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND '.dbConditionId('g.templateid', $tpl_graphids).
						' AND '.dbConditionId('i.hostid', $hostids)
				: 'SELECT g.graphid,g.flags'.
					' FROM graphs g'.
					' WHERE '.dbConditionId('g.templateid', $tpl_graphids);

			$db_graphs = DBSelect($sql);

			while ($db_graph = DBfetch($db_graphs)) {
				if ($clear) {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']] = true;
				}
				else {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']] = [
						'values' => ['templateid' => 0],
						'where' => ['graphid' => $db_graph['graphid']]
					];
				}
			}

			if ($upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
				if ($clear) {
					CGraphPrototypeManager::delete(array_keys($upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
				}
				else {
					DB::update('graphs', $upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
				}
			}

			if ($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]) {
				if ($clear) {
					CGraphManager::delete(array_keys($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]));
				}
				else {
					DB::update('graphs', $upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]);
				}
			}
		}

		if ($clear) {
			CDiscoveryRule::clearTemplateObjects($templateids, $hostids);
			CItem::clearTemplateObjects($templateids, $hostids);
			CHttpTest::clearTemplateObjects($templateids, $hostids);
		}
		else {
			CDiscoveryRule::unlinkTemplateObjects($templateids, $hostids);
			CItem::unlinkTemplateObjects($templateids, $hostids);
			CHttpTest::unlinkTemplateObjects($templateids, $hostids);
		}
	}

	/**
	 * Add objects of given templates to given hosts.
	 *
	 * @param array $templateids
	 * @param array $hostids
	 */
	private static function linkTemplatesObjects(array $templateids, array $hostids): void {
		// TODO: Modify parameters of syncTemplates methods when complete audit log will be implementing for hosts.
		$link_request = [
			'templateids' => $templateids,
			'hostids' => $hostids
		];

		foreach ($templateids as $templateid) {
			// Fist link web items, so that later regular items can use web item as their master item.
			Manager::HttpTest()->link($templateid, $hostids);
		}

		CItem::linkTemplateObjects($templateids, $hostids);
		$ruleids = API::DiscoveryRule()->syncTemplates($templateids, $hostids);

		if ($ruleids) {
			CItemPrototype::linkTemplateObjects($templateids, $hostids);
			API::HostPrototype()->syncTemplates($ruleids, $hostids);
		}

		API::Trigger()->syncTemplates($link_request);

		if ($ruleids) {
			API::TriggerPrototype()->syncTemplates($link_request);
			API::GraphPrototype()->syncTemplates($link_request);
		}

		API::Graph()->syncTemplates($link_request);

		CTriggerGeneral::syncTemplateDependencies($link_request['templateids'], $link_request['hostids']);
	}

	/**
	 * @param array $data
	 * @param array $data['hosts']
	 * @param array $data['groups']     (optional)
	 * @param array $data['templates']  (optional)
	 * @param array $data['interfaces'] (optional)
	 * @param array $data['macros']     (optional)
	 *
	 * @return array
	 */
	public function massAdd(array $data): array {
		if (!array_key_exists('hosts', $data) || !$data['hosts']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Incorrect arguments passed to function.'));
		}

		$hosts = zbx_toArray($data['hosts']);
		$hostids = array_column($hosts, 'hostid');

		self::checkPermissions($hostids, _('You do not have permission to perform this operation.'));

		if (array_key_exists('interfaces', $data) && $data['interfaces']) {
			API::HostInterface()->massAdd([
				'hosts' => $data['hosts'],
				'interfaces' => zbx_toArray($data['interfaces'])
			]);
		}

		if (array_key_exists('groups', $data) && $data['groups']) {
			$options = [
				'groups' => $data['groups'],
				'hosts' => array_map(
					static function($host) {
						return array_intersect_key($host, array_flip(['hostid']));
					},
					$data['hosts']
				)
			];

			API::HostGroup()->massAdd($options);
		}

		if (array_key_exists('templates', $data) && $data['templates']) {
			self::link(array_column(zbx_toArray($data['templates']), 'templateid'), $hostids);
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			$data['macros'] = zbx_toArray($data['macros']);

			$hostMacrosToAdd = [];
			foreach ($data['macros'] as $hostMacro) {
				foreach ($hostids as $hostid) {
					$hostMacro['hostid'] = $hostid;
					$hostMacrosToAdd[] = $hostMacro;
				}
			}

			API::UserMacro()->create($hostMacrosToAdd);
		}

		return ['hostids' => $hostids];
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
	 * @param int    $hosts['fields']['details']		Details. OPTIONAL
	 * @param int    $hosts['fields']['proxy_hostid']	Proxy Host ID. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_authtype']	IPMI authentication type. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_privilege']	IPMI privilege. OPTIONAL
	 * @param string $hosts['fields']['ipmi_username']	IPMI username. OPTIONAL
	 * @param string $hosts['fields']['ipmi_password']	IPMI password. OPTIONAL
	 *
	 * @return boolean
	 */
	public function massUpdate($data) {
		if (!array_key_exists('hosts', $data) || !is_array($data['hosts'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Field "%1$s" is mandatory.', 'hosts'));
		}

		$hosts = zbx_toArray($data['hosts']);
		$inputHostIds = zbx_objectValues($hosts, 'hostid');
		$hostids = array_unique($inputHostIds);

		sort($hostids);

		$db_hosts = $this->get([
			'output' => ['hostid', 'proxy_hostid', 'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
				'ipmi_password', 'name', 'description', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject',
				'tls_psk_identity', 'tls_psk', 'inventory_mode'
			],
			'hostids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($hosts as $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
			}
		}

		// Check inventory mode value.
		if (array_key_exists('inventory_mode', $data)) {
			$valid_inventory_modes = [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC];
			$inventory_mode = new CLimitedSetValidator([
				'values' => $valid_inventory_modes,
				'messageInvalid' => _s('Incorrect value for field "%1$s": %2$s.', 'inventory_mode',
					_s('value must be one of %1$s', implode(', ', $valid_inventory_modes)))
			]);
			$this->checkValidator($data['inventory_mode'], $inventory_mode);
		}

		// Check connection fields only for massupdate action.
		if (array_key_exists('tls_connect', $data) || array_key_exists('tls_accept', $data)
				|| array_key_exists('tls_psk_identity', $data) || array_key_exists('tls_psk', $data)
				|| array_key_exists('tls_issuer', $data) || array_key_exists('tls_subject', $data)) {
			if (!array_key_exists('tls_connect', $data) || !array_key_exists('tls_accept', $data)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _(
					'Cannot update host encryption settings. Connection settings for both directions should be specified.'
				));
			}

			// Clean PSK fields.
			if ($data['tls_connect'] != HOST_ENCRYPTION_PSK && !($data['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				$data['tls_psk_identity'] = '';
				$data['tls_psk'] = '';
			}

			// Clean certificate fields.
			if ($data['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
					&& !($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				$data['tls_issuer'] = '';
				$data['tls_subject'] = '';
			}
		}

		$this->validateEncryption([$data]);

		if (array_key_exists('groups', $data) && !$data['groups'] && $db_hosts) {
			$host = reset($db_hosts);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Host "%1$s" cannot be without host group.', $host['host'])
			);
		}

		// Property 'auto_compress' is not supported for hosts.
		if (array_key_exists('auto_compress', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
		}

		/*
		 * Update hosts properties
		 */
		if (isset($data['name'])) {
			if (count($hosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update visible host name.'));
			}
		}

		if (array_key_exists('host', $data)) {
			$host_name_parser = new CHostNameParser();

			if ($host_name_parser->parse($data['host']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect characters used for host name "%1$s".', $data['host'])
				);
			}

			if (count($hosts) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update host name.'));
			}

			$curHost = reset($hosts);

			$sameHostnameHost = $this->get([
				'output' => ['hostid'],
				'filter' => ['host' => $data['host']],
				'nopermissions' => true,
				'limit' => 1
			]);
			$sameHostnameHost = reset($sameHostnameHost);
			if ($sameHostnameHost && (bccomp($sameHostnameHost['hostid'], $curHost['hostid']) != 0)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host "%1$s" already exists.', $data['host']));
			}

			// can't add host with the same name as existing template
			$sameHostnameTemplate = API::Template()->get([
				'output' => ['templateid'],
				'filter' => ['host' => $data['host']],
				'nopermissions' => true,
				'limit' => 1
			]);
			if ($sameHostnameTemplate) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Template "%1$s" already exists.', $data['host']));
			}
		}

		if (isset($data['groups'])) {
			$updateGroups = $data['groups'];
		}

		if (isset($data['interfaces'])) {
			$updateInterfaces = $data['interfaces'];
		}

		if (array_key_exists('templates_clear', $data)) {
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
				$updateInventory = [];
			}
			$updateInventory['inventory_mode'] = $data['inventory_mode'];
		}

		unset($data['hosts'], $data['groups'], $data['interfaces'], $data['templates_clear'], $data['templates'],
			$data['macros'], $data['inventory'], $data['inventory_mode']);

		if (!zbx_empty($data)) {
			DB::update('hosts', [
				'values' => $data,
				'where' => ['hostid' => $hostids]
			]);
		}

		/*
		 * Update template linkage
		 */
		if (isset($updateTemplatesClear)) {
			$templateIdsClear = array_column($updateTemplatesClear, 'templateid');

			if ($updateTemplatesClear) {
				$this->massRemove(['hostids' => $hostids, 'templateids_clear' => $templateIdsClear]);
			}
		}
		else {
			$templateIdsClear = [];
		}

		// unlink templates
		if (isset($updateTemplates)) {
			$hostTemplates = API::Template()->get([
				'hostids' => $hostids,
				'output' => ['templateid'],
				'preservekeys' => true
			]);

			$hostTemplateids = array_keys($hostTemplates);
			$newTemplateids = array_column($updateTemplates, 'templateid');

			$templatesToDel = array_diff($hostTemplateids, $newTemplateids);
			$templatesToDel = array_diff($templatesToDel, $templateIdsClear);

			if ($templatesToDel) {
				$result = $this->massRemove([
					'hostids' => $hostids,
					'templateids' => $templatesToDel
				]);
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot unlink template'));
				}
			}
		}

		/*
		 * update interfaces
		 */
		if (isset($updateInterfaces)) {
			foreach($hostids as $hostid) {
				API::HostInterface()->replaceHostInterfaces([
					'hostid' => $hostid,
					'interfaces' => $updateInterfaces
				]);
			}
		}

		// link new templates
		if (isset($updateTemplates)) {
			$result = $this->massAdd([
				'hosts' => $hosts,
				'templates' => $updateTemplates
			]);

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot link template'));
			}
		}

		// macros
		if (isset($updateMacros)) {
			DB::delete('hostmacro', ['hostid' => $hostids]);

			$this->massAdd([
				'hosts' => $hosts,
				'macros' => $updateMacros
			]);
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
				$automaticHostIds = [];
				if ($updateInventory['inventory_mode'] === null) {
					foreach ($hostids as $hostid) {
						// if inventory is disabled for one of the updated hosts, throw an exception
						if (!isset($existingInventoriesDb[$hostid])) {
							$host = get_host_by_hostid($hostid);
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Inventory disabled for host "%1$s".', $host['host']
							));
						}
						// if inventory mode is set to automatic, save its ID for later usage
						elseif ($existingInventoriesDb[$hostid]['inventory_mode'] == HOST_INVENTORY_AUTOMATIC) {
							$automaticHostIds[] = $hostid;
						}
					}
				}

				$inventoriesToSave = [];
				foreach ($hostids as $hostid) {
					$hostInventory = $updateInventory;
					$hostInventory['hostid'] = $hostid;

					// if no 'inventory_mode' has been passed, set inventory 'inventory_mode' from DB
					if ($updateInventory['inventory_mode'] === null) {
						$hostInventory['inventory_mode'] = $existingInventoriesDb[$hostid]['inventory_mode'];
					}

					$inventoriesToSave[$hostid] = $hostInventory;
				}

				// when updating automatic inventory, ignore fields that have items linked to them
				if ($updateInventory['inventory_mode'] == HOST_INVENTORY_AUTOMATIC
						|| ($updateInventory['inventory_mode'] === null && $automaticHostIds)) {

					$itemsToInventories = API::item()->get([
						'output' => ['inventory_link', 'hostid'],
						'hostids' => $automaticHostIds ? $automaticHostIds : $hostids,
						'nopermissions' => true
					]);

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
					$hostid = $inventory['hostid'];
					if (isset($existingInventoriesDb[$hostid])) {
						DB::update('host_inventory', [
							'values' => $inventory,
							'where' => ['hostid' => $hostid]
						]);
					}
					else {
						DB::insert('host_inventory', [$inventory], false);
					}
				}
			}
		}

		/*
		 * Update host and host group linkage. This procedure should be done the last because user can unlink
		 * him self from a group with write permissions leaving only read permissions. Thus other procedures, like
		 * host-template linkage, inventory update, macros update, must be done before this.
		 */
		if (isset($updateGroups)) {
			$updateGroups = zbx_toArray($updateGroups);

			$hostGroups = API::HostGroup()->get([
				'output' => ['groupid'],
				'hostids' => $hostids
			]);
			$hostGroupIds = array_column($hostGroups, 'groupid');
			$newGroupIds = array_column($updateGroups, 'groupid');

			$groupsToAdd = array_diff($newGroupIds, $hostGroupIds);
			if ($groupsToAdd) {
				$this->massAdd([
					'hosts' => $hosts,
					'groups' => zbx_toObject($groupsToAdd, 'groupid')
				]);
			}

			$groupIdsToDelete = array_diff($hostGroupIds, $newGroupIds);
			if ($groupIdsToDelete) {
				$this->massRemove([
					'hostids' => $hostids,
					'groupids' => $groupIdsToDelete
				]);
			}
		}

		$new_hosts = [];
		foreach ($db_hosts as $hostid => $db_host) {
			$new_host = $data + $db_host;
			if ($new_host['status'] != $db_host['status']) {
				info(_s('Updated status of host "%1$s".', $new_host['host']));
			}

			$new_hosts[] = $new_host;
		}

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_HOST, $new_hosts, $db_hosts);

		return ['hostids' => $inputHostIds];
	}

	/**
	 * @param array $data
	 * @param array $data['hostids]            (optional)
	 * @param array $data['templateids']       (optional)
	 * @param array $data['templateids_clear'] (optional)
	 * @param array $data['interfaces']        (optional)
	 * @param array $data['groupids']          (optional)
	 * @param array $data['macros']            (optional)
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function massRemove(array $data): array {
		if (!array_key_exists('hostids', $data) || $data['hostids'] === null) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}

		self::checkPermissions($data['hostids'], _('No permissions to referred object or it does not exist!'));

		$data += array_fill_keys(['templateids', 'templateids_clear', 'interfaces', 'groupids', 'macros'], []);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if ($data['templateids'] || $data['templateids_clear']) {
			$data['templateids'] = zbx_toArray($data['templateids']);
			$data['templateids_clear'] = zbx_toArray($data['templateids_clear']);

			// Remove IDs of templates not allowed to be unlinked due to link_type.
			$prohibited_templateids = [];

			$hosts_templates = API::Host()->get([
				'selectParentTemplates' => ['templateid', 'link_type'],
				'hostids' => $data['hostids'],
				'preservekeys' => true,
				'nopermissions' => true
			]);

			foreach ($hosts_templates as $host_templates) {
				foreach ($host_templates['parentTemplates'] as $template) {
					if ($template['link_type'] == TEMPLATE_LINK_LLD) {
						$prohibited_templateids[$template['templateid']] = $template['templateid'];
					}
				}
			}

			if ($prohibited_templateids) {
				$data['templateids'] = array_diff($data['templateids'], $prohibited_templateids);
				$data['templateids_clear'] = array_diff($data['templateids_clear'], $prohibited_templateids);
			}

			$templateids = array_unique(array_merge($data['templateids'], $data['templateids_clear']));

			$count = API::Template()->get([
				'countOutput' => true,
				'templateids' => $templateids,
				'editable' => true
			]);

			if ($count != count($templateids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			if ($data['templateids']) {
				self::unlink($data['templateids'], $data['hostids']);
			}

			if ($data['templateids_clear']) {
				self::unlink($data['templateids_clear'], $data['hostids'], true);
			}
		}

		if ($data['macros']) {
			$macros = API::UserMacro()->get([
				'output' => ['hostmacroid'],
				'hostids' => $data['hostids'],
				'filter' => [
					'macro' => $data['macros']
				]
			]);

			$macroids = array_column($macros, 'hostmacroid');

			if ($macroids) {
				API::UserMacro()->delete($macroids);
			}
		}

		if ($data['interfaces']) {
			$options = [
				'hostids' => $data['hostids'],
				'interfaces' => zbx_toArray($data['interfaces'])
			];

			API::HostInterface()->massRemove($options);
		}

		if ($data['groupids']) {
			$options = [
				'hostids' => $data['hostids'],
				'groupids' => $data['groupids']
			];

			API::HostGroup()->massRemove($options);
		}

		return ['hostids' => $data['hostids']];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array      $hostids
	 * @param array|null $db_hosts
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$hostids, array &$db_hosts = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $hostids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = $this->get([
			'output' => ['hostid', 'host'],
			'hostids' => $hostids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_hosts) != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::validateDeleteForce($db_hosts);
	}

	/**
	 * @param array $db_hosts
	 */
	public static function validateDeleteForce(array $db_hosts): void {
		self::checkMaintenances(array_keys($db_hosts));
	}

	/**
	 * Check that no maintenance object will be left without hosts and host groups as the result of the given hosts
	 * deletion.
	 *
	 * @param array $hostids
	 *
	 * @throws APIException
	 */
	private static function checkMaintenances(array $hostids): void {
		$maintenance = DBfetch(DBselect(
			'SELECT m.maintenanceid,m.name'.
			' FROM maintenances m'.
			' WHERE NOT EXISTS ('.
				'SELECT NULL'.
				' FROM maintenances_hosts mh'.
				' WHERE m.maintenanceid=mh.maintenanceid'.
					' AND '.dbConditionId('mh.hostid', $hostids, true).
			')'.
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_groups mg'.
					' WHERE m.maintenanceid=mg.maintenanceid'.
				')'
		, 1));

		if ($maintenance) {
			$maintenance_hosts = DBfetchColumn(DBselect(
				'SELECT h.host'.
				' FROM maintenances_hosts mh,hosts h'.
				' WHERE mh.hostid=h.hostid'.
					' AND '.dbConditionId('mh.maintenanceid', [$maintenance['maintenanceid']])
			), 'host');

			self::exception(ZBX_API_ERROR_PARAMETERS, _n(
				'Cannot delete host %1$s because maintenance "%2$s" must contain at least one host or host group.',
				'Cannot delete hosts %1$s because maintenance "%2$s" must contain at least one host or host group.',
				'"'.implode('", "', $maintenance_hosts).'"', $maintenance['name'], count($maintenance_hosts)
			));
		}
	}

	/**
	 * @param array $hostids
	 *
	 * @return array
	 */
	public function delete(array $hostids): array {
		$this->validateDelete($hostids, $db_hosts);

		self::deleteForce($db_hosts);

		return ['hostids' => $hostids];
	}

	/**
	 * @param array $db_hosts
	 */
	public static function deleteForce(array $db_hosts): void {
		$hostids = array_keys($db_hosts);

		// delete the discovery rules first
		$del_rules = API::DiscoveryRule()->get([
			'output' => [],
			'hostids' => $hostids,
			'nopermissions' => true,
			'preservekeys' => true
		]);
		if ($del_rules) {
			CDiscoveryRuleManager::delete(array_keys($del_rules));
		}

		// delete the items
		$db_items = DB::select('items', [
			'output' => ['itemid', 'name'],
			'filter' => [
				'hostid' => $hostids,
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
				'type' => CItem::SUPPORTED_ITEM_TYPES
			],
			'preservekeys' => true
		]);

		if ($db_items) {
			CItem::deleteForce($db_items);
		}

		// delete web scenarios
		$db_httptests = DB::select('httptest', [
			'output' => ['httptestid', 'name'],
			'filter' => ['hostid' => $hostids],
			'preservekeys' => true
		]);

		if ($db_httptests) {
			CHttpTest::deleteForce($db_httptests);
		}

		// delete host from maps
		if (!empty($hostids)) {
			DB::delete('sysmaps_elements', [
				'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
				'elementid' => $hostids
			]);
		}

		// disable actions
		// actions from conditions
		$actionids = [];
		$sql = 'SELECT DISTINCT actionid'.
				' FROM conditions'.
				' WHERE conditiontype='.CONDITION_TYPE_HOST.
				' AND '.dbConditionString('value', $hostids);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		// actions from operations
		$sql = 'SELECT DISTINCT o.actionid'.
				' FROM operations o, opcommand_hst oh'.
				' WHERE o.operationid=oh.operationid'.
				' AND '.dbConditionInt('oh.hostid', $hostids);
		$dbActions = DBselect($sql);
		while ($dbAction = DBfetch($dbActions)) {
			$actionids[$dbAction['actionid']] = $dbAction['actionid'];
		}

		if (!empty($actionids)) {
			$update = [];
			$update[] = [
				'values' => ['status' => ACTION_STATUS_DISABLED],
				'where' => ['actionid' => $actionids]
			];
			DB::update('actions', $update);
		}

		// delete action conditions
		DB::delete('conditions', [
			'conditiontype' => CONDITION_TYPE_HOST,
			'value' => $hostids
		]);

		// delete action operation commands
		$operationids = [];
		$sql = 'SELECT DISTINCT oh.operationid'.
				' FROM opcommand_hst oh'.
				' WHERE '.dbConditionInt('oh.hostid', $hostids);
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('opcommand_hst', [
			'hostid' => $hostids
		]);

		// delete empty operations
		$delOperationids = [];
		$sql = 'SELECT DISTINCT o.operationid'.
				' FROM operations o'.
				' WHERE '.dbConditionInt('o.operationid', $operationids).
				' AND NOT EXISTS(SELECT oh.opcommand_hstid FROM opcommand_hst oh WHERE oh.operationid=o.operationid)';
		$dbOperations = DBselect($sql);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', [
			'operationid' => $delOperationids
		]);

		// delete host inventory
		DB::delete('host_inventory', ['hostid' => $hostids]);

		// delete host
		DB::delete('host_tag', ['hostid' => $hostids]);
		DB::update('hosts', [
			'values' => ['templateid' => 0],
			'where' => ['hostid' => $hostids, 'flags' => ZBX_FLAG_DISCOVERY_PROTOTYPE]
		]);
		DB::delete('hosts', ['hostid' => $hostids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_HOST, $db_hosts);
	}

	/**
	 * Retrieves and adds additional requested data to the result set.
	 *
	 * @param array  $options
	 * @param array  $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$this->addRelatedGroups($options, $result, 'selectGroups');
		$this->addRelatedGroups($options, $result, 'selectHostGroups');
		$this->addRelatedTemplates($options, $result);

		$hostids = array_keys($result);

		if ($options['selectInventory'] !== null) {
			$inventory = API::getApiService()->select('host_inventory', [
				'output' => $options['selectInventory'],
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			]);

			$inventory = $this->unsetExtraFields($inventory, ['hostid', 'inventory_mode'], []);
			$relation_map = $this->createRelationMap($result, 'hostid', 'hostid');
			$result = $relation_map->mapOne($result, $inventory, 'inventory');
		}

		if ($options['selectInterfaces'] !== null) {
			if ($options['selectInterfaces'] != API_OUTPUT_COUNT) {
				$interfaces = API::HostInterface()->get([
					'output' => $this->outputExtend($options['selectInterfaces'], ['hostid', 'interfaceid']),
					'hostids' => $hostids,
					'nopermissions' => true,
					'preservekeys' => true
				]);

				// we need to order interfaces for proper linkage and viewing
				order_result($interfaces, 'interfaceid', ZBX_SORT_UP);

				$relationMap = $this->createRelationMap($interfaces, 'hostid', 'interfaceid');

				$interfaces = $this->unsetExtraFields($interfaces, ['hostid', 'interfaceid'],
					$options['selectInterfaces']
				);
				$result = $relationMap->mapMany($result, $interfaces, 'interfaces', $options['limitSelects']);
			}
			else {
				$interfaces = API::HostInterface()->get([
					'hostids' => $hostids,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);

				$interfaces = zbx_toHash($interfaces, 'hostid');
				foreach ($result as $hostid => $host) {
					$result[$hostid]['interfaces'] = array_key_exists($hostid, $interfaces)
						? $interfaces[$hostid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectDashboards'] !== null) {
			[$hosts_templates, $templateids] = CApiHostHelper::getParentTemplates($hostids);

			if ($options['selectDashboards'] != API_OUTPUT_COUNT) {
				$dashboards = API::TemplateDashboard()->get([
					'output' => $this->outputExtend($options['selectDashboards'], ['templateid']),
					'templateids' => $templateids
				]);

				if (!is_null($options['limitSelects'])) {
					order_result($dashboards, 'name');
				}

				foreach ($result as &$host) {
					foreach ($hosts_templates[$host['hostid']] as $templateid) {
						foreach ($dashboards as $dashboard) {
							if ($dashboard['templateid'] == $templateid) {
								$host['dashboards'][] = $dashboard;
							}
						}
					}
				}
				unset($host);
			}
			else {
				$dashboards = API::TemplateDashboard()->get([
					'templateids' => $templateids,
					'countOutput' => true,
					'groupCount' => true
				]);

				foreach ($result as $hostid => $host) {
					$result[$hostid]['dashboards'] = 0;

					foreach ($dashboards as $dashboard) {
						if (in_array($dashboard['templateid'], $hosts_templates[$hostid])) {
							$result[$hostid]['dashboards'] += $dashboard['rowscount'];
						}
					}

					$result[$hostid]['dashboards'] = (string) $result[$hostid]['dashboards'];
				}
			}
		}

		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			// discovered items
			$discoveryRules = DBFetchArray(DBselect(
				'SELECT hd.hostid,hd2.parent_itemid'.
					' FROM host_discovery hd,host_discovery hd2'.
					' WHERE '.dbConditionInt('hd.hostid', $hostids).
					' AND hd.parent_hostid=hd2.hostid'
			));
			$relationMap = $this->createRelationMap($discoveryRules, 'hostid', 'parent_itemid');

			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		if ($options['selectHostDiscovery'] !== null) {
			$hostDiscoveries = API::getApiService()->select('host_discovery', [
				'output' => $this->outputExtend($options['selectHostDiscovery'], ['hostid']),
				'filter' => ['hostid' => $hostids],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($hostDiscoveries, 'hostid', 'hostid');

			$hostDiscoveries = $this->unsetExtraFields($hostDiscoveries, ['hostid'],
				$options['selectHostDiscovery']
			);
			$result = $relationMap->mapOne($result, $hostDiscoveries, 'hostDiscovery');
		}

		if ($options['selectTags'] !== null) {
			foreach ($result as &$row) {
				$row['tags'] = [];
			}
			unset($row);

			if ($options['selectTags'] === API_OUTPUT_EXTEND) {
				$output = ['hosttagid', 'hostid', 'tag', 'value', 'automatic'];
			}
			else {
				$output = array_unique(array_merge(['hosttagid', 'hostid'], $options['selectTags']));
			}

			$sql_options = [
				'output' => $output,
				'filter' => ['hostid' => $hostids]
			];
			$db_tags = DBselect(DB::makeSql('host_tag', $sql_options));

			while ($db_tag = DBfetch($db_tags)) {
				$hostid = $db_tag['hostid'];

				unset($db_tag['hosttagid'], $db_tag['hostid']);

				$result[$hostid]['tags'][] = $db_tag;
			}
		}

		if ($options['selectInheritedTags'] !== null && $options['selectInheritedTags'] != API_OUTPUT_COUNT) {
			[$hosts_templates, $templateids] = CApiHostHelper::getParentTemplates($hostids);

			$templates = API::Template()->get([
				'output' => [],
				'selectTags' => ['tag', 'value'],
				'templateids' => $templateids,
				'preservekeys' => true,
				'nopermissions' => true
			]);

			// Set "inheritedTags" for each host.
			foreach ($result as &$host) {
				$tags = [];

				// Get IDs and template tag values from previously stored variables.
				foreach ($hosts_templates[$host['hostid']] as $templateid) {
					foreach ($templates[$templateid]['tags'] as $tag) {
						foreach ($tags as $_tag) {
							// Skip tags with same name and value.
							if ($_tag['tag'] === $tag['tag'] && $_tag['value'] === $tag['value']) {
								continue 2;
							}
						}
						$tags[] = $tag;
					}
				}

				$host['inheritedTags'] = $this->unsetExtraFields($tags, ['tag', 'value'],
					$options['selectInheritedTags']
				);
			}
		}

		return $result;
	}

	/**
	 * Adds related host groups requested by "select*" options to the resulting object set.
	 *
	 * @param array  $options  [IN] Original input options.
	 * @param array  $result   [IN/OUT] Result output.
	 * @param string $option   [IN] Possible values:
	 *                                - "selectGroups" (deprecated);
	 *                                - "selectHostGroups" (or any other value).
	 */
	private function addRelatedGroups(array $options, array &$result, string $option): void {
		if ($options[$option] === null || $options[$option] === API_OUTPUT_COUNT) {
			return;
		}

		$relationMap = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
		$groups = API::HostGroup()->get([
			'output' => $options[$option],
			'groupids' => $relationMap->getRelatedIds(),
			'preservekeys' => true
		]);

		$output_tag = $option === 'selectGroups' ? 'groups' : 'hostgroups';
		$result = $relationMap->mapMany($result, $groups, $output_tag);
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private function addRelatedTemplates(array $options, array &$result): void {
		if ($options['selectParentTemplates'] === null) {
			return;
		}

		foreach ($result as &$host) {
			$host['parentTemplates'] = [];
		}
		unset($host);

		if ($options['selectParentTemplates'] === API_OUTPUT_COUNT) {
			$templates = API::Template()->get([
				'hostids' => array_keys($result),
				'countOutput' => true,
				'groupCount' => true
			]);

			$host_templates = array_column($templates, 'rowscount', 'hostid');

			foreach ($result as $hostid => &$host) {
				$host['parentTemplates'] = array_key_exists($hostid, $host_templates)
					? $host_templates[$hostid]
					: '0';
			}
			unset($host);
		}
		else {
			$template_options = [
				'output' => ['hostid', 'templateid'],
				'filter' => ['hostid' => array_keys($result)]
			];

			if ($this->outputIsRequested('link_type', $options['selectParentTemplates'])) {
				$template_options['output'][] = 'link_type';
			}

			$hosts_templates = DBselect(DB::makeSql('hosts_templates', $template_options));

			$template_links = [];

			while ($row = DBfetch($hosts_templates)) {
				$template_links[$row['templateid']][] = $row;
			}

			if ($options['selectParentTemplates'] === API_OUTPUT_EXTEND) {
				$output = $options['selectParentTemplates'];
			}
			else {
				$output = array_diff($options['selectParentTemplates'], ['link_type']);
			}

			$templates = API::Template()->get([
				'output' => $output,
				'templateids' => array_keys($template_links),
				'nopermissions' => $options['nopermissions'],
				'preservekeys' => true
			]);

			if ($options['limitSelects'] !== null) {
				order_result($templates, 'host');
			}

			$templateid_requested = $this->outputIsRequested('templateid', $options['selectParentTemplates']);

			foreach ($templates as $templateid => $template) {
				if (!$templateid_requested) {
					unset($template['templateid']);
				}

				foreach ($template_links[$templateid] as $link) {
					$result[$link['hostid']]['parentTemplates'][] = $template
						+ array_intersect_key($link, array_flip(['link_type']));
				}
			}

			if ($options['limitSelects'] !== null) {
				foreach ($result as &$host) {
					$host['parentTemplates'] = array_slice($host['parentTemplates'], 0, $options['limitSelects']);
				}
				unset($host);
			}
		}
	}

	/**
	 * Validates the input parameters for the get() method.
	 *
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateGet(array $options) {
		// Validate input parameters.
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'inheritedTags' =>				['type' => API_BOOLEAN, 'default' => false],
			'selectInheritedTags' =>		['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
			'severities' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE | API_NOT_EMPTY, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)), 'uniq' => true],
			'withProblemsSuppressed' =>		['type' => API_BOOLEAN, 'flags' => API_ALLOW_NULL],
			'selectTags' =>					['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['tag', 'value', 'automatic'])],
			'selectValueMaps' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['valuemapid', 'name', 'mappings'])],
			'selectParentTemplates' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['templateid', 'host', 'name', 'description', 'uuid', 'link_type'])],
			'selectMacros' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['hostmacroid', 'macro', 'value', 'type', 'description', 'automatic'])],
			'limitSelects' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32]
		]];

		$options_filter = array_intersect_key($options, $api_input_rules['fields']);

		if (!CApiInputValidator::validate($api_input_rules, $options_filter, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Checks if all of the given hosts are available for writing.
	 *
	 * @param array  $hostids
	 * @param string $error

	 * @throws APIException
	 */
	private static function checkPermissions(array $hostids, string $error): void {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
			}
		}
	}

	/**
	 * Validate connections from/to host and PSK fields.
	 *
	 * @param array  $hosts
	 * @param string $hosts[]['hostid']                    (optional if $db_hosts is null)
	 * @param int    $hosts[]['tls_connect']               (optional)
	 * @param int    $hosts[]['tls_accept']                (optional)
	 * @param string $hosts[]['tls_psk_identity']          (optional)
	 * @param string $hosts[]['tls_psk']                   (optional)
	 * @param string $hosts[]['tls_issuer']                (optional)
	 * @param string $hosts[]['tls_subject']               (optional)
	 * @param array  $db_hosts                             (optional)
	 * @param int    $hosts[<hostid>]['tls_connect']
	 * @param int    $hosts[<hostid>]['tls_accept']
	 * @param string $hosts[<hostid>]['tls_psk_identity']
	 * @param string $hosts[<hostid>]['tls_psk']
	 * @param string $hosts[<hostid>]['tls_issuer']
	 * @param string $hosts[<hostid>]['tls_subject']
	 *
	 * @throws APIException if incorrect encryption options.
	 */
	protected function validateEncryption(array $hosts, array $db_hosts = null) {
		$available_connect_types = [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE];
		$min_accept_type = HOST_ENCRYPTION_NONE;
		$max_accept_type = HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE;

		foreach ($hosts as $host) {
			foreach (['tls_connect', 'tls_accept'] as $field_name) {
				$$field_name = array_key_exists($field_name, $host)
					? $host[$field_name]
					: ($db_hosts !== null ? $db_hosts[$host['hostid']][$field_name] : HOST_ENCRYPTION_NONE);
			}

			if (!in_array($tls_connect, $available_connect_types)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_connect',
					_s('unexpected value "%1$s"', $tls_connect)
				));
			}

			if ($tls_accept < $min_accept_type || $tls_accept > $max_accept_type) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_accept',
					_s('unexpected value "%1$s"', $tls_accept)
				));
			}

			foreach (['tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject'] as $field_name) {
				$$field_name = array_key_exists($field_name, $host)
					? $host[$field_name]
					: ($db_hosts !== null ? $db_hosts[$host['hostid']][$field_name] : '');
			}

			// PSK validation.
			if ($tls_connect == HOST_ENCRYPTION_PSK || ($tls_accept & HOST_ENCRYPTION_PSK)) {
				if ($tls_psk_identity === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('cannot be empty'))
					);
				}

				if ($tls_psk === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('cannot be empty'))
					);
				}

				if (!preg_match('/^([0-9a-f]{2})+$/i', $tls_psk)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_psk',
						_('an even number of hexadecimal characters is expected')
					));
				}

				if (strlen($tls_psk) < PSK_MIN_LEN) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_psk',
						_s('minimum length is %1$s characters', PSK_MIN_LEN)
					));
				}
			}
			else {
				if ($tls_psk_identity !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('should be empty'))
					);
				}

				if ($tls_psk !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('should be empty'))
					);
				}
			}

			// Certificate validation.
			if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
				if ($tls_issuer !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_issuer', _('should be empty'))
					);
				}

				if ($tls_subject !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'tls_subject', _('should be empty'))
					);
				}
			}
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $hosts		hosts data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$hosts) {
		$hosts = zbx_toArray($hosts);

		if (!$hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$macro_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
			'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
			'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
		]];

		$host_name_parser = new CHostNameParser();

		$host_db_fields = ['host' => null];

		$groupids = [];

		foreach ($hosts as $index => &$host) {
			// Validate mandatory fields.
			if (!check_db_fields($host_db_fields, $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Wrong fields for host "%1$s".', array_key_exists('host', $host) ? $host['host'] : '')
				);
			}

			// Property 'auto_compress' is not supported for hosts.
			if (array_key_exists('auto_compress', $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			// Validate "host" field.
			if ($host_name_parser->parse($host['host']) != CParser::PARSE_SUCCESS) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect characters used for host name "%1$s".', $host['host'])
				);
			}

			// If visible name is not given or empty it should be set to host name. Required for duplicate checks.
			if (!array_key_exists('name', $host) || trim($host['name']) === '') {
				$host['name'] = $host['host'];
			}

			// Validate "groups" field.
			if (!array_key_exists('groups', $host) || !is_array($host['groups']) || !$host['groups']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host "%1$s" cannot be without host group.', $host['host'])
				);
			}

			$host['groups'] = zbx_toArray($host['groups']);

			foreach ($host['groups'] as $group) {
				if (!is_array($group) || (is_array($group) && !array_key_exists('groupid', $group))) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'groups',
							_s('the parameter "%1$s" is missing', 'groupid')
						)
					);
				}

				$groupids[$group['groupid']] = true;
			}

			if (array_key_exists('macros', $host)) {
				if (!CApiInputValidator::validate($macro_rules, $host['macros'], '/'.($index + 1).'/macros', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
		unset($host);

		self::checkTags($hosts);

		// Check for duplicate "host" and "name" fields.
		$duplicate = CArrayHelper::findDuplicate($hosts, 'host');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate host. Host with the same host name "%1$s" already exists in data.', $duplicate['host'])
			);
		}

		$duplicate = CArrayHelper::findDuplicate($hosts, 'name');
		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Duplicate host. Host with the same visible name "%1$s" already exists in data.', $duplicate['name'])
			);
		}

		// Validate permissions to host groups.
		$db_groups = $groupids
			? API::HostGroup()->get([
				'output' => ['groupid'],
				'groupids' => array_keys($groupids),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		foreach ($hosts as $host) {
			foreach ($host['groups'] as $group) {
				if (!array_key_exists($group['groupid'], $db_groups)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('No permissions to referred object or it does not exist!')
					);
				}
			}
		}

		$inventory_fields = zbx_objectValues(getHostInventories(), 'db_field');

		$valid_inventory_modes = [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC];
		$inventory_mode = new CLimitedSetValidator([
			'values' => $valid_inventory_modes,
			'messageInvalid' => _s('Incorrect value for field "%1$s": %2$s.', 'inventory_mode',
				_s('value must be one of %1$s', implode(', ', $valid_inventory_modes)))
		]);

		$status_validator = new CLimitedSetValidator([
			'values' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED],
			'messageInvalid' => _('Incorrect status for host "%1$s".')
		]);

		$host_names = [];

		foreach ($hosts as $host) {
			if (array_key_exists('interfaces', $host) && $host['interfaces'] !== null
					&& !is_array($host['interfaces'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (array_key_exists('status', $host)) {
				$status_validator->setObjectName($host['host']);
				$this->checkValidator($host['status'], $status_validator);
			}

			if (array_key_exists('inventory_mode', $host)) {
				$inventory_mode->setObjectName($host['host']);
				$this->checkValidator($host['inventory_mode'], $inventory_mode);
			}

			if (array_key_exists('inventory', $host) && $host['inventory']) {
				if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
				}

				$fields = array_keys($host['inventory']);
				foreach ($fields as $field) {
					if (!in_array($field, $inventory_fields)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect inventory field "%1$s".', $field));
					}
				}
			}

			// Collect technical and visible names to check if they exist in hosts and templates.
			$host_names['host'][$host['host']] = true;
			$host_names['name'][$host['name']] = true;
		}

		$filter = [
			'host' => array_keys($host_names['host']),
			'name' => array_keys($host_names['name'])
		];

		$hosts_exists = $this->get([
			'output' => ['host', 'name'],
			'filter' => $filter,
			'searchByAny' => true,
			'nopermissions' => true
		]);

		foreach ($hosts_exists as $host_exists) {
			if (array_key_exists($host_exists['host'], $host_names['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host with the same name "%1$s" already exists.', $host_exists['host'])
				);
			}

			if (array_key_exists($host_exists['name'], $host_names['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Host with the same visible name "%1$s" already exists.', $host_exists['name'])
				);
			}
		}

		$templates_exists = API::Template()->get([
			'output' => ['host', 'name'],
			'filter' => $filter,
			'searchByAny' => true,
			'nopermissions' => true
		]);

		foreach ($templates_exists as $template_exists) {
			if (array_key_exists($template_exists['host'], $host_names['host'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template with the same name "%1$s" already exists.', $template_exists['host'])
				);
			}

			if (array_key_exists($template_exists['name'], $host_names['name'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template with the same visible name "%1$s" already exists.', $template_exists['name'])
				);
			}
		}

		$this->validateEncryption($hosts);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $hosts			hosts data array
	 * @param array $db_hosts		db hosts data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$hosts, array &$db_hosts = null) {
		$hosts = zbx_toArray($hosts);

		if (!$hosts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$macro_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
			'hostmacroid' =>	['type' => API_ID],
			'macro' =>			['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
			'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')],
			'automatic' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_USERMACRO_MANUAL])]
		]];

		$db_hosts = $this->get([
			'output' => ['hostid', 'host', 'flags', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject'],
			'hostids' => array_column($hosts, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		// Load existing values of PSK fields of hosts independently from APP mode.
		$hosts_psk_fields = DB::select($this->tableName(), [
			'output' => ['tls_psk_identity', 'tls_psk'],
			'hostids' => array_keys($db_hosts),
			'preservekeys' => true
		]);

		foreach ($hosts_psk_fields as $hostid => $psk_fields) {
			$db_hosts[$hostid] += $psk_fields;
		}

		$host_db_fields = ['hostid' => null];

		foreach ($hosts as $index => &$host) {
			// Validate mandatory fields.
			if (!check_db_fields($host_db_fields, $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Wrong fields for host "%1$s".', array_key_exists('host', $host) ? $host['host'] : '')
				);
			}

			// Property 'auto_compress' is not supported for hosts.
			if (array_key_exists('auto_compress', $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}

			// Validate host permissions.
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _(
					'No permissions to referred object or it does not exist!'
				));
			}

			// Validate "groups" field.
			if (array_key_exists('groups', $host)) {
				if (!is_array($host['groups']) || !$host['groups']) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host "%1$s" cannot be without host group.', $db_hosts[$host['hostid']]['host'])
					);
				}

				$host['groups'] = zbx_toArray($host['groups']);

				foreach ($host['groups'] as $group) {
					if (!is_array($group) || (is_array($group) && !array_key_exists('groupid', $group))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'groups',
								_s('the parameter "%1$s" is missing', 'groupid')
							)
						);
					}
				}
			}

			// Permissions to host groups is validated in massUpdate().
			if (array_key_exists('macros', $host)) {
				if (!CApiInputValidator::validate($macro_rules, $host['macros'], '/'.($index + 1).'/macros', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
		unset($host);

		if (array_column($hosts, 'macros')) {
			$db_hosts = $this->getHostMacros($db_hosts);
			$hosts = $this->validateHostMacros($hosts, $db_hosts);
		}

		$inventory_fields = zbx_objectValues(getHostInventories(), 'db_field');

		$valid_inventory_modes = [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC];
		$inventory_mode = new CLimitedSetValidator([
			'values' => $valid_inventory_modes,
			'messageInvalid' => _s('Incorrect value for field "%1$s": %2$s.', 'inventory_mode',
				_s('value must be one of %1$s', implode(', ', $valid_inventory_modes)))
		]);

		$status_validator = new CLimitedSetValidator([
			'values' => [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED],
			'messageInvalid' => _('Incorrect status for host "%1$s".')
		]);

		$update_discovered_validator = new CUpdateDiscoveredValidator([
			'allowed' => ['hostid', 'status', 'description', 'tags', 'macros', 'inventory', 'templates',
				'templates_clear'
			],
			'messageAllowedField' => _('Cannot update "%2$s" for a discovered host "%1$s".')
		]);

		$host_name_parser = new CHostNameParser();

		$host_names = [];

		foreach ($hosts as &$host) {
			$db_host = $db_hosts[$host['hostid']];
			$host_name = array_key_exists('host', $host) ? $host['host'] : $db_host['host'];

			if (array_key_exists('status', $host)) {
				$status_validator->setObjectName($host_name);
				$this->checkValidator($host['status'], $status_validator);
			}

			if (array_key_exists('inventory_mode', $host)) {
				$inventory_mode->setObjectName($host_name);
				$this->checkValidator($host['inventory_mode'], $inventory_mode);
			}

			if (array_key_exists('inventory', $host) && $host['inventory']) {
				if (array_key_exists('inventory_mode', $host) && $host['inventory_mode'] == HOST_INVENTORY_DISABLED) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot set inventory fields for disabled inventory.'));
				}

				$fields = array_keys($host['inventory']);
				foreach ($fields as $field) {
					if (!in_array($field, $inventory_fields)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect inventory field "%1$s".', $field));
					}
				}
			}

			// cannot update certain fields for discovered hosts
			$update_discovered_validator->setObjectName($host_name);
			$this->checkPartialValidator($host, $update_discovered_validator, $db_host);

			if (array_key_exists('interfaces', $host) && $host['interfaces'] !== null
					&& !is_array($host['interfaces'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (array_key_exists('host', $host)) {
				if ($host_name_parser->parse($host['host']) != CParser::PARSE_SUCCESS) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect characters used for host name "%1$s".', $host['host'])
					);
				}

				if (array_key_exists('host', $host_names) && array_key_exists($host['host'], $host_names['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Duplicate host. Host with the same host name "%1$s" already exists in data.', $host['host'])
					);
				}

				$host_names['host'][$host['host']] = $host['hostid'];
			}

			if (array_key_exists('name', $host)) {
				// if visible name is empty replace it with host name
				if (zbx_empty(trim($host['name']))) {
					if (!array_key_exists('host', $host)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Visible name cannot be empty if host name is missing.')
						);
					}
					$host['name'] = $host['host'];
				}

				if (array_key_exists('name', $host_names) && array_key_exists($host['name'], $host_names['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Duplicate host. Host with the same visible name "%1$s" already exists in data.', $host['name'])
					);
				}
				$host_names['name'][$host['name']] = $host['hostid'];
			}

			if (array_key_exists('tls_connect', $host) || array_key_exists('tls_accept', $host)) {
				$tls_connect = array_key_exists('tls_connect', $host) ? $host['tls_connect'] : $db_host['tls_connect'];
				$tls_accept = array_key_exists('tls_accept', $host) ? $host['tls_accept'] : $db_host['tls_accept'];

				// Clean PSK fields.
				if ($tls_connect != HOST_ENCRYPTION_PSK && !($tls_accept & HOST_ENCRYPTION_PSK)) {
					if (!array_key_exists('tls_psk_identity', $host)) {
						$host['tls_psk_identity'] = '';
					}
					if (!array_key_exists('tls_psk', $host)) {
						$host['tls_psk'] = '';
					}
				}

				// Clean certificate fields.
				if ($tls_connect != HOST_ENCRYPTION_CERTIFICATE && !($tls_accept & HOST_ENCRYPTION_CERTIFICATE)) {
					if (!array_key_exists('tls_issuer', $host)) {
						$host['tls_issuer'] = '';
					}
					if (!array_key_exists('tls_subject', $host)) {
						$host['tls_subject'] = '';
					}
				}
			}
		}
		unset($host);

		$this->addAffectedTags($hosts, $db_hosts);
		self::checkTags($hosts);

		if (array_key_exists('host', $host_names) || array_key_exists('name', $host_names)) {
			$filter = [];

			if (array_key_exists('host', $host_names)) {
				$filter['host'] = array_keys($host_names['host']);
			}

			if (array_key_exists('name', $host_names)) {
				$filter['name'] = array_keys($host_names['name']);
			}

			$hosts_exists = $this->get([
				'output' => ['hostid', 'host', 'name'],
				'filter' => $filter,
				'searchByAny' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach ($hosts_exists as $host_exists) {
				if (array_key_exists('host', $host_names) && array_key_exists($host_exists['host'], $host_names['host'])
						&& bccomp($host_exists['hostid'], $host_names['host'][$host_exists['host']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host with the same name "%1$s" already exists.', $host_exists['host'])
					);
				}

				if (array_key_exists('name', $host_names) && array_key_exists($host_exists['name'], $host_names['name'])
						&& bccomp($host_exists['hostid'], $host_names['name'][$host_exists['name']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Host with the same visible name "%1$s" already exists.', $host_exists['name'])
					);
				}
			}

			$templates_exists = API::Template()->get([
				'output' => ['hostid', 'host', 'name'],
				'filter' => $filter,
				'searchByAny' => true,
				'nopermissions' => true,
				'preservekeys' => true
			]);

			foreach ($templates_exists as $template_exists) {
				if (array_key_exists('host', $host_names)
						&& array_key_exists($template_exists['host'], $host_names['host'])
						&& bccomp($template_exists['templateid'], $host_names['host'][$template_exists['host']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Template with the same name "%1$s" already exists.', $template_exists['host'])
					);
				}

				if (array_key_exists('name', $host_names)
						&& array_key_exists($template_exists['name'], $host_names['name'])
						&& bccomp($template_exists['templateid'], $host_names['name'][$template_exists['name']]) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Template with the same visible name "%1$s" already exists.', $template_exists['name'])
					);
				}
			}
		}

		$this->validateEncryption($hosts, $db_hosts);

		return $hosts;
	}

	/**
	 * Adding "macros" to the each host object.
	 *
	 * @param array  $db_hosts
	 *
	 * @return array
	 */
	private function getHostMacros(array $db_hosts): array {
		foreach ($db_hosts as &$db_host) {
			$db_host['macros'] = [];
		}
		unset($db_host);

		$options = [
			'output' => ['hostmacroid', 'hostid', 'macro', 'type', 'value', 'description', 'automatic'],
			'filter' => ['hostid' => array_keys($db_hosts)]
		];
		$db_macros = DBselect(DB::makeSql('hostmacro', $options));

		while ($db_macro = DBfetch($db_macros)) {
			$hostid = $db_macro['hostid'];
			unset($db_macro['hostid']);

			$db_hosts[$hostid]['macros'][$db_macro['hostmacroid']] = $db_macro;
		}

		return $db_hosts;
	}

	/**
	 * Check template links.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 */
	protected static function checkTemplatesLinks(array $hosts, array $db_hosts = null): void {
		$del_templates = [];
		$del_links_clear = [];

		foreach ($hosts as $host) {
			if (array_key_exists('templates', $host)) {
				$db_templates = ($db_hosts !== null)
					? array_column($db_hosts[$host['hostid']]['templates'], null, 'templateid')
					: [];
				$upd_templateids = [];

				if ($db_hosts !== null
						&& array_key_exists('nopermissions_templates', $db_hosts[$host['hostid']])) {
					foreach ($db_hosts[$host['hostid']]['nopermissions_templates'] as $db_template) {
						$upd_templateids[] = $db_template['templateid'];
					}
				}

				foreach ($host['templates'] as $template) {
					if (array_key_exists($template['templateid'], $db_templates)) {
						$upd_templateids[] = $template['templateid'];
						unset($db_templates[$template['templateid']]);
					}
				}

				if ($upd_templateids) {
					foreach ($db_templates as $db_template) {
						$del_templates[$db_template['templateid']][$host['hostid']] = $upd_templateids;
					}
				}
			}
			elseif (array_key_exists('templates_clear', $host)) {
				$templateids = array_column($host['templates_clear'], 'templateid');
				$upd_templateids = [];

				foreach ($db_hosts[$host['hostid']]['templates'] as $db_template) {
					if (!in_array($db_template['templateid'], $templateids)) {
						$upd_templateids[] = $db_template['templateid'];
					}
				}

				if ($upd_templateids) {
					foreach ($host['templates_clear'] as $template) {
						$del_templates[$template['templateid']][$host['hostid']] = $upd_templateids;
					}
				}
			}

			if (array_key_exists('templates_clear', $host)) {
				foreach ($host['templates_clear'] as $template) {
					$del_links_clear[$template['templateid']][$host['hostid']] = true;
				}
			}
		}

		if ($del_templates) {
			self::checkTriggerExpressionsOfDelTemplates($del_templates);
		}

		if ($del_links_clear) {
			self::checkTriggerDependenciesOfHostTriggers($del_links_clear);
		}

		parent::checkTemplatesLinks($hosts, $db_hosts);
	}

	/**
	 * Check whether all templates of triggers in templates being unlinked, are unlinked from all target hosts.
	 *
	 * @param array $del_templates
	 * @param array $del_templates[<templateid>][<hostid>]  Array of IDs of existing templates.
	 *
	 * @throws APIException if not linked template is found.
	 */
	public static function checkTriggerExpressionsOfDelTemplates(array $del_templates): void {
		$result = DBselect(
			'SELECT DISTINCT i.hostid AS del_templateid,f.triggerid,ii.hostid'.
			' FROM items i,functions f,functions ff,items ii'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND '.dbConditionId('i.hostid', array_keys($del_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($del_templates[$row['del_templateid']] as $hostid => $upd_templateids) {
				if (in_array($row['hostid'], $upd_templateids)) {
					$objects = DB::select('hosts', [
						'output' => ['host'],
						'hostids' => [$row['del_templateid'], $row['hostid'], $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf(
						_('Cannot unlink template "%1$s" without template "%2$s" from host "%3$s" due to expression of trigger "%4$s".'),
						$objects[$row['del_templateid']]['host'], $objects[$row['hostid']]['host'],
						$objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}
	}

	/**
	 * Check whether the triggers of the target hosts don't have dependencies on the triggers of the templates being
	 * unlinked (with cleaning).
	 *
	 * @param array $del_links_clear[<templateid>][<hostid>]
	 *
	 * @throws APIException
	 */
	public static function checkTriggerDependenciesOfHostTriggers(array $del_links_clear): void {
		$del_host_templates = [];

		foreach ($del_links_clear as $templateid => $hosts) {
			foreach ($hosts as $hostid => $foo) {
				$del_host_templates[$hostid][] = $templateid;
			}
		}

		$result = DBselect(
			'SELECT DISTINCT i.hostid AS templateid,t.triggerid,ii.hostid'.
			' FROM items i,functions f,triggers t,functions ff,items ii'.
			' WHERE i.itemid=f.itemid'.
				' AND f.triggerid=t.templateid'.
				' AND t.triggerid=ff.triggerid'.
				' AND ff.itemid=ii.itemid'.
				' AND '.dbConditionId('i.hostid', array_keys($del_links_clear)).
				' AND '.dbConditionId('ii.hostid', array_keys($del_host_templates))
		);

		$trigger_links = [];

		while ($row = DBfetch($result)) {
			if (in_array($row['templateid'], $del_host_templates[$row['hostid']])) {
				$trigger_links[$row['triggerid']][$row['hostid']] = $row['templateid'];
			}
		}

		if (!$trigger_links) {
			return;
		}

		$result = DBselect(
			'SELECT DISTINCT td.triggerid_up,td.triggerid_down,i.hostid'.
			' FROM trigger_depends td,functions f,items i'.
			' WHERE td.triggerid_down=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND '.dbConditionId('td.triggerid_up', array_keys($trigger_links)).
				' AND '.dbConditionId('td.triggerid_down', array_keys($trigger_links), true).
				' AND '.dbConditionId('i.hostid', array_keys($del_host_templates))
		);

		while ($row = DBfetch($result)) {
			foreach ($trigger_links[$row['triggerid_up']] as $hostid => $templateid) {
				if (bccomp($row['hostid'], $hostid) == 0) {
					$objects = DB::select('hosts', [
						'output' => ['host'],
						'hostids' => [$templateid, $hostid],
						'preservekeys' => true
					]);

					$triggers = DB::select('triggers', [
						'output' => ['description'],
						'triggerids' => $row['triggerid_down']
					]);

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf(
						_('Cannot unlink template "%1$s" from host "%2$s" due to dependency of trigger "%3$s".'),
						$objects[$templateid]['host'], $objects[$hostid]['host'], $triggers[0]['description']
					));
				}
			}
		}
	}

	/**
	 * @param array $hosts
	 *
	 * @throws APIException
	 */
	private static function checkTags(array &$hosts): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'tags' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function requiresPostSqlFiltering(array $options) {
		return ($options['severities'] !== null || $options['withProblemsSuppressed'] !== null);
	}

	protected function applyPostSqlFiltering(array $hosts, array $options) {
		$hosts = zbx_toHash($hosts, 'hostid');

		if ($options['severities'] !== null || $options['withProblemsSuppressed'] !== null) {
			$triggers = API::Trigger()->get([
				'output' => [],
				'selectHosts' => ['hostid'],
				'hostids' => zbx_objectValues($hosts, 'hostid'),
				'skipDependent' => true,
				'status' => TRIGGER_STATUS_ENABLED,
				'preservekeys' => true,
				'nopermissions' => true
			]);

			$problems = API::Problem()->get([
				'output' => ['objectid'],
				'objectids' => array_keys($triggers),
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'suppressed' => $options['withProblemsSuppressed'],
				'severities' => $options['severities'],
				'nopermissions' => true
			]);

			if (!$problems) {
				return [];
			}

			// Keys are the trigger ids, that have problems.
			$problem_triggers = array_flip(array_column($problems, 'objectid'));

			// Hostids, with triggerids on them.
			$host_triggers = [];
			foreach ($triggers as $triggerid => $trigger) {
				foreach ($trigger['hosts'] as $trigger_host) {
					$host_triggers[$trigger_host['hostid']][$triggerid] = true;
				}
			}

			foreach ($hosts as $key => $host) {
				$problems_found = false;

				if (array_key_exists($host['hostid'], $host_triggers)) {
					foreach (array_keys($host_triggers[$host['hostid']]) as $host_trigger) {
						if (array_key_exists($host_trigger, $problem_triggers)) {
							$problems_found = true;
							break;
						}
					}
				}

				if (!$problems_found) {
					unset($hosts[$key]);
				}
			}
		}

		return $hosts;
	}

	/**
	 * @inheritdoc
	 */
	protected function getObjectsByData(array $data, array $db_objects): array {
		$objects = parent::getObjectsByData($data, $db_objects);
		$objects = array_column($objects, null, 'hostid');

		foreach ($db_objects as $db_object) {
			if (array_key_exists('templates', $db_object)) {
				$object = $objects[$db_object['hostid']];

				if (array_key_exists('templates', $data) || array_key_exists('templateids', $data)) {
					$object['templates'] = [];

					if (array_key_exists('templates', $data)) {
						foreach ($data['templates'] as $template) {
							$object['templates'][] = ['templateid' => $template['templateid']];
						}
					}
				}

				if (array_key_exists('templates_clear', $data) || array_key_exists('templateids_clear', $data)) {
					$object['templates_clear'] = [];
					$db_templateids = array_column($db_object['templates'], 'templateid');

					if (array_key_exists('templates_clear', $data)) {
						foreach ($data['templates_clear'] as $template) {
							if (in_array($template['templateid'], $db_templateids)) {
								$object['templates_clear'][] = ['templateid' => $template['templateid']];
							}
						}
					}
					else {
						foreach ($data['templateids_clear'] as $templateid) {
							if (in_array($templateid, $db_templateids)) {
								$object['templates_clear'][] = ['templateid' => $templateid];
							}
						}
					}
				}

				$objects[$db_object['hostid']] = $object;
			}
		}

		return array_values($objects);
	}

	/**
	 * Add the existing groups, macros or templates if affected by mass methods.
	 *
	 * @param string     $objects
	 * @param array      $objectids
	 * @param array      $db_hosts
	 */
	protected function massAddAffectedObjects(string $objects, array $objectids, array &$db_hosts): void {
		parent::massAddAffectedObjects($objects, $objectids, $db_hosts);

		if ($objects === 'templates') {
			$permitted_templates = [];

			if (!$objectids && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
				$permitted_templates = API::Template()->get([
					'output' => [],
					'hostids' => array_keys($db_hosts),
					'preservekeys' => true
				]);
			}

			$options = [
				'output' => ['hosttemplateid', 'hostid', 'templateid'],
				'filter' => [
					'hostid' => array_keys($db_hosts)
				]
			];
			$db_hosts_templates = DBselect(DB::makeSql('hosts_templates', $options));

			while ($link = DBfetch($db_hosts_templates)) {
				if ($objectids) {
					if (in_array($link['templateid'], $objectids)) {
						$db_hosts[$link['hostid']]['templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
					else {
						$db_hosts[$link['hostid']]['nopermissions_templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
				}
				else {
					if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN
							|| array_key_exists($link['templateid'], $permitted_templates)) {
						$db_hosts[$link['hostid']]['templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
					else {
						$db_hosts[$link['hostid']]['nopermissions_templates'][$link['hosttemplateid']] =
							array_diff_key($link, array_flip(['hostid']));
					}
				}
			}
		}
	}

	/**
	 * Links the templates to the given hosts.
	 *
	 * @param array $templateIds
	 * @param array $targetIds		an array of host IDs to link the templates to
	 *
	 * @return array 	an array of added hosts_templates rows, with 'hostid' and 'templateid' set for each row
	 */
	private static function link(array $templateIds, array $targetIds): array {
		if (!$templateIds) {
			return [];
		}

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateIds);
		if ($templateIdDuplicates) {
			$duplicatesFound = [];
			foreach ($templateIdDuplicates as $value => $count) {
				$duplicatesFound[] = _s('template ID "%1$s" is passed %2$s times', $value, $count);
			}
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot pass duplicate template IDs for the linkage: %1$s.', implode(', ', $duplicatesFound))
			);
		}

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateIds
		]);

		if ($count != count($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
		// get DB templates which exists in all targets
		$res = DBselect('SELECT * FROM hosts_templates WHERE '.dbConditionInt('hostid', $targetIds));
		$mas = [];
		while ($row = DBfetch($res)) {
			if (!isset($mas[$row['templateid']])) {
				$mas[$row['templateid']] = [];
			}
			$mas[$row['templateid']][$row['hostid']] = 1;
		}
		$commonDBTemplateIds = [];
		foreach ($mas as $templateId => $targetList) {
			if (count($targetList) == count($targetIds)) {
				$commonDBTemplateIds[] = $templateId;
			}
		}

		// check if there are any template with triggers which depends on triggers in templates which will be not linked
		$commonTemplateIds = array_unique(array_merge($commonDBTemplateIds, $templateIds));
		foreach ($templateIds as $templateid) {
			$triggerids = [];
			$dbTriggers = get_triggers_by_hostid($templateid);
			while ($trigger = DBfetch($dbTriggers)) {
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];
			}

			$sql = 'SELECT DISTINCT h.host'.
				' FROM trigger_depends td,functions f,items i,hosts h'.
				' WHERE ('.
				dbConditionInt('td.triggerid_down', $triggerids).
				' AND f.triggerid=td.triggerid_up'.
				' )'.
				' AND i.itemid=f.itemid'.
				' AND h.hostid=i.hostid'.
				' AND '.dbConditionInt('h.hostid', $commonTemplateIds, true).
				' AND h.status='.HOST_STATUS_TEMPLATE;
			if ($dbDepHost = DBfetch(DBselect($sql))) {
				$tmpTpls = API::Template()->get([
					'output'=> ['host'],
					'templateids' => $templateid
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Trigger in template "%1$s" has dependency with trigger in template "%2$s".', $tmpTpls[0]['host'], $dbDepHost['host']));
			}
		}

		$res = DBselect(
			'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionInt('ht.hostid', $targetIds).
				' AND '.dbConditionInt('ht.templateid', $templateIds)
		);
		$linked = [];
		while ($row = DBfetch($res)) {
			$linked[$row['templateid']][$row['hostid']] = true;
		}

		// add template linkages, if problems rollback later
		$links = [];

		foreach ($templateIds as $templateid) {
			$linked_targets = array_key_exists($templateid, $linked) ? $linked[$templateid] : [];

			foreach ($targetIds as $targetid) {
				if (array_key_exists($targetid, $linked_targets)) {
					continue;
				}

				$links[] = ['hostid' => $targetid, 'templateid' => $templateid];
			}
		}

		if ($links) {
			$hosttemplateids = DB::insertBatch('hosts_templates', $links);

			foreach ($links as &$host_linkage) {
				$host_linkage['hosttemplateid'] = array_shift($hosttemplateids);
			}
			unset($host_linkage);
		}

		// check if all trigger templates are linked to host.
		// we try to find template that is not linked to hosts ($targetids)
		// and exists trigger which reference that template and template from ($templateids)
		$sql = 'SELECT DISTINCT h.host'.
			' FROM functions f,items i,triggers t,hosts h'.
			' WHERE f.itemid=i.itemid'.
			' AND f.triggerid=t.triggerid'.
			' AND i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_TEMPLATE.
			' AND NOT EXISTS (SELECT 1 FROM hosts_templates ht WHERE ht.templateid=i.hostid AND '.dbConditionInt('ht.hostid', $targetIds).')'.
			' AND EXISTS (SELECT 1 FROM functions ff,items ii WHERE ff.itemid=ii.itemid AND ff.triggerid=t.triggerid AND '.dbConditionInt('ii.hostid', $templateIds). ')';
		if ($dbNotLinkedTpl = DBfetch(DBSelect($sql, 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Trigger has items from template "%1$s" that is not linked to host.', $dbNotLinkedTpl['host'])
			);
		}

		$templates_hostids = [];
		$link_requests = [];

		foreach ($links as $host_tpl_ids) {
			$templates_hostids[$host_tpl_ids['templateid']][] = $host_tpl_ids['hostid'];
		}

		foreach ($templates_hostids as $templateid => $hostids) {
			// Fist link web items, so that later regular items can use web item as their master item.
			Manager::HttpTest()->link($templateid, $hostids);
		}

		while ($templates_hostids) {
			$templateid = key($templates_hostids);
			$link_request = [
				'hostids' => reset($templates_hostids),
				'templateids' => [$templateid]
			];
			unset($templates_hostids[$templateid]);

			foreach ($templates_hostids as $templateid => $hostids) {
				if ($link_request['hostids'] === $hostids) {
					$link_request['templateids'][] = $templateid;
					unset($templates_hostids[$templateid]);
				}
			}

			$link_requests[] = $link_request;
		}

		foreach ($link_requests as $link_request) {
			CItem::linkTemplateObjects($link_request['templateids'], $link_request['hostids']);
			$ruleids = API::DiscoveryRule()->syncTemplates($link_request['templateids'], $link_request['hostids']);

			if ($ruleids) {
				CItemPrototype::linkTemplateObjects($link_request['templateids'], $link_request['hostids']);
				API::HostPrototype()->syncTemplates($ruleids, $link_request['hostids']);
			}
		}

		// we do linkage in two separate loops because for triggers you need all items already created on host
		foreach ($link_requests as $link_request){
			API::Trigger()->syncTemplates($link_request);
			API::TriggerPrototype()->syncTemplates($link_request);
			API::GraphPrototype()->syncTemplates($link_request);
			API::Graph()->syncTemplates($link_request);
		}

		foreach ($link_requests as $link_request){
			CTriggerGeneral::syncTemplateDependencies($link_request['templateids'], $link_request['hostids']);
		}

		return $links;
	}

	/**
	 * Unlinks the templates from the given hosts. If $targetids is set to null, the templates will be unlinked from
	 * all hosts.
	 *
	 * @param array      $templateids
	 * @param null|array $targetids		the IDs of the hosts to unlink the templates from
	 * @param bool       $clear			delete all of the inherited objects from the hosts
	 */
	private static function unlink($templateids, $targetids = null, $clear = false) {
		$flags = ($clear)
			? [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE]
			: [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_PROTOTYPE];

		// check that all triggers on templates that we unlink, don't have items from another templates
		$sql = 'SELECT DISTINCT t.description'.
			' FROM triggers t,functions f,items i'.
			' WHERE t.triggerid=f.triggerid'.
			' AND f.itemid=i.itemid'.
			' AND '.dbConditionInt('i.hostid', $templateids).
			' AND EXISTS ('.
			'SELECT ff.triggerid'.
			' FROM functions ff,items ii'.
			' WHERE ff.itemid=ii.itemid'.
			' AND ff.triggerid=t.triggerid'.
			' AND '.dbConditionInt('ii.hostid', $templateids, true).
			')'.
			' AND t.flags='.ZBX_FLAG_DISCOVERY_NORMAL;
		if ($dbTrigger = DBfetch(DBSelect($sql, 1))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot unlink trigger "%1$s", it has items from template that is left linked to host.',
					$dbTrigger['description']
				)
			);
		}

		$templ_triggerids = [];

		$db_triggers = DBselect(
			'SELECT DISTINCT f.triggerid'.
			' FROM functions f,items i'.
			' WHERE f.itemid=i.itemid'.
				' AND '.dbConditionInt('i.hostid', $templateids)
		);

		while ($db_trigger = DBfetch($db_triggers)) {
			$templ_triggerids[] = $db_trigger['triggerid'];
		}

		$upd_triggers = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		if ($templ_triggerids) {
			$sql_distinct = ($targetids !== null) ? ' DISTINCT' : '';
			$sql_from = ($targetids !== null) ? ',functions f,items i' : '';
			$sql_where = ($targetids !== null)
				? ' AND t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND '.dbConditionInt('i.hostid', $targetids)
				: '';

			$db_triggers = DBSelect(
				'SELECT'.$sql_distinct.' t.triggerid,t.flags'.
				' FROM triggers t'.$sql_from.
				' WHERE '.dbConditionInt('t.templateid', $templ_triggerids).
					' AND '.dbConditionInt('t.flags', $flags).
					$sql_where
			);

			while ($db_trigger = DBfetch($db_triggers)) {
				if ($clear) {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']] = true;
				}
				else {
					$upd_triggers[$db_trigger['flags']][$db_trigger['triggerid']] = [
						'values' => ['templateid' => 0],
						'where' => ['triggerid' => $db_trigger['triggerid']]
					];
				}
			}
		}

		if ($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]) {
			if ($clear) {
				CTriggerManager::delete(array_keys($upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]));
			}
			else {
				DB::update('triggers', $upd_triggers[ZBX_FLAG_DISCOVERY_NORMAL]);
			}
		}

		if ($upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			if ($clear) {
				CTriggerPrototypeManager::delete(array_keys($upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
			}
			else {
				DB::update('triggers', $upd_triggers[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			}
		}

		/* GRAPHS {{{ */
		$db_tpl_graphs = DBselect(
			'SELECT DISTINCT g.graphid'.
			' FROM graphs g,graphs_items gi,items i'.
			' WHERE g.graphid=gi.graphid'.
				' AND gi.itemid=i.itemid'.
				' AND '.dbConditionInt('i.hostid', $templateids).
				' AND '.dbConditionInt('g.flags', $flags)
		);

		$tpl_graphids = [];

		while ($db_tpl_graph = DBfetch($db_tpl_graphs)) {
			$tpl_graphids[] = $db_tpl_graph['graphid'];
		}

		if ($tpl_graphids) {
			$upd_graphs = [
				ZBX_FLAG_DISCOVERY_NORMAL => [],
				ZBX_FLAG_DISCOVERY_PROTOTYPE => []
			];

			$sql = ($targetids !== null)
				? 'SELECT DISTINCT g.graphid,g.flags'.
					' FROM graphs g,graphs_items gi,items i'.
					' WHERE g.graphid=gi.graphid'.
						' AND gi.itemid=i.itemid'.
						' AND '.dbConditionInt('g.templateid', $tpl_graphids).
						' AND '.dbConditionInt('i.hostid', $targetids)
				: 'SELECT g.graphid,g.flags'.
					' FROM graphs g'.
					' WHERE '.dbConditionInt('g.templateid', $tpl_graphids);

			$db_graphs = DBSelect($sql);

			while ($db_graph = DBfetch($db_graphs)) {
				if ($clear) {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']] = true;
				}
				else {
					$upd_graphs[$db_graph['flags']][$db_graph['graphid']] = [
						'values' => ['templateid' => 0],
						'where' => ['graphid' => $db_graph['graphid']]
					];
				}
			}

			if ($upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
				if ($clear) {
					CGraphPrototypeManager::delete(array_keys($upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
				}
				else {
					DB::update('graphs', $upd_graphs[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
				}
			}

			if ($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]) {
				if ($clear) {
					CGraphManager::delete(array_keys($upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]));
				}
				else {
					DB::update('graphs', $upd_graphs[ZBX_FLAG_DISCOVERY_NORMAL]);
				}
			}
		}
		/* }}} GRAPHS */

		if ($clear) {
			CDiscoveryRule::clearTemplateObjects($templateids, $targetids);
			CItem::clearTemplateObjects($templateids, $targetids);
			CHttpTest::clearTemplateObjects($templateids, $targetids);
		}
		else {
			CDiscoveryRule::unlinkTemplateObjects($templateids, $targetids);
			CItem::unlinkTemplateObjects($templateids, $targetids);
			CHttpTest::unlinkTemplateObjects($templateids, $targetids);
		}

		if ($targetids !== null) {
			$hosts = API::Host()->get([
				'hostids' => $targetids,
				'output' => ['host'],
				'nopermissions' => true
			]);
		}
		else {
			$hosts = API::Host()->get([
				'templateids' => $templateids,
				'output' => ['host'],
				'nopermissions' => true
			]);
		}

		$options = ['templateid' => $templateids];

		if ($targetids !== null) {
			$options['hostid'] = $targetids;
		}

		DB::delete('hosts_templates', $options);

		if ($hosts) {
			$templates = API::Template()->get([
				'output' => ['host'],
				'templateids' => $templateids,
				'nopermissions' => true
			]);

			info(_s('Templates "%1$s" unlinked from hosts "%2$s".',	implode(', ', array_column($templates, 'host')),
				implode(', ', array_column($hosts, 'host'))
			));
		}
	}

	/**
	 * Check templates links for given data of mass API methods.
	 *
	 * @param string $method
	 * @param array  $templateids
	 * @param array  $db_hosts
	 */
	protected function massCheckTemplatesLinks(string $method, array $templateids, array $db_hosts,
			array $templateids_clear = []): void {
		$ins_templates = [];
		$del_links = [];
		$del_templates = [];
		$del_links_clear  = [];

		foreach ($db_hosts as $hostid => $db_host) {
			$db_templateids = array_column($db_host['templates'], 'templateid');

			if ($method === 'massadd') {
				$_templateids = array_diff($templateids, $db_templateids);
			}
			elseif ($method === 'massremove') {
				$_templateids = array_diff($db_templateids, $templateids);
			}
			else {
				$_templateids = $templateids;
			}

			$permitted_templateids = $_templateids;
			$templates_count = count($permitted_templateids);
			$upd_templateids = [];

			if (array_key_exists('nopermissions_templates', $db_host)) {
				foreach ($db_host['nopermissions_templates'] as $db_template) {
					$_templateids[] = $db_template['templateid'];
					$templates_count++;
					$upd_templateids[] = $db_template['templateid'];
				}
			}

			foreach ($permitted_templateids as $templateid) {
				$index = array_search($templateid, $db_templateids);

				if ($index !== false) {
					$upd_templateids[] = $templateid;
					unset($db_templateids[$index]);
				}
				else {
					$ins_templates[$templateid][$hostid] = $_templateids;
				}
			}

			foreach ($db_templateids as $db_templateid) {
				$del_links[$db_templateid][$hostid] = true;

				if ($upd_templateids) {
					$del_templates[$db_templateid][$hostid] = $upd_templateids;
				}

				if (array_key_exists($db_templateid, $templateids_clear)) {
					$del_links_clear[$db_templateid][$hostid] = true;
				}
			}
		}

		if ($del_templates) {
			self::checkTriggerExpressionsOfDelTemplates($del_templates);
		}

		if ($del_links_clear) {
			self::checkTriggerDependenciesOfHostTriggers($del_links_clear);
		}

		if ($ins_templates) {
			self::checkTriggerDependenciesOfInsTemplates($ins_templates);
			self::checkTriggerExpressionsOfInsTemplates($ins_templates);
		}
	}
}
