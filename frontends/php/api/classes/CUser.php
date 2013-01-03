<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @package API
 */
class CUser extends CZBXAPI {

	protected $tableName = 'users';
	protected $tableAlias = 'u';

	/**
	 * Get Users data
	 *
	 * @param array $options
	 * @param array $options['nodeids'] filter by Node IDs
	 * @param array $options['usrgrpids'] filter by UserGroup IDs
	 * @param array $options['userids'] filter by User IDs
	 * @param boolean $options['type'] filter by User type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
	 * @param boolean $options['selectUsrgrps'] extend with UserGroups data for each User
	 * @param boolean $options['getAccess'] extend with access data for each User
	 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
	 * @param string $options['pattern'] filter by Host name containing only give pattern
	 * @param int $options['limit'] output will be limited to given number
	 * @param string $options['sortfield'] output will be sorted by given property [ 'userid', 'alias' ]
	 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
	 * @return array
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];

		// allowed columns for sorting
		$sortColumns = array('userid', 'alias');

		$sqlParts = array(
			'select'	=> array('users' => 'u.userid'),
			'from'		=> array('users' => 'users u'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'usrgrpids'					=> null,
			'userids'					=> null,
			'mediaids'					=> null,
			'mediatypeids'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'editable'					=> null,
			'selectUsrgrps'				=> null,
			'selectMedias'				=> null,
			'selectMediatypes'			=> null,
			'getAccess'					=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['users']);

			$dbTable = DB::getSchema('users');
			$sqlParts['select']['userid'] = ' u.userid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'u.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// permission check
		if (USER_TYPE_SUPER_ADMIN == $userType) {
		}
		elseif (is_null($options['editable']) && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where']['uug'] = 'u.userid=ug.userid';
			$sqlParts['where'][] = 'ug.usrgrpid IN ('.
				' SELECT uug.usrgrpid'.
				' FROM users_groups uug'.
				' WHERE uug.userid='.self::$userData['userid'].
			')';
		}
		elseif (!is_null($options['editable']) || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$options['userids'] = self::$userData['userid'];
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);
			$sqlParts['where'][] = dbConditionInt('u.userid', $options['userids']);
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['usrgrpid'] = 'ug.usrgrpid';
			}
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['uug'] = 'u.userid=ug.userid';
		}

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['mediaid'] = 'm.mediaid';
			}
			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sqlParts['select']['mediatypeid'] = 'm.mediatypeid';
			}
			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediatypeid', $options['mediatypeids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['users'] = 'u.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('COUNT(DISTINCT u.userid) AS rowscount');
		}

		// filter
		if (is_array($options['filter'])) {
			if (isset($options['filter']['passwd'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to filter by user password.'));
			}
			$this->dbFilter('users u', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			if ($options['search']['passwd']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to search by user password.'));
			}
			zbx_db_search('users u', $options, $sqlParts);
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'u');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryNodeOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);

		$userids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.$sqlWhere.
				$sqlOrder;
		$res = DBselect($sql, $sqlLimit);
		while ($user = DBfetch($res)) {
			unset($user['passwd']);
			if (!is_null($options['countOutput'])) {
				$result = $user['rowscount'];
			}
			else {
				$userids[$user['userid']] = $user['userid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$user['userid']] = array('userid' => $user['userid']);
				}
				else {
					if (!isset($result[$user['userid']])) {
						$result[$user['userid']] = array();
					}

					if ($options['selectUsrgrps'] && !isset($result[$user['userid']]['usrgrps'])) {
						$result[$user['userid']]['usrgrps'] = array();
					}

					// usrgrpids
					if (isset($user['usrgrpid']) && is_null($options['selectUsrgrps'])) {
						if (!isset($result[$user['userid']]['usrgrps'])) {
							$result[$user['userid']]['usrgrps'] = array();
						}
						$result[$user['userid']]['usrgrps'][] = array('usrgrpid' => $user['usrgrpid']);
						unset($user['usrgrpid']);
					}

					// mediaids
					if (isset($user['mediaid']) && is_null($options['selectMedias'])) {
						if (!isset($result[$user['userid']]['medias'])) {
							$result[$user['userid']]['medias'] = array();
						}
						$result[$user['userid']]['medias'][] = array('mediaid' => $user['mediaid']);
						unset($user['mediaid']);
					}

					// mediatypeids
					if (isset($user['mediatypeid']) && is_null($options['selectMediatypes'])) {
						if (!isset($result[$user['userid']]['mediatypes'])) {
							$result[$user['userid']]['mediatypes'] = array();
						}
						$result[$user['userid']]['mediatypes'][] = array('mediatypeid' => $user['mediatypeid']);
						unset($user['mediatypeid']);
					}
					$result[$user['userid']] += $user;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		if (!is_null($options['getAccess'])) {
			foreach ($result as $userid => $user) {
				$result[$userid] += array('gui_access' => 0, 'debug_mode' => 0, 'users_status' => 0);
			}

			$access = DBselect(
				'SELECT ug.userid,MAX(g.gui_access) AS gui_access,'.
					' MAX(g.debug_mode) AS debug_mode,MAX(g.users_status) AS users_status'.
					' FROM usrgrp g,users_groups ug'.
					' WHERE '.dbConditionInt('ug.userid', $userids).
						' AND g.usrgrpid=ug.usrgrpid'.
					' GROUP BY ug.userid'
			);
			while ($userAccess = DBfetch($access)) {
				$result[$userAccess['userid']] = zbx_array_merge($result[$userAccess['userid']], $userAccess);
			}
		}

		$result = $this->addRelatedObjects($options, $result);

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	protected function checkInput(&$users, $method) {
		$create = ($method == 'create');
		$update = ($method == 'update');
		$delete = ($method == 'delete');

		// permissions
		if ($update || $delete) {
			$userDBfields = array('userid' => null);
			$dbUsers = $this->get(array(
				'output' => array('userid', 'alias', 'autologin', 'autologout'),
				'userids' => zbx_objectValues($users, 'userid'),
				'editable' => true,
				'preservekeys' => true
			));
		}
		else {
			$userDBfields = array('alias' => null, 'passwd' => null, 'usrgrps' => null, 'user_medias' => array());
		}

		$alias = array();
		foreach ($users as &$user) {
			if (!check_db_fields($userDBfields, $user)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong fields for user "%s".', $user['alias']));
			}

			// permission check
			if ($create) {
				if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to create users.'));
				}

				$dbUser = $user;
			}
			elseif ($update) {
				if (!isset($dbUsers[$user['userid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to update user or user does not exist.'));
				}

				if (bccomp(self::$userData['userid'], $user['userid']) != 0) {
					if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to update other users.'));
					}
				}
				$dbUser = $dbUsers[$user['userid']];
			}
			else {
				if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to delete users.'));
				}

				if (!isset($dbUsers[$user['userid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('You do not have permissions to delete user or user does not exist.'));
				}

				if (bccomp(self::$userData['userid'], $user['userid']) == 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User is not allowed to delete himself.'));
				}

				if ($dbUsers[$user['userid']]['alias'] == ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete Zabbix internal user "%1$s", try disabling that user.', ZBX_GUEST_USER));
				}

				continue;
			}

			// check if user alais
			if (isset($user['alias'])) {
				// check if we change guest user
				if ($dbUser['alias'] == ZBX_GUEST_USER && $user['alias'] != ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot rename guest user.'));
				}

				if (!isset($alias[$user['alias']])) {
					$alias[$user['alias']] = $update ? $user['userid'] : 1;
				}
				else {
					if ($create || bccomp($user['userid'], $alias[$user['alias']]) != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Duplicate user alias "%s".', $user['alias']));
					}
				}

				if (zbx_strlen($user['alias']) > 64) {
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_n(
							'Maximum alias length is %2$d characters, "%3$s" is %1$d character.',
							'Maximum alias length is %2$d characters, "%3$s" is %1$d characters.',
							zbx_strlen($user['alias']),
							64,
							$user['alias']
						)
					);
				}
			}

			if (isset($user['usrgrps'])) {
				if (empty($user['usrgrps'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User "%s" cannot be without user group.', $dbUser['alias']));
				}

				// checking if user tries to disable himself (not allowed). No need to check this on creating a user.
				if (!$create && bccomp(self::$userData['userid'], $user['userid']) == 0) {
					$usrgrps = API::UserGroup()->get(array(
						'usrgrpids' => zbx_objectValues($user['usrgrps'], 'usrgrpid'),
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => true,
						'nopermissions' => true
					));
					foreach ($usrgrps as $groupid => $group) {
						if ($group['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('User may not modify GUI access for himself by becoming a member of user group "%s".', $group['name']));
						}

						if ($group['users_status'] == GROUP_STATUS_DISABLED) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('User may not modify system status for himself by becoming a member of user group "%s".', $group['name']));
						}
					}
				}
			}

			if (isset($user['type']) && (USER_TYPE_SUPER_ADMIN != self::$userData['type'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('You are not allowed to alter privileges for user "%s".', $dbUser['alias']));
			}

			if (isset($user['autologin']) && $user['autologin'] == 1 && $dbUser['autologout'] != 0) {
				$user['autologout'] = 0;
			}

			if (isset($user['autologout']) && $user['autologout'] > 0 && $dbUser['autologin'] != 0) {
				$user['autologin'] = 0;
			}

			if (array_key_exists('passwd', $user)) {
				if (is_null($user['passwd'])) {
					unset($user['passwd']);
				}
				else {
					if ($dbUser['alias'] == ZBX_GUEST_USER && !zbx_empty($user['passwd'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Not allowed to set password for user "guest".'));
					}

					$user['passwd'] = md5($user['passwd']);
				}
			}

			if (isset($user['alias'])) {
				$nodeids = $update ? id2nodeid($user['userid']) : get_current_nodeid(false);
				$userExist = $this->get(array(
					'nodeids' => $nodeids,
					'filter' => array('alias' => $user['alias']),
					'nopermissions' => true
				));
				if ($exUser = reset($userExist)) {
					if ($create || (bccomp($exUser['userid'], $user['userid']) != 0)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with alias "%s" already exists.', $user['alias']));
					}
				}
			}
		}
		unset($user);
	}

	/**
	 * Add Users
	 *
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
	 * @param string $users['user_medias']['mediatypeid']
	 * @param string $users['user_medias']['address']
	 * @param int $users['user_medias']['severity']
	 * @param int $users['user_medias']['active']
	 * @param string $users['user_medias']['period']
	 * @return array|boolean
	 */
	public function create($users) {
		$users = zbx_toArray($users);

		$this->checkInput($users, __FUNCTION__);

		$userids = DB::insert('users', $users);

		foreach ($users as $unum => $user) {
			$userid = $userids[$unum];

			$usrgrps = zbx_objectValues($user['usrgrps'], 'usrgrpid');
			foreach ($usrgrps as $groupid) {
				$usersGroupdId = get_dbid('users_groups', 'id');
				$sql = 'INSERT INTO users_groups (id,usrgrpid,userid) VALUES ('.$usersGroupdId.','.$groupid.','.$userid.')';
				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
			}

			foreach ($user['user_medias'] as $mediaData) {
				$mediaid = get_dbid('media', 'mediaid');
				$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
					' VALUES ('.$mediaid.','.$userid.','.$mediaData['mediatypeid'].','.
					zbx_dbstr($mediaData['sendto']).','.$mediaData['active'].','.$mediaData['severity'].','.
					zbx_dbstr($mediaData['period']).')';
				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
			}
		}

		return array('userids' => $userids);
	}

