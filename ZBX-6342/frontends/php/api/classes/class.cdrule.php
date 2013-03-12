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
 * File containing drule class for API.
 * @package API
 */
/**
 * Class containing methods for operations with discovery rules
 */
class CDRule extends CZBXAPI{
/**
* Get drule data
*
* @static
* @param array $options
* @return array
*/
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$nodeCheck = false;
		$user_type = $USER_DETAILS['type'];
		$result = array();

		$sort_columns = array('druleid','name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('drules' => 'dr.druleid'),
			'from' => array('drules' => 'drules dr'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null,
		);

		$def_options = array(
			'nodeids'				=> null,
			'druleids'				=> null,
			'dhostids'				=> null,
			'dserviceids'			=> null,
			'dcheckids'				=> null,
			'editable'				=> null,
			'selectDHosts'			=> null,
			'selectDServices'		=> null,
			'selectDChecks'			=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// output
			'output'				=> API_OUTPUT_REFER,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null,
			'limitSelects'			=> null
		);

		$options = zbx_array_merge($def_options, $options);

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

// druleids
		if(!is_null($options['druleids'])){
			zbx_value2array($options['druleids']);
			$sql_parts['where']['druleid'] = DBcondition('dr.druleid', $options['druleids']);

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('dr.druleid', $nodeids);
			}
		}

// dhostids
		if(!is_null($options['dhostids'])){
			zbx_value2array($options['dhostids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dhostid'] = 'dh.dhostid';
			}

			$sql_parts['from']['dhosts'] = 'dhosts dh';
			$sql_parts['where']['dhostid'] = DBcondition('dh.dhostid', $options['dhostids']);
			$sql_parts['where']['dhdr'] = 'dh.druleid=dr.druleid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dhostid'] = 'dh.dhostid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('dh.dhostid', $nodeids);
			}
		}

// dserviceids
		if(!is_null($options['dserviceids'])){
			zbx_value2array($options['dserviceids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['dserviceid'] = 'ds.dserviceid';
			}

			$sql_parts['from']['dhosts'] = 'dhosts dh';
			$sql_parts['from']['dservices'] = 'dservices ds';

			$sql_parts['where']['dserviceid'] = DBcondition('ds.dserviceid', $options['dserviceids']);
			$sql_parts['where']['dhdr'] = 'dh.druleid=dr.druleid';
			$sql_parts['where']['dhds'] = 'dh.dhostid=ds.dhostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['dserviceid'] = 'ds.dserviceid';
			}

			if(!$nodeCheck){
				$nodeCheck = true;
				$sql_parts['where'][] = DBin_node('ds.dserviceid', $nodeids);
			}
		}

