<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
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

		$sql_parts = array(
			'select' => array('maintenance' => 'm.maintenanceid'),
			'from' => array('maintenances m'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'				=> null,
			'groupids'				=> null,
			'hostids'				=> null,
			'maintenanceids'		=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
// filter
			'pattern'				=> '',

// OutPut
			'output'				=> API_OUTPUT_REFER,
			'extendoutput'			=> null,
			'select_groups'			=> null,
			'select_hosts'			=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null,
		);

		$options = zbx_array_merge($def_options, $options);

		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_groups'])){
				$options['select_groups'] = API_OUTPUT_EXTEND;
			}
			if(!is_null($options['select_hosts'])){
				$options['select_hosts'] = API_OUTPUT_EXTEND;
			}
		}
// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
			$maintenanceids = array();
			if(!is_null($options['groupids']) || !is_null($options['hostids'])){

				if(!is_null($options['groupids'])){
					zbx_value2array($options['groupids']);
					$sql = ' SELECT mmg.maintenanceid '.
						' FROM maintenances_groups mmg '.
						' WHERE '.DBcondition('mmg.groupid', $options['groupids']);

					$res = DBselect($sql);
					while($miantenace = DBfetch($res)){
						$maintenanceids[] = $miantenace['maintenanceid'];
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
				while($miantenace = DBfetch($res)){
					$maintenanceids[] = $miantenace['maintenanceid'];
				}

				$sql_parts['where'][] = DBcondition('m.maintenanceid',$maintenanceids);
			}
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$maintenanceids = array();
			$sql = ' SELECT mm.maintenanceid '.
					' FROM maintenances mm, maintenances_groups mmg, rights r,users_groups ug '.
					' WHERE r.groupid=ug.usrgrpid  '.
						' AND ug.userid='.$userid.
						' AND r.permission>='.$permission.
						' AND mm.maintenanceid=mmg.maintenanceid  '.
						' AND NOT EXISTS( '.
								' SELECT rr.id '.
								' FROM rights rr, users_groups gg  '.
								' WHERE rr.id=mmg.groupid  '.
									' AND rr.groupid=gg.usrgrpid  '.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
			if(!is_null($options['groupids'])){
				zbx_value2array($options['groupids']);
				$sql.=' AND '.DBcondition('mmg.groupid', $options['groupids']);
			}

			$res = DBselect($sql);
			while($miantenace = DBfetch($res)){
				$maintenanceids[] = $miantenace['maintenanceid'];
			}

			$sql = ' SELECT mm.maintenanceid '.
					' FROM maintenances mm, maintenances_hosts mmh, rights r,users_groups ug, hosts_groups hg '.
					' WHERE r.groupid=ug.usrgrpid  '.
						' AND ug.userid='.$userid.
						' AND r.permission>='.$permission.
						' AND mm.maintenanceid=mmh.maintenanceid  '.
						' AND hg.hostid=mmh.hostid '.
						' AND r.id=hg.groupid  '.
						' AND NOT EXISTS( '.
								' SELECT rr.id '.
								 ' FROM rights rr, users_groups gg  '.
								 ' WHERE rr.id=hg.groupid  '.
									' AND rr.groupid=gg.usrgrpid  '.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
			if(!is_null($options['groupids'])){
				zbx_value2array($options['groupids']);
				$sql.=' AND '.DBcondition('hg.groupid', $options['groupids']);
			}

			if(!is_null($options['hostids'])){
				zbx_value2array($options['hostids']);
				$sql.=' AND '.DBcondition('hg.hostid', $options['hostids']);
			}

			$res = DBselect($sql);
			while($miantenace = DBfetch($res)){
				$maintenanceids[] = $miantenace['maintenanceid'];
			}

			$sql_parts['where'][] = DBcondition('m.maintenanceid',$maintenanceids);
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if(!is_null($options['groupids'])){
			$options['select_groups'] = 1;
		}

// hostids
		if(!is_null($options['hostids'])){
			$options['select_hosts'] = 1;
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

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(m.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
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

		$sql = 'SELECT DISTINCT '.$sql_select.
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

// groupids
					if(isset($maintenance['groupid']) && is_null($options['select_groups'])){
						if(!isset($result[$maintenance['maintenanceid']]['groups']))
							$result[$maintenance['maintenanceid']]['groups'] = array();

						$result[$maintenance['maintenanceid']]['groups'][] = array('groupid' => $maintenance['groupid']);
						unset($maintenance['groupid']);
					}

// hostids
					if(isset($maintenance['hostid']) && is_null($options['select_hosts'])){
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

// TODO:
		if(!is_null($options['select_groups'])){

		}

		if(!is_null($options['select_hosts'])){

		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Maintenance ID by host.name and item.key
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $maintenance
 * @param array $maintenance['name']
 * @param array $maintenance['hostid']
 * @return int|boolean
 */
	public static function getObjects($maintenance){
		$result = array();
		$maintenanceids = array();

		$sql = 'SELECT m.maintenanceid '.
				' FROM maintenances m '.
				' WHERE m.name='.$maintenance['name'];
		$res = DBselect($sql);
		while($maintenance = DBfetch($res)){
			$maintenanceids[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];
		}

		if(!empty($maintenanceids))
			$result = self::get(array('maintenanceids'=>$maintenanceids, 'extendoutput'=>1));

	return $result;
	}

/**
 * Add maintenances
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $maintenances
 * @param array $maintenance['name']
 * @param array $maintenance['hostid']
 * @return boolean
 */
	public static function create($maintenances){
		$maintenances = zbx_toArray($maintenances);
		$maintenanceids = array();
		$result = false;
//------

		self::BeginTransaction(__METHOD__);
		foreach($maintenances as $num => $maintenance){
			$result = add_maintenance($maintenance);
			if(!$result) break;

			$maintenanceids[] = $result;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('maintenanceids'=>$maintenanceids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Update maintenances
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $maintenances
 * @param array $maintenance['name']
 * @param array $maintenance['hostid']
 * @return boolean
 */
	public static function update($maintenances){
		$maintenances = zbx_toArray($maintenances);
		$maintenanceids = array();
		$result = false;
//------

		$options = array(
			'maintenanceids'=>zbx_objectValues($maintenances, 'maintenanceid'),
			'editable'=>1,
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$upd_maintenances = self::get($options);
		foreach($maintenances as $snum => $maintenance){
			if(!isset($upd_maintenances[$maintenance['maintenanceid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$maintenanceids[] = $maintenance['maintenanceid'];
			//add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MAINTENANCE, 'Maintenance ['.$maintenance['name'].']');
		}

		self::BeginTransaction(__METHOD__);
		foreach($maintenances as $num => $maintenance){
			$result = update_maintenance($maintenance['maintenanceid'], $maintenance);
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('maintenanceids'=>$maintenanceids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Delete maintenances
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $maintenanceids
 * @param _array $maintenanceids['maintenanceids']
 * @return boolean
 */
	public static function delete($maintenances){
		$maintenances = zbx_toArray($maintenances);
		$maintenanceids = array();
		$result = false;
//------

		$options = array(
			'maintenanceids'=>zbx_objectValues($maintenances, 'maintenanceid'),
			'editable'=>1,
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$del_maintenances = self::get($options);
		foreach($maintenances as $snum => $maintenance){
			if(!isset($del_maintenances[$maintenance['maintenanceid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$maintenanceids[] = $maintenance['maintenanceid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MAINTENANCE, 'Maintenance ['.$maintenance['name'].']');
		}

		if(!empty($maintenanceids)){
			$result = delete_maintenance($maintenanceids);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ maintenanceids ]');
			$result = false;
		}

		if($result){
			return array('maintenanceids'=>$maintenanceids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>
