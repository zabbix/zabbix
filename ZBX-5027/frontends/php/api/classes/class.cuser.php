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
 * File containing CUser class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Users
 */
class CUser extends CZBXAPI{
/**
 * Get Users data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] filter by Node IDs
 * @param array $options['usrgrpids'] filter by UserGroup IDs
 * @param array $options['userids'] filter by User IDs
 * @param boolean $options['type'] filter by User type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
 * @param boolean $options['select_usrgrps'] extend with UserGroups data for each User
 * @param boolean $options['get_access'] extend with access data for each User
 * @param boolean $options['extendoutput'] output only User IDs if not set.
 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
 * @param string $options['pattern'] filter by Host name containing only give pattern
 * @param int $options['limit'] output will be limited to given number
 * @param string $options['sortfield'] output will be sorted by given property [ 'userid', 'alias' ]
 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
 * @return array
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('userid', 'alias'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND); // allowed output options for [ select_* ] params


		$sql_parts = array(
			'select' => array('users' => 'u.userid'),
			'from' => array('users' => 'users u'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'mediaids'					=> null,
			'mediatypeids'				=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

// OutPut
			'extendoutput'				=> null,
			'output'					=> API_OUTPUT_REFER,
			'editable'					=> null,
			'select_usrgrps'			=> null,
			'select_mediatypes'			=> null,
			'get_access'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(!is_null($options['extendoutput'])){
			$options['output'] = API_OUTPUT_EXTEND;

			if(!is_null($options['select_usrgrps'])){
				$options['select_usrgrps'] = API_OUTPUT_EXTEND;
			}
		}


// PERMISSION CHECK
		if(USER_TYPE_SUPER_ADMIN == $user_type){

		}
		else if(is_null($options['editable']) && ($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN)){
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['uug'] = 'u.userid=ug.userid';
			$sql_parts['where'][] = 'ug.usrgrpid IN ('.
				' SELECT uug.usrgrpid'.
				' FROM users_groups uug'.
				' WHERE uug.userid='.$USER_DETAILS['userid'].
				' )';
		}
		else if(!is_null($options['editable']) || ($USER_DETAILS['type']!=USER_TYPE_SUPER_ADMIN)){
			$options['userids'] = $USER_DETAILS['userid'];
		}

// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// userids
		if(!is_null($options['userids'])){
			zbx_value2array($options['userids']);
			$sql_parts['where'][] = DBcondition('u.userid', $options['userids']);
		}

// usrgrpids
		if(!is_null($options['usrgrpids'])){
			zbx_value2array($options['usrgrpids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['usrgrpid'] = 'ug.usrgrpid';
			}
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where'][] = DBcondition('ug.usrgrpid', $options['usrgrpids']);
			$sql_parts['where']['uug'] = 'u.userid=ug.userid';
		}

// mediaids
		if(!is_null($options['mediaids'])){
			zbx_value2array($options['mediaids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['mediaid'] = 'm.mediaid';
			}
			$sql_parts['from']['media'] = 'media m';
			$sql_parts['where'][] = DBcondition('m.mediaid', $options['mediaids']);
			$sql_parts['where']['mu'] = 'm.userid=u.userid';
		}

// mediatypeids
		if(!is_null($options['mediatypeids'])){
			zbx_value2array($options['mediatypeids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['mediatypeid'] = 'm.mediatypeid';
			}
			$sql_parts['from']['media'] = 'media m';
			$sql_parts['where'][] = DBcondition('m.mediatypeid', $options['mediatypeids']);
			$sql_parts['where']['mu'] = 'm.userid=u.userid';
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['users'] = 'u.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(u.userid) as rowscount');
		}

// filter
		if(is_array($options['filter'])){
			zbx_db_filter('users u', $options, $sql_parts);
		}

// search
		if(is_array($options['search'])){
			unset($options['search']['passwd']);
			zbx_db_search('users u', $options, $sql_parts);
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

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('u.userid', $nodeids).
				$sql_where.
				$sql_order;
//SDI($sql);
		$res = DBselect($sql, $sql_limit);
		while($user = DBfetch($res)){
			if(!is_null($options['countOutput'])){
				$result = $user['rowscount'];
			}
			else{
				$userids[$user['userid']] = $user['userid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$user['userid']] = array('userid' => $user['userid']);
				}
				else{
					if(!isset($result[$user['userid']])) $result[$user['userid']]= array();

					if($options['select_usrgrps'] && !isset($result[$user['userid']]['usrgrps'])){
						$result[$user['userid']]['usrgrps'] = array();
					}

// usrgrpids
					if(isset($user['usrgrpid']) && is_null($options['select_usrgrps'])){
						if(!isset($result[$user['userid']]['usrgrps']))
							$result[$user['userid']]['usrgrps'] = array();

						$result[$user['userid']]['usrgrps'][] = array('usrgrpid' => $user['usrgrpid']);
						unset($user['usrgrpid']);
					}

// mediatypeids
					if(isset($user['mediatypeid']) && is_null($options['select_mediatypes'])){
						if(!isset($result[$user['userid']]['mediatypes']))
							$result[$user['userid']]['mediatypes'] = array();

						$result[$user['userid']]['mediatypes'][] = array('mediatypeid' => $user['mediatypeid']);
						unset($user['mediatypeid']);
					}
					$result[$user['userid']] += $user;
				}
			}
		}

Copt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

		// adding objects
		if(!is_null($options['get_access'])){
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

		// adding usergroups
		if(!is_null($options['select_usrgrps']) && str_in_array($options['select_usrgrps'], $subselects_allowed_outputs)){
			$obj_params = array(
				'output' => $options['select_usrgrps'],
				'userids' => $userids,
				'preservekeys' => 1
			);
			$usrgrps = CUserGroup::get($obj_params);
			foreach($usrgrps as $usrgrpid => $usrgrp){
				$uusers = $usrgrp['users'];
				unset($usrgrp['users']);
				foreach($uusers as $num => $user){
					$result[$user['userid']]['usrgrps'][] = $usrgrp;
				}
			}
		}

		// adding mediatypes
		if (!is_null($options['select_mediatypes']) && str_in_array($options['select_mediatypes'], $subselects_allowed_outputs)) {
			foreach ($result as &$user) {
				$user['mediatypes'] = array();
			}
			unset($user);

			$userMediatypes = CMediatype::get(array(
				'output' => $options['select_mediatypes'],
				'userids' => $userids,
				'preservekeys' => true
			));
			foreach ($userMediatypes as $userMediatype) {
				$users = $userMediatype['users'];
				unset($userMediatype['users']);

				foreach ($users as $user) {
					if (!empty($result[$user['userid']])) {
						$result[$user['userid']]['mediatypes'][] = $userMediatype;
					}
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
 * Get User ID by User alias
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $user_data
 * @param array $user_data['alias'] User alias
 * @return string|boolean
 */
	public static function getObjects($user_data){
		$result = array();
		$userids = array();

		$sql = 'SELECT u.userid '.
				' FROM users u '.
				' WHERE u.alias='.zbx_dbstr($user_data['alias']).
					' AND '.DBin_node('u.userid', false);
		$res = DBselect($sql);
		while($user = DBfetch($res)){
			$userids[] = $user['userid'];
		}

		if(!empty($userids))
			$result = self::get(array('userids' => $userids, 'extendoutput' => 1));

		return $result;
	}

/**
 * Add Users
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $users multidimensional array with Users data
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
 * @param string $users['user_medias']['mediatypeid']
 * @param string $users['user_medias']['address']
 * @param int $users['user_medias']['severity']
 * @param int $users['user_medias']['active']
 * @param string $users['user_medias']['period']
 * @return array|boolean
 */
	public static function create($users){
		global $USER_DETAILS;
		$result = false;
		$errors = array();

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_SUPER_ADMIN_CAN_CREATE_USERS);
			return false;
		}

		$users = zbx_toArray($users);
		$userids = array();

		self::BeginTransaction(__METHOD__);
		foreach($users as $unum => $user){

			$user_db_fields = array(
				'name' => 'ZABBIX',
				'surname' => 'USER',
				'alias' => null,
				'passwd' => 'zabbix',
				'url' => '',
				'autologin' => 0,
				'autologout' => 900,
				'lang' => 'en_gb',
				'theme' => 'default.css',
				'refresh' => 30,
				'rows_per_page' => 50,
				'type' => USER_TYPE_ZABBIX_USER,
				'user_medias' => array(),
			);
			if(!check_db_fields($user_db_fields, $user)){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_WRONG_FIELD_FOR_USER);
				$result = false;
				break;
			}


			$user_exist = self::getObjects(array('alias' => $user['alias']));
			if(!empty($user_exist)){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_USER_EXISTS_FIRST_PART.' '.$user_exist[0]['alias'].' '.S_CUSER_ERROR_USER_EXISTS_SECOND_PART);
				$result = false;
				break;
			}

			$userid = get_dbid('users', 'userid');
			$result = DBexecute('INSERT INTO users (userid, name, surname, alias, passwd, url, autologin, autologout, lang, theme,
				refresh, rows_per_page, type) '.
				' VALUES ('.
					$userid.','.
					zbx_dbstr($user['name']).','.
					zbx_dbstr($user['surname']).','.
					zbx_dbstr($user['alias']).','.
					zbx_dbstr(md5($user['passwd'])).','.
					zbx_dbstr($user['url']).','.
					$user['autologin'].','.
					$user['autologout'].','.
					zbx_dbstr($user['lang']).','.
					zbx_dbstr($user['theme']).','.
					$user['refresh'].','.
					$user['rows_per_page'].','.
					$user['type'].
				')');

			if($result){
				$usrgrps = zbx_objectValues($user['usrgrps'], 'usrgrpid');
				foreach($usrgrps as $groupid){
					if(!$result) break;
					$users_groups_id = get_dbid("users_groups","id");
					$result = DBexecute('INSERT INTO users_groups (id,usrgrpid,userid)'.
						'values('.$users_groups_id.','.$groupid.','.$userid.')');
				}
			}

			if($result){
				foreach($user['user_medias'] as $media_data){
					if(!$result) break;
					$mediaid = get_dbid('media', 'mediaid');
					$result = DBexecute('INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' VALUES ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
						zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
						zbx_dbstr($media_data['period']).')');
				}
			}

