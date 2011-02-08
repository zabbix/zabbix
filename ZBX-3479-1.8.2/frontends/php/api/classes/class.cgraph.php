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
* @static
* @param array $options
* @return array
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
			'group' => array(),
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
			'countOutput'			=> null,
			'groupCount'			=> null,
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

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
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

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['i'] = 'i.hostid';
			}
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

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT g.graphid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where']['name'] = ' UPPER(g.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
		}

// filter

// filter
		if(!is_null($options['filter'])){
			zbx_value2array($options['filter']);

			if(isset($options['filter']['name']))
				$sql_parts['where']['name'] = 'g.name='.zbx_dbstr($options['filter']['name']);

			if(isset($options['filter']['templateid']))
				$sql_parts['where']['templateid'] = 'g.templateid='.$options['filter']['templateid'];

			if(isset($options['filter']['host']) || isset($options['filter']['hostid'])){
				$sql_parts['from']['gi'] = 'graphs_items gi';
				$sql_parts['from']['i'] = 'items i';
				$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
				$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';

				if(isset($options['filter']['host'])){
					$sql_parts['from']['h'] = 'hosts h';
					$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
					$sql_parts['where']['host'] = 'h.host='.zbx_dbstr($options['filter']['host']);
				}

				if(isset($options['filter']['hostid']))
					$sql_parts['where']['hostid'] = 'i.hostid='.$options['filter']['hostid'];
			}
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
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT DISTINCT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('g.graphid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);
		while($graph = DBfetch($db_res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $graph;
				else
					$result = $graph['rowscount'];
			}
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
		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['countOutput'])){
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
		if(!is_null($options['select_hosts'])){
			if(is_array($options['select_hosts']) || str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
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
 * params: hostids, name
 *
 * @static
 * @param array $graphData
 * @return string|boolean
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

	public static function exists($object){
		$options = array(
			'filter' => array(),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['name'])) $options['filter']['name'] = $object['name'];
		if(isset($object['host'])) $options['filter']['host'] = $object['host'];
		if(isset($object['hostids'])) $options['hostids'] = zbx_toArray($object['hostids']);

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];
			
		$objs = self::get($options);

	return !empty($objs);
	}
	
/**
 * Create new graphs
 *
 * @static
 * @param array $graphs
 * @return boolean
 */
	public static function create($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		try{
			self::BeginTransaction(__METHOD__);
			
			self::checkInput($graphs);
			
			foreach($graphs as $gnum => $graph){

				$options = array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'templated_hosts' => 1,
				);
				$graph_hosts = CHost::get($options);
			
// check - already exists
				$filter = array(
					'name' => $graph['name'],
					'hostids' => zbx_objectValues($graph_hosts, 'hostid')
				);
				if(self::exists($filter)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph already exists [ '.$graph['name'].' ] on Host [ '.$graph_hosts[0]['host'].' ]');
				}

// check - items from one template
				$templated_graph = false;
				foreach($graph_hosts as $host){
					if(HOST_STATUS_TEMPLATE == $host['status']){
						$templated_graph = $host['hostid'];
						break;
					}
				}
				if($templated_graph && (count($graph_hosts) > 1)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$graph['name'].' ] '.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
				}
				
// check ymin, ymax items
				if($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
					$options = array(
						'itemids' => $graph['ymin_itemid'],
						'output' => API_OUTPUT_SHORTEN,
						'templated_hosts' => 1
					);
					if($templated_graph)
						$options['hostids'] = $templated_graph;
					else
						$options['monitored_hosts'] = 1;
						
					$yminhost = CHost::get($options);
					if(empty($yminhost)) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$graph['name'].' ]: Incorrect item for Ymin value');
				}
				if($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
					$options = array(
						'itemids' => $graph['ymax_itemid'],
						'output' => API_OUTPUT_SHORTEN
					);
					if($templated_graph)
						$options['hostids'] = $templated_graph;
					else
						$options['monitored_hosts'] = 1;
						
					$ymaxhost = CHost::get($options);
					if(empty($ymaxhost)) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$graph['name'].' ]: Incorrect item for Ymax value');
				}			
				
	
				$graphid = self::createReal($graph);

				if($templated_graph){				
					$graph['graphid'] = $graphid;
					self::inherit($graph);
				}
				
				$graphids[] = $graphid;
			}
			
			self::EndTransaction(true, __METHOD__);
			// self::EndTransaction(false, __METHOD__);
			
			return array('graphids' => $graphids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}
	
/**
 * Update existing graphs
 *
 * @static
 * @param array $graphs
 * @return boolean
 */
	public static function update($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		try{
			self::BeginTransaction(__METHOD__);
		
// GRAPHS PERMISSIONS {{{
			$options = array(
				'graphids' => zbx_objectValues($graphs, 'graphid'),
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$upd_graphs = self::get($options);
			foreach($graphs as $gnum => $graph){
				if(!isset($upd_graphs[$graph['graphid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}
// }}} GRAPHS PERMISSIONS

			self::checkInput($graphs);

			foreach($graphs as $gnum => $graph){
			
				unset($graph['templateid']);
				
// EXCEPTION: GRAPH EXISTS {{{
				$options = array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'templated_hosts' => 1,
				);
				$graph_hosts = CHost::get($options);

				$options = array(
					'filter' => array('name' => $graph['name']),
					'hostids' => zbx_objectValues($graph_hosts, 'hostid'),
					'nopermissions' => 1
				);
				$graph_exists = self::get($options);
				$graph_exists = reset($graph_exists);

				if($graph_exists && ($graph_exists['graphid'] != $graph['graphid'])){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$graph['name'].' ]: already exists on [ '.$graph_hosts[0]['host'].' ]');
				}
// }}} EXCEPTION: GRAPH EXISTS


// EXCEPTION: MESS TEMPLATED ITEMS {{{
				$templated_graph = false;
				foreach($graph_hosts as $host){
					if(HOST_STATUS_TEMPLATE == $host['status']){
						$templated_graph = $host['hostid'];
						break;
					}
				}
				if($templated_graph && (count($graph_hosts) > 1)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$graph['name'].' ] '.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
				}
// }}} EXCEPTION: MESS TEMPLATED ITEMS

// check ymin, ymax items
				if($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
					$options = array(
						'itemids' => $graph['ymin_itemid'],
						'output' => API_OUTPUT_SHORTEN,
						'templated_hosts' => 1
					);
					if($templated_graph)
						$options['hostids'] = $templated_graph;
					else
						$options['monitored_hosts'] = 1;
						
					$yminhost = CHost::get($options);
					if(empty($yminhost)) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$graph['name'].' ]: Incorrect item for Ymin value');
				}
				if($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
					$options = array(
						'itemids' => $graph['ymax_itemid'],
						'output' => API_OUTPUT_SHORTEN
					);
					if($templated_graph)
						$options['hostids'] = $templated_graph;
					else
						$options['monitored_hosts'] = 1;
						
					$ymaxhost = CHost::get($options);
					if(empty($ymaxhost)) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$graph['name'].' ]: Incorrect item for Ymax value');
				}

				self::updateReal($graph);

				if($templated_graph){
					self::inherit($graph);
				}
			}
			
			self::EndTransaction(true, __METHOD__);
			return array('graphids' => $graphids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}
	
	protected static function createReal($graph){
		$graphid = get_dbid('graphs', 'graphid');

		$values = array(
			'graphid' => $graphid,
			'name' => zbx_dbstr($graph['name'])
		);
		if(isset($graph['width'])) $values['width'] = $graph['width'];
		if(isset($graph['height'])) $values['height'] = $graph['height'];
		if(isset($graph['ymin_type'])) $values['ymin_type'] = $graph['ymin_type'];
		if(isset($graph['ymax_type'])) $values['ymax_type'] = $graph['ymax_type'];
		if(isset($graph['yaxismin'])) $values['yaxismin'] = $graph['yaxismin'];
		if(isset($graph['yaxismax'])) $values['yaxismax'] = $graph['yaxismax'];
		if(isset($graph['ymin_itemid'])) $values['ymin_itemid'] = $graph['ymin_itemid'];
		if(isset($graph['ymax_itemid'])) $values['ymax_itemid'] = $graph['ymax_itemid'];
		if(isset($graph['show_work_period'])) $values['show_work_period'] = $graph['show_work_period'];
		if(isset($graph['show_triggers'])) $values['show_triggers'] = $graph['show_triggers'];
		if(isset($graph['graphtype'])) $values['graphtype'] = $graph['graphtype'];
		if(isset($graph['show_legend'])) $values['show_legend'] = $graph['show_legend'];
		if(isset($graph['show_3d'])) $values['show_3d'] = $graph['show_3d'];
		if(isset($graph['percent_left'])) $values['percent_left'] = $graph['percent_left'];
		if(isset($graph['percent_right'])) $values['percent_right'] = $graph['percent_right'];
		if(isset($graph['templateid'])) $values['templateid'] = $graph['templateid'];
		
		$sql = 'INSERT INTO graphs ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
		DBexecute($sql) or self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');			
	
		foreach($graph['gitems'] as $gitem){
			$values = array(
				'gitemid' => get_dbid('graphs_items', 'gitemid'),
				'graphid' => $graphid,
			);
			if(isset($gitem['itemid'])) $values['itemid'] = $gitem['itemid'];
			if(isset($gitem['color'])) $values['color'] = zbx_dbstr($gitem['color']);
			if(isset($gitem['drawtype'])) $values['drawtype'] = $gitem['drawtype'];
			if(isset($gitem['sortorder'])) $values['sortorder'] = $gitem['sortorder'];
			if(isset($gitem['yaxisside'])) $values['yaxisside'] = $gitem['yaxisside'];
			if(isset($gitem['calc_fnc'])) $values['calc_fnc'] = $gitem['calc_fnc'];
			if(isset($gitem['type'])) $values['type'] = $gitem['type'];
			if(isset($gitem['periods_cnt'])) $values['periods_cnt'] = $gitem['periods_cnt'];
			
			$sql = 'INSERT INTO graphs_items ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
			DBexecute($sql) or self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}
		
		return $graphid;
	}
	
	protected static function updateReal($graph){
		$values = array(
			'name' => zbx_dbstr($graph['name'])
		);
		if(isset($graph['width'])) $values['width'] = $graph['width'];
		if(isset($graph['height'])) $values['height'] = $graph['height'];
		if(isset($graph['ymin_type'])) $values['ymin_type'] = $graph['ymin_type'];
		if(isset($graph['ymax_type'])) $values['ymax_type'] = $graph['ymax_type'];
		if(isset($graph['yaxismin'])) $values['yaxismin'] = $graph['yaxismin'];
		if(isset($graph['yaxismax'])) $values['yaxismax'] = $graph['yaxismax'];
		if(isset($graph['ymin_itemid'])) $values['ymin_itemid'] = $graph['ymin_itemid'];
		if(isset($graph['ymax_itemid'])) $values['ymax_itemid'] = $graph['ymax_itemid'];
		if(isset($graph['show_work_period'])) $values['show_work_period'] = $graph['show_work_period'];
		if(isset($graph['show_triggers'])) $values['show_triggers'] = $graph['show_triggers'];
		if(isset($graph['graphtype'])) $values['graphtype'] = $graph['graphtype'];
		if(isset($graph['show_legend'])) $values['show_legend'] = $graph['show_legend'];
		if(isset($graph['show_3d'])) $values['show_3d'] = $graph['show_3d'];
		if(isset($graph['percent_left'])) $values['percent_left'] = $graph['percent_left'];
		if(isset($graph['percent_right'])) $values['percent_right'] = $graph['percent_right'];
		if(isset($graph['templateid'])) $values['templateid'] = $graph['templateid'];
		
		$q = '';
		foreach($values as $field => $value){
			$q .= $field.'='.$value.', ';
		}
		$q = rtrim($q, ', ');
		$sql = 'UPDATE graphs SET '.$q.' WHERE graphid='.$graph['graphid'];
		DBexecute($sql) or self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');			
	
	
		DBexecute('DELETE FROM graphs_items WHERE graphid='.$graph['graphid'])
			or self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		
		foreach($graph['gitems'] as $gitem){
			$values = array(
				'gitemid' => get_dbid('graphs_items', 'gitemid'),
				'graphid' => $graph['graphid']
			);
			if(isset($gitem['itemid'])) $values['itemid'] = $gitem['itemid'];
			if(isset($gitem['color'])) $values['color'] = zbx_dbstr($gitem['color']);
			if(isset($gitem['drawtype'])) $values['drawtype'] = $gitem['drawtype'];
			if(isset($gitem['sortorder'])) $values['sortorder'] = $gitem['sortorder'];
			if(isset($gitem['yaxisside'])) $values['yaxisside'] = $gitem['yaxisside'];
			if(isset($gitem['calc_fnc'])) $values['calc_fnc'] = $gitem['calc_fnc'];
			if(isset($gitem['type'])) $values['type'] = $gitem['type'];
			if(isset($gitem['periods_cnt'])) $values['periods_cnt'] = $gitem['periods_cnt'];
			
			$sql = 'INSERT INTO graphs_items ('.implode(', ', array_keys($values)).') VALUES ('.implode(', ', $values).')';
			DBexecute($sql) or self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}
		
		return $graph['graphid'];
	}
	
	protected static function inherit($graph, $hostids=null){
		$options = array(
			'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
		);
		$graph_templates = CTemplate::get($options);
		if(!empty($graph_templates)){
			$graph_template = reset($graph_templates);

			$options = array(
				'templateids' => $graph_template['templateid'],
				'output' => array('hostid', 'host'),
				'preservekeys' => 1,
				'hostids' => $hostids,
				'nopermissions' => 1,
				'templated_hosts' => 1,
			);
			$chd_hosts = CHost::get($options);

			$options = array(
				'graphids' => $graph['graphid'],
				'nopermissions' => 1,
				'select_items' => API_OUTPUT_EXTEND,
				'select_graph_items' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND
			);
			$graph = self::get($options);
			$graph = reset($graph);
				
			foreach($chd_hosts as $chd_host){
				$tmp_graph = $graph;
				$tmp_graph['templateid'] = $graph['graphid'];
				
				$tmp_graph['gitems'] = get_same_graphitems_for_host($tmp_graph['gitems'], $chd_host['hostid'])
					or self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: cannot inherit. No required items on [ '.$chd_host['host'].' ]');
			
				if($tmp_graph['ymax_itemid'] > 0){
					$ymax_itemid = get_same_graphitems_for_host(array(array('itemid' => $tmp_graph['ymax_itemid'])), $chd_host['hostid']);
					if(!$ymax_itemid) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: cannot inherit. No required items on [ '.$chd_host['host'].' ] (Ymax value item)');
					$ymax_itemid = reset($ymax_itemid);
					$tmp_graph['ymax_itemid'] = $ymax_itemid['itemid'];
				}
				if($tmp_graph['ymin_itemid'] > 0){
					$ymin_itemid = get_same_graphitems_for_host(array(array('itemid' => $tmp_graph['ymin_itemid'])), $chd_host['hostid']);
					if(!$ymin_itemid) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: cannot inherit. No required items on [ '.$chd_host['host'].' ] (Ymin value item)');				
					$ymin_itemid = reset($ymin_itemid);
					$tmp_graph['ymin_itemid'] = $ymin_itemid['itemid'];
				}
				
// check if templated graph exists
				$chd_graph = self::get(array(
					'filter' => array('templateid' => $tmp_graph['graphid']),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => 1,
					'hostids' => $chd_host['hostid']
				));
				if($chd_graph = reset($chd_graph)){
					if(($tmp_graph['name'] != $chd_graph['name']) && self::exists(array('name' => $tmp_graph['name'], 'hostids' => $chd_host['hostid']))){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ]');
					}
					
					$tmp_graph['graphid'] = $chd_graph['graphid'];
					self::updateReal($tmp_graph);
				}
// check if graph with same name and items exists
				else{
					$options = array(
						'filter' => array('name' => $tmp_graph['name']),
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => 1,
						'nopermissions' => 1,
						'hostids' => $chd_host['hostid']
					);
					$chd_graph = self::get($options);
					if($chd_graph = reset($chd_graph)){
						if($chd_graph['templateid'] != 0){
							self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ] (inherited from another template)');
						}

						$options = array(
							'graphids' => $chd_graph['graphid'],
							'output' => API_OUTPUT_EXTEND,
							'preservekeys' => 1,
							'expand_data' => 1,
							'nopermissions' => 1
						);
						$chd_graph_items = CGraphItem::get($options);
						
						if(count($chd_graph_items) == count($tmp_graph['gitems'])){
							foreach($tmp_graph['gitems'] as $gitem){
								foreach($chd_graph_items as $chd_item){
									if(($gitem['key_'] == $chd_item['key_']) && (bccomp($chd_host['hostid'], $chd_item['hostid']) == 0)) 
										continue 2;
								}
								
								self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ] (items are not identical)');
							}
							
							$tmp_graph['graphid'] = $chd_graph['graphid'];
							self::updateReal($tmp_graph);
						}
						else{
							self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ] (items are not identical)');
						}
					}
					else{
						$graphid = self::createReal($tmp_graph);
						$tmp_graph['graphid'] = $graphid;
					}
				}				
				self::inherit($tmp_graph);
			}
		}
	}
	
/**
 * Inherit template graphs from template to host
 *
 * params: templateids, hostids
 *
 * @static
 * @param array $data
 * @return boolean
 */
	public static function syncTemplates($data){	
		try{
			self::BeginTransaction(__METHOD__);
			
			$data['templateids'] = zbx_toArray($data['templateids']);
			$data['hostids'] = zbx_toArray($data['hostids']);
			
			$objectids = array_merge($data['templateids'], $data['hostids']);
			$options = array(
				'hostids' => $objectids,
				'editable' => 1,
				'preservekeys' => 1,
				'templated_hosts' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedObjects = CHost::get($options);
			foreach($objectids as $objectid){
				if(!isset($allowedObjects[$objectid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
			
			$sql = 'SELECT hostid, templateid'.
				' FROM hosts_templates'.
				' WHERE '.DBcondition('hostid', $data['hostids']).
				' AND '.DBcondition('templateid', $data['templateids']);
			$db_links = DBSelect($sql);
			$linkage = array();
			while($link = DBfetch($db_links)){
				if(!isset($linkage[$link['templateid']])) $linkage[$link['templateid']] = array();
				$linkage[$link['templateid']][$link['hostid']] = 1;		
			}

			$options = array(
				'hostids' => $data['templateids'],
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
				'select_graph_items' => API_OUTPUT_EXTEND
			);
			$graphs = self::get($options);
			
			foreach($graphs as $graph){
				foreach($data['hostids'] as $hostid){
					if(isset($linkage[$graph['hosts'][0]['hostid']][$hostid])){
						self::inherit($graph, $hostid);
					}
				}			
			}
			
			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete graphs
 *
 * @static
 * @param array $graphs
 * @param array $graphs['graphids']
 * @return boolean
 */
	public static function delete($graphs){
		$graphs = zbx_toArray($graphs);

		try{
			self::BeginTransaction(__METHOD__);
			
			$graphids = array();
			$options = array(
				'graphids' => zbx_objectValues($graphs, 'graphid'),
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$del_graphs = self::get($options);
			foreach($graphs as $gnum => $graph){
				if(!isset($del_graphs[$graph['graphid']]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);

				$graphids[] = $graph['graphid'];
			}

			if(!delete_graph($graphids))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot delete Graphs');
				
			self::EndTransaction(true, __METHOD__);
			return array('graphids' => $graphids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

// DEPRECATED
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

// DEPRECATED
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

// DEPRECATED
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
	
	private static function checkInput($graphs){
	
		foreach($graphs as $gnum => $graph){
// EXCEPTION: NO ITEMS {{{
			if(!isset($graph['gitems']) || !is_array($graph['gitems']) || empty($graph['gitems'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_MISSING_ITEMS_FOR_GRAPH.' [ '.$graph['name'].' ]');
			}
// }}} EXCEPTION: NO ITEMS


// EXCEPTION: GRAPH FIELDS {{{
			$fields = array('name' => null);
			if(!check_db_fields($fields, $graph)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for graph');
			}
// }}} EXCEPTION: GRAPH FIELDS


// EXCEPTION: ITEMS FIELDS {{{
			$fields = array('itemid' => null);
			foreach($graph['gitems'] as $ginum => $gitem){
				if(!check_db_fields($fields, $gitem)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for items');
				}
			}
// }}} EXCEPTION: ITEMS FIELDS


// EXCEPTION: ITEMS PERMISSIONS {{{
			$options = array(
				'nodeids' => get_current_nodeid(true),
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'webitems' => 1,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$allowed_items = CItem::get($options);
			foreach($graph['gitems'] as $gitem){
				if(!isset($allowed_items[$gitem['itemid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}
// }}} EXCEPTION: ITEMS PERMISSIONS
		}
		return true;
	}
	
}
?>