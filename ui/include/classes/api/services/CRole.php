<?php declare(strict_types = 0);
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


/**
 * User roles API implementation.
 */
class CRole extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'role';
	protected $tableAlias = 'r';
	protected $sortColumns = ['roleid', 'name'];

	public const OUTPUT_FIELDS = ['roleid', 'name', 'type', 'readonly'];

	/**
	 * Rule types.
	 */
	private const RULE_TYPE_INT32 = 0;
	private const RULE_TYPE_STR = 1;
	private const RULE_TYPE_MODULE = 2;
	private const RULE_TYPE_SERVICE = 3;

	/**
	 * Rule type correspondence to the database field names.
	 */
	public const RULE_TYPE_FIELDS = [
		self::RULE_TYPE_INT32 => 'value_int',
		self::RULE_TYPE_STR => 'value_str',
		self::RULE_TYPE_MODULE => 'value_moduleid',
		self::RULE_TYPE_SERVICE => 'value_serviceid'
	];

	/**
	 * @param array $options
	 *
	 * @return array|int
	 *
	 * @throws APIException
	 */
	public function get(array $options = []) {
		$user_output_fields = self::$userData['type'] == USER_TYPE_SUPER_ADMIN
			? CUser::OUTPUT_FIELDS
			: CUser::OWN_LIMITED_OUTPUT_FIELDS;

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'roleids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['roleid', 'name', 'type', 'readonly']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectRules' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['ui', 'ui.default_access', 'services.read.mode', 'services.read.list', 'services.read.tag', 'services.write.mode', 'services.write.list', 'services.write.tag', 'modules', 'modules.default_access', 'api.access', 'api.mode', 'api', 'actions', 'actions.default_access']), 'default' => null],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', $user_output_fields), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['editable'] && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return $options['countOutput'] ? '0' : [];
		}

		$db_roles = [];

		$sql = $this->createSelectQuery('role', $options);
		$resource = DBselect($sql, $options['limit']);

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_roles[$row['roleid']] = $row;
		}

		if ($db_roles) {
			$db_roles = $this->addRelatedObjects($options, $db_roles);
			$db_roles = $this->unsetExtraFields($db_roles, ['roleid', 'type'], $options['output']);

			if (!$options['preservekeys']) {
				$db_roles = array_values($db_roles);
			}
		}

		return $db_roles;
	}

	/**
	 * @param array $roles
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function create(array $roles): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'role', __FUNCTION__)
			);
		}

		$this->validateCreate($roles);

		$ins_roles = [];

		foreach ($roles as $role) {
			unset($role['rules']);
			$ins_roles[] = $role;
		}

		$roleids = DB::insert('role', $ins_roles);
		$roles = array_combine($roleids, $roles);

		$this->updateRules($roles);

		foreach ($roles as $roleid => &$role) {
			$role['roleid'] = $roleid;
		}
		unset($role);

		$this->addAuditBulk(CAudit::ACTION_ADD, CAudit::RESOURCE_USER_ROLE, $roles);

		return ['roleids' => $roleids];
	}

	/**
	 * @param array $roles
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$roles): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('role', 'name')],
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'rules' =>			['type' => API_OBJECT, 'default' => [], 'fields' => [
				'ui' =>						['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
				]],
				'ui.default_access' =>		['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED],
				'services.read.mode' =>		['type' => API_INT32, 'in' => ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM.','.ZBX_ROLE_RULE_SERVICES_ACCESS_ALL],
				'services.read.list' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'serviceid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
				]],
				'services.read.tag' =>		['type' => API_OBJECT, 'fields' => [
					'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str'), 'default' => '']
				]],
				'services.write.mode' =>	['type' => API_INT32, 'in' => ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM.','.ZBX_ROLE_RULE_SERVICES_ACCESS_ALL],
				'services.write.list' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'serviceid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
				]],
				'services.write.tag' =>		['type' => API_OBJECT, 'fields' => [
					'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str'), 'default' => '']
				]],
				'modules' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'moduleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
					'status' =>					['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
				]],
				'modules.default_access' =>	['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED],
				'api' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'uniq' => true],
				'api.access' =>				['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED],
				'api.mode' =>				['type' => API_INT32, 'in' => ZBX_ROLE_RULE_API_MODE_DENY.','.ZBX_ROLE_RULE_API_MODE_ALLOW],
				'actions' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
				]],
				'actions.default_access' =>	['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $roles, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates($roles);
		$this->checkRules($roles);
	}

	/**
	 * @param array $roles
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function update(array $roles): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'role', __FUNCTION__)
			);
		}

		$this->validateUpdate($roles, $db_roles);

		$upd_roles = [];

		foreach ($roles as $role) {
			$upd_role = DB::getUpdatedValues('role', $role, $db_roles[$role['roleid']]);

			if ($upd_role) {
				$upd_roles[] = [
					'values' => $upd_role,
					'where' => ['roleid' => $role['roleid']]
				];
			}
		}

		if ($upd_roles) {
			DB::update('role', $upd_roles);
		}

		$roles = array_column($roles, null, 'roleid');

		self::updateUserUgSets($roles, $db_roles);
		$this->updateRules($roles, $db_roles);

		$this->addAuditBulk(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USER_ROLE, $roles, $db_roles);

		return ['roleids' => array_column($roles, 'roleid')];
	}

	/**
	 * @param array      $roles
	 * @param array|null $db_roles
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$roles, ?array &$db_roles): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'roleid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('role', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'rules' =>			['type' => API_OBJECT, 'fields' => [
				'ui' =>						['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
				]],
				'ui.default_access' =>		['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED],
				'services.read.mode' =>		['type' => API_INT32, 'in' => ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM.','.ZBX_ROLE_RULE_SERVICES_ACCESS_ALL],
				'services.read.list' =>		['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'serviceid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
				]],
				'services.read.tag' =>		['type' => API_OBJECT, 'fields' => [
					'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str'), 'default' => '']
				]],
				'services.write.mode' =>	['type' => API_INT32, 'in' => ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM.','.ZBX_ROLE_RULE_SERVICES_ACCESS_ALL],
				'services.write.list' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'serviceid' =>				['type' => API_ID, 'flags' => API_REQUIRED]
				]],
				'services.write.tag' =>		['type' => API_OBJECT, 'fields' => [
					'tag' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'value' =>					['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str'), 'default' => '']
				]],
				'modules' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'moduleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
					'status' =>					['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
				]],
				'modules.default_access' =>	['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED],
				'api' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'uniq' => true],
				'api.access' =>				['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED],
				'api.mode' =>				['type' => API_INT32, 'in' => ZBX_ROLE_RULE_API_MODE_DENY.','.ZBX_ROLE_RULE_API_MODE_ALLOW],
				'actions' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED, 'default' => ZBX_ROLE_RULE_ENABLED]
				]],
				'actions.default_access' =>	['type' => API_INT32, 'in' => ZBX_ROLE_RULE_DISABLED.','.ZBX_ROLE_RULE_ENABLED]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $roles, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_roles = $this->get([
			'output' => ['roleid', 'name', 'type', 'readonly'],
			'roleids' => array_column($roles, 'roleid'),
			'selectRules' => ['ui', 'ui.default_access', 'services.read.mode', 'services.read.list',
				'services.read.tag', 'services.write.mode', 'services.write.list', 'services.write.tag', 'modules',
				'modules.default_access', 'api.access', 'api.mode', 'api', 'actions', 'actions.default_access'
			],
			'preservekeys' => true
		]);

		if (count($db_roles) != count($roles)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$this->checkDuplicates($roles, $db_roles);
		$this->checkRules($roles, $db_roles);
		$this->checkReadonly($db_roles);
		$this->checkOwnRoleType($roles);
	}

	/**
	 * @param array $roleids
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function delete(array $roleids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'role', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $roleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_roles = $this->get([
			'output' => ['roleid', 'name', 'readonly'],
			'selectUsers' => ['userid'],
			'roleids' => $roleids,
			'preservekeys' => true
		]);

		if (count($db_roles) != count($roleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($db_roles as $db_role) {
			if ($db_role['readonly'] == 1) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Cannot delete readonly user role "%1$s".', $db_role['name'])
				);
			}

			if ($db_role['users']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Cannot delete assigned user role "%1$s".', $db_role['name'])
				);
			}
		}

		DB::delete('role', ['roleid' => $roleids]);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_USER_ROLE, $db_roles);

		return ['roleids' => $roleids];
	}

	/**
	 * @param array      $roles
	 * @param array|null $db_roles
	 *
	 * @throws APIException
	 */
	private function checkDuplicates(array $roles, ?array $db_roles = null): void {
		$names = [];

		foreach ($roles as $role) {
			if ($db_roles === null
					|| (array_key_exists('name', $role) && $role['name'] !== $db_roles[$role['roleid']]['name'])) {
				$names[] = $role['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DB::select('role', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User role "%1$s" already exists.', $duplicate[0]['name']));
		}
	}

	/**
	 * Check user role rules.
	 *
	 * @param array      $roles
	 * @param array|null $db_roles
	 *
	 * @throws APIException if input is invalid.
	 */
	private function checkRules(array $roles, ?array $db_roles = null): void {
		foreach ($roles as $role) {
			if (!array_key_exists('rules', $role)) {
				continue;
			}

			$name = array_key_exists('name', $role) ? $role['name'] : $db_roles[$role['roleid']]['name'];
			$type = array_key_exists('type', $role) ? $role['type'] : $db_roles[$role['roleid']]['type'];

			$db_rules = $db_roles !== null ? $db_roles[$role['roleid']]['rules'] : null;

			self::checkUiRules($name, (int) $type, $role['rules'], $db_rules);
			$this->checkServicesRules($name, (int) $type, $role['rules'], $db_rules);
			$this->checkModulesRules($name, $role['rules']);
			self::checkApiRules($name, (int) $type, $role['rules']);
			$this->checkActionsRules($name, (int) $type, $role['rules']);
		}
	}

	/**
	 * @param string     $name
	 * @param int        $type
	 * @param array      $rules
	 * @param array|null $db_rules

	 * @throws APIException
	 */
	private static function checkUiRules(string $name, int $type, array $rules, ?array $db_rules = null): void {
		if (!array_key_exists('ui', $rules)) {
			return;
		}

		if (array_key_exists('ui.default_access', $rules)) {
			$default_access = $rules['ui.default_access'];
		}
		elseif ($db_rules !== null) {
			$default_access = $db_rules['ui.default_access'];
		}
		else {
			$default_access = ZBX_ROLE_RULE_ENABLED;
		}

		$ui_rules = [];
		$db_ui_rules = $db_rules !== null ? array_column($db_rules['ui'], 'status', 'name') : [];

		foreach (CRoleHelper::getUiElementsByUserType($type) as $ui_element) {
			$ui_rule_name = substr($ui_element, strlen('ui.'));
			$ui_rules[$ui_rule_name] = array_key_exists($ui_rule_name, $db_ui_rules)
				? $db_ui_rules[$ui_rule_name]
				: $default_access;
		}

		foreach ($rules['ui'] as $ui_rule) {
			if (!array_key_exists($ui_rule['name'], $ui_rules)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('UI element "%2$s" is not available for user role "%1$s".', $name, $ui_rule['name'])
				);
			}

			$ui_rules[$ui_rule['name']] = $ui_rule['status'];
		}

		if (!in_array(ZBX_ROLE_RULE_ENABLED, $ui_rules)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('At least one UI element must be enabled for user role "%1$s".', $name)
			);
		}
	}

	/**
	 * @param string     $name
	 * @param int        $type
	 * @param array      $rules
	 * @param array|null $db_rules
	 *
	 * @throws APIException
	 */
	private function checkServicesRules(string $name, int $type, array $rules, ?array $db_rules = null): void {
		$this->checkServicesReadRules($name, $rules, $db_rules);
		$this->checkServicesWriteRules($name, $type, $rules, $db_rules);

		$list = [];

		if (array_key_exists('services.read.list', $rules)) {
			$list = array_merge($list, $rules['services.read.list']);
		}
		elseif ($db_rules !== null) {
			$list = array_merge($list, $db_rules['services.read.list']);
		}

		if (array_key_exists('services.write.list', $rules)) {
			$list = array_merge($list, $rules['services.write.list']);
		}
		elseif ($db_rules !== null) {
			$list = array_merge($list, $db_rules['services.write.list']);
		}

		if (!$list) {
			return;
		}

		$serviceids = array_unique(array_column($list, 'serviceid'));

		$db_services = DB::select('services', [
			'output' => ['serviceid'],
			'serviceids' => $serviceids,
			'preservekeys' => true
		]);

		$unavailable_serviceids = array_diff($serviceids, array_keys($db_services));

		if ($unavailable_serviceids) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Service with ID "%2$s" is not available for user role "%1$s".', $name, $unavailable_serviceids[0])
			);
		}
	}

	/**
	 * @param string     $name
	 * @param array      $rules
	 * @param array|null $db_rules

	 * @throws APIException
	 */
	private function checkServicesReadRules(string $name, array $rules, ?array $db_rules = null): void {
		if (!array_key_exists('services.read.mode', $rules)
				&& !array_key_exists('services.read.list', $rules)
				&& !array_key_exists('services.read.tag', $rules)) {
			return;
		}

		if (array_key_exists('services.read.mode', $rules)) {
			$mode = $rules['services.read.mode'];
		}
		elseif ($db_rules !== null) {
			$mode = $db_rules['services.read.mode'];
		}
		else {
			$mode = ZBX_ROLE_RULE_SERVICES_ACCESS_ALL;
		}

		if ($mode == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
			if (array_key_exists('services.read.tag', $rules)) {
				if ($rules['services.read.tag']['tag'] === '' && $rules['services.read.tag']['value'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot have non-empty tag value while having empty tag in rule "%2$s" for user role "%1$s".',
						$name, 'services.read.tag'
					));
				}
			}

			return;
		}

		if (array_key_exists('services.read.list', $rules)) {
			$has_list = (bool) $rules['services.read.list'];
		}
		elseif ($db_rules !== null) {
			$has_list = (bool) $db_rules['services.read.list'];
		}
		else {
			$has_list = false;
		}

		if ($has_list) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Cannot have non-default "%2$s" rule while having "%3$s" set to %4$d for user role "%1$s".',
				$name, 'services.read.list', 'services.read.mode', ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
			));
		}

		if (array_key_exists('services.read.tag', $rules)) {
			$has_tag = $rules['services.read.tag']['tag'] !== '';
		}
		elseif ($db_rules !== null) {
			$has_tag = $db_rules['services.read.tag']['tag'] !== '';
		}
		else {
			$has_tag = false;
		}

		if ($has_tag) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Cannot have non-default "%2$s" rule while having "%3$s" set to %4$d for user role "%1$s".',
				$name, 'services.read.tag', 'services.read.mode', ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
			));
		}
	}

	/**
	 * @param string     $name
	 * @param int        $type
	 * @param array      $rules
	 * @param array|null $db_rules

	 * @throws APIException
	 */
	private function checkServicesWriteRules(string $name, int $type, array $rules, ?array $db_rules = null): void {
		if (!array_key_exists('services.write.mode', $rules)
				&& !array_key_exists('services.write.list', $rules)
				&& !array_key_exists('services.write.tag', $rules)) {
			return;
		}

		if (array_key_exists('services.write.mode', $rules)) {
			$mode = $rules['services.write.mode'];
		}
		elseif ($db_rules !== null) {
			$mode = $db_rules['services.write.mode'];
		}
		elseif (self::$userData['type'] == USER_TYPE_SUPER_ADMIN || self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
			$mode = ZBX_ROLE_RULE_SERVICES_ACCESS_ALL;
		}
		else {
			$mode = ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM;
		}

		if ($mode == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
			if (array_key_exists('services.write.tag', $rules)) {
				if ($rules['services.write.tag']['tag'] === '' && $rules['services.write.tag']['value'] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Cannot have non-empty tag value while having empty tag in rule "%2$s" for user role "%1$s".',
						$name, 'services.write.tag'
					));
				}
			}

			return;
		}

		if (array_key_exists('services.write.list', $rules)) {
			$has_list = (bool) $rules['services.write.list'];
		}
		elseif ($db_rules !== null) {
			$has_list = (bool) $db_rules['services.write.list'];
		}
		else {
			$has_list = false;
		}

		if ($has_list) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Cannot have non-default "%2$s" rule while having "%3$s" set to %4$d for user role "%1$s".',
				$name, 'services.write.list', 'services.write.mode', ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
			));
		}

		if (array_key_exists('services.write.tag', $rules)) {
			$has_tag = $rules['services.write.tag']['tag'] !== '';
		}
		elseif ($db_rules !== null) {
			$has_tag = $db_rules['services.write.tag']['tag'] !== '';
		}
		else {
			$has_tag = false;
		}

		if ($has_tag) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Cannot have non-default "%2$s" rule while having "%3$s" set to %4$d for user role "%1$s".',
				$name, 'services.write.tag', 'services.write.mode', ZBX_ROLE_RULE_SERVICES_ACCESS_ALL
			));
		}
	}

	/**
	 * @param string     $name
	 * @param array      $rules
	 *
	 * @throws APIException
	 */
	private function checkModulesRules(string $name, array $rules): void {
		if (!array_key_exists('modules', $rules)) {
			return;
		}

		$moduleids = [];

		foreach ($rules['modules'] as $module) {
			$moduleids[$module['moduleid']] = true;
		}

		if (!$moduleids) {
			return;
		}

		$unavailable_moduleids = array_diff(array_keys($moduleids), self::getModuleIds());

		if ($unavailable_moduleids) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Module with ID "%2$s" is not available for user role "%1$s".', $name, $unavailable_moduleids[0])
			);
		}
	}

	/**
	 * @param string $name
	 * @param int    $type
	 * @param array  $rules
	 *
	 * @throws APIException
	 */
	private static function checkApiRules(string $name, int $type, array $rules): void {
		if (!array_key_exists('api', $rules)) {
			return;
		}

		foreach ($rules['api'] as $rule) {
			if ($rule === ZBX_ROLE_RULE_API_WILDCARD || $rule === ZBX_ROLE_RULE_API_WILDCARD_ALIAS) {
				continue;
			}

			if (!in_array($rule, CRoleHelper::getApiMethodMasks($type), true)
					&& !in_array($rule, CRoleHelper::getApiMethods($type), true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid API method "%2$s" for user role "%1$s".', $name, $rule)
				);
			}
		}
	}

	/**
	 * @param string $name
	 * @param int    $type
	 * @param array  $rules
	 *
	 * @throws APIException
	 */
	private function checkActionsRules(string $name, int $type, array $rules): void {
		if (!array_key_exists('actions', $rules)) {
			return;
		}

		$all_actions = CRoleHelper::getActionsByUserType($type);

		foreach ($rules['actions'] as $rule) {
			if (!in_array('actions.'.$rule['name'], $all_actions)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Action "%2$s" is not available for user role "%1$s".', $name, $rule['name'])
				);
			}
		}
	}

	/**
	 * @param array $db_roles
	 *
	 * @throws APIException
	 */
	private function checkReadonly(array $db_roles): void {
		foreach ($db_roles as $db_role) {
			if ($db_role['readonly'] == 1) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot update readonly user role "%1$s".',
					$db_role['name']
				));
			}
		}
	}

	/**
	 * @param array $roles
	 *
	 * @throws APIException
	 */
	private function checkOwnRoleType(array $roles): void {
		$role_types = array_column($roles, 'type', 'roleid');

		if (array_key_exists(self::$userData['roleid'], $role_types)
				&& $role_types[self::$userData['roleid']] != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot change the user type of own role.'));
		}
	}

	private function updateUserUgSets(array $roles, array $db_roles): void {
		$role_indexes = [];

		foreach ($roles as $i => $role) {
			if (array_key_exists('type', $role) && $role['type'] != $db_roles[$role['roleid']]['type']
					&& ($role['type'] == USER_TYPE_SUPER_ADMIN
						|| $db_roles[$role['roleid']]['type'] == USER_TYPE_SUPER_ADMIN)) {
				$role_indexes[$role['roleid']] = $i;
			}
		}

		if (!$role_indexes) {
			return;
		}

		$options = [
			'output' => ['userid', 'username', 'roleid'],
			'filter' => ['roleid' => array_keys($role_indexes)]
		];
		$result = DBselect(DB::makeSql('users', $options));

		$users = [];
		$db_users = [];

		while ($row = DBfetch($result)) {
			$users[] = [
				'userid' => $row['userid'],
				'role_type' => $roles[$role_indexes[$row['roleid']]]['type']
			];

			$db_users[$row['userid']] = [
				'userid' => $row['userid'],
				'username' => $row['username'],
				'roleid' => $row['roleid'],
				'role_type' => $db_roles[$row['roleid']]['type']
			];
		}

		if ($users) {
			CUser::updateFromRole($users, $db_users);
		}
	}

	/**
	 * @param array      $roles
	 * @param array|null $db_roles
	 *
	 * @throws APIException
	 */
	private function updateRules(array $roles, ?array $db_roles = null): void {
		$default_rules = [
			'ui' => [],
			'ui.default_access' => ZBX_ROLE_RULE_ENABLED,
			'services.read.mode' => ZBX_ROLE_RULE_SERVICES_ACCESS_ALL,
			'services.read.list' => [],
			'services.read.tag' => ['tag' => '', 'value' => ''],
			'services.write.mode' => ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM,
			'services.write.list' => [],
			'services.write.tag' => ['tag' => '', 'value' => ''],
			'modules' => [],
			'modules.default_access' => ZBX_ROLE_RULE_ENABLED,
			'api' => [],
			'api.access' => ZBX_ROLE_RULE_ENABLED,
			'api.mode' => ZBX_ROLE_RULE_API_MODE_DENY,
			'actions' => [],
			'actions.default_access' => ZBX_ROLE_RULE_ENABLED
		];

		$rules = [];

		foreach ($roles as $roleid => $role) {
			$type = array_key_exists('type', $role) ? $role['type'] : $db_roles[$role['roleid']]['type'];
			$old_rules = $db_roles !== null ? $db_roles[$roleid]['rules'] : $default_rules;
			$new_rules = array_key_exists('rules', $role) ? $role['rules'] + $old_rules : $old_rules;

			$rules[$roleid] = array_merge(
				self::compileUiRules((int) $type, $old_rules, $new_rules),
				self::compileServicesReadRules($new_rules),
				self::compileServicesWriteRules($new_rules),
				self::compileModulesRules($old_rules, $new_rules),
				self::compileApiRules((int) $type, $new_rules),
				self::compileActionsRules((int) $type, $old_rules, $new_rules)
			);
		}

		$del_rules = [];
		$ins_rules = [];

		if ($db_roles !== null) {
			$db_rules = DB::select('role_rule', [
				'output' => ['role_ruleid', 'roleid', 'type', 'name', 'value_int', 'value_str', 'value_moduleid',
					'value_serviceid'
				],
				'filter' => ['roleid' => array_keys($rules)]
			]);

			foreach ($db_rules as $db_rule) {
				$value = $db_rule[self::RULE_TYPE_FIELDS[$db_rule['type']]];

				$del_rules[$db_rule['roleid']][$db_rule['name']][$db_rule['type']][$value] = $db_rule['role_ruleid'];
			}
		}

		foreach ($rules as $roleid => $role_rules) {
			foreach ($role_rules as $rule) {
				if (array_key_exists($roleid, $del_rules)
						&& array_key_exists($rule['name'], $del_rules[$roleid])
						&& array_key_exists($rule['type'], $del_rules[$roleid][$rule['name']])
						&& array_key_exists($rule['value'], $del_rules[$roleid][$rule['name']][$rule['type']])) {
					unset($del_rules[$roleid][$rule['name']][$rule['type']][$rule['value']]);
				}
				else {
					$ins_rules[$rule['type']][] = [
						'roleid' => $roleid,
						'type' => $rule['type'],
						'name' => $rule['name'],
						self::RULE_TYPE_FIELDS[$rule['type']] => $rule['value']
					];
				}
			}
		}

		if ($del_rules) {
			$del_role_ruleids = [];

			foreach ($del_rules as $del_rules) {
				foreach ($del_rules as $del_rules) {
					foreach ($del_rules as $del_rules) {
						foreach ($del_rules as $role_ruleid) {
							$del_role_ruleids[$role_ruleid] = true;
						}
					}
				}
			}

			DB::delete('role_rule', ['role_ruleid' => array_keys($del_role_ruleids)]);
		}

		if ($ins_rules) {
			foreach ($ins_rules as $ins_rules) {
				DB::insertBatch('role_rule', $ins_rules);
			}
		}
	}

	/**
	 * @param int   $type
	 * @param array $old_rules
	 * @param array $new_rules
	 *
	 * @return array
	 */
	private static function compileUiRules(int $type, array $old_rules, array $new_rules): array {
		$old_ui_rules = array_column($old_rules['ui'], null, 'name');
		$new_ui_rules = array_column($new_rules['ui'], null, 'name');

		$compiled_rules = [];

		foreach (CRoleHelper::getUiElementsByUserType($type) as $ui_rule_name) {
			$ui_element = substr($ui_rule_name, strlen('ui.'));

			if (array_key_exists($ui_element, $new_ui_rules)) {
				$ui_rule_status = $new_ui_rules[$ui_element]['status'];
			}
			elseif (array_key_exists($ui_element, $old_ui_rules)) {
				$ui_rule_status = $old_ui_rules[$ui_element]['status'];
			}
			else {
				$ui_rule_status = $old_rules['ui.default_access'];
			}

			if ($ui_rule_status != $new_rules['ui.default_access']) {
				$compiled_rules[] = [
					'name' => $ui_rule_name,
					'type' => self::RULE_TYPE_INT32,
					'value' => $ui_rule_status
				];
			}
		}

		$compiled_rules[] = [
			'name' => 'ui.default_access',
			'type' => self::RULE_TYPE_INT32,
			'value' => $new_rules['ui.default_access']
		];

		return $compiled_rules;
	}

	/**
	 * @param array $new_rules
	 *
	 * @return array
	 */
	private static function compileServicesReadRules(array $new_rules): array {
		$compiled_rules[] = [
			'name' => 'services.read',
			'type' => self::RULE_TYPE_INT32,
			'value' => $new_rules['services.read.mode']
		];

		if ($new_rules['services.read.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
			foreach ($new_rules['services.read.list'] as $index => $service) {
				$compiled_rules[] = [
					'name' => 'services.read.id.'.$index,
					'type' => self::RULE_TYPE_SERVICE,
					'value' => $service['serviceid']
				];
			}

			if ($new_rules['services.read.tag']['tag'] !== '') {
				$compiled_rules[] = [
					'name' => 'services.read.tag.name',
					'type' => self::RULE_TYPE_STR,
					'value' => $new_rules['services.read.tag']['tag']
				];

				if ($new_rules['services.read.tag']['value'] !== '') {
					$compiled_rules[] = [
						'name' => 'services.read.tag.value',
						'type' => self::RULE_TYPE_STR,
						'value' => $new_rules['services.read.tag']['value']
					];
				}
			}
		}

		return $compiled_rules;
	}

	/**
	 * @param array $new_rules
	 *
	 * @return array
	 */
	private static function compileServicesWriteRules(array $new_rules): array {
		$compiled_rules[] = [
			'name' => 'services.write',
			'type' => self::RULE_TYPE_INT32,
			'value' => $new_rules['services.write.mode']
		];

		if ($new_rules['services.write.mode'] == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
			foreach ($new_rules['services.write.list'] as $index => $service) {
				$compiled_rules[] = [
					'name' => 'services.write.id.'.$index,
					'type' => self::RULE_TYPE_SERVICE,
					'value' => $service['serviceid']
				];
			}

			if ($new_rules['services.write.tag']['tag'] !== '') {
				$compiled_rules[] = [
					'name' => 'services.write.tag.name',
					'type' => self::RULE_TYPE_STR,
					'value' => $new_rules['services.write.tag']['tag']
				];

				if ($new_rules['services.write.tag']['value'] !== '') {
					$compiled_rules[] = [
						'name' => 'services.write.tag.value',
						'type' => self::RULE_TYPE_STR,
						'value' => $new_rules['services.write.tag']['value']
					];
				}
			}
		}

		return $compiled_rules;
	}

	/**
	 * @param array $old_rules
	 * @param array $new_rules
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	private static function compileModulesRules(array $old_rules, array $new_rules): array {
		$old_modules_rules = array_column($old_rules['modules'], null, 'moduleid');
		$new_modules_rules = array_column($new_rules['modules'], null, 'moduleid');

		$compiled_rules = [];

		$index = 0;

		foreach (self::getModuleIds() as $moduleid) {
			if (array_key_exists($moduleid, $new_modules_rules)) {
				$module_status = $new_modules_rules[$moduleid]['status'];
			}
			elseif (array_key_exists($moduleid, $old_modules_rules)) {
				$module_status = $old_modules_rules[$moduleid]['status'];
			}
			else {
				$module_status = $old_rules['modules.default_access'];
			}

			if ($module_status != $new_rules['modules.default_access']) {
				$compiled_rules[] = [
					'name' => 'modules.module.'.$index,
					'type' => self::RULE_TYPE_MODULE,
					'value' => $moduleid
				];

				$index++;
			}
		}

		$compiled_rules[] = [
			'name' => 'modules.default_access',
			'type' => self::RULE_TYPE_INT32,
			'value' => $new_rules['modules.default_access']
		];

		return $compiled_rules;
	}

	/**
	 * @param int   $type
	 * @param array $new_rules
	 *
	 * @return array
	 */
	private static function compileApiRules(int $type, array $new_rules): array {
		$compiled_rules = [];

		$compiled_rules[] = [
			'name' => 'api.access',
			'type' => self::RULE_TYPE_INT32,
			'value' => $new_rules['api.access']
		];

		if ($new_rules['api.access'] == ZBX_ROLE_RULE_ENABLED) {
			$compiled_rules[] = [
				'name' => 'api.mode',
				'type' => self::RULE_TYPE_INT32,
				'value' => $new_rules['api.mode']
			];

			foreach ($new_rules['api'] as $index => $api_method) {
				// Skip specific API methods that do not belong to new user in case of user type change.
				if ($api_method !== ZBX_ROLE_RULE_API_WILDCARD && $api_method !== ZBX_ROLE_RULE_API_WILDCARD_ALIAS
						&& !in_array($api_method, CRoleHelper::getApiMethodMasks($type), true)
						&& !in_array($api_method, CRoleHelper::getApiMethods($type), true)) {
					continue;
				}

				$compiled_rules[] = [
					'name' => 'api.method.'.$index,
					'type' => self::RULE_TYPE_STR,
					'value' => $api_method
				];
			}
		}

		return $compiled_rules;
	}

	/**
	 * @param int   $type
	 * @param array $old_rules
	 * @param array $new_rules
	 *
	 * @return array
	 */
	private static function compileActionsRules(int $type, array $old_rules, array $new_rules): array {
		$old_actions_rules = array_column($old_rules['actions'], null, 'name');
		$new_actions_rules = array_column($new_rules['actions'], null, 'name');

		$compiled_rules = [];

		foreach (CRoleHelper::getActionsByUserType($type) as $action_rule_name) {
			$action_element = substr($action_rule_name, strlen('actions.'));

			if (array_key_exists($action_element, $new_actions_rules)) {
				$action_rule_status = $new_actions_rules[$action_element]['status'];
			}
			elseif (array_key_exists($action_element, $old_actions_rules)) {
				$action_rule_status = $old_actions_rules[$action_element]['status'];
			}
			else {
				$action_rule_status = $old_rules['actions.default_access'];
			}

			if ($action_rule_status != $new_rules['actions.default_access']) {
				$compiled_rules[] = [
					'name' => $action_rule_name,
					'type' => self::RULE_TYPE_INT32,
					'value' => $action_rule_status
				];
			}
		}

		$compiled_rules[] = [
			'name' => 'actions.default_access',
			'type' => self::RULE_TYPE_INT32,
			'value' => $new_rules['actions.default_access']
		];

		return $compiled_rules;
	}

	/**
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param array  $sql_parts
	 *
	 * @return array
	 */
	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$sql_parts['from']['users'] = 'users u';
			$sql_parts['where']['u'] = 'r.roleid=u.roleid';
			$sql_parts['where'][] = 'u.userid='.self::$userData['userid'];
		}

		return $sql_parts;
	}

	/**
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param array  $sql_parts
	 *
	 * @return array
	 */
	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput'] && $options['selectRules'] !== null) {
			$sql_parts = $this->addQuerySelect('r.type', $sql_parts);
		}

		return $sql_parts;
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		$roleids = array_keys($result);

		if ($options['selectRules'] !== null) {
			if ($options['selectRules'] === API_OUTPUT_EXTEND) {
				$output = ['ui', 'ui.default_access', 'services.read.mode', 'services.read.list', 'services.read.tag',
					'services.write.mode', 'services.write.list', 'services.write.tag', 'modules',
					'modules.default_access', 'api', 'api.access', 'api.mode', 'actions', 'actions.default_access'
				];
			}
			else {
				$output = $options['selectRules'];
			}

			$rules = DB::select('role_rule', [
				'output' => ['role_ruleid', 'roleid', 'type', 'name', 'value_int', 'value_str', 'value_moduleid',
					'value_serviceid'
				],
				'filter' => ['roleid' => $roleids]
			]);

			$roles_rules = array_fill_keys($roleids, []);

			foreach ($rules as $rule) {
				$roles_rules[$rule['roleid']][$rule['name']] = $rule[self::RULE_TYPE_FIELDS[$rule['type']]];
			}

			foreach ($result as $roleid => &$role) {
				$role['rules'] = array_merge(
					$this->getRelatedUiRules($roles_rules[$roleid], $output, (int) $role['type']),
					$this->getRelatedServicesReadRules($roles_rules[$roleid], $output),
					$this->getRelatedServicesWriteRules($roles_rules[$roleid], $output),
					$this->getRelatedModulesRules($roles_rules[$roleid], $output),
					$this->getRelatedApiRules($roles_rules[$roleid], $output),
					$this->getRelatedActionsRules($roles_rules[$roleid], $output, (int) $role['type'])
				);
			}
			unset($role);
		}

		if ($options['selectUsers'] !== null) {
			if ($options['selectUsers'] === API_OUTPUT_COUNT) {
				$output = ['userid', 'roleid'];
			}
			elseif ($options['selectUsers'] === API_OUTPUT_EXTEND) {
				$output = self::$userData['type'] == USER_TYPE_SUPER_ADMIN
					? CUser::OUTPUT_FIELDS
					: CUser::OWN_LIMITED_OUTPUT_FIELDS;
			}
			else {
				$output = array_unique(array_merge(['userid', 'roleid'], $options['selectUsers']));
			}

			if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
				$users = API::User()->get([
					'output' => $output,
					'filter' => ['roleid' => $roleids],
					'preservekeys' => true
				]);
			}
			else {
				$users = array_key_exists(self::$userData['roleid'], $result)
					? API::User()->get([
						'output' => $output,
						'userids' => self::$userData['userid'],
						'preservekeys' => true
					])
					: [];
			}

			$relation_map = $this->createRelationMap($users, 'roleid', 'userid');
			$users = $this->unsetExtraFields($users, ['userid', 'roleid'], $options['selectUsers']);
			$result = $relation_map->mapMany($result, $users, 'users');

			if ($options['selectUsers'] === API_OUTPUT_COUNT) {
				foreach ($result as &$row) {
					$row['users'] = (string) count($row['users']);
				}
				unset($row);
			}
		}

		return $result;
	}

	/**
	 * @param array $rules
	 * @param array $output
	 * @param int   $type
	 *
	 * @return array
	 */
	private function getRelatedUiRules(array $rules, array $output, int $type): array {
		$ui_default_access = array_key_exists('ui.default_access', $rules)
			? $rules['ui.default_access']
			: (string) ZBX_ROLE_RULE_ENABLED;

		$result = [];

		if (in_array('ui', $output, true)) {
			$ui = array_fill_keys(CRoleHelper::getUiElementsByUserType($type), $ui_default_access);
			$ui = array_intersect_key($rules, $ui) + $ui;

			$result['ui'] = [];

			foreach ($ui as $ui_element => $status) {
				$result['ui'][] = [
					'name' => substr($ui_element, strlen('ui.')),
					'status' => $status
				];
			}
		}

		if (in_array('ui.default_access', $output, true)) {
			$result['ui.default_access'] = $ui_default_access;
		}

		return $result;
	}

	/**
	 * @param array $rules
	 * @param array $output
	 *
	 * @return array
	 */
	private function getRelatedServicesReadRules(array $rules, array $output): array {
		$result = [];

		$services_read_mode = array_key_exists('services.read', $rules)
			? $rules['services.read']
			: (string) ZBX_ROLE_RULE_SERVICES_ACCESS_ALL;

		if (in_array('services.read.mode', $output, true)) {
			$result['services.read.mode'] = $services_read_mode;
		}

		if (in_array('services.read.list', $output, true)) {
			$result['services.read.list'] = [];

			if ($services_read_mode == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
				$enum = 'services.read.id.';

				foreach ($rules as $rule_name => $rule_value) {
					if (strpos($rule_name, $enum) === 0) {
						$result['services.read.list'][] = ['serviceid' => $rule_value];
					}
				}
			}
		}

		if (in_array('services.read.tag', $output, true)) {
			$result['services.read.tag'] = ['tag' => '', 'value' => ''];

			if ($services_read_mode == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
				if (array_key_exists('services.read.tag.name', $rules)) {
					$result['services.read.tag']['tag'] = $rules['services.read.tag.name'];
				}

				if (array_key_exists('services.read.tag.value', $rules)
						&& $result['services.read.tag']['tag'] !== '') {
					$result['services.read.tag']['value'] = $rules['services.read.tag.value'];
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $rules
	 * @param array $output
	 *
	 * @return array
	 */
	private function getRelatedServicesWriteRules(array $rules, array $output): array {
		$result = [];

		$services_write_mode = array_key_exists('services.write', $rules)
			? $rules['services.write']
			: (string) ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM;

		if (in_array('services.write.mode', $output, true)) {
			$result['services.write.mode'] = $services_write_mode;
		}

		if (in_array('services.write.list', $output, true)) {
			$result['services.write.list'] = [];

			if ($services_write_mode == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
				$enum = 'services.write.id.';

				foreach ($rules as $rule_name => $rule_value) {
					if (strpos($rule_name, $enum) === 0) {
						$result['services.write.list'][] = ['serviceid' => $rule_value];
					}
				}
			}
		}

		if (in_array('services.write.tag', $output, true)) {
			$result['services.write.tag'] = ['tag' => '', 'value' => ''];

			if ($services_write_mode == ZBX_ROLE_RULE_SERVICES_ACCESS_CUSTOM) {
				if (array_key_exists('services.write.tag.name', $rules)) {
					$result['services.write.tag']['tag'] = $rules['services.write.tag.name'];
				}

				if (array_key_exists('services.write.tag.value', $rules)
						&& $result['services.write.tag']['tag'] !== '') {
					$result['services.write.tag']['value'] = $rules['services.write.tag.value'];
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $rules
	 * @param array $output
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	private function getRelatedModulesRules(array $rules, array $output): array {
		$modules_default_access = array_key_exists('modules.default_access', $rules)
			? $rules['modules.default_access']
			: (string) ZBX_ROLE_RULE_ENABLED;

		$result = [];

		if (in_array('modules', $output, true)) {
			$modules = [];

			foreach (self::getModuleIds() as $moduleid) {
				$modules[$moduleid] = [
					'moduleid' => $moduleid,
					'status' => $modules_default_access
				];
			}

			$enum = 'modules.module.';

			foreach ($rules as $rule_name => $rule_value) {
				if (array_key_exists($rule_value, $modules) && strpos($rule_name, $enum) === 0) {
					$modules[$rule_value]['status'] = $modules_default_access == ZBX_ROLE_RULE_ENABLED
						? (string) ZBX_ROLE_RULE_DISABLED
						: (string) ZBX_ROLE_RULE_ENABLED;
				}
			}

			$result['modules'] = array_values($modules);
		}

		if (in_array('modules.default_access', $output, true)) {
			$result['modules.default_access'] = $modules_default_access;
		}

		return $result;
	}

	/**
	 * @param array $rules
	 * @param array $output
	 *
	 * @return array
	 */
	private function getRelatedApiRules(array $rules, array $output): array {
		$result = [];

		if (in_array('api.access', $output, true)) {
			$result['api.access'] = array_key_exists('api.access', $rules)
				? $rules['api.access']
				: (string) ZBX_ROLE_RULE_ENABLED;
		}

		if (in_array('api.mode', $output, true)) {
			$result['api.mode'] = array_key_exists('api.mode', $rules)
				? $rules['api.mode']
				: (string) ZBX_ROLE_RULE_API_MODE_DENY;
		}

		if (in_array('api', $output, true)) {
			$result['api'] = [];

			$enum = 'api.method.';

			foreach ($rules as $rule_name => $rule_value) {
				if (strpos($rule_name, $enum) === 0) {
					$result['api'][] = $rule_value;
				}
			}
		}

		return $result;
	}

	/**
	 * @param array $rules
	 * @param array $output
	 * @param int   $type
	 *
	 * @return array
	 */
	private function getRelatedActionsRules(array $rules, array $output, int $type): array {
		$actions_default_access = array_key_exists('actions.default_access', $rules)
			? $rules['actions.default_access']
			: (string) ZBX_ROLE_RULE_ENABLED;

		$result = [];

		if (in_array('actions', $output, true)) {
			$actions = array_fill_keys(CRoleHelper::getActionsByUserType($type), $actions_default_access);
			$actions = array_intersect_key($rules, $actions) + $actions;

			$result['actions'] = [];

			foreach ($actions as $action => $status) {
				$result['actions'][] = [
					'name' => substr($action, strlen('actions.')),
					'status' => $status
				];
			}
		}

		if (in_array('actions.default_access', $output, true)) {
			$result['actions.default_access'] = $actions_default_access;
		}

		return $result;
	}

	/**
	 * @return array
	 *
	 * @throws APIException
	 */
	private static function getModuleIds(): array {
		$modules = API::getApiService('module')->get([
			'output' => [],
			'preservekeys' => true
		], false);

		return array_keys($modules);
	}
}
