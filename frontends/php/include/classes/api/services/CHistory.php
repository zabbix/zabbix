<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Class containing methods for operations with histories.
 */
class CHistory extends CApiService {

	protected $tableName = 'history';
	protected $tableAlias = 'h';
	protected $sortColumns = ['itemid', 'clock'];

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
	 * @param string $options['sortorder']                     Order of sorting. If an array is passed, each value will
	 *                                                         be matched to the corresponding property given in the
	 *                                                         sortfield parameter.
	 * @param int    $options['limit']                         Limit the number of records returned.
	 * @param bool   $options['nopermissions']                 Select values without checking permissions of hosts.
	 * @param bool   $options['editable']                      If set to true return only objects that the user has
	 *                                                         write permissions to.
	 *
	 * @throws Exception
	 * @return array|int    Data array or number of rows.
	 */
	public function get($options = []) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filter and search properties.
			'history' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_TEXT]), 'default' => ITEM_VALUE_TYPE_UINT64],
			'hostids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'itemids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'time_from' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'time_till' =>				['type' => API_INT32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'itemid' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'clock' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'value' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'ns' =>						['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'value' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],

			// Output properties.
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['itemid', 'clock', 'value', 'ns']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],

			// Sort and limit properties.
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'default' => []],
			'sortorder' =>				['type' => API_STRING_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]), 'default' => null],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],

			// Flags properties.
			'nopermissions' =>			['type' => API_BOOLEAN, 'default' => false],
			'editable' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ((self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions'])
				|| $options['hostids'] !== null) {
			$items = API::Item()->get([
				'output' => ['itemid'],
				'itemids' => $options['itemids'],
				'hostids' => $options['hostids'],
				'nopermissions' => $options['nopermissions'],
				'editable' => $options['editable'],
				'webitems' => true,
				'preservekeys' => true
			]);
			$options['itemids'] = array_keys($items);
		}

		if ($options['itemids'] !== null) {
			zbx_value2array($options['itemids']);
		}

		switch (CHistoryManager::getDataSourceType($options['history'])) {
			case ZBX_HISTORY_SOURCE_ELASTIC:
				return $this->getFromElasticsearch($options);

			default:
				return $this->getFromSql($options);
		}
	}

	/**
	 * SQL specific implementation of get.
	 *
	 * @see CHistory::get
	 */
	private function getFromSql($options) {
		$result = [];
		$table_name = CHistoryManager::getTableName($options['history']);

		$sql_parts = [
			'select'	=> ['history' => 'h.itemid'],
			'from'		=> [
				'history' => $table_name.' h'
			],
			'where'		=> [],
			'group'		=> [],
			'order'		=> []
		];

		// itemids
		if ($options['itemids'] !== null) {
			$sql_parts['where']['itemid'] = dbConditionInt('h.itemid', $options['itemids']);
		}

		// time_from
		if ($options['time_from'] !== null) {
			$sql_parts['where']['clock_from'] = 'h.clock>='.zbx_dbstr($options['time_from']);
		}

		// time_till
		if ($options['time_till'] !== null) {
			$sql_parts['where']['clock_till'] = 'h.clock<='.zbx_dbstr($options['time_till']);
		}

		// filter
		if ($options['filter']) {
			$this->dbFilter($sql_parts['from']['history'], $options, $sql_parts);
		}

		// search
		if ($options['search']) {
			zbx_db_search($sql_parts['from']['history'], $options, $sql_parts);
		}

		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($table_name, $this->tableAlias(), $options, $sql_parts);

		$db_res = DBselect($this->createSelectQueryFromParts($sql_parts), $options['limit']);

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
		$table_name = CHistoryManager::getTableName($options['history']);
		$schema = DB::getSchema($table_name);

		// itemids
		if ($options['itemids'] !== null) {
			$query['query']['bool']['must'][] = [
				'terms' => [
					'itemid' => array_values($options['itemids'])
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
		if ($options['filter']) {
			$query = CElasticsearchHelper::addFilter(DB::getSchema($table_name), $query, $options);
		}

		// search
		if ($options['search']) {
			$query = CElasticsearchHelper::addSearch($schema, $query, $options);
		}

		// output
		if ($options['output'] != API_OUTPUT_EXTEND && $options['output'] != API_OUTPUT_COUNT) {
			$query['_source'] = $options['output'];
		}

		// sorting
		if ($this->sortColumns && $options['sortfield']) {
			$query = CElasticsearchHelper::addSort($this->sortColumns, $query, $options);
		}

		// limit
		if ($options['limit']) {
			$query['size'] = $options['limit'];
		}

		$endpoints = CHistoryManager::getElasticsearchEndpoints($options['history']);
		if ($endpoints) {
			return CElasticsearchHelper::query('POST', reset($endpoints), $query);
		}

		return null;
	}
}
