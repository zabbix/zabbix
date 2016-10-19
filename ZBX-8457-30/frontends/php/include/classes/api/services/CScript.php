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
 *
 * @package API
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
	 * Add scripts.
	 *
	 * @param array $scripts
	 * @param array $scripts['name']
	 * @param array $scripts['hostid']
	 *
	 * @return array
	 */
	public function create(array $scripts) {
		$scripts = zbx_toArray($scripts);

		$this->validateCreate($scripts);

		$scripts = $this->trimMenuPath($scripts);

		$this->validateMenuPath($scripts, __FUNCTION__);

		$scripts = $this->unsetExecutionType($scripts);

		$scriptIds = DB::insert('scripts', $scripts);

		return ['scriptids' => $scriptIds];
	}

	/**
	 * Update scripts.
	 *
	 * @param array $scripts
	 * @param array $scripts['name']
	 * @param array $scripts['hostid']
	 *
	 * @return array
	 */
	public function update(array $scripts) {
		$scripts = zbx_toArray($scripts);

		$this->validateUpdate($scripts);

		$scripts = $this->trimMenuPath($scripts);

		$this->validateMenuPath($scripts, __FUNCTION__);

		$scripts = $this->unsetExecutionType($scripts);

		$update = [];

		foreach ($scripts as $script) {
			$scriptId = $script['scriptid'];
			unset($script['scriptid']);

			$update[] = [
				'values' => $script,
				'where' => ['scriptid' => $scriptId]
			];
		}

		DB::update('scripts', $update);

		return ['scriptids' => zbx_objectValues($scripts, 'scriptid')];
	}

	/**
	 * Delete scripts.
	 *
	 * @param array $scriptIds
	 *
	 * @return array
	 */
	public function delete(array $scriptIds) {
		$this->validateDelete($scriptIds);

		DB::delete('scripts', ['scriptid' => $scriptIds]);

		return ['scriptids' => $scriptIds];
	}

	/**
	 * Execute script.
	 *
	 * @param string $data['scriptid']
	 * @param string $data['hostid']
	 *
	 * @return array
	 */
	public function execute(array $data) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$scriptId = $data['scriptid'];
		$hostId = $data['hostid'];

		$scripts = $this->get([
			'hostids' => $hostId,
			'scriptids' => $scriptId,
			'output' => ['scriptid'],
			'preservekeys' => true
		]);

		if (!isset($scripts[$scriptId])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		// execute script
		$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SCRIPT_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
		$result = $zabbixServer->executeScript($scriptId, $hostId);
		if ($result !== false) {
			// return the result in a backwards-compatible format
			return [
				'response' => 'success',
				'value' => $result
			];
		}
		else {
			self::exception(ZBX_API_ERROR_INTERNAL, $zabbixServer->getError());
		}
	}

	/**
	 * Returns all the scripts that are available on each given host.
	 *
	 * @param $hostIds
	 *
	 * @return array (an array of scripts in the form of array($hostId => array($script1, $script2, ...), ...) )
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

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $scripts
	 */
	protected function validateCreate(array $scripts) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$dbFields = ['command' => null, 'name' => null];

		foreach ($scripts as $script) {
			if (!check_db_fields($dbFields, $script)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for script.'));
			}

			if ($script['name'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Script name cannot be empty.'));
			}
			if ($script['command'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Script command cannot be empty.'));
			}
		}
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $scripts
	 */
	protected function validateUpdate(array $scripts) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		foreach ($scripts as $script) {
			if (empty($script['scriptid'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Invalid method parameters.'));
			}
		}

		$scripts = zbx_toHash($scripts, 'scriptid');
		$scriptIds = array_keys($scripts);

		$dbScripts = $this->get([
			'scriptids' => $scriptIds,
			'output' => ['scriptid'],
			'preservekeys' => true
		]);

		foreach ($scripts as $script) {
			if (!isset($dbScripts[$script['scriptid']])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script with scriptid "%1$s" does not exist.', $script['scriptid']));
			}

			if (isset($script['name'])) {
				if ($script['name'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Script name cannot be empty.'));
				}
			}

			if (isset($script['command']) && $script['command'] === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Script command cannot be empty.'));
			}
		}
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array $scriptIds
	 */
	protected function validateDelete(array $scriptIds) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		if (empty($scriptIds)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete scripts. Empty input parameter "scriptids".'));
		}

		$dbScripts = $this->get([
			'scriptids' => $scriptIds,
			'editable' => true,
			'output' => ['name'],
			'preservekeys' => true
		]);

		foreach ($scriptIds as $scriptId) {
			if (isset($dbScripts[$scriptId])) {
				continue;
			}

			self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Cannot delete scripts. Script with scriptid "%1$s" does not exist.', $scriptId));
		}

		$actions = API::Action()->get([
			'scriptids' => $scriptIds,
			'nopermissions' => true,
			'preservekeys' => true,
			'output' => ['actionid', 'name'],
			'selectOperations' => ['opcommand']
		]);

		foreach ($actions as $action) {
			foreach ($action['operations'] as $operation) {
				if (isset($operation['opcommand']['scriptid'])
						&& $operation['opcommand']['scriptid']
						&& in_array($operation['opcommand']['scriptid'], $scriptIds)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Cannot delete scripts. Script "%1$s" is used in action operation "%2$s".',
							$dbScripts[$operation['opcommand']['scriptid']]['name'], $action['name']
						)
					);
				}
			}
		}
	}

	/**
	 * Validates script name menu path.
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @param array  $scripts
	 * @param string $method
	 */
	protected function validateMenuPath(array $scripts, $method) {
		$dbScripts = $this->get([
			'output' => ['scriptid', 'name'],
			'nopermissions' => true
		]);

		foreach ($scripts as $script) {
			if (!isset($script['name'])) {
				continue;
			}

			$folders = $path = splitPath($script['name']);
			$name = array_pop($folders);

			// menu1/menu2/{empty}
			if (zbx_empty($name)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Empty name for script "%1$s".', $script['name']));
			}

			// menu1/{empty}/name
			foreach ($folders as $folder) {
				if (zbx_empty($folder)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect menu path for script "%1$s".', $script['name']));
				}
			}

			// validate path
			foreach ($dbScripts as $dbScript) {
				if ($method == 'update' && $script['scriptid'] === $dbScript['scriptid']) {
					continue;
				}

				$dbScriptFolders = $dbScriptPath = splitPath($dbScript['name']);
				array_pop($dbScriptFolders);

				// script NAME cannot be a FOLDER for other scripts
				$dbScriptFolderItems = [];
				foreach ($dbScriptFolders as $dbScriptFolder) {
					$dbScriptFolderItems[] = $dbScriptFolder;

					if ($path === $dbScriptFolderItems) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Script name "%1$s" already used in menu path for script "%2$s".',
								$script['name'], $dbScript['name'])
						);
					}
				}

				// script FOLDER cannot be a NAME for other scripts
				$folderItems = [];
				foreach ($folders as $folder) {
					$folderItems[] = $folder;

					if ($dbScriptPath === $folderItems) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Script menu path "%1$s" already used in script name "%2$s".',
								$script['name'], $dbScript['name'])
						);
					}
				}

				// check duplicate script names in same menu path
				if ($path == $dbScriptPath) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%1$s" already exists.', $script['name']));
				}
			}
		}
	}

	/**
	 * Trim script name menu path.
	 *
	 * @param array $scripts
	 *
	 * @return array
	 */
	protected function trimMenuPath(array $scripts) {
		foreach ($scripts as &$script) {
			if (!isset($script['name'])) {
				continue;
			}

			$path = splitPath($script['name'], false);
			$path = array_map('trim', $path);
			$script['name'] = implode('/', $path);
		}
		unset($script);

		return $scripts;
	}

	/**
	 * Unset script execution type if type is IPMI.
	 *
	 * @param array $scripts
	 *
	 * @return array
	 */
	protected function unsetExecutionType(array $scripts) {
		foreach ($scripts as $key => $script) {
			if (isset($script['type']) && $script['type'] == ZBX_SCRIPT_TYPE_IPMI) {
				unset($scripts[$key]['execute_on']);
			}
		}

		return $scripts;
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
