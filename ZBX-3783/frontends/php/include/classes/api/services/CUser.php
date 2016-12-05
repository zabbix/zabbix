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
 * Class containing methods for operations with users.
 */
class CUser extends CApiService {

	protected $tableName = 'users';
	protected $tableAlias = 'u';
	protected $sortColumns = ['userid', 'alias'];

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
	 * @param string $options['sortfield']		output will be sorted by given property ['userid', 'alias']
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
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
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

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		while ($user = DBfetch($res)) {
			unset($user['passwd']);

			if ($options['countOutput'] !== null) {
				$result = $user['rowscount'];
			}
			else {
				$userIds[$user['userid']] = $user['userid'];

				$result[$user['userid']] = $user;
			}
		}

		if ($options['countOutput'] !== null) {
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
		if ($options['preservekeys'] === null) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Create user.
	 *
	 * @param array $users
	 *
	 * @return array
	 */
	public function create(array $users) {
		$this->validateCreate($users);

		$ins_users = [];

		foreach ($users as $user) {
			unset($user['usrgrps'], $user['user_medias']);
			$ins_users[] = $user;
		}
		$userids = DB::insert('users', $ins_users);

		foreach ($users as $index => &$user) {
			$user['userid'] = $userids[$index];
		}
		unset($user);

		$this->updateUsersGroups($users, __FUNCTION__);
		$this->updateMedias($users, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_USER, $users);

		return ['userids' => $userids];
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $users
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$users) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('You do not have permissions to create users.'));
		}

		$valid_themes = THEME_DEFAULT.','.implode(',', array_keys(Z::getThemes()));

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['alias']], 'fields' => [
			'alias' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'alias')],
			'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')],
			'passwd' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'url' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'url')],
			'autologin' =>		['type' => API_INT32, 'in' => '0,1'],
			'autologout' =>		['type' => API_INT32, 'in' => '0,90:10000'],
			'lang' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'lang')],
			'theme' =>			['type' => API_STRING_UTF8, 'in' => $valid_themes, 'length' => DB::getFieldLength('users', 'theme')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'refresh' =>		['type' => API_INT32, 'in' => '0:3600'],
			'rows_per_page' =>	['type' => API_INT32, 'in' => '1:999999'],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'user_medias' =>	['type' => API_OBJECTS, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('media', 'sendto')],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_MULTIPLE, 'length' => DB::getFieldLength('media', 'period')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $users, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($users as &$user) {
			$user = $this->checkLoginOptions($user);

			$user['passwd'] = md5($user['passwd']);
		}
		unset($user);

		$this->checkDuplicates(zbx_objectValues($users, 'alias'));
		$this->checkUserGroups($users);
		$this->checkMediaTypes($users);
	}

	/**
	 * Update user.
	 *
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
			foreach (['alias', 'name', 'surname', 'passwd', 'url', 'lang', 'theme'] as $field_name) {
				if (array_key_exists($field_name, $user) && $user[$field_name] !== $db_user[$field_name]) {
					$upd_user[$field_name] = $user[$field_name];
				}
			}
			// integers
			foreach (['autologin', 'autologout', 'type', 'refresh', 'rows_per_page'] as $field_name) {
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

		$this->updateUsersGroups($users, __FUNCTION__);
		$this->updateMedias($users, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER, $users, $db_users);

		return ['userids' => zbx_objectValues($users, 'userid')];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $users
	 * @param array $db_users
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$users, array &$db_users = null) {
		$valid_themes = THEME_DEFAULT.','.implode(',', array_keys(Z::getThemes()));

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['userid'], ['alias']], 'fields' => [
			'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'alias' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'alias')],
			'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')],
			'passwd' =>			['type' => API_STRING_UTF8, 'length' => 255],
			'url' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'url')],
			'autologin' =>		['type' => API_INT32, 'in' => '0,1'],
			'autologout' =>		['type' => API_INT32, 'in' => '0,90:10000'],
			'lang' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'lang')],
			'theme' =>			['type' => API_STRING_UTF8, 'in' => $valid_themes, 'length' => DB::getFieldLength('users', 'theme')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'refresh' =>		['type' => API_INT32, 'in' => '0:3600'],
			'rows_per_page' =>	['type' => API_INT32, 'in' => '1:999999'],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'user_medias' =>	['type' => API_OBJECTS, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('media', 'sendto')],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_MULTIPLE, 'length' => DB::getFieldLength('media', 'period')]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $users, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = $this->get([
			'output' => ['userid', 'alias', 'name', 'surname', 'passwd', 'url', 'autologin', 'autologout', 'lang',
				'theme', 'type', 'refresh', 'rows_per_page'
			],
			'userids' => zbx_objectValues($users, 'userid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$aliases = [];

		foreach ($users as &$user) {
			if (!array_key_exists($user['userid'], $db_users)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_user = $db_users[$user['userid']];

			if (array_key_exists('alias', $user) && $user['alias'] !== $db_user['alias']) {
				if ($db_user['alias'] === ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot rename guest user.'));
				}

				$aliases[] = $user['alias'];
			}

			$user = $this->checkLoginOptions($user);

			if (array_key_exists('passwd', $user)) {
				if ($db_user['alias'] == ZBX_GUEST_USER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set password for user "guest".'));
				}

				$user['passwd'] = md5($user['passwd']);
			}
		}
		unset($user);

		if ($aliases) {
			$this->checkDuplicates($aliases);
		}
		$this->checkUserGroups($users);
		$this->checkMediaTypes($users);
		$this->checkHimself($users);
	}

	/**
	 * Check for duplicated users.
	 *
	 * @param array $aliases
	 *
	 * @throws APIException  if user already exists.
	 */
	private function checkDuplicates(array $aliases) {
		$db_users = API::getApiService()->select('users', [
			'output' => ['alias'],
			'filter' => ['alias' => $aliases],
			'limit' => 1
		]);

		if ($db_users) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User with alias "%s" already exists.', $db_users[0]['alias'])
			);
		}
	}

	/**
	 * Check for valid user groups.
	 *
	 * @param array $users
	 * @param array $users[]['usrgrps']  (optional)
	 *
	 * @throws APIException  if user groups is not exists.
	 */
	private function checkUserGroups(array $users) {
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

		$db_usrgrps = API::getApiService()->select('usrgrp', [
			'output' => [],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
		}
	}

	/**
	 * Check for valid media types.
	 *
	 * @param array $users
	 * @param array $users[]['user_medias']  (optional)
	 *
	 * @throws APIException  if user media type is not exists.
	 */
	private function checkMediaTypes(array $users) {
		$mediatypeids = [];

		foreach ($users as $user) {
			if (array_key_exists('user_medias', $user)) {
				foreach ($user['user_medias'] as $media) {
					$mediatypeids[$media['mediatypeid']] = true;
				}
			}
		}

		if (!$mediatypeids) {
			return;
		}

		$mediatypeids = array_keys($mediatypeids);

		$db_mediatypes = API::getApiService()->select('media_type', [
			'output' => [],
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
				if (array_key_exists('type', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot alter user type for himself.'));
				}

				if (array_key_exists('usrgrps', $user)) {
					$db_usrgrps = API::getApiService()->select('usrgrp', [
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
	 * @param array $user
	 * @param int   $user[]['autologin']   (optional)
	 * @param int   $user[]['autologout']  (optional)
	 *
	 * @throws APIException
	 */
	private function checkLoginOptions(array $user) {
		if (!array_key_exists('autologout', $user) && array_key_exists('autologin', $user) && $user['autologin'] != 0) {
			$user['autologout'] = 0;
		}

		if (!array_key_exists('autologin', $user) && array_key_exists('autologout', $user)
				&& $user['autologout'] != 0) {
			$user['autologin'] = 0;
		}

		if (array_key_exists('autologin', $user) && array_key_exists('autologout', $user)
				&& $user['autologin'] != 0 && $user['autologout'] != 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_('Auto-login and auto-logout options cannot be enabled together.')
			);
		}

		return $user;
	}

	/**
	 * Update table "users_groups".
	 *
	 * @param array  $users
	 * @param string $method
	 */
	private function updateUsersGroups(array $users, $method) {
		$users_groups = [];

		foreach ($users as $user) {
			if (array_key_exists('usrgrps', $user)) {
				$users_groups[$user['userid']] = [];

				foreach ($user['usrgrps'] as $usrgrp) {
					$users_groups[$user['userid']][$usrgrp['usrgrpid']] = true;
				}
			}
		}

		if (!$users_groups) {
			return;
		}

		$db_users_groups = ($method === 'update')
			? API::getApiService()->select('users_groups', [
				'output' => ['id', 'usrgrpid', 'userid'],
				'filter' => ['userid' => array_keys($users_groups)]
			])
			: [];

		$ins_users_groups = [];
		$del_ids = [];

		foreach ($db_users_groups as $db_user_group) {
			if (array_key_exists($db_user_group['usrgrpid'], $users_groups[$db_user_group['userid']])) {
				unset($users_groups[$db_user_group['userid']][$db_user_group['usrgrpid']]);
			}
			else {
				$del_ids[] = $db_user_group['id'];
			}
		}

		foreach ($users_groups as $userid => $usrgrpids) {
			foreach (array_keys($usrgrpids) as $usrgrpid) {
				$ins_users_groups[] = [
					'userid' => $userid,
					'usrgrpid' => $usrgrpid
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
	 * Auxiliary function for updateMedias().
	 *
	 * @param array  $medias
	 * @param string $mediatypeid
	 * @param string $sendto
	 *
	 * @return int
	 */
	private function getSimilarMedia(array $medias, $mediatypeid, $sendto) {
		foreach ($medias as $index => $media) {
			if (bccomp($media['mediatypeid'], $mediatypeid) == 0 && $media['sendto'] === $sendto) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * Update table "media".
	 *
	 * @param array  $users
	 * @param string $method
	 */
	private function updateMedias(array $users, $method) {
		$medias = [];

		foreach ($users as $user) {
			if (array_key_exists('user_medias', $user)) {
				$medias[$user['userid']] = [];

				foreach ($user['user_medias'] as $media) {
					$medias[$user['userid']][] = $media;
				}
			}
		}

		if (!$medias) {
			return;
		}

		$db_medias = ($method === 'update')
			? API::getApiService()->select('media', [
				'output' => ['mediaid', 'userid', 'mediatypeid', 'sendto', 'active', 'severity', 'period'],
				'filter' => ['userid' => array_keys($medias)]
			])
			: [];

		$ins_medias = [];
		$upd_medias = [];
		$del_mediaids = [];

		foreach ($db_medias as $db_media) {
			$index = $this->getSimilarMedia($medias[$db_media['userid']], $db_media['mediatypeid'],
				$db_media['sendto']
			);

			if ($index != -1) {
				$media = $medias[$db_media['userid']][$index];

				$upd_media = [];

				if (array_key_exists('active', $media) && $media['active'] != $db_media['active']) {
					$upd_media['active'] = $media['active'];
				}
				if (array_key_exists('severity', $media) && $media['severity'] != $db_media['severity']) {
					$upd_media['severity'] = $media['severity'];
				}
				if (array_key_exists('period', $media) && $media['period'] !== $db_media['period']) {
					$upd_media['period'] = $media['period'];
				}

				if ($upd_media) {
					$upd_medias[] = [
						'values' => $upd_media,
						'where' => ['mediaid' => $db_media['mediaid']]
					];
				}

				unset($medias[$db_media['userid']][$index]);
			}
			else {
				$del_mediaids[] = $db_media['mediaid'];
			}
		}

		foreach ($medias as $userid => $user_medias) {
			foreach ($user_medias as $media) {
				$ins_medias[] = ['userid' => $userid] + $media;
			}
		}

		if ($ins_medias) {
			DB::insert('media', $ins_medias);
		}

		if ($upd_medias) {
			DB::update('media', $upd_medias);
		}

		if ($del_mediaids) {
			DB::delete('media', ['id' => $del_mediaids]);
		}
	}

	/**
	 * @deprecated	As of version 3.4, use update() method instead.
	 */
	public function updateProfile($user) {
		$this->deprecated('user.updateprofile method is deprecated.');

		$user['userid'] = self::$userData['userid'];

		return $this->update([$user]);
	}

	/**
	 * Delete user.
	 *
	 * @param array $userids
	 *
	 * @return array
	 */
	public function delete(array $userids) {
		$this->validateDelete($userids, $db_users);

		DB::delete('media', ['userid' => $userids]);
		DB::delete('profiles', ['userid' => $userids]);
		DB::delete('users_groups', ['userid' => $userids]);
		DB::delete('users', ['userid' => $userids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER, $db_users);

		return ['userids' => $userids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
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
			'output' => ['userid', 'alias'],
			'userids' => $userids,
			'editable' => true,
			'preservekeys' => true
		]);

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

			if ($db_user['alias'] == ZBX_GUEST_USER) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Cannot delete Zabbix internal user "%1$s", try disabling that user.', ZBX_GUEST_USER)
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
				$db_users[$db_action['userid']]['alias'], $db_action['name']
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
				_s('User "%1$s" is map "%2$s" owner.', $db_users[$db_maps[0]['userid']]['alias'], $db_maps[0]['name'])
			);
		}

		// Check if deleted users have a screen.
		$db_screens = API::Screen()->get([
			'output' => ['name', 'userid'],
			'userids' => $userids,
			'limit' => 1
		]);

		if ($db_screens) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is screen "%2$s" owner.', $db_users[$db_screens[0]['userid']]['alias'],
					$db_screens[0]['name']
				)
			);
		}

		// Check if deleted users have a slide show.
		$db_slideshows = API::getApiService()->select('slideshows', [
			'output' => ['name', 'userid'],
			'filter' => ['userid' => $userids],
			'limit' => 1
		]);

		if ($db_slideshows) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User "%1$s" is slide show "%2$s" owner.', $db_users[$db_slideshows[0]['userid']]['alias'],
					$db_slideshows[0]['name']
				)
			);
		}
	}

	/**
	 * Add user media.
	 *
	 * @deprecated	As of version 3.4, use update() method instead.
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 *
	 * @return array
	 */
	public function addMedia(array $data) {
		$this->deprecated('user.addmedia method is deprecated.');

		$this->validateAddMedia($data);
		$mediaIds = $this->addMediaReal($data);

		return ['mediaids' => $mediaIds];
	}

	/**
	 * Validate add user media.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 */
	protected function validateAddMedia(array $data) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only Zabbix Admins can add user media.'));
		}

		if (!isset($data['users']) || !isset($data['medias'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		if (!$this->isWritable(zbx_objectValues($users, 'userid'))) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$mediaDBfields = [
			'period' => null,
			'mediatypeid' => null,
			'sendto' => null,
			'active' => null,
			'severity' => null
		];

		foreach ($media as $mediaItem) {
			if (!check_db_fields($mediaDBfields, $mediaItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$timePeriodValidator = new CTimePeriodValidator();

		foreach ($media as $mediaItem) {
			if (!$timePeriodValidator->validate($mediaItem['period'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $timePeriodValidator->getError());
			}
		}
	}

	/**
	 * Create user media.
	 *
	 * @throws APIException if user media insert is fail.
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 *
	 * @return array
	 */
	protected function addMediaReal(array $data) {
		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		$mediaIds = [];

		foreach ($users as $user) {
			foreach ($media as $mediaItem) {
				$mediaId = get_dbid('media', 'mediaid');

				$sql = 'INSERT INTO media (mediaid,userid,mediatypeid,sendto,active,severity,period)'.
						' VALUES ('.zbx_dbstr($mediaId).','.zbx_dbstr($user['userid']).','.zbx_dbstr($mediaItem['mediatypeid']).','.
									zbx_dbstr($mediaItem['sendto']).','.zbx_dbstr($mediaItem['active']).','.zbx_dbstr($mediaItem['severity']).','.
									zbx_dbstr($mediaItem['period']).')';

				if (!DBexecute($sql)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot insert user media.'));
				}

				$mediaIds[] = $mediaId;
			}
		}

		return $mediaIds;
	}

	/**
	 * Update user media.
	 *
	 * @throws APIException if user media update is fail.
	 *
	 * @deprecated	As of version 3.4, use update() method instead.
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 *
	 * @return array
	 */
	public function updateMedia(array $data) {
		$this->deprecated('user.updatemedia method is deprecated.');

		$this->validateUpdateMedia($data);

		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		$userIds = array_keys(array_flip((zbx_objectValues($users, 'userid'))));

		$dbMedia = API::UserMedia()->get([
			'output' => ['mediaid'],
			'userids' => $userIds,
			'editable' => true,
			'preservekeys' => true
		]);

		$mediaToCreate = $mediaToUpdate = $mediaToDelete = [];

		foreach ($media as $mediaItem) {
			if (isset($mediaItem['mediaid'])) {
				$mediaToUpdate[$mediaItem['mediaid']] = $mediaItem;
			}
			else {
				$mediaToCreate[] = $mediaItem;
			}
		}

		foreach ($dbMedia as $dbMediaItem) {
			if (!isset($mediaToUpdate[$dbMediaItem['mediaid']])) {
				$mediaToDelete[$dbMediaItem['mediaid']] = $dbMediaItem['mediaid'];
			}
		}

		// create
		if ($mediaToCreate) {
			$this->addMediaReal([
				'users' => $users,
				'medias' => $mediaToCreate
			]);
		}

		// update
		if ($mediaToUpdate) {
			foreach ($mediaToUpdate as $media) {
				$result = DBexecute(
					'UPDATE media'.
					' SET mediatypeid='.zbx_dbstr($media['mediatypeid']).','.
						' sendto='.zbx_dbstr($media['sendto']).','.
						' active='.zbx_dbstr($media['active']).','.
						' severity='.zbx_dbstr($media['severity']).','.
						' period='.zbx_dbstr($media['period']).
					' WHERE mediaid='.zbx_dbstr($media['mediaid'])
				);

				if (!$result) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot update user media.'));
				}
			}
		}

		// delete
		if ($mediaToDelete) {
			$this->deleteMediaReal($mediaToDelete);
		}

		return ['userids' => $userIds];
	}

	/**
	 * Validate update user media.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array  $data['users']
	 * @param string $data['users']['userid']
	 * @param array  $data['medias']
	 * @param string $data['medias']['mediatypeid']
	 * @param string $data['medias']['address']
	 * @param int    $data['medias']['severity']
	 * @param int    $data['medias']['active']
	 * @param string $data['medias']['period']
	 */
	protected function validateUpdateMedia(array $data) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Zabbix Admins can change user media.'));
		}

		if (!isset($data['users']) || !isset($data['medias'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
		}

		$users = zbx_toArray($data['users']);
		$media = zbx_toArray($data['medias']);

		// validate user permissions
		if (!$this->isWritable(zbx_objectValues($users, 'userid'))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		// validate media permissions
		$mediaIds = [];

		foreach ($media as $mediaItem) {
			if (isset($mediaItem['mediaid'])) {
				$mediaIds[$mediaItem['mediaid']] = $mediaItem['mediaid'];
			}
		}

		if ($mediaIds) {
			$dbUserMediaCount = API::UserMedia()->get([
				'countOutput' => true,
				'mediaids' => $mediaIds,
				'editable' => true
			]);

			if ($dbUserMediaCount != count($mediaIds)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		// validate media parameters
		$mediaDBfields = [
			'period' => null,
			'mediatypeid' => null,
			'sendto' => null,
			'active' => null,
			'severity' => null
		];

		$timePeriodValidator = new CTimePeriodValidator();

		foreach ($media as $mediaItem) {
			if (!check_db_fields($mediaDBfields, $mediaItem)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}

			if (!$timePeriodValidator->validate($mediaItem['period'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $timePeriodValidator->getError());
			}
		}
	}

	/**
	 * Delete user media.
	 *
	 * @deprecated	As of version 3.4, use update() method instead.
	 *
	 * @param array $mediaIds
	 *
	 * @return array
	 */
	public function deleteMedia($mediaIds) {
		$this->deprecated('user.deletemedia method is deprecated.');

		$mediaIds = zbx_toArray($mediaIds);

		$this->validateDeleteMedia($mediaIds);
		$this->deleteMediaReal($mediaIds);

		return ['mediaids' => $mediaIds];
	}

	/**
	 * Validate delete user media.
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateDeleteMedia(array $mediaIds) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Only Zabbix Admins can remove user media.'));
		}

		$dbUserMediaCount = API::UserMedia()->get([
			'countOutput' => true,
			'mediaids' => $mediaIds,
			'editable' => true
		]);

		if (count($mediaIds) != $dbUserMediaCount) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Delete user media.
	 *
	 * @throws APIException if delete is fail
	 */
	protected function deleteMediaReal($mediaIds) {
		if (!DBexecute('DELETE FROM media WHERE '.dbConditionInt('mediaid', $mediaIds))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete user media.'));
		}
	}

	/**
	 * Authenticate a user using LDAP.
	 *
	 * The $user array must have the following attributes:
	 * - user       - user name
	 * - password   - user password
	 *
	 * @param array $user
	 *
	 * @return bool
	 */
	protected function ldapLogin(array $user) {
		$config = select_config();
		$cnf = [];

		foreach ($config as $id => $value) {
			if (strpos($id, 'ldap_') !== false) {
				$cnf[str_replace('ldap_', '', $id)] = $config[$id];
			}
		}

		if (!function_exists('ldap_connect')) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Probably php-ldap module is missing.'));
		}

		$ldapValidator = new CLdapAuthValidator(['conf' => $cnf]);

		if ($ldapValidator->validate($user)) {
			return true;
		}
		else {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect.'));
		}
	}

	public function logout($user) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => []];
		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sessionid = self::$userData['sessionid'];

		$db_sessions = API::getApiService()->select('sessions', [
			'output' => ['userid'],
			'filter' => [
				'sessionid' => $sessionid,
				'status' => ZBX_SESSION_ACTIVE
			],
			'limit' => 1
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot logout.'));
		}

		DB::delete('sessions', [
			'status' => ZBX_SESSION_PASSIVE,
			'userid' => $db_sessions[0]['userid']
		]);
		DB::update('sessions', [
			'values' => ['status' => ZBX_SESSION_PASSIVE],
			'where' => ['sessionid' => $sessionid]
		]);

		$this->addAuditDetails(AUDIT_ACTION_LOGOUT, AUDIT_RESOURCE_USER);

		self::$userData = null;

		return true;
	}

	/**
	 * Login user.
	 *
	 * @param array $user
	 *
	 * @return string|array
	 */
	public function login(array $user) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'user' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'password' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'userData' =>	['type' => API_FLAG]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = API::getApiService()->select('users', [
			'output' => ['userid', 'alias', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh',
				'type', 'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'passwd'
			],
			'filter' => ['alias' => $user['user']]
		]);

		if (!$db_users) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect.'));
		}

		$db_user = $db_users[0];

		// Check if user is blocked.
		if ($db_user['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS) {
			$time_left = ZBX_LOGIN_BLOCK - (time() - $db_user['attempt_clock']);

			if ($time_left > 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_n('Account is blocked for %1$s second.', 'Account is blocked for %1$s seconds.', $time_left)
				);
			}

			DB::update('users', [
				'values' => ['attempt_clock' => time()],
				'where' => ['userid' => $db_user['userid']]
			]);
		}

		$usrgrps = $this->getUserGroupsData($db_user['userid']);

		$db_user['debug_mode'] = $usrgrps['debug_mode'];
		$db_user['userip'] = $usrgrps['userip'];
		$db_user['gui_access'] = $usrgrps['gui_access'];

		// Check system permissions.
		if ($usrgrps['users_status'] == GROUP_STATUS_DISABLED) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions for system access.'));
		}

		$config = select_config();
		$authentication_type = $config['authentication_type'];

		if ($db_user['gui_access'] == GROUP_GUI_ACCESS_INTERNAL) {
			$authentication_type = ($authentication_type == ZBX_AUTH_HTTP) ? ZBX_AUTH_HTTP : ZBX_AUTH_INTERNAL;
		}

		if ($authentication_type == ZBX_AUTH_HTTP) {
			// if PHP_AUTH_USER is not set, it means that HTTP authentication is not enabled
			if (!array_key_exists('PHP_AUTH_USER', $_SERVER)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot login.'));
			}
			// check if the user name used when calling the API matches the one used for HTTP authentication
			elseif ($user['user'] !== $_SERVER['PHP_AUTH_USER']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Login name "%1$s" does not match the name "%2$s" used to pass HTTP authentication.',
						$user['user'], $_SERVER['PHP_AUTH_USER']
					)
				);
			}
		}

		try {
			switch ($authentication_type) {
				case ZBX_AUTH_LDAP:
					$this->ldapLogin($user);
					break;

				case ZBX_AUTH_INTERNAL:
					if (md5($user['password']) !== $db_user['passwd']) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Login name or password is incorrect.'));
					}
					break;
			}
		}
		catch (APIException $e) {
			DB::update('users', [
				'values' => [
					'attempt_failed' => ++$db_user['attempt_failed'],
					'attempt_clock' => time(),
					'attempt_ip' => $db_user['userip']
				],
				'where' => ['userid' => $db_user['userid']]
			]);

			$this->addAuditDetails(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, _('Login failed.'), $db_user['userid'],
				$db_user['userip']
			);

			self::exception(ZBX_API_ERROR_PARAMETERS, $e->getMessage());
		}

		// Start session.
		unset($db_user['passwd']);
		$db_user['sessionid'] = md5(time().md5($user['password']).$user['user'].rand(0, 10000000));

		DB::insert('sessions', [[
			'sessionid' => $db_user['sessionid'],
			'userid' => $db_user['userid'],
			'lastaccess' => time(),
			'status' => ZBX_SESSION_ACTIVE
		]], false);

		if ($db_user['attempt_failed'] != 0) {
			DB::update('users', [
				'values' => ['attempt_failed' => 0],
				'where' => ['userid' => $db_user['userid']]
			]);
		}

		self::$userData = $db_user;

		$this->addAuditDetails(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER);

		return array_key_exists('userData', $user) && $user['userData'] ? $db_user : $db_user['sessionid'];
	}

	/**
	 * Check if session id is authenticated.
	 *
	 * @param array $session
	 *
	 * @return array
	 */
	public function checkAuthentication(array $session) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'sessionid' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('sessions', 'sessionid')],
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

		$db_sessions = API::getApiService()->select('sessions', [
			'output' => ['userid', 'lastaccess'],
			'sessionids' => $sessionid,
			'filter' => ['status' => ZBX_SESSION_ACTIVE]
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		$db_session = $db_sessions[0];

		$db_users = API::getApiService()->select('users', [
			'output' => ['userid', 'alias', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh',
				'type', 'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page'
			],
			'userids' => $db_session['userid']
		]);

		if (!$db_users) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		$db_user = $db_users[0];

		$usrgrps = $this->getUserGroupsData($db_user['userid']);

		$db_user['sessionid'] = $sessionid;
		$db_user['debug_mode'] = $usrgrps['debug_mode'];
		$db_user['userip'] = $usrgrps['userip'];
		$db_user['gui_access'] = $usrgrps['gui_access'];

		// Check system permissions.
		if (($db_user['autologout'] != 0 && $db_session['lastaccess'] + $db_user['autologout'] <= $time)
				|| $usrgrps['users_status'] == GROUP_STATUS_DISABLED) {
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

		if ($time != $db_session['lastaccess']) {
			DB::update('sessions', [
				'values' => ['lastaccess' => $time],
				'where' => ['sessionid' => $sessionid]
			]);
		}

		self::$userData = $db_user;

		return $db_user;
	}

	private function getUserGroupsData($userid) {
		$usrgrps = [
			'debug_mode' => GROUP_DEBUG_MODE_DISABLED,
			'userip' => (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_FOR'] !== '')
				? $_SERVER['HTTP_X_FORWARDED_FOR']
				: $_SERVER['REMOTE_ADDR'],
			'users_status' => GROUP_STATUS_ENABLED,
			'gui_access' => GROUP_GUI_ACCESS_SYSTEM
		];

		$db_usrgrps = DBselect(
			'SELECT g.debug_mode,g.users_status,g.gui_access'.
			' FROM usrgrp g,users_groups ug'.
			' WHERE g.usrgrpid=ug.usrgrpid'.
				' AND ug.userid='.$userid
		);

		while ($db_usrgrp = DBfetch($db_usrgrps)) {
			if ($db_usrgrp['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
				$usrgrps['debug_mode'] = GROUP_DEBUG_MODE_ENABLED;
			}
			if ($db_usrgrp['users_status'] == GROUP_STATUS_DISABLED) {
				$users_status = GROUP_STATUS_DISABLED;
			}
			if ($db_usrgrp['gui_access'] > $usrgrps['gui_access']) {
				$usrgrps['gui_access'] = $db_usrgrp['gui_access'];
			}
		}

		return $usrgrps;
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
			'userids' => $ids,
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
			'userids' => $ids,
			'editable' => true,
			'countOutput' => true
		]);

		return (count($ids) == $count);
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
			$userMedias = API::UserMedia()->get([
				'output' => $this->outputExtend($options['selectMedias'], ['userid', 'mediaid']),
				'userids' => $userIds,
				'preservekeys' => true
			]);

			$relationMap = $this->createRelationMap($userMedias, 'userid', 'mediaid');

			$userMedias = $this->unsetExtraFields($userMedias, ['userid', 'mediaid'], $options['selectMedias']);
			$result = $relationMap->mapMany($result, $userMedias, 'medias');
		}

		// adding media types
		if ($options['selectMediatypes'] !== null && $options['selectMediatypes'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'userid', 'mediatypeid', 'media');
			$mediaTypes = API::Mediatype()->get([
				'output' => $options['selectMediatypes'],
				'mediatypeids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $mediaTypes, 'mediatypes');
		}

		return $result;
	}
}
