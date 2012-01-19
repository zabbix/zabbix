<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php
/**
 * @package API
 */

class CUserMacro extends CZBXAPI {

	protected $tableName = 'hostmacro';

	protected $tableAlias = 'hm';

	/**
	 * Get UserMacros data
	 *
	 * @param array $options
	 * @param array $options['nodeids'] Node IDs
	 * @param array $options['groupids'] UserMacrosGroup IDs
	 * @param array $options['macroids'] UserMacros IDs
	 * @param boolean $options['monitored_macros'] only monitored UserMacros
	 * @param boolean $options['templated_macros'] include templates in result
	 * @param boolean $options['with_items'] only with items
	 * @param boolean $options['with_monitored_items'] only with monitored items
	 * @param boolean $options['with_historical_items'] only with historical items
	 * @param boolean $options['with_triggers'] only with triggers
	 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
	 * @param boolean $options['with_httptests'] only with http tests
	 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
	 * @param boolean $options['with_graphs'] only with graphs
	 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
	 * @param int $options['count'] count UserMacros, returned column name is rowscount
	 * @param string $options['pattern'] search macros by pattern in macro names
	 * @param int $options['limit'] limit selection
	 * @param string $options['order'] deprecated parameter (for now)
	 * @return array|boolean UserMacros data as array or false if error
	 */
	public function get($options = array()) {
		$result = array();
		$user_type = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sort_columns = array('macro');

		// allowed output options for [ select_* ] params
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND);

