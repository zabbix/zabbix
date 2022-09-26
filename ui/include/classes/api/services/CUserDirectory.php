<?php declare(strict_types = 0);
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
	protected $output_fields = ['userdirectoryid', 'name', 'description', 'host', 'port', 'base_dn', 'bind_dn',
		'search_attribute', 'start_tls', 'search_filter'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'userdirectoryids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['userdirectoryid', 'host', 'name']],
			'search' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['base_dn', 'bind_dn', 'description', 'host', 'name', 'search_attribute', 'search_filter']],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		$db_userdirectories = [];

		$sql = $this->createSelectQuery($this->tableName, $options);
		$resource = DBselect($sql, $options['limit']);

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_userdirectories[$row['userdirectoryid']] = $row;
		}

		if ($db_userdirectories) {
			$db_userdirectories = $this->addRelatedObjects($options, $db_userdirectories);
			$db_userdirectories = $this->unsetExtraFields($db_userdirectories, ['userdirectoryid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_userdirectories = array_values($db_userdirectories);
			}
		}

		return $db_userdirectories;
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedUserGroups($options, $result);

		return $result;
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedUserGroups(array $options, array &$result): void {
		if ($options['selectUsrgrps'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['usrgrps'] = [];
		}
		unset($row);

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			$output = ['userdirectoryid'];
		}
		elseif ($options['selectUsrgrps'] === API_OUTPUT_EXTEND) {
			$output = ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid'];
		}
		else {
			$output = array_unique(array_merge(['userdirectoryid'], $options['selectUsrgrps']));
		}

		$db_usergroups = API::UserGroup()->get([
			'output' => $output,
			'filter' => ['userdirectoryid' => array_keys($result)]
		]);

		foreach ($db_usergroups as $db_usergroup) {
			$result[$db_usergroup['userdirectoryid']]['usrgrps'][] =
				array_diff_key($db_usergroup, array_flip(['userdirectoryid']));
		}

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['usrgrps'] = (string) count($row['usrgrps']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $userdirectories): array {
		self::validateCreate($userdirectories);

		$db_count = DB::select('userdirectory', ['countOutput' => true]);
		$userdirectoryids = DB::insert('userdirectory', $userdirectories);

		foreach ($userdirectories as $index => &$userdirectory) {
			$userdirectory['userdirectoryid'] = $userdirectoryids[$index];
		}
		unset($userdirectory);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USERDIRECTORY, $userdirectories);

		if (!$db_count) {
			API::Authentication()->update(['ldap_userdirectoryid' => reset($userdirectoryids)]);
		}

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$userdirectories): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description')],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn')],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($userdirectories);
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $userdirectories): array {
		self::validateUpdate($userdirectories, $db_userdirectories);

		$upd_userdirectories = [];

		foreach ($userdirectories as $userdirectory) {
			$upd_userdirectory = DB::getUpdatedValues('userdirectory', $userdirectory,
				$db_userdirectories[$userdirectory['userdirectoryid']]
			);

			if ($upd_userdirectory) {
				$upd_userdirectories[] = [
					'values' => $upd_userdirectory,
					'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
				];
			}
		}

		if ($upd_userdirectories) {
			DB::update('userdirectory', $upd_userdirectories);

			self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USERDIRECTORY, $userdirectories,
				$db_userdirectories
			);
		}

		return ['userdirectoryids' => array_column($userdirectories, 'userdirectoryid')];
	}

	/**
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateUpdate(array &$userdirectories, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['userdirectoryid'], ['name']], 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description')],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn')],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = DB::select('userdirectory', [
			'output' => ['userdirectoryid', 'name', 'description', 'host', 'port', 'base_dn', 'bind_dn',
				'bind_password', 'search_attribute', 'start_tls', 'search_filter'
			],
			'userdirectoryids' => array_column($userdirectories, 'userdirectoryid'),
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectories)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($userdirectories, $db_userdirectories);
	}

	/**
	 * Check for unique names.
	 *
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException if userdirectory name is not unique.
	 */
	private static function checkDuplicates(array $userdirectories, array $db_userdirectories = null): void {
		$names = [];

		foreach ($userdirectories as $userdirectory) {
			if (!array_key_exists('name', $userdirectory)) {
				continue;
			}

			if ($db_userdirectories === null
					|| $userdirectory['name'] !== $db_userdirectories[$userdirectory['userdirectoryid']]['name']) {
				$names[] = $userdirectory['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('userdirectory', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User directory "%1$s" already exists.',
				$duplicates[0]['name'])
			);
		}
	}

	/**
	 * @param array $userdirectoryids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $userdirectoryids): array {
		self::validateDelete($userdirectoryids, $db_userdirectories);

		DB::delete('userdirectory', ['userdirectoryid' => $userdirectoryids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_USERDIRECTORY, $db_userdirectories);

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array      $userdirectoryids
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateDelete(array $userdirectoryids, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectoryids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = API::UserDirectory()->get([
			'output' => ['userdirectoryid', 'name'],
			'userdirectoryids' => $userdirectoryids,
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectoryids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$userdirectories_left = API::UserDirectory()->get(['countOutput' => true]) - count($userdirectoryids);
		$auth = API::Authentication()->get([
			'output' => ['ldap_userdirectoryid', 'authentication_type', 'ldap_configured']
		]);

		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)
				&& ($auth['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED || $userdirectories_left > 0)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default user directory.'));
		}

		$db_groups = API::UserGroup()->get([
			'output' => ['userdirectoryid'],
			'filter' => [
				'gui_access' => [GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_SYSTEM],
				'userdirectoryid' => $userdirectoryids
			],
			'limit' => 1
		]);

		if ($db_groups) {
			$db_group = reset($db_groups);

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete user directory "%1$s".',
				$db_userdirectories[$db_group['userdirectoryid']]['name'])
			);
		}

		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)) {
			// If last (default) is removed, reset default userdirectoryid to prevent from foreign key constraint.
			API::Authentication()->update(['ldap_userdirectoryid' => 0]);
		}
	}

	/**
	 * Test user against specific userdirectory connection.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 *
	 * @return bool
	 */
	public function test(array $userdirectory): bool {
		self::validateTest($userdirectory);

		$user = [
			'username' => $userdirectory['test_username'],
			'password' => $userdirectory['test_password']
		];
		$ldap_validator = new CLdapAuthValidator(['conf' => $userdirectory]);

		if (!$ldap_validator->validate($user)) {
			self::exception(
				$ldap_validator->isConnectionError() ? ZBX_API_ERROR_PARAMETERS : ZBX_API_ERROR_PERMISSIONS,
				$ldap_validator->getError()
			);
		}

		return true;
	}

	/**
	 * Validate user directory and test user credentials to be used for testing.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 */
	protected static function validateTest(array &$userdirectory): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'default' => 0],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn'), 'default' => ''],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON, 'default' => ZBX_AUTH_START_TLS_OFF],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter'), 'default' => ''],
			'test_username' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'test_password' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectory, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($userdirectory['userdirectoryid'] != 0) {
			$db_userdirectory = DB::select('userdirectory', [
				'output' => ['host', 'port', 'base_dn', 'bind_dn', 'bind_password', 'search_attribute', 'start_tls',
					'search_filter'
				],
				'userdirectoryids' => $userdirectory['userdirectoryid']
			]);
			$db_userdirectory = reset($db_userdirectory);

			if (!$db_userdirectory) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$userdirectory += $db_userdirectory;
		}
	}
}
