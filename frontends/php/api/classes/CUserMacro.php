<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CUserMacro extends CZBXAPI {

	protected $tableName = 'hostmacro';
	protected $tableAlias = 'hm';

	/**
	 * Get UserMacros data.
	 *
	 * @param array $options
	 * @param array $options['nodeids'] node ids
	 * @param array $options['groupids'] usermacrosgroup ids
	 * @param array $options['hostids'] host ids
	 * @param array $options['hostmacroids'] host macros ids
	 * @param array $options['globalmacroids'] global macros ids
	 * @param array $options['templateids'] tempalate ids
	 * @param boolean $options['globalmacro'] only global macros
	 * @param boolean $options['selectGroups'] select groups
	 * @param boolean $options['selectHosts'] select hosts
	 * @param boolean $options['selectTemplates'] select templates
	 *
	 * @return array|boolean UserMacros data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('macro');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sqlParts = array(
			'select'	=> array('macros' => 'hm.hostmacroid'),
			'from'		=> array('hostmacro hm'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$sqlPartsGlobal = array(
			'select'	=> array('macros' => 'gm.globalmacroid'),
			'from'		=> array('globalmacro gm'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
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
			'output'					=> API_OUTPUT_REFER,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		elseif (!is_null($options['editable']) && !is_null($options['globalmacro'])) {
			return array();
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where']['hgh'] = 'hg.hostid=hm.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS ('.
				' SELECT hgg.groupid'.
				' FROM hosts_groups hgg,rights rr,users_groups gg'.
				' WHERE hgg.hostid=hg.hostid'.
					' AND rr.id=hgg.groupid'.
					' AND rr.groupid=gg.usrgrpid'.
					' AND gg.userid='.$userid.
					' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// global macro
		if (!is_null($options['globalmacro'])) {
			$sqlPartsGlobal['where'][] = DBin_node('gm.globalmacroid', $nodeids);
			$options['groupids'] = null;
			$options['hostmacroids'] = null;
			$options['triggerids'] = null;
			$options['hostids'] = null;
			$options['itemids'] = null;
			$options['selectGroups'] = null;
			$options['selectTemplates'] = null;
			$options['selectHosts'] = null;
		}
		else {
			$sqlParts['where'][] = DBin_node('hm.hostmacroid', $nodeids);
		}

		// globalmacroids
		if (!is_null($options['globalmacroids'])) {
			zbx_value2array($options['globalmacroids']);
			$sqlPartsGlobal['where'][] = DBcondition('gm.globalmacroid', $options['globalmacroids']);
		}

		// hostmacroids
		if (!is_null($options['hostmacroids'])) {
			zbx_value2array($options['hostmacroids']);
			$sqlParts['where'][] = DBcondition('hm.hostmacroid', $options['hostmacroids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['select']['groupid'] = 'hg.groupid';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=hm.hostid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['select']['hostid'] = 'hm.hostid';
			$sqlParts['where'][] = DBcondition('hm.hostid', $options['hostids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['select']['templateid'] = 'ht.templateid';
			$sqlParts['from']['macros_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = DBcondition('ht.templateid', $options['templateids']);
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

				$sqlParts['where'][] = DBcondition('hm.macro', $options['filter']['macro']);
				$sqlPartsGlobal['where'][] = DBcondition('gm.macro', $options['filter']['macro']);
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['macros'] = 'hm.*';
			$sqlPartsGlobal['select']['macros'] = 'gm.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sqlParts['select'] = array('COUNT(DISTINCT hm.hostmacroid) AS rowscount');
			$sqlPartsGlobal['select'] = array('COUNT(DISTINCT gm.globalmacroid) AS rowscount');
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'hm');
		zbx_db_sorting($sqlPartsGlobal, $options, $sortColumns, 'gm');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
			$sqlPartsGlobal['limit'] = $options['limit'];
		}

		// init GLOBALS
		if (!is_null($options['globalmacro'])) {
			$res = DBselect($this->createSelectQueryFromParts($sqlPartsGlobal), $sqlPartsGlobal['limit']);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					if (!isset($result[$macro['globalmacroid']])) {
						$result[$macro['globalmacroid']] = array();
					}

					$result[$macro['globalmacroid']] += $macro;
				}
			}
		}
		// init HOSTS
		else {
			$hostIds = array();

			$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
			while ($macro = DBfetch($res)) {

				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$hostIds[$macro['hostid']] = $macro['hostid'];

					if (!isset($result[$macro['hostmacroid']])) {
						$result[$macro['hostmacroid']] = array();
					}

					// groups
					if ($options['selectGroups'] && !isset($result[$macro['hostmacroid']]['groups'])) {
						$result[$macro['hostmacroid']]['groups'] = array();
					}

					// templates
					if ($options['selectTemplates'] && !isset($result[$macro['hostmacroid']]['templates'])) {
						$result[$macro['hostmacroid']]['templates'] = array();
					}

					// hosts
					if ($options['selectHosts'] && !isset($result[$macro['hostmacroid']]['hosts'])) {
						$result[$macro['hostmacroid']]['hosts'] = array();
					}

					// groupids
					if (isset($macro['groupid'])) {
						if (!isset($result[$macro['hostmacroid']]['groups'])) {
							$result[$macro['hostmacroid']]['groups'] = array();
						}
						$result[$macro['hostmacroid']]['groups'][] = array('groupid' => $macro['groupid']);
						unset($macro['groupid']);
					}

					// templateids
					if (isset($macro['templateid'])) {
						if (!isset($result[$macro['hostmacroid']]['templates'])) {
							$result[$macro['hostmacroid']]['templates'] = array();
						}
						$result[$macro['hostmacroid']]['templates'][] = array('templateid' => $macro['templateid']);
						unset($macro['templateid']);
					}

					// hostids
					if (isset($macro['hostid'])) {
						if (!isset($result[$macro['hostmacroid']]['hosts'])) {
							$result[$macro['hostmacroid']]['hosts'] = array();
						}
						$result[$macro['hostmacroid']]['hosts'][] = array('hostid' => $macro['hostid']);

						if ($options['output'] == API_OUTPUT_REFER) {
							unset($macro['hostid']);
						}
					}

					$result[$macro['hostmacroid']] += $macro;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		/*
		 * Adding objects
		 */
		// adding groups
		if (!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselectsAllowedOutputs)) {
			$groups = API::HostGroup()->get(array(
				'output' => $options['selectGroups'],
				'hostids' => $hostIds,
				'preservekeys' => true
			));
			foreach ($groups as $group) {
				$ghosts = $group['hosts'];
				unset($group['hosts']);
				foreach ($ghosts as $host) {
					foreach ($result as $macroid => $macro) {
						if (bccomp($macro['hostid'], $host['hostid']) == 0) {
							$result[$macroid]['groups'][] = $group;
						}
					}
				}
			}
		}

		// adding templates
		if (!is_null($options['selectTemplates']) && str_in_array($options['selectTemplates'], $subselectsAllowedOutputs)) {
			$templates = API::Template()->get(array(
				'output' => $options['selectTemplates'],
				'hostids' => $hostIds,
				'preservekeys' => true
			));
			foreach ($templates as $template) {
				$thosts = $template['hosts'];
				unset($template['hosts']);
				foreach ($thosts as $host) {
					foreach ($result as $macroid => $macro) {
						if (bccomp($macro['hostid'], $host['hostid']) == 0) {
							$result[$macroid]['templates'][] = $template;
						}
					}
				}
			}
		}

		// adding hosts
		if (!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
			$hosts = API::Host()->get(array(
				'output' => $options['selectHosts'],
				'hostids' => $hostIds,
				'preservekeys' => true
			));
			foreach ($hosts as $hostid => $host) {
				foreach ($result as $macroid => $macro) {
					if (bccomp($macro['hostid'], $hostid) == 0) {
						$result[$macroid]['hosts'][] = $host;
					}
				}
			}
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
	 * @param array $globalMacros
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreateGlobal(array $globalMacros) {
		$this->checkGlobalMacrosPermissions(_('Only Super Admins can create global macros.'));

		foreach ($globalMacros as $globalMacro) {
			$this->checkMacro($globalMacro);
			$this->checkValue($globalMacro);
			$this->checkUnsupportedFields('globalmacro', $globalMacro,
				_s('Wrong fields for macro "%1$s".', $globalMacro['macro']));
		}

		$this->checkDuplicateMacros($globalMacros);
		$this->checkIfGlobalMacrosDontRepeat($globalMacros);
	}

	/**
	 * Add global macros.
	 *
	 * @param array $globalMacros
	 *
	 * @return array
	 */
	public function createGlobal(array $globalMacros) {
		$globalMacros = zbx_toArray($globalMacros);

		$this->validateCreateGlobal($globalMacros);

		$globalmacroids = DB::insert('globalmacro', $globalMacros);

		return array('globalmacroids' => $globalmacroids);
	}

	/**
	 * Validates the input parameters for the updateGlobal() method.
	 *
	 * @param array $globalMacros
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateUpdateGlobal(array $globalMacros) {
		$this->checkGlobalMacrosPermissions(_('Only Super Admins can update global macros.'));

		foreach ($globalMacros as $globalMacro) {
			if (!isset($globalMacro['globalmacroid']) || zbx_empty($globalMacro['globalmacroid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$globalMacros = $this->extendObjects('globalmacro', $globalMacros, array('macro'));

		foreach ($globalMacros as $globalMacro) {
			$this->checkMacro($globalMacro);
			$this->checkValue($globalMacro);
			$this->checkUnsupportedFields('globalmacro', $globalMacro,
				_s('Wrong fields for macro "%1$s".', $globalMacro['macro']));
		}

		$this->checkDuplicateMacros($globalMacros);
		$this->checkIfGlobalMacrosExist(zbx_objectValues($globalMacros, 'globalmacroid'));
		$this->checkIfGlobalMacrosDontRepeat($globalMacros);
	}

	/**
	 * Updates global macros.
	 *
	 * @param array $globalMacros
	 *
	 * @return array
	 */
	public function updateGlobal(array $globalMacros) {
		$globalMacros = zbx_toArray($globalMacros);

		$this->validateUpdateGlobal($globalMacros);

		// update macros
		$data = array();
		foreach ($globalMacros as $gmacro) {
			$globalMacroId = $gmacro['globalmacroid'];
			unset($gmacro['globalmacroid']);

			$data[] = array(
				'values'=> $gmacro,
				'where'=> array('globalmacroid' => $globalMacroId)
			);
		}
		DB::update('globalmacro', $data);

		return array('globalmacroids' => zbx_objectValues($globalMacros, 'globalmacroid'));
	}

	/**
	 * Validates the input parameters for the deleteGlobal() method.
	 *
	 * @param array $globalMacroIds
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateDeleteGlobal(array $globalMacroIds) {
		if (empty($globalMacroIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$this->checkGlobalMacrosPermissions(_('Only Super Admins can delete global macros.'));
		$this->checkIfGlobalMacrosExist($globalMacroIds);
	}

	/**
	 * Delete global macros.
	 *
	 * @param mixed $globalMacroIds
	 *
	 * @return array
	 */
	public function deleteGlobal($globalMacroIds) {
		$globalMacroIds = zbx_toArray($globalMacroIds);

		$this->validateDeleteGlobal($globalMacroIds);

		// delete macros
		DB::delete('globalmacro', array('globalmacroid' => $globalMacroIds));

		return array('globalmacroids' => $globalMacroIds);
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $hostMacros
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreate(array $hostMacros) {
		// check the data required for authorization first
		foreach ($hostMacros as $hostMacro) {
			$this->checkHostId($hostMacro);
		}

		$this->checkHostPermissions(array_unique(zbx_objectValues($hostMacros, 'hostid')));

		foreach ($hostMacros as $hostMacro) {
			$this->checkMacro($hostMacro);
			$this->checkValue($hostMacro);
			$this->checkUnsupportedFields('hostmacro', $hostMacro,
				_s('Wrong fields for macro "%1$s".', $hostMacro['macro']));
		}

		$this->checkDuplicateMacros($hostMacros);
		$this->checkIfHostMacrosDontRepeat($hostMacros);
	}

	/**
	 * Add new host macros.
	 *
	 * @param array $hostMacros an array of host macros
	 *
	 * @return array
	 */
	public function create(array $hostMacros) {
		$hostMacros = zbx_toArray($hostMacros);

		$this->validateCreate($hostMacros);

		$hostmacroids = DB::insert('hostmacro', $hostMacros);

		return array('hostmacroids' => $hostmacroids);
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $hostMacros
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateUpdate(array $hostMacros) {
		foreach ($hostMacros as $hostMacro) {
			if (!isset($hostMacro['hostmacroid']) || zbx_empty($hostMacro['hostmacroid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		// make sure we have all the data we need
		$hostMacros = $this->extendObjects($this->tableName(), $hostMacros, array('macro', 'hostid'));
		$dbHostMacros = $this->get(array(
			'hostmacroids' => zbx_objectValues($hostMacros, 'hostmacroid'),
			'output' => API_OUTPUT_EXTEND
		));

		// check the data required for authorization first
		foreach ($hostMacros as $hostMacro) {
			$this->checkHostId($hostMacro);
		}

		// check permissions for all affected hosts
		$affectedHostIds = array_merge(zbx_objectValues($dbHostMacros, 'hostid'), zbx_objectValues($hostMacros, 'hostid'));
		$affectedHostIds = array_unique($affectedHostIds);
		$this->checkHostPermissions($affectedHostIds);

		foreach ($hostMacros as $hostMacro) {
			$this->checkMacro($hostMacro);
			$this->checkHostId($hostMacro);
			$this->checkValue($hostMacro);
			$this->checkUnsupportedFields('hostmacro', $hostMacro,
				_s('Wrong fields for macro "%1$s".', $hostMacro['macro']));
		}

		$this->checkDuplicateMacros($hostMacros);

		// check if the macros exist
		$this->checkIfHostMacrosExistIn(zbx_objectValues($hostMacros, 'hostmacroid'), $dbHostMacros);

		$this->checkIfHostMacrosDontRepeat($hostMacros);
	}

	/**
	 * Update host macros
	 *
	 * @param array $hostMacros an array of host macros
	 *
	 * @return boolean
	 */
	public function update($hostMacros) {
		$hostMacros = zbx_toArray($hostMacros);

		$this->validateUpdate($hostMacros);

		$data = array();
		foreach ($hostMacros as $macro) {
			$hostMacroId = $macro['hostmacroid'];
			unset($macro['hostmacroid']);

			$data[] = array(
				'values' => $macro,
				'where' => array('hostmacroid' => $hostMacroId)
			);
		}

		DB::update('hostmacro', $data);

		return array('hostmacroids' => zbx_objectValues($hostMacros, 'hostmacroid'));
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $hostMacroIds
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateDelete(array $hostMacroIds) {
		if (!$hostMacroIds) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$dbHostMacros = API::getApi()->select('hostmacro', array(
			'output' => array('hostid', 'hostmacroid'),
			'hostmacroids' => $hostMacroIds
		));

		// check permissions for all affected hosts
		$this->checkHostPermissions(array_unique(zbx_objectValues($dbHostMacros, 'hostid')));

		// check if the macros exist
		$this->checkIfHostMacrosExistIn($hostMacroIds, $dbHostMacros);
	}

	/**
	 * Remove Macros from Hosts
	 *
	 * @param mixed $hostMacroIds
	 *
	 * @return boolean
	 */
	public function delete($hostMacroIds) {
		$hostMacroIds = zbx_toArray($hostMacroIds);

		$this->validateDelete($hostMacroIds);

		DB::delete('hostmacro', array('hostmacroid' => $hostMacroIds));

		return array('hostmacroids' => $hostMacroIds);
	}

	/**
	 * Get macros
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function getMacros($data) {
		$macros = $data['macros'];
		$itemid = isset($data['itemid']) ? $data['itemid'] : null;
		$triggerid = isset($data['triggerid']) ? $data['triggerid'] : null;

		zbx_value2array($macros);
		$macros = array_unique($macros);

		$result = array();

		$hosts = API::Host()->get(array(
			'itemids' => $itemid,
			'triggerids' => $triggerid,
			'nopermissions' => true,
			'preservekeys' => true,
			'output' => array('hostid'),
			'templated_hosts' => true
		));
		$hostIds = array_keys($hosts);

		do {
			$hostMacros = $this->get(array(
				'hostids' => $hostIds,
				'macros' => $macros,
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => true,
				'preservekeys' => true
			));
			order_result($hostMacros, 'hostid');

			foreach ($macros as $mnum => $macro) {
				foreach ($hostMacros as $hmnum => $hmacro) {
					if ($macro == $hmacro['macro']) {
						$result[$macro] = $hmacro['value'];
						unset($hostMacros[$hmnum], $macros[$mnum]);
						break;
					}
				}
			}

			if (!empty($macros)) {
				$hosts = API::Template()->get(array(
					'hostids' => $hostIds,
					'nopermissions' => true,
					'preservekeys' => true,
					'output' => array('templateid')
				));
				$hostIds = array_keys($hosts);
			}
		} while (!empty($macros) && !empty($hostIds));


		if (!empty($macros)) {
			$gmacros = $this->get(array(
				'output' => API_OUTPUT_EXTEND,
				'globalmacro' => true,
				'nopermissions' => true,
				'macros' => $macros
			));

			foreach ($macros as $macro) {
				foreach ($gmacros as $mid => $gmacro) {
					if ($macro == $gmacro['macro']) {
						$result[$macro] = $gmacro['value'];
						unset($gmacros[$mid]);
						break;
					}
				}
			}
		}

		return $result;
	}

	public function resolveTrigger($triggers) {
		$single = false;
		if (isset($triggers['triggerid'])) {
			$single = true;
			$triggers = array($triggers);
		}

		foreach ($triggers as $num => $trigger) {
			if (!isset($trigger['triggerid']) || !isset($trigger['expression'])) continue;

			if ($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $trigger['expression'], $arr)) {
				$macros = $this->getMacros(array('macros' => $arr[1], 'triggerid' => $trigger['triggerid']));

				$search = array_keys($macros);
				$values = array_values($macros);

				$triggers[$num]['expression'] = str_replace($search, $values, $trigger['expression']);
			}
		}

		if ($single) {
			$triggers = reset($triggers);
		}

		return $triggers;
	}

	public function resolveItem($items) {
		$single = false;
		if (isset($items['itemid'])) {
			$single = true;
			$items = array($items);
		}

		foreach ($items as $num => $item) {
			if (!isset($item['itemid']) || !isset($item['key_'])) continue;

			if ($res = preg_match_all('/'.ZBX_PREG_EXPRESSION_USER_MACROS.'/', $item['key_'], $arr)) {
				$macros = $this->getMacros(array('macros' => $arr[1],'itemid' => $item['itemid']));

				$search = array_keys($macros);
				$values = array_values($macros);
				$items[$num]['key_'] = str_replace($search, $values, $item['key_']);
			}
		}

		if ($single) {
			$items = $items[0];
		}

		return $items;
	}

	/**
	 * Replace macros on hosts/templates.
	 * $macros input array has hostid as key and array of that host macros as value.
	 *
	 * @param array $macros
	 *
	 * @return void
	 */
	public function replaceMacros(array $macros) {
		$hostIds = array_keys($macros);
		if (!API::Host()->isWritable($hostIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$dbMacros = API::Host()->get(array(
			'hostids' => $hostIds,
			'selectMacros' => API_OUTPUT_EXTEND,
			'templated_hosts' => true,
			'output' => API_OUTPUT_REFER,
			'preservekeys' => true
		));

		$macroIdsToDelete = array();
		$macrosToUpdate = array();
		$macrosToAdd = array();

		foreach ($macros as $hostid => $hostMacros) {
			$dbHostMacros = $dbMacros[$hostid]['macros'];

			// look for db macros which hostmacroids are not in list of new macros
			// if there are any, they should be deleted
			$hostMacroIds = zbx_toHash($hostMacros, 'hostmacroid');
			foreach ($dbHostMacros as $dbHostMacro) {

				if (!isset($hostMacroIds[$dbHostMacro['hostmacroid']])) {
					$macroIdsToDelete[] = $dbHostMacro['hostmacroid'];
				}
			}

			// if macro has hostmacrid it should be updated otherwise created as new
			foreach ($hostMacros as $hostMacro) {
				if (isset($hostMacro['hostmacroid']) && isset($dbHostMacros[$hostMacro['hostmacroid']])) {
					$macrosToUpdate[] = $hostMacro;
				}
				else {
					$hostMacro['hostid'] = $hostid;
					$macrosToAdd[] = $hostMacro;
				}
			}
		}

		if ($macroIdsToDelete) {
			$this->delete($macroIdsToDelete);
		}
		if ($macrosToAdd) {
			$this->create($macrosToAdd);
		}
		if ($macrosToUpdate) {
			$this->update($macrosToUpdate);
		}
	}

	/**
	 * Validates the "macro" field.
	 *
	 * @param array $macro
	 *
	 * @throws APIException if the field is empty, too long or doesn't match the ZBX_PREG_EXPRESSION_USER_MACROS
	 * regex.
	 */
	protected function checkMacro(array $macro) {
		if (!isset($macro['macro']) || zbx_empty($macro['macro'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty macro.'));
		}
		if (zbx_strlen($macro['macro']) > 64) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro name "%1$s" is too long, it should not exceed 64 chars.', $macro['macro']));
		}
		if (!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $macro['macro'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong macro "%1$s".', $macro['macro']));
		}
	}

	/**
	 * Validate the "value" field.
	 *
	 * @param array $macro
	 *
	 * @throws APIException if the field is too long.
	 */
	protected function checkValue(array $macro) {
		if (isset($macro['value']) && zbx_strlen($macro['value']) > 255) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" value is too long, it should not exceed 255 chars.', $macro['macro']));
		}
	}

	/**
	 * Validates the "hostid" field.
	 *
	 * @param array $macro
	 *
	 * @throws APIException if the field is empty.
	 */
	protected function checkHostId(array $macro) {
		if (!isset($macro['hostid']) || zbx_empty($macro['hostid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('No host given for macro "%1$s".', $macro['macro']));
		}
		if (!is_numeric($macro['hostid'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid hostid for macro "%1$s".', $macro['macro']));
		}
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts
	 *
	 * @param array $hostIds    an array of host or template IDs
	 */
	protected function checkHostPermissions(array $hostIds) {
		if (!API::Host()->isWritable($hostIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Checks if the given macros contain duplicates. Assumes the "macro" field is valid.
	 *
	 * @throws APIException if the given macros contain duplicates
	 *
	 * @param array $macros
	 *
	 * @return void
	 */
	protected function checkDuplicateMacros(array $macros) {
		$existingMacros = array();
		foreach ($macros as $macro) {
			// global macros don't have hostid
			$hostid = isset($macro['hostid']) ? $macro['hostid'] : 1;

			if (isset($existingMacros[$hostid][$macro['macro']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" is not unique.', $macro['macro']));
			}

			$existingMacros[$hostid][$macro['macro']] = 1;
		}
	}

	/**
	 * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
	 * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
	 * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
	 *
	 * @param array $hostMacros
	 *
	 * @throws APIException if any of the given macros already exist
	 */
	protected function checkIfHostMacrosDontRepeat(array $hostMacros) {
		$dbHostMacros = API::getApi()->select($this->tableName(), array(
			'output' => array('hostmacroid', 'hostid', 'macro'),
			'filter' => array(
				'macro' => zbx_objectValues($hostMacros, 'macro'),
				'hostid' => array_unique(zbx_objectValues($hostMacros, 'hostid'))
			)
		));

		foreach ($hostMacros as $hostMacro) {
			foreach ($dbHostMacros as $dbHostMacro) {
				$differentMacros = ((isset($hostMacro['hostmacroid'])
					&& bccomp($hostMacro['hostmacroid'], $dbHostMacro['hostmacroid']) != 0)
					|| !isset($hostMacro['hostmacroid']));

				if ($hostMacro['macro'] == $dbHostMacro['macro'] && bccomp($hostMacro['hostid'], $dbHostMacro['hostid']) == 0
						&& $differentMacros) {

					$hosts = API::getApi()->select('hosts', array(
						'output' => array('name'),
						'hostids' => $hostMacro['hostid']
					));
					$host = reset($hosts);
					$error = _s('Macro "%1$s" already exists on "%2$s".', $hostMacro['macro'], $host['name']);
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
	}

	/**
	 * Checks if all of the host macros with hostmacrosids given in $hostMacrosIds are present in $hostMacros.
	 * Assumes the "hostmacroid" field is valid.
	 *
	 * @param array $hostMacrosIds
	 * @param array $hostMacros
	 *
	 * @throws APIException if any of the host macros is not present in $hostMacros
	 */
	protected function checkIfHostMacrosExistIn(array $hostMacrosIds, array $hostMacros) {
		$hostMacros = zbx_toHash($hostMacros, 'hostmacroid');
		foreach ($hostMacrosIds as $hostMacroId) {
			if (!isset($hostMacros[$hostMacroId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro with hostmacroid "%1$s" does not exist.', $hostMacroId));
			}
		}
	}

	/**
	 * Checks if any of the given host global macros already exist. If the macros are updated and
	 * the "globalmacroid" field is set, the method will only fail, if a macro with a different globalmacroid exists.
	 * Assumes the "macro", "hostmacroid" fields are valid.
	 *
	 * @param array $globalMacros
	 *
	 * @throws APIException if any of the given macros already exist
	 */
	protected function checkIfGlobalMacrosDontRepeat(array $globalMacros) {
		$nameMacro = zbx_toHash($globalMacros, 'macro');
		$macroNames = zbx_objectValues($globalMacros, 'macro');
		if ($macroNames) {
			$dbMacros = API::getApi()->select('globalmacro', array(
				'filter' => array('macro' => $macroNames),
				'output' => array('globalmacroid', 'macro')
			));
			foreach ($dbMacros as $dbMacro) {
				$macro = $nameMacro[$dbMacro['macro']];
				if (!isset($macro['globalmacroid']) || bccomp($macro['globalmacroid'], $dbMacro['globalmacroid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists.', $dbMacro['macro']));
				}
			}
		}
	}

	/**
	 * Checks if the user has the permissions to edit global macros.
	 *
	 * @param string $error a message that will be used as the error text
	 *
	 * @throws APIException if the user doesn't have the required permissions
	 */
	protected function checkGlobalMacrosPermissions($error) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, $error);
		}
	}

	/**
	 * Checks if all of the global macros with globalmacroids given in $globalMacroIds are present in $globalMacros.
	 * Assumes the "globalmacroids" field is valid.
	 *
	 * @param array $globalMacroIds
	 *
	 * @throws APIException if any of the global macros is not present in $globalMacros
	 */
	protected function checkIfGlobalMacrosExist(array $globalMacroIds) {
		$globalMacros = API::getApi()->select('globalmacro', array(
			'output' => array('globalmacroid'),
			'globalmacroids' => $globalMacroIds
		));
		$globalMacros = zbx_toHash($globalMacros, 'globalmacroid');
		foreach ($globalMacroIds as $globalMacroId) {
			if (!isset($globalMacros[$globalMacroId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro with globalmacroid "%1$s" does not exist.', $globalMacroId));
			}
		}
	}
}
