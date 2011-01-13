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
 * File containing CMaintenance class for API.
 * @package API
 */
/**
 * Class containing methods for operations with maintenances
 *
 */
class CMaintenance extends CZBXAPI{
/**
 * Get maintenances data
 *
 * @param array $options
 * @param array $options['itemids']
 * @param array $options['hostids']
 * @param array $options['groupids']
 * @param array $options['triggerids']
 * @param array $options['maintenanceids']
 * @param boolean $options['status']
 * @param boolean $options['editable']
 * @param boolean $options['count']
 * @param string $options['pattern']
 * @param int $options['limit']
 * @param string $options['order']
 * @return array|int item data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('maintenanceid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('maintenance' => 'm.maintenanceid'),
			'from' => array('maintenances' => 'maintenances m'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'maintenanceids'		=> null,
			'editable'				=> null,
			'nopermissions'			=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'			=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'filter'				=> null,

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'selectGroups'			=> null,
			'selectHosts'			=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null,
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK

		$maintenanceids = array();
		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
			if(!is_null($options['groupids']) || !is_null($options['hostids'])){

				if(!is_null($options['groupids'])){
					zbx_value2array($options['groupids']);
					$sql = ' SELECT mmg.maintenanceid '.
						' FROM maintenances_groups mmg '.
						' WHERE '.DBcondition('mmg.groupid', $options['groupids']);


					$res = DBselect($sql);
					while($maintenance = DBfetch($res)){
						$maintenanceids[] = $maintenance['maintenanceid'];
					}
				}


				$sql = ' SELECT mmh.maintenanceid '.
					' FROM maintenances_hosts mmh, hosts_groups hg '.
					' WHERE hg.hostid=mmh.hostid ';

				if(!is_null($options['groupids'])){
					zbx_value2array($options['groupids']);
					$sql.=' AND '.DBcondition('hg.groupid', $options['groupids']);
				}

				if(!is_null($options['hostids'])){
					zbx_value2array($options['hostids']);
					$sql.=' AND '.DBcondition('hg.hostid', $options['hostids']);
				}
				$res = DBselect($sql);
				while($maintenance = DBfetch($res)){
					$maintenanceids[] = $maintenance['maintenanceid'];
				}

				$sql_parts['where'][] = DBcondition('m.maintenanceid',$maintenanceids);
			}
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;



			$sql =
				'SELECT DISTINCT m.maintenanceid'.
					' FROM maintenances m'.
					' WHERE'.
					 ' NOT EXISTS ('.
					  ' SELECT mh3.maintenanceid'.
					  ' FROM maintenances_hosts mh3, rights r3, users_groups ug3, hosts_groups hg3'.
					  ' WHERE mh3.maintenanceid = m.maintenanceid'.
					   ' AND r3.groupid = ug3.usrgrpid'.
					   ' AND hg3.hostid = mh3.hostid'.
					   ' AND r3.id = hg3.groupid'.
					   ' AND ug3.userid = '.$userid.
					   ' AND r3.permission < '.$permission.
					 ' ) '.
					 ' AND NOT EXISTS ( '.
					  ' SELECT mh4.maintenanceid '.
					  ' FROM maintenances_hosts mh4 '.
					  ' WHERE mh4.maintenanceid = m.maintenanceid '.
					   ' AND NOT EXISTS( '.
						' SELECT r5.id '.
						' FROM rights r5, users_groups ug5, hosts_groups hg5 '.
						' WHERE r5.groupid = ug5.usrgrpid '.
						 ' AND hg5.hostid = mh4.hostid '.
						 ' AND r5.id = hg5.groupid '.
						 ' AND ug5.userid = '.$userid.
					  ' ) '.
					 ' ) '.
					 ' AND NOT EXISTS ( '.
					  ' SELECT mg2.maintenanceid '.
					  ' FROM maintenances_groups mg2, rights r3, users_groups ug3 '.
					  ' WHERE mg2.maintenanceid = m.maintenanceid '.
					   ' AND r3.groupid = ug3.usrgrpid '.
					   ' AND r3.id = mg2.groupid '.
					   ' AND ug3.userid = '.$userid.
					   ' AND r3.permission < '.$permission.
					 ' ) '.

					 ' AND NOT EXISTS ( '.
					  ' SELECT mg3.maintenanceid '.
					  ' FROM maintenances_groups mg3 '.
					  ' WHERE mg3.maintenanceid = m.maintenanceid '.
					   ' AND NOT EXISTS( '.
						' SELECT r5.id '.
						' FROM rights r5, users_groups ug5, hosts_groups hg5 '.
						' WHERE r5.groupid = ug5.usrgrpid '.
						 ' AND r5.id = mg3.groupid '.
						 ' AND ug5.userid = '.$userid.
					  ' ) '.
					 ' ) ';

			if(!is_null($options['groupids'])){
				zbx_value2array($options['groupids']);
				//filtering using groups attached to maintenence
				$sql .= 'AND ( '.
						' EXISTS ('.
							' SELECT mgf.maintenanceid '.
							' FROM maintenances_groups mgf '.
							' WHERE mgf.maintenanceid = m.maintenanceid '.
								' AND '.DBcondition('mgf.groupid', $options['groupids']).
						' ) ';
				//filtering by hostgroups of hosts attached to maintenance
				$sql .= 'OR EXISTS ('.
							' SELECT mh.maintenanceid '.
							' FROM maintenances_hosts mh, hosts_groups hg '.
							' WHERE mh.maintenanceid = m.maintenanceid '.
								' AND hg.hostid = mh.hostid '.
								' AND '.DBcondition('hg.groupid', $options['groupids']).
							' ) '.
						' ) ';

			}

			if(!is_null($options['hostids'])){
				zbx_value2array($options['hostids']);
				$sql .= 'AND EXISTS ('.
							' SELECT mh.maintenanceid '.
							' FROM maintenances_hosts mh'.
							' WHERE mh.maintenanceid = m.maintenanceid '.
								' AND '.DBcondition('mh.hostid', $options['hostids']).
							' ) ';
			}

			$res = DBselect($sql);
			while($miantenace = DBfetch($res)){
				$maintenanceids[] = $miantenace['maintenanceid'];
			}

			$sql_parts['where'][] = DBcondition('m.maintenanceid',$maintenanceids);
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			$options['selectGroups'] = 1;
		}

// hostids
		if(!is_null($options['hostids'])){
			$options['selectHosts'] = 1;
		}

// maintenanceids
		if(!is_null($options['maintenanceids'])){
			zbx_value2array($options['maintenanceids']);

			$sql_parts['where'][] = DBcondition('m.maintenanceid', $options['maintenanceids']);
		}

// output
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['maintenance'] = 'm.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT m.maintenanceid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('maintenances m', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('maintenances m', $options, $sql_parts);
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'm.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('m.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('m.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'm.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//----------

		$maintenanceids = array();

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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('m.maintenanceid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($maintenance = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $maintenance;
				else
					$result = $maintenance['rowscount'];
			}
			else{
				$maintenanceids[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$maintenance['maintenanceid']] = array('maintenanceid' => $maintenance['maintenanceid']);
				}
				else{
					if(!isset($result[$maintenance['maintenanceid']]))
						$result[$maintenance['maintenanceid']]= array();

					if(!is_null($options['selectGroups']) && !isset($result[$maintenance['maintenanceid']]['groups'])){
						$result[$maintenance['maintenanceid']]['groups'] = array();
					}
					if(!is_null($options['selectHosts']) && !isset($result[$maintenance['maintenanceid']]['hosts'])){
						$result[$maintenance['maintenanceid']]['hosts'] = array();
					}
// groupids
					if(isset($maintenance['groupid']) && is_null($options['selectGroups'])){
						if(!isset($result[$maintenance['maintenanceid']]['groups']))
							$result[$maintenance['maintenanceid']]['groups'] = array();

						$result[$maintenance['maintenanceid']]['groups'][] = array('groupid' => $maintenance['groupid']);
						unset($maintenance['groupid']);
					}

// hostids
					if(isset($maintenance['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$maintenance['maintenanceid']]['hosts']))
							$result[$maintenance['maintenanceid']]['hosts'] = array();

						$result[$maintenance['maintenanceid']]['hosts'][] = array('hostid' => $maintenance['hostid']);
						unset($maintenance['hostid']);
					}

					$result[$maintenance['maintenanceid']] += $maintenance;
				}
			}
		}


Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// selectGroups
		if(is_array($options['selectGroups']) || str_in_array($options['selectGroups'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'maintenanceids' => $maintenanceids,
				'preservekeys' => 1,
				'output' => $options['selectGroups'],
			);
			$groups = CHostGroup::get($obj_params);

			foreach($groups as $groupid => $group){
				$gmaintenances = $group['maintenances'];
				unset($group['maintenances']);
				foreach($gmaintenances as $num => $maintenance){
					$result[$maintenance['maintenanceid']]['groups'][] = $group;
				}
			}
		}


// selectHosts
		if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'maintenanceids' => $maintenanceids,
				'preservekeys' => 1,
				'output' => $options['selectHosts'],
			);
			$hosts = CHost::get($obj_params);

			foreach($hosts as $hostid => $host){
				$hmaintenances = $host['maintenances'];
				unset($host['maintenances']);
				foreach($hmaintenances as $num => $maintenance){
					$result[$maintenance['maintenanceid']]['hosts'][] = $host;
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


	/**
	 * Determine, whether an object already exists
	 *
	 * @param array $object
	 * @return bool
	 */
	public static function exists($object){
		$keyFields = array(array('maintenanceid', 'name'));

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);

