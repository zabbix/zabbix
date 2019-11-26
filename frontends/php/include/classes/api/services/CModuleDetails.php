<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
class CModuleDetails extends CApiService {

	protected $tableName = 'module';
	protected $tableAlias = 'md';
	protected $sortColumns = ['moduleid'];

	/**
	 * Get module data.
	 *
	 * @param array    $options
	 * @param int      $options['moduleid']
	 * @param string   $options['id']               Module unique identifier as defined in manifest.json file.
	 * @param string   $options['relative_path']    Relative path to module directory.
	 * @param bool     $options['status']           Module status.
	 * @param array    $options['config']           Module configuration data.
	 * @return array
	 */
	public function get($options = []) {
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
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
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
	 * @param array    $options
	 * @param int      $options['moduleid']
	 * @param string   $options['id']               Module unique identifier as defined in manifest.json file.
	 * @param string   $options['relative_path']    Relative path to module directory.
	 * @param bool     $options['status']           Module status. (optional)
	 * @param array    $options['config']           Module configuration data. (optional)
	 * @return array
	 */
	public function create($modules) {
		$this->denyAccessUnlessGranted();

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
				'status' => ['type' => API_INT32, 'in' => '0,1']
			]
		];
		$this->validate($rules, $modules);

		$defaults = [
			'status' => 0,
			'config' => []
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
	 * @param array    $options
	 * @param int      $options['moduleid']
	 * @param bool     $options['status']           Module status, true - enabled.
	 * @param array    $options['config']           Module configuration data.
	 * @return array
	 */
	public function update($modules) {
		$this->denyAccessUnlessGranted();

		$modules = zbx_toArray($modules);
		$rules = [
			'type' => API_OBJECT,
			'fields' => [
				'moduleid' => ['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'status' => ['type' => API_INT32, 'in' => '0,1']
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
	 * @param array $moduleids    Array of module ids to be deleted.
	 *
	 * @return array
	 */
	public function delete(array $moduleids) {
		$this->denyAccessUnlessGranted();

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
	protected function validate($rules, $modules) {
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

	/**
	 * Check user permission level.
	 *
	 * @throws APIException if not enought permission.
	 */
	protected function denyAccessUnlessGranted() {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}
	}
}
