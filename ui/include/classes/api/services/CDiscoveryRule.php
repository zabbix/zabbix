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
 * Class containing methods for operations with discovery rules.
 */
class CDiscoveryRule extends CDiscoveryRuleGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'type', 'status'];

	public const OUTPUT_FIELDS = ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'status',
		'trapper_hosts', 'templateid', 'params', 'ipmi_sensor', 'authtype', 'username', 'password', 'publickey',
		'privatekey', 'flags', 'interfaceid', 'description', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
		'enabled_lifetime', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts', 'status_codes',
		'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method', 'output_format',
		'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host', 'allow_traps', 'state',
		'error', 'parameters', 'uuid'
	];

	public static function getOutputFieldsOnHost(): array {
		return array_diff(self::OUTPUT_FIELDS, ['uuid']);
	}

	public static function getOutputFieldsOnTemplate(): array {
		return array_diff(self::OUTPUT_FIELDS, ['flags', 'interfaceid', 'state', 'error']);
	}

	/**
	 * Get DiscoveryRule data
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> 'items i',
			'where'		=> ['i.flags IN ('.ZBX_FLAG_DISCOVERY_RULE.','.ZBX_FLAG_DISCOVERY_RULE_CREATED.')'],
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
			'limit'							=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		self::validateGet($options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['join']['hh'] = ['table' => 'host_hgset', 'using' => 'hostid'];
			$sqlParts['join']['p'] = ['left_table' => 'hh', 'table' => 'permission', 'using' => 'hgsetid'];
			$sqlParts['where'][] = 'p.ugsetid='.self::$userData['ugsetid'];

			if ($options['editable']) {
				$sqlParts['where'][] = 'p.permission='.PERM_READ_WRITE;
			}
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

			$sqlParts['join']['hg'] = ['table' => 'hosts_groups', 'using' => 'hostid'];
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);

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
			$sqlParts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];

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

			if (array_key_exists('enabled_lifetime', $options['filter'])
					&& $options['filter']['enabled_lifetime'] !== null) {
				$options['filter']['enabled_lifetime'] = getTimeUnitFilters($options['filter']['enabled_lifetime']);
			}

			if (array_key_exists('state', $options['filter']) && $options['filter']['state'] !== null) {
				$this->dbFilter('item_rtdata ir', ['filter' => ['state' => $options['filter']['state']]] + $options,
					$sqlParts
				);
			}

			$this->dbFilter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['join']['h'] = ['table' => 'hosts', 'using' => 'hostid'];
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
			'selectDiscoveryRule' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => null],
			'selectDiscoveryData' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::DISCOVERY_DATA_OUTPUT_FIELDS), 'default' => null],
			'selectDiscoveryRulePrototypes' =>	['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE | API_ALLOW_COUNT, 'in' => implode(',', CDiscoveryRulePrototype::OUTPUT_FIELDS), 'default' => null],
			// Sort and limit.
			'limitSelects' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ((!$options['countOutput'] && ($this->outputIsRequested('state', $options['output'])
				|| $this->outputIsRequested('error', $options['output'])))
				|| (is_array($options['search']) && array_key_exists('error', $options['search']))
				|| (is_array($options['filter']) && array_key_exists('state', $options['filter']))) {
			$sqlParts['join']['ir'] = ['type' => 'left', 'table' => 'item_rtdata', 'using' => 'itemid'];
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

		self::addRelatedDiscoveryRules($options, $result);
		self::addRelatedDiscoveryData($options, $result);
		self::addRelatedChildDiscoveryRulePrototypes($options, $result);

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
		self::addFlags($items, ZBX_FLAG_DISCOVERY_RULE);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'flags' =>					['type' => API_ANY],
			'uuid' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_UUID],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'hostid' =>					['type' => API_ANY],
			'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>					['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>					['type' => API_ITEM_KEY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime_type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY]), 'default' => DB::getDefault('items', 'lifetime_type')],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime'), 'default' => DB::getDefault('items', 'lifetime')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '0', 'default' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'default' => DB::getDefault('items', 'enabled_lifetime_type'), 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			self::getPreprocessingValidationRules(),
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
		self::checkLifetimeFields($items);
		self::checkHostInterfaces($items);
		self::checkDependentItems($items);
		self::checkNestedItems($items);
		self::checkFilterFormula($items);
		self::checkOverridesFilterFormula($items);
		self::checkOverridesOperationTemplates($items);
	}

	/**
	 * @param array $items
	 */
	private static function createForce(array &$items): void {
		self::addValueType($items);

		self::prepareItemsForDb($items);
		$itemids = DB::insert('items', $items);
		self::prepareItemsForApi($items);

		$ins_items_rtdata = [];
		$host_statuses = [];

		foreach ($items as &$item) {
			$item['itemid'] = array_shift($itemids);

			if (in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				$ins_items_rtdata[] = ['itemid' => $item['itemid']];
			}

			$host_statuses[] = $item['host_status'];
			unset($item['host_status'], $item['value_type']);
		}
		unset($item);

		if ($ins_items_rtdata) {
			DB::insertBatch('item_rtdata', $ins_items_rtdata, false);
		}

		self::updateParameters($items);
		self::updatePreprocessing($items);
		self::updateLldMacroPaths($items);
		self::updateItemFilters($items);
		self::updateOverrides($items);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_LLD_RULE, $items);

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
			'itemid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$count = $this->get([
			'countOutput' => true,
			'itemids' => array_column($items, 'itemid'),
			'editable' => true
		]);

		if ($count != count($items)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$db_items = DB::select('items', [
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime',
				'enabled_lifetime_type', 'enabled_lifetime', 'description', 'status'],
				array_diff(CItemType::FIELD_NAMES, ['parameters'])
			),
			'itemids' => array_column($items, 'itemid'),
			'preservekeys' => true
		]);

		self::addInternalFields($db_items);

		foreach ($items as $i => &$item) {
			$db_item = $db_items[$item['itemid']];
			$item['host_status'] = $db_item['host_status'];

			if ($db_item['flags'] == ZBX_FLAG_DISCOVERY_RULE_CREATED) {
				$api_input_rules = self::getDiscoveredValidationRules();
			}
			else {
				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'lifetime_type' =>	['type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER, ZBX_LLD_DELETE_IMMEDIATELY])]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$item += ['lifetime_type' => $db_item['lifetime_type']];

				$item += $item['lifetime_type'] == ZBX_LLD_DELETE_IMMEDIATELY
					? ['enabled_lifetime_type' => DB::getDefault('items', 'enabled_lifetime_type')]
					: ['enabled_lifetime_type' => $db_item['enabled_lifetime_type']];

				$api_input_rules = $db_item['templateid'] == 0
					? self::getValidationRules()
					: self::getInheritedValidationRules();
			}

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($item);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['type', 'key_']);

		self::validateByType(array_keys($api_input_rules['fields']), $items, $db_items);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid',
			['hostid', 'flags', 'lifetime', 'enabled_lifetime']
		);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkUuidDuplicates($items, $db_items);
		self::checkDuplicates($items, $db_items);
		self::checkLifetimeFields($items);
		self::checkHostInterfaces($items, $db_items);
		self::checkDependentItems($items, $db_items);
		self::checkNestedItems($items, $db_items);
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
			'key_' =>					['type' => API_ITEM_KEY, 'length' => DB::getFieldLength('items', 'key_')],
			'lifetime_type' =>			['type' => API_ANY],
			'lifetime' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			self::getPreprocessingValidationRules(),
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
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'lifetime')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '0']
			]],
			'enabled_lifetime_type' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_INT32, 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER, ZBX_LLD_DISABLE_NEVER, ZBX_LLD_DISABLE_IMMEDIATELY])],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'enabled_lifetime_type')]
			]],
			'enabled_lifetime' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'lifetime_type', 'in' => implode(',', [ZBX_LLD_DELETE_AFTER, ZBX_LLD_DELETE_NEVER])], 'type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'enabled_lifetime_type', 'in' => implode(',', [ZBX_LLD_DISABLE_AFTER])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'enabled_lifetime')],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
											]],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'enabled_lifetime')]
			]],
			'description' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'lld_macro_paths' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'filter' =>					self::getFilterValidationRules('items', 'item_condition'),
			'overrides' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
		]];
	}

	private static function getDiscoveredValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>			['type' => API_ANY],
			'uuid' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'itemid' =>					['type' => API_ANY],
			'name' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'type' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'key_' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'lifetime_type' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'lifetime' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'enabled_lifetime_type' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'enabled_lifetime' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'description' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'status' =>					['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'preprocessing' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'lld_macro_paths' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'filter' =>					['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'overrides' =>				['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		]];
	}

	private static function checkNestedItems(array $items, ?array $db_items = null): void {
		$item_indexes = [];

		foreach ($items as $i => $item) {
			if (in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
					&& $item['type'] == ITEM_TYPE_NESTED
					&& ($db_items === null || $item['type'] != $db_items[$item['itemid']]['type'])) {
				$item_indexes[$item['hostid']][] = $i;
			}
		}

		if (!$item_indexes) {
			return;
		}

		$result = DBselect(
			'SELECT h.hostid'.
			' FROM hosts h'.
			' WHERE '.dbConditionId('h.hostid', array_keys($item_indexes)).
				' AND '.dbConditionInt('h.flags', [ZBX_FLAG_DISCOVERY_CREATED], true).
			' LIMIT 1'
		);

		while ($row = DBfetch($result)) {
			$i = reset($item_indexes[$row['hostid']]);

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
				'/'.($i + 1).'/type', _('cannot have a nested LLD rule on a non-discovered host')
			));
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

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_LLD_RULE, $items, $db_items);
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 */
	public static function linkTemplateObjects(array $templateids, array $hostids): void {
		$db_items = DB::select('items', [
			'output' => array_merge(
				['itemid', 'name', 'type', 'key_', 'lifetime_type', 'lifetime', 'enabled_lifetime_type',
					'enabled_lifetime', 'description', 'status'
				],
				array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters'])
			),
			'filter' => [
				'hostid' => $templateids,
				'flags' => [ZBX_FLAG_DISCOVERY_RULE]
			],
			'preservekeys' => true
		]);

		if (!$db_items) {
			return;
		}

		self::prepareItemsForApi($db_items);
		self::addInternalFields($db_items);

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
		CDiscoveryRulePrototype::linkTemplateObjects($ruleids, $hostids);
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
		self::checkInheritedNestedItems($items, $db_items, $tpl_links);

		$chunks = self::getInheritChunks($items, $tpl_links);

		foreach ($chunks as $chunk) {
			$_items = array_intersect_key($items, array_flip($chunk['item_indexes']));
			$_db_items = array_intersect_key($db_items, array_flip(array_column($_items, 'itemid')));
			$_hostids = array_keys($chunk['hosts']);

			self::inheritChunk($_items, $_db_items, $tpl_links, $_hostids);
		}
	}

	private static function checkInheritedNestedItems(array $items, array $db_items, array $tpl_links): void {
		foreach ($items as $item) {
			$check = false;

			if (array_key_exists($item['itemid'], $db_items)) {
				if ($item['type'] != $db_items[$item['itemid']]['type'] && $item['type'] == ITEM_TYPE_NESTED) {
					$check = true;
				}
			}
			elseif ($item['type'] == ITEM_TYPE_NESTED) {
				$check = true;
			}

			if (!$check) {
				continue;
			}

			foreach ($tpl_links[$item['hostid']] as $host) {
				if (!in_array($host['status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
						|| $host['flags'] != ZBX_FLAG_DISCOVERY_NORMAL) {
					continue;
				}

				$root_item = array_key_exists($item['itemid'], $db_items)
					? $item + ['templateid' => $db_items[$item['itemid']]['templateid']]
					: $item;

				while ($root_item['templateid'] != 0) {
					$root_item = DB::select('items', [
						'output' => ['hostid', 'templateid'],
						'itemids' => $root_item['templateid']
					])[0];
				}

				$db_hosts = DB::select('hosts', [
					'output' => ['host'],
					'hostids' => [$root_item['hostid'], $host['hostid']],
					'preservekeys' => true
				]);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('The nested LLD rule with key "%1$s" of the template "%2$s" cannot be inherited to non-discovered host "%3$s".',
						$item['key_'], $db_hosts[$root_item['hostid']]['host'], $db_hosts[$host['hostid']]['host']
					)
				);
			}
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
			$upd_db_items = self::getChildObjectsUsingName($items_to_link, $hostids);

			if ($upd_db_items) {
				$upd_items = self::getUpdChildObjectsUsingName($items_to_link, $upd_db_items);
			}

			$ins_items = self::getInsChildObjects($items_to_link, $upd_db_items, $tpl_links, $hostids);
		}

		if ($items_to_update) {
			$_upd_db_items = self::getChildObjectsUsingTemplateid($items_to_update, $db_items, $hostids);
			$_upd_items = self::getUpdChildObjectsUsingTemplateid($items_to_update, $_upd_db_items);

			self::checkDuplicates($_upd_items, $_upd_db_items);

			$upd_items = array_merge($upd_items, $_upd_items);
			$upd_db_items += $_upd_db_items;
		}

		self::setChildMasterItemIds($upd_items, $ins_items, $hostids);

		self::checkDependentItems(array_merge($upd_items, $ins_items), $upd_db_items, true);

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
	 *
	 * @return array
	 */
	private static function getChildObjectsUsingName(array $items, array $hostids): array {
		$result = DBselect(
			'SELECT i.itemid,ht.hostid,i.key_,i.templateid,i.flags,h.status AS host_status,'.
				'ht.templateid AS parent_hostid'.
			' FROM hosts_templates ht,items i,hosts h'.
			' WHERE ht.hostid=i.hostid'.
				' AND ht.hostid=h.hostid'.
				' AND '.dbConditionId('ht.templateid', array_unique(array_column($items, 'hostid'))).
				' AND '.dbConditionString('i.key_', array_unique(array_column($items, 'key_'))).
				' AND '.dbConditionId('ht.hostid', $hostids)
		);

		$upd_db_items = [];
		$parent_indexes = [];

		while ($row = DBfetch($result)) {
			foreach ($items as $i => $item) {
				if (bccomp($row['parent_hostid'], $item['hostid']) == 0 && $row['key_'] === $item['key_']) {
					if ($row['flags'] == $item['flags'] && $row['templateid'] == 0) {
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
					'enabled_lifetime', 'description', 'status'
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
				'host_status' => $upd_db_item['host_status']
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
	 *
	 * @return array
	 */
	private static function getInsChildObjects(array $items, array $upd_db_items, array $tpl_links,
			array $hostids): array {
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
					'host_status' => $host['status']
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
					'enabled_lifetime', 'description', 'status'
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
	 * @param array $upd_db_items
	 *
	 * @return array
	 */
	private static function getUpdChildObjectsUsingTemplateid(array $items, array $upd_db_items): array {
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
				array_flip(['itemid', 'hostid', 'templateid', 'host_status'])
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
					_('cannot delete inherited LLD rule')
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

		self::deleteAffectedItemPrototypes($del_itemids);
		self::deleteAffectedHostPrototypes($del_itemids);
		self::deleteAffectedLldRulePrototypes($db_items);

		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);

		$ins_housekeeper = [];

		foreach ($del_itemids as $itemid) {
			$ins_housekeeper[] = [
				'tablename' => 'events',
				'field' => 'lldruleid',
				'value' => $itemid
			];
		}

		DB::insertBatch('housekeeper', $ins_housekeeper);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_LLD_RULE, $db_items);
	}

	/**
	 * Delete discovery prototypes which belong to the given LLD rules prototypes.
	 *
	 * @param array $db_items
	 */
	private static function deleteAffectedLldRulePrototypes(array $db_items): void {
		$lldruleids = [];
		$discovered_lldruleids = [];

		foreach ($db_items as $db_item) {
			if ($db_item['flags'] == ZBX_FLAG_DISCOVERY_RULE) {
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
				CDiscoveryRulePrototype::deleteForce($db_lld_rule_prototypes);
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
				CDiscoveryRulePrototype::deleteForce($db_lld_rule_prototypes);
			}
		}
	}

	/**
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function unlinkTemplateObjects(array $templateids, ?array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('ii.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT ii.itemid,ii.name,ii.templateid,ii.uuid,h.status AS host_status'.
			' FROM items i,items ii,hosts h'.
			' WHERE i.itemid=ii.templateid'.
				' AND ii.hostid=h.hostid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE]).
				$hostids_condition
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

		if ($items) {
			self::updateForce($items, $db_items);

			$ruleids = array_keys($db_items);

			CItemPrototype::unlinkTemplateObjects($ruleids);
			API::HostPrototype()->unlinkTemplateObjects($ruleids);
			CDiscoveryRulePrototype::unlinkTemplateObjects($ruleids);
		}
	}

	/**
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function clearTemplateObjects(array $templateids, ?array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('ii.hostid', $hostids) : '';

		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT ii.itemid,ii.name,ii.flags'.
			' FROM items i,items ii'.
			' WHERE i.itemid=ii.templateid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_RULE]).
				$hostids_condition
		), 'itemid');

		if ($db_items) {
			self::deleteForce($db_items);
		}
	}
}
