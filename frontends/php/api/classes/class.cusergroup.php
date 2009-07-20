<?php
/**
 * File containing CUserGroup class for API.
 * @package API
 */
/**
 * Class containing methods for operations with UserGroups.
 */
class CUserGroup {

	public static $error;

	/**
	 * Gets all UserGroup data from DB by usrgrpid.
	 *
	 * <code>
	 * $user_data = array(
	 * 	*string 'usrgrpid' => 'UserGroup ID'
	 * )
	 * </code>
	 *
	 * @static
	 * @param array $user_data
	 * @return array|boolean user data as array or false if error
	 */
	public static function getById($group_data){
		$group = get_group_by_usrgrpid($group_data['usrgrpid']);

		if($group)
			return $group;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'User with id: '.$group_data['usrgrpid'].' doesn\'t exists.');
			return false;
		}
	}

	/**
	 * Get UserGroup ID by UserGroup name.
	 *
	 * <code>
	 * $group_data = array(
	 * 	*string 'name' => 'UserGroup name'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $group_data
	 * @return string|boolean
	 */
	public static function getId($group_data){
		$result = false;

		$sql = 'SELECT g.usrgrpid '.
				' FROM usrgrp g '.
				' WHERE g.name='.zbx_dbstr($group_data['name']).
					' AND '.DBin_node('g.usrgrpid', get_current_nodeid(false));

		if($group = DBfetch(DBselect($sql)))
			$result = $group['usrgrpid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Host with name: "'.$user_data['alias'].'" doesn\'t exists.');
		}

	return $result;
	}

	/**
	 * Create UserGroups.
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
	 * @static
	 * @param array $groups multidimensional array with UserGroups data
	 * @return boolean
	 */
	public static function add($groups){
		$error = 'Unknown ZABBIX internal error';
		$result = false;

		DBstart(false);
		
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
		$result = DBend($result);

		if($result){
			return true;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);
			return false;
		}
	}

	/**
	 * Update UserGroups.
	 *
	 * @static
	 * @param array $groups multidimensional array with UserGroups data
	 * @return boolean
	 */
	public static function update($groups){
		$result = false;

		DBstart(false);
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
		$result = DBend($result);

		if($result){
			return true;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

	/**
	 * Update UserGroup rights to HostGroups.
	 *
	 * <code>
	 * $rights = array(
	 * 	*string 'groupid' => 'UserGroup ID',
	 * 	array 'rights' => array( array('id' => 'HostGroup ID', 'permission' => 'permission'), ..)
	 * )
	 * </code>
	 *
	 * @static
	 * @param array $rights multidimensional array with rights data
	 * @return boolean
	 */
	public static function updateRights($rights){
		$result = false;
		
		$usrgrpid = $rights['usrgrpid'];
		
		DBstart(false);
		$result=DBexecute("DELETE FROM rights WHERE groupid=".$usrgrpid);
		
		foreach($rights['rights'] as $right){
			$id = get_dbid('rights', 'rightid');
			$result = DBexecute('INSERT INTO rights (rightid, groupid, permission, id)'.
				' VALUES ('.$id.','.$usrgrpid.','.$right['permission'].','.$right['id'].')');
			if(!$result) break;
		}
		
		$result = DBend($result);
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Add rights for UserGroup to HostGroups. Existing rights are updated, new ones added.
	 *
	 * <code>
	 * $rights = array(
	 * 	*string 'groupid' => 'UserGroup ID',
	 * 	array 'rights' => array( array('id' => 'HostGroup ID', 'permission' => 'permission'), ..)
	 * )
	 * </code>
	 *
	 * @static
	 * @param array $rights multidimensional array with rights data
	 * @return boolean
	 */
	public static function addRights($rights){
		$result = false;
		
		$usrgrpid = $rights['usrgrpid'];

		DBstart(false);
		
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
					' VALUES ('.$id.','.$usrgrpid.','.$right['permission'].','.$right['id'].')');
			}
			$result = DBexecute($sql);
			if(!$result) break;
		}
		
		$result = DBend($result);

		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Add Users to UserGroup.
	 *
	 * <code>
	 * $data = array(
	 * 	*string 'usrgrpid' => 'UserGroup ID',
	 * 	*array 'userids' => ('User ID', 'User ID', ...)
	 * )
	 * </code>
	 *
	 * @static
	 * @param array $data
	 * @return boolean
	 */
	public static function addUsers($data){
		$result = false;
		
		DBstart(false);
		foreach($data['userids'] as $userid){
			$result = add_user_to_group($userid, $data['usrgrpid']);
			if(!$result) break;
		}
		$result = DBend($result);
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Remove users from UserGroup.
	 *
	 * <code>
	 * $data = array(
	 * 	*string 'usrgrpid' => 'UserGroup ID',
	 * 	*array 'userids' => ('User ID', 'User ID', ...)
	 * )
	 * </code>
	 *
	 * @static
	 * @param array $data
	 * @return boolean
	 */
	public static function removeUsers($data){
		$result = false;
		
		DBstart(false);
		foreach($data['userids'] as $userid){
			$result = remove_user_from_group($userid, $data['usrgrpid']);
			if(!$result) break;
		}
		$result = DBend($result);
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Delete UserGroups.
	 *
	 * @static
	 * @param array $groupids
	 * @return boolean
	 */
	public static function delete($groupids){
		$result = false;
		
		DBstart(false);
		foreach($groupids au $groupid){
			$result = delete_user_group($groupid);
			if(!$resukt) break;
		}
		DBend($result);
		
		if($result)
			return true;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

}
?>
