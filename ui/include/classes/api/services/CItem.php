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
 * Class containing methods for operations with items.
 */
class CItem extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status'];

	public const OUTPUT_FIELDS = ['itemid', 'type', 'snmp_oid', 'hostid', 'name', 'key_', 'delay', 'history', 'trends',
		'status', 'value_type', 'trapper_hosts', 'units', 'logtimefmt', 'templateid', 'valuemapid', 'params',
		'ipmi_sensor', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'flags', 'interfaceid',
		'description', 'inventory_link', 'jmx_endpoint', 'master_itemid', 'timeout', 'url', 'query_fields', 'posts',
		'status_codes', 'follow_redirects', 'post_type', 'http_proxy', 'headers', 'retrieve_mode', 'request_method',
		'output_format', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'verify_peer', 'verify_host',
		'allow_traps', 'state', 'error', 'parameters', 'lastclock', 'lastns', 'lastvalue', 'prevvalue', 'name_resolved'
	];

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
	public const SUPPORTED_ITEM_TYPES = [
		ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
		ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
		ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT,
		ITEM_TYPE_BROWSER
	];

	/**
	 * @inheritDoc
	 */
	protected const VALUE_TYPE_FIELD_NAMES = [
		ITEM_VALUE_TYPE_FLOAT => ['units', 'trends', 'valuemapid', 'inventory_link'],
		ITEM_VALUE_TYPE_STR => ['valuemapid', 'inventory_link'],
		ITEM_VALUE_TYPE_LOG => ['logtimefmt'],
		ITEM_VALUE_TYPE_UINT64 => ['units', 'trends', 'valuemapid', 'inventory_link'],
		ITEM_VALUE_TYPE_TEXT => ['inventory_link'],
		ITEM_VALUE_TYPE_BINARY => []
	];

	/**
	 * Get items data.
	 *
	 * @param array  $options
	 * @param array  $options['itemids']
	 * @param array  $options['hostids']
	 * @param array  $options['groupids']
	 * @param array  $options['triggerids']
	 * @param bool   $options['status']
	 * @param bool   $options['templated_items']
	 * @param bool   $options['editable']
	 * @param bool   $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$sqlParts = [
			'select'	=> ['items' => 'i.itemid'],
			'from'		=> ['items' => 'items i'],
			'where'		=> ['webtype' => 'i.type<>'.ITEM_TYPE_HTTPTEST, 'flags' => 'i.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'proxyids'					=> null,
			'itemids'					=> null,
			'interfaceids'				=> null,
			'graphids'					=> null,
			'triggerids'				=> null,
			'webitems'					=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			'group'						=> null,
			'host'						=> null,
			'with_triggers'				=> null,
			'evaltype'					=> TAG_EVAL_TYPE_AND_OR,
			'tags'						=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectHosts'				=> null,
			'selectInterfaces'			=> null,
			'selectTags'				=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectDiscoveryRule'		=> null,
			'selectItemDiscovery'		=> null,
			'selectPreprocessing'		=> null,
			'selectValueMap'			=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);
		$this->validateGet($options);

		// editable + permission check
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

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = dbConditionInt('i.itemid', $options['itemids']);
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

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);

			$sqlParts['where']['interfaceid'] = dbConditionId('i.interfaceid', $options['interfaceids']);

			if ($options['groupCount']) {
				$sqlParts['group']['i'] = 'i.interfaceid';
			}
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where'][] = 'hg.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['hg'] = 'hg.groupid';
			}
		}

		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where'][] = dbConditionId('h.proxyid', $options['proxyids']);
			$sqlParts['where'][] = 'h.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['h'] = 'h.proxyid';
			}
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['if'] = 'i.itemid=f.itemid';
		}

		// tags
		if ($options['tags'] !== null && $options['tags']) {
			$sqlParts['where'][] = CApiTagHelper::addWhereCondition($options['tags'], $options['evaltype'], 'i',
				'item_tag', 'itemid'
			);
		}

		// graphids
		if (!is_null($options['graphids'])) {
			zbx_value2array($options['graphids']);

			$sqlParts['from']['graphs_items'] = 'graphs_items gi';
			$sqlParts['where'][] = dbConditionInt('gi.graphid', $options['graphids']);
			$sqlParts['where']['igi'] = 'i.itemid=gi.itemid';
		}

		// webitems
		if (!is_null($options['webitems'])) {
			unset($sqlParts['where']['webtype']);
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

			if (array_key_exists('name_resolved', $options['search']) && $options['search']['name_resolved'] !== null) {
				zbx_db_search(
					'item_rtname irn',
					['search' => ['name_resolved' => $options['search']['name_resolved']]] + $options,
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

			if (array_key_exists('history', $options['filter']) && $options['filter']['history'] !== null) {
				$options['filter']['history'] = getTimeUnitFilters($options['filter']['history']);
			}

			if (array_key_exists('trends', $options['filter']) && $options['filter']['trends'] !== null) {
				$options['filter']['trends'] = getTimeUnitFilters($options['filter']['trends']);
			}

			if (array_key_exists('state', $options['filter']) && $options['filter']['state'] !== null) {
				$this->dbFilter('item_rtdata ir', ['filter' => ['state' => $options['filter']['state']]] + $options,
					$sqlParts
				);
			}

			if (array_key_exists('name_resolved', $options['filter']) && $options['filter']['name_resolved'] !== null) {
				$this->dbFilter(
					'item_rtname irn',
					['filter' => ['name_resolved' => $options['filter']['name_resolved']]] + $options,
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

			if (array_key_exists('flags', $options['filter'])
					&& (is_null($options['filter']['flags']) || !zbx_empty($options['filter']['flags']))) {
				unset($sqlParts['where']['flags']);
			}
		}

		// group
		if (!is_null($options['group'])) {
			$sqlParts['from']['hstgrp'] = 'hstgrp g';
			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where']['ghg'] = 'g.groupid=hg.groupid';
			$sqlParts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = ' g.name='.zbx_dbstr($options['group']);
		}

		// host
		if (!is_null($options['host'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
			$sqlParts['where'][] = ' h.host='.zbx_dbstr($options['host']);
		}

		// with_triggers
		if (!is_null($options['with_triggers'])) {
			if ($options['with_triggers'] == 1) {
				$sqlParts['where'][] = 'EXISTS ('.
					'SELECT NULL'.
					' FROM functions ff,triggers t'.
					' WHERE i.itemid=ff.itemid'.
						' AND ff.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
			}
			else {
				$sqlParts['where'][] = 'NOT EXISTS ('.
					'SELECT NULL'.
					' FROM functions ff,triggers t'.
					' WHERE i.itemid=ff.itemid'.
						' AND ff.triggerid=t.triggerid'.
						' AND t.flags IN ('.ZBX_FLAG_DISCOVERY_NORMAL.','.ZBX_FLAG_DISCOVERY_CREATED.')'.
					')';
			}
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

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

		// removing keys (hash -> array)
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
	private function validateGet(array $options) {
		// Validate input parameters.
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'selectValueMap' => ['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => 'valuemapid,name,mappings'],
			'evaltype' => ['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR])]
		]];
		$options_filter = array_intersect_key($options, $api_input_rules['fields']);
		if (!CApiInputValidator::validate($api_input_rules, $options_filter, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private function prepareChunkObjects(array &$items, array $options): void {
		$items = $this->addRelatedObjects($options, $items);
		$items = $this->unsetExtraFields($items, ['hostid', 'interfaceid', 'value_type', 'valuemapid'],
			$options['output']
		);
		$items = $this->unsetExtraFields($items, ['name_upper']);

		self::prepareItemsForApi($items, false);

		foreach ($items as &$item) {
			// Items share table with item prototypes. Therefore remove item unrelated fields.
			unset($item['discover']);
		}
		unset($item);
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
		self::addFlags($items, ZBX_FLAG_DISCOVERY_NORMAL);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'flags' =>			['type' => API_ANY],
			'uuid' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_UUID],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid'), 'unset' => true]
			]],
			'hostid' =>			['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => ITEM_TYPE_DEPENDENT], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY])],
									['else' => true, 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])]
			]],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'units')]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => '0', 'default' => 0]
			]],
			'valuemapid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])], 'type' => API_ID],
									['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'inventory_link' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])], 'type' => API_INT32, 'in' => '0,'.implode(',', array_keys(getHostInventories()))],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'inventory_link')]
			]],
			'logtimefmt' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => ITEM_VALUE_TYPE_LOG], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'logtimefmt')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'logtimefmt')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	self::getPreprocessingValidationRules()
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType(array_keys($api_input_rules['fields']), $items);

		self::addUuid($items);

		self::checkUuidDuplicates($items);
		self::checkDuplicates($items);
		self::checkPreprocessingStepsDuplicates($items);
		self::checkValueMaps($items);
		self::checkInventoryLinks($items);
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

		$ins_items_rtdata = [];
		$ins_items_rtname = [];
		$host_statuses = [];

		foreach ($items as &$item) {
			$item['itemid'] = array_shift($itemids);

			if (in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
				$ins_items_rtdata[] = ['itemid' => $item['itemid']];
				$ins_items_rtname[] = [
					'itemid' => $item['itemid'],
					'name_resolved' => $item['name']
				];
			}

			$host_statuses[] = $item['host_status'];
			unset($item['host_status']);
		}
		unset($item);

		if ($ins_items_rtdata) {
			DB::insertBatch('item_rtdata', $ins_items_rtdata, false);
		}

		if ($ins_items_rtname) {
			DB::insertBatch('item_rtname', $ins_items_rtname, false);
		}

		self::updateParameters($items);
		self::updatePreprocessing($items);
		self::updateTags($items);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_ITEM, $items);

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
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
				'trends', 'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
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
			elseif ($db_item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
				$api_input_rules = self::getDiscoveredValidationRules();
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

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['hostid', 'flags']);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkUuidDuplicates($items, $db_items);
		self::checkDuplicates($items, $db_items);
		self::checkPreprocessingStepsDuplicates($items);
		self::checkValueMaps($items, $db_items);
		self::checkInventoryLinks($items, $db_items);
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
			'key_' =>			['type' => API_ITEM_KEY, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => ITEM_TYPE_DEPENDENT], 'type' => API_INT32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY])],
									['else' => true, 'type' => API_INT32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])]
			]],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'units')]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => ITEM_NO_STORAGE_VALUE.','.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => '0']
			]],
			'valuemapid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])], 'type' => API_ID],
									['else' => true, 'type' => API_ID, 'in' => '0']
			]],
			'inventory_link' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])], 'type' => API_INT32, 'in' => '0,'.implode(',', array_keys(getHostInventories()))],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'inventory_link')]
			]],
			'logtimefmt' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => ITEM_VALUE_TYPE_LOG], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'logtimefmt')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'logtimefmt')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	self::getPreprocessingValidationRules()
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
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => ITEM_NO_STORAGE_VALUE.','.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_DAY, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => '0']
			]],
			'valuemapid' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'inventory_link' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])], 'type' => API_INT32, 'in' => '0,'.implode(',', array_keys(getHostInventories()))],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('items', 'inventory_link')]
			]],
			'logtimefmt' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED]
		]];
	}

	/**
	 * @return array
	 */
	private static function getDiscoveredValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'uuid' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'itemid' =>			['type' => API_ANY],
			'name' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'type' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'key_' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'value_type' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'units' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'history' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'trends' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'valuemapid' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'inventory_link' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'logtimefmt' =>		['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'description' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'tags' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'preprocessing' =>	['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED]
		]];
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
		$upd_items_rtname = [];
		$upd_item_discoveries = [];

		$internal_fields = array_flip(['itemid', 'type', 'key_', 'value_type', 'hostid', 'flags', 'host_status']);
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

				if (array_key_exists('name', $upd_item)
						&& in_array($item['host_status'], [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED])) {
					$upd_items_rtname[] = [
						'values' => ['name_resolved' => $upd_item['name']],
						'where' => ['itemid' => $item['itemid']]
					];
				}

				if (array_key_exists('flags', $item) && $item['flags'] == ZBX_FLAG_DISCOVERY_CREATED
						&& array_key_exists('status', $item) && $item['status'] == ITEM_STATUS_DISABLED
						&& $item['status'] != $db_items[$item['itemid']]['status']) {
					$upd_item_discoveries[] = $item['itemid'];
				}
			}
			else {
				$item = array_intersect_key($item, $internal_fields + $nested_object_fields);
			}
		}
		unset($item);

		if ($upd_items) {
			DB::update('items', $upd_items);
		}

		if ($upd_items_rtname) {
			DB::update('item_rtname', $upd_items_rtname);
		}

		if ($upd_item_discoveries) {
			DB::update('item_discovery', [
				'values' => ['disable_source' => ZBX_DISABLE_DEFAULT],
				'where' => ['itemid' => $upd_item_discoveries]
			]);
		}

		self::updateTags($items, $db_items, $upd_itemids);
		self::updatePreprocessing($items, $db_items, $upd_itemids);
		self::updateParameters($items, $db_items, $upd_itemids);

		$items = array_intersect_key($items, $upd_itemids);
		$db_items = array_intersect_key($db_items, array_flip($upd_itemids));

		self::prepareItemsForApi($items);
		self::prepareItemsForApi($db_items);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_ITEM, $items, $db_items);
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

		return ['itemids' => $itemids];
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
					_('cannot delete inherited item')
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
				'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
			], array_diff(CItemType::FIELD_NAMES, ['interfaceid', 'parameters'])),
			'filter' => [
				'hostid' => $templateids,
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL,
				'type' => self::SUPPORTED_ITEM_TYPES
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
			$upd_db_items = self::getChildObjectsUsingName($items_to_link, $hostids);

			if ($upd_db_items) {
				$upd_items = self::getUpdChildObjectsUsingName($items_to_link, $upd_db_items);
			}

			$ins_items = self::getInsChildObjects($items_to_link, $upd_db_items, $tpl_links, $hostids);
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
		self::checkInventoryLinks($edit_items, $upd_db_items, true);

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
				'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
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

			foreach ($upd_db_items as $upd_db_item) {
				$item = $items[$parent_indexes[$upd_db_item['templateid']]];
				$db_item = $db_items[$upd_db_item['templateid']];

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
				array_flip(['itemid', 'hostid', 'templateid', 'host_status'])
			) + $item;
		}

		return $upd_items;
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
			'output' => array_merge(['uuid', 'itemid', 'name', 'type', 'key_', 'value_type', 'units', 'history',
				'trends', 'valuemapid', 'inventory_link', 'logtimefmt', 'description', 'status'
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
				'host_status' => $upd_db_item['host_status']
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
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function unlinkTemplateObjects(array $templateids, ?array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('ii.hostid', $hostids) : '';

		$result = DBselect(
			'SELECT ii.itemid,ii.name,ii.type,ii.key_,ii.value_type,ii.templateid,ii.uuid,ii.valuemapid,ii.hostid,'.
				'ii.flags,h.status AS host_status'.
			' FROM items i,items ii,hosts h'.
			' WHERE i.itemid=ii.templateid'.
				' AND ii.hostid=h.hostid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL]).
				' AND '.dbConditionInt('i.type', self::SUPPORTED_ITEM_TYPES).
				$hostids_condition
		);

		$items = [];
		$db_items = [];
		$i = 0;
		$tpl_itemids = [];
		$internal_fields = array_flip(['type', 'key_', 'value_type', 'hostid', 'flags', 'host_status']);

		while ($row = DBfetch($result)) {
			$item = [
				'itemid' => $row['itemid'],
				'templateid' => 0
			];

			if ($row['host_status'] == HOST_STATUS_TEMPLATE) {
				$item += ['uuid' => generateUuidV4()];
			}

			if ($row['valuemapid'] != 0) {
				$item += ['valuemapid' => 0];

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
	 * @param array      $templateids
	 * @param array|null $hostids
	 */
	public static function clearTemplateObjects(array $templateids, ?array $hostids = null): void {
		$hostids_condition = $hostids ? ' AND '.dbConditionId('ii.hostid', $hostids) : '';

		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT ii.itemid,ii.name'.
			' FROM items i,items ii'.
			' WHERE i.itemid=ii.templateid'.
				' AND '.dbConditionId('i.hostid', $templateids).
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL]).
				' AND '.dbConditionInt('i.type', self::SUPPORTED_ITEM_TYPES).
				$hostids_condition
		), 'itemid');

		if ($db_items) {
			self::deleteForce($db_items);
		}
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 * @param bool  $inherited
	 *
	 * @throws APIException
	 */
	private static function checkInventoryLinks(array $items, array $db_items = [], bool $inherited = false): void {
		$item_indexes = [];
		$del_links = [];

		$value_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT];

		foreach ($items as $i => $item) {
			$check = false;

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL && in_array($item['value_type'], $value_types)) {
				if (array_key_exists('inventory_link', $item)) {
					if (!array_key_exists('itemid', $item)) {
						if ($item['inventory_link'] != 0) {
							$check = true;
							$item_indexes[$item['hostid']][] = $i;
						}
					}
					else {
						if ($item['inventory_link'] != 0) {
							if ($item['inventory_link'] != $db_items[$item['itemid']]['inventory_link']) {
								$check = true;
								$item_indexes[$item['hostid']][] = $i;

								if ($db_items[$item['itemid']]['inventory_link'] != 0) {
									$del_links[$item['hostid']][] = $db_items[$item['itemid']]['inventory_link'];
								}
							}
						}
						elseif ($db_items[$item['itemid']]['inventory_link'] != 0) {
							$del_links[$item['hostid']][] = $db_items[$item['itemid']]['inventory_link'];
						}
					}
				}
			}
			elseif (array_key_exists('itemid', $item) && $db_items[$item['itemid']]['inventory_link'] != 0) {
				$del_links[$item['hostid']][] = $db_items[$item['itemid']]['inventory_link'];
			}

			if (!$check) {
				unset($items[$i]);
			}
		}

		if (!$items) {
			return;
		}

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['hostid', 'inventory_link']], 'fields' => [
			'hostid' =>			['type' => API_ANY],
			'inventory_link' =>	['type' => API_ANY]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $items, '/', $error)) {
			if ($inherited) {
				$_item_indexes = [];

				foreach ($items as $i => $item) {
					if (array_key_exists($item['hostid'], $_item_indexes)
							&& array_key_exists($item['inventory_link'], $_item_indexes[$item['hostid']])) {
						$error = $item['host_status'] == HOST_STATUS_TEMPLATE
							? _('Cannot inherit item with key "%1$s" of template "%2$s" and item with key "%3$s" of template "%4$s" to template "%5$s", because they would populate the same inventory field "%6$s".')
							: _('Cannot inherit item with key "%1$s" of template "%2$s" and item with key "%3$s" of template "%4$s" to host "%5$s", because they would populate the same inventory field "%6$s".');

						$_item = $items[$_item_indexes[$item['hostid']][$item['inventory_link']]];

						$templates = DBfetchArrayAssoc(DBselect(
							'SELECT i.itemid,h.host'.
							' FROM items i,hosts h'.
							' WHERE i.hostid=h.hostid'.
								' AND '.dbConditionId('i.itemid', [$_item['templateid'], $item['templateid']])
						), 'itemid');

						$hosts = DB::select('hosts', [
							'output' => ['host'],
							'hostids' => $item['hostid']
						]);

						$inventory_fields = getHostInventories();

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $_item['key_'],
							$templates[$_item['templateid']]['host'], $item['key_'],
							$templates[$item['templateid']]['host'], $hosts[0]['host'],
							$inventory_fields[$item['inventory_link']]['title']
						));
					}

					$_item_indexes[$item['hostid']][$item['inventory_link']] = $i;
				}
			}

			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$options = [
			'output' => ['hostid', 'inventory_link', 'key_'],
			'filter' => [
				'hostid' => array_unique(array_column($items, 'hostid')),
				'inventory_link' => array_unique(array_column($items, 'inventory_link'))
			]
		];
		$result = DBselect(DB::makeSql('items', $options));

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['hostid'], $del_links)
					&& in_array($row['inventory_link'], $del_links[$row['hostid']])) {
				continue;
			}

			foreach ($item_indexes[$row['hostid']] as $i) {
				if ($row['inventory_link'] == $items[$i]['inventory_link']) {
					$item = $items[$i];

					if ($inherited) {
						$error = $item['host_status'] == HOST_STATUS_TEMPLATE
							? _('Cannot inherit item with key "%1$s" of template "%2$s" to template "%3$s", because its inventory field "%4$s" is already populated by the item with key "%5$s".')
							: _('Cannot inherit item with key "%1$s" of template "%2$s" to host "%3$s", because its inventory field "%4$s" is already populated by the item with key "%5$s".');

						$template = DBfetch(DBselect(
							'SELECT h.host'.
							' FROM items i,hosts h'.
							' WHERE i.hostid=h.hostid'.
								' AND '.dbConditionId('i.itemid', [$item['templateid']])
						));

						$hosts = DB::select('hosts', [
							'output' => ['host'],
							'hostids' => $item['hostid']
						]);

						$inventory_fields = getHostInventories();

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error, $item['key_'], $template['host'],
							$hosts[0]['host'], $inventory_fields[$item['inventory_link']]['title'], $row['key_']
						));
					}
					else {
						$error = $item['host_status'] == HOST_STATUS_TEMPLATE
							? _('Cannot assign the inventory field "%1$s" to the item with key "%2$s" of template "%3$s", because it is already populated by the item with key "%4$s".')
							: _('Cannot assign the inventory field "%1$s" to the item with key "%2$s" of host "%3$s", because it is already populated by the item with key "%4$s".');

						$inventory_fields = getHostInventories();

						$hosts = DB::select('hosts', [
							'output' => ['host'],
							'hostids' => $item['hostid']
						]);

						self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
							$inventory_fields[$item['inventory_link']]['title'], $item['key_'], $hosts[0]['host'],
							$row['key_']
						));
					}
				}
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$itemids = array_keys($result);

		// adding interfaces
		if ($options['selectInterfaces'] !== null && $options['selectInterfaces'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'itemid', 'interfaceid');
			$interfaces = API::HostInterface()->get([
				'output' => $options['selectInterfaces'],
				'interfaceids' => $relationMap->getRelatedIds(),
				'nopermissions' => true,
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $interfaces, 'interfaces');
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			if ($options['selectTriggers'] != API_OUTPUT_COUNT) {
				$triggers = [];
				$relationMap = $this->createRelationMap($result, 'itemid', 'triggerid', 'functions');
				$related_ids = $relationMap->getRelatedIds();

				if ($related_ids) {
					$triggers = API::Trigger()->get([
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
				$triggers = API::Trigger()->get([
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
					$graphs = API::Graph()->get([
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
				$graphs = API::Graph()->get([
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
			$discoveryRules = [];
			$relationMap = new CRelationMap();
			// discovered items
			$dbRules = DBselect(
				'SELECT id1.itemid,id2.parent_itemid'.
					' FROM item_discovery id1,item_discovery id2,items i'.
					' WHERE '.dbConditionInt('id1.itemid', $itemids).
					' AND id1.parent_itemid=id2.itemid'.
					' AND i.itemid=id1.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_CREATED
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['itemid'], $rule['parent_itemid']);
			}

			// item prototypes
			// TODO: this should not be in the item API
			$dbRules = DBselect(
				'SELECT id.parent_itemid,id.itemid'.
					' FROM item_discovery id,items i'.
					' WHERE '.dbConditionInt('id.itemid', $itemids).
					' AND i.itemid=id.itemid'.
					' AND i.flags='.ZBX_FLAG_DISCOVERY_PROTOTYPE
			);
			while ($rule = DBfetch($dbRules)) {
				$relationMap->addRelation($rule['itemid'], $rule['parent_itemid']);
			}

			$related_ids = $relationMap->getRelatedIds();

			if ($related_ids) {
				$discoveryRules = API::DiscoveryRule()->get([
					'output' => $options['selectDiscoveryRule'],
					'itemids' => $related_ids,
					'nopermissions' => true,
					'preservekeys' => true
				]);
			}

			$result = $relationMap->mapOne($result, $discoveryRules, 'discoveryRule');
		}

		// adding item discovery
		if ($options['selectItemDiscovery'] !== null) {
			$itemDiscoveries = API::getApiService()->select('item_discovery', [
				'output' => $this->outputExtend($options['selectItemDiscovery'], ['itemdiscoveryid', 'itemid']),
				'filter' => ['itemid' => array_keys($result)],
				'preservekeys' => true
			]);
			$relationMap = $this->createRelationMap($itemDiscoveries, 'itemid', 'itemdiscoveryid');

			$itemDiscoveries = $this->unsetExtraFields($itemDiscoveries, ['itemid', 'itemdiscoveryid'],
				$options['selectItemDiscovery']
			);
			$result = $relationMap->mapOne($result, $itemDiscoveries, 'itemDiscovery');
		}

		$requested_output = array_filter([
			'lastclock' => $this->outputIsRequested('lastclock', $options['output']),
			'lastns' => $this->outputIsRequested('lastns', $options['output']),
			'lastvalue' => $this->outputIsRequested('lastvalue', $options['output']),
			'prevvalue' => $this->outputIsRequested('prevvalue', $options['output'])
		]);

		if ($requested_output) {
			$history = Manager::History()->getLastValues($result, 2, timeUnitToSeconds(CSettingsHelper::get(
				CSettingsHelper::HISTORY_PERIOD
			)));

			foreach ($result as &$item) {
				$last_history = null;
				$prev_history = null;
				$no_value = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]) ? '0' : '';

				if (array_key_exists($item['itemid'], $history)) {
					[$last_history, $prev_history] = $history[$item['itemid']] + [null, null];

					if ($item['value_type'] == ITEM_VALUE_TYPE_BINARY) {
						if ($last_history) {
							$last_history['value'] = base64_encode($last_history['value']);
						}

						if ($prev_history) {
							$prev_history['value'] = base64_encode($prev_history['value']);
						}
					}
				}

				$item += array_intersect_key([
					'lastclock' => $last_history ? $last_history['clock'] : '0',
					'lastns' => $last_history ? $last_history['ns'] : '0',
					'lastvalue' => $last_history ? $last_history['value'] : $no_value,
					'prevvalue' => $prev_history ? $prev_history['value'] : $no_value
				], $requested_output);
			}
			unset($item);
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

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ((!$options['countOutput'] && ($this->outputIsRequested('state', $options['output'])
				|| $this->outputIsRequested('error', $options['output'])))
				|| (is_array($options['search']) && array_key_exists('error', $options['search']))
				|| (is_array($options['filter']) && array_key_exists('state', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'ir', 'table' => 'item_rtdata', 'using' => 'itemid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if ((!$options['countOutput'] && $this->outputIsRequested('name_resolved', $options['output']))
				|| (is_array($options['search']) && array_key_exists('name_resolved', $options['search']))
				|| (is_array($options['filter']) && array_key_exists('name_resolved', $options['filter']))) {
			$sqlParts['left_join'][] = ['alias' => 'irn', 'table' => 'item_rtname', 'using' => 'itemid'];
			$sqlParts['left_table'] = ['alias' => $this->tableAlias, 'table' => $this->tableName];
		}

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('state', $options['output'])) {
				$sqlParts = $this->addQuerySelect('ir.state', $sqlParts);
			}

			/*
			 * Use SQL COALESCE function for template items, because they don't have records
			 * in item_rtdata and item_rtname tables and DBFetch converts null to '0'.
			 */
			if ($this->outputIsRequested('error', $options['output'])) {
				$sqlParts = $this->addQuerySelect(dbConditionCoalesce('ir.error', '', 'error'), $sqlParts);
			}
			if ($this->outputIsRequested('name_resolved', $options['output'])) {
				$sqlParts = $this->addQuerySelect(dbConditionCoalesce('irn.name_resolved', '', 'name_resolved'),
					$sqlParts
				);
			}

			if ($options['selectHosts'] !== null) {
				$sqlParts = $this->addQuerySelect('i.hostid', $sqlParts);
			}

			if ($options['selectInterfaces'] !== null) {
				$sqlParts = $this->addQuerySelect('i.interfaceid', $sqlParts);
			}

			if ($options['selectValueMap'] !== null) {
				$sqlParts = $this->addQuerySelect('i.valuemapid', $sqlParts);
			}

			if ($this->outputIsRequested('lastclock', $options['output'])
					|| $this->outputIsRequested('lastns', $options['output'])
					|| $this->outputIsRequested('lastvalue', $options['output'])
					|| $this->outputIsRequested('prevvalue', $options['output'])) {

				$sqlParts = $this->addQuerySelect('i.value_type', $sqlParts);
			}
		}

		return $sqlParts;
	}

	/**
	 * @param array $db_items
	 */
	public static function deleteForce(array $db_items): void {
		self::addInheritedItems($db_items);
		self::addDependentItems($db_items, $db_lld_rules, $db_item_prototypes);

		if ($db_lld_rules) {
			CDiscoveryRule::deleteForce($db_lld_rules);
		}

		if ($db_item_prototypes) {
			CItemPrototype::deleteForce($db_item_prototypes);
		}

		$del_itemids = array_keys($db_items);

		self::deleteAffectedGraphs($del_itemids);
		self::resetGraphsYAxis($del_itemids);
		self::deleteFromFavoriteGraphs($del_itemids);

		self::deleteAffectedTriggers($del_itemids);

		self::clearHistoryAndTrends($del_itemids);

		DB::delete('graphs_items', ['itemid' => $del_itemids]);
		DB::delete('widget_field', ['value_itemid' => $del_itemids]);
		DB::delete('item_discovery', ['itemid' => $del_itemids]);
		DB::delete('item_parameter', ['itemid' => $del_itemids]);
		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::delete('item_rtdata', ['itemid' => $del_itemids]);
		DB::delete('item_rtname', ['itemid' => $del_itemids]);
		DB::delete('item_tag', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0, 'master_itemid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_ITEM, $db_items);
	}

	/**
	 * Add the dependent items of the given items to the given item array. Also add the dependent LLD rules and item
	 * prototypes to the given appropriate variables.
	 *
	 * @param array      $db_items
	 * @param array|null $db_lld_rules
	 * @param array|null $db_item_prototypes
	 */
	protected static function addDependentItems(array &$db_items, ?array &$db_lld_rules = null,
			?array &$db_item_prototypes = null): void {
		$db_lld_rules = [];
		$db_item_prototypes = [];

		$master_itemids = array_keys($db_items);

		do {
			$options = [
				'output' => ['itemid', 'name', 'flags'],
				'filter' => ['master_itemid' => $master_itemids]
			];
			$result = DBselect(DB::makeSql('items', $options));

			$master_itemids = [];

			while ($row = DBfetch($result)) {
				if ($row['flags'] == ZBX_FLAG_DISCOVERY_RULE) {
					$db_lld_rules[$row['itemid']] = array_diff_key($row, array_flip(['flags']));
				}
				elseif ($row['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
					$master_itemids[] = $row['itemid'];

					$db_item_prototypes[$row['itemid']] = array_diff_key($row, array_flip(['flags']));
				}
				else {
					if (!array_key_exists($row['itemid'], $db_items)) {
						$master_itemids[] = $row['itemid'];

						$db_items[$row['itemid']] = array_diff_key($row, array_flip(['flags']));
					}
				}
			}
		} while ($master_itemids);
	}

	/**
	 * Delete graphs, which would remain without items after the given items deletion.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteAffectedGraphs(array $del_itemids): void {
		$del_graphids = DBfetchColumn(DBselect(
			'SELECT DISTINCT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionId('gi.itemid', $del_itemids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii'.
					' WHERE gii.graphid=gi.graphid'.
						' AND '.dbConditionId('gii.itemid', $del_itemids, true).
				')'
		), 'graphid');

		if ($del_graphids) {
			CGraphManager::delete($del_graphids);
		}
	}

	/**
	 * Delete the latest data graph of the given items from the favorites.
	 *
	 * @param array $del_itemids
	 */
	private static function deleteFromFavoriteGraphs(array $del_itemids): void {
		DB::delete('profiles', [
			'idx' => 'web.favorite.graphids',
			'source' => 'itemid',
			'value_id' => $del_itemids
		]);
	}

	/**
	 * Clear the history and trends of the given items.
	 *
	 * @param array $del_itemids
	 */
	private static function clearHistoryAndTrends(array $del_itemids): void {
		global $DB;

		$table_names = ['events'];

		$timescale_extension = $DB['TYPE'] === ZBX_DB_POSTGRESQL
			&& CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION) === ZBX_DB_EXTENSION_TIMESCALEDB;

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_MODE) == 1
				&& (!$timescale_extension || CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 0)) {
			$table_names = array_merge($table_names, CHistoryManager::getTableName());
		}

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_MODE) == 1
				&& (!$timescale_extension || CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL) == 0)) {
			array_push($table_names, 'trends', 'trends_uint');
		}

		$ins_housekeeper = [];

		foreach ($del_itemids as $del_itemid) {
			foreach ($table_names as $table_name) {
				$ins_housekeeper[] = [
					'tablename' => $table_name,
					'field' => 'itemid',
					'value' => $del_itemid
				];

				if (count($ins_housekeeper) == ZBX_DB_MAX_INSERTS) {
					DB::insertBatch('housekeeper', $ins_housekeeper);
					$ins_housekeeper = [];
				}
			}
		}

		if ($ins_housekeeper) {
			DB::insertBatch('housekeeper', $ins_housekeeper);
		}
	}
}
