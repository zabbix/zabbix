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
 * File containing CGraphItem class for API.
 * @package API
 */
/**
 * Class containing methods for operations with GraphItems
 */
class CGraphItem extends CZBXAPI{
/**
* Get GraphItems data
*
* @static
* @param array $options
* @return array|boolean
*/
	public static function get($options = array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		$result = array();

		$sort_columns = array('gitemid'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('gitems' => 'gi.gitemid'),
			'from' => array('graphs_items' => 'graphs_items gi'),
			'where' => array(),
			'order' => array(),
			'limit' => null,
		);

		$def_options = array(
			'nodeids' 				=> null,
			'graphids' 				=> null,
			'itemids' 				=> null,
			'type' 					=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// output
			'select_graphs'			=> null,
			'output'				=> API_OUTPUT_REFER,
			'expandData'			=> null,
			'extendoutput'			=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;
		}


// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
										' SELECT hgg.groupid '.
										' FROM hosts_groups hgg, rights rr, users_groups ugg '.
										' WHERE i.hostid=hgg.hostid '.
											' AND rr.id=hgg.groupid '.
											' AND rr.groupid=ugg.usrgrpid '.
											' AND ugg.userid='.$userid.
											' AND rr.permission<'.$permission.')';
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}
			$sql_parts['from']['graphs'] = 'graphs g';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where'][] = DBcondition('g.graphid', $options['graphids']);
		}
// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'gi.itemid';
			}
			$sql_parts['where'][] = DBcondition('gi.itemid', $options['itemids']);
		}

// type
		if(!is_null($options['type'] )){
			$sql_parts['where'][] = 'gi.type='.$options['type'];
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['gitems'] = 'gi.*';
		}

// expandData
		if(!is_null($options['expandData'])){
			$sql_parts['select']['key'] = 'i.key_';
			$sql_parts['select']['hostid'] = 'i.hostid';
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['gii'] = 'gi.itemid=i.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
		}


// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT gi.gitemid) as rowscount');
		}


// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'gi.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('gi.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('gi.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'gi.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//------------

		$gitemids = array();

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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('gi.gitemid', $nodeids).
					$sql_where.
				$sql_order;
//SDI($sql);
		$db_res = DBselect($sql, $sql_limit);
		while($gitem = DBfetch($db_res)){
			if(!is_null($options['countOutput'])){
				$result = $gitem['rowscount'];
			}
			else{
				$gitemids[$gitem['gitemid']] = $gitem['gitemid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$gitem['gitemid']] = array('gitemid' => $gitem['gitemid']);
				}
				else{
					if(!isset($result[$gitem['gitemid']]))
						$result[$gitem['gitemid']]= array();

// graphids
					if(isset($gitem['graphid']) && is_null($options['select_graphs'])){
						if(!isset($result[$gitem['gitemid']]['graphs'])) $result[$gitem['gitemid']]['graphs'] = array();

						$result[$gitem['gitemid']]['graphs'][] = array('graphid' => $gitem['graphid']);
//						unset($gitem['graphid']);
					}

					$result[$gitem['gitemid']] += $gitem;
				}
			}
		}

		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding graphs
		if(!is_null($options['select_graphs']) && str_in_array($options['select_graphs'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_graphs'],
				'gitemids' => $gitemids,
				'preservekeys' => 1
			);
			$graphs = CGraph::get($obj_params);
			foreach($graphs as $graphid => $graph){
				$gitems = $graph['gitems'];
				unset($graph['gitems']);
				foreach($gitems as $inum => $item){
					$result[$gitem['gitemid']]['graphs'][] = $graph;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get graph items by graph id and graph item id
 *
 * @static
 * @param _array $gitem_data
 * @param array $gitem_data['itemid']
 * @param array $gitem_data['graphid']
 * @return string|boolean graphid
 */
	public static function getObjects($gitem_data){
		$result = array();
		$gitemids = array();

		$sql = 'SELECT gi.gitemid '.
				' FROM graphs_items gi '.
				' WHERE gi.itemid='.$gitem_data['itemid'].
					' AND gi.graphid='.$gitem_data['graphid'].
		$db_res = DBselect($sql);
		while($gitem = DBfetch($db_res)){
			$gitemids[$gitem['gitemid']] = $gitem['gitemid'];
		}

		if(!empty($gitemids))
			$result = self::get(array('gitemids'=>$gitemids, 'extendoutput'=>1));

	return $result;
	}
}
?>
