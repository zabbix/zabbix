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
 * Class containing methods for operations with scripts.
 */
class CScript extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getscriptsbyhosts' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getscriptsbyevents' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'execute' => ['min_user_type' => USER_TYPE_ZABBIX_USER, 'action' => CRoleHelper::ACTIONS_EXECUTE_SCRIPTS]
	];

	protected $tableName = 'scripts';
	protected $tableAlias = 's';
	protected $sortColumns = ['scriptid', 'name'];

	/**
	 * Fields from "actions" table. Used in get() validation and addRelatedObjects() when selecting action fields.
	 */
	private $action_fields = ['actionid', 'name', 'eventsource', 'status', 'esc_period', 'pause_suppressed',
		'notify_if_canceled', 'pause_symptoms'
	];

	/**
	 * This property, if filled out, will contain all hostrgroup ids
	 * that requested scripts did inherit from.
	 * Keyed by scriptid.
	 *
	 * @var array
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
			'confirmation', 'type', 'execute_on', 'timeout', 'parameters', 'scope', 'port', 'authtype', 'username',
			'password', 'publickey', 'privatekey', 'menu_path', 'url', 'new_window', 'manualinput',
			'manualinput_prompt', 'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value'
		];
		$group_fields = ['groupid', 'name', 'flags', 'uuid'];
		$host_fields = ['hostid', 'host', 'name', 'description', 'status', 'proxyid', 'inventory_mode', 'flags',
			'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'maintenanceid', 'maintenance_status',
			'maintenance_type', 'maintenance_from', 'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject'
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'scriptids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'groupids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'usrgrpids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'confirmation', 'type', 'url', 'new_window', 'execute_on', 'scope', 'menu_path', 'manualinput', 'manualinput_prompt', 'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'command', 'url', 'description', 'confirmation', 'username', 'menu_path', 'manualinput_prompt', 'manualinput_validator', 'manualinput_default_value']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', $script_fields), 'default' => API_OUTPUT_EXTEND],
			'selectHostGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $group_fields), 'default' => null],
			'selectHosts' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $host_fields), 'default' => null],
			'selectActions' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', $this->action_fields), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false],
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select' =>	['scripts' => 's.scriptid'],
			'from' =>	['scripts' => 'scripts s'],
			'where' =>	[],
			'order' =>	[]
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

		$result = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

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
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'script', __FUNCTION__)
			);
		}

		$this->validateCreate($scripts);

		$scriptids = DB::insert('scripts', $scripts);

		foreach ($scripts as $index => &$script) {
			$script['scriptid'] = $scriptids[$index];
		}
		unset($script);

		self::updateParams($scripts, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_SCRIPT, $scripts);

		return ['scriptids' => $scriptids];
	}

	/**
	 * @param array $scripts
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateCreate(array &$scripts) {
		$api_input_rules = self::getValidationRules('create');

		if (!CApiInputValidator::validate($api_input_rules, $scripts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkUniqueness($scripts);
		$this->checkDuplicates($scripts);
		self::checkScriptExecutionEnabled($scripts);
		$this->checkUserGroups($scripts);
		$this->checkHostGroups($scripts);
		self::checkManualInput($scripts);
	}

	/**
	 * @param array $scripts
	 *
	 * @return array
	 */
	public function update(array $scripts) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'script', __FUNCTION__)
			);
		}

		$this->validateUpdate($scripts, $db_scripts);

		$upd_scripts = [];

		foreach ($scripts as $script) {
			$upd_script = DB::getUpdatedValues('scripts', $script, $db_scripts[$script['scriptid']]);

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

		self::updateParams($scripts, __FUNCTION__, $db_scripts);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_SCRIPT, $scripts, $db_scripts);

		return ['scriptids' => array_column($scripts, 'scriptid')];
	}

	/**
	 * @param array $scripts
	 * @param array $db_scripts
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateUpdate(array &$scripts, ?array &$db_scripts = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['scriptid']], 'fields' => [
			'scriptid' => ['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $scripts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check if given script IDs exist.
		$db_scripts = DB::select('scripts', [
			'output' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'description',
				'confirmation', 'type', 'execute_on', 'timeout', 'scope', 'port', 'authtype', 'username', 'password',
				'publickey', 'privatekey', 'menu_path', 'url', 'new_window', 'manualinput', 'manualinput_prompt',
				'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value'
			],
			'scriptids' => array_column($scripts, 'scriptid'),
			'preservekeys' => true
		]);

		if (count($db_scripts) != count($scripts)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$extend_fields = CSettingsHelper::isGlobalScriptsEnabled()
			? ['name', 'type', 'scope']
			: ['name', 'type', 'scope', 'execute_on'];

		// Populate name, type and scope.
		$scripts = $this->extendObjectsByKey($scripts, $db_scripts, 'scriptid', $extend_fields);
		self::addDbFieldsByType($scripts, $db_scripts);

		$api_input_rules = self::getValidationRules('update');

		if (!CApiInputValidator::validate($api_input_rules, $scripts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::addFieldDefaultsByType($scripts, $db_scripts);

		self::checkUniqueness($scripts, $db_scripts);
		self::addAffectedObjects($scripts, $db_scripts);
		$this->checkDuplicates($scripts, $db_scripts);
		self::checkScriptExecutionEnabled($scripts);
		$this->checkUserGroups($scripts);
		$this->checkHostGroups($scripts);
		self::checkManualInput($scripts);
		self::checkActions($scripts, $db_scripts);
	}

	/**
	 * Add values from the database as a result of changes ot the dependent field data.
	 *
	 * @param array $scripts
	 * @param array $db_scripts
	 */
	private static function addDbFieldsByType(array &$scripts, array $db_scripts): void {
		foreach ($scripts as &$script) {
			$db_script = $db_scripts[$script['scriptid']];

			if ($script['type'] != $db_script['type']) {
				// If type changed to URL, require "url" or if type changed to another type, require "command".
				if ($db_script['type'] == ZBX_SCRIPT_TYPE_URL) {
					$script += array_intersect_key($db_script, array_flip(['command']));
				}
				elseif ($script['type'] == ZBX_SCRIPT_TYPE_URL) {
					$script += array_intersect_key($db_script, array_flip(['url']));
				}

				// If type changed from something to SSH or TELNET then username is required.
				if ($script['type'] == ZBX_SCRIPT_TYPE_TELNET || $script['type'] == ZBX_SCRIPT_TYPE_SSH) {
					$script += array_intersect_key($db_script, array_flip(['username']));
				}
			}

			// If type is SSH and new "authtype" is set to public key, require "publickey" and "privatekey".
			if ($script['type'] == ZBX_SCRIPT_TYPE_SSH) {
				$script += array_intersect_key($db_script, array_flip(['authtype']));

				if ($script['authtype'] != $db_script['authtype'] && $script['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
					$script += array_intersect_key($db_script, array_flip(['publickey', 'privatekey']));
				}
			}

			// For host and event scope, check "manualinput" and "manualinput_validator_type" changes.
			if ($script['scope'] == ZBX_SCRIPT_SCOPE_HOST || $script['scope'] == ZBX_SCRIPT_SCOPE_EVENT) {
				$script += array_intersect_key($db_script, array_flip(['manualinput']));

				if ($script['manualinput'] == ZBX_SCRIPT_MANUALINPUT_ENABLED) {
					$script += array_intersect_key($db_script, array_flip(['manualinput_validator_type',
						'manualinput_validator'
					]));

					if ($script['manualinput'] != $db_script['manualinput']) {
						$script += array_intersect_key($db_script, array_flip(['manualinput_prompt']));
					}

					if ($script['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_STRING) {
						$script += array_intersect_key($db_script, array_flip(['manualinput_default_value']));
					}
				}
			}
		}
		unset($script);
	}

	/**
	 * Add default values for fields that became unnecessary as the result of the change of the type fields.
	 *
	 * @param array $scripts
	 * @param array $db_scripts
	 */
	private static function addFieldDefaultsByType(array &$scripts, array $db_scripts): void {
		$defaults = DB::getDefaults('scripts');
		$defaults['usrgrpid'] = 0;
		$defaults['parameters'] = [];

		foreach ($scripts as &$script) {
			$db_script = $db_scripts[$script['scriptid']];

			if ($script['type'] != $db_script['type']) {
				switch ($script['type']) {
					case ZBX_SCRIPT_TYPE_IPMI:
						$script += array_intersect_key($defaults, array_flip(['execute_on']));
						// break; is not missing here

					case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
						$script += array_intersect_key($defaults, array_flip(['authtype', 'username', 'publickey',
							'privatekey', 'password', 'port', 'parameters', 'timeout', 'url', 'new_window'
						]));
						break;

					case ZBX_SCRIPT_TYPE_SSH:
						$script += array_intersect_key($defaults, array_flip(['execute_on', 'parameters', 'timeout',
							'url', 'new_window'
						]));
						break;

					case ZBX_SCRIPT_TYPE_TELNET:
						$script += array_intersect_key($defaults, array_flip(['execute_on', 'authtype', 'publickey',
							'privatekey', 'parameters', 'timeout', 'url', 'new_window'
						]));
						break;

					case ZBX_SCRIPT_TYPE_WEBHOOK:
						$script += array_intersect_key($defaults, array_flip(['execute_on', 'authtype', 'username',
							'publickey', 'privatekey', 'password', 'port', 'url', 'new_window'
						]));
						break;

					case ZBX_SCRIPT_TYPE_URL:
						$script += array_intersect_key($defaults, array_flip(['execute_on', 'authtype', 'username',
							'publickey', 'privatekey', 'password', 'port', 'command', 'parameters', 'timeout'
						]));
						break;
				}
			}
			elseif ($script['type'] == ZBX_SCRIPT_TYPE_SSH && $script['authtype'] != $db_script['authtype']
					&& $script['authtype'] == ITEM_AUTHTYPE_PASSWORD) {
				$script += array_intersect_key($defaults, array_flip(['publickey', 'privatekey']));
			}

			if ($script['scope'] != $db_script['scope'] && $script['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
				$script += array_intersect_key($defaults, array_flip(['menu_path', 'usrgrpid', 'host_access',
					'confirmation', 'manualinput', 'manualinput_prompt', 'manualinput_validator_type',
					'manualinput_validator', 'manualinput_default_value'
				]));
			}

			if ($script['scope'] == ZBX_SCRIPT_SCOPE_HOST || $script['scope'] == ZBX_SCRIPT_SCOPE_EVENT) {
				if ($script['manualinput'] != $db_script['manualinput']
						&& $script['manualinput'] == ZBX_SCRIPT_MANUALINPUT_DISABLED) {
					$script += array_intersect_key($defaults, array_flip(['manualinput_prompt',
						'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value'
					]));
				}

				if ($script['manualinput'] == ZBX_SCRIPT_MANUALINPUT_ENABLED
						&& $script['manualinput_validator_type'] != $db_script['manualinput_validator_type']
						&& $script['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_LIST) {
					$script += array_intersect_key($defaults, array_flip(['manualinput_default_value']));
				}
			}
		}
		unset($script);
	}

	/**
	 * Validates manual input fields.
	 *
	 * @param array $scripts  Script data.
	 *
	 * $scripts = [[
	 *     'scope' =>                      (int)     Script scope - Host or Event.
	 *     'manualinput' =>                (int)     Is script manual input Enabled "1" or Disabled "0".
	 *     'manualinput_validator_type' => (int)     Manual input validator type - string or list.
	 *     'manualinput_validator' =>      (string)  Regular expression or comma separated list.
	 *     'manualinput_default_value' =>  (string)  Manual input default value (used when manual input validator type
	 *                                               is list).
	 * ]]
	 *
	 * @throws APIException if input is invalid.
	 */
	private static function checkManualInput(array $scripts): void {
		foreach ($scripts as $index => $script) {
			if (($script['scope'] == ZBX_SCRIPT_SCOPE_HOST || $script['scope'] == ZBX_SCRIPT_SCOPE_EVENT)
					&& $script['manualinput'] == ZBX_SCRIPT_MANUALINPUT_ENABLED) {
				if ($script['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_STRING) {
					$regular_expression = '/'.str_replace('/', '\/', $script['manualinput_validator']).'/';

					if (!preg_match($regular_expression, $script['manualinput_default_value'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Invalid parameter "%1$s": %2$s.', '/'.($index + 1).'/manualinput_default_value',
								_s('input does not match the provided pattern: %1$s', $script['manualinput_validator'])
							)
						);
					}
				}
				elseif ($script['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_LIST) {
					$manualinput_validator = array_map('trim', explode(',', $script['manualinput_validator']));

					if (array_unique($manualinput_validator) !== $manualinput_validator) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							'/'.($index + 1).'/manualinput_validator', _('values must be unique')
						));
					}
				}
			}
		}
	}

	/**
	 * Get validation rules for script.create and script.update methods.
	 *
	 * @param string $method  "create" or "update" method.
	 *
	 * @return array
	 */
	private static function getValidationRules(string $method): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'name' =>						['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'name')],
			'scope' =>						['type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])],
			'type' =>						['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_ACTION])], 'type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK])],
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK, ZBX_SCRIPT_TYPE_URL])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'type')]
			]],
			'groupid' =>					['type' => API_ID],
			'description' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'description')],
			'menu_path' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_SCRIPT_MENU_PATH, 'length' => DB::getFieldLength('scripts', 'menu_path')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'menu_path')]
			]],
			'usrgrpid' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_ID],
												['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'host_access' =>				['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_INT32, 'in' => implode(',', [PERM_READ, PERM_READ_WRITE])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'host_access')]
			]],
			'confirmation' =>				['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'confirmation')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'confirmation')]
			]],
			'command' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'command')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'command')]
			]],
			'execute_on' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT], 'type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'execute_on')]
			]],
			'port' =>						['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', [ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET])], 'type' => API_PORT, 'flags' => API_ALLOW_USER_MACRO],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'port')]
			]],
			'authtype' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_SSH], 'type' => API_INT32, 'in' => implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'authtype')]
			]],
			'publickey' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_SSH], 'type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'publickey')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'publickey')]
												]],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'publickey')]
			]],
			'privatekey' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_SSH], 'type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'authtype', 'in' => ITEM_AUTHTYPE_PUBLICKEY], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'privatekey')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'privatekey')]
												]],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'privatekey')]
			]],
			'username' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', [ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'username')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'username')]
			]],
			'password' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => implode(',', [ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'password')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'password')]
			]],
			'timeout' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_WEBHOOK], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY, 'in' => '1:'.SEC_PER_MIN],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'timeout')]
			]],
			'parameters' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_WEBHOOK], 'type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
													'name' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('script_param', 'name')],
													'value' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('script_param', 'value')]
												]],
												['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			/*
			 * Regardless of "manualinput" value, allow {MANUALINPUT} macro in URL. Otherwise, changing "manualinput" to
			 * DISABLED, will require additional URL re-validation and that would be not only confusing but also
			 * annoying to user.
			 */
			'url' =>						['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_URL], 'type' => API_URL, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_MACRO | API_ALLOW_MANUALINPUT_MACRO, 'length' => DB::getFieldLength('scripts', 'url')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'url')]
			]],
			'new_window' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'type', 'in' => ZBX_SCRIPT_TYPE_URL], 'type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_URL_NEW_WINDOW_NO, ZBX_SCRIPT_URL_NEW_WINDOW_YES])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'new_window')]
			]],
			'manualinput' =>				['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_MANUALINPUT_DISABLED, ZBX_SCRIPT_MANUALINPUT_ENABLED])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'manualinput')]
			]],
			'manualinput_prompt' =>			['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'manualinput', 'in' => ZBX_SCRIPT_MANUALINPUT_ENABLED], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'manualinput_prompt')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_prompt')]
												]],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_prompt')]
			]],
			'manualinput_validator_type' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'manualinput', 'in' => ZBX_SCRIPT_MANUALINPUT_ENABLED], 'type' => API_INT32, 'in' => implode(',', [ZBX_SCRIPT_MANUALINPUT_TYPE_STRING, ZBX_SCRIPT_MANUALINPUT_TYPE_LIST])],
													['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'manualinput_validator_type')]
												]],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('scripts', 'manualinput_validator_type')]
			]],
			'manualinput_validator' =>		['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'manualinput', 'in' => ZBX_SCRIPT_MANUALINPUT_ENABLED], 'type' => API_MULTIPLE, 'rules' => [
														['if' => ['field' => 'manualinput_validator_type', 'in' => ZBX_SCRIPT_MANUALINPUT_TYPE_STRING], 'type' => API_REGEX, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'manualinput_validator')],
														['if' => ['field' => 'manualinput_validator_type', 'in' => ZBX_SCRIPT_MANUALINPUT_TYPE_LIST], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('scripts', 'manualinput_validator')],
														['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_validator')]
													]],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_validator')]
												]],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_validator')]
			]],
			'manualinput_default_value' =>	['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'scope', 'in' => implode(',', [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT])], 'type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'manualinput', 'in' => ZBX_SCRIPT_MANUALINPUT_ENABLED], 'type' => API_MULTIPLE, 'rules' => [
														['if' => ['field' => 'manualinput_validator_type', 'in' => ZBX_SCRIPT_MANUALINPUT_TYPE_STRING], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'manualinput_default_value')],
														['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_default_value')]
													]],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_default_value')]
												]],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('scripts', 'manualinput_default_value')]
			]]
		]];

		if ($method === 'create') {
			$api_input_rules['fields']['name']['flags'] |= API_REQUIRED;
			$api_input_rules['fields']['scope']['flags'] = API_REQUIRED;
			$api_input_rules['fields']['type']['rules'][0]['flags'] = API_REQUIRED;
			$api_input_rules['fields']['type']['rules'][1]['flags'] = API_REQUIRED;

			$api_input_rules['fields']['command']['rules'][0]['flags'] |= API_REQUIRED;

			$api_input_rules['fields']['username']['rules'][0]['flags'] |= API_REQUIRED;
			$api_input_rules['fields']['publickey']['rules'][0]['rules'][0]['flags'] |= API_REQUIRED;
			$api_input_rules['fields']['privatekey']['rules'][0]['rules'][0]['flags'] |= API_REQUIRED;

			$api_input_rules['fields']['url']['rules'][0]['flags'] |= API_REQUIRED;

			$api_input_rules['fields']['manualinput_prompt']['rules'][0]['rules'][0]['flags'] |= API_REQUIRED;
			$api_input_rules['fields']['manualinput_validator']['rules'][0]['rules'][0]['rules'][0]['flags']
				|= API_REQUIRED;
			$api_input_rules['fields']['manualinput_validator']['rules'][0]['rules'][0]['rules'][1]['flags']
				|= API_REQUIRED;

			$api_input_rules['fields']['authtype']['rules'][0]['default'] = DB::getDefault('scripts', 'authtype');

			$api_input_rules['fields']['manualinput']['rules'][0]['default'] = DB::getDefault('scripts', 'manualinput');
			$api_input_rules['fields']['manualinput_validator_type']['rules'][0]['rules'][0]['default'] =
				DB::getDefault('scripts', 'manualinput_validator_type');
			$api_input_rules['fields']['manualinput_default_value']['rules'][0]['rules'][0]['rules'][0]['default'] =
				DB::getDefault('scripts', 'manualinput_default_value');
		}
		else {
			$api_input_rules['fields']['scriptid'] = ['type' => API_ID];
		}

		return $api_input_rules;
	}

	/**
	 * @param array $scripts
	 *
	 * @throws APIException if at least one script has execute_on parameter equal to Zabbix server and global script
	 *                      execution is disabled by Zabbix server.
	 */
	private static function checkScriptExecutionEnabled(array $scripts): void {
		if (!CSettingsHelper::isGlobalScriptsEnabled()) {
			foreach ($scripts as $index => $script) {
				if ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
						&& $script['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_SERVER) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.($index + 1).'/execute_on',
							_('global script execution on Zabbix server is disabled by server configuration')
						)
					);
				}
			}
		}
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

		$db_groups = API::HostGroup()->get([
			'output' => [],
			'groupids' => $groupids,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'preservekeys' => true
		]);

		foreach ($groupids as $groupid) {
			if (!array_key_exists($groupid, $db_groups)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Host group with ID "%1$s" is not available.', $groupid));
			}
		}
	}

	/**
	 * @param array $scriptids
	 *
	 * @return array
	 */
	public function delete(array $scriptids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'script', __FUNCTION__)
			);
		}

		self::validateDelete($scriptids, $db_scripts);

		DB::delete('scripts', ['scriptid' => $scriptids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_SCRIPT, $db_scripts);

		return ['scriptids' => $scriptids];
	}

	/**
	 * Validates parameters for script.delete method.
	 *
	 * @param array      $scriptids
	 * @param array|null $db_scripts
	 *
	 * @throws APIException if the input is invalid
	 */
	private static function validateDelete(array &$scriptids, ?array &$db_scripts = null) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $scriptids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_scripts = DB::select('scripts', [
			'output' => ['scriptid', 'name'],
			'scriptids' => $scriptids,
			'preservekeys' => true
		]);

		if (count($db_scripts) != count($scriptids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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
			'scriptid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'hostid' =>			['type' => API_ID],
			'eventid' =>		['type' => API_ID],
			'manualinput' =>	['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $data, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (!array_key_exists('hostid', $data) && !array_key_exists('eventid', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/', _s('the parameter "%1$s" is missing', 'eventid'))
			);
		}

		if (array_key_exists('hostid', $data) && array_key_exists('eventid', $data)) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Invalid parameter "%1$s": %2$s.', '/', _s('unexpected parameter "%1$s"', 'eventid'))
			);
		}

		if (array_key_exists('eventid', $data)) {
			$db_events = API::Event()->get([
				'output' => [],
				'selectHosts' => ['hostid', 'proxyid'],
				'eventids' => $data['eventid']
			]);
			if (!$db_events) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$hostids = array_column($db_events[0]['hosts'], 'hostid');
			$db_hosts = $db_events[0]['hosts'];

			$is_event = true;
		}
		else {
			$hostids = $data['hostid'];
			$is_event = false;

			$db_hosts = API::Host()->get([
				'output' => ['proxyid'],
				'hostids' => $hostids
			]);
			if (!$db_hosts) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$db_scripts = $this->get([
			'output' => ['type', 'execute_on'],
			'hostids' => $hostids,
			'scriptids' => $data['scriptid']
		]);

		if (!$db_scripts) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_script = $db_scripts[0];

		if (!CSettingsHelper::isGlobalScriptsEnabled() && $db_script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
				&& ($db_script['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_SERVER
					|| ($db_script['execute_on'] == ZBX_SCRIPT_EXECUTE_ON_PROXY && $db_hosts[0]['proxyid'] == 0))) {
			self::exception(ZBX_API_ERROR_INTERNAL,
				_('Global script execution on Zabbix server is disabled by server configuration.')
			);
		}

		if ($db_scripts[0]['type'] == ZBX_SCRIPT_TYPE_URL) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Cannot execute URL type script.'));
		}

		// execute script
		$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SCRIPT_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$result = $zabbix_server->executeScript($data['scriptid'], self::getAuthIdentifier(),
			$is_event ? null : $data['hostid'],
			$is_event ? $data['eventid'] : null,
			array_key_exists('manualinput', $data) ? $data['manualinput'] : null
		);

		if ($result !== false) {
			// return the result in a backwards-compatible format
			return [
				'response' => 'success',
				'value' => $result,
				'debug' => $zabbix_server->getDebug()
			];
		}
		else {
			self::exception(ZBX_API_ERROR_INTERNAL, $zabbix_server->getError());
		}
	}

	/**
	 * Returns all the scripts that are available on each given host. Automatically resolves macros in
	 * confirmation, URL and manual input prompt fields.
	 *
	 * @param array  $options
	 *
	 * $options = [
	 *     'hostid' =>      (string)  Host ID to return scripts for.
	 *     'scriptid' =>    (string)  Script ID for value retrieval (optional).
	 *     'manualinput' => (string)  Value of the user-provided {MANUALINPUT} macro value (optional).
	 * ]
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function getScriptsByHosts(array $options): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['hostid']], 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'scriptid' =>		['type' => API_ID],
			'manualinput' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'manualinput_default_value')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options = array_column($options, null, 'hostid');
		$hostids = array_keys($options);
		$scripts_by_host = [];

		if (!$hostids) {
			return $scripts_by_host;
		}

		foreach ($hostids as $hostid) {
			$scripts_by_host[$hostid] = [];
		}

		/*
		 * If at least one host has no script ID set, get all scripts and then filter out scripts for each host.
		 * However, if all hosts have their script IDs set, do not select all scripts. Get only scripts that are
		 * requested. General use for this is in frontend when only host and one script is requested.
		 */
		$get_all_scripts = false;
		$scriptids = [];

		foreach ($options as $option) {
			if (array_key_exists('scriptid', $option)) {
				$scriptids[$option['scriptid']] = true;
			}
			else {
				$get_all_scripts = true;
				break;
			}
		}

		$scripts = $this->get([
			'output' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'description',
				'confirmation', 'type', 'execute_on', 'timeout', 'scope', 'port', 'authtype', 'username', 'password',
				'publickey', 'privatekey', 'menu_path', 'url', 'new_window', 'manualinput', 'manualinput_prompt',
				'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value'
			],
			'hostids' => $hostids,
			'scriptids' => $get_all_scripts ? null : array_keys($scriptids),
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		$scripts = $this->addRelatedGroupsAndHosts([
			'selectHostGroups' => null,
			'selectHosts' => ['hostid']
		], $scripts, $hostids);

		$macros_data = [];

		foreach ($scripts as $scriptid => $script) {
			foreach ($script['hosts'] as $host) {
				$hostid = $host['hostid'];

				if (array_key_exists($hostid, $options)) {
					$option = $options[$hostid];

					if (!array_key_exists('scriptid', $option) || bccomp($option['scriptid'], $scriptid) == 0) {
						unset($script['hosts']);
						$scripts_by_host[$hostid][] = $script;

						foreach (['confirmation', 'url', 'manualinput_prompt'] as $field) {
							if (strpos($script[$field], '{') !== false) {
								$macros_data[$hostid][$scriptid][$field] = $script[$field];
							}
						}
					}
				}
			}
		}

		$manualinput_values = array_column($options, 'manualinput', 'hostid');
		$macros_data = CMacrosResolverHelper::resolveManualHostActionScripts($macros_data, $manualinput_values);

		foreach ($scripts_by_host as $hostid => &$scripts) {
			if (array_key_exists($hostid, $macros_data)) {
				foreach ($scripts as &$script) {
					if (array_key_exists($script['scriptid'], $macros_data[$hostid])) {
						foreach ($macros_data[$hostid][$script['scriptid']] as $field => $value) {
							$script[$field] = $value;
						}
					}
				}
				unset($script);
			}
		}
		unset($scripts);

		return $scripts_by_host;
	}

	/**
	 * Returns all the scripts that are available on each given event. Automatically resolves macros in
	 * confirmation, URL and manual input prompt fields.
	 *
	 * @param array  $options
	 *
	 *  $options = [
	 *     'eventid' =>     (string)  Event ID to return scripts for.
	 *     'scriptid' =>    (string)  Script ID for value retrieval (optional).
	 *     'manualinput' => (string)  Value of the user-provided {MANUALINPUT} macro value (optional).
	 * ]
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function getScriptsByEvents(array $options): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['eventid']], 'fields' => [
			'eventid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'scriptid' =>		['type' => API_ID],
			'manualinput' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('scripts', 'manualinput_default_value')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options = array_column($options, null, 'eventid');
		$scripts_by_events = [];

		foreach ($options as $eventid => $option) {
			$scripts_by_events[$eventid] = [];
		}

		$events = API::Event()->get([
			'output' => ['eventid', 'objectid', 'value', 'name', 'severity', 'cause_eventid'],
			'selectHosts' => ['hostid'],
			'object' => EVENT_OBJECT_TRIGGER,
			'source' => EVENT_SOURCE_TRIGGERS,
			'eventids' => array_keys($scripts_by_events),
			'preservekeys' => true
		]);

		if (!$events) {
			return $scripts_by_events;
		}

		$symptom_cause_eventids = [];

		foreach ($events as &$event) {
			if ($event['cause_eventid'] != 0) {
				// There is no need to select already preselected events again.
				if (array_key_exists($event['cause_eventid'], $events)) {
					$event['cause'] = [
						'eventid' => $events[$event['cause_eventid']]['eventid'],
						'value' => $events[$event['cause_eventid']]['value'],
						'name' => $events[$event['cause_eventid']]['name'],
						'severity' => $events[$event['cause_eventid']]['severity']
					];
				}
				else {
					$event['cause'] = [];
					// Collect cause event IDs for symptom events.
					$symptom_cause_eventids[] = $event['cause_eventid'];
				}
			}
		}
		unset($event);

		if ($symptom_cause_eventids) {
			$cause_events = API::Event()->get([
				'output' => ['eventid', 'value', 'name', 'severity'],
				'object' => EVENT_OBJECT_TRIGGER,
				'source' => EVENT_SOURCE_TRIGGERS,
				'eventids' => $symptom_cause_eventids,
				'preservekeys' => true
			]);

			if ($cause_events) {
				foreach ($events as &$event) {
					foreach ($cause_events as $cause_event) {
						if (bccomp($event['cause_eventid'], $cause_event['eventid']) == 0) {
							$event['cause'] = [
								'eventid' => $cause_event['eventid'],
								'value' => $cause_event['value'],
								'name' => $cause_event['name'],
								'severity' => $cause_event['severity']
							];
						}
					}
				}
				unset($event);
			}
		}

		$eventids_by_hostid = [];
		foreach ($events as $event) {
			foreach ($event['hosts'] as $host) {
				$eventids_by_hostid[$host['hostid']][] = $event['eventid'];
			}
		}

		$hostids = array_keys($eventids_by_hostid);

		/*
		 * If at least one event has no script ID set, get all scripts and then filter out scripts for each event.
		 * However, if all events have their script IDs set, do not select all scripts. Get only scripts that are
		 * requested. General use for this is in frontend when only event and one script is requested.
		 */
		$get_all_scripts = false;
		$scriptids = [];

		foreach ($options as $option) {
			if (array_key_exists('scriptid', $option)) {
				$scriptids[$option['scriptid']] = true;
			}
			else {
				$get_all_scripts = true;
				break;
			}
		}

		$scripts = $this->get([
			'output' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'description',
				'confirmation', 'type', 'execute_on', 'timeout', 'scope', 'port', 'authtype', 'username', 'password',
				'publickey', 'privatekey', 'menu_path', 'url', 'new_window', 'manualinput', 'manualinput_prompt',
				'manualinput_validator_type', 'manualinput_validator', 'manualinput_default_value'
			],
			'hostids' => $hostids,
			'scriptids' => $get_all_scripts ? null : array_keys($scriptids),
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		$scripts = $this->addRelatedGroupsAndHosts([
			'selectHostGroups' => null,
			'selectHosts' => ['hostid']
		], $scripts, $hostids);

		$macros_data = [];

		foreach ($scripts as $scriptid => $script) {
			foreach ($script['hosts'] as $host) {
				if (array_key_exists($host['hostid'], $eventids_by_hostid)) {
					foreach ($eventids_by_hostid[$host['hostid']] as $eventid) {
						if (array_key_exists($eventid, $options)) {
							$option = $options[$eventid];

							if (!array_key_exists('scriptid', $option) || bccomp($option['scriptid'], $scriptid) == 0) {
								if (!array_key_exists($scriptid, $scripts_by_events[$eventid])) {
									$scripts_by_events[$eventid][$scriptid] =
										array_diff_key($script, ['hosts' => true]);

									foreach (['confirmation', 'url', 'manualinput_prompt'] as $field) {
										if (strpos($script[$field], '{') !== false) {
											$macros_data[$eventid][$scriptid][$field] = $script[$field];
										}
									}
								}
							}
						}
					}
				}
			}
		}

		$manualinput_values = array_column($options, 'manualinput', 'eventid');
		$macros_data = CMacrosResolverHelper::resolveManualEventActionScripts($macros_data, $events,
			$manualinput_values
		);

		foreach ($scripts_by_events as $eventid => &$scripts) {
			if (array_key_exists($eventid, $macros_data)) {
				foreach ($scripts as $scriptid => &$script) {
					if (array_key_exists($scriptid, $macros_data[$eventid])) {
						foreach ($macros_data[$eventid][$script['scriptid']] as $field => $value) {
							$script[$field] = $value;
						}
					}
				}
				unset($script);
			}

			$scripts = array_values($scripts);
		}
		unset($scripts);

		return $scripts_by_events;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['selectHostGroups'] !== null || $options['selectHosts'] !== null) {
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

		// Adding actions.
		if ($options['selectActions'] !== null && $options['selectActions'] !== API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['actions'] = [];
			}
			unset($row);

			$action_scriptids = [];

			if ($this->outputIsRequested('scope', $options['output'])) {
				foreach ($result as $scriptid => $row) {
					if ($row['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
						$action_scriptids[] = $scriptid;
					}
				}
			}
			else {
				$db_scripts = API::getApiService()->select('scripts', [
					'output' => ['scope'],
					'filter' => ['scriptid' => array_keys($result)],
					'preservekeys' => true
				]);
				$db_scripts = $this->extendFromObjects($result, $db_scripts, ['scope']);

				foreach ($db_scripts as $scriptid => $db_script) {
					if ($db_script['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
						$action_scriptids[] = $scriptid;
					}
				}

				// Remove scope from output, since it's not requested.
				$result = $this->unsetExtraFields($result, ['scope']);
			}

			if ($action_scriptids) {
				if ($options['selectActions'] === API_OUTPUT_EXTEND) {
					$action_fields = array_map(function ($field) { return 'a.'.$field; }, $this->action_fields);
					$action_fields = implode(',', $action_fields);
				}
				elseif (is_array($options['selectActions'])) {
					$action_fields = $options['selectActions'];

					if (!in_array('actionid', $options['selectActions'])) {
						$action_fields[] = 'actionid';
					}

					$action_fields = array_map(function ($field) { return 'a.'.$field; }, $action_fields);
					$action_fields = implode(',', $action_fields);
				}

				$db_script_actions = DBfetchArray(DBselect(
					'SELECT DISTINCT oc.scriptid,'.$action_fields.
					' FROM actions a,operations o,opcommand oc'.
					' WHERE a.actionid=o.actionid'.
						' AND o.operationid=oc.operationid'.
						' AND '.dbConditionInt('oc.scriptid', $action_scriptids)
				));

				foreach ($result as $scriptid => &$row) {
					if ($db_script_actions) {
						foreach ($db_script_actions as $db_script_action) {
							if (bccomp($db_script_action['scriptid'], $scriptid) == 0) {
								unset($db_script_action['scriptid']);
								$row['actions'][] = $db_script_action;
							}
						}

						$row['actions'] = $this->unsetExtraFields($row['actions'], ['actionid'],
							$options['selectActions']
						);
					}
				}
				unset($row);
			}
		}

		if ($this->outputIsRequested('parameters', $options['output'])) {
			foreach ($result as $scriptid => $script) {
				$result[$scriptid]['parameters'] = [];
			}

			$param_options = [
				'output' => ['script_paramid', 'scriptid', 'name', 'value'],
				'filter' => ['scriptid' => array_keys($result)]
			];
			$db_parameters = DBselect(DB::makeSql('script_param', $param_options));

			while ($db_param = DBfetch($db_parameters)) {
				$result[$db_param['scriptid']]['parameters'][] = [
					'name' => $db_param['name'],
					'value' => $db_param['value']
				];
			}
		}

		return $this->addRelatedGroupsAndHosts($options, $result);
	}

	/**
	 * Applies relational subselect onto already fetched result.
	 *
	 * @param  array $options
	 * @param  mixed $options['selectHostGroups']
	 * @param  mixed $options['selectHosts']
	 * @param  array $result
	 * @param  array $hostids                  An additional filter by hostids, which will be added to "hosts" key.
	 *
	 * @return array $result
	 */
	private function addRelatedGroupsAndHosts(array $options, array $result, ?array $hostids = null) {
		$is_hostgroups_select = $options['selectHostGroups'] !== null;
		$is_hosts_select = $options['selectHosts'] !== null;

		if (!$is_hostgroups_select && !$is_hosts_select) {
			return $result;
		}

		$host_groups_with_write_access = [];
		$has_write_access_level = false;

		$group_search_names = [];
		foreach ($result as $script) {
			if ($script['host_access'] == PERM_READ_WRITE) {
				$has_write_access_level = true;
			}

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

		$select_groups = $options['selectHostGroups'] === API_OUTPUT_EXTEND
			? API_OUTPUT_EXTEND
			: (is_array($options['selectHostGroups']) ? $options['selectHostGroups'] : []);

		$select_groups = $this->outputExtend($select_groups, ['groupid', 'name']);

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
				'groupids' => array_keys($host_groups),
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

			if ($is_hostgroups_select) {
				$script['hostgroups'] = array_values($this->unsetExtraFields($script_groups,
					['groupid', 'name', 'flags', 'uuid'], $options['selectHostGroups']
				));
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

	/**
	 * Check for unique script names within menu path in the input.
	 *
	 * @param array  $scripts
	 * @param array  $db_scripts
	 *
	 * $scripts = [[
	 *     'name' =>      (string)  Script name.
	 *     'menu_path' => (string)  Script menu path.
	 * ]]
	 *
	 * @throws APIException if script names within menu paths are not unique.
	 */
	private static function checkUniqueness(array $scripts, ?array $db_scripts = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name', 'menu_path']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8],
			'menu_path' =>	['type' => API_SCRIPT_MENU_PATH]
		]];

		foreach ($scripts as &$script) {
			$script += ['menu_path' => $db_scripts !== null ? $db_scripts[$script['scriptid']]['menu_path'] : ''];
		}
		unset($script);

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $scripts, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * Check for duplicate script names within menu path.
	 *
	 * @param array       $scripts     Array of scripts.
	 * @param array|null  $db_scripts  Array of scripts from database.
	 *
	 * $scripts = [[
	 *     'scriptid' =>  (string)  Script ID.
	 *     'name' =>      (string)  Script name.
	 *     'menu_path' => (string)  Script menu path (exists if scope = 1 for update method).
	 *     'scope' =>     (string)  Script scope.
	 * ]]
	 *
	 * $db_scripts = [
	 *     <scriptid> => [
	 *         'name' =>      (string)  Script name.
	 *         'menu_path' => (string)  Script menu path.
	 *         'scope' =>     (string)  Script scope.
	 *     ]
	 * ]
	 *
	 * @throws APIException if script names within menu paths have duplicates in DB.
	 */
	private function checkDuplicates(array $scripts, ?array $db_scripts = null): void {
		if ($db_scripts !== null) {
			$scripts = $this->extendFromObjects(zbx_toHash($scripts, 'scriptid'), $db_scripts, ['menu_path']);

			/*
			 * Remove unchanged scripts and continue validation only for scripts that have changed name, menu path or
			 * scope. If scope is changed to action, menu_path will be reset to empty string and that is a change.
			 */
			$scripts = array_filter($scripts,
				static fn($script) => $script['name'] !== $db_scripts[$script['scriptid']]['name']
					|| $script['menu_path'] !== $db_scripts[$script['scriptid']]['menu_path']
					|| ($script['scope'] !== $db_scripts[$script['scriptid']]['scope']
						&& $script['scope'] == ZBX_SCRIPT_SCOPE_ACTION)
			);

			if (!$scripts) {
				return;
			}
		}

		$scripts_ex = DB::select('scripts', [
			'output' => ['scriptid', 'name', 'menu_path'],
			'filter' => ['name' => array_column($scripts, 'name')]
		]);

		if (!$scripts_ex) {
			return;
		}

		$db_scriptids = [];

		foreach ($scripts_ex as $script) {
			$name = self::getScriptNameAndPath($script);
			$db_scriptids[$name] = $script['scriptid'];
		}

		foreach ($scripts as $script) {
			$name = self::getScriptNameAndPath($script);

			if ($db_scripts === null && array_key_exists($name, $db_scriptids)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%1$s" already exists.', $script['name']));
			}
			elseif (array_key_exists($name, $db_scriptids) && bccomp($script['scriptid'], $db_scriptids[$name]) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Script "%1$s" already exists.', $script['name']));
			}
		}
	}

	/**
	 * Update "script_param" table and populate script.parameters by "script_paramid" property.
	 *
	 * @param array      $scripts
	 * @param string     $method
	 * @param array|null $db_scripts
	 */
	private static function updateParams(array &$scripts, string $method, ?array $db_scripts = null): void {
		$ins_params = [];
		$upd_params = [];
		$del_paramids = [];

		foreach ($scripts as &$script) {
			if (!array_key_exists('parameters', $script)) {
				continue;
			}

			$db_params = ($method === 'update')
				? array_column($db_scripts[$script['scriptid']]['parameters'], null, 'name')
				: [];

			foreach ($script['parameters'] as &$param) {
				if (array_key_exists($param['name'], $db_params)) {
					$db_param = $db_params[$param['name']];
					$param['script_paramid'] = $db_param['script_paramid'];
					unset($db_params[$param['name']]);

					$upd_param = DB::getUpdatedValues('script_param', $param, $db_param);

					if ($upd_param) {
						$upd_params[] = [
							'values' => $upd_param,
							'where' => ['script_paramid' => $db_param['script_paramid']]
						];
					}
				}
				else {
					$ins_params[] = ['scriptid' => $script['scriptid']] + $param;
				}
			}
			unset($param);

			$del_paramids = array_merge($del_paramids, array_column($db_params, 'script_paramid'));
		}
		unset($script);

		if ($ins_params) {
			$script_paramids = DB::insertBatch('script_param', $ins_params);
		}

		if ($upd_params) {
			DB::update('script_param', $upd_params);
		}

		if ($del_paramids) {
			DB::delete('script_param', ['script_paramid' => $del_paramids]);
		}

		foreach ($scripts as &$script) {
			if (!array_key_exists('parameters', $script)) {
				continue;
			}

			foreach ($script['parameters'] as &$param) {
				if (!array_key_exists('script_paramid', $param)) {
					$param['script_paramid'] = array_shift($script_paramids);
				}
			}
			unset($param);
		}
		unset($script);
	}

	/**
	 * Add the existing parameters to $db_scripts whether these are affected by the update.
	 *
	 * @param array $scripts
	 * @param array $db_scripts
	 */
	private static function addAffectedObjects(array $scripts, array &$db_scripts): void {
		$scriptids = [];

		foreach ($scripts as $script) {
			$scriptids[] = $script['scriptid'];
			$db_scripts[$script['scriptid']]['parameters'] = [];
		}

		if (!$scriptids) {
			return;
		}

		$options = [
			'output' => ['script_paramid', 'scriptid', 'name', 'value'],
			'filter' => ['scriptid' => $scriptids]
		];
		$db_parameters = DBselect(DB::makeSql('script_param', $options));

		while ($db_parameter = DBfetch($db_parameters)) {
			$db_scripts[$db_parameter['scriptid']]['parameters'][$db_parameter['script_paramid']] =
				array_diff_key($db_parameter, array_flip(['scriptid']));
		}
	}

	/**
	 * Helper function to combine trimmed menu path with name.
	 *
	 * @param array  $script  Script data.
	 *
	 * $script = [
	 *     'name' =>      (string)  Script name.
	 *     'menu_path' => (string)  Script menu path (optional).
	 * ]
	 *
	 * Example:
	 *   $script = [
	 *       'name' =>      'ABC'
	 *       'menu_path' => '/a/b'
	 *   ]
	 * Output: a/b/ABC
	 *
	 * @return string
	 */
	private static function getScriptNameAndPath(array $script): string {
		$menu_path = '';

		if (array_key_exists('menu_path', $script)) {
			$menu_path = trimPath($script['menu_path']);
		}

		$menu_path = trim($menu_path, '/');

		return $menu_path === '' ? $script['name'] : $menu_path.'/'.$script['name'];
	}

	/**
	 * Check if script belongs to action in one of the operations. If at least one usage of script is found
	 * then script scope cannot be changed. Used only for script.update method.
	 *
	 * @param array $scripts
	 * @param array $db_script
	 *
	 * $scripts = [
	 *     [
	 *         'scriptid' =>  (string)
	 *         'name' =>      (string)
	 *         'scope' =>     (int)
	 *     ]
	 * ]
	 *
	 * $db_scripts = [
	 *     <scriptid> => [
	 *         'scope' =>     (int)
	 *     ]
	 * ]
	 *
	 * @throws APIException if script belongs to at least one of action operations.
	 */
	private static function checkActions(array $scripts, array $db_scripts): void {
		// Validate if scripts belong to actions and scope can be changed.
		$action_scripts = [];

		foreach ($scripts as $script) {
			$db_script = $db_scripts[$script['scriptid']];

			if ($script['scope'] != ZBX_SCRIPT_SCOPE_ACTION && $db_script['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
				$action_scripts[$script['scriptid']] = [
					'name' => $script['name']
				];
			}
		}

		if ($action_scripts) {
			$actions = API::Action()->get([
				'output' => ['actionid', 'name'],
				'selectOperations' => ['opcommand'],
				'selectRecoveryOperations' => ['opcommand'],
				'selectUpdateOperations' => ['opcommand'],
				'scriptids' => array_keys($action_scripts)
			]);

			foreach ($actions as $action) {
				foreach (['operations', 'recovery_operations', 'update_operations'] as $operation_type) {
					foreach ($action[$operation_type] as $operation) {
						if (array_key_exists('opcommand', $operation)
								&& array_key_exists($operation['opcommand']['scriptid'], $action_scripts)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Cannot update script scope. Script "%1$s" is used in action "%2$s".',
									$action_scripts[$operation['opcommand']['scriptid']]['name'], $action['name']
								)
							);
						}
					}
				}
			}
		}
	}
}
