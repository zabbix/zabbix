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
 * Class containing methods for operations with items.
 */
class CItem extends CItemGeneral {

	protected $tableName = 'items';
	protected $tableAlias = 'i';
	protected $sortColumns = ['itemid', 'name', 'key_', 'delay', 'history', 'trends', 'type', 'status'];

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
		ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, ZBX_PREPROC_XML_TO_JSON
	];

	/**
	 * @inheritDoc
	 */
	protected const PREPROC_TYPES_WITH_PARAMS = [
		ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_TRIM, ZBX_PREPROC_REGSUB,
		ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH, ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX,
		ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX,
		ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT, ZBX_PREPROC_PROMETHEUS_PATTERN,
		ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON, ZBX_PREPROC_STR_REPLACE
	];

	/**
	 * @inheritDoc
	 */
	protected const PREPROC_TYPES_WITH_ERR_HANDLING = [
		ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_REGSUB, ZBX_PREPROC_BOOL2DEC, ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC,
		ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
		ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX,
		ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX,
		ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON,
		ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, ZBX_PREPROC_XML_TO_JSON
	];

	/**
	 * @inheritDoc
	 */
	protected const SUPPORTED_ITEM_TYPES = [
		ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
		ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED,
		ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT, ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
	];

	/**
	 * @inheritDoc
	 */
	protected const VALUE_TYPE_FIELD_NAMES = [
		ITEM_VALUE_TYPE_FLOAT => ['units', 'trends', 'valuemapid', 'inventory_link'],
		ITEM_VALUE_TYPE_STR => ['valuemapid', 'inventory_link'],
		ITEM_VALUE_TYPE_LOG => ['logtimefmt'],
		ITEM_VALUE_TYPE_UINT64 => ['units', 'trends', 'valuemapid', 'inventory_link'],
		ITEM_VALUE_TYPE_TEXT => ['inventory_link']
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
		$result = [];

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
			$sqlParts['where'][] = dbConditionId('h.proxy_hostid', $options['proxyids']);
			$sqlParts['where'][] = 'h.hostid=i.hostid';

			if ($options['groupCount']) {
				$sqlParts['group']['h'] = 'h.proxy_hostid';
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
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			// Items share table with item prototypes. Therefore remove item unrelated fields.
			unset($item['discover']);

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
			$result = $this->unsetExtraFields($result, ['hostid', 'interfaceid', 'value_type', 'valuemapid'],
				$options['output']
			);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		// Decode ITEM_TYPE_HTTPAGENT encoded fields.
		foreach ($result as &$item) {
			if (array_key_exists('query_fields', $item)) {
				$query_fields = ($item['query_fields'] !== '') ? json_decode($item['query_fields'], true) : [];
				$item['query_fields'] = json_last_error() ? [] : $query_fields;
			}

			if (array_key_exists('headers', $item)) {
				$item['headers'] = self::headersStringToArray($item['headers']);
			}
		}
		unset($item);

		return $result;
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

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $items): array {
		self::validateCreate($items);

		self::createForce($items);
		[$tpl_items] = self::getTemplatedObjects($items);

		if ($tpl_items) {
			$this->inherit($tpl_items);
		}

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
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'uuid')]
			]],
			'hostid' =>			['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'units')]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => timeUnitToSeconds(DB::getDefault('items', 'trends'))]
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

		self::checkAndAddUuid($items);
		self::checkDuplicates($items);
		self::checkValueMaps($items);
		self::checkInventoryLinks($items);
		self::checkHostInterfaces($items);
		self::checkDependentItems($items);
	}

	/**
	 * @param array $items
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $items): array {
		$this->validateUpdate($items, $db_items);

		self::updateForce($items, $db_items);

		[$tpl_items, $tpl_db_items] = self::getTemplatedObjects($items, $db_items);

		if ($tpl_items) {
			$this->inherit($tpl_items, $tpl_db_items);
		}

		return ['itemids' => array_column($items, 'itemid')];
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

		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT i.itemid,i.name,i.type,i.key_,i.value_type,i.units,i.history,i.trends,i.valuemapid,'.
				'i.inventory_link,i.logtimefmt,i.description,i.status,i.hostid,i.templateid,i.flags,'.
				'h.status AS host_status'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.itemid', array_column($items, 'itemid'))
		), 'itemid');

		foreach ($items as $i => &$item) {
			$db_item = $db_items[$item['itemid']];

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
				$item += array_intersect_key($db_item, array_flip(['key_', 'value_type']));

				$api_input_rules = self::getValidationRules();
			}

			if (!CApiInputValidator::validate($api_input_rules, $item, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$item += array_intersect_key($db_item, array_flip(['type']));
		}
		unset($item);

		self::addDbFieldsByType($items, $db_items);

		self::validateByType(array_keys($api_input_rules['fields']), $items, $db_items);

		$items = $this->extendObjectsByKey($items, $db_items, 'itemid', ['hostid', 'key_', 'host_status', 'flags']);

		self::validateUniqueness($items);

		self::addAffectedObjects($items, $db_items);

		self::checkDuplicates($items, $db_items);
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
			'itemid' =>			['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('items', 'units')]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => ITEM_NO_STORAGE_VALUE.','.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => timeUnitToSeconds(DB::getDefault('items', 'trends'))]
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
			'itemid' =>			['type' => API_ANY],
			'name' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'type' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'key_' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'value_type' =>		['type' => API_ANY],
			'units' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => ITEM_NO_STORAGE_VALUE.','.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_TIME_UNIT, 'in' => timeUnitToSeconds(DB::getDefault('items', 'trends'))]
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
			'output' => ['itemid', 'name', 'templateid', 'flags'],
			'itemids' => $itemids,
			'editable' => true,
			'preservekeys' => true
		]);

		if (count($db_items) != count($itemids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($itemids as $itemid) {
			if ($db_items[$itemid]['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete inherited item.'));
			}
		}
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 */
	public function syncTemplates(array $templateids, array $hostids): void {
		$db_items = DBfetchArrayAssoc(DBselect(
			'SELECT i.itemid,i.name,i.type,i.key_,i.value_type,i.units,i.history,i.trends,i.valuemapid,'.
				'i.inventory_link,i.logtimefmt,i.description,i.status,i.hostid,i.templateid,i.flags,'.
				'h.status AS host_status'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_NORMAL]).
				' AND '.dbConditionId('i.hostid', $templateids)
		), 'itemid');

		if (!$db_items) {
			return;
		}

		$items = [];

		foreach ($db_items as $db_item) {
			$item = array_intersect_key($db_item, array_flip(['itemid', 'type']));

			if ($db_item['type'] == ITEM_TYPE_SCRIPT) {
				$item += ['parameters' => []];
			}

			$items[] = $item + [
				'preprocessing' => [],
				'tags' => []
			];
		}

		self::addDbFieldsByType($items, $db_items);
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

		$this->inherit($items, [], $hostids);
	}

	/**
	 * @param array $items
	 * @param array $db_items
	 *
	 * @throws APIException
	 */
	protected static function checkInventoryLinks(array $items, array $db_items = []): void {
		$item_indexes = [];
		$del_links = [];

		foreach ($items as $i => $item) {
			if (!array_key_exists('inventory_link', $item) || $item['inventory_link'] == 0
					|| (array_key_exists('itemid', $item) && $item['inventory_link'] == $db_items[$item['itemid']])) {
				unset($items[$i]);
			}
			else {
				$item_indexes[$item['hostid']][] = $i;

				if (array_key_exists('itemid', $item)) {
					if ($db_items[$item['itemid']]['inventory_link'] != 0) {
						$del_links[$item['hostid']][] = $db_items[$item['itemid']]['inventory_link'];
					}
				}
			}
		}

		if (!$items) {
			return;
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['hostid', 'inventory_link']], 'fields' => [
			'hostid' =>			['type' => API_ANY],
			'inventory_link' =>	['type' => API_ANY]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$result = DBselect(
			'SELECT i.hostid,i.inventory_link,h.status,h.host,i.key_'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND '.dbConditionId('i.hostid', array_unique(array_column($items, 'hostid'))).
				' AND '.dbConditionInt('i.inventory_link', array_unique(array_column($items, 'inventory_link')))
		);

		while ($row = DBfetch($result)) {
			if (array_key_exists($row['hostid'], $del_links)
					&& in_array($row['inventory_link'], $del_links[$row['hostid']])) {
				continue;
			}

			foreach ($item_indexes[$row['hostid']] as $i) {
				if ($row['inventory_link'] == $items[$i]['inventory_link']) {
					$error = ($row['status'] == HOST_STATUS_TEMPLATE)
						? _('Cannot set the host inventory field "%1$s" to the item "%2$s" on the template "%3$s": %4$s.')
						: _('Cannot set the host inventory field "%1$s" to the item "%2$s" on the host "%3$s": %4$s.');

					$inventory_fields = getHostInventories();

					self::exception(ZBX_API_ERROR_PARAMETERS, sprintf($error,
						$inventory_fields[$row['inventory_link']]['title'], $items[$i]['key_'], $row['host'],
						_s('it is already set for the item "%1$s"', $row['key_'])
					));
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

		// adding history data
		$requestedOutput = [];
		if ($this->outputIsRequested('lastclock', $options['output'])) {
			$requestedOutput['lastclock'] = true;
		}
		if ($this->outputIsRequested('lastns', $options['output'])) {
			$requestedOutput['lastns'] = true;
		}
		if ($this->outputIsRequested('lastvalue', $options['output'])) {
			$requestedOutput['lastvalue'] = true;
		}
		if ($this->outputIsRequested('prevvalue', $options['output'])) {
			$requestedOutput['prevvalue'] = true;
		}
		if ($requestedOutput) {
			$history = Manager::History()->getLastValues($result, 2, timeUnitToSeconds(CSettingsHelper::get(
				CSettingsHelper::HISTORY_PERIOD
			)));
			foreach ($result as &$item) {
				$lastHistory = isset($history[$item['itemid']][0]) ? $history[$item['itemid']][0] : null;
				$prevHistory = isset($history[$item['itemid']][1]) ? $history[$item['itemid']][1] : null;
				$no_value = in_array($item['value_type'],
						[ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]) ? '' : '0';

				if (isset($requestedOutput['lastclock'])) {
					$item['lastclock'] = $lastHistory ? $lastHistory['clock'] : '0';
				}
				if (isset($requestedOutput['lastns'])) {
					$item['lastns'] = $lastHistory ? $lastHistory['ns'] : '0';
				}
				if (isset($requestedOutput['lastvalue'])) {
					$item['lastvalue'] = $lastHistory ? $lastHistory['value'] : $no_value;
				}
				if (isset($requestedOutput['prevvalue'])) {
					$item['prevvalue'] = $prevHistory ? $prevHistory['value'] : $no_value;
				}
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
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedObjects(array $items, array &$db_items): void {
		parent::addAffectedObjects($items, $db_items);
		self::addAffectedTags($items, $db_items);
	}

		/**
	 * Deletes items and related entities without permission check.
	 *
	 * @param array  $db_items
	 * @param string $db_items[<num>]['itemid']
	 * @param string $db_items[<num>]['name']
	 * @param int    $db_items[<num>]['flags']
	 */
	public static function deleteForce(array $db_items) {
		$del_itemids = [];
		$del_items = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_RULE => [],
			ZBX_FLAG_DISCOVERY_CREATED => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		$parent_itemids = array_column($db_items, 'itemid');

		foreach ($db_items as $i => $db_item) {
			$del_items[$db_item['flags']][$db_item['itemid']] = $db_item;
			unset($db_items[$i]);
		}

		// Select inherited items.
		do {
			$child_items = DBselect(
				'SELECT i.itemid,i.name,i.flags'.
				' FROM items i'.
				' WHERE '.dbConditionId('i.templateid', $parent_itemids)
			);

			$del_itemids += array_flip($parent_itemids);
			$parent_itemids = [];

			while ($child_item = DBfetch($child_items)) {
				$itemid = $child_item['itemid'];
				$del_items[$child_item['flags']][$itemid] = $child_item;

				if (!array_key_exists($itemid, $del_itemids)) {
					$parent_itemids[] = $itemid;
				}
			}
		} while ($parent_itemids);

		// Select all dependent items.
		// Note: We are not separating normal from discovered items at this point.
		$dep_itemids = array_keys($del_itemids);
		$del_itemids = [];

		do {
			$dep_items = DBselect(
				'SELECT i.itemid,i.name,i.flags'.
				' FROM items i'.
				' WHERE i.type='.ITEM_TYPE_DEPENDENT.
					' AND '.dbConditionInt('i.master_itemid', $dep_itemids)
			);

			$del_itemids += array_flip($dep_itemids);
			$dep_itemids = [];

			while ($dep_item = DBfetch($dep_items)) {
				$itemid = $dep_item['itemid'];
				$del_items[$dep_item['flags']][$itemid] = $dep_item;

				if (!array_key_exists($itemid, $del_itemids)) {
					$dep_itemids[] = $itemid;
				}
			}

		} while ($dep_itemids);

		$del_itemids = array_keys($del_itemids);

		if ($del_items[ZBX_FLAG_DISCOVERY_RULE]) {
			CDiscoveryRuleManager::delete(array_keys($del_items[ZBX_FLAG_DISCOVERY_RULE]));
		}

		if ($del_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			API::getApiService('itemprototype')->deleteForce($del_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
			$del_itemids = array_diff_key($del_itemids, array_keys($del_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]));
			unset($del_items[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		// Delete graphs and graph prototypes, which will remain without items.
		$del_graphids = DBfetchColumn(DBselect(
			'SELECT DISTINCT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionInt('gi.itemid', $del_itemids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii'.
					' WHERE gii.graphid=gi.graphid'.
						' AND '.dbConditionInt('gii.itemid', $del_itemids, true).
				')'
		), 'graphid');

		if ($del_graphids) {
			CGraphManager::delete($del_graphids);
		}

		// Cleanup ymin_itemid and ymax_itemid fields for graphs and graph prototypes.
		DB::update('graphs', [
			'values' => [
				'ymin_type' => GRAPH_YAXIS_TYPE_CALCULATED,
				'ymin_itemid' => null
			],
			'where' => ['ymin_itemid' => $del_itemids]
		]);

		DB::update('graphs', [
			'values' => [
				'ymax_type' => GRAPH_YAXIS_TYPE_CALCULATED,
				'ymax_itemid' => null
			],
			'where' => ['ymax_itemid' => $del_itemids]
		]);

		// Delete triggers and trigger prototypes.
		$db_triggers = DBselect(
			'SELECT DISTINCT t.triggerid,t.flags'.
			' FROM triggers t,functions f'.
			' WHERE t.triggerid=f.triggerid'.
				' AND '.dbConditionInt('f.itemid', $del_itemids)
		);

		$del_triggerids = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_CREATED => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => []
		];

		while ($db_trigger = DBfetch($db_triggers)) {
			$del_triggerids[$db_trigger['flags']][] = $db_trigger['triggerid'];
		}

		if ($del_triggerids[ZBX_FLAG_DISCOVERY_NORMAL] || $del_triggerids[ZBX_FLAG_DISCOVERY_CREATED]) {
			CTriggerManager::delete(array_merge(
				$del_triggerids[ZBX_FLAG_DISCOVERY_NORMAL],
				$del_triggerids[ZBX_FLAG_DISCOVERY_CREATED]
			));
		}

		if ($del_triggerids[ZBX_FLAG_DISCOVERY_PROTOTYPE]) {
			CTriggerPrototypeManager::delete($del_triggerids[ZBX_FLAG_DISCOVERY_PROTOTYPE]);
		}

		DB::delete('profiles', [
			'idx' => 'web.favorite.graphids',
			'source' => 'itemid',
			'value_id' => $del_itemids
		]);

		// Clean history and trends tables from outdated items.
		global $DB;
		$table_names = ['trends', 'trends_uint', 'history_text', 'history_log', 'history_uint', 'history_str',
			'history', 'events'
		];
		$ins_housekeeper = [];

		if ($DB['TYPE'] === ZBX_DB_POSTGRESQL) {
			if (CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION) === ZBX_DB_EXTENSION_TIMESCALEDB) {
				if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_MODE) != 0
						&& CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
					$table_names = array_diff($table_names,
						['history', 'history_str', 'history_uint', 'history_log', 'history_text']
					);
				}

				if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_MODE) != 0
						&& CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL) == 1) {
					$table_names = array_diff($table_names, ['trends', 'trends_uint']);
				}
			}
		}

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

		DB::delete('item_tag', ['itemid' => $del_itemids]);
		DB::delete('item_preproc', ['itemid' => $del_itemids]);
		DB::update('items', [
			'values' => ['templateid' => 0, 'master_itemid' => 0],
			'where' => ['itemid' => $del_itemids]
		]);
		DB::delete('items', ['itemid' => $del_itemids]);

		$del_items_combined = [];

		foreach ($del_items as $i => $items) {
			$del_items_combined += $items;
			unset($del_items[$i]);
		}

		self::massAddAuditLog(CAudit::ACTION_DELETE, $del_items_combined);
	}
}
