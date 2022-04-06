<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class containing methods for operations with discovery rules.
 */
class CDiscoveryRule extends CItemGeneral {

	public const ACCESS_RULES = parent::ACCESS_RULES + [
		'copy' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'type', 'status'];

	/**
	 * Define a set of supported pre-processing rules.
	 *
	 * @var array
	 */
	const SUPPORTED_PREPROCESSING_TYPES = [ZBX_PREPROC_REGSUB, ZBX_PREPROC_JSONPATH,
		ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_THROTTLE_TIMED_VALUE,
		ZBX_PREPROC_SCRIPT, ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_XPATH, ZBX_PREPROC_ERROR_FIELD_XML,
		ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_XML_TO_JSON
	];

	/**
	 * Define a set of supported item types.
	 *
	 * @var array
	 */
	const SUPPORTED_ITEM_TYPES = [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
		ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
		ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
	];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_EXISTS_TEMPLATE => _('Discovery rule "%1$s" already exists on "%2$s", inherited from another template.'),
			self::ERROR_EXISTS => _('Discovery rule "%1$s" already exists on "%2$s".'),
			self::ERROR_INVALID_KEY => _('Invalid key "%1$s" for discovery rule "%2$s" on "%3$s": %4$s.')
		]);
	}

	/**
	 * Get DiscoveryRule data
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['i.flags='.ZBX_FLAG_DISCOVERY_RULE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'itemids'						=> null,
			'interfaceids'					=> null,
			'inherited'						=> null,
			'templated'						=> null,
			'monitored'						=> null,
			'editable'						=> false,
			'nopermissions'					=> null,
			// filter
			'filter'						=> null,
			'search'						=> null,
			'searchByAny'					=> null,
			'startSearch'					=> false,
			'excludeSearch'					=> false,
			'searchWildcardsEnabled'		=> null,
			// output
			'output'						=> API_OUTPUT_EXTEND,
			'selectHosts'					=> null,
			'selectItems'					=> null,
			'selectTriggers'				=> null,
			'selectGraphs'					=> null,
			'selectHostPrototypes'			=> null,
			'selectFilter'					=> null,
			'selectLLDMacroPaths'			=> null,
			'selectPreprocessing'			=> null,
			'selectOverrides'				=> null,
			'countOutput'					=> false,
			'groupCount'					=> false,
			'preservekeys'					=> false,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE i.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where']['hostid'] = dbConditionInt('i.hostid', $options['hostids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = dbConditionInt('i.itemid', $options['itemids']);
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['where']['interfaceid'] = dbConditionId('i.interfaceid', $options['interfaceids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.interfaceid';
			}
		}

		// groupids
		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			if (array_key_exists('error', $options['search']) && $options['search']['error'] !== null) {
				zbx_db_search('item_rtdata ir', ['search' => ['error' => $options['search']['error']]] + $options,
					$sqlParts
				);
			}

			zbx_db_search('items i', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$sqlParts['where'][] = makeUpdateIntervalFilter('i.delay', $options['filter']['delay']);
				unset($options['filter']['delay']);
			}

			if (array_key_exists('lifetime', $options['filter']) && $options['filter']['lifetime'] !== null) {
				$options['filter']['lifetime'] = getTimeUnitFilters($options['filter']['lifetime']);
			}

			if (array_key_exists('state', $options['filter']) && $options['filter']['state'] !== null) {
				$this->dbFilter('item_rtdata ir', ['filter' => ['state' => $options['filter']['state']]] + $options,
					$sqlParts
				);
			}

			$this->dbFilter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = dbConditionString('h.host', $options['filter']['host']);
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if (!$options['countOutput']) {
				$result[$item['itemid']] = $item;
				continue;
			}

			if ($options['groupCount']) {
				$result[] = $item;
			}
			else {
				$result = $item['rowscount'];
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			if (self::dbDistinct($sqlParts)) {
				$result = $this->addNclobFieldValues($options, $result);
			}

			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);

			foreach ($result as &$rule) {
				// unset the fields that are returned in the filter
				unset($rule['formula'], $rule['evaltype']);

				if ($options['selectFilter'] !== null) {
					$filter = $this->unsetExtraFields([$rule['filter']],
						['conditions', 'formula', 'evaltype'],
						$options['selectFilter']
					);
					$filter = reset($filter);
					if (isset($filter['conditions'])) {
						foreach ($filter['conditions'] as &$condition) {
							unset($condition['item_conditionid'], $condition['itemid']);
						}
						unset($condition);
					}

					$rule['filter'] = $filter;
				}
			}
			unset($rule);
		}

		// Decode ITEM_TYPE_HTTPAGENT encoded fields.
		foreach ($result as &$item) {
			if (array_key_exists('query_fields', $item)) {
				$query_fields = ($item['query_fields'] !== '') ? json_decode($item['query_fields'], true) : [];
				$item['query_fields'] = json_last_error() ? [] : $query_fields;
			}

			if (array_key_exists('headers', $item)) {
				$item['headers'] = $this->headersStringToArray($item['headers']);
			}

			// Option 'Convert to JSON' is not supported for discovery rule.
			unset($item['output_format']);
		}
		unset($item);

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * Add DiscoveryRule.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function create($items) {
		$items = zbx_toArray($items);
		$this->checkInput($items);

		foreach ($items as &$item) {
			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				if (array_key_exists('query_fields', $item)) {
					$item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';
				}

				if (array_key_exists('headers', $item)) {
					$item['headers'] = $this->headersArrayToString($item['headers']);
				}

				if (array_key_exists('request_method', $item) && $item['request_method'] == HTTPCHECK_REQUEST_HEAD
						&& !array_key_exists('retrieve_mode', $item)) {
					$item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
				}
			}
			else {
				$item['query_fields'] = '';
				$item['headers'] = '';
			}

			// Option 'Convert to JSON' is not supported for discovery rule.
			unset($item['itemid'], $item['output_format']);
		}
		unset($item);

		// Get only hosts not templates from items
		$hosts = API::Host()->get([
			'output' => [],
			'hostids' => zbx_objectValues($items, 'hostid'),
			'preservekeys' => true
		]);
		foreach ($items as &$item) {
			if (array_key_exists($item['hostid'], $hosts)) {
				$item['rtdata'] = true;
			}
		}
		unset($item);

		$this->validateCreateLLDMacroPaths($items);
		$this->validateDependentItems($items);
		$this->createReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Update DiscoveryRule.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function update($items) {
		$items = zbx_toArray($items);

		$db_items = $this->get([
			'output' => ['itemid', 'name', 'type', 'master_itemid', 'authtype', 'allow_traps', 'retrieve_mode'],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'itemids' => zbx_objectValues($items, 'itemid'),
			'preservekeys' => true
		]);

		$this->checkInput($items, true, $db_items);
		$this->validateUpdateLLDMacroPaths($items);

		$items = $this->extendFromObjects(zbx_toHash($items, 'itemid'), $db_items, ['flags', 'type', 'authtype',
			'master_itemid'
		]);
		$this->validateDependentItems($items);

		$defaults = DB::getDefaults('items');
		$clean = [
			ITEM_TYPE_HTTPAGENT => [
				'url' => '',
				'query_fields' => '',
				'timeout' => $defaults['timeout'],
				'status_codes' => $defaults['status_codes'],
				'follow_redirects' => $defaults['follow_redirects'],
				'request_method' => $defaults['request_method'],
				'allow_traps' => $defaults['allow_traps'],
				'post_type' => $defaults['post_type'],
				'http_proxy' => '',
				'headers' => '',
				'retrieve_mode' => $defaults['retrieve_mode'],
				'output_format' => $defaults['output_format'],
				'ssl_key_password' => '',
				'verify_peer' => $defaults['verify_peer'],
				'verify_host' => $defaults['verify_host'],
				'ssl_cert_file' => '',
				'ssl_key_file' => '',
				'posts' => ''
			]
		];

		// set the default values required for updating
		foreach ($items as &$item) {
			$type_change = (array_key_exists('type', $item) && $item['type'] != $db_items[$item['itemid']]['type']);

			if (isset($item['filter'])) {
				foreach ($item['filter']['conditions'] as &$condition) {
					$condition += [
						'operator' => DB::getDefault('item_condition', 'operator')
					];
				}
				unset($condition);
			}

			if ($type_change && $db_items[$item['itemid']]['type'] == ITEM_TYPE_HTTPAGENT) {
				$item = array_merge($item, $clean[ITEM_TYPE_HTTPAGENT]);

				if ($item['type'] != ITEM_TYPE_SSH) {
					$item['authtype'] = $defaults['authtype'];
					$item['username'] = '';
					$item['password'] = '';
				}

				if ($item['type'] != ITEM_TYPE_TRAPPER) {
					$item['trapper_hosts'] = '';
				}
			}

			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				// Clean username and password when authtype is set to HTTPTEST_AUTH_NONE.
				if ($item['authtype'] == HTTPTEST_AUTH_NONE) {
					$item['username'] = '';
					$item['password'] = '';
				}

				if (array_key_exists('allow_traps', $item) && $item['allow_traps'] == HTTPCHECK_ALLOW_TRAPS_OFF
						&& $item['allow_traps'] != $db_items[$item['itemid']]['allow_traps']) {
					$item['trapper_hosts'] = '';
				}

				if (array_key_exists('query_fields', $item) && is_array($item['query_fields'])) {
					$item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';
				}

				if (array_key_exists('headers', $item) && is_array($item['headers'])) {
					$item['headers'] = $this->headersArrayToString($item['headers']);
				}

				if (array_key_exists('request_method', $item) && $item['request_method'] == HTTPCHECK_REQUEST_HEAD
						&& !array_key_exists('retrieve_mode', $item)
						&& $db_items[$item['itemid']]['retrieve_mode'] != HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
					$item['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_HEADERS;
				}
			}
			else {
				$item['query_fields'] = '';
				$item['headers'] = '';
			}

			if ($type_change && $db_items[$item['itemid']]['type'] == ITEM_TYPE_SCRIPT) {
				if ($item['type'] != ITEM_TYPE_SSH && $item['type'] != ITEM_TYPE_DB_MONITOR
						&& $item['type'] != ITEM_TYPE_TELNET && $item['type'] != ITEM_TYPE_CALCULATED) {
					$item['params'] = '';
				}

				if ($item['type'] != ITEM_TYPE_HTTPAGENT) {
					$item['timeout'] = $defaults['timeout'];
				}
			}

			// Option 'Convert to JSON' is not supported for discovery rule.
			unset($item['output_format']);
		}
		unset($item);

		// update
		$this->updateReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Delete DiscoveryRules.
	 *
	 * @param array $ruleids
	 *
	 * @return array
	 */
	public function delete(array $ruleids) {
		$this->validateDelete($ruleids);

		CDiscoveryRuleManager::delete($ruleids);

		return ['ruleids' => $ruleids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $ruleids   [IN/OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$ruleids) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $ruleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_rules = $this->get([
			'output' => ['templateid'],
			'itemids' => $ruleids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($ruleids as $ruleid) {
			if (!array_key_exists($ruleid, $db_rules)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_rule = $db_rules[$ruleid];

			if ($db_rule['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated items.'));
			}
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

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Copies the given discovery rules to the specified hosts.
	 *
	 * @throws APIException if no discovery rule IDs or host IDs are given or
	 * the user doesn't have the necessary permissions.
	 *
	 * @param array $data
	 * @param array $data['discoveryids']  An array of item ids to be cloned.
	 * @param array $data['hostids']       An array of host ids were the items should be cloned to.
	 *
	 * @return bool
	 */
	public function copy(array $data) {
		// validate data
		if (!isset($data['discoveryids']) || !$data['discoveryids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No discovery rule IDs given.'));
		}
		if (!isset($data['hostids']) || !$data['hostids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No host IDs given.'));
		}

		$this->checkHostPermissions($data['hostids']);

		// check if the given discovery rules exist
		$count = $this->get([
			'countOutput' => true,
			'itemids' => $data['discoveryids']
		]);

		if ($count != count($data['discoveryids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// copy
		foreach ($data['discoveryids'] as $discoveryid) {
			foreach ($data['hostids'] as $hostid) {
				$this->copyDiscoveryRule($discoveryid, $hostid);
			}
		}

		return true;
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 *
	 * @return array Array of discovery rule IDs.
	 */
	public function syncTemplates(array $templateids, array $hostids): array {
		$output = [];
		foreach ($this->fieldRules as $field_name => $rules) {
			if (!array_key_exists('system', $rules) && !array_key_exists('host', $rules)) {
				$output[] = $field_name;
			}
		}

		$tpl_items = $this->get([
			'output' => $output,
			'selectFilter' => ['formula', 'evaltype', 'conditions'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'hostids' => $templateids,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		foreach ($tpl_items as &$item) {
			if ($item['type'] == ITEM_TYPE_HTTPAGENT) {
				if (array_key_exists('query_fields', $item) && is_array($item['query_fields'])) {
					$item['query_fields'] = $item['query_fields'] ? json_encode($item['query_fields']) : '';
				}

				if (array_key_exists('headers', $item) && is_array($item['headers'])) {
					$item['headers'] = $this->headersArrayToString($item['headers']);
				}
			}
			else {
				$item['query_fields'] = '';
				$item['headers'] = '';
			}

			// Option 'Convert to JSON' is not supported for discovery rule.
			unset($item['output_format']);
		}
		unset($item);

		$this->inherit($tpl_items, $hostids);

		return array_keys($tpl_items);
	}

	/**
	 * Copies all of the triggers from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if trigger saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $srcHost         The host the source discovery belongs to
	 * @param array $dstHost         The host the target discovery belongs to
	 *
	 * @return array
	 */
	protected function copyTriggerPrototypes(array $srcDiscovery, array $srcHost, array $dstHost) {
		$srcTriggers = API::TriggerPrototype()->get([
			'discoveryids' => $srcDiscovery['itemid'],
			'output' => ['triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments',
				'templateid', 'type', 'recovery_mode', 'recovery_expression', 'correlation_mode', 'correlation_tag',
				'opdata', 'discover', 'event_name'
			],
			'selectHosts' => API_OUTPUT_EXTEND,
			'selectItems' => ['itemid', 'type'],
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectFunctions' => API_OUTPUT_EXTEND,
			'selectDependencies' => ['triggerid'],
			'selectTags' => ['tag', 'value'],
			'preservekeys' => true
		]);

		foreach ($srcTriggers as $id => $trigger) {
			// Skip trigger prototypes with web items and remove them from source.
			if (httpItemExists($trigger['items'])) {
				unset($srcTriggers[$id]);
			}
		}

		if (!$srcTriggers) {
			return [];
		}

		/*
		 * Copy the remaining trigger prototypes to a new source. These will contain IDs and original dependencies.
		 * The dependencies from $srcTriggers will be removed.
		 */
		$trigger_prototypes = $srcTriggers;

		// Contains original trigger prototype dependency IDs.
		$dep_triggerids = [];

		/*
		 * Collect dependency trigger IDs and remove them from source. Otherwise these IDs do not pass
		 * validation, since they don't belong to destination discovery rule.
		 */
		$add_dependencies = false;

		foreach ($srcTriggers as $id => &$trigger) {
			if ($trigger['dependencies']) {
				foreach ($trigger['dependencies'] as $dep_trigger) {
					$dep_triggerids[] = $dep_trigger['triggerid'];
				}
				$add_dependencies = true;
			}
			unset($trigger['dependencies']);
		}
		unset($trigger);

		// Save new trigger prototypes and without dependencies for now.
		$dstTriggers = $srcTriggers;
		$dstTriggers = CMacrosResolverHelper::resolveTriggerExpressions($dstTriggers,
			['sources' => ['expression', 'recovery_expression']]
		);
		foreach ($dstTriggers as $id => &$trigger) {
			unset($trigger['triggerid'], $trigger['templateid'], $trigger['hosts'], $trigger['functions'],
				$trigger['items'], $trigger['discoveryRule']
			);

			// Update the destination expressions.
			$trigger['expression'] = triggerExpressionReplaceHost($trigger['expression'], $srcHost['host'],
				$dstHost['host']
			);
			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				$trigger['recovery_expression'] = triggerExpressionReplaceHost($trigger['recovery_expression'],
					$srcHost['host'], $dstHost['host']
				);
			}
		}
		unset($trigger);

		$result = API::TriggerPrototype()->create($dstTriggers);
		if (!$result) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone trigger prototypes.'));
		}

		// Process dependencies, if at least one trigger prototype has a dependency.
		if ($add_dependencies) {
			$trigger_prototypeids = array_keys($trigger_prototypes);

			foreach ($result['triggerids'] as $i => $triggerid) {
				$new_trigger_prototypes[$trigger_prototypeids[$i]] = [
					'new_triggerid' => $triggerid,
					'new_hostid' => $dstHost['hostid'],
					'new_host' => $dstHost['host'],
					'src_hostid' => $srcHost['hostid'],
					'src_host' => $srcHost['host']
				];
			}

			/*
			 * Search for original dependent triggers and expressions to find corresponding triggers on destination host
			 * with same expression.
			 */
			$dep_triggers = API::Trigger()->get([
				'output' => ['description', 'expression'],
				'selectHosts' => ['hostid'],
				'triggerids' => $dep_triggerids,
				'preservekeys' => true
			]);
			$dep_triggers = CMacrosResolverHelper::resolveTriggerExpressions($dep_triggers);

			// Map dependencies to the new trigger IDs and save.
			foreach ($trigger_prototypes as &$trigger_prototype) {
				// Get corresponding created trigger prototype ID.
				$new_trigger_prototype = $new_trigger_prototypes[$trigger_prototype['triggerid']];

				if ($trigger_prototype['dependencies']) {
					foreach ($trigger_prototype['dependencies'] as &$dependency) {
						$dep_triggerid = $dependency['triggerid'];

						/*
						 * We have added a dependent trigger prototype and we know corresponding trigger prototype ID
						 * for newly created trigger prototype.
						 */
						if (array_key_exists($dependency['triggerid'], $new_trigger_prototypes)) {
							/*
							 * Dependency is within same host according to $srcHostId parameter or dep trigger has
							 * single host.
							 */
							if ($new_trigger_prototype['src_hostid'] ==
									$new_trigger_prototypes[$dep_triggerid]['src_hostid']) {
								$dependency['triggerid'] = $new_trigger_prototypes[$dep_triggerid]['new_triggerid'];
							}
						}
						elseif (in_array(['hostid' => $new_trigger_prototype['src_hostid']],
								$dep_triggers[$dep_triggerid]['hosts'])) {
							// Get all possible $depTrigger matching triggers by description.
							$target_triggers = API::Trigger()->get([
								'output' => ['hosts', 'triggerid', 'expression'],
								'hostids' => $new_trigger_prototype['new_hostid'],
								'filter' => ['description' => $dep_triggers[$dep_triggerid]['description']],
								'preservekeys' => true
							]);
							$target_triggers = CMacrosResolverHelper::resolveTriggerExpressions($target_triggers);

							// Compare exploded expressions for exact match.
							$expr1 = $dep_triggers[$dep_triggerid]['expression'];
							$dependency['triggerid'] = null;

							foreach ($target_triggers as $target_trigger) {
								$expr2 = triggerExpressionReplaceHost($target_trigger['expression'],
									$new_trigger_prototype['new_host'],
									$new_trigger_prototype['src_host']
								);

								if ($expr2 === $expr1) {
									// Matching trigger has been found.
									$dependency['triggerid'] = $target_trigger['triggerid'];
									break;
								}
							}

							// If matching trigger was not found, raise exception.
							if ($dependency['triggerid'] === null) {
								$expr2 = triggerExpressionReplaceHost($dep_triggers[$dep_triggerid]['expression'],
									$new_trigger_prototype['src_host'],
									$new_trigger_prototype['new_host']
								);
								self::exception(ZBX_API_ERROR_PARAMETERS, _s(
									'Cannot add dependency from trigger "%1$s:%2$s" to non existing trigger "%3$s:%4$s".',
									$trigger_prototype['description'],
									$trigger_prototype['expression'],
									$dep_triggers[$dep_triggerid]['description'],
									$expr2
								));
							}
						}
					}
					unset($dependency);

					$trigger_prototype['triggerid'] = $new_trigger_prototype['new_triggerid'];
				}
			}
			unset($trigger_prototype);

			// If adding a dependency fails, the exception will be raised in TriggerPrototype API.
			API::TriggerPrototype()->addDependencies($trigger_prototypes);
		}

		return $result;
	}

	protected function createReal(array &$items) {
		$items_rtdata = [];
		$create_items = [];

		// create items without formulas, they will be updated when items and conditions are saved
		foreach ($items as $key => $item) {
			if (array_key_exists('filter', $item)) {
				$item['evaltype'] = $item['filter']['evaltype'];
				unset($item['filter']);
			}

			if (array_key_exists('rtdata', $item)) {
				$items_rtdata[$key] = [];
				unset($item['rtdata']);
			}

			$create_items[] = $item;
		}
		$create_items = DB::save('items', $create_items);

		foreach ($items_rtdata as $key => &$value) {
			$value['itemid'] = $create_items[$key]['itemid'];
		}
		unset($value);

		DB::insert('item_rtdata', $items_rtdata, false);

		$conditions = [];
		$itemids = [];

		foreach ($items as $key => &$item) {
			$item['itemid'] = $create_items[$key]['itemid'];
			$itemids[$key] = $item['itemid'];

			// conditions
			if (isset($item['filter'])) {
				foreach ($item['filter']['conditions'] as $condition) {
					$condition['itemid'] = $item['itemid'];

					$conditions[] = $condition;
				}
			}
		}
		unset($item);

		$conditions = DB::save('item_condition', $conditions);

		$item_conditions = [];

		foreach ($conditions as $condition) {
			$item_conditions[$condition['itemid']][] = $condition;
		}

		$lld_macro_paths = [];

		foreach ($items as $item) {
			// update formulas
			if (isset($item['filter']) && $item['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$this->updateFormula($item['itemid'], $item['filter']['formula'], $item_conditions[$item['itemid']]);
			}

			// $item['lld_macro_paths'] expects to be filled with validated fields 'lld_macro' and 'path' and values.
			if (array_key_exists('lld_macro_paths', $item)) {
				foreach ($item['lld_macro_paths'] as $lld_macro_path) {
					$lld_macro_paths[] = $lld_macro_path + ['itemid' => $item['itemid']];
				}
			}
		}

		DB::insertBatch('lld_macro_path', $lld_macro_paths);

		$this->createItemParameters($items, $itemids);
		$this->createItemPreprocessing($items);
		$this->createOverrides($items);
	}

	/**
	 * Creates overrides for low-level discovery rules.
	 *
	 * @param array $items  Low-level discovery rules.
	 */
	protected function createOverrides(array $items) {
		$overrides = [];

		foreach ($items as $item) {
			if (array_key_exists('overrides', $item)) {
				foreach ($item['overrides'] as $override) {
					// Formula will be added after conditions.
					$new_override = [
						'itemid' => $item['itemid'],
						'name' => $override['name'],
						'step' => $override['step'],
						'stop' => array_key_exists('stop', $override) ? $override['stop'] : ZBX_LLD_OVERRIDE_STOP_NO
					];

					$new_override['evaltype'] = array_key_exists('filter', $override)
						? $override['filter']['evaltype']
						: DB::getDefault('lld_override', 'evaltype');

					$overrides[] = $new_override;
				}
			}
		}

		$overrideids = DB::insertBatch('lld_override', $overrides);

		if ($overrideids) {
			$ovrd_conditions = [];
			$ovrd_idx = 0;
			$cnd_idx = 0;

			foreach ($items as &$item) {
				if (array_key_exists('overrides', $item)) {
					foreach ($item['overrides'] as &$override) {
						$override['lld_overrideid'] = $overrideids[$ovrd_idx++];

						if (array_key_exists('filter', $override)) {
							foreach ($override['filter']['conditions'] as $condition) {
								$ovrd_conditions[] = [
									'macro' => $condition['macro'],
									'value' => $condition['value'],
									'formulaid' => array_key_exists('formulaid', $condition)
										? $condition['formulaid']
										: '',
									'operator' => array_key_exists('operator', $condition)
										? $condition['operator']
										: DB::getDefault('lld_override_condition', 'operator'),
									'lld_overrideid' => $override['lld_overrideid']
								];
							}
						}
					}
					unset($override);
				}
			}
			unset($item);

			$conditionids = DB::insertBatch('lld_override_condition', $ovrd_conditions);

			$ids = [];

			if ($conditionids) {
				foreach ($items as &$item) {
					if (array_key_exists('overrides', $item)) {
						foreach ($item['overrides'] as &$override) {
							if (array_key_exists('filter', $override)) {
								foreach ($override['filter']['conditions'] as &$condition) {
									$condition['lld_override_conditionid'] = $conditionids[$cnd_idx++];
								}
								unset($condition);
							}
						}
						unset($override);
					}
				}
				unset($item);

				foreach ($items as $item) {
					if (array_key_exists('overrides', $item)) {
						foreach ($item['overrides'] as $override) {
							if (array_key_exists('filter', $override)
									&& $override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
								$ids = [];
								foreach ($override['filter']['conditions'] as $condition) {
									$ids[$condition['formulaid']] = $condition['lld_override_conditionid'];
								}

								$formula = CConditionHelper::replaceLetterIds($override['filter']['formula'], $ids);
								DB::updateByPk('lld_override', $override['lld_overrideid'], ['formula' => $formula]);
							}
						}
					}
				}
			}

			$operations = [];
			foreach ($items as $item) {
				if (array_key_exists('overrides', $item)) {
					foreach ($item['overrides'] as $override) {
						if (array_key_exists('operations', $override)) {
							foreach ($override['operations'] as $operation) {
								$operations[] = [
									'lld_overrideid' => $override['lld_overrideid'],
									'operationobject' => $operation['operationobject'],
									'operator' => array_key_exists('operator', $operation)
										? $operation['operator']
										: DB::getDefault('lld_override_operation', 'operator'),
									'value' => array_key_exists('value', $operation) ? $operation['value'] : ''
								];
							}
						}
					}
				}
			}

			$operationids = DB::insertBatch('lld_override_operation', $operations);

			$opr_idx = 0;
			$opstatus = [];
			$opdiscover = [];
			$opperiod = [];
			$ophistory = [];
			$optrends = [];
			$opseverity = [];
			$optag = [];
			$optemplate = [];
			$opinventory = [];

			foreach ($items as $item) {
				if (array_key_exists('overrides', $item)) {
					foreach ($item['overrides'] as $override) {
						if (array_key_exists('operations', $override)) {
							foreach ($override['operations'] as $operation) {
								$operation['lld_override_operationid'] = $operationids[$opr_idx++];

								// Discover status applies to all operation object types.
								if (array_key_exists('opdiscover', $operation)) {
									$opdiscover[] = [
										'lld_override_operationid' => $operation['lld_override_operationid'],
										'discover' => $operation['opdiscover']['discover']
									];
								}

								switch ($operation['operationobject']) {
									case OPERATION_OBJECT_ITEM_PROTOTYPE:
										if (array_key_exists('opstatus', $operation)) {
											$opstatus[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'status' => $operation['opstatus']['status']
											];
										}

										if (array_key_exists('opperiod', $operation)) {
											$opperiod[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'delay' => $operation['opperiod']['delay']
											];
										}

										if (array_key_exists('ophistory', $operation)) {
											$ophistory[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'history' => $operation['ophistory']['history']
											];
										}

										if (array_key_exists('optrends', $operation)) {
											$optrends[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'trends' => $operation['optrends']['trends']
											];
										}

										if (array_key_exists('optag', $operation)) {
											foreach ($operation['optag'] as $tag) {
												$optag[] = [
													'lld_override_operationid' =>
														$operation['lld_override_operationid'],
													'tag' => $tag['tag'],
													'value'	=> array_key_exists('value', $tag) ? $tag['value'] : ''
												];
											}
										}
										break;

									case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
										if (array_key_exists('opstatus', $operation)) {
											$opstatus[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'status' => $operation['opstatus']['status']
											];
										}

										if (array_key_exists('opseverity', $operation)) {
											$opseverity[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'severity' => $operation['opseverity']['severity']
											];
										}

										if (array_key_exists('optag', $operation)) {
											foreach ($operation['optag'] as $tag) {
												$optag[] = [
													'lld_override_operationid' =>
														$operation['lld_override_operationid'],
													'tag' => $tag['tag'],
													'value'	=> array_key_exists('value', $tag) ? $tag['value'] : ''
												];
											}
										}
										break;

									case OPERATION_OBJECT_HOST_PROTOTYPE:
										if (array_key_exists('opstatus', $operation)) {
											$opstatus[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'status' => $operation['opstatus']['status']
											];
										}

										if (array_key_exists('optemplate', $operation)) {
											foreach ($operation['optemplate'] as $template) {
												$optemplate[] = [
													'lld_override_operationid' =>
														$operation['lld_override_operationid'],
													'templateid' => $template['templateid']
												];
											}
										}

										if (array_key_exists('optag', $operation)) {
											foreach ($operation['optag'] as $tag) {
												$optag[] = [
													'lld_override_operationid' =>
														$operation['lld_override_operationid'],
													'tag' => $tag['tag'],
													'value'	=> array_key_exists('value', $tag) ? $tag['value'] : ''
												];
											}
										}

										if (array_key_exists('opinventory', $operation)) {
											$opinventory[] = [
												'lld_override_operationid' => $operation['lld_override_operationid'],
												'inventory_mode' => $operation['opinventory']['inventory_mode']
											];
										}
										break;
								}
							}
						}
					}
				}
			}

			DB::insertBatch('lld_override_opstatus', $opstatus, false);
			DB::insertBatch('lld_override_opdiscover', $opdiscover, false);
			DB::insertBatch('lld_override_opperiod', $opperiod, false);
			DB::insertBatch('lld_override_ophistory', $ophistory, false);
			DB::insertBatch('lld_override_optrends', $optrends, false);
			DB::insertBatch('lld_override_opseverity', $opseverity, false);
			DB::insertBatch('lld_override_optag', $optag);
			DB::insertBatch('lld_override_optemplate', $optemplate);
			DB::insertBatch('lld_override_opinventory', $opinventory, false);
		}
	}

	protected function updateReal(array $items) {
		CArrayHelper::sort($items, ['itemid']);

		$ruleIds = zbx_objectValues($items, 'itemid');

		$data = [];
		foreach ($items as $item) {
			$values = $item;

			if (isset($item['filter'])) {
				// clear the formula for non-custom expression rules
				if ($item['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					$values['formula'] = '';
				}

				$values['evaltype'] = $item['filter']['evaltype'];
				unset($values['filter']);
			}

			$data[] = ['values' => $values, 'where' => ['itemid' => $item['itemid']]];
		}
		DB::update('items', $data);

		$newRuleConditions = null;
		foreach ($items as $item) {
			// conditions
			if (isset($item['filter'])) {
				if ($newRuleConditions === null) {
					$newRuleConditions = [];
				}

				$newRuleConditions[$item['itemid']] = [];
				foreach ($item['filter']['conditions'] as $condition) {
					$condition['itemid'] = $item['itemid'];

					$newRuleConditions[$item['itemid']][] = $condition;
				}
			}
		}

		// replace conditions
		$ruleConditions = [];
		if ($newRuleConditions !== null) {
			// fetch existing conditions
			$exConditions = DBfetchArray(DBselect(
				'SELECT item_conditionid,itemid,macro,value,operator'.
				' FROM item_condition'.
				' WHERE '.dbConditionInt('itemid', $ruleIds).
				' ORDER BY item_conditionid'
			));
			$exRuleConditions = [];
			foreach ($exConditions as $condition) {
				$exRuleConditions[$condition['itemid']][] = $condition;
			}

			// replace and add the new IDs
			$conditions = DB::replaceByPosition('item_condition', $exRuleConditions, $newRuleConditions);
			foreach ($conditions as $condition) {
				$ruleConditions[$condition['itemid']][] = $condition;
			}
		}

		$itemids = [];
		$lld_macro_paths = [];
		$db_lld_macro_paths = [];

		foreach ($items as $item) {
			// update formulas
			if (isset($item['filter']) && $item['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$this->updateFormula($item['itemid'], $item['filter']['formula'], $ruleConditions[$item['itemid']]);
			}

			// "lld_macro_paths" could be empty or filled with fields "lld_macro", "path" or "lld_macro_pathid".
			if (array_key_exists('lld_macro_paths', $item)) {
				$itemids[$item['itemid']] = true;

				if ($item['lld_macro_paths']) {
					foreach ($item['lld_macro_paths'] as $lld_macro_path) {
						$lld_macro_paths[] = $lld_macro_path + ['itemid' => $item['itemid']];
					}
				}
			}
		}

		// Gather all existing LLD macros from given discovery rules.
		if ($itemids) {
			$db_lld_macro_paths = DB::select('lld_macro_path', [
				'output' => ['lld_macro_pathid', 'itemid', 'lld_macro', 'path'],
				'filter' => ['itemid' => array_keys($itemids)]
			]);
		}

		/*
		 * DB::replaceByPosition() does not allow to change records one by one due to unique indexes on two table
		 * columns. Problems arise when given records are the same as records in DB and they are sorted differently.
		 * That's why checking differences between old and new records is done manually.
		 */

		$lld_macro_paths_to_update = [];

		foreach ($lld_macro_paths as $idx1 => $lld_macro_path) {
			foreach ($db_lld_macro_paths as $idx2 => $db_lld_macro_path) {
				if (array_key_exists('lld_macro_pathid', $lld_macro_path)) {
					// Update records by primary key.

					// Find matching "lld_macro_pathid" and update fields accordingly.
					if (bccomp($lld_macro_path['lld_macro_pathid'], $db_lld_macro_path['lld_macro_pathid']) == 0) {
						$fields_to_update = [];

						if (array_key_exists('lld_macro', $lld_macro_path)
								&& $lld_macro_path['lld_macro'] === $db_lld_macro_path['lld_macro']) {
							// If same "lld_macro" is found in DB, update only "path" if necessary.

							if (array_key_exists('path', $lld_macro_path)
									&& $lld_macro_path['path'] !== $db_lld_macro_path['path']) {
								$fields_to_update['path'] = $lld_macro_path['path'];
							}
						}
						else {
							/*
							 * Update all other fields that correspond to given "lld_macro_pathid". Except for primary
							 * key "lld_macro_pathid" and "itemid".
							 */

							foreach ($lld_macro_path as $field => $value) {
								if ($field !== 'itemid' && $field !== 'lld_macro_pathid') {
									$fields_to_update[$field] = $value;
								}
							}
						}

						/*
						 * If there are any changes made, update fields in DB. Otherwise skip updating and result in
						 * success anyway.
						 */
						if ($fields_to_update) {
							$lld_macro_paths_to_update[] = $fields_to_update
								+ ['lld_macro_pathid' => $lld_macro_path['lld_macro_pathid']];
						}

						/*
						 * Remove processed LLD macros from the list. Macros left in $db_lld_macro_paths will be removed
						 * afterwards.
						 */
						unset($db_lld_macro_paths[$idx2]);
						unset($lld_macro_paths[$idx1]);
					}
					// Incorrect "lld_macro_pathid" cannot be given due to validation done previously.
				}
				else {
					// Add or update fields by given "lld_macro".

					if (bccomp($lld_macro_path['itemid'], $db_lld_macro_path['itemid']) == 0) {
						if ($lld_macro_path['lld_macro'] === $db_lld_macro_path['lld_macro']) {
							// If same "lld_macro" is given, add primary key and update only "path", if necessary.

							if ($lld_macro_path['path'] !== $db_lld_macro_path['path']) {
								$lld_macro_paths_to_update[] = [
									'lld_macro_pathid' => $db_lld_macro_path['lld_macro_pathid'],
									'path' => $lld_macro_path['path']
								];
							}

							/*
							 * Remove processed LLD macros from the list. Macros left in $db_lld_macro_paths will
							 * be removed afterwards. And macros left in $lld_macro_paths will be created.
							 */
							unset($db_lld_macro_paths[$idx2]);
							unset($lld_macro_paths[$idx1]);
						}
					}
				}
			}
		}

		// After all data has been collected, proceed with record update in DB.
		$lld_macro_pathids_to_delete = zbx_objectValues($db_lld_macro_paths, 'lld_macro_pathid');

		if ($lld_macro_pathids_to_delete) {
			DB::delete('lld_macro_path', ['lld_macro_pathid' => $lld_macro_pathids_to_delete]);
		}

		if ($lld_macro_paths_to_update) {
			$data = [];

			foreach ($lld_macro_paths_to_update as $lld_macro_path) {
				$data[] = [
					'values' => $lld_macro_path,
					'where' => [
						'lld_macro_pathid' => $lld_macro_path['lld_macro_pathid']
					]
				];
			}

			DB::update('lld_macro_path', $data);
		}

		DB::insertBatch('lld_macro_path', $lld_macro_paths);

		$this->updateItemParameters($items);
		$this->updateItemPreprocessing($items);

		// Delete old overrides and replace with new ones if any.
		$ovrd_itemids = [];
		foreach ($items as $item) {
			if (array_key_exists('overrides', $item)) {
				$ovrd_itemids[$item['itemid']] = true;
			}
		}

		if ($ovrd_itemids) {
			DBexecute('DELETE FROM lld_override WHERE '.dbConditionId('itemid', array_keys($ovrd_itemids)));
		}

		$this->createOverrides($items);
	}

	/**
	 * Converts a formula with letters to a formula with IDs and updates it.
	 *
	 * @param string 	$itemId
	 * @param string 	$evalFormula		formula with letters
	 * @param array 	$conditions
	 */
	protected function updateFormula($itemId, $evalFormula, array $conditions) {
		$ids = [];
		foreach ($conditions as $condition) {
			$ids[$condition['formulaid']] = $condition['item_conditionid'];
		}
		$formula = CConditionHelper::replaceLetterIds($evalFormula, $ids);

		DB::updateByPk('items', $itemId, [
			'formula' => $formula
		]);
	}

	/**
	 * Check item data and set missing default values.
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 * @param array $dbItems
	 */
	protected function checkInput(array &$items, $update = false, array $dbItems = []) {
		// add the values that cannot be changed, but are required for further processing
		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_RULE;
			$item['value_type'] = ITEM_VALUE_TYPE_TEXT;

			// unset fields that are updated using the 'filter' parameter
			unset($item['evaltype']);
			unset($item['formula']);
		}
		unset($item);

		parent::checkInput($items, $update);

		$validateItems = $items;
		if ($update) {
			$validateItems = $this->extendFromObjects(zbx_toHash($validateItems, 'itemid'), $dbItems, ['name']);
		}

		// filter validator
		$filterValidator = new CSchemaValidator($this->getFilterSchema());

		// condition validation
		$conditionValidator = new CSchemaValidator($this->getFilterConditionSchema());
		foreach ($validateItems as $item) {
			// validate custom formula and conditions
			if (isset($item['filter'])) {
				$filterValidator->setObjectName($item['name']);
				$this->checkValidator($item['filter'], $filterValidator);

				foreach ($item['filter']['conditions'] as $condition) {
					$conditionValidator->setObjectName($item['name']);
					$this->checkValidator($condition, $conditionValidator);
				}
			}
		}

		$this->validateOverrides($validateItems);
	}

	/**
	 * Validate low-level discovery rule overrides.
	 *
	 * @param array $items  Low-level discovery rules.
	 *
	 * @throws APIException
	 */
	protected function validateOverrides(array $items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name'], ['step']], 'fields' => [
			'step' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => '1:'.ZBX_MAX_INT32],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override', 'name')],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_OVERRIDE_STOP_NO, ZBX_LLD_OVERRIDE_STOP_YES]), 'default' => ZBX_LLD_OVERRIDE_STOP_NO],
			'filter' =>			['type' => API_OBJECT, 'fields' => [
				'evaltype' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
				'formula' =>		['type' => API_STRING_UTF8],
				'conditions' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
					'macro' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_condition', 'macro')],
					'operator' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]), 'default' => DB::getDefault('lld_override_condition', 'operator')],
					'value' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('lld_override_condition', 'value')],
					'formulaid' =>		['type' => API_STRING_UTF8]
				]]
			]],
			'operations' =>	['type' => API_OBJECTS, 'fields' => [
				'operationobject' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_GRAPH_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE])],
				'operator' =>			['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]), 'default' => DB::getDefault('lld_override_operation', 'operator')],
				'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('lld_override_operation', 'value')],
				'opstatus' =>			['type' => API_OBJECT, 'fields' => [
					'status' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PROTOTYPE_STATUS_ENABLED, ZBX_PROTOTYPE_STATUS_DISABLED])]
				]],
				'opdiscover' =>			['type' => API_OBJECT, 'fields' => [
					'discover' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER])]
				]],
				'opperiod' =>			['type' => API_OBJECT, 'fields' => [
					'delay' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_opperiod', 'delay')]
				]],
				'ophistory' =>			['type' => API_OBJECT, 'fields' => [
					'history' =>			['type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('lld_override_ophistory', 'history')]
				]],
				'optrends' =>			['type' => API_OBJECT, 'fields' => [
					'trends' =>				['type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('lld_override_optrends', 'trends')]
				]],
				'opseverity' =>			['type' => API_OBJECT, 'fields' => [
					'severity' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))]
				]],
				'optag' =>				['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['tag', 'value']], 'fields' => [
					'tag' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_override_optag', 'tag')],
					'value' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('lld_override_optag', 'value'), 'default' => DB::getDefault('lld_override_optag', 'value')]
				]],
				'optemplate' =>			['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
					'templateid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
				]],
				'opinventory' =>		['type' => API_OBJECT, 'fields' => [
					'inventory_mode' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC])]
				]]
			]]
		]];

		// Schema for filter is already validated in API validator. Create the formula validator for filter.
		$condition_validator = new CConditionValidator([
			'messageMissingFormula' => _('Formula missing for override "%1$s".'),
			'messageInvalidFormula' => _('Incorrect custom expression "%2$s" for override "%1$s": %3$s.'),
			'messageMissingCondition' =>
				_('Condition "%2$s" used in formula "%3$s" for override "%1$s" is not defined.'),
			'messageUnusedCondition' => _('Condition "%2$s" is not used in formula "%3$s" for override "%1$s".')
		]);

		$update_interval_parser = new CUpdateIntervalParser([
			'usermacros' => true,
			'lldmacros' => true
		]);

		$lld_idx = 0;
		foreach ($items as $item) {
			if (array_key_exists('overrides', $item)) {
				$path = '/'.(++$lld_idx).'/overrides';

				if (!CApiInputValidator::validate($api_input_rules, $item['overrides'], $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				foreach ($item['overrides'] as $ovrd_idx => $override) {
					if (array_key_exists('filter', $override)) {
						$condition_validator->setObjectName($override['name']);

						// Validate the formula and check if they are in the conditions.
						if (!$condition_validator->validate($override['filter'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, $condition_validator->getError());
						}

						// Validate that conditions have correct macros and 'formulaid' for custom expressions.
						if (array_key_exists('conditions', $override['filter'])) {
							foreach ($override['filter']['conditions'] as $cnd_idx => $condition) {
								// API validator only checks if 'macro' field exists and is not empty. It must be macro.
								if (!preg_match('/^'.ZBX_PREG_EXPRESSION_LLD_MACROS.'$/', $condition['macro'])) {
									self::exception(ZBX_API_ERROR_PARAMETERS,
										_s('Incorrect filter condition macro for override "%1$s".', $override['name'])
									);
								}

								if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
									/*
									 * Check only if 'formulaid' exists. It cannot be empty or incorrect, but that is
									 * already validated by previously set conditionValidator.
									 */
									if (!array_key_exists('formulaid', $condition)) {
										$cond_path = $path.'/'.($ovrd_idx + 1).'/filter/conditions/'.($cnd_idx + 1);
										self::exception(ZBX_API_ERROR_PARAMETERS,
											_s('Invalid parameter "%1$s": %2$s.', $path,
												_s('the parameter "%1$s" is missing', 'formulaid')
											)
										);
									}
								}
							}
						}
					}

					// Check integrity of 'overrideobject' and its fields.
					if (array_key_exists('operations', $override)) {
						foreach ($override['operations'] as $opr_idx => $operation) {
							$opr_path = $path.'/'.($ovrd_idx + 1).'/operations/'.($opr_idx + 1);

							switch ($operation['operationobject']) {
								case OPERATION_OBJECT_ITEM_PROTOTYPE:
									foreach (['opseverity', 'optemplate', 'opinventory'] as $field) {
										if (array_key_exists($field, $operation)) {
											self::exception(ZBX_API_ERROR_PARAMETERS,
												_s('Invalid parameter "%1$s": %2$s.', $opr_path,
													_s('unexpected parameter "%1$s"', $field)
												)
											);
										}
									}

									if (!array_key_exists('opstatus', $operation)
											&& !array_key_exists('opperiod', $operation)
											&& !array_key_exists('ophistory', $operation)
											&& !array_key_exists('optrends', $operation)
											&& !array_key_exists('optag', $operation)
											&& !array_key_exists('opdiscover', $operation)) {
										self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
											$opr_path, _s('value must be one of %1$s',
												'opstatus, opdiscover, opperiod, ophistory, optrends, optag'
											)
										));
									}

									if (array_key_exists('opperiod', $operation)
											&& !validateDelay($update_interval_parser, 'delay',
												$operation['opperiod']['delay'], $error)) {
										self::exception(ZBX_API_ERROR_PARAMETERS, $error);
									}
									break;

								case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
									foreach (['opperiod', 'ophistory', 'optrends', 'optemplate', 'opinventory'] as
											$field) {
										if (array_key_exists($field, $operation)) {
											self::exception(ZBX_API_ERROR_PARAMETERS,
												_s('Invalid parameter "%1$s": %2$s.', $opr_path,
													_s('unexpected parameter "%1$s"', $field)
												)
											);
										}
									}

									if (!array_key_exists('opstatus', $operation)
											&& !array_key_exists('opseverity', $operation)
											&& !array_key_exists('optag', $operation)
											&& !array_key_exists('opdiscover', $operation)) {
										self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
											$opr_path,
											_s('value must be one of %1$s', 'opstatus, opdiscover, opseverity, optag')
										));
									}
									break;

								case OPERATION_OBJECT_GRAPH_PROTOTYPE:
									foreach (['opstatus', 'opperiod', 'ophistory', 'optrends', 'opseverity', 'optag',
											'optemplate', 'opinventory'] as $field) {
										if (array_key_exists($field, $operation)) {
											self::exception(ZBX_API_ERROR_PARAMETERS,
												_s('Invalid parameter "%1$s": %2$s.', $opr_path,
													_s('unexpected parameter "%1$s"', $field)
												)
											);
										}
									}

									if (!array_key_exists('opdiscover', $operation)) {
										self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
											$opr_path,
											_s('value must be one of %1$s', 'opdiscover')
										));
									}
									break;

								case OPERATION_OBJECT_HOST_PROTOTYPE:
									foreach (['opperiod', 'ophistory', 'optrends', 'opseverity'] as $field) {
										if (array_key_exists($field, $operation)) {
											self::exception(ZBX_API_ERROR_PARAMETERS,
												_s('Invalid parameter "%1$s": %2$s.', $opr_path,
													_s('unexpected parameter "%1$s"', $field)
												)
											);
										}
									}

									if (!array_key_exists('opstatus', $operation)
											&& !array_key_exists('optemplate', $operation)
											&& !array_key_exists('optag', $operation)
											&& !array_key_exists('opinventory', $operation)
											&& !array_key_exists('opdiscover', $operation)) {
										self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
											$opr_path, _s('value must be one of %1$s',
												'opstatus, opdiscover, optemplate, optag, opinventory'
											)
										));
									}

									if (array_key_exists('optemplate', $operation)) {
										$templates_cnt = API::Template()->get([
											'countOutput' => true,
											'templateids' => zbx_objectValues($operation['optemplate'], 'templateid')
										]);

										if (count($operation['optemplate']) != $templates_cnt) {
											self::exception(ZBX_API_ERROR_PERMISSIONS,
												_('No permissions to referred object or it does not exist!')
											);
										}
									}
									break;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Returns the parameters for creating a discovery rule filter validator.
	 *
	 * @return array
	 */
	protected function getFilterSchema() {
		return [
			'validators' => [
				'evaltype' => new CLimitedSetValidator([
					'values' => [
						CONDITION_EVAL_TYPE_OR,
						CONDITION_EVAL_TYPE_AND,
						CONDITION_EVAL_TYPE_AND_OR,
						CONDITION_EVAL_TYPE_EXPRESSION
					],
					'messageInvalid' => _('Incorrect type of calculation for discovery rule "%1$s".')
				]),
				'formula' => new CStringValidator([
					'empty' => true
				]),
				'conditions' => new CCollectionValidator([
					'empty' => true,
					'messageInvalid' => _('Incorrect conditions for discovery rule "%1$s".')
				])
			],
			'postValidators' => [
				new CConditionValidator([
					'messageMissingFormula' => _('Formula missing for discovery rule "%1$s".'),
					'messageInvalidFormula' => _('Incorrect custom expression "%2$s" for discovery rule "%1$s": %3$s.'),
					'messageMissingCondition' => _('Condition "%2$s" used in formula "%3$s" for discovery rule "%1$s" is not defined.'),
					'messageUnusedCondition' => _('Condition "%2$s" is not used in formula "%3$s" for discovery rule "%1$s".')
				])
			],
			'required' => ['evaltype', 'conditions'],
			'messageRequired' => _('No "%2$s" given for the filter of discovery rule "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for the filter of discovery rule "%1$s".')
		];
	}

	/**
	 * Returns the parameters for creating a discovery rule filter condition validator.
	 *
	 * @return array
	 */
	protected function getFilterConditionSchema() {
		return [
			'validators' => [
				'macro' => new CStringValidator([
					'regex' => '/^'.ZBX_PREG_EXPRESSION_LLD_MACROS.'$/',
					'messageEmpty' => _('Empty filter condition macro for discovery rule "%1$s".'),
					'messageRegex' => _('Incorrect filter condition macro for discovery rule "%1$s".')
				]),
				'value' => new CStringValidator([
					'empty' => true
				]),
				'formulaid' => new CStringValidator([
					'regex' => '/[A-Z]+/',
					'messageEmpty' => _('Empty filter condition formula ID for discovery rule "%1$s".'),
					'messageRegex' => _('Incorrect filter condition formula ID for discovery rule "%1$s".')
				]),
				'operator' => new CLimitedSetValidator([
					'values' => [CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP, CONDITION_OPERATOR_EXISTS,
						CONDITION_OPERATOR_NOT_EXISTS
					],
					'messageInvalid' => _('Incorrect filter condition operator for discovery rule "%1$s".')
				])
			],
			'required' => ['macro', 'value'],
			'messageRequired' => _('No "%2$s" given for a filter condition of discovery rule "%1$s".'),
			'messageUnsupported' => _('Unsupported parameter "%2$s" for a filter condition of discovery rule "%1$s".')
		];
	}

	/**
	 * Check discovery rule specific fields.
	 *
	 * @param array  $item    An array of single item data.
	 * @param string $method  A string of "create" or "update" method.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkSpecificFields(array $item, $method) {
		if (array_key_exists('lifetime', $item)
				&& !validateTimeUnit($item['lifetime'], SEC_PER_HOUR, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'lifetime', $error)
			);
		}
	}

	/**
	 * Checks if LLD macros contain duplicate names in "lld_macro".
	 *
	 * @param array  $lld_macro_paths                 Array of items to validate.
	 * @param string $lld_macro_paths[]['lld_macro']  LLD macro string (optional for update method).
	 * @param array  $macro_names                     Array where existing macro names are collected.
	 * @param string $path                            Path to API object.
	 *
	 * @throws APIException if same discovery rules contains duplicate LLD macro names.
	 */
	protected function checkDuplicateLLDMacros(array $lld_macro_paths, $macro_names, $path) {
		foreach ($lld_macro_paths as $num => $lld_macro_path) {
			if (array_key_exists('lld_macro', $lld_macro_path)) {
				if (array_key_exists($lld_macro_path['lld_macro'], $macro_names)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', $path.'/lld_macro_paths/'.($num + 1).'/lld_macro',
							_s('value "%1$s" already exists', $lld_macro_path['lld_macro'])
						)
					);
				}

				$macro_names[$lld_macro_path['lld_macro']] = true;
			}
		}
	}

	/**
	 * Validates parameters in "lld_macro_paths" property for each item in create method.
	 *
	 * @param array  $items                                      Array of items to validate.
	 * @param array  $items[]['lld_macro_paths']                 Array of LLD macro paths to validate for each
	 *                                                           discovery rule (optional).
	 * @param string $items[]['lld_macro_paths'][]['lld_macro']  LLD macro string. Required if "lld_macro_paths" exists.
	 * @param string $items[]['lld_macro_paths'][]['path']       Path string. Validates as regular string. Required if
	 *                                                           "lld_macro_paths" exists.
	 *
	 * @throws APIException if incorrect fields and values given.
	 */
	protected function validateCreateLLDMacroPaths(array $items) {
		$rules = [
			'lld_macro_paths' =>	['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => [
				'lld_macro' =>			['type' => API_LLD_MACRO, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'lld_macro')],
				'path' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'path')]
			]]
		];

		foreach ($items as $key => $item) {
			if (array_key_exists('lld_macro_paths', $item)) {
				$item = array_intersect_key($item, $rules);
				$path = '/'.($key + 1);

				if (!CApiInputValidator::validate(['type' => API_OBJECT, 'fields' => $rules], $item, $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$this->checkDuplicateLLDMacros($item['lld_macro_paths'], [], $path);
			}
		}
	}

	/**
	 * Validates parameters in "lld_macro_paths" property for each item in create method.
	 *
	 * @param array  $items                                             Array of items to validate.
	 * @param array  $items[]['lld_macro_paths']                        Array of LLD macro paths to validate for each
	 *                                                                  discovery rule (optional).
	 * @param string $items[]['lld_macro_paths'][]['lld_macro_pathid']  LLD macro path ID from DB (optional).
	 * @param string $items[]['lld_macro_paths'][]['lld_macro']         LLD macro string. Required if "lld_macro_pathid"
	 *                                                                  does not exist.
	 * @param string $items[]['lld_macro_paths'][]['path']              Path string. Validates as regular string.
	 *                                                                  Required if "lld_macro_pathid" and "lld_macro"
	 *                                                                  do not exist.
	 *
	 * @throws APIException if incorrect fields and values given.
	 */
	protected function validateUpdateLLDMacroPaths(array $items) {
		$rules = [
			'lld_macro_paths' =>	['type' => API_OBJECTS, 'fields' => [
				'lld_macro_pathid' =>	['type' => API_ID],
				'lld_macro' =>			['type' => API_LLD_MACRO, 'length' => DB::getFieldLength('lld_macro_path', 'lld_macro')],
				'path' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('lld_macro_path', 'path')]
			]]
		];

		$items = $this->extendObjects('items', $items, ['templateid']);

		foreach ($items as $key => $item) {
			if (array_key_exists('lld_macro_paths', $item)) {
				$itemid = $item['itemid'];
				$templateid = $item['templateid'];

				$item = array_intersect_key($item, $rules);
				$path = '/'.($key + 1);

				if (!CApiInputValidator::validate(['type' => API_OBJECT, 'fields' => $rules], $item, $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				if (array_key_exists('lld_macro_paths', $item)) {
					if ($templateid != 0) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Invalid parameter "%1$s": %2$s.', $path.'/lld_macro_paths',
								_('cannot update property for templated discovery rule')
							)
						);
					}

					$lld_macro_pathids = [];

					// Check that fields exists, are not empty, do not duplicate and collect IDs to compare with DB.
					foreach ($item['lld_macro_paths'] as $num => $lld_macro_path) {
						$subpath = $num + 1;

						// API_NOT_EMPTY will not work, so we need at least one field to be present.
						if (!array_key_exists('lld_macro', $lld_macro_path)
								&& !array_key_exists('path', $lld_macro_path)
								&& !array_key_exists('lld_macro_pathid', $lld_macro_path)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Invalid parameter "%1$s": %2$s.', $path.'/lld_macro_paths/'.$subpath,
									_('cannot be empty')
								)
							);
						}

						// API 'uniq' => true will not work, because we validate API_ID not API_IDS. So make IDs unique.
						if (array_key_exists('lld_macro_pathid', $lld_macro_path)) {
							$lld_macro_pathids[$lld_macro_path['lld_macro_pathid']] = true;
						}
						else {
							/*
							 * In case "lld_macro_pathid" does not exist, we need to treat it as a new LLD macro with
							 * both fields present.
							 */
							if (array_key_exists('lld_macro', $lld_macro_path)
									&& !array_key_exists('path', $lld_macro_path)) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Invalid parameter "%1$s": %2$s.', $path.'/lld_macro_paths/'.$subpath,
										_s('the parameter "%1$s" is missing', 'path')
									)
								);
							}
							elseif (array_key_exists('path', $lld_macro_path)
									&& !array_key_exists('lld_macro', $lld_macro_path)) {
								self::exception(ZBX_API_ERROR_PARAMETERS,
									_s('Invalid parameter "%1$s": %2$s.', $path.'/lld_macro_paths/'.$subpath,
										_s('the parameter "%1$s" is missing', 'lld_macro')
									)
								);
							}
						}
					}

					$this->checkDuplicateLLDMacros($item['lld_macro_paths'], [], $path);

					/*
					 * Validate "lld_macro_pathid" field. If "lld_macro_pathid" doesn't correspond to given "itemid"
					 * or does not exist, throw an exception.
					 */
					if ($lld_macro_pathids) {
						$lld_macro_pathids = array_keys($lld_macro_pathids);

						$db_lld_macro_paths = DBfetchArrayAssoc(DBselect(
							'SELECT lmp.lld_macro_pathid,lmp.lld_macro'.
							' FROM lld_macro_path lmp'.
							' WHERE lmp.itemid='.zbx_dbstr($itemid).
								' AND '.dbConditionId('lmp.lld_macro_pathid', $lld_macro_pathids)
						), 'lld_macro_pathid');

						if (count($db_lld_macro_paths) != count($lld_macro_pathids)) {
							self::exception(ZBX_API_ERROR_PERMISSIONS,
								_('No permissions to referred object or it does not exist!')
							);
						}

						$macro_names = [];

						foreach ($item['lld_macro_paths'] as $num => $lld_macro_path) {
							if (array_key_exists('lld_macro_pathid', $lld_macro_path)
									&& !array_key_exists('lld_macro', $lld_macro_path)) {
								$db_lld_macro_path = $db_lld_macro_paths[$lld_macro_path['lld_macro_pathid']];
								$macro_names[$db_lld_macro_path['lld_macro']] = true;
							}
						}

						$this->checkDuplicateLLDMacros($item['lld_macro_paths'], $macro_names, $path);
					}
				}
			}
		}
	}

	/**
	 * Copies the given discovery rule to the specified host.
	 *
	 * @throws APIException if the discovery rule interfaces could not be mapped
	 * to the new host interfaces.
	 *
	 * @param string $discoveryid  The ID of the discovery rule to be copied
	 * @param string $hostid       Destination host id
	 *
	 * @return bool
	 */
	protected function copyDiscoveryRule($discoveryid, $hostid) {
		// fetch discovery to clone
		$srcDiscovery = $this->get([
			'itemids' => $discoveryid,
			'output' => ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'lastlogsize', 'logtimefmt', 'valuemapid', 'params',
				'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'mtime', 'flags',
				'interfaceid', 'description', 'inventory_link', 'lifetime', 'jmx_endpoint', 'url', 'query_fields',
				'parameters', 'timeout', 'posts', 'status_codes', 'follow_redirects', 'post_type', 'http_proxy',
				'headers', 'retrieve_mode', 'request_method', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password',
				'verify_peer', 'verify_host', 'allow_traps', 'master_itemid'
			],
			'selectFilter' => ['evaltype', 'formula', 'conditions'],
			'selectLLDMacroPaths' => ['lld_macro', 'path'],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectOverrides' => ['name', 'step', 'stop', 'filter', 'operations'],
			'preservekeys' => true
		]);
		$srcDiscovery = reset($srcDiscovery);

		// fetch source and destination hosts
		$hosts = API::Host()->get([
			'hostids' => [$srcDiscovery['hostid'], $hostid],
			'output' => ['hostid', 'host', 'name', 'status'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'templated_hosts' => true,
			'preservekeys' => true
		]);
		$srcHost = $hosts[$srcDiscovery['hostid']];
		$dstHost = $hosts[$hostid];

		$dstDiscovery = $srcDiscovery;
		$dstDiscovery['hostid'] = $hostid;
		unset($dstDiscovery['itemid']);
		if ($dstDiscovery['filter']) {
			foreach ($dstDiscovery['filter']['conditions'] as &$condition) {
				unset($condition['itemid'], $condition['item_conditionid']);
			}
			unset($condition);
		}

		if (!$dstDiscovery['lld_macro_paths']) {
			unset($dstDiscovery['lld_macro_paths']);
		}

		if ($dstDiscovery['overrides']) {
			foreach ($dstDiscovery['overrides'] as &$override) {
				if (array_key_exists('filter', $override)) {
					if (!$override['filter']['conditions']) {
						unset($override['filter']);
					}
					unset($override['filter']['eval_formula']);
				}
			}
			unset($override);
		}
		else {
			unset($dstDiscovery['overrides']);
		}

		// if this is a plain host, map discovery interfaces
		if ($srcHost['status'] != HOST_STATUS_TEMPLATE) {
			// find a matching interface
			$interface = self::findInterfaceForItem($dstDiscovery['type'], $dstHost['interfaces']);
			if ($interface) {
				$dstDiscovery['interfaceid'] = $interface['interfaceid'];
			}
			// no matching interface found, throw an error
			elseif ($interface !== false) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s(
					'Cannot find host interface on "%1$s" for item key "%2$s".',
					$dstHost['name'],
					$dstDiscovery['key_']
				));
			}
		}

		// Master item should exists for LLD rule with type dependent item.
		if ($srcDiscovery['type'] == ITEM_TYPE_DEPENDENT) {
			$master_items = DBfetchArray(DBselect(
				'SELECT i1.itemid'.
				' FROM items i1,items i2'.
				' WHERE i1.key_=i2.key_'.
					' AND i1.hostid='.zbx_dbstr($dstDiscovery['hostid']).
					' AND i2.itemid='.zbx_dbstr($srcDiscovery['master_itemid'])
			));

			if (!$master_items) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_s('Discovery rule "%1$s" cannot be copied without its master item.', $srcDiscovery['name'])
				);
			}

			$dstDiscovery['master_itemid'] = $master_items[0]['itemid'];
		}

		// save new discovery
		$newDiscovery = $this->create([$dstDiscovery]);
		$dstDiscovery['itemid'] = $newDiscovery['itemids'][0];

		// copy prototypes
		$new_prototypeids = $this->copyItemPrototypes($srcDiscovery, $dstDiscovery, $dstHost);

		// if there were prototypes defined, clone everything else
		if ($new_prototypeids) {
			// fetch new prototypes
			$dstDiscovery['items'] = API::ItemPrototype()->get([
				'output' => ['itemid', 'key_'],
				'itemids' => $new_prototypeids,
				'preservekeys' => true
			]);

			// copy graphs
			$this->copyGraphPrototypes($srcDiscovery, $dstDiscovery);

			// copy triggers
			$this->copyTriggerPrototypes($srcDiscovery, $srcHost, $dstHost);
		}

		// copy host prototypes
		$this->copyHostPrototypes($discoveryid, $dstDiscovery['itemid']);

		return true;
	}

	/**
	 * Copies all of the item prototypes from the source discovery to the target
	 * discovery rule. Return array of created item prototype ids.
	 *
	 * @throws APIException if prototype saving fails
	 *
	 * @param array $srcDiscovery   The source discovery rule to copy from
	 * @param array $dstDiscovery   The target discovery rule to copy to
	 * @param array $dstHost        The target host to copy the deiscovery rule to
	 *
	 * @return array
	 */
	protected function copyItemPrototypes(array $srcDiscovery, array $dstDiscovery, array $dstHost) {
		$item_prototypes = API::ItemPrototype()->get([
			'output' => ['itemid', 'type', 'snmp_oid', 'name', 'key_', 'delay', 'history', 'trends', 'status',
				'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'valuemapid', 'params', 'ipmi_sensor', 'authtype',
				'username', 'password', 'publickey', 'privatekey', 'interfaceid', 'port', 'description', 'jmx_endpoint',
				'master_itemid', 'templateid', 'url', 'query_fields', 'timeout', 'posts', 'status_codes',
				'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method',
				'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host',
				'allow_traps', 'discover', 'parameters'
			],
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'selectValueMap' => ['name'],
			'discoveryids' => $srcDiscovery['itemid'],
			'preservekeys' => true
		]);
		$new_itemids = [];
		$itemkey_to_id = [];
		$create_items = [];
		$src_valuemap_names = [];
		$valuemap_map = [];

		foreach ($item_prototypes as $item_prototype) {
			if ($item_prototype['valuemap']) {
				$src_valuemap_names[] = $item_prototype['valuemap']['name'];
			}
		}

		if ($src_valuemap_names) {
			$valuemap_map = array_column(API::ValueMap()->get([
				'output' => ['valuemapid', 'name'],
				'hostids' => $dstHost['hostid'],
				'filter' => ['name' => $src_valuemap_names]
			]), 'valuemapid', 'name');
		}

		if ($item_prototypes) {
			$create_order = [];
			$src_itemid_to_key = [];
			$unresolved_master_itemids = [];

			// Gather all master item IDs and check if master item IDs already belong to item prototypes.
			foreach ($item_prototypes as $itemid => $item_prototype) {
				if ($item_prototype['type'] == ITEM_TYPE_DEPENDENT
						&& !array_key_exists($item_prototype['master_itemid'], $item_prototypes)) {
					$unresolved_master_itemids[$item_prototype['master_itemid']] = true;
				}
			}

			$items = [];

			// It's possible that master items are non-prototype items.
			if ($unresolved_master_itemids) {
				$items = API::Item()->get([
					'output' => ['itemid'],
					'itemids' => array_keys($unresolved_master_itemids),
					'webitems' => true,
					'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
					'preservekeys' => true
				]);

				foreach ($items as $item) {
					if (array_key_exists($item['itemid'], $unresolved_master_itemids)) {
						unset($unresolved_master_itemids[$item['itemid']]);
					}
				}

				// If still there are IDs left, there's nothing more we can do.
				if ($unresolved_master_itemids) {
					reset($unresolved_master_itemids);
					self::exception(ZBX_API_ERROR_PERMISSIONS, _s('Incorrect value for field "%1$s": %2$s.',
						'master_itemid', _s('Item "%1$s" does not exist or you have no access to this item',
							key($unresolved_master_itemids)
					)));
				}
			}

			foreach ($item_prototypes as $itemid => $item_prototype) {
				$dependency_level = 0;
				$master_item_prototype = $item_prototype;
				$src_itemid_to_key[$itemid] = $item_prototype['key_'];

				while ($master_item_prototype['type'] == ITEM_TYPE_DEPENDENT) {
					if (array_key_exists($master_item_prototype['master_itemid'], $item_prototypes)) {
						$master_item_prototype = $item_prototypes[$master_item_prototype['master_itemid']];
						++$dependency_level;
					}
					else {
						break;
					}
				}

				$create_order[$itemid] = $dependency_level;
			}
			asort($create_order);

			$current_dependency = reset($create_order);

			foreach ($create_order as $key => $dependency_level) {
				if ($current_dependency != $dependency_level && $create_items) {
					$current_dependency = $dependency_level;
					$created_itemids = API::ItemPrototype()->create($create_items);

					if (!$created_itemids) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone item prototypes.'));
					}

					$created_itemids = $created_itemids['itemids'];
					$new_itemids = array_merge($new_itemids, $created_itemids);

					foreach ($create_items as $index => $created_item) {
						$itemkey_to_id[$created_item['key_']] = $created_itemids[$index];
					}

					$create_items = [];
				}

				$item_prototype = $item_prototypes[$key];
				$item_prototype['ruleid'] = $dstDiscovery['itemid'];
				$item_prototype['hostid'] = $dstDiscovery['hostid'];

				if ($item_prototype['valuemapid'] != 0) {
					$item_prototype['valuemapid'] = array_key_exists($item_prototype['valuemap']['name'], $valuemap_map)
						? $valuemap_map[$item_prototype['valuemap']['name']]
						: 0;
				}

				// map prototype interfaces
				if ($dstHost['status'] != HOST_STATUS_TEMPLATE) {
					// find a matching interface
					$interface = self::findInterfaceForItem($item_prototype['type'], $dstHost['interfaces']);
					if ($interface) {
						$item_prototype['interfaceid'] = $interface['interfaceid'];
					}
					// no matching interface found, throw an error
					elseif ($interface !== false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Cannot find host interface on "%1$s" for item key "%2$s".',
							$dstHost['name'],
							$item_prototype['key_']
						));
					}
				}

				if (!$item_prototype['preprocessing']) {
					unset($item_prototype['preprocessing']);
				}

				if ($item_prototype['type'] == ITEM_TYPE_DEPENDENT) {
					$master_itemid = $item_prototype['master_itemid'];

					if (array_key_exists($master_itemid, $src_itemid_to_key)) {
						$src_item_key = $src_itemid_to_key[$master_itemid];
						$item_prototype['master_itemid'] = $itemkey_to_id[$src_item_key];
					}
					else {
						// It's a non-prototype item, so look for it on destination host.
						$dst_item = get_same_item_for_host($items[$master_itemid], $dstHost['hostid']);

						if (!$dst_item) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone item prototypes.'));
						}

						$item_prototype['master_itemid'] = $dst_item['itemid'];
					}
				}
				else {
					unset($item_prototype['master_itemid']);
				}

				unset($item_prototype['templateid']);
				$create_items[] = $item_prototype;
			}

			if ($create_items) {
				$created_itemids = API::ItemPrototype()->create($create_items);

				if (!$created_itemids) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone item prototypes.'));
				}

				$new_itemids = array_merge($new_itemids, $created_itemids['itemids']);
			}
		}

		return $new_itemids;
	}

	/**
	 * Copies all of the graphs from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if graph saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 *
	 * @return array
	 */
	protected function copyGraphPrototypes(array $srcDiscovery, array $dstDiscovery) {
		// fetch source graphs
		$srcGraphs = API::GraphPrototype()->get([
			'output' => ['graphid', 'name', 'width', 'height', 'yaxismin', 'yaxismax', 'show_work_period',
				'show_triggers', 'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_right',
				'ymin_type', 'ymax_type', 'ymin_itemid', 'ymax_itemid', 'discover'
			],
			'selectGraphItems' => ['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
			'selectHosts' => ['hostid'],
			'discoveryids' => $srcDiscovery['itemid'],
			'preservekeys' => true
		]);

		if (!$srcGraphs) {
			return [];
		}

		$srcItemIds = [];
		foreach ($srcGraphs as $key => $graph) {
			// skip graphs with items from multiple hosts
			if (count($graph['hosts']) > 1) {
				unset($srcGraphs[$key]);
				continue;
			}

			// skip graphs with http items
			if (httpItemExists($graph['gitems'])) {
				unset($srcGraphs[$key]);
				continue;
			}

			// save all used item ids to map them to the new items
			foreach ($graph['gitems'] as $item) {
				$srcItemIds[$item['itemid']] = $item['itemid'];
			}
			if ($graph['ymin_itemid']) {
				$srcItemIds[$graph['ymin_itemid']] = $graph['ymin_itemid'];
			}
			if ($graph['ymax_itemid']) {
				$srcItemIds[$graph['ymax_itemid']] = $graph['ymax_itemid'];
			}
		}

		// fetch source items
		$items = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'webitems' => true,
			'itemids' => $srcItemIds,
			'filter' => ['flags' => null],
			'preservekeys' => true
		]);

		$srcItems = [];
		$itemKeys = [];
		foreach ($items as $item) {
			$srcItems[$item['itemid']] = $item;
			$itemKeys[$item['key_']] = $item['key_'];
		}

		// fetch newly cloned items
		$newItems = API::Item()->get([
			'output' => ['itemid', 'key_'],
			'webitems' => true,
			'hostids' => $dstDiscovery['hostid'],
			'filter' => [
				'key_' => $itemKeys,
				'flags' => null
			],
			'preservekeys' => true
		]);

		$items = array_merge($dstDiscovery['items'], $newItems);
		$dstItems = [];
		foreach ($items as $item) {
			$dstItems[$item['key_']] = $item;
		}

		$dstGraphs = $srcGraphs;
		foreach ($dstGraphs as &$graph) {
			unset($graph['graphid']);

			foreach ($graph['gitems'] as &$gitem) {
				// replace the old item with the new one with the same key
				$item = $srcItems[$gitem['itemid']];
				$gitem['itemid'] = $dstItems[$item['key_']]['itemid'];
			}
			unset($gitem);

			// replace the old axis items with the new one with the same key
			if ($graph['ymin_itemid']) {
				$yMinSrcItem = $srcItems[$graph['ymin_itemid']];
				$graph['ymin_itemid'] = $dstItems[$yMinSrcItem['key_']]['itemid'];
			}
			if ($graph['ymax_itemid']) {
				$yMaxSrcItem = $srcItems[$graph['ymax_itemid']];
				$graph['ymax_itemid'] = $dstItems[$yMaxSrcItem['key_']]['itemid'];
			}
		}
		unset($graph);

		// save graphs
		$rs = API::GraphPrototype()->create($dstGraphs);
		if (!$rs) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone graph prototypes.'));
		}

		return $rs;
	}

	/**
	 * Copy all of the host prototypes from the source discovery rule to the target discovery rule.
	 *
	 * @param string $src_discoveryid
	 * @param string $dst_discoveryid
	 *
	 * @throws APIException
	 */
	protected function copyHostPrototypes(string $src_discoveryid, string $dst_discoveryid): void {
		$src_host_prototypes = API::HostPrototype()->get([
			'output' => ['host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode'],
			'selectInterfaces' => ['type', 'useip', 'ip', 'dns', 'port', 'main', 'details'],
			'selectGroupLinks' => ['groupid'],
			'selectGroupPrototypes' => ['name'],
			'selectTemplates' => ['templateid'],
			'selectTags' => ['tag', 'value'],
			'selectMacros' => ['macro', 'type', 'value', 'description'],
			'discoveryids' => $src_discoveryid
		]);

		if (!$src_host_prototypes) {
			return;
		}

		$dst_host_prototypes = [];

		foreach ($src_host_prototypes as $i => $src_host_prototype) {
			unset($src_host_prototypes[$i]);

			$dst_host_prototype = ['ruleid' => $dst_discoveryid] + array_intersect_key($src_host_prototype, array_flip([
				'host', 'name', 'custom_interfaces', 'status', 'discover', 'inventory_mode', 'groupLinks',
				'groupPrototypes', 'templates', 'tags'
			]));

			if ($src_host_prototype['custom_interfaces'] == HOST_PROT_INTERFACES_CUSTOM) {
				foreach ($src_host_prototype['interfaces'] as $src_interface) {
					$dst_interface =
						array_intersect_key($src_interface, array_flip(['type', 'useip', 'ip', 'dns', 'port', 'main']));

					if ($src_interface['type'] == INTERFACE_TYPE_SNMP) {
						switch ($src_interface['details']['version']) {
							case SNMP_V1:
							case SNMP_V2C:
								$dst_interface['details'] = array_intersect_key($src_interface['details'],
									array_flip(['version', 'bulk', 'community'])
								);
								break;

							case SNMP_V3:
								$field_names = array_flip(['version', 'bulk', 'contextname', 'securityname',
									'securitylevel'
								]);

								if ($src_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV) {
									$field_names += array_flip(['authprotocol', 'authpassphrase']);
								}
								elseif ($src_interface['details']['securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
									$field_names +=
										array_flip(['authprotocol', 'authpassphrase', 'privprotocol', 'privpassphrase']);
								}

								$dst_interface['details'] = array_intersect_key($src_interface['details'], $field_names);
								break;
						}
					}

					$dst_host_prototype['interfaces'][] = $dst_interface;
				}
			}

			foreach ($src_host_prototype['macros'] as $src_macro) {
				if ($src_macro['type'] == ZBX_MACRO_TYPE_SECRET) {
					$dst_host_prototype['macros'][] = ['type' => ZBX_MACRO_TYPE_TEXT, 'value' => ''] + $src_macro;
				}
				else {
					$dst_host_prototype['macros'][] = $src_macro;
				}
			}

			$dst_host_prototypes[] = $dst_host_prototype;
		}

		API::HostPrototype()->create($dst_host_prototypes);
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ((!$options['countOutput'] && ($this->outputIsRequested('state', $options['output'])
				|| $this->outputIsRequested('error', $options['output'])))
				|| (is_array($options['search']) && array_key_exists('error', $options['search']))
				|| (is_array($options['filter']) && array_key_exists('state', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'ir', 'table' => 'item_rtdata', 'using' => 'itemid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('state', $options['output'])) {
				$sqlParts = $this->addQuerySelect('ir.state', $sqlParts);
			}
			if ($this->outputIsRequested('error', $options['output'])) {
				/*
				 * SQL func COALESCE use for template items because they don't have record
				 * in item_rtdata table and DBFetch convert null to '0'
				 */
				$sqlParts = $this->addQuerySelect(dbConditionCoalesce('ir.error', '', 'error'), $sqlParts);
			}

			// add filter fields
			if ($this->outputIsRequested('formula', $options['selectFilter'])
					|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
					|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sqlParts = $this->addQuerySelect('i.formula', $sqlParts);
				$sqlParts = $this->addQuerySelect('i.evaltype', $sqlParts);
			}
			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sqlParts = $this->addQuerySelect('i.evaltype', $sqlParts);
			}

			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemIds = array_keys($result);

		// adding items
		if (!is_null($options['selectItems'])) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = [];
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'itemid', 'item_discovery');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$items = API::ItemPrototype()->get([
						'output' => $options['selectItems'],
						'itemids' => $related_ids,
						'nopermissions' => true,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::ItemPrototype()->get([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);

				$items = zbx_toHash($items, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['items'] = array_key_exists($itemid, $items) ? $items[$itemid]['rowscount'] : '0';
				}
			}
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = [];
				$relationMap = new CRelationMap();
				$res = DBselect(
					'SELECT id.parent_itemid,f.triggerid'.
					' FROM item_discovery id,items i,functions f'.
					' WHERE '.dbConditionInt('id.parent_itemid', $itemIds).
						' AND id.itemid=i.itemid'.
						' AND i.itemid=f.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['parent_itemid'], $relation['triggerid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$triggers = API::TriggerPrototype()->get([
						'output' => $options['selectTriggers'],
						'triggerids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::TriggerPrototype()->get([
					'discoveryids' => $itemIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				$triggers = zbx_toHash($triggers, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['triggers'] = array_key_exists($itemid, $triggers)
						? $triggers[$itemid]['rowscount']
						: '0';
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			if ($options['selectGraphs'] != API_OUTPUT_COUNT) {
				$graphs = [];
				$relationMap = new CRelationMap();
				$res = DBselect(
					'SELECT id.parent_itemid,gi.graphid'.
					' FROM item_discovery id,items i,graphs_items gi'.
					' WHERE '.dbConditionInt('id.parent_itemid', $itemIds).
						' AND id.itemid=i.itemid'.
						' AND i.itemid=gi.itemid'
				);
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['parent_itemid'], $relation['graphid']);
				}

				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$graphs = API::GraphPrototype()->get([
						'output' => $options['selectGraphs'],
						'graphids' => $related_ids,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::GraphPrototype()->get([
					'discoveryids' => $itemIds,
					'countOutput' => true,
					'groupCount' => true
				]);

				$graphs = zbx_toHash($graphs, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					$result[$itemid]['graphs'] = array_key_exists($itemid, $graphs)
						? $graphs[$itemid]['rowscount']
						: '0';
				}
			}
		}

		// adding hosts
		if ($options['selectHostPrototypes'] !== null) {
			if ($options['selectHostPrototypes'] != API_OUTPUT_COUNT) {
				$hostPrototypes = [];
				$relationMap = $this->createRelationMap($result, 'parent_itemid', 'hostid', 'host_discovery');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$hostPrototypes = API::HostPrototype()->get([
						'output' => $options['selectHostPrototypes'],
						'hostids' => $related_ids,
						'nopermissions' => true,
						'preservekeys' => true
					]);
				}

				$result = $relationMap->mapMany($result, $hostPrototypes, 'hostPrototypes', $options['limitSelects']);
			}
			else {
				$hostPrototypes = API::HostPrototype()->get([
					'discoveryids' => $itemIds,
					'nopermissions' => true,
					'countOutput' => true,
					'groupCount' => true
				]);
				$hostPrototypes = zbx_toHash($hostPrototypes, 'parent_itemid');

				foreach ($result as $itemid => $item) {
					$result[$itemid]['hostPrototypes'] = array_key_exists($itemid, $hostPrototypes)
						? $hostPrototypes[$itemid]['rowscount']
						: '0';
				}
			}
		}

		if ($options['selectFilter'] !== null) {
			$formulaRequested = $this->outputIsRequested('formula', $options['selectFilter']);
			$evalFormulaRequested = $this->outputIsRequested('eval_formula', $options['selectFilter']);
			$conditionsRequested = $this->outputIsRequested('conditions', $options['selectFilter']);

			$filters = [];
			foreach ($result as $rule) {
				$filters[$rule['itemid']] = [
					'evaltype' => $rule['evaltype'],
					'formula' => isset($rule['formula']) ? $rule['formula'] : ''
				];
			}

			// adding conditions
			if ($formulaRequested || $evalFormulaRequested || $conditionsRequested) {
				$conditions = DB::select('item_condition', [
					'output' => ['item_conditionid', 'macro', 'value', 'itemid', 'operator'],
					'filter' => ['itemid' => $itemIds],
					'preservekeys' => true,
					'sortfield' => ['item_conditionid']
				]);
				$relationMap = $this->createRelationMap($conditions, 'itemid', 'item_conditionid');

				$filters = $relationMap->mapMany($filters, $conditions, 'conditions');

				foreach ($filters as &$filter) {
					// in case of a custom expression - use the given formula
					if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$formula = $filter['formula'];
					}
					// in other cases - generate the formula automatically
					else {
						// sort the conditions by macro before generating the formula
						$conditions = zbx_toHash($filter['conditions'], 'item_conditionid');
						$conditions = order_macros($conditions, 'macro');

						$formulaConditions = [];
						foreach ($conditions as $condition) {
							$formulaConditions[$condition['item_conditionid']] = $condition['macro'];
						}
						$formula = CConditionHelper::getFormula($formulaConditions, $filter['evaltype']);
					}

					// generate formulaids from the effective formula
					$formulaIds = CConditionHelper::getFormulaIds($formula);
					foreach ($filter['conditions'] as &$condition) {
						$condition['formulaid'] = $formulaIds[$condition['item_conditionid']];
					}
					unset($condition);

					// generated a letter based formula only for rules with custom expressions
					if ($formulaRequested && $filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$filter['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
					}

					if ($evalFormulaRequested) {
						$filter['eval_formula'] = CConditionHelper::replaceNumericIds($formula, $formulaIds);
					}
				}
				unset($filter);
			}

			// add filters to the result
			foreach ($result as &$rule) {
				$rule['filter'] = $filters[$rule['itemid']];
			}
			unset($rule);
		}

		// Add LLD macro paths.
		if ($options['selectLLDMacroPaths'] !== null && $options['selectLLDMacroPaths'] != API_OUTPUT_COUNT) {
			$lld_macro_paths = API::getApiService()->select('lld_macro_path', [
				'output' => $this->outputExtend($options['selectLLDMacroPaths'], ['itemid', 'lld_macro_pathid']),
				'filter' => ['itemid' => $itemIds]
			]);

			foreach ($result as &$lld_macro_path) {
				$lld_macro_path['lld_macro_paths'] = [];
			}
			unset($lld_macro_path);

			foreach ($lld_macro_paths as $lld_macro_path) {
				$itemid = $lld_macro_path['itemid'];

				if (!$this->outputIsRequested('lld_macro_pathid', $options['selectLLDMacroPaths'])) {
					unset($lld_macro_path['lld_macro_pathid']);
				}
				unset($lld_macro_path['itemid']);

				$result[$itemid]['lld_macro_paths'][] = $lld_macro_path;
			}
		}

		// add overrides
		if ($options['selectOverrides'] !== null && $options['selectOverrides'] != API_OUTPUT_COUNT) {
			$ovrd_fields = ['itemid', 'lld_overrideid'];
			$filter_requested = $this->outputIsRequested('filter', $options['selectOverrides']);
			$operations_requested = $this->outputIsRequested('operations', $options['selectOverrides']);

			if ($filter_requested) {
				$ovrd_fields = array_merge($ovrd_fields, ['formula', 'evaltype']);
			}

			$overrides = API::getApiService()->select('lld_override', [
				'output' => $this->outputExtend($options['selectOverrides'], $ovrd_fields),
				'filter' => ['itemid' => $itemIds],
				'preservekeys' => true
			]);

			if ($filter_requested && $overrides) {
				$conditions = DB::select('lld_override_condition', [
					'output' => ['lld_override_conditionid', 'macro', 'value', 'lld_overrideid', 'operator'],
					'filter' => ['lld_overrideid' => array_keys($overrides)],
					'sortfield' => ['lld_override_conditionid'],
					'preservekeys' => true
				]);

				$relation_map = $this->createRelationMap($conditions, 'lld_overrideid', 'lld_override_conditionid');

				foreach ($overrides as &$override) {
					$override['filter'] = [
						'evaltype' => $override['evaltype'],
						'formula' => $override['formula']
					];
					unset($override['evaltype'], $override['formula']);
				}
				unset($override);

				$overrides = $relation_map->mapMany($overrides, $conditions, 'conditions');

				foreach ($overrides as &$override) {
					$override['filter'] += ['conditions' => $override['conditions']];
					unset($override['conditions']);

					if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$formula = $override['filter']['formula'];
					}
					else {
						$conditions = zbx_toHash($override['filter']['conditions'], 'lld_override_conditionid');
						$conditions = order_macros($conditions, 'macro');
						$formula_conditions = [];

						foreach ($conditions as $condition) {
							$formula_conditions[$condition['lld_override_conditionid']] = $condition['macro'];
						}

						$formula = CConditionHelper::getFormula($formula_conditions, $override['filter']['evaltype']);
					}

					$formulaids = CConditionHelper::getFormulaIds($formula);

					foreach ($override['filter']['conditions'] as &$condition) {
						$condition['formulaid'] = $formulaids[$condition['lld_override_conditionid']];
						unset($condition['lld_override_conditionid'], $condition['lld_overrideid']);
					}
					unset($condition);

					if ($override['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
						$override['filter']['formula'] = CConditionHelper::replaceNumericIds($formula, $formulaids);
						$override['filter']['eval_formula'] = $override['filter']['formula'];
					}
					else {
						$override['filter']['eval_formula'] = CConditionHelper::replaceNumericIds($formula,
							$formulaids
						);
					}
				}
				unset($override);
			}

			if ($operations_requested && $overrides) {
				$operations = DB::select('lld_override_operation', [
					'output' => ['lld_override_operationid', 'lld_overrideid', 'operationobject', 'operator', 'value'],
					'filter' => ['lld_overrideid' => array_keys($overrides)],
					'sortfield' => ['lld_override_operationid'],
					'preservekeys' => true
				]);

				if ($operations) {
					$opdiscover = DB::select('lld_override_opdiscover', [
						'output' => ['lld_override_operationid', 'discover'],
						'filter' => ['lld_override_operationid' => array_keys($operations)]
					]);

					$item_prototype_objectids = [];
					$trigger_prototype_objectids = [];
					$host_prototype_objectids = [];

					foreach ($operations as $operation) {
						switch ($operation['operationobject']) {
							case OPERATION_OBJECT_ITEM_PROTOTYPE:
								$item_prototype_objectids[$operation['lld_override_operationid']] = true;
								break;

							case OPERATION_OBJECT_TRIGGER_PROTOTYPE:
								$trigger_prototype_objectids[$operation['lld_override_operationid']] = true;
								break;

							case OPERATION_OBJECT_HOST_PROTOTYPE:
								$host_prototype_objectids[$operation['lld_override_operationid']] = true;
								break;
						}
					}

					if ($item_prototype_objectids || $trigger_prototype_objectids || $host_prototype_objectids) {
						$opstatus = DB::select('lld_override_opstatus', [
							'output' => ['lld_override_operationid', 'status'],
							'filter' => ['lld_override_operationid' => array_keys(
								$item_prototype_objectids + $trigger_prototype_objectids + $host_prototype_objectids
							)]
						]);
					}

					if ($item_prototype_objectids) {
						$ophistory = DB::select('lld_override_ophistory', [
							'output' => ['lld_override_operationid', 'history'],
							'filter' => ['lld_override_operationid' => array_keys($item_prototype_objectids)]
						]);
						$optrends = DB::select('lld_override_optrends', [
							'output' => ['lld_override_operationid', 'trends'],
							'filter' => ['lld_override_operationid' => array_keys($item_prototype_objectids)]
						]);
						$opperiod = DB::select('lld_override_opperiod', [
							'output' => ['lld_override_operationid', 'delay'],
							'filter' => ['lld_override_operationid' => array_keys($item_prototype_objectids)]
						]);
					}

					if ($trigger_prototype_objectids) {
						$opseverity = DB::select('lld_override_opseverity', [
							'output' => ['lld_override_operationid', 'severity'],
							'filter' => ['lld_override_operationid' => array_keys($trigger_prototype_objectids)]
						]);
					}

					if ($trigger_prototype_objectids || $host_prototype_objectids || $item_prototype_objectids) {
						$optag = DB::select('lld_override_optag', [
							'output' => ['lld_override_operationid', 'tag', 'value'],
							'filter' => ['lld_override_operationid' => array_keys(
								$trigger_prototype_objectids + $host_prototype_objectids + $item_prototype_objectids
							)]
						]);
					}

					if ($host_prototype_objectids) {
						$optemplate = DB::select('lld_override_optemplate', [
							'output' => ['lld_override_operationid', 'templateid'],
							'filter' => ['lld_override_operationid' => array_keys($host_prototype_objectids)]
						]);
						$opinventory = DB::select('lld_override_opinventory', [
							'output' => ['lld_override_operationid', 'inventory_mode'],
							'filter' => ['lld_override_operationid' => array_keys($host_prototype_objectids)]
						]);
					}

					foreach ($operations as &$operation) {
						$lld_override_operationid = $operation['lld_override_operationid'];

						if ($item_prototype_objectids || $trigger_prototype_objectids || $host_prototype_objectids) {
							foreach ($opstatus as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opstatus']['status'] = $row['status'];
								}
							}
						}

						foreach ($opdiscover as $row) {
							if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
								$operation['opdiscover']['discover'] = $row['discover'];
							}
						}

						if ($item_prototype_objectids) {
							foreach ($ophistory as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['ophistory']['history'] = $row['history'];
								}
							}

							foreach ($optrends as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['optrends']['trends'] = $row['trends'];
								}
							}

							foreach ($opperiod as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opperiod']['delay'] = $row['delay'];
								}
							}
						}

						if ($trigger_prototype_objectids) {
							foreach ($opseverity as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opseverity']['severity'] = $row['severity'];
								}
							}
						}

						if ($trigger_prototype_objectids || $host_prototype_objectids || $item_prototype_objectids) {
							foreach ($optag as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['optag'][] = ['tag' => $row['tag'], 'value' => $row['value']];
								}
							}
						}

						if ($host_prototype_objectids) {
							foreach ($optemplate as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['optemplate'][] = ['templateid' => $row['templateid']];
								}
							}

							foreach ($opinventory as $row) {
								if (bccomp($lld_override_operationid, $row['lld_override_operationid']) == 0) {
									$operation['opinventory']['inventory_mode'] = $row['inventory_mode'];
								}
							}
						}
					}
					unset($operation);
				}

				$relation_map = $this->createRelationMap($operations, 'lld_overrideid', 'lld_override_operationid');

				$overrides = $relation_map->mapMany($overrides, $operations, 'operations');
			}

			foreach ($result as &$row) {
				$row['overrides'] = [];

				foreach ($overrides as $override) {
					if (bccomp($override['itemid'], $row['itemid']) == 0) {
						unset($override['itemid'], $override['lld_overrideid']);

						if ($operations_requested) {
							foreach ($override['operations'] as &$operation) {
								unset($operation['lld_override_operationid'], $operation['lld_overrideid']);
							}
							unset($operation);
						}

						$row['overrides'][] = $override;
					}
				}
			}
			unset($row);
		}

		return $result;
	}
}
