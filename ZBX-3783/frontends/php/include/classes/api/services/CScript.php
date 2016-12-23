<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Class containing methods for operations with scripts.
 */
class CScript extends CApiService {

	protected $tableName = 'scripts';
	protected $tableAlias = 's';
	protected $sortColumns = ['scriptid', 'name'];

	/**
	 * Get scripts data.
	 *
	 * @param array  $options
	 * @param array  $options['itemids']
	 * @param array  $options['hostids']	deprecated (very slow)
	 * @param array  $options['groupids']
	 * @param array  $options['triggerids']
	 * @param array  $options['scriptids']
	 * @param bool   $options['status']
	 * @param bool   $options['editable']
	 * @param bool   $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array
	 */
	public function get(array $options) {
		$result = [];
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['scripts' => 's.scriptid'],
			'from'		=> ['scripts s'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'				=> null,
			'hostids'				=> null,
			'scriptids'				=> null,
			'usrgrpids'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,
			// output
			'output'				=> API_OUTPUT_EXTEND,
			'selectGroups'			=> null,
			'selectHosts'			=> null,
			'countOutput'			=> null,
			'preservekeys'			=> null,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + permission check
		if ($userType != USER_TYPE_SUPER_ADMIN) {
			if (!is_null($options['editable'])) {
				return $result;
			}

			$userGroups = getUserGroupsByUserId($userid);

			$sqlParts['where'][] = '(s.usrgrpid IS NULL OR '.dbConditionInt('s.usrgrpid', $userGroups).')';
			$sqlParts['where'][] = '(s.groupid IS NULL OR EXISTS ('.
					'SELECT NULL'.
					' FROM rights r'.
					' WHERE s.groupid=r.id'.
						' AND '.dbConditionInt('r.groupid', $userGroups).
					' GROUP BY r.id'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
					'))';
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['where'][] = '(s.groupid IS NULL OR '.dbConditionInt('s.groupid', $options['groupids']).')';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			// return scripts that are assigned to the hosts' groups or to no group
			$hostGroups = API::HostGroup()->get([
				'output' => ['groupid'],
				'hostids' => $options['hostids']
			]);
			$hostGroupIds = zbx_objectValues($hostGroups, 'groupid');

			$sqlParts['where'][] = '('.dbConditionInt('s.groupid', $hostGroupIds).' OR s.groupid IS NULL)';
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['where'][] = '(s.usrgrpid IS NULL OR '.dbConditionInt('s.usrgrpid', $options['usrgrpids']).')';
		}

		// scriptids
		if (!is_null($options['scriptids'])) {
			zbx_value2array($options['scriptids']);

			$sqlParts['where'][] = dbConditionInt('s.scriptid', $options['scriptids']);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('scripts s', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('scripts s', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($script = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $script['rowscount'];
			}
			else {
				$result[$script['scriptid']] = $script;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['groupid', 'host_access'], $options['output']);
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array $scripts
	 *
	 * @return array
	 */
	public function create(array $scripts) {
		$this->validateCreate($scripts);

		$scriptids = DB::insert('scripts', $scripts);

		foreach ($scripts as $index => &$script) {
			$script['scriptid'] = $scriptids[$index];
		}
		unset($script);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_SCRIPT, $scripts);

		return ['scriptids' => $scriptids];
	}

	/**
	 * @param array $scripts
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreate(array &$scripts) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_SCRIPT_NAME, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('scripts', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI])],
			'execute_on' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER])],
			'command' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'description')],
			'usrgrpid' =>		['type' => API_ID],
			'groupid' =>		['type' => API_ID],
			'host_access' =>	['type' => API_INT32, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])],
			'confirmation' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'confirmation')]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $scripts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$scripts = $this->checkExecutionType($scripts);
		$this->checkUserGroups($scripts);
		$this->checkHostGroups($scripts);
		$this->checkDuplicates($scripts);
	}

	/**
	 * @param array $scripts
	 *
	 * @return array
	 */
	public function update(array $scripts) {
		$this->validateUpdate($scripts, $db_scripts);

		$upd_scripts = [];

		foreach ($scripts as $script) {
			$db_script = $db_scripts[$script['scriptid']];

			$upd_script = [];

			// strings
			foreach (['name', 'command', 'description', 'confirmation'] as $field_name) {
				if (array_key_exists($field_name, $script) && $script[$field_name] !== $db_script[$field_name]) {
					$upd_script[$field_name] = $script[$field_name];
				}
			}
			// integers
			foreach (['type', 'execute_on', 'usrgrpid', 'groupid', 'host_access'] as $field_name) {
				if (array_key_exists($field_name, $script) && $script[$field_name] != $db_script[$field_name]) {
					$upd_script[$field_name] = $script[$field_name];
				}
			}

			if ($upd_script) {
				$upd_scripts[] = [
					'values' => $upd_script,
					'where' => ['scriptid' => $script['scriptid']]
				];
			}
		}

		if ($upd_scripts) {
			DB::update('scripts', $upd_scripts);
		}

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCRIPT, $scripts, $db_scripts);

		return ['scriptids' => zbx_objectValues($scripts, 'scriptid')];
	}

	/**
	 * @param array $scripts
	 * @param array $db_scripts
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateUpdate(array &$scripts, array &$db_scripts = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['scriptid'], ['name']], 'fields' => [
			'scriptid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_SCRIPT_NAME, 'length' => DB::getFieldLength('scripts', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI])],
			'execute_on' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER])],
			'command' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'description')],
			'usrgrpid' =>		['type' => API_ID],
			'groupid' =>		['type' => API_ID],
			'host_access' =>	['type' => API_INT32, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])],
			'confirmation' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'confirmation')]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $scripts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_scripts = DB::select('scripts', [
			'output' => ['scriptid', 'name', 'type', 'execute_on', 'command', 'description', 'usrgrpid', 'groupid',
				'host_access', 'confirmation'
			],
			'scriptids' => zbx_objectValues($scripts, 'scriptid'),
			'preservekeys' => true
		]);

		$new_name_scripts = [];

		foreach ($scripts as $script) {
			if (!array_key_exists($script['scriptid'], $db_scripts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_script = $db_scripts[$script['scriptid']];

			if (array_key_exists('name', $script) && trimPath($script['name']) !== trimPath($db_script['name'])) {
				$new_name_scripts[] = $script;
			}
		}

		$scripts = $this->checkExecutionType($scripts);
		$this->checkUserGroups($scripts);
		$this->checkHostGroups($scripts);
		if ($new_name_scripts) {
			$this->checkDuplicates($new_name_scripts);
		}
	}

	/**
	 * Validate incompatible options ZBX_SCRIPT_TYPE_IPMI and ZBX_SCRIPT_EXECUTE_ON_AGENT.
	 *
	 * @param array $scripts
	 *
	 * @return array
	 */
	private function checkExecutionType(array $scripts) {
		foreach ($scripts as &$script) {
			if (array_key_exists('type', $script) && $script['type'] == ZBX_SCRIPT_TYPE_IPMI) {
				if (!array_key_exists('execute_on', $script)) {
					$script['execute_on'] = ZBX_SCRIPT_EXECUTE_ON_SERVER;
				}
				elseif ($script['execute_on'] != ZBX_SCRIPT_EXECUTE_ON_SERVER) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('IPMI scripts can be executed only by server.'));
				}
			}
		}
		unset($script);

		return $scripts;
	}

	/**
	 * Check for valid user groups.
	 *
	 * @param array $scripts
	 * @param array $scripts[]['usrgrpid']  (optional)
	 *
	 * @throws APIException  if user group is not exists.
	 */
	private function checkUserGroups(array $scripts) {
		$usrgrpids = [];

		foreach ($scripts as $script) {
			if (array_key_exists('usrgrpid', $script) && $script['usrgrpid'] != 0) {
				$usrgrpids[$script['usrgrpid']] = true;
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys($usrgrpids);

		$db_usrgrps = DB::select('usrgrp', [
			'output' => [],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
		}
	}

	/**
	 * Check for valid host groups.
	 *
	 * @param array $scripts
	 * @param array $scripts[]['groupid']  (optional)
	 *
	 * @throws APIException  if host group is not exists.
	 */
	private function checkHostGroups(array $scripts) {
		$groupids = [];

		foreach ($scripts as $script) {
			if (array_key_exists('groupid', $script) && $script['groupid'] != 0) {
				$groupids[$script['groupid']] = true;
			}
		}

		if (!$groupids) {
			return;
		}

		$groupids = array_keys($groupids);

		$db_groups = DB::select('groups', [
			'output' => [],
			'groupids' => $groupids,
			'preservekeys' => true
		]);

		foreach ($groupids as $groupid) {
			if (!array_key_exists($groupid, $db_groups)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group with ID "%1$s" is not available.', $groupid));
			}
		}
	}

	/**
	 * Check for duplicated scripts.
	 *
	 * @param array  $scripts
	 * @param string $scripts['scriptid']
	 * @param string $scripts['name']
	 *
	 * @throws APIException  if global script already exists.
	 */
	private function checkDuplicates(array $scripts) {
		$db_scripts = DB::select('scripts', [
			'output' => ['scriptid', 'name']
		]);

		$uniq_names = [];

		foreach ($db_scripts as &$db_script) {
			$db_script['folders'] = array_map('trim', splitPath($db_script['name']));
			$uniq_names[implode('/', $db_script['folders'])] = true;
		}
		unset($db_script);

		foreach ($scripts as $script) {
			$folders = array_map('trim', splitPath($script['name']));
			$uniq_name = implode('/', $folders);

			if (array_key_exists($uniq_name, $uniq_names)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%1$s" already exists.', $script['name']));
			}
			$uniq_names[$uniq_name] = true;

			foreach ($db_scripts as $db_script) {
				if (array_key_exists('scriptid', $script) && bccomp($script['scriptid'], $db_script['scriptid']) == 0) {
					continue;
				}

				if (array_slice($folders, 0, count($db_script['folders'])) === $db_script['folders']) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Script menu path "%1$s" already used in script name "%2$s".', $script['name'],
							$db_script['name']
						)
					);
				}

				if (array_slice($db_script['folders'], 0, count($folders)) === $folders) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Script name "%1$s" already used in menu path for script "%2$s".', $script['name'],
							$db_script['name']
						)
					);
				}
			}
		}
	}

	/**
	 * @param array $scriptids
	 *
	 * @return array
	 */
	public function delete(array $scriptids) {
		$this->validateDelete($scriptids, $db_scripts);

		DB::delete('scripts', ['scriptid' => $scriptids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, $db_scripts);

		return ['scriptids' => $scriptids];
	}

	/**
	 * @param array $scriptids
	 * @param array $db_scripts
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateDelete(array &$scriptids, array &$db_scripts = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $scriptids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_scripts = DB::select('scripts', [
			'output' => ['scriptid', 'name'],
			'scriptids' => $scriptids,
			'preservekeys' => true
		]);

		foreach ($scriptids as $scriptid) {
			if (!array_key_exists($scriptid, $db_scripts)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// Check if deleted scripts used in actions.
		$db_actions = DBselect(
			'SELECT a.name,oc.scriptid'.
			' FROM opcommand oc,operations o,actions a'.
			' WHERE oc.operationid=o.operationid'.
				' AND o.actionid=a.actionid'.
				' AND '.dbConditionInt('oc.scriptid', $scriptids),
			1
		);

		if ($db_action = DBfetch($db_actions)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot delete scripts. Script "%1$s" is used in action operation "%2$s".',
					$db_scripts[$db_action['scriptid']]['name'], $db_action['name']
				)
			);
		}
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function execute(array $data) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'hostid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'scriptid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hosts = API::Host()->get([
			'output' => [],
			'hostids' => $data['hostid']
		]);
		if (!$db_hosts) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_scripts = $this->get([
			'output' => [],
			'hostids' => $data['hostid'],
			'scriptids' => $data['scriptid']
		]);
		if (!$db_scripts) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// execute script
		$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SCRIPT_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
		$result = $zabbix_server->executeScript($data['scriptid'], $data['hostid'], self::$userData['sessionid']);

		if ($result !== false) {
			// return the result in a backwards-compatible format
			return [
				'response' => 'success',
				'value' => $result
			];
		}
		else {
			self::exception(ZBX_API_ERROR_INTERNAL, $zabbix_server->getError());
		}
	}

	/**
	 * Returns all the scripts that are available on each given host.
	 *
	 * @param $hostIds
	 *
	 * @return array
	 */
	public function getScriptsByHosts($hostIds) {
		zbx_value2array($hostIds);

		$scriptsByHost = [];

		if (!$hostIds) {
			return $scriptsByHost;
		}

		foreach ($hostIds as $hostId) {
			$scriptsByHost[$hostId] = [];
		}

		$scripts = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => ['hostid'],
			'hostids' => $hostIds,
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		if ($scripts) {
			// resolve macros
			$macrosData = [];
			foreach ($scripts as $scriptId => $script) {
				if (!empty($script['confirmation'])) {
					foreach ($script['hosts'] as $host) {
						if (isset($scriptsByHost[$host['hostid']])) {
							$macrosData[$host['hostid']][$scriptId] = $script['confirmation'];
						}
					}
				}
			}
			if ($macrosData) {
				$macrosData = CMacrosResolverHelper::resolve([
					'config' => 'scriptConfirmation',
					'data' => $macrosData
				]);
			}

			foreach ($scripts as $scriptId => $script) {
				$hosts = $script['hosts'];
				unset($script['hosts']);
				// set script to host
				foreach ($hosts as $host) {
					$hostId = $host['hostid'];

					if (isset($scriptsByHost[$hostId])) {
						$size = count($scriptsByHost[$hostId]);
						$scriptsByHost[$hostId][$size] = $script;

						// set confirmation text with resolved macros
						if (isset($macrosData[$hostId][$scriptId]) && $script['confirmation']) {
							$scriptsByHost[$hostId][$size]['confirmation'] = $macrosData[$hostId][$scriptId];
						}
					}
				}
			}
		}

		return $scriptsByHost;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['output'] != API_OUTPUT_COUNT) {
			if ($options['selectGroups'] !== null || $options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('groupid'), $sqlParts);
				$sqlParts = $this->addQuerySelect($this->fieldId('host_access'), $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding groups
		if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
			foreach ($result as $scriptId => $script) {
				$result[$scriptId]['groups'] = API::HostGroup()->get([
					'output' => $options['selectGroups'],
					'groupids' => $script['groupid'] ? $script['groupid'] : null,
					'editable' => ($script['host_access'] == PERM_READ_WRITE) ? true : null
				]);
			}
		}

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$processedGroups = [];

			foreach ($result as $scriptId => $script) {
				if (isset($processedGroups[$script['groupid'].'_'.$script['host_access']])) {
					$result[$scriptId]['hosts'] = $result[$processedGroups[$script['groupid'].'_'.$script['host_access']]]['hosts'];
				}
				else {
					$result[$scriptId]['hosts'] = API::Host()->get([
						'output' => $options['selectHosts'],
						'groupids' => $script['groupid'] ? $script['groupid'] : null,
						'hostids' => $options['hostids'] ? $options['hostids'] : null,
						'editable' => ($script['host_access'] == PERM_READ_WRITE) ? true : null
					]);

					$processedGroups[$script['groupid'].'_'.$script['host_access']] = $scriptId;
				}
			}
		}

		return $result;
	}
}
