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
class CItem extends CItemGeneral {

	protected $tableName = 'items';

	protected $tableAlias = 'i';


	/**
	 * Get items data.
	 *
	 * @param array $options
	 * @param array $options['itemids']
	 * @param array $options['hostids']
	 * @param array $options['groupids']
	 * @param array $options['triggerids']
	 * @param array $options['applicationids']
	 * @param boolean $options['status']
	 * @param boolean $options['templated_items']
	 * @param boolean $options['editable']
	 * @param boolean $options['count']
	 * @param string $options['pattern']
	 * @param int $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sql_parts = array(
			'select'	=> array('items' => 'i.itemid'),
			'from'		=> array('items' => 'items i'),
			'where'		=> array('webtype' => 'i.type<>'.ITEM_TYPE_HTTPTEST),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'itemids'					=> null,
			'interfaceids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'applicationids'			=> null,
			'discoveryids'				=> null,
			'webitems'					=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			'group'						=> null,
			'host'						=> null,
			'application'				=> null,
			'belongs'					=> null,
			'with_triggers'				=> null,
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
			'selectInterfaces'			=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectApplications'		=> null,
			'selectDiscoveryRule'		=> null,
			'selectItemDiscovery'       => null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sql_parts['select']['items']);

			$dbTable = DB::getSchema('items');
			$sql_parts['select']['itemid'] = 'i.itemid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sql_parts['select'][$field] = 'i.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + permission check
		if (USER_TYPE_SUPER_ADMIN == $user_type || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS('.
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

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sql_parts['where']['itemid'] = DBcondition('i.itemid', $options['itemids']);
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
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sql_parts['select']['interfaceid'] = 'i.interfaceid';
			}

			$sql_parts['where']['interfaceid'] = DBcondition('i.interfaceid', $options['interfaceids']);

			if (!is_null($options['groupCount'])) {
				$sql_parts['group']['i'] = 'i.interfaceid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';

			if (!is_null($options['groupCount'])) {
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sql_parts['select']['proxyid'] = 'h.proxy_hostid';
			}

			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where'][] = DBcondition('h.proxy_hostid', $options['proxyids']);
			$sql_parts['where'][] = 'h.hostid=i.hostid';

			if (!is_null($options['groupCount'])) {
				$sql_parts['group']['h'] = 'h.proxy_hostid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}
			$sql_parts['from']['functions'] = 'functions f';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['if'] = 'i.itemid=f.itemid';
		}

		// applicationids
		if (!is_null($options['applicationids'])) {
			zbx_value2array($options['applicationids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['applicationid'] = 'ia.applicationid';
			}
			$sql_parts['from']['items_applications'] = 'items_applications ia';
			$sql_parts['where'][] = DBcondition('ia.applicationid', $options['applicationids']);
			$sql_parts['where']['ia'] = 'ia.itemid=i.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}
			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
		}

		// discoveryids
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['discoveryid'] = 'id.parent_itemid';
			}

			$sql_parts['from']['item_discovery'] = 'item_discovery id';
			$sql_parts['where'][] = DBcondition('id.parent_itemid', $options['discoveryids']);
			$sql_parts['where']['idi'] = 'i.itemid=id.itemid';

			if (!is_null($options['groupCount'])) {
				$sql_parts['group']['id'] = 'id.parent_itemid';
			}
		}

		// webitems
		if (!is_null($options['webitems'])) {
			unset($sql_parts['where']['webtype']);
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sql_parts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sql_parts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sql_parts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sql_parts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sql_parts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('items i', $options, $sql_parts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sql_parts['from']['hosts'] = 'hosts h';
				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where']['h'] = DBcondition('h.host', $options['filter']['host'], false, true);
			}
		}

		// group
		if (!is_null($options['group'])) {
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['name'] = 'g.name';
			}
			$sql_parts['from']['groups'] = 'groups g';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where']['ghg'] = 'g.groupid = hg.groupid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = ' UPPER(g.name)='.zbx_dbstr(zbx_strtoupper($options['group']));
		}

		// host
		if (!is_null($options['host'])) {
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['host'] = 'h.host';
			}
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
			$sql_parts['where'][] = ' UPPER(h.host)='.zbx_dbstr(zbx_strtoupper($options['host']));
		}

		// application
		if (!is_null($options['application'])) {
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['application'] = 'a.name as application';
			}
			$sql_parts['from']['applications'] = 'applications a';
			$sql_parts['from']['items_applications'] = 'items_applications ia';
			$sql_parts['where']['aia'] = 'a.applicationid = ia.applicationid';
			$sql_parts['where']['iai'] = 'ia.itemid=i.itemid';
			$sql_parts['where'][] = ' UPPER(a.name)='.zbx_dbstr(zbx_strtoupper($options['application']));
		}

		// with_triggers
		if (!is_null($options['with_triggers'])) {
			if ($options['with_triggers'] == 1) {
				$sql_parts['where'][] = ' EXISTS (SELECT ff.functionid FROM functions ff WHERE ff.itemid=i.itemid)';
			}
			else {
				$sql_parts['where'][] = 'NOT EXISTS (SELECT ff.functionid FROM functions ff WHERE ff.itemid=i.itemid)';
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sql_parts['select']['items'] = 'i.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT i.itemid) as rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sql_parts['group'] as $key => $fields) {
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'i');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$itemids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if (!empty($sql_parts['select'])) {
			$sql_select .= implode(',', $sql_parts['select']);
		}
		if (!empty($sql_parts['from'])) {
			$sql_from .= implode(',', $sql_parts['from']);
		}
		if (!empty($sql_parts['where'])) {
			$sql_where .= ' AND '.implode(' AND ', $sql_parts['where']);
		}
		if (!empty($sql_parts['group'])) {
			$sql_where .= ' GROUP BY '.implode(',', $sql_parts['group']);
		}
		if (!empty($sql_parts['order'])) {
			$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
		}
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('i.itemid', $nodeids).
					$sql_where.
					$sql_group.
					$sql_order;
		$res = DBselect($sql, $sql_limit);
		while ($item = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $item;
				}
				else {
					$result = $item['rowscount'];
				}
			}
			else {
				$itemids[$item['itemid']] = $item['itemid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$item['itemid']] = array('itemid' => $item['itemid']);
				}
				else {
					if (!isset($result[$item['itemid']])) {
						$result[$item['itemid']]= array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$item['itemid']]['hosts'])) {
						$result[$item['itemid']]['hosts'] = array();
					}
					if (!is_null($options['selectTriggers']) && !isset($result[$item['itemid']]['triggers'])) {
						$result[$item['itemid']]['triggers'] = array();
					}
					if (!is_null($options['selectGraphs']) && !isset($result[$item['itemid']]['graphs'])) {
						$result[$item['itemid']]['graphs'] = array();
					}
					if (!is_null($options['selectApplications']) && !isset($result[$item['itemid']]['applications'])) {
						$result[$item['itemid']]['applications'] = array();
					}
					if (!is_null($options['selectDiscoveryRule']) && !isset($result[$item['itemid']]['discoveryRule'])) {
						$result[$item['itemid']]['discoveryRule'] = array();
					}

					// triggerids
					if (isset($item['triggerid']) && is_null($options['selectTriggers'])) {
						if (!isset($result[$item['itemid']]['triggers'])) {
							$result[$item['itemid']]['triggers'] = array();
						}
						$result[$item['itemid']]['triggers'][] = array('triggerid' => $item['triggerid']);
						unset($item['triggerid']);
					}
					// graphids
					if (isset($item['graphid']) && is_null($options['selectGraphs'])) {
						if (!isset($result[$item['itemid']]['graphs'])) {
							$result[$item['itemid']]['graphs'] = array();
						}
						$result[$item['itemid']]['graphs'][] = array('graphid' => $item['graphid']);
						unset($item['graphid']);
					}
					// applicationids
					if (isset($item['applicationid']) && is_null($options['selectApplications'])) {
						if (!isset($result[$item['itemid']]['applications'])) {
							$result[$item['itemid']]['applications'] = array();
						}
						$result[$item['itemid']]['applications'][] = array('applicationid' => $item['applicationid']);
						unset($item['applicationid']);
					}

					$result[$item['itemid']] += $item;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding hosts
		if (!is_null($options['selectHosts'])) {
			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)) {
				$obj_params = array(
					'nodeids' => $nodeids,
					'itemids' => $itemids,
					'templated_hosts' => 1,
					'output' => $options['selectHosts'],
					'nopermissions' => 1,
					'preservekeys' => 1
				);
				$hosts = API::Host()->get($obj_params);
				foreach ($hosts as $host) {
					$hitems = $host['items'];
					unset($host['items']);
					foreach ($hitems as $item) {
						$result[$item['itemid']]['hosts'][] = $host;
					}
				}

				$templates = API::Template()->get($obj_params);
				foreach ($templates as $template) {
					$titems = $template['items'];
					unset($template['items']);
					foreach ($titems as $item) {
						$result[$item['itemid']]['hosts'][] = $template;
					}
				}
			}
		}

		// adding interfaces
		if (!is_null($options['selectInterfaces'])) {
			if (is_array($options['selectInterfaces']) || str_in_array($options['selectInterfaces'], $subselects_allowed_outputs)) {
				$obj_params = array(
					'nodeids' => $nodeids,
					'itemids' => $itemids,
					'output' => $options['selectInterfaces'],
					'nopermissions' => 1,
					'preservekeys' => 1
				);
				$interfaces = API::HostInterface()->get($obj_params);
				foreach ($interfaces as $interface) {
					$hitems = $interface['items'];
					unset($interface['items']);
					foreach ($hitems as $item) {
						$result[$item['itemid']]['interfaces'][] = $interface;
					}
				}
			}
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			$obj_params = array(
				'nodeids' => $nodeids,
				'itemids' => $itemids,
				'preservekeys' => 1
			);

			if (in_array($options['selectTriggers'], $subselects_allowed_outputs)) {
				$obj_params['output'] = $options['selectTriggers'];
				$triggers = API::Trigger()->get($obj_params);

				if (!is_null($options['limitSelects'])) {
					order_result($triggers, 'name');
				}
				foreach ($triggers as $triggerid => $trigger) {
					unset($triggers[$triggerid]['items']);
					$count = array();
					foreach ($trigger['items'] as $item) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$item['itemid']])) {
								$count[$item['itemid']] = 0;
							}
							$count[$item['itemid']]++;

							if ($count[$item['itemid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$item['itemid']]['triggers'][] = &$triggers[$triggerid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectTriggers']) {
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$triggers = API::Trigger()->get($obj_params);
				$triggers = zbx_toHash($triggers, 'itemid');

				foreach ($result as $itemid => $item) {
					if (isset($triggers[$itemid])) {
						$result[$itemid]['triggers'] = $triggers[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['triggers'] = 0;
					}
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			$obj_params = array(
				'nodeids' => $nodeids,
				'itemids' => $itemids,
				'preservekeys' => 1
			);

			if (in_array($options['selectGraphs'], $subselects_allowed_outputs)) {
				$obj_params['output'] = $options['selectGraphs'];
				$graphs = API::Graph()->get($obj_params);

				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
				}
				foreach ($graphs as $graphid => $graph) {
					unset($graphs[$graphid]['items']);
					$count = array();
					foreach ($graph['items'] as $item) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$item['itemid']])) {
								$count[$item['itemid']] = 0;
							}
							$count[$item['itemid']]++;

							if ($count[$item['itemid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$item['itemid']]['graphs'][] = &$graphs[$graphid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectGraphs']) {
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$graphs = API::Graph()->get($obj_params);
				$graphs = zbx_toHash($graphs, 'itemid');

				foreach ($result as $itemid => $item) {
					if (isset($graphs[$itemid])) {
						$result[$itemid]['graphs'] = $graphs[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['graphs'] = 0;
					}
				}
			}
		}

		// adding applications
		if (!is_null($options['selectApplications']) && str_in_array($options['selectApplications'], $subselects_allowed_outputs)) {
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectApplications'],
				'itemids' => $itemids,
				'preservekeys' => 1
			);
			$applications = API::Application()->get($obj_params);
			foreach ($applications as $application) {
				$aitems = $application['items'];
				unset($application['items']);
				foreach ($aitems as $item) {
					$result[$item['itemid']]['applications'][] = $application;
				}
			}
		}

		// adding discoveryrule
		if (!is_null($options['selectDiscoveryRule'])) {
			$ruleids = $rule_map = array();

			$db_rules = DBselect(
				'SELECT id1.itemid,id2.parent_itemid'.
				' FROM item_discovery id1,item_discovery id2,items i'.
				' WHERE '.DBcondition('id1.itemid', $itemids).
					' AND id1.parent_itemid=id2.itemid'.
					' AND i.itemid=id1.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_CREATED
			);
			while ($rule = DBfetch($db_rules)) {
				$ruleids[$rule['parent_itemid']] = $rule['parent_itemid'];
				$rule_map[$rule['itemid']] = $rule['parent_itemid'];
			}

			$db_rules = DBselect(
				'SELECT id.parent_itemid, id.itemid'.
				' FROM item_discovery id, items i'.
				' WHERE '.DBcondition('id.itemid', $itemids).
					' AND i.itemid=id.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_CHILD
			);
			while ($rule = DBfetch($db_rules)) {
				$ruleids[$rule['parent_itemid']] = $rule['parent_itemid'];
				$rule_map[$rule['itemid']] = $rule['parent_itemid'];
			}

			$obj_params = array(
				'nodeids' => $nodeids,
				'itemids' => $ruleids,
				'filter' => array('flags' => null),
				'nopermissions' => 1,
				'preservekeys' => 1
			);

			if (is_array($options['selectDiscoveryRule']) || str_in_array($options['selectDiscoveryRule'], $subselects_allowed_outputs)) {
				$obj_params['output'] = $options['selectDiscoveryRule'];
				$discoveryRules = $this->get($obj_params);

				foreach ($result as $itemid => $item) {
					if (isset($rule_map[$itemid]) && isset($discoveryRules[$rule_map[$itemid]])) {
						$result[$itemid]['discoveryRule'] = $discoveryRules[$rule_map[$itemid]];
					}
				}
			}
		}

		// add other related objects
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
	 * Get itemid by host.name and item.key.
	 *
	 * @param array $item_data
	 * @param array $item_data['key_']
	 * @param array $item_data['hostid']
	 *
	 * @return int|bool
	 */
	public function getObjects($itemData){
		$options = array(
			'filter' => $itemData,
			'output'=>API_OUTPUT_EXTEND,
			'webitems' => 1,
		);

		if(isset($itemData['node']))
			$options['nodeids'] = getNodeIdByNodeName($itemData['node']);
		else if(isset($itemData['nodeids']))
			$options['nodeids'] = $itemData['nodeids'];

		$result = $this->get($options);

	return $result;
	}

