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
 * Class containing methods for operations with item prototypes.
 */
class CItemPrototype extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status', 'discover'];

	/**
	 * @inheritDoc
	 */
	public const SUPPORTED_PREPROCESSING_TYPES = [
		ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_TRIM, ZBX_PREPROC_REGSUB,
		ZBX_PREPROC_BOOL2DEC, ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC, ZBX_PREPROC_DELTA_VALUE,
		ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH, ZBX_PREPROC_VALIDATE_RANGE,
		ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
		ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX, ZBX_PREPROC_THROTTLE_VALUE,
		ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT, ZBX_PREPROC_PROMETHEUS_PATTERN,
		ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE,
		ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, ZBX_PREPROC_XML_TO_JSON, ZBX_PREPROC_SNMP_WALK_VALUE,
		ZBX_PREPROC_SNMP_WALK_TO_JSON, ZBX_PREPROC_SNMP_GET_VALUE
	];

	/**
	 * @inheritDoc
	 */
	protected const SUPPORTED_ITEM_TYPES = [
		ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
		ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
		ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT,
		ITEM_TYPE_BROWSER
	];

	/**
	 * @inheritDoc
	 */
	protected const VALUE_TYPE_FIELD_NAMES = [
		ITEM_VALUE_TYPE_FLOAT => ['units', 'trends', 'valuemapid'],
		ITEM_VALUE_TYPE_STR => ['valuemapid'],
		ITEM_VALUE_TYPE_LOG => ['logtimefmt'],
		ITEM_VALUE_TYPE_UINT64 => ['units', 'trends', 'valuemapid'],
		ITEM_VALUE_TYPE_TEXT => [],
		ITEM_VALUE_TYPE_BINARY => []
	];

	/**
	 * Get ItemPrototype data.
	 */
	public function get($options = []) {
		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['i.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'						=> null,
			'templateids'					=> null,
			'hostids'						=> null,
			'itemids'						=> null,
			'discoveryids'					=> null,
			'graphids'						=> null,
			'triggerids'					=> null,
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
			'selectTriggers'				=> null,
			'selectGraphs'					=> null,
			'selectDiscoveryRule'			=> null,
			'selectPreprocessing'			=> null,
			'selectTags'					=> null,
			'selectValueMap'				=> null,
			'countOutput'					=> false,
			'groupCount'					=> false,
			'preservekeys'					=> false,
			'sortfield'						=> '',
			'sortorder'						=> '',
			'limit'							=> null,
			'limitSelects'					=> null
		];
		$options = zbx_array_merge($defOptions, $options);
		$this->validateGet($options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if (self::$userData['ugsetid'] == 0) {
				return $options['countOutput'] ? '0' : [];
			}

			$sqlParts['from'][] = 'host_hgset hh';
			$sqlParts['from'][] = 'permission p';
			$sqlParts['where'][] = 'i.hostid=hh.hostid';
			$sqlParts['where'][] = 'hh.hgsetid=p.hgsetid';
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
			else{
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

		// discoveryids
		if (!is_null($options['discoveryids'])) {
			zbx_value2array($options['discoveryids']);

			$sqlParts['from']['item_discovery'] = 'item_discovery id';
			$sqlParts['where'][] = dbConditionInt('id.parent_itemid', $options['discoveryids']);
			$sqlParts['where']['idi'] = 'i.itemid=id.itemid';

			if ($options['groupCount']) {
				$sqlParts['group']['id'] = 'id.parent_itemid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['if'] = 'i.itemid=f.itemid';
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited'])
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			else
				$sqlParts['where'][] = 'i.templateid IS NULL';
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated'])
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			else
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else{
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sqlParts);
		}

		// --- FILTER ---
		if (is_array($options['filter'])) {
			if (array_key_exists('delay', $options['filter']) && $options['filter']['delay'] !== null) {
				$sqlParts['where'][] = makeUpdateIntervalFilter('i.delay', $options['filter']['delay']);
				unset($options['filter']['delay']);
			}

			if (array_key_exists('history', $options['filter']) && $options['filter']['history'] !== null) {
				$options['filter']['history'] = getTimeUnitFilters($options['filter']['history']);
			}

			if (array_key_exists('trends', $options['filter']) && $options['filter']['trends'] !== null) {
				$options['filter']['trends'] = getTimeUnitFilters($options['filter']['trends']);
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
		//----------

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$resource = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);

		if ($options['countOutput']) {
			if ($options['groupCount']) {
				$result = [];

				while ($item = DBfetch($resource)) {
					$result[] = $item;
				}

				return $result;
			}

			return DBfetch($resource)['rowscount'];
		}

		$items = [];
		$items_chunk = [];

		do {
			while ($item = DBfetch($resource)) {
				$items_chunk[$item['itemid']] = $item;

				if (count($items_chunk) == CItemGeneral::CHUNK_SIZE) {
					break;
				}
			}

			if (!$items_chunk) {
				break;
			}

			$this->prepareChunkObjects($items_chunk, $options);

			$items += $items_chunk;
			$items_chunk = [];
		} while ($item !== false);

		if (!$options['preservekeys']) {
			$items = array_values($items);
		}

		return $items;
	}

	/**
	 * Validates the input parameters for the get() method.
	 *
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid
	 */
	protected function validateGet(array $options) {
		// Validate input parameters.
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'selectValueMap' => ['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => 'valuemapid,name,mappings']
		]];
		$options_filter = array_intersect_key($options, $api_input_rules['fields']);

		if (!CApiInputValidator::validate($api_input_rules, $options_filter, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private function prepareChunkObjects(array &$items, array $options): void {
		$items = $this->addRelatedObjects($options, $items);
		$items = $this->unsetExtraFields($items, ['hostid', 'valuemapid'], $options['output']);
		$items = $this->unsetExtraFields($items, ['name_upper']);

		self::prepareItemsForApi($items, false);
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
	protected static function validateCreate(array &$items): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkHostsAndTemplates($items, $db_hosts, $db_templates);
		self::addHostStatus($items, $db_hosts, $db_templates);
		self::addFlags($items, ZBX_FLAG_DISCOVERY_PROTOTYPE);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'flags' =>			['type' => API_ANY],
			'uuid' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_UUID],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'hostid' =>			['type' => API_ANY],
			'ruleid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'flags' => API_REQUIRED | API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => ITEM_TYPE_DEPENDENT], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY])],
									['else' => true, 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])]
			]],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'units')]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => '0', 'default' => 0]
			]],
			'valuemapid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])], 'type' => API_ID],
									['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'logtimefmt' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => ITEM_VALUE_TYPE_LOG], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'logtimefmt')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'logtimefmt')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	self::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO)
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType(array_keys($api_input_rules['fields']), $items);

		self::addUuid($items);

		self::checkUuidDuplicates($items);
		self::checkDuplicates($items);
		self::checkPreprocessingStepsDuplicates($items);
		self::checkDiscoveryRules($items);
		self::checkValueMaps($items);
		self::checkHostInterfaces($items);
		self::checkDependentItems($items);
	}

	/**
	 * @param array $items
	 */
	public static function createForce(array &$items): void {
		self::prepareItemsForDb($items);
		$itemids = DB::insert('items', $items);
		self::prepareItemsForApi($items);

		$ins_items_discovery = [];
		$host_statuses = [];
		$flags = [];

		foreach ($items as &$item) {
			$item['itemid'] = array_shift($itemids);

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$ins_items_discovery[] = [
					'itemid' => $item['itemid'],
					'parent_itemid' => $item['ruleid']
				];
			}

			$host_statuses[] = $item['host_status'];
			$flags[] = $item['flags'];
			unset($item['host_status'], $item['flags']);
		}
		unset($item);

		if ($ins_items_discovery) {
			DB::insertBatch('item_discovery', $ins_items_discovery);
		}

		self::updateParameters($items);
		self::updatePreprocessing($items);
		self::updateTags($items);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_ITEM_PROTOTYPE, $items);

		foreach ($items as &$item) {
			$item['host_status'] = array_shift($host_statuses);
			$item['flags'] = array_shift($flags);
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
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
				'trends', 'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
			], array_diff(CItemType::FIELD_NAMES, ['parameters'])),
			'itemids' => array_column($items, 'itemid'),
			'preservekeys' => true
		]);

		self::addInternalFields($db_items);

		foreach ($items as $i => &$item) {
			$db_item = $db_items[$item['itemid']];
			$item['host_status'] = $db_item['host_status'];

			if ($db_item['templateid'] != 0) {
				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'value_type' => ['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$item += array_intersect_key($db_item, array_flip(['value_type']));

				$api_input_rules = self::getInheritedValidationRules();
			}
			else {
				$item += array_intersect_key($db_item, array_flip(['type', 'value_type']));

				$api_input_rules = self::getValidationRules();
			}

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($item);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['type', 'key_']);

		self::validateByType(array_keys($api_input_rules['fields']), $items, $db_items);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['hostid', 'flags', 'ruleid']);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkUuidDuplicates($items, $db_items);
		self::checkDuplicates($items, $db_items);
		self::checkPreprocessingStepsDuplicates($items);
		self::checkValueMaps($items, $db_items);
		self::checkHostInterfaces($items, $db_items);
		self::checkDependentItems($items, $db_items);
	}

	/**
	 * @return array
	 */
	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'uuid' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'host_status', 'in' => HOST_STATUS_TEMPLATE], 'type' => API_UUID],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'itemid' =>			['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'flags' => API_REQUIRED_LLD_MACRO, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => ITEM_TYPE_DEPENDENT], 'type' => API_INT32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY])],
									['else' => true, 'type' => API_INT32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])]
			]],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'units')]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => '0']
			]],
			'valuemapid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])], 'type' => API_ID],
									['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'logtimefmt' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => ITEM_VALUE_TYPE_LOG], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'logtimefmt')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'logtimefmt')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	self::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO)
		]];
	}

	/**
	 * @return array
	 */
	private static function getInheritedValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'uuid' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'itemid' =>			['type' => API_ANY],
			'name' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'type' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'key_' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'value_type' =>		['type' => API_ANY],
			'units' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => '0']
			]],
			'valuemapid' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'logtimefmt' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
		]];
	}

	/**
	 * @inheritDoc
	 */
	protected static function addInternalFields(array &$db_items): void {
		$result = DBselect(
			'SELECT i.itemid,i.hostid,i.templateid,i.flags,h.status AS host_status,id.parent_itemid AS ruleid'.
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
	public static function updateForce(array &$items, array &$db_items): void {
		// Helps to avoid deadlocks.
		CArrayHelper::sort($items, ['itemid']);

		self::addFieldDefaultsByType($items, $db_items);

		$upd_items = [];
		$upd_itemids = [];

		$internal_fields = array_flip(['itemid', 'type', 'key_', 'hostid', 'flags', 'host_status']);
		$nested_object_fields = array_flip(['tags', 'preprocessing', 'parameters']);

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

		self::updateTags($items, $db_items, $upd_itemids);
		self::updatePreprocessing($items, $db_items, $upd_itemids);
		self::updateParameters($items, $db_items, $upd_itemids);
		self::updateDiscoveredItems($items, $db_items);

		$items = array_intersect_key($items, $upd_itemids);
		$db_items = array_intersect_key($db_items, array_flip($upd_itemids));

		self::prepareItemsForApi($items);
		self::prepareItemsForApi($db_items);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_ITEM_PROTOTYPE, $items, $db_items);
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 */
	private static function updateDiscoveredItems(array $item_prototypes, array $db_item_prototypes): void {
		foreach ($item_prototypes as $i => $item_prototype) {
			if (!in_array($item_prototype['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])
					|| !array_key_exists('update_discovered_items', $db_item_prototypes[$item_prototype['itemid']])) {
				unset($item_prototypes[$i]);
				continue;
			}
		}

		if (!$item_prototypes) {
			return;
		}

		$result = DBselect(
			'SELECT id.itemid,i.name,i.valuemapid'.
			' FROM item_discovery id,items i'.
			' WHERE id.itemid=i.itemid'.
				' AND '.dbConditionId('id.parent_itemid', array_column($item_prototypes, 'itemid'))
		);

		$items = [];
		$db_items = [];

		while ($row = DBfetch($result)) {
			$items[] = [
				'itemid' => $row['itemid'],
				'valuemapid' => 0
			];

			$db_items[$row['itemid']] = $row;
		}

		if ($items) {
			CItem::updateForce($items, $db_items);
		}
	}

	/**
	 * @param array $itemids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $itemids): array {
		$this->validateDelete($itemids, $db_items);

		self::deleteForce($db_items);

		return ['prototypeids' => $itemids];
	}

	/**
	 * @param array      $itemids
	 * @param array|null $db_items
	 *
	 * @throws APIException
	 */
	private function validateDelete(array $itemids, ?array &$db_items): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $itemids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_items = $this->get([
			'output' => ['itemid', 'name', 'templateid'],
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
					_('cannot delete inherited item prototype')
				));
			}
		}
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 */
	public static function linkTemplateObjects(array $templateids, array $hostids): void {
		$db_items = DB::select('items', [
			'output' => array_merge(['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
				'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
			], array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters'])),
			'filter' => [
				'flags' => ZBX_FLAG_DISCOVERY_PROTOTYPE,
				'hostid' => $templateids
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
				'tags' => []
			];
		}

		self::addAffectedObjects($items, $db_items);

		$items = array_values($db_items);

		foreach ($items as &$item) {
			if (array_key_exists('parameters', $item)) {
				$item['parameters'] = array_values($item['parameters']);
			}

			$item['preprocessing'] = array_values($item['preprocessing']);
			$item['tags'] = array_values($item['tags']);
		}
		unset($item);

		self::inherit($items, [], $hostids);
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

		if ($hostids !== null && !$is_dep_items) {
			$dep_items_to_link = [];

			$item_indexes = array_flip(array_column($items, 'itemid'));

			foreach ($items as $i => $item) {
				if ($item['type'] == ITEM_TYPE_DEPENDENT && array_key_exists($item['master_itemid'], $item_indexes)) {
					$dep_items_to_link[$item_indexes[$item['master_itemid']]][$i] = $item;

					unset($items[$i]);
				}
			}
		}

		$chunks = self::getInheritChunks($items, $tpl_links);

		foreach ($chunks as $chunk) {
			$_items = array_intersect_key($items, array_flip($chunk['item_indexes']));
			$_db_items = array_intersect_key($db_items, array_flip(array_column($_items, 'itemid')));
			$_hostids = array_keys($chunk['hosts']);

			self::inheritChunk($_items, $_db_items, $tpl_links, $_hostids);
		}

		if ($hostids !== null && !$is_dep_items) {
			self::inheritDependentItems($dep_items_to_link, $items, $hostids);
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
	 * @param array $db_items
	 * @param array $hostids
	 *
	 * @return array
	 */
	private static function getChildObjectsUsingTemplateid(array $items, array $db_items, array $hostids): array {
		$upd_db_items = DB::select('items', [
			'output' => array_merge(['itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history', 'trends',
				'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
			], array_diff(CItemType::FIELD_NAMES, ['parameters'])),
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

			foreach ($upd_db_items as &$upd_db_item) {
				$item = $items[$parent_indexes[$upd_db_item['templateid']]];
				$db_item = $db_items[$upd_db_item['templateid']];

				if (array_key_exists('update_discovered_items', $db_item)) {
					$upd_db_item['update_discovered_items'] = true;
				}

				$upd_item = [
					'itemid' => $upd_db_item['itemid'],
					'type' => $item['type']
				];

				$upd_item += array_intersect_key([
					'tags' => [],
					'preprocessing' => [],
					'parameters' => []
				], $db_item);

				$upd_items[] = $upd_item;
			}
			unset($upd_db_item);

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
	 * @param array $items
	 * @param array $tpl_links
	 *
	 * @return array
	 */
	private static function getLldLinks(array $items): array {
		$options = [
			'output' => ['templateid', 'hostid', 'itemid'],
			'filter' => ['templateid' => array_unique(array_column($items, 'ruleid'))]
		];
		$result = DBselect(DB::makeSql('items', $options));

		$lld_links = [];

		while ($row = DBfetch($result)) {
			$lld_links[$row['templateid']][$row['hostid']] = $row['itemid'];
		}

		return $lld_links;
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
				'ht.templateid AS parent_hostid,id.parent_itemid AS ruleid,'.
				dbConditionCoalesce('id.parent_itemid', 0, 'ruleid').
			' FROM hosts_templates ht'.
			' INNER JOIN items i ON ht.hostid=i.hostid'.
			' INNER JOIN hosts h ON ht.hostid=h.hostid'.
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
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
				'trends', 'valuemapid', 'logtimefmt', 'description', 'status', 'discover'
			], array_diff(CItemType::FIELD_NAMES, ['parameters'])),
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
				'tags' => [],
				'preprocessing' => [],
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
				'headers' => [],
				'tags' => [],
				'preprocessing' => [],
				'parameters' => [],
				'query_fields' => []
			];

			$upd_items[] = $upd_item;
		}

		return $upd_items;
	}

	/**
	 * @param array $item
	 * @param array $upd_db_item
	 *
	 * @throws APIException
	 */
	protected static function showObjectMismatchError(array $item, array $upd_db_item): void {
		parent::showObjectMismatchError($item, $upd_db_item);

		$target_is_host = in_array($upd_db_item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]);

		$hosts = DB::select('hosts', [
			'output' => ['host'],
			'hostids' => [$item['hostid'], $upd_db_item['hostid']],
			'preservekeys' => true
		]);

		$lld_rules = DB::select('items', [
			'output' => ['name'],
			'itemids' => [$item['ruleid'], $upd_db_item['ruleid']],
			'preservekeys' => true
		]);

		$error = $target_is_host
			? _('Cannot inherit item prototype with key "%1$s" of template "%2$s" and LLD rule "%3$s" to host "%4$s", because an item prototype with the same key already belongs to LLD rule "%5$s".')
			: _('Cannot inherit item prototype with key "%1$s" of template "%2$s" and LLD rule "%3$s" to template "%4$s", because an item prototype with the same key already belongs to LLD rule "%5$s".');

		self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $upd_db_item['key_'], $hosts[$item['hostid']]['host'],
			$lld_rules[$item['ruleid']]['name'], $hosts[$upd_db_item['hostid']]['host'],
			$lld_rules[$upd_db_item['ruleid']]['name']
		));
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
	 * @param array $ruleids
	 */
	public static function unlinkTemplateObjects(array $ruleids): void {
		$result = DBselect(
			'SELECT id.itemid,i.name,i.type,i.key_,i.templateid,i.uuid,i.valuemapid,i.hostid,i.flags,'.
				'h.status AS host_status'.
			' FROM item_discovery id,items i,hosts h'.
			' WHERE id.itemid=i.itemid'.
				' AND i.hostid=h.hostid'.
				' AND '.dbConditionId('id.parent_itemid', $ruleids).
				' AND '.dbConditionId('i.templateid', [0], true)
		);

		$items = [];
		$db_items = [];
		$i = 0;
		$tpl_itemids = [];
		$internal_fields = array_flip(['type', 'key_', 'hostid', 'flags']);

		while ($row = DBfetch($result)) {
			$item = [
				'itemid' => $row['itemid'],
				'templateid' => 0,
				'host_status' => $row['host_status']
			];

			if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
				$item += ['uuid' => generateUuidV4()];
			}

			if ($row['valuemapid'] != 0) {
				$item += ['valuemapid' => 0];
				$row['update_discovered_items'] = true;

				if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
					$tpl_itemids[$i] = $row['itemid'];
					$item += array_intersect_key($row, $internal_fields);
				}
			}

			if ($row['host_status'] != HOST_STATUS_TEMPLATE || $row['valuemapid'] == 0) {
				unset($row['type']);
			}

			$items[$i++] = $item;
			$db_items[$row['itemid']] = $row;
		}

		if ($items) {
			self::updateForce($items, $db_items);

			if ($tpl_itemids) {
				$items = array_intersect_key($items, $tpl_itemids);
				$db_items = array_intersect_key($db_items, array_flip($tpl_itemids));

				self::inherit($items, $db_items);
			}
		}
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
				'flags' => ZBX_FLAG_DISCOVERY_RULE,
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

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if (!$options['countOutput']) {
			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}

			if ($options['selectValueMap'] !== null) {
				$sqlParts = $this->addQuerySelect('i.valuemapid', $sqlParts);
			}
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemids = array_keys($result);

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = [];
				$relationMap = $this->createRelationMap($result, 'itemid', 'triggerid', 'functions');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$triggers = API::TriggerPrototype()->get([
						'output' => $options['selectTriggers'],
						'triggerids' => $related_ids,
						'preservekeys' => true
					]);

					if (!is_null($options['limitSelects'])) {
						order_result($triggers, 'description');
					}
				}

				$result = $relationMap->mapMany($result, $triggers, 'triggers', $options['limitSelects']);
			}
			else {
				$triggers = API::TriggerPrototype()->get([
					'countOutput' => true,
					'groupCount' => true,
					'itemids' => $itemids
				]);
				$triggers = zbx_toHash($triggers, 'itemid');

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
				$relationMap = $this->createRelationMap($result, 'itemid', 'graphid', 'graphs_items');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$graphs = API::GraphPrototype()->get([
						'output' => $options['selectGraphs'],
						'graphids' => $related_ids,
						'preservekeys' => true
					]);

					if (!is_null($options['limitSelects'])) {
						order_result($graphs, 'name');
					}
				}

				$result = $relationMap->mapMany($result, $graphs, 'graphs', $options['limitSelects']);
			}
			else {
				$graphs = API::GraphPrototype()->get([
					'countOutput' => true,
					'groupCount' => true,
					'itemids' => $itemids
				]);
				$graphs = zbx_toHash($graphs, 'itemid');

				foreach ($result as $itemid => $item) {
					$result[$itemid]['graphs'] = array_key_exists($itemid, $graphs)
						? $graphs[$itemid]['rowscount']
						: '0';
				}
			}
		}

		// adding discoveryrule
		if ($options['selectDiscoveryRule'] !== null && $options['selectDiscoveryRule'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'parent_itemid', 'item_discovery');
			$discoveryRules = API::DiscoveryRule()->get([
				'output' => $options['selectDiscoveryRule'],
				'itemids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// Adding item tags.
		if ($options['selectTags'] !== null) {
			$options['selectTags'] = ($options['selectTags'] !== API_OUTPUT_EXTEND)
				? (array) $options['selectTags']
				: ['tag', 'value'];

			$options['selectTags'] = array_intersect(['tag', 'value'], $options['selectTags']);
			$requested_output = array_flip($options['selectTags']);

			$db_tags = DBselect(
				'SELECT '.implode(',', array_merge($options['selectTags'], ['itemid'])).
				' FROM item_tag'.
				' WHERE '.dbConditionInt('itemid', $itemids)
			);

			array_walk($result, function (&$item) {
				$item['tags'] = [];
			});

			while ($db_tag = DBfetch($db_tags)) {
				$result[$db_tag['itemid']]['tags'][] = array_intersect_key($db_tag, $requested_output);
			}
		}

		return $result;
	}

	/**
	 * @param array $db_items
	 */
	public static function deleteForce(array $db_items): void {
		self::addInheritedItems($db_items);
		self::addDependentItems($db_items);

		$del_itemids = array_keys($db_items);

		// Lock item prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM items i'.
			' WHERE '.dbConditionId('i.itemid', $del_itemids).
			' FOR UPDATE'
		);

		self::deleteAffectedGraphPrototypes($del_itemids);
		self::resetGraphsYAxis($del_itemids);

		self::deleteDiscoveredItems($del_itemids);

		self::deleteAffectedTriggers($del_itemids);

		DB::delete('graphs_items', ['itemid' => $del_itemids]);
		DB::delete('widget_field', ['value_itemid' => $del_itemids]);
		DB::delete('item_discovery', ['itemid' => $del_itemids]);
		DB::delete('item_parameter', ['itemid' => $del_itemids]);
		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::delete('item_tag', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0, 'master_itemid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_ITEM_PROTOTYPE, $db_items);
	}

	/**
	 * Add the dependent item prototypes of the given items to the given item prototypes array.
	 *
	 * @param array      $db_items
	 */
	protected static function addDependentItems(array &$db_items): void {
		$master_itemids = array_keys($db_items);

		do {
			$options = [
				'output' => ['itemid', 'name'],
				'filter' => ['master_itemid' => $master_itemids]
			];
			$result = DBselect(DB::makeSql('items', $options));

			$master_itemids = [];

			while ($row = DBfetch($result)) {
				$master_itemids[] = $row['itemid'];

				$db_items[$row['itemid']] = $row;
			}
		} while ($master_itemids);
	}

	/**
	 * Delete graph prototypes, which would remain without item prototypes after the given item prototypes deletion.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteAffectedGraphPrototypes(array $del_itemids): void {
		$del_graphids = DBfetchColumn(DBselect(
			'SELECT DISTINCT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionId('gi.itemid', $del_itemids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii,items i'.
					' WHERE gi.graphid=gii.graphid'.
						' AND gii.itemid=i.itemid'.
						' AND '.dbConditionId('gii.itemid', $del_itemids, true).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
				')'
		), 'graphid');

		if ($del_graphids) {
			CGraphPrototypeManager::delete($del_graphids);
		}
	}

	/**
	 * Delete discovered items of the given item prototypes.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteDiscoveredItems(array $del_itemids): void {
		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT id.itemid,i.name'.
			' FROM item_discovery id,items i'.
			' WHERE id.itemid=i.itemid'.
				' AND '.dbConditionId('id.parent_itemid', $del_itemids)
		), 'itemid');

		if ($db_items) {
			CItem::deleteForce($db_items);
		}
	}
}