	/**
	 * Update Users
	 *
	 * @param array $users multidimensional array with Users data
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
	public function update($users) {
		$users = zbx_toArray($users);
		$userids = zbx_objectValues($users, 'userid');

		$this->checkInput($users, __FUNCTION__);

		foreach ($users as $user) {
			$self = (bccomp(self::$userData['userid'], $user['userid']) == 0);

			$result = DB::update('users', array(
				array(
					'values' => $user,
					'where' => array('userid' => $user['userid'])
				)
			));

			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
			}

			if (isset($user['usrgrps']) && !is_null($user['usrgrps'])) {
				$newUsrgrpids = zbx_objectValues($user['usrgrps'], 'usrgrpid');

				// deleting all relations with groups, but not touching those, where user still must be after update
				DBexecute('DELETE FROM users_groups WHERE userid='.$user['userid'].' AND '.dbConditionInt('usrgrpid', $newUsrgrpids, true));

				// getting the list of groups user is currently in
				$dbGroupsUserIn = DBSelect('SELECT usrgrpid FROM users_groups WHERE userid='.$user['userid']);
				$groupsUserIn = array();
				while ($grp = DBfetch($dbGroupsUserIn)) {
					$groupsUserIn[$grp['usrgrpid']] = $grp['usrgrpid'];
				}

				$usrgrps = API::UserGroup()->get(array(
					'usrgrpids' => zbx_objectValues($user['usrgrps'], 'usrgrpid'),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => true
				));
				foreach ($usrgrps as $groupid => $group) {
					// if user is not already in a given group
					if (isset($groupsUserIn[$groupid])) {
						continue;
					}

					$usersGroupdId = get_dbid('users_groups', 'id');
					$sql = 'INSERT INTO users_groups (id,usrgrpid,userid) VALUES ('.$usersGroupdId.','.$groupid.','.$user['userid'].')';

					if (!DBexecute($sql)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
					}
				}
			}
		}

		return array('userids' => $userids);
	}

	public function updateProfile($user) {
		$user['userid'] = self::$userData['userid'];
		return $this->update(array($user));
	}

	/**
	 * Delete Users
	 *
	 * @param array $users
	 * @param array $users[0,...]['userids']
	 * @return boolean
	 */
	public function delete($users) {
		$users = zbx_toArray($users);
		$userids = zbx_objectValues($users, 'userid');

		$this->checkInput($users, __FUNCTION__);

		// delete action operation msg
		$operationids = array();
		$dbOperations = DBselect(
			'SELECT DISTINCT om.operationid'.
			' FROM opmessage_usr om'.
			' WHERE '.dbConditionInt('om.userid', $userids)
		);
		while ($dbOperation = DBfetch($dbOperations)) {
			$operationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('opmessage_usr', array('userid' => $userids));

		// delete empty operations
		$delOperationids = array();
		$dbOperations = DBselect(
			'SELECT DISTINCT o.operationid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', $operationids).
				' AND NOT EXISTS(SELECT om.opmessage_usrid FROM opmessage_usr om WHERE om.operationid=o.operationid)'
		);
		while ($dbOperation = DBfetch($dbOperations)) {
			$delOperationids[$dbOperation['operationid']] = $dbOperation['operationid'];
		}

		DB::delete('operations', array('operationid' => $delOperationids));
		DB::delete('media', array('userid' => $userids));
		DB::delete('profiles', array('userid' => $userids));
		DB::delete('users_groups', array('userid' => $userids));
		DB::delete('users', array('userid' => $userids));

		return array('userids' => $userids);
	}

