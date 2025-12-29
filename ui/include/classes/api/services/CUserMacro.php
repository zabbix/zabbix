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


/**
 * Class containing methods for operations with user macro.
 */
class CUserMacro extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'createglobal' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'updateglobal' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'deleteglobal' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	public const OUTPUT_FIELDS = ['hostmacroid', 'hostid', 'macro', 'value', 'type', 'description', 'automatic',
		'config'
	];

	protected $tableName = 'hostmacro';
	protected $tableAlias = 'hm';
	protected $sortColumns = ['macro'];

	public static function getOutputFieldsOnHost(): array {
		return array_diff(self::OUTPUT_FIELDS, ['hostid', 'config']);
	}

	public static function getOutputFieldsOnHostPrototype(): array {
		return array_diff(self::OUTPUT_FIELDS, ['hostid', 'automatic', 'config']);
	}

	public static function getOutputFieldsOnTemplate(): array {
		return array_diff(self::OUTPUT_FIELDS, ['hostid', 'automatic']);
	}

	/**
	 * Get UserMacros data.
	 *
	 * @param array $options
	 * @param array $options['groupids'] usermacrosgroup ids
	 * @param array $options['hostids'] host ids
	 * @param array $options['hostmacroids'] host macros ids
	 * @param array $options['globalmacroids'] global macros ids
	 * @param array $options['templateids'] template ids
	 * @param boolean $options['globalmacro'] only global macros
	 * @param boolean $options['selectHostGroups'] select host groups
	 * @param boolean $options['selectTemplateGroups'] select template groups
	 * @param boolean $options['selectHosts'] select hosts
	 * @param boolean $options['selectTemplates'] select templates
	 *
	 * @return array|boolean UserMacros data as array or false if error
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['macros' => 'hm.hostmacroid'],
			'from'		=> 'hostmacro hm',
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$sqlPartsGlobal = [
			'select'	=> ['macros' => 'gm.globalmacroid'],
			'from'		=> 'globalmacro gm',
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'hostids'					=> null,
			'hostmacroids'				=> null,
			'globalmacroids'			=> null,
			'templateids'				=> null,
			'globalmacro'				=> null,
			'inherited'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectHostGroups'			=> null,
			'selectTemplateGroups'		=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (($options['editable'] && !is_null($options['globalmacro'])) || self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}
			else {
				$sqlParts['join']['hh'] = ['table' => 'host_hgset', 'using' => 'hostid'];
				$sqlParts['join']['p'] = ['left_table' => 'hh', 'table' => 'permission', 'using' => 'hgsetid'];

				if ($options['editable']) {
					$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
				}
			}
		}

		// global macro
		if (!is_null($options['globalmacro'])) {
			$options['groupids'] = null;
			$options['hostmacroids'] = null;
			$options['triggerids'] = null;
			$options['hostids'] = null;
			$options['itemids'] = null;
			$options['selectHostGroups'] = null;
			$options['selectTemplateGroups'] = null;
			$options['selectTemplates'] = null;
			$options['selectHosts'] = null;
			$options['inherited'] = null;
		}

		// globalmacroids
		if (!is_null($options['globalmacroids'])) {
			zbx_value2array($options['globalmacroids']);
			$sqlPartsGlobal['where'][] = dbConditionInt('gm.globalmacroid', $options['globalmacroids']);
		}

		// hostmacroids
		if (!is_null($options['hostmacroids'])) {
			zbx_value2array($options['hostmacroids']);
			$sqlParts['where'][] = dbConditionInt('hm.hostmacroid', $options['hostmacroids']);
		}

		// inherited
		if (!is_null($options['inherited'])) {
			$sqlParts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];
			$sqlParts['where'][] = $options['inherited'] ? 'h.templateid IS NOT NULL' : 'h.templateid IS NULL';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['join']['hg'] = ['table' => 'hosts_groups', 'using' => 'hostid'];
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where'][] = dbConditionInt('hm.hostid', $options['hostids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['join']['ht'] = ['table' => 'hosts_templates', 'using' => 'hostid'];
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['templateids']);
		}

		// sorting
		$sqlParts = $this->applyQuerySortOptions('hostmacro', 'hm', $options, $sqlParts);
		$sqlPartsGlobal = $this->applyQuerySortOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
			$sqlPartsGlobal['limit'] = $options['limit'];
		}

		// init GLOBALS
		if (!is_null($options['globalmacro'])) {
			$sqlPartsGlobal = $this->applyQueryFilterOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);
			$sqlPartsGlobal = $this->applyQueryOutputOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);
			$res = DBselect(self::createSelectQueryFromParts($sqlPartsGlobal), $sqlPartsGlobal['limit']);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$result[$macro['globalmacroid']] = $macro;
				}
			}
		}
		// init HOSTS
		else {
			$options['output'] = $this->outputExtend($options['output'], ['hostid']);
			$sqlParts = $this->applyQueryFilterOptions('hostmacro', 'hm', $options, $sqlParts);
			$sqlParts = $this->applyQueryOutputOptions('hostmacro', 'hm', $options, $sqlParts);
			$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$result[$macro['hostmacroid']] = $macro;
				}
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid', 'type'], $options['output']);

			if (!$options['preservekeys']) {
				$result = array_values($result);
			}
		}

		return $result;
	}

	/**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function createGlobal(array $globalmacros) {
		$this->validateCreateGlobal($globalmacros);

		$globalmacroids = DB::insert('globalmacro', $globalmacros);

		foreach ($globalmacros as $index => &$globalmacro) {
			$globalmacro['globalmacroid'] = $globalmacroids[$index];
		}
		unset($globalmacro);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MACRO, $globalmacros);

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * @param array $globalmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreateGlobal(array &$globalmacros) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
			'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('globalmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
			'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'value')],
									['if' => ['field' => 'type', 'in' => ZBX_MACRO_TYPE_VAULT], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('globalmacro', 'value')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $globalmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates($globalmacros);
	}

	/**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function updateGlobal(array $globalmacros) {
		$this->validateUpdateGlobal($globalmacros, $db_globalmacros);

		$upd_globalmacros = [];

		foreach ($globalmacros as $globalmacro) {
			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			$upd_globalmacro = DB::getUpdatedValues('globalmacro', $globalmacro, $db_globalmacro);

			if ($upd_globalmacro) {
				$upd_globalmacros[] = [
					'values'=> $upd_globalmacro,
					'where'=> ['globalmacroid' => $globalmacro['globalmacroid']]
				];
			}
		}

		if ($upd_globalmacros) {
			DB::update('globalmacro', $upd_globalmacros);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MACRO, $globalmacros, $db_globalmacros);

		return ['globalmacroids' => array_column($globalmacros, 'globalmacroid')];
	}

	/**
	 * @param array $globalmacros
	 * @param array $db_globalmacros
	 *
	 * @throws APIException if the input is invalid
	 */
	private function validateUpdateGlobal(array &$globalmacros, ?array &$db_globalmacros = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['globalmacroid'], ['macro']], 'fields' => [
			'globalmacroid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'length' => DB::getFieldLength('globalmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
			'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'value')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $globalmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['globalmacroid', 'macro', 'value', 'description', 'type'],
			'globalmacroids' => array_column($globalmacros, 'globalmacroid'),
			'preservekeys' => true
		]);

		if (count($globalmacros) != count($db_globalmacros)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$globalmacros = $this->extendObjectsByKey($globalmacros, $db_globalmacros, 'globalmacroid', ['type']);

		foreach ($globalmacros as $index => &$globalmacro) {
			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			if ($globalmacro['type'] != $db_globalmacro['type']) {
				if ($db_globalmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
					$globalmacro += ['value' => ''];
				}

				if ($globalmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
					$globalmacro += ['value' => $db_globalmacro['value']];
				}
			}

			if (array_key_exists('value', $globalmacro) && $globalmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
				if (!CApiInputValidator::validate([
							'type' => API_VAULT_SECRET,
							'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)
						], $globalmacro['value'], '/'.($index + 1).'/value', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
		unset($globalmacro);

		$this->checkDuplicates($globalmacros, $db_globalmacros);
	}

	/**
	 * Check for duplicated macros.
	 *
	 * @param array      $globalmacros
	 * @param string     $globalmacros[]['globalmacroid']  (optional if $db_globalmacros is null)
	 * @param string     $globalmacros[]['macro']          (optional if $db_globalmacros is not null)
	 * @param array|null $db_globalmacros
	 *
	 * @throws APIException if macros already exists.
	 */
	private function checkDuplicates(array $globalmacros, ?array $db_globalmacros = null): void {
		$macros = [];

		foreach ($globalmacros as $globalmacro) {
			if ($db_globalmacros === null || (array_key_exists('macro', $globalmacro)
					&& CApiInputValidator::trimMacro($globalmacro['macro'])
						!== CApiInputValidator::trimMacro($db_globalmacros[$globalmacro['globalmacroid']]['macro']))) {
				$macros[] = $globalmacro['macro'];
			}
		}

		if (!$macros) {
			return;
		}

		$db_macros = [];

		$options = ['output' => ['macro']];
		$db_globalmacros = DBselect(DB::makeSql('globalmacro', $options));

		while ($db_globalmacro = DBfetch($db_globalmacros)) {
			$db_macros[CApiInputValidator::trimMacro($db_globalmacro['macro'])] = true;
		}

		foreach ($macros as $macro) {
			if (array_key_exists(CApiInputValidator::trimMacro($macro), $db_macros)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists.', $macro));
			}
		}
	}

	/**
	 * @param array $globalmacroids
	 *
	 * @return array
	 */
	public function deleteGlobal(array $globalmacroids) {
		$this->validateDeleteGlobal($globalmacroids, $db_globalmacros);

		DB::delete('globalmacro', ['globalmacroid' => $globalmacroids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MACRO, $db_globalmacros);

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * @param array $globalmacroids
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDeleteGlobal(array &$globalmacroids, ?array &$db_globalmacros = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $globalmacroids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['globalmacroid', 'macro'],
			'globalmacroids' => $globalmacroids,
			'preservekeys' => true
		]);

		if (count($globalmacroids) != count($db_globalmacros)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * @param array $hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$hostmacros) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'macro']], 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
			'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER), 'length' => DB::getFieldLength('hostmacro', 'value')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')],
			'config' => 		['type' => API_ANY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hostmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateMacroConfig($hostmacros);
		$this->checkHostPermissions(array_unique(array_column($hostmacros, 'hostid')));
		$this->checkHostDuplicates($hostmacros);
	}

	/**
	 * @param array $hostmacros
	 *
	 * @return array
	 */
	public function create(array $hostmacros) {
		$this->validateCreate($hostmacros);

		$this->createReal($hostmacros);

		if ($tpl_hostmacros = $this->getMacrosToInherit($hostmacros)) {
			$this->inherit($tpl_hostmacros);
		}

		return ['hostmacroids' => array_column($hostmacros, 'hostmacroid')];
	}

	/**
	 * Inserts hostmacros records into the database.
	 *
	 * @param array $hostmacros
	 */
	private function createReal(array &$hostmacros): void {
		$hostmacroids = DB::insert('hostmacro', $hostmacros);

		foreach ($hostmacros as $index => &$hostmacro) {
			$hostmacro['hostmacroid'] = $hostmacroids[$index];
		}
		unset($hostmacro);

		$this->updateRealHostmacroConfig($hostmacros);
	}

	/**
	 * @param array $hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$hostmacros, ?array &$db_hostmacros = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
			'hostmacroid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
			'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')],
			'automatic' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_USERMACRO_MANUAL])],
			'config' => 		['type' => API_ANY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hostmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hostmacros = $this->get([
			'output' => ['hostmacroid', 'hostid', 'macro', 'type', 'description', 'automatic', 'config'],
			'hostmacroids' => array_column($hostmacros, 'hostmacroid'),
			'editable' => true,
			'inherited' => false,
			'preservekeys' => true
		]);

		if (count($hostmacros) != count($db_hostmacros)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// CUserMacro::get does not return secret values. Loading directly from the database.
		$options = [
			'output' => ['hostmacroid', 'value'],
			'hostmacroids' => array_keys($db_hostmacros)
		];
		$db_hostmacro_values = DBselect(DB::makeSql('hostmacro', $options));

		while ($db_hostmacro_value = DBfetch($db_hostmacro_values)) {
			$db_hostmacros[$db_hostmacro_value['hostmacroid']] += $db_hostmacro_value;
		}

		$hostmacros = $this->extendObjectsByKey($hostmacros, $db_hostmacros, 'hostmacroid', ['hostid', 'type']);

		foreach ($hostmacros as $index => &$hostmacro) {
			$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];

			if ($db_hostmacro['automatic'] == ZBX_USERMACRO_AUTOMATIC && !array_key_exists('automatic', $hostmacro)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Not allowed to modify automatic user macro "%1$s".', $db_hostmacro['macro'])
				);
			}

			if ($hostmacro['type'] != $db_hostmacro['type']) {
				if ($db_hostmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
					$hostmacro += ['value' => ''];
				}

				if ($hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
					$hostmacro += ['value' => $db_hostmacro['value']];
				}
			}

			if (array_key_exists('value', $hostmacro) && $hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
				if (!CApiInputValidator::validate([
							'type' => API_VAULT_SECRET,
							'provider' => CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER)
						], $hostmacro['value'], '/'.($index + 1).'/value', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
		unset($hostmacro);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['hostid', 'macro']], 'fields' => [
			'hostid' =>	['type' => API_ID],
			'macro' =>	['type' => API_USER_MACRO]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $hostmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateMacroConfig($hostmacros, $db_hostmacros);
		$this->checkHostDuplicates($hostmacros, $db_hostmacros);
	}

	/**
	 * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
	 * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
	 * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
	 *
	 * @param array      $hostmacros
	 * @param string     $hostmacros[]['hostmacroid']  (optional if $db_hostmacros is null)
	 * @param string     $hostmacros[]['hostid']
	 * @param string     $hostmacros[]['macro']        (optional if $db_hostmacros is not null)
	 * @param array|null $db_hostmacros
	 *
	 * @throws APIException if any of the given macros already exist.
	 */
	private function checkHostDuplicates(array $hostmacros, ?array $db_hostmacros = null) {
		$macro_names = [];
		$existing_macros = [];

		// Parse each macro, get unique names and, if context exists, narrow down the search.
		foreach ($hostmacros as $index => $hostmacro) {
			if ($db_hostmacros !== null && (!array_key_exists('macro', $hostmacro)
					|| CApiInputValidator::trimMacro($hostmacro['macro'])
						=== CApiInputValidator::trimMacro($db_hostmacros[$hostmacro['hostmacroid']]['macro']))) {
				unset($hostmacros[$index]);

				continue;
			}

			$trimmed_macro = CApiInputValidator::trimMacro($hostmacro['macro']);
			[$macro_name] = explode(':', $trimmed_macro, 2);
			$macro_name = !isset($trimmed_macro[strlen($macro_name)]) ? '{$'.$macro_name : '{$'.$macro_name.':';

			$macro_names[$macro_name] = true;
			$existing_macros[$hostmacro['hostid']] = [];
		}

		if (!$existing_macros) {
			return;
		}

		$options = [
			'output' => ['hostmacroid', 'hostid', 'macro'],
			'filter' => ['hostid' => array_keys($existing_macros)],
			'search' => ['macro' => array_keys($macro_names)],
			'searchByAny' => true,
			'startSearch' => true
		];

		$db_hostmacros = DBselect(DB::makeSql('hostmacro', $options));

		// Collect existing unique macro names and their contexts for each host.
		while ($db_hostmacro = DBfetch($db_hostmacros)) {
			$trimmed_macro = CApiInputValidator::trimMacro($db_hostmacro['macro']);

			$existing_macros[$db_hostmacro['hostid']][$trimmed_macro] = $db_hostmacro['hostmacroid'];
		}

		// Compare each macro name and context to existing one.
		foreach ($hostmacros as $hostmacro) {
			$hostid = $hostmacro['hostid'];
			$trimmed_macro = CApiInputValidator::trimMacro($hostmacro['macro']);

			if (array_key_exists($trimmed_macro, $existing_macros[$hostid])) {
				$hosts = DB::select('hosts', [
					'output' => ['name'],
					'hostids' => $hostid
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Macro "%1$s" already exists on "%2$s".', $hostmacro['macro'], $hosts[0]['name'])
				);
			}
		}
	}

	private static function validateMacroConfig(array $hostmacros, ?array $db_hostmacros = null): void {
		$api_input_rules = CTemplate::getMacroConfigValidationRules();

		$all_hostids = array_flip(array_column($hostmacros, 'hostid'));

		if ($db_hostmacros !== null) {
			$all_hostids += array_flip(array_column($db_hostmacros, 'hostid'));
		}

		$templates = API::Template()->get([
			'output' => [],
			'templateids' => array_keys($all_hostids),
			'preservekeys' => true
		]);

		foreach ($hostmacros as $index => $hostmacro) {
			// Config is supported only for template macros.
			if (!array_key_exists('config', $hostmacro)) {
				continue;
			}

			$path = '/'.($index + 1);

			if (!array_key_exists($hostmacro['hostid'], $templates)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path,
					_s('unexpected parameter "%1$s"', 'config'))
				);
			}

			if (!$hostmacro['config']) {
				continue;
			}

			$path .= '/config';

			if ($db_hostmacros !== null && array_key_exists($hostmacro['hostmacroid'], $db_hostmacros)) {
				$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];
				$hostmacro['config'] += ['type' => $db_hostmacro['config']['type']];
				self::addRequiredFieldsByMacroConfigType($hostmacro['config'], $db_hostmacro['config']);
			}

			if (!CApiInputValidator::validate($api_input_rules, $hostmacro['config'], $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if (array_key_exists('options', $hostmacro['config'])) {
				$path_options = $path . '/options';
				$encoded = json_encode($hostmacro['config']['options'], JSON_THROW_ON_ERROR);

				if (mb_strlen($encoded) > DB::getFieldLength('hostmacro_config', 'options')) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path_options,
						_('value is too long')
					));
				}
			}
		}
	}

	private static function addRequiredFieldsByMacroConfigType(array &$config, array $db_config): void {
		if ($config['type'] == ZBX_WIZARD_FIELD_TEXT) {
			$config += array_intersect_key($db_config, array_flip(['label']));
		}

		if (in_array($config['type'], [ZBX_WIZARD_FIELD_LIST, ZBX_WIZARD_FIELD_CHECKBOX])) {
			$config += array_intersect_key($db_config, array_flip(['label', 'options']));
		}
	}

	/**
	 * Update host macros.
	 *
	 * @param array $hostmacros an array of host macros
	 *
	 * @return array
	 */
	public function update($hostmacros) {
		$this->validateUpdate($hostmacros, $db_hostmacros);

		$this->updateReal($hostmacros, $db_hostmacros);

		if ($tpl_hostmacros = $this->getMacrosToInherit($hostmacros, $db_hostmacros)) {
			$this->inherit($tpl_hostmacros);
		}

		return ['hostmacroids' => array_column($hostmacros, 'hostmacroid')];
	}

	/**
	 * Updates hostmacros records in the database.
	 *
	 * @param array $hostmacros
	 * @param array $db_hostmacros
	 */
	private function updateReal(array $hostmacros, array $db_hostmacros) {
		$upd_hostmacros = [];

		foreach ($hostmacros as $hostmacro) {
			$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];

			$upd_hostmacro = DB::getUpdatedValues('hostmacro', $hostmacro, $db_hostmacro);

			if ($upd_hostmacro) {
				$upd_hostmacros[] = [
					'values' => $upd_hostmacro,
					'where' => ['hostmacroid' => $hostmacro['hostmacroid']]
				];
			}
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		self::updateRealHostmacroConfig($hostmacros, $db_hostmacros);
	}

	/**
	 * Updates hostmacros_config records in the database.
	 *
	 * @param array      $hostmacros
	 * @param array|null $db_hostmacros
	 */
	private static function updateRealHostmacroConfig(array $hostmacros, ?array $db_hostmacros = null): void {
		$ins_hostmacros_configs = [];
		$upd_hostmacros_configs = [];
		$del_hostmacros_configids = [];

		self::addFieldDefaultsByMacroConfigType($hostmacros, $db_hostmacros);
		self::prepareMacroConfigOptionsForDb($hostmacros, $db_hostmacros);

		foreach ($hostmacros as $hostmacro) {
			if (!array_key_exists('config', $hostmacro) || !$hostmacro['config']) {
				continue;
			}

			if ($db_hostmacros !== null) {
				$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];
				$db_hostmacro_config = array_key_exists('config', $db_hostmacro) ? $db_hostmacro['config'] : [];

				if ($db_hostmacro_config
						&& $db_hostmacro_config['type'] != ZBX_WIZARD_FIELD_NOCONF
						&& array_key_exists('type', $hostmacro['config'])
						&& $hostmacro['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
					$del_hostmacros_configids[] = $hostmacro['hostmacroid'];
					continue;
				}

				if ($db_hostmacro_config
						&& $db_hostmacro_config['type'] == ZBX_WIZARD_FIELD_NOCONF
						&& array_key_exists('type', $hostmacro['config'])
						&& $hostmacro['config']['type'] != ZBX_WIZARD_FIELD_NOCONF) {
					$ins_hostmacros_configs[] = ['hostmacroid' => $hostmacro['hostmacroid']] + $hostmacro['config'];
					continue;
				}

				$upd_hostmacro_config = DB::getUpdatedValues('hostmacro_config', $hostmacro['config'],
					$db_hostmacro_config
				);

				if ($upd_hostmacro_config) {
					$upd_hostmacros_configs[] = [
						'values' => $upd_hostmacro_config,
						'where' => ['hostmacroid' => $hostmacro['hostmacroid']]
					];
				}
			}
			else {
				if ($hostmacro['config']['type'] != ZBX_WIZARD_FIELD_NOCONF) {
					$ins_hostmacros_configs[] = ['hostmacroid' => $hostmacro['hostmacroid']] + $hostmacro['config'];
				}
			}
		}

		if ($ins_hostmacros_configs) {
			DB::insert('hostmacro_config', $ins_hostmacros_configs, false);
		}

		if ($upd_hostmacros_configs) {
			DB::update('hostmacro_config', $upd_hostmacros_configs);
		}

		if ($del_hostmacros_configids) {
			DB::delete('hostmacro_config', ['hostmacroid' => $del_hostmacros_configids]);
		}
	}

	private static function addFieldDefaultsByMacroConfigType(array &$hostmacros, ?array &$db_hostmacros = null): void {
		if ($db_hostmacros === null) {
			return;
		}

		$field_defaults = DB::getDefaults('hostmacro_config');
		$field_defaults['options'] = [];

		foreach ($hostmacros as &$macro) {
			if (array_key_exists($macro['hostmacroid'], $db_hostmacros)
				&& array_key_exists('config', $macro)
				&& array_key_exists('type', $macro['config'])
				&& $macro['config']['type'] != $db_hostmacros[$macro['hostmacroid']]['config']['type']
			) {
				$field_names = [];

				if ($macro['config']['type'] == ZBX_WIZARD_FIELD_NOCONF) {
					$field_names = ['priority', 'section_name', 'label', 'description', 'required', 'regex', 'options'];
				}
				elseif ($macro['config']['type'] == ZBX_WIZARD_FIELD_TEXT) {
					$field_names = ['options'];
				}
				elseif ($macro['config']['type'] == ZBX_WIZARD_FIELD_LIST) {
					$field_names = ['regex'];
				}
				elseif ($macro['config']['type'] == ZBX_WIZARD_FIELD_CHECKBOX) {
					$field_names = ['required', 'regex'];
				}

				$macro['config'] += array_intersect_key($field_defaults, array_flip($field_names));
			}
		}
		unset($macro);
	}

	private static function prepareMacroConfigOptionsForDb(array &$hostmacros, ?array &$db_hostmacros = null): void {
		foreach ($hostmacros as &$macro) {
			if (array_key_exists('config', $macro) && array_key_exists('options', $macro['config'])) {
				$macro['config']['options'] = $macro['config']['options']
					? json_encode($macro['config']['options'])
					: '';
			}
		}
		unset($macro);

		if ($db_hostmacros !== null) {
			foreach ($db_hostmacros as &$db_macro) {
				if (array_key_exists('config', $db_macro) && array_key_exists('options', $db_macro['config'])) {
					$db_macro['config']['options'] = $db_macro['config']['options']
						? json_encode($db_macro['config']['options'])
						: '';
				}
			}
			unset($db_macro);
		}
	}

	/**
	 * @param array $hostmacroids
	 * @param array $db_hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array &$hostmacroids, ?array &$db_hostmacros = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $hostmacroids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hostmacros = $this->get([
			'output' => ['hostmacroid', 'hostid', 'macro'],
			'hostmacroids' => $hostmacroids,
			'editable' => true,
			'inherited' => false,
			'preservekeys' => true
		]);

		if (count($hostmacroids) != count($db_hostmacros)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Remove macros from hosts.
	 *
	 * @param array $hostmacroids
	 *
	 * @return array
	 */
	public function delete(array $hostmacroids) {
		$this->validateDelete($hostmacroids, $db_hostmacros);

		DB::delete('hostmacro', ['hostmacroid' => $hostmacroids]);

		if ($tpl_hostmacros = $this->getMacrosToInherit($db_hostmacros)) {
			$this->inherit($tpl_hostmacros, true);
		}

		return ['hostmacroids' => $hostmacroids];
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @param array $hostids  An array of host or template IDs.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	protected function checkHostPermissions(array $hostids) {
		$count = API::Host()->get([
			'countOutput' => true,
			'hostids' => $hostids,
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
			],
			'editable' => true
		]);

		if ($count == count($hostids)) {
			return;
		}

		$count += API::Template()->get([
			'countOutput' => true,
			'templateids' => $hostids,
			'editable' => true
		]);

		if ($count == count($hostids)) {
			return;
		}

		$count += API::HostPrototype()->get([
			'countOutput' => true,
			'hostids' => $hostids,
			'editable' => true,
			'inherited' => false
		]);

		if ($count != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Forms the array of hostmacros, which are support the inheritance, from the passed hostmacros array.
	 *
	 * @param array      $hostmacros
	 * @param string     $hostmacros[]['hostmacroid']
	 * @param string     $hostmacros[]['hostid']
	 * @param string     $hostmacros[]['macro']                  (optional)
	 * @param string     $hostmacros[]['value']                  (optional)
	 * @param string     $hostmacros[]['description']            (optional)
	 * @param int        $hostmacros[]['type']                   (optional)
	 * @param array|null $db_hostmacros                          Used to set the old macro name in case when it was
	 *                                                           updated.
	 * @param string     $db_hostmacros[<hostmacroid>]['macro']
	 *
	 * @return array
	 */
	private function getMacrosToInherit(array $hostmacros, ?array $db_hostmacros = null): array {
		$templated_host_prototypeids = DBfetchColumn(DBselect(
			'SELECT hd.hostid'.
			' FROM host_discovery hd,items i,hosts h'.
			' WHERE hd.lldruleid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_TEMPLATE.
				' AND '.dbConditionId('hd.hostid', array_unique(array_column($hostmacros, 'hostid')))
		), 'hostid');

		if (!$templated_host_prototypeids) {
			return [];
		}

		foreach ($hostmacros as $index => &$hostmacro) {
			if (!in_array($hostmacro['hostid'], $templated_host_prototypeids)) {
				unset($hostmacros[$index]);

				continue;
			}

			if ($db_hostmacros) {
				$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];
				$hostmacro += array_intersect_key($db_hostmacro, array_flip(['macro']));

				if ($hostmacro['macro'] !== $db_hostmacro['macro']) {
					$hostmacro['macro_old'] = $db_hostmacro['macro'];
				}
			}
		}
		unset($hostmacro);

		return $hostmacros;
	}

	/**
	 * Prepares and returns an array of child hostmacros, inherited from hostmacros $tpl_hostmacros on the all hosts.
	 *
	 * @param array  $tpl_hostmacros
	 * @param string $tpl_hostmacros[]['hostmacroid']
	 * @param string $tpl_hostmacros[]['hostid']
	 * @param string $tpl_hostmacros[]['macro']
	 * @param string $tpl_hostmacros[]['value']        (optional)
	 * @param string $tpl_hostmacros[]['description']  (optional)
	 * @param int    $tpl_hostmacros[]['type']         (optional)
	 * @param string $tpl_hostmacros[]['macro_old']    (optional)
	 * @param array  $ins_hostmacros
	 * @param array  $upd_hostmacros
	 * @param array  $db_hostmacros
	 */
	private function prepareInheritedObjects(array $tpl_hostmacros, ?array &$ins_hostmacros = null,
			?array &$upd_hostmacros = null, ?array &$db_hostmacros = null): void {
		$ins_hostmacros = [];
		$upd_hostmacros = [];
		$db_hostmacros = [];

		$templateids_hostids = [];
		$hostids = [];

		$options = [
			'output' => ['hostid', 'templateid'],
			'filter' => ['templateid' => array_unique(array_column($tpl_hostmacros, 'hostid'))]
		];
		$chd_hosts = DBselect(DB::makeSql('hosts', $options));

		while ($chd_host = DBfetch($chd_hosts)) {
			$templateids_hostids[$chd_host['templateid']][] = $chd_host['hostid'];
			$hostids[] = $chd_host['hostid'];
		}

		if (!$templateids_hostids) {
			return;
		}

		$macros = [];
		foreach ($tpl_hostmacros as $tpl_hostmacro) {
			if (array_key_exists('macro_old', $tpl_hostmacro)) {
				$macros[$tpl_hostmacro['macro_old']] = true;
			}
			else {
				$macros[$tpl_hostmacro['macro']] = true;
			}
		}

		$chd_hostmacros = DB::select('hostmacro', [
			'output' => ['hostmacroid', 'hostid', 'macro', 'type', 'value', 'description'],
			'filter' => ['hostid' => $hostids, 'macro' => array_keys($macros)],
			'preservekeys' => true
		]);

		$host_macros = array_fill_keys($hostids, []);

		foreach ($chd_hostmacros as $hostmacroid => $hostmacro) {
			$host_macros[$hostmacro['hostid']][$hostmacro['macro']] = $hostmacroid;
		}

		foreach ($tpl_hostmacros as $tpl_hostmacro) {
			$templateid = $tpl_hostmacro['hostid'];

			if (!array_key_exists($templateid, $templateids_hostids)) {
				continue;
			}

			foreach ($templateids_hostids[$templateid] as $hostid) {
				if (array_key_exists('macro_old', $tpl_hostmacro)) {
					$hostmacroid = $host_macros[$hostid][$tpl_hostmacro['macro_old']];

					$upd_hostmacros[] = ['hostmacroid' => $hostmacroid, 'hostid' => $hostid] + $tpl_hostmacro;
					$db_hostmacros[$hostmacroid] = $chd_hostmacros[$hostmacroid];

					unset($chd_hostmacros[$hostmacroid], $host_macros[$hostid][$tpl_hostmacro['macro_old']]);
				}
				elseif (array_key_exists($tpl_hostmacro['macro'], $host_macros[$hostid])) {
					$hostmacroid = $host_macros[$hostid][$tpl_hostmacro['macro']];

					$upd_hostmacros[] = ['hostmacroid' => $hostmacroid, 'hostid' => $hostid] + $tpl_hostmacro;
					$db_hostmacros[$hostmacroid] = $chd_hostmacros[$hostmacroid];

					unset($chd_hostmacros[$hostmacroid], $host_macros[$hostid][$tpl_hostmacro['macro']]);
				}
				else {
					$ins_hostmacros[] = ['hostid' => $hostid] + $tpl_hostmacro;
				}
			}
		}
	}

	/**
	 * Updates the macros for the children of host prototypes and propagates the inheritance to the child host
	 * prototypes.
	 *
	 * @param array  $tpl_hostmacros
	 * @param string $tpl_hostmacros[]['hostmacroid']
	 * @param string $tpl_hostmacros[]['hostid']
	 * @param string $tpl_hostmacros[]['macro']
	 * @param string $tpl_hostmacros[]['value']        (optional)
	 * @param string $tpl_hostmacros[]['description']  (optional)
	 * @param int    $tpl_hostmacros[]['type']         (optional)
	 * @param string $tpl_hostmacros[]['macro_old']    (optional)
	 * @param bool   $is_delete                        Whether the passed hostmacros are intended to delete.
	 */
	private function inherit(array $tpl_hostmacros, bool $is_delete = false): void {
		$this->prepareInheritedObjects($tpl_hostmacros, $ins_hostmacros, $upd_hostmacros, $db_hostmacros);

		if ($ins_hostmacros) {
			$this->createReal($ins_hostmacros);
		}

		if ($upd_hostmacros) {
			if ($is_delete) {
				DB::delete('hostmacro', ['hostmacroid' => array_column($upd_hostmacros, 'hostmacroid')]);
			}
			else {
				$this->updateReal($upd_hostmacros, $db_hostmacros);
			}
		}

		if ($ins_hostmacros || $upd_hostmacros) {
			$this->inherit(array_merge($ins_hostmacros, $upd_hostmacros), $is_delete);
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// Added type to query because it required to check macro is secret or not.
		if (!$this->outputIsRequested('type', $options['output'])) {
			$options['output'][] = 'type';
		}

		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['output'] != API_OUTPUT_COUNT && $options['globalmacro'] === null) {
			if ($options['selectHostGroups'] !== null || $options['selectTemplateGroups'] !== null
					|| $options['selectHosts'] !== null || $options['selectTemplates'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('hostid'), $sqlParts);
			}
		}

		return $sqlParts;
	}

	/**
	 * @inheritdoc
	 */
	protected function applyQueryFilterOptions($table, $alias, array $options, $sql_parts) {
		if (is_array($options['search'])) {
			// Do not allow to search by value for macro of type ZBX_MACRO_TYPE_SECRET.
			if (array_key_exists('value', $options['search'])) {
				$sql_parts['where']['search'] = $alias.'.type!='.ZBX_MACRO_TYPE_SECRET;
				zbx_db_search($table.' '.$alias, [
						'searchByAny' => false,
						'search' => ['value' => $options['search']['value']]
					] + $options, $sql_parts
				);
				unset($options['search']['value']);
			}

			if ($options['search']) {
				zbx_db_search($table.' '.$alias, $options, $sql_parts);
			}
		}

		if (is_array($options['filter'])) {
			// Do not allow to filter by value for macro of type ZBX_MACRO_TYPE_SECRET.
			if (array_key_exists('value', $options['filter'])) {
				$sql_parts['where']['filter'] = $alias.'.type!='.ZBX_MACRO_TYPE_SECRET;
				$this->dbFilter($table.' '.$alias, [
						'searchByAny' => false,
						'filter' => ['value' => $options['filter']['value']]
					] + $options, $sql_parts
				);
				unset($options['filter']['value']);
			}

			if ($options['filter']) {
				$this->dbFilter($table.' '.$alias, $options, $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		if ($options['globalmacro'] === null) {
			$this->addRelatedConfig($options, $result);
			$this->addRelatedHostGroups($options, $result);
			$this->addRelatedTemplateGroups($options, $result);
			$this->addRelatedTemplates($options, $result);
			$this->addRelatedHosts($options, $result);
		}

		return $result;
	}

	private function addRelatedConfig(array $options, array &$result): void {
		if (!$this->outputIsRequested('config', $options['output'])) {
			return;
		}

		$templates = API::Template()->get([
			'output' => [],
			'templateids' => array_keys(array_flip(array_column($result, 'hostid'))),
			'preservekeys' => true
		]);

		if (!$templates) {
			return;
		}

		$resource = DB::select('hostmacro_config', [
			'output' => ['type', 'priority', 'section_name', 'label', 'description', 'required', 'regex', 'options'],
			'hostmacroids' => array_keys($result),
			'preservekeys' => true
		]);

		foreach ($result as $hostmacroid => $hostmacro) {
			if (!array_key_exists($hostmacro['hostid'], $templates)) {
				continue;
			}

			$result[$hostmacroid]['config'] = array_key_exists($hostmacroid, $resource)
				? $resource[$hostmacroid]
				: DB::getDefaults('hostmacro_config');

			$result[$hostmacroid]['config']['options'] = $result[$hostmacroid]['config']['options'] !== ''
				? json_decode($result[$hostmacroid]['config']['options'], true)
				: [];
		}
	}

	private function addRelatedHostGroups(array $options, array &$result): void {
		if ($options['selectHostGroups'] === null || $options['selectHostGroups'] === API_OUTPUT_COUNT) {
			return;
		}

		$resource = DBselect(
			'SELECT hm.hostmacroid,hg.groupid'.
			' FROM hostmacro hm'.
			' JOIN hosts_groups hg ON hm.hostid=hg.hostid'.
			' JOIN hstgrp hgg ON hg.groupid=hgg.groupid'.
				' AND '.dbConditionInt('hgg.type', [HOST_GROUP_TYPE_HOST_GROUP]).
			' WHERE '.dbConditionId('hm.hostmacroid', array_keys($result))
		);
		$relation_map = new CRelationMap();

		while ($relation = DBfetch($resource)) {
			$relation_map->addRelation($relation['hostmacroid'], $relation['groupid']);
		}

		$related_ids = $relation_map->getRelatedIds();
		$groups = $related_ids
			? API::HostGroup()->get([
				'output' => $options['selectHostGroups'],
				'groupids' => $related_ids,
				'preservekeys' => true
			])
			: [];

		$result = $relation_map->mapMany($result, $groups, 'hostgroups');
	}

	private function addRelatedTemplateGroups(array $options, array &$result): void {
		if ($options['selectTemplateGroups'] === null || $options['selectTemplateGroups'] === API_OUTPUT_COUNT) {
			return;
		}

		$resource = DBselect(
			'SELECT hm.hostmacroid,hg.groupid'.
			' FROM hostmacro hm'.
			' JOIN hosts_groups hg ON hm.hostid=hg.hostid'.
			' JOIN hstgrp hgg ON hg.groupid=hgg.groupid'.
				' AND '.dbConditionInt('hgg.type', [HOST_GROUP_TYPE_TEMPLATE_GROUP]).
			' WHERE '.dbConditionId('hm.hostmacroid', array_keys($result))
		);
		$relation_map = new CRelationMap();

		while ($relation = DBfetch($resource)) {
			$relation_map->addRelation($relation['hostmacroid'], $relation['groupid']);
		}

		$related_ids = $relation_map->getRelatedIds();
		$groups = $related_ids
			? API::TemplateGroup()->get([
				'output' => $options['selectTemplateGroups'],
				'groupids' => $related_ids,
				'preservekeys' => true
			])
			: [];

		$result = $relation_map->mapMany($result, $groups, 'templategroups');
	}

	private function addRelatedTemplates(array $options, array &$result): void {
		if ($options['selectTemplates'] === null || $options['selectTemplates'] === API_OUTPUT_COUNT) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'hostmacroid', 'hostid');
		$templates = API::Template()->get([
			'output' => $options['selectTemplates'],
			'templateids' => $relation_map->getRelatedIds(),
			'preservekeys' => true
		]);

		$result = $relation_map->mapMany($result, $templates, 'templates');
	}

	private function addRelatedHosts(array $options, array &$result): void {
		if ($options['selectHosts'] === null || $options['selectHosts'] === API_OUTPUT_COUNT) {
			return;
		}

		$relation_map = $this->createRelationMap($result, 'hostmacroid', 'hostid');
		$hosts = API::Host()->get([
			'output' => $options['selectHosts'],
			'hostids' => $relation_map->getRelatedIds(),
			'preservekeys' => true
		]);

		$result = $relation_map->mapMany($result, $hosts, 'hosts');
	}

	protected function unsetExtraFields(array $objects, array $fields, $output = []) {
		foreach ($objects as &$object) {
			if ($object['type'] == ZBX_MACRO_TYPE_SECRET) {
				unset($object['value']);
			}
		}
		unset($object);

		return parent::unsetExtraFields($objects, $fields, $output);
	}
}