// } copy from frontend
			if(!$result) break;

			$userids[] = $userid;
		}
		$result = self::EndTransaction($result, __METHOD__);
		if($result){
			return array('userids' => $userids);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
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
 * @param _array $users multidimensional array with Users data
 * @param string $users['userid']
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
 * @param string $users['user_medias']['mediatypeid']
 * @param string $users['user_medias']['address']
 * @param int $users['user_medias']['severity']
 * @param int $users['user_medias']['active']
 * @param string $users['user_medias']['period']
 * @return boolean
 */
	public static function update($users){
		global $USER_DETAILS;
		$errors = array();
		$result = true;
		$self = false;

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_SUPER_ADMIN_CAN_UPDATE_USERS);
			return false;
		}

		$users = zbx_toArray($users);
		$userids = zbx_objectValues($users, 'userid');

		$options = array(
			'userids' => zbx_objectValues($users, 'userid'),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$upd_users = self::get($options);
		foreach($users as $gnum => $user){
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User ['.$user['alias'].']');
		}

		self::BeginTransaction(__METHOD__);

		if(bccomp($USER_DETAILS['userid'], $user['userid']) == 0){
			$self = true;
		}

		foreach($users as $unum => $user){
			$user_db_fields = $upd_users[$user['userid']];

// check if we change guest user
			if(($user_db_fields['alias'] == ZBX_GUEST_USER) && isset($user['alias']) && ($user['alias'] != ZBX_GUEST_USER)){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_CANT_RENAME_GUEST_USER);
				$result = false;
				break;
			}


// unset if not changed passwd
			if(isset($user['passwd']) && !is_null($user['passwd'])){
				$user['passwd'] = md5($user['passwd']);
			}
			else{
				unset($user['passwd']);
			}
//---------

			if(!check_db_fields($user_db_fields, $user)){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_WRONG_FIELD_FOR_USER);
				$result = false;
				break;
			}

