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
 * File containing CUserGroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with UserGroups.
 */
class CUserGroup extends CZBXAPI{
/**
 * Get UserGroups
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['usrgrpids'] UserGroup IDs
 * @param array $options['userids'] User IDs
 * @param boolean $options['status']
 * @param boolean $options['with_gui_access']
 * @param boolean $options['with_api_access']
 * @param boolean $options['select_users']
 * @param int $options['extendoutput']
 * @param int $options['count']
 * @param string $options['pattern']
 * @param int $options['limit'] limit selection
 * @param string $options['order']
 * @return array
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('usrgrpid', 'name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('usrgrp' => 'g.usrgrpid'),
			'from' => array('usrgrp' => 'usrgrp g'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'status'					=> null,
			'with_gui_access'			=> null,
			'with_api_access'			=> null,
// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'extendoutput'				=> null,
			'editable'					=> null,
			'output'					=> API_OUTPUT_REFER,
			'select_users'				=> null,
			'countOutput'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_users'])){
				$options['select_users'] = API_OUTPUT_EXTEND;
			}
		}

// PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN == $user_type){

		}
		else if(is_null($options['editable']) && ($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN)){
			$sql_parts['where'][] = 'g.usrgrpid IN ('.
				' SELECT uug.usrgrpid'.
				' FROM users_groups uug'.
				' WHERE uug.userid='.$USER_DETAILS['userid'].
				' )';
		}
		else if(!is_null($options['editable']) && ($USER_DETAILS['type']!=USER_TYPE_SUPER_ADMIN)){
			return array();
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// usrgrpids
		if(!is_null($options['usrgrpids'])){
			zbx_value2array($options['usrgrpids']);

			$sql_parts['where'][] = DBcondition('g.usrgrpid', $options['usrgrpids']);
		}

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['userid'] = 'ug.userid';
			}

			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = DBcondition('ug.userid', $options['userids']);
			$sql_parts['where']['gug'] = 'g.usrgrpid=ug.usrgrpid';
		}

// status
		if(!is_null($options['status'])){
			$sql_parts['where'][] = 'g.users_status='.$options['status'];
		}

// with_gui_access
		if(!is_null($options['with_gui_access'])){
			$sql_parts['where'][] = 'g.gui_access='.GROUP_GUI_ACCESS_ENABLED;
		}
// with_api_access
		if(!is_null($options['with_api_access'])){
			$sql_parts['where'][] = 'g.api_access='.GROUP_API_ACCESS_ENABLED;
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['usrgrp'] = 'g.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(g.usrgrpid) as rowscount');
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('usrgrp g', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('usrgrp g', $options, $sql_parts);
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
//-------
		$usrgrpids = array();

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
				WHERE '.DBin_node('g.usrgrpid', $nodeids).
					$sql_where.
				$sql_order;
//SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($usrgrp = DBfetch($res)){
			if($options['countOutput']){
				$result = $usrgrp['rowscount'];
			}
			else{
				$usrgrpids[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$usrgrp['usrgrpid']] = array('usrgrpid' => $usrgrp['usrgrpid']);
				}
				else{
					if(!isset($result[$usrgrp['usrgrpid']])) $result[$usrgrp['usrgrpid']]= array();

					if(!is_null($options['select_users']) && !isset($result[$usrgrp['usrgrpid']]['users'])){
						$result[$usrgrp['usrgrpid']]['users'] = array();
					}

// groupids
					if(isset($usrgrp['userid']) && is_null($options['select_users'])){
						if(!isset($result[$usrgrp['usrgrpid']]['users']))
							$result[$usrgrp['usrgrpid']]['users'] = array();

						$result[$usrgrp['usrgrpid']]['users'][] = array('userid' => $usrgrp['userid']);
						unset($usrgrp['userid']);
					}

					$result[$usrgrp['usrgrpid']] += $usrgrp;
				}
			}
		}

		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding users
		if(!is_null($options['select_users']) && str_in_array($options['select_users'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_users'],
				'usrgrpids' => $usrgrpids,
				'get_access' => ($options['select_users'] == API_OUTPUT_EXTEND)?true:null,
				'preservekeys' => 1
			);
			$users = CUser::get($obj_params);
			foreach($users as $userid => $user){
				$uusrgrps = $user['usrgrps'];
				unset($user['usrgrps']);
				foreach($uusrgrps as $num => $usrgrp){
					$result[$usrgrp['usrgrpid']]['users'][] = $user;
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
 * Get UserGroup ID by UserGroup name.
 *
 * @param array $group_data
 * @return string|boolean
 */
	public static function getObjects($group_data){
		$result = array();
		$usrgrpids = array();

		$sql = 'SELECT g.usrgrpid '.
				' FROM usrgrp g '.
				' WHERE g.name='.zbx_dbstr($group_data['name']).
					' AND '.DBin_node('g.usrgrpid', false);
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$usrgrpids[$group['usrgrpid']] = $group['usrgrpid'];
		}