	/**
	 * Add Medias for User
	 *
	 * @param array $mediaData
	 * @param string $mediaData['userid']
	 * @param string $mediaData['medias']['mediatypeid']
	 * @param string $mediaData['medias']['address']
	 * @param int $mediaData['medias']['severity']
	 * @param int $mediaData['medias']['active']
	 * @param string $mediaData['medias']['period']
	 * @return boolean
	 */
	public function addMedia($mediaData) {
		$medias = zbx_toArray($mediaData['medias']);
		$users = zbx_toArray($mediaData['users']);
		$mediaids = array();
		$userids = array();

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only Zabbix Admins can add user media.'));
		}

		$timePeriodValidator = new CTimePeriodValidator();
		foreach ($users as $user) {
			$userids[] = $user['userid'];

			foreach ($medias as $media) {
				if (!$timePeriodValidator->validate($media['period'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $timePeriodValidator->getError());
				}

				$mediaid = get_dbid('media', 'mediaid');

				$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' VALUES ('.$mediaid.','.$user['userid'].','.$media['mediatypeid'].','.
									zbx_dbstr($media['sendto']).','.$media['active'].','.$media['severity'].','.
									zbx_dbstr($media['period']).')';
				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
				}
				$mediaids[] = $mediaid;
			}
		}

