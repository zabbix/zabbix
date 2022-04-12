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
		ITEM_VALUE_TYPE_FLOAT => ['units', 'trends', 'valuemapid'],
		ITEM_VALUE_TYPE_STR => ['valuemapid'],
		ITEM_VALUE_TYPE_LOG => ['logtimefmt'],
		ITEM_VALUE_TYPE_UINT64 => ['units', 'trends', 'valuemapid'],
		ITEM_VALUE_TYPE_TEXT => []
	];

	/**
	 * @inheritDoc
	 */
	protected const AUDIT_RESOURCE = CAudit::RESOURCE_ITEM_PROTOTYPE;

	/**
	 * Get ItemPrototype data.
	 */
	public function get($options = []) {
		$result = [];

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
		$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($item = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount'])
					$result[] = $item;
				else
					$result = $item['rowscount'];
			}
			else {
				$result[$item['itemid']] = $item;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		// add other related objects
		if ($result) {
			if (self::dbDistinct($sqlParts)) {
				$result = $this->addNclobFieldValues($options, $result);
			}

			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid', 'valuemapid'], $options['output']);
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

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
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
		self::addFlags($items, ZBX_FLAG_DISCOVERY_PROTOTYPE);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_ALLOW_UNEXPECTED, 'uniq' => [['uuid'], ['hostid', 'key_']], 'fields' => [
			'host_status' =>	['type' => API_ANY],
			'flags' =>			['type' => API_ANY],
			'uuid' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'host_status', 'in' => implode(',', [HOST_STATUS_TEMPLATE])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'hostid' =>			['type' => API_ANY],
			'ruleid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history'), 'default' => '90d'],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends'), 'default' => '365d'],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'valuemapid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])], 'type' => API_ID],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'logtimefmt' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => ITEM_VALUE_TYPE_LOG], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'logtimefmt')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
			'tags' =>			self::getTagsValidationRules(),
			'preprocessing' =>	self::getPreprocessingValidationRules()
		]];

		if (!CApiInputValidator::validate($api_input_rules, $items, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateByType(array_keys($api_input_rules['fields']), $items);

		self::checkAndAddUuid($items);
		self::checkDuplicates($items);
		self::checkDiscoveryRules($items);
		self::checkValueMaps($items);
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
			'SELECT i.itemid,i.name,i.type,i.key_,i.value_type,i.units,i.history,i.trends,i.valuemapid,i.logtimefmt,'.
				'i.description,i.status,i.discover,i.hostid,i.templateid,i.flags,h.status AS host_status,'.
				'id.parent_itemid AS ruleid'.
			' FROM items i,hosts h,item_discovery id'.
			' WHERE i.hostid=h.hostid'.
				' AND i.itemid=id.itemid'.
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
		self::checkHostInterfaces($items, $db_items);
		self::checkDependentItems($items, $db_items);
	}

	/**
	 * @inheritDoc
	 */
	public static function getPreprocessingValidationRules(int $flags = 0x00): array {
		return parent::getPreprocessingValidationRules(API_ALLOW_LLD_MACRO);
	}

	/**
	 * @return array
	 */
	private static function getValidationRules(): array {
		return ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'itemid' =>			['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'name')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', self::SUPPORTED_ITEM_TYPES)],
			'key_' =>			['type' => API_ITEM_KEY, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('items', 'key_')],
			'value_type' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT])],
			'units' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'units')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'valuemapid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_UINT64])], 'type' => API_ID],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'logtimefmt' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => ITEM_VALUE_TYPE_LOG], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'logtimefmt')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ITEM_STATUS_ACTIVE, ITEM_STATUS_DISABLED])],
			'discover' =>		['type' => API_INT32, 'in' => implode(',', [ITEM_DISCOVER, ITEM_NO_DISCOVER])],
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
			'history' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'history')],
			'trends' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'value_type', 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])], 'type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO | API_ALLOW_LLD_MACRO, 'in' => '0,'.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]), 'length' => DB::getFieldLength('items', 'trends')],
									['else' => true, 'type' => API_UNEXPECTED]
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
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete inherited item prototype.'));
			}
		}
	}

	/**
	 * @param array $templateids
	 * @param array $hostids
	 */
	public function syncTemplates(array $templateids, array $hostids): void {
		$db_item_prototypes = DBfetchArrayAssoc(DBselect(
			'SELECT i.itemid,i.name,i.type,i.key_,i.value_type,i.units,i.history,i.trends,i.valuemapid,i.logtimefmt,'.
				'i.description,i.status,i.discover,i.hostid,i.templateid,i.flags,h.status AS host_status,'.
				'id.parent_itemid AS ruleid'.
			' FROM items i,hosts h,item_discovery id'.
			' WHERE i.hostid=h.hostid'.
				' AND i.itemid=id.itemid'.
				' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
				' AND '.dbConditionId('i.hostid', $templateids)
		), 'itemid');

		if (!$db_item_prototypes) {
			return;
		}

		$item_prototypes = [];

		foreach ($db_item_prototypes as $db_item_prototype) {
			$item_prototype = array_intersect_key($db_item_prototype, array_flip(['itemid', 'type']));

			if ($db_item_prototype['type'] == ITEM_TYPE_SCRIPT) {
				$item_prototype += ['parameters' => []];
			}

			$item_prototypes[] = $item_prototype + [
				'preprocessing' => [],
				'tags' => []
			];
		}

		self::addDbFieldsByType($item_prototypes, $db_item_prototypes);
		self::addAffectedObjects($item_prototypes, $db_item_prototypes);

		$item_prototypes = array_values($db_item_prototypes);

		foreach ($item_prototypes as &$item_prototype) {
			if (array_key_exists('parameters', $item_prototype)) {
				$item_prototype['parameters'] = array_values($item_prototype['parameters']);
			}

			$item_prototype['preprocessing'] = array_values($item_prototype['preprocessing']);
			$item_prototype['tags'] = array_values($item_prototype['tags']);
		}
		unset($item_prototype);

		$this->inherit($item_prototypes, [], $hostids);
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
	 * @param array $items
	 * @param array $db_items
	 */
	protected static function addAffectedObjects(array $items, array &$db_items): void {
		parent::addAffectedObjects($items, $db_items);
		self::addAffectedTags($items, $db_items);
	}

	/**
	 * Deletes item prototypes and related entities without permission check.
	 *
	 * @param array $db_items
	 */
	public static function deleteForce(array $db_items) {
		$del_itemids = [];
		$del_items = [
			ZBX_FLAG_DISCOVERY_NORMAL => [],
			ZBX_FLAG_DISCOVERY_RULE => [],
			ZBX_FLAG_DISCOVERY_CREATED => [],
			ZBX_FLAG_DISCOVERY_PROTOTYPE => $db_items
		];

		// Select parent and their inherited items.
		$parent_itemids = array_column($db_items, 'itemid');

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
					$parent_itemids[] = $child_item['itemid'];
				}
			}
		} while ($parent_itemids);

		// Select dependent items.
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

		// Lock item prototypes before delete to prevent server from adding new LLD elements.
		DBselect(
			'SELECT NULL'.
			' FROM items i'.
			' WHERE '.dbConditionInt('i.itemid', $del_itemids).
			' FOR UPDATE'
		);

		// Deleting graph prototypes, which will remain without item prototypes.
		$db_graphs = DBselect(
			'SELECT DISTINCT gi.graphid'.
			' FROM graphs_items gi'.
			' WHERE '.dbConditionInt('gi.itemid', $del_itemids).
				' AND NOT EXISTS ('.
					'SELECT NULL'.
					' FROM graphs_items gii,items i'.
					' WHERE gi.graphid=gii.graphid'.
						' AND gii.itemid=i.itemid'.
						' AND '.dbConditionInt('gii.itemid', $del_itemids, true).
						' AND '.dbConditionInt('i.flags', [ZBX_FLAG_DISCOVERY_PROTOTYPE]).
				')'
		);

		$del_graphids = [];

		while ($db_graph = DBfetch($db_graphs)) {
			$del_graphids[] = $db_graph['graphid'];
		}

		if ($del_graphids) {
			CGraphPrototypeManager::delete($del_graphids);
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

		// Delete discovered items.
		$del_discovered_items = DBfetchColumn(DBselect(
			'SELECT i.itemid,i.name,i.flags'.
			' FROM items i,item_discovery id'.
			' WHERE id.itemid=i.itemid'.
				' AND '.dbConditionInt('id.parent_itemid', $del_itemids)
		), 'itemid');

		if ($del_discovered_items) {
			CItemGeneral::deleteForce($del_discovered_items);
		}

		// Deleting trigger prototypes.
		$del_triggerids = DBfetchColumn(DBselect(
			'SELECT DISTINCT f.triggerid'.
			' FROM functions f'.
			' WHERE '.dbConditionInt('f.itemid', $del_itemids)
		), 'triggerid');

		if ($del_triggerids) {
			CTriggerPrototypeManager::delete($del_triggerids);
		}

		DB::delete('items', ['itemid' => $del_itemids]);

		static $resource_types = [
			ZBX_FLAG_DISCOVERY_NORMAL => CAudit::RESOURCE_ITEM,
			ZBX_FLAG_DISCOVERY_CREATED => CAudit::RESOURCE_ITEM,
			ZBX_FLAG_DISCOVERY_RULE => CAudit::RESOURCE_DISCOVERY_RULE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE => CAudit::RESOURCE_ITEM_PROTOTYPE
		];

		foreach ($del_items as $flags => $db_items) {
			if ($db_items) {
				self::addAuditLog(CAudit::ACTION_DELETE, $resource_types[$flags], $db_items);
			}
		}
	}
}
