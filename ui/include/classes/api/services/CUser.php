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
 * Class containing methods for operations with users.
 */
class CUser extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'checkauthentication' => [],
		'login' => [],
		'logout' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'unblock' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'users';
	protected $tableAlias = 'u';
	protected $sortColumns = ['userid', 'username', 'alias']; // Field "alias" is deprecated in favor for "username".

	/**
	 * Get users data.
	 *
	 * @param array  $options
	 * @param array  $options['usrgrpids']		filter by UserGroup IDs
	 * @param array  $options['userids']		filter by User IDs
	 * @param bool   $options['type']			filter by User type [USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3]
	 * @param bool   $options['selectUsrgrps']	extend with UserGroups data for each User
	 * @param bool   $options['getAccess']		extend with access data for each User
	 * @param bool   $options['count']			output only count of objects in result. (result returned in property 'rowscount')
	 * @param string $options['pattern']		filter by Host name containing only give pattern
	 * @param int    $options['limit']			output will be limited to given number
	 * @param string $options['sortfield']		output will be sorted by given property ['userid', 'username', 'alias']
	 * @param string $options['sortorder']		output will be sorted in given order ['ASC', 'DESC']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['users' => 'u.userid'],
			'from'		=> ['users' => 'users u'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'usrgrpids'					=> null,
			'userids'					=> null,
			'mediaids'					=> null,
			'mediatypeids'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'editable'					=> false,
			'selectUsrgrps'				=> null,
			'selectMedias'				=> null,
			'selectMediatypes'			=> null,
			'selectRole'				=> null,
			'getAccess'					=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable']) {
				$sqlParts['from']['users_groups'] = 'users_groups ug';
				$sqlParts['where']['uug'] = 'u.userid=ug.userid';
				$sqlParts['where'][] = 'ug.usrgrpid IN ('.
					' SELECT uug.usrgrpid'.
					' FROM users_groups uug'.
					' WHERE uug.userid='.self::$userData['userid'].
				')';
			}
			else {
				$sqlParts['where'][] = 'u.userid='.self::$userData['userid'];
			}
		}

		// userids
		if ($options['userids'] !== null) {
			zbx_value2array($options['userids']);

			$sqlParts['where'][] = dbConditionInt('u.userid', $options['userids']);
		}

		// usrgrpids
		if ($options['usrgrpids'] !== null) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = dbConditionInt('ug.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['uug'] = 'u.userid=ug.userid';
		}

		// mediaids
		if ($options['mediaids'] !== null) {
			zbx_value2array($options['mediaids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';
		}

		// mediatypeids
		if ($options['mediatypeids'] !== null) {
			zbx_value2array($options['mediatypeids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediatypeid', $options['mediatypeids']);
			$sqlParts['where']['mu'] = 'm.userid=u.userid';
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('autologout', $options['filter']) && $options['filter']['autologout'] !== null) {
				$options['filter']['autologout'] = getTimeUnitFilters($options['filter']['autologout']);
			}

			if (array_key_exists('refresh', $options['filter']) && $options['filter']['refresh'] !== null) {
				$options['filter']['refresh'] = getTimeUnitFilters($options['filter']['refresh']);
			}

			if (isset($options['filter']['passwd'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to filter by user password.'));
			}

			$this->dbFilter('users u', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			if (isset($options['search']['passwd'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('It is not possible to search by user password.'));
			}

			zbx_db_search('users u', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$userIds = [];

		if (is_array($options['output']) && in_array('alias', $options['output'])) {
			$this->deprecated(_s('Parameter "%1$s" is deprecated.', '/output/alias'));
			$options['output'][] = 'username';
		}

		if ($options['sortfield']) {
			$options['sortfield'] = (array) $options['sortfield'];
			if (in_array('alias', $options['sortfield'])) {
				$this->deprecated(_s('Parameter "%1$s" is deprecated.', '/sortfield/alias'));
				$options['sortfield'][] = 'username';
				$options['sortfield'] = array_unique(array_diff($options['sortfield'], ['alias']));
			}
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		while ($user = DBfetch($res)) {
			unset($user['passwd']);

			if ($options['countOutput']) {
				$result = $user['rowscount'];
			}
			else {
				$userIds[$user['userid']] = $user['userid'];

				$result[$user['userid']] = $user;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		if ($options['getAccess'] !== null) {
			foreach ($result as $userid => $user) {
				$result[$userid] += ['gui_access' => 0, 'debug_mode' => 0, 'users_status' => 0];
			}

			$access = DBselect(
				'SELECT ug.userid,MAX(g.gui_access) AS gui_access,'.
					' MAX(g.debug_mode) AS debug_mode,MAX(g.users_status) AS users_status'.
					' FROM usrgrp g,users_groups ug'.
					' WHERE '.dbConditionInt('ug.userid', $userIds).
						' AND g.usrgrpid=ug.usrgrpid'.
					' GROUP BY ug.userid'
			);

			while ($userAccess = DBfetch($access)) {
				$result[$userAccess['userid']] = zbx_array_merge($result[$userAccess['userid']], $userAccess);
			}
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array $users
	 *
	 * @return array
	 */
	public function create(array $users) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'user', __FUNCTION__)
			);
		}

		$this->validateCreate($users);

		$ins_users = [];

		foreach ($users as $user) {
			unset($user['usrgrps'], $user['medias']);
			$ins_users[] = $user;
		}
		$userids = DB::insert('users', $ins_users);

		foreach ($users as $index => &$user) {
			$user['userid'] = $userids[$index];
		}
		unset($user);

		self::updateUsersGroups($users, __FUNCTION__);
		self::updateMedias($users, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USER, $users);

		return ['userids' => $userids];
	}

	/**
	 * @param array $users
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$users) {
		$locales = LANG_DEFAULT.','.implode(',', array_keys(getLocales()));
		$timezones = TIMEZONE_DEFAULT.','.implode(',', array_keys(CTimezoneHelper::getList()));
		$themes = THEME_DEFAULT.','.implode(',', array_keys(APP::getThemes()));

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['username'], ['alias']], 'fields' => [
			'username' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'alias' =>			['type' => API_STRING_UTF8, 'flags' => API_DEPRECATED, 'length' => DB::getFieldLength('users', 'username')],
			'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')],
			'passwd' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => 255],
			'url' =>			['type' => API_URL, 'length' => DB::getFieldLength('users', 'url')],
			'autologin' =>		['type' => API_INT32, 'in' => '0,1'],
			'autologout' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0,90:'.SEC_PER_DAY],
			'lang' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'in' => $locales, 'length' => DB::getFieldLength('users', 'lang')],
			'refresh' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0:'.SEC_PER_HOUR],
			'theme' =>			['type' => API_STRING_UTF8, 'in' => $themes, 'length' => DB::getFieldLength('users', 'theme')],
			'rows_per_page' =>	['type' => API_INT32, 'in' => '1:999999'],
			'timezone' =>		['type' => API_STRING_UTF8, 'in' => $timezones, 'length' => DB::getFieldLength('users', 'timezone')],
			'roleid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'user_medias' =>	['type' => API_OBJECTS, 'flags' => API_DEPRECATED, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
			]],
			'medias' =>			['type' => API_OBJECTS, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
			]]
		]];

		reset($users);
		if (!is_int(key($users))) {
			$users = [$users];
		}

		foreach ($users as $index => $user) {
			if (array_key_exists('alias', $user)) {
				if (array_key_exists('username', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'alias'));
				}

				$users[$index]['username'] = $user['alias'];
			}
		}

		if (!CApiInputValidator::validate($api_input_rules, $users, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($users as $i => &$user) {
			if (array_key_exists('alias', $user)) {
				unset($user['alias']);
			}

			if (array_key_exists('user_medias', $user)) {
				if (array_key_exists('medias', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'user_medias'));
				}

				$user['medias'] = $user['user_medias'];
				unset($user['user_medias']);
			}

			$user = $this->checkLoginOptions($user);

			if (array_key_exists('passwd', $user)) {
				$this->checkPassword($user, '/'.($i + 1).'/passwd');
			}

			/*
			 * If user is created without a password (e.g. for GROUP_GUI_ACCESS_LDAP), store an empty string
			 * as his password in database.
			 */
			$user['passwd'] = array_key_exists('passwd', $user)
				? password_hash($user['passwd'], PASSWORD_BCRYPT, ['cost' => ZBX_BCRYPT_COST])
				: '';
		}
		unset($user);

		$this->checkDuplicates(zbx_objectValues($users, 'username'));
		$this->checkLanguages(zbx_objectValues($users, 'lang'));
		$this->checkRoles(array_column($users, 'roleid'));
		$this->checkUserGroups($users, []);
		$db_mediatypes = $this->checkMediaTypes($users);
		$this->validateMediaRecipients($users, $db_mediatypes);
	}

	/**
	 * @param array $users
	 *
	 * @return array
	 */
	public function update(array $users) {
		$this->validateUpdate($users, $db_users);

		$upd_users = [];

		foreach ($users as $user) {
			$db_user = $db_users[$user['userid']];

			$upd_user = [];

			// strings
			$field_names = ['username', 'name', 'surname', 'autologout', 'passwd', 'refresh', 'url', 'lang', 'theme',
				'timezone'
			];
			foreach ($field_names as $field_name) {
				if (array_key_exists($field_name, $user) && $user[$field_name] !== $db_user[$field_name]) {
					$upd_user[$field_name] = $user[$field_name];
				}
			}

			// integers
			foreach (['autologin', 'rows_per_page', 'roleid'] as $field_name) {
				if (array_key_exists($field_name, $user) && $user[$field_name] != $db_user[$field_name]) {
					$upd_user[$field_name] = $user[$field_name];
				}
			}

			if ($upd_user) {
				$upd_users[] = [
					'values' => $upd_user,
					'where' => ['userid' => $user['userid']]
				];
			}
		}

		if ($upd_users) {
			DB::update('users', $upd_users);
		}

		self::updateUsersGroups($users, 'update', $db_users);
		self::updateMedias($users, 'update', $db_users);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users);

		return ['userids' => array_column($users, 'userid')];
	}

	/**
	 * @param array $users
	 * @param array $db_users
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$users, array &$db_users = null) {
		$locales = LANG_DEFAULT.','.implode(',', array_keys(getLocales()));
		$timezones = TIMEZONE_DEFAULT.','.implode(',', array_keys(CTimezoneHelper::getList()));
		$themes = THEME_DEFAULT.','.implode(',', array_keys(APP::getThemes()));

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['userid'], ['alias'], ['username']], 'fields' => [
			'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'username' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'alias' =>			['type' => API_STRING_UTF8, 'flags' => API_DEPRECATED, 'length' => DB::getFieldLength('users', 'username')],
			'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')],
			'passwd' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => 255],
			'url' =>			['type' => API_URL, 'length' => DB::getFieldLength('users', 'url')],
			'autologin' =>		['type' => API_INT32, 'in' => '0,1'],
			'autologout' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0,90:'.SEC_PER_DAY],
			'lang' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'in' => $locales, 'length' => DB::getFieldLength('users', 'lang')],
			'refresh' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '0:'.SEC_PER_HOUR],
			'theme' =>			['type' => API_STRING_UTF8, 'in' => $themes, 'length' => DB::getFieldLength('users', 'theme')],
			'rows_per_page' =>	['type' => API_INT32, 'in' => '1:999999'],
			'timezone' =>		['type' => API_STRING_UTF8, 'in' => $timezones, 'length' => DB::getFieldLength('users', 'timezone')],
			'roleid' =>			['type' => API_ID],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'medias' =>	['type' => API_OBJECTS, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
			]],
			'user_medias' =>	['type' => API_OBJECTS, 'flags' => API_DEPRECATED, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
			]]
		]];

		reset($users);
		if (!is_int(key($users))) {
			$users = [$users];
		}

		foreach ($users as $index => $user) {
			if (array_key_exists('alias', $user)) {
				if (array_key_exists('username', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'alias'));
				}

				$users[$index]['username'] = $user['alias'];
			}
		}

		if (!CApiInputValidator::validate($api_input_rules, $users, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = $this->get([
			'output' => [],
			'userids' => array_column($users, 'userid'),
			'editable' => true,
			'preservekeys' => true
		]);

		// 'passwd' can't be received by the user.get method
		$db_users = DB::select('users', [
			'output' => ['userid', 'username', 'name', 'surname', 'passwd', 'url', 'autologin', 'autologout', 'lang',
				'refresh', 'theme', 'rows_per_page', 'timezone', 'roleid'
			],
			'userids' => array_keys($db_users),
			'preservekeys' => true
		]);

		// Get readonly super admin role ID and name.
		$db_roles = DBfetchArray(DBselect(
			'SELECT roleid,name'.
			' FROM role'.
			' WHERE type='.USER_TYPE_SUPER_ADMIN.
				' AND readonly=1'
		));
		$readonly_superadmin_role = $db_roles[0];

		$superadminids_to_update = [];
		$usernames = [];
		$check_roleids = [];

		foreach ($users as $i => &$user) {
			if (array_key_exists('alias', $user)) {
				unset($user['alias']);
			}

			if (array_key_exists('user_medias', $user)) {
				if (array_key_exists('medias', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'user_medias'));
				}

				$user['medias'] = $user['user_medias'];
				unset($user['user_medias']);
			}

			if (!array_key_exists($user['userid'], $db_users)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_user = $db_users[$user['userid']];

			if (array_key_exists('username', $user) && $user['username'] !== $db_user['username']) {
				if ($db_user['username'] === ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot rename guest user.'));
				}

				$usernames[] = $user['username'];
			}

			$user = $this->checkLoginOptions($user);

			if (array_key_exists('passwd', $user)) {
				$user_data = $user + array_intersect_key($db_user, array_flip(['username', 'name', 'surname']));
				$this->checkPassword($user_data, '/'.($i + 1).'/passwd');

				if ($db_user['username'] == ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set password for user "guest".'));
				}

				$user['passwd'] = password_hash($user['passwd'], PASSWORD_BCRYPT, ['cost' => ZBX_BCRYPT_COST]);
			}

			if ($db_user['username'] == ZBX_GUEST_USER) {
				if (array_key_exists('lang', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set language for user "guest".'));
				}
				if (array_key_exists('theme', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set theme for user "guest".'));
				}
			}

			if (array_key_exists('roleid', $user) && $user['roleid'] != $db_user['roleid']) {
				if ($db_user['roleid'] == $readonly_superadmin_role['roleid']) {
					$superadminids_to_update[] = $user['userid'];
				}

				$check_roleids[] = $user['roleid'];
			}
		}
		unset($user);

		// Check that at least one active user will remain with readonly super admin role.
		if ($superadminids_to_update) {
			$db_superadmins = DBselect(
				'SELECT NULL'.
				' FROM users u'.
				' WHERE u.roleid='.$readonly_superadmin_role['roleid'].
					' AND '.dbConditionId('u.userid', $superadminids_to_update, true).
					' AND EXISTS('.
						'SELECT NULL'.
						' FROM usrgrp g,users_groups ug'.
						' WHERE g.usrgrpid=ug.usrgrpid'.
							' AND ug.userid=u.userid'.
						' GROUP BY ug.userid'.
						' HAVING MAX(g.gui_access)<'.GROUP_GUI_ACCESS_DISABLED.
							' AND MAX(g.users_status)='.GROUP_STATUS_ENABLED.
					')'
			, 1);

			if (!DBfetch($db_superadmins)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('At least one active user must exist with role "%1$s".', $readonly_superadmin_role['name'])
				);
			}
		}

		self::addAffectedObjects($users, $db_users);

		if ($usernames) {
			$this->checkDuplicates($usernames);
		}
		$this->checkLanguages(zbx_objectValues($users, 'lang'));
		if ($check_roleids) {
			$this->checkRoles($check_roleids);
		}
		$this->checkUserGroups($users, $db_users);
		$db_mediatypes = $this->checkMediaTypes($users);
		$this->validateMediaRecipients($users, $db_mediatypes);
		$this->checkHimself($users);
	}

	/**
	 * Add the existing medias and user groups to $db_users whether these are affected by the update.
	 *
	 * @static
	 *
	 * @param array $users
	 * @param array $db_users
	 */
	private static function addAffectedObjects(array $users, array &$db_users): void {
		$userids = ['usrgrps' => [], 'medias' => []];

		foreach ($users as $user) {
			if (array_key_exists('usrgrps', $user)) {
				$userids['usrgrps'][] = $user['userid'];
				$db_users[$user['userid']]['usrgrps'] = [];
			}

			if (array_key_exists('medias', $user)) {
				$userids['medias'][] = $user['userid'];
				$db_users[$user['userid']]['medias'] = [];
			}
		}

		if ($userids['usrgrps']) {
			$options = [
				'output' => ['id', 'usrgrpid', 'userid'],
				'filter' => ['userid' => $userids['usrgrps']]
			];
			$db_usrgrps = DBselect(DB::makeSql('users_groups', $options));

			while ($db_usrgrp = DBfetch($db_usrgrps)) {
				$db_users[$db_usrgrp['userid']]['usrgrps'][$db_usrgrp['id']] =
					array_diff_key($db_usrgrp, array_flip(['userid']));
			}
		}

		if ($userids['medias']) {
			$options = [
				'output' => ['mediaid', 'userid', 'mediatypeid', 'sendto', 'active', 'severity', 'period'],
				'filter' => ['userid' => $userids['medias']]
			];
			$db_medias = DBselect(DB::makeSql('media', $options));

			while ($db_media = DBfetch($db_medias)) {
				$db_users[$db_media['userid']]['medias'][$db_media['mediaid']] =
					array_diff_key($db_media, array_flip(['userid']));
			}
		}
	}

	/**
	 * Check for duplicated users.
	 *
	 * @param array $usernames
	 *
	 * @throws APIException  if user already exists.
	 */
	private function checkDuplicates(array $usernames) {
		$db_users = DB::select('users', [
			'output' => ['username'],
			'filter' => ['username' => $usernames],
			'limit' => 1
		]);

		if ($db_users) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User with username "%1$s" already exists.', $db_users[0]['username'])
			);
		}
	}

	/**
	 * Check for valid user groups.
	 *
	 * @param array $users
	 * @param array $users[]['passwd']  (optional)
	 * @param array $users[]['usrgrps']  (optional)
	 * @param array $db_users
	 * @param array $db_users[]['passwd']
	 *
	 * @throws APIException  if user groups is not exists.
	 */
	private function checkUserGroups(array $users, array $db_users) {
		$usrgrpids = [];

		foreach ($users as $user) {
			if (array_key_exists('usrgrps', $user)) {
				foreach ($user['usrgrps'] as $usrgrp) {
					$usrgrpids[$usrgrp['usrgrpid']] = true;
				}
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys($usrgrpids);

		$db_usrgrps = DB::select('usrgrp', [
			'output' => ['gui_access'],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
		}

		foreach ($users as $user) {
			if (array_key_exists('passwd', $user)) {
				$passwd = $user['passwd'];
			}
			elseif (array_key_exists('userid', $user) && array_key_exists($user['userid'], $db_users)) {
				$passwd = $db_users[$user['userid']]['passwd'];
			}
			else {
				$passwd = '';
			}

			// Do not allow empty password for users with GROUP_GUI_ACCESS_INTERNAL.
			if ($passwd === '' && self::hasInternalAuth($user, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'passwd', _('cannot be empty'))
				);
			}
		}
	}

	/**
	 * Check if specified language has dependent locale installed.
	 *
	 * @param array $languages
	 *
	 * @throws APIException if language locale is not installed.
	 */
	private function checkLanguages(array $languages) {
		foreach ($languages as $lang) {
			if ($lang !== LANG_DEFAULT && !setlocale(LC_MONETARY, zbx_locale_variants($lang))) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Language "%1$s" is not supported.', $lang));
			}
		}
	}

	/**
	 * Check for valid user roles.
	 *
	 * @param array $roleids
	 *
	 * @throws APIException
	 */
	private function checkRoles(array $roleids): void {
		$db_roles = DB::select('role', [
			'output' => ['roleid'],
			'roleids' => $roleids,
			'preservekeys' => true
		]);

		foreach ($roleids as $roleid) {
			if (!array_key_exists($roleid, $db_roles)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User role with ID "%1$s" is not available.', $roleid));
			}
		}
	}

	/**
	 * Returns true if user has internal authentication type.
	 *
	 * @param array  $user
	 * @param string $user['usrgrps'][]['usrgrpid']
	 * @param array  $db_usrgrps
	 * @param int    $db_usrgrps[usrgrpid]['gui_access']
	 *
	 * @return bool
	 */
	private static function hasInternalAuth($user, $db_usrgrps) {
		$system_gui_access =
			(CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE) == ZBX_AUTH_INTERNAL)
				? GROUP_GUI_ACCESS_INTERNAL
				: GROUP_GUI_ACCESS_LDAP;

		foreach($user['usrgrps'] as $usrgrp) {
			$gui_access = (int) $db_usrgrps[$usrgrp['usrgrpid']]['gui_access'];
			$gui_access = ($gui_access == GROUP_GUI_ACCESS_SYSTEM) ? $system_gui_access : $gui_access;

			if ($gui_access == GROUP_GUI_ACCESS_INTERNAL) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check for valid media types.
	 *
	 * @param array $users                               Array of users.
	 * @param array $users[]['medias']       (optional)  Array of user medias.
	 *
	 * @throws APIException if user media type does not exist.
	 *
	 * @return array                                     Returns valid media types.
	 */
	private function checkMediaTypes(array $users) {
		$mediatypeids = [];

		foreach ($users as $user) {
			if (array_key_exists('medias', $user)) {
				foreach ($user['medias'] as $media) {
					$mediatypeids[$media['mediatypeid']] = true;
				}
			}
		}

		if (!$mediatypeids) {
			return [];
		}

		$mediatypeids = array_keys($mediatypeids);

		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'type'],
			'mediatypeids' => $mediatypeids,
			'preservekeys' => true
		]);

		foreach ($mediatypeids as $mediatypeid) {
			if (!array_key_exists($mediatypeid, $db_mediatypes)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Media type with ID "%1$s" is not available.', $mediatypeid)
				);
			}
		}

		return $db_mediatypes;
	}

	/**
	 * Check if the passed 'sendto' value is a valid input according to the mediatype. Currently validates
	 * only e-mail media types.
	 *
	 * @param array         $users                                    Array of users.
	 * @param string        $users[]['medias'][]['mediatypeid']       Media type ID.
	 * @param array|string  $users[]['medias'][]['sendto']            Address where to send the alert.
	 * @param array         $db_mediatypes                            List of available media types.
	 *
	 * @throws APIException if e-mail is not valid or exceeds maximum DB field length.
	 */
	private function validateMediaRecipients(array $users, array $db_mediatypes) {
		if ($db_mediatypes) {
			$email_mediatypes = [];

			foreach ($db_mediatypes as $db_mediatype) {
				if ($db_mediatype['type'] == MEDIA_TYPE_EMAIL) {
					$email_mediatypes[$db_mediatype['mediatypeid']] = true;
				}
			}

			$max_length = DB::getFieldLength('media', 'sendto');
			$email_validator = new CEmailValidator();

			foreach ($users as $user) {
				if (array_key_exists('medias', $user)) {
					foreach ($user['medias'] as $media) {
						/*
						 * For non-email media types only one value allowed. Since value is normalized, need to validate
						 * if array contains only one item. If there are more than one string, error message is
						 * displayed, indicating that passed value is not a string.
						 */
						if (!array_key_exists($media['mediatypeid'], $email_mediatypes)
								&& count($media['sendto']) > 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Invalid parameter "%1$s": %2$s.', 'sendto', _('a character string is expected'))
							);
						}

						/*
						 * If input value is an array with empty string, ApiInputValidator identifies it as valid since
						 * values are normalized. That's why value must be revalidated.
						 */
						foreach ($media['sendto'] as $sendto) {
							if ($sendto === '') {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Invalid parameter "%1$s": %2$s.', 'sendto', _('cannot be empty'))
								);
							}
						}

						/*
						 * If media type is email, validate each given string against email pattern.
						 * Additionally, total length of emails must be checked, because all media type emails are
						 * separated by newline and stored as a string in single database field. Newline characters
						 * consumes extra space, so additional validation must be made.
						 */
						if (array_key_exists($media['mediatypeid'], $email_mediatypes)) {
							foreach ($media['sendto'] as $sendto) {
								if (!$email_validator->validate($sendto)) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('Invalid email address for media type with ID "%1$s".',
											$media['mediatypeid']
										)
									);
								}
								elseif (strlen(implode("\n", $media['sendto'])) > $max_length) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('Maximum total length of email address exceeded for media type with ID "%1$s".',
											$media['mediatypeid']
										)
									);
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Additional check to exclude an opportunity to deactivate himself.
	 *
	 * @param array  $users
	 * @param array  $users[]['usrgrps']  (optional)
	 *
	 * @throws APIException
	 */
	private function checkHimself(array $users) {
		foreach ($users as $user) {
			if (bccomp($user['userid'], self::$userData['userid']) == 0) {
				if (array_key_exists('roleid', $user) && $user['roleid'] != self::$userData['roleid']) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot change own role.'));
				}

				if (array_key_exists('usrgrps', $user)) {
					$db_usrgrps = DB::select('usrgrp', [
						'output' => ['gui_access', 'users_status'],
						'usrgrpids' => zbx_objectValues($user['usrgrps'], 'usrgrpid')
					]);

					foreach ($db_usrgrps as $db_usrgrp) {
						if ($db_usrgrp['gui_access'] == GROUP_GUI_ACCESS_DISABLED
								|| $db_usrgrp['users_status'] == GROUP_STATUS_DISABLED) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_('User cannot add himself to a disabled group or a group with disabled GUI access.')
							);
						}
					}
				}

				break;
			}
		}
	}

	/**
	 * Additional check to exclude an opportunity to enable auto-login and auto-logout options together..
	 *
	 * @param array  $user
	 * @param int    $user[]['autologin']   (optional)
	 * @param string $user[]['autologout']  (optional)
	 *
	 * @throws APIException
	 */
	private function checkLoginOptions(array $user) {
		if (!array_key_exists('autologout', $user) && array_key_exists('autologin', $user) && $user['autologin'] != 0) {
			$user['autologout'] = '0';
		}

		if (!array_key_exists('autologin', $user) && array_key_exists('autologout', $user)
				&& timeUnitToSeconds($user['autologout']) != 0) {
			$user['autologin'] = 0;
		}

		if (array_key_exists('autologin', $user) && array_key_exists('autologout', $user)
				&& $user['autologin'] != 0 && timeUnitToSeconds($user['autologout']) != 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_('Auto-login and auto-logout options cannot be enabled together.')
			);
		}

		return $user;
	}

	/**
	 * Update table "users_groups" and populate users.usrgrps by "id" property.
	 *
	 * @static
	 *
	 * @param array      $users
	 * @param string     $method
	 * @param null|array $db_users
	 */
	private static function updateUsersGroups(array &$users, string $method, array $db_users = null): void {
		$ins_users_groups = [];
		$del_ids = [];

		foreach ($users as &$user) {
			if (!array_key_exists('usrgrps', $user)) {
				continue;
			}

			$db_usrgrps = ($method === 'update')
				? array_column($db_users[$user['userid']]['usrgrps'], null, 'usrgrpid')
				: [];

			foreach ($user['usrgrps'] as &$usrgrp) {
				if (array_key_exists($usrgrp['usrgrpid'], $db_usrgrps)) {
					$usrgrp['id'] = $db_usrgrps[$usrgrp['usrgrpid']]['id'];
					unset($db_usrgrps[$usrgrp['usrgrpid']]);
				}
				else {
					$ins_users_groups[] = [
						'userid' => $user['userid'],
						'usrgrpid' => $usrgrp['usrgrpid']
					];
				}
			}
			unset($usrgrp);

			$del_ids = array_merge($del_ids, array_column($db_usrgrps, 'id'));
		}
		unset($user);

		if ($del_ids) {
			DB::delete('users_groups', ['id' => $del_ids]);
		}

		if ($ins_users_groups) {
			$ids = DB::insertBatch('users_groups', $ins_users_groups);
		}

		foreach ($users as &$user) {
			if (!array_key_exists('usrgrps', $user)) {
				continue;
			}

			foreach ($user['usrgrps'] as &$usrgrp) {
				if (!array_key_exists('id', $usrgrp)) {
					$usrgrp['id'] = array_shift($ids);
				}
			}
			unset($usrgrp);
		}
		unset($user);
	}

	/**
	 * Auxiliary function for updateMedias().
	 *
	 * @static
	 *
	 * @param array  $medias
	 * @param string $mediatypeid
	 * @param string $sendto
	 *
	 * @return int
	 */
	private static function getSimilarMedia(array $medias, $mediatypeid, $sendto) {
		foreach ($medias as $index => $media) {
			if (bccomp($media['mediatypeid'], $mediatypeid) == 0 && $media['sendto'] === $sendto) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * Update table "media" and populate users.medias by "mediaid" property. Also this function converts "sendto" to the
	 * string.
	 *
	 * @static
	 *
	 * @param array      $users
	 * @param string     $method
	 * @param null|array $db_users
	 */
	private static function updateMedias(array &$users, string $method, array $db_users = null): void {
		$ins_medias = [];
		$upd_medias = [];
		$del_mediaids = [];

		foreach ($users as &$user) {
			if (!array_key_exists('medias', $user)) {
				continue;
			}

			$db_medias = ($method === 'update') ? $db_users[$user['userid']]['medias'] : [];

			foreach ($user['medias'] as &$media) {
				$media['sendto'] = implode("\n", $media['sendto']);

				$index = self::getSimilarMedia($db_medias, $media['mediatypeid'], $media['sendto']);

				if ($index != -1) {
					$db_media = $db_medias[$index];

					$upd_media = DB::getUpdatedValues('media', $media, $db_media);

					if ($upd_media) {
						$upd_medias[] = [
							'values' => $upd_media,
							'where' => ['mediaid' => $db_media['mediaid']]
						];
					}

					$media['mediaid'] = $db_media['mediaid'];
					unset($db_medias[$index]);
				}
				else {
					$ins_medias[] = ['userid' => $user['userid']] + $media;
				}
			}
			unset($media);

			$del_mediaids = array_merge($del_mediaids, array_column($db_medias, 'mediaid'));
		}
		unset($user);

		if ($del_mediaids) {
			DB::delete('media', ['mediaid' => $del_mediaids]);
		}

		if ($upd_medias) {
			DB::update('media', $upd_medias);
		}

		if ($ins_medias) {
			$mediaids = DB::insert('media', $ins_medias);
		}

		foreach ($users as &$user) {
			if (!array_key_exists('medias', $user)) {
				continue;
			}

			foreach ($user['medias'] as &$media) {
				if (!array_key_exists('mediaid', $media)) {
					$media['mediaid'] = array_shift($mediaids);
				}
			}
			unset($media);
		}
		unset($user);
	}

	/**
	 * @param array $userids
	 *
	 * @return array
	 */
	public function delete(array $userids) {
		$this->validateDelete($userids, $db_users);

		DB::delete('media', ['userid' => $userids]);
		DB::delete('profiles', ['userid' => $userids]);
		DB::delete('users_groups', ['userid' => $userids]);
		DB::update('token', [
			'values' => ['creator_userid' => null],
			'where' => ['creator_userid' => $userids]
		]);
		DB::update('event_suppress', [
			'values' => ['userid' => null],
			'where' => ['userid' => $userids]
		]);

		$tokenids = DB::select('token', [
			'output' => [],
			'filter' => ['userid' => $userids],
			'preservekeys' => true
		]);
		CToken::deleteForce(array_keys($tokenids), false);

		DB::delete('users', ['userid' => $userids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_USER, $db_users);

		return ['userids' => $userids];
	}

	/**
	 * @param array $userids
	 * @param array $db_users
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$userids, array &$db_users = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $userids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = $this->get([
			'output' => ['userid', 'username', 'roleid'],
			'userids' => $userids,
			'editable' => true,
			'preservekeys' => true
		]);

		// Get readonly super admin role ID and name.
		$db_roles = DBfetchArray(DBselect(
			'SELECT roleid,name'.
			' FROM role'.
			' WHERE type='.USER_TYPE_SUPER_ADMIN.
				' AND readonly=1'
		));
		$readonly_superadmin_role = $db_roles[0];

		$superadminids_to_delete = [];

		foreach ($userids as $userid) {
			if (!array_key_exists($userid, $db_users)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_user = $db_users[$userid];

			if (bccomp($userid, self::$userData['userid']) == 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('User is not allowed to delete himself.'));
			}

			if ($db_user['username'] == ZBX_GUEST_USER) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot delete Zabbix internal user "%1$s", try disabling that user.', ZBX_GUEST_USER)
				);
			}

			if ($db_user['roleid'] == $readonly_superadmin_role['roleid']) {
				$superadminids_to_delete[] = $userid;
			}
		}

		// Check that at least one user will remain with readonly super admin role.
		if ($superadminids_to_delete) {
			$db_superadmins = DBselect(
				'SELECT NULL'.
				' FROM users u'.
				' WHERE u.roleid='.$readonly_superadmin_role['roleid'].
					' AND '.dbConditionId('u.userid', $superadminids_to_delete, true).
					' AND EXISTS('.
						'SELECT NULL'.
						' FROM usrgrp g,users_groups ug'.
						' WHERE g.usrgrpid=ug.usrgrpid'.
							' AND ug.userid=u.userid'.
						' GROUP BY ug.userid'.
						' HAVING MAX(g.gui_access)<'.GROUP_GUI_ACCESS_DISABLED.
							' AND MAX(g.users_status)='.GROUP_STATUS_ENABLED.
					')'
			, 1);

			if (!DBfetch($db_superadmins)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('At least one active user must exist with role "%1$s".', $readonly_superadmin_role['name'])
				);
			}
		}

		// Check if deleted users used in actions.
		$db_actions = DBselect(
			'SELECT a.name,om.userid'.
			' FROM opmessage_usr om,operations o,actions a'.
			' WHERE om.operationid=o.operationid'.
				' AND o.actionid=a.actionid'.
				' AND '.dbConditionInt('om.userid', $userids),
			1
		);

		if ($db_action = DBfetch($db_actions)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User "%1$s" is used in "%2$s" action.',
				$db_users[$db_action['userid']]['username'], $db_action['name']
			));
		}

		// Check if deleted users have a map.
		$db_maps = API::Map()->get([
			'output' => ['name', 'userid'],
			'userids' => $userids,
			'limit' => 1
		]);

		if ($db_maps) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is map "%2$s" owner.', $db_users[$db_maps[0]['userid']]['username'],
					$db_maps[0]['name']
				)
			);
		}

		// Check if deleted users have dashboards.
		$db_dashboards = API::Dashboard()->get([
			'output' => ['name', 'userid'],
			'filter' => ['userid' => $userids],
			'limit' => 1
		]);

		if ($db_dashboards) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is dashboard "%2$s" owner.', $db_users[$db_dashboards[0]['userid']]['username'],
					$db_dashboards[0]['name']
				)
			);
		}

		// Check if deleted users used in scheduled reports.
		$db_reports = DBselect(
			'SELECT r.name,r.userid,ru.userid AS recipientid,ru.access_userid AS user_creatorid,'.
					'rug.access_userid AS usrgrp_creatorid'.
			' FROM report r'.
				' LEFT JOIN report_user ru ON r.reportid=ru.reportid'.
				' LEFT JOIN report_usrgrp rug ON r.reportid=rug.reportid'.
			' WHERE '.dbConditionInt('r.userid', $userids).
				' OR '.dbConditionInt('ru.userid', $userids).
				' OR '.dbConditionInt('ru.access_userid', $userids).
				' OR '.dbConditionInt('rug.access_userid', $userids),
			1
		);

		if ($db_report = DBfetch($db_reports)) {
			if (array_key_exists($db_report['userid'], $db_users)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" is report "%2$s" owner.', $db_users[$db_report['userid']]['username'],
						$db_report['name']
					)
				);
			}

			if (array_key_exists($db_report['recipientid'], $db_users)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" is report "%2$s" recipient.', $db_users[$db_report['recipientid']]['username'],
						$db_report['name']
					)
				);
			}

			if (array_key_exists($db_report['user_creatorid'], $db_users)
					|| array_key_exists($db_report['usrgrp_creatorid'], $db_users)) {
				$creator = array_key_exists($db_report['user_creatorid'], $db_users)
					? $db_users[$db_report['user_creatorid']]
					: $db_users[$db_report['usrgrp_creatorid']];

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" is user on whose behalf report "%2$s" is created.', $creator['username'],
						$db_report['name']
					)
				);
			}
		}
	}

	public function logout($user) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => []];
		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sessionid = self::$userData['sessionid'];

		$db_sessions = DB::select('sessions', [
			'output' => ['userid'],
			'filter' => [
				'sessionid' => $sessionid,
				'status' => ZBX_SESSION_ACTIVE
			],
			'limit' => 1
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot log out.'));
		}

		DB::delete('sessions', [
			'status' => ZBX_SESSION_PASSIVE,
			'userid' => $db_sessions[0]['userid']
		]);
		DB::update('sessions', [
			'values' => ['status' => ZBX_SESSION_PASSIVE],
			'where' => ['sessionid' => $sessionid]
		]);

		self::addAuditLog(CAudit::ACTION_LOGOUT, CAudit::RESOURCE_USER);

		self::$userData = null;

		return true;
	}

	/**
	 * @param array $user
	 *
	 * @return string|array
	 */
	public function login(array $user) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'username' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'user' =>		['type' => API_STRING_UTF8, 'flags' => API_DEPRECATED, 'length' => 255],
			'password' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'userData' =>	['type' => API_FLAG]
		]];

		if (array_key_exists('user', $user)) {
			if (array_key_exists('username', $user)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Parameter "%1$s" is deprecated.', 'user'));
			}

			$user['username'] = $user['user'];
		}

		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (array_key_exists('user', $user)) {
			unset($user['user']);
		}

		$group_to_auth_map = [
			GROUP_GUI_ACCESS_SYSTEM => CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE),
			GROUP_GUI_ACCESS_INTERNAL => ZBX_AUTH_INTERNAL,
			GROUP_GUI_ACCESS_LDAP => ZBX_AUTH_LDAP,
			GROUP_GUI_ACCESS_DISABLED => CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE)
		];

		$user_data = $this->findAccessibleUser($user['username'],
			(CAuthenticationHelper::get(CAuthenticationHelper::LDAP_CASE_SENSITIVE) == ZBX_AUTH_CASE_SENSITIVE),
			CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE), true
		);

		if (array_key_exists('error', $user_data)) {
			self::addAuditLogByUser(array_key_exists('db_user', $user_data) ? $user_data['db_user']['userid'] : null,
				CWebUser::getIp(), $user['username'], CAudit::ACTION_LOGIN_FAILED, CAudit::RESOURCE_USER
			);

			self::exception(ZBX_API_ERROR_PARAMETERS, $user_data['error']);
		}

		$db_user = $this->addExtraFields($user_data['db_user'], $user_data['permissions']);
		$this->setTimezone($db_user['timezone']);

		if ($db_user['attempt_failed'] >= CSettingsHelper::get(CSettingsHelper::LOGIN_ATTEMPTS)) {
			$sec_left = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::LOGIN_BLOCK))
				- (time() - $db_user['attempt_clock']);

			if ($sec_left > 0) {
				self::addAuditLogByUser($db_user['userid'], $db_user['userip'], $db_user['username'],
					CAudit::ACTION_LOGIN_FAILED, CAudit::RESOURCE_USER
				);

				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('Incorrect user name or password or account is temporarily blocked.')
				);
			}
		}

		try {
			switch ($group_to_auth_map[$db_user['gui_access']]) {
				case ZBX_AUTH_LDAP:
					if (CAuthenticationHelper::get(CAuthenticationHelper::LDAP_CONFIGURED) == ZBX_AUTH_LDAP_DISABLED) {
						self::exception(ZBX_API_ERROR_INTERNAL, _('LDAP authentication is disabled.'));
					}

					$id = $db_user['userdirectoryid'] != 0
						? $db_user['userdirectoryid']
						: CAuthenticationHelper::get(CAuthenticationHelper::LDAP_USERDIRECTORYID);
					$userdirectory = [];

					if ($id != 0) {
						$userdirectory = API::UserDirectory()->get([
							'output' => ['userdirectoryid', 'host', 'port', 'base_dn', 'bind_dn', 'search_attribute',
								'search_filter', 'start_tls'
							],
							'userdirectoryids' => $id
						]);
						$userdirectory = reset($userdirectory);
					}

					if (!$userdirectory) {
						self::exception(ZBX_API_ERROR_INTERNAL, _('Cannot find user directory for LDAP.'));
					}

					$userdirectory += [
						'test_username' => $user['username'],
						'test_password' => $user['password']
					];
					API::UserDirectory()->test($userdirectory);
					break;

				case ZBX_AUTH_INTERNAL:
					if (!password_verify($user['password'], $db_user['passwd'])) {
						self::exception(ZBX_API_ERROR_PERMISSIONS,
							_('Incorrect user name or password or account is temporarily blocked.')
						);
					}
					break;

				default:
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions for system access.'));
			}
		}
		catch (APIException $e) {
			if ($e->getCode() == ZBX_API_ERROR_PERMISSIONS) {
				$attempt_failed = $db_user['attempt_failed'] + 1;

				DB::update('users', [
					'values' => [
						'attempt_failed' => $attempt_failed,
						'attempt_clock' => time(),
						'attempt_ip' => substr($db_user['userip'], 0, 39)
					],
					'where' => ['userid' => $db_user['userid']]
				]);

				$users = [['userid' => $db_user['userid'], 'attempt_failed' => $attempt_failed]];
				$db_users = [$db_user['userid'] => $db_user];

				self::addAuditLogByUser($db_user['userid'], $db_user['userip'], $db_user['username'],
					CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users
				);
				self::addAuditLogByUser($db_user['userid'], $db_user['userip'], $db_user['username'],
					CAudit::ACTION_LOGIN_FAILED, CAudit::RESOURCE_USER
				);

				if ($attempt_failed >= CSettingsHelper::get(CSettingsHelper::LOGIN_ATTEMPTS)) {
					self::exception(ZBX_API_ERROR_PERMISSIONS,
						_('Incorrect user name or password or account is temporarily blocked.')
					);
				}
			}

			self::exception(ZBX_API_ERROR_PERMISSIONS, $e->getMessage());
		}

		// Start session.
		unset($db_user['passwd']);
		$db_user = self::createSession($db_user);

		self::addAuditLog(CAudit::ACTION_LOGIN_SUCCESS, CAudit::RESOURCE_USER);

		return array_key_exists('userData', $user) && $user['userData'] ? $db_user : $db_user['sessionid'];
	}

	/**
	 * Method is ONLY for internal use!
	 * Login user by username. Return array with user data.
	 *
	 * @param string    $username        User username to search for.
	 * @param bool|null $case_sensitive  Perform case-sensitive search.
	 * @param int|null  $default_auth    Default system authentication type.
	 *
	 * @throws APIException if the method is called via an API call or the input is invalid.
	 *
	 * @return array
	 */
	public function loginByUsername($username, $case_sensitive = null, $default_auth = null) {
		// Check whether the method is called via an API call or from a local php file.
		if ($case_sensitive === null || $default_auth === null) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect method "%1$s.%2$s".', 'user', 'loginByUsername'));
		}

		$user_data = $this->findAccessibleUser($username, $case_sensitive, $default_auth, false);

		if (array_key_exists('error', $user_data)) {
			self::addAuditLogByUser(array_key_exists('db_user', $user_data) ? $user_data['db_user']['userid'] : null,
				CWebUser::getIp(), $username, CAudit::ACTION_LOGIN_FAILED, CAudit::RESOURCE_USER
			);

			self::exception(ZBX_API_ERROR_PARAMETERS, $user_data['error']);
		}

		$db_user = $this->addExtraFields($user_data['db_user'], $user_data['permissions']);
		$this->setTimezone($db_user['timezone']);

		unset($db_user['passwd']);
		$db_user = self::createSession($db_user);

		self::addAuditLog(CAudit::ACTION_LOGIN_SUCCESS, CAudit::RESOURCE_USER);

		return $db_user;
	}

	/**
	 * Check if session id is authenticated.
	 *
	 * @param array  $session
	 * @param string $session[]['sessionid']  (required) session id to be checked
	 * @param bool   $session[]['extend']     (optional) extend session (update lastaccess time)
	 *
	 * @return array
	 */
	public function checkAuthentication(array $session): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'sessionid' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('sessions', 'sessionid')],
			'extend' =>	['type' => API_BOOLEAN, 'default' => true]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $session, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sessionid = $session['sessionid'];

		// access DB only once per page load
		if (self::$userData !== null && self::$userData['sessionid'] === $sessionid) {
			return self::$userData;
		}

		$time = time();

		$db_sessions = DB::select('sessions', [
			'output' => ['userid', 'lastaccess'],
			'sessionids' => $sessionid,
			'filter' => ['status' => ZBX_SESSION_ACTIVE]
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		$db_session = $db_sessions[0];

		$db_users = DB::select('users', [
			'output' => ['userid', 'username', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh',
				'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone', 'roleid'
			],
			'userids' => $db_session['userid']
		]);

		// If user not exists.
		if (!$db_users) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		$db_user = $db_users[0];
		$db_user['sessionid'] = $sessionid;

		$permissions = $this->getUserGroupsPermissions($db_user['userid']);

		$db_user = $this->addExtraFields($db_user, $permissions);
		$this->setTimezone($db_user['timezone']);

		$autologout = timeUnitToSeconds($db_user['autologout']);

		// Check system permissions.
		if (($autologout != 0 && $db_session['lastaccess'] + $autologout <= $time)
				|| $permissions['users_status'] == GROUP_STATUS_DISABLED) {
			DB::delete('sessions', [
				'status' => ZBX_SESSION_PASSIVE,
				'userid' => $db_user['userid']
			]);
			DB::update('sessions', [
				'values' => ['status' => ZBX_SESSION_PASSIVE],
				'where' => ['sessionid' => $sessionid]
			]);

			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		if ($session['extend'] && $time != $db_session['lastaccess']) {
			DB::update('sessions', [
				'values' => ['lastaccess' => $time],
				'where' => ['sessionid' => $sessionid]
			]);
		}

		self::$userData = $db_user;

		return $db_user;
	}

	/**
	 * Unblock user account.
	 *
	 * @param array $userids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function unblock(array $userids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $userids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = $this->get([
			'output' => ['userid', 'username', 'attempt_failed'],
			'userids' => $userids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_users) != count($userids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$users = [];
		$upd_users = [];

		foreach ($userids as $userid) {
			$upd_user = DB::getUpdatedValues('users', ['userid' => $userid, 'attempt_failed' => 0], $db_users[$userid]);

			if ($upd_user) {
				$upd_users[] = [
					'values' => $upd_user,
					'where' => ['userid' => $userid]
				];

				$users[] = $upd_user + ['userid' => $userid];
			}
			else {
				unset($db_users[$userid]);
			}
		}

		if ($upd_users) {
			DB::update('users', $upd_users);
			self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users);
		}

		return ['userids' => $userids];
	}

	/**
	 * Returns user groups permissions of specific user.
	 *
	 * @param string $userid
	 *
	 * @return array
	 */
	private function getUserGroupsPermissions(string $userid): array {
		$permissions = [
			'debug_mode' => GROUP_DEBUG_MODE_DISABLED,
			'users_status' => GROUP_STATUS_ENABLED,
			'gui_access' => GROUP_GUI_ACCESS_SYSTEM,
			'userdirectoryid' => 0
		];

		$db_usrgrps = DBselect(
			'SELECT g.debug_mode,g.users_status,g.gui_access,g.userdirectoryid'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE g.usrgrpid=ug.usrgrpid'.
				' AND ug.userid='.$userid
		);

		$userdirectoryids = [];

		while ($db_usrgrp = DBfetch($db_usrgrps)) {
			if ($db_usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
				$permissions['debug_mode'] = GROUP_DEBUG_MODE_ENABLED;
			}

			if ($db_usrgrp['users_status'] == GROUP_STATUS_DISABLED) {
				$permissions['users_status'] = GROUP_STATUS_DISABLED;
			}

			if ($db_usrgrp['gui_access'] > $permissions['gui_access']) {
				$permissions['gui_access'] = $db_usrgrp['gui_access'];
				$userdirectoryids = [];
			}

			if ($permissions['gui_access'] === $db_usrgrp['gui_access']
					&& ($db_usrgrp['gui_access'] == GROUP_GUI_ACCESS_LDAP
						|| ($db_usrgrp['gui_access'] == GROUP_GUI_ACCESS_SYSTEM
							&& CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE) == ZBX_AUTH_LDAP
			))) {
				$userdirectoryids[$db_usrgrp['userdirectoryid']] = true;
			}
		}

		if ($userdirectoryids) {
			if (array_key_exists(0, $userdirectoryids)) {
				unset($userdirectoryids[0]);
				$default_userdirectoryid = CAuthenticationHelper::get(CAuthenticationHelper::LDAP_USERDIRECTORYID);

				if ($default_userdirectoryid != 0) {
					$userdirectoryids[$default_userdirectoryid] = true;
				}
			}

			if (count($userdirectoryids) === 1) {
				$permissions['userdirectoryid'] = array_keys($userdirectoryids)[0];
			}
			elseif (count($userdirectoryids) > 1) {
				$db_userdirectoryids = API::UserDirectory()->get([
					'output' => [],
					'userdirectoryids' => array_keys($userdirectoryids),
					'sortfield' => ['name'],
					'limit' => 1,
					'preservekeys' => true
				]);

				$permissions['userdirectoryid'] = array_keys($db_userdirectoryids)[0];
			}
		}

		return $permissions;
	}

	/**
	 * Returns user type.
	 *
	 * @param string $roleid
	 *
	 * @return int
	 */
	private function getUserType(string $roleid): int {
		return DBfetchColumn(DBselect('SELECT type FROM role WHERE roleid='.zbx_dbstr($roleid)), 'type')[0];
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$userIds = zbx_objectValues($result, 'userid');

		// adding usergroups
		if ($options['selectUsrgrps'] !== null && $options['selectUsrgrps'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'userid', 'usrgrpid', 'users_groups');

			$dbUserGroups = API::UserGroup()->get([
				'output' => $options['selectUsrgrps'],
				'usrgrpids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);

			$result = $relationMap->mapMany($result, $dbUserGroups, 'usrgrps');
		}

		// adding medias
		if ($options['selectMedias'] !== null && $options['selectMedias'] != API_OUTPUT_COUNT) {
			$db_medias = API::getApiService()->select('media', [
				'output' => $this->outputExtend($options['selectMedias'], ['userid', 'mediaid', 'mediatypeid']),
				'filter' => ['userid' => $userIds],
				'preservekeys' => true
			]);

			// 'sendto' parameter in media types with 'type' == MEDIA_TYPE_EMAIL are returned as array.
			if (($options['selectMedias'] === API_OUTPUT_EXTEND || in_array('sendto', $options['selectMedias']))
					&& $db_medias) {
				$db_email_medias = DB::select('media_type', [
					'output' => [],
					'filter' => [
						'mediatypeid' => zbx_objectValues($db_medias, 'mediatypeid'),
						'type' => MEDIA_TYPE_EMAIL
					],
					'preservekeys' => true
				]);

				foreach ($db_medias as &$db_media) {
					if (array_key_exists($db_media['mediatypeid'], $db_email_medias)) {
						$db_media['sendto'] = explode("\n", $db_media['sendto']);
					}
				}
				unset($db_media);
			}

			$relationMap = $this->createRelationMap($db_medias, 'userid', 'mediaid');

			$db_medias = $this->unsetExtraFields($db_medias, ['userid', 'mediaid', 'mediatypeid'],
				$options['selectMedias']
			);
			$result = $relationMap->mapMany($result, $db_medias, 'medias');
		}

		// adding media types
		if ($options['selectMediatypes'] !== null && $options['selectMediatypes'] != API_OUTPUT_COUNT) {
			$mediaTypes = [];
			$relationMap = $this->createRelationMap($result, 'userid', 'mediatypeid', 'media');
			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$mediaTypes = API::Mediatype()->get([
					'output' => $options['selectMediatypes'],
					'mediatypeids' => $related_ids,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapMany($result, $mediaTypes, 'mediatypes');
		}

		// adding user role
		if ($options['selectRole'] !== null && $options['selectRole'] !== API_OUTPUT_COUNT) {
			if ($options['selectRole'] === API_OUTPUT_EXTEND) {
				$options['selectRole'] = ['roleid', 'name', 'type', 'readonly'];
			}

			$db_roles = DBselect(
				'SELECT u.userid'.($options['selectRole'] ? ',r.'.implode(',r.', $options['selectRole']) : '').
				' FROM users u,role r'.
				' WHERE u.roleid=r.roleid'.
				' AND '.dbConditionInt('u.userid', $userIds)
			);

			foreach ($result as $userid => $user) {
				$result[$userid]['role'] = [];
			}

			while ($db_role = DBfetch($db_roles)) {
				$userid = $db_role['userid'];
				unset($db_role['userid']);

				$result[$userid]['role'] = $db_role;
			}
		}

		return $result;
	}

	/**
	 * Initialize session for user. Returns user data array with valid sessionid.
	 *
	 * @param array  $db_user  User data from database.
	 *
	 * @return array
	 */
	private static function createSession(array $db_user): array {
		$db_user['sessionid'] = CEncryptHelper::generateKey();

		DB::insert('sessions', [[
			'sessionid' => $db_user['sessionid'],
			'userid' => $db_user['userid'],
			'lastaccess' => time(),
			'status' => ZBX_SESSION_ACTIVE
		]], false);

		self::$userData = $db_user;

		if ($db_user['attempt_failed'] != 0) {
			$upd_user = ['attempt_failed' => 0];

			DB::update('users', [
				'values' => $upd_user,
				'where' => ['userid' => $db_user['userid']]
			]);

			$users = [$upd_user + ['userid' => $db_user['userid']]];
			$db_users = [$db_user['userid'] => $db_user];

			self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users);
		}

		return $db_user;
	}

	/**
	 * Find accessible user by username.
	 *
	 * @param string $username             User username to search for.
	 * @param bool   $case_sensitive    Perform case sensitive search.
	 * @param int    $default_auth      System default authentication type.
	 * @param bool   $do_group_check    Is actual only when $case_sensitive equals false. In HTTP authentication case
	 *                                  user username string is case insensitive string even for groups with frontend
	 *                                  access GROUP_GUI_ACCESS_INTERNAL.
	 *
	 * @return array The array with the following keys:
	 *                       - 'error' - (optional) the error message;
	 *                       - 'db_user' - (optional) contains user data from database;
	 *                       - 'permissions' - (optional) contains user permissions data.
	 */
	private function findAccessibleUser(string $username, bool $case_sensitive, int $default_auth,
			bool $do_group_check): array {
		$db_users = [];
		$group_to_auth_map = [
			GROUP_GUI_ACCESS_SYSTEM => $default_auth,
			GROUP_GUI_ACCESS_INTERNAL => ZBX_AUTH_INTERNAL,
			GROUP_GUI_ACCESS_LDAP => ZBX_AUTH_LDAP,
			GROUP_GUI_ACCESS_DISABLED => $default_auth
		];
		$fields = ['userid', 'username', 'name', 'surname', 'passwd', 'url', 'autologin', 'autologout', 'lang',
			'refresh', 'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone', 'roleid'
		];

		if ($case_sensitive) {
			$db_users = DB::select('users', [
				'output' => $fields,
				'filter' => ['username' => $username]
			]);
		}
		else {
			$db_users_rows = DBfetchArray(DBselect(
				'SELECT '.implode(',', $fields).
				' FROM users'.
					' WHERE LOWER(username)='.zbx_dbstr(strtolower($username))
			));

			if ($do_group_check) {
				// Users with ZBX_AUTH_INTERNAL access attribute 'username' is always case sensitive.
				foreach($db_users_rows as $db_user_row) {
					$permissions = $this->getUserGroupsPermissions($db_user_row['userid']);

					if ($group_to_auth_map[$permissions['gui_access']] != ZBX_AUTH_INTERNAL
							|| $db_user_row['username'] === $username) {
						$db_users[] = $db_user_row;
					}
				}
			}
			else {
				$db_users = $db_users_rows;
			}
		}

		if (!$db_users) {
			return ['error' => _('Incorrect user name or password or account is temporarily blocked.')];
		}

		if (count($db_users) > 1) {
			return ['error' => _s('Authentication failed: %1$s.', _('supplied credentials are not unique'))];
		}

		$db_user = reset($db_users);
		$permissions = $this->getUserGroupsPermissions($db_user['userid']);

		if ($permissions['users_status'] == GROUP_STATUS_DISABLED) {
			return ['error' => _('No permissions for system access.'), 'db_user' => $db_user];
		}

		return ['db_user' => $db_user, 'permissions' => $permissions];
	}

	/**
	 * Adds extra fields to database user data.
	 *
	 * @param array  $db_user
	 * @param array  $permissions
	 * @param string $permissions['debug_mode']
	 * @param string $permissions['gui_access']
	 * @param string $permissions['userdirectoryid']
	 */
	private function addExtraFields(array $db_user, array $permissions): array {
		$db_user['type'] = $this->getUserType($db_user['roleid']);
		$db_user['userip'] = CWebUser::getIp();

		$db_user['debug_mode'] = $permissions['debug_mode'];
		$db_user['gui_access'] = $permissions['gui_access'];
		$db_user['userdirectoryid'] = $permissions['userdirectoryid'];

		if ($db_user['lang'] === LANG_DEFAULT) {
			$db_user['lang'] = CSettingsHelper::getGlobal(CSettingsHelper::DEFAULT_LANG);
		}

		if ($db_user['timezone'] === TIMEZONE_DEFAULT) {
			$db_user['timezone'] = CSettingsHelper::getGlobal(CSettingsHelper::DEFAULT_TIMEZONE);
		}

		return $db_user;
	}

	/**
	 * Sets the default user timezone used by all date/time functions.
	 *
	 * @param string|null $timezone
	 */
	private function setTimezone(?string $timezone): void {
		if ($timezone !== null && $timezone !== ZBX_DEFAULT_TIMEZONE) {
			date_default_timezone_set($timezone);
		}
	}

	/**
	 * Function to validate if password meets password policy requirements.
	 *
	 * @param array  $user
	 * @param string $user['username']  (optional)
	 * @param string $user['name']      (optional)
	 * @param string $user['surname']   (optional)
	 * @param string $user['passwd']
	 * @param string $path              Password field path to display error message.
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return bool
	 */
	private function checkPassword(array $user, string $path): bool {
		$context_data = array_filter(array_intersect_key($user, array_flip(['username', 'name', 'surname'])));
		$passwd_validator = new CPasswordComplexityValidator([
			'passwd_min_length' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_MIN_LENGTH),
			'passwd_check_rules' => CAuthenticationHelper::get(CAuthenticationHelper::PASSWD_CHECK_RULES)
		]);
		$passwd_validator->setContextData($context_data);

		if ($passwd_validator->validate($user['passwd']) === false) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', $path, $passwd_validator->getError())
			);
		}

		return true;
	}
}
