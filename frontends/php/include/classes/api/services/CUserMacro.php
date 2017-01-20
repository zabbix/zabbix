<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		$userType = self::$userData['type'];
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
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if ($userType != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (!is_null($options['editable']) && !is_null($options['globalmacro'])) {
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

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hostmacro hm', $options, $sqlParts);
			zbx_db_search('globalmacro gm', $options, $sqlPartsGlobal);
		}

		// filter
		if (is_array($options['filter'])) {
			if (isset($options['filter']['macro'])) {
				zbx_value2array($options['filter']['macro']);

				$sqlParts['where'][] = dbConditionString('hm.macro', $options['filter']['macro']);
				$sqlPartsGlobal['where'][] = dbConditionString('gm.macro', $options['filter']['macro']);
			}
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
			$sqlPartsGlobal = $this->applyQueryOutputOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);
			$res = DBselect($this->createSelectQueryFromParts($sqlPartsGlobal), $sqlPartsGlobal['limit']);
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
			$sqlParts = $this->applyQueryOutputOptions('hostmacro', 'hm', $options, $sqlParts);
			$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$result[$macro['hostmacroid']] = $macro;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Validates the input parameters for the createGlobal() method.
	 *
	 * @param array $globalmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreateGlobal(array $globalmacros) {
		$this->checkGlobalMacrosPermissions(_('Only Super Admins can create global macros.'));

		foreach ($globalmacros as $globalmacro) {
			$this->checkMacro($globalmacro);
			$this->checkUnsupportedFields('globalmacro', $globalmacro,
				_s('Wrong fields for macro "%1$s".', $globalmacro['macro'])
			);
		}

		$this->checkDuplicateMacros($globalmacros);
		$this->checkIfGlobalMacrosDontRepeat($globalmacros);
	}

	/**
	 * Add global macros.
	 *
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function createGlobal(array $globalmacros) {
		$globalmacros = zbx_toArray($globalmacros);

		$this->validateCreateGlobal($globalmacros);

		$globalmacroids = DB::insert('globalmacro', $globalmacros);

		$globalmacroid = reset($globalmacroids);

		foreach ($globalmacros as $globalmacro) {
			$value = array_key_exists('value', $globalmacro) ? $globalmacro['value'] : '';

			add_audit_ext(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MACRO, $globalmacroid,
				$globalmacro['macro'].' &rArr; '.$value, null, null, null
			);

			$globalmacroid = next($globalmacroids);
		}

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * Updates global macros.
	 *
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function updateGlobal(array $globalmacros) {
		$globalmacros = zbx_toArray($globalmacros);

		// validate macros
		$this->checkGlobalMacrosPermissions(_('Only Super Admins can update global macros.'));

		$required_fields = ['globalmacroid'];

		foreach ($globalmacros as $globalmacro) {
			$missing_keys = array_diff($required_fields, array_keys($globalmacro));

			if ($missing_keys) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('User macro missing parameters: %1$s', implode(', ', $missing_keys))
				);
			}
		}

		$ex_globalmacros = $this->extendObjects('globalmacro', $globalmacros, ['macro', 'value']);

		foreach ($ex_globalmacros as $ex_globalmacro) {
			$this->checkMacro($ex_globalmacro);
			$this->checkUnsupportedFields('globalmacro', $ex_globalmacro,
				_s('Wrong fields for macro "%1$s".', $ex_globalmacro['macro']));
		}

		$this->checkDuplicateMacros($ex_globalmacros);
		$this->checkIfGlobalMacrosExist(zbx_objectValues($globalmacros, 'globalmacroid'));
		$this->checkIfGlobalMacrosDontRepeat($ex_globalmacros);

		// update macros
		$data = [];

		foreach ($globalmacros as $globalmacro) {
			$values = [];
			foreach (['macro', 'value'] as $field) {
				if (array_key_exists($field, $globalmacro)) {
					$values[$field] = $globalmacro[$field];
				}
			}

			if ($values) {
				$data[] = [
					'values'=> $values,
					'where'=> ['globalmacroid' => $globalmacro['globalmacroid']]
				];
			}
		}

		DB::update('globalmacro', $data);

		foreach ($ex_globalmacros as $ex_globalmacro) {
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MACRO, $ex_globalmacro['globalmacroid'],
				$ex_globalmacro['macro'].' &rArr; '.$ex_globalmacro['value'], null, null, null
			);
		}

		return ['globalmacroids' => zbx_objectValues($globalmacros, 'globalmacroid')];
	}

	/**
	 * Validates the input parameters for the deleteGlobal() method.
	 *
	 * @param array $globalmacroids
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDeleteGlobal(array $globalmacroids) {
		if (!$globalmacroids) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkGlobalMacrosPermissions(_('Only Super Admins can delete global macros.'));
		$this->checkIfGlobalMacrosExist($globalmacroids);
	}

	/**
	 * Delete global macros.
	 *
	 * @param array $globalmacroids
	 *
	 * @return array
	 */
	public function deleteGlobal(array $globalmacroids) {
		$this->validateDeleteGlobal($globalmacroids);

		$db_globalmacros = API::getApiService()->select('globalmacro', [
			'output' => ['globalmacroid', 'macro', 'value'],
			'globalmacroids' => $globalmacroids
		]);

		// delete macros
		DB::delete('globalmacro', ['globalmacroid' => $globalmacroids]);

		foreach ($db_globalmacros as $db_globalmacro) {
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MACRO, $db_globalmacro['globalmacroid'],
				$db_globalmacro['macro'].' &rArr; '.$db_globalmacro['value'], null, null, null
			);
		}

		return ['globalmacroids' => $globalmacroids];
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
		$hostmacros = $this->extendObjects($this->tableName(), $hostmacros, ['macro', 'hostid']);

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

		$data = [];

		foreach ($hostmacros as $macro) {
			$hostmacroid = $macro['hostmacroid'];
			unset($macro['hostmacroid']);

			$data[] = [
				'values' => $macro,
				'where' => ['hostmacroid' => $hostmacroid]
			];
		}

		DB::update('hostmacro', $data);

		return ['hostmacroids' => zbx_objectValues($hostmacros, 'hostmacroid')];
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
		if (!API::Host()->isWritable($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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

			/*
			 * Macros with same name can have different contexts. A macro with no context is not the same
			 * as a macro with an empty context.
			 */
			if (array_key_exists($hostid, $existing_macros) && array_key_exists($macro_name, $existing_macros[$hostid])
					&& in_array($context, $existing_macros[$hostid][$macro_name], true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" is not unique.', $macro['macro']));
			}

			$existing_macros[$hostid][$macro_name][] = $context;
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
	 * @paramt string $hostmacros['macro']
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

			if ($context === null) {
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
			'filter' => ['hostid' => array_unique(zbx_objectValues($hostmacros, 'hostid'))],
			'search' => ['macro' => array_keys($macro_names)],
			'searchByAny' => true
		]);

		$existing_macros = [];

		// Collect existing unique macro names and their contexts for each host.
		foreach ($db_hostmacros as $db_hostmacro) {
			$user_macro_parser->parse($db_hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();

			$existing_macros[$db_hostmacro['hostid']][$macro_name][$db_hostmacro['hostmacroid']] = $context;
		}

		// Compare each macro name and context to existing one.
		foreach ($hostmacros as $hostmacro) {
			$hostid = $hostmacro['hostid'];

			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();

			if (array_key_exists($hostid, $existing_macros) && array_key_exists($macro_name, $existing_macros[$hostid])
					&& in_array($context, $existing_macros[$hostid][$macro_name], true)) {
				foreach ($existing_macros[$hostid][$macro_name] as $hostmacroid => $existing_macro_context) {
					if ((!array_key_exists('hostmacroid', $hostmacro)
							|| bccomp($hostmacro['hostmacroid'], $hostmacroid) != 0)
								&& $context === $existing_macro_context) {
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

	/**
	 * Checks if any of the given host global macros already exist. If the macros are updated and
	 * the "globalmacroid" field is set, the method will only fail, if a macro with a different globalmacroid exists.
	 * Assumes the "macro", "hostmacroid" fields are valid.
	 *
	 * @param array $globalmacros
	 * @param int $globalmacros[]['globalmacroid']
	 * @param string $globalmacros[]['macro']
	 * @param string $globalmacros[]['value']
	 *
	 * @throws APIException if any of the given macros already exist.
	 */
	protected function checkIfGlobalMacrosDontRepeat(array $globalmacros) {
		if (!$globalmacros) {
			return;
		}

		$macro_names = [];
		$user_macro_parser = new CUserMacroParser();

		// Parse each macro, get unique names and, if context exists, narrow down the search.
		foreach ($globalmacros as $globalmacro) {
			$user_macro_parser->parse($globalmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();

			if ($context === null) {
				$macro_names['{$'.$macro_name] = true;
			}
			else {
				// Narrow down the search for macros with contexts.
				$macro_names['{$'.$macro_name.':'] = true;
			}
		}

		// When updating with empty array, don't select any data from database.
		$db_macros = API::getApiService()->select('globalmacro', [
			'output' => ['globalmacroid', 'macro'],
			'search' => ['macro' => array_keys($macro_names)],
			'searchByAny' => true
		]);

		$existing_macros = [];

		// Collect existing unique macro names and their contexts.
		foreach ($db_macros as $db_macro) {
			$user_macro_parser->parse($db_macro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();

			$existing_macros[$macro_name][$db_macro['globalmacroid']] = $context;
		}

		// Compare each macro name and context to existing one.
		foreach ($globalmacros as $globalmacro) {
			$user_macro_parser->parse($globalmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();

			if (array_key_exists($macro_name, $existing_macros)
					&& in_array($context, $existing_macros[$macro_name], true)) {
				foreach ($existing_macros[$macro_name] as $globalmacroid => $existing_macro_context) {
					if ((!array_key_exists('globalmacroid', $globalmacro)
							|| bccomp($globalmacro['globalmacroid'], $globalmacroid) != 0)
								&& $context === $existing_macro_context) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Macro "%1$s" already exists.', $globalmacro['macro'])
						);
					}
				}
			}
		}
	}

	/**
	 * Checks if the user has the permissions to edit global macros.
	 *
	 * @param string $error a message that will be used as the error text
	 *
	 * @throws APIException if the user doesn't have the required permissions.
	 */
	protected function checkGlobalMacrosPermissions($error) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
		}
	}

	/**
	 * Checks if all of the global macros with globalmacroids given in $globalmacroids are present in $globalmacros.
	 * Assumes the "globalmacroids" field is valid.
	 *
	 * @param array $globalmacroids
	 *
	 * @throws APIException if any of the global macros is not present in $globalmacros.
	 */
	protected function checkIfGlobalMacrosExist(array $globalmacroids) {
		if ($globalmacroids) {
			$db_globamacros = API::getApiService()->select('globalmacro', [
				'output' => ['globalmacroid'],
				'globalmacroids' => $globalmacroids,
				'preservekeys' => true
			]);

			foreach ($globalmacroids as $globalmacroid) {
				if (!array_key_exists($globalmacroid, $db_globamacros)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Macro with globalmacroid "%1$s" does not exist.', $globalmacroid)
					);
				}
			}
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['output'] != API_OUTPUT_COUNT && $options['globalmacro'] === null) {
			if ($options['selectGroups'] !== null || $options['selectHosts'] !== null || $options['selectTemplates'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('hostid'), $sqlParts);
			}
		}

		return $sqlParts;
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
}
