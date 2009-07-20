<?php
/**
 * File containing CUser class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Users
 */
class CUser {

	public static $error;

	/**
	 * Authenticate user
	 *
	 * @static
	 * @param _array $user
	 * @param array $user['login']
	 * @param array $user['password']
	 * @return string session ID
	 */
	public static function authenticate($user){
	
		$login = user_login($user['user'], $user['password'], ZBX_AUTH_INTERNAL);
		if($login){
			return $login;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => 'Given login or password is incorrect.');
		}
	}
	
	/**
	 * Check if session ID authenticated
	 *
	 * @static
	 * @param _array $session
	 * @param array $session['sessionid']
	 * @return boolean 
	 */
	public static function checkAuth($session){
		return check_authentication($session['sessionid']);
	}
	
	/**
	 * get API Access status
	 *
	 * @static
	 * @param _array $user
	 * @param array $user['user']
	 * @return boolean host data as array or false if error
	 */
	public static function apiAccess($user){
		$sql = 'SELECT min(g.api_access) as access
				FROM usrgrp g, users_groups ug, users u
				WHERE ug.usrgrpid=g.usrgrpid
					AND u.userid=ug.userid
					AND u.alias='.zbx_dbstr($user['user']).
					' AND '.DBin_node('u.userid', get_current_nodeid(false)).
				' GROUP BY u.userid';
				
		$access = DBfetch(DBselect($sql));
		return $access['access'] ? true : false;
	}
	

	/**
	 * Gets all User data from DB by User ID
	 *
	 * <code>
	 * $user_data = array(
	 * 	*string 'userid' => 'User ID'
	 * )
	 * </code>
	 *
	 * @static
	 * @param array $user_data
	 * @return array|boolean User data as array or false if error
	 */
	public static function getById($user_data){
		$user = get_user_by_userid($user_data['userid']);

		if($user)
			return $user;
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'User with id: '.$user_data['userid'].' doesn\'t exists.');
			return false;
		}
	}

	/**
	 * Get User ID by User alias
	 *
	 * <code>
	 * $user_data = array(
	 * 	*string 'alias' => 'User alias'
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $user_data
	 * @return string|boolean 
	 */
	public static function getId($user_data){
		$result = false;

		$sql = 'SELECT u.userid '.
				' FROM users u '.
				' WHERE u.alias='.zbx_dbstr($user_data['alias']).
					' AND '.DBin_node('u.userid', get_current_nodeid(false));

		if($user = DBfetch(DBselect($sql)))
			$result = $user['userid'];
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Host with name: "'.$user_data['alias'].'" doesn\'t exists.');
		}

	return $result;
	}

	/**
	 * Add Users
	 *
	 * @static
	 * @param array $users multidimensional array with Users data
	 * @param string $users['name']
	 * @param string $users['surname']
	 * @param array $users['alias']
	 * @param string $users['passwd']
	 * @param string $users['url']
	 * @param int $users['autologin']
	 * @param int $users['autologout']
	 * @param string $users['lang']
	 * @param string $users['theme']
	 * @param int $users['refresh']
	 * @param int $users['rows_per_page']
	 * @param int $users['type']
	 * @param array $users['user_medias']
	 * @return boolean
	 */
	public static function add($users){
		$error = 'Unknown ZABBIX internal error';
		$result = false;

		DBstart(false);

		foreach($users as $user){
			$result = add_user($user);
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
	 * Update Users
	 *
	 * @static
	 * @param array $users multidimensional array with Users data
	 * @return boolean
	 */
	public static function update($users){
		$result = false;

		DBstart(false);
		foreach($users as $user){
			$result = update_user($user['userid'], $user);
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
	 * Add Medias for User
	 *
	 * <code>
	 * $media_data = array(
	 * 	*string 'userid => 'User ID',
	 * 	array 'medias' => array(
	 * 		string 'mediatypeid' => 'media type ID',
	 * 		string 'sendto' => 'address',
	 * 		int 'severity' => 'severity',
	 * 		int 'active' => 'active',
	 * 		string 'period' => 'period',
	 * 		)
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $media_data 
	 * @return boolean
	 */
	public static function addMedia($media_data){
		$result = false;
		$userid = $media_data['userid'];
		
		foreach($media_data['medias'] as $media){
			$result = add_media( $userid, $media['mediatypeid'], $media['sendto'], $media['severity'], $media['active'], $media['period']);
			if(!$result) break;
		}
		
		if($result){
			return true;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Delete User Medias
	 *
	 * <code>
	 * $media_data = array(
	 * 	*string 'userid => 'User ID',
	 * 	array 'mediaids' => array('Media ID', 'Media ID', ...)
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $media_data 
	 * @return boolean
	 */
	public static function deleteMedia($media_data){
		$sql = 'DELETE FROM media WHERE userid='.$media_data['userid'].' AND '.DBcondition('mediaid', $media_data['mediaids']);
		$result = DBexecute($sql);
		
		if($result){
			return true;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}
	
	/**
	 * Add Medias for User
	 *
	 * <code>
	 * $media_data = array(
	 * 	*string 'userid => 'User ID',
	 * 	array 'medias' => array(
	 * 		string 'mediaid' => 'Medi ID',
	 * 		string 'mediatypeid' => 'media type ID',
	 * 		string 'sendto' => 'address',
	 * 		int 'severity' => 'severity',
	 * 		int 'active' => 'active',
	 * 		string 'period' => 'period',
	 * 		)
	 * );
	 * </code>
	 *
	 * @static
	 * @param array $media_data 
	 * @return boolean
	 */
	public static function updateMedia($media_data){
		$result = false;
		$userid = $media_data['userid'];
		
		foreach($media_data['medias'] as $media){
			$result = update_media($media['mediaid'], $userid, $media['mediatypeid'], $media['sendto'], $media['severity'], $media['active'], $media['period']);
			if(!$result) break;
		}
		
		if($result){
			return true;
		}
		else{
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	
	}
	
	/**
	 * Delete Users
	 *
	 * @static
	 * @param array $userids
	 * @return boolean
	 */
	public static function delete($userids){
		$result = false;
		
		DBstart(false);
		foreach($userids as $userid){
			$result = delete_user($userid);
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
