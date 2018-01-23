<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with user groups.
 */
class CUserGroup extends CApiService {

	protected $tableName = 'usrgrp';
	protected $tableAlias = 'g';
	protected $sortColumns = ['usrgrpid', 'name'];

	/**
	 * Get user groups.
	 *
	 * @param array  $options
	 * @param array  $options['usrgrpids']
	 * @param array  $options['userids']
	 * @param bool   $options['status']
	 * @param bool   $options['with_gui_access']
	 * @param bool   $options['selectUsers']
	 * @param int    $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['usrgrp' => 'g.usrgrpid'],
			'from'		=> ['usrgrp' => 'usrgrp g'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'usrgrpids'					=> null,
			'userids'					=> null,
			'status'					=> null,
			'with_gui_access'			=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'editable'					=> false,
			'output'					=> API_OUTPUT_EXTEND,
			'selectUsers'				=> null,
			'selectRights'				=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		// permissions
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable']) {
				$sqlParts['where'][] = 'g.usrgrpid IN ('.
					'SELECT uug.usrgrpid'.
					' FROM users_groups uug'.
					' WHERE uug.userid='.self::$userData['userid'].
				')';
			}
			else {
				return [];
			}
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['where'][] = dbConditionInt('g.usrgrpid', $options['usrgrpids']);
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.userid', $options['userids']);
			$sqlParts['where']['gug'] = 'g.usrgrpid=ug.usrgrpid';
		}

		// status
		if (!is_null($options['status'])) {
			$sqlParts['where'][] = 'g.users_status='.zbx_dbstr($options['status']);
		}

		// with_gui_access
		if (!is_null($options['with_gui_access'])) {
			$sqlParts['where'][] = 'g.gui_access='.GROUP_GUI_ACCESS_ENABLED;
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('usrgrp g', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('usrgrp g', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($usrgrp = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $usrgrp['rowscount'];
			}
			else {
				$result[$usrgrp['usrgrpid']] = $usrgrp;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array  $usrgrps
	 *
	 * @return array
	 */
	public function create(array $usrgrps) {
		$this->validateCreate($usrgrps);

		$ins_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			unset($usrgrp['rights'], $usrgrp['userids']);
			$ins_usrgrps[] = $usrgrp;
		}
		$usrgrpids = DB::insert('usrgrp', $ins_usrgrps);

		foreach ($usrgrps as $index => &$usrgrp) {
			$usrgrp['usrgrpid'] = $usrgrpids[$index];
		}
		unset($usrgrp);

