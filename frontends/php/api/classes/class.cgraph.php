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
 * File containing graph class for API.
 * @package API
 */
/**
 * Class containing methods for operations with graphs
 */
class CGraph extends CZBXAPI {

	protected $tableName = 'graphs';

	protected $tableAlias = 'g';

	/**
	* Get graph data
	*
	* @param array $options
	* @return array
	*/
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('graphid', 'name', 'graphtype');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sqlParts = array(
			'select'	=> array('graphs' => 'g.graphid'),
			'from'		=> array('graphs' => 'graphs g'),
			'where'		=> array(),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null,
		);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'graphids'					=> null,
			'itemids'					=> null,
			'discoveryids'				=> null,
			'type'						=> null,
			'templated'					=> null,
			'inherited'					=> null,
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
			'selectTemplates'			=> null,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'selectGraphItems'			=> null,
			'selectDiscoveryRule'		=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($def_options, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['graphs']);

			$dbTable = DB::getSchema('graphs');
			$sqlParts['select']['graphid'] = 'g.graphid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'g.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// permission check
		if (USER_TYPE_SUPER_ADMIN == $user_type || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS('.
										' SELECT gii.graphid'.
										' FROM graphs_items gii,items ii'.
										' WHERE gii.graphid=g.graphid'.
											' AND gii.itemid=ii.itemid'.
											' AND EXISTS('.
												' SELECT hgg.groupid'.
												' FROM hosts_groups hgg,rights rr,users_groups ugg'.
												' WHERE ii.hostid=hgg.hostid'.
													' AND rr.id=hgg.groupid'.
													' AND rr.groupid=ugg.usrgrpid'.
													' AND ugg.userid='.$userid.
													' AND rr.permission<'.$permission.'))';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['groupid'] = 'hg.groupid';
			}
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';

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
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['hostid'] = 'i.hostid';
			}
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = DBcondition('i.hostid', $options['hostids']);
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['where'][] = DBcondition('g.graphid', $options['graphids']);
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['itemid'] = 'gi.itemid';
			}
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where'][] = DBcondition('gi.itemid', $options['itemids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['gi'] = 'gi.itemid';
			}
		}

		// discoveryids
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['itemid'] = 'id.parent_itemid';
			}
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
			$sqlParts['where']['giid'] = 'gi.itemid=id.itemid';
			$sqlParts['where'][] = DBcondition('id.parent_itemid', $options['discoveryids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
			}
		}

		// type
		if (!is_null($options['type'])) {
			$sqlParts['where'][] = 'g.type='.$options['type'];
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
			$sqlParts['where']['ggi'] = 'g.graphid=gi.graphid';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

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
				$sqlParts['where'][] = 'g.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'g.templateid IS NULL';
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['graphs'] = 'g.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT g.graphid) AS rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('graphs g', $options, $sqlParts);
		}

		// filter
		if (is_null($options['filter'])) {
			$options['filter'] = array();
		}

		if (is_array($options['filter'])) {
			if (!array_key_exists('flags', $options['filter'])) {
				$options['filter']['flags'] = array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED);
			}

			zbx_db_filter('graphs g', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['graphs_items'] = 'graphs_items gi';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
				$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['host'] = DBcondition('h.host', $options['filter']['host']);
			}

			if (isset($options['filter']['hostid'])) {
				zbx_value2array($options['filter']['hostid']);

				$sqlParts['from']['graphs_items'] = 'graphs_items gi';
				$sqlParts['from']['items'] = 'items i';
				$sqlParts['where']['gig'] = 'gi.graphid=g.graphid';
				$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
				$sqlParts['where']['hostid'] = DBcondition('i.hostid', $options['filter']['hostid']);
			}
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'g');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$graphids = array();

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
				' WHERE '.DBin_node('g.graphid', $nodeids).
					$sqlWhere.
					$sqlGroup.
					$sqlOrder;

		$db_res = DBselect($sql, $sqlLimit);
		while ($graph = DBfetch($db_res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $graph;
				}
				else {
					$result = $graph['rowscount'];
				}
			}
			else {
				$graphids[$graph['graphid']] = $graph['graphid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$graph['graphid']] = array('graphid' => $graph['graphid']);
				}
				else {
					if (!isset($result[$graph['graphid']])) {
						$result[$graph['graphid']] = array();
					}

					if (!is_null($options['selectHosts']) && !isset($result[$graph['graphid']]['hosts'])) {
						$result[$graph['graphid']]['hosts'] = array();
					}
					if (!is_null($options['selectGraphItems']) && !isset($result[$graph['graphid']]['gitems'])) {
						$result[$graph['graphid']]['gitems'] = array();
					}
					if (!is_null($options['selectTemplates']) && !isset($result[$graph['graphid']]['templates'])) {
						$result[$graph['graphid']]['templates'] = array();
					}
					if (!is_null($options['selectItems']) && !isset($result[$graph['graphid']]['items'])) {
						$result[$graph['graphid']]['items'] = array();
					}
					if (!is_null($options['selectDiscoveryRule']) && !isset($result[$graph['graphid']]['discoveryRule'])) {
						$result[$graph['graphid']]['discoveryRule'] = array();
					}

					// hostids
					if (isset($graph['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$graph['graphid']]['hosts'])) {
							$result[$graph['graphid']]['hosts'] = array();
						}
						$result[$graph['graphid']]['hosts'][] = array('hostid' => $graph['hostid']);
						unset($graph['hostid']);
					}

					// itemids
					if (isset($graph['itemid']) && is_null($options['selectItems'])) {
						if (!isset($result[$graph['graphid']]['items'])) {
							$result[$graph['graphid']]['items'] = array();
						}
						$result[$graph['graphid']]['items'][] = array('itemid' => $graph['itemid']);
						unset($graph['itemid']);
					}
					$result[$graph['graphid']] += $graph;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding GraphItems
		if (!is_null($options['selectGraphItems']) && str_in_array($options['selectGraphItems'], $subselects_allowed_outputs)) {
			$objParams = array(
				'nodeids' => $nodeids,
				'output' => $options['selectGraphItems'],
				'graphids' => $graphids,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$gitems = API::GraphItem()->get($objParams);

			foreach ($gitems as $gitemid => $gitem) {
				$ggraphs = $gitem['graphs'];
				unset($gitem['graphs']);
				foreach ($ggraphs as $graph) {
					$result[$graph['graphid']]['gitems'][] = $gitem;
				}
			}
		}

		// adding HostGroups
		if (!is_null($options['selectGroups'])) {
			if (is_array($options['selectGroups']) || str_in_array($options['selectGroups'], $subselects_allowed_outputs)) {
				$objParams = array(
					'nodeids' => $nodeids,
					'output' => $options['selectGroups'],
					'graphids' => $graphids,
					'nopermissions' => true,
					'preservekeys' => true
				);
				$groups = API::HostGroup()->get($objParams);

				foreach ($groups as $groupis => $group) {
					$groupGraphs = $group['graphs'];
					unset($group['graphs']);
					foreach ($groupGraphs as $graph) {
						$result[$graph['graphid']]['groups'][] = $group;
					}
				}
			}
		}

		// adding Hosts
		if (!is_null($options['selectHosts'])) {
			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)) {
				$objParams = array(
					'nodeids' => $nodeids,
					'output' => $options['selectHosts'],
					'graphids' => $graphids,
					'templated_hosts' => true,
					'nopermissions' => true,
					'preservekeys' => true
				);
				$hosts = API::Host()->get($objParams);
				foreach ($hosts as $hostid => $host) {
					$hostGraphs = $host['graphs'];
					unset($host['graphs']);
					foreach ($hostGraphs as $graph) {
						$result[$graph['graphid']]['hosts'][] = $host;
					}
				}
			}
		}

		// adding Templates
		if (!is_null($options['selectTemplates']) && str_in_array($options['selectTemplates'], $subselects_allowed_outputs)) {
			$objParams = array(
				'nodeids' => $nodeids,
				'output' => $options['selectTemplates'],
				'graphids' => $graphids,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$templates = API::Template()->get($objParams);
			foreach ($templates as $templateid => $template) {
				$templateGraphs = $template['graphs'];
				unset($template['graphs']);
				foreach ($templateGraphs as $graph) {
					$result[$graph['graphid']]['templates'][] = $template;
				}
			}
		}

		// adding Items
		if (!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselects_allowed_outputs)) {
			$objParams = array(
				'nodeids' => $nodeids,
				'output' => $options['selectItems'],
				'graphids' => $graphids,
				'webitems' => 1,
				'nopermissions' => true,
				'preservekeys' => true
			);
			$items = API::Item()->get($objParams);
			foreach ($items as $itemid => $item) {
				$itemGraphs = $item['graphs'];
				unset($item['graphs']);
				foreach ($itemGraphs as $graph) {
					$result[$graph['graphid']]['items'][] = $item;
				}
			}
		}

		// adding discoveryRule
		if (!is_null($options['selectDiscoveryRule'])) {
			$ruleids = $rule_map = array();

			$dbRules = DBselect(
				'SELECT id.parent_itemid,gd.graphid'.
				' FROM graph_discovery gd,item_discovery id,graphs_items gi'.
				' WHERE '.DBcondition('gd.graphid', $graphids).
					' AND gd.parent_graphid=gi.graphid'.
					' AND gi.itemid=id.itemid'
			);
			while ($rule = DBfetch($dbRules)) {
				$ruleids[$rule['parent_itemid']] = $rule['parent_itemid'];
				$rule_map[$rule['graphid']] = $rule['parent_itemid'];
			}

			$objParams = array(
				'nodeids' => $nodeids,
				'itemids' => $ruleids,
				'nopermissions' => true,
				'preservekeys' => true
			);

			if (is_array($options['selectDiscoveryRule']) || str_in_array($options['selectDiscoveryRule'], $subselects_allowed_outputs)) {
				$objParams['output'] = $options['selectDiscoveryRule'];
				$discoveryRules = API::Item()->get($objParams);

				foreach ($result as $graphid => $graph) {
					if (isset($rule_map[$graphid]) && isset($discoveryRules[$rule_map[$graphid]])) {
						$result[$graphid]['discoveryRule'] = $discoveryRules[$rule_map[$graphid]];
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
 * Get graphid by graph name
 *
 * params: hostids, name
 *
 * @param array $graphData
 * @return string|boolean
 */
	public function getObjects($graphData) {
		$options = array(
			'filter' => $graphData,
			'output'=>API_OUTPUT_EXTEND
		);

		if (isset($graphData['node']))
			$options['nodeids'] = getNodeIdByNodeName($graphData['node']);
		elseif (isset($graphData['nodeids']))
			$options['nodeids'] = $graphData['nodeids'];

		$result = $this->get($options);

	return $result;
	}

	/**
	 * @param array $object
	 * @return bool
	 */
	public function exists($object) {
		$options = array(
			'filter' => array('flags' => null),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if (isset($object['name'])) $options['filter']['name'] = $object['name'];
		if (isset($object['host'])) $options['filter']['host'] = $object['host'];
		if (isset($object['hostids'])) $options['hostids'] = zbx_toArray($object['hostids']);

		if (isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		elseif (isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = $this->get($options);

	return !empty($objs);
	}

/**
 * Create new graphs
 *
 * @param array $graphs
 * @return boolean
 */
	public function create($graphs) {
		$graphs = zbx_toArray($graphs);
		$graphids = array();

			$this->checkInput($graphs, false);

			foreach ($graphs as $gnum => $graph) {

				$options = array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'templated_hosts' => 1,
				);
				$graph_hosts = API::Host()->get($options);

// check - items from one template
				$templated_graph = false;
				foreach ($graph_hosts as $host) {
					if (HOST_STATUS_TEMPLATE == $host['status']) {
						$templated_graph = $host['hostid'];
						break;
					}
				}
				if ($templated_graph && (count($graph_hosts) > 1)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$graph['name'].' ] '.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
				}

// check ymin, ymax items
				$this->checkAxisItems($graph, $templated_graph);

				$graphid = $this->createReal($graph);

				if ($templated_graph) {
					$graph['graphid'] = $graphid;
					$this->inherit($graph);
				}

				$graphids[] = $graphid;
			}

			return array('graphids' => $graphids);
	}

/**
 * Update existing graphs
 *
 * @param array $graphs
 * @return bool
 */
	public function update($graphs) {
		$graphs = zbx_toArray($graphs);
		$graphids = zbx_objectValues($graphs, 'graphid');

// GRAPHS PERMISSIONS {{{
			$options = array(
				'graphids' => $graphids,
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'selectGraphItems'=> API_OUTPUT_EXTEND
			);
			$upd_graphs = $this->get($options);

			foreach ($graphs as $gnum => $graph) {
				if (!isset($upd_graphs[$graph['graphid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}

				if (!isset($graph['gitems']))
					$graphs[$gnum]['gitems'] = $upd_graphs[$graph['graphid']]['gitems'];
			}

// }}} GRAPHS PERMISSIONS

			$this->checkInput($graphs, true);

			foreach ($graphs as $gnum => $graph) {

				unset($graph['templateid']);

				$options = array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'templated_hosts' => 1,
				);
				$graph_hosts = API::Host()->get($options);

// EXCEPTION: MESS TEMPLATED ITEMS {{{
				$templated_graph = false;
				foreach ($graph_hosts as $host) {
					if (HOST_STATUS_TEMPLATE == $host['status']) {
						$templated_graph = $host['hostid'];
						break;
					}
				}
				if ($templated_graph && (count($graph_hosts) > 1)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$graph['name'].' ] '.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
				}
// }}} EXCEPTION: MESS TEMPLATED ITEMS

// check ymin, ymax items
				$this->checkAxisItems($graph, $templated_graph);

				$this->updateReal($graph);
// inheritance
				if ($templated_graph) $this->inherit($graph);
			}

			return array('graphids' => $graphids);
	}

	protected function createReal($graph) {
		$graphids = DB::insert('graphs', array($graph));
		$graphid = reset($graphids);

		foreach ($graph['gitems'] as $gitem) {
			$gitem['graphid'] = $graphid;

			DB::insert('graphs_items', array($gitem));
		}

		return $graphid;
	}

	protected function updateReal($graph) {
		$data = array(array(
			'values' => $graph,
			'where'=> array('graphid'=>$graph['graphid'])
		));
		DB::update('graphs', $data);

		if (isset($graph['gitems'])) {
			DB::delete('graphs_items', array('graphid'=>$graph['graphid']));

			foreach ($graph['gitems'] as $inum => $gitem) {
				$gitem['graphid'] = $graph['graphid'];

				DB::insert('graphs_items', array($gitem));
			}
		}

		return $graph['graphid'];
	}

	protected function inherit($graph, $hostids=null) {
		$options = array(
			'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
		);
		$graph_templates = API::Template()->get($options);

		if (empty($graph_templates)) return true;


		$graphTemplate = reset($graph_templates);
		$options = array(
			'templateids' => $graphTemplate['templateid'],
			'output' => array('hostid', 'host'),
			'preservekeys' => 1,
			'hostids' => $hostids,
			'nopermissions' => 1,
			'templated_hosts' => 1,
		);
		$chd_hosts = API::Host()->get($options);

		$options = array(
			'graphids' => $graph['graphid'],
			'nopermissions' => 1,
			'selectItems' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
		$graph = $this->get($options);
		$graph = reset($graph);

		foreach ($chd_hosts as $chd_host) {
			$tmp_graph = $graph;
			$tmp_graph['templateid'] = $graph['graphid'];

			if (!$tmp_graph['gitems'] = get_same_graphitems_for_host($tmp_graph['gitems'], $chd_host['hostid']))
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: cannot inherit. No required items on [ %2$s ]', $tmp_graph['name'], $chd_host['host']));

			if ($tmp_graph['ymax_itemid'] > 0) {
				$ymax_itemid = get_same_graphitems_for_host(array(array('itemid' => $tmp_graph['ymax_itemid'])), $chd_host['hostid']);
				if (!$ymax_itemid) self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: cannot inherit. No required items on [ %2$s ] (Ymax value item)', $tmp_graph['name'], $chd_host['host']));
				$ymax_itemid = reset($ymax_itemid);
				$tmp_graph['ymax_itemid'] = $ymax_itemid['itemid'];
			}
			if ($tmp_graph['ymin_itemid'] > 0) {
				$ymin_itemid = get_same_graphitems_for_host(array(array('itemid' => $tmp_graph['ymin_itemid'])), $chd_host['hostid']);
				if (!$ymin_itemid) self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: cannot inherit. No required items on [ %2$s ] (Ymin value item)', $tmp_graph['name'], $chd_host['host']));
				$ymin_itemid = reset($ymin_itemid);
				$tmp_graph['ymin_itemid'] = $ymin_itemid['itemid'];
			}

// check if templated graph exists
			$chd_graphs = $this->get(array(
				'filter' => array('templateid' => $tmp_graph['graphid'], 'flags' => array(ZBX_FLAG_DISCOVERY_CHILD, ZBX_FLAG_DISCOVERY_NORMAL)),
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
				'hostids' => $chd_host['hostid']
			));

			if ($chd_graph = reset($chd_graphs)) {
				if ((zbx_strtolower($tmp_graph['name']) != zbx_strtolower($chd_graph['name']))
						&& $this->exists(array('name' => $tmp_graph['name'], 'hostids' => $chd_host['hostid'])))
				{
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: already exists on [ %2$s ]', $tmp_graph['name'], $chd_host['host']));
				}
				elseif ($chd_graph['flags'] != $tmp_graph['flags']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Graph with same name but other type exist'));
				}

				$tmp_graph['graphid'] = $chd_graph['graphid'];
				$this->updateReal($tmp_graph);
			}
// check if graph with same name and items exists
			else{
				$options = array(
					'filter' => array('name' => $tmp_graph['name'], 'flags' => null),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => 1,
					'nopermissions' => 1,
					'hostids' => $chd_host['hostid']
				);
				$chd_graph = $this->get($options);
				if ($chd_graph = reset($chd_graph)) {
					if ($chd_graph['templateid'] != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: already exists on [ %2$s ] (inherited from another template)', $tmp_graph['name'], $chd_host['host']));
					}
					elseif ($chd_graph['flags'] != $tmp_graph['flags']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Graph with same name but other type exist'));
					}

					$options = array(
						'graphids' => $chd_graph['graphid'],
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => 1,
						'expandData' => 1,
						'nopermissions' => 1
					);
					$chd_graph_items = API::GraphItem()->get($options);

					if (count($chd_graph_items) == count($tmp_graph['gitems'])) {
						foreach ($tmp_graph['gitems'] as $gitem) {
							foreach ($chd_graph_items as $chd_item) {
								if (($gitem['key_'] == $chd_item['key_']) && (bccomp($chd_host['hostid'], $chd_item['hostid']) == 0))
									continue 2;
							}

							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: already exists on [ %2$s ] (items are not identical)', $tmp_graph['name'], $chd_host['host']));
						}

						$tmp_graph['graphid'] = $chd_graph['graphid'];
						$this->updateReal($tmp_graph);
					}
					else{
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph [ %1$s ]: already exists on [ %2$s ] (items are not identical)', $tmp_graph['name'], $chd_host['host']));
					}
				}
				else{
					$graphid = $this->createReal($tmp_graph);
					$tmp_graph['graphid'] = $graphid;
				}
			}
			$this->inherit($tmp_graph);
		}
	}

/**
 * Inherit template graphs from template to host
 *
 * @param array $data
 * @return bool
 */
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
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
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
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$sql = 'SELECT hostid, templateid'.
				' FROM hosts_templates'.
				' WHERE '.DBcondition('hostid', $data['hostids']).
				' AND '.DBcondition('templateid', $data['templateids']);
			$db_links = DBSelect($sql);
			$linkage = array();
			while ($link = DBfetch($db_links)) {
				if (!isset($linkage[$link['templateid']])) $linkage[$link['templateid']] = array();
				$linkage[$link['templateid']][$link['hostid']] = 1;
			}

			$options = array(
				'hostids' => $data['templateids'],
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
				'selectGraphItems' => API_OUTPUT_EXTEND,
			);
			$graphs = $this->get($options);

			foreach ($graphs as $graph) {
				foreach ($data['hostids'] as $hostid) {
					if (isset($linkage[$graph['hosts'][0]['hostid']][$hostid])) {
						$this->inherit($graph, $hostid);
					}
				}
			}

			return true;
	}

	/**
	 * Delete graphs
	 *
	 * @param array $graphids
	 * @param bool $nopermissions
	 * @return bool
	 */
	public function delete($graphids, $nopermissions = false) {
		if (empty($graphids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$graphids = zbx_toArray($graphids);

		// TODO: remove $nopermissions hack
		$options = array(
			'graphids' => $graphids,
			'editable' => true,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'selectHosts' => array('name')
		);
		$del_graphs = $this->get($options);

		if (!$nopermissions) {
			foreach ($graphids as $graphid) {
				if (!isset($del_graphs[$graphid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
				if ($del_graphs[$graphid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot delete templated graphs'));
				}
			}
		}

		$parent_graphids = $graphids;
		do {
			$db_graphs = DBselect('SELECT graphid FROM graphs WHERE '.DBcondition('templateid', $parent_graphids));
			$parent_graphids = array();
			while ($db_graph = DBfetch($db_graphs)) {
				$parent_graphids[] = $db_graph['graphid'];
				$itemids[$db_graph['graphid']] = $db_graph['graphid'];
			}
		} while (!empty($parent_graphids));

		DB::delete('screens_items', array(
			'resourceid' => $graphids,
			'resourcetype' => SCREEN_RESOURCE_GRAPH
		));

		DB::delete('profiles', array(
			'idx' => 'web.favorite.graphids',
			'source' => 'graphid',
			'value_id' => $graphids
		));

		DB::delete('graphs', array(
			'graphid' => $graphids
		));

		// TODO: REMOVE info
		foreach ($del_graphs as $graph) {
			$host = reset($graph['hosts']);
			info(_s('Deleted: Graph "%1$s" on "%2$s".', $graph['name'], $host['name']));
		}

		return array('graphids' => $graphids);
	}

	private function checkInput($graphs, $update=false) {
		$itemids = array();

		foreach ($graphs as $gnum => $graph) {
// EXCEPTION: GRAPH FIELDS {{{
			$fields = array('name' => null);
			if (!$update && !check_db_fields($fields, $graph)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for graph'));
			}
// }}} EXCEPTION: GRAPH FIELDS

// EXCEPTION: NO ITEMS {{{
			if (!isset($graph['gitems']) || !is_array($graph['gitems']) || empty($graph['gitems'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, S_MISSING_ITEMS_FOR_GRAPH.' [ '.$graph['name'].' ]');
			}
// }}} EXCEPTION: NO ITEMS

// EXCEPTION: ITEMS FIELDS {{{
			$fields = array('itemid' => null);
			foreach ($graph['gitems'] as $ginum => $gitem) {
				if (!check_db_fields($fields, $gitem)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for items'));
				}
			}
// }}} EXCEPTION: ITEMS FIELDS

// EXCPETION: more than one sum type item for pie graph {{{
			if (($graph['graphtype'] == GRAPH_TYPE_PIE) || ($graph['graphtype'] == GRAPH_TYPE_EXPLODED)) {
				$sum_items = 0;
				foreach ($graph['gitems'] as $gitem) {
					if ($gitem['type'] == GRAPH_ITEM_SUM) $sum_items++;
				}
				if ($sum_items > 1) self::exception(ZBX_API_ERROR_PARAMETERS, S_ANOTHER_ITEM_SUM.' [ '.$graph['name'].' ]');
			}
// }}} EXCEPTION

			$itemids += zbx_objectValues($graph['gitems'], 'itemid');
		}


		if (!empty($itemids)) {
// EXCEPTION: ITEMS PERMISSIONS {{{
			$options = array(
				'nodeids' => get_current_nodeid(true),
				'itemids' => array_unique($itemids),
				'webitems' => 1,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
			);
			$allowed_items = API::Item()->get($options);

			foreach ($itemids as $inum => $itemid) {
				if (!isset($allowed_items[$itemid])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}
// }}} EXCEPTION: ITEMS PERMISSIONS
		}

		foreach ($graphs as $graph) {
			if (!isset($graph['name'])) {
				continue;
			}
			$hosts = API::Host()->get(array(
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions'=> true,
				'preservekeys' => true
			));
			$options = array(
				'hostids' => array_keys($hosts),
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array('name' => $graph['name'], 'flags' => null),
				'nopermissions' => true,
				'preservekeys' => true,
				'limit' => 1
			);
			$graphsExists = $this->get($options);
			foreach ($graphsExists as $graphExists) {
				if (!$update || (bccomp($graphExists['graphid'], $graph['graphid']) != 0)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Graph with name "%1$s" already exists', $graph['name']));
				}
			}
		}

		return true;
	}

	protected function checkAxisItems($graph, $tpl=false) {

		$axis_items = array();
		if (isset($graph['ymin_type']) && ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE)) {
			$axis_items[$graph['ymin_itemid']] = $graph['ymin_itemid'];
		}
		if (isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			$axis_items[$graph['ymax_itemid']] = $graph['ymax_itemid'];
		}

		if (!empty($axis_items)) {
			$cnt = count($axis_items);

			$options = array(
				'itemids' => $axis_items,
				'output' => API_OUTPUT_SHORTEN,
				'countOutput' => 1,
			);
			if ($tpl)
				$options['hostids'] = $tpl;
			else
				$options['templated'] = false;

			$cnt_exist = API::Item()->get($options);

			if ($cnt != $cnt_exist)
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect item for axis value'));
		}

		return true;
	}
}
?>