// copy from frontend {
			$sql = 'SELECT userid '.
					' FROM users '.
					' WHERE alias='.zbx_dbstr($user['alias']).
						' AND '.DBin_node('userid', id2nodeid($user['userid']));
			$db_user = DBfetch(DBselect($sql));
			if($db_user && ($db_user['userid'] != $user['userid'])){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_USER_EXISTS_FIRST_PART.' '.$user['alias'].' '.S_CUSER_ERROR_USER_EXISTS_SECOND_PART);
				$result = false;
				break;
			}

			$sql = 'UPDATE users SET '.
						' name='.zbx_dbstr($user['name']).', '.
						' surname='.zbx_dbstr($user['surname']).', '.
						' alias='.zbx_dbstr($user['alias']).', '.
						' passwd='.zbx_dbstr($user['passwd']).', '.
						' url='.zbx_dbstr($user['url']).', '.
						' autologin='.$user['autologin'].', '.
						' autologout='.$user['autologout'].', '.
						' lang='.zbx_dbstr($user['lang']).', '.
						' theme='.zbx_dbstr($user['theme']).', '.
						' refresh='.$user['refresh'].', '.
						' rows_per_page='.$user['rows_per_page'].', '.
						' type='.$user['type'].
					' WHERE userid='.$user['userid'];

			$result = DBexecute($sql);

			// if(isset($user['usrgrps']) && !is_null($user['usrgrps'])){
				// $user_groups = CHostGroup::get(array('userids' => $user['userid']));
				// $user_groupids = zbx_objectValues($user_groups, 'usrgrpid');
				// $new_groupids = zbx_objectValues($user['usrgrps'], 'usrgrpid');

				// $groups_to_add = array_diff($new_groupids, $user_groupids);

				// if(!empty($groups_to_add)){
					// $result &= self::massAdd(array('users' => $user, 'usrgrps' => $groups_to_add));
				// }

				// $groups_to_del = array_diff($user_groupids, $new_groupids);
				// if(!empty($groups_to_del)){
					// $result &= self::massRemove(array('users' => $user, 'usrgrps' => $groups_to_del));
				// }
			// }



			if($result && isset($user['usrgrps']) && !is_null($user['usrgrps'])){
				DBexecute('DELETE FROM users_groups WHERE userid='.$user['userid']);

				$options = array(
					'usrgrpids' => zbx_objectValues($user['usrgrps'], 'usrgrpid'),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => 1
				);
				$usrgrps = CUserGroup::get($options);

				foreach($usrgrps as $groupid => $group){
					if(!$result) break;

					if(($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED) && $self){
						$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_USER_UNABLE_RESTRICT_SELF_GUI_ACCESS_PART1.' '.$group['name'].' '.S_CUSER_ERROR_USER_UNABLE_RESTRICT_SELF_GUI_ACCESS_PART2);
						$result = false;
						break;
					}

					if(($group['users_status'] == GROUP_STATUS_DISABLED) && $self){
						$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_USER_CANT_DISABLE_SELF_PART1.' '.$group['name'].' '.S_CUSER_ERROR_USER_CANT_DISABLE_SELF_PART2);
						$result = false;
						break;
					}

					$users_groups_id = get_dbid('users_groups', 'id');
					$result = DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES ('.$users_groups_id.','.$groupid.','.$user['userid'].')');
				}
			}
