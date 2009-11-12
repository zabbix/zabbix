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
			'select_users'				=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

// PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN == $user_type){

		}
		else if($options['editable']){
			return $result();
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// usrgrpids
		if(!is_null($options['usrgrpids'])){
			zbx_value2array($options['usrgrpids']);

			$sql_parts['where'][] = DBcondition('g.usrgrpid', $options['usrgrpids']);
		}

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);

			if(!is_null($options['extendoutput'])){
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
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['usrgrp'] = 'g.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(g.usrgrpid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(g.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
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

		$sql = 'SELECT '.$sql_select.'
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

				if(is_null($options['extendoutput'])){
					$result[$usrgrp['usrgrpid']] = $usrgrp['usrgrpid'];
				}
				else{
					if(!isset($result[$usrgrp['usrgrpid']])) $result[$usrgrp['usrgrpid']]= array();

					if($options['select_users'] && !isset($result[$usrgrp['usrgrpid']]['userids'])){
						$result[$usrgrp['usrgrpid']]['userids'] = array();
						$result[$usrgrp['usrgrpid']]['users'] = array();
					}

					// groupids
					if(isset($usrgrp['userid'])){
						if(!isset($result[$usrgrp['usrgrpid']]['userids']))
							$result[$usrgrp['usrgrpid']]['userids'] = array();

						$result[$usrgrp['usrgrpid']]['userids'][$usrgrp['userid']] = $usrgrp['userid'];
						unset($usrgrp['userid']);
					}

					$result[$usrgrp['usrgrpid']] += $usrgrp;
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding users
		if($options['select_users']){
			$obj_params = array('extendoutput' => 1, 'usrgrpids' => $usrgrpids, 'preservekeys' => 1);
			$users = CUser::get($obj_params);
			foreach($users as $userid => $user){
				foreach($user['usrgrpids'] as $num => $usrgrpid){
					$result[$usrgrpid]['userids'][$userid] = $userid;
					$result[$usrgrpid]['users'][$userid] = $user;
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
 * Gets all UserGroup data from DB by usrgrpid.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $group_data
 * @param array $group_data['usrgrpid'] UserGroup ID
 * @return array|boolean user data as array or false if error
 */
	public static function getById($group_data){

		$group = DBfetch(DBselect('SELECT * FROM usrgrp WHERE usrgrpid='.$group_data['usrgrpid']));

		if($group)
			return $group;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'UserGroup with id: '.$group_data['usrgrpid'].' doesn\'t exists.');
			return false;
		}
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
	public static function getId($group_data){
		$result = false;

		$sql = 'SELECT g.usrgrpid '.
				' FROM usrgrp g '.
				' WHERE g.name='.zbx_dbstr($group_data['name']).
					' AND '.DBin_node('g.usrgrpid', false);

		if($group = DBfetch(DBselect($sql)))
			$result = $group['usrgrpid'];
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Host with name: "'.$user_data['alias'].'" doesn\'t exists.');
		}

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
	public static function add($groups){
		$error = 'Unknown ZABBIX internal error';
		$result = false;

		self::BeginTransaction(__METHOD__);

		foreach($groups as $group){

			$group_db_fields = array(
				'name' 				=> null,
				'users_status' 		=> GROUP_STATUS_DISABLED,
				'gui_access' 		=> 200,
				'api_access' 		=> 0
			);

			if(!check_db_fields($group_db_fields, $group)){
				$result = false;
				$error = 'Wrong fields for user group [ '.$group['name'].' ]';
				break;
			}

			$result = add_user_group($group['name'], $group['users_status'], $group['gui_access'], $group['api_access']);
			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return true;
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
	public static function update($groups){
		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($groups as $group){
			$group_db_fields = self::getById(array('usrgrpid' => $group['usrgrpid']));

			if(!$group_db_fields) {
				$result = false;
				break;
			}

			if(!check_db_fields($group_db_fields, $group)){
				$result = false;
				break;
			}
			$result = update_user_group($group['usrgrpid'], $group['name'],$group['users_status'], $group['gui_access'],$group['api_access']);
			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return true;
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
 * $data = array(
 * 	*string 'usrgrpid' => 'UserGroup ID',
 * 	*array 'userids' => ('User ID', 'User ID', ...)
 * )
 * </code>
 *
 * @param array $data
 * @return boolean
 */
	public static function addUsers($data){
		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($data['userids'] as $userid){
			$result = add_user_to_group($userid, $data['usrgrpid']);
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
 * Remove users from UserGroup.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * <code>
 * $data = array(
 * 	*string 'usrgrpid' => 'UserGroup ID',
 * 	*array 'userids' => ('User ID', 'User ID', ...)
 * )
 * </code>
 *
 * @param array $data
 * @return boolean
 */
	public static function removeUsers($data){
		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($data['userids'] as $userid){
			$result = remove_user_from_group($userid, $data['usrgrpid']);
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
	public static function delete($usrgrpids){
		$result = false;
		$usrgrpids = isset($usrgrpids['usrgrpids']) ? $usrgrpids['usrgrpids'] : array();
		$usrgrpids = zbx_value2array($usrgrpids);

		if(!empty($usrgrpids)){
			self::BeginTransaction(__METHOD__);
			foreach($usrgrpids as $groupid){
				$result = delete_user_group($groupid);
				if(!$result) break;
			}
			$result = self::EndTransaction($result, __METHOD__);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ usrgrpids ]');
			$result = false;
		}

		if($result)
			return true;
		else{
			self::setError(__METHOD__);
			return false;
		}
	}

}
?>