// node check !!!!!
// should be last, after all ****IDS checks
		if(!$nodeCheck){
			$nodeCheck = true;
			$sql_parts['where'][] = DBin_node('dr.druleid', $nodeids);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['drules'] = 'dr.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT dr.druleid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// search
		if(!is_null($options['search'])){
			zbx_db_search('drules dr', $options, $sql_parts);
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('drules dr', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('drules dr', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'dr.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('dr.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('dr.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'dr.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//------------

		$druleids = array();

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
		$db_res = DBselect($sql, $sql_limit);
		while($drule = DBfetch($db_res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $drule;
				else
					$result = $drule['rowscount'];
			}
			else{
				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$drule['druleid']] = array('druleid' => $drule['druleid']);
				}
				else{
					$druleids[$drule['druleid']] = $drule['druleid'];

					if(!is_null($options['selectDHosts']) && !isset($result[$drule['druleid']]['dhosts'])){
						$result[$drule['druleid']]['dhosts'] = array();
					}
					if(!is_null($options['selectDChecks']) && !isset($result[$drule['druleid']]['dchecks'])){
						$result[$drule['druleid']]['dchecks'] = array();
					}
					if(!is_null($options['selectDServices']) && !isset($result[$drule['druleid']]['dservices'])){
						$result[$drule['druleid']]['dservices'] = array();
					}

// dhostids
					if(isset($drule['dhostid']) && is_null($options['selectDHosts'])){
						if(!isset($result[$drule['druleid']]['dhosts']))
							$result[$drule['druleid']]['dhosts'] = array();

						$result[$drule['druleid']]['dhosts'][] = array('dhostid' => $drule['dhostid']);
						unset($drule['dhostid']);
					}
// dchecks
					if(isset($drule['dcheckid']) && is_null($options['selectDChecks'])){
						if(!isset($result[$drule['druleid']]['dchecks']))
							$result[$drule['druleid']]['dchecks'] = array();

						$result[$drule['druleid']]['dchecks'][] = array('dcheckid' => $drule['dcheckid']);
						unset($drule['dcheckid']);
					}

// dservices
					if(isset($drule['dserviceid']) && is_null($options['selectDServices'])){
						if(!isset($result[$drule['druleid']]['dservices']))
							$result[$drule['druleid']]['dservices'] = array();

						$result[$drule['druleid']]['dservices'][] = array('dserviceid' => $drule['dserviceid']);
						unset($drule['dserviceid']);
					}

					if(!isset($result[$drule['druleid']]))
						$result[$drule['druleid']]= array();

					$result[$drule['druleid']] += $drule;
				}
			}
		}

COpt::memoryPick();
		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects

// Adding Discovery Checks
		if(!is_null($options['selectDChecks'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'dhostids' => $dhostids,
				'preservekeys' => 1
			);

			if(is_array($options['selectDChecks']) || str_in_array($options['selectDChecks'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDChecks'];
				$dchecks = CDCheck::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($dchecks, 'name');
				foreach($dchecks as $dcheckid => $dcheck){
					unset($dchecks[$dcheckid]['dhosts']);
					$count = array();
					foreach($dcheck['dhosts'] as $dnum => $dhost){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$dhost['dhostid']])) $count[$dhost['dhostid']] = 0;
							$count[$dhost['dhostid']]++;

							if($count[$dhost['dhostid']] > $options['limitSelects']) continue;
						}

						$result[$dhost['dhostid']]['dchecks'][] = &$dchecks[$dcheckid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectDChecks']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$dchecks = CDCheck::get($obj_params);
				$dchecks = zbx_toHash($dchecks, 'druleid');
				foreach($result as $dhostid => $dhost){
					if(isset($dchecks[$druleid]))
						$result[$dhostid]['dchecks'] = $dchecks[$druleid]['rowscount'];
					else
						$result[$dhostid]['dchecks'] = 0;
				}
			}
		}

// Adding Discovery Hosts
		if(!is_null($options['selectDHosts'])){
			$obj_params = array(
				'nodeids' => $nodeids,
				'druleids' => $druleids,
				'preservekeys' => 1
			);

			if(is_array($options['selectDHosts']) || str_in_array($options['selectDHosts'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDHosts'];
				$dhosts = CDHost::get($obj_params);

				if(!is_null($options['limitSelects'])) order_result($dhosts, 'name');
				foreach($dhosts as $dhostid => $dhost){
					unset($dhosts[$dhostid]['drules']);

					foreach($dhost['drules'] as $dnum => $drule){
						if(!is_null($options['limitSelects'])){
							if(!isset($count[$drule['druleid']])) $count[$drule['druleid']] = 0;
							$count[$drule['druleid']]++;

							if($count[$drule['druleid']] > $options['limitSelects']) continue;
						}

						$result[$drule['druleid']]['dhosts'][] = &$dhosts[$dhostid];
					}
				}
			}
			else if(API_OUTPUT_COUNT == $options['selectDHosts']){
				$obj_params['countOutput'] = 1;
				$obj_params['groupCount'] = 1;

				$dhosts = CDHost::get($obj_params);
				$dhosts = zbx_toHash($dhosts, 'druleid');
				foreach($result as $druleid => $drule){
					if(isset($dhosts[$druleid]))
						$result[$druleid]['dhosts'] = $dhosts[$druleid]['rowscount'];
					else
						$result[$druleid]['dhosts'] = 0;
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


	public static function exists($object){
		$options = array(
			'filter' => array(),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['name'])) $options['filter']['name'] = $object['name'];
		if(isset($object['hostids'])) $options['druleids'] = zbx_toArray($object['druleids']);

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

/**
 * Create new drules
 *
 * @static
 * @param array $drules
 * @return boolean
 */
	public static function create($drules){
		$drules = zbx_toArray($drules);
		$druleids = array();

		try{
			self::BeginTransaction(__METHOD__);
			self::EndTransaction(true, __METHOD__);

			return array('druleids' => $druleids);
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
 * Update existing drules
 *
 * @static
 * @param array $drules
 * @return boolean
 */
	public static function update($drules){
		$drules = zbx_toArray($drules);
		$druleids = array();

		try{
			self::BeginTransaction(__METHOD__);

			self::EndTransaction(true, __METHOD__);
			return array('druleids' => $druleids);
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
 * Delete drules
 *
 * @static
 * @param array $drules
 * @param array $drules['druleids']
 * @return boolean
 */
	public static function delete($druleids){
		$druleids = zbx_toArray($druleids);

		try{
			self::BeginTransaction(__METHOD__);
// permissions
			$options = array(
				'druleids' => $druleids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$del_drules = self::get($options);
			foreach($drules as $gnum => $drule){
				if(!isset($del_drules[$drule['druleid']]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}
//----
//
// Conditions
			$sql = 'SELECT DISTINCT actionid, name '.
					' FROM conditions '.
					' WHERE conditiontype='.CONDITION_TYPE_DRULE.
						' AND value='.zbx_dbstr($druleids);
			$db_actions = DBselect($sql);
			while($db_action = DBfetch($db_actions)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Discovery rule is used in Action condition ['.$db_action['name'].'].');
			}


			$dhostids = array();
			$sql = 'select dhostid '.
					' from dhosts'.
					' where '.DBcondition('druleid',$druleids).
						' and '.DBin_node('dhostid');
			$db_dhosts = DBselect($sql);
			while($db_dhost = DBfetch($db_dhosts))
				$dhostids[$db_dhost['dhostid']] = $db_dhost['dhostid'];

			DBexecute('DELETE FROM dservices WHERE '.DBcondition('dhostid',$dhostids));
			DBexecute('DELETE FROM dhosts WHERE '.DBcondition('druleid',$druleids));
			DBexecute('DELETE FROM dchecks WHERE '.DBcondition('druleid',$druleids));
			DBexecute('DELETE FROM drules WHERE '.DBcondition('druleid',$druleids));

			self::EndTransaction(true, __METHOD__);
			return array('druleids' => $druleids);
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
	public static function addChecks($checks){

		$error = 'Unknown Zabbix internal error';
		$result_ids = array();
		$result = false;
		$tpl_drule = false;

		$druleid = $checks['druleid'];
		$checks_tmp = $checks['checks'];
		$checks = array();
		$checkids = array();

		foreach($checks_tmp as $check){

			$drule_db_fields = array(
				'checkid'	=> null,
				'color'		=> '000000',
				'drawtype'	=> 0,
				'sortorder'	=> 0,
				'yaxisside'	=> 1,
				'calc_fnc'	=> 2,
				'type'		=> 0,
				'periods_cnt'	=> 5
			);

			if(!check_db_fields($drule_db_fields, $check)){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Wrong fields for check [ '.$check['checkid'].' ]');
				return false;
			}
			$checks[$check['checkid']] = $check;
			$checkids[$check['checkid']] = $check['checkid'];
		}

// check if drule is templated drule, then checks cannot be added
		$drule = self::get(array('druleids' => $druleid,  'extendoutput' => 1));
		$drule = reset($drule);

		if($drule['templateid'] != 0){
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Cannot edit templated drule : '.$drule['name']);
			return false;
		}

		// check if drule belongs to template, if so, only checks from same template can be added
		$tmp_hosts = get_hosts_by_druleid($druleid);
		$host = DBfetch($tmp_hosts); // if drule belongs to template, only one host is possible

		if($host["status"] == HOST_STATUS_TEMPLATE ){
			$sql = 'SELECT DISTINCT count(i.hostid) as count
					FROM checks i
					WHERE i.hostid<>'.$host['hostid'].
						' AND '.DBcondition('i.checkid', $checkids);

			$host_count = DBfetch(DBselect($sql));
			if ($host_count['count']){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'You must use checks only from host : '.$host['host'].' for template drule : '.$drule['name']);
				return false;
			}
			$tpl_drule = true;
		}

		self::BeginTransaction(__METHOD__);
		$result = self::addchecks_rec($druleid, $checks, $tpl_drule);
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
	public static function deleteChecks($check_list, $force=false){
		$error = 'Unknown Zabbix internal error';
		$result = true;

		$druleid = $check_list['druleid'];
		$checks = $check_list['checks'];

		if(!$force){
			// check if drule is templated drule, then checks cannot be deleted
			$drule = self::get(array('druleids' => $druleid,  'extendoutput' => 1));
			$drule = reset($drule);

			if($drule['templateid'] != 0){
				self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Cannot edit templated drule : '.$drule['name']);
				return false;
			}
		}

		$chd_drules = get_drules_by_templateid($druleid);
		while($chd_drule = DBfetch($chd_drules)){
			$check_list['druleid'] = $chd_drule['druleid'];
			$result = self::deletechecks($check_list, true);
			if(!$result) return false;
		}


		$sql = 'SELECT curr.checkid
				FROM drules_checks gi, checks curr, checks src
				WHERE gi.druleid='.$druleid.
					' AND gi.checkid=curr.checkid
					AND curr.key_=src.key_
					AND '.DBcondition('src.checkid', $checks);
		$db_checks = DBselect($sql);
		$gchecks = array();
		while($curr_check = DBfetch($db_checks)){
			$gchecks[$curr_check['checkid']] = $curr_check['checkid'];
		}

		$sql = 'DELETE
				FROM drules_checks
				WHERE druleid='.$druleid.
					' AND '.DBcondition('checkid', $gchecks);
		$result = DBselect($sql);

		return $result;
	}
}
?>