	/**
	 * Check if item exists.
	 *
	 * @param array $object
	 *
	 * @return bool
	 */
	public function exists(array $object){
		$options = array(
			'filter' => array('key_' => $object['key_']),
			'webitems' => 1,
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		if(isset($object['hostid'])) $options['hostids'] = $object['hostid'];
		if(isset($object['host'])) $options['filter']['host'] = $object['host'];

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

	return !empty($objs);
	}

	/**
	 * Items data validation.
	 *
	 * @param array $items
	 * @param bool $update checks for updating items
	 */
	protected function checkInput(array &$items, $update=false) {
		foreach ($items as $inum => $item) {
			$items[$inum]['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
		}
		// validate if everything is ok with 'item->inventory fields' linkage
		self::validateInventoryLinks($items, $update);
		parent::checkInput($items, $update);
	}

	/**
	 * Create item.
	 *
	 * @param $items
	 *
	 * @return array
	 */
	public function create($items){
		$items = zbx_toArray($items);

		$this->checkInput($items);

		$this->createReal($items);

		$this->inherit($items);

		return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	/**
	 * Create item.
	 *
	 * @param array $items
	 */
	protected function createReal(array &$items) {
		foreach ($items as $item) {
			$itemsExists = API::Item()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(
					'hostid' => $item['hostid'],
					'key_' => $item['key_']
				),
				'nopermissions' => 1
			));
			if (!empty($itemsExists)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item with key "%s" already exists on given host.', $item['key_']));
			}
		}

		$itemids = DB::insert('items', $items);

		$itemApplications = array();
		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];

			if (!isset($item['applications'])) {
				continue;
			}

				foreach ($item['applications'] as $appid) {
					if ($appid == 0) {
						continue;
					}

					$itemApplications[] = array(
						'applicationid' => $appid,
						'itemid' => $items[$key]['itemid']
					);
				}
			}

