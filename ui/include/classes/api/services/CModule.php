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
 * Class containing methods for operations module.
 */
class CModule extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
	];

	protected $tableName = 'module';
	protected $tableAlias = 'md';
	protected $sortColumns = ['moduleid', 'relative_path'];

	/**
	 * Get module data.
	 *
	 * @param array  $options
	 * @param int    $options['moduleid']
	 * @param string $options['id']             Module unique identifier as defined in manifest.json file.
	 * @param string $options['relative_path']  Relative path to module directory.
	 * @param bool   $options['status']         Module status.
	 * @param array  $options['config']         Module configuration data.
	 * @param bool   $api_call                  Check is method called via API call or from local php file.
	 *
	 * @return array|int
	 *
	 * @throws APIException
	 */
	public function get(array $options = [], $api_call = true) {
		if ($api_call && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$result = [];

		$sqlParts = [
			'select'	=> ['module' => 'md.moduleid'],
			'from'		=> ['module' => 'module md'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$options += [
			'moduleids'					=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];

		// moduleids
		if ($options['moduleids']) {
			zbx_value2array($options['moduleids']);
			$sqlParts['where'][] = dbConditionInt('md.moduleid', $options['moduleids']);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('module md', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('module md', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($module = DBfetch($res)) {
			if ($options['countOutput']) {
				return $module['rowscount'];
			}

			if (array_key_exists('config', $module)) {
				$config = json_decode($module['config'], true);
				$module['config'] = is_null($config) ? [] : $config;
			}

			if ($options['preservekeys']) {
				$result[$module['moduleid']] = $module;
			}
			else {
				$result[] = $module;
			}
		}

		return $result;
	}

	/**
	 * Create module.
	 *
	 * @param array  $modules
	 * @param int    $modules[]['moduleid']
	 * @param string $modules[]['id']             Module unique identifier as defined in manifest.json file.
	 * @param string $modules[]['relative_path']  Relative path to module directory.
	 * @param bool   $modules[]['status']         Module status. (optional)
	 * @param array  $modules[]['config']         Module configuration data. (optional)
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function create(array $modules): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$modules = zbx_toArray($modules);
		$rules = [
			'type' => API_OBJECT,
			'fields' => [
				'id' => [
					'type' => API_STRING_UTF8,
					'flags' => API_REQUIRED | API_NOT_EMPTY,
					'length' => DB::getFieldLength($this->tableName, 'id')
				],
				'relative_path' => [
					'type' => API_STRING_UTF8,
					'flags' => API_REQUIRED | API_NOT_EMPTY,
					'length' => DB::getFieldLength($this->tableName, 'relative_path')
				],
				'status' => ['type' => API_INT32, 'in' => MODULE_STATUS_DISABLED.','.MODULE_STATUS_ENABLED]
			]
		];
		$this->validate($rules, $modules);

		$defaults = [
			'status' => MODULE_STATUS_DISABLED,
			'config' => '[]'
		];

		foreach ($modules as &$module) {
			if (array_key_exists('config', $module)) {
				$module['config'] = json_encode($module['config']);
			}

			$module += $defaults;
		}
		unset($module);

		$moduleids = DB::insert($this->tableName, $modules);

		foreach ($moduleids as $index => $moduleid) {
			$modules[$index]['moduleid'] = $moduleid;
		}

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MODULE, $modules);

		return ['moduleids' => $moduleids];
	}

	/**
	 * Update module data
	 *
	 * @param array $modules
	 * @param int   $modules[]['moduleid']
	 * @param bool  $modules[]['status']    Module status, true - enabled.
	 * @param array $modules[]['config']    Module configuration data.
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function update(array $modules): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$modules = zbx_toArray($modules);
		$rules = [
			'type' => API_OBJECT,
			'fields' => [
				'moduleid' => ['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'status' => ['type' => API_INT32, 'in' => MODULE_STATUS_DISABLED.','.MODULE_STATUS_ENABLED]
			]
		];
		$this->validate($rules, $modules);

		$db_modules = DB::select($this->tableName, [
			'output' => ['moduleid', 'id', 'status'],
			'filter' => ['moduleid' => zbx_objectValues($modules, 'moduleid')],
			'preservekeys' => true
		]);
		$diff = array_diff_key(zbx_objectValues($modules, 'moduleid'), array_keys($db_modules));

		if ($diff) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$update = [];

		foreach ($modules as $module) {
			$moduleid = $module['moduleid'];
			unset($module['moduleid']);

			if (array_key_exists('config', $module)) {
				$module['config'] = json_encode($module['config']);
			}

			$update[] = [
				'values' => $module,
				'where' => ['moduleid' => $moduleid]
			];
		}

		DB::update($this->tableName, $update);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MODULE, $modules, $db_modules);

		return ['moduleids' => array_keys($db_modules)];
	}

	/**
	 * Delete modules
	 *
	 * @param array $moduleids Array of module ids to be deleted.
	 *
	 * @return array
	 *
	 * @throws APIException
	 */
	public function delete(array $moduleids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$db_modules = DB::select($this->tableName, [
			'output' => [$this->pk, 'id'],
			'moduleids' => $moduleids,
			'preservekeys' => true
		]);

		if ($moduleids) {
			DB::delete($this->tableName, [$this->pk => $moduleids]);

			$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MODULE, $db_modules);
		}

		return ['moduleids' => $moduleids];
	}

	/**
	 * Validate module data.
	 *
	 * @param array $rules          API validation rules for module object.
	 * @param array $modules        Array of modules data to be validated.
	 *
	 * @throws APIException
	 */
	protected function validate(array $rules, array $modules): void {
		foreach ($modules as $module) {
			if (array_key_exists('config', $module) && !is_array($module['config'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', 'config', _('an array is expected'))
				);
			}

			unset($module['config']);
			if (!CApiInputValidator::validate($rules, $module, '', $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
	}
}
