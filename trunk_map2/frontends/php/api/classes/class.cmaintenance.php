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
 * @param boolean $options['templated_items']
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
			'extendoutput'			=> null,
			'select_hosts'			=> null,
			'count'					=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null,
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable']?PERM_READ_WRITE:PERM_READ_ONLY;

			$sql_parts['from']['mg'] = 'maintenances_groups mg';
			$sql_parts['from']['mh'] = 'maintenances_hosts mh';
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';

			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = '((hg.hostid=mh.hostid '.
									' AND m.maintenanceid=mh.maintenanceid '.
									' AND NOT EXISTS( '.
										' SELECT hgg.groupid '.
										' FROM hosts_groups hgg, rights rr, users_groups gg '.
										' WHERE hgg.hostid=hg.hostid '.
											' AND rr.id=hgg.groupid '.
											' AND rr.groupid=gg.usrgrpid '.
											' AND gg.userid='.$userid.
											' AND rr.permission<'.$permission.'))'.
								' AND '.
									'(hg.groupid=mg.groupid '.
									' AND m.maintenanceid=mg.maintenanceid '.
									' AND NOT EXISTS( '.
										' SELECT hgg.groupid '.
										' FROM hosts_groups hgg, rights rr, users_groups gg '.
										' WHERE hgg.groupid=hg.groupid '.
											' AND rr.id=hgg.groupid '.
											' AND rr.groupid=gg.usrgrpid '.
											' AND gg.userid='.$userid.
											' AND rr.permission<'.$permission.')))';
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['groupids'] = 'mg.groupid';
			}
			$sql_parts['from']['mg'] = 'maintenances_groups mg';
			$sql_parts['where']['mmg'] = 'm.maintenanceid=mg.maintenanceid';
			$sql_parts['where'][] = DBcondition('mg.groupid', $options['groupids']);
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['hostid'] = 'mh.hostid';
			}

			$sql_parts['from']['mg'] = 'maintenances_hosts mh';
			$sql_parts['where']['mmh'] = 'm.maintenanceid=mh.maintenanceid';
			$sql_parts['where'][] = DBcondition('mh.hostid', $options['hostids']);
		}

// maintenanceids
		if(!is_null($options['maintenanceids'])){
			zbx_value2array($options['maintenanceids']);

			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['maintenanceid'] = 'm.maintenanceid';
			}

			$sql_parts['where'][] = DBcondition('m.maintenanceid', $options['maintenanceids']);
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['maintenance'] = 'm.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(m.maintenanceid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(m.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
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

		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('m.maintenanceid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($maintenance = DBfetch($res)){
			if($options['count'])
				$result = $maintenance;
			else{
				$maintenanceids[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];

				if(is_null($options['extendoutput'])){
					$result[$maintenance['maintenanceid']] = array('maintenanceid' => $maintenance['maintenanceid']);
				}
				else{
					if(!isset($result[$maintenance['maintenanceid']]))
						$result[$maintenance['maintenanceid']]= array();

					// groupids
					if(isset($maintenance['groupid'])){
						if(!isset($result[$maintenance['maintenanceid']]['groupids']))
							$result[$maintenance['maintenanceid']]['groupids'] = array();

						$result[$maintenance['maintenanceid']]['groupids'][$maintenance['groupid']] = $maintenance['groupid'];
						unset($maintenance['groupid']);
					}

					// hostids
					if(isset($maintenance['hostid'])){
						if(!isset($result[$maintenance['maintenanceid']]['hostids']))
							$result[$maintenance['maintenanceid']]['hostids'] = array();

						$result[$maintenance['maintenanceid']]['hostids'][$maintenance['hostid']] = $maintenance['hostid'];
						unset($maintenance['hostid']);
					}

					$result[$maintenance['maintenanceid']] += $maintenance;
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
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
	public static function add($maintenances){
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
			$new_maintenances = self::get(array('maintenanceids'=>$maintenanceids, 'extendoutput'=>1));
			return $new_maintenances;
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

		$upd_maintenances = self::get(array('maintenanceids'=>zbx_objectValues($maintenances, 'maintenanceid'), 
											'editable'=>1, 
											'extendoutput'=>1, 
											'preservekeys'=>1));
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
			$upd_maintenances = self::get(array('maintenanceids'=>$maintenanceids, 'extendoutput'=>1));
			return $upd_maintenances;
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

		$del_maintenances = self::get(array('maintenanceids'=>zbx_objectValues($maintenances, 'maintenanceid'), 
											'editable'=>1, 
											'extendoutput'=>1, 
											'preservekeys'=>1));
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
			return zbx_cleanHashes($del_maintenances);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>