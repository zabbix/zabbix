<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Class containing methods for operations with hosts.
 */
class CHost extends CHostGeneral {

	protected $sortColumns = ['hostid', 'host', 'name', 'status'];

	public const OUTPUT_FIELDS = ['hostid', 'host', 'monitored_by', 'proxyid', 'proxy_groupid', 'assigned_proxyid',
		'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'maintenanceid',
		'maintenance_status', 'maintenance_type', 'maintenance_from', 'name', 'flags', 'description', 'tls_connect',
		'tls_accept', 'tls_issuer', 'tls_subject', 'inventory_mode', 'active_available'
	];

	/**
	 * Get host data.
	 *
	 * @param array         $options
	 * @param array         $options['groupids']                           Select hosts by group IDs.
	 * @param array         $options['hostids']                            Select hosts by host IDs.
	 * @param array         $options['proxyids']                           Select hosts by proxy IDs.
	 * @param array         $options['proxy_groupids']                     Select hosts by proxy group IDs.
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
			'proxy_groupids'					=> null,
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

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p1';
			$sqlParts['where'][] = 'h.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p1.hgsetid';
			$sqlParts['where'][] = 'p1.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p1.permission='.PERM_READ_WRITE;
			}
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

			$sqlParts['where'][] = dbConditionId('h.proxyid', $options['proxyids']);
		}

		// proxy_groupids
		if (!is_null($options['proxy_groupids'])) {
			zbx_value2array($options['proxy_groupids']);

			$sqlParts['where'][] = dbConditionId('h.proxy_groupid', $options['proxy_groupids']);
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

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			if ($options['inheritedTags']) {
				$sqlParts['left_join'][] = ['alias' => 'ht2', 'table' => 'hosts_templates', 'using' => 'hostid'];
				$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
				$sqlParts['where'][] = CApiTagHelper::addInheritedHostTagsWhereCondition($options['tags'],
					$options['evaltype']
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
			$all_keys[] = 'assigned_proxyid';
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

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if (is_array($options['filter'])) {
			if (array_key_exists('inventory_mode', $options['filter'])
					&& $options['filter']['inventory_mode'] !== null) {
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

			if (array_key_exists('assigned_proxyid', $options['filter'])
					&& $options['filter']['assigned_proxyid'] !== null) {
				$sqlParts['where'][] = dbConditionId('p.proxyid', $options['filter']['assigned_proxyid']);
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

		if ((!$options['countOutput'] && $this->outputIsRequested('assigned_proxyid', $options['output']))
				|| (is_array($options['filter']) && array_key_exists('assigned_proxyid', $options['filter'])
					&& $options['filter']['assigned_proxyid'] !== null)) {
			$sqlParts['left_join'][] = ['alias' => 'hp', 'table' => 'host_proxy', 'using' => 'hostid'];
			// Override host_proxy.proxyid with NULL if hosts.proxy_groupid and proxy.proxy_groupid do not match.
			$sqlParts['left_join'][] = ['alias' => 'p', 'table' => 'proxy', 'use_distinct' => false,
				'condition' => 'h.proxy_groupid=p.proxy_groupid AND hp.proxyid=p.proxyid'
			];
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

			if ($this->outputIsRequested('assigned_proxyid', $options['output'])) {
				$sqlParts = $this->addQuerySelect('p.proxyid AS assigned_proxyid', $sqlParts);
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
	 * @param int    $hosts[]['monitored_by']               Source of monitoring (optional).
	 * @param string $hosts[]['proxyid']                    ID of the proxy used to monitor the host (optional).
	 * @param string $hosts[]['proxy_groupid']              ID of the proxy group used to monitor the host (optional).
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

		$hostids = DB::insert('hosts', $hosts);

		foreach ($hosts as &$host) {
			$host['hostid'] = array_shift($hostids);
		}
		unset($host);

		$this->checkTemplatesLinks($hosts);

		$this->updateGroups($hosts);
		$this->updateHgSets($hosts);
		$this->updateTags($hosts);
		$this->updateMacros($hosts);

		$hosts_rtdata = [];
		$hosts_interfaces = [];
		$hosts_inventory = [];

		foreach ($hosts as &$host) {
			$hosts_rtdata[] = ['hostid' => $host['hostid']];

			if (array_key_exists('interfaces', $host)) {
				foreach (zbx_toArray($host['interfaces']) as $interface) {
					$hosts_interfaces[] = ['hostid' => $host['hostid']] + $interface;
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

		if ($hosts_interfaces) {
			API::HostInterface()->create($hosts_interfaces);
		}

		$this->updateTemplates($hosts);

		if ($hosts_inventory) {
			DB::insert('host_inventory', $hosts_inventory, false);
		}

		DB::insertBatch('host_rtdata', $hosts_rtdata, false);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_HOST, $hosts);

		return ['hostids' => array_column($hosts, 'hostid')];
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
	 * @param int    $hosts[]['monitored_by']                     Source of monitoring (optional).
	 * @param string $hosts[]['proxyid']                          ID of the proxy used to monitor the host (optional).
	 * @param string $hosts[]['proxy_groupid']                    ID of the proxy group used to monitor the host (optional).
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
		$this->validateUpdate($hosts, $db_hosts);
		$this->updateForce($hosts, $db_hosts);

		return ['hostids' => array_column($hosts, 'hostid')];
	}

	public function updateForce(array $hosts, array $db_hosts): void {
		$this->updateTags($hosts, $db_hosts);
		$this->updateMacros($hosts, $db_hosts);

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

		foreach ($hosts as $host) {
			$host = array_diff_key($host, array_flip(['groups', 'tags', 'macros', 'templates', 'templates_clear']));

			// Extend host inventory with the required data.
			if (array_key_exists('inventory', $host) && $host['inventory']) {
				// If inventory mode is HOST_INVENTORY_DISABLED, database record is not created.
				if (array_key_exists('inventory_mode', $inventories[$host['hostid']])
						&& ($inventories[$host['hostid']]['inventory_mode'] == HOST_INVENTORY_MANUAL
							|| $inventories[$host['hostid']]['inventory_mode'] == HOST_INVENTORY_AUTOMATIC)) {
					$host['inventory'] = $inventories[$host['hostid']];
				}
			}

			if (array_key_exists('monitored_by', $host)) {
				if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
					$host += ['proxyid' => $db_hosts[$host['hostid']]['proxyid']];
				}
				elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
					$host += ['proxy_groupid' => $db_hosts[$host['hostid']]['proxy_groupid']];
				}
			}

			if (array_key_exists('tls_connect', $host)
					&& ($host['tls_connect'] == HOST_ENCRYPTION_PSK || $host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				$host += [
					'tls_psk_identity' => $db_hosts[$host['hostid']]['tls_psk_identity'],
					'tls_psk' => $db_hosts[$host['hostid']]['tls_psk']
				];
			}

			$data = array_diff_key($host, array_flip(['hostid']));
			$data['hosts'] = ['hostid' => $host['hostid']];
			$result = $this->massUpdate($data, ['skip_tls_psk_pair_check' => true]);

			if (!$result) {
				self::exception(ZBX_API_ERROR_INTERNAL, _('Host update failed.'));
			}
		}

		$this->updateGroups($hosts, $db_hosts);
		$this->updateHgSets($hosts, $db_hosts);
		$this->updateTemplates($hosts, $db_hosts);
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
		$this->validateMassAdd($data, $hosts, $db_hosts);

		// add new interfaces
		if (!empty($data['interfaces'])) {
			API::HostInterface()->massAdd([
				'hosts' => $data['hosts'],
				'interfaces' => zbx_toArray($data['interfaces'])
			]);
		}

		$this->updateForce($hosts, $db_hosts);

		return ['hostids' => array_column($data['hosts'], 'hostid')];
	}

	private function validateMassAdd(array &$data, ?array &$hosts, ?array &$db_hosts): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'hosts' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'macros' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
										['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'templates' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'interfaces' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => []]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = $this->get([
			'output' => ['hostid', 'host', 'flags'],
			'hostids' => array_column($data['hosts'], 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($data['hosts'] as $i => $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/hosts/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
			elseif ($db_hosts[$host['hostid']]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$field_name = null;

				if (array_key_exists('interfaces', $data) && $data['interfaces']) {
					$field_name = 'interfaces';
				}

				if (array_key_exists('groups', $data) && $data['groups']) {
					$field_name = 'groups';
				}

				if ($field_name !== null) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/hosts/'.($i + 1),
						_s('cannot update readonly parameter "%1$s" of discovered object', $field_name)
					));
				}
			}
		}

		$hosts = $data['hosts'];

		$this->addObjectsByData($data, $hosts);
		$this->addAffectedObjects($hosts, $db_hosts);
		$this->addUnchangedObjects($hosts, $db_hosts);

		if (array_key_exists('groups', $data) && $data['groups']) {
			$this->checkGroups($hosts, $db_hosts, '/groups', array_flip(array_column($data['groups'], 'groupid')));
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			$hosts = $this->validateHostMacros($hosts, $db_hosts);
		}

		if (array_key_exists('templates', $data) && $data['templates']) {
			$this->checkTemplates($hosts, $db_hosts, '/templates',
				array_flip(array_column($data['templates'], 'templateid'))
			);
			$this->checkTemplatesLinks($hosts, $db_hosts);
		}
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
	 * @param int    $hosts['fields']['monitored_by']	Source of monitoring. OPTIONAL
	 * @param string $hosts['fields']['proxyid']		Proxy ID. OPTIONAL
	 * @param string $hosts['fields']['proxy_groupid']	Proxy group ID. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_authtype']	IPMI authentication type. OPTIONAL
	 * @param int    $hosts['fields']['ipmi_privilege']	IPMI privilege. OPTIONAL
	 * @param string $hosts['fields']['ipmi_username']	IPMI username. OPTIONAL
	 * @param string $hosts['fields']['ipmi_password']	IPMI password. OPTIONAL
	 * @param array  $options
	 *
	 * @return boolean
	 */
	public function massUpdate($data, array $options = []): array {
		$this->validateMassUpdate($data, $hosts, $db_hosts, $options);

		$hostids = array_column($data['hosts'], 'hostid');

		sort($hostids);

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

		if (array_key_exists('monitored_by', $data)) {
			if ($data['monitored_by'] != ZBX_MONITORED_BY_PROXY) {
				$data += ['proxyid' => 0];
			}

			if ($data['monitored_by'] != ZBX_MONITORED_BY_PROXY_GROUP) {
				$data += ['proxy_groupid' => 0];
			}
		}

		if (array_key_exists('tls_connect', $data)) {
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

		// Property 'auto_compress' is not supported for hosts.
		if (array_key_exists('auto_compress', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
		}

		/*
		 * Update hosts properties
		 */
		if (isset($data['name'])) {
			if (count($data['hosts']) > 1) {
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

			if (count($data['hosts']) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot mass update host name.'));
			}

			$curHost = reset($data['hosts']);

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

		if (isset($data['interfaces'])) {
			$updateInterfaces = $data['interfaces'];
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

		if ($data) {
			DB::update('hosts', [
				'values' => $data,
				'where' => ['hostid' => $hostids]
			]);

			if (array_key_exists('status', $data) && $data['status'] == HOST_STATUS_NOT_MONITORED) {
				$discovered_hostids = [];

				foreach ($db_hosts as $db_host) {
					if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $data['status'] != $db_host['status']) {
						$discovered_hostids[] = $db_host['hostid'];
					}
				}

				if ($discovered_hostids) {
					DB::update('host_discovery', [
						'values' => ['disable_source' => ZBX_DISABLE_DEFAULT],
						'where' => ['hostid' => $discovered_hostids]
					]);
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

		if ($hosts) {
			$this->updateForce($hosts, $db_hosts);
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

		return ['hostids' => $hostids];
	}

	private function validateMassUpdate(array &$data, ?array &$hosts, ?array &$db_hosts, array $options): void {
		self::checkProxyFields($data);
		self::checkTlsFields($data);

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'hosts' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['hostid']], 'fields' => [
				'hostid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'monitored_by' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP])],
			'proxyid' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $_data): bool => array_key_exists('monitored_by', $_data) && $_data['monitored_by'] == ZBX_MONITORED_BY_PROXY, 'type' => API_ID, 'flags' => API_REQUIRED],
										['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'proxy_groupid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $_data): bool => array_key_exists('monitored_by', $_data) && $_data['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP, 'type' => API_ID, 'flags' => API_REQUIRED],
										['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'tls_connect' =>		['type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE])],
			'tls_accept' =>			['type' => API_INT32, 'in' => implode(':', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE])],
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $_data): bool => (array_key_exists('tls_connect', $_data) && $_data['tls_connect'] == HOST_ENCRYPTION_PSK) || (array_key_exists('tls_accept', $_data) && ($_data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0), 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_psk_identity')]
			]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $_data): bool => (array_key_exists('tls_connect', $_data) && $_data['tls_connect'] == HOST_ENCRYPTION_PSK) || (array_key_exists('tls_accept', $_data) && ($_data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0), 'type' => API_PSK, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_psk')]
			]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $_data): bool => (array_key_exists('tls_connect', $_data) && $_data['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE) || (array_key_exists('tls_accept', $_data) && ($_data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0), 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_issuer')]
			]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $_data): bool => (array_key_exists('tls_connect', $_data) && $_data['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE) || (array_key_exists('tls_accept', $_data) && ($_data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0), 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_subject')]
			]],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>				['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates_clear' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$hostids = array_column($data['hosts'], 'hostid');

		$count = $this->get([
			'countOutput' => true,
			'hostids' => $hostids,
			'editable' => true
		]);

		if ($count != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_hosts = DBfetchArrayAssoc(DBselect(
			'SELECT h.hostid,h.host,h.flags,h.status,h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,'.
				'h.ipmi_password,h.name,h.description,h.monitored_by,h.proxyid,h.proxy_groupid,h.tls_connect,'.
				'h.tls_accept,h.tls_psk_identity,h.tls_psk,h.tls_issuer,h.tls_subject,'.
				dbConditionCoalesce('hi.inventory_mode', HOST_INVENTORY_MANUAL, 'inventory_mode').
			' FROM hosts h'.
			' LEFT JOIN host_inventory hi ON h.hostid=hi.hostid'.
			' WHERE '.dbConditionId('h.hostid', $hostids)
		), 'hostid');

		foreach ($data['hosts'] as $i => $host) {
			if (!array_key_exists($host['hostid'], $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/hosts/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
			elseif ($db_hosts[$host['hostid']]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$field_name = array_key_exists('groups', $data) ? 'groups' : null;

				if ($field_name) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/hosts/'.($i + 1),
						_s('cannot update readonly parameter "%1$s" of discovered object', $field_name)
					));
				}
			}
		}

		if (array_key_exists('monitored_by', $data)) {
			self::checkProxiesAndProxyGroups([$data], null, '');
		}

		if (!$options || !array_key_exists('skip_tls_psk_pair_check', $options)) {
			$this->checkTlsPskPair($data, $db_hosts);
		}

		if (!array_key_exists('groups', $data) && !array_key_exists('macros', $data)
				&& !array_key_exists('templates', $data) && !array_key_exists('templates_clear', $data)) {
			return;
		}

		$hosts = $data['hosts'];

		$this->addObjectsByData($data, $hosts);
		$this->addAffectedObjects($hosts, $db_hosts);

		if (array_key_exists('groups', $data)) {
			$this->checkGroups($hosts, $db_hosts, '/groups', array_flip(array_column($data['groups'], 'groupid')));
			$this->checkHostsWithoutGroups($hosts, $db_hosts);
		}

		if (array_key_exists('macros', $data) && $data['macros']) {
			self::addHostMacroIds($hosts, $db_hosts);
		}

		if (array_key_exists('templates', $data)
				|| (array_key_exists('templates_clear', $data) && $data['templates_clear'])) {
			$path = array_key_exists('templates', $data) ? '/templates' : null;
			$template_indexes = array_key_exists('templates', $data)
				? array_flip(array_column($data['templates'], 'templateid'))
				: null;

			$path_clear = array_key_exists('templates_clear', $data) && $data['templates_clear']
				? '/templates_clear'
				: null;
			$template_clear_indexes = array_key_exists('templates_clear', $data) && $data['templates_clear']
				? array_flip(array_column($data['templates_clear'], 'templateid'))
				: null;

			$this->checkTemplates($hosts, $db_hosts, $path, $template_indexes, $path_clear,
				$template_clear_indexes
			);
			$this->checkTemplatesLinks($hosts, $db_hosts);
		}
	}

	private static function checkProxyFields(array $data): void {
		$proxy_data = array_intersect_key($data, array_flip(['proxyid', 'proxy_groupid']));

		if ($proxy_data && !array_key_exists('monitored_by', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_('The field "monitored_by" must be specified when changing proxy or proxy group for host monitoring.')
			);
		}
	}

	private static function checkTlsFields(array $data): void {
		$tls_fields =
			array_flip(['tls_connect', 'tls_accept', 'tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject']);

		if (array_intersect_key($data, $tls_fields)) {
			if (!array_key_exists('tls_connect', $data) || !array_key_exists('tls_accept', $data)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('Both "tls_connect" and "tls_accept" fields must be specified when changing settings of connection encryption.')
				);
			}

			if (($data['tls_connect'] == HOST_ENCRYPTION_PSK || $data['tls_accept'] & HOST_ENCRYPTION_PSK)
					&& (!array_key_exists('tls_psk_identity', $data) || !array_key_exists('tls_psk', $data))) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('Both "tls_psk_identity" and "tls_psk" fields must be specified when changing the PSK for connection encryption.')
				);
			}
		}
	}

	/**
	 * Removes templates and interfaces from hosts.
	 *
	 * @param array $data
	 * @param array $data['interfaces']         Interfaces to delete from the hosts.
	 * @param array $data['templateids']        Templates to unlink from host.
	 * @param array $data['templateids_clear']  Templates to unlink and clear from host.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$this->validateMassRemove($data, $hosts, $db_hosts);

		$this->updateForce($hosts, $db_hosts);

		if (isset($data['interfaces'])) {
			$options = [
				'hostids' => $data['hostids'],
				'interfaces' => zbx_toArray($data['interfaces'])
			];
			API::HostInterface()->massRemove($options);
		}

		return ['hostids' => $data['hostids']];
	}

	private function validateMassRemove(array &$data, ?array &$hosts, ?array &$db_hosts): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'hostids' =>			['type' => API_IDS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => true],
			'groupids' =>			['type' => API_IDS, 'flags' => API_NORMALIZE, 'uniq' => true],
			'macros' =>				['type' => API_USER_MACROS, 'flags' => API_NORMALIZE, 'uniq' => true, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'templateids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE, 'uniq' => true],
			'templateids_clear' =>	['type' => API_IDS, 'flags' => API_NORMALIZE, 'uniq' => true],
			'interfaces' =>			['type' => API_ANY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = $this->get([
			'output' => ['hostid', 'host', 'flags'],
			'hostids' => $data['hostids'],
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($data['hostids'] as $i => $hostid) {
			if (!array_key_exists($hostid, $db_hosts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
					'/hostids/'.($i + 1), _('object does not exist, or you have no permissions to it')
				));
			}
			elseif ($db_hosts[$hostid]['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$field_name = null;

				if (array_key_exists('interfaces', $data) && $data['interfaces']) {
					$field_name = 'interfaces';
				}

				if (array_key_exists('groupids', $data) && $data['groupids']) {
					$field_name = 'groups';
				}

				if ($field_name !== null) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/hostids/'.($i + 1),
						_s('cannot update readonly parameter "%1$s" of discovered object', $field_name)
					));
				}
			}
		}

		$hosts = [];

		foreach ($data['hostids'] as $hostid) {
			$hosts[] = ['hostid' => $hostid];
		}

		$data = CArrayHelper::renameKeys($data, ['macros' => 'macro_names']);

		$this->addObjectsByData($data, $hosts);
		$this->addAffectedObjects($hosts, $db_hosts);
		$this->addUnchangedObjects($hosts, $db_hosts, $data);

		if (array_key_exists('groupids', $data) && $data['groupids']) {
			$this->checkGroups($hosts, $db_hosts, '/groupids', array_flip($data['groupids']));
			$this->checkHostsWithoutGroups($hosts, $db_hosts);
		}

		if ((array_key_exists('templateids', $data) && $data['templateids'])
				|| (array_key_exists('templateids_clear', $data) && $data['templateids_clear'])) {
			$path_clear = array_key_exists('templateids_clear', $data) && $data['templateids_clear']
				? '/templateids_clear'
				: null;
			$template_clear_indexes = array_key_exists('templateids_clear', $data) && $data['templateids_clear']
				? array_flip($data['templateids_clear'])
				: null;

			$this->checkTemplates($hosts, $db_hosts, null, null, $path_clear, $template_clear_indexes);
			$this->checkTemplatesLinks($hosts, $db_hosts);
		}
	}

	private function addObjectsByData(array $data, array &$templates): void {
		self::addGroupsByData($data, $templates);
		self::addMacrosByData($data, $templates);
		$this->addTemplatesByData($data, $templates);
		self::addTemplatesClearByData($data, $templates);
	}

	private function addUnchangedObjects(array &$hosts, array $db_hosts, array $del_objectids = []): void {
		$this->addUnchangedGroups($hosts, $db_hosts, $del_objectids);
		$this->addUnchangedMacros($hosts, $db_hosts, $del_objectids);
		$this->addUnchangedTemplates($hosts, $db_hosts, $del_objectids);
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array      $hostids
	 * @param array|null $db_hosts
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$hostids, ?array &$db_hosts = null): void {
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
	}

	/**
	 * @param array $db_hosts
	 */
	public static function validateDeleteForce(array $db_hosts): void {
		self::checkUsedInActions($db_hosts);
		self::checkMaintenances(array_keys($db_hosts));
	}

	private static function checkUsedInActions(array $db_hosts): void {
		$hostids = array_keys($db_hosts);

		$row = DBfetch(DBselect(
			'SELECT c.value AS hostid,a.name'.
			' FROM conditions c'.
			' JOIN actions a ON c.actionid=a.actionid'.
			' WHERE c.conditiontype='.ZBX_CONDITION_TYPE_HOST.
				' AND '.dbConditionString('c.value', $hostids),
			1
		));

		if (!$row) {
			$row = DBfetch(DBselect(
				'SELECT och.hostid,a.name'.
				' FROM opcommand_hst och'.
				' JOIN operations o ON och.operationid=o.operationid'.
				' JOIN actions a ON o.actionid=a.actionid'.
				' WHERE '.dbConditionId('och.hostid', $hostids),
				1
			));
		}

		if ($row) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete host "%1$s": %2$s.',
				$db_hosts[$row['hostid']]['host'], _s('action "%1$s" uses this host', $row['name'])
			));
		}
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
			'SELECT DISTINCT mh.maintenanceid,m.name'.
			' FROM maintenances_hosts mh'.
			' JOIN maintenances m ON mh.maintenanceid=m.maintenanceid'.
			' WHERE '.dbConditionId('mh.hostid', $hostids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_hosts mh1'.
					' WHERE mh.maintenanceid=mh1.maintenanceid'.
						' AND '.dbConditionId('mh1.hostid', $hostids, true).
				')'.
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM maintenances_groups mg'.
					' WHERE mh.maintenanceid=mg.maintenanceid'.
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
		self::validateDeleteForce($db_hosts);

		$hostids = array_keys($db_hosts);

		// delete the discovery rules first
		$db_lld_rules = DB::select('items', [
			'output' => ['itemid', 'name'],
			'filter' => [
				'hostid' => $hostids,
				'flags' => ZBX_FLAG_DISCOVERY_RULE
			],
			'preservekeys' => true
		]);

		if ($db_lld_rules) {
			CDiscoveryRule::deleteForce($db_lld_rules);
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

		// delete host inventory
		DB::delete('host_inventory', ['hostid' => $hostids]);

		self::deleteHgSets($db_hosts);
		DB::delete('hosts_groups', ['hostid' => $hostids]);

		// delete host
		DB::delete('host_proxy', ['hostid' => $hostids]);
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

		$this->addRelatedHostGroups($options, $result);

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

	private function addRelatedHostGroups(array $options, array &$result): void {
		if ($options['selectHostGroups'] === null || $options['selectHostGroups'] === API_OUTPUT_COUNT) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'hostid', 'groupid', 'hosts_groups');
		$groups = API::HostGroup()->get([
			'output' => $options['selectHostGroups'],
			'groupids' => $relation_map->getRelatedIds(),
			'preservekeys' => true
		]);

		$result = $relation_map->mapMany($result, $groups, 'hostgroups');
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
			'selectMacros' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['hostmacroid', 'macro', 'value', 'type', 'description', 'automatic'])]
		]];

		$options_filter = array_intersect_key($options, $api_input_rules['fields']);

		if (!CApiInputValidator::validate($api_input_rules, $options_filter, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'monitored_by' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP]), 'default' => DB::getDefault('hosts', 'monitored_by')],
			'proxyid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'monitored_by', 'in' => ZBX_MONITORED_BY_PROXY], 'type' => API_ID, 'flags' => API_REQUIRED],
									['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'proxy_groupid' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'monitored_by', 'in' => ZBX_MONITORED_BY_PROXY_GROUP], 'type' => API_ID, 'flags' => API_REQUIRED],
									['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'tls_connect' =>		['type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]), 'default' => DB::getDefault('hosts', 'tls_connect')],
			'tls_accept' =>			['type' => API_INT32, 'in' => implode(':', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE]), 'default' => DB::getDefault('hosts', 'tls_accept')],
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_PSK || ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_psk_identity')]
			]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_PSK || ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0, 'type' => API_PSK, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_psk')]
			]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE || ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_issuer')]
			]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE || ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_subject')]
			]],
			'groups' =>			['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
				'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')],
										['else' => true, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')]
				]],
				'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkProxiesAndProxyGroups($hosts);
		self::checkTlsPskPairs($hosts);
		$this->checkGroups($hosts);
		$this->checkTemplates($hosts);

		$host_name_parser = new CHostNameParser();

		$host_db_fields = ['host' => null];

		foreach ($hosts as &$host) {
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
		}
		unset($host);

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
	}

	/**
	 * @param array       $hosts
	 * @param array|null  $db_hosts
	 * @param string|null $path
	 *
	 * @throws APIException
	 */
	private static function checkProxiesAndProxyGroups(array $hosts, ?array $db_hosts = null,
			?string $path = null): void {
		$host_indexes = [
			'proxyids' => [],
			'proxy_groupids' => []
		];

		foreach ($hosts as $i => $host) {
			if ($db_hosts !== null && $db_hosts[$host['hostid']]['flags'] != ZBX_FLAG_DISCOVERY_NORMAL) {
				continue;
			}

			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				if (!array_key_exists('proxyid', $host)) {
					continue;
				}

				if ($host['proxyid'] == 0) {
					$path = $path === null ? '/'.($i + 1) : '';

					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/proxyid', _('object does not exist, or you have no permissions to it')
					));
				}

				if (($db_hosts === null || bccomp($host['proxyid'], $db_hosts[$host['hostid']]['proxyid']) != 0)
						&& !array_key_exists($host['proxyid'], $host_indexes['proxyids'])) {
					$host_indexes['proxyids'][$host['proxyid']] = $i;
				}
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				if (!array_key_exists('proxy_groupid', $host)) {
					continue;
				}

				if ($host['proxy_groupid'] == 0) {
					$path = $path === null ? '/'.($i + 1) : '';

					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/proxy_groupid', _('object does not exist, or you have no permissions to it')
					));
				}

				if (($db_hosts === null || bccomp($host['proxy_groupid'], $db_hosts[$host['hostid']]['proxy_groupid']) != 0)
						&& !array_key_exists($host['proxy_groupid'], $host_indexes['proxy_groupids'])) {
					$host_indexes['proxy_groupids'][$host['proxy_groupid']] = $i;
				}
			}
		}

		if ($host_indexes['proxyids']) {
			$db_proxies = API::Proxy()->get([
				'output' => [],
				'proxyids' => array_keys($host_indexes['proxyids']),
				'preservekeys' => true
			]);

			foreach ($host_indexes['proxyids'] as $proxyid => $i) {
				if (!array_key_exists($proxyid, $db_proxies)) {
					$path = $path === null ? '/'.($i + 1) : '';

					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/proxyid', _('object does not exist, or you have no permissions to it')
					));
				}
			}
		}

		if ($host_indexes['proxy_groupids']) {
			$db_proxy_groups = API::ProxyGroup()->get([
				'output' => [],
				'proxy_groupids' => array_keys($host_indexes['proxy_groupids']),
				'preservekeys' => true
			]);

			foreach ($host_indexes['proxy_groupids'] as $proxy_groupid => $i) {
				if (!array_key_exists($proxy_groupid, $db_proxy_groups)) {
					$path = $path === null ? '/'.($i + 1) : '';

					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/proxy_groupid', _('object does not exist, or you have no permissions to it')
					));
				}
			}
		}
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $hosts			hosts data array
	 * @param array $db_hosts		db hosts data array
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$hosts, ?array &$db_hosts = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hosts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'hostids' => array_column($hosts, 'hostid'),
			'editable' => true
		]);

		if ($count != count($hosts)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_hosts = DB::select('hosts', [
			'output' => ['hostid', 'host', 'flags', 'monitored_by', 'proxyid', 'proxy_groupid', 'tls_connect',
				'tls_accept', 'tls_psk_identity', 'tls_psk', 'tls_issuer', 'tls_subject'
			],
			'hostids' => array_column($hosts, 'hostid'),
			'preservekeys' => true
		]);

		$this->checkDiscoveredFields($hosts, $db_hosts);

		foreach ($hosts as $i => &$host) {
			$db_host = $db_hosts[$host['hostid']];

			if ($db_host['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$api_input_rules = self::getDiscoveredValidationRules();
			}
			else {
				$host += array_intersect_key($db_host, array_flip(['monitored_by', 'tls_connect', 'tls_accept']));

				self::addRequiredFieldsByMonitoredBy($host, $db_host);
				self::addRequiredFieldsByTls($host, $db_host);

				$api_input_rules = self::getValidationRules();
			}

			if (!CApiInputValidator::validate($api_input_rules, $host, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($host);

		self::checkProxiesAndProxyGroups($hosts, $db_hosts);
		self::checkTlsPskPairs($hosts, $db_hosts);

		foreach ($hosts as &$host) {
			// Property 'auto_compress' is not supported for hosts.
			if (array_key_exists('auto_compress', $host)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect input parameters.'));
			}
		}
		unset($host);

		$this->addAffectedObjects($hosts, $db_hosts);

		$this->checkGroups($hosts, $db_hosts);
		$this->checkTemplates($hosts, $db_hosts);
		$this->checkTemplatesLinks($hosts, $db_hosts);
		$hosts = $this->validateHostMacros($hosts, $db_hosts);

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
		}
		unset($host);

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
	}

	private function checkDiscoveredFields(array $hosts, array $db_hosts): void {
		$update_discovered_validator = new CUpdateDiscoveredValidator([
			'allowed' => ['hostid', 'status', 'description', 'tags', 'macros', 'inventory', 'templates',
				'templates_clear'
			],
			'messageAllowedField' => _('Cannot update "%2$s" for a discovered host "%1$s".')
		]);

		foreach ($hosts as $host) {
			$db_host = $db_hosts[$host['hostid']];
			$host_name = array_key_exists('host', $host) ? $host['host'] : $db_host['host'];

			$update_discovered_validator->setObjectName($host_name);
			$this->checkPartialValidator($host, $update_discovered_validator, $db_host);
		}
	}

	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'hostid' =>				['type' => API_ANY],
			'monitored_by' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_MONITORED_BY_SERVER, ZBX_MONITORED_BY_PROXY, ZBX_MONITORED_BY_PROXY_GROUP])],
			'proxyid' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'monitored_by', 'in' => ZBX_MONITORED_BY_PROXY], 'type' => API_ID],
										['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'proxy_groupid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'monitored_by', 'in' => ZBX_MONITORED_BY_PROXY_GROUP], 'type' => API_ID],
										['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'tls_connect' =>		['type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE])],
			'tls_accept' =>			['type' => API_INT32, 'in' => implode(':', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE])],
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_PSK || ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk_identity')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_psk_identity')]
			]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_PSK || ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0, 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('hosts', 'tls_psk')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_psk')]
			]],
			'tls_issuer' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE || ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_issuer')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_issuer')]
			]],
			'tls_subject' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => $data['tls_connect'] == HOST_ENCRYPTION_CERTIFICATE || ($data['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE) != 0, 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hosts', 'tls_subject')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('hosts', 'tls_subject')]
			]],
			'groups' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['groupid']], 'fields' => [
				'groupid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates_clear' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
				'hostmacroid' =>		['type' => API_ID],
				'macro' =>				['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')],
				'automatic' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_USERMACRO_MANUAL])]
			]]
		]];
	}

	private static function getDiscoveredValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'hostid' =>				['type' => API_ANY],
			'templates' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'templates_clear' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['templateid']], 'fields' => [
				'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'tags' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
				'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('host_tag', 'tag')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('host_tag', 'value'), 'default' => DB::getDefault('host_tag', 'value')]
			]],
			'macros' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
				'hostmacroid' =>		['type' => API_ID],
				'macro' =>				['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
				'type' =>				['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
				'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')],
				'automatic' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_USERMACRO_MANUAL])]
			]]
		]];
	}

	private static function addRequiredFieldsByMonitoredBy(array &$host, array $db_host): void {
		if ($host['monitored_by'] != $db_host['monitored_by']) {
			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$host += array_intersect_key($db_host, array_flip(['proxyid']));
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				$host += array_intersect_key($db_host, array_flip(['proxy_groupid']));
			}
		}
	}

	private static function addRequiredFieldsByTls(array &$host, array $db_host): void {
		if (($host['tls_connect'] == HOST_ENCRYPTION_PSK || $host['tls_accept'] & HOST_ENCRYPTION_PSK)
				&& $db_host['tls_connect'] != HOST_ENCRYPTION_PSK
				&& ($db_host['tls_accept'] & HOST_ENCRYPTION_PSK) == 0) {
			$host += [
				'tls_psk_identity' => $db_host['tls_psk_identity'],
				'tls_psk' => $db_host['tls_psk']
			];
		}
	}

	protected function addAffectedObjects(array $hosts, array &$db_hosts): void {
		$this->addAffectedGroups($hosts, $db_hosts);
		$this->addAffectedTemplates($hosts, $db_hosts);
		$this->addAffectedTags($hosts, $db_hosts);
		$this->addAffectedMacros($hosts, $db_hosts);
	}

	/**
	 * Check tls_psk_identity have same tls_psk value across all hosts, proxies and autoregistration.
	 *
	 * @param array      $hosts
	 * @param array|null $db_hosts
	 *
	 * @throws APIException
	 */
	private static function checkTlsPskPairs(array $hosts, ?array $db_hosts = null): void {
		$psk_pairs = [];
		$tls_psk_fields = array_flip(['tls_psk_identity', 'tls_psk']);
		$psk_hostids = $db_hosts !== null ? [] : null;

		foreach ($hosts as $i => $host) {
			if ($db_hosts !== null && $db_hosts[$host['hostid']]['flags'] != ZBX_FLAG_DISCOVERY_NORMAL) {
				continue;
			}

			$psk_pair = array_intersect_key($host, $tls_psk_fields);

			if ($psk_pair) {
				if ($host['tls_connect'] == HOST_ENCRYPTION_PSK || $host['tls_accept'] & HOST_ENCRYPTION_PSK) {
					if ($db_hosts !== null) {
						$psk_pair += array_intersect_key($db_hosts[$host['hostid']], $tls_psk_fields);
						$psk_hostids[] = $host['hostid'];
					}

					$psk_pairs[$i] = $psk_pair;
				}
				elseif ($db_hosts !== null
						&& ($db_hosts[$host['hostid']]['tls_connect'] == HOST_ENCRYPTION_PSK
							|| $db_hosts[$host['hostid']]['tls_accept'] & HOST_ENCRYPTION_PSK)) {
					$psk_hostids[] = $host['hostid'];
				}
			}
		}

		if ($psk_pairs) {
			CApiPskHelper::checkPskOfIdentitiesAmongGivenPairs($psk_pairs);
			CApiPskHelper::checkPskOfIdentitiesInAutoregistration($psk_pairs);
			CApiPskHelper::checkPskOfIdentitiesAmongHosts($psk_pairs, $psk_hostids);
			CApiPskHelper::checkPskOfIdentitiesAmongProxies($psk_pairs);
		}
	}

	private function checkTlsPskPair(array $data, array $db_hosts): void {
		$psk_pair = array_intersect_key($data, array_flip(['tls_psk_identity', 'tls_psk']));

		if (!$psk_pair) {
			return;
		}

		CApiPskHelper::checkPskOfIdentityInAutoregistration($psk_pair);
		CApiPskHelper::checkPskOfIdentityAmongHosts($psk_pair, array_keys($db_hosts));
		CApiPskHelper::checkPskOfIdentityAmongProxies($psk_pair);
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
}
