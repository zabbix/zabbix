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


/**
 * Class containing methods for operations with modules.
 */
class CModule extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'module';
	protected $tableAlias = 'md';
	protected $sortColumns = ['moduleid', 'relative_path'];

	/**
	 * @param array $options
	 * @param bool  $api_call  Flag indicating whether this method called via an API call or from a local PHP file.
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options = [], bool $api_call = true) {
		if ($api_call && self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'moduleids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'moduleid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'id' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'relative_path' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'status' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [MODULE_STATUS_DISABLED, MODULE_STATUS_ENABLED])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'relative_path' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['moduleid', 'id', 'relative_path', 'status', 'config']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_modules = [];

		$result = DBselect($this->createSelectQuery('module', $options), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			if ($this->outputIsRequested('config', $options['output'])) {
				$row['config'] = json_decode($row['config'], true);
			}

			$db_modules[$row['moduleid']] = $row;
		}

		if ($db_modules) {
			$db_modules = $this->unsetExtraFields($db_modules, ['moduleid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_modules = array_values($db_modules);
			}
		}

		return $db_modules;
	}

	/**
	 * @param array $modules
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $modules): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'module', __FUNCTION__)
			);
		}

		self::validateCreate($modules);

		$moduleids = DB::insert('module', $modules);

		foreach ($modules as $index => &$module) {
			$module['moduleid'] = $moduleids[$index];
		}
		unset($module);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MODULE, $modules);

		return ['moduleids' => $moduleids];
	}

	/**
	 * @static
	 *
	 * @param array $modules
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateCreate(array &$modules): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'id' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('module', 'id')],
			'relative_path' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('module', 'relative_path')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [MODULE_STATUS_DISABLED, MODULE_STATUS_ENABLED])],
			'config' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'default' => [], 'fields' => []]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $modules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		foreach ($modules as &$module) {
			$module['config'] = json_encode($module['config']);
		}
		unset($module);
	}

	/**
	 * @param array $modules
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $modules): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'module', __FUNCTION__)
			);
		}

		self::validateUpdate($modules, $db_modules);

		$upd_modules = [];

		foreach ($modules as $module) {
			$upd_module = DB::getUpdatedValues('module', $module, $db_modules[$module['moduleid']]);

			if ($upd_module) {
				$upd_modules[] = [
					'values' => $upd_module,
					'where' => ['moduleid' => $module['moduleid']]
				];
			}
		}

		if ($upd_modules) {
			DB::update('module', $upd_modules);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MODULE, $modules, $db_modules);

		return ['moduleids' => array_column($modules, 'moduleid')];
	}

	/**
	 * @static
	 *
	 * @param array      $modules
	 * @param array|null $db_modules
	 *
	 * @throws APIException if the input is invalid.
	 */
	private static function validateUpdate(array &$modules, array &$db_modules = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['moduleid']], 'fields' => [
			'moduleid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'status' =>		['type' => API_INT32, 'in' => implode(',', [MODULE_STATUS_DISABLED, MODULE_STATUS_ENABLED])],
			'config' =>		['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $modules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_modules = DB::select('module', [
			'output' => ['moduleid', 'id', 'status', 'config'],
			'filter' => ['moduleid' => array_column($modules, 'moduleid')],
			'preservekeys' => true
		]);

		if (count($db_modules) != count($modules)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($modules as &$module) {
			if (array_key_exists('config', $module)) {
				$module['config'] = json_encode($module['config']);
			}
		}
		unset($module);
	}

	/**
	 * @param array $moduleids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $moduleids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'module', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $moduleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_modules = DB::select('module', [
			'output' => ['moduleid', 'id'],
			'moduleids' => $moduleids,
			'preservekeys' => true
		]);

		if (count($db_modules) != count($moduleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('module', ['moduleid' => $moduleids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MODULE, $db_modules);

		return ['moduleids' => $moduleids];
	}
}