		return array('mediaids' => $mediaids);
	}

	/**
	 * Delete User Medias
	 *
	 * @param array $mediaids
	 * @return boolean
	 */
	public function deleteMedia($mediaids) {
		$mediaids = zbx_toArray($mediaids);

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only Zabbix Admins can remove user media.'));
		}

		$sql = 'DELETE FROM media WHERE '.dbConditionInt('mediaid', $mediaids);
		if (!DBexecute($sql)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');
		}

		return array('mediaids' => $mediaids);
	}

	/**
	 * Update Medias for User
	 *
	 * @param array $mediaData
	 * @param array $mediaData['users']
	 * @param array $mediaData['users']['userid']
	 * @param array $mediaData['medias']
	 * @param string $mediaData['medias']['mediatypeid']
	 * @param string $mediaData['medias']['sendto']
	 * @param int $mediaData['medias']['severity']
	 * @param int $mediaData['medias']['active']
	 * @param string $mediaData['medias']['period']
	 * @return boolean
	 */
	public function updateMedia($mediaData) {
		$newMedias = zbx_toArray($mediaData['medias']);
		$users = zbx_toArray($mediaData['users']);

		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Zabbix Admins can change user media.'));
		}

		$updMedias = array();
		$delMedias = array();

		$userids = zbx_objectValues($users, 'userid');
		$result = DBselect(
			'SELECT m.mediaid'.
			' FROM media m'.
			' WHERE '.dbConditionInt('userid', $userids)
		);
		while ($media = DBfetch($result)) {
			$delMedias[$media['mediaid']] = $media;
		}

		foreach ($newMedias as $mnum => $media) {
			if (!isset($media['mediaid'])) {
				continue;
			}

			if (isset($delMedias[$media['mediaid']])) {
				$updMedias[$media['mediaid']] = $newMedias[$mnum];
			}

			unset($newMedias[$mnum]);
			unset($delMedias[$media['mediaid']]);
		}

		// delete
		if (!empty($delMedias)) {
			$mediaids = zbx_objectValues($delMedias, 'mediaid');
			$result = $this->deleteMedia($mediaids);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete user media.'));
			}
		}

		// update
		$timePeriodValidator = new CTimePeriodValidator();
		foreach ($updMedias as $media) {
			if (!$timePeriodValidator->validate($media['period'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $timePeriodValidator->getError());
			}

			$result = DBexecute(
				'UPDATE media'.
				' SET mediatypeid='.$media['mediatypeid'].','.
					' sendto='.zbx_dbstr($media['sendto']).','.
					' active='.$media['active'].','.
					' severity='.$media['severity'].','.
					' period='.zbx_dbstr($media['period']).
				' WHERE mediaid='.$media['mediaid']
			);
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update user media.'));
			}
		}

		// create
		if (!empty($newMedias)) {
			$result = $this->addMedia(array('users' => $users, 'medias' => $newMedias));
			if (!$result) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot insert user media.'));
			}
		}

		return array('userids'=>$userids);
	}

	// ******************************************************************************
	// LOGIN Methods
	// ******************************************************************************
	public function ldapLogin($user) {
		$cnf = isset($user['cnf']) ? $user['cnf'] : null;

		if (is_null($cnf)) {
			$config = select_config();
			foreach ($config as $id => $value) {
				if (zbx_strpos($id, 'ldap_') !== false) {
					$cnf[str_replace('ldap_', '', $id)] = $config[$id];
				}
			}
		}

		if (!function_exists('ldap_connect')) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Probably php-ldap module is missing.'));
		}

		$ldap = new CLdap($cnf);
		$ldap->connect();

		if ($ldap->checkPass($user['user'], $user['password'])) {
			return true;
		}
		else {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect.'));
		}
	}

	private function dbLogin($user) {
		global $ZBX_LOCALNODEID;

		$login = DBfetch(DBselect(
			'SELECT u.userid'.
			' FROM users u'.
			' WHERE u.alias='.zbx_dbstr($user['user']).
				' AND u.passwd='.zbx_dbstr(md5($user['password'])).
				' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID)
		));
		if ($login) {
			return true;
		}
		else {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect.'));
		}
	}

	public function logout() {
		global $ZBX_LOCALNODEID;

		$sessionId = CWebUser::$data['sessionid'];

		$session = DBfetch(DBselect(
			'SELECT s.userid'.
			' FROM sessions s'.
			' WHERE s.sessionid='.zbx_dbstr($sessionId).
				' AND s.status='.ZBX_SESSION_ACTIVE.
				' AND '.DBin_node('s.userid', $ZBX_LOCALNODEID)
		));
		if (!$session) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot logout.'));
		}

		DBexecute('DELETE FROM sessions WHERE status='.ZBX_SESSION_PASSIVE.' AND userid='.zbx_dbstr($session['userid']));
		DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid='.zbx_dbstr($sessionId));

		return true;
	}

	/**
	 * Login user
	 *
	 * @param array $user
	 * @param array $user['user'] User alias
	 * @param array $user['password'] User password
	 * @return string session ID
	 */
	public function login($user) {
		global $ZBX_LOCALNODEID;

		$name = $user['user'];
		$password = md5($user['password']);

		$userInfo = DBfetch(DBselect(
			'SELECT u.userid,u.attempt_failed,u.attempt_clock,u.attempt_ip'.
			' FROM users u'.
			' WHERE u.alias='.zbx_dbstr($name).
				' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID)
		));
		if (!$userInfo) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect.'));
		}

		// check if user is blocked
		if ($userInfo['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS) {
			if ((time() - $userInfo['attempt_clock']) < ZBX_LOGIN_BLOCK) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Account is blocked for %s seconds', (ZBX_LOGIN_BLOCK - (time() - $userInfo['attempt_clock']))));
			}

			DBexecute('UPDATE users SET attempt_clock='.time().' WHERE alias='.zbx_dbstr($name));
		}

		// check system permissions
		if (!check_perm2system($userInfo['userid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));
		}

		$dbAccess = DBfetch(DBselect(
			'SELECT MAX(g.gui_access) AS gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.$userInfo['userid'].
				' AND g.usrgrpid=ug.usrgrpid'
		));
		if (!zbx_empty($dbAccess['gui_access'])) {
			$guiAccess = $dbAccess['gui_access'];
		}
		else {
			$guiAccess = GROUP_GUI_ACCESS_SYSTEM;
		}

		switch ($guiAccess) {
			case GROUP_GUI_ACCESS_INTERNAL:
				$authType = ZBX_AUTH_INTERNAL;
				break;
			case GROUP_GUI_ACCESS_DISABLED:
				/* fall through */
			case GROUP_GUI_ACCESS_SYSTEM:
				$config = select_config();
				$authType = $config['authentication_type'];
				break;
		}

		try {
			switch ($authType) {
				case ZBX_AUTH_LDAP:
					$this->ldapLogin($user);
					break;
				case ZBX_AUTH_INTERNAL:
					$this->dbLogin($user);
					break;
				case ZBX_AUTH_HTTP:
			}
		}
		catch (APIException $e) {
			$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];
			$userInfo['attempt_failed']++;

			DBexecute(
				'UPDATE users'.
				' SET attempt_failed='.$userInfo['attempt_failed'].','.
					' attempt_clock='.time().','.
					' attempt_ip='.zbx_dbstr($ip).
				' WHERE userid='.$userInfo['userid']
			);

			add_audit(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, _s('Login failed "%s".', $name));
			self::exception(ZBX_API_ERROR_PARAMETERS, $e->getMessage());
		}

		// start session
		$sessionid = md5(time().$password.$name.rand(0, 10000000));
		DBexecute('INSERT INTO sessions (sessionid,userid,lastaccess,status) VALUES ('.zbx_dbstr($sessionid).','.$userInfo['userid'].','.time().','.ZBX_SESSION_ACTIVE.')');

		add_audit(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, _s('Correct login "%s".', $name));

		$userData = $this->_getUserData($userInfo['userid']);
		$userData['sessionid'] = $sessionid;
		$userData['gui_access'] = $guiAccess;
		$userData['userid'] = $userInfo['userid'];

		if ($userInfo['attempt_failed']) {
			DBexecute('UPDATE users SET attempt_failed=0 WHERE userid='.$userInfo['userid']);
		}

		CWebUser::$data = self::$userData = $userData;

		return isset($user['userData']) ? $userData : $userData['sessionid'];
	}

	/**
	 * Check if session ID is authenticated
	 *
	 * @param string $sessionid     session ID
	 *
	 * @return array    an array of user data
	 */
	public function checkAuthentication($sessionid) {
		global $ZBX_LOCALNODEID;

		// access DB only once per page load
		if (!is_null(self::$userData)) {
			return self::$userData;
		}

		$time = time();
		$userInfo = DBfetch(DBselect(
			'SELECT u.userid,u.autologout,s.lastaccess'.
			' FROM sessions s,users u'.
			' WHERE s.sessionid='.zbx_dbstr($sessionid).
				' AND s.status='.ZBX_SESSION_ACTIVE.
				' AND s.userid=u.userid'.
				' AND ((s.lastaccess+u.autologout>'.$time.') OR (u.autologout=0))'.
				' AND '.DBin_node('u.userid', $ZBX_LOCALNODEID)
		));

		if (!$userInfo) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		// don't check permissions on the same second
		if ($time != $userInfo['lastaccess']) {
			if (!check_perm2system($userInfo['userid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));
			}

			if ($userInfo['autologout'] > 0) {
				DBexecute('DELETE FROM sessions WHERE userid='.$userInfo['userid'].' AND lastaccess<'.(time() - $userInfo['autologout']));
			}

			DBexecute('UPDATE sessions SET lastaccess='.time().' WHERE userid='.$userInfo['userid'].' AND sessionid='.zbx_dbstr($sessionid));
		}

		$dbAccess = DBfetch(DBselect(
			'SELECT MAX(g.gui_access) AS gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.$userInfo['userid'].
				' AND g.usrgrpid=ug.usrgrpid'
		));
		if (!zbx_empty($dbAccess['gui_access'])) {
			$guiAccess = $dbAccess['gui_access'];
		}
		else {
			$guiAccess = GROUP_GUI_ACCESS_SYSTEM;
		}

		$userData = $this->_getUserData($userInfo['userid']);
		$userData['sessionid'] = $sessionid;
		$userData['gui_access'] = $guiAccess;

		CWebUser::$data = self::$userData = $userData;

		return $userData;
	}

	private function _getUserData($userid) {
		global $ZBX_LOCALNODEID, $ZBX_NODES;

		$userData = DBfetch(DBselect(
			'SELECT u.userid,u.alias,u.name,u.surname,u.url,u.autologin,u.autologout,u.lang,u.refresh,u.type,'.
			' u.theme,u.attempt_failed,u.attempt_ip,u.attempt_clock,u.rows_per_page'.
			' FROM users u'.
			' WHERE u.userid='.$userid
		));

		$userData['debug_mode'] = (bool) DBfetch(DBselect(
			'SELECT ug.userid'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE ug.userid='.$userid.
			' AND g.usrgrpid=ug.usrgrpid'.
			' AND g.debug_mode='.GROUP_DEBUG_MODE_ENABLED
		));

		$userData['userip'] = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
					? $_SERVER['HTTP_X_FORWARDED_FOR']
					: $_SERVER['REMOTE_ADDR'];

		if (isset($ZBX_NODES[$ZBX_LOCALNODEID])) {
			$userData['node'] = $ZBX_NODES[$ZBX_LOCALNODEID];
		}
		else {
			$userData['node'] = array();
			$userData['node']['name'] = '- unknown -';
			$userData['node']['nodeid'] = $ZBX_LOCALNODEID;
		}

		return $userData;
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'userids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'userids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if (!isset($options['usrgrpids'])) {
			$sqlParts = parent::applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$userids = zbx_objectValues($result, 'userid');

		// adding usergroups
		if (!is_null($options['selectUsrgrps']) && str_in_array($options['selectUsrgrps'], array(API_OUTPUT_REFER, API_OUTPUT_EXTEND))) {
			foreach ($result as &$user) {
				$user['usrgrps'] = array();
			}
			unset($user);

			$usrgrps = API::UserGroup()->get(array(
				'output' => $options['selectUsrgrps'],
				'userids' => $userids,
				'preservekeys' => true
			));
			foreach ($usrgrps as $usrgrp) {
				$uusers = $usrgrp['users'];
				unset($usrgrp['users']);
				$usrgrps = $this->unsetExtraFields('usrgrp', $usrgrps, $options['selectUsrgrps']);

				foreach ($uusers as $user) {
					$result[$user['userid']]['usrgrps'][] = $usrgrp;
				}
			}
		}

		// adding medias
		if (!is_null($options['selectMedias']) && str_in_array($options['selectMedias'], array(API_OUTPUT_REFER, API_OUTPUT_EXTEND))) {
			foreach ($result as &$user) {
				$user['medias'] = array();
			}
			unset($user);

			$userMedias = API::UserMedia()->get(array(
				'output' => $options['selectMedias'],
				'userids' => $userids,
				'preservekeys' => true
			));
			$userMedias = $this->unsetExtraFields('media', $userMedias, $options['selectMedias']);

			foreach ($userMedias as $mediaid => $media) {
				$result[$media['userid']]['medias'][] = $media;
			}
		}

		// adding media types
		if (!is_null($options['selectMediatypes'])) {
			foreach ($result as &$user) {
				$user['mediatypes'] = array();
			}
			unset($user);

			$mediatypes = API::Mediatype()->get(array(
				'output' => $options['selectMediatypes'],
				'userids' => $userids,
				'selectUsers' => API_OUTPUT_REFER,
				'preservekeys' => true
			));
			foreach ($mediatypes as $mediatype) {
				$users = $mediatype['users'];
				unset($mediatype['users']);
				$mediatype = $this->unsetExtraFields('media_type', $mediatype, $options['selectMediatypes']);

				foreach ($users as $user) {
					if (!empty($result[$user['userid']])) {
						$result[$user['userid']]['mediatypes'][] = $mediatype;
					}
				}
			}
		}

		return $result;
	}
}