		$objs = self::get($options);

	return !empty($objs);
	}


/**
 * Add maintenances
 *
 * @param array $maintenances
 * @return boolean
 */
	public static function create($maintenances){
		global $USER_DETAILS;
		$maintenances = zbx_toArray($maintenances);

		try{
			self::BeginTransaction(__METHOD__);

			if($USER_DETAILS['type'] == USER_TYPE_ZABBIX_USER){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			$hostids = array();
			$groupids = array();
			foreach($maintenances as $maintenance){
				$hostids = array_merge($hostids, $maintenance['hostids']);
				$groupids = array_merge($groupids, $maintenance['groupids']);
			}

			if(empty($hostids) && empty($groupids)){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_GROUP_OR_HOST_NEEDED);
			}
// hosts permissions
			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_hosts = CHost::get($options);
			foreach($hostids as $hostid){
				if(!isset($upd_hosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
// groups permissions
			$options = array(
				'groupids' => $groupids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_groups = CHostGroup::get($options);
			foreach($groupids as $groupid){
				if(!isset($upd_groups[$groupid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
//---

			$tid = 0;
			$insert = array();
			$timeperiods = array();
			$insert_timeperiods = array();
			foreach($maintenances as $mnum => $maintenance){
				$db_fields = array(
					'name' => null,
					'active_since'=> time(),
					'active_till' => time()+86400,
				);
				if(!check_db_fields($db_fields, $maintenance)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect parameters used for Maintenance');
				}
				//checkig wheter a maintence with this name already exists
				if(self::exists(array('name' => $maintenance['name']))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_MAINTENANCE.' [ '.$maintenance['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}

				$insert[$mnum] = $maintenance;

				foreach($maintenance['timeperiods'] as $timeperiod){
					$db_fields = array(
						'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'period' =>	3600,
						'start_date' =>	time()
					);
					check_db_fields($db_fields, $timeperiod);

					$tid++;
					$insert_timeperiods[$tid] = $timeperiod;
					$timeperiods[$tid] = $mnum;
				}
			}
			$maintenanceids = DB::insert('maintenances', $insert);
			$timeperiodids = DB::insert('timeperiods', $insert_timeperiods);


			$insert_windows = array();
			foreach($timeperiods as $tid => $mnum){
				$insert_windows[] = array(
					'timeperiodid' => $timeperiodids[$tid],
					'maintenanceid' => $maintenanceids[$mnum],
				);
			}
			DB::insert('maintenances_windows', $insert_windows);


			$insert_hosts = array();
			$insert_groups = array();
			foreach($maintenances as $mnum => $maintenance){
				foreach($maintenance['hostids'] as $hostid){
					$insert_hosts[] = array(
						'hostid' => $hostid,
						'maintenanceid' => $maintenanceids[$mnum],
					);
				}
				foreach($maintenance['groupids'] as $groupid){
					$insert_groups[] = array(
						'groupid' => $groupid,
						'maintenanceid' => $maintenanceids[$mnum],
					);
				}
			}
			DB::insert('maintenances_hosts', $insert_hosts);
			DB::insert('maintenances_groups', $insert_groups);


			self::EndTransaction(true, __METHOD__);
			return array('maintenanceids'=>$maintenanceids);
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
 * Update maintenances
 *
 * @param _array $maintenances
 * @return boolean
 */
	public static function update($maintenances){
		global $USER_DETAILS;
		$maintenances = zbx_toArray($maintenances);
		$maintenanceids = zbx_objectValues($maintenances, 'maintenanceid');

		try{
			self::BeginTransaction(__METHOD__);

			if($USER_DETAILS['type'] == USER_TYPE_ZABBIX_USER){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

// Maintenance permissions
			$hostids = array();
			$groupids = array();
			$options = array(
				'maintenanceids' => zbx_objectValues($maintenances, 'maintenanceid'),
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_maintenances = self::get($options);
			foreach($maintenances as $maintenance){
				if(!isset($upd_maintenances[$maintenance['maintenanceid']])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}

				//checkig wheter a maintence with this name and different already exists
				//first, getting all maintences with the same name as this
				$options = array(
					'filter' => array(
									'name'=>$maintenance['name']
								)
				);
				$recieved_maintenaces = CMaintenance::get($options);
				//now going though a result, to find records with different id, then our object
				foreach($recieved_maintenaces as $r_maintenace){
					if ($r_maintenace['maintenanceid'] != $maintenance['maintenanceid']) {
						//error! Maintenance with this name already exists
						self::exception(ZBX_API_ERROR_PARAMETERS, S_MAINTENANCE.' [ '.$maintenance['name'].' ] '.S_ALREADY_EXISTS_SMALL);
					}
				}

				$hostids = array_merge($hostids, $maintenance['hostids']);
				$groupids = array_merge($groupids, $maintenance['groupids']);
			}

			if(empty($hostids) && empty($groupids)){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_GROUP_OR_HOST_NEEDED);
			}
// hosts permissions
			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_hosts = CHost::get($options);
			foreach($hostids as $hostid){
				if(!isset($upd_hosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
// groups permissions
			$options = array(
				'groupids' => $groupids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1,
			);
			$upd_groups = CHostGroup::get($options);
			foreach($groupids as $groupid){
				if(!isset($upd_groups[$groupid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}


			$timeperiodids = array();
			$sql = 'SELECT DISTINCT tp.timeperiodid '.
			' FROM timeperiods tp, maintenances_windows mw '.
			' WHERE '.DBcondition('mw.maintenanceid',$maintenanceids).
				' AND tp.timeperiodid=mw.timeperiodid ';
			$db_timeperiods = DBselect($sql);
			while($timeperiod = DBfetch($db_timeperiods)){
				$timeperiodids[] = $timeperiod['timeperiodid'];
			}

			DB::delete('timeperiods', array('timeperiodid'=>$timeperiodids));
			DB::delete('maintenances_windows', array('maintenanceid'=>$maintenanceids));


			$tid = 0;
			$update = array();
			$timeperiods = array();
			$insert_timeperiods = array();
			foreach($maintenances as $mnum => $maintenance){
				$db_fields = array(
					'maintenanceid' => null,
				);
				if(!check_db_fields($db_fields, $maintenance)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect parameters used for Maintenance');
				}

				$update[$mnum] = array(
					'values' => $maintenance,
					'where' => array('maintenanceid='.$maintenance['maintenanceid']),
				);

				foreach($maintenance['timeperiods'] as $timeperiod){
					$tid++;
					$insert_timeperiods[$tid] = $timeperiod;
					$timeperiods[$tid] = $mnum;
				}
			}
			DB::update('maintenances', $update);
			$timeperiodids = DB::insert('timeperiods', $insert_timeperiods);


			$insert_windows = array();
			foreach($timeperiods as $tid => $mnum){
				$insert_windows[] = array(
					'timeperiodid' => $timeperiodids[$tid],
					'maintenanceid' => $maintenances[$mnum]['maintenanceid'],
				);
			}
			DB::insert('maintenances_windows', $insert_windows);


			DB::delete('maintenances_hosts', array('maintenanceid'=>$maintenanceids));
			DB::delete('maintenances_groups', array('maintenanceid'=>$maintenanceids));

			$insert_hosts = array();
			$insert_groups = array();
			foreach($maintenances as $mnum => $maintenance){
				foreach($maintenance['hostids'] as $hostid){
					$insert_hosts[] = array(
						'hostid' => $hostid,
						'maintenanceid' => $maintenance['maintenanceid'],
					);
				}
				foreach($maintenance['groupids'] as $groupid){
					$insert_groups[] = array(
						'groupid' => $groupid,
						'maintenanceid' => $maintenance['maintenanceid'],
					);
				}
			}
			DB::insert('maintenances_hosts', $insert_hosts);
			DB::insert('maintenances_groups', $insert_groups);


			self::EndTransaction(true, __METHOD__);
			return array('maintenanceids'=> $maintenanceids);
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
 * Delete maintenances
 *
 * @param _array $maintenanceids
 * @param _array $maintenanceids['maintenanceids']
 * @return boolean
 */
	public static function delete($maintenanceids){
		global $USER_DETAILS;
		$maintenanceids = zbx_toArray($maintenanceids);

		try{
			self::BeginTransaction(__METHOD__);

			if($USER_DETAILS['type'] == USER_TYPE_ZABBIX_USER){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
			}

			$options = array(
				'maintenanceids' => $maintenanceids,
				'editable' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => 1
			);
			$del_maintenances = self::get($options);

			foreach($maintenanceids as $snum => $maintenanceid){
				if(!isset($del_maintenances[$maintenanceid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$timeperiodids = array();
			$sql = 'SELECT DISTINCT tp.timeperiodid '.
			' FROM timeperiods tp, maintenances_windows mw '.
			' WHERE '.DBcondition('mw.maintenanceid',$maintenanceids).
				' AND tp.timeperiodid=mw.timeperiodid ';
			$db_timeperiods = DBselect($sql);
			while($timeperiod = DBfetch($db_timeperiods)){
				$timeperiodids[] = $timeperiod['timeperiodid'];
			}

			$mid_cond = array('maintenanceid'=>$maintenanceids);
			DB::delete('timeperiods', array('timeperiodid'=>$timeperiodids));
			DB::delete('maintenances_windows', $mid_cond);
			DB::delete('maintenances_hosts', $mid_cond);
			DB::delete('maintenances_groups', $mid_cond);
			DB::delete('maintenances', $mid_cond);

			self::EndTransaction(true, __METHOD__);
			return array('maintenanceids'=> $maintenanceids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}




}
?>
