<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FA\Support\Constants;


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
		'unblock' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'provision' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'users';
	protected $tableAlias = 'u';
	protected $sortColumns = ['userid', 'username'];

	public const OUTPUT_FIELDS = ['userid', 'username', 'name', 'surname', 'passwd', 'url', 'autologin', 'autologout',
		'lang', 'refresh', 'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone',
		'roleid', 'userdirectoryid', 'ts_provisioned'
	];

	protected const PROVISIONED_FIELDS = ['username', 'name', 'surname', 'usrgrps', 'medias', 'roleid', 'passwd'];

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
	 * @param string $options['sortfield']		output will be sorted by given property ['userid', 'username']
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
		self::createForce($users);

		return ['userids' => array_column($users, 'userid')];
	}

	/**
	 * @param array $users
	 */
	private static function createForce(array &$users): void {
		$userids = DB::insert('users', $users);

		foreach ($users as &$user) {
			$user['userid'] = array_shift($userids);
		}
		unset($user);

		self::updateGroups($users);
		self::updateUgSets($users);
		self::updateMedias($users);
		self::updateMfaTotpSecret($users);

		foreach ($users as &$user) {
			unset($user['role_type']);
		}
		unset($user);

		if (array_key_exists('ts_provisioned', $users[0])) {
			self::addAuditLogByUser(null, CWebUser::getIp(), CProvisioning::AUDITLOG_USERNAME, CAudit::ACTION_ADD,
				CAudit::RESOURCE_USER, $users
			);
		}
		else {
			self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USER, $users);
		}
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

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['username']], 'fields' => [
			'username' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
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
			'usrgrps' =>		['type' => API_OBJECTS, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'medias' =>			['type' => API_OBJECTS, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
			]],
			'userdirectoryid' =>	['type' => API_ID, 'default' => 0],
			'mfa_totp_secrets' =>	['type' => API_OBJECTS, 'uniq' => [['mfaid']], 'fields' => [
				'mfaid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'totp_secret' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('mfa_totp_secret', 'totp_secret')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $users, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($users as $i => &$user) {
			$user = $this->checkLoginOptions($user);

			if (array_key_exists('passwd', $user)) {
				if ($user['userdirectoryid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Not allowed to update field "%1$s" for provisioned user.', 'passwd')
					);
				}

				$this->checkPassword($user, '/'.($i + 1).'/passwd');
			}

			if (array_key_exists('passwd', $user)) {
				$user['passwd'] = password_hash($user['passwd'], PASSWORD_BCRYPT, ['cost' => ZBX_BCRYPT_COST]);
			}
		}
		unset($user);

		$this->checkDuplicates(array_column($users, 'username'));
		$this->checkLanguages(array_column($users, 'lang'));

		$db_roles = self::getDbRoles($users);
		self::checkRoles($users, $db_roles);
		self::addRoleType($users, $db_roles);

		$this->checkUserdirectories($users);
		$this->checkUserGroups($users, $db_user_groups);
		self::checkEmptyPassword($users, $db_user_groups);
		$this->checkMfaMethods($users);
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
		self::updateForce($users, $db_users);

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

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['userid'], ['username']], 'fields' => [
			'userid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'username' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')],
			'current_passwd' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => 255],
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
			'usrgrps' =>		['type' => API_OBJECTS, 'uniq' => [['usrgrpid']], 'fields' => [
				'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]],
			'medias' =>	['type' => API_OBJECTS, 'fields' => [
				'mediatypeid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
				'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
				'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
				'severity' =>		['type' => API_INT32, 'in' => '0:63'],
				'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
			]],
			'userdirectoryid' =>	['type' => API_ID],
			'mfa_totp_secrets' =>	['type' => API_OBJECTS, 'uniq' => [['mfaid']], 'fields' => [
				'mfaid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
				'totp_secret' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('mfa_totp_secret', 'totp_secret')]
			]]
		]];

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
				'refresh', 'theme', 'rows_per_page', 'timezone', 'roleid', 'userdirectoryid'
			],
			'userids' => array_keys($db_users),
			'preservekeys' => true
		]);

		if (array_diff_key(array_column($users, 'userid', 'userid'), $db_users)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// Get readonly super admin role ID and name.
		[$readonly_superadmin_role] = DBfetchArray(DBselect(
			'SELECT roleid,name'.
			' FROM role'.
			' WHERE type='.USER_TYPE_SUPER_ADMIN.
				' AND readonly=1'
		));

		$superadminids_to_update = [];
		$usernames = [];
		$readonly_fields = array_fill_keys(['username', 'passwd'], 1);

		foreach ($users as $i => &$user) {
			$db_user = $db_users[$user['userid']];

			if (array_key_exists('userdirectoryid', $user) && $user['userdirectoryid'] != 0) {
				$provisioned_field = array_key_first(array_intersect_key($readonly_fields, $user));

				if ($provisioned_field !== null) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Not allowed to update field "%1$s" for provisioned user.', $provisioned_field)
					);
				}
			}

			$user = $this->checkLoginOptions($user);

			if (array_key_exists('username', $user) && $user['username'] !== $db_user['username']) {
				$usernames[] = $user['username'];
			}

			if (array_key_exists('current_passwd', $user)) {
				if (!password_verify($user['current_passwd'], $db_user['passwd'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect current password.'));
				}
			}

			if (array_key_exists('passwd', $user) && $this->checkPassword($user + $db_user, '/'.($i + 1).'/passwd')) {
				if ($user['userid'] == self::$userData['userid'] && !array_key_exists('current_passwd', $user)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Current password is mandatory.'));
				}

				$user['passwd'] = password_hash($user['passwd'], PASSWORD_BCRYPT, ['cost' => ZBX_BCRYPT_COST]);
			}

			unset($user['current_passwd']);

			if (array_key_exists('roleid', $user) && $user['roleid'] && $user['roleid'] != $db_user['roleid']) {
				if ($db_user['roleid'] == $readonly_superadmin_role['roleid']) {
					$superadminids_to_update[] = $user['userid'];
				}
			}

			if ($db_user['username'] !== ZBX_GUEST_USER) {
				continue;
			}

			// Additional validation for guest user.
			if (array_key_exists('username', $user) && $user['username'] !== $db_user['username']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot rename guest user.'));
			}

			if (array_key_exists('lang', $user)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set language for user "guest".'));
			}

			if (array_key_exists('theme', $user)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set theme for user "guest".'));
			}

			if (array_key_exists('passwd', $user)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Not allowed to set password for user "guest".'));
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

		$db_roles = self::getDbRoles($users, $db_users);
		self::checkRoles($users, $db_roles);
		self::addRoleType($users, $db_roles, $db_users);

		self::addAffectedObjects($users, $db_users);

		if ($usernames) {
			$this->checkDuplicates($usernames);
		}
		$this->checkLanguages(zbx_objectValues($users, 'lang'));

		$this->checkUserdirectories($users);
		$this->checkMfaMethods($users);
		$this->checkUserGroups($users, $db_user_groups);
		self::checkEmptyPassword($users, $db_user_groups, $db_users);
		$db_mediatypes = $this->checkMediaTypes($users);
		$this->validateMediaRecipients($users, $db_mediatypes);
		$this->checkHimself($users);
	}

	/**
	 * @param array $users
	 * @param array $db_users
	 */
	private static function updateForce(array $users, array $db_users): void {
		$upd_users = [];

		foreach ($users as $user) {
			$upd_user = DB::getUpdatedValues('users', $user, $db_users[$user['userid']]);

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

		self::terminateActiveSessionsOnPasswordUpdate($users);
		self::updateGroups($users, $db_users);
		self::updateUgSets($users, $db_users);
		self::updateMedias($users, $db_users);
		self::updateMfaTotpSecret($users, $db_users);

		foreach ($users as &$user) {
			unset($user['role_type']);
			unset($db_users[$user['userid']]['role_type']);
		}
		unset($user);

		if (array_key_exists('ts_provisioned', $users[0])) {
			self::addAuditLogByUser(null, CWebUser::getIp(), CProvisioning::AUDITLOG_USERNAME, CAudit::ACTION_UPDATE,
				CAudit::RESOURCE_USER, $users, $db_users
			);
		}
		else {
			self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users);
		}
	}

	private static function addAffectedObjects(array $users, array &$db_users): void {
		self::addAffectedUserGroups($users, $db_users);
		self::addAffectedMedias($users, $db_users);
		self::addAffectedMfaTotpSecrets($users, $db_users);
	}

	private static function addAffectedUserGroups(array $users, array &$db_users): void {
		$userids = [];

		foreach ($users as $user) {
			if (array_key_exists('usrgrps', $user) || self::ugSetUpdateRequired($user, $db_users[$user['userid']])
					|| self::emptyPasswordCheckRequired($user, $db_users[$user['userid']])) {
				$userids[] = $user['userid'];
				$db_users[$user['userid']]['usrgrps'] = [];
			}
		}

		if (!$userids) {
			return;
		}

		$result = DBselect(
			'SELECT ug.id,ug.usrgrpid,ug.userid,g.gui_access'.
			' FROM users_groups ug,usrgrp g'.
			' WHERE ug.usrgrpid=g.usrgrpid'.
				' AND '.dbConditionId('ug.userid', $userids)
		);

		while ($db_usrgrp = DBfetch($result)) {
			$db_users[$db_usrgrp['userid']]['usrgrps'][$db_usrgrp['id']] =
				array_diff_key($db_usrgrp, array_flip(['userid']));
		}
	}

	private static function ugSetUpdateRequired(array $user, array $db_user): bool {
		if ($user['role_type'] == $db_user['role_type']) {
			return false;
		}

		if ($user['role_type'] !== null && $user['role_type'] != USER_TYPE_SUPER_ADMIN
				&& ($db_user['role_type'] === null || $db_user['role_type'] == USER_TYPE_SUPER_ADMIN)) {
			return true;
		}

		if (($user['role_type'] === null || $user['role_type'] == USER_TYPE_SUPER_ADMIN)
				&& $db_user['role_type'] !== null && $db_user['role_type'] != USER_TYPE_SUPER_ADMIN) {
			return true;
		}

		return false;
	}

	private static function emptyPasswordCheckRequired(array $user, array $db_user): bool {
		return !array_key_exists('passwd', $user) && $db_user['passwd'] === '';
	}

	private static function addAffectedMedias(array $users, array &$db_users): void {
		$userids = [];

		foreach ($users as $user) {
			if (array_key_exists('medias', $user)) {
				$userids[] = $user['userid'];
				$db_users[$user['userid']]['medias'] = [];
			}
		}

		if (!$userids) {
			return;
		}

		$options = [
			'output' => ['mediaid', 'userid', 'mediatypeid', 'sendto', 'active', 'severity', 'period'],
			'filter' => ['userid' => $userids]
		];
		$db_medias = DBselect(DB::makeSql('media', $options));

		while ($db_media = DBfetch($db_medias)) {
			$db_users[$db_media['userid']]['medias'][$db_media['mediaid']] =
				array_diff_key($db_media, array_flip(['userid']));
		}
	}

	private static function addAffectedMfaTotpSecrets(array $users, array $db_users): void {
		$userids = [];

		foreach ($users as $user) {
			if (array_key_exists('mfa_totp_secrets', $user)) {
				$userids[] = $user['userid'];
				$db_users[$user['userid']]['mfa_totp_secrets'] = [];
			}
		}

		if (!$userids) {
			return;
		}

		$db_mfa_totp_secrets = DB::select('mfa_totp_secret', [
			'output' => ['mfa_totp_secretid', 'mfaid', 'userid', 'totp_secret'],
			'filter' => ['userid' => $userids]
		]);

		foreach ($db_mfa_totp_secrets as $db_mfa_totp_secret) {
			$db_users[$db_mfa_totp_secret['userid']]['mfa_totp_secrets'][$db_mfa_totp_secret['mfa_totp_secretid']] =
				array_diff_key($db_mfa_totp_secret, array_flip(['userid']));
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
	 * Check user directories, used in users data, exist.
	 *
	 * @param array $users
	 * @param int   $users[]['userdirectoryid']  (optional)
	 *
	 * @throws APIException  if user directory do not exists.
	 */
	private function checkUserdirectories(array $users) {
		$userdirectoryids = array_column($users, 'userdirectoryid', 'userdirectoryid');
		unset($userdirectoryids[0]);

		if (!$userdirectoryids) {
			return;
		}

		$db_userdirectoryids = API::UserDirectory()->get([
			'output' => [],
			'userdirectoryids' => $userdirectoryids,
			'preservekeys' => true
		]);
		$ids = array_diff_key($userdirectoryids, $db_userdirectoryids);

		if ($ids) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User directory with ID "%1$s" is not available.', reset($ids))
			);
		}
	}

	/**
	 * Check if MFA method, used in users data, exist.
	 *
	 * @param array $users
	 * @param int   $users[]['mfa_totp_secrets']['mfaid']  (optional)
	 *
	 * @throws APIException  if mfa method does not exist.
	 */
	private function checkMfaMethods(array $users): void {
		$mfaids = [];
		foreach ($users as $user) {
			if (array_key_exists('mfa_totp_secrets', $user)) {
				$mfaids += array_column($user['mfa_totp_secrets'], 'mfaid', 'mfaid');
			}
		}

		if (!$mfaids) {
			return;
		}

		$db_mfaids = API::Mfa()->get([
			'output' => ['type'],
			'mfaids' => $mfaids,
			'preservekeys' => true
		]);
		$ids = array_diff_key($mfaids, $db_mfaids);
		$types = array_column($db_mfaids, 'type');

		if ($ids) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('MFA method with ID "%1$s" is not available.', reset($ids))
			);
		}

		if (in_array(MFA_TYPE_DUO, $types)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_('Incorrect MFA method type "DUO Universal Prompt" is not available for TOTP secret.')
			);
		}
	}

	private function checkUserGroups(array $users, array &$db_user_groups = null): void {
		$user_group_indexes = [];

		foreach ($users as $i1 => $user) {
			if (!array_key_exists('usrgrps', $user)) {
				continue;
			}

			foreach ($user['usrgrps'] as $i2 => $user_group) {
				$user_group_indexes[$user_group['usrgrpid']][$i1] = $i2;
			}
		}

		if (!$user_group_indexes) {
			return;
		}

		$db_user_groups = DB::select('usrgrp', [
			'output' => ['gui_access'],
			'usrgrpids' => array_keys($user_group_indexes),
			'preservekeys' => true
		]);

		foreach ($user_group_indexes as $usrgrpid => $indexes) {
			if (!array_key_exists($usrgrpid, $db_user_groups)) {
				$i1 = key($indexes);
				$i2 = $indexes[$i1];

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.$i1.'/usrgrps/'.$i2, _('object does not exist'))
				);
			}
		}
	}

	private static function checkEmptyPassword(array $users, ?array $db_user_groups, array $db_users = null): void {
		foreach ($users as $i => $user) {
			$check = false;

			if ($db_users === null) {
				if (!array_key_exists('passwd', $user)
						&& (!array_key_exists('userdirectoryid', $user) || $user['userdirectoryid'] == 0)
						&& (array_key_exists('usrgrps', $user) && $user['usrgrps'])) {
					$check = true;
				}
			}
			else {
				$db_user = $db_users[$user['userid']];

				if (!array_key_exists('passwd', $user) && $db_user['passwd'] === ''
						&& ((!array_key_exists('userdirectoryid', $user) && $db_user['userdirectoryid'] == 0)
							|| $user['userdirectoryid'] == 0)
						&& ((!array_key_exists('usrgrps', $user) && $db_user['usrgrps']) || $user['usrgrps'])) {
					$userdirectory_changed = array_key_exists('userdirectoryid', $user)
						&& bccomp($user['userdirectoryid'], $db_user['userdirectoryid']) != 0;

					$user_groups_changed = array_key_exists('usrgrps', $user)
						&& self::userGroupsChanged($user, $db_user);

					if (!$userdirectory_changed && !$user_groups_changed) {
						continue;
					}

					$check = true;
				}
			}

			if (!$check) {
				unset($users[$i]);
			}
		}

		if (!$users) {
			return;
		}

		foreach ($users as $i => $user) {
			$gui_access = null;

			if (array_key_exists('usrgrps', $user)) {
				foreach ($user['usrgrps'] as $user_group) {
					if ($gui_access === null
							|| $db_user_groups[$user_group['usrgrpid']]['gui_access'] > $user['gui_access']) {
						$gui_access = $db_user_groups[$user_group['usrgrpid']]['gui_access'];
					}
				}
			}
			else {
				foreach ($db_users[$user['userid']]['usrgrps'] as $db_user_group) {
					if ($gui_access === null || $db_user_group['gui_access'] > $user['gui_access']) {
						$gui_access = $db_user_group['gui_access'];
					}
				}
			}

			if (self::getAuthTypeByGuiAccess($gui_access) == ZBX_AUTH_INTERNAL) {
				if ($db_users === null) {
					$username = $user['username'];
				}
				else {
					$username = array_key_exists('username', $user)
						? $user['username']
						: $db_users[$user['userid']]['username'];
				}

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User "%1$s" must have a password, because internal authentication is in effect.', $username)
				);
			}
		}
	}

	private static function userGroupsChanged(array $user, array $db_user): bool {
		$usrgrpids = array_column($user['usrgrps'], 'usrgrpid');
		$db_usrgrpids = array_column($db_user['usrgrps'], 'usrgrpid');

		return array_diff($usrgrpids, $db_usrgrpids) || array_diff($db_usrgrpids, $usrgrpids);
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
	 * @param array      $users
	 * @param array|null $db_users
	 */
	private static function getDbRoles(array $users, array $db_users = null): array {
		$roleids = [];

		foreach ($users as $user) {
			if (array_key_exists('roleid', $user) && $user['roleid'] != 0) {
				$roleids[$user['roleid']] = true;
			}

			if ($db_users !== null && $db_users[$user['userid']]['roleid'] != 0) {
				$roleids[$db_users[$user['userid']]['roleid']] = true;
			}
		}

		if (!$roleids) {
			return [];
		}

		return DB::select('role', [
			'output' => ['type'],
			'roleids' => array_keys($roleids),
			'preservekeys' => true
		]);
	}

	/**
	 * Check for valid user roles.
	 *
	 * @param array      $users
	 * @param array      $db_roles
	 *
	 * @throws APIException
	 */
	private static function checkRoles(array $users, array $db_roles): void {
		foreach ($users as $i => $user) {
			if (array_key_exists('roleid', $user) && $user['roleid'] != 0
					&& !array_key_exists($user['roleid'], $db_roles)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/roleid',
					_('object does not exist')
				));
			}
		}
	}

	/**
	 * @param array      $users
	 * @param array      $db_roles
	 * @param array|null $db_users
	 */
	private static function addRoleType(array &$users, array $db_roles, array &$db_users = null): void {
		foreach ($users as &$user) {
			$user['role_type'] = null;

			if ($db_users !== null) {
				$db_users[$user['userid']]['role_type'] = null;
			}

			if (array_key_exists('roleid', $user) && $user['roleid'] != 0) {
				$user['role_type'] = $db_roles[$user['roleid']]['type'];
			}

			if ($db_users !== null && $db_users[$user['userid']]['roleid'] != 0) {
				if (!array_key_exists('roleid', $user)) {
					$user['role_type'] = $db_roles[$db_users[$user['userid']]['roleid']]['type'];
				}

				$db_users[$user['userid']]['role_type'] = $db_roles[$db_users[$user['userid']]['roleid']]['type'];
			}
		}
		unset($user);
	}

	/**
	 * Check does the user belong to at least one group with GROUP_GUI_ACCESS_INTERNAL frontend access.
	 * Check does the user belong to at least one group with GROUP_GUI_ACCESS_SYSTEM when default frontend access
	 * is set to GROUP_GUI_ACCESS_INTERNAL.
	 * If user is without user groups default frontend access method is checked.
	 *
	 * @param array  $user
	 * @param int    $user['userdirectoryid']
	 * @param array  $user['usrgrps']                     (optional)
	 * @param string $user['usrgrps'][]['usrgrpid']
	 * @param array  $db_usrgrps
	 * @param int    $db_usrgrps[usrgrpid]['gui_access']
	 *
	 * @return bool
	 */
	private static function hasInternalAuth($user, $db_usrgrps) {
		if ($user['userdirectoryid']) {
			return false;
		}

		$system_gui_access =
			(CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE) == ZBX_AUTH_INTERNAL)
				? GROUP_GUI_ACCESS_INTERNAL
				: GROUP_GUI_ACCESS_LDAP;

		if (!array_key_exists('usrgrps', $user) || !$user['usrgrps']) {
			return $system_gui_access == GROUP_GUI_ACCESS_INTERNAL;
		}

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
	 * Terminate all active sessions for user whose password was successfully updated.
	 *
	 * @static
	 *
	 * @param array      $users
	 */
	private static function terminateActiveSessionsOnPasswordUpdate(array $users): void {
		foreach ($users as $user) {
			if (array_key_exists('passwd', $user)) {
				DB::update('sessions', [
					'values' => ['status' => ZBX_SESSION_PASSIVE],
					'where' => ['userid' => $user['userid']]
				]);
			}
		}
	}

	/**
	 * @param array      $users
	 * @param null|array $db_users
	 */
	private static function updateGroups(array &$users, array $db_users = null): void {
		$ins_groups = [];
		$del_groupids = [];

		foreach ($users as &$user) {
			if (!array_key_exists('usrgrps', $user)) {
				continue;
			}

			$db_groups = $db_users !== null
				? array_column($db_users[$user['userid']]['usrgrps'], null, 'usrgrpid')
				: [];

			foreach ($user['usrgrps'] as &$group) {
				if (array_key_exists($group['usrgrpid'], $db_groups)) {
					$group['id'] = $db_groups[$group['usrgrpid']]['id'];
					unset($db_groups[$group['usrgrpid']]);
				}
				else {
					$ins_groups[] = [
						'userid' => $user['userid'],
						'usrgrpid' => $group['usrgrpid']
					];
				}
			}
			unset($group);

			$del_groupids = array_merge($del_groupids, array_column($db_groups, 'id'));
		}
		unset($user);

		if ($del_groupids) {
			DB::delete('users_groups', ['id' => $del_groupids]);
		}

		if ($ins_groups) {
			$groupids = DB::insert('users_groups', $ins_groups);
		}

		foreach ($users as &$user) {
			if (!array_key_exists('usrgrps', $user)) {
				continue;
			}

			foreach ($user['usrgrps'] as &$group) {
				if (!array_key_exists('id', $group)) {
					$group['id'] = array_shift($groupids);
				}
			}
			unset($group);
		}
		unset($user);
	}

	private static function updateUgSets(array $users, array $db_users = null): void {
		$ugsets = [];

		foreach ($users as &$user) {
			$usrgrpids = null;

			if ($user['role_type'] !== null && $user['role_type'] != USER_TYPE_SUPER_ADMIN) {
				if ($db_users === null) {
					if (array_key_exists('usrgrps', $user) && $user['usrgrps']) {
						$usrgrpids = array_column($user['usrgrps'], 'usrgrpid');
					}
				}
				elseif ($db_users[$user['userid']]['role_type'] !== null
						&& $db_users[$user['userid']]['role_type'] != USER_TYPE_SUPER_ADMIN) {
					if (array_key_exists('usrgrps', $user)) {
						$_usrgrpids = array_column($user['usrgrps'], 'usrgrpid');
						$_db_usrgrpids = array_column($db_users[$user['userid']]['usrgrps'], 'usrgrpid');

						if (array_diff($_usrgrpids, $_db_usrgrpids) || array_diff($_db_usrgrpids, $_usrgrpids)) {
							$usrgrpids = $_usrgrpids;
						}
					}
				}
				else {
					$_usrgrpids = array_key_exists('usrgrps', $user)
						? array_column($user['usrgrps'], 'usrgrpid')
						: array_column($db_users[$user['userid']]['usrgrps'], 'usrgrpid');

					if ($_usrgrpids) {
						$usrgrpids = $_usrgrpids;
					}
				}
			}
			elseif ($db_users !== null
					&& $db_users[$user['userid']]['role_type'] !== null
					&& $db_users[$user['userid']]['role_type'] != USER_TYPE_SUPER_ADMIN) {
				$usrgrpids = [];
			}

			if ($usrgrpids !== null) {
				$ugset_hash = self::getUgSetHash($usrgrpids);

				$ugsets[$ugset_hash]['hash'] = $ugset_hash;
				$ugsets[$ugset_hash]['usrgrpids'] = $usrgrpids;
				$ugsets[$ugset_hash]['userids'][] = $user['userid'];
			}
		}
		unset($user);

		if ($ugsets) {
			if ($db_users === null) {
				self::createUserUgSets($ugsets);
			}
			else {
				self::updateUserUgSets($ugsets);
			}
		}
	}

	private static function getUgSetHash(array $usrgrpids): string {
		usort($usrgrpids, 'bccomp');

		return hash('sha256', implode('|', $usrgrpids));
	}

	private static function createUserUgSets(array $ugsets): void {
		$ins_user_ugsets = [];

		$options = [
			'output' => ['ugsetid', 'hash'],
			'filter' => ['hash' => array_keys($ugsets)]
		];
		$result = DBselect(DB::makeSql('ugset', $options));

		while ($row = DBfetch($result)) {
			foreach ($ugsets[$row['hash']]['userids'] as $userid) {
				$ins_user_ugsets[] = [
					'userid' => $userid,
					'ugsetid' => $row['ugsetid']
				];
			}

			unset($ugsets[$row['hash']]);
		}

		if ($ugsets) {
			self::createUgSets($ugsets);

			foreach ($ugsets as $ugset) {
				foreach ($ugset['userids'] as $userid) {
					$ins_user_ugsets[] = [
						'userid' => $userid,
						'ugsetid' => $ugset['ugsetid']
					];
				}
			}
		}

		DB::insert('user_ugset', $ins_user_ugsets, false);
	}

	private static function updateUserUgSets(array $ugsets): void {
		$ins_user_ugsets = [];
		$upd_user_ugsets = [];

		$db_user_ugsetids = self::getDbUserUgSetIds($ugsets);
		$db_ugsetids = array_flip($db_user_ugsetids);

		$empty_ugset_hash = self::getUgSetHash([]);

		if (array_key_exists($empty_ugset_hash, $ugsets)) {
			DB::delete('user_ugset', ['userid' => $ugsets[$empty_ugset_hash]['userids']]);
			unset($ugsets[$empty_ugset_hash]);
		}

		if ($ugsets) {
			$options = [
				'output' => ['ugsetid', 'hash'],
				'filter' => ['hash' => array_keys($ugsets)]
			];
			$result = DBselect(DB::makeSql('ugset', $options));

			while ($row = DBfetch($result)) {
				$upd_userids = [];

				foreach ($ugsets[$row['hash']]['userids'] as $userid) {
					if (array_key_exists($userid, $db_user_ugsetids)) {
						$upd_userids[] = $userid;
						unset($db_user_ugsetids[$userid]);
					}
					else {
						$ins_user_ugsets[] = [
							'userid' => $userid,
							'ugsetid' => $row['ugsetid']
						];
					}
				}

				if ($upd_userids) {
					$upd_user_ugsets[] = [
						'values' => ['ugsetid' => $row['ugsetid']],
						'where' => ['userid' => $upd_userids]
					];

					if (array_key_exists($row['ugsetid'], $db_ugsetids)) {
						unset($db_ugsetids[$row['ugsetid']]);
					}
				}

				unset($ugsets[$row['hash']]);
			}

			if ($ugsets) {
				self::createUgSets($ugsets);

				foreach ($ugsets as $ugset) {
					$upd_userids = [];

					foreach ($ugset['userids'] as $userid) {
						if (array_key_exists($userid, $db_user_ugsetids)) {
							$upd_userids[] = $userid;
							unset($db_user_ugsetids[$userid]);
						}
						else {
							$ins_user_ugsets[] = [
								'userid' => $userid,
								'ugsetid' => $ugset['ugsetid']
							];
						}
					}

					if ($upd_userids) {
						$upd_user_ugsets[] = [
							'values' => ['ugsetid' => $ugset['ugsetid']],
							'where' => ['userid' => $upd_userids]
						];
					}
				}
			}

			if ($upd_user_ugsets) {
				DB::update('user_ugset', $upd_user_ugsets);
			}

			if ($ins_user_ugsets) {
				DB::insert('user_ugset', $ins_user_ugsets, false);
			}
		}

		if ($db_ugsetids) {
			self::deleteUnusedUgSets(array_keys($db_ugsetids));
		}
	}

	private static function getDbUserUgSetIds(array $ugsets): array {
		$userids = [];

		foreach ($ugsets as $ugset) {
			$userids = array_merge($userids, $ugset['userids']);
		}

		$options = [
			'output' => ['userid', 'ugsetid'],
			'userids' => $userids
		];
		$result = DBselect(DB::makeSql('user_ugset', $options));

		$db_user_ugsetids = [];

		while ($row = DBfetch($result)) {
			$db_user_ugsetids[$row['userid']] = $row['ugsetid'];
		}

		return $db_user_ugsetids;
	}

	private static function createUgSets(array &$ugsets): void {
		$ugsetids = DB::insert('ugset', $ugsets);

		foreach ($ugsets as &$ugset) {
			$ugset['ugsetid'] = array_shift($ugsetids);
		}
		unset($ugset);

		self::createUgSetGroups($ugsets);

		self::addUgSetPermissions($ugsets);
		self::createPermissions($ugsets);
	}

	private static function createUgSetGroups(array $ugsets): void {
		$ins_ugset_groups = [];

		foreach ($ugsets as $ugset) {
			foreach ($ugset['usrgrpids'] as $usrgrpid) {
				$ins_ugset_groups[] = ['ugsetid' => $ugset['ugsetid'], 'usrgrpid' => $usrgrpid];
			}
		}

		DB::insert('ugset_group', $ins_ugset_groups, false);
	}

	private static function addUgSetPermissions(array &$ugsets): void {
		$ugset_indexes = [];

		foreach ($ugsets as $i => &$ugset) {
			$ugset['permissions'] = [];

			foreach ($ugset['usrgrpids'] as $usrgrpid) {
				$ugset_indexes[$usrgrpid][] = $i;
			}
		}
		unset($ugset);

		if (!$ugset_indexes) {
			return;
		}

		$options = [
			'output' => ['groupid', 'id', 'permission'],
			'filter' => ['groupid' => array_keys($ugset_indexes)]
		];
		$result = DBselect(DB::makeSql('rights', $options));

		while ($row = DBfetch($result)) {
			foreach ($ugset_indexes[$row['groupid']] as $i) {
				if (!array_key_exists($row['id'], $ugsets[$i]['permissions'])
						|| ($ugsets[$i]['permissions'][$row['id']] != PERM_DENY
							&& ($row['permission'] == PERM_DENY
								|| $row['permission'] > $ugsets[$i]['permissions'][$row['id']]))) {
					$ugsets[$i]['permissions'][$row['id']] = $row['permission'];
				}
			}
		}
	}

	/**
	 * @param array $ugsets
	 */
	private static function createPermissions(array $ugsets): void {
		$ins_permissions = [];

		$hgset_groupids = self::getHgSetGroupIds($ugsets);

		foreach ($ugsets as $ugset) {
			foreach ($hgset_groupids as $hgsetid => $groupids) {
				if (!array_intersect(array_keys($ugset['permissions']), $groupids)) {
					continue;
				}

				$max_permission = null;

				foreach ($ugset['permissions'] as $groupid => $permission) {
					if (!in_array($groupid, $groupids)) {
						continue;
					}

					if ($max_permission === null
							|| ($max_permission != PERM_DENY
								&& ($permission == PERM_DENY || $permission > $max_permission))) {
						$max_permission = $permission;
					}
				}

				if ($max_permission != PERM_DENY) {
					$ins_permissions[] = [
						'ugsetid' => $ugset['ugsetid'],
						'hgsetid' => $hgsetid,
						'permission' => $max_permission
					];
				}
			}
		}

		if ($ins_permissions) {
			DB::insert('permission', $ins_permissions, false);
		}
	}

	private static function getHgSetGroupIds(array $ugsets): array {
		$groupids = [];

		foreach ($ugsets as $ugset) {
			foreach ($ugset['permissions'] as $groupid => $permission) {
				$groupids[$groupid] = true;
			}
		}

		$result = DBselect(
			'SELECT hgg.hgsetid,hgg.groupid'.
			' FROM hgset_group hgg'.
			' WHERE hgg.hgsetid IN('.
				'SELECT DISTINCT t1.hgsetid'.
				' FROM hgset_group t1'.
				' WHERE '.dbConditionId('t1.groupid', array_keys($groupids)).
			')'
		);

		$hgset_groupids = [];

		while ($row = DBfetch($result)) {
			$hgset_groupids[$row['hgsetid']][] = $row['groupid'];
		}

		return $hgset_groupids;
	}

	private static function deleteUnusedUgSets(array $db_ugsetids): void {
		$del_ugsetids = DBfetchColumn(DBselect(
			'SELECT u.ugsetid'.
			' FROM ugset u'.
			' LEFT JOIN user_ugset uu ON u.ugsetid=uu.ugsetid'.
			' WHERE '.dbConditionId('u.ugsetid', $db_ugsetids).
				' AND uu.userid IS NULL'
		), 'ugsetid');

		if ($del_ugsetids) {
			DB::delete('permission', ['ugsetid' => $del_ugsetids]);
			DB::delete('ugset_group', ['ugsetid' => $del_ugsetids]);
			DB::delete('ugset', ['ugsetid' => $del_ugsetids]);
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
	private static function findMediaIndex(array $medias, $mediatypeid, $sendto) {
		foreach ($medias as $index => $media) {
			if (bccomp($media['mediatypeid'], $mediatypeid) == 0 && $media['sendto'] === $sendto) {
				return $index;
			}
		}

		return -1;
	}

	/**
	 * @param array      $users
	 * @param null|array $db_users
	 */
	private static function updateMedias(array &$users, array $db_users = null): void {
		$ins_medias = [];
		$upd_medias = [];
		$del_mediaids = [];

		foreach ($users as &$user) {
			if (!array_key_exists('medias', $user)) {
				continue;
			}

			$db_medias = $db_users !== null ? $db_users[$user['userid']]['medias'] : [];

			foreach ($user['medias'] as &$media) {
				$media['sendto'] = implode("\n", $media['sendto']);

				$index = self::findMediaIndex($db_medias, $media['mediatypeid'], $media['sendto']);

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
	 * @param array $users
	 */
	private static function updateMfaTotpSecret(array &$users, array $db_users = null): void {
		$ins_mfa_totp_secrets = [];
		$upd_mfa_totp_secrets = [];
		$del_mfa_totp_secrets = [];

		foreach ($users as $user) {
			if (!array_key_exists('mfa_totp_secrets', $user)) {
				continue;
			}

			$db_mfa_totp_secrets =[];

			if ($db_users != null) {
				$db_users_mfa_totp_secrets = DB::select('mfa_totp_secret', [
					'output' => ['mfa_totp_secretid', 'mfaid', 'totp_secret'],
					'filter' => ['userid' => $user['userid']]
				]);
				$db_mfa_totp_secrets = array_column($db_users_mfa_totp_secrets, null, 'mfaid');
			}

			foreach ($user['mfa_totp_secrets'] as &$mfa_totp_secret) {
				if (array_key_exists($mfa_totp_secret['mfaid'], $db_mfa_totp_secrets)) {
					$db_mfa_totp_secret = $db_mfa_totp_secrets[$mfa_totp_secret['mfaid']];
					$mfa_totp_secret['mfa_totp_secretid'] = $db_mfa_totp_secret['mfa_totp_secretid'];
					unset($db_mfa_totp_secrets[$mfa_totp_secret['mfaid']]);

					$upd_mfa_totp_secret = DB::getUpdatedValues('mfa_totp_secret', $mfa_totp_secret, $db_mfa_totp_secret);

					if ($mfa_totp_secret) {
						$upd_mfa_totp_secrets[] = [
							'values' => $upd_mfa_totp_secret,
							'where' => ['mfa_totp_secretid' => $db_mfa_totp_secret['mfa_totp_secretid']]
						];
					}
				}
				else {
					$ins_mfa_totp_secrets[] = ['userid' => $user['userid']] + $mfa_totp_secret;
				}
			}
			unset($mfa_totp_secret);

			$del_mfa_totp_secrets = array_merge($del_mfa_totp_secrets,
				array_column($db_mfa_totp_secrets, 'mfa_totp_secretid')
			);
		}

		if ($del_mfa_totp_secrets) {
			DB::delete('mfa_totp_secret', ['mfa_totp_secretid' => $del_mfa_totp_secrets]);
		}

		if ($upd_mfa_totp_secrets) {
			DB::update('mfa_totp_secret', $upd_mfa_totp_secrets);
		}

		if ($ins_mfa_totp_secrets) {
			$mfa_totp_secretids = DB::insert('mfa_totp_secret', $ins_mfa_totp_secrets);

			foreach ($users as &$user) {
				if (array_key_exists('mfa_totp_secrets', $user)) {
					foreach ($user['mfa_totp_secrets'] as &$mfa_totp_secret) {
						$mfa_totp_secret['mfa_totp_secretid'] = array_shift($mfa_totp_secretids);
					}
					unset($mfa_totp_secret);
				}
			}
			unset($user);
		}
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

		self::deleteUgSets($db_users);
		DB::delete('users_groups', ['userid' => $userids]);
		DB::delete('mfa_totp_secret', ['userid' => $userids]);
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

	private static function deleteUgSets(array $db_users): void {
		$ugsets = [];
		$ugset_hash = self::getUgSetHash([]);

		foreach ($db_users as $db_user) {
			if ($db_user['role'] && $db_user['role']['type'] != USER_TYPE_SUPER_ADMIN
					&& $db_user['usrgrps']) {
				$ugsets[$ugset_hash]['hash'] = $ugset_hash;
				$ugsets[$ugset_hash]['usrgrpids'] = [];
				$ugsets[$ugset_hash]['userids'][] = $db_user['userid'];
			}
		}

		if ($ugsets) {
			self::updateUserUgSets($ugsets);
		}
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
			'selectRole' => ['type'],
			'selectUsrgrps' => ['usrgrpid'],
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

	public static function updateFromUserGroup(array $users, array $del_user_usrgrpids): void {
		$db_users = DB::select('users', [
			'output' => ['userid', 'username', 'roleid'],
			'userids' => array_keys($users),
			'preservekeys' => true
		]);

		$db_roles = self::getDbRoles($users, $db_users);
		self::addRoleType($users, $db_roles, $db_users);

		self::addAffectedUserGroups($users, $db_users);
		self::addUnchangedUserGroups($users, $db_users, $del_user_usrgrpids);

		self::updateForce(array_values($users), $db_users);
	}

	private static function addUnchangedUserGroups(array &$users, array $db_users, array $del_user_usrgrpids): void {
		foreach ($users as &$user) {
			$usrgrpids = array_column($user['usrgrps'], 'usrgrpid');

			foreach ($db_users[$user['userid']]['usrgrps'] as $db_group) {
				if (!in_array($db_group['usrgrpid'], $usrgrpids)
						&& (!array_key_exists($user['userid'], $del_user_usrgrpids)
							|| !in_array($db_group['usrgrpid'], $del_user_usrgrpids[$user['userid']]))) {
					$user['usrgrps'][] = ['usrgrpid' => $db_group['usrgrpid']];
				}
			}
		}
		unset($user);
	}

	public static function updateFromRole(array $users, array $db_users): void {
		self::addAffectedUserGroups($users, $db_users);

		self::updateForce($users, $db_users);
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

		if (CAuthenticationHelper::isLdapProvisionEnabled(self::$userData['userdirectoryid'])) {
			$this->provisionLdapUser(self::$userData);
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
	 * @param array $data
	 *
	 * @return string|array
	 */
	public function login(array $data) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'username' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'password' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255],
			'userData' =>	['type' => API_FLAG]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = self::findLoginUsersByUsername($data['username']);

		$created = !$db_users && $data['username'] !== ZBX_GUEST_USER
			? $this->tryToCreateLdapProvisionedUser($data, $db_users)
			: false;

		self::checkSingleUserExists($data['username'], $db_users);

		$db_user = $db_users[0];

		if (!$created && $db_user['userdirectoryid'] != 0) {
			self::checkUserProvisionedByLdap($db_user);
		}

		self::addUserGroupFields($db_user, $group_status, $group_auth_type, $group_userdirectoryid);

		$db_user['auth_type'] = $db_user['userdirectoryid'] == 0 ? $group_auth_type : ZBX_AUTH_LDAP;

		if (!$created) {
			self::checkLoginTemporarilyBlocked($db_user);

			if ($db_user['auth_type'] == ZBX_AUTH_LDAP) {
				self::checkLdapAuthenticationEnabled($db_user);

				$idp_user_data = self::verifyLdapCredentials($data, $db_user, $group_userdirectoryid);
				$this->tryToUpdateLdapProvisionedUser($db_user, $group_status, $idp_user_data);
			}
			else {
				self::verifyPassword($data, $db_user);
			}
		}

		self::checkGroupStatus($db_user, $group_status);
		self::checkRole($db_user);

		self::addAdditionalFields($db_user);
		self::setTimezone($db_user['timezone']);
		self::createSession($db_user);
		unset($db_user['ugsetid']);

		if ($db_user['attempt_failed'] != 0 && $db_user['mfaid'] == 0) {
			self::resetFailedLoginAttempts($db_user);
		}

		if ($db_user['mfaid'] == 0) {
			self::addAuditLog(CAudit::ACTION_LOGIN_SUCCESS, CAudit::RESOURCE_USER);
		}

		return array_key_exists('userData', $data) && $data['userData'] ? $db_user : $db_user['sessionid'];
	}

	public static function loginByUsername(string $username, bool $case_sensitive): array {
		$db_users = self::findUsersByUsername($username, $case_sensitive);

		self::checkSingleUserExists($username, $db_users);

		$db_user = $db_users[0];

		self::addUserGroupFields($db_user, $group_status, $group_auth_type);

		self::checkGroupStatus($db_user, $group_status);
		self::checkRole($db_user);

		$db_user['auth_type'] =
			$db_user['userdirectoryid'] == 0 || !self::isLdapUserDirectory($db_user['userdirectoryid'])
				? $group_auth_type
				: ZBX_AUTH_LDAP;

		self::addAdditionalFields($db_user);
		self::setTimezone($db_user['timezone']);
		self::createSession($db_user);
		unset($db_user['ugsetid']);

		self::addAuditLog(CAudit::ACTION_LOGIN_SUCCESS, CAudit::RESOURCE_USER);

		return $db_user;
	}

	private static function findLoginUsersByUsername(string $username): array {
		$case_sensitive =
			CAuthenticationHelper::get(CAuthenticationHelper::LDAP_CASE_SENSITIVE) == ZBX_AUTH_CASE_SENSITIVE;

		$db_users = self::findUsersByUsername($username, $case_sensitive);

		if ($db_users && !$case_sensitive) {
			self::unsetCaseInsensitiveUsersOfInternalAuthType($db_users, $username);
		}

		return $db_users;
	}

	public static function findUsersByUsername(string $username, bool $case_sensitive = true): array {
		$db_users = [];

		$fields = ['userid', 'username', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh',
			'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone', 'roleid',
			'userdirectoryid', 'ts_provisioned'
		];

		if ($case_sensitive) {
			$db_users = DB::select('users', [
				'output' => $fields,
				'filter' => ['username' => $username]
			]);
		}
		else {
			$db_users = DBfetchArray(DBselect(
				'SELECT '.implode(',', $fields).
				' FROM users'.
				' WHERE LOWER(username)='.zbx_dbstr(mb_strtolower($username))
			));
		}

		return $db_users;
	}

	/**
	 * Leave only the user with ZBX_AUTH_INTERNAL authentication type, whose username strictly matches the given
	 * username, in the given users array.
	 * The given users array is cleared if users are only of ZBX_AUTH_INTERNAL authentication type, but none of them
	 * matched by the given username.
	 *
	 * Otherwise the users array will remain unchanged for further LDAP case insensitive authentication attempt.
	 *
	 * @param array  $db_users
	 * @param string $username
	 */
	private static function unsetCaseInsensitiveUsersOfInternalAuthType(array &$db_users, string $username): void {
		$gui_accesses = array_column(DBfetchArray(DBselect(
			'SELECT ug.userid,MAX(g.gui_access) AS gui_access'.
			' FROM users_groups ug,usrgrp g'.
			' WHERE ug.usrgrpid=g.usrgrpid'.
				' AND '.dbConditionId('ug.userid', array_column($db_users, 'userid')).
			' GROUP BY ug.userid'
		)), 'gui_access', 'userid');

		$auth_types = [];

		foreach ($db_users as $i => $db_user) {
			$gui_access = array_key_exists($db_user['userid'], $gui_accesses)
				? $gui_accesses[$db_user['userid']]
				: GROUP_GUI_ACCESS_SYSTEM;

			$auth_type = self::getAuthTypeByGuiAccess($gui_access);

			if ($auth_type == ZBX_AUTH_INTERNAL && $db_user['username'] === $username) {
				$db_users = array_intersect_key($db_users, array_flip([$i]));
				$auth_types = [];
				break;
			}

			$auth_types[$auth_type] = true;
		}

		if (count($auth_types) == 1 && array_key_exists(ZBX_AUTH_INTERNAL, $auth_types)) {
			$db_users = [];
		}

		$db_users = array_values($db_users);
	}

	/**
	 * Get the actual authentication type for the given GUI access value.
	 * The authentication type for GROUP_GUI_ACCESS_DISABLED should be the same as for GROUP_GUI_ACCESS_SYSTEM, because
	 * even if frontend access is disabled, this shouldn't disable the login into API.
	 *
	 * @param int $gui_access
	 *
	 * @return int
	 */
	private static function getAuthTypeByGuiAccess(int $gui_access): int {
		if ($gui_access == GROUP_GUI_ACCESS_INTERNAL) {
			return ZBX_AUTH_INTERNAL;
		}
		elseif ($gui_access == GROUP_GUI_ACCESS_LDAP) {
			return ZBX_AUTH_LDAP;
		}

		return CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE);
	}

	private function tryToCreateLdapProvisionedUser(array $data, array &$db_users): bool {
		$ldap_userdirectoryid = CAuthenticationHelper::get(CAuthenticationHelper::LDAP_USERDIRECTORYID);

		if (CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE) == ZBX_AUTH_LDAP
				&& CAuthenticationHelper::isLdapProvisionEnabled($ldap_userdirectoryid)) {
			$idp_user_data = API::UserDirectory()->test([
				'userdirectoryid' => $ldap_userdirectoryid,
				'test_username' => $data['username'],
				'test_password' => $data['password']
			]);

			if ($idp_user_data['usrgrps']) {
				$user = $this->createProvisionedUser($idp_user_data);
				$db_users = self::findUsersByUsername($user['username']);

				return true;
			}
		}

		return false;
	}

	private static function checkSingleUserExists(string $username, array $db_users): void {
		if (!$db_users) {
			self::loginException(null, $username, ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect user name or password or account is temporarily blocked.')
			);
		}

		if (count($db_users) > 1) {
			self::loginException(null, $username, ZBX_API_ERROR_PERMISSIONS,
				_s('Authentication failed: %1$s.', _('supplied credentials are not unique'))
			);
		}
	}

	private static function checkUserProvisionedByLdap(array $db_user) {
		if (!self::isLdapUserDirectory($db_user['userdirectoryid'])) {
			self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect user name or password or account is temporarily blocked.')
			);
		}
	}

	private static function isLdapUserDirectory(string $userdirectoryid): bool {
		return (bool) DB::select('userdirectory', [
			'output' => [],
			'userdirectoryids' => $userdirectoryid,
			'filter' => ['idp_type' => ZBX_AUTH_LDAP]
		]);
	}

	/**
	 * Add user group data fields to the given user and populates the given $group_status and $group_userdirectoryid.
	 * Note: user without groups is able to log in with default user group field values.
	 */
	public static function addUserGroupFields(array &$db_user, int &$group_status = null, int &$group_auth_type = null,
			string &$group_userdirectoryid = null): void {
		$db_user['debug_mode'] = GROUP_DEBUG_MODE_DISABLED;
		$db_user['deprovisioned'] = false;
		$db_user['gui_access'] = GROUP_GUI_ACCESS_SYSTEM;
		$db_user['mfaid'] = 0;

		$group_auth_type = self::getAuthTypeByGuiAccess($db_user['gui_access']);
		$group_status = GROUP_STATUS_ENABLED;
		$group_userdirectoryid = 0;

		$result = DBselect(
			'SELECT g.usrgrpid,g.debug_mode,g.users_status,g.gui_access,g.userdirectoryid,g.mfa_status,g.mfaid'.
			' FROM users_groups ug,usrgrp g'.
			' WHERE ug.usrgrpid=g.usrgrpid'.
				' AND '.dbConditionId('ug.userid', [$db_user['userid']])
		);

		$deprovision_groupid = CAuthenticationHelper::get(CAuthenticationHelper::DISABLED_USER_GROUPID);
		$mfa_status = CAuthenticationHelper::get(CAuthenticationHelper::MFA_STATUS);
		$default_mfaid = CAuthenticationHelper::get(CAuthenticationHelper::MFAID);

		$userdirectoryids = [];
		$mfaids = [];

		while ($row = DBfetch($result)) {
			if ($row['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
				$db_user['debug_mode'] = $row['debug_mode'];
			}

			if (bccomp($row['usrgrpid'], $deprovision_groupid) == 0 && $db_user['userdirectoryid'] != 0) {
				$db_user['deprovisioned'] = true;
			}

			if ($row['gui_access'] > $db_user['gui_access']) {
				$db_user['gui_access'] = $row['gui_access'];
				$group_auth_type = self::getAuthTypeByGuiAccess($row['gui_access']);
			}

			if ($row['users_status'] == GROUP_STATUS_DISABLED) {
				$group_status = $row['users_status'];
			}

			if ($group_auth_type == ZBX_AUTH_LDAP) {
				$userdirectoryids[$row['userdirectoryid']] = true;
			}

			if ($mfa_status == MFA_ENABLED && $row['mfa_status'] == GROUP_MFA_ENABLED) {
				if ($row['mfaid'] == 0) {
					$db_user['mfaid'] = $default_mfaid;
				}
				else {
					$mfaids[$row['mfaid']] = true;
				}
			}
		}

		if ($group_auth_type == ZBX_AUTH_LDAP) {
			if (array_key_exists(0, $userdirectoryids)) {
				unset($userdirectoryids[0]);

				if (CAuthenticationHelper::get(CAuthenticationHelper::LDAP_USERDIRECTORYID) != 0) {
					$userdirectoryids[CAuthenticationHelper::get(CAuthenticationHelper::LDAP_USERDIRECTORYID)] = true;
				}
			}

			if (count($userdirectoryids) > 1) {
				$db_userdirectories = DB::select('userdirectory', [
					'output' => [],
					'userdirectoryids' => array_keys($userdirectoryids),
					'sortfield' => ['name'],
					'limit' => 1,
					'preservekeys' => true
				]);

				$group_userdirectoryid = key($db_userdirectories);
			}
			elseif ($userdirectoryids) {
				$group_userdirectoryid = key($userdirectoryids);
			}
		}

		if ($mfa_status == MFA_ENABLED && $db_user['mfaid'] === 0 && $mfaids) {
			$db_mfas = DB::select('mfa', [
				'output' => ['mfaid', 'name'],
				'mfaids' => array_keys($mfaids),
				'sortfield' => ['name'],
				'limit' => 1,
				'preservekeys' => true
			]);

			$db_user['mfaid'] = key($db_mfas);
		}
	}

	private static function checkLoginTemporarilyBlocked(array $db_user): void {
		if ($db_user['attempt_failed'] < CSettingsHelper::get(CSettingsHelper::LOGIN_ATTEMPTS)) {
			return;
		}

		$login_blocking_interval = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::LOGIN_BLOCK));
		$actual_login_blocking_interval = time() - $db_user['attempt_clock'];

		if ($actual_login_blocking_interval < $login_blocking_interval) {
			self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect user name or password or account is temporarily blocked.')
			);
		}
	}

	private static function checkLdapAuthenticationEnabled(array $db_user): void {
		if (CAuthenticationHelper::get(CAuthenticationHelper::LDAP_AUTH_ENABLED) == ZBX_AUTH_LDAP_DISABLED) {
			self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect user name or password or account is temporarily blocked.')
			);
		}
	}

	private static function verifyLdapCredentials(array $data, array $db_user, string $group_userdirectoryid): array {
		try {
			return API::UserDirectory()->test([
				'userdirectoryid' => $db_user['userdirectoryid'] != 0
					? $db_user['userdirectoryid']
					: $group_userdirectoryid,
				'test_username' => $data['username'],
				'test_password' => $data['password']
			]);
		}
		catch (APIException $e) {
			if ($e->getCode() == ZBX_API_ERROR_PERMISSIONS) {
				self::increaseFailedLoginAttempts($db_user);

				self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PERMISSIONS,
					_('Incorrect user name or password or account is temporarily blocked.')
				);
			}

			throw $e;
		}
	}

	private function tryToUpdateLdapProvisionedUser(array &$db_user, int &$group_status, array $idp_user_data): void {
		if (CAuthenticationHelper::isLdapProvisionEnabled($db_user['userdirectoryid'])) {
			$idp_user_data['userid'] = $db_user['userid'];

			if ($this->updateProvisionedUser($idp_user_data)) {

				$db_user = self::findUsersByUsername($db_user['username'])[0];

				self::addUserGroupFields($db_user, $group_status);
				$db_user['auth_type'] = ZBX_AUTH_LDAP;
			}
			else {
				$db_user['deprovisioned'] = true;
			}
		}
	}

	private static function verifyPassword(array $data, array &$db_user): void {
		$options = [
			'output' => ['passwd'],
			'userids' => $db_user['userid']
		];
		[$db_passwd] = DBfetchColumn(DBselect(DB::makeSql('users', $options)), 'passwd');

		if (!password_verify($data['password'], $db_passwd)) {
			self::increaseFailedLoginAttempts($db_user);

			self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PERMISSIONS,
				_('Incorrect user name or password or account is temporarily blocked.')
			);
		}
	}

	private static function increaseFailedLoginAttempts(array $db_user): void {
		$attempt_failed = $db_user['attempt_failed'] + 1;

		DB::update('users', [
			'values' => [
				'attempt_failed' => $attempt_failed,
				'attempt_clock' => time(),
				'attempt_ip' => substr(CWebUser::getIp(), 0, 39)
			],
			'where' => ['userid' => $db_user['userid']]
		]);

		$users = [['userid' => $db_user['userid'], 'attempt_failed' => $attempt_failed]];
		$db_users = [$db_user['userid'] => $db_user];

		self::addAuditLogByUser($db_user['userid'], CWebUser::getIp(), $db_user['username'],
			CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users
		);
	}

	private static function checkGroupStatus(array $db_user, int $group_status): void {
		if ($group_status == GROUP_STATUS_DISABLED) {
			self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PARAMETERS,
				_('No permissions for system access.')
			);
		}
	}

	private static function checkRole(array $db_user): void {
		if ($db_user['roleid'] == 0) {
			self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PARAMETERS,
				_('No permissions for system access.')
			);
		}
	}

	private static function loginException(?string $userid, string $username, int $code, string $error): void {
		self::addAuditLogByUser($userid, CWebUser::getIp(), $username, CAudit::ACTION_LOGIN_FAILED,
			CAudit::RESOURCE_USER
		);

		self::exception($code, $error);
	}

	private static function addAdditionalFields(array &$db_user): void {
		$db_user['type'] = self::getUserType($db_user['roleid']);
		$db_user['ugsetid'] = self::getUgSetId($db_user);
		$db_user['userip'] = CWebUser::getIp();

		if ($db_user['lang'] === LANG_DEFAULT) {
			$db_user['lang'] = CSettingsHelper::getGlobal(CSettingsHelper::DEFAULT_LANG);
		}

		if ($db_user['timezone'] === TIMEZONE_DEFAULT) {
			$db_user['timezone'] = CSettingsHelper::getGlobal(CSettingsHelper::DEFAULT_TIMEZONE);
		}
	}

	private static function resetFailedLoginAttempts(array $db_user): void {
		$upd_user = ['attempt_failed' => 0];

		DB::update('users', [
			'values' => $upd_user,
			'where' => ['userid' => $db_user['userid']]
		]);

		$users = [$upd_user + ['userid' => $db_user['userid']]];
		$db_users = [$db_user['userid'] => $db_user];

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users);
	}


	private static function setTimezone(?string $timezone): void {
		if ($timezone !== null && $timezone !== ZBX_DEFAULT_TIMEZONE) {
			date_default_timezone_set($timezone);
		}
	}

	private static function createSession(array &$db_user): void {
		$db_user['sessionid'] = CEncryptHelper::generateKey();
		$db_user['secret'] = CEncryptHelper::generateKey();
		$session_status = ZBX_SESSION_ACTIVE;

		if (array_key_exists('mfaid', $db_user) && $db_user['mfaid'] != 0) {
			$session_status = ZBX_SESSION_VERIFICATION_REQUIRED;
		}

		DB::insert('sessions', [[
			'sessionid' => $db_user['sessionid'],
			'userid' => $db_user['userid'],
			'lastaccess' => time(),
			'status' => $session_status,
			'secret' => $db_user['secret']
		]], false);

		self::$userData = $db_user;
	}

	/**
	 * Checks if user is authenticated by session ID or by API token.
	 *
	 * @param array  $session
	 * @param string $session[]['sessionid']  Session ID to be checked.
	 * @param string $session[]['token']      API token to be checked.
	 * @param bool   $session[]['extend']     Optional. Used with 'sessionid' to extend the user session which updates
	 *                                        'lastaccess' time.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function checkAuthentication(array $session): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'sessionid' => ['type' => API_STRING_UTF8],
			'extend' => ['type' => API_MULTIPLE, 'rules' => [
				['if' => function (array $data): bool {
					return !array_key_exists('token', $data);
				}, 'type' => API_BOOLEAN, 'default' => true],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'token' => ['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $session, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$session += ['sessionid' => null, 'token' => null];

		if (($session['sessionid'] === null && $session['token'] === null)
				|| ($session['sessionid'] !== null && $session['token'] !== null)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session ID or token is expected.'));
		}

		$time = time();

		// Access DB only once per page load.
		if (self::$userData !== null && self::$userData['sessionid'] === $session['sessionid']) {
			return self::$userData;
		}

		if ($session['sessionid'] !== null) {
			$db_session = self::sessionidAuthentication($session['sessionid']);
			$userid = $db_session['userid'];
		}
		else {
			$db_token = self::tokenAuthentication($session['token'], $time);
			$userid = $db_token['userid'];
		}

		$fields = ['userid', 'username', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh',
			'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone', 'roleid',
			'userdirectoryid', 'ts_provisioned'
		];

		[$db_user] = DB::select('users', ['output' => $fields, 'userids' => $userid]);

		self::addUserGroupFields($db_user, $group_status, $group_auth_type);

		if (!$db_user['deprovisioned'] && CAuthenticationHelper::isTimeToProvision($db_user['ts_provisioned'])
				&& CAuthenticationHelper::isLdapProvisionEnabled($db_user['userdirectoryid'])
				&& !$this->provisionLdapUser($db_user)) {
			[$db_user] = DB::select('users', ['output' => $fields, 'userids' => $userid]);

			self::addUserGroupFields($db_user, $group_status, $group_auth_type);
		}

		$db_user['auth_type'] =
			$db_user['userdirectoryid'] == 0 || !self::isLdapUserDirectory($db_user['userdirectoryid'])
				? $group_auth_type
				: ZBX_AUTH_LDAP;

		self::addAdditionalFields($db_user);
		self::setTimezone($db_user['timezone']);

		if ($session['sessionid'] !== null) {
			$autologout = timeUnitToSeconds($db_user['autologout']);

			if (($autologout != 0 && $db_session['lastaccess'] + $autologout <= $time)
					|| $group_status == GROUP_STATUS_DISABLED) {
				DB::delete('sessions', [
					'status' => ZBX_SESSION_PASSIVE,
					'userid' => $db_user['userid']
				]);
				DB::update('sessions', [
					'values' => ['status' => ZBX_SESSION_PASSIVE],
					'where' => ['sessionid' => $session['sessionid']]
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
			}

			if ($session['extend'] && $time != $db_session['lastaccess']) {
				DB::update('sessions', [
					'values' => ['lastaccess' => $time],
					'where' => ['sessionid' => $session['sessionid']]
				]);
			}

			self::$userData = $db_user + ['sessionid' => $session['sessionid']];

			$db_user['sessionid'] = $session['sessionid'];
			$db_user['secret'] = $db_session['secret'];
		}
		else {
			if ($group_status == GROUP_STATUS_DISABLED) {
				self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
			}

			DB::update('token', [
				'values' => ['lastaccess' => $time],
				'where' => ['tokenid' => $db_token['tokenid']]
			]);

			self::$userData = $db_user + ['token' => $session['token']];

		}

		unset($db_user['ugsetid']);

		return $db_user;
	}

	/**
	 * Authenticates user based on API token.
	 *
	 * @param string $auth_token API token.
	 * @param int    $time       Current time unix timestamp.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	private static function tokenAuthentication(string $auth_token, int $time): array {
		$db_tokens = DB::select('token', [
			'output' => ['userid', 'expires_at', 'tokenid'],
			'filter' => ['token' => hash('sha512', $auth_token), 'status' => ZBX_AUTH_TOKEN_ENABLED]
		]);

		if (!$db_tokens) {
			usleep(10000);
			self::exception(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
		}

		$db_token = $db_tokens[0];

		if ($db_token['expires_at'] != 0 && $db_token['expires_at'] < $time) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('API token expired.'));
		}

		return $db_token;
	}

	/**
	 * Authenticates user based on session ID.
	 *
	 * @param string $sessionid Session ID.
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	private static function sessionidAuthentication(string $sessionid): array {
		$db_sessions = DB::select('sessions', [
			'output' => ['userid', 'lastaccess', 'secret'],
			'sessionids' => $sessionid,
			'filter' => ['status' => ZBX_SESSION_ACTIVE]
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Session terminated, re-login, please.'));
		}

		return $db_sessions[0];
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
	 * Provision users. Only users with IDP_TYPE_LDAP userdirectory will be provisioned.
	 *
	 * @param array $userids
	 */
	public function provision(array $userids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $userids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users = API::User()->get([
			'output' => ['userid', 'username', 'userdirectoryid'],
			'userids' => $userids,
			'preservekeys' => true
		]);
		$userdirectoryids = array_column($db_users, 'userdirectoryid', 'userdirectoryid');
		unset($userdirectoryids[0]);
		$provisionedids = [];
		$db_userdirectoryids = [];

		if ($userdirectoryids) {
			$db_userdirectoryids = array_column(API::UserDirectory()->get([
				'output' => ['userdirectoryid', 'idp_type'],
				'userdirectoryids' => $userdirectoryids,
				'filter' => ['provision_status' => JIT_PROVISIONING_ENABLED]
			]), 'idp_type', 'userdirectoryid');

			if (array_diff_key($userdirectoryids, $db_userdirectoryids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		if ($db_userdirectoryids) {
			$db_user_userdirectory = array_column($db_users, 'userdirectoryid', 'userid');

			foreach (array_keys($db_userdirectoryids, IDP_TYPE_LDAP) as $db_userdirectoryid) {
				$provisioning = CProvisioning::forUserDirectoryId($db_userdirectoryid);
				$provision_users = array_keys($db_user_userdirectory, $db_userdirectoryid);
				$provision_users = array_intersect_key($db_users, array_flip($provision_users));
				$config = $provisioning->getIdpConfig();
				$ldap = new CLdap($config);

				if ($ldap->bind_type == CLdap::BIND_DNSTRING) {
					continue;
				}

				foreach ($provision_users as $provision_user) {
					$user = array_merge(
						$provision_user,
						$ldap->getProvisionedData($provisioning, $provision_user['username'])
					);
					$this->updateProvisionedUser($user);
					$provisionedids[] = $provision_user['userid'];
				}
			}
		}

		return ['userids' => $provisionedids];
	}

	/**
	 * Create user in database from provision data.
	 * User media are sanitized removing media with malformed or empty 'sendto'.
	 *
	 * @param array  $idp_user_data
	 * @param string $idp_user_data['username']
	 * @param string $idp_user_data['name']
	 * @param string $idp_user_data['surname']
	 * @param int    $idp_user_data['roleid']
	 * @param array  $idp_user_data['media']                   Required to be set, can be empty array.
	 * @param int    $idp_user_data['media'][]['mediatypeid']
	 * @param array  $idp_user_data['media'][]['sendto']
	 * @param string $idp_user_data['media'][]['sendto'][]
	 * @param array  $idp_user_data['usrgrps']                 Required to be set, can be empty array.
	 * @param int    $idp_user_data['usrgrps'][]['usrgrpid']
	 * @param int    $idp_user_data['userdirectoryid']
	 *
	 * @return array of created user data in database.
	 */
	public function createProvisionedUser(array $idp_user_data): array {
		$attrs = array_flip(array_merge(self::PROVISIONED_FIELDS, ['userdirectoryid']));
		unset($attrs['passwd']);
		$user = array_intersect_key($idp_user_data, $attrs);
		$user['medias'] = $this->sanitizeUserMedia($user['medias']);

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'username' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$users = [$user];

		$db_roles = self::getDbRoles($users);
		self::addRoleType($users, $db_roles);

		$users[0]['ts_provisioned'] = time();
		self::createForce($users);

		return reset($users);
	}

	/**
	 * Update provisioned user in database. Return empty array when user is deprovisioned.
	 *
	 * @param int   $db_userid
	 * @param array $idp_user_data
	 * @param array $idp_user_data['userid']
	 * @param array $idp_user_data['usrgrps']  (optional) Array of user matched groups.
	 * @param array $idp_user_data['medias']   (optional) Array of user matched medias.
	 *
	 * @return array
	 */
	public function updateProvisionedUser(array $idp_user_data): array {
		$attrs = array_flip(array_merge(
			array_diff(self::PROVISIONED_FIELDS, ['username', 'passwd']), ['userdirectoryid', 'userid']
		));
		$user = array_intersect_key($idp_user_data, $attrs);

		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'username' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('users', 'username')],
			'name' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'name')],
			'surname' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('users', 'surname')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $user, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$userid = $user['userid'];
		$db_users = DB::select('users', [
			'output' => ['userid', 'username', 'name', 'surname', 'roleid', 'userdirectoryid', 'ts_provisioned'],
			'userids' => [$userid],
			'preservekeys' => true
		]);
		$user['ts_provisioned'] = time();
		$users = [$userid => $user];
		$user += ['username' => $db_users[$userid]['username']];

		$db_roles = self::getDbRoles($users, $db_users);
		self::addRoleType($users, $db_roles, $db_users);

		if (array_key_exists('medias', $user)) {
			$users[$userid]['medias'] = $this->sanitizeUserMedia($user['medias']);
			$db_users[$userid]['medias'] = DB::select('media', [
				'output' => ['mediatypeid', 'mediaid', 'sendto'],
				'filter' => ['userid' => $userid]
			]);
		}

		if (array_key_exists('usrgrps', $user)) {
			$db_users[$userid]['usrgrps'] = DB::select('users_groups', [
				'output' => ['usrgrpid', 'id'],
				'filter' => ['userid' => $userid]
			]);

			if (!$user['usrgrps']) {
				$users[$userid]['usrgrps'] = [[
					'usrgrpid' => CAuthenticationHelper::get(CAuthenticationHelper::DISABLED_USER_GROUPID)
				]];
				$users[$userid]['roleid'] = 0;
				$user = [];
			}
		}

		self::updateForce(array_values($users), $db_users);

		return $user;
	}

	/**
	 * Returns user type.
	 *
	 * @param string $roleid
	 *
	 * @return int
	 */
	private static function getUserType(string $roleid): int {
		if (!$roleid) {
			return USER_TYPE_ZABBIX_USER;
		}

		return DBfetchColumn(DBselect('SELECT type FROM role WHERE roleid='.zbx_dbstr($roleid)), 'type')[0];
	}

	private static function getUgSetId(array $db_user): ?string {
		if ($db_user['roleid'] != 0 && $db_user['type'] != USER_TYPE_SUPER_ADMIN) {
			$options = [
				'output' => ['ugsetid'],
				'userids' => $db_user['userid']
			];
			$row = DBfetch(DBselect(DB::makeSql('user_ugset', $options)));

			if ($row) {
				return $row['ugsetid'];
			}
		}

		return 0;
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

	/**
	 * For user provisioned by IDP_TYPE_LDAP update user provisioned attributes. Will return empty array
	 * when user is deprovisioned.
	 *
	 * @param array  $user_data
	 * @param int    $user_data['userid']
	 * @param string $user_data['username']
	 * @return array
	 */
	protected function provisionLdapUser(array $user_data): array {
		$provisioning = CProvisioning::forUserDirectoryId($user_data['userdirectoryid']);
		$config = $provisioning->getIdpConfig();
		$ldap = new CLdap($config);

		if ($ldap->bind_type != CLdap::BIND_ANONYMOUS && $ldap->bind_type != CLdap::BIND_CONFIG_CREDENTIALS) {
			return $user_data;
		}

		$user = $ldap->getProvisionedData($provisioning, $user_data['username']);
		$user['username'] = $user_data['username'];
		$user['userid'] = $user_data['userid'];

		return $this->updateProvisionedUser($user);
	}

	/**
	 * Remove invalid medias.
	 *
	 * @param array $medias
	 * @param array $medias[]['mediatypeid']
	 */
	protected function sanitizeUserMedia(array $medias): array {
		if (!$medias) {
			return $medias;
		}

		$user_medias = [];
		$email_mediatypeids = [];
		$max_length = DB::getFieldLength('media', 'sendto');
		$mediatypeids = array_column($medias, 'mediatypeid', 'mediatypeid');
		$email_validator = new CEmailValidator();

		if ($mediatypeids) {
			$email_mediatypeids = array_keys(DB::select('media_type', [
				'output' => ['mediatypeid'],
				'filter' => ['type' => MEDIA_TYPE_EMAIL],
				'mediatypeids' => $mediatypeids,
				'preservekeys' => true
			]));
		}

		foreach ($medias as $media) {
			$sendto = array_filter($media['sendto'], 'strlen');

			if (in_array($media['mediatypeid'], $email_mediatypeids)) {
				$sendto = array_filter($media['sendto'], [$email_validator, 'validate']);

				while (mb_strlen(implode("\n", $sendto)) > $max_length && count($sendto) > 0) {
					array_pop($sendto);
				}
			}

			if ($sendto) {
				$user_medias[] = [
					'mediatypeid' => $media['mediatypeid'],
					'sendto' => $sendto
				];
			}
		}

		return $user_medias;
	}

	/**
	 * Returns data necessary for user.confirm method.
	 *
	 * @param array  $session_data
	 * @param string $session_data['sessionid']     User's sessionid passed in session data.
	 * @param string $session_data['mfaid']         User's mfaid passed ins session data.
	 * @param string $session_data['redirect_uri']  Redirect uri that will be used for Duo MFA.
	 *
	 * Returns:
	 *                     data['mfa']
	 *                     data['userid]
	 *
	 * If MFA_TYPE_TOTP and user has no totp_secret additionally returns:
	 *                     data['totp_secret']
	 *                     data['qr_code_url']
	 *
	 * If MFA_TYPE_DUO additionally returns:
	 *                     data['username']
	 *                     data['state']
	 *                     data['prompt_uri']
	 */
	public static function getConfirmData(array $session_data): array {
		$userid = DB::select('sessions', [
			'output' => ['userid'],
			'filter' => ['sessionid' => $session_data['sessionid']]
		]);

		if (!$userid) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('You must login to view this page.'));
		}

		$userid = $userid[0];

		[$mfa] = DB::select('mfa', [
			'output' => ['mfaid', 'type', 'name', 'hash_function', 'code_length', 'api_hostname', 'clientid',
				'client_secret'
			],
			'filter' => ['mfaid' => $session_data['mfaid']]
		]);

		$data = [
			'sessionid' => $session_data['sessionid'],
			'mfa' => $mfa,
			'userid' => $userid['userid']
		];

		if ($mfa['type'] == MFA_TYPE_TOTP) {
			$totp_generator = self::createTotpGenerator($data['mfa']);

			$user_totp_secret = DB::select('mfa_totp_secret', [
				'output' => ['totp_secret'],
				'filter' => ['mfaid' => $data['mfa']['mfaid'], 'userid' => $data['userid']]
			]);

			if (!$user_totp_secret) {
				$data['totp_secret'] = $totp_generator->generateSecretKey(TOTP_SECRET_LENGTH_32);
				$data['qr_code_url'] = $totp_generator->getQRCodeUrl('Zabbix', $data['mfa']['name'],
					$data['totp_secret']
				);
			}
		}

		if ($mfa['type'] == MFA_TYPE_DUO) {
			try {
				$duo_client = new Client($data['mfa']['clientid'], $data['mfa']['client_secret'],
					$data['mfa']['api_hostname'], $session_data['redirect_uri']);

				$duo_client->healthCheck();
			}
			catch (DuoException $e) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					'Verify the values in Duo Universal Prompt MFA method are correct.'. $e->getMessage()
				);
			}

			[$username] = DB::select('users', [
				'output' => ['username'],
				'filter' => ['userid' => $data['userid']]
			]);
			$data['username'] = $username['username'];

			$data['state'] = $duo_client->generateState();
			$data['prompt_uri'] = $duo_client->createAuthUrl($data['username'], $data['state']);
		}

		return $data;
	}

	/**
	 * Check MFA method authentication for the user based on provided data.
	 *
	 * @param array  $data
	 * @param string $data['sessionid']                               User's sessionid passed in session data.
	 * @param string $data['mfaid']                                   User's mfaid passed ins session data.
	 * @param string $data['redirect_uri']                            Redirect uri that will be used for Duo MFA.
	 * @param array  $data['mfa_response_data']                       Array with data for MFA response confirmation.
	 * @param int    $data['mfa_response_data']['verification_code']  TOTP MFA verification code.
	 * @param string $data['mfa_response_data']['totp_secret']        TOTP MFA secret at initial registration.
	 * @param string $data['mfa_response_data']['duo_code']           DUO MFA response code.
	 * @param string $data['mfa_response_data']['duo_state']          DUO MFA response state.
	 * @param string $data['mfa_response_data']['state']              DUO MFA state from session.
	 * @param string $data['mfa_response_data']['username']           DUO MFA username from session.
	 *
	 * Returns 'sessionid' and 'mfa' object, in case MFA authentication was successful or throws Exception.
	 */
	public static function confirm(array $data): array {
		$userid = DB::select('sessions', [
			'output' => ['userid'],
			'filter' => ['sessionid' => $data['sessionid']]
		]);

		if (!$userid) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('You must login to view this page.'));
		}

		$userid = $userid[0];

		[$mfa] = DB::select('mfa', [
			'output' => ['mfaid', 'type', 'name', 'hash_function', 'code_length', 'api_hostname', 'clientid',
				'client_secret'
			],
			'filter' => ['mfaid' => $data['mfaid']]
		]);

		[$failed_login_data] = DB::select('users', [
			'output' => ['userid', 'username', 'attempt_failed', 'attempt_clock'],
			'userids' => $userid,
			'limit' => 1
		]);
		$mfa_response_data = $data['mfa_response_data'];

		if ($mfa['type'] == MFA_TYPE_TOTP) {
			$totp_generator = self::createTotpGenerator($mfa);

			if (!array_key_exists('totp_secret', $mfa_response_data) || $mfa_response_data['totp_secret'] == '') {
				$user_totp_secret = DB::select('mfa_totp_secret', [
					'output' => ['totp_secret'],
					'filter' => ['mfaid' => $mfa['mfaid'], 'userid' => $userid['userid']]
				]);
				$user_totp_secret = $user_totp_secret[0]['totp_secret'];
			}
			else {
				$user_totp_secret = $mfa_response_data['totp_secret'];
			}

			$valid_code = $totp_generator->verifyKey($user_totp_secret, $mfa_response_data['verification_code']);

			if ($valid_code) {
				if (array_key_exists('totp_secret', $mfa_response_data) && $mfa_response_data['totp_secret'] != '') {
					DB::insert('mfa_totp_secret', [[
						'mfaid' => $mfa['mfaid'],
						'userid' => $userid['userid'],
						'totp_secret' => $mfa_response_data['totp_secret']
					]]);
				}
			}
			else {
				self::increaseFailedLoginAttempts($failed_login_data);

				if (($failed_login_data['attempt_failed'] + 1) >= CSettingsHelper::get(
					CSettingsHelper::LOGIN_ATTEMPTS
				)) {
					[$failed_login_data] = DB::select('users', [
						'output' => ['userid', 'username', 'attempt_failed', 'attempt_clock'],
						'userids' => $userid,
						'limit' => 1
					]);
				}

				try {
					self::checkLoginTemporarilyBlocked($failed_login_data);
				}
				catch (Exception $e) {
					DB::delete('sessions', ['sessionid' => $data['sessionid']]);

					throw $e;
				}

				self::exception(ZBX_API_ERROR_PARAMETERS, _('The verification code was incorrect, please try again.'));
			}
		}

		if ($mfa['type'] == MFA_TYPE_DUO) {
			if (!array_key_exists('state', $mfa_response_data) || !array_key_exists('username', $mfa_response_data)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No saved state please login again.'));
			}

			if ($mfa_response_data['duo_state'] != $mfa_response_data['state']) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Duo state does not match saved state.'));
			}

			try {
				$duo_client = new Client($mfa['clientid'], $mfa['client_secret'],
					$mfa['api_hostname'], $data['redirect_uri']
				);

				$duo_client->exchangeAuthorizationCodeFor2FAResult($mfa_response_data['duo_code'],
					$mfa_response_data['username']
				);
			} catch (DuoException $e) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Error decoding Duo result.'));
			}
		}

		DB::update('sessions', [
			'values' => ['status' => ZBX_SESSION_ACTIVE],
			'where' => ['sessionid' => $data['sessionid']]
		]);

		$outdated = strtotime('-5 minutes');

		DBexecute(
			'DELETE FROM sessions'.
				' WHERE '.dbConditionId('userid', [$userid['userid']]).
					' AND '.dbConditionInt('status', [ZBX_SESSION_VERIFICATION_REQUIRED]).
					' AND lastaccess<'.zbx_dbstr($outdated)
		);

		CWebUser::checkAuthentication($data['sessionid']);
		self::resetFailedLoginAttempts($failed_login_data);
		self::addAuditLog(CAudit::ACTION_LOGIN_SUCCESS, CAudit::RESOURCE_USER);

		return [
			'sessionid' => $data['sessionid'],
			'mfa' => $mfa
		];
	}

	/**
	 * Returns Google2FA library instance for TOTP secret creation and code verification.
	 *
	 * @param $data
	 * @return Google2FA
	 */
	private static function createTotpGenerator($data): Google2FA {
		$totp_generator = new Google2FA();

		switch ($data['hash_function']) {
			case TOTP_HASH_SHA256:
				$totp_generator->setAlgorithm(Constants::SHA256);
				break;

			case TOTP_HASH_SHA512:
				$totp_generator->setAlgorithm(Constants::SHA512);
				break;

			default:
				$totp_generator->setAlgorithm(Constants::SHA1);
		}

		$totp_generator->setWindow(TOTP_VERIFICATION_DELAY_WINDOW);

		if ($data['code_length'] == TOTP_CODE_LENGTH_8) {
			$totp_generator->setOneTimePasswordLength(TOTP_CODE_LENGTH_8);
		}

		return $totp_generator;
	}
}