		if (!empty($itemApplications)) {
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Created: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Update items.
	 *
	 * @param $items
	 *
	 * @return void
	 */
	protected function updateReal($items){
		$items = zbx_toArray($items);

		$itemids = array();
		$data = array();
		foreach($items as $inum => $item){
			$itemsExists = API::Item()->get(array(
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array(
					'hostid' => $item['hostid'],
					'key_' => $item['key_']
				),
				'nopermissions' => 1
			));
			foreach($itemsExists as $inum => $itemExists){
				if(bccomp($itemExists['itemid'],$item['itemid']) != 0){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Host with item [ '.$item['key_'].' ] already exists');
				}
			}

			$data[] = array('values' => $item, 'where'=> array('itemid'=>$item['itemid']));
			$itemids[] = $item['itemid'];
		}
		$result = DB::update('items', $data);
		if(!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		$itemApplications = $aids = array();
		foreach($items as $key => $item){
			if(!isset($item['applications'])) continue;
			$aids[] = $item['itemid'];

			foreach($item['applications'] as $anum => $appid){
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				);
			}
		}

		if(!empty($aids)){
			DB::delete('items_applications', array('itemid' => $aids));
			DB::insert('items_applications', $itemApplications);
		}

// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Updated: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Update item
	 *
	 * @param array $items
	 * @return boolean
	 */
	public function update($items){
		$items = zbx_toArray($items);

		$this->checkInput($items, true);

		$this->updateReal($items);

		$this->inherit($items);

		return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	/**
	 * Delete items
	 *
	 * @param array $itemids
	 * @return
	 */
	public function delete($itemids, $nopermissions = false) {
			if (empty($itemids)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
			}

			$itemids = zbx_toHash($itemids);

			$options = array(
				'itemids' => $itemids,
				'editable' => true,
				'preservekeys' => true,
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => array('name')
			);
			$del_items = $this->get($options);

			// TODO: remove $nopermissions hack
			if (!$nopermissions) {
				foreach ($itemids as $itemid) {
					if (!isset($del_items[$itemid])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSIONS);
					}
					if ($del_items[$itemid]['templateid'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete templated item.');
					}
				}
			}

			// first delete child items
			$parent_itemids = $itemids;
			do {
				$db_items = DBselect('SELECT i.itemid FROM items i WHERE '.DBcondition('i.templateid', $parent_itemids));
				$parent_itemids = array();
				while ($db_item = DBfetch($db_items)) {
					$parent_itemids[] = $db_item['itemid'];
					$itemids[$db_item['itemid']] = $db_item['itemid'];
				}
			} while (!empty($parent_itemids));

			// delete graphs, leave if graph still have item
			$del_graphs = array();
			$db_graphs = DBselect(
				'SELECT gi.graphid'.
				' FROM graphs_items gi'.
				' WHERE '.DBcondition('gi.itemid', $itemids).
					' AND NOT EXISTS ('.
						'SELECT gii.gitemid'.
						' FROM graphs_items gii'.
						' WHERE gii.graphid=gi.graphid'.
							' AND '.DBcondition('gii.itemid', $itemids, true, false).
					')'
			);
			while($db_graph = DBfetch($db_graphs)){
				$del_graphs[$db_graph['graphid']] = $db_graph['graphid'];
			}

			if (!empty($del_graphs)) {
				$result = API::Graph()->delete($del_graphs, true);
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete graph.'));
				}
			}

			// check if any graphs are referencing this item
			$this->checkGraphReference($itemids);

			$triggers = API::Trigger()->get(array(
				'itemids' => $itemids,
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => true,
				'preservekeys' => true
			));
			if (!empty($triggers)) {
				$result = API::Trigger()->delete(array_keys($triggers), true);
				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete trigger.'));
				}
			}

			DB::delete('screens_items', array(
				'resourceid' => $itemids,
				'resourcetype' => array(SCREEN_RESOURCE_SIMPLE_GRAPH, SCREEN_RESOURCE_PLAIN_TEXT)
			));
			DB::delete('items', array('itemid' => $itemids));
			DB::delete('profiles', array(
				'idx' => 'web.favorite.graphids',
				'source' => 'itemid',
				'value_id' => $itemids
			));

			$item_data_tables = array(
				'trends',
				'trends_uint',
				'history_text',
				'history_log',
				'history_uint',
				'history_str',
				'history'
			);
			$insert = array();
			foreach ($itemids as $itemid) {
				foreach ($item_data_tables as $table) {
					$insert[] = array(
						'tablename' => $table,
						'field' => 'itemid',
						'value' => $itemid
					);
				}
			}
			DB::insert('housekeeper', $insert);

			// TODO: remove info from API
			foreach ($del_items as $item) {
				$host = reset($item['hosts']);
				info(_s('Deleted: Item "%1$s" on "%2$s".', $item['name'], $host['name']));
			}

			return array('itemids' => $itemids);
	}


	public function syncTemplates($data){
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if(!API::Host()->isWritable($data['hostids'])){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}
		if(!API::Template()->isReadable($data['templateids'])){
			self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
		}

		$selectFields = array();
		foreach($this->fieldRules as $key => $rules){
			if(!isset($rules['system']) && !isset($rules['host'])){
				$selectFields[] = $key;
			}
		}
		$options = array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'selectApplications' => API_OUTPUT_REFER,
			'output' => $selectFields,
			'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		);
		$items = $this->get($options);

		foreach($items as $inum => $item){
			$items[$inum]['applications'] = zbx_objectValues($item['applications'], 'applicationid');
		}

		$this->inherit($items, $data['hostids']);

		return true;
	}

	/**
	 * Inherit items to child hosts/templates.
	 * @param array $items
	 * @param null|array $hostids array of hostids which items should be inherited to
	 * @return bool
	 */
	protected function inherit(array $items, $hostids=null) {
		if (empty($items)) {
			return true;
		}

		$chdHosts = API::Host()->get(array(
			'output' => array('hostid', 'host', 'status'),
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'templateids' => zbx_objectValues($items, 'hostid'),
			'hostids' => $hostids,
			'preservekeys' => true,
			'nopermissions' => true,
			'templated_hosts' => true
		));
		if (empty($chdHosts)) {
			return true;
		}

		$insertItems = array();
		$updateItems = array();
		foreach ($chdHosts as $hostid => $host) {

			$templateids = zbx_toHash($host['templates'], 'templateid');

// skip items not from parent templates of current host
			$parentItems = array();
			foreach ($items as $inum => $item) {
				if (isset($templateids[$item['hostid']])) {
					$parentItems[$inum] = $item;
				}
			}
//----

// check existing items to decide insert or update
			$exItems = $this->get(array(
				'output' => array('itemid', 'type', 'key_', 'flags', 'templateid'),
				'hostids' => $hostid,
				'filter' => array('flags' => null),
				'preservekeys' => true,
				'nopermissions' => true,
			));
			$exItemsKeys = zbx_toHash($exItems, 'key_');
			$exItemsTpl = zbx_toHash($exItems, 'templateid');

			foreach ($parentItems as $item) {
				$exItem = null;

// update by templateid
				if (isset($exItemsTpl[$item['itemid']])) {
					$exItem = $exItemsTpl[$item['itemid']];
				}

// update by key
				if (isset($exItemsKeys[$item['key_']])) {
					$exItem = $exItemsKeys[$item['key_']];

					if ($exItem['flags'] != ZBX_FLAG_DISCOVERY_NORMAL) {
						$this->errorInheritFlags($exItem['flags'], $exItem['key_'], $host['host']);
					}
					elseif ($exItem['templateid'] > 0 && bccomp($exItem['templateid'], $item['itemid']) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Item "%1$s" already exists on "%2$s", inherited from another template.', $item['key_'], $host['host']));
					}
				}


				if ($host['status'] == HOST_STATUS_TEMPLATE || !isset($item['type'])) {
					unset($item['interfaceid']);
				}
				elseif ((isset($item['type']) && isset($exItem) && $item['type'] != $exItem['type']) || !isset($exItem)) {

					// find a matching interface
					$interface = self::findInterfaceForItem($item, $host['interfaces']);
					if ($interface) {
						$item['interfaceid'] = $interface['interfaceid'];
					}
					// no matching interface found, throw an error
					elseif($interface !== false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot find host interface on "%1$s" for item key "%2$s".', $host['host'], $item['key_']));
					}
				}

// coping item
				$newItem = $item;
				$newItem['hostid'] = $host['hostid'];
				$newItem['templateid'] = $item['itemid'];

// setting item application
				if (isset($item['applications'])) {
					$newItem['applications'] = get_same_applications_for_host($item['applications'], $host['hostid']);
				}
//--
				if ($exItem) {
					$newItem['itemid'] = $exItem['itemid'];
					$updateItems[] = $newItem;
				}
				else {
					$insertItems[] = $newItem;
				}
			}
		}

		if (!zbx_empty($insertItems)) {
			self::validateInventoryLinks($insertItems, false); // false means 'create'
			$this->createReal($insertItems);
		}

		if (!zbx_empty($updateItems)) {
			self::validateInventoryLinks($updateItems, true); // true means 'update'
			$this->updateReal($updateItems);
		}

		$inheritedItems = array_merge($updateItems, $insertItems);
		$this->inherit($inheritedItems);
	}

	/**
	 * Check, if items that are about to be inserted or updated violate the rule:
	 * only one item can be linked to a inventory filed.
	 * If everything is ok, function return true or throws Exception otherwise
	 * @static
	 * @param array $items
	 * @param bool $update whether this is update operation
	 * @return bool
	 */
	public static function validateInventoryLinks(array $items, $update=false){

		// inventory link field is not being updated, or being updated to 0, no need to validate anything then
		foreach($items as $i=>$item){
			if(!isset($item['inventory_link']) || $item['inventory_link'] == 0){
				unset($items[$i]);
			}
		}

		if(zbx_empty($items)){
			return true;
		}

		$possibleHostInventories = getHostInventories();
		if($update){
			// for successful validation we need three fields for each item: inventory_link, hostid and key_
			// problem is, that when we are updating an item, we might not have them, because they are not changed
			// so, we need to find out what is missing and use API to get the lacking info
			$itemsWithNoHostId = array();
			$itemsWithNoInventoryLink = array();
			$itemsWithNoKeys = array();
			foreach($items as $item){
				if(!isset($item['inventory_link'])){
					$itemsWithNoInventoryLink[$item['itemid']] = $item['itemid'];
				}
				if(!isset($item['hostid'])){
					$itemsWithNoHostId[$item['itemid']] = $item['itemid'];
				}
				if(!isset($item['key_'])){
					$itemsWithNoKeys[$item['itemid']] = $item['itemid'];
				}
			}
			$itemsToFind = array_merge($itemsWithNoHostId, $itemsWithNoInventoryLink, $itemsWithNoKeys);
			// are there any items with lacking info?
			if(!zbx_empty($itemsToFind)){
			// getting it
				$options = array(
					'output' => array('hostid', 'inventory_link', 'key_'),
					'filter' => array(
						'itemid' => $itemsToFind
					),
					'nopermissions' => true
				);
				$missingInfo = API::Item()->get($options);
				$missingInfo = zbx_toHash($missingInfo, 'itemid');
				// appending host ids, inventory_links and keys where they are needed
				foreach($items as $i=>$item){
					if (isset($missingInfo[$item['itemid']])){
						if(!isset($items[$i]['hostid'])){
							$items[$i]['hostid'] = $missingInfo[$item['itemid']]['hostid'];
						}
						if(!isset($items[$i]['inventory_link'])){
							$items[$i]['inventory_link'] = $missingInfo[$item['itemid']]['inventory_link'];
						}
						if(!isset($items[$i]['key_'])){
							$items[$i]['key_'] = $missingInfo[$item['itemid']]['key_'];
						}
					}
				}
			}
		}

		$hostIds = zbx_objectValues($items, 'hostid');

		// getting all inventory links on every affected host
		$options = array(
			'output' => array('key_', 'inventory_link', 'hostid'),
			'filter' => array(
				'hostid' => $hostIds
			),
			'nopermissions' => true
		);
		$itemsOnHostsInfo = API::Item()->get($options);

		// now, changing array to: 'hostid' => array('key_'=>'inventory_link')
		$linksOnHostsCurr = array();
		foreach($itemsOnHostsInfo as $info){
			// 0 means no link - we are not interested in those ones
			if($info['inventory_link'] != 0){
				if(!isset($linksOnHostsCurr[$info['hostid']])){
					$linksOnHostsCurr[$info['hostid']] = array($info['key_'] => $info['inventory_link']);
				}
				else{
					$linksOnHostsCurr[$info['hostid']][$info['key_']] = $info['inventory_link'];
				}
			}
		}

		$linksOnHostsFuture = array();

		foreach($items as $item){
			// checking if inventory_link value is a valid number
			if($update || $item['value_type'] != ITEM_VALUE_TYPE_LOG){
				// does inventory field with provided number exists?
				if(!isset($possibleHostInventories[$item['inventory_link']])){
					$maxVar = max(array_keys($possibleHostInventories));
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Item "%1$s" cannot populate a missing host inventory field number "%2$d". Choices are: from 0 (do not populate) to %3$d.', $item['name'], $item['inventory_link'], $maxVar)
					);
				}
			}

			if(!isset($linksOnHostsFuture[$item['hostid']])){
				$linksOnHostsFuture[$item['hostid']] = array($item['key_'] => $item['inventory_link']);
			}
			else{
				$linksOnHostsFuture[$item['hostid']][$item['key_']] = $item['inventory_link'];
			}
		}

		foreach($linksOnHostsFuture as $hostId => $linkFuture){
			if(isset($linksOnHostsCurr[$hostId])){
				$futureSituation = array_merge($linksOnHostsCurr[$hostId], $linksOnHostsFuture[$hostId]);
			}
			else{
				$futureSituation = $linksOnHostsFuture[$hostId];
			}
			$valuesCount = array_count_values($futureSituation);
			// if we have a duplicate inventory links after merging - we are in trouble
			if(max($valuesCount) > 1){
				// what inventory field caused this conflict?
				$conflictedLink = array_keys($valuesCount, 2);
				$conflictedLink = reset($conflictedLink);

				// which of updated items populates this link?
				$beingSavedItemName = '';
				foreach($items as $item){
					if($item['inventory_link'] == $conflictedLink){
						if(isset($item['name'])){
							$beingSavedItemName = $item['name'];
						}
						else{
							$options = array(
								'output' => array('name'),
								'filter' => array(
									'itemid' => $item['itemid'],
								),
								'nopermissions' => true
							);
							$thisItem = API::Item()->get($options);
							$beingSavedItemName = $thisItem[0]['name'];
						}
						break;
					}
				}

				// name of the original item that already populates the field
				$options = array(
					'output' => array('name'),
					'filter' => array(
						'hostid' => $hostId,
						'inventory_link' => $conflictedLink
					),
					'nopermissions' => true
				);
				$originalItem = API::Item()->get($options);
				$originalItemName = $originalItem[0]['name'];

				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s(
						'Two items ("%1$s" and "%2$s") cannot populate one host inventory field "%3$s", this would lead to a conflict.',
						$beingSavedItemName,
						$originalItemName,
						$possibleHostInventories[$conflictedLink]['title']
					)
				);
			}
		}
		return true;
	}


	public function addRelatedObjects(array $options, array $result) {

		// TODO: move selectItemHosts to CItemGeneral::addRelatedObjects();
		// TODO: move selectInterfaces to CItemGeneral::addRelatedObjects();
		// TODO: move selectTriggers to CItemGeneral::addRelatedObjects();
		// TODO: move selectGraphs to CItemGeneral::addRelatedObjects();
		// TODO: move selectApplications to CItemGeneral::addRelatedObjects();
		$result = parent::addRelatedObjects($options, $result);

		$itemids = zbx_objectValues($result, 'itemid');

		// adding item discovery
		if ($options['selectItemDiscovery']) {
			$itemDiscoveryOutput = $this->extendOutputOption('item_discovery', 'itemid', $options['selectItemDiscovery']);
			$itemDiscoveries = $this->select('item_discovery', array(
				'output' => $itemDiscoveryOutput,
				'filter' => array(
					'itemid' => $itemids
				)
			));
			foreach ($itemDiscoveries as $itemDiscovery) {
				$refId = $itemDiscovery['itemid'];
				$itemDiscovery = $this->unsetExtraFields('item_discovery', $itemDiscovery, $options['selectItemDiscovery']);

				$result[$refId]['itemDiscovery'] = $itemDiscovery;
			}
		}

		return $result;
	}
}
?>
