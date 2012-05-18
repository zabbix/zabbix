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
 * File containing CCService class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Discovered Services
 */
class CDService extends CZBXAPI{
/**
 * Get Service data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] ServiceGroup IDs
 * @param array $options['hostids'] Service IDs
 * @param boolean $options['monitored_hosts'] only monitored Services
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
 * @param int $options['extendoutput'] return all fields for Services
 * @param boolean $options['select_groups'] select ServiceGroups
 * @param boolean $options['select_templates'] select Templates
 * @param boolean $options['select_items'] select Items
 * @param boolean $options['select_triggers'] select Triggers
 * @param boolean $options['select_graphs'] select Graphs
 * @param boolean $options['select_applications'] select Applications
 * @param boolean $options['select_macros'] select Macros
 * @param boolean $options['select_profile'] select Profile
 * @param int $options['count'] count Services, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Service name
 * @param string $options['extendPattern'] search hosts by pattern in Service name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Service data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$nodeCheck = false;
		$user_type = $USER_DETAILS['type'];

		$sort_columns = array('dserviceid', 'dhostid'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('dservices' => 'ds.dserviceid'),
			'from' => array('dservices' => 'dservices ds'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'					=> null,
			'dserviceids'				=> null,
			'dhostids'					=> null,
			'dcheckids'					=> null,
			'druleids'					=> null,
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
			'selectDHosts'				=> null,
			'selectDChecks'				=> null,
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
			unset($sql_parts['select']['dservices']);
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' ds.'.$field;
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

// dserviceids
		if(!is_null($options['dserviceids'])){
			zbx_value2array($options['dserviceids']);
			$sql_parts['where']['dserviceid'] = DBcondition('ds.dserviceid', $options['dserviceids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// dhostids
		if(!is_null($options['dhostids'])){
			zbx_value2array($options['dhostids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dhostid'] = 'ds.dhostid';
			}

			$sql_parts['where'][] = DBcondition('ds.dhostid', $options['dhostids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dhostid'] = 'ds.dhostid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ds.dhostid', $nodeids);
			}
		}


// dcheckids
		if(!is_null($options['dcheckids'])){
			zbx_value2array($options['dcheckids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dcheckid'] = 'dc.dcheckid';
			}

			$sql_parts['from']['dhosts'] = 'dhosts dh';
			$sql_parts['from']['dchecks'] = 'dchecks dc';

			$sql_parts['where'][] = DBcondition('dc.dcheckid', $options['dcheckids']);
			$sql_parts['where']['dhds'] = 'dh.hostid=ds.hostid';
			$sql_parts['where']['dcdh'] = 'dc.druleid=dh.druleid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dcheckid'] = 'dc.dcheckid';
			}
		}

// druleids
		if(!is_null($options['druleids'])){
			zbx_value2array($options['druleids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['druleid'] = 'dh.druleid';
			}

			$sql_parts['from']['dhosts'] = 'dhosts dh';

			$sql_parts['where']['druleid'] = DBcondition('dh.druleid', $options['druleids']);
			$sql_parts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['druleid'] = 'dh.druleid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('dh.druleid', $nodeids);
			}
		}

// node check !!!!!
// should last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('ds.dserviceid', $nodeids);
		}


// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['dservices'] = 'ds.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT ds.dserviceid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('dservices ds', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('dservices ds', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][$options['sortfield']] = 'ds.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('ds.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('ds.*', $sql_parts['select'])){
				$sql_parts['select'][$options['sortfield']] = 'ds.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------


		$dserviceids = array();

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
		while($dservice = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $dservice;
				else
					$result = $dservice['rowscount'];
			}
			else{
				$dserviceids[$dservice['dserviceid']] = $dservice['dserviceid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$dservice['dserviceid']] = array('dserviceid' => $dservice['dserviceid']);
				}
				else{
					if(!isset($result[$dservice['dserviceid']])) $result[$dservice['dserviceid']]= array();

					if(!is_null($options['selectDRules']) && !isset($result[$dservice['dserviceid']]['drules'])){
						$result[$dservice['dserviceid']]['drules'] = array();
					}

					if(!is_null($options['selectDHosts']) && !isset($result[$dservice['dserviceid']]['dhosts'])){
						$result[$dservice['dserviceid']]['dhosts'] = array();
					}

					if(!is_null($options['selectDChecks']) && !isset($result[$dservice['dserviceid']]['dchecks'])){
						$result[$dservice['dserviceid']]['dchecks'] = array();
					}

					if(!is_null($options['selectHosts']) && !isset($result[$dservice['dserviceid']]['hosts'])){
						$result[$dservice['dserviceid']]['hosts'] = array();
					}
// druleids
					if(isset($dservice['druleid']) && is_null($options['selectDRules'])){
						if(!isset($result[$dservice['dserviceid']]['drules']))
							$result[$dservice['dserviceid']]['drules'] = array();

						$result[$dservice['dserviceid']]['drules'][] = array('druleid' => $dservice['druleid']);
					}
// dhostids
					if(isset($dservice['dhostid']) && is_null($options['selectDHosts'])){
						if(!isset($result[$dservice['dserviceid']]['dhosts']))
							$result[$dservice['dserviceid']]['dhosts'] = array();

						$result[$dservice['dserviceid']]['dhosts'][] = array('dhostid' => $dservice['dhostid']);
					}
// dcheckids
					if(isset($dservice['dcheckid']) && is_null($options['selectDChecks'])){
						if(!isset($result[$dservice['dserviceid']]['dchecks']))
							$result[$dservice['dserviceid']]['dchecks'] = array();

						$result[$dservice['dserviceid']]['dchecks'][] = array('dcheckid' => $dservice['dcheckid']);
					}

					$result[$dservice['dserviceid']] += $dservice;
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
				'dserviceids' => $dserviceids,
				'preservekeys' => 1
			);

			if(is_array($options['selectDRules']) || str_in_array($options['selectDRules'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDRules'];
				$drules = CDRule::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($drules, 'name');
				foreach($drules as $druleid => $drule){
					unset($drules[$druleid]['dservices']);
					$count = array();
					foreach($drule['dservices'] as $dnum => $dservice){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dservice['dserviceid']])) $count[$dservice['dserviceid']] = 0;
							$count[$dservice['dserviceid']]++;

							if($count[$dservice['dserviceid']] > $options['limitSelects']) continue;
						}

						$result[$dservice['dserviceid']]['drules'][] = &$drules[$druleid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectDRules']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$drules = CDRule::get($obj_params);
				$drules = zbx_toHash($drules, 'dserviceid');
				foreach($result as $dserviceid => $dservice){
					if(isset($drules[$dserviceid]))
						$result[$dserviceid]['drules'] = $drules[$dserviceid]['rowscount'];
					else
						$result[$dserviceid]['drules'] = 0;
				}
			}
		}

// selectDHosts
		if(!is_null($options['selectDHosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'dserviceids' => $dserviceids,
				'preservekeys' => 1
			);

			if(is_array($options['selectDHosts']) || str_in_array($options['selectDHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDHosts'];
				$dhosts = CDHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($dhosts, 'dhostid');
				foreach($dhosts as $dhostid => $dhost){
					unset($dhosts[$dhostid]['dservices']);
					foreach($dhost['dservices'] as $snum => $dservice){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dservice['dserviceid']])) $count[$dservice['dserviceid']] = 0;
							$count[$dservice['dserviceid']]++;

							if($count[$dservice['dserviceid']] > $options['limitSelects']) continue;
						}

						$result[$dservice['dserviceid']]['dhosts'][] = &$dhosts[$dhostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectDHosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$dhosts = CDHost::get($obj_params);
				$dhosts = zbx_toHash($dhosts, 'dhostid');
				foreach($result as $dserviceid => $dservice){
					if(isset($dhosts[$dserviceid]))
						$result[$dserviceid]['dhosts'] = $dhosts[$dserviceid]['rowscount'];
					else
						$result[$dserviceid]['dhosts'] = 0;
				}
			}
		}

// select_hosts
		if(!is_null($options['selectHosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'dserviceids' => $dserviceids,
				'preservekeys' => 1,
				'sortfield' => 'status'
			);

			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectHosts'];
				$hosts = CHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($hosts, 'hostid');

				foreach($hosts as $hostid => $host){
					unset($hosts[$hostid]['dservices']);
					foreach($host['dservices'] as $dnum => $dservice){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dservice['dserviceid']])) $count[$dservice['dserviceid']] = 0;
							$count[$dservice['dserviceid']]++;

							if($count[$dservice['dserviceid']] > $options['limitSelects']) continue;
						}

						$result[$dservice['dserviceid']]['hosts'][] = &$hosts[$hostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectHosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$hosts = CHost::get($obj_params);
				$hosts = zbx_toHash($hosts, 'hostid');
				foreach($result as $dserviceid => $dservice){
					if(isset($hosts[$dserviceid]))
						$result[$dserviceid]['hosts'] = $hosts[$dserviceid]['rowscount'];
					else
						$result[$dserviceid]['hosts'] = 0;
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
		$keyFields = array(array('dserviceid'));

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
 * Add Service
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $dservices multidimensional array with Services data
 */
	public static function create($dservices){
		$errors = array();
		$dservices = zbx_toArray($dservices);
		$dserviceids = array();
		$groupids = array();
		$result = false;

		if($result){
			return array('dserviceids' => $dserviceids);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Update DService
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $dservices multidimensional array with Services data
 */
	public static function update($dservices){
		$errors = array();
		$result = true;

		$dservices = zbx_toArray($dservices);
		$dserviceids = zbx_objectValues($dservices, 'hostid');

		try{
			return array('dserviceids' => $dserviceids);
		}
		catch(APIException $e){
			if(isset($transaction)) self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);

			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete Discovered Service
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $dservices
 * @param array $dservices[0, ...]['hostid'] Service ID to delete
 * @return array|boolean
 */
	public static function delete($dservices){
		$dservices = zbx_toArray($dservices);
		$dserviceids = array();

		if($result){
			return array('hostids' => $dserviceids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>
