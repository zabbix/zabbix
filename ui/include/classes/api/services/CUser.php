<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
		'provision' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'resettotp' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'users';
	protected $tableAlias = 'u';
	protected $sortColumns = ['userid', 'username'];

	public const OUTPUT_FIELDS = ['userid', 'username', 'name', 'surname', 'passwd', 'url', 'autologin', 'autologout',
		'lang', 'refresh', 'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone',
		'roleid', 'userdirectoryid', 'ts_provisioned'
	];

	private const PROVISIONED_FIELDS = ['username', 'name', 'surname', 'usrgrps', 'medias', 'roleid'];

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
			$result = $this->unsetExtraFields($result, ['roleid'], $options['output']);

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput'] && $options['selectRole'] !== null) {
			$sql_parts = $this->addQuerySelect($this->fieldId('roleid'), $sql_parts);
		}

		return $sql_parts;
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
			'medias' =>			['type' => API_OBJECTS, 'fields' => self::getMediaValidationFields()]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $users, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($users as $i => &$user) {
			$user = $this->checkLoginOptions($user);

			if (array_key_exists('passwd', $user)) {
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

		self::checkUserGroups($users, $db_user_groups);
		self::checkEmptyPassword($users, $db_user_groups);
		self::checkMediaTypes($users, $db_mediatypes);
		self::checkMediaRecipients($users, $db_mediatypes);
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
			'medias' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['mediaid']], 'fields' => [
				'mediaid' =>		['type' => API_ID]
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

		foreach ($users as $i => &$user) {
			$db_user = $db_users[$user['userid']];

			if ($db_user['userdirectoryid'] != 0) {
				$upd_user = DB::getUpdatedValues('users',
					array_intersect_key($user, array_flip(['username', 'passwd'])), $db_users[$user['userid']]
				);

				if ($upd_user) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i + 1),
						_s('cannot update readonly parameter "%1$s" of provisioned user', key($upd_user))
					));
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

			if ($db_user['username'] === ZBX_GUEST_USER) {
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
		self::checkRoles($users, $db_roles, $db_users);
		self::addRoleType($users, $db_roles, $db_users);

		self::addAffectedObjects($users, $db_users);

		self::validateMedias($users, $db_users);

		self::checkOwnParameters($users, $db_users);

		if ($usernames) {
			$this->checkDuplicates($usernames);
		}
		$this->checkLanguages(zbx_objectValues($users, 'lang'));

		self::checkUserGroups($users, $db_user_groups, $db_users);
		self::checkEmptyPassword($users, $db_user_groups, $db_users);
		self::checkMediaTypes($users, $db_mediatypes);
		self::checkMediaRecipients($users, $db_mediatypes);
	}

	private static function getMediaValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$specific_rules = $is_update
			? [
				'mediaid' =>	['type' => API_ANY]
			]
			: [];

		return $specific_rules + [
			'mediatypeid' =>	['type' => API_ID, 'flags' => $api_required],
			'sendto' =>			['type' => API_STRINGS_UTF8, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE],
			'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
			'severity' =>		['type' => API_INT32, 'in' => '0:63'],
			'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('media', 'period')]
		];
	}

	private static function validateMedias(array &$users, array &$db_users): void {
		foreach ($users as $i1 => &$user) {
			if (!array_key_exists('medias', $user)) {
				continue;
			}

			$path = '/'.($i1 + 1).'/medias';
			$db_medias = $db_users[$user['userid']]['medias'];

			foreach ($user['medias'] as $i2 => &$media) {
				$is_update = array_key_exists('mediaid', $media);

				if ($is_update) {
					if (!array_key_exists($media['mediaid'], $db_users[$user['userid']]['medias'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/'.($i2 + 1).'/mediaid', _('object does not exist or belongs to another object')
						));
					}
				}

				$api_input_rules = ['type' => API_OBJECT, 'fields' => self::getMediaValidationFields($is_update)];

				if (!CApiInputValidator::validate($api_input_rules, $media, $path.'/'.($i2 + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				if ($is_update) {
					$db_media = $db_medias[$media['mediaid']];
					unset($db_medias[$media['mediaid']]);

					if ($db_media['userdirectory_mediaid'] != 0) {
						$_media = [];

						if (array_key_exists('mediatypeid', $media)) {
							$_media['mediatypeid'] = $media['mediatypeid'];
						}

						if (array_key_exists('sendto', $media)) {
							$_media['sendto'] = implode("\n", $media['sendto']);
						}

						$upd_media = DB::getUpdatedValues('media', $_media, $db_media);

						if ($upd_media) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								$path.'/'.($i2 + 1),
								_s('cannot update readonly parameter "%1$s" of provisioned user', key($upd_media))
							));
						}
					}

					if (!array_key_exists('mediatypeid', $media)) {
						$media['mediatypeid'] = $db_media['mediatypeid'];
					}
				}
			}
			unset($media);

			foreach ($db_medias as $db_media) {
				if ($db_media['userdirectory_mediaid'] != 0) {
					unset($db_users[$user['userid']]['medias'][$db_media['mediaid']]);
				}
			}
		}
		unset($user);
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
			'output' => ['mediaid', 'userid', 'mediatypeid', 'sendto', 'active', 'severity', 'period',
				'userdirectory_mediaid'
			],
			'filter' => ['userid' => $userids]
		];
		$db_medias = DBselect(DB::makeSql('media', $options));

		while ($db_media = DBfetch($db_medias)) {
			$db_users[$db_media['userid']]['medias'][$db_media['mediaid']] =
				array_diff_key($db_media, array_flip(['userid']));
		}
	}

	/**
	 * Check whether current user is allowed to change specific own parameters.
	 *
	 * @param array $users
	 * @param array $db_users
	 *
	 * @throws APIException
	 */
	private static function checkOwnParameters(array $users, array $db_users): void {
		$user = self::getOwnUser($users);

		if ($user === null) {
			return;
		}

		$db_user = $db_users[$user['userid']];

		self::checkOwnUsername($user, $db_user);
		self::checkOwnRole($user, $db_user);
		self::checkOwnUserGroups($user, $db_user);
	}

	private static function getOwnUser(array $users): ?array {
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
			foreach ($users as $user) {
				if (bccomp($user['userid'], self::$userData['userid']) == 0) {
					return $user;
				}
			}

			return null;
		}

		return reset($users);
	}

	private static function checkOwnUsername(array $user, array $db_user): void {
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
			return;
		}

		if (array_key_exists('username', $user) && $user['username'] !== $db_user['username']) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Only Super admin users can update "%1$s" parameter.', 'username')
			);
		}
	}

	private static function checkOwnRole(array $user, array $db_user): void {
		if (array_key_exists('roleid', $user) && bccomp($user['roleid'], $db_user['roleid']) != 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('User cannot change own role.'));
		}
	}

	private static function checkOwnUserGroups(array $user, array $db_user): void {
		if (!array_key_exists('usrgrps', $user)) {
			return;
		}

		$usrgrpids = array_column($user['usrgrps'], 'usrgrpid');

		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
			$disabled_user_group = DBfetch(DBselect(
				'SELECT NULL'.
				' FROM usrgrp'.
				' WHERE '.dbConditionId('usrgrpid', $usrgrpids).
					' AND ('.
						'gui_access='.GROUP_GUI_ACCESS_DISABLED.
						' OR users_status='.GROUP_STATUS_DISABLED.
					')',
				1
			));

			if ($disabled_user_group) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_('User cannot add oneself to a disabled group or a group with disabled GUI access.')
				);
			}
		}
		else {
			$db_usrgrpids = array_column($db_user['usrgrps'], 'usrgrpid');

			if (array_diff($usrgrpids, $db_usrgrpids) || array_diff($db_usrgrpids, $usrgrpids)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Only Super admin users can update "%1$s" parameter.', 'usrgrps')
				);
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

	private static function checkUserGroups(array $users, array &$db_user_groups = null, array $db_users = null): void {
		$user_group_indexes = [];

		foreach ($users as $i1 => $user) {
			if (!array_key_exists('usrgrps', $user)) {
				continue;
			}

			if ($db_users !== null && $db_users[$user['userid']]['userdirectoryid'] != 0
					&& self::userGroupsChanged($user, $db_users[$user['userid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i1 + 1),
					_s('cannot update readonly parameter "%1$s" of provisioned user', 'usrgrps')
				));
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
					_s('Invalid parameter "%1$s": %2$s.', '/'.($i1 + 1).'/usrgrps/'.($i2 + 1),
						_('object does not exist')
					)
				);
			}
		}
	}

	private static function checkEmptyPassword(array $users, ?array $db_user_groups, array $db_users = null): void {
		foreach ($users as $i => $user) {
			$check = false;

			if ($db_users === null) {
				$check = !array_key_exists('passwd', $user);
			}
			else {
				$db_user = $db_users[$user['userid']];

				if (!array_key_exists('passwd', $user) && $db_user['passwd'] === ''
						&& $db_user['userdirectoryid'] == 0) {
					$user_groups_changed = array_key_exists('usrgrps', $user)
						&& self::userGroupsChanged($user, $db_user);

					$user_groups_empty = array_key_exists('usrgrps', $user) ? !$user['usrgrps'] : !$db_user['usrgrps'];

					if ($user_groups_changed || $user_groups_empty) {
						$check = true;
					}
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
			$gui_access = GROUP_GUI_ACCESS_SYSTEM;

			if (array_key_exists('usrgrps', $user)) {
				foreach ($user['usrgrps'] as $user_group) {
					if ($db_user_groups[$user_group['usrgrpid']]['gui_access'] > $gui_access) {
						$gui_access = $db_user_groups[$user_group['usrgrpid']]['gui_access'];
					}
				}
			}
			elseif ($db_users !== null) {
				foreach ($db_users[$user['userid']]['usrgrps'] as $db_user_group) {
					if ($db_user_group['gui_access'] > $gui_access) {
						$gui_access = $db_user_group['gui_access'];
					}
				}
			}

			if ($gui_access != GROUP_GUI_ACCESS_DISABLED
					&& self::getAuthTypeByGuiAccess($gui_access) == ZBX_AUTH_INTERNAL) {
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
	 * Check if 'mediatypeid' parameter of the given users with medias is valid.
	 *
	 * @param array      $users
	 * @param array|null $db_media_types
	 *
	 * @throws APIException
	 */
	private static function checkMediaTypes(array $users, array &$db_media_types = null): void {
		$media_indexes = [];

		foreach ($users as $i1 => &$user) {
			if (!array_key_exists('medias', $user)) {
				continue;
			}

			foreach ($user['medias'] as $i2 => &$media) {
				$media_indexes[$media['mediatypeid']][$i1][] = $i2;
			}
			unset($media);
		}
		unset($user);

		if (!$media_indexes) {
			return;
		}

		$db_media_types = DB::select('media_type', [
			'output' => ['type'],
			'mediatypeids' => array_keys($media_indexes),
			'preservekeys' => true
		]);

		foreach ($media_indexes as $mediatypeid => $indexes) {
			if (!array_key_exists($mediatypeid, $db_media_types)) {
				$i1 = key($indexes);
				$i2 = reset($indexes[$i1]);

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i1 + 1).'/medias/'.($i2 + 1).'/mediatypeid', _('object does not exist')
				));
			}
		}
	}

	/**
	 * Check if 'sendto' parameter value of the given users with medias is valid.
	 *
	 * @param array      $users
	 * @param array|null $db_media_types
	 *
	 * @throws APIException
	 */
	private static function checkMediaRecipients(array $users, ?array $db_media_types): void {
		if (!$db_media_types) {
			return;
		}

		$email_validator = new CEmailValidator();
		$length = DB::getFieldLength('media', 'sendto');

		foreach ($users as $i1 => $user) {
			if (!array_key_exists('medias', $user)) {
				continue;
			}

			foreach ($user['medias'] as $i2 => $media) {
				if (!array_key_exists('sendto', $media)) {
					continue;
				}

				if ($db_media_types[$media['mediatypeid']]['type'] != MEDIA_TYPE_EMAIL && count($media['sendto']) > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i1 + 1).'/medias/'.($i2 + 1).'/sendto', _('a character string is expected')
					));
				}

				if ($db_media_types[$media['mediatypeid']]['type'] == MEDIA_TYPE_EMAIL) {
					foreach ($media['sendto'] as $i3 => $email) {
						if ($email === '') {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i1 + 1).'/medias/'.($i2 + 1).'/sendto/'.($i3 + 1), _('cannot be empty')
							));
						}

						if (!$email_validator->validate($email)) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
								'/'.($i1 + 1).'/medias/'.($i2 + 1).'/sendto/'.($i3 + 1),
								_('an email address is expected')
							));
						}
					}
				}

				if (mb_strlen(implode("\n", $media['sendto'])) > $length) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i1 + 1).'/medias/'.($i2 + 1).'/sendto', _('value is too long')
					));
				}
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
	 * @param array|null $db_users
	 *
	 * @throws APIException
	 */
	private static function checkRoles(array $users, array $db_roles, array $db_users = null): void {
		foreach ($users as $i => $user) {
			if (!array_key_exists('roleid', $user)) {
				continue;
			}

			if ($db_users !== null && $db_users[$user['userid']]['userdirectoryid'] != 0
					&& bccomp($user['roleid'], $db_users[$user['userid']]['roleid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_s('cannot update readonly parameter "%1$s" of provisioned user', 'roleid')
				));
			}

			if ($user['roleid'] != 0 && !array_key_exists($user['roleid'], $db_roles)) {
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
				if (array_key_exists('sendto', $media)) {
					$media['sendto'] = implode("\n", $media['sendto']);
				}

				if (array_key_exists('mediaid', $media)) {
					$db_media = $db_medias[$media['mediaid']];
					$upd_media = DB::getUpdatedValues('media', $media, $db_media);

					if ($upd_media) {
						$upd_medias[] = [
							'values' => $upd_media,
							'where' => ['mediaid' => $db_media['mediaid']]
						];
					}

					unset($db_medias[$media['mediaid']]);
				}
				else {
					$ins_medias[] = ['userid' => $user['userid']] + $media;
				}
			}
			unset($media);

			$del_mediaids = array_merge($del_mediaids, array_keys($db_medias));
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
				self::exception(ZBX_API_ERROR_PARAMETERS, _('User is not allowed to delete oneself.'));
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
		self::createSession($db_user, $db_user['mfaid'] == 0 ? ZBX_SESSION_ACTIVE : ZBX_SESSION_CONFIRMATION_REQUIRED);
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
		self::createSession($db_user, ZBX_SESSION_ACTIVE);
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

		return CAuthenticationHelper::getPublic(CAuthenticationHelper::AUTHENTICATION_TYPE);
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

		$deprovision_groupid = CAuthenticationHelper::getPublic(CAuthenticationHelper::DISABLED_USER_GROUPID);
		$mfa_status = CAuthenticationHelper::getPublic(CAuthenticationHelper::MFA_STATUS);
		$default_mfaid = CAuthenticationHelper::getPublic(CAuthenticationHelper::MFAID);

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

				if (CAuthenticationHelper::getPublic(CAuthenticationHelper::LDAP_USERDIRECTORYID) != 0) {
					$userdirectoryids[CAuthenticationHelper::getPublic(CAuthenticationHelper::LDAP_USERDIRECTORYID)] =
						true;
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

		/*
		 * The default MFA has the highest priority.
		 * If user's groups only have exact MFA IDs, we select first of them by name.
		 */
		if ($mfa_status == MFA_ENABLED && $db_user['mfaid'] == 0 && $mfaids) {
			$db_mfas = DB::select('mfa', [
				'output' => [],
				'mfaids' => array_keys($mfaids),
				'sortfield' => ['name'],
				'limit' => 1,
				'preservekeys' => true
			]);

			$db_user['mfaid'] = key($db_mfas);
		}
	}

	private static function checkLoginTemporarilyBlocked(array $db_user): void {
		if ($db_user['attempt_failed'] < CSettingsHelper::getPublic(CSettingsHelper::LOGIN_ATTEMPTS)) {
			return;
		}

		$blocked_duration = time() - $db_user['attempt_clock'];

		if ($blocked_duration < timeUnitToSeconds(CSettingsHelper::getPublic(CSettingsHelper::LOGIN_BLOCK))) {
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
			}

			self::addUserGroupFields($db_user, $group_status);
			$db_user['auth_type'] = ZBX_AUTH_LDAP;
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

	private static function increaseFailedLoginAttempts(array &$db_user): void {
		$attempt_failed = $db_user['attempt_failed'] + 1;

		$upd_user = [
			'attempt_failed' => $attempt_failed,
			'attempt_clock' => time(),
			'attempt_ip' => substr(CWebUser::getIp(), 0, 39)
		];

		DB::update('users', [
			'values' => $upd_user,
			'where' => ['userid' => $db_user['userid']]
		]);

		$users = [['userid' => $db_user['userid'], 'attempt_failed' => $attempt_failed]];
		$db_users = [$db_user['userid'] => $db_user];

		self::addAuditLogByUser($db_user['userid'], CWebUser::getIp(), $db_user['username'],
			CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER, $users, $db_users
		);

		$db_user = $upd_user + $db_user;
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
			$db_user['lang'] = CSettingsHelper::getPublic(CSettingsHelper::DEFAULT_LANG);
		}

		if ($db_user['timezone'] === TIMEZONE_DEFAULT) {
			$db_user['timezone'] = CSettingsHelper::getPublic(CSettingsHelper::DEFAULT_TIMEZONE);
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

	private static function createSession(array &$db_user, int $session_status): void {
		$db_user['sessionid'] = CEncryptHelper::generateKey();
		$db_user['secret'] = CEncryptHelper::generateKey();

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

		$auth_method = $session['sessionid'] !== null ? 'sessionid' : 'token';

		// Access DB only once per page load.
		if (self::$userData !== null && array_key_exists($auth_method, self::$userData)
				&& self::$userData[$auth_method] === $session[$auth_method]) {
			return array_diff_key(self::$userData, array_flip(['token']));
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
			$idp_medias = $this->sanitizeUserMedia($user['medias']);
			$idp_medias = array_column($idp_medias, null, 'userdirectory_mediaid');
			$db_users[$userid]['medias'] = DB::select('media', [
				'output' => ['mediatypeid', 'mediaid', 'sendto', 'userdirectory_mediaid'],
				'filter' => ['userid' => $userid],
				'preservekeys' => true
			]);
			$users[$userid]['medias'] = [];

			foreach ($db_users[$userid]['medias'] as $db_media) {
				if ($db_media['userdirectory_mediaid'] == 0) {
					$users[$userid]['medias'][] = ['mediaid' => $db_media['mediaid']];
				}
				else if (array_key_exists($db_media['userdirectory_mediaid'], $idp_medias)) {
					$users[$userid]['medias'][] = [
						'mediatypeid' => $idp_medias[$db_media['userdirectory_mediaid']]['mediatypeid'],
						'sendto' => $idp_medias[$db_media['userdirectory_mediaid']]['sendto']
					] + $db_media;

					unset($idp_medias[$db_media['userdirectory_mediaid']]);
				}
			}

			$users[$userid]['medias'] = array_merge($users[$userid]['medias'], $idp_medias);
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

		$this->addRelatedRole($options, $result);

		return $result;
	}

	private function addRelatedRole(array $options, array &$result): void {
		if ($options['selectRole'] === null) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'userid', 'roleid');

		$db_roles = API::Role()->get([
			'output' => $options['selectRole'] === API_OUTPUT_EXTEND
				? CRole::OUTPUT_FIELDS
				: $options['selectRole'],
			'roleids' => $relation_map->getRelatedIds(),
			'preservekeys' => true
		]);

		$result = $relation_map->mapOne($result, $db_roles, 'role');
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
	 * @param array  $medias
	 * @param string $medias[]['name']
	 * @param string $medias[]['mediatypeid']
	 * @param array  $medias[]['sendto']
	 * @param string $medias[]['active']
	 * @param string $medias[]['severity']
	 * @param string $medias[]['period']
	 * @param string $medias[]['userdirectory_mediaid']
	 *
	 * @return array
	 */
	protected function sanitizeUserMedia(array $medias): array {
		if (!$medias) {
			return $medias;
		}

		$email_mediatypeids = [];
		$mediatypeids = array_unique(array_column($medias, 'mediatypeid'));

		if ($mediatypeids) {
			$email_mediatypeids = DB::select('media_type', [
				'output' => [],
				'filter' => ['type' => MEDIA_TYPE_EMAIL],
				'mediatypeids' => $mediatypeids,
				'preservekeys' => true
			]);
		}

		$user_medias = [];

		$email_validator = new CEmailValidator();
		$max_length = DB::getFieldLength('media', 'sendto');
		$fields = array_flip(['mediatypeid', 'sendto', 'active', 'severity', 'period', 'userdirectory_mediaid']);

		foreach ($medias as $media) {
			$sendto = array_filter($media['sendto'], 'strlen');

			if (array_key_exists($media['mediatypeid'], $email_mediatypeids)) {
				$sendto = array_filter($media['sendto'], [$email_validator, 'validate']);

				while (mb_strlen(implode("\n", $sendto)) > $max_length && count($sendto) > 0) {
					array_pop($sendto);
				}
			}

			if ($sendto) {
				$media['sendto'] = $sendto;
				$user_medias[] = array_intersect_key($media, $fields);
			}
		}

		return $user_medias;
	}

	/**
	 * Returns data necessary for user.confirm method.
	 *
	 * @param array  $session_data
	 *
	 * @return array  data['mfa']
	 * @return string data['userid]
	 * @return string data['totp_secret']  If MFA_TYPE_TOTP and user has no totp_secret.
	 * @return string data['qr_code_url']  If MFA_TYPE_TOTP and user has no totp_secret.
	 * @return string data['username']     If MFA_TYPE_DUO.
	 * @return string data['state']        If MFA_TYPE_DUO.
	 * @return string data['prompt_uri']   If MFA_TYPE_DUO.
	 */
	public static function getConfirmData(array $session_data): array {
		$db_sessions = DB::select('sessions', [
			'output' => ['userid'],
			'sessionids' => $session_data['sessionid']
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You must login to view this page.'));
		}

		$db_user = DB::select('users', [
			'output' => ['userid', 'userdirectoryid', 'username'],
			'userids' => $db_sessions[0]['userid']
		])[0];

		self::addUserGroupFields($db_user, $group_status);
		self::checkGroupStatus($db_user, $group_status);

		if ($db_user['mfaid'] == 0) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You must login to view this page.'));
		}

		$db_mfas = DB::select('mfa', [
			'output' => ['mfaid', 'type', 'name', 'hash_function', 'code_length', 'api_hostname', 'clientid',
				'client_secret'
			],
			'mfaids' => $db_user['mfaid']
		]);
		$mfa = $db_mfas[0];

		$data = [
			'sessionid' => $session_data['sessionid'],
			'mfa' => $mfa,
			'userid' => $db_user['userid']
		];

		if ($mfa['type'] == MFA_TYPE_TOTP) {
			$user_totp_secret = DB::select('mfa_totp_secret', [
				'output' => ['mfa_totp_secretid', 'totp_secret', 'status'],
				'filter' => ['mfaid' => $data['mfa']['mfaid'], 'userid' => $db_user['userid']]
			]);

			// Delete previously saved totp_secret for this specific user which are not related to current MFA method.
			DBexecute(
				'DELETE FROM mfa_totp_secret'.
					' WHERE '.dbConditionId('userid', [$db_user['userid']]).
						' AND '.dbConditionId('mfaid', [$mfa['mfaid']], true)
			);

			if (!$user_totp_secret || $user_totp_secret[0]['status'] == TOTP_SECRET_CONFIRMATION_REQUIRED) {
				$totp_generator = self::createTotpGenerator($data['mfa']);
				$data['totp_secret'] = $totp_generator->generateSecretKey(TOTP_SECRET_LENGTH_32);
				$data['qr_code_url'] = $totp_generator->getQRCodeUrl($data['mfa']['name'], $db_user['username'],
					$data['totp_secret']
				);

				if (!$user_totp_secret) {
					DB::insert('mfa_totp_secret', [[
						'mfaid' => $data['mfa']['mfaid'],
						'userid' => $data['userid'],
						'totp_secret' => $data['totp_secret'],
						'status' => TOTP_SECRET_CONFIRMATION_REQUIRED
					]]);
				}
				else {
					DB::update('mfa_totp_secret', [
						'values' => ['totp_secret' => $data['totp_secret']],
						'where' => ['mfa_totp_secretid' => $user_totp_secret[0]['mfa_totp_secretid']]
					]);
				}
			}
		}

		if ($mfa['type'] == MFA_TYPE_DUO) {
			try {
				$duo_client = new Client($data['mfa']['clientid'], $data['mfa']['client_secret'],
					$data['mfa']['api_hostname'], $session_data['redirect_uri']
				);

				$duo_client->healthCheck();
			}
			catch (DuoException $e) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					'Verify the values in Duo Universal Prompt MFA method are correct.'. $e->getMessage()
				);
			}

			$data['username'] = $db_user['username'];
			$data['state'] = $duo_client->generateState();
			$data['prompt_uri'] = $duo_client->createAuthUrl($data['username'], $data['state']);
		}

		return $data;
	}

	/**
	 * Check MFA method authentication for the user based on provided data.
	 * Returns 'sessionid' and 'mfa' object, in case MFA authentication was successful.
	 *
	 * @param array  $data
	 * @param string $data['sessionid']                               User's sessionid passed in session data.
	 * @param string $data['redirect_uri']                            Redirect uri that will be used for Duo MFA.
	 * @param array  $data['mfa_response_data']                       Array with data for MFA response confirmation.
	 * @param string $data['mfa_response_data']['verification_code']  TOTP MFA verification code.
	 * @param string $data['mfa_response_data']['totp_secret']        TOTP MFA secret at initial registration.
	 * @param string $data['mfa_response_data']['duo_code']           DUO MFA response code.
	 * @param string $data['mfa_response_data']['duo_state']          DUO MFA response state.
	 * @param string $data['mfa_response_data']['state']              DUO MFA state from session.
	 * @param string $data['mfa_response_data']['username']           DUO MFA username from session.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public static function confirm(array $data): array {
		$db_sessions = DB::select('sessions', [
			'output' => ['userid'],
			'sessionids' => $data['sessionid']
		]);

		if (!$db_sessions) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You must login to view this page.'));
		}

		$db_user = DB::select('users', [
			'output' => ['userid', 'userdirectoryid', 'username', 'attempt_failed', 'attempt_clock'],
			'userids' => $db_sessions[0]['userid']
		])[0];

		self::addUserGroupFields($db_user, $group_status);
		self::checkGroupStatus($db_user, $group_status);

		if ($db_user['mfaid'] == 0) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You must login to view this page.'));
		}

		$db_mfas = DB::select('mfa', [
			'output' => ['mfaid', 'type', 'name', 'hash_function', 'code_length', 'api_hostname', 'clientid',
				'client_secret'
			],
			'mfaids' => $db_user['mfaid']
		]);
		$mfa = $db_mfas[0];

		$mfa_response = $data['mfa_response_data'];

		if ($mfa['type'] == MFA_TYPE_TOTP) {
			$enrollment_filter = $mfa_response['totp_secret'] != null
				? ['totp_secret' => $mfa_response['totp_secret'], 'status' => TOTP_SECRET_CONFIRMATION_REQUIRED]
				: [];

			$db_user_secrets = DB::select('mfa_totp_secret', [
				'output' => ['mfa_totp_secretid', 'totp_secret', 'status', 'used_codes'],
				'filter' => ['mfaid' => $mfa['mfaid'], 'userid' => $db_user['userid']] + $enrollment_filter
			]);

			if (!$db_user_secrets) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('You must login to view this page.'));
			}

			$db_user_secret = $db_user_secrets[0];
			$used_codes = explode(',', $db_user_secret['used_codes']);

			$valid_code = (self::createTotpGenerator($mfa))
				->verifyKey($db_user_secret['totp_secret'], $mfa_response['verification_code']);

			if ($valid_code) {
				$valid_code = !array_key_exists($mfa_response['verification_code'], array_flip($used_codes));
			}

			if ($valid_code) {
				$used_codes = array_slice(
					array_merge($used_codes, [$mfa_response['verification_code']]), -TOTP_MAX_USED_CODES
				);

				$upd_totp_secret = [
					'values' => ['used_codes' => implode(',', $used_codes)],
					'where' => ['mfa_totp_secretid' => $db_user_secret['mfa_totp_secretid']]
				];

				if ($mfa_response['totp_secret'] != null) {
					$upd_totp_secret['values']['status'] = TOTP_SECRET_CONFIRMED;
				}

				DB::update('mfa_totp_secret', [$upd_totp_secret]);
			}
			else {
				self::increaseFailedLoginAttempts($db_user);

				try {
					self::checkLoginTemporarilyBlocked($db_user);
				}
				catch (Exception $e) {
					DB::delete('sessions', ['sessionid' => $data['sessionid']]);

					throw $e;
				}

				self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PARAMETERS,
					_('The verification code was incorrect, please try again.')
				);
			}
		}

		if ($mfa['type'] == MFA_TYPE_DUO) {
			if (!array_key_exists('state', $mfa_response) || !array_key_exists('username', $mfa_response)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No saved state, please login again.'));
			}

			if ($mfa_response['duo_state'] != $mfa_response['state']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('Duo state does not match saved state.'));
			}

			try {
				$duo_client = new Client($mfa['clientid'], $mfa['client_secret'],
					$mfa['api_hostname'], $data['redirect_uri']
				);

				$duo_client->exchangeAuthorizationCodeFor2FAResult($mfa_response['duo_code'],
					$mfa_response['username']
				);
			} catch (DuoException $e) {
				self::loginException($db_user['userid'], $db_user['username'], ZBX_API_ERROR_PERMISSIONS,
					_('Error decoding Duo result.')
				);
			}
		}

		DB::update('sessions', [
			'values' => ['status' => ZBX_SESSION_ACTIVE],
			'where' => ['sessionid' => $data['sessionid']]
		]);

		$outdated = strtotime('-5 minutes');

		DBexecute(
			'DELETE FROM sessions'.
				' WHERE '.dbConditionId('userid', [$db_user['userid']]).
					' AND '.dbConditionInt('status', [ZBX_SESSION_CONFIRMATION_REQUIRED]).
					' AND lastaccess<'.zbx_dbstr($outdated)
		);

		self::$userData = $db_user + ['userip' => CWebUser::getIp()];

		self::resetFailedLoginAttempts($db_user);
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

		switch ($data['code_length']) {
			case TOTP_CODE_LENGTH_6:
				$totp_generator->setOneTimePasswordLength(TOTP_CODE_LENGTH_6);
				break;

			case TOTP_CODE_LENGTH_8:
				$totp_generator->setOneTimePasswordLength(TOTP_CODE_LENGTH_8);
				break;
		}

		return $totp_generator;
	}

	public static function terminateActiveSessions(array $userids): void {
		DB::update('sessions', [
			'values' => ['status' => ZBX_SESSION_PASSIVE],
			'where' => ['userid' => $userids]
		]);
	}

	/**
	 * Reset TOTP secret of provided users and terminate active session.
	 *
	 * @param array $userids
	 */
	public function resetTotp(array $userids): array {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $userids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_users_secrets = DB::select('mfa_totp_secret', [
			'output' => ['userid'],
			'filter' => ['userid' => $userids],
			'preservekeys' => true
		]);

		if ($db_users_secrets) {
			DB::delete('mfa_totp_secret', ['mfa_totp_secretid' => array_keys($db_users_secrets)]);

			self::terminateActiveSessions(array_filter($userids,
				static fn (string $userid): bool => bccomp($userid, self::$userData['userid']) != 0
			));
		}

		return ['userids' => $userids];
	}
}
