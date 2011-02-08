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
			'from' => array('usrgrp g'),
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
			'pattern'					=> '',

// OutPut
			'extendoutput'				=> null,
			'output'				=> API_OUTPUT_REFER,
			'editable'					=> null,
			'select_users'				=> null,
			'count'						=> null,
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
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid(false);

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

			$sql_parts['from']['ug'] = 'users_groups ug';
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

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(g.usrgrpid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(g.name) LIKE '.zbx_dbstr('%'.zbx_strtoupper($options['pattern']).'%');
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

		$sql = 'SELECT DISTINCT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('g.usrgrpid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($usrgrp = DBfetch($res)){
			if($options['count'])
				$result = $usrgrp;
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

		if(($options['output'] != API_OUTPUT_EXTEND) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding users
		if(!is_null($options['select_users']) && str_in_array($options['select_users'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_users'],
					'usrgrpids' => $usrgrpids,
					'get_access' => 1,
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
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * <code>
 * $group_data = array(
 * 	*string 'name' => 'UserGroup name'
 * );
 * </code>
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

/**
 * Create UserGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * <code>
 * $groups = array( array(
 * 	*string 'name' 				=> null,
 * 	string 'users_status' 		=> GROUP_STATUS_DISABLED,
 * 	string 'gui_access' 		=> 0,
 * 	string 'api_access' 		=> 0
 * ));
 * </code>
 *
 * @param array $groups multidimensional array with UserGroups data
 * @return boolean
 */
	public static function create($usrgrps){
		global $USER_DETAILS;
		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can add User Groups');
			return false;
		}

		$usrgrps = zbx_toArray($usrgrps);
		$usrgrpids = array();

		$result = false;
		$error = 'Unknown Zabbix internal error';

		self::BeginTransaction(__METHOD__);
		foreach($usrgrps as $ugnum => $usrgrp){
			$usrgrp_db_fields = array(
				'name' 				=> null,
				'users_status' 		=> GROUP_STATUS_DISABLED,
				'gui_access' 		=> 200,
				'api_access' 		=> 0
			);

			if(!check_db_fields($usrgrp_db_fields, $usrgrp)){
				$result = false;
				$error = 'Wrong fields for user group [ '.$usrgrp['name'].' ]';
				break;
			}

			$result = add_user_group($usrgrp['name'], $usrgrp['users_status'], $usrgrp['gui_access'], $usrgrp['api_access']);
			if(!$result) break;

			$usrgrpids[] = $result;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('usrgrpids'=>$usrgrpids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

/**
 * Update UserGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $groups multidimensional array with UserGroups data
 * @return boolean
 */
	public static function update($usrgrps){
		global $USER_DETAILS;
		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can edit User Groups');
			return false;
		}

		$usrgrps = zbx_toArray($usrgrps);
		$usrgrpids = array();
		$dep_usrgrps = array();
		$result = false;

//-----
		$options = array(
			'usrgrpids'=>zbx_objectValues($usrgrps, 'usrgrpid'),
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$upd_usrgrps = self::get($options);

		foreach($usrgrps as $ugnum => $usrgrp){
			if(($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED) || ($usrgrp['users_status'] == GROUP_STATUS_DISABLED)){
				$dep_usrgrps[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];
			}

			$usrgrpids[] = $usrgrp['usrgrpid'];
			//add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER_GROUP, 'User group ['.$usrgrp['name'].']');
		}

		self::BeginTransaction(__METHOD__);
		foreach($usrgrps as $ugnum => $usrgrp){
			$group_db_fields = $upd_usrgrps[$usrgrp['usrgrpid']];

			if(!check_db_fields($group_db_fields, $usrgrp)){
				$result = false;
				break;
			}

			$result = update_user_group($usrgrp['usrgrpid'], $usrgrp['name'], $usrgrp['users_status'], $usrgrp['gui_access'], $usrgrp['api_access']);
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('usrgrpids'=>$usrgrpids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Update UserGroup rights to HostGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * <code>
 * $rights = array(
 * 	*string 'groupid' => 'UserGroup ID',
 * 	array 'rights' => array( array('id' => 'HostGroup ID', 'permission' => 'permission'), ..)
 * )
 * </code>
 *
 * @param array $rights multidimensional array with rights data
 * @return boolean
 */
	public static function updateRights($rights){
		$result = false;

		$usrgrpid = $rights['usrgrpid'];

		self::BeginTransaction(__METHOD__);
		$result=DBexecute("DELETE FROM rights WHERE groupid=".$usrgrpid);

		foreach($rights['rights'] as $right){
			$id = get_dbid('rights', 'rightid');
			$result = DBexecute('INSERT INTO rights (rightid, groupid, permission, id)'.
				' VALUES ('.$id.','.$usrgrpid.','.$right['permission'].','.$right['id'].')');
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);
		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Add rights for UserGroup to HostGroups. Existing rights are updated, new ones added.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * <code>
 * $rights = array(
 * 	*string 'groupid' => 'UserGroup ID',
 * 	array 'rights' => array( array('id' => 'HostGroup ID', 'permission' => 'permission'), ..)
 * )
 * </code>
 *
 * @param array $rights multidimensional array with rights data
 * @return boolean
 */
	public static function addRights($rights){
		$result = false;

		$usrgrpid = $rights['usrgrpid'];

		self::BeginTransaction(__METHOD__);
		foreach($rights['rights'] as $right){
			$sql = 'SELECT rightid, permission FROM rights WHERE groupid='.$usrgrpid.' AND id='.$right['id'];
			$curr_perm = DBfetch(DBselect($sql));

			if($curr_perm){
				if($curr_perm['permission'] != $right['permission']){
					$sql = 'UPDATE rights SET permission='.$right['permission'].' WHERE rightid='.$curr_perm['rightid'];
				}
			}
			else{
				$id = get_dbid('rights', 'rightid');
				$sql = 'INSERT INTO rights (rightid, groupid, permission, id)'.
					' VALUES ('.$id.','.$usrgrpid.','.$right['permission'].','.$right['id'].')';
			}

			$result = DBexecute($sql);
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * Add Users to UserGroup.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * <code>
 * 	@param $usrgrps[0,...]['usrgrpids']
 * 	@param $users[0,...]['userids']
 * </code>
 *
 * @param array $data
 * @return boolean
 */
	public static function updateUsers($data){
		global $USER_DETAILS;
		$result = false;
		$errors = array();

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can add Users to Groups');
			return false;
		}

		$usrgrpids = zbx_objectValues($data['usrgrps'], 'usrgrpid');
		$userids = zbx_objectValues($data['users'], 'userid');
		$dep_usrgrps = array();


		$usrgrps = self::get(array(
			'usrgrpids' => $usrgrpids,
			'extendoutput' => 1,
			'preservekeys' => 1));
		foreach($usrgrps as $ugnum => $usrgrp){
			if(($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED) || ($usrgrp['users_status'] == GROUP_STATUS_DISABLED)){
				$dep_usrgrps[] = $usrgrp['usrgrpid'];
			}
			//add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER_GROUP, 'User group ['.$usrgrp['name'].']');
		}

		$users = CUser::get(array(
			'userids' => $userids,
			'extendoutput' => 1,
			'preservekeys' => 1));
		foreach($users as $gnum => $user){
			if((bccomp($USER_DETAILS['userid'], $user['userid']) == 0) && !empty($dep_usrgrps)){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_USER_CANNOT_CHANGE_STATUS);
				return false;
			}
			//add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, 'User ['.$user['alias'].']');
		}

// TRANSACTION {{{
		self::BeginTransaction(__METHOD__);

		$result = DBexecute('DELETE FROM users_groups WHERE '.DBcondition('usrgrpid', $usrgrpids).' OR '.DBcondition('userid', $userids));
		foreach($usrgrps as $ugnum => $usrgrp){
			foreach($users as $unaum => $user){
				$users_groups_id = get_dbid('users_groups', 'id');
				$sql = 'INSERT INTO users_groups (id, usrgrpid, userid) '.
						' VALUES ('.$users_groups_id.','.$usrgrp['usrgrpid'].','.$user['userid'].')';
				$result = DBexecute($sql);
				if(!$result){
					$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Error adding user to group');
					break 2;
				}
			}
		}

		$result = self::EndTransaction($result, __METHOD__);
// }}} TRANSACTION

		if($result){
			$options = array('usrgrpids' => $usrgrpids, 'extendoutput' => 1, 'select_users' => 1);
			return self::get($options);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Remove users from UserGroup.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param $usrgrps[0,...]['usrgrps']
 * @param $users[0,...]['users']
 *
 * @param array $data
 * @return boolean
 */
	public static function removeUsers($data){
		global $USER_DETAILS;
		$result = false;

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can remove Users from Groups');
			return false;
		}

		$usrgrpids = zbx_objectValues($data['usrgrps'], 'usrgrpsid');
		$userids = zbx_objectValues($data['users'], 'userid');

		$usrgrps = self::get(array(
			'usrgrpids' => $usrgrpids,
			'extendoutput' => 1,
			'preservekeys' => 1));
		foreach($usrgrps as $ugnum => $usrgrp){
			//add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER_GROUP, 'User group ['.$usrgrp['name'].']');
		}

		$users = self::get(array(
			'userids' => zbx_objectValues($users, 'userid'),
			'extendoutput' => 1,
			'preservekeys' => 1));
		foreach($users as $gnum => $user){
			//add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, 'User ['.$user['alias'].']');
		}

//		self::BeginTransaction(__METHOD__);
		$result = DBexecute('DELETE FROM users_groups WHERE '.DBcondition('usrgrpid', $usrgrpids).' AND '.DBcondition('userid', $userids));
//		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$options = array('usrgrpids' => $usrgrpids, 'extendoutput' => 1, 'select_users' => 1);
			return self::get($options);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Delete UserGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $groupids
 * @param array $groupids['usrgrpids']
 * @return boolean
 */
	public static function delete($usrgrps){
		global $USER_DETAILS;
		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Only Super Admins can delete User Groups');
			return false;
		}

		$usrgrps = zbx_toArray($usrgrps);
		$usrgrpids = array();
		$result = false;

//-----
		$options = array(
			'usrgrpids'=>zbx_objectValues($usrgrps, 'usrgrpid'),
			'extendoutput'=>1,
			'preservekeys'=>1
		);
		$del_usrgrps = self::get($options);
		foreach($usrgrps as $gnum => $usrgrp){
			$usrgrpids[] = $usrgrp['usrgrpid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, 'User group ['.$usrgrp['name'].']');
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($usrgrpids)){
			foreach($usrgrpids as $groupid){
				$result = delete_user_group($groupid);
				if(!$result) break;
			}
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ usrgrpids ]');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('usrgrpids'=>$usrgrpids);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>
