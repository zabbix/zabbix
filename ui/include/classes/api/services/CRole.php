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
 * Class containing methods for operations with roles.
 */
class CRole extends CApiService {

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
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		$result = [];

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
			'selectRules' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['type', 'name', 'value']), 'default' => null],
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

		$roleids = DB::insert('role', $roles);

		foreach ($roles as $index => &$role) {
			$role['roleid'] = $roleids[$index];
		}
		unset($role);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_SCRIPT, $roles);

		return ['roleids' => $roleids];
	}

	/**
	 * @param array $roles
	 *
	 * @throws APIException if no permissions or the input is invalid.
	 */
	protected function validateCreate(array $roles) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permissions to create user roles.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_SCRIPT_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('role', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'readonly' =>		['type' => API_INT32, 'in' => implode(',', [0, 1])],
			'rules' =>			['type' => API_OBJECTS, 'fields' => [
				'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [0,1,2])],
				'name' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'name'), 'default' => DB::getDefault('role_rule', 'name')],
				'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [0])], 'type' => API_INT32],
										['if' => ['field' => 'type', 'in' => implode(',', [1])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('role_rule', 'value_str')],
										['if' => ['field' => 'type', 'in' => implode(',', [2])], 'type' => API_ID]
				]]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $roles, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(array_column($roles, 'name'));
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

	protected function addRelatedObjects(array $options, array $result): array {
		if ($options['selectRules'] !== null && $options['selectRules'] !== API_OUTPUT_COUNT) {
			$role_rules = API::getApiService()->select('role_rule', [
				'output' => $this->outputExtend($options['selectRules'], ['roleid', 'role_ruleid']),
				'filter' => ['hostid' => array_keys($result)],
				'preservekeys' => true
			]);

			$relation_map = $this->createRelationMap($role_rules, 'roleid', 'role_ruleid');
			$role_rules = $this->unsetExtraFields($role_rules, ['roleid', 'role_ruleid'], []);
			$result = $relation_map->mapMany($result, $role_rules, 'rules');
		}

		return $result;
	}
}
