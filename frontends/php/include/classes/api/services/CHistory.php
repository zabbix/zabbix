<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * @param array  $options['itemids']
	 * @param bool   $options['editable']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array|int item data as array or false if error
	 */
	public function get($options = []) {
		$def_options = [
			'history'					=> ITEM_VALUE_TYPE_UINT64,
			'hostids'					=> null,
			'itemids'					=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			'time_from'					=> null,
			'time_till'					=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($def_options, $options);

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == self::$userData['type'] || $options['nopermissions']) {
		}
		else {
			$items = API::Item()->get([
				'itemids' => ($options['itemids'] === null) ? null : $options['itemids'],
				'output' => ['itemid'],
				'editable' => $options['editable'],
				'preservekeys' => true,
				'webitems' => true
			]);
			$options['itemids'] = array_keys($items);
		}

		if ($options['itemids'] !== null) {
			zbx_value2array($options['itemids']);
		}

		if ($options['hostids'] !== null) {
			$itemids = [];

			$hosts = API::Host()->get([
				'hostids' => $options['hostids'],
				'itemids' => $options['itemids'],
				'output' => [],
				'selectItems' => ['output' => 'itemid']
			]);

			foreach ($hosts as $host) {
				foreach ($host['items'] as $item) {
					$itemids[] = $item['itemid'];
				}
			}

			$options['itemids'] = $itemids;
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
		$sql_parts = [
			'select'	=> ['history' => 'h.itemid'],
			'from'		=> [],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		if (!$table_name = CHistoryManager::getTableName($options['history'])) {
			$table_name = 'history';
		}

		$sql_parts['from']['history'] = $table_name.' h';

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
		if (is_array($options['filter'])) {
			$this->dbFilter($sql_parts['from']['history'], $options, $sql_parts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search($sql_parts['from']['history'], $options, $sql_parts);
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			unset($sql_parts['select']['clock']);
			$sql_parts['select']['history'] = 'h.*';
		}

		// countOutput
		if ($options['countOutput']) {
			$options['sortfield'] = '';
			$sql_parts['select'] = ['count(DISTINCT h.hostid) as rowscount'];

			// groupCount
			if ($options['groupCount']) {
				foreach ($sql_parts['group'] as $key => $fields) {
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		$sql_parts = $this->applyQuerySortOptions($table_name, $this->tableAlias(), $options, $sql_parts);

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sql_parts['limit'] = $options['limit'];
		}

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_order = '';

		if ($sql_parts['select']) {
			$sql_select .= implode(',', $sql_parts['select']);
		}

		if ($sql_parts['from']) {
			$sql_from .= implode(',', $sql_parts['from']);
		}

		$sql_where = $sql_parts['where'] ? ' WHERE '.implode(' AND ', $sql_parts['where']) : '';

		if ($sql_parts['order']) {
			$sql_order .= ' ORDER BY '.implode(',', $sql_parts['order']);
		}

		$sql_limit = $sql_parts['limit'];
		$sql = 'SELECT '.$sql_select.
				' FROM '.$sql_from.
				$sql_where.
				$sql_order;
		$db_res = DBselect($sql, $sql_limit);

		while ($data = DBfetch($db_res)) {
			if ($options['countOutput']) {
				$result = $data;
			}
			else {
				$result[] = $data;
			}
		}

		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
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

		if (!$table_name = CHistoryManager::getTableName($options['history'])) {
			$table_name = 'history';
		}

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
		if (is_array($options['filter'])) {
			$query = CElasticsearchHelper::addFilter(DB::getSchema($table_name), $query, $options);
		}

		// search
		if (is_array($options['search'])) {
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
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$query['size'] = $options['limit'];
		}

		$endpoints = CHistoryManager::getElasticsearchEndpoints($options['history']);
		if ($endpoints) {
			return CElasticsearchHelper::query('POST', reset($endpoints), $query);
		}

		return null;
	}
}