		$sql_parts = array(
			'select'	=> array('macros' => 'hm.hostmacroid'),
			'from'		=> array('hostmacro hm'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$sql_parts_global = array(
			'select'	=> array('macros' => 'gm.globalmacroid'),
			'from'		=> array('globalmacro gm'),
			'where'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$def_options = array(
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
		$options = zbx_array_merge($def_options, $options);

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $user_type || $options['nopermissions']) {
		}
		elseif (!is_null($options['editable']) && !is_null($options['globalmacro'])) {
			return array();
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['hgh'] = 'hg.hostid=hm.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS('.
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
			$sql_parts_global['where'][] = DBcondition('gm.globalmacroid', $options['globalmacroids']);
		}

		// hostmacroids
		if (!is_null($options['hostmacroids'])) {
			zbx_value2array($options['hostmacroids']);
			$sql_parts['where'][] = DBcondition('hm.hostmacroid', $options['hostmacroids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=hm.hostid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sql_parts['where'][] = DBcondition('hm.hostid', $options['hostids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);
			if ($options['output'] != API_OUTPUT_SHORTEN) {
				$sql_parts['select']['templateid'] = 'ht.templateid';
			}
			$sql_parts['from']['macros_templates'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.templateid', $options['templateids']);
			$sql_parts['where']['hht'] = 'hm.hostid=ht.hostid';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('hostmacro hm', $options, $sql_parts);
			zbx_db_search('globalmacro gm', $options, $sql_parts_global);
		}

		// filter
		if (is_array($options['filter'])) {
			if (isset($options['filter']['macro'])) {
				zbx_value2array($options['filter']['macro']);

				$sql_parts['where'][] = DBcondition('hm.macro', $options['filter']['macro']);
				$sql_parts_global['where'][] = DBcondition('gm.macro', $options['filter']['macro']);
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sql_parts['select']['macros'] = 'hm.*';
			$sql_parts_global['select']['macros'] = 'gm.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT hm.hostmacroid) as rowscount');
			$sql_parts_global['select'] = array('count(DISTINCT gm.globalmacroid) as rowscount');
		}

		// sorting
		zbx_db_sorting($sql_parts, $options, $sort_columns, 'hm');
		zbx_db_sorting($sql_parts_global, $options, $sort_columns, 'gm');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
			$sql_parts_global['limit'] = $options['limit'];
		}

		// init GLOBALS
		if (!is_null($options['globalmacro'])) {
			$sql_parts_global['select'] = array_unique($sql_parts_global['select']);
			$sql_parts_global['from'] = array_unique($sql_parts_global['from']);
			$sql_parts_global['where'] = array_unique($sql_parts_global['where']);
			$sql_parts_global['order'] = array_unique($sql_parts_global['order']);

			$sql_select = '';
			$sql_from = '';
			$sql_where = '';
			$sql_order = '';
			if (!empty($sql_parts_global['select'])) {
				$sql_select .= implode(',', $sql_parts_global['select']);
			}
			if (!empty($sql_parts_global['from'])) {
				$sql_from .= implode(',', $sql_parts_global['from']);
			}
			if (!empty($sql_parts_global['where'])) {
				$sql_where .= ' AND '.implode(' AND ', $sql_parts_global['where']);
			}
			if (!empty($sql_parts_global['order'])) {
				$sql_order .= ' ORDER BY '.implode(',', $sql_parts_global['order']);
			}
			$sql_limit = $sql_parts_global['limit'];

			$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.'
					FROM '.$sql_from.'
					WHERE '.DBin_node('gm.globalmacroid', $nodeids).
						$sql_where.
						$sql_order;
			$res = DBselect($sql, $sql_limit);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$globalmacroids[$macro['globalmacroid']] = $macro['globalmacroid'];

					if ($options['output'] == API_OUTPUT_SHORTEN) {
						$result[$macro['globalmacroid']] = array('globalmacroid' => $macro['globalmacroid']);
					}
					else {
						if (!isset($result[$macro['globalmacroid']])) {
							$result[$macro['globalmacroid']] = array();
						}
						$result[$macro['globalmacroid']] += $macro;
					}
				}
			}
		}
		// init HOSTS
		else {
			$hostids = array();

			$sql_parts['select'] = array_unique($sql_parts['select']);
			$sql_parts['from'] = array_unique($sql_parts['from']);
			$sql_parts['where'] = array_unique($sql_parts['where']);
			$sql_parts['order'] = array_unique($sql_parts['order']);

			$sql_select = '';
			$sql_from = '';
			$sql_where = '';
			$sql_order = '';
			if (!empty($sql_parts['select'])) {
				$sql_select .= implode(',', $sql_parts['select']);
			}
			if (!empty($sql_parts['from'])) {
				$sql_from .= implode(',', $sql_parts['from']);
			}
			if (!empty($sql_parts['where'])) {
				$sql_where .= ' AND '.implode(' AND ', $sql_parts['where']);
			}
			if (!empty($sql_parts['order'])) {
				$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
			}
			$sql_limit = $sql_parts['limit'];

			$sql = 'SELECT '.$sql_select.'
					FROM '.$sql_from.'
					WHERE '.DBin_node('hm.hostmacroid', $nodeids).
						$sql_where.
						$sql_order;
			$res = DBselect($sql, $sql_limit);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$hostmacroids[$macro['hostmacroid']] = $macro['hostmacroid'];

					if ($options['output'] == API_OUTPUT_SHORTEN) {
						$result[$macro['hostmacroid']] = $macro['hostmacroid'];
					}
					else {
						$hostids[$macro['hostid']] = $macro['hostid'];

						if (!isset($result[$macro['hostmacroid']])) {
							$result[$macro['hostmacroid']]= array();
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
						}
						$result[$macro['hostmacroid']] += $macro;
					}
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
		if (!is_null($options['selectGroups']) && str_in_array($options['selectGroups'], $subselects_allowed_outputs)) {
			$obj_params = array(
				'output' => $options['selectGroups'],
				'hostids' => $hostids,
				'preservekeys' => true
			);
			$groups = API::HostGroup()->get($obj_params);
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
		if (!is_null($options['selectTemplates']) && str_in_array($options['selectTemplates'], $subselects_allowed_outputs)) {
			$obj_params = array(
				'output' => $options['selectTemplates'],
				'hostids' => $hostids,
				'preservekeys' => true
			);
			$templates = API::Template()->get($obj_params);
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
		if (!is_null($options['selectHosts']) && str_in_array($options['selectHosts'], $subselects_allowed_outputs)) {
			$obj_params = array(
				'output' => $options['selectHosts'],
				'hostids' => $hostids,
				'preservekeys' => true
			);
			$hosts = API::Host()->get($obj_params);
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
 * Delete UserMacros
 *
 * @param array $hostmacroids
 * @param array $hostmacroids['hostmacroids']
 * @return boolean
 */
	public function deleteHostMacro($hostmacroids) {
		$hostmacroids = zbx_toArray($hostmacroids);

		if (empty($hostmacroids))
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Empty input parameter [ hostmacroids ]');

// permissions + existance
		$options = array(
			'hostmacroids' => $hostmacroids,
			'editable' => 1,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => 1
		);
		$db_hmacros = $this->get($options);

		foreach ($hostmacroids as $hostmacroid) {
			if (!isset($db_hmacros[$hostmacroid]))
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
//--------

		$sql = 'DELETE FROM hostmacro WHERE '.DBcondition('hostmacroid', $hostmacroids);
		if (!DBExecute($sql))
			self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		return array('hostmacroids' => $hostmacroids);
	}

/**
 * Add global macros.
 *
 * @param array $macros
 * @param string $macros[0..]['macro']
 * @param string $macros[0..]['value']
 * @return array
 */
	public function createGlobal(array $macros) {
		if (USER_TYPE_SUPER_ADMIN != self::$userData['type']) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create global macros.'));
		}

		$macros = zbx_toArray($macros);

		$this->validateGlobal($macros);

		$globalmacroids = DB::insert('globalmacro', $macros);

		return array('globalmacroids' => $globalmacroids);
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

		// permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update global macros.'));
		}

		$this->validateGlobal($globalmacros);

		// existence
		$ids = zbx_objectValues($globalmacros, 'globalmacroid');
		$dbGmacros = $this->get(array(
			'globalmacroids' => $ids,
			'globalmacro' => true,
			'editable' => true,
			'output'=> API_OUTPUT_EXTEND,
			'preservekeys' => true
		));
		foreach ($globalmacros as $gmacro) {
			// check if the macro has an id
			if (!isset($gmacro['globalmacroid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
			// check if the macro exists in the DB
			if (!isset($dbGmacros[$gmacro['globalmacroid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro with globalmacroid "%1$s" does not exist.', $gmacro['globalmacroid']));
			}
		}

		// update macros
		$data = array();
		foreach ($globalmacros as $gmacro) {
			$globalmacroid = $gmacro['globalmacroid'];
			unset($gmacro['globalmacroid']);

			$data[] = array(
				'values'=> $gmacro,
				'where'=> array('globalmacroid' => $globalmacroid)
			);
		}
		DB::update('globalmacro', $data);

		return array('globalmacroids' => $ids);
	}


	/**
	 * Delete global macros.
	 *
	 * @param mixed $globalmacroIds
	 *
	 * @return array
	 */
	public function deleteGlobal($globalmacroIds) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete global macros.'));
		}

		$globalmacroIds = zbx_toArray($globalmacroIds);

		if (empty($globalmacroIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// existence
		$dbGmacros = $this->get(array(
			'globalmacroids' => $globalmacroIds,
			'globalmacro' => true,
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'preservekeys' => true
		));
		foreach ($globalmacroIds as $gmacroId) {
			if (!isset($dbGmacros[$gmacroId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Global macro with globalmacroid "%1$s" does not exist.', $gmacroId));
			}
		}

		// delete macros
		DB::delete('globalmacro', array('globalmacroid' => $globalmacroIds));

		return array('globalmacroids' => $globalmacroIds);
	}

	/**
	 * Validates macros expression
	 *
	 * @param array $macros array with macros expressions
	 * @return boolean
	 */
	private function validate($macros) {
		$tmp = array();
		foreach ($macros as $macro) {
			if (isset($tmp[$macro['macro']]))
				self::exception(ZBX_API_ERROR_PARAMETERS, '['.$macro['macro'].']: not unique');
			else
				$tmp[$macro['macro']] = 1;
		}

		foreach ($macros as $mnum => $macro) {
			if (zbx_empty($macro['value'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Empty value for macro "%1$s".', $macro['macro']));
			}
			if (zbx_strlen($macro['macro']) > 64) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro name "%1$s" is too long, it should not exceed 64 chars.', $macro['macro']));
			}

			if (zbx_strlen($macro['value']) > 255) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" value is too long, it should not exceed 255 chars.', $macro['macro']));
			}

			if (!preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $macro['macro'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Wrong macro "%1$s".', $macro['macro']));
			}
		}

		return true;
	}


	/**
	 * Performs global macro validation.
	 *
	 * @param array $macros
	 */
	protected function validateGlobal(array $macros) {
		$this->validate($macros);

		// check for duplicate names
		$nameMacro = zbx_toHash($macros, 'macro');
		$macroNames = zbx_objectValues($macros, 'macro');
		if ($macroNames) {
			$options = array(
				'globalmacro' => true,
				'filter' => array(
					'macro' => $macroNames
				),
				'output' => API_OUTPUT_EXTEND
			);
			$dbMacros = $this->get($options);
			foreach ($dbMacros as $dbMacro) {
				$macro = $nameMacro[$dbMacro['macro']];
				if (!isset($macro['globalmacroid']) || bccomp($macro['globalmacroid'], $dbMacro['globalmacroid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists.', $dbMacro['macro']));
				}
			}
		}
	}

/**
 * Add Macros to Hosts
 *
 * @param array $data
 * @param array $data['templates']
 * @param array $data['hosts']
 * @param array $data['macros']
 * @return boolean
 */
	public function massAdd($data) {
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : array();
		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : array();

		$hostids = zbx_objectValues($hosts, 'hostid');
		$templateids = zbx_objectValues($templates, 'templateid');

		if (!isset($data['macros']) || empty($data['macros'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ macros ]');
		}
		elseif (empty($hosts) && empty($templates)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ hosts ] or [ templates ]');
		}

		// Host permission
		if (!empty($hosts)) {
			$upd_hosts = API::Host()->get(array(
				'hostids' => $hostids,
				'editable' => true,
				'output' => array('hostid', 'name'),
				'preservekeys' => true
			));
			foreach ($hosts as $host) {
				if (!isset($upd_hosts[$host['hostid']])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
		}

		// Template permission
		if (!empty($templates)) {
			$upd_templates = API::Template()->get(array(
				'templateids' => $templateids,
				'editable' => true,
				'output' => array('hostid', 'name'),
				'preservekeys' => true
			));
			foreach ($templates as $template) {
				if (!isset($upd_templates[$template['templateid']])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
		}

		// Check on existing
		$objectids = array_merge($hostids, $templateids);
		$existing_macros = $this->get(array(
			'hostids' => $objectids,
			'filter' => array('macro' => zbx_objectValues($data['macros'], 'macro')),
			'output' => API_OUTPUT_EXTEND,
			'limit' => 1
		));
		foreach ($existing_macros as $exst_macro) {
			if (isset($upd_hosts[$exst_macro['hostid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists on "%2$s".',
						$exst_macro['macro'], $upd_hosts[$exst_macro['hostid']]['name']));
			}
			elseif (isset($upd_templates[$exst_macro['hostid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists on "%2$s".',
						$exst_macro['macro'], $upd_templates[$exst_macro['hostid']]['name']));
			}
		}

		self::validate($data['macros']);

		$insertData = array();
		foreach ($data['macros'] as $macro) {
			foreach ($objectids as $hostid) {
				$insertData[] = array(
					'hostid' => $hostid,
					'macro' => $macro['macro'],
					'value' => $macro['value']
				);
			}
		}

		$hostmacroids = DB::insert('hostmacro', $insertData);

		return array('hostmacroids' => $hostmacroids);
	}

/**
 * Remove Macros from Hosts
 *
 * @param array $data
 * @param array $data['hostids']
 * @param array $data['templateids']
 * @return boolean
 */
	public function massRemove($data) {

			$macros = zbx_toArray($data['macros'], 'macro');

			$hostids = isset($data['hostids']) ? zbx_toArray($data['hostids']) : array();
			$templateids = isset($data['templateids']) ? zbx_toArray($data['templateids']) : array();
			$objectids = array_merge($hostids, $templateids);

// Check on existing
			$options = array(
				'hostids' => $objectids,
				'templated_hosts' => 1,
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			);
			$db_objects = API::Host()->get($options);

			foreach ($objectids as $objectid) {
				if (!isset($db_objects[$objectid]))
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			$options = array(
				'hostids' => $objectids,
				'filter' => array('macro' => $macros),
				'nopermissions' => true,
				'output' => API_OUTPUT_SHORTEN,
				'preservekeys' => true
			);
			$db_macros = $this->get($options);
			$hostmacroids = array_keys($db_macros);

			DB::delete('hostmacro', array('hostmacroid'=>$hostmacroids));

			return array('hostmacroids' => $hostmacroids);
	}

/**
 * Remove Macros from Hosts
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @param array $data['templates']
 * @return boolean
 */
	public function massUpdate($data) {
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : array();
		$hostids = zbx_objectValues($hosts, 'hostid');

		$templates = isset($data['templates']) ? zbx_toArray($data['templates']) : array();
		$templateids = zbx_objectValues($templates, 'templateid');

			if (!isset($data['macros']) || empty($data['macros']))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ macros ]');
			else if (empty($hosts) && empty($templates))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Not set input parameter [ hosts ] or [ templates ]');

			if (!empty($hosts)) {
// Host permission
				$options = array(
					'hostids' => $hostids,
					'editable' => 1,
					'output' => array('hostid', 'host'),
					'preservekeys' => 1
				);
				$upd_hosts = API::Host()->get($options);
				foreach ($hosts as $hnum => $host) {
					if (!isset($upd_hosts[$host['hostid']]))
						self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
//--
			}

			if (!empty($templates)) {
// Template permission
				$options = array(
					'templateids' => $templateids,
					'editable' => 1,
					'output' => array('hostid', 'host'),
					'preservekeys' => 1
				);
				$upd_templates = API::Template()->get($options);
				foreach ($templates as $tnum => $template) {
					if (!isset($upd_templates[$template['templateid']]))
						self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
//--
			}

			$objectids = array_merge($hostids, $templateids);

// first we need to validate input data
			$this->validate($data['macros']);

// Check on existing
			$options = array(
				'hostids' => $objectids,
				'filter' => array('macro' => zbx_objectValues($data['macros'], 'macro')),
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND
			);
			$db_macros = $this->get($options);
//--

			$updateMacros = zbx_toHash($data['macros'], 'macro');

			$hostmacroids = array();
			$data_update = array();

			foreach ($db_macros as $dbnum => $db_macro) {
				$hostmacroids[] = $db_macro['hostmacroid'];
				$data_update[] = array(
					'values' => array('value' => $updateMacros[$db_macro['macro']]['value']),
					'where' => array('hostmacroid' => $db_macro['hostmacroid'])
				);
			}

			DB::update('hostmacro', $data_update);

			return array('hostmacroids' => $hostmacroids);
	}

// TODO: should be private
	public function getMacros($data) {
		$macros = $data['macros'];
		$itemid = isset($data['itemid']) ? $data['itemid'] : null;
		$triggerid = isset($data['triggerid']) ? $data['triggerid'] : null;

		zbx_value2array($macros);
		$macros = array_unique($macros);

		$result = array();

		$obj_options = array(
			'itemids' => $itemid,
			'triggerids' => $triggerid,
			'nopermissions' => true,
			'preservekeys' => true,
			'output' => API_OUTPUT_SHORTEN,
			'templated_hosts' => true,
		);
		$hosts = API::Host()->get($obj_options);
		$hostids = array_keys($hosts);

		do{
			$obj_options = array(
				'hostids' => $hostids,
				'macros' => $macros,
				'output' => API_OUTPUT_EXTEND,
				'nopermissions' => 1,
				'preservekeys' => 1,
			);
			$host_macros = $this->get($obj_options);
			order_result($host_macros, 'hostid');

			foreach ($macros as $mnum => $macro) {
				foreach ($host_macros as $hmnum => $hmacro) {
					if ($macro == $hmacro['macro']) {
						$result[$macro] = $hmacro['value'];
						unset($host_macros[$hmnum], $macros[$mnum]);
						break;
					}
				}
			}

			if (!empty($macros)) {
				$obj_options = array(
					'hostids' => $hostids,
					'nopermissions' => 1,
					'preservekeys' => 1,
					'output' => API_OUTPUT_SHORTEN,
				);
				$hosts = API::Template()->get($obj_options);
				$hostids = array_keys($hosts);
			}
		}while (!empty($macros) && !empty($hostids));


		if (!empty($macros)) {
			$obj_options = array(
				'output' => API_OUTPUT_EXTEND,
				'globalmacro' => 1,
				'nopermissions' => 1,
				'macros' => $macros
			);
			$gmacros = $this->get($obj_options);

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

		if ($single) $triggers = reset($triggers);
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

		if ($single) $items = $items[0];

		return $items;
	}
}
?>
