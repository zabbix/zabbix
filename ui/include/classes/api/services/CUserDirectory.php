<?php declare(strict_types = 1);
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


class CUserDirectory extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'test' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'userdirectory';
	protected $tableAlias = 'ud';
	protected $sortColumns = ['host', 'name'];

	/**
	 * @var array
	 */
	protected $output_fields = ['userdirectoryid', 'base_dn', 'bind_dn', 'description', 'host', 'name', 'port',
		'search_attribute', 'search_filter', 'start_tls'
	];

	/**
	 * Get list of user directories.
	 *
	 * @param array $options
	 *
	 * @return array|string
	 *
	 * @throws APIException
	 */
	public function get(array $options) {
		$usergroups_fields = array_keys($this->getTableSchema('usrgrp')['fields']);
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'userdirectoryids' => 			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'userdirectoryid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'host' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_fill_keys(
				['base_dn', 'bind_dn', 'description', 'host', 'name', 'search_attribute', 'search_filter'],
				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			)],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $usergroups_fields), 'default' => null],
			// sort and limit
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'sortfield' => 					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false],
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		$user_directories = [];
		$result = DBselect($this->createSelectQuery($this->tableName(), $options));

		if ($options['countOutput']) {
			$row = DBfetch($result);

			return (string) $row['rowscount'];
		}

		while ($row = DBfetch($result)) {
			$user_directories[$row['userdirectoryid']] = $row;
		}

		if ($user_directories) {
			$user_directories = $this->addRelatedObjects($options, $user_directories);
			$user_directories = $this->unsetExtraFields($user_directories, ['userdirectoryids'], $options['output']);

			if (!$options['preservekeys']) {
				$user_directories = array_values($user_directories);
			}
		}

		return $user_directories;
	}

	/**
	 * Create user directories.
	 *
	 * @param array $userdirectories
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function create(array $userdirectories) {
		static::validateCreate($userdirectories);

		$ids = DB::insert($this->tableName(), $userdirectories);

		foreach (array_keys($userdirectories) as $i) {
			$userdirectories[$i]['userdirectoryid'] = $ids[$i];
		}

		static::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USERDIRECTORY, $userdirectories);

		return ['userdirectoryids' => $ids];
	}

	/**
	 * Update user directories.
	 *
	 * @param array $userdirectories
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function update(array $userdirectories) {
		$update = [];
		$db_userdirectories = [];

		static::validateUpdate($userdirectories, $db_userdirectories);

		foreach ($userdirectories as $userdirectory) {
			$columns = DB::getUpdatedValues('userdirectory', $userdirectory,
				$db_userdirectories[$userdirectory['userdirectoryid']]
			);

			if ($columns) {
				$update[] = [
					'values' => $columns,
					'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
				];
			}
		}

		if ($update) {
			DB::update('userdirectory', $update);

			static::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USERDIRECTORY, $userdirectories,
				$db_userdirectories
			);
		}


		return ['userdirectoryids' => array_column($userdirectories, 'userdirectoryid')];
	}

	/**
	 * Delete user directories by userdirectoryid.
	 *
	 * @param array $userdirectoryids
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function delete(array $userdirectoryids) {
		static::validateDelete($userdirectoryids);

		DB::delete('userdirectory', ['userdirectoryid' => $userdirectoryids]);

		static::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_USERDIRECTORY, $userdirectoryids);

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * Test user against specific userdirectory connection.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 */
	public function test(array $userdirectory) {
		static::validateTest($userdirectory);

		$user = [
			'username' => $userdirectory['test_username'],
			'password' => $userdirectory['test_password']
		];
		$ldapValidator = new CLdapAuthValidator(['conf' => $userdirectory]);

		if (!$ldapValidator->validate($user)) {
			self::exception($ldapValidator->isConnectionError()
					? ZBX_API_ERROR_PARAMETERS
					: ZBX_API_ERROR_PERMISSIONS,
				$ldapValidator->getError()
			);
		}

		return true;
	}

	/**
	 * Add user groups data if requested.
	 *
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $userdirectories): array {
		$userdirectories = parent::addRelatedObjects($options, $userdirectories);

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			static::addUserGroupsCounts($options, $userdirectories);
		}
		else if ($options['selectUsrgrps'] !== null) {
			static::addUserGroups($options, $userdirectories);
		}

		return $userdirectories;
	}

	/**
	 * Add user groups details to $userdirectories array, passed by reference.
	 *
	 * @static
	 *
	 * @param array $options
	 * @param array $userdirectories
	 */
	protected static function addUserGroups(array $options, array &$userdirectories): void {
		$ids = array_unique(array_column($userdirectories, 'userdirectoryid'));
		$fields = ($options['selectUsrgrps'] === API_OUTPUT_EXTEND)
			? array_keys(DB::getSchema('usrgrp')['fields'])
			: $options['selectUsrgrps'];
		$keys = array_fill_keys($fields, '');

		if (!in_array('userdirectoryid', $fields)) {
			$fields[] = 'userdirectoryid';
		}

		foreach (array_keys($userdirectories) as $i) {
			$userdirectories[$i]['usrgrps'] = [];
		}

		$db_usergroups = API::UserGroup()->get([
			'output' => $fields,
			'filter' => ['userdirectoryid' => $ids]
		]);

		foreach ($db_usergroups as $db_usergroup) {
			$userdirectories[$db_usergroup['userdirectoryid']]['usrgrps'][] = array_intersect_key($db_usergroup, $keys);
		}
	}

	/**
	 * Add user groups count details to $userdirectories array, passed by reference.
	 *
	 * @static
	 *
	 * @param array $options
	 * @param array $userdirectories
	 */
	protected static function addUserGroupsCounts(array $options, array &$userdirectories): void {
		$ids = array_unique(array_column($userdirectories, 'userdirectoryid'));

		$db_usergroups = API::UserGroup()->get([
			'output' => ['userdirectoryid'],
			'filter' => ['userdirectoryid' => $ids]
		]);

		foreach (array_keys($userdirectories) as $i) {
			$userdirectories[$i]['usrgrps'] = 0;
		}

		foreach ($db_usergroups as $db_usergroup) {
			++$userdirectories[$db_usergroup['userdirectoryid']]['usrgrps'];
		}
	}

	/**
	 * Validate input data before create. Modify input data in $userdirectories.
	 *
	 * @static
	 *
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 */
	protected static function validateCreate(array &$userdirectories): void {
		$rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description'), 'default' => ''],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn'), 'default' => ''],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password'), 'default' => ''],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON, 'default' => ZBX_AUTH_START_TLS_OFF],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter'), 'default' => '']
		]];

		if (!CApiInputValidator::validate($rules, $userdirectories, '/', $error)) {
			static::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$names_sql = dbConditionString('name', array_column($userdirectories, 'name'));
		$duplicate = DBfetch(DBselect('SELECT name FROM userdirectory WHERE '.$names_sql, 1));

		if ($duplicate) {
			$subpath = '/'.(array_search($duplicate['name'], array_column($userdirectories, 'name')) + 1);
			$error = _s('Invalid parameter "%1$s": %2$s.', $subpath,
				_s('value %1$s already exists', '(name)=('.$duplicate['name'].')')
			);
			static::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Validate input data before update. Modify input data in $db_userdirectories.
	 *
	 * @static
	 *
	 * @param array $userdirectories
	 * @param array $db_userdirectories
	 *
	 * @throws APIException
	 */
	protected static function validateUpdate(array &$userdirectories, ?array &$db_userdirectories) {
		$rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['userdirectoryid'], ['name']], 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description')],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn')],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter')]
		]];

		if (!CApiInputValidator::validate($rules, $userdirectories, '/', $error)) {
			static::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$duplicates = DB::select('userdirectory', [
			'output' =>			['userdirectoryid', 'name'],
			'filter' =>			['name' => array_column($userdirectories, 'name')]
		]);
		$duplicates = array_column($duplicates, 'name', 'userdirectoryid');
		$duplicates = array_diff_key($duplicates, array_column($userdirectories, 'name', 'userdirectoryid'));

		if ($duplicates) {
			$name = reset($duplicates);
			$subpath = '/'.(array_search($name, array_column($userdirectories, 'name')) + 1);
			$error = _s('Invalid parameter "%1$s": %2$s.', $subpath,
				_s('value %1$s already exists', '(name)=('.$name.')')
			);
			static::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = DB::select('userdirectory', [
			'output' => array_keys($rules['fields']),
			'userdirectoryids' => array_column($userdirectories, 'userdirectoryid'),
			'preservekeys' => true
		]);
	}

	/**
	 * Validate user directory to be deleted.
	 *
	 * @static
	 *
	 * @param array $userdirectoryids
	 *
	 * @throws APIException
	 */
	protected static function validateDelete(array $userdirectoryids) {
		$rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($rules, $userdirectoryids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = API::UserDirectory()->get([
			'output' => ['name'],
			'userdirectoryids' => $userdirectoryids,
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectoryids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$auth = API::Authentication()->get([
			'output' => ['ldap_userdirectoryid', 'authentication_type', 'ldap_configured']
		]);

		if ($auth['authentication_type'] == ZBX_AUTH_LDAP
				&& in_array($auth['ldap_userdirectoryid'], $userdirectoryids)) {
			// Check there are no user groups with default user directory.
			$userdirectoryids[] = 0;
		}

		$db_groups = API::UserGroup()->get([
			'output' => ['userdirectoryid', 'name'],
			'filter' => [
				'gui_access' => ZBX_AUTH_LDAP,
				'userdirectoryid' => $userdirectoryids
			],
			'limit' => 1
		]);

		if (!$db_groups) {
			return;
		}

		$db_group = reset($db_groups);

		if ($db_group['userdirectoryid'] === $auth['ldap_userdirectoryid']) {
			$error = _('Cannot delete default user directory "%1$s".',
				$db_userdirectories[$auth['ldap_userdirectoryid']]['name']
			);
		}
		else {
			$error = _('Cannot delete user directory "%1$s".', $db_group['name']);
		}

		static::exception(ZBX_API_ERROR_PARAMETERS, $error);
	}

	/**
	 * Validate user directory and test user credentials to be used for testing.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 */
	protected static function validateTest(array &$userdirectory) {
		$rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'default' => null],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn'), 'default' => ''],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON, 'default' => ZBX_AUTH_START_TLS_OFF],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter'), 'default' => ''],
			'test_username' => 		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'test_password' => 		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($rules, $userdirectories, '/', $error)) {
			static::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($userdirectory['userdirectoryid'] !== null) {
			$db_userdirectory = DB::select('userdirectory', [
				'output' => [
					'host', 'port', 'base_dn', 'bind_dn', 'bind_password', 'search_attribute', 'start_tls',
					'search_filter'
				],
				'userdirectoryids' => $userdirectory['userdirectoryid']
			]);
			$db_userdirectory = reset($db_userdirectory);

			if (!$db_userdirectory) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			$userdirectory += $db_userdirectory;
		}
	}
}
