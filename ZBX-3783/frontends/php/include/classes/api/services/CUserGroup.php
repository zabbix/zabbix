<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
		$userType = self::$userData['type'];

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
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'editable'					=> null,
			'output'					=> API_OUTPUT_EXTEND,
			'selectUsers'				=> null,
			'selectRights'				=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		// permissions
		if ($userType != USER_TYPE_SUPER_ADMIN) {
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

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array  $usrgrps
	 *
	 * @return array
	 */
	public function create($usrgrps) {
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

		add_audit_bulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_USER_GROUP, $usrgrps);

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
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
			'debug_mode' =>		['type' => API_INT32, 'in' => [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED]],
			'gui_access' =>		['type' => API_INT32, 'in' => [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_DISABLED]],
			'users_status' =>	['type' => API_INT32, 'in' => [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]],
			'rights' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => [PERM_DENY, PERM_READ, PERM_READ_WRITE]]
			]],
			'userids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($usrgrps, 'name'));
		$this->checkHimself($usrgrps);
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

		add_audit_bulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER_GROUP, $usrgrps, $db_usrgrps);

		return ['usrgrpids'=> zbx_objectValues($usrgrps, 'usrgrpid')];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $usrgrps
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
			'debug_mode' =>		['type' => API_INT32, 'in' => [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED]],
			'gui_access' =>		['type' => API_INT32, 'in' => [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_DISABLED]],
			'users_status' =>	['type' => API_INT32, 'in' => [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]],
			'rights' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => [PERM_DENY, PERM_READ, PERM_READ_WRITE]]
			]],
			'userids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check user group names.
		$db_usrgrps = API::getApiService()->select('usrgrp', [
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
		$this->checkHimself($usrgrps, $db_usrgrps);
		$this->checkUsersWithoutGroups($usrgrps);
	}

	/**
	 * Check for duplicated user groups.
	 *
	 * @param array  $names
	 *
	 * @throws APIException  if user group already exists.
	 */
	private function checkDuplicates($names) {
		$db_usrgrps = API::getApiService()->select('usrgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_usrgrps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" already exists.', $db_usrgrps[0]['name']));
		}
	}

	/**
	 * Additional check to exclude an opportunity to deactivate himself.
	 *
	 * @param array  $usrgrps
	 * @param array  $db_usrgrps
	 *
	 * @throws APIException
	 */
	private function checkHimself(array $usrgrps, array $db_usrgrps = null) {
		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('userids', $usrgrp) && uint_in_array(self::$userData['userid'], $usrgrp['userids'])) {
				$gui_access = array_key_exists('gui_access', $usrgrp)
					? $usrgrp['gui_access']
					: ($db_usrgrps === null ? GROUP_GUI_ACCESS_SYSTEM : $db_usrgrps[$usrgrp['usrgrpid']]['gui_access']);
				$users_status = array_key_exists('users_status', $usrgrp)
					? $usrgrp['users_status']
					: ($db_usrgrps === null ? GROUP_STATUS_ENABLED : $db_usrgrps[$usrgrp['usrgrpid']]['users_status']);

				if ($gui_access == GROUP_GUI_ACCESS_DISABLED || $users_status == GROUP_STATUS_DISABLED) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_('User cannot add himself to a disabled group or a group with disabled GUI access.')
					);
				}
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

		$db_users_groups = API::getApiService()->select('users_groups', [
			'output' => ['usrgrpid', 'userid'],
			'filter' => ['usrgrpid' => array_keys($users_groups)]
		]);

		$ins_userids = [];
		$del_userids = [];

		foreach ($db_users_groups as $index => $db_user_group) {
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
	private function updateRights($usrgrps, $method) {
		$rights = [];

		foreach ($usrgrps as $index => $usrgrp) {
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
			? API::getApiService()->select('rights', [
				'output' => ['rightid', 'groupid', 'id', 'permission'],
				'filter' => ['groupid' => array_keys($rights)]
			])
			: [];

		$ins_rights = [];
		$upd_rights = [];
		$del_rightids = [];

		foreach ($db_rights as $index => $db_right) {
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
	private function updateUsersGroups($usrgrps, $method) {
		$users_groups = [];

		foreach ($usrgrps as $index => $usrgrp) {
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
			? API::getApiService()->select('users_groups', [
				'output' => ['id', 'usrgrpid', 'userid'],
				'filter' => ['usrgrpid' => array_keys($users_groups)]
			])
			: [];

		$ins_users_groups = [];
		$del_ids = [];

		foreach ($db_users_groups as $index => $db_user_group) {
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
	 * @deprecated	As of version 3.4, use create() method instead.
	 */
	public function massAdd($data) {
		$this->deprecated('usergroup.massadd method is deprecated.');

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$usrgrpids = array_keys(array_flip(zbx_toArray($data['usrgrpids'])));
		$userids = (isset($data['userids']) && !is_null($data['userids'])) ? zbx_toArray($data['userids']) : null;
		$rights = (isset($data['rights']) && !is_null($data['rights'])) ? zbx_toArray($data['rights']) : null;

		if (!is_null($userids)) {
			$options = [
				'usrgrpids' => $usrgrpids,
				'output' => API_OUTPUT_EXTEND,
			];
			$usrgrps = $this->get($options);
			foreach ($usrgrps as $usrgrp) {
				if ((($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
					|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED))
					&& uint_in_array(self::$userData['userid'], $userids)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot change status of himself'));
				}
			}

			$linkedUsers = [];
			$sql = 'SELECT usrgrpid, userid'.
				' FROM users_groups'.
				' WHERE '.dbConditionInt('usrgrpid', $usrgrpids).
				' AND '.dbConditionInt('userid', $userids);
			$linkedUsersDb = DBselect($sql);
			while ($link = DBfetch($linkedUsersDb)) {
				if (!isset($linkedUsers[$link['usrgrpid']])) $linkedUsers[$link['usrgrpid']] = [];
				$linkedUsers[$link['usrgrpid']][$link['userid']] = 1;
			}

			$usersInsert = [];
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($userids as $userid) {
					if (!isset($linkedUsers[$usrgrpid][$userid])) {
						$usersInsert[] = [
							'usrgrpid' => $usrgrpid,
							'userid' => $userid,
						];
					}
				}
			}
			DB::insert('users_groups', $usersInsert);
		}

		if (!is_null($rights)) {
			$linkedRights = [];
			$sql = 'SELECT groupid,id'.
				' FROM rights'.
				' WHERE '.dbConditionInt('groupid', $usrgrpids).
					' AND '.dbConditionInt('id', zbx_objectValues($rights, 'id'));
			$linkedRightsDb = DBselect($sql);
			while ($link = DBfetch($linkedRightsDb)) {
				if (!isset($linkedRights[$link['groupid']])) $linkedRights[$link['groupid']] = [];
				$linkedRights[$link['groupid']][$link['id']] = 1;
			}

			$rightInsert = [];
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($rights as $right) {
					if (!isset($linkedRights[$usrgrpid][$right['id']])) {
						$rightInsert[] = [
							'groupid' => $usrgrpid,
							'permission' => $right['permission'],
							'id' => $right['id']
						];
					}
				}
			}
			DB::insert('rights', $rightInsert);
		}

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * Mass update user group.
	 * Checks for permissions - only super admins can change user groups.
	 * Changes name to a group if name and one user group id is provided.
	 * Links/unlinks users to user groups.
	 * Links/unlinks rights to user groups.
	 *
	 * @deprecated	As of version 3.4, use update() method instead.
	 *
	 * @param array $data
	 * @param int|int[] $data['usrgrpids'] id or ids of user groups to be updated.
	 * @param string $data['name'] name to be set to a user group. Only one host group id can be passed at a time!
	 * @param null|int|int[] $data['userids'] user ids to link to given user groups. Missing user ids will be unlinked from user groups.
	 * @param null|array $data['rights'] rights to link to given user groups. Missing rights will be unlinked from user groups.
	 * @param int $data['rights']['id'] id of right.
	 * @param int $data['rights']['permission'] permission level of right.
	 *
	 * @return int[] array['usrgrpids'] returns passed user group ids
	 */
	public function massUpdate($data) {
		$this->deprecated('usergroup.massupdate method is deprecated.');

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$usrgrpids = zbx_toArray($data['usrgrpids']);

		if (count($usrgrpids) == 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Missing parameter: usrgrpids.'));
		}

		// $data['name'] parameter restrictions
		if (isset($data['name'])) {
			// same name can be set only to one hostgroup
			if (count($usrgrpids) > 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Only one user group name can be changed at a time.'));
			}
			else {
				// check if there already is hostgroup with this name, except current hostgroup
				$groupExists = $this->get([
					'filter' => ['name' => $data['name']],
					'output' => ['usrgrpid'],
					'limit' => 1
				]);
				$groupExists = reset($groupExists);
				if ($groupExists && (bccomp($groupExists['usrgrpid'], $usrgrpids[0]) != 0) ) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%s" already exists.', $data['name']));
				}
			}
		}

		// update usrgrp (user group) table if there is something to update
		$usrgrpTableUpdateData = $data;
		unset($usrgrpTableUpdateData['usrgrpids'], $usrgrpTableUpdateData['userids'], $usrgrpTableUpdateData['rights']);
		if (!empty($usrgrpTableUpdateData)) {
			foreach ($usrgrpids as $usrgrpid) {
				DB::update('usrgrp', [
					'values' => $usrgrpTableUpdateData,
					'where' => ['usrgrpid' => $usrgrpid],
				]);
			}
		}

		// check that user do not add himself to a disabled user group
		// insert and delete user-userGroup links
		if (isset($data['userids'])) {
			$userids = zbx_toArray($data['userids']);

			// check whether user tries to add himself to a disabled user group
			$usrgrps = $this->get([
				'output' => ['usrgrpid', 'gui_access', 'users_status'],
				'usrgrpids' => $usrgrpids,
				'selectUsers' => ['userid'],
				'preservekeys' => true
			]);
			if (uint_in_array(self::$userData['userid'], $userids)) {
				foreach ($usrgrps as $usrgrp) {
					if (($usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED)
						|| ($usrgrp['users_status'] == GROUP_STATUS_DISABLED)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot add himself to a disabled group or a group with disabled GUI access.'));
					}
				}
			}

			$userUsergroupLinksToInsert = [];
			$amount_of_usrgrps_to_unlink = [];

			foreach ($usrgrps as $usrgrpid => $usrgrp) {
				$usrgrp_userids = [];

				foreach ($usrgrp['users'] as $user) {
					$usrgrp_userids[$user['userid']] = true;
				}

				foreach ($userids as $userid) {
					if (!array_key_exists($userid, $usrgrp_userids)) {
						$userUsergroupLinksToInsert[] = [
							'usrgrpid' => $usrgrpid,
							'userid' => $userid
						];
					}
					else {
						unset($usrgrp_userids[$userid]);
					}
				}

				foreach ($usrgrp_userids as $userid => $value) {
					$amount_of_usrgrps_to_unlink[$userid] = array_key_exists($userid, $amount_of_usrgrps_to_unlink)
						? $amount_of_usrgrps_to_unlink[$userid] + 1
						: 1;
				}
			}

			// link users to user groups
			if (!empty($userUsergroupLinksToInsert)) {
				DB::insert('users_groups', $userUsergroupLinksToInsert);
			}

			if ($amount_of_usrgrps_to_unlink) {
				// Unlink users from user groups.

				$userids_to_unlink = array_keys($amount_of_usrgrps_to_unlink);

				$db_users = API::User()->get([
					'output' => ['userid', 'alias'],
					'userids' => $userids_to_unlink,
					'selectUsrgrps' => ['usrgrpid']
				]);

				foreach ($db_users as $user) {
					if (count($user['usrgrps']) <= $amount_of_usrgrps_to_unlink[$user['userid']]) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('User "%1$s" cannot be without user group.', $user['alias'])
						);
					}
				}

				DB::delete('users_groups', [
					'userid' => $userids_to_unlink,
					'usrgrpid' => $usrgrpids
				]);
			}
		}

		// link rights to user groups
		// update permissions to right-userGroup links
		// unlink rights from user groups (permissions)
		if (isset($data['rights'])) {
			$rights = zbx_toArray($data['rights']);

			// get already linked rights
			$linkedRights = [];
			$sql = 'SELECT groupid,permission,id'.
				' FROM rights'.
				' WHERE '.dbConditionInt('groupid', $usrgrpids);
			$linkedRightsDb = DBselect($sql);
			while ($link = DBfetch($linkedRightsDb)) {
				if (!isset($linkedRights[$link['groupid']])) {
					$linkedRights[$link['groupid']] = [];
				}
				$linkedRights[$link['groupid']][$link['id']] = $link['permission'];
			}

			// get right-userGroup links to insert
			// get right-userGroup links to update permissions
			// get rightIds to unlink rights from user groups
			$rightUsergroupLinksToInsert = [];
			$rightUsergroupLinksToUpdate = [];
			$rightIdsToUnlink = [];
			foreach ($usrgrpids as $usrgrpid) {
				foreach ($rights as $right) {
					if (!isset($linkedRights[$usrgrpid][$right['id']])) {
						$rightUsergroupLinksToInsert[] = [
							'groupid' => $usrgrpid,
							'id' => $right['id'],
							'permission' => $right['permission'],
						];
					}
					elseif ($linkedRights[$usrgrpid][$right['id']] != $right['permission']) {
						$rightUsergroupLinksToUpdate[] = [
							'values' => ['permission' => $right['permission']],
							'where' => ['groupid' => $usrgrpid, 'id' => $right['id']],
						];
					}
					unset($linkedRights[$usrgrpid][$right['id']]);
				}

				if (isset($linkedRights[$usrgrpid]) && !empty($linkedRights[$usrgrpid])) {
					$rightIdsToUnlink = array_merge($rightIdsToUnlink, array_keys($linkedRights[$usrgrpid]));
				}
			}

			// link rights to user groups
			if (!empty($rightUsergroupLinksToInsert)) {
				DB::insert('rights', $rightUsergroupLinksToInsert);
			}

			// unlink rights from user groups
			if (!empty($rightIdsToUnlink)) {
				DB::delete('rights', [
					'id' => $rightIdsToUnlink,
					'groupid' => $usrgrpids,
				]);
			}

			// update right-userGroup permissions
			if (!empty($rightUsergroupLinksToUpdate)) {
				DB::update('rights', $rightUsergroupLinksToUpdate);
			}
		}

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * Delete user groups.
	 *
	 * @param array $usrgrpids
	 *
	 * @return array
	 */
	public function delete(array $usrgrpids) {
		$this->validateDelete($usrgrpids, $db_usrgrps);

		$db_operations = DBFetchArray(DBselect(
			'SELECT DISTINCT om.operationid'.
			' FROM opmessage_grp om'.
			' WHERE '.dbConditionInt('om.usrgrpid', $usrgrpids)
		));

		DB::delete('opmessage_grp', ['usrgrpid' => $usrgrpids]);

		// delete empty operations
		$del_operations = DBFetchArray(DBselect(
			'SELECT DISTINCT o.operationid,o.actionid'.
			' FROM operations o'.
			' WHERE '.dbConditionInt('o.operationid', zbx_objectValues($db_operations, 'operationid')).
				' AND NOT EXISTS(SELECT NULL FROM opmessage_grp omg WHERE omg.operationid=o.operationid)'.
				' AND NOT EXISTS(SELECT NULL FROM opmessage_usr omu WHERE omu.operationid=o.operationid)'
		));

		DB::delete('operations', ['operationid' => zbx_objectValues($del_operations, 'operationid')]);
		DB::delete('rights', ['groupid' => $usrgrpids]);
		DB::delete('users_groups', ['usrgrpid' => $usrgrpids]);
		DB::delete('usrgrp', ['usrgrpid' => $usrgrpids]);

		$actionids = zbx_objectValues($del_operations, 'actionid');
		if ($actionids) {
			$this->disableActionsWithoutOperations($actionids);
		}

		add_audit_bulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_GROUP, $db_usrgrps);

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException
	 *
	 * @param array $usrgrpids
	 * @param array $db_usrgrps
	 */
	protected function validateDelete(array $usrgrpids, array &$db_usrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete user groups.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrpids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_usrgrps = API::getApiService()->select('usrgrp', [
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

		// check if user group is used in scripts
		$db_scripts = API::getApiService()->select('scripts', [
			'output' => ['name', 'usrgrpid'],
			'filter' => ['usrgrpid' => $usrgrpids],
			'limit' => 1
		]);

		if ($db_scripts) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" is used in script "%2$s".',
				$db_usrgrps[$db_scripts[0]['usrgrpid']]['name'],
				$db_scripts[0]['name']
			));
		}

		// check if user group is used in config
		$config = select_config();

		if (array_key_exists($config['alert_usrgrpid'], $db_usrgrps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'User group "%1$s" is used in configuration for database down messages.',
				$db_usrgrps[$config['alert_usrgrpid']]['name']
			));
		}

		$this->checkUsersWithoutGroups($usrgrps);
	}

	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		if (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get([
			'usrgrpids' => $ids,
			'countOutput' => true
		]);

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

		$count = $this->get([
			'usrgrpids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
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

	/**
	 * Disable actions that do not have operations.
	 */
	protected function disableActionsWithoutOperations(array $actionids) {
		$actions = DBFetchArray(DBselect(
			'SELECT DISTINCT a.actionid'.
			' FROM actions a'.
			' WHERE NOT EXISTS (SELECT NULL FROM operations o WHERE o.actionid=a.actionid)'.
				' AND '.dbConditionInt('a.actionid', $actionids)
		));

		$actions_without_operations = zbx_objectValues($actions, 'actionid');
		if ($actions_without_operations) {
			$this->disableActions($actions_without_operations);
		}
	}

	/**
	 * Disable actions.
	 *
	 * @param array $actionids
	 */
	protected function disableActions(array $actionids) {
		$update = [
			'values' => ['status' => ACTION_STATUS_DISABLED],
			'where' => ['actionid' => $actionids]
		];
		DB::update('actions', $update);

		foreach($actionids as $actionid) {
			add_audit_details(AUDIT_ACTION_DISABLE, AUDIT_RESOURCE_ACTION, $actionid, '',
				_('Action disabled due to deletion of user group.'), null
			);
		}
	}
}
