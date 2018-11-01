<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * This property, if filled out, will contain all hostrgroup ids
	 * that requested scripts did inherit from.
	 * Keyed by scriptid.
	 *
	 * @var array|HostGroup[]
	 */
	protected $parent_host_groups = [];

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
			'editable'				=> false,
			'nopermissions'			=> null,
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'			=> false,
			'excludeSearch'			=> false,
			'searchWildcardsEnabled'=> null,
			// output
			'output'				=> API_OUTPUT_EXTEND,
			'selectGroups'			=> null,
			'selectHosts'			=> null,
			'countOutput'			=> false,
			'preservekeys'			=> false,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if ($options['editable']) {
				return $result;
			}

			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

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

		$host_group_sources = [];
		$host_groups = null;
		// hostids selection source
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$host_group_sources[] = $this->enrichParentGroups(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'hostids' => $options['hostids'],
				'preservekeys' => true
			]));
		}
		// groupids selection source
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);
			$host_group_sources[] = $this->enrichParentGroups(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $options['groupids'],
				'preservekeys' => true
			]));
		}
		// if more that one source - intersect selection was attempted
		if (array_key_exists(1, $host_group_sources)) {
			$host_groups = call_user_func_array('array_intersect_key', $host_group_sources);
		}
		// if single selection source was used
		elseif (array_key_exists(0, $host_group_sources)) {
			$host_groups = $host_group_sources[0];
		}
		// if any selection was attempted
		if (!is_null($host_groups)) {
			$sqlParts['where'][] = '('.dbConditionInt('s.groupid', array_keys($host_groups)).' OR s.groupid IS NULL)';
		}

		$this->parent_host_groups = $host_groups;

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

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['groupid', 'host_access'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}


	/**
	 * Creates unsorted list of all of group id's that are in preceding path.
	 *
	 * @param array $groups
	 * @param array $groupids
	 *
	 * @return array
	 */
	public function createGroupPaths(array $groups, array $groupids) {
		$paths = array_combine($groupids, array_fill(0, count($groupids), []));

		foreach ($groups as $group) {
			foreach ($groups as $regroup) {
				if (strpos($regroup['name'], $group['name']) === 0) {
					$paths[$group['groupid']][] = $regroup['groupid'];
				}
			}
		}

		return $paths;
	}

	/**
	 * Enriches host groups array by parent groups.
	 *
	 * @param array  $groups
	 * @param string $groups[<groupid>]['groupid']
	 * @param string $groups[<groupid>]['name']
	 *
	 * @return array
	 */
	public function enrichParentGroups(array $groups) {
		$parents = [];
		foreach ($groups as $group) {
			$parent = explode('/', $group['name']);
			for (array_pop($parent); $parent; array_pop($parent)) {
				$parents[implode('/', $parent)] = true;
			}
		}

		if ($parents) {
			foreach ($groups as $group) {
				if (array_key_exists($group['name'], $parents)) {
					unset($parents[$group['name']]);
				}
			}
		}

		if ($parents) {
			$group = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'filter' => ['name' => array_keys($parents)],
				'preservekeys' => true
			]);
			$groups += $group;
		}

		return $groups;
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
			'execute_on' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY])],
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
			'execute_on' =>		['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY])],
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
				elseif ($script['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_AGENT) {
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

		$db_groups = DB::select('hstgrp', [
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
	 * Auxiliary function for checkDuplicates().
	 *
	 * @param array  $folders
	 * @param string $name
	 * @param array  $db_folders
	 * @param string $db_name
	 *
	 * @throws APIException
	 */
	private static function checkScriptNames(array $folders, $name, array $db_folders, $db_name) {
		if (array_slice($folders, 0, count($db_folders)) === $db_folders) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Script menu path "%1$s" already used in script name "%2$s".', $name, $db_name)
			);
		}

		if (array_slice($db_folders, 0, count($folders)) === $folders) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Script name "%1$s" already used in menu path for script "%2$s".', $name, $db_name)
			);
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

		$ok_scripts = [];

		foreach ($scripts as $script) {
			$script['folders'] = array_map('trim', splitPath($script['name']));
			$uniq_name = implode('/', $script['folders']);

			if (array_key_exists($uniq_name, $uniq_names)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%1$s" already exists.', $script['name']));
			}
			$uniq_names[$uniq_name] = true;

			foreach ($ok_scripts as $ok_script) {
				self::checkScriptNames($script['folders'], $script['name'], $ok_script['folders'], $ok_script['name']);
			}

			foreach ($db_scripts as $db_script) {
				if (array_key_exists('scriptid', $script) && bccomp($script['scriptid'], $db_script['scriptid']) == 0) {
					continue;
				}

				self::checkScriptNames($script['folders'], $script['name'], $db_script['folders'], $db_script['name']);
			}

			$ok_scripts[] = $script;
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

	/**
	 * Creates a map between groups and them immediate children.
	 *
	 * @param $host_groups array|HostGroup[]
	 * @return array nodeid => children_nodeid[]
	 */
	private static function createAdjList(array $host_groups) {
		$adj_list = [0 => []];
		foreach ($host_groups as $groupid => $group) {
			if (!array_key_exists($groupid, $adj_list)) {
				$adj_list[$groupid] = [];
			}
			foreach ($host_groups as $groupid2 => $group2) {
				$parts = explode($group['name'] . '/', $group2['name']);
				if (!array_key_exists(1, $parts)) {
					continue;
				}
				if ($parts[0] === '' && strpos($parts[1], '/') === false) {
					$adj_list[$groupid][] = $groupid2;
				}
			}
		}
		return $adj_list;
	}

	/**
	 * @param $carry array initial list to append to
	 * @param $node_list array a valid node lookup list (no checks are made, this is private method)
	 * @param $adj_list array node relations definition
	 * @param $groupid int requested branches root node id
	 * @return array all the branch nodes (inclusive)
	 */
	private static function selectNodes($nodeid, array $adj_list, array $node_list, array &$carry = []) {
		$carry[$nodeid] = $node_list[$nodeid];
		foreach ($adj_list[$nodeid] as $next_groupid) {
			self::selectNodes($next_groupid, $adj_list, $node_list, $carry);
		}
		return $carry;
	}

	/**
	 * Applies relational subselect onto alreadey fetched result.
	 *
	 * @param $options array
	 * @param $restult array
	 * @return $restult array
	 */
	protected function addRelatedObjects(array $options, array $result) {
		// TODO unset extra added output
		// TODO allow to flow undefined branching
		// TODO implement black list filter for self::selectNodes
		// TODO testing for read only hg etc.
		$result = parent::addRelatedObjects($options, $result);

		$is_groups_select = $options['selectGroups'] !== null && $options['selectGroups'] !== API_OUTPUT_COUNT;
		$is_hosts_select = $options['selectHosts'] !== null && $options['selectHosts'] !== API_OUTPUT_COUNT;

		if (!$is_groups_select && !$is_hosts_select) {
			return $result;
		}

		// these host group id's are "blacklisted" and will not be used in selection
		$noselect_groupids = [];
		// any script has a read only permission
		$has_read_only_restriction = false;

		// child or all host group selection
		$child_group_serach = ['name' => []];
		foreach ($result as $script) {
			$has_read_only_restriction |= ($script['host_access'] == PERM_READ);
			if ($script['groupid'] === '0') {
				$child_group_serach = null;
				break;
			}
			$child_group_serach['name'][] = $this->parent_host_groups[$script['groupid']]['name'];
		}

		// must select name (later will be unset)
		if ($options['selectGroups'] !== API_OUTPUT_EXTEND) {
			if (!is_array($options['selectGroups'])) {
				$options['selectGroups'] = ['name'];
			} else {
				$options['selectGroups'][] = 'name';
			}
		}

		$host_group_nodes = API::HostGroup()->get([
			'output' => $options['selectGroups'],
			'search' => $child_group_serach,
			'searchByAny' => true,
			'startSearch' => true,
			'preservekeys' => true
		]);

		if ($has_read_only_restriction) {
			$noselect_groupids = array_keys(API::HostGroup()->get([
				'output' => ['groupid'],
				'search' => $child_group_serach,
				'searchByAny' => true,
				'startSearch' => true,
				'preservekeys' => true,
				'editable' => false
			]));
		}

		$host_group_paths = self::createAdjList($host_group_nodes);
		if ($is_hosts_select) {
			$sql = 'SELECT hostid,groupid FROM hosts_groups';
			if ($child_group_serach !== null) {
				$sql .= ' WHERE '.dbConditionInt('groupid', array_keys($host_group_nodes));
			}
			$db_group_hosts = DBSelect($sql);

			$all_hostids = [];
			$group_to_hosts = [];
			while ($row = DBFetch($db_group_hosts)) {
				$all_hostids[] = $row['hostid'];
				if (!array_key_exists($row['groupid'], $group_to_hosts)) {
					$group_to_hosts[$row['groupid']] = [];
				}
				$group_to_hosts[$row['groupid']][$row['hostid']] = true;
			}

			$used_hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $all_hostids,
				'preservekeys' => true
			]);
		}

		foreach ($result as $scriptid => &$script) {
			$script_groups = self::selectNodes($script['groupid'], $host_group_paths, $host_group_nodes);

			if ($is_groups_select) {
				$script['groups'] = array_values($script_groups);
			}

			if ($is_hosts_select) {
				$script['hosts'] = [];
				foreach (array_keys($script_groups) as $script_groupid) {
					if (array_key_exists($script_groupid, $group_to_hosts)) {
						$script['hosts'] += array_intersect_key($used_hosts, $group_to_hosts[$script_groupid]);
					}
				}
				$script['hosts'] = array_values($script['hosts']);
			}
		}
		return $result;
	}
}
