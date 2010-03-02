<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
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
class CGraph extends CZBXAPI{
/**
* Get graph data
*
* <code>
* $options = array(
*	array 'graphids'				=> array(graphid1, graphid2, ...),
*	array 'itemids'					=> array(itemid1, itemid2, ...),
*	array 'hostids'					=> array(hostid1, hostid2, ...),
*	int 'type'						=> 'graph type, chart/pie'
*	boolean 'templated_graphs'		=> 'only templated graphs',
*	int 'count'						=> 'count',
*	string 'pattern'				=> 'search hosts by pattern in graph names',
*	integer 'limit'					=> 'limit selection',
*	string 'order'					=> 'deprecated parameter (for now)'
* );
* </code>
*
* @static
* @param array $options
* @return array|boolean host data as array or false if error
*/
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		$result = array();

		$sort_columns = array('graphid','name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('graphs' => 'g.graphid'),
			'from' => array('graphs g'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
			);

		$def_options = array(
			'nodeids' 				=> null,
			'groupids' 				=> null,
			'hostids' 				=> null,
			'graphids' 				=> null,
			'itemids' 				=> null,
			'type' 					=> null,
			'templated'				=> null,
			'inherited'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// filter
			'filter'				=> '',
			'pattern'				=> '',
// output
			'output'				=> API_OUTPUT_REFER,
			'select_hosts'			=> null,
			'select_templates'		=> null,
			'select_items'			=> null,
			'select_graph_items'	=> null,
			'extendoutput'			=> null,
			'count'					=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_hosts'])){
				$options['select_hosts'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_templates'])){
				$options['select_templates'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_items'])){
				$options['select_items'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_graph_items'])){
				$options['select_graph_items'] = API_OUTPUT_EXTEND;
			}
		}


// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
											' SELECT gii.graphid '.
											' FROM graphs_items gii, items ii '.
											' WHERE gii.graphid=g.graphid '.
												' AND gii.itemid=ii.itemid '.
												' AND EXISTS( '.
													' SELECT hgg.groupid '.
													' FROM hosts_groups hgg, rights rr, users_groups ugg '.
													' WHERE ii.hostid=hgg.hostid '.
														' AND rr.id=hgg.groupid '.
														' AND rr.groupid=ugg.usrgrpid '.
														' AND ugg.userid='.$userid.
														' AND rr.permission<'.$permission.'))';
		}


// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['hg'] = 'hosts_groups hg';

			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);

			$sql_parts['where'][] = DBcondition('g.graphid', $options['graphids']);
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'gi.itemid';
			}
			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where'][] = DBcondition('gi.itemid', $options['itemids']);
		}

// type
		if(!is_null($options['type'] )){
			$sql_parts['where'][] = 'g.type='.$options['type'];
		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['from']['h'] = 'hosts h';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['ggi'] = 'g.graphid=gi.graphid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['templated']){
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else{
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited']){
				$sql_parts['where'][] = 'g.templateid<>0';
			}
			else{
				$sql_parts['where'][] = 'g.templateid=0';
			}
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['graphs'] = 'g.*';
		}

// count
		if(!is_null($options['count'])){
			$sql_parts['select'] = array('count(g.graphid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where']['name'] = ' UPPER(g.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['name']))
				$sql_parts['where']['name'] = zbx_dbstr($options['filter']['name']);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'g.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('g.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('g.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'g.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//------------

		$graphids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('g.graphid', $nodeids).
					$sql_where.
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);
		while($graph = DBfetch($db_res)){
			if($options['count'])
				$result = $graph;
			else{
				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$graph['graphid']] = array('graphid' => $graph['graphid']);
				}
				else{
					$graphids[$graph['graphid']] = $graph['graphid'];

					if(!isset($result[$graph['graphid']]))
						$result[$graph['graphid']]= array();

					if(!is_null($options['select_hosts']) && !isset($result[$graph['graphid']]['hosts'])){
						$result[$graph['graphid']]['hosts'] = array();
					}
					if(!is_null($options['select_graph_items']) && !isset($result[$graph['graphid']]['gitems'])){
						$result[$graph['graphid']]['gitems'] = array();
					}
					if(!is_null($options['select_templates']) && !isset($result[$graph['graphid']]['templates'])){
						$result[$graph['graphid']]['templates'] = array();
					}
					if(!is_null($options['select_items']) && !isset($result[$graph['graphid']]['items'])){
						$result[$graph['graphid']]['items'] = array();
					}

// hostids
					if(isset($graph['hostid']) && is_null($options['select_hosts'])){
						if(!isset($result[$graph['graphid']]['hosts']))
							$result[$graph['graphid']]['hosts'] = array();

						$result[$graph['graphid']]['hosts'][] = array('hostid' => $graph['hostid']);
						unset($graph['hostid']);
					}
// itemids
					if(isset($graph['itemid']) && is_null($options['select_items'])){
						if(!isset($result[$graph['graphid']]['items']))
							$result[$graph['graphid']]['items'] = array();

						$result[$graph['graphid']]['items'][] = array('itemid' => $graph['itemid']);
						unset($graph['itemid']);
					}

					$result[$graph['graphid']] += $graph;
				}
			}
		}

