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
 * File containing CHistory class for API.
 * @package API
 */
/**
 * Class containing methods for operations with History of Items
 *
 */
class CHistory extends CZBXAPI{
/**
 * Get history data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8.3
 * @version 1.3
 *
 * @param array $options
 * @param array $options['itemids']
 * @param boolean $options['editable']
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['order']
 * @return array|int item data as array or false if error
 */
	public static function get(array $options=array()){
		global $USER_DETAILS;

		$result = array();

		$sort_columns = array('itemid', 'clock'); // allowed columns for sorting

		$sql_parts = array(
			'select' => array('history' => 'h.itemid'),
			'from' => array(),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'history'				=> ITEM_VALUE_TYPE_UINT64,
			'nodeids'				=> null,
			'itemids'				=> null,
			'nopermissions'			=> null,

// filter
			'filter'				=> null,
			'search'				=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'	=> null,

			'time_from'				=> null,
			'time_till'				=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'groupOutput'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);



		// Cassandra
		if(
			($options['history'] == ITEM_VALUE_TYPE_UINT64
			|| $options['history'] == ITEM_VALUE_TYPE_FLOAT)
			&& CassandraHistory::i()->enabled()
		){
			foreach($options['itemids'] as $itemid){
				$data = CassandraHistory::i()->getData($itemid, $options['time_from'], $options['time_till'], $options['limit'], ZBX_SORT_DOWN);

				foreach($data as $clock => $value){
					$result[] = array(
						'itemid' => $itemid,
						'clock' => $clock,
						'value' => $value,
					);
				}
			}
			return $result;
		}

		switch($options['history']){
			case ITEM_VALUE_TYPE_LOG:
				$sql_parts['from']['history'] = 'history_log h';
				$sort_columns[] = 'id';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				$sql_parts['from']['history'] = 'history_text h';
				$sort_columns[] = 'id';
				break;
			case ITEM_VALUE_TYPE_STR:
				$sql_parts['from']['history'] = 'history_str h';
				break;
			case ITEM_VALUE_TYPE_UINT64:
				$sql_parts['from']['history'] = 'history_uint h';
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			default:
				$sql_parts['from']['history'] = 'history h';
		}

// editable + PERMISSION CHECK
		if((USER_TYPE_SUPER_ADMIN == $USER_DETAILS['type']) || $options['nopermissions']){

		}
		else{
			$items = CItem::get(array(
				'itemids' => $options['itemids'],
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true,
			));
			$options['itemids'] = array_keys($items);
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();
		$sql_parts['where'][] = DBin_node('h.itemid', $nodeids);

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			$sql_parts['where']['itemid'] = DBcondition('h.itemid', $options['itemids']);
		}

// time_from
		if(!is_null($options['time_from'])){
			$sql_parts['select']['clock'] = 'h.clock';
			$sql_parts['where']['clock_from'] = 'h.clock>='.$options['time_from'];
		}

// time_till
		if(!is_null($options['time_till'])){
			$sql_parts['select']['clock'] = 'h.clock';
			$sql_parts['where']['clock_till'] = 'h.clock<='.$options['time_till'];
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter($sql_parts['from']['history'], $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search($sql_parts['from']['history'], $options, $sql_parts);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			unset($sql_parts['select']['clock']);

			$sql_parts['select']['history'] = 'h.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(h.itemid) as rowscount');
		}

// groupOutput
		$groupOutput = false;
		if(!is_null($options['groupOutput'])){
			if(str_in_array('h.'.$options['groupOutput'], $sql_parts['select']) || str_in_array('h.*', $sql_parts['select'])){
				$groupOutput = true;
			}
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN) ? ZBX_SORT_DOWN : ZBX_SORT_UP;

			if($options['sortfield'] == 'clock'){
				$sql_parts['order']['itemid'] = 'h.itemid ' . $sortorder;
			}
			$sql_parts['order'][$options['sortfield']] = 'h.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('h.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('h.*', $sql_parts['select'])){
				$sql_parts['select'][$options['sortfield']] = 'h.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//---------------


		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select'])) $sql_select .= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from'])) $sql_from .= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where'])) $sql_where .= implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order'])) $sql_order .= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.$sql_where.
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);

		$num = 0;
		$group = array();
		while($data = DBfetch($db_res)){

			if($options['countOutput']){
				$result = $data;
			}
			else{
				$result[$num] = $data;
// grouping
				if($groupOutput){
					$dataid = $data[$options['groupOutput']];
					if(!isset($group[$dataid])) $group[$dataid] = array();
					$group[$dataid][] = $result[$num];
				}

				$num++;
			}
		}

		return $result;
	}

}
?>
