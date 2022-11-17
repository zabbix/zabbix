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
	 * Define a set of supported pre-processing rules.
	 *
	 * @var array
	 */
	const SUPPORTED_PREPROCESSING_TYPES = [ZBX_PREPROC_REGSUB, ZBX_PREPROC_TRIM, ZBX_PREPROC_RTRIM,
		ZBX_PREPROC_LTRIM, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH, ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_DELTA_VALUE,
		ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_BOOL2DEC, ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC,
		ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX,
		ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX,
		ZBX_PREPROC_THROTTLE_VALUE, ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_SCRIPT,
		ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON, ZBX_PREPROC_CSV_TO_JSON,
		ZBX_PREPROC_STR_REPLACE, ZBX_PREPROC_VALIDATE_NOT_SUPPORTED, ZBX_PREPROC_XML_TO_JSON
	];

	/**
	 * Define a set of supported item types.
	 *
	 * @var array
	 */
	const SUPPORTED_ITEM_TYPES = [ITEM_TYPE_ZABBIX, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
		ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_SSH,
		ITEM_TYPE_TELNET, ITEM_TYPE_CALCULATED, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DEPENDENT,
		ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SNMP, ITEM_TYPE_SCRIPT
	];

	public function __construct() {
		parent::__construct();

		$this->errorMessages = array_merge($this->errorMessages, [
			self::ERROR_EXISTS_TEMPLATE => _('Item "%1$s" already exists on "%2$s", inherited from another template.'),
			self::ERROR_EXISTS => _('Item "%1$s" already exists on "%2$s".'),
			self::ERROR_INVALID_KEY => _('Invalid key "%1$s" for item "%2$s" on "%3$s": %4$s.')
		]);
	}

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
			$result = $this->unsetExtraFields($result, ['name_upper']);
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
				$item['headers'] = $this->headersStringToArray($item['headers']);
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
	 * Create item.
	 *
	 * @param $items
	 *
	 * @return array
	 */
	public function create($items) {
		$items = zbx_toArray($items);

		parent::checkInput($items);
		self::validateInventoryLinks($items);

		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
			unset($item['itemid']);
		}
		unset($item);

		$this->validateDependentItems($items);

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

			if (array_key_exists('preprocessing', $item)) {
				$item['preprocessing'] = $this->normalizeItemPreprocessingSteps($item['preprocessing']);
			}
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

		$this->createReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Create host item.
	 *
	 * @param array $items
	 */
	protected function createReal(array &$items) {
		$items_rtdata = [];

		foreach ($items as $key => &$item) {
			if ($item['type'] != ITEM_TYPE_DEPENDENT) {
				$item['master_itemid'] = null;
			}

			if (array_key_exists('rtdata', $item)) {
				$items_rtdata[$key] = [];
				unset($item['rtdata']);
			}
		}
		unset($item);

		$itemids = DB::insert('items', $items);

		foreach ($items_rtdata as $key => &$value) {
			$value['itemid'] = $itemids[$key];
		}
		unset($value);

		DB::insert('item_rtdata', $items_rtdata, false);

		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];
		}

		$this->createItemParameters($items, $itemids);
		$this->createItemPreprocessing($items);
		$this->createItemTags($items, $itemids);
	}

	/**
	 * Update host items.
	 *
	 * @param array $items
	 */
	protected function updateReal(array $items) {
		CArrayHelper::sort($items, ['itemid']);

		$data = [];
		foreach ($items as $item) {
			unset($item['flags']); // flags cannot be changed
			$data[] = ['values' => $item, 'where' => ['itemid' => $item['itemid']]];
		}
		DB::update('items', $data);

		$this->updateItemParameters($items);
		$this->updateItemPreprocessing($items);
		$this->updateItemTags($items);
	}

	/**
	 * Update item.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function update($items) {
		$items = zbx_toArray($items);

		parent::checkInput($items, true);
		self::validateInventoryLinks($items, true);

		$db_items = $this->get([
			'output' => ['flags', 'type', 'master_itemid', 'authtype', 'allow_traps', 'retrieve_mode', 'value_type'],
			'itemids' => zbx_objectValues($items, 'itemid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$items = $this->extendFromObjects(zbx_toHash($items, 'itemid'), $db_items, ['flags', 'type', 'authtype',
			'master_itemid', 'value_type'
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

		foreach ($items as &$item) {
			$type_change = ($item['type'] != $db_items[$item['itemid']]['type']);

			if ($item['type'] != ITEM_TYPE_DEPENDENT && $db_items[$item['itemid']]['master_itemid'] != 0) {
				$item['master_itemid'] = 0;
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

			if ($item['value_type'] == ITEM_VALUE_TYPE_LOG || $item['value_type'] == ITEM_VALUE_TYPE_TEXT) {
				if ($item['value_type'] != $db_items[$item['itemid']]['value_type']) {
					// Reset valuemapid when value_type is LOG or TEXT.
					$item['valuemapid'] = 0;
				}
				else {
					unset($item['valuemapid']);
				}
			}

			if (array_key_exists('tags', $item)) {
				$item['tags'] = array_map(function ($tag) {
					return $tag + ['value' => ''];
				}, $item['tags']);
			}

			if (array_key_exists('preprocessing', $item)) {
				$item['preprocessing'] = $this->normalizeItemPreprocessingSteps($item['preprocessing']);
			}
		}
		unset($item);

		$this->updateReal($items);
		$this->inherit($items);

		return ['itemids' => zbx_objectValues($items, 'itemid')];
	}

	/**
	 * Delete items.
	 *
	 * @param array $itemids
	 *
	 * @return array
	 */
	public function delete(array $itemids) {
		$this->validateDelete($itemids, $db_items);

		CItemManager::delete($itemids);

		$this->addAuditBulk(CAudit::ACTION_DELETE, CAudit::RESOURCE_ITEM, $db_items);

		return ['itemids' => $itemids];
	}

	/**
	 * Validates the input parameters for the delete() method.
	 *
	 * @param array $itemids   [IN/OUT]
	 * @param array $db_items  [OUT]
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDelete(array &$itemids, array &$db_items = null) {
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

		foreach ($itemids as $itemid) {
			if (!array_key_exists($itemid, $db_items)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_item = $db_items[$itemid];

			if ($db_item['templateid'] != 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated item.'));
			}
		}
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$output = [];
		foreach ($this->fieldRules as $field_name => $rules) {
			if (!array_key_exists('system', $rules) && !array_key_exists('host', $rules)) {
				$output[] = $field_name;
			}
		}

		$tpl_items = $this->get([
			'output' => $output,
			'selectPreprocessing' => ['type', 'params', 'error_handler', 'error_handler_params'],
			'selectTags' => ['tag', 'value'],
			'hostids' => $data['templateids'],
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		foreach ($tpl_items as &$tpl_item) {
			if ($tpl_item['type'] == ITEM_TYPE_HTTPAGENT) {
				if (array_key_exists('query_fields', $tpl_item) && is_array($tpl_item['query_fields'])) {
					$tpl_item['query_fields'] = $tpl_item['query_fields']
						? json_encode($tpl_item['query_fields'])
						: '';
				}

				if (array_key_exists('headers', $tpl_item) && is_array($tpl_item['headers'])) {
					$tpl_item['headers'] = $this->headersArrayToString($tpl_item['headers']);
				}
			}
			else {
				$tpl_item['query_fields'] = '';
				$tpl_item['headers'] = '';
			}
		}
		unset($tpl_item);

		$this->inherit($tpl_items, $data['hostids']);

		return true;
	}

	/**
	 * Check item specific fields:
	 *		- validate history and trends using simple interval parser and user macro parser;
	 *		- validate item preprocessing.
	 *
	 * @param array  $item    An array of single item data.
	 * @param string $method  A string of "create" or "update" method.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkSpecificFields(array $item, $method) {
		if (array_key_exists('history', $item)
				&& !validateTimeUnit($item['history'], SEC_PER_HOUR, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'history', $error)
			);
		}

		if (array_key_exists('trends', $item)
				&& !validateTimeUnit($item['trends'], SEC_PER_DAY, 25 * SEC_PER_YEAR, true, $error,
					['usermacros' => true])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', 'trends', $error)
			);
		}
	}

	/**
	 * Check, if items that are about to be inserted or updated violate the rule:
	 * only one item can be linked to a inventory filed.
	 * If everything is ok, function return true or throws Exception otherwise
	 *
	 * @static
	 *
	 * @param array $items
	 * @param bool $update whether this is update operation
	 *
	 * @return bool
	 */
	public static function validateInventoryLinks(array $items, $update = false) {
		// inventory link field is not being updated, or being updated to 0, no need to validate anything then
		foreach ($items as $i => $item) {
			if (!isset($item['inventory_link']) || $item['inventory_link'] == 0) {
				unset($items[$i]);
			}
		}

		if (zbx_empty($items)) {
			return true;
		}

		$possibleHostInventories = getHostInventories();
		if ($update) {
			// for successful validation we need three fields for each item: inventory_link, hostid and key_
			// problem is, that when we are updating an item, we might not have them, because they are not changed
			// so, we need to find out what is missing and use API to get the lacking info
			$itemsWithNoHostId = [];
			$itemsWithNoInventoryLink = [];
			$itemsWithNoKeys = [];
			foreach ($items as $item) {
				if (!isset($item['inventory_link'])) {
					$itemsWithNoInventoryLink[$item['itemid']] = $item['itemid'];
				}
				if (!isset($item['hostid'])) {
					$itemsWithNoHostId[$item['itemid']] = $item['itemid'];
				}
				if (!isset($item['key_'])) {
					$itemsWithNoKeys[$item['itemid']] = $item['itemid'];
				}
			}
			$itemsToFind = array_merge($itemsWithNoHostId, $itemsWithNoInventoryLink, $itemsWithNoKeys);

			// are there any items with lacking info?
			if (!zbx_empty($itemsToFind)) {
				$missingInfo = API::Item()->get([
					'output' => ['hostid', 'inventory_link', 'key_'],
					'filter' => ['itemid' => $itemsToFind],
					'nopermissions' => true
				]);
				$missingInfo = zbx_toHash($missingInfo, 'itemid');

				// appending host ids, inventory_links and keys where they are needed
				foreach ($items as $i => $item) {
					if (isset($missingInfo[$item['itemid']])) {
						if (!isset($items[$i]['hostid'])) {
							$items[$i]['hostid'] = $missingInfo[$item['itemid']]['hostid'];
						}
						if (!isset($items[$i]['inventory_link'])) {
							$items[$i]['inventory_link'] = $missingInfo[$item['itemid']]['inventory_link'];
						}
						if (!isset($items[$i]['key_'])) {
							$items[$i]['key_'] = $missingInfo[$item['itemid']]['key_'];
						}
					}
				}
			}
		}

		$hostids = zbx_objectValues($items, 'hostid');

		// getting all inventory links on every affected host
		$itemsOnHostsInfo = API::Item()->get([
			'output' => ['key_', 'inventory_link', 'hostid'],
			'filter' => ['hostid' => $hostids],
			'nopermissions' => true
		]);

		// now, changing array to: 'hostid' => array('key_'=>'inventory_link')
		$linksOnHostsCurr = [];
		foreach ($itemsOnHostsInfo as $info) {
			// 0 means no link - we are not interested in those ones
			if ($info['inventory_link'] != 0) {
				if (!isset($linksOnHostsCurr[$info['hostid']])) {
					$linksOnHostsCurr[$info['hostid']] = [$info['key_'] => $info['inventory_link']];
				}
				else{
					$linksOnHostsCurr[$info['hostid']][$info['key_']] = $info['inventory_link'];
				}
			}
		}

		$linksOnHostsFuture = [];

		foreach ($items as $item) {
			// checking if inventory_link value is a valid number
			if ($update || $item['value_type'] != ITEM_VALUE_TYPE_LOG) {
				// does inventory field with provided number exists?
				if (!isset($possibleHostInventories[$item['inventory_link']])) {
					$maxVar = max(array_keys($possibleHostInventories));
					self::exception(
						ZBX_API_ERROR_PARAMETERS,
						_s('Item "%1$s" cannot populate a missing host inventory field number "%2$d". Choices are: from 0 (do not populate) to %3$d.', $item['name'], $item['inventory_link'], $maxVar)
					);
				}
			}

			if (!isset($linksOnHostsFuture[$item['hostid']])) {
				$linksOnHostsFuture[$item['hostid']] = [$item['key_'] => $item['inventory_link']];
			}
			else {
				$linksOnHostsFuture[$item['hostid']][$item['key_']] = $item['inventory_link'];
			}
		}

		foreach ($linksOnHostsFuture as $hostId => $linkFuture) {
			if (isset($linksOnHostsCurr[$hostId])) {
				$futureSituation = array_merge($linksOnHostsCurr[$hostId], $linksOnHostsFuture[$hostId]);
			}
			else {
				$futureSituation = $linksOnHostsFuture[$hostId];
			}
			$valuesCount = array_count_values($futureSituation);

			// if we have a duplicate inventory links after merging - we are in trouble
			if (max($valuesCount) > 1) {
				// what inventory field caused this conflict?
				$conflictedLink = array_keys($valuesCount, max($valuesCount));
				$conflictedLink = reset($conflictedLink);

				// which of updated items populates this link?
				$beingSavedItemName = '';
				$names = [];

				foreach ($items as $item) {
					if (count($names) == 2) {
						break;
					}

					if ($item['inventory_link'] == $conflictedLink) {
						if (isset($item['name'])) {
							$beingSavedItemName = $item['name'];
						}
						else {
							$thisItem = API::Item()->get([
								'output' => ['name'],
								'filter' => ['itemid' => $item['itemid']],
								'nopermissions' => true
							]);
							$beingSavedItemName = $thisItem[0]['name'];
						}

						$names[] = $beingSavedItemName;
					}
				}

				// Case when at least two templates that are being linked have items that populate same inventory field.
				if (count($names) == 2) {
					[$name1, $name2] = $names;
				}
				else {
					// name of the original item that already populates the field
					$originalItem = API::Item()->get([
						'output' => ['name'],
						'filter' => [
							'hostid' => $hostId,
							'inventory_link' => $conflictedLink
						],
						'nopermissions' => true
					]);

					$name1 = reset($names);
					$name2 = $originalItem[0]['name'];
				}

				self::exception(
					ZBX_API_ERROR_PARAMETERS,
					_s(
						'Two items ("%1$s" and "%2$s") cannot populate one host inventory field "%3$s", this would lead to a conflict.',
						$name1,
						$name2,
						$possibleHostInventories[$conflictedLink]['title']
					)
				);
			}
		}

		return true;
	}

	public function addRelatedObjects(array $options, array $result) {
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

		$upcased_index = array_search($tableAlias.'.name_upper', $sqlParts['select']);

		if ($upcased_index !== false) {
			unset($sqlParts['select'][$upcased_index]);
		}

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
}
