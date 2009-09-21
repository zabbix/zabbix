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
 * File containing CUser class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Users
 */
class CUser {

	public static $error;

	/**
	 * Get Users
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
	 * @param boolean $options['type']
	 * @param boolean $options['status']
	 * @param boolean $options['with_gui_access']
	 * @param boolean $options['with_api_access']
	 * @param boolean $options['select_usrgrps']
	 * @param boolean $options['get_access']
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

		$sort_columns = array('userid', 'alias'); // allowed columns for sorting


		$sql_parts = array(
			'select' => array('users' => 'u.userid'),
			'from' => array('users u'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'type'						=> null,
			'status'					=> null,
			'with_gui_access'			=> null,
			'with_api_access'			=> null,
// OutPut
			'extendoutput'				=> null,
			'select_usrgrps'			=> null,
			'get_access'				=> null,
			'count'						=> null,
			'pattern'					=> '',
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
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['usrgrpid'] = 'ug.usrgrpid';
			}
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where'][] = DBcondition('ug.usrgrpid', $options['usrgrpids']);
			$sql_parts['where']['uug'] = 'u.userid=ug.userid';

		}

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);
			$sql_parts['where'][] = DBcondition('u.userid', $options['userids']);
		}

// type
		if(!is_null($options['status'])){
			$sql_parts['where'][] = 'g.users_status='.$options['status'];
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
			$sql_parts['select']['usrgrp'] = 'u.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(u.userid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(u.alias) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'u.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('u.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('u.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'u.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------
		$userids = array();

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
				WHERE '.DBin_node('u.userid', $nodeids).
				$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($user = DBfetch($res)){
			if($options['count'])
				$result = $user;
			else{
				$userids[$user['userid']] = $user['userid'];

				if(is_null($options['extendoutput'])){
					$result[$user['userid']] = $user['userid'];
				}
				else{
					if(!isset($result[$user['userid']])) $result[$user['userid']]= array();

					if($options['select_usrgrps'] && !isset($result[$user['userid']]['usrgrpids'])){
						$result[$user['userid']]['usrgrpids'] = array();
						$result[$user['userid']]['usrgrps'] = array();
					}

					// usrgrpids
					if(isset($user['usrgrpid'])){
						if(!isset($result[$user['userid']]['usrgrpids']))
							$result[$user['userid']]['usrgrpids'] = array();

						$result[$user['userid']]['usrgrpids'][$user['usrgrpid']] = $user['usrgrpid'];
						unset($user['usrgrpid']);
					}

					$result[$user['userid']] += $user;
				}
			}
		}

		if($options['get_access'] != 0){

			foreach($result as $userid => $user){
				$result[$userid] += array('api_access' => 0, 'gui_access' => 0, 'debug_mode' => 0, 'users_status' => 0);
			}

			$sql = 'SELECT ug.userid, MAX(g.api_access) as api_access,  MAX(g.gui_access) as gui_access,
						MAX(g.debug_mode) as debug_mode, MAX(g.users_status) as users_status'.
					' FROM usrgrp g, users_groups ug '.
					' WHERE '.DBcondition('ug.userid', $userids).
						' AND g.usrgrpid=ug.usrgrpid '.
					' GROUP BY ug.userid';
			$access = DBselect($sql);

			while($useracc = DBfetch($access)){
				$result[$useracc['userid']] = zbx_array_merge($result[$useracc['userid']], $useracc);
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])) return $result;

// Adding Objects
// Adding usegroups
		if($options['select_usrgrps']){
			$obj_params = array('extendoutput' => 1, 'userids' => $userids);
			$usrgrps = CUserGroup::get($obj_params);
			foreach($usrgrps as $usrgrpid => $usrgrp){
				foreach($usrgrp['userids'] as $num => $userid){
					$result[$userid]['usrgrpids'][$usrgrpid] = $usrgrpid;
					$result[$userid]['usrgrps'][$usrgrpid] = $usrgrp;
				}
			}
		}

	return $result;
	}

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
	 * @return boolean
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
			self::$error = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'User with name: "'.$user_data['alias'].'" doesn\'t exists.');
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
	 * <code>
	 * $media_data = array(
	 * 	*string 'userid => 'User ID',
	 * 	array 'mediaids' => array('Media ID', 'Media ID', ...)
	 * );
	 * </code>
	 *
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
	 * Update Medias for User
	 *
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
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
	 * {@source}
	 * @access public
	 * @static
	 * @since 1.8
	 * @version 1
	 *
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
