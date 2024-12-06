<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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

	public const OUTPUT_FIELDS = ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid',
		'mfa_status', 'mfaid'
	];

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
			'mfaids'					=> null,
			'status'					=> null,
			'mfa_status'				=> null,
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
			'selectHostGroupRights'		=> null,
			'selectTemplateGroupRights'	=> null,
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

		if (!is_null($options['mfaids'])) {
			zbx_value2array($options['mfaids']);

			$sqlParts['where'][] = dbConditionId('g.mfaid', $options['mfaids']);
		}

		// status
		if (!is_null($options['status'])) {
			$sqlParts['where'][] = 'g.users_status='.zbx_dbstr($options['status']);
		}

		if (!is_null($options['mfa_status'])) {
			zbx_value2array($options['mfa_status']);

			$sqlParts['where'][] = dbConditionInt('g.mfa_status', $options['mfa_status']);
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
			unset($usrgrp['hostgroup_rights'], $usrgrp['templategroup_rights'], $usrgrp['tag_filters'],
				$usrgrp['users']
			);
			$ins_usrgrps[] = $usrgrp;
		}
		$usrgrpids = DB::insert('usrgrp', $ins_usrgrps);

		foreach ($usrgrps as $index => &$usrgrp) {
			$usrgrp['usrgrpid'] = $usrgrpids[$index];
		}
		unset($usrgrp);

		self::updateRights($usrgrps);
		self::updateTagFilters($usrgrps);
		self::updateUsers($usrgrps);

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
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('usrgrp', 'name')],
			'debug_mode' =>				['type' => API_INT32, 'in' => implode(',', [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED])],
			'gui_access' =>				['type' => API_INT32, 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED]), 'default' => DB::getDefault('usrgrp', 'gui_access')],
			'users_status' =>			['type' => API_INT32, 'in' => implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED])],
			'userdirectoryid' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'gui_access', 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_LDAP])], 'type' => API_ID],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'mfa_status' =>				['type' => API_INT32, 'in' => implode(',', [GROUP_MFA_DISABLED, GROUP_MFA_ENABLED]), 'default' => DB::getDefault('usrgrp', 'mfa_status')],
			'mfaid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'mfa_status', 'in' => implode(',', [GROUP_MFA_ENABLED])], 'type' => API_ID],
											['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'hostgroup_rights' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>						['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'templategroup_rights' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>						['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'tag_filters' =>			['type' => API_OBJECTS, 'uniq' => [['groupid', 'tag', 'value']], 'fields' => [
				'groupid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'tag' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'tag'), 'default' => DB::getDefault('tag_filter', 'tag')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'value'), 'default' => DB::getDefault('tag_filter', 'value')]
			]],
			'users' =>					['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'fields' => [
				'userid' =>					['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(array_column($usrgrps, 'name'));
		$this->checkUsers($usrgrps);
		$this->checkOneself($usrgrps, __FUNCTION__);
		$this->checkTemplateGroups($usrgrps);
		$this->checkHostGroups($usrgrps);
		$this->checkTagFilters($usrgrps);
		self::checkUserDirectories($usrgrps);
		self::checkMfaIds($usrgrps);
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
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['usrgrpid']], 'fields' => [
			'usrgrpid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$usrgrpids = array_column($usrgrps, 'usrgrpid');
		$db_usrgrps = API::UserGroup()->get([
			'output' => self::OUTPUT_FIELDS,
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		if (count($usrgrpids) != count($db_usrgrps)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$enabled_groupids = array_keys(array_column($usrgrps, 'users_status', 'usrgrpid'), GROUP_STATUS_ENABLED);
		$disabled_user_groupid = CAuthenticationHelper::get(CAuthenticationHelper::DISABLED_USER_GROUPID);

		if ($enabled_groupids && in_array($disabled_user_groupid, $enabled_groupids)) {
			static::exception(ZBX_API_ERROR_PARAMETERS, _('Deprovisioned users group cannot be enabled.'));
		}

		$names = [];
		$usrgrps = $this->extendObjectsByKey($usrgrps, $db_usrgrps, 'usrgrpid', ['gui_access', 'mfa_status']);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'usrgrpid' =>				['type' => API_ID],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('usrgrp', 'name')],
			'debug_mode' =>				['type' => API_INT32, 'in' => implode(',', [GROUP_DEBUG_MODE_DISABLED, GROUP_DEBUG_MODE_ENABLED])],
			'gui_access' =>				['type' => API_INT32, 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_INTERNAL, GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_DISABLED])],
			'users_status' =>			['type' => API_INT32, 'in' => implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED])],
			'userdirectoryid' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'gui_access', 'in' => implode(',', [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_LDAP])], 'type' => API_ID],
											['else' => true, 'type' => API_UNEXPECTED]
			]],
			'mfa_status' =>				['type' => API_INT32, 'in' => implode(',', [GROUP_MFA_DISABLED, GROUP_MFA_ENABLED])],
			'mfaid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'mfa_status', 'in' => implode(',', [GROUP_MFA_ENABLED])], 'type' => API_ID],
											['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'hostgroup_rights' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>						['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'templategroup_rights' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['id']], 'fields' => [
				'id' =>						['type' => API_ID, 'flags' => API_REQUIRED],
				'permission' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [PERM_DENY, PERM_READ, PERM_READ_WRITE])]
			]],
			'tag_filters' =>			['type' => API_OBJECTS, 'uniq' => [['groupid', 'tag', 'value']], 'fields' => [
				'groupid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'tag' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'tag'), 'default' => DB::getDefault('tag_filter', 'tag')],
				'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('tag_filter', 'value'), 'default' => DB::getDefault('tag_filter', 'value')]
			]],
			'users' =>					['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['userid']], 'fields' => [
				'userid' =>					['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $usrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($usrgrps as &$usrgrp) {
			$db_usrgrp = $db_usrgrps[$usrgrp['usrgrpid']];

			if (array_key_exists('name', $usrgrp) && $usrgrp['name'] !== $db_usrgrp['name']) {
				$names[] = $usrgrp['name'];
			}

			if (array_key_exists('gui_access', $usrgrp) && $usrgrp['gui_access'] != $db_usrgrp['gui_access']
					&& $usrgrp['gui_access'] != GROUP_GUI_ACCESS_LDAP
					&& $usrgrp['gui_access'] != GROUP_GUI_ACCESS_SYSTEM) {
				$usrgrp['userdirectoryid'] = 0;
			}
		}
		unset($usrgrp);

		self::addAffectedObjects($usrgrps, $db_usrgrps);

		if ($names) {
			$this->checkDuplicates($names);
		}
		$this->checkUsers($usrgrps, $db_usrgrps);
		$this->checkOneself($usrgrps, __FUNCTION__, $db_usrgrps);
		$this->checkTemplateGroups($usrgrps);
		$this->checkHostGroups($usrgrps);
		$this->checkTagFilters($usrgrps);
		self::checkUserDirectories($usrgrps);
		self::checkMfaIds($usrgrps, $db_usrgrps);
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
	 * @param array      $user_groups
	 * @param array|null $db_user_groups
	 *
	 * @throws APIException
	 */
	private function checkUsers(array $user_groups, array &$db_user_groups = null) {
		$user_indexes = [];

		foreach ($user_groups as $i1 => $user_group) {
			if (!array_key_exists('users', $user_group)) {
				continue;
			}

			foreach ($user_group['users'] as $i2 => $user) {
				$db_userids = $db_user_groups !== null
					? array_column($db_user_groups[$user_group['usrgrpid']]['users'], 'userid')
					: [];

				if (!in_array($user['userid'], $db_userids)) {
					$user_indexes[$user['userid']][$i1] = $i2;
				}
			}
		}

		if (!$user_indexes) {
			return;
		}

		$db_users = DB::select('users', [
			'output' => ['userdirectoryid'],
			'userids' => array_keys($user_indexes),
			'preservekeys' => true
		]);

		foreach ($user_indexes as $userid => $indexes) {
			if (!array_key_exists($userid, $db_users)) {
				$i1 = key($indexes);
				$i2 = reset($indexes);

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i1 + 1).'/users/'.($i2 + 1).'/userid', _('object does not exist')
				));
			}

			if ($db_users[$userid]['userdirectoryid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i1 + 1).'/users/'.($i2 + 1).'/userid',
					_s('cannot update readonly parameter "%1$s" of provisioned user', 'usrgrps')
				));
			}
		}
	}

	/**
	 * Check for valid template groups.
	 *
	 * @param array  $usrgrps
	 * @param array  $usrgrps[]['templategroup_rights']  (optional)
	 *
	 * @throws APIException
	 */
	private function checkTemplateGroups(array $usrgrps) {
		$groupids = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('templategroup_rights', $usrgrp)) {
				foreach ($usrgrp['templategroup_rights'] as $right) {
					$groupids[$right['id']] = true;
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
			'filter' => ['type' => HOST_GROUP_TYPE_TEMPLATE_GROUP],
			'preservekeys' => true
		]);

		foreach ($groupids as $groupid) {
			if (!array_key_exists($groupid, $db_groups)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Template group with ID "%1$s" is not available.', $groupid)
				);
			}
		}
	}

	/**
	 * Check for valid host groups.
	 *
	 * @param array  $usrgrps
	 * @param array  $usrgrps[]['hostgroup_rights']  (optional)
	 * @param array  $usrgrps[]['tag_filters']       (optional)
	 *
	 * @throws APIException
	 */
	private function checkHostGroups(array $usrgrps) {
		$groupids = [];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('hostgroup_rights', $usrgrp)) {
				foreach ($usrgrp['hostgroup_rights'] as $right) {
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
			'filter' => ['type' => HOST_GROUP_TYPE_HOST_GROUP],
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
	 * Auxiliary function for checkOneself().
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
	 * Additional check to exclude an opportunity to deactivate oneself.
	 *
	 * @param array  $usrgrps
	 * @param string $method
	 * @param array  $db_usrgrps
	 *
	 * @throws APIException
	 */
	private function checkOneself(array $usrgrps, $method, array $db_usrgrps = null) {
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
							_('User cannot add oneself to a disabled group or a group with disabled GUI access.')
						);
					}
				}
			}
		}
	}

	/**
	 * @param array $usrgrps
	 * @param array $db_usrgrps
	 */
	public static function updateForce($usrgrps, $db_usrgrps): void {
		self::addFieldDefaultsByType($usrgrps, $db_usrgrps);

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

		self::updateRights($usrgrps, $db_usrgrps);
		self::updateTagFilters($usrgrps, $db_usrgrps);
		self::updateUsers($usrgrps, $db_usrgrps);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER_GROUP, $usrgrps, $db_usrgrps);
	}

	private static function addFieldDefaultsByType(array &$usrgrps, array $db_usrgrps): void {
		foreach ($usrgrps as &$usrgrp) {
			if (array_key_exists('mfa_status', $usrgrp) && $usrgrp['mfa_status'] == GROUP_MFA_DISABLED
					&& $db_usrgrps[$usrgrp['usrgrpid']]['mfaid'] != '0') {
				$usrgrp['mfaid'] = '0';
			}
		}
		unset($usrgrp);
	}

	/**
	 * @param array      $usrgrps
	 * @param null|array $db_usrgrps
	 */
	private static function updateRights(array &$usrgrps, array $db_usrgrps = null): void {
		$ins_rights = [];
		$upd_rights = [];
		$del_rightids = [];
		$changed_permissions = [];

		foreach (['hostgroup_rights', 'templategroup_rights'] as $parameter) {
			foreach ($usrgrps as &$usrgrp) {
				if (!array_key_exists($parameter, $usrgrp)) {
					continue;
				}

				$db_rights = $db_usrgrps !== null
					? array_column($db_usrgrps[$usrgrp['usrgrpid']][$parameter], null, 'id')
					: [];

				foreach ($usrgrp[$parameter] as &$right) {
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
							$changed_permissions[$usrgrp['usrgrpid']][$right['id']]['new'] = $right['permission'];
							$changed_permissions[$usrgrp['usrgrpid']][$right['id']]['old'] = $db_right['permission'];
						}
					}
					else {
						$ins_rights[] = [
							'groupid' => $usrgrp['usrgrpid'],
							'id' => $right['id'],
							'permission' => $right['permission']
						];

						if ($db_usrgrps !== null) {
							$changed_permissions[$usrgrp['usrgrpid']][$right['id']]['new'] = $right['permission'];
							$changed_permissions[$usrgrp['usrgrpid']][$right['id']]['old'] = PERM_NONE;
						}
					}
				}
				unset($right);

				foreach ($db_rights as $db_right) {
					$del_rightids[] = $db_right['rightid'];
					$changed_permissions[$usrgrp['usrgrpid']][$db_right['id']]['new'] = PERM_NONE;
					$changed_permissions[$usrgrp['usrgrpid']][$db_right['id']]['old'] = $db_right['permission'];
				}
			}
			unset($usrgrp);
		}

		if ($changed_permissions) {
			$ugset_permissions = self::getUgSetPermissions($changed_permissions);

			if ($ugset_permissions) {
				$db_ugset_permissions = self::getDbUgSetPermissions($ugset_permissions);

				self::updatePermissions($ugset_permissions, $db_ugset_permissions);
			}
		}

		if ($ins_rights) {
			$rightids = DB::insertBatch('rights', $ins_rights);
		}

		if ($upd_rights) {
			DB::update('rights', $upd_rights);
		}

		if ($del_rightids) {
			DB::delete('rights', ['rightid' => $del_rightids]);
		}

		foreach (['hostgroup_rights', 'templategroup_rights'] as $parameter) {
			foreach ($usrgrps as &$usrgrp) {
				if (!array_key_exists($parameter, $usrgrp)) {
					continue;
				}

				foreach ($usrgrp[$parameter] as &$right) {
					if (!array_key_exists('rightid', $right)) {
						$right['rightid'] = array_shift($rightids);
					}
				}
				unset($right);
			}
			unset($usrgrp);
		}
	}

	private static function getUgSetPermissions(array $changed_permissions): array {
		$ugset_permissions = [];

		$options = [
			'output' => ['usrgrpid', 'ugsetid'],
			'filter' => [
				'usrgrpid' => array_keys($changed_permissions)
			]
		];
		$result = DBselect(DB::makeSql('ugset_group', $options));

		while ($row = DBfetch($result)) {
			foreach ($changed_permissions[$row['usrgrpid']] as $groupid => $permission) {
				if (!array_key_exists($row['ugsetid'], $ugset_permissions)
						|| !array_key_exists($groupid, $ugset_permissions[$row['ugsetid']])) {
					$ugset_permissions[$row['ugsetid']][$groupid]['new'] = $permission['new'];
					$ugset_permissions[$row['ugsetid']][$groupid]['old'] = $permission['old'];
					$ugset_permissions[$row['ugsetid']][$groupid]['usrgrp_count'] = 1;
				}
				else {
					if ($ugset_permissions[$row['ugsetid']][$groupid]['new'] != PERM_DENY
							&& ($permission['new'] > $ugset_permissions[$row['ugsetid']][$groupid]['new']
								|| $permission['new'] == PERM_DENY)) {
						$ugset_permissions[$row['ugsetid']][$groupid]['new'] = $permission['new'];
					}

					if ($permission['old'] == $ugset_permissions[$row['ugsetid']][$groupid]['old']) {
						$ugset_permissions[$row['ugsetid']][$groupid]['usrgrp_count']++;
					}
					elseif ($ugset_permissions[$row['ugsetid']][$groupid]['old'] != PERM_DENY
							&& ($permission['old'] > $ugset_permissions[$row['ugsetid']][$groupid]['old']
								|| $permission['old'] == PERM_DENY)) {
						$ugset_permissions[$row['ugsetid']][$groupid]['old'] = $permission['old'];
						$ugset_permissions[$row['ugsetid']][$groupid]['usrgrp_count'] = 1;
					}
				}
			}
		}

		return $ugset_permissions;
	}

	private static function getDbUgSetPermissions(array $ugset_permissions): array {
		$db_ugset_permissions = array_fill_keys(array_keys($ugset_permissions), []);

		$result = DBselect(
			'SELECT ugg.ugsetid,ugg.usrgrpid,r.id,r.permission'.
			' FROM ugset_group ugg'.
			' JOIN rights r ON ugg.usrgrpid=r.groupid'.
			' WHERE '.dbConditionId('ugg.ugsetid', array_keys($ugset_permissions))
		);

		while ($row = DBfetch($result)) {
			if (!array_key_exists($row['ugsetid'], $db_ugset_permissions)
					|| !array_key_exists($row['id'], $db_ugset_permissions[$row['ugsetid']])) {
				$db_ugset_permissions[$row['ugsetid']][$row['id']]['old'] = $row['permission'];
				$db_ugset_permissions[$row['ugsetid']][$row['id']]['usrgrp_count'] = 1;
			}
			else {
				if ($row['permission'] == $db_ugset_permissions[$row['ugsetid']][$row['id']]['old']) {
					$db_ugset_permissions[$row['ugsetid']][$row['id']]['usrgrp_count']++;
				}
				elseif ($db_ugset_permissions[$row['ugsetid']][$row['id']]['old'] != PERM_DENY
					&& ($row['permission'] > $db_ugset_permissions[$row['ugsetid']][$row['id']]['old']
						|| $row['permission'] == PERM_DENY)) {
					$db_ugset_permissions[$row['ugsetid']][$row['id']]['old'] = $row['permission'];
					$db_ugset_permissions[$row['ugsetid']][$row['id']]['usrgrp_count'] = 1;
				}
			}
		}

		return $db_ugset_permissions;
	}

	private static function updatePermissions(array $ugset_permissions, array $db_ugset_permissions): void {
		$ins_permissions = [];
		$upd_permissions = [];
		$del_permissions = [];

		self::unsetUnhangedUgSetPermissions($ugset_permissions, $db_ugset_permissions);

		if (!$ugset_permissions) {
			return;
		}

		[$permissions, $db_permissions] = self::getPermissions($ugset_permissions, $db_ugset_permissions);

		if (!$permissions) {
			return;
		}

		foreach ($permissions as $ugsetid => $hgset_permissions) {
			foreach ($hgset_permissions as $hgsetid => $permission) {
				$db_permission = $db_permissions[$ugsetid][$hgsetid];

				if ($permission >= PERM_READ) {
					if ($db_permission >= PERM_READ) {
						if ($permission != $db_permission) {
							$upd_permissions[] = [
								'values' => ['permission' => $permission],
								'where' => ['ugsetid' => $ugsetid, 'hgsetid' => $hgsetid]
							];
						}
					}
					else {
						$ins_permissions[] = [
							'ugsetid' => $ugsetid,
							'hgsetid' => $hgsetid,
							'permission' => $permission
						];
					}
				}
				elseif ($db_permission >= PERM_READ) {
					$del_permissions[] = dbConditionId('ugsetid', [$ugsetid]).
						' AND '.dbConditionId('hgsetid', [$hgsetid]);
				}
			}
		}

		if ($del_permissions) {
			DBexecute('DELETE FROM permission WHERE ('.implode(') OR (', $del_permissions).')');
		}

		if ($upd_permissions) {
			DB::update('permission', $upd_permissions);
		}

		if ($ins_permissions) {
			DB::insert('permission', $ins_permissions, false);
		}
	}

	private static function unsetUnhangedUgSetPermissions(array &$ugset_permissions,
			array &$db_ugset_permissions): void {
		foreach ($ugset_permissions as $ugsetid => $group_permissions) {
			foreach ($group_permissions as $groupid => $permission) {
				$changed = false;

				if (!array_key_exists($groupid, $db_ugset_permissions[$ugsetid])) {
					$changed = true;
				}
				else {
					$db_permission = $db_ugset_permissions[$ugsetid][$groupid];

					if ($permission['new'] > $db_permission['old']
							|| ($permission['new'] == PERM_DENY && $db_permission['old'] != PERM_DENY)
							|| ($permission['old'] == $db_permission['old']
								&& $permission['usrgrp_count'] == $db_permission['usrgrp_count'])) {
						$changed = true;
					}
				}

				if (!$changed) {
					unset($ugset_permissions[$ugsetid][$groupid]);
				}
			}

			if (!$ugset_permissions[$ugsetid]) {
				unset($ugset_permissions[$ugsetid], $db_ugset_permissions[$ugsetid]);
			}
		}
	}

	private static function getPermissions(array $ugset_permissions, array $db_ugset_permissions): array {
		$permissions = [];
		$db_permissions = [];

		$groupids = [];
		$group_ugsetids = [];

		foreach ($ugset_permissions as $ugsetid => $group_permissions) {
			$groupids += $group_permissions;

			foreach ($group_permissions as $groupid => $foo) {
				$group_ugsetids[$groupid][$ugsetid] = true;
			}

			foreach ($db_ugset_permissions[$ugsetid] as $groupid => $foo) {
				$group_ugsetids[$groupid][$ugsetid] = true;
			}
		}

		$result = DBselect(
			'SELECT hgg.hgsetid,hgg.groupid'.
			' FROM hgset_group hgg'.
			' WHERE hgg.hgsetid IN('.
					'SELECT DISTINCT hgg1.hgsetid'.
					' FROM hgset_group hgg1'.
					' WHERE '.dbConditionId('hgg1.groupid', array_keys($groupids)).
				')'.
				' AND '.dbConditionId('hgg.groupid', array_keys($group_ugsetids))
		);

		while ($row = DBfetch($result)) {
			foreach ($group_ugsetids[$row['groupid']] as $ugsetid => $foo) {
				if (!array_key_exists($row['groupid'], $ugset_permissions[$ugsetid])
						&& !array_key_exists($row['groupid'], $db_ugset_permissions[$ugsetid])) {
					continue;
				}

				$permission = array_key_exists($row['groupid'], $ugset_permissions[$ugsetid])
					? $ugset_permissions[$ugsetid][$row['groupid']]['new']
					: $db_ugset_permissions[$ugsetid][$row['groupid']]['old'];

				if (!array_key_exists($ugsetid, $permissions)
						|| !array_key_exists($row['hgsetid'], $permissions[$ugsetid])
						|| ($permissions[$ugsetid][$row['hgsetid']] != PERM_DENY
							&& ($permission > $permissions[$ugsetid][$row['hgsetid']] || $permission == PERM_DENY))) {
					$permissions[$ugsetid][$row['hgsetid']] = $permission;
				}

				$db_permission = array_key_exists($row['groupid'], $db_ugset_permissions[$ugsetid])
					? $db_ugset_permissions[$ugsetid][$row['groupid']]['old']
					: PERM_NONE;

				if (!array_key_exists($ugsetid, $db_permissions)
						|| !array_key_exists($row['hgsetid'], $db_permissions[$ugsetid])
						|| ($db_permissions[$ugsetid][$row['hgsetid']] != PERM_DENY
							&& ($db_permission > $db_permissions[$ugsetid][$row['hgsetid']]
								|| $db_permission == PERM_DENY))) {
					$db_permissions[$ugsetid][$row['hgsetid']] = $db_permission;
				}
			}
		}

		return [$permissions, $db_permissions];
	}

	/**
	 * @param array      $usrgrps
	 * @param null|array $db_usrgrps
	 */
	private static function updateTagFilters(array &$usrgrps, array $db_usrgrps = null): void {
		$ins_tag_filters = [];
		$del_tag_filterids = [];

		foreach ($usrgrps as &$usrgrp) {
			if (!array_key_exists('tag_filters', $usrgrp)) {
				continue;
			}

			$db_tag_filterids_by_tag_value = [];
			$db_tag_filters = $db_usrgrps !== null
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
	 * @param array      $groups
	 * @param null|array $db_groups
	 */
	private static function updateUsers(array &$groups, array &$db_groups = null): void {
		$users = [];
		$del_user_usrgrpids = [];

		foreach ($groups as &$group) {
			if (!array_key_exists('users', $group)) {
				continue;
			}

			$userids = array_column($group['users'], 'userid');
			$db_users = $db_groups !== null
				? array_column($db_groups[$group['usrgrpid']]['users'], null, 'userid')
				: [];

			$del_userids = array_diff(array_keys($db_users), $userids);

			if (!array_diff($userids, array_keys($db_users)) && !$del_userids) {
				unset($group['users'], $db_groups[$group['usrgrpid']]['users']);
				continue;
			}

			foreach ($del_userids as $del_userid) {
				if ($db_users[$del_userid]['userdirectoryid'] != 0) {
					unset($db_groups[$group['usrgrpid']]['users'][$db_users[$del_userid]['id']]);
				}
				else {
					$del_user_usrgrpids[$del_userid][] = $group['usrgrpid'];
				}
			}

			foreach ($group['users'] as $user) {
				$users[$user['userid']]['userid'] = $user['userid'];
				$users[$user['userid']]['usrgrps'][]['usrgrpid'] = $group['usrgrpid'];
			}

			if ($db_groups !== null) {
				foreach ($db_groups[$group['usrgrpid']]['users'] as $db_user) {
					if (!array_key_exists($db_user['userid'], $users)) {
						$users[$db_user['userid']]['userid'] = $db_user['userid'];
						$users[$db_user['userid']]['usrgrps'] = [];
					}
				}
			}

			unset($group['users'], $db_groups[$group['usrgrpid']]['users']);
		}

		if ($users) {
			CUser::updateFromUserGroup($users, $del_user_usrgrpids);
		}
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

		self::unlinkUsers($db_usrgrps);

		DB::delete('rights', ['groupid' => $usrgrpids]);
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

		if (in_array(CAuthenticationHelper::get(CAuthenticationHelper::DISABLED_USER_GROUPID), $usrgrpids)) {
			static::exception(ZBX_API_ERROR_PARAMETERS, _('Deprovisioned users group cannot be deleted.'));
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

		self::checkProvisionedUsersExist($db_usrgrps);
		self::checkUsedInProvisionGroupMapping($db_usrgrps);
	}

	private static function checkProvisionedUsersExist(array $db_user_groups): void {
		$row = DBfetch(DBselect(
			'SELECT ug.usrgrpid,u.username'.
			' FROM users_groups ug,users u'.
			' WHERE ug.userid=u.userid'.
				' AND u.userdirectoryid IS NOT NULL'.
				' AND '.dbConditionId('ug.usrgrpid', array_keys($db_user_groups)),
			1
		));

		if ($row) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot delete user group "%1$s", because it is used by provisioned user "%2$s".',
					$db_user_groups[$row['usrgrpid']]['name'], $row['username']
				)
			);
		}
	}

	private static function checkUsedInProvisionGroupMapping(array $db_usrgrps): void {
		$row = DBfetch(DBselect(
			'SELECT ud.name,ud.idp_type,udug.usrgrpid'.
			' FROM userdirectory_usrgrp udug'.
			' JOIN userdirectory_idpgroup udig ON udug.userdirectory_idpgroupid=udig.userdirectory_idpgroupid'.
			' JOIN userdirectory ud ON udig.userdirectoryid=ud.userdirectoryid'.
			' WHERE '.dbConditionId('udug.usrgrpid', array_keys($db_usrgrps)),
			1
		));

		if (!$row) {
			return;
		}

		if ($row['idp_type'] == IDP_TYPE_SAML) {
			$error = _s('Cannot delete user group "%1$s", because it is used by SAML userdirectory.',
				$db_usrgrps[$row['usrgrpid']]['name']
			);
		}
		else {
			$error = _s('Cannot delete user group "%1$s", because it is used by LDAP userdirectory "%2$s".',
				$db_usrgrps[$row['usrgrpid']]['name'], $row['name']
			);
		}

		self::exception(ZBX_API_ERROR_PARAMETERS, $error);
	}

	private static function unlinkUsers(array $db_groups): void {
		$groups = [];

		foreach ($db_groups as $db_group) {
			$groups[] = [
				'usrgrpid' => $db_group['usrgrpid'],
				'users' => []
			];
		}

		self::addAffectedObjects($groups, $db_groups);
		self::updateUsers($groups, $db_groups);
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

		self::addRelatedHostGroupRights($options, $result);
		self::addRelatedTemplateGroupRights($options, $result);

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

	private static function addRelatedHostGroupRights(array $options, array &$result): void {
		if ($options['selectHostGroupRights'] === null || $options['selectHostGroupRights'] === API_OUTPUT_COUNT) {
			return;
		}

		foreach ($result as &$row) {
			$row['hostgroup_rights'] = [];
		}
		unset($row);

		$output_fields = is_array($options['selectHostGroupRights'])
			? array_merge(['groupid'], array_intersect($options['selectHostGroupRights'], ['id', 'permission']))
			: ['groupid', 'id', 'permission'];
		$sql = 'SELECT r.'.implode(',r.', $output_fields).
			' FROM rights r'.
			' JOIN hstgrp hg ON r.id=hg.groupid'.
			' WHERE '.dbConditionInt('hg.type', [HOST_GROUP_TYPE_HOST_GROUP]).
				' AND '.dbConditionId('r.groupid', array_keys($result));

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sql .= ' AND '.dbConditionId('r.permission', [PERM_READ_WRITE, PERM_READ]);
		}

		$db_rights = DBselect($sql);

		while ($db_right = DBfetch($db_rights)) {
			$result[$db_right['groupid']]['hostgroup_rights'][] = array_diff_key($db_right, ['groupid' => true]);
		}
	}

	private static function addRelatedTemplateGroupRights(array $options, array &$result): void {
		if ($options['selectTemplateGroupRights'] === null
				|| $options['selectTemplateGroupRights'] === API_OUTPUT_COUNT) {
			return;
		}

		foreach ($result as &$row) {
			$row['templategroup_rights'] = [];
		}
		unset($row);

		$output_fields = is_array($options['selectTemplateGroupRights'])
			? array_merge(['groupid'], array_intersect($options['selectTemplateGroupRights'], ['id', 'permission']))
			: ['groupid', 'id', 'permission'];
		$sql = 'SELECT r.'.implode(',r.', $output_fields).
			' FROM rights r'.
			' JOIN hstgrp hg ON r.id=hg.groupid'.
			' WHERE '.dbConditionInt('hg.type', [HOST_GROUP_TYPE_TEMPLATE_GROUP]).
				' AND '.dbConditionId('r.groupid', array_keys($result));

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sql .= ' AND '.dbConditionId('r.permission', [PERM_READ_WRITE, PERM_READ]);
		}

		$db_rights = DBselect($sql);

		while ($db_right = DBfetch($db_rights)) {
			$result[$db_right['groupid']]['templategroup_rights'][] = array_diff_key($db_right, ['groupid' => true]);
		}
	}

	/**
	 * Add the existing rights, tag_filters and userids to $db_usrgrps whether these are affected by the update.
	 *
	 * @param array $usrgrps
	 * @param array $db_usrgrps
	 */
	private static function addAffectedObjects(array $usrgrps, array &$db_usrgrps): void {
		$usrgrpids = ['hostgroup_rights' => [], 'templategroup_rights' => [], 'tag_filters' => [], 'users' => []];

		foreach ($usrgrps as $usrgrp) {
			if (array_key_exists('hostgroup_rights', $usrgrp)) {
				$usrgrpids['hostgroup_rights'][] = $usrgrp['usrgrpid'];
				$db_usrgrps[$usrgrp['usrgrpid']]['hostgroup_rights'] = [];
			}

			if (array_key_exists('templategroup_rights', $usrgrp)) {
				$usrgrpids['templategroup_rights'][] = $usrgrp['usrgrpid'];
				$db_usrgrps[$usrgrp['usrgrpid']]['templategroup_rights'] = [];
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

		if ($usrgrpids['hostgroup_rights']) {
			$db_rights = DBselect(
				'SELECT r.rightid,r.groupid,r.permission,r.id'.
				' FROM rights r,hstgrp hg'.
				' WHERE r.id=hg.groupid'.
					' AND '.dbConditionId('r.groupid', $usrgrpids['hostgroup_rights']).
					' AND '.dbConditionInt('hg.type', [HOST_GROUP_TYPE_HOST_GROUP])
			);

			while ($db_right = DBfetch($db_rights)) {
				$db_usrgrps[$db_right['groupid']]['hostgroup_rights'][$db_right['rightid']] =
					array_diff_key($db_right, array_flip(['groupid']));
			}
		}

		if ($usrgrpids['templategroup_rights']) {
			$db_rights = DBselect(
				'SELECT r.rightid,r.groupid,r.permission,r.id'.
				' FROM rights r,hstgrp hg'.
				' WHERE r.id=hg.groupid'.
					' AND '.dbConditionId('r.groupid', $usrgrpids['templategroup_rights']).
					' AND '.dbConditionInt('hg.type', [HOST_GROUP_TYPE_TEMPLATE_GROUP])
			);

			while ($db_right = DBfetch($db_rights)) {
				$db_usrgrps[$db_right['groupid']]['templategroup_rights'][$db_right['rightid']] =
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
			$db_users = DBselect(
				'SELECT ug.id,ug.usrgrpid,ug.userid,u.userdirectoryid'.
				' FROM users_groups ug,users u'.
				' WHERE ug.userid=u.userid'.
					' AND '.dbConditionId('ug.usrgrpid', $usrgrpids['users'])
			);

			while ($db_user = DBfetch($db_users)) {
				$db_usrgrps[$db_user['usrgrpid']]['users'][$db_user['id']] =
					array_diff_key($db_user, array_flip(['usrgrpid']));
			}
		}
	}

	/**
	 * Check if user directories exist.
	 *
	 * @param array  $usrgrps
	 * @param string $usrgrps['userdirectoryid']
	 *
	 * @throws APIException
	 */
	private static function checkUserDirectories(array $usrgrps): void {
		$userdirectoryids = array_filter(array_column($usrgrps, 'userdirectoryid'));

		if (!$userdirectoryids) {
			return;
		}

		$db_userdirectories = API::UserDirectory()->get([
			'output' => [],
			'userdirectoryids' => $userdirectoryids,
			'filter' => ['idp_type' => IDP_TYPE_LDAP],
			'preservekeys' => true
		]);

		foreach ($usrgrps as $i => $usrgrp) {
			if (array_key_exists('userdirectoryid', $usrgrp) && $usrgrp['userdirectoryid'] != 0
					&& !array_key_exists($usrgrp['userdirectoryid'], $db_userdirectories)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/userdirectoryid',
						_('referred object does not exist')
					)
				);
			}
		}
	}

	/**
	 * Check for valid MFA method.
	 *
	 * @param array      $user_groups
	 * @param array|null $db_user_groups
	 *
	 * @throws APIException
	 */
	private static function checkMfaIds(array $user_groups, array $db_user_groups = null): void {
		foreach ($user_groups as $i => $user_group) {
			if (!array_key_exists('mfaid', $user_group) || $user_group['mfaid'] == 0
					|| ($db_user_groups !== null
						&& bccomp($user_group['mfaid'], $db_user_groups[$user_group['usrgrpid']]['mfaid']) == 0)) {
				unset($user_groups[$i]);
			}
		}

		if (!$user_groups) {
			return;
		}

		$db_mfas = DB::select('mfa', [
			'output' => [],
			'mfaids' => array_column($user_groups, 'mfaid'),
			'preservekeys' => true
		]);

		foreach ($user_groups as $i => $user_group) {
			if (!array_key_exists($user_group['mfaid'], $db_mfas)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/mfaid', _('object does not exist'))
				);
			}
		}
	}
}
