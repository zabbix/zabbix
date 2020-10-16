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
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'type' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
				'readonly' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => '0,1']
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

		$output = $options['output'];

		if ($options['selectRules'] !== null && is_array($options['output']) && !in_array('type', $options['output'])) {
			$options['output'][] = 'type';
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
			$result = $this->unsetExtraFields($result, ['roleid', 'type'], $output);

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
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'rules' =>			['type' => API_OBJECT, 'default' => [], 'fields' => [
				'ui' =>						['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => '0,1', 'default' => '1']
				]],
				'ui.default_access' =>		['type' => API_INT32, 'in' => '0,1', 'default' => '1'],
				'modules' =>				['type' => API_OBJECT, 'fields' => [
					'moduleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
					'status' =>					['type' => API_INT32, 'in' => '0,1', 'default' => '1']
				]],
				'modules.default_access' =>	['type' => API_INT32, 'in' => '0,1', 'default' => '1'],
				'api.access' =>				['type' => API_INT32, 'in' => '0,1'],
				'api.mode' =>				['type' => API_INT32, 'in' => '0,1'],
				'api.methods' =>			['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE],
				'actions' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => '0,1', 'default' => '1']
				]],
				'actions.default_access' =>	['type' => API_INT32, 'in' => '0,1', 'default' => '1']
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $roles, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(array_keys(array_flip(array_column($roles, 'name'))));
		$this->checkRules($roles);
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

		foreach ($db_roles as $db_roleid => $db_role) {
			unset($db_roles[$db_roleid]['rules']);
		}

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
			'rules' =>			['type' => API_OBJECT, 'fields' => [
				'ui' =>						['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => '0,1']
				]],
				'ui.default_access' =>		['type' => API_INT32, 'in' => '0,1'],
				'modules' =>				['type' => API_OBJECT, 'fields' => [
					'moduleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
					'status' =>					['type' => API_INT32, 'in' => '0,1']
				]],
				'modules.default_access' =>	['type' => API_INT32, 'in' => '0,1'],
				'api.access' =>				['type' => API_INT32, 'in' => '0,1'],
				'api.mode' =>				['type' => API_INT32, 'in' => '0,1'],
				'api.methods' =>			['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE],
				'actions' =>				['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role_rule', 'value_str')],
					'status' =>					['type' => API_INT32, 'in' => '0,1']
				]],
				'actions.default_access' =>	['type' => API_INT32, 'in' => '0,1']
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
		}

		if ($names) {
			$this->checkDuplicates($names);
		}

		$this->checkRules($roles, $db_roles);
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
	 * @param array $roles
	 * @param array $db_roles
	 *
	 * @throws APIException if input is invalid.
	 */
	private function checkRules(array $roles, array $db_roles = []): void {
		$moduleids = [];

		foreach ($roles as $role) {
			if (!array_key_exists('rules', $role)) {
				continue;
			}

			if (!array_key_exists('type', $role)) {
				$role['type'] = $db_roles[$role['roleid']]['type'];
			}

			if (array_key_exists('ui', $role['rules'])) {
				foreach ($role['rules']['ui'] as $ui_element) {
					if (!in_array('ui.'.$ui_element['name'], CRoleHelper::getAllUiElements((int) $role['type']))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('UI element "%1$s" is not available.', $ui_element['name'])
						);
					}
				}
			}

			if (array_key_exists('modules', $role['rules'])) {
				foreach ($role['rules']['modules'] as $module) {
					$moduleids[$module['moduleid']] = true;
				}
			}

			if (array_key_exists('api.methods', $role['rules'])) {
				foreach ($role['rules']['api.methods'] as $api_method) {
					if (!in_array($api_method, CRoleHelper::getApiMethods((int) $role['type']))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('API method "%1$s" is not available.', $api_method)
						);
					}
				}
			}

			if (array_key_exists('actions', $role['rules'])) {
				foreach ($role['rules']['actions'] as $action) {
					if (!in_array('actions.'.$action['name'], CRoleHelper::getAllActions((int) $role['type']))) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Action "%1$s" is not available.', $action['name'])
						);
					}
				}
			}
		}

		if ($moduleids) {
			$moduleids = array_keys($moduleids);

			$db_modules = DBfetchArrayAssoc(DBselect(
				'SELECT moduleid'.
				' FROM module'.
				' WHERE '.dbConditionInt('moduleid', $moduleids).
					' AND status='.MODULE_STATUS_ENABLED
			), 'moduleid');

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
	 * @param array  $roles                   Array of roles.
	 * @param int    $roles[<role>]['type']   Role type.
	 * @param array  $roles[<role>]['rules']  Array or role rules to be updated.
	 * @param string $method
	 */
	private function updateRules(array $roles, string $method): void {
		$insert = [];
		$update = [];
		$delete = [];
		$db_roles_rules = [];
		$is_update = ($method === 'update');

		if ($is_update) {
			$db_rows = DB::select('role_rule', [
				'output' => ['role_ruleid', 'roleid', 'type', 'name', 'value_int', 'value_str', 'value_moduleid'],
				'filter' => ['roleid' => array_column($roles, 'roleid')]
			]);

			// Move rules in database to $delete if it is not accessible anymore by role type.
			foreach ($db_rows as $db_row) {
				$db_roles_rules[$db_row['roleid']][$db_row['role_ruleid']] = $db_row;
			}
		}

		$roles_rules = [];
		$processed_sections = [];

		foreach ($roles as $role) {
			if (!array_key_exists('rules', $role)) {
				continue;
			}

			$default = [
				CRoleHelper::SECTION_UI => [],
				CRoleHelper::UI_DEFAULT_ACCESS => 1,
				CRoleHelper::API_ACCESS => 1,
				CRoleHelper::API_MODE => 1,
				CRoleHelper::SECTION_API => [],
				CRoleHelper::MODULES_DEFAULT_ACCESS => 1,
				CRoleHelper::SECTION_MODULES => [],
				CRoleHelper::ACTIONS_DEFAULT_ACCESS => 1,
				CRoleHelper::SECTION_ACTIONS => []
			];

			if ($is_update) {
				$db_role_rules = array_column($db_roles_rules[$role['roleid']], 'value_int', 'name');
				$default = array_intersect_key($db_role_rules, $default) + $default;
			}

			$rules = $role['rules'] + $default;
			$roleid = $role['roleid'];

			// UI rules.
			$default_access = $rules[CRoleHelper::UI_DEFAULT_ACCESS];
			$processed_sections[$roleid][CRoleHelper::SECTION_UI] = (bool) array_intersect_key($role['rules'], [
				CRoleHelper::UI_DEFAULT_ACCESS => '',
				CRoleHelper::SECTION_UI => ''
			]);
			$roles_rules[$roleid][] = [
				'name' => CRoleHelper::UI_DEFAULT_ACCESS,
				'value_int' => $default_access
			];

			foreach ($rules[CRoleHelper::SECTION_UI] as $rule) {
				if ($rule['status'] != $default_access) {
					$roles_rules[$roleid][] = [
						'name' => sprintf('%s.%s', CRoleHelper::SECTION_UI, $rule['name']),
						'value_int' => $rule['status']
					];
				}
			}

			// API rules.
			$api_access = $rules[CRoleHelper::API_ACCESS];
			$processed_sections[$roleid][CRoleHelper::SECTION_API] = (bool) array_intersect_key($role['rules'], [
				CRoleHelper::API_ACCESS => '',
				CRoleHelper::SECTION_API => ''
			]);
			$roles_rules[$roleid][] = [
				'name' => CRoleHelper::API_ACCESS,
				'value_int' => $api_access
			];

			if ($api_access) {
				$status = $rules[CRoleHelper::API_MODE];

				$index = 0;
				foreach ($rules[CRoleHelper::SECTION_API] as $method) {
					$roles_rules[$roleid][] = [
						'name' => sprintf('%s.%s', CRoleHelper::API_METHOD, $index),
						'value_str' => $method
					];
					$index++;
				}

				if ($index) {
					$roles_rules[$roleid][] = [
						'name' => CRoleHelper::API_MODE,
						'value_int' => $status
					];
				}
			}

			// Module rules.
			$default_access = $rules[CRoleHelper::MODULES_DEFAULT_ACCESS];
			$processed_sections[$roleid][CRoleHelper::SECTION_MODULES] = (bool) array_intersect_key($role['rules'], [
				CRoleHelper::MODULES_DEFAULT_ACCESS => '',
				CRoleHelper::SECTION_MODULES => ''
			]);
			$roles_rules[$roleid][] = [
				'name' => CRoleHelper::MODULES_DEFAULT_ACCESS,
				'value_int' => $default_access
			];

			$index = 0;
			foreach ($rules[CRoleHelper::SECTION_MODULES] as $module) {
				if ($module['status'] != $default_access) {
					$roles_rules[$roleid][] = [
						'name' => sprintf('%s.%d', CRoleHelper::MODULES_MODULE, $index),
						'module_id' => $module['moduleid']
					];
					$roles_rules[$roleid][] = [
						'name' => sprintf('%s.%d', CRoleHelper::MODULES_MODULE_STATUS, $index),
						'value_int' => $module['status']
					];
					$index++;
				}
			}

			// Action rules.
			$default_access = $rules[CRoleHelper::ACTIONS_DEFAULT_ACCESS];
			$processed_sections[$roleid][CRoleHelper::SECTION_ACTIONS] = (bool) array_intersect_key($role['rules'], [
				CRoleHelper::ACTIONS_DEFAULT_ACCESS => '',
				CRoleHelper::SECTION_ACTIONS => ''
			]);
			$roles_rules[$roleid][] = [
				'name' => CRoleHelper::ACTIONS_DEFAULT_ACCESS,
				'value_int' =>$default_access
			];

			foreach ($rules[CRoleHelper::SECTION_ACTIONS] as $rule) {
				if ($rule['status'] != $default_access) {
					$roles_rules[$roleid][] = [
						'name' => sprintf('%s.%s', CRoleHelper::SECTION_ACTIONS, $rule['name']),
						'value_int' => $rule['status']
					];
				}
			}
		}

		// Fill rules to be inserted, updated or deleted.
		foreach ($roles_rules as $roleid => $rules) {
			if (!array_key_exists($roleid, $db_roles_rules)) {
				foreach ($rules as $rule) {
					$insert[] = $rule + ['roleid' => $roleid];
				}

				continue;
			}

			$db_role_rules = array_column($db_roles_rules[$roleid], null, 'name');

			foreach ($rules as $rule) {
				if (!array_key_exists($rule['name'], $db_role_rules)) {
					$insert[] = $rule + ['roleid' => $roleid];

					continue;
				}

				$role_ruleid = $db_role_rules[$rule['name']]['role_ruleid'];
				$type_index = self::RULE_VALUE_TYPES[$db_role_rules[$rule['name']]['type']];

				if (strval($db_role_rules[$rule['name']][$type_index]) != strval($rule[$type_index])) {
					$update[] = [
						'values' => $rule,
						'where' => ['role_ruleid' => $role_ruleid]
					];
				}

				unset($db_roles_rules[$roleid][$role_ruleid]);
			}
		}

		foreach ($db_roles_rules as $roleid => $db_role_rules) {
			if (!array_key_exists($roleid, $processed_sections)) {
				continue;
			}

			foreach ($db_role_rules as $db_rule) {
				$section = substr($db_rule['name'], 0, strpos($db_rule['name'], '.'));

				if ($processed_sections[$roleid][$section]) {
					$delete[] = $db_rule['role_ruleid'];
				}
			}
		}

		if ($insert) {
			DB::insert('role_rule', $insert);
		}

		if ($update) {
			DB::update('role_rule', $update);
		}

		if ($delete) {
			DB::delete('role_rule', ['role_ruleid' => $delete]);
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
			if ($options['selectRules'] === API_OUTPUT_EXTEND) {
				$options['selectRules'] = ['ui', 'ui.default_access', 'modules', 'modules.default_access', 'api.access',
					'api.mode', 'api.methods', 'actions', 'actions.default_access'
				];
			}

			$enabled_modules = in_array('modules', $options['selectRules'])
				? DBfetchArray(DBselect('SELECT moduleid FROM module WHERE status='.MODULE_STATUS_ENABLED))
				: [];

			$db_rules = DBselect(
				'SELECT roleid,type,name,value_int,value_str,value_moduleid'.
				' FROM role_rule'.
				' WHERE '.dbConditionInt('roleid', $roleids)
			);

			$rules = [];
			while ($db_rule = DBfetch($db_rules)) {
				$value = $db_rule[self::RULE_VALUE_TYPES[$db_rule['type']]];

				if (in_array($db_rule['name'], $options['selectRules'])) {
					$rules[$db_rule['roleid']][$db_rule['name']] = $value;
				}
				else {
					[$key, $name] = explode('.', $db_rule['name'], 2);
					$rules[$db_rule['roleid']][$key][$name] = $value;
				}
			}

			foreach ($result as $roleid => $role) {
				$role_rules = [];

				if (in_array('ui', $options['selectRules'])) {
					$role_rules['ui'] = [];
					foreach (CRoleHelper::getAllUiElements((int) $role['type']) as $ui_element) {
						$ui_element = explode('.', $ui_element, 2)[1];
						$role_rules['ui'][] = [
							'name' => $ui_element,
							'status' => (array_key_exists('ui', $rules[$roleid])
									&& array_key_exists($ui_element, $rules[$roleid]['ui']))
								? $rules[$roleid]['ui'][$ui_element]
								: '1'
						];
					}
				}
				if (in_array('ui.default_access', $options['selectRules'])) {
					$role_rules['ui.default_access'] = array_key_exists('ui.default_access', $rules[$roleid])
						? $rules[$roleid]['ui.default_access']
						: '1';
				}
				if (in_array('modules', $options['selectRules'])) {
					$role_rules['modules'] = [];
					$module_rules = array_key_exists('modules', $rules[$roleid]) ? $rules[$roleid]['modules'] : [];
					$modules = [];
					foreach ($module_rules as $key => $value) {
						$index = mb_substr($key, mb_strrpos($key, '.') + 1);

						if (mb_strpos($key, 'module.moduleid') === 0) {
							$modules[$index]['moduleid'] = $value;
						}
						else {
							$modules[$index]['status'] = $value;
						}
					}

					$denied_modules = [];
					foreach ($modules as $index => $module) {
						if (array_key_exists('moduleid', $module) && $module['status'] == 0) {
							$denied_modules[$module['moduleid']] = true;
						}
					}

					foreach ($enabled_modules as $module) {
						$role_rules['modules'][] = [
							'moduleid' => $module['moduleid'],
							'status' => array_key_exists($module['moduleid'], $denied_modules) ? '0' : '1'
						];
					}
				}
				if (in_array('modules.default_access', $options['selectRules'])) {
					$role_rules['modules.default_access'] = array_key_exists('modules.default_access', $rules[$roleid])
						? $rules[$roleid]['modules.default_access']
						: '1';
				}
				if (in_array('api.access', $options['selectRules'])) {
					$role_rules['api.access'] = array_key_exists('api.access', $rules[$roleid])
						? $rules[$roleid]['api.access']
						: '1';
				}
				if (in_array('api.mode', $options['selectRules'])) {
					$role_rules['api.mode'] = array_key_exists('api.mode', $rules[$roleid])
						? $rules[$roleid]['api.mode']
						: '0';
				}
				if (in_array('api.methods', $options['selectRules'])) {
					$role_rules['api.methods'] = array_key_exists('api', $rules[$roleid]) ? $rules[$roleid]['api'] : [];
				}
				if (in_array('actions', $options['selectRules'])) {
					$role_rules['actions'] = [];
					foreach (CRoleHelper::getAllActions((int) $role['type']) as $action) {
						$action = explode('.', $action, 2)[1];
						$role_rules['actions'][] = [
							'name' => $action,
							'status' => (array_key_exists('actions', $rules[$roleid])
									&& array_key_exists($action, $rules[$roleid]['actions']))
								? $rules[$roleid]['actions'][$action]
								: '1'
						];
					}
				}
				if (in_array('actions.default_access', $options['selectRules'])) {
					$role_rules['actions.default_access'] = array_key_exists('actions.default_access', $rules[$roleid])
						? $rules[$roleid]['actions.default_access']
						: '1';
				}

				$result[$roleid]['rules'] = $role_rules;
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
