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
 * File containing CProxy class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Proxies
 */
class CProxy extends CZBXAPI{
/**
 * Get Proxy data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids']
 * @param array $options['proxyids']
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param boolean $options['extendoutput'] return all fields
 * @param int $options['count'] returns value in rowscount
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['sortfield']
 * @param string $options['sortorder']
 * @return array|boolean
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('hostid', 'host', 'status'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('hostid' => 'h.hostid'),
			'from' => array('hosts' => 'hosts h'),
			'where' => array('h.status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'proxyids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'extendoutput'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'preservekeys'				=> null,

			'select_hosts'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
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
			if($permission == PERM_READ_WRITE)
				return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// proxyids
		if(!is_null($options['proxyids'])){
			zbx_value2array($options['proxyids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['proxyids']);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('hosts h', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('hosts h', $options, $sql_parts);
		}


// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['hostid'] = 'h.hostid';
			$sql_parts['select']['host'] = 'h.host';
			$sql_parts['select']['status'] = 'h.status';
			$sql_parts['select']['lastaccess'] = 'h.lastaccess';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT h.hostid) as rowscount');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'h.'.$options['sortfield'].' '.$sortorder;
			if(!str_in_array('h.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('h.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'h.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$proxyids = array();

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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('h.hostid', $nodeids).
					$sql_where.
				$sql_order;
// sdi($sql);
		$res = DBselect($sql, $sql_limit);
		while($proxy = DBfetch($res)){
			if($options['countOutput']){
				$result = $proxy['rowscount'];
			}
			else{
				$proxyids[$proxy['hostid']] = $proxy['hostid'];

				$proxy['proxyid'] = $proxy['hostid'];
				unset($proxy['hostid']);

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$proxy['proxyid']] = array('proxyid' => $proxy['proxyid']);
				}
				else{
					if(!isset($result[$proxy['proxyid']])) $result[$proxy['proxyid']]= array();

					if(!is_null($options['select_hosts']) && !isset($result[$proxy['proxyid']]['hosts'])){
						$result[$proxy['proxyid']]['hosts'] = array();
					}

					$result[$proxy['proxyid']] += $proxy;
				}
			}
		}

		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects

// select_hosts
		if(!empty($proxyids)){
			if(!is_null($options['select_hosts']) && str_in_array($options['select_hosts'], $subselects_allowed_outputs)){
				$obj_params = array(
					'nodeids' => $nodeids,
					'output' => $options['select_hosts'],
					'proxyids' => $proxyids,
					'preservekeys' => 1
				);
				$hosts = CHost::get($obj_params);

				foreach($hosts as $host){
					$result[$host['proxy_hostid']]['hosts'][] = $host;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

}
?>
