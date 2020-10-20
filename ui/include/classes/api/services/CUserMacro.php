<?php
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

	protected $tableName = 'hostmacro';
	protected $tableAlias = 'hm';
	protected $sortColumns = ['macro'];

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
	 * @param boolean $options['selectGroups'] select groups
	 * @param boolean $options['selectHosts'] select hosts
	 * @param boolean $options['selectTemplates'] select templates
	 *
	 * @return array|boolean UserMacros data as array or false if error
	 */
	public function get($options = []) {
		$result = [];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['macros' => 'hm.hostmacroid'],
			'from'		=> ['hostmacro hm'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$sqlPartsGlobal = [
			'select'	=> ['macros' => 'gm.globalmacroid'],
			'from'		=> ['globalmacro gm'],
			'where'		=> [],
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
			'selectGroups'				=> null,
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
			if ($options['editable'] && !is_null($options['globalmacro'])) {
				return [];
			}
			else {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

				$userGroups = getUserGroupsByUserId($userid);

				$sqlParts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $userGroups).
						' WHERE hm.hostid=hgg.hostid'.
						' GROUP BY hgg.hostid'.
						' HAVING MIN(r.permission)>'.PERM_DENY.
							' AND MAX(r.permission)>='.zbx_dbstr($permission).
						')';
			}
		}

		// global macro
		if (!is_null($options['globalmacro'])) {
			$options['groupids'] = null;
			$options['hostmacroids'] = null;
			$options['triggerids'] = null;
			$options['hostids'] = null;
			$options['itemids'] = null;
			$options['selectGroups'] = null;
			$options['selectTemplates'] = null;
			$options['selectHosts'] = null;
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

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=hm.hostid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where'][] = dbConditionInt('hm.hostid', $options['hostids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['from']['macros_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['templateids']);
			$sqlParts['where']['hht'] = 'hm.hostid=ht.hostid';
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
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
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

		$globalmacroids = DB::insertBatch('globalmacro', $globalmacros);

		foreach ($globalmacros as $index => &$globalmacro) {
			$globalmacro['globalmacroid'] = $globalmacroids[$index];
		}
		unset($globalmacro);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MACRO, $globalmacros);

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * @param array $globalmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreateGlobal(array &$globalmacros) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
			'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('globalmacro', 'macro')],
			'value' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('globalmacro', 'value')],
			'type' =>			['type' => API_INT32, 'in' => ZBX_MACRO_TYPE_TEXT.','.ZBX_MACRO_TYPE_SECRET.','.ZBX_MACRO_TYPE_VAULT],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'description')]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $globalmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($globalmacros, 'macro'));

		foreach ($globalmacros as $macro) {
			$this->checkMacroValue($macro);
		}
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

			$upd_globalmacro = [];

			// strings
			foreach (['macro', 'value', 'description', 'type'] as $field_name) {
				if (array_key_exists($field_name, $globalmacro)
						&& $globalmacro[$field_name] !== $db_globalmacro[$field_name]) {
					$upd_globalmacro[$field_name] = $globalmacro[$field_name];
				}
			}

			if (array_key_exists('type', $globalmacro) && $globalmacro['type'] != $db_globalmacro['type']
					&& $db_globalmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
				$upd_globalmacro += ['value' => ''];
			}

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

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MACRO, $globalmacros, $db_globalmacros);

		return ['globalmacroids' => zbx_objectValues($globalmacros, 'globalmacroid')];
	}

	/**
	 * Returns macro without spaces and curly braces.
	 *
	 * @param string $macro
	 *
	 * @return string
	 */
	private function trimMacro($macro) {
		$user_macro_parser = new CUserMacroParser();

		$user_macro_parser->parse($macro);

		$macro = $user_macro_parser->getMacro();
		$context = $user_macro_parser->getContext();
		$regex = $user_macro_parser->getRegex();

		if ($context !== null) {
			$macro .= ':'.$context;
		}
		elseif ($regex !== null) {
			$macro .= ':'.CUserMacroParser::REGEX_PREFIX.$regex;
		}

		return $macro;
	}

	/**
	 * @param array $globalmacros
	 * @param array $db_globalmacros
	 *
	 * @throws APIException if the input is invalid
	 */
	private function validateUpdateGlobal(array &$globalmacros, array &$db_globalmacros = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['globalmacroid'], ['macro']], 'fields' => [
			'globalmacroid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'length' => DB::getFieldLength('globalmacro', 'macro')],
			'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'value')],
			'type' =>			['type' => API_INT32, 'in' => ZBX_MACRO_TYPE_TEXT.','.ZBX_MACRO_TYPE_SECRET.','.ZBX_MACRO_TYPE_VAULT],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'description')]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $globalmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['globalmacroid', 'macro', 'value', 'description', 'type'],
			'globalmacroids' => zbx_objectValues($globalmacros, 'globalmacroid'),
			'preservekeys' => true
		]);

		$macros = [];

		foreach ($globalmacros as $index => $globalmacro) {
			if (!array_key_exists($globalmacro['globalmacroid'], $db_globalmacros)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			if (!array_key_exists('type', $globalmacro)) {
				$globalmacros[$index]['type'] = $db_globalmacro['type'];
			}

			// Use database value to bypass Vault macro validation in case if no value is given.
			if ($globalmacros[$index]['type'] == ZBX_MACRO_TYPE_VAULT && !array_key_exists('value', $globalmacro)) {
				$globalmacros[$index]['value'] = $db_globalmacro['value'];
			}

			if (array_key_exists('macro', $globalmacro)
					&& $this->trimMacro($globalmacro['macro']) !== $this->trimMacro($db_globalmacro['macro'])) {
				$macros[] = $globalmacro['macro'];
			}
		}

		if ($macros) {
			$this->checkDuplicates($macros);
		}

		foreach ($globalmacros as $macro) {
			$this->checkMacroValue($macro);
		}
	}

	/**
	 * Check for duplicated macros.
	 *
	 * @param array $macros
	 *
	 * @throws APIException if macros already exists.
	 */
	private function checkDuplicates(array $macros) {
		$user_macro_parser = new CUserMacroParser();

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['macro']
		]);

		$uniq_macros = [];

		foreach ($db_globalmacros as $db_globalmacro) {
			$uniq_macros[$this->trimMacro($db_globalmacro['macro'])] = true;
		}

		foreach ($macros as $macro) {
			$macro_orig = $macro;
			$macro = $this->trimMacro($macro);

			if (array_key_exists($macro, $uniq_macros)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists.', $macro_orig));
			}
			$uniq_macros[$macro] = true;
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

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MACRO, $db_globalmacros);

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * @param array $globalmacroids
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDeleteGlobal(array &$globalmacroids, array &$db_globalmacros = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $globalmacroids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['globalmacroid', 'macro'],
			'globalmacroids' => $globalmacroids,
			'preservekeys' => true
		]);

		foreach ($globalmacroids as $globalmacroid) {
			if (!array_key_exists($globalmacroid, $db_globalmacros)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $hostmacros) {
		// Check the data required for authorization first.
		foreach ($hostmacros as $hostmacro) {
			$this->checkHostId($hostmacro);
		}

		$this->checkHostPermissions(array_unique(zbx_objectValues($hostmacros, 'hostid')));

		foreach ($hostmacros as $hostmacro) {
			$this->checkMacro($hostmacro);
			$this->checkUnsupportedFields('hostmacro', $hostmacro,
				_s('Wrong fields for macro "%1$s".', $hostmacro['macro']));
			$this->checkMacroType($hostmacro);
			$this->checkMacroValue($hostmacro);
		}

		$this->checkDuplicateMacros($hostmacros);
		$this->checkIfHostMacrosDontRepeat($hostmacros);
	}

	/**
	 * Add new host macros.
	 *
	 * @param array $hostmacros an array of host macros
	 *
	 * @return array
	 */
	public function create(array $hostmacros) {
		$hostmacros = zbx_toArray($hostmacros);

		$this->validateCreate($hostmacros);

		$hostmacroids = DB::insert('hostmacro', $hostmacros);

		return ['hostmacroids' => $hostmacroids];
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $hostmacros) {
		$required_fields = ['hostmacroid'];

		foreach ($hostmacros as $hostmacro) {
			$missing_keys = array_diff($required_fields, array_keys($hostmacro));

			if ($missing_keys) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User macro missing parameters: %1$s', implode(', ', $missing_keys))
				);
			}
		}

		// Make sure we have all the data we need.
		$hostmacros = $this->extendObjects($this->tableName(), $hostmacros, ['macro', 'hostid', 'type', 'value']);

		$db_hostmacros = API::getApiService()->select($this->tableName(), [
			'output' => ['hostmacroid', 'hostid', 'macro'],
			'hostmacroids' => zbx_objectValues($hostmacros, 'hostmacroid')
		]);

		// Check if the macros exist in host.
		$this->checkIfHostMacrosExistIn(zbx_objectValues($hostmacros, 'hostmacroid'), $db_hostmacros);

		// Check the data required for authorization first.
		foreach ($hostmacros as $hostmacro) {
			$this->checkHostId($hostmacro);
		}

		// Check permissions for all affected hosts.
		$affected_hostids = array_merge(zbx_objectValues($db_hostmacros, 'hostid'),
			zbx_objectValues($hostmacros, 'hostid')
		);
		$affected_hostids = array_unique($affected_hostids);
		$this->checkHostPermissions($affected_hostids);

		foreach ($hostmacros as $hostmacro) {
			$this->checkMacro($hostmacro);
			$this->checkUnsupportedFields('hostmacro', $hostmacro,
				_s('Wrong fields for macro "%1$s".', $hostmacro['macro'])
			);
			$this->checkMacroType($hostmacro);
			$this->checkMacroValue($hostmacro);
		}

		$this->checkDuplicateMacros($hostmacros);
		$this->checkIfHostMacrosDontRepeat($hostmacros);
	}

	/**
	 * Update host macros.
	 *
	 * @param array $hostmacros an array of host macros
	 *
	 * @return array
	 */
	public function update($hostmacros) {
		$hostmacros = zbx_toArray($hostmacros);

		$this->validateUpdate($hostmacros);

		$db_macros = DB::select('hostmacro', [
			'output' => ['type'],
			'filter' => ['hostmacroid' => array_column($hostmacros, 'hostmacroid')],
			'preservekeys' => true
		]);
		$data = [];

		foreach ($hostmacros as $macro) {
			$db_macro = $db_macros[$macro['hostmacroid']];

			if (array_key_exists('type', $macro) && $macro['type'] != $db_macro['type']
					&& $db_macro['type'] == ZBX_MACRO_TYPE_SECRET) {
				$macro += ['value' => ''];
			}

			$hostmacroid = $macro['hostmacroid'];
			unset($macro['hostmacroid']);

			$data[] = [
				'values' => $macro,
				'where' => ['hostmacroid' => $hostmacroid]
			];
		}

		DB::update('hostmacro', $data);

		return ['hostmacroids' => array_column($hostmacros, 'hostmacroid')];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $hostmacroids
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array $hostmacroids) {
		if (!$hostmacroids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$db_hostmacros = API::getApiService()->select('hostmacro', [
			'output' => ['hostid', 'hostmacroid'],
			'hostmacroids' => $hostmacroids
		]);

		// Check permissions for all affected hosts.
		$this->checkHostPermissions(array_unique(zbx_objectValues($db_hostmacros, 'hostid')));

		// Check if the macros exist in host.
		$this->checkIfHostMacrosExistIn($hostmacroids, $db_hostmacros);
	}

	/**
	 * Remove macros from hosts.
	 *
	 * @param array $hostmacroids
	 *
	 * @return array
	 */
	public function delete(array $hostmacroids) {
		$this->validateDelete($hostmacroids);

		DB::delete('hostmacro', ['hostmacroid' => $hostmacroids]);

		return ['hostmacroids' => $hostmacroids];
	}

	/**
	 * Replace macros on hosts/templates.
	 * $macros input array has hostid as key and array of that host macros as value.
	 *
	 * @param array $macros
	 */
	public function replaceMacros(array $macros) {
		$hostids = array_keys($macros);

		$this->checkHostPermissions($hostids);

		$db_hosts = API::Host()->get([
			'output' => ['hostmacroid'],
			'hostids' => $hostids,
			'selectMacros' => API_OUTPUT_EXTEND,
			'templated_hosts' => true,
			'preservekeys' => true
		]);

		$hostmacroids_to_delete = [];
		$hostmacros_to_update = [];
		$hostmacros_to_add = [];

		foreach ($macros as $hostid => $hostmacros) {
			$db_hostmacros = zbx_toHash($db_hosts[$hostid]['macros'], 'hostmacroid');

			/*
			 * Look for db macros which hostmacroids are not in list of new macros. If there are any,
			 * they should be deleted.
			 */
			$hostmacroids = zbx_toHash($hostmacros, 'hostmacroid');

			foreach ($db_hostmacros as $db_hostmacro) {
				if (!array_key_exists($db_hostmacro['hostmacroid'], $hostmacroids)) {
					$hostmacroids_to_delete[] = $db_hostmacro['hostmacroid'];
				}
			}

			// if macro has hostmacroid it should be updated otherwise created as new
			foreach ($hostmacros as $hostmacro) {
				if (array_key_exists('hostmacroid', $hostmacro)
						&& array_key_exists($hostmacro['hostmacroid'], $db_hostmacros)) {
					$hostmacros_to_update[] = $hostmacro;
				}
				else {
					$hostmacro['hostid'] = $hostid;
					$hostmacros_to_add[] = $hostmacro;
				}
			}
		}

		if ($hostmacroids_to_delete) {
			$this->delete($hostmacroids_to_delete);
		}

		if ($hostmacros_to_add) {
			$this->create($hostmacros_to_add);
		}

		if ($hostmacros_to_update) {
			$this->update($hostmacros_to_update);
		}
	}

	/**
	 * Validates the "macro" field.
	 *
	 * @param array $macro
	 * @param string $macro['macro']
	 *
	 * @throws APIException if the field is not valid.
	 */
	protected function checkMacro(array $macro) {
		$missing_keys = array_diff(['macro'], array_keys($macro));

		if ($missing_keys) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User macro missing parameters: %1$s', implode(', ', $missing_keys))
			);
		}

		$user_macro_parser = new CUserMacroParser();

		if ($user_macro_parser->parse($macro['macro']) != CParser::PARSE_SUCCESS) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid macro "%1$s": %2$s.', $macro['macro'], $user_macro_parser->getError())
			);
		}
	}

	/**
	 * Validate the "type" field.
	 *
	 * @param array $macro
	 */
	protected function checkMacroType(array $macro) {
		if (array_key_exists('type', $macro)
				&& !in_array($macro['type'], [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid type for macro "%1$s".', $macro['macro']));
		}
	}

	/**
	 * Validate the "value" field.
	 *
	 * @param array  $macro
	 * @param int    $macro['type']
	 * @param string $macro['value']
	 *
	 * @throws APIException if the field is empty.
	 */
	protected function checkMacroValue(array $macro) {
		if (!array_key_exists('type', $macro) || $macro['type'] != ZBX_MACRO_TYPE_VAULT) {
			return;
		}

		if ($macro['value'] === '') {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid value for macro "%1$s": %2$s.', $macro['macro'], _('cannot be empty'))
			);
		}

		$vault_secret_parser = new CVaultSecretParser();

		if ($vault_secret_parser->parse($macro['value']) != CParser::PARSE_SUCCESS) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid value for macro "%1$s": %2$s.', $macro['macro'], $vault_secret_parser->getError())
			);
		}
	}

	/**
	 * Validates the "hostid" field.
	 *
	 * @param array $hostmacro
	 *
	 * @throws APIException if the field is empty.
	 */
	protected function checkHostId(array $hostmacro) {
		if (!array_key_exists('hostid', $hostmacro) || zbx_empty($hostmacro['hostid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('No host given for macro "%1$s".', $hostmacro['macro']));
		}

		if (!is_numeric($hostmacro['hostid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid hostid for macro "%1$s".', $hostmacro['macro']));
		}
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @param array $hostids    an array of host or template IDs
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	protected function checkHostPermissions(array $hostids) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'filter' => [
					'flags' => ZBX_FLAG_DISCOVERY_NORMAL
				],
				'editable' => true
			]);

			if ($count == count($hostids)) {
				return;
			}

			$count += API::Template()->get([
				'countOutput' => true,
				'templateids' => $hostids,
				'filter' => [
					'flags' => ZBX_FLAG_DISCOVERY_NORMAL
				],
				'editable' => true
			]);

			if ($count == count($hostids)) {
				return;
			}

			$count += API::HostPrototype()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Checks if the given macros contain duplicates. Assumes the "macro" field is valid.
	 *
	 * @param array $macros
	 *
	 * @throws APIException if the given macros contain duplicates.
	 */
	protected function checkDuplicateMacros(array $macros) {
		if (count($macros) <= 1) {
			return;
		}

		$existing_macros = [];
		$user_macro_parser = new CUserMacroParser();

		foreach ($macros as $macro) {
			// Global macros don't have a 'hostid'.
			$hostid = array_key_exists('hostid', $macro) ? $macro['hostid'] : 1;

			$user_macro_parser->parse($macro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			/*
			 * Macros with same name can have different contexts. A macro with no context is not the same
			 * as a macro with an empty context.
			 */
			if (array_key_exists($hostid, $existing_macros)
					&& array_key_exists($macro_name, $existing_macros[$hostid])) {
				$has_context = in_array($context, array_column($existing_macros[$hostid][$macro_name], 'context'),
					true
				);
				$context_exists = ($context !== null && $has_context);

				$has_regex = in_array($regex, array_column($existing_macros[$hostid][$macro_name], 'regex'), true);
				$regex_exists = ($regex !== null && $has_regex);

				$is_macro_without_context = ($context === null && $regex === null);

				if (($is_macro_without_context && $has_context && $has_regex) || ($context_exists || $regex_exists)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" is not unique.', $macro['macro']));
				}
			}

			$existing_macros[$hostid][$macro_name][] = ['context' => $context, 'regex' => $regex];
		}
	}

	/**
	 * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
	 * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
	 * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
	 *
	 * @param array $hostmacros
	 * @param int $hostmacros[]['hostmacroid']
	 * @param int $hostmacros[]['hostid']
	 * @param string $hostmacros['macro']
	 *
	 * @throws APIException if any of the given macros already exist.
	 */
	protected function checkIfHostMacrosDontRepeat(array $hostmacros) {
		if (!$hostmacros) {
			return;
		}

		$macro_names = [];
		$user_macro_parser = new CUserMacroParser();

		// Parse each macro, get unique names and, if context exists, narrow down the search.
		foreach ($hostmacros as $hostmacro) {
			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			if ($context === null && $regex === null) {
				$macro_names['{$'.$macro_name] = true;
			}
			else {
				// Narrow down the search for macros with contexts.
				$macro_names['{$'.$macro_name.':'] = true;
			}
		}

		// When updating with empty array, don't select any data from database.
		$db_hostmacros = API::getApiService()->select($this->tableName(), [
			'output' => ['hostmacroid', 'hostid', 'macro'],
			'filter' => ['hostid' => array_unique(array_column($hostmacros, 'hostid'))],
			'search' => ['macro' => array_keys($macro_names)],
			'searchByAny' => true
		]);

		$existing_macros = [];

		// Collect existing unique macro names and their contexts for each host.
		foreach ($db_hostmacros as $db_hostmacro) {
			$user_macro_parser->parse($db_hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			$existing_macros[$db_hostmacro['hostid']][$macro_name][$db_hostmacro['hostmacroid']] =
				['context' => $context, 'regex' => $regex];
		}

		// Compare each macro name and context to existing one.
		foreach ($hostmacros as $hostmacro) {
			$hostid = $hostmacro['hostid'];

			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			if (array_key_exists($hostid, $existing_macros)
					&& array_key_exists($macro_name, $existing_macros[$hostid])) {
				$has_context = ($context !== null && in_array($context,
					array_column($existing_macros[$hostid][$macro_name], 'context'), true
				));
				$has_regex = ($regex !== null && in_array($regex,
					array_column($existing_macros[$hostid][$macro_name], 'regex'), true
				));
				$is_macro_without_context = ($context === null && $regex === null);

				if ($is_macro_without_context || $has_context || $has_regex) {
					foreach ($existing_macros[$hostid][$macro_name] as $hostmacroid => $macro_details) {
						if ((!array_key_exists('hostmacroid', $hostmacro)
									|| bccomp($hostmacro['hostmacroid'], $hostmacroid) != 0)
								&& $context === $macro_details['context'] && $regex === $macro_details['regex']) {
							$hosts = API::getApiService()->select('hosts', [
								'output' => ['name'],
								'hostids' => $hostmacro['hostid']
							]);

							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Macro "%1$s" already exists on "%2$s".', $hostmacro['macro'], $hosts[0]['name'])
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Checks if all of the host macros with hostmacrosids given in $hostmacrosids are present in $db_hostmacros.
	 * Assumes the "hostmacroid" field is valid.
	 *
	 * @param array $hostmacroids
	 * @param array $db_hostmacros
	 *
	 * @throws APIException if any of the host macros is not present in $db_hostmacros.
	 */
	protected function checkIfHostMacrosExistIn(array $hostmacroids, array $db_hostmacros) {
		$db_hostmacros = zbx_toHash($db_hostmacros, 'hostmacroid');

		foreach ($hostmacroids as $hostmacroid) {
			if (!array_key_exists($hostmacroid, $db_hostmacros)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Macro with hostmacroid "%1$s" does not exist.', $hostmacroid)
				);
			}
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// Added type to query because it required to check macro is secret or not.
		if (!$this->outputIsRequested('type', $options['output'])) {
			$options['output'][] = 'type';
		}

		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['output'] != API_OUTPUT_COUNT && $options['globalmacro'] === null) {
			if ($options['selectGroups'] !== null || $options['selectHosts'] !== null || $options['selectTemplates'] !== null) {
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
			$hostMacroIds = array_keys($result);

			/*
			 * Adding objects
			 */
			// adding groups
			if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
				$res = DBselect(
					'SELECT hm.hostmacroid,hg.groupid'.
						' FROM hostmacro hm,hosts_groups hg'.
						' WHERE '.dbConditionInt('hm.hostmacroid', $hostMacroIds).
						' AND hm.hostid=hg.hostid'
				);
				$relationMap = new CRelationMap();
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostmacroid'], $relation['groupid']);
				}

				$groups = API::HostGroup()->get([
					'output' => $options['selectGroups'],
					'groupids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $groups, 'groups');
			}

			// adding templates
			if ($options['selectTemplates'] !== null && $options['selectTemplates'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostmacroid', 'hostid');
				$templates = API::Template()->get([
					'output' => $options['selectTemplates'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'templates');
			}

			// adding templates
			if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostmacroid', 'hostid');
				$templates = API::Host()->get([
					'output' => $options['selectHosts'],
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'hosts');
			}
		}

		return $result;
	}

	protected function unsetExtraFields(array $objects, array $fields, $output) {
		foreach ($objects as &$object) {
			if ($object['type'] == ZBX_MACRO_TYPE_SECRET) {
				unset($object['value']);
			}
		}
		unset($object);

		return parent::unsetExtraFields($objects, $fields, $output);
	}
}