/*
			if($result && !is_null($user['user_medias'])){
				$result = DBexecute('DELETE FROM media WHERE userid='.$userid);
				foreach($user['user_medias'] as $media_data){
					if(!$result) break;
					$mediaid = get_dbid('media', 'mediaid');
					$result = DBexecute('INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period)'.
						' VALUES ('.$mediaid.','.$userid.','.$media_data['mediatypeid'].','.
							zbx_dbstr($media_data['sendto']).','.$media_data['active'].','.$media_data['severity'].','.
							zbx_dbstr($media_data['period']).')');
				}
			}
//*/
// } copy from frontend
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('userids' => $userids);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
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
 * @param _array $users multidimensional array with Users data
 * @param string $users['userid']
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
 * @param string $users['user_medias']['mediatypeid']
 * @param string $users['user_medias']['address']
 * @param int $users['user_medias']['severity']
 * @param int $users['user_medias']['active']
 * @param string $users['user_medias']['period']
 * @return boolean
 */
	public static function updateProfile($user){
		global $USER_DETAILS;
		$errors = array();
		$result = true;

		$options = array(
			'nodeids' => id2nodeid($USER_DETAILS['userid']),
			'userids' => $USER_DETAILS['userid'],
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$upd_users = self::get($options);
		$upd_user = reset($upd_users);
//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User ['.$user['alias'].']');

		self::BeginTransaction(__METHOD__);


		$user_db_fields = $upd_user;

// unset if not changed passwd
		if(isset($user['passwd']) && !is_null($user['passwd'])){
			$user['passwd'] = md5($user['passwd']);
		}
		else{
			unset($user['passwd']);
		}
//---------

		if(!check_db_fields($user_db_fields, $user)){
			$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CUSER_ERROR_WRONG_FIELD_FOR_USER);
			$result = false;
			break;
		}

// copy from frontend {

		$sql = 'UPDATE users SET '.
					' passwd='.zbx_dbstr($user['passwd']).', '.
					' url='.zbx_dbstr($user['url']).', '.
					' autologin='.$user['autologin'].', '.
					' autologout='.$user['autologout'].', '.
					' lang='.zbx_dbstr($user['lang']).', '.
					' theme='.zbx_dbstr($user['theme']).', '.
					' refresh='.$user['refresh'].', '.
					' rows_per_page='.$user['rows_per_page'].
				' WHERE userid='.$user['userid'];

		$result = DBexecute($sql);


		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$options = array(
					'nodeids' => id2nodeid($USER_DETAILS['userid']),
					'userids' => $USER_DETAILS['userid'],
					'extendoutput' => 1
				);

			$upd_users = self::get($options);
			return $upd_users;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
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
 * @param array $users
 * @param array $users[0,...]['userids']
 * @return boolean
 */
	public static function delete($users){
		global $USER_DETAILS;
		$errors = array();

		if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_SUPER_ADMIN_CAN_DELETE_USERS);
			return false;
		}

		$users = zbx_toArray($users);
		$userids = array();
		$result = true;

		$options = array(
			'userids'=> zbx_objectValues($users, 'userid'),
			'output'=> API_OUTPUT_EXTEND,
			'preservekeys'=>1
		);
		$del_users = self::get($options);

		foreach($del_users as $gnum => $user){
			if(bccomp($USER_DETAILS['userid'], $user['userid']) == 0){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_USER_CANNOT_DELETE_ITSELF);
				$result = false;
			}

			if($del_users[$user['userid']]['alias'] == ZBX_GUEST_USER){
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_CANNOT_DELETE_USER.' [ '.ZBX_GUEST_USER.' ]');
				$result = false;
			}

			$userids[] = $user['userid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, 'User ['.$user['alias'].']');
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($userids) && $result){
			$result = DBexecute('DELETE FROM operations WHERE object='.OPERATION_OBJECT_USER.' AND '.DBcondition('objectid', $userids));
			$result = DBexecute('DELETE FROM media WHERE '.DBcondition('userid', $userids));
			$result = DBexecute('DELETE FROM profiles WHERE '.DBcondition('userid', $userids));
			$result = DBexecute('DELETE FROM users_groups WHERE '.DBcondition('userid', $userids));
			$result = DBexecute('DELETE FROM users WHERE '.DBcondition('userid', $userids));
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return array('userids' => $userids);
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
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
 * @param array $media_data
 * @param string $media_data['userid']
 * @param string $media_data['medias']['mediatypeid']
 * @param string $media_data['medias']['address']
 * @param int $media_data['medias']['severity']
 * @param int $media_data['medias']['active']
 * @param string $media_data['medias']['period']
 * @return boolean
 */
	public static function addMedia($media_data){
		global $USER_DETAILS;

		$result = true;

		$medias = zbx_toArray($media_data['medias']);
		$users = zbx_toArray($media_data['users']);

		$userids = array();

		if($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_ADMIN_CAN_ADD_USER_MEDIAS);
			return false;
		}

		foreach($users as $unum => $user){
			$userids[] = $user['userid'];

			foreach($medias as $mnum => $media){
				if(!validate_period($media['period'])){
					self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_INCORRECT_TIME_PERIOD);
					return false;
				}

				$mediaid = get_dbid('media','mediaid');

				$sql='INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period) '.
						' VALUES ('.$mediaid.','.$user['userid'].','.$media['mediatypeid'].','.
									zbx_dbstr($media['sendto']).','.$media['active'].','.$media['severity'].','.
									zbx_dbstr($media['period']).')';

				$result = DBexecute($sql);

				if(!$result) break 2;
			}
		}

		if($result){
			return array('userids'=>$userids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => S_CUSER_ERROR_INTERNAL_ZABBIX_ERROR);
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
 * @param array $medias
 * @param array $medias[...][mediaid]
 * @return boolean
 */
	public static function deleteMedia($medias){
		global $USER_DETAILS;

		$medias = zbx_toArray($medias);
		$mediaids = zbx_objectValues($medias, 'mediaid');

		if($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_ADMIN_CAN_REMOVE_USER_MEDIAS);
			return false;
		}

		$sql = 'DELETE FROM media WHERE '.DBcondition('mediaid', $mediaids);
		$result = DBexecute($sql);

		if($result){
			return array('mediaids'=>$mediaids);
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => S_CUSER_ERROR_INTERNAL_ZABBIX_ERROR);
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
 * @param array $media_data
 * @param array $media_data['users']
 * @param array $media_data['users']['userid']
 * @param array $media_data['medias']
 * @param string $media_data['medias']['mediatypeid']
 * @param string $media_data['medias']['sendto']
 * @param int $media_data['medias']['severity']
 * @param int $media_data['medias']['active']
 * @param string $media_data['medias']['period']
 * @return boolean
 */
	public static function updateMedia($media_data){
		global $USER_DETAILS;

		$new_medias = zbx_toArray($media_data['medias']);
		$users = zbx_toArray($media_data['users']);

		try{
			self::BeginTransaction(__METHOD__);

			if($USER_DETAILS['type'] < USER_TYPE_ZABBIX_ADMIN){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_CUSER_ERROR_ONLY_ADMIN_CAN_CHANGE_USER_MEDIAS);
				return false;
			}

			$upd_medias = array();
			$del_medias = array();

			$userids = zbx_objectValues($users, 'userid');
			$sql = 'SELECT m.mediaid '.
					' FROM media m '.
					' WHERE '.DBcondition('userid', $userids);
			$result = DBselect($sql);
			while($media = DBfetch($result)){
				$del_medias[$media['mediaid']] = $media;
			}

			foreach($new_medias as $mnum => $media){
				if(!isset($media['mediaid'])) continue;

				if(isset($del_medias[$media['mediaid']])){
					$upd_medias[$media['mediaid']] = $new_medias[$mnum];
				}

				unset($new_medias[$mnum]);
				unset($del_medias[$media['mediaid']]);
			}

// DELETE
			if(!empty($del_medias)){
				$result = self::deleteMedia($del_medias);
				if(!$result){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_DELETE_USER_MEDIAS);
				}
			}

// UPDATE
			foreach($upd_medias as $mnum => $media){
				if(!validate_period($media['period'])){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_WRONG_PERIOD_PART1.' '.$media['period'].' '.S_CUSER_ERROR_WRONG_PERIOD_PART2);
				}

				$sql = 'UPDATE media '.
						' SET mediatypeid='.$media['mediatypeid'].','.
							' sendto='.zbx_dbstr($media['sendto']).','.
							' active='.$media['active'].','.
							' severity='.$media['severity'].','.
							' period='.zbx_dbstr($media['period']).
						' WHERE mediaid='.$media['mediaid'];
				$result = DBexecute($sql);
				if(!$result){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_UPDATE_USER_MEDIAS);
				}
			}

