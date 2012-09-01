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
 * File containing CCHost class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Discovered Hosts
 */
class CDHost extends CZBXAPI{
/**
 * Get Host data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] HostGroup IDs
 * @param array $options['hostids'] Host IDs
 * @param boolean $options['monitored_hosts'] only monitored Hosts
 * @param boolean $options['templated_hosts'] include templates in result
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['with_monitored_items'] only with monitored items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param boolean $options['select_groups'] select HostGroups
 * @param boolean $options['select_templates'] select Templates
 * @param boolean $options['select_items'] select Items
 * @param boolean $options['select_triggers'] select Triggers
 * @param boolean $options['select_graphs'] select Graphs
 * @param boolean $options['select_applications'] select Applications
 * @param boolean $options['select_macros'] select Macros
 * @param boolean $options['select_profile'] select Profile
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Host name
 * @param string $options['extendPattern'] search hosts by pattern in Host name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$nodeCheck = false;
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('dhostid', 'druleid'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('dhosts' => 'dh.dhostid'),
			'from' => array('dhosts' => 'dhosts dh'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'druleids'					=> null,
			'dhostids'					=> null,
			'dserviceids'				=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'output'					=> API_OUTPUT_REFER,

			'selectDRules'				=> null,
			'selectDServices'			=> null,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);

		$options = zbx_array_merge($def_options, $options);

		if(is_array($options['output'])){
			unset($sql_parts['select']['dhosts']);
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' dh.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN == $user_type){
		}
		else if(is_null($options['editable']) && ($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN)){
		}
		else if(!is_null($options['editable']) && ($USER_DETAILS['type']!=USER_TYPE_SUPER_ADMIN)){
			return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// dhostids
		if(!is_null($options['dhostids'])){
			zbx_value2array($options['dhostids']);
			$sql_parts['where']['dhostid'] = DBcondition('dh.dhostid', $options['dhostids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('dh.dhostid', $nodeids);
			}
		}

// druleids
		if(!is_null($options['druleids'])){
			zbx_value2array($options['druleids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['druleid'] = 'dh.druleid';
			}

			$sql_parts['where']['druleid'] = DBcondition('dh.druleid', $options['druleids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['druleid'] = 'dh.druleid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('dh.druleid', $nodeids);
			}
		}

// dserviceids
		if(!is_null($options['dserviceids'])){
			zbx_value2array($options['dserviceids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dserviceids'] = 'ds.dserviceids';
			}

			$sql_parts['from']['dservices'] = 'dservices ds';
			$sql_parts['where'][] = DBcondition('ds.dserviceid', $options['dserviceids']);
			$sql_parts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dserviceids'] = 'ds.dserviceid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['dhh'] = 'h.ip=dh.ip';
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';


			if(!is_null($options['groupCount'])){
				$sql_parts['group']['groupid'] = 'hg.groupid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('hg.groupid', $nodeids);
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'h.hostid';
			}

			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where'][] = DBcondition('h.hostid', $options['hostids']);
			$sql_parts['where']['dhh'] = 'h.ip=dh.ip';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hostid'] = 'h.hostid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('h.hostid', $nodeids);
			}
		}

// node check !!!!!
// should be last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('dh.dhostid', $nodeids);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['dhosts'] = 'dh.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT dh.dhostid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('dhosts dh', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('dhosts dh', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][$options['sortfield']] = 'dh.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('dh.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('dh.*', $sql_parts['select'])){
				$sql_parts['select'][$options['sortfield']] = 'dh.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------


		$dhostids = array();

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
		if(!empty($sql_parts['where']))		$sql_where.= implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.$sql_where.
				$sql_group.
				$sql_order;
 //SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($dhost = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $dhost;
				else
					$result = $dhost['rowscount'];
			}
			else{
				$dhostids[$dhost['dhostid']] = $dhost['dhostid'];
//				$dips[$dhost['ip']] = $dhost['ip'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$dhost['dhostid']] = array('dhostid' => $dhost['dhostid']);
				}
				else{
					if(!isset($result[$dhost['dhostid']])) $result[$dhost['dhostid']]= array();

					if(!is_null($options['selectDRules']) && !isset($result[$dhost['dhostid']]['drules'])){
						$result[$dhost['dhostid']]['drules'] = array();
					}

					if(!is_null($options['selectDServices']) && !isset($result[$dhost['dhostid']]['dservices'])){
						$result[$dhost['dhostid']]['dservices'] = array();
					}

					if(!is_null($options['selectGroups']) && !isset($result[$dhost['dhostid']]['groups'])){
						$result[$dhost['dhostid']]['groups'] = array();
					}

					if(!is_null($options['selectHosts']) && !isset($result[$dhost['dhostid']]['hosts'])){
						$result[$dhost['dhostid']]['hosts'] = array();
					}

// druleids
					if(isset($dhost['druleid']) && is_null($options['selectDRules'])){
						if(!isset($result[$dhost['dhostid']]['drules']))
							$result[$dhost['dhostid']]['drules'] = array();

						$result[$dhost['dhostid']]['drules'][] = array('druleid' => $dhost['druleid']);
					}
// dserviceids
					if(isset($dhost['dserviceid']) && is_null($options['selectDServices'])){
						if(!isset($result[$dhost['dhostid']]['dservices']))
							$result[$dhost['dhostid']]['dservices'] = array();

						$result[$dhost['dhostid']]['dservices'][] = array('dserviceid' => $dhost['dserviceid']);
						unset($dhost['dserviceid']);
					}
// groupids
					if(isset($dhost['groupid']) && is_null($options['selectGroups'])){
						if(!isset($result[$dhost['dhostid']]['groups']))
							$result[$dhost['dhostid']]['groups'] = array();

						$result[$dhost['dhostid']]['groups'][] = array('groupid' => $dhost['groupid']);
						unset($dhost['groupid']);
					}

// hostids
					if(isset($dhost['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$dhost['hostid']]['hosts']))
							$result[$dhost['dhostid']]['hosts'] = array();

						$result[$dhost['dhostid']]['hosts'][] = array('hostid' => $dhost['hostid']);
						unset($dhost['hostid']);
					}

					$result[$dhost['dhostid']] += $dhost;
				}
			}
		}

Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// select_drules
		if(!is_null($options['selectDRules'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if(is_array($options['selectDRules']) || str_in_array($options['selectDRules'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDRules'];
				$drules = CDRule::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($drules, 'name');
				foreach($drules as $druleid => $drule){
					unset($drules[$druleid]['dhosts']);
					$count = array();
					foreach($drule['dhosts'] as $dnum => $dhost){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['drules'][] = &$drules[$druleid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectDRules']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$drules = CDRule::get($obj_params);
				$drules = zbx_toHash($drules, 'dhostid');
				foreach($result as $dhostid => $dhost){
					if(isset($drules[$dhostid]))
						$result[$dhostid]['drules'] = $drules[$dhostid]['rowscount'];
					else
						$result[$dhostid]['drules'] = 0;
				}
			}
		}

// selectDServices
		if(!is_null($options['selectDServices'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if(is_array($options['selectDServices']) || str_in_array($options['selectDServices'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDServices'];
				$dservices = CDService::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($dservices, 'name');
				foreach($dservices as $dserviceid => $dservice){
					unset($dservices[$dserviceid]['dhosts']);
					foreach($dservice['dhosts'] as $dnum => $dhost){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['dservices'][] = &$dservices[$dserviceid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectDServices']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$dservices = CDService::get($obj_params);
				$dservices = zbx_toHash($dservices, 'dhostid');
				foreach($result as $dhostid => $dhost){
					if(isset($dservices[$dhostid]))
						$result[$dhostid]['dservices'] = $dservices[$dhostid]['rowscount'];
					else
						$result[$dhostid]['dservices'] = 0;
				}
			}
		}

// TODO :select_groups
		if(!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselects_allowed_outputs)){
			$obj_params = array(
					'nodeids' => $nodeids,
					'output' => $options['selectGroups'],
					'hostids' => $dhostids,
					'preservekeys' => 1
				);
			$groups = CHostgroup::get($obj_params);

			foreach($groups as $groupid => $group){
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach($ghosts as $num => $dhost){
					$result[$dhost['hostid']]['groups'][] = $group;
				}
			}
		}

// select_hosts
		if(!is_null($options['selectHosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectHosts'];
				$hosts = CHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($hosts, 'host');

				foreach($hosts as $hostid => $host){
					unset($hosts[$hostid]['dhosts']);
					foreach($host['dhosts'] as $dnum => $dhost){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectHosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$hosts = CHost::get($obj_params);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach($result as $dhostid => $dhost){
					if(isset($hosts[$dhostid]))
						$result[$dhostid]['hosts'] = $hosts[$dhostid]['rowscount'];
					else
						$result[$dhostid]['hosts'] = 0;
				}
			}
		}

Copt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

	public static function exists($object){
		$keyFields = array(array('dhostid'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

/**
 * Add Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $dhosts multidimensional array with Hosts data
 */
	public static function create($dhosts){
		$errors = array();
		$dhosts = zbx_toArray($dhosts);
		$dhostids = array();
		$result = false;

		if($result){
			return array('dhostids' => $dhostids);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Update DHost
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $dhosts multidimensional array with Hosts data
 */
	public static function update($dhosts){
		$dhosts = zbx_toArray($dhosts);
		$dhostids = zbx_objectValues($dhosts, 'hostid');

		try{
			return array('dhostids' => $dhostids);
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
 * Delete Discovered Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $dhosts
 * @param array $dhosts[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 */
	public static function delete($dhostids){
		$result = false;
		$dhostids = zbx_toArray($dhostids);

		if($result){
			return array('hostids' => $dhostids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}
}
?>
