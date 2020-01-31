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
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	public function get(array $options) {
		$script_fields = ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'description',
			'confirmation', 'type', 'execute_on'
		];
		$group_fields = ['groupid', 'name', 'flags', 'internal'];
		$host_fields = ['hostid', 'host', 'name', 'description', 'status', 'proxy_hostid', 'inventory_mode', 'flags',
			'available', 'snmp_available', 'jmx_available', 'ipmi_available', 'error', 'snmp_error', 'jmx_error',
			'ipmi_error', 'errors_from', 'snmp_errors_from', 'jmx_errors_from', 'ipmi_errors_from', 'disable_until',
			'snmp_disable_until', 'jmx_disable_until', 'ipmi_disable_until', 'ipmi_authtype', 'ipmi_privilege',
			'ipmi_username', 'ipmi_password', 'maintenanceid', 'maintenance_status', 'maintenance_type',
			'maintenance_from', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'tls_psk_identity', 'tls_psk'
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'scriptids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'groupids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'usrgrpids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'scriptid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'command' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'host_access' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])],
				'usrgrpid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'groupid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'confirmation' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'type' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI])],
				'execute_on' =>				['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY])]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'command' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'description' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'confirmation' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $script_fields), 'default' => API_OUTPUT_EXTEND],
			'selectGroups' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $group_fields), 'default' => null],
			'selectHosts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $host_fields), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]), 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select'	=> ['scripts' => 's.scriptid'],
			'from'		=> ['scripts' => 'scripts s'],
			'where'		=> [],
			'order'		=> []
		];

		// editable + permission check
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if ($options['editable']) {
				return $options['countOutput'] ? 0 : [];
			}

			$user_groups = getUserGroupsByUserId(self::$userData['userid']);

			$sql_parts['where'][] = '(s.usrgrpid IS NULL OR '.dbConditionInt('s.usrgrpid', $user_groups).')';
			$sql_parts['where'][] = '(s.groupid IS NULL OR EXISTS ('.
				'SELECT NULL'.
				' FROM rights r'.
				' WHERE s.groupid=r.id'.
					' AND '.dbConditionInt('r.groupid', $user_groups).
				' GROUP BY r.id'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
			'))';
		}

		$host_groups = null;
		$host_groups_by_hostids = null;
		$host_groups_by_groupids = null;

		// Hostids and groupids selection API calls must be made separately because we must intersect enriched groupids.
		if ($options['hostids'] !== null) {
			$host_groups_by_hostids = enrichParentGroups(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'hostids' => $options['hostids'],
				'preservekeys' => true
			]));
		}
		if ($options['groupids'] !== null) {
			$host_groups_by_groupids = enrichParentGroups(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $options['groupids'],
				'preservekeys' => true
			]));
		}

		if ($host_groups_by_groupids !== null && $host_groups_by_hostids !== null) {
			$host_groups = array_intersect_key($host_groups_by_hostids, $host_groups_by_groupids);
		}
		elseif ($host_groups_by_hostids !== null) {
			$host_groups = $host_groups_by_hostids;
		}
		elseif ($host_groups_by_groupids !== null) {
			$host_groups = $host_groups_by_groupids;
		}

		if ($host_groups !== null) {
			$sql_parts['where'][] = '('.dbConditionInt('s.groupid', array_keys($host_groups)).' OR s.groupid IS NULL)';
			$this->parent_host_groups = $host_groups;
		}

		// usrgrpids
		if ($options['usrgrpids'] !== null) {
			$sql_parts['where'][] = '(s.usrgrpid IS NULL OR '.dbConditionInt('s.usrgrpid', $options['usrgrpids']).')';
		}

		// scriptids
		if ($options['scriptids'] !== null) {
			$sql_parts['where'][] = dbConditionInt('s.scriptid', $options['scriptids']);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search('scripts s', $options, $sql_parts);
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter('scripts s', $options, $sql_parts);
		}

		$db_scripts = [];

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($db_script = DBfetch($result)) {
			if ($options['countOutput']) {
				return $db_script['rowscount'];
			}

			$db_scripts[$db_script['scriptid']] = $db_script;
		}

		if ($db_scripts) {
			$db_scripts = $this->addRelatedObjects($options, $db_scripts);
			$db_scripts = $this->unsetExtraFields($db_scripts, ['scriptid', 'groupid', 'host_access'],
				$options['output']
			);

			if (!$options['preservekeys']) {
				$db_scripts = array_values($db_scripts);
			}
		}

		return $db_scripts;
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
	 * @param $hostids
	 *
	 * @return array
	 */
	public function getScriptsByHosts($hostids) {
		zbx_value2array($hostids);

		$scripts_by_host = [];

		if (!$hostids) {
			return $scripts_by_host;
		}

		foreach ($hostids as $hostid) {
			$scripts_by_host[$hostid] = [];
		}

		$scripts = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'hostids' => $hostids,
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		$scripts = $this->addRelatedGroupsAndHosts([
			'selectGroups' => null,
			'selectHosts' => ['hostid']
		], $scripts, $hostids);

		if ($scripts) {
			// resolve macros
			$macros_data = [];
			foreach ($scripts as $scriptid => $script) {
				if (!empty($script['confirmation'])) {
					foreach ($script['hosts'] as $host) {
						if (isset($scripts_by_host[$host['hostid']])) {
							$macros_data[$host['hostid']][$scriptid] = $script['confirmation'];
						}
					}
				}
			}
			if ($macros_data) {
				$macros_data = CMacrosResolverHelper::resolve([
					'config' => 'scriptConfirmation',
					'data' => $macros_data
				]);
			}

			foreach ($scripts as $scriptid => $script) {
				$hosts = $script['hosts'];
				unset($script['hosts']);
				// set script to host
				foreach ($hosts as $host) {
					$hostid = $host['hostid'];

					if (isset($scripts_by_host[$hostid])) {
						$size = count($scripts_by_host[$hostid]);
						$scripts_by_host[$hostid][$size] = $script;

						// set confirmation text with resolved macros
						if (isset($macros_data[$hostid][$scriptid]) && $script['confirmation']) {
							$scripts_by_host[$hostid][$size]['confirmation'] = $macros_data[$hostid][$scriptid];
						}
					}
				}
			}
		}

		return $scripts_by_host;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['selectGroups'] !== null || $options['selectHosts'] !== null) {
			$sqlParts = $this->addQuerySelect($this->fieldId('groupid'), $sqlParts);
			$sqlParts = $this->addQuerySelect($this->fieldId('host_access'), $sqlParts);
		}

		return $sqlParts;
	}

	/**
	 * Applies relational subselect onto already fetched result.
	 *
	 * @param  array $options
	 * @param  array $result
	 *
	 * @return array $result
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		return $this->addRelatedGroupsAndHosts($options, $result);
	}

	/**
	 * Applies relational subselect onto already fetched result.
	 *
	 * @param  array $options
	 * @param  mixed $options['selectGroups']
	 * @param  mixed $options['selectHosts']
	 * @param  array $result
	 * @param  array $hostids                  An additional filter by hostids, which will be added to "hosts" key.
	 *
	 * @return array $result
	 */
	private function addRelatedGroupsAndHosts(array $options, array $result, array $hostids = null) {
		$is_groups_select = $options['selectGroups'] !== null && $options['selectGroups'];
		$is_hosts_select = $options['selectHosts'] !== null && $options['selectHosts'];

		if (!$is_groups_select && !$is_hosts_select) {
			return $result;
		}

		$host_groups_with_write_access = [];
		$has_write_access_level = false;

		$group_search_names = [];
		foreach ($result as $script) {
			$has_write_access_level |= ($script['host_access'] == PERM_READ_WRITE);

			// If any script belongs to all host groups.
			if ($script['groupid'] == 0) {
				$group_search_names = null;
			}

			if ($group_search_names !== null) {
				/*
				 * If scripts were requested by host or group filters, then we have already requested group names
				 * for all groups linked to scripts. And then we can request less groups by adding them as search
				 * condition in hostgroup.get. Otherwise we will need to request all groups, user has access to.
				 */
				if (array_key_exists($script['groupid'], $this->parent_host_groups)) {
					$group_search_names[] = $this->parent_host_groups[$script['groupid']]['name'];
				}
			}
		}

		$select_groups = ['name', 'groupid'];
		$select_groups = $this->outputExtend($options['selectGroups'], $select_groups);

		$host_groups = API::HostGroup()->get([
			'output' => $select_groups,
			'search' => $group_search_names ? ['name' => $group_search_names] : null,
			'searchByAny' => true,
			'startSearch' => true,
			'preservekeys' => true
		]);

		if ($has_write_access_level && $host_groups) {
			$host_groups_with_write_access = API::HostGroup()->get([
				'output' => $select_groups,
				'groupid' => array_keys($host_groups),
				'preservekeys' => true,
				'editable' => true
			]);
		}
		else {
			$host_groups_with_write_access = $host_groups;
		}

		$nested = [];
		foreach ($host_groups as $groupid => $group) {
			$name = $group['name'];

			while (($pos = strrpos($name, '/')) !== false) {
				$name = substr($name, 0, $pos);
				$nested[$name][$groupid] = true;
			}
		}

		$hstgrp_branch = [];
		foreach ($host_groups as $groupid => $group) {
			$hstgrp_branch[$groupid] = [$groupid => true];
			if (array_key_exists($group['name'], $nested)) {
				$hstgrp_branch[$groupid] += $nested[$group['name']];
			}
		}

		if ($is_hosts_select) {
			$sql = 'SELECT hostid,groupid FROM hosts_groups'.
				' WHERE '.dbConditionInt('groupid', array_keys($host_groups));
			if ($hostids !== null) {
				$sql .= ' AND '.dbConditionInt('hostid', $hostids);
			}

			$db_group_hosts = DBSelect($sql);

			$all_hostids = [];
			$group_to_hosts = [];
			while ($row = DBFetch($db_group_hosts)) {
				if (!array_key_exists($row['groupid'], $group_to_hosts)) {
					$group_to_hosts[$row['groupid']] = [];
				}

				$group_to_hosts[$row['groupid']][$row['hostid']] = true;
				$all_hostids[] = $row['hostid'];
			}

			$used_hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hostids' => $all_hostids,
				'preservekeys' => true
			]);
		}

		$host_groups = $this->unsetExtraFields($host_groups, ['name', 'groupid'], $options['selectGroups']);
		$host_groups_with_write_access = $this->unsetExtraFields(
			$host_groups_with_write_access, ['name', 'groupid'], $options['selectGroups']
		);

		foreach ($result as &$script) {
			if ($script['groupid'] == 0) {
				$script_groups = ($script['host_access'] == PERM_READ_WRITE)
					? $host_groups_with_write_access
					: $host_groups;
			}
			else {
				$script_groups = ($script['host_access'] == PERM_READ_WRITE)
					? array_intersect_key($host_groups_with_write_access, $hstgrp_branch[$script['groupid']])
					: array_intersect_key($host_groups, $hstgrp_branch[$script['groupid']]);
			}

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
		unset($script);

		return $result;
	}
}
