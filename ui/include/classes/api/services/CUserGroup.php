<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

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
			'selectTagFilters'			=> null,
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
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'usergroup', __FUNCTION__)
			);
		}

		$this->validateCreate($usrgrps);

		$ins_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			unset($usrgrp['rights'], $usrgrp['tag_filters'], $usrgrp['users']);
			$ins_usrgrps[] = $usrgrp;
		}
		$usrgrpids = DB::insert('usrgrp', $ins_usrgrps);

		foreach ($usrgrps as $index => &$usrgrp) {
			$usrgrp['usrgrpid'] = $usrgrpids[$index];
		}
		unset($usrgrp);

		self::updateRights($usrgrps, __FUNCTION__);
		self::updateTagFilters($usrgrps, __FUNCTION__);
		self::updateUsersGroups($usrgrps, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USER_GROUP, $usrgrps);

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * @param array $usrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$usrgrps) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('usrgrp', 'name')],
			'debug_mode' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED])],
			'gui_access' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED])],
			'users_status' =>	['type' => API_INT32, 'in' => implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED])],
			'rights' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'tag_filters' =>	['type' => API_OBJECTS, 'uniq' => [['groupid', 'tag', 'value']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'tag' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'tag'), 'default' => DB::getDefault('tag_filter', 'tag')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'value'), 'default' => DB::getDefault('tag_filter', 'value')]
			]],
			'userids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE | API_DEPRECATED, 'uniq' => true],
			'users' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'fields' => [
				'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($usrgrps as &$usrgrp) {
			if (array_key_exists('userids', $usrgrp)) {
				if (array_key_exists('users', $usrgrp)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'userids'));
				}

				$usrgrp['users'] = zbx_toObject($usrgrp['userids'], 'userid');
				unset($usrgrp['userids']);
			}
		}
		unset($usrgrp);

		$this->checkDuplicates(array_column($usrgrps, 'name'));
		$this->checkUsers($usrgrps);
		$this->checkHimself($usrgrps, __FUNCTION__);
		$this->checkHostGroups($usrgrps);
		$this->checkTagFilters($usrgrps);
	}

	/**
	 * @param array  $usrgrps
	 *
	 * @return array
	 */
	public function update($usrgrps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'usergroup', __FUNCTION__)
			);
		}

		$this->validateUpdate($usrgrps, $db_usrgrps);

		self::updateForce($usrgrps, $db_usrgrps);

		return ['usrgrpids'=> array_column($usrgrps, 'usrgrpid')];
	}

	/**
	 * @param array $usrgrps
	 * @param array $db_usrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$usrgrps, array &$db_usrgrps = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['usrgrpid'], ['name']], 'fields' => [
			'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('usrgrp', 'name')],
			'debug_mode' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED])],
			'gui_access' =>		['type' => API_INT32, 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED])],
			'users_status' =>	['type' => API_INT32, 'in' => implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED])],
			'rights' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'tag_filters' =>	['type' => API_OBJECTS, 'uniq' => [['groupid', 'tag', 'value']], 'fields' => [
				'groupid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'tag' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'tag'), 'default' => DB::getDefault('tag_filter', 'tag')],
				'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'value'), 'default' => DB::getDefault('tag_filter', 'value')]
			]],
			'userids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE | API_DEPRECATED, 'uniq' => true],
			'users' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'fields' => [
				'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check user group names.
		$db_usrgrps = DB::select('usrgrp', [
			'output' => ['usrgrpid', 'name', 'debug_mode', 'gui_access', 'users_status'],
			'usrgrpids' => array_column($usrgrps, 'usrgrpid'),
			'preservekeys' => true
		]);

		$names = [];

		foreach ($usrgrps as &$usrgrp) {
			// Check if this user group exists.
			if (!array_key_exists($usrgrp['usrgrpid'], $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			if (array_key_exists('userids', $usrgrp)) {
				if (array_key_exists('users', $usrgrp)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'userids'));
				}

				$usrgrp['users'] = zbx_toObject($usrgrp['userids'], 'userid');
				unset($usrgrp['userids']);
			}

			$db_usrgrp = $db_usrgrps[$usrgrp['usrgrpid']];

			if (array_key_exists('name', $usrgrp) && $usrgrp['name'] !== $db_usrgrp['name']) {
				$names[] = $usrgrp['name'];
			}
		}
		unset($usrgrp);

		self::addAffectedObjects($usrgrps, $db_usrgrps);

		if ($names) {
			$this->checkDuplicates($names);
		}
		$this->checkUsers($usrgrps);
		$this->checkHimself($usrgrps, __FUNCTION__, $db_usrgrps);
		$this->checkUsersWithoutGroups($usrgrps);
		$this->checkHostGroups($usrgrps);
		$this->checkTagFilters($usrgrps);
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
	 * @param array  $usrgrps[]['users']              (optional)
	 * @param string $usrgrps[]['users'][]['userid']
	 *
	 * @throws APIException
	 */
	private function checkUsers(array $usrgrps) {
		$userids = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('users', $usrgrp)) {
				foreach ($usrgrp['users'] as $user) {
					$userids[$user['userid']] = true;
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
			if (array_key_exists('tag_filters', $usrgrp)) {
				foreach ($usrgrp['tag_filters'] as $tag_filter) {
					$groupids[$tag_filter['groupid']] = true;
				}
			}
		}

		if (!$groupids) {
			return;
		}

		$groupids = array_keys($groupids);

		$db_groups = DB::select('hstgrp', [
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
	 * Tag filter validation.
	 *
	 * @param array  $usrgrps
	 *
	 * @throws APIException
	 */
	private function checkTagFilters(array $usrgrps) {
		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('tag_filters', $usrgrp)) {
				foreach ($usrgrp['tag_filters'] as $tag_filter) {
					if ($tag_filter['tag'] === '' && $tag_filter['value'] !== '') {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', _('tag'), _('cannot be empty'))
						);
					}
				}
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
				if (self::userGroupDisabled($usrgrp, $method, $db_usrgrps) && !array_key_exists('users', $usrgrp)) {
					$groups_users[$usrgrp['usrgrpid']] = [];
				}
			}

			if ($groups_users) {
				$db_users_groups = DB::select('users_groups', [
					'output' => ['usrgrpid', 'userid'],
					'filter' => ['usrgrpid' => array_keys($groups_users)]
				]);

				foreach ($db_users_groups as $db_user_group) {
					$groups_users[$db_user_group['usrgrpid']][] = ['userid' => $db_user_group['userid']];
				}

				foreach ($usrgrps as &$usrgrp) {
					if (self::userGroupDisabled($usrgrp, $method, $db_usrgrps) && !array_key_exists('users', $usrgrp)) {
						$usrgrp['users'] = $groups_users[$usrgrp['usrgrpid']];
					}
				}
				unset($usrgrp);
			}
		}

		foreach ($usrgrps as $usrgrp) {
			if (self::userGroupDisabled($usrgrp, $method, $db_usrgrps) && array_key_exists('users', $usrgrp)) {
				foreach ($usrgrp['users'] as $user) {
					if (bccomp(self::$userData['userid'], $user['userid']) == 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_('User cannot add himself to a disabled group or a group with disabled GUI access.')
						);
					}
				}
			}
		}
	}

	/**
	 * Check to exclude an opportunity to leave user without user groups.
	 *
	 * @param array  $usrgrps
	 * @param array  $usrgrps[]['usrgrpid']
	 * @param array  $usrgrps[]['users']              (optional)
	 * @param string $usrgrps[]['users'][]['userid']
	 *
	 * @throws APIException
	 */
	private function checkUsersWithoutGroups(array $usrgrps) {
		$users_groups = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('users', $usrgrp)) {
				$users_groups[$usrgrp['usrgrpid']] = [];

				foreach ($usrgrp['users'] as $user) {
					$users_groups[$usrgrp['usrgrpid']][$user['userid']] = true;
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
			'SELECT u.userid,u.username,count(ug.usrgrpid) as usrgrp_num'.
			' FROM users u,users_groups ug'.
			' WHERE u.userid=ug.userid'.
				' AND '.dbConditionInt('u.userid', array_keys($del_userids)).
			' GROUP BY u.userid,u.username'
		);

		while ($db_user = DBfetch($db_users)) {
			if ($db_user['usrgrp_num'] == $del_userids[$db_user['userid']]) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" cannot be without user group.', $db_user['username'])
				);
			}
		}
	}

	/**
	 * @static
	 *
	 * @param array $usrgrps
	 * @param array $db_usrgrps
	 */
	public static function updateForce($usrgrps, $db_usrgrps): void {
		$upd_usrgrps = [];

		foreach ($usrgrps as $usrgrp) {
			$db_usrgrp = $db_usrgrps[$usrgrp['usrgrpid']];

			$upd_usrgrp = DB::getUpdatedValues('usrgrp', $usrgrp, $db_usrgrp);

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

		self::updateRights($usrgrps, 'update', $db_usrgrps);
		self::updateTagFilters($usrgrps, 'update', $db_usrgrps);
		self::updateUsersGroups($usrgrps, 'update', $db_usrgrps);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER_GROUP, $usrgrps, $db_usrgrps);
	}

	/**
	 * Update table "rights".
	 *
	 * @static
	 *
	 * @param array      $usrgrps
	 * @param string     $method
	 * @param null|array $db_usrgrps
	 */
	private static function updateRights(array &$usrgrps, string $method, array $db_usrgrps = null): void {
		$ins_rights = [];
		$upd_rights = [];
		$del_rightids = [];

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('rights', $usrgrp)) {
				continue;
			}

			$db_rights = ($method === 'update')
				? array_column($db_usrgrps[$usrgrp['usrgrpid']]['rights'], null, 'id')
				: [];

			foreach ($usrgrp['rights'] as &$right) {
				if (array_key_exists($right['id'], $db_rights)) {
					$db_right = $db_rights[$right['id']];
					unset($db_rights[$right['id']]);

					$right['rightid'] = $db_right['rightid'];

					$upd_right = DB::getUpdatedValues('rights', $right, $db_right);

					if ($upd_right) {
						$upd_rights[] = [
							'values' => $upd_right,
							'where' => ['rightid' => $db_right['rightid']]
						];
					}
				}
				else {
					$ins_rights[] = [
						'groupid' => $usrgrp['usrgrpid'],
						'id' => $right['id'],
						'permission' => $right['permission']
					];
				}
			}
			unset($right);

			$del_rightids = array_merge($del_rightids, array_column($db_rights, 'rightid'));
		}
		unset($usrgrp);

		if ($ins_rights) {
			$rightids = DB::insertBatch('rights', $ins_rights);
		}

		if ($upd_rights) {
			DB::update('rights', $upd_rights);
		}

		if ($del_rightids) {
			DB::delete('rights', ['rightid' => $del_rightids]);
		}

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('rights', $usrgrp)) {
				continue;
			}

			foreach ($usrgrp['rights'] as &$right) {
				if (!array_key_exists('rightid', $right)) {
					$right['rightid'] = array_shift($rightids);
				}
			}
			unset($right);
		}
		unset($usrgrp);
	}

	/**
	 * Update table "tag_filter".
	 *
	 * @static
	 *
	 * @param array      $usrgrps
	 * @param string     $method
	 * @param null|array $db_usrgrps
	 */
	private static function updateTagFilters(array &$usrgrps, string $method, array $db_usrgrps = null): void {
		$ins_tag_filters = [];
		$del_tag_filterids = [];

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('tag_filters', $usrgrp)) {
				continue;
			}

			$db_tag_filterids_by_tag_value = [];
			$db_tag_filters = ($method === 'update')
				? $db_usrgrps[$usrgrp['usrgrpid']]['tag_filters']
				: [];

			foreach ($db_tag_filters as $db_tag_filter) {
				$db_tag_filterids_by_tag_value[$db_tag_filter['groupid']][$db_tag_filter['tag']][
					$db_tag_filter['value']
				] = $db_tag_filter['tag_filterid'];
			}

			foreach ($usrgrp['tag_filters'] as &$tag_filter) {
				$groupid = $tag_filter['groupid'];
				$tag = $tag_filter['tag'];
				$value = $tag_filter['value'];

				if (array_key_exists($groupid, $db_tag_filterids_by_tag_value)
						&& array_key_exists($tag, $db_tag_filterids_by_tag_value[$groupid])
						&& array_key_exists($value, $db_tag_filterids_by_tag_value[$groupid][$tag])) {
					$tag_filterid = $db_tag_filterids_by_tag_value[$groupid][$tag][$value];
					unset($db_tag_filters[$tag_filterid]);

					$tag_filter['tag_filterid'] = $tag_filterid;
				}
				else {
					$ins_tag_filters[] = [
						'usrgrpid' => $usrgrp['usrgrpid'],
						'groupid' => $tag_filter['groupid'],
						'tag' => $tag_filter['tag'],
						'value' => $tag_filter['value']
					];
				}
			}
			unset($tag_filter);

			$del_tag_filterids = array_merge($del_tag_filterids, array_column($db_tag_filters, 'tag_filterid'));
		}
		unset($usrgrp);

		if ($ins_tag_filters) {
			$tag_filterids = DB::insertBatch('tag_filter', $ins_tag_filters);
		}

		if ($del_tag_filterids) {
			DB::delete('tag_filter', ['tag_filterid' => $del_tag_filterids]);
		}

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('tag_filters', $usrgrp)) {
				continue;
			}

			foreach ($usrgrp['tag_filters'] as &$tag_filter) {
				if (!array_key_exists('tag_filterid', $tag_filter)) {
					$tag_filter['tag_filterid'] = array_shift($tag_filterids);
				}
			}
			unset($tag_filter);
		}
		unset($usrgrp);
	}

	/**
	 * Update table "users_groups".
	 *
	 * @static
	 *
	 * @param array      $usrgrps
	 * @param string     $method
	 * @param null|array $db_usrgrps
	 */
	private static function updateUsersGroups(array &$usrgrps, string $method, array $db_usrgrps = null): void {
		$ins_users_groups = [];
		$del_ids = [];

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('users', $usrgrp)) {
				continue;
			}

			$db_users = ($method === 'update')
				? array_column($db_usrgrps[$usrgrp['usrgrpid']]['users'], null, 'userid')
				: [];

			foreach ($usrgrp['users'] as &$user) {
				if (array_key_exists($user['userid'], $db_users)) {
					$user['id'] = $db_users[$user['userid']]['id'];
					unset($db_users[$user['userid']]);
				}
				else {
					$ins_users_groups[] = [
						'userid' => $user['userid'],
						'usrgrpid' => $usrgrp['usrgrpid']
					];
				}
			}
			unset($user);

			$del_ids = array_merge($del_ids, array_column($db_users, 'id'));
		}
		unset($usrgrp);

		if ($ins_users_groups) {
			$ids = DB::insertBatch('users_groups', $ins_users_groups);
		}

		if ($del_ids) {
			DB::delete('users_groups', ['id' => $del_ids]);
		}

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('users', $usrgrp)) {
				continue;
			}

			foreach ($usrgrp['users'] as &$user) {
				if (!array_key_exists('id', $user)) {
					$user['id'] = array_shift($ids);
				}
			}
			unset($user);
		}
		unset($usrgrp);
	}

	/**
	 * @param array $usrgrpids
	 *
	 * @return array
	 */
	public function delete(array $usrgrpids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'usergroup', __FUNCTION__)
			);
		}

		$this->validateDelete($usrgrpids, $db_usrgrps);

		DB::delete('rights', ['groupid' => $usrgrpids]);
		DB::delete('users_groups', ['usrgrpid' => $usrgrpids]);
		DB::delete('usrgrp', ['usrgrpid' => $usrgrpids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_USER_GROUP, $db_usrgrps);

		return ['usrgrpids' => $usrgrpids];
	}

	/**
	 * @throws APIException
	 *
	 * @param array $usrgrpids
	 * @param array $db_usrgrps
	 */
	protected function validateDelete(array &$usrgrpids, array &$db_usrgrps = null) {
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
				'users' => []
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
		if (array_key_exists(CSettingsHelper::get(CSettingsHelper::ALERT_USRGRPID), $db_usrgrps)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User group "%1$s" is used in configuration for database down messages.', $db_usrgrps[CSettingsHelper::get(CSettingsHelper::ALERT_USRGRPID)]['name'])
			);
		}

		// Check if user groups are used in scheduled reports.
		$db_reports = DBselect(
			'SELECT r.name,rug.usrgrpid'.
			' FROM report r,report_usrgrp rug'.
			' WHERE r.reportid=rug.reportid'.
				' AND '.dbConditionInt('rug.usrgrpid', $usrgrpids),
			1
		);

		if ($db_report = DBfetch($db_reports)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group "%1$s" is report "%2$s" recipient.',
				$db_usrgrps[$db_report['usrgrpid']]['name'], $db_report['name']
			));
		}

		$this->checkUsersWithoutGroups($usrgrps);
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$dbUsers = [];
			$relationMap = $this->createRelationMap($result, 'usrgrpid', 'userid', 'users_groups');
			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$get_access = ($this->outputIsRequested('gui_access', $options['selectUsers'])
					|| $this->outputIsRequested('debug_mode', $options['selectUsers'])
					|| $this->outputIsRequested('users_status', $options['selectUsers'])) ? true : null;

				$dbUsers = API::User()->get([
					'output' => $options['selectUsers'],
					'userids' => $related_ids,
					'getAccess' => $get_access,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $dbUsers, 'users');
		}

		// adding usergroup rights
		if ($options['selectRights'] !== null && $options['selectRights'] != API_OUTPUT_COUNT) {
			$db_rights = [];
			$relationMap = $this->createRelationMap($result, 'groupid', 'rightid', 'rights');
			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
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
					' WHERE '.dbConditionInt('r.rightid', $related_ids).
						((self::$userData['type'] == USER_TYPE_SUPER_ADMIN) ? '' : ' AND r.permission>'.PERM_DENY)
				));
				$db_rights = zbx_toHash($db_rights, 'rightid');

				foreach ($db_rights as &$db_right) {
					unset($db_right['rightid'], $db_right['groupid']);
				}
				unset($db_right);
			}

			$result = $relationMap->mapMany($result, $db_rights, 'rights');
		}

		// Adding usergroup tag filters.
		if ($options['selectTagFilters'] !== null && $options['selectTagFilters'] != API_OUTPUT_COUNT) {
			foreach ($result as &$usrgrp) {
				$usrgrp['tag_filters'] = [];
			}
			unset($usrgrp);

			if (is_array($options['selectTagFilters'])) {
				$output_fields = [];

				foreach ($this->outputExtend($options['selectTagFilters'], ['usrgrpid']) as $field) {
					if ($this->hasField($field, 'tag_filter')) {
						$output_fields[$field] = $this->fieldId($field, 't');
					}
				}

				$output_fields = implode(',', $output_fields);
			}
			else {
				$output_fields = 't.*';
			}

			$db_tag_filters = DBselect(
				'SELECT '.$output_fields.
				' FROM tag_filter t'.
				' WHERE '.dbConditionInt('t.usrgrpid', array_keys($result))
			);

			while ($db_tag_filter = DBfetch($db_tag_filters)) {
				$usrgrpid = $db_tag_filter['usrgrpid'];
				unset($db_tag_filter['tag_filterid'], $db_tag_filter['usrgrpid']);

				$result[$usrgrpid]['tag_filters'][] = $db_tag_filter;
			}
		}

		return $result;
	}

	/**
	 * Add the existing rights, tag_filters and userids to $db_usrgrps whether these are affected by the update.
	 *
	 * @static
	 *
	 * @param array $usrgrps
	 * @param array $db_usrgrps
	 */
	private static function addAffectedObjects(array $usrgrps, array &$db_usrgrps): void {
		$usrgrpids = ['rights' => [], 'tag_filters' => [], 'users' => []];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('rights', $usrgrp)) {
				$usrgrpids['rights'][] = $usrgrp['usrgrpid'];
				$db_usrgrps[$usrgrp['usrgrpid']]['rights'] = [];
			}

			if (array_key_exists('tag_filters', $usrgrp)) {
				$usrgrpids['tag_filters'][] = $usrgrp['usrgrpid'];
				$db_usrgrps[$usrgrp['usrgrpid']]['tag_filters'] = [];
			}

			if (array_key_exists('users', $usrgrp)) {
				$usrgrpids['users'][] = $usrgrp['usrgrpid'];
				$db_usrgrps[$usrgrp['usrgrpid']]['users'] = [];
			}
		}

		if ($usrgrpids['rights']) {
			$options = [
				'output' => ['rightid', 'groupid', 'permission', 'id'],
				'filter' => ['groupid' => $usrgrpids['rights']]
			];
			$db_rights = DBselect(DB::makeSql('rights', $options));

			while ($db_right = DBfetch($db_rights)) {
				$db_usrgrps[$db_right['groupid']]['rights'][$db_right['rightid']] =
					array_diff_key($db_right, array_flip(['groupid']));
			}
		}

		if ($usrgrpids['tag_filters']) {
			$options = [
				'output' => ['tag_filterid', 'usrgrpid', 'groupid', 'tag', 'value'],
				'filter' => ['usrgrpid' => $usrgrpids['tag_filters']]
			];
			$db_tags = DBselect(DB::makeSql('tag_filter', $options));

			while ($db_tag = DBfetch($db_tags)) {
				$db_usrgrps[$db_tag['usrgrpid']]['tag_filters'][$db_tag['tag_filterid']] =
					array_diff_key($db_tag, array_flip(['usrgrpid']));
			}
		}

		if ($usrgrpids['users']) {
			$options = [
				'output' => ['id', 'usrgrpid', 'userid'],
				'filter' => ['usrgrpid' => $usrgrpids['users']]
			];
			$db_users = DBselect(DB::makeSql('users_groups', $options));

			while ($db_user = DBfetch($db_users)) {
				$db_usrgrps[$db_user['usrgrpid']]['users'][$db_user['id']] =
					array_diff_key($db_user, array_flip(['usrgrpid']));
			}
		}
	}
}