COpt::memoryPick();
		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}


// Adding GraphItems
		if(!is_null($options['select_graph_items']) && str_in_array($options['select_graph_items'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_graph_items'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$gitems = CGraphItem::get($obj_params);
			foreach($gitems as $gitemid => $gitem){
				$ggraphs = $gitem['graphs'];
				unset($gitem['graphs']);
				foreach($ggraphs as $num => $graph){
					$result[$graph['graphid']]['gitems'][] = $gitem;
				}
			}
		}

// Adding Hosts
		if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_hosts'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$hosts = CHost::get($obj_params);
			foreach($hosts as $hostid => $host){
				$hgraphs = $host['graphs'];
				unset($host['graphs']);
				foreach($hgraphs as $num => $graph){
					$result[$graph['graphid']]['hosts'][] = $host;
				}
			}
		}

// Adding Templates
		if(!is_null($options['select_templates']) && str_in_array($options['select_templates'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_templates'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				$tgraphs = $template['graphs'];
				unset($template['graphs']);
				foreach($tgraphs as $num => $graph){
					$result[$graph['graphid']]['templates'][] = $template;
				}
			}
		}

// Adding Items
		if(!is_null($options['select_items']) && str_in_array($options['select_items'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_items'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				$igraphs = $item['graphs'];
				unset($item['graphs']);
				foreach($igraphs as $num => $graph){
					$result[$graph['graphid']]['items'][] = $item;
				}
			}
		}

COpt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}


/**
 * Get graphid by graph name
 *
 * <code>
 * $graph_data = array(
 * 	*string 'graph' => 'graph name'
 * );
 * </code>
 *
 * @static
 * @param array $graph_data
 * @return string|boolean graphid
 */
	public static function getObjects($graphData){
		$options = array(
			'filter' => $graphData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($graphData['node']))
			$options['nodeids'] = getNodeIdByNodeName($graphData['node']);
		else if(isset($graphData['nodeids']))
			$options['nodeids'] = $graphData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function checkObjects($graphsData){

		$graphsData = zbx_toArray($graphsData);
		$result = array();
		foreach($graphsData as $inum => $graphData){
			$options = array(
				'filter' => $graphData,
				'hostids' => isset($graphData['hostid'])?$graphData['hostid']:null,
				'output' => API_OUTPUT_SHORTEN,
				'nopermissions' => 1
			);

			if(isset($hostData['node']))
				$options['nodeids'] = getNodeIdByNodeName($hostData['node']);
			else if(isset($hostData['nodeids']))
				$options['nodeids'] = $hostData['nodeids'];

			$graphs = self::get($options);
			$result+= $graphs;
		}

	return $result;
	}
/**
 * Add graph
 *
 * <code>
 * $graphs = array(
 * 	*string 'name'			=> null,
 * 	int 'width'			=> 900,
 * 	int 'height'			=> 200,
 * 	int 'ymin_type'			=> 0,
 * 	int 'ymax_type'			=> 0,
 * 	int 'yaxismin'			=> 0,
 * 	int 'yaxismax'			=> 100,
 * 	int 'ymin_itemid'		=> 0,
 * 	int 'ymax_itemid'		=> 0,
 * 	int 'show_work_period'		=> 1,
 * 	int 'show_triggers'		=> 1,
 * 	int 'graphtype'			=> 0,
 * 	int 'show_legend'		=> 0,
 * 	int 'show_3d'			=> 0,
 * 	int 'percent_left'		=> 0,
 * 	int 'percent_right'		=> 0
 * );
 * </code>
 *
 * @static
 * @param array $graphs multidimensional array with graphs data
 * @return boolean
 */
	public static function create($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		$error = 'Unknown Zabbix internal error';

		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($graphs as $gnum => $graph){

			if(!isset($graph['gitems']) || !is_array($graph['gitems']) || empty($graph['gitems'])){
				$result = false;
				$error = 'Missing items for graph "'.$graph['name'].'"';
				break;
			}

			$graph_db_fields = array(
				'name'			=> null,
				'width'			=> 900,
				'height'		=> 200,
				'ymin_type'		=> 0,
				'ymax_type'		=> 0,
				'yaxismin'		=> 0,
				'yaxismax'		=> 100,
				'ymin_itemid'		=> 0,
				'ymax_itemid'		=> 0,
				'showworkperiod'	=> 1,
				'showtriggers'		=> 1,
				'graphtype'		=> 0,
				'legend'		=> 0,
				'graph3d'		=> 0,
				'percent_left'		=> 0,
				'percent_right'		=> 0,
				'templateid'		=> 0,
			);



			if(!check_db_fields($graph_db_fields, $graph)){
				$result = false;
				$error = 'Wrong fields for graph [ '.$graph['name'].' ]';
				break;
			}

			foreach($graph['gitems'] as $ginum => $gitem){

				$gitem_db_fields = array(
					'itemid' => null,
					'color' => 009600,
					'drawtype' => 0,
					'sortorder' => 0,
					'yaxisside' => 1,
					'calc_fnc' => 2,
					'type' => 0,
					'periods_cnt' => 5
				);

				if(!check_db_fields($gitem_db_fields, $gitem)){
					$result = false;
					$error = 'Wrong fields for items';
					break 2;
				}
				$graph['gitems'][$ginum] = $gitem;
			}

			$result = add_graph_with_items($graph['name'],$graph['width'],$graph['height'],
										$graph['ymin_type'],$graph['ymax_type'],$graph['yaxismin'],
										$graph['yaxismax'],$graph['ymin_itemid'],$graph['ymax_itemid'],
										$graph['showworkperiod'],$graph['showtriggers'],$graph['graphtype'],
										$graph['legend'],$graph['graph3d'],$graph['percent_left'],
										$graph['percent_right'],$graph['gitems'],$graph['templateid']);

			if(!$result) break;
			$graphids[] = $result;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_graphs = self::get(array('graphids'=>$graphids, 'extendoutput'=>1, 'nopermissions'=>1, 'select_graph_items'=>1));
			return $new_graphs;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);//'Internal Zabbix error');
			return false;
		}
	}

/**
 * Update graphs
 *
 * @static
 * @param array $graphs multidimensional array with graphs data
 * @return boolean
 */
	public static function update($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		$upd_graphs = self::get(array('graphids'=>zbx_objectValues($graphs, 'graphid'),
									'editable'=>1,
									'extendoutput'=>1,
									'preservekeys'=>1));
		foreach($graphs as $gnum => $graph){
			if(!isset($upd_graphs[$graph['graphid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}
			$graphids[] = $graph['graphid'];
		}

		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($graphs as $gnum => $graph){
			$graph_db_fields = $upd_graphs[$graph['graphid']];

			if(!check_db_fields($graph_db_fields, $graph)){
				$result = false;
				break;
			}

			$result = update_graph($graph['graphid'],$graph['name'],$graph['width'],$graph['height'],
									$graph['ymin_type'],$graph['ymax_type'],$graph['yaxismin'],
									$graph['yaxismax'],$graph['ymin_itemid'],$graph['ymax_itemid'],$graph['show_work_period'],
									$graph['show_triggers'],$graph['graphtype'],$graph['show_legend'],$graph['show_3d'],
									$graph['percent_left'],$graph['percent_right'],$graph['templateid']);

			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_graphs = self::get(array('graphids'=>$graphids, 'extendoutput'=>1, 'nopermissions'=>1));
			return $upd_graphs;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal Zabbix error');
			return false;
		}
	}

/**
 * Delete graphs
 *
 * @static
 * @param _array $graphs
 * @param array $graphs['graphids']
 * @return boolean
 */
	public static function delete($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		$del_graphs = self::get(array(
			'graphids' => zbx_objectValues($graphs, 'graphid'),
			'editable' => 1,
			'extendoutput' => 1,
			'preservekeys' => 1));
		foreach($graphs as $gnum => $graph){
			if(!isset($del_graphs[$graph['graphid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$graphids[] = $graph['graphid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_GRAPH, 'Graph ['.$graph['name'].']');
		}

		if(!empty($graphids)){
			$result = delete_graph($graphids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Incorrect input parameter [ graphs ]');
			$result = false;
		}

		if($result){
			return zbx_cleanHashes($del_graphs);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

/**
 * Add items to graph
 *
 * <code>
 * $items = array(
 * 	*string 'graphid'		=> null,
 * 	array 'items' 			=> (
 *		'item1' => array(
 * 			*int 'itemid'			=> null,
 * 			int 'color'			=> '000000',
 * 			int 'drawtype'			=> 0,
 * 			int 'sortorder'			=> 0,
 * 			int 'yaxisside'			=> 1,
 * 			int 'calc_fnc'			=> 2,
 * 			int 'type'			=> 0,
 * 			int 'periods_cnt'		=> 5,
 *		), ... )
 * );
 * </code>
 *
 * @static
 * @param array $items multidimensional array with items data
 * @return boolean
 */
	public static function addItems($items){

		$error = 'Unknown Zabbix internal error';
		$result_ids = array();
		$result = false;
		$tpl_graph = false;

		$graphid = $items['graphid'];
		$items_tmp = $items['items'];
		$items = array();
		$itemids = array();

		foreach($items_tmp as $item){

			$graph_db_fields = array(
				'itemid'	=> null,
				'color'		=> '000000',
				'drawtype'	=> 0,
				'sortorder'	=> 0,
				'yaxisside'	=> 1,
				'calc_fnc'	=> 2,
				'type'		=> 0,
				'periods_cnt'	=> 5
			);

			if(!check_db_fields($graph_db_fields, $item)){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Wrong fields for item [ '.$item['itemid'].' ]');
				return false;
			}
			$items[$item['itemid']] = $item;
			$itemids[$item['itemid']] = $item['itemid'];
		}

// check if graph is templated graph, then items cannot be added
		$graph = self::get(array('graphids' => $graphid,  'extendoutput' => 1));
		$graph = reset($graph);

		if($graph['templateid'] != 0){
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Cannot edit templated graph : '.$graph['name']);
			return false;
		}

		// check if graph belongs to template, if so, only items from same template can be added
		$tmp_hosts = get_hosts_by_graphid($graphid);
		$host = DBfetch($tmp_hosts); // if graph belongs to template, only one host is possible

		if($host["status"] == HOST_STATUS_TEMPLATE ){
			$sql = 'SELECT DISTINCT count(i.hostid) as count
					FROM items i
					WHERE i.hostid<>'.$host['hostid'].
						' AND '.DBcondition('i.itemid', $itemids);

			$host_count = DBfetch(DBselect($sql));
			if ($host_count['count']){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'You must use items only from host : '.$host['host'].' for template graph : '.$graph['name']);
				return false;
			}
			$tpl_graph = true;
		}

		self::BeginTransaction(__METHOD__);
		$result = self::addItems_rec($graphid, $items, $tpl_graph);
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return $result;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);//'Internal Zabbix error');
			return false;
		}
	}

	protected static function addItems_rec($graphid, $items, $tpl_graph=false){

		if($tpl_graph){
			$chd_graphs = get_graphs_by_templateid($graphid);
			while($chd_graph = DBfetch($chd_graphs)){
				$result = self::addItems_rec($chd_graph['graphid'], $items, $tpl_graph);
				if(!$result) return false;
			}

			$tmp_hosts = get_hosts_by_graphid($graphid);
			$graph_host = DBfetch($tmp_hosts);
			if(!$items = get_same_graphitems_for_host($items, $graph_host['hostid'])){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Can not update graph "'.$chd_graph['name'].'" for host "'.$graph_host['host'].'"');
				return false;
			}
		}

		foreach($items as $item){
			$result = add_item_to_graph($graphid,$item['itemid'],$item['color'],$item['drawtype'],$item['sortorder'],$item['yaxisside'],
				$item['calc_fnc'],$item['type'],$item['periods_cnt']);
			if(!$result) return false;
		}

	return true;
	}

/**
 * Delete graph items
 *
 * @static
 * @param array $items
 * @return boolean
 */
	public static function deleteItems($item_list, $force=false){
		$error = 'Unknown Zabbix internal error';
		$result = true;

		$graphid = $item_list['graphid'];
		$items = $item_list['items'];

		if(!$force){
			// check if graph is templated graph, then items cannot be deleted
			$graph = self::get(array('graphids' => $graphid,  'extendoutput' => 1));
			$graph = reset($graph);

			if($graph['templateid'] != 0){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Cannot edit templated graph : '.$graph['name']);
				return false;
			}
		}

		$chd_graphs = get_graphs_by_templateid($graphid);
		while($chd_graph = DBfetch($chd_graphs)){
			$item_list['graphid'] = $chd_graph['graphid'];
			$result = self::deleteItems($item_list, true);
			if(!$result) return false;
		}


		$sql = 'SELECT curr.itemid
				FROM graphs_items gi, items curr, items src
				WHERE gi.graphid='.$graphid.
					' AND gi.itemid=curr.itemid
					AND curr.key_=src.key_
					AND '.DBcondition('src.itemid', $items);
		$db_items = DBselect($sql);
		$gitems = array();
		while($curr_item = DBfetch($db_items)){
			$gitems[$curr_item['itemid']] = $curr_item['itemid'];
		}

		$sql = 'DELETE
				FROM graphs_items
				WHERE graphid='.$graphid.
					' AND '.DBcondition('itemid', $gitems);
		$result = DBselect($sql);

		return $result;
	}
}
?>