		if(!empty($usrgrpids))
			$result = self::get(array('usrgrpids'=>$usrgrpids, 'extendoutput'=>1));

	return $result;
	}

	public static function exists($object){
		$options = array(
			'filter' => array('name' => $object['name']),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1,
		);
		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

		return !empty($objs);
	}

/**
 * Create UserGroups.
 *
 * @param array $usrgrps
 * @return boolean
 */
	public static function create($usrgrps){
		global $USER_DETAILS;

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::exception(ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can add User Groups');
		}

		$usrgrps = zbx_toArray($usrgrps);
		$insert = array();

		try{
			self::BeginTransaction(__METHOD__);

			foreach($usrgrps as $gnum => $usrgrp){
				$usrgrp_db_fields = array(
					'name' => null,
				);
				if(!check_db_fields($usrgrp_db_fields, $usrgrp)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect parameters used for UserGroup');
				}

				if(self::exists(array('name' => $usrgrp['name'], 'nodeids' => get_current_nodeid(false)))){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_USER_GROUP.' [ '.$usrgrp['name'].' ] '.S_ALREADY_EXISTS_SMALL);
				}
				$insert[$gnum] = $usrgrp;
			}
			$usrgrpids = DB::insert('usrgrp', $insert);


			foreach($usrgrps as $gnum => $usrgrp){
				$mass_add = array();
				if(isset($usrgrp['userids'])){
					$mass_add['userids'] = $usrgrp['userids'];
				}
				if(isset($usrgrp['rights'])){
					$mass_add['rights'] = $usrgrp['rights'];
				}
				if(!empty($mass_add)){
					$mass_add['usrgrpids'] = $usrgrpids[$gnum];
					if(!self::massAdd($mass_add))
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot add users');
				}
			}

			self::EndTransaction(true, __METHOD__);
			return array('usrgrpids' => $usrgrpids);
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
 * Update UserGroups.
 *
 * @param array $usrgrps
 * @return boolean
 */
	public static function update($usrgrps){
		global $USER_DETAILS;
		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::exception(ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can add User Groups');
		}

		$usrgrps = zbx_toArray($usrgrps);
		$usrgrpids = zbx_objectValues($usrgrps, 'usrgrpid');

		try{
			self::BeginTransaction(__METHOD__);

			foreach($usrgrps as $ugnum => $usrgrp){
				$group_db_fields = array('usrgrpid' => null);
				if(!check_db_fields($group_db_fields, $usrgrp)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect parameters used for UserGroup');
				}

				$mass_update = $usrgrp;
				$mass_update['usrgrpids'] = $usrgrp['usrgrpid'];
				unset($mass_update['usrgrpid']);
				if(!self::massUpdate($mass_update))
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Cannot update group');
			}

			self::EndTransaction(true, __METHOD__);
		return array('usrgrpids'=> $usrgrpids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function massAdd($data){
		global $USER_DETAILS;
		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::exception(ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can add User Groups');
		}

		$usrgrpids = zbx_toArray($data['usrgrpids']);
		$userids = (isset($data['userids']) && !is_null($data['userids'])) ? zbx_toArray($data['userids']) : null;
		$rights = (isset($data['rights']) && !is_null($data['rights'])) ? zbx_toArray($data['rights']) : null;

		try{
			self::BeginTransaction(__METHOD__);

			if(!is_null($userids)){
				$options = array(
					'usrgrpids' => $usrgrpids,
					'output' => API_OUTPUT_EXTEND,
				);
				$usrgrps = self::get($options);
				foreach($usrgrps as $usrgrp){
					if((($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
							|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED))
							&& uint_in_array($USER_DETAILS['userid'], $userids)){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_USER_CANNOT_CHANGE_STATUS);
					}
				}

				$linked_users = array();
				$sql = 'SELECT usrgrpid, userid'.
					' FROM users_groups'.
					' WHERE '.DBcondition('usrgrpid', $usrgrpids).
						' AND '.DBcondition('userid', $userids);
				$linked_users_db = DBselect($sql);
				while($link = DBfetch($linked_users_db)){
					if(!isset($linked_users[$link['usrgrpid']])) $linked_users[$link['usrgrpid']] = array();
					$linked_users[$link['usrgrpid']][$link['userid']] = 1;
				}

				$users_insert = array();
				foreach($usrgrpids as $usrgrpid){
					foreach($userids as $userid){
						if(!isset($linked_users[$usrgrpid][$userid])){
							$users_insert[] = array(
								'usrgrpid' => $usrgrpid,
								'userid' => $userid,
							);
						}
					}
				}
				DB::insert('users_groups', $users_insert);
			}

			if(!is_null($rights)){
				$linked_rights = array();
				$sql = 'SELECT groupid, id'.
						' FROM rights'.
						' WHERE '.DBcondition('groupid', $usrgrpids);
							' AND '.DBcondition('id', zbx_objectValues($rights, 'id'));
				$linked_rights_db = DBselect($sql);
				while($link = DBfetch($linked_rights_db)){
					if(!isset($linked_rights[$link['groupid']])) $linked_rights[$link['groupid']] = array();
					$linked_rights[$link['groupid']][$link['id']] = 1;
				}

				$rights_insert = array();
				foreach($usrgrpids as $usrgrpid){
					foreach($rights as $right){
						if(!isset($linked_users[$usrgrpid][$right['id']])){
							$rights_insert[] = array(
								'groupid' => $usrgrpid,
								'permission' => $right['permission'],
								'id' => $right['id']
							);
						}
					}
				}
				DB::insert('rights', $rights_insert);
			}

			self::EndTransaction(true, __METHOD__);
			return array('usrgrpids' => $usrgrpids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function massUpdate($data){
		global $USER_DETAILS;
		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::exception(ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can add User Groups');
		}

		$usrgrpids = zbx_toArray($data['usrgrpids']);
		$userids = (isset($data['userids']) && !is_null($data['userids'])) ? zbx_toArray($data['userids']) : null;;
		$rights = zbx_toArray($data['rights']);

		$update = array();

		try{
			self::BeginTransaction(__METHOD__);

			if(isset($data['name']) && count($usrgrpids)>1){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Multiple Name column');
			}

			foreach($usrgrpids as $ugnum => $usrgrpid){
				if(isset($data['name'])){
					$group_exists = self::get(array(
						'filter' => array('name' => $data['name']),
						'output' => API_OUTPUT_SHORTEN,
					));
					$group_exists = reset($group_exists);
					if($group_exists && ($group_exists['usrgrpid'] != $usrgrpid)){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_GROUP . ' ' . $data['name'] . ' ' . S_ALREADY_EXISTS_SMALL);
					}
				}

				if(!empty($data)){
					$update[] = array(
						'values' => $data,
						'where' => array('usrgrpid='.$usrgrpid),
					);
				}
			}
			DB::update('usrgrp', $update);

			if(!is_null($userids)){
				$usrgrps = self::get(array(
					'usrgrpids' => $usrgrpids,
					'output' => API_OUTPUT_EXTEND,
				));
				foreach($usrgrps as $usrgrp){
					if((($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
							|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED))
							&& uint_in_array($USER_DETAILS['userid'], $userids)){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_USER_CANNOT_CHANGE_STATUS);
					}
				}

				$linked_users = array();
				$sql = 'SELECT usrgrpid, userid'.
						' FROM users_groups'.
						' WHERE '.DBcondition('usrgrpid', $usrgrpids);
				$linked_users_db = DBselect($sql);
				while($link = DBfetch($linked_users_db)){
					if(!isset($linked_users[$link['usrgrpid']])) $linked_users[$link['usrgrpid']] = array();
					$linked_users[$link['usrgrpid']][$link['userid']] = 1;
				}

				$users_insert = array();
				$userids_to_unlink = array();
				foreach($usrgrpids as $usrgrpid){
					foreach($userids as $userid){
						if(!isset($linked_users[$usrgrpid][$userid])){
							$users_insert[] = array(
								'usrgrpid' => $usrgrpid,
								'userid' => $userid,
							);
						}
						unset($linked_users[$usrgrpid][$userid]);
					}
					if(isset($linked_users[$usrgrpid]) && !empty($linked_users[$usrgrpid])){
						$userids_to_unlink = array_merge($userids_to_unlink, array_keys($linked_users[$usrgrpid]));
					}
				}
				if(!empty($users_insert))
					DB::insert('users_groups', $users_insert);
				if(!empty($userids_to_unlink))
					DB::delete('users_groups', array(
						DBcondition('userid', $userids_to_unlink),
						DBcondition('usrgrpid', $usrgrpids),
					));
			}


			if(!is_null($rights)){
				$linked_rights = array();
				$sql = 'SELECT groupid, permission, id'.
						' FROM rights'.
						' WHERE '.DBcondition('groupid', $usrgrpids);
				$linked_rights_db = DBselect($sql);
				while($link = DBfetch($linked_rights_db)){
					if(!isset($linked_rights[$link['groupid']])) $linked_rights[$link['groupid']] = array();
					$linked_rights[$link['groupid']][$link['id']] = $link['permission'];
				}

				$rights_insert = array();
				$rights_update = array();
				$rights_to_unlink = array();
				foreach($usrgrpids as $usrgrpid){
					foreach($rights as $rnum => $right){
						if(!isset($linked_rights[$usrgrpid][$right['id']])){
							$rights_insert[] = array(
								'groupid' => $usrgrpid,
								'id' => $right['id'],
								'permission' => $right['permission'],
							);
						}
						else if($linked_rights[$usrgrpid][$right['id']] != $right['permission']){
							$rights_update[] = array(
								'values' => array('permission' => $right['permission']),
								'where' => array('groupid='.$usrgrpid, 'id='.$right['id']),
							);
						}
						unset($linked_rights[$usrgrpid][$right['id']]);
					}

					if(isset($linked_rights[$usrgrpid]) && !empty($linked_rights[$usrgrpid])){
						$rights_to_unlink = array_merge($rights_to_unlink, array_keys($linked_rights[$usrgrpid]));
					}
				}

				if(!empty($rights_insert)){
					DB::insert('rights', $rights_insert);
				}

				if(!empty($rights_to_unlink)){
					DB::delete('rights', array(
						DBcondition('id', $rights_to_unlink),
						DBcondition('groupid', $usrgrpids),
					));
				}

				if(!empty($rights_update)){
					DB::update('rights', $rights_update);
				}
			}


			self::EndTransaction(true, __METHOD__);
			return array('usrgrpids' => $usrgrpids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	public static function massRemove($data){

	}

/**
 * Delete UserGroups.
 *
 * @param array $usrgrpids
 * @return boolean
 */
	public static function delete($usrgrpids){
		global $USER_DETAILS;

		$usrgrpids = zbx_toArray($usrgrpids);

		if(empty($usrgrpids)) return true;

		try{
			self::BeginTransaction(__METHOD__);

			if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
				self::exception(ZBX_API_ERROR_PERMISSIONS, S_ONLY_SUPERADMIN_CAN_DELETE_USERGROUP);
			}

			//we must check, if this user group is used in one of the scripts. If so, it cannot be deleted
			$error_array = array();
			$sql = 'SELECT s.name AS script_name, ug.name AS group_name '.
					' FROM scripts s, usrgrp ug'.
					' WHERE '.
						' ug.usrgrpid = s.usrgrpid '.
						' AND '.DBcondition('s.usrgrpid', $usrgrpids);
			$res = DBselect($sql);
			while($group = DBfetch($res)){
				$error_array[] = sprintf(S_GROUP_IS_USED_IN_SCRIPT, $group['group_name'], $group['script_name']);
			}
			if(!empty($error_array))
				self::exception(ZBX_API_ERROR_PARAMETERS, $error_array);

			// check, if this user group is used in the config. If so, it cannot be deleted
			$config = select_config();
			if(!empty($config)){
				if(uint_in_array($config['alert_usrgrpid'], $usrgrpids)){
					// get group name
					$sql = 'SELECT ug.name FROM usrgrp ug WHERE ug.usrgrpid='.$config['alert_usrgrpid'];
					$group = DBfetch(DBselect($sql));

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf(S_GROUP_IS_USED_IN_CONFIGURATION, $group['name']));
				}
			}

			DB::delete('rights', array(DBcondition('groupid', $usrgrpids)));
			DB::delete('operations', array('object='.OPERATION_OBJECT_GROUP, DBcondition('objectid',$usrgrpids)));
			DB::delete('users_groups', array(DBcondition('usrgrpid',$usrgrpids)));
			DB::delete('usrgrp', array(DBcondition('usrgrpid', $usrgrpids)));

			self::EndTransaction(true, __METHOD__);
			return array('usrgrpids' => $usrgrpids);
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