		$this->updateRights($usrgrps, __FUNCTION__);
		$this->updateUsersGroups($usrgrps, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_USER_GROUP, $usrgrps);

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * @param array $usrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$usrgrps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create user groups.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('usrgrp', 'name')],
			'debug_mode' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED])],
			'gui_access' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_DISABLED])],
			'users_status' =>	['type' => API_INT32, 'in' => implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED])],
			'rights' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'userids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($usrgrps, 'name'));
		$this->checkUsers($usrgrps);
		$this->checkHimself($usrgrps, __FUNCTION__);
		$this->checkHostGroups($usrgrps);
	}

	/**
	 * @param array  $usrgrps
	 *
	 * @return array
	 */
	public function update($usrgrps) {
		$this->validateUpdate($usrgrps, $db_usrgrps);

		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$db_usrgrp = $db_usrgrps[$usrgrp['usrgrpid']];

			$upd_usrgrp = [];

			if (array_key_exists('name', $usrgrp) && $usrgrp['name'] !== $db_usrgrp['name']) {
				$upd_usrgrp['name'] = $usrgrp['name'];
			}
			if (array_key_exists('debug_mode', $usrgrp) && $usrgrp['debug_mode'] != $db_usrgrp['debug_mode']) {
				$upd_usrgrp['debug_mode'] = $usrgrp['debug_mode'];
			}
			if (array_key_exists('gui_access', $usrgrp) && $usrgrp['gui_access'] != $db_usrgrp['gui_access']) {
				$upd_usrgrp['gui_access'] = $usrgrp['gui_access'];
			}
			if (array_key_exists('users_status', $usrgrp) && $usrgrp['users_status'] != $db_usrgrp['users_status']) {
				$upd_usrgrp['users_status'] = $usrgrp['users_status'];
			}

			if ($upd_usrgrp) {
				$upd_usrgrps[] = [
					'values' => $upd_usrgrp,
					'where' => ['usrgrpid' => $usrgrp['usrgrpid']]
				];
			}
		}

		if ($upd_usrgrps) {
			DB::update('usrgrp', $upd_usrgrps);
		}

		$this->updateRights($usrgrps, __FUNCTION__);
		$this->updateUsersGroups($usrgrps, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER_GROUP, $usrgrps, $db_usrgrps);

		return ['usrgrpids'=> zbx_objectValues($usrgrps, 'usrgrpid')];
	}

	/**
	 * @param array $usrgrps
	 * @param array $db_usrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$usrgrps, array &$db_usrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update user groups.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['usrgrpid'], ['name']], 'fields' => [
			'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('usrgrp', 'name')],
			'debug_mode' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED])],
			'gui_access' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_DISABLED])],
			'users_status' =>	['type' => API_INT32, 'in' => implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED])],
			'rights' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'userids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check user group names.
		$db_usrgrps = DB::select('usrgrp', [
			'output' => ['usrgrpid', 'name', 'debug_mode', 'gui_access', 'users_status'],
			'usrgrpids' => zbx_objectValues($usrgrps, 'usrgrpid'),
			'preservekeys' => true
		]);

		$names = [];

		foreach ($usrgrps as $usrgrp) {
			// Check if this user group exists.
			if (!array_key_exists($usrgrp['usrgrpid'], $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_usrgrp = $db_usrgrps[$usrgrp['usrgrpid']];

			if (array_key_exists('name', $usrgrp) && $usrgrp['name'] !== $db_usrgrp['name']) {
				$names[] = $usrgrp['name'];
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}
		$this->checkUsers($usrgrps);
		$this->checkHimself($usrgrps, __FUNCTION__, $db_usrgrps);
		$this->checkUsersWithoutGroups($usrgrps);
		$this->checkHostGroups($usrgrps);
	}

	/**
	 * Check for duplicated user groups.
	 *
	 * @param array  $names
	 *
	 * @throws APIException  if user group already exists.
	 */
	private function checkDuplicates(array $names) {
		$db_usrgrps = DB::select('usrgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_usrgrps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" already exists.', $db_usrgrps[0]['name']));
		}
	}

	/**
	 * Check for valid users.
	 *
	 * @param array  $usrgrps
	 * @param array  $usrgrps[]['userids']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkUsers(array $usrgrps) {
		$userids = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('userids', $usrgrp)) {
				foreach ($usrgrp['userids'] as $userid) {
					$userids[$userid] = true;
				}
			}
		}

		if (!$userids) {
			return;
		}

		$userids = array_keys($userids);

		$db_users = DB::select('users', [
			'output' => [],
			'userids' => $userids,
			'preservekeys' => true
		]);

		foreach ($userids as $userid) {
			if (!array_key_exists($userid, $db_users)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User with ID "%1$s" is not available.', $userid));
			}
		}
	}

	/**
	 * Check for valid host grups.
	 *
	 * @param array  $usrgrps
	 * @param array  $usrgrps[]['rights']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkHostGroups(array $usrgrps) {
		$groupids = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('rights', $usrgrp)) {
				foreach ($usrgrp['rights'] as $right) {
					$groupids[$right['id']] = true;
				}
			}
		}

		if (!$groupids) {
			return;
		}

		$groupids = array_keys($groupids);

		$db_groups = DB::select('groups', [
			'output' => [],
			'groupids' => $groupids,
			'preservekeys' => true
		]);

		foreach ($groupids as $groupid) {
			if (!array_key_exists($groupid, $db_groups)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group with ID "%1$s" is not available.', $groupid));
			}
		}
	}

	/**
	 * Auxiliary function for checkHimself().
	 * Returns true if user group has GROUP_GUI_ACCESS_DISABLED or GROUP_STATUS_DISABLED states.
	 *
	 * @param array  $usrgrp
	 * @param string $method
	 * @param array  $db_usrgrps
	 *
	 * @return bool
	 */
	private static function userGroupDisabled(array $usrgrp, $method, array $db_usrgrps = null) {
		$gui_access = array_key_exists('gui_access', $usrgrp)
			? $usrgrp['gui_access']
			: ($method === 'validateCreate' ? GROUP_GUI_ACCESS_SYSTEM : $db_usrgrps[$usrgrp['usrgrpid']]['gui_access']);
		$users_status = array_key_exists('users_status', $usrgrp)
			? $usrgrp['users_status']
			: ($method === 'validateCreate' ? GROUP_STATUS_ENABLED : $db_usrgrps[$usrgrp['usrgrpid']]['users_status']);

		return ($gui_access == GROUP_GUI_ACCESS_DISABLED || $users_status == GROUP_STATUS_DISABLED);
	}

	/**
	 * Additional check to exclude an opportunity to deactivate himself.
	 *
	 * @param array  $usrgrps
	 * @param string $method
	 * @param array  $db_usrgrps
	 *
	 * @throws APIException
	 */
	private function checkHimself(array $usrgrps, $method, array $db_usrgrps = null) {
		if ($method === 'validateUpdate') {
			$groups_users = [];

			foreach ($usrgrps as $usrgrp) {
				if (self::userGroupDisabled($usrgrp, $method, $db_usrgrps) && !array_key_exists('userids', $usrgrp)) {
					$groups_users[$usrgrp['usrgrpid']] = [];
				}
			}

			if ($groups_users) {
				$db_users_groups = DB::select('users_groups', [
					'output' => ['usrgrpid', 'userid'],
					'filter' => ['usrgrpid' => array_keys($groups_users)]
				]);

				foreach ($db_users_groups as $db_user_group) {
					$groups_users[$db_user_group['usrgrpid']][] = $db_user_group['userid'];
				}

				foreach ($usrgrps as &$usrgrp) {
					if (self::userGroupDisabled($usrgrp, $method, $db_usrgrps)
							&& !array_key_exists('userids', $usrgrp)) {
						$usrgrp['userids'] = $groups_users[$usrgrp['usrgrpid']];
					}
				}
				unset($usrgrp);
			}
		}

		foreach ($usrgrps as $usrgrp) {
			if (self::userGroupDisabled($usrgrp, $method, $db_usrgrps)
					&& uint_in_array(self::$userData['userid'], $usrgrp['userids'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('User cannot add himself to a disabled group or a group with disabled GUI access.')
				);
			}
		}
	}

	/**
	 * Check to exclude an opportunity to leave user without user groups.
	 *
	 * @param array  $usrgrps
	 * @param array  $usrgrps[]['usrgrpid']
	 * @param array  $usrgrps[]['userids']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkUsersWithoutGroups(array $usrgrps) {
		$users_groups = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('userids', $usrgrp)) {
				$users_groups[$usrgrp['usrgrpid']] = [];

				foreach ($usrgrp['userids'] as $userid) {
					$users_groups[$usrgrp['usrgrpid']][$userid] = true;
				}
			}
		}

		if (!$users_groups) {
			return;
		}

		$db_users_groups = DB::select('users_groups', [
			'output' => ['usrgrpid', 'userid'],
			'filter' => ['usrgrpid' => array_keys($users_groups)]
		]);

		$ins_userids = [];
		$del_userids = [];

		foreach ($db_users_groups as $db_user_group) {
			if (array_key_exists($db_user_group['userid'], $users_groups[$db_user_group['usrgrpid']])) {
				unset($users_groups[$db_user_group['usrgrpid']][$db_user_group['userid']]);
			}
			else {
				if (!array_key_exists($db_user_group['userid'], $del_userids)) {
					$del_userids[$db_user_group['userid']] = 0;
				}
				$del_userids[$db_user_group['userid']]++;
			}
		}

		foreach ($users_groups as $usrgrpid => $userids) {
			foreach (array_keys($userids) as $userid) {
				$ins_userids[$userid] = true;
			}
		}

		$del_userids = array_diff_key($del_userids, $ins_userids);

		if (!$del_userids) {
			return;
		}

		$db_users = DBselect(
			'SELECT u.userid,u.alias,count(ug.usrgrpid) as usrgrp_num'.
			' FROM users u,users_groups ug'.
			' WHERE u.userid=ug.userid'.
				' AND '.dbConditionInt('u.userid', array_keys($del_userids)).
			' GROUP BY u.userid,u.alias'
		);

		while ($db_user = DBfetch($db_users)) {
			if ($db_user['usrgrp_num'] == $del_userids[$db_user['userid']]) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" cannot be without user group.', $db_user['alias'])
				);
			}
		}
	}

	/**
	 * Update table "rights".
	 *
	 * @param array  $usrgrps
	 * @param string $method
	 */
	private function updateRights(array $usrgrps, $method) {
		$rights = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('rights', $usrgrp)) {
				$rights[$usrgrp['usrgrpid']] = [];

				foreach ($usrgrp['rights'] as $right) {
					$rights[$usrgrp['usrgrpid']][$right['id']] = $right['permission'];
				}
			}
		}

		if (!$rights) {
			return;
		}

		$db_rights = ($method === 'update')
			? DB::select('rights', [
				'output' => ['rightid', 'groupid', 'id', 'permission'],
				'filter' => ['groupid' => array_keys($rights)]
			])
			: [];

		$ins_rights = [];
		$upd_rights = [];
		$del_rightids = [];

		foreach ($db_rights as $db_right) {
			if (array_key_exists($db_right['groupid'], $rights)
					&& array_key_exists($db_right['id'], $rights[$db_right['groupid']])) {
				if ($db_right['permission'] != $rights[$db_right['groupid']][$db_right['id']]) {
					$upd_rights[] = [
						'values' => ['permission' => $rights[$db_right['groupid']][$db_right['id']]],
						'where' => ['rightid' => $db_right['rightid']],
					];
				}
				unset($rights[$db_right['groupid']][$db_right['id']]);
			}
			else {
				$del_rightids[] = $db_right['rightid'];
			}
		}

		foreach ($rights as $groupid => $usrgrp_rights) {
			foreach ($usrgrp_rights as $id => $permission) {
				$ins_rights[] = [
					'groupid' => $groupid,
					'id' => $id,
					'permission' => $permission
				];
			}
		}

		if ($ins_rights) {
			DB::insertBatch('rights', $ins_rights);
		}

		if ($upd_rights) {
			DB::update('rights', $upd_rights);
		}

		if ($del_rightids) {
			DB::delete('rights', ['rightid' => $del_rightids]);
		}
	}

	/**
	 * Update table "users_groups".
	 *
	 * @param array  $usrgrps
	 * @param string $method
	 */
	private function updateUsersGroups(array $usrgrps, $method) {
		$users_groups = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('userids', $usrgrp)) {
				$users_groups[$usrgrp['usrgrpid']] = [];

				foreach ($usrgrp['userids'] as $userid) {
					$users_groups[$usrgrp['usrgrpid']][$userid] = true;
				}
			}
		}

		if (!$users_groups) {
			return;
		}

		$db_users_groups = ($method === 'update')
			? DB::select('users_groups', [
				'output' => ['id', 'usrgrpid', 'userid'],
				'filter' => ['usrgrpid' => array_keys($users_groups)]
			])
			: [];

		$ins_users_groups = [];
		$del_ids = [];

		foreach ($db_users_groups as $db_user_group) {
			if (array_key_exists($db_user_group['userid'], $users_groups[$db_user_group['usrgrpid']])) {
				unset($users_groups[$db_user_group['usrgrpid']][$db_user_group['userid']]);
			}
			else {
				$del_ids[] = $db_user_group['id'];
			}
		}

		foreach ($users_groups as $usrgrpid => $userids) {
			foreach (array_keys($userids) as $userid) {
				$ins_users_groups[] = [
					'usrgrpid' => $usrgrpid,
					'userid' => $userid
				];
			}
		}

		if ($ins_users_groups) {
			DB::insertBatch('users_groups', $ins_users_groups);
		}

		if ($del_ids) {
			DB::delete('users_groups', ['id' => $del_ids]);
		}
	}

	/**
	 * @param array $usrgrpids
	 *
	 * @return array
	 */
	public function delete(array $usrgrpids) {
		$this->validateDelete($usrgrpids, $db_usrgrps);

		DB::delete('rights', ['groupid' => $usrgrpids]);
		DB::delete('users_groups', ['usrgrpid' => $usrgrpids]);
		DB::delete('usrgrp', ['usrgrpid' => $usrgrpids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, $db_usrgrps);

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * @throws APIException
	 *
	 * @param array $usrgrpids
	 * @param array $db_usrgrps
	 */
	protected function validateDelete(array &$usrgrpids, array &$db_usrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrpids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_usrgrps = DB::select('usrgrp', [
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		$usrgrps = [];

		foreach ($usrgrpids as $usrgrpid) {
			// Check if this user group exists.
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$usrgrps[] = [
				'usrgrpid' => $usrgrpid,
				'userids' => []
			];
		}

		// Check if user groups are used in actions.
		$db_actions = DBselect(
			'SELECT a.name,og.usrgrpid'.
			' FROM opmessage_grp og,operations o,actions a'.
			' WHERE og.operationid=o.operationid'.
				' AND o.actionid=a.actionid'.
				' AND '.dbConditionInt('og.usrgrpid', $usrgrpids),
			1
		);

		if ($db_action = DBfetch($db_actions)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" is used in "%2$s" action.',
				$db_usrgrps[$db_action['usrgrpid']]['name'], $db_action['name']
			));
		}

		// Check if user groups are used in scripts.
		$db_scripts = DB::select('scripts', [
			'output' => ['name', 'usrgrpid'],
			'filter' => ['usrgrpid' => $usrgrpids],
			'limit' => 1
		]);

		if ($db_scripts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" is used in script "%2$s".',
				$db_usrgrps[$db_scripts[0]['usrgrpid']]['name'], $db_scripts[0]['name']
			));
		}

		// Check if user group are used in config.
		$config = select_config();

		if (array_key_exists($config['alert_usrgrpid'], $db_usrgrps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'User group "%1$s" is used in configuration for database down messages.',
				$db_usrgrps[$config['alert_usrgrpid']]['name']
			));
		}

		$this->checkUsersWithoutGroups($usrgrps);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'usrgrpid', 'userid', 'users_groups');

			$dbUsers = API::User()->get([
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'getAccess' => ($options['selectUsers'] == API_OUTPUT_EXTEND) ? true : null,
				'preservekeys' => true
			]);

			$result = $relationMap->mapMany($result, $dbUsers, 'users');
		}

		// adding usergroup rights
		if ($options['selectRights'] !== null && $options['selectRights'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'groupid', 'rightid', 'rights');

			if (is_array($options['selectRights'])) {
				$pk_field = $this->pk('rights');

				$output_fields = [
					$pk_field => $this->fieldId($pk_field, 'r')
				];

				foreach ($options['selectRights'] as $field) {
					if ($this->hasField($field, 'rights')) {
						$output_fields[$field] = $this->fieldId($field, 'r');
					}
				}

				$output_fields = implode(',', $output_fields);
			}
			else {
				$output_fields = 'r.*';
			}

			$db_rights = DBfetchArray(DBselect(
				'SELECT '.$output_fields.
				' FROM rights r'.
				' WHERE '.dbConditionInt('r.rightid', $relationMap->getRelatedIds()).
					((self::$userData['type'] == USER_TYPE_SUPER_ADMIN) ? '' : ' AND r.permission>'.PERM_DENY)
			));
			$db_rights = zbx_toHash($db_rights, 'rightid');

			foreach ($db_rights as &$db_right) {
				unset($db_right['rightid'], $db_right['groupid']);
			}
			unset($db_right);

			$result = $relationMap->mapMany($result, $db_rights, 'rights');
		}

		return $result;
	}
}