// CREATE
			if(!empty($new_medias)){
				$result = self::addMedia(array('users' => $users, 'medias' => $new_medias));
				if(!$result){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, S_CUSER_ERROR_CANT_INSERT_USER_MEDIAS);
				}
			}

			self::EndTransaction(true, __METHOD__);
			return array('userids'=>$userids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);

			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

// ******************************************************************************
//  LOGIN Methods
// ******************************************************************************

	public static function login($user){
		$config = select_config();
		$user['auth_type'] = $config['authentication_type'];

		$sessionid = self::authenticate($user);
		if(($sessionid !== false) && self::checkAuthentication(array('sessionid' => $sessionid))){
			return $sessionid;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_PARAMETERS, 'data' => $_REQUEST['message']);
			return false;
		}
	}

	public static function ldapLogin($user){
		$name = $user['user'];
		$passwd = $user['password'];
		$cnf = isset($user['cnf'])?$user['cnf']:null;

		if(is_null($cnf)){
			$config = select_config();
			foreach($config as $id => $value){
				if(zbx_strpos($id,'ldap_') !== false){
					$cnf[str_replace('ldap_','',$id)] = $config[$id];
				}
			}
		}

		if(!function_exists('ldap_connect')){
			info(S_CUSER_ERROR_LDAP_MODULE_MISSING);
			return false;
		}

		$ldap = new CLdap($cnf);
		$ldap->connect();

		$result = $ldap->checkPass($name,$passwd);

	return $result;
	}

	public static function logout($sessionid){
		global $ZBX_LOCALNODEID;

		$sql = 'SELECT s.* '.
			' FROM sessions s '.
			' WHERE s.sessionid='.zbx_dbstr($sessionid).
				' AND s.status='.ZBX_SESSION_ACTIVE.
				' AND '.DBin_node('s.userid', $ZBX_LOCALNODEID);

		$session = DBfetch(DBselect($sql));
		if(!$session) return false;

		try {
			self::BeginTransaction(__METHOD__);

			zbx_unsetcookie('zbx_sessionid');
			DBexecute('DELETE FROM sessions WHERE status='.ZBX_SESSION_PASSIVE.' AND userid='.zbx_dbstr($session['userid']));
			DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid='.zbx_dbstr($sessionid));
			add_audit(AUDIT_ACTION_LOGOUT, AUDIT_RESOURCE_USER, 'Manual Logout');

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch (APIException $e) {
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}
/**
 * Authenticate user
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $user
 * @param array $user['user'] User alias
 * @param array $user['password'] User password
 * @return string session ID
 */
	public static function authenticate($user){
		try {
			self::BeginTransaction(__METHOD__);
			global $USER_DETAILS, $ZBX_LOCALNODEID;

			$name = $user['user'];
			$passwd = $user['password'];
			$auth_type = $user['auth_type'];

			$password = md5($passwd);

			$sql = 'SELECT u.userid,u.attempt_failed, u.attempt_clock, u.attempt_ip '.
					' FROM users u '.
					' WHERE u.alias='.zbx_dbstr($name);

			$login = $attempt = DBfetch(DBselect($sql));

			if($login){
				if($login['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS){
					if((time() - $login['attempt_clock']) < ZBX_LOGIN_BLOCK){
						throw new APIException(ZBX_API_ERROR_INTERNAL, S_CUSER_ERROR_ACCOUNT_IS_BLOCKED_FOR_XX_SECONDS_FIRST_PART.' '.(ZBX_LOGIN_BLOCK - (time() - $login['attempt_clock'])).' '.S_CUSER_ERROR_ACCOUNT_IS_BLOCKED_FOR_XX_SECONDS_SECOND_PART);
					}
					else{
						DBexecute('UPDATE users SET attempt_clock='.time().' WHERE alias='.zbx_dbstr($name));
					}
				}

				if($auth_type != ZBX_AUTH_HTTP){
					switch(get_user_auth($login['userid'])){
						case GROUP_GUI_ACCESS_INTERNAL:
							$auth_type = ZBX_AUTH_INTERNAL;
							break;
						case GROUP_GUI_ACCESS_SYSTEM:
						case GROUP_GUI_ACCESS_DISABLED:
						default:
							break;
					}
				}

				switch($auth_type){
					case ZBX_AUTH_LDAP:
						$login = self::ldapLogin($user);
						break;
					case ZBX_AUTH_HTTP:
						$login = true;
						break;
					case ZBX_AUTH_INTERNAL:
					default:
						$login = true;
				}
			}

			if($login){
				$sql = 'SELECT u.* '.
						' FROM users u'.
						' WHERE u.alias='.zbx_dbstr($name).
							((ZBX_AUTH_INTERNAL==$auth_type)? ' AND u.passwd='.zbx_dbstr($password):'').
							' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID);

				$login = $user = DBfetch(DBselect($sql));
			}

			if($login){
				$login = (check_perm2login($user['userid']) && check_perm2system($user['userid']));
			}

			if($login){
				$sessionid = zbx_session_start($user['userid'], $name, $password);

				// we assign $USER_DETAILS first time, so that it could be used in Cprofile::get
				$USER_DETAILS = $user;

				add_audit(AUDIT_ACTION_LOGIN,AUDIT_RESOURCE_USER, 'Correct login ['.$name.']');
				if(empty($user['url'])){
					$user['url'] = CProfile::get('web.menu.view.last','index.php');

					// we assign $USER_DETAILS second time, to update 'url'
					$USER_DETAILS['url'] = $user['url'];
				}

				$login = $sessionid;
			}
			else{
				$user = NULL;

				$_REQUEST['message'] = S_CUSER_ERROR_LOGIN_OR_PASSWORD_INCORRECT;
				add_audit(AUDIT_ACTION_LOGIN,AUDIT_RESOURCE_USER,'Login failed ['.$name.']');

				if($attempt){
					$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];
					$attempt['attempt_failed']++;
					$sql = 'UPDATE users '.
							' SET attempt_failed='.$attempt['attempt_failed'].','.
								' attempt_clock='.time().','.
								' attempt_ip='.zbx_dbstr($ip).
							' WHERE userid='.$attempt['userid'];
					DBexecute($sql);
				}
			}

			self::EndTransaction(true, __METHOD__);
			return $login;
		}
		catch (APIException $e) {
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			$_REQUEST['message'] = $error;
			return false;
		}
	}

/**
 * Check if session ID is authenticated
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param string $sessionid Session ID
 * @return boolean
 */
	public static function simpleAuth($sessionid){
		global	$USER_DETAILS;
		global	$ZBX_LOCALNODEID;
		global	$ZBX_NODES;

		$USER_DETAILS = NULL;
		$login = FALSE;

		if(is_null($sessionid)) return false;

		$sql = 'SELECT u.*,s.* '.
			' FROM sessions s,users u'.
			' WHERE '.DBin_node('u.userid', $ZBX_LOCALNODEID).
				' AND s.sessionid='.zbx_dbstr($sessionid).
				' AND s.status='.ZBX_SESSION_ACTIVE.
				' AND s.userid = u.userid';

		$login = $USER_DETAILS = DBfetch(DBselect($sql));

		if($login){
			$login = (check_perm2login($USER_DETAILS['userid']) && check_perm2system($USER_DETAILS['userid']));
		}

		if(!$login) return false;

		if(isset($ZBX_NODES[$ZBX_LOCALNODEID])){
			$USER_DETAILS['node'] = $ZBX_NODES[$ZBX_LOCALNODEID];
		}
		else{
			$USER_DETAILS['node'] = array();
			$USER_DETAILS['node']['name'] = '- unknown -';
			$USER_DETAILS['node']['nodeid'] = $ZBX_LOCALNODEID;
		}

		$USER_DETAILS['debug_mode'] = 0;

		$userip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];
		$USER_DETAILS['userip'] = $userip;

	return true;
	}

/**
 * Check if session ID is authenticated
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $session
 * @param array $session['sessionid'] Session ID
 * @return boolean
 */
	public static function checkAuthentication($user=null){
		global	$USER_DETAILS;
		global	$ZBX_LOCALNODEID;
		global	$ZBX_NODES;

		$sessionid = is_null($user)?null:$user['sessionid'];

		$USER_DETAILS = NULL;
		$login = FALSE;

		if(!is_null($sessionid)){
			$sql = 'SELECT u.*,s.* '.
					' FROM sessions s,users u'.
					' WHERE s.sessionid='.zbx_dbstr($sessionid).
						' AND s.status='.ZBX_SESSION_ACTIVE.
						' AND s.userid=u.userid'.
						' AND ((s.lastaccess+u.autologout>'.time().') OR (u.autologout=0))'.
						' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID);

			$login = $USER_DETAILS = DBfetch(DBselect($sql));

			if(!$USER_DETAILS){
				$incorrect_session = true;
			}
			else if($login['attempt_failed']){
				DBexecute('UPDATE users SET attempt_failed=0 WHERE userid='.$login['userid']);
			}
		}

		if(!$USER_DETAILS && !isset($_SERVER['PHP_AUTH_USER'])){
			$sql = 'SELECT u.* '.
				' FROM users u '.
				' WHERE u.alias='.zbx_dbstr(ZBX_GUEST_USER).
					' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID);
			$login = $USER_DETAILS = DBfetch(DBselect($sql));

			if(!$USER_DETAILS){
				$missed_user_guest = true;
			}
			else{
				$sessionid = zbx_session_start($USER_DETAILS['userid'], ZBX_GUEST_USER, '');
			}
		}

// Perm to login, perm to system
		if($login){
			$login = (check_perm2login($USER_DETAILS['userid']) && check_perm2system($USER_DETAILS['userid']));
		}

		if(!$login){
			$USER_DETAILS = NULL;
		}

		if($login && $sessionid && !isset($incorrect_session)){
			zbx_setcookie('zbx_sessionid',$sessionid,$USER_DETAILS['autologin']?(time()+86400*31):0);	//1 month
			DBexecute('UPDATE sessions SET lastaccess='.time().' WHERE sessionid='.zbx_dbstr($sessionid));

			if($USER_DETAILS['autologout'] > 0){
				DBexecute('DELETE FROM sessions WHERE userid='.$USER_DETAILS['userid'].' AND status='.ZBX_SESSION_ACTIVE.' AND lastaccess<'.(time() - $USER_DETAILS['autologout']));
			}
		}
		else{
			self::logout($sessionid);
		}

		if($USER_DETAILS){
			if(isset($ZBX_NODES[$ZBX_LOCALNODEID])){
				$USER_DETAILS['node'] = $ZBX_NODES[$ZBX_LOCALNODEID];
			}
			else{
				$USER_DETAILS['node'] = array();
				$USER_DETAILS['node']['name'] = '- unknown -';
				$USER_DETAILS['node']['nodeid'] = $ZBX_LOCALNODEID;
			}

			$USER_DETAILS['debug_mode'] = get_user_debug_mode($USER_DETAILS['userid']);
		}
		else{
			$USER_DETAILS = array(
				'alias'	=> ZBX_GUEST_USER,
				'userid'=> 0,
				'lang'	=> 'en_gb',
				'type'	=> '0',
				'node'	=> array( 'name'=>'- unknown -', 'nodeid'=>0 )
			);
		}

		$userip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];
		$USER_DETAILS['userip'] = $userip;

		if(!$login || isset($incorrect_session) || isset($missed_user_guest)){

			if(isset($incorrect_session))	$message = 'Session terminated, re-login, please'; // S_CUSER_ERROR_SESSION_TERMINATED
			else if(isset($missed_user_guest)){
				$row = DBfetch(DBselect('SELECT count(u.userid) as user_cnt FROM users u'));
				if(!$row || $row['user_cnt'] == 0){
					$message = 'Table users is empty. Possible database corruption.'; // S_CUSER_ERROR_TABLE_USERS_EMPTY
				}
			}

			if(!isset($_REQUEST['message']) && isset($message)) $_REQUEST['message'] = $message;

		return false;
		}

	return true;
	}
}
?>
