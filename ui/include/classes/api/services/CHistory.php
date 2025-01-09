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
 * Class containing methods for operations with histories.
 */
class CHistory extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'clear' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'push' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	protected $tableName;
	protected $tableAlias = 'h';
	protected $sortColumns = ['itemid', 'clock', 'ns'];

	public function __construct() {
		// considering the quirky nature of the history API,
		// the parent::__construct() method should not be called.
	}

	/**
	 * Get history data.
	 *
	 * @param array  $options
	 * @param int    $options['history']                       History object type to return.
	 * @param array  $options['hostids']                       Return only history from the given hosts.
	 * @param array  $options['itemids']                       Return only history from the given items.
	 * @param int    $options['time_from']                     Return only values that have been received after or at
	 *                                                         the given time.
	 * @param int    $options['time_till']                     Return only values that have been received before or at
	 *                                                         the given time.
	 * @param array  $options['filter']                        Return only those results that exactly match the given
	 *                                                         filter.
	 * @param int    $options['filter']['itemid']
	 * @param int    $options['filter']['clock']
	 * @param mixed  $options['filter']['value']
	 * @param int    $options['filter']['ns']
	 * @param array  $options['search']                        Return results that match the given wildcard search
	 *                                                         (case-insensitive).
	 * @param string $options['search']['value']
	 * @param bool   $options['searchByAny']                   If set to true return results that match any of the
	 *                                                         criteria given in the filter or search parameter instead
	 *                                                         of all of them.
	 * @param bool   $options['startSearch']                   Return results that match the given wildcard search
	 *                                                         (case-insensitive).
	 * @param bool   $options['excludeSearch']                 Return results that do not match the criteria given in
	 *                                                         the search parameter.
	 * @param bool   $options['searchWildcardsEnabled']        If set to true enables the use of "*" as a wildcard
	 *                                                         character in the search parameter.
	 * @param array  $options['output']                        Object properties to be returned.
	 * @param bool   $options['countOutput']                   Return the number of records in the result instead of the
	 *                                                         actual data.
	 * @param array  $options['sortfield']                     Sort the result by the given properties. Refer to a
	 *                                                         specific API get method description for a list of
	 *                                                         properties that can be used for sorting. Macros are not
	 *                                                         expanded before sorting.
	 * @param array  $options['sortorder']                     Order of sorting. If an array is passed, each value will
	 *                                                         be matched to the corresponding property given in the
	 *                                                         sortfield parameter.
	 * @param int    $options['limit']                         Limit the number of records returned.
	 * @param bool   $options['editable']                      If set to true return only objects that the user has
	 *                                                         write permissions to.
	 *
	 * @throws Exception
	 * @return array|int    Data array or number of rows.
	 */
	public function get($options = []) {
		$value_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64,
			ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_BINARY
		];

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'history' =>				['type' => API_INT32, 'in' => implode(',', $value_types), 'default' => ITEM_VALUE_TYPE_UINT64],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'itemids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'time_from' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'time_till' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>					['type' => API_MULTIPLE, 'default' => null, 'rules' => [
											['if' => ['field' => 'history', 'in' => implode(',', [ITEM_VALUE_TYPE_LOG])], 'type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['itemid', 'clock', 'timestamp', 'source', 'severity', 'logeventid', 'ns']],
											['if' => ['field' => 'history', 'in' => implode(',', [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])], 'type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['itemid', 'clock', 'ns']],
											['else' => true, 'type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['itemid', 'clock', 'ns', 'value']]
			]],
			'search' =>					['type' => API_MULTIPLE, 'default' => null, 'rules' => [
											['if' => ['field' => 'history', 'in' => implode(',', [ITEM_VALUE_TYPE_LOG])], 'type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['source', 'value']],
											['if' => ['field' => 'history', 'in' => implode(',', [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])], 'type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => ['value']],
											['else' => true, 'type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'fields' => []]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_MULTIPLE, 'default' => API_OUTPUT_EXTEND, 'rules' => [
											['if' => ['field' => 'history', 'in' => implode(',', [ITEM_VALUE_TYPE_LOG])], 'type' => API_OUTPUT, 'in' => implode(',', ['itemid', 'clock', 'timestamp', 'source', 'severity', 'value', 'logeventid', 'ns'])],
											['else' => true, 'type' => API_OUTPUT, 'in' => implode(',', ['itemid', 'clock', 'value', 'ns'])]
			]],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'editable' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN || $options['hostids'] !== null) {
			$items = API::Item()->get([
				'output' => ['itemid'],
				'itemids' => $options['itemids'],
				'hostids' => $options['hostids'],
				'editable' => $options['editable'],
				'webitems' => true,
				'preservekeys' => true
			]);
			$options['itemids'] = array_keys($items);
		}

		$this->tableName = CHistoryManager::getTableName($options['history']);

		switch (CHistoryManager::getDataSourceType($options['history'])) {
			case ZBX_HISTORY_SOURCE_ELASTIC:
				$result = $this->getFromElasticsearch($options);
				break;

			default:
				if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
					$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
					$options['time_from'] = max($options['time_from'], time() - $hk_history + 1);
				}

				$result = $this->getFromSql($options);
				break;
		}

		if (!$options['countOutput'] && $options['history'] == ITEM_VALUE_TYPE_BINARY
				&& $this->outputIsRequested('value', $options['output'])) {
			foreach ($result as &$row) {
				$row['value'] = base64_encode($row['value']);
			}
			unset($row);
		}

		return $result;
	}

	/**
	 * SQL specific implementation of get.
	 *
	 * @see CHistory::get
	 */
	private function getFromSql($options) {
		$result = [];
		$sql_parts = [
			'select'	=> ['history' => 'h.itemid'],
			'from'		=> [
				'history' => $this->tableName.' h'
			],
			'where'		=> [],
			'group'		=> [],
			'order'		=> []
		];

		// itemids
		if ($options['itemids'] !== null) {
			$sql_parts['where']['itemid'] = dbConditionId('h.itemid', $options['itemids']);
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sql_parts['where']['clock_from'] = 'h.clock>='.$options['time_from'];
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sql_parts['where']['clock_till'] = 'h.clock<='.$options['time_till'];
		}

		// filter
		if ($options['filter'] !== null) {
			$this->dbFilter($sql_parts['from']['history'], $options, $sql_parts);
		}

		// search
		if ($options['search'] !== null) {
			zbx_db_search($sql_parts['from']['history'], $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName, $this->tableAlias(), $options, $sql_parts);

		$db_res = DBselect(self::createSelectQueryFromParts($sql_parts), $options['limit']);

		while ($data = DBfetch($db_res)) {
			if ($options['countOutput']) {
				$result = $data['rowscount'];
			}
			else {
				$result[] = $data;
			}
		}

		return $result;
	}

	/**
	 * Elasticsearch specific implementation of get.
	 *
	 * @see CHistory::get
	 */
	private function getFromElasticsearch($options) {
		$query = [];
		$schema = DB::getSchema($this->tableName);

		// itemids
		if ($options['itemids'] !== null) {
			$query['query']['bool']['must'][] = [
				'terms' => [
					'itemid' => $options['itemids']
				]
			];
		}

		// time_from
		if ($options['time_from'] !== null) {
			$query['query']['bool']['must'][] = [
				'range' => [
					'clock' => [
						'gte' => $options['time_from']
					]
				]
			];
		}

		// time_till
		if ($options['time_till'] !== null) {
			$query['query']['bool']['must'][] = [
				'range' => [
					'clock' => [
						'lte' => $options['time_till']
					]
				]
			];
		}

		// filter
		if ($options['filter'] !== null) {
			$query = CElasticsearchHelper::addFilter(DB::getSchema($this->tableName), $query, $options);
		}

		// search
		if ($options['search'] !== null) {
			$query = CElasticsearchHelper::addSearch($schema, $query, $options);
		}

		// output
		if ($options['countOutput'] === false && $options['output'] !== API_OUTPUT_EXTEND) {
			$query['_source'] = $options['output'];
		}

		// sorting
		if ($options['sortfield']) {
			$query = CElasticsearchHelper::addSort($query, $options);
		}

		// limit
		if ($options['limit'] !== null) {
			$query['size'] = $options['limit'];
		}

		$endpoints = CHistoryManager::getElasticsearchEndpoints($options['history']);
		if ($endpoints) {
			return CElasticsearchHelper::query('POST', reset($endpoints), $query);
		}

		return null;
	}

	/**
	 * Clear item history. Support web scenario history cleanup.
	 *
	 * @param array $itemids
	 *
	 * @return array
	 */
	public function clear(array $itemids): array {
		self::validateClear($itemids, $db_items);

		Manager::History()->deleteHistory(array_column($db_items, 'value_type', 'itemid'));

		self::addAuditLog(CAudit::ACTION_HISTORY_CLEAR, CAudit::RESOURCE_ITEM, $db_items);

		return ['itemids' => $itemids];
	}

	/**
	 * Validates the input parameters for the clear() method.
	 *
	 * @param array      $itemids
	 * @param array|null $db_items
	 *
	 * @throws APIException if the input is invalid
	 * @throws APIException if compression is enabled
	 */
	private static function validateClear(array $itemids, array &$db_items = null): void {
		global $DB;

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $itemids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($DB['TYPE'] == ZBX_DB_POSTGRESQL && CHousekeepingHelper::get(CHousekeepingHelper::COMPRESSION_STATUS)
				&& self::checkCompressionAvailability() === true) {
			self::exception(ZBX_API_ERROR_INTERNAL, _('History cleanup is not supported if compression is enabled'));
		}

		$db_items = API::Item()->get([
			'output' => ['itemid', 'value_type', 'name'],
			'itemids' => $itemids,
			'templated' => false,
			'webitems' => true,
			'editable' => true
		]);

		if (count($db_items) != count($itemids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	/**
	 * Returns true if database supports data compression. False otherwise.
	 */
	private static function checkCompressionAvailability(): bool {
		foreach (CSettingsHelper::getDbVersionStatus() as $dbversion) {
			if ($dbversion['database'] === ZBX_DB_EXTENSION_TIMESCALEDB) {
				return array_key_exists('compression_availability', $dbversion)
					&& (bool) $dbversion['compression_availability'];
			}
		}

		return false;
	}

	/**
	 * @param array $history
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function push(array $history): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'itemid' =>	['type' => API_ID],
			'host' =>	['type' => API_STRING_UTF8],
			'key' =>	['type' => API_STRING_UTF8],
			'value' =>	['type' => API_VALUE, 'flags' => API_REQUIRED],
			'clock' =>	['type' => API_TIMESTAMP],
			'ns' =>		['type' => API_INT32, 'in' => '0:999999999']
		]];

		if (!CApiInputValidator::validate($api_input_rules, $history, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SCRIPT_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$result = $zabbix_server->pushHistory($history, self::getAuthIdentifier());

		if ($result === false) {
			self::exception(ZBX_API_ERROR_INTERNAL, $zabbix_server->getError());
		}

		return [
			'response' => 'success',
			'data' => $result
		];
	}
}
