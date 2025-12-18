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
 * Class containing methods for operations with discovery rule prototypes.
 */
class CDiscoveryRulePrototype extends CDiscoveryRuleGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'type', 'status', 'discover'];

	public const OUTPUT_FIELDS = ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'status',
		'trapper_hosts', 'templateid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey',
		'privatekey', 'flags', 'interfaceid', 'description', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
		'enabled_lifetime', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts', 'status_codes',
		'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'output_format',
		'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps', 'parameters',
		'discover', 'uuid'
	];

	/**
	 * Get LLD rule prototype data.
	 */
	public function get($options = []) {
		$result = [];

		$sql_parts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> 'items i',
			'where'		=> ['i.flags IN('.ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE.','.ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE_CREATED.')'],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$options += [
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'itemids'						=> null,
			'discoveryids'					=> null,
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
			'limit'							=> null
		];

		self::validateGet($options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sql_parts['join']['hh'] = ['table' => 'host_hgset', 'using' => 'hostid'];
			$sql_parts['join']['p'] = ['left_table' => 'hh', 'table' => 'permission', 'using' => 'hgsetid'];
			$sql_parts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sql_parts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}
		}

		if ($options['templateids'] !== null) {
			zbx_value2array($options['templateids']);

			if ($options['hostids'] !== null) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		if ($options['hostids'] !== null) {
			zbx_value2array($options['hostids']);

			$sql_parts['where']['hostid'] = dbConditionId('i.hostid', $options['hostids']);

			if ($options['groupCount']) {
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

		if ($options['itemids'] !== null) {
			zbx_value2array($options['itemids']);

			$sql_parts['where']['itemid'] = dbConditionId('i.itemid', $options['itemids']);
		}

		if ($options['discoveryids'] !== null) {
			zbx_value2array($options['discoveryids']);

			$sql_parts['join']['id'] = ['table' => 'item_discovery', 'using' => 'itemid'];
			$sql_parts['where'][] = dbConditionId('id.lldruleid', $options['discoveryids']);

			if ($options['groupCount']) {
				$sql_parts['group']['id'] = 'id.lldruleid';
			}
		}

		if ($options['interfaceids'] !== null) {
			zbx_value2array($options['interfaceids']);

			$sql_parts['where']['interfaceid'] = dbConditionId('i.interfaceid', $options['interfaceids']);

			if ($options['groupCount']) {
				$sql_parts['group']['i'] = 'i.interfaceid';
			}
		}

		if ($options['groupids'] !== null) {
			zbx_value2array($options['groupids']);

			$sql_parts['join']['hg'] = ['table' => 'hosts_groups', 'using' => 'hostid'];
			$sql_parts['where'][] = dbConditionId('hg.groupid', $options['groupids']);

			if ($options['groupCount']) {
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

		if ($options['inherited'] !== null) {
			if ($options['inherited']) {
				$sql_parts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sql_parts['where'][] = 'i.templateid IS NULL';
			}
		}

		if ($options['templated'] !== null) {
			$sql_parts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];

			if ($options['templated']) {
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		if ($options['monitored'] !== null) {
			$sql_parts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];

			if ($options['monitored']) {
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sql_parts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sql_parts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sql_parts);
		}

		// filter
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$sql_parts['where'][] = makeUpdateIntervalFilter('i.delay', $options['filter']['delay']);
				unset($options['filter']['delay']);
			}

			if (array_key_exists('lifetime', $options['filter']) && $options['filter']['lifetime'] !== null) {
				$options['filter']['lifetime'] = getTimeUnitFilters($options['filter']['lifetime']);
			}

			if (array_key_exists('enabled_lifetime', $options['filter'])
					&& $options['filter']['enabled_lifetime'] !== null) {
				$options['filter']['enabled_lifetime'] = getTimeUnitFilters($options['filter']['enabled_lifetime']);
			}

			$this->dbFilter('items i', $options, $sql_parts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sql_parts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];
				$sql_parts['where']['h'] = dbConditionString('h.host', $options['filter']['host']);
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$res = DBselect(self::createSelectQueryFromParts($sql_parts), $sql_parts['limit']);

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
			self::prepareItemsForApi($result, false);

			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['formula', 'evaltype']);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		if (!$options['preservekeys']) {
			$result = array_values($result);
		}

		return $result;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			// Output.
			'selectDiscoveryRule' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', CDiscoveryRule::OUTPUT_FIELDS), 'default' => null],
			'selectDiscoveryRulePrototype' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => null],
			'selectDiscoveryData' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::DISCOVERY_DATA_OUTPUT_FIELDS), 'default' => null],
			'selectDiscoveryRulePrototypes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE | API_ALLOW_COUNT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => null],
			// Sort and limit.
			'limitSelects' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('formula', $options['selectFilter'])
					|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
					|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sql_parts = $this->addQuerySelect('i.formula', $sql_parts);
				$sql_parts = $this->addQuerySelect('i.evaltype', $sql_parts);
			}
			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sql_parts = $this->addQuerySelect('i.evaltype', $sql_parts);
			}

			if ($options['selectHosts'] !== null) {
				$sql_parts = $this->addQuerySelect('i.hostid', $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedDiscoveryRules($options, $result);
		self::addRelatedDiscoveryRulePrototypes($options, $result);
		self::addRelatedChildDiscoveryRulePrototypes($options, $result);
		self::addRelatedDiscoveryData($options, $result);

		return $result;
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public function create(array $items): array {
		self::validateCreate($items);

		self::createForce($items);
		self::inherit($items);

		return ['itemids' => array_column($items, 'itemid')];
	}

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkHostsAndTemplates($items, $db_hosts, $db_templates);
		self::addHostStatus($items, $db_hosts, $db_templates);
		self::addFlags($items, ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'flags' =>					['type' => API_ANY],
			'uuid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_UUID],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'hostid' =>					['type' => API_ANY],
			'ruleid' =>					['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>					['type' => API_ITEM_KEY, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY]), 'default' => DB::getDefault('items', 'lifetime_type')],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime'), 'default' => DB::getDefault('items', 'lifetime')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '0', 'default' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'default' => DB::getDefault('items', 'enabled_lifetime_type'), 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>				['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'preprocessing' =>			self::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO),
			'lld_macro_paths' =>		self::getLldMacroPathsValidationRules(),
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				self::getOverridesValidationRules()
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType(array_keys($api_input_rules['fields']), $items);

		self::addUuid($items);

		self::checkUuidDuplicates($items);
		self::checkDuplicates($items);
		self::checkDiscoveryRules($items);
		self::checkLifetimeFields($items);
		self::checkHostInterfaces($items);
		self::checkDependentItems($items);
		self::checkFilterFormula($items);
		self::checkOverridesFilterFormula($items);
		self::checkOverridesOperationTemplates($items);
	}

	/**
	 * Check that discovery rule IDs of given items are valid.
	 *
	 * @param array $items
	 *
	 * @throws APIException
	 */
	private static function checkDiscoveryRules(array $items): void {
		$ruleids = array_unique(array_column($items, 'ruleid'));

		$db_discovery_rules = DB::select('items', [
			'output' => ['hostid'],
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_RULE, ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE],
				'itemid' => $ruleids
			],
			'preservekeys' => true
		]);

		if (count($db_discovery_rules) != count($ruleids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($items as $item) {
			if (bccomp($db_discovery_rules[$item['ruleid']]['hostid'], $item['hostid']) != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}
	}

	/**
	 * @param array $items
	 */
	private static function createForce(array &$items): void {
		self::addValueType($items);

		self::prepareItemsForDb($items);
		$itemids = DB::insert('items', $items);
		self::prepareItemsForApi($items);

		$ins_items_discovery = [];
		$host_statuses = [];

		foreach ($items as &$item) {
			$item['itemid'] = array_shift($itemids);

			$ins_items_discovery[] = [
				'itemid' => $item['itemid'],
				'lldruleid' => $item['ruleid']
			];

			$host_statuses[] = $item['host_status'];
			unset($item['host_status'], $item['value_type']);
		}
		unset($item);

		DB::insertBatch('item_discovery', $ins_items_discovery);

		self::updateParameters($items);
		self::updatePreprocessing($items);
		self::updateLldMacroPaths($items);
		self::updateItemFilters($items);
		self::updateOverrides($items);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_LLD_RULE_PROTOTYPE, $items);

		foreach ($items as &$item) {
			$item['host_status'] = array_shift($host_statuses);
		}
		unset($item);
	}

	/**
	 * @param array $items
	 *
	 * @return array
	 */
	public function update(array $items): array {
		$this->validateUpdate($items, $db_items);

		$itemids = array_column($items, 'itemid');

		self::updateForce($items, $db_items);
		self::inherit($items, $db_items);

		return ['itemids' => $itemids];
	}

	/**
	 * @param array      $items
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$items, ?array &$db_items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['itemid']], 'fields' => [
			'itemid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'lifetime_type' => ['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'itemids' => array_column($items, 'itemid'),
			'filter' => [
				'flags' => [ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE]
			],
			'editable' => true
		]);

		if ($count != count($items)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_items = DB::select('items', [
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime',
				'enabled_lifetime_type', 'enabled_lifetime', 'description', 'status', 'discover'],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'itemids' => array_column($items, 'itemid'),
			'preservekeys' => true
		]);

		self::addInternalFields($db_items);

		foreach ($items as $i => &$item) {
			$db_item = $db_items[$item['itemid']];
			$item['host_status'] = $db_item['host_status'];

			$item += ['lifetime_type' => $db_item['lifetime_type']];

			$item += $item['lifetime_type'] == ZBX_LLD_DELETE_IMMEDIATELY
				? ['enabled_lifetime_type' => DB::getDefault('items', 'enabled_lifetime_type')]
				: ['enabled_lifetime_type' => $db_item['enabled_lifetime_type']];

			$api_input_rules = $db_item['templateid'] == 0
				? self::getValidationRules()
				: self::getInheritedValidationRules();

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($item);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['type', 'key_']);

		self::validateByType(array_keys($api_input_rules['fields']), $items, $db_items);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid',
			['hostid', 'flags', 'lifetime', 'enabled_lifetime', 'ruleid']
		);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkUuidDuplicates($items, $db_items);
		self::checkDuplicates($items, $db_items);
		self::checkLifetimeFields($items);
		self::checkHostInterfaces($items, $db_items);
		self::checkDependentItems($items, $db_items);
		self::checkFilterFormula($items);
		self::checkOverridesFilterFormula($items);
		self::checkOverridesOperationTemplates($items);
	}

	/**
	 * @return array
	 */
	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'uuid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'itemid' =>					['type' => API_ANY],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>					['type' => API_INT32, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>					['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime_type' =>			['type' => API_ANY],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>				['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'preprocessing' =>			self::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO),
			'lld_macro_paths' =>		self::getLldMacroPathsValidationRules(),
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				self::getOverridesValidationRules()
		]];
	}

	/**
	 * @return array
	 */
	protected static function getInheritedValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'uuid' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'itemid' =>					['type' => API_ANY],
			'name' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'type' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'key_' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lifetime_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY])],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>				['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'preprocessing' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lld_macro_paths' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
		]];
	}

	/**
	 * Add the internally used fields to the given $db_items.
	 *
	 * @param array $db_items
	 */
	protected static function addInternalFields(array &$db_items): void {
		$result = DBselect(
			'SELECT i.itemid,i.hostid,i.templateid,i.flags,h.status AS host_status,id.lldruleid AS ruleid'.
			' FROM items i,hosts h,item_discovery id'.
			' WHERE i.hostid=h.hostid'.
				' AND i.itemid=id.itemid'.
				' AND '.dbConditionId('i.itemid', array_keys($db_items))
		);

		while ($row = DBfetch($result)) {
			$db_items[$row['itemid']] += $row;
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	private static function updateForce(array &$items, array &$db_items): void {
		// Helps to avoid deadlocks.
		CArrayHelper::sort($items, ['itemid']);

		self::addFieldDefaultsByType($items, $db_items);

		$upd_items = [];
		$upd_itemids = [];

		$internal_fields = array_flip(['itemid', 'type', 'key_', 'hostid', 'flags', 'host_status']);
		$nested_object_fields = array_flip(['preprocessing', 'lld_macro_paths', 'filter', 'overrides', 'parameters']);

		self::prepareItemsForDb($items);

		foreach ($items as $i => &$item) {
			$upd_item = DB::getUpdatedValues('items', $item, $db_items[$item['itemid']]);

			if ($upd_item) {
				$upd_items[] = [
					'values' => $upd_item,
					'where' => ['itemid' => $item['itemid']]
				];

				if (array_key_exists('type', $item) && $item['type'] == ITEM_TYPE_HTTPAGENT) {
					$item = array_intersect_key($item,
						array_flip(['authtype']) + $internal_fields + $upd_item + $nested_object_fields
					);
				}
				else {
					$item = array_intersect_key($item, $internal_fields + $upd_item + $nested_object_fields);
				}

				$upd_itemids[$i] = $item['itemid'];
			}
			else {
				$item = array_intersect_key($item, $internal_fields + $nested_object_fields);
			}
		}
		unset($item);

		if ($upd_items) {
			DB::update('items', $upd_items);
		}

		self::updateParameters($items, $db_items, $upd_itemids);
		self::updatePreprocessing($items, $db_items, $upd_itemids);
		self::updateLldMacroPaths($items, $db_items, $upd_itemids);
		self::updateItemFilters($items, $db_items, $upd_itemids);
		self::updateOverrides($items, $db_items, $upd_itemids);

		$items = array_intersect_key($items, $upd_itemids);
		$db_items = array_intersect_key($db_items, array_flip($upd_itemids));

		self::prepareItemsForApi($items);
		self::prepareItemsForApi($db_items);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_LLD_RULE_PROTOTYPE, $items, $db_items);
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 */
	public static function linkTemplateObjects(array $ruleids, array $hostids): void {
		$fields = array_merge(
			['itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
				'enabled_lifetime', 'description', 'status', 'discover'
			],
			array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters']),
			['hostid', 'templateid', 'flags']
		);

		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT i.'.implode(',i.', $fields).',h.status AS host_status,id.lldruleid AS ruleid'.
			' FROM items i,hosts h,item_discovery id'.
			' WHERE i.hostid=h.hostid'.
				' AND i.itemid=id.itemid'.
				' AND '.dbConditionId('id.lldruleid', $ruleids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE])
		), 'itemid');

		if (!$db_items) {
			return;
		}

		self::prepareItemsForApi($db_items);

		$items = [];

		foreach ($db_items as $db_item) {
			$item = array_intersect_key($db_item, array_flip(['itemid', 'type']));

			if (in_array($db_item['type'], [ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER])) {
				$item += ['parameters' => []];
			}

			$items[] = $item + [
				'preprocessing' => [],
				'lld_macro_paths' => [],
				'filter' => [],
				'overrides' => []
			];
		}

		self::addAffectedObjects($items, $db_items);

		$items = array_values($db_items);

		foreach ($items as &$item) {
			if (array_key_exists('parameters', $item)) {
				$item['parameters'] = array_values($item['parameters']);
			}

			$item['preprocessing'] = array_values($item['preprocessing']);
			$item['lld_macro_paths'] = array_values($item['lld_macro_paths']);
			$item['filter']['conditions'] = array_values($item['filter']['conditions']);

			foreach ($item['filter']['conditions'] as &$condition) {
				if ($item['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
					unset($condition['formulaid']);
				}
			}
			unset($condition);

			foreach ($item['overrides'] as &$override) {
				foreach ($override['filter']['conditions'] as &$condition) {
					if ($override['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
						unset($condition['formulaid']);
					}
				}
				unset($condition);

				$override['filter']['conditions'] = array_values($override['filter']['conditions']);

				foreach ($override['operations'] as &$operation) {
					$operation['optag'] = array_values($operation['optag']);
					$operation['optemplate'] = array_values($operation['optemplate']);
				}
				unset($operation);

				$override['operations'] = array_values($override['operations']);
			}
			unset($override);

			$item['overrides'] = array_values($item['overrides']);
		}
		unset($item);

		self::inherit($items, [], $hostids);

		$ruleids = array_keys($db_items);

		API::ItemPrototype()->linkTemplateObjects($ruleids, $hostids);
		API::TriggerPrototype()->linkTemplateObjects($ruleids, $hostids);
		API::GraphPrototype()->linkTemplateObjects($ruleids, $hostids);
		API::HostPrototype()->linkTemplateObjects($ruleids, $hostids);
		self::linkTemplateObjects($ruleids, $hostids);
	}

	/**
	 * @inheritDoc
	 */
	protected static function inherit(array $items, array $db_items = [], ?array $hostids = null,
			bool $is_dep_items = false): void {
		$tpl_links = self::getTemplateLinks($items, $hostids);

		if ($hostids === null) {
			self::filterObjectsToInherit($items, $db_items, $tpl_links);

			if (!$items) {
				return;
			}
		}

		self::checkDoubleInheritedNames($items, $db_items, $tpl_links);

		$chunks = self::getInheritChunks($items, $tpl_links);

		foreach ($chunks as $chunk) {
			$_items = array_intersect_key($items, array_flip($chunk['item_indexes']));
			$_db_items = array_intersect_key($db_items, array_flip(array_column($_items, 'itemid')));
			$_hostids = array_keys($chunk['hosts']);

			self::inheritChunk($_items, $_db_items, $tpl_links, $_hostids);
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 * @param array $tpl_links
	 * @param array $hostids
	 */
	private static function inheritChunk(array $items, array $db_items, array $tpl_links, array $hostids): void {
		$items_to_link = [];
		$items_to_update = [];

		foreach ($items as $i => $item) {
			if (!array_key_exists($item['itemid'], $db_items)) {
				$items_to_link[] = $item;
			}
			else {
				$items_to_update[] = $item;
			}

			unset($items[$i]);
		}

		$ins_items = [];
		$upd_items = [];
		$upd_db_items = [];

		if ($items_to_link) {
			$lld_links = self::getLldLinks($items_to_link);

			$upd_db_items = self::getChildObjectsUsingName($items_to_link, $hostids, $lld_links);

			if ($upd_db_items) {
				$upd_items = self::getUpdChildObjectsUsingName($items_to_link, $upd_db_items);
			}

			$ins_items = self::getInsChildObjects($items_to_link, $upd_db_items, $tpl_links, $hostids, $lld_links);
		}

		if ($items_to_update) {
			$_upd_db_items = self::getChildObjectsUsingTemplateid($items_to_update, $db_items, $hostids);
			$_upd_items = self::getUpdChildObjectsUsingTemplateid($items_to_update, $db_items, $_upd_db_items);

			self::checkDuplicates($_upd_items, $_upd_db_items);

			$upd_items = array_merge($upd_items, $_upd_items);
			$upd_db_items += $_upd_db_items;
		}

		self::setChildMasterItemIds($upd_items, $ins_items, $hostids);

		$edit_items = array_merge($upd_items, $ins_items);

		self::checkDependentItems($edit_items, $upd_db_items, true);

		self::addInterfaceIds($upd_items, $upd_db_items, $ins_items);

		if ($upd_items) {
			self::updateForce($upd_items, $upd_db_items);
		}

		if ($ins_items) {
			self::createForce($ins_items);
		}

		self::inherit(array_merge($upd_items, $ins_items), $upd_db_items);
	}

	/**
	 * @param array $items
	 * @param array $hostids
	 * @param array $lld_links
	 *
	 * @return array
	 */
	private static function getChildObjectsUsingName(array $items, array $hostids, array $lld_links): array {
		$result = DBselect(
			'SELECT i.itemid,ht.hostid,i.key_,i.templateid,i.flags,h.status AS host_status,'.
				'ht.templateid AS parent_hostid,'.dbConditionCoalesce('id.lldruleid', 0, 'ruleid').
			' FROM hosts_templates ht'.
			' JOIN items i ON ht.hostid=i.hostid'.
			' JOIN hosts h ON ht.hostid=h.hostid'.
			' LEFT JOIN item_discovery id ON i.itemid=id.itemid'.
			' WHERE '.dbConditionId('ht.templateid', array_unique(array_column($items, 'hostid'))).
				' AND '.dbConditionString('i.key_', array_unique(array_column($items, 'key_'))).
				' AND '.dbConditionId('ht.hostid', $hostids)
		);

		$upd_db_items = [];
		$parent_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($items as $i => $item) {
				if (bccomp($row['parent_hostid'], $item['hostid']) == 0 && $row['key_'] === $item['key_']) {
					if ($row['flags'] == $item['flags'] && $row['templateid'] == 0
							&& bccomp($row['ruleid'], $lld_links[$item['ruleid']][$row['hostid']]) == 0) {
						$upd_db_items[$row['itemid']] = $row;
						$parent_indexes[$row['itemid']] = $i;
					}
					else {
						self::showObjectMismatchError($item, $row);
					}
				}
			}
		}

		if (!$upd_db_items) {
			return [];
		}

		$options = [
			'output' => array_merge(
				['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime', 'description', 'status', 'discover'
				],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'itemids' => array_keys($upd_db_items)
		];
		$result = DBselect(DB::makeSql('items', $options));

		while ($row = DBfetch($result)) {
			$upd_db_items[$row['itemid']] = $row + $upd_db_items[$row['itemid']];
		}

		$upd_items = [];

		foreach ($upd_db_items as $upd_db_item) {
			$item = $items[$parent_indexes[$upd_db_item['itemid']]];

			$upd_items[] = [
				'itemid' => $upd_db_item['itemid'],
				'type' => $item['type'],
				'preprocessing' => [],
				'lld_macro_paths' => [],
				'filter' => [],
				'overrides' => [],
				'parameters' => []
			];
		}

		self::addAffectedObjects($upd_items, $upd_db_items);

		return $upd_db_items;
	}

	/**
	 * @param array $items
	 * @param array $upd_db_items
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingName(array $items, array $upd_db_items): array {
		$parent_indexes = [];

		foreach ($items as $i => &$item) {
			$item['uuid'] = '';
			$item = self::unsetNestedObjectIds($item);

			$parent_indexes[$item['hostid']][$item['key_']] = $i;
		}
		unset($item);

		$upd_items = [];

		foreach ($upd_db_items as $upd_db_item) {
			$item = $items[$parent_indexes[$upd_db_item['parent_hostid']][$upd_db_item['key_']]];

			$upd_item = [
				'itemid' => $upd_db_item['itemid'],
				'hostid' => $upd_db_item['hostid'],
				'templateid' => $item['itemid'],
				'host_status' => $upd_db_item['host_status'],
				'ruleid' => $upd_db_item['ruleid']
			] + $item;

			$upd_item += [
				'preprocessing' => [],
				'lld_macro_paths' => [],
				'filter' => [
					'evaltype' => DB::getDefault('items', 'evaltype'),
					'formula' => DB::getDefault('items', 'formula'),
					'conditions' => []
				],
				'overrides' => [],
				'parameters' => []
			];

			$upd_items[] = $upd_item;
		}

		return $upd_items;
	}

	/**
	 * @param array $items
	 * @param array $upd_db_items
	 * @param array $tpl_links
	 * @param array $hostids
	 * @param array $lld_links
	 *
	 * @return array
	 */
	private static function getInsChildObjects(array $items, array $upd_db_items, array $tpl_links, array $hostids,
			array $lld_links): array {
		$ins_items = [];

		$upd_item_keys = [];

		foreach ($upd_db_items as $upd_db_item) {
			$upd_item_keys[$upd_db_item['hostid']][] = $upd_db_item['key_'];
		}

		foreach ($items as $item) {
			$item['uuid'] = '';
			$item = self::unsetNestedObjectIds($item);

			foreach ($tpl_links[$item['hostid']] as $host) {
				if (!in_array($host['hostid'], $hostids)
						|| (array_key_exists($host['hostid'], $upd_item_keys)
							&& in_array($item['key_'], $upd_item_keys[$host['hostid']]))) {
					continue;
				}

				$ins_items[] = [
					'hostid' => $host['hostid'],
					'templateid' => $item['itemid'],
					'host_status' => $host['status'],
					'ruleid' => $lld_links[$item['ruleid']][$host['hostid']]
				] + array_diff_key($item, array_flip(['itemid']));
			}
		}

		return $ins_items;
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 * @param array $hostids
	 *
	 * @return array
	 */
	private static function getChildObjectsUsingTemplateid(array $items, array $db_items, array $hostids): array {
		$upd_db_items = DB::select('items', [
			'output' => array_merge(
				['itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime', 'description', 'status', 'discover'
				],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'filter' => [
				'templateid' => array_keys($db_items),
				'hostid' => $hostids
			],
			'preservekeys' => true
		]);

		self::addInternalFields($upd_db_items);

		if ($upd_db_items) {
			$parent_indexes = array_flip(array_column($items, 'itemid'));
			$upd_items = [];

			foreach ($upd_db_items as $upd_db_item) {
				$item = $items[$parent_indexes[$upd_db_item['templateid']]];
				$db_item = $db_items[$upd_db_item['templateid']];

				$upd_item = [
					'itemid' => $upd_db_item['itemid'],
					'type' => $item['type']
				];

				$upd_item += array_intersect_key([
					'preprocessing' => [],
					'lld_macro_paths' => [],
					'filter' => [],
					'overrides' => [],
					'parameters' => []
				], $db_item);

				$upd_items[] = $upd_item;
			}

			self::addAffectedObjects($upd_items, $upd_db_items);
		}

		return $upd_db_items;
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 * @param array $upd_db_items
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingTemplateid(array $items, array $db_items,
			array $upd_db_items): array {
		$parent_indexes = array_flip(array_column($items, 'itemid'));

		foreach ($items as &$item) {
			unset($item['uuid']);
			$item = self::unsetNestedObjectIds($item);
		}
		unset($item);

		$upd_items = [];

		foreach ($upd_db_items as $upd_db_item) {
			$item = $items[$parent_indexes[$upd_db_item['templateid']]];

			$upd_items[] = array_intersect_key($upd_db_item,
				array_flip(['itemid', 'hostid', 'templateid', 'host_status', 'ruleid'])
			) + $item;
		}

		return $upd_items;
	}

	/**
	 * @param array $itemids
	 *
	 * @return array
	 */
	public function delete(array $itemids): array {
		$this->validateDelete($itemids, $db_items);

		self::deleteForce($db_items);

		return ['ruleids' => $itemids];
	}

	private function validateDelete(array $itemids, ?array &$db_items = null): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $itemids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_items = $this->get([
			'output' => ['itemid', 'name', 'templateid', 'flags'],
			'itemids' => $itemids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_items) != count($itemids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($itemids as $i => $itemid) {
			if ($db_items[$itemid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1),
					_('cannot delete inherited LLD rule prototype')
				));
			}
		}
	}

	/**
	 * @param array $db_items
	 */
	public static function deleteForce(array $db_items): void {
		self::addInheritedItems($db_items);

		$del_itemids = array_keys($db_items);

		// Lock discovery prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM items i'.
			' WHERE '.dbConditionId('i.itemid', $del_itemids).
			' FOR UPDATE'
		);

		self::deleteAffectedItemPrototypes($del_itemids);
		self::deleteAffectedHostPrototypes($del_itemids);
		self::deleteAffectedLldRulePrototypes($db_items);
		self::deleteDiscoveredLldRulePrototypes($db_items);
		self::deleteDiscoveredLldRules($del_itemids);

		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_LLD_RULE_PROTOTYPE, $db_items);
	}

	/**
	 * Delete LLD rule prototypes which belong to the given LLD rule prototypes.
	 *
	 * @param array $db_items
	 */
	private static function deleteAffectedLldRulePrototypes(array $db_items): void {
		$lldruleids = [];
		$discovered_lldruleids = [];

		foreach ($db_items as $db_item) {
			if ($db_item['flags'] == ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE) {
				$lldruleids[] = $db_item['itemid'];
			}
			else {
				$discovered_lldruleids[] = $db_item['itemid'];
			}
		}

		if ($lldruleids) {
			$db_lld_rule_prototypes = DBfetchArrayAssoc(DBselect(
				'SELECT id.itemid,i.name,i.flags'.
				' FROM item_discovery id,items i'.
				' WHERE id.itemid=i.itemid'.
					' AND '.dbConditionId('id.lldruleid', $lldruleids).
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE])
			), 'itemid');

			if ($db_lld_rule_prototypes) {
				self::deleteForce($db_lld_rule_prototypes);
			}
		}

		if ($discovered_lldruleids) {
			$db_lld_rule_prototypes = DBfetchArrayAssoc(DBselect(
				'SELECT id.itemid,i.name,i.flags'.
				' FROM item_discovery id,items i'.
				' WHERE id.itemid=i.itemid'.
					' AND '.dbConditionId('id.lldruleid', $discovered_lldruleids).
					' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE_CREATED])
			), 'itemid');

			if ($db_lld_rule_prototypes) {
				self::deleteForce($db_lld_rule_prototypes);
			}
		}
	}

	private static function deleteDiscoveredLldRulePrototypes(array $db_items): void {
		$db_lld_rule_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT id.itemid,i.name,i.flags'.
			' FROM item_discovery id,items i'.
			' WHERE id.itemid=i.itemid'.
				' AND '.dbConditionId('id.parent_itemid', array_keys($db_items)).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE_CREATED])
		), 'itemid');

		if ($db_lld_rule_prototypes) {
			self::deleteForce($db_lld_rule_prototypes);
		}
	}

	/**
	 * Delete (nested) discovered rules of the given discovery prototypes.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteDiscoveredLldRules(array $del_itemids): void {
		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT id.itemid,i.name,i.flags'.
			' FROM item_discovery id,items i'.
			' WHERE id.itemid=i.itemid'.
				' AND '.dbConditionId('id.parent_itemid', $del_itemids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE_CREATED])
		), 'itemid');

		if ($db_items) {
			CDiscoveryRule::deleteForce($db_items);
		}
	}

	/**
	 * @param array $ruleids
	 */
	public static function unlinkTemplateObjects(array $ruleids): void {
		$result = DBselect(
			'SELECT id.itemid,i.name,i.templateid,i.uuid,h.status AS host_status'.
			' FROM item_discovery id,items i,hosts h'.
			' WHERE id.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionId('id.lldruleid', $ruleids).
				' AND '.dbConditionId('i.templateid', [0], true).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE_PROTOTYPE])
		);

		$items = [];
		$db_items = [];

		while ($row = DBfetch($result)) {
			$item = [
				'itemid' => $row['itemid'],
				'templateid' => 0
			];

			if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
				$item += ['uuid' => generateUuidV4()];
			}

			$items[] = $item;
			$db_items[$row['itemid']] = $row;
		}

		$ruleids = array_keys($db_items);

		if ($items) {
			self::updateForce($items, $db_items);

			CItemPrototype::unlinkTemplateObjects($ruleids);
			API::HostPrototype()->unlinkTemplateObjects($ruleids);
			self::unlinkTemplateObjects($ruleids);
		}
	}
}
