<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Class containing methods for operations with user roles.
 */
class CRole extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	/**
	 * @var string
	 */
	protected $tableName = 'role';

	/**
	 * @var string
	 */
	protected $tableAlias = 'r';

	/**
	 * @var array
	 */
	protected $sortColumns = ['roleid', 'name'];

	/**
	 * Rule value types.
	 */
	private const RULE_VALUE_TYPE_INT32 = 0;
	private const RULE_VALUE_TYPE_STR = 1;
	private const RULE_VALUE_TYPE_MODULE = 2;

	/**
	 * Set of rule value types and database field names that store their values.
	 */
	public const RULE_VALUE_TYPES = [
		self::RULE_VALUE_TYPE_INT32 => 'value_int',
		self::RULE_VALUE_TYPE_STR => 'value_str',
		self::RULE_VALUE_TYPE_MODULE => 'value_moduleid'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		$result = [];

		$user_fields = ['userid', 'alias', 'name', 'surname', 'url', 'autologin', 'autologout', 'lang', 'refresh',
			'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page', 'timezone', 'roleid'
		];
		$rules_options = [CRoleHelper::SECTION_UI, CRoleHelper::UI_DEFAULT_ACCESS, CRoleHelper::SECTION_MODULES,
			CRoleHelper::MODULES_DEFAULT_ACCESS, CRoleHelper::API_ACCESS, CRoleHelper::API_MODE, 'api.methods',
			CRoleHelper::SECTION_ACTIONS, CRoleHelper::ACTIONS_DEFAULT_ACCESS
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'roleids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'roleid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['roleid', 'name', 'type', 'readonly']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectRules' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $rules_options), 'default' => null],
			'selectUsers' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $user_fields), 'default' => null],
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

		$sql_parts = [
			'select'	=> ['role' => 'r.roleid'],
			'from'		=> ['role' => 'role r'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		// editable + permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && $options['editable']) {
			return $options['countOutput'] ? 0 : [];
		}

		// roleids
		if ($options['roleids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('r.roleid', $options['roleids']);
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('role r', $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('role r', $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$res = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($db_role = DBfetch($res)) {
			if ($options['countOutput']) {
				return $db_role['rowscount'];
			}

			$result[$db_role['roleid']] = $db_role;
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

	/**
	 * @param array $roles
	 *
	 * @return array
	 */
	public function create(array $roles): array {
		$this->validateCreate($roles);

		$ins_roles = [];

		foreach ($roles as $role) {
			unset($role['rules']);
			$ins_roles[] = $role;
		}

		$roleids = DB::insert('role', $ins_roles);

		foreach ($roles as $index => $role) {
			$roles[$index]['roleid'] = $roleids[$index];
		}

		$this->updateRules($roles, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_USER_ROLE, $roles);

		return ['roleids' => $roleids];
	}

	/**
	 * @param array $roles
	 *
	 * @throws APIException if no permissions or the input is invalid.
	 */
	protected function validateCreate(array &$roles) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permissions to create user roles.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('role', 'name')],
			'type' =>			['type' => API_INT32,  'flags' => API_REQUIRED, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'rules' =>			['type' => API_OBJECTS, 'fields' => [
				'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::RULE_VALUE_TYPE_INT32, self::RULE_VALUE_TYPE_STR, self::RULE_VALUE_TYPE_MODULE])],
				'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'name'), 'default' => DB::getDefault('role_rule', 'name')],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => self::RULE_VALUE_TYPE_INT32], 'type' => API_INT32, 'in' => '0,1'],
										['if' => ['field' => 'type', 'in' => self::RULE_VALUE_TYPE_STR], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str')],
										['if' => ['field' => 'type', 'in' => self::RULE_VALUE_TYPE_MODULE], 'type' => API_ID]
				]]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $roles, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(array_keys(array_flip(array_column($roles, 'name'))));
		$this->checkRules($roles, __FUNCTION__);
	}

	/**
	 * @param array $roles
	 *
	 * @return array
	 */
	public function update(array $roles): array {
		$this->validateUpdate($roles, $db_roles);

		$upd_roles = [];

		foreach ($roles as $role) {
			$db_role = $db_roles[$role['roleid']];

			$upd_role = [];

			if (array_key_exists('name', $role) && $role['name'] !== $db_role['name']) {
				$upd_role['name'] = $role['name'];
			}
			if (array_key_exists('type', $role) && $role['type'] !== $db_role['type']) {
				$upd_role['type'] = $role['type'];
			}

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

		$this->updateRules($roles, __FUNCTION__);

		foreach ($db_roles as &$db_role) {
			unset($db_role['rules']);
		}
		unset($db_role);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_USER_ROLE, $roles, $db_roles);

		return ['roleids' => array_column($roles, 'roleid')];
	}

	/**
	 * @param array $roles
	 * @param array $db_roles
	 *
	 * @throws APIException if input is invalid.
	 */
	private function validateUpdate(array &$roles, ?array &$db_roles) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'roleid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('role', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'rules' =>			['type' => API_OBJECTS, 'fields' => [
				'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::RULE_VALUE_TYPE_INT32, self::RULE_VALUE_TYPE_STR, self::RULE_VALUE_TYPE_MODULE])],
				'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'name'), 'default' => DB::getDefault('role_rule', 'name')],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => self::RULE_VALUE_TYPE_INT32], 'type' => API_INT32, 'in' => '0,1'],
										['if' => ['field' => 'type', 'in' => self::RULE_VALUE_TYPE_STR], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str')],
										['if' => ['field' => 'type', 'in' => self::RULE_VALUE_TYPE_MODULE], 'type' => API_ID]
				]]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $roles, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_roles = $this->get([
			'output' => ['roleid', 'name', 'type', 'readonly'],
			'preservekeys' => true
		]);

		$roles = $this->extendObjectsByKey($roles, $db_roles, 'roleid', ['name']);

		$names = [];

		foreach ($roles as $index => $role) {
			// Check if this user role exists.
			if (!array_key_exists($role['roleid'], $db_roles)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_role = $db_roles[$role['roleid']];

			if ($db_role['readonly'] == 1) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Cannot update readonly user role "%1$s".', $db_role['name'])
				);
			}

			if ($role['name'] !== $db_role['name']) {
				$names[] = $role['name'];
			}

			if (!array_key_exists('type', $role)) {
				$roles[$index]['type'] = $db_role['type'];
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}

		$this->checkRules($roles);
	}

	/**
	 * Check for duplicated user roles.
	 *
	 * @param array $names
	 *
	 * @throws APIException if user role already exists.
	 */
	private function checkDuplicates(array $names): void {
		$db_roles = DB::select('role', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_roles) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User role with name "%1$s" already exists.', $db_roles[0]['name'])
			);
		}
	}

	/**
	 * Check user role rules.
	 *
	 * @param array  $roles
	 * @param string $method
	 *
	 * @throws APIException if input is invalid.
	 */
	private function checkRules(array $roles, string $method): void {
		$rules = [];

		if ($method === 'validateUpdate') {
			$db_rules = DB::select('role_rule', [
				'output' => ['roleid', 'type', 'value_int', 'value_str', 'value_moduleid'],
				'filter' => [
					'roleid' => array_keys(array_flip(array_column($roles, 'roleid'))),
					'type' => [self::RULE_VALUE_TYPE_MODULE]
				]
			]);

			foreach ($db_rules as $db_rule) {
				$roleid = $db_rule['roleid'];
				$type = $db_rule['type'];
				$value = $db_rule[self::RULE_VALUE_TYPES[$db_rule['type']]];

				$rules[$roleid][$type][$value] = true;
			}
		}

		$ids = [
			self::RULE_VALUE_TYPE_MODULE => []
		];

		foreach ($roles as $role) {
			if (!array_key_exists('rules', $role)) {
				continue;
			}

			$roleid = array_key_exists('roleid', $role) ? $role['roleid'] : 0;

			foreach ($role['rules'] as $rule) {
				if ($roleid == 0 || !array_key_exists($roleid, $rules)
						|| !array_key_exists($rule['type'], $rules[$roleid])
						|| !array_key_exists($rule['value'], $rules[$roleid][$rule['type']])) {
					$ids[$rule['type']][$rule['value']] = true;
				}
			}
		}

		if ($ids[self::RULE_VALUE_TYPE_MODULE]) {
			$moduleids = array_keys($ids[self::RULE_VALUE_TYPE_MODULE]);

			$db_modules = API::Module()->get([
				'output' => [],
				'moduleids' => $moduleids,
				'filter' => ['status' => MODULE_STATUS_ENABLED],
				'preservekeys' => true
			]);

			foreach ($moduleids as $moduleid) {
				if (!array_key_exists($moduleid, $db_modules)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Module with ID "%1$s" is not available.', $moduleid)
					);
				}
			}
		}
	}

	/**
	 * Update table "role_rule".
	 *
	 * @param array  $roles
	 * @param string $method
	 */
	private function updateRules(array $roles, string $method): void {
		$roles_rules = [];
		$def_values = [];
		foreach (self::RULE_VALUE_TYPES as $field_name) {
			$default = DB::getDefault('role_rule', $field_name);
			$def_values[$field_name] = ($default !== null) ? $default : 0;
		}

		foreach ($roles as $role) {
			if (array_key_exists('rules', $role)) {
				CArrayHelper::sort($role['rules'], ['type', 'name']);
				$roles_rules[$role['roleid']] = $role['rules'];
			}
		}

		foreach ($roles_rules as &$rules) {
			foreach ($rules as &$rule) {
				$rule[self::RULE_VALUE_TYPES[$rule['type']]] = $rule['value'];
				unset($rule['value']);
				$rule += $def_values;
			}
			unset($rule);
		}
		unset($rules);

		$db_rules = ($method === 'update')
			? DB::select('role_rule', [
				'output' => ['role_ruleid', 'roleid', 'type', 'name', 'value_int', 'value_str', 'value_moduleid'],
				'filter' => ['roleid' => array_keys(array_flip(array_column($roles, 'roleid')))],
				'sortfield' => ['roleid', 'type', 'name']
			])
			: [];

		$ins_rules = [];
		$upd_rules = [];
		$del_ruleids = [];

		$field_names = [
			'str' => ['name', 'value_str'],
			'int' => ['type', 'value_int'],
			'ids' => ['value_moduleid']
		];

		foreach ($db_rules as $db_rule) {
			if ($roles_rules[$db_rule['roleid']]) {
				$rule = array_shift($roles_rules[$db_rule['roleid']]);

				$upd_rule = [];

				foreach ($field_names['str'] as $field_name) {
					if (array_key_exists($field_name, $rule) && $rule[$field_name] !== $db_rule[$field_name]) {
						$upd_rule[$field_name] = $rule[$field_name];
					}
				}
				foreach ($field_names['int'] as $field_name) {
					if (array_key_exists($field_name, $rule) && $rule[$field_name] != $db_rule[$field_name]) {
						$upd_rule[$field_name] = $rule[$field_name];
					}
				}
				foreach ($field_names['ids'] as $field_name) {
					if (array_key_exists($field_name, $rule) && $rule[$field_name] != $db_rule[$field_name]) {
						$upd_rule[$field_name] = $rule[$field_name];
					}
				}

				if ($upd_rule) {
					$upd_rules[] = [
						'values' => $upd_rule,
						'where' => ['role_ruleid' => $db_rule['role_ruleid']]
					];
				}
			}
			else {
				$del_ruleids[] = $db_rule['role_ruleid'];
			}
		}

		foreach ($roles_rules as $roleid => $rules) {
			foreach ($rules as $rule) {
				$ins_rules[] = ['roleid' => $roleid] + $rule;
			}
		}

		if ($ins_rules) {
			DB::insert('role_rule', $ins_rules);
		}

		if ($upd_rules) {
			DB::update('role_rule', $upd_rules);
		}

		if ($del_ruleids) {
			DB::delete('role_rule', ['role_ruleid' => $del_ruleids]);
		}
	}

	/**
	 * @param array $roleids
	 *
	 * @return array
	 */
	public function delete(array $roleids): array {
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

		foreach ($roleids as $roleid) {
			if (!array_key_exists($roleid, $db_roles)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_role = $db_roles[$roleid];

			if ($db_role['readonly'] == 1) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Cannot delete readonly user role "%1$s".', $db_role['name'])
				);
			}

			if ($db_role['users']) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('The role "%1$s" is assigned to at least one user and cannot be deleted.', $db_role['name'])
				);
			}
		}

		DB::delete('role', ['roleid' => $roleids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_USER_ROLE, $db_roles);

		return ['roleids' => $roleids];
	}

	protected function addRelatedObjects(array $options, array $result): array {
		$roleids = array_keys($result);

		// adding role rules
		if ($options['selectRules'] !== null && $options['selectRules'] !== API_OUTPUT_COUNT) {
			$requested_rules = [
				CRoleHelper::SECTION_UI => [],
				CRoleHelper::UI_DEFAULT_ACCESS => '1',
				CRoleHelper::SECTION_MODULES => [],
				CRoleHelper::MODULES_DEFAULT_ACCESS => '1',
				CRoleHelper::API_ACCESS => '1',
				CRoleHelper::API_MODE => '0',
				'api.methods' => [],
				CRoleHelper::SECTION_ACTIONS => [],
				CRoleHelper::ACTIONS_DEFAULT_ACCESS => '1'
			];

			if ($options['selectRules'] !== API_OUTPUT_EXTEND) {
				foreach ($requested_rules as $key => $value) {
					if (!in_array($key, $options['selectRules'])) {
						unset($requested_rules[$key]);
					}
				}
			}

			$db_rules = DBselect(
				'SELECT roleid,type,name,value_int,value_str,value_moduleid'.
				' FROM role_rule'.
				' WHERE '.dbConditionInt('roleid', $roleids)
			);

			foreach ($result as $roleid => $role) {
				$result[$roleid]['rules'] = [];
			}

			while ($db_rule = DBfetch($db_rules)) {
				$value = $db_rule[self::RULE_VALUE_TYPES[$db_rule['type']]];

				if (array_key_exists($db_rule['name'], $requested_rules)) {
					$result[$db_rule['roleid']]['rules'][$db_rule['name']] = $value;
				}
				elseif (strpos($db_rule['name'], CRoleHelper::SECTION_UI) !== false
						&& $db_rule['name'] !== CRoleHelper::UI_DEFAULT_ACCESS
						&& array_key_exists(CRoleHelper::SECTION_UI, $requested_rules)) {
					$result[$db_rule['roleid']]['rules'][CRoleHelper::SECTION_UI][$db_rule['name']] = $value;
				}
				elseif (strpos($db_rule['name'], CRoleHelper::MODULES_MODULE) !== false
						&& array_key_exists(CRoleHelper::SECTION_MODULES, $requested_rules)) {
					$id = (int) substr($db_rule['name'], strrpos($db_rule['name'], '.') + 1);

					if (strpos($db_rule['name'], CRoleHelper::MODULES_MODULE_STATUS) !== false) {
						$result[$db_rule['roleid']]['rules'][CRoleHelper::SECTION_MODULES][$id]['status'] = $value;
					}
					else {
						$result[$db_rule['roleid']]['rules'][CRoleHelper::SECTION_MODULES][$id]['moduleid'] = $value;
					}
				}
				elseif (strpos($db_rule['name'], CRoleHelper::API_METHOD) !== false
						&& array_key_exists('api.methods', $requested_rules)) {
					$result[$db_rule['roleid']]['rules']['api.methods'][] = $value;
				}
				elseif (strpos($db_rule['name'], CRoleHelper::SECTION_ACTIONS) !== false
						&& $db_rule['name'] !== CRoleHelper::ACTIONS_DEFAULT_ACCESS
						&& array_key_exists(CRoleHelper::SECTION_ACTIONS, $requested_rules)) {
					$result[$db_rule['roleid']]['rules'][CRoleHelper::SECTION_ACTIONS][] = [$db_rule['name'] => $value];
				}
			}
		}

		// adding users
		if ($options['selectUsers'] !== null && $options['selectRules'] !== API_OUTPUT_COUNT) {
			if ($options['selectUsers'] === API_OUTPUT_EXTEND) {
				$options['selectUsers'] = ['userid', 'alias', 'name', 'surname', 'url', 'autologin', 'autologout',
					'lang', 'refresh', 'theme', 'attempt_failed', 'attempt_ip', 'attempt_clock', 'rows_per_page',
					'timezone', 'roleid'
				];
			}

			if (in_array('roleid', $options['selectUsers'])) {
				$roleid_requested = true;
			}
			else {
				$roleid_requested = false;
				$options['selectUsers'][] = 'roleid';
			}

			$db_users = DBselect(
				'SELECT '.implode(',', $options['selectUsers']).
				' FROM users'.
				' WHERE '.dbConditionInt('roleid', $roleids)
			);

			foreach ($result as $roleid => $role) {
				$result[$roleid]['users'] = [];
			}

			while ($db_user = DBfetch($db_users)) {
				$roleid = $db_user['roleid'];
				if (!$roleid_requested) {
					unset($db_user['roleid']);
				}

				$result[$roleid]['users'][] = $db_user;
			}
		}

		return $result;
	}
}
