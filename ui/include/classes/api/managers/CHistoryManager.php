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
 * Class to perform low level history related actions.
 */
class CHistoryManager {

	private $primary_keys_enabled = false;

	/**
	 * Whether to enable optimizations that make use of PRIMARY KEY (itemid, clock, ns) on the history tables.
	 *
	 * @param bool $enabled
	 *
	 * @return CHistoryManager
	 */
	public function setPrimaryKeysEnabled(bool $enabled = true) {
		$this->primary_keys_enabled = $enabled;

		return $this;
	}

	/**
	 * Returns a subset of $items having history data within the $period of time.
	 *
	 * @param array $items   An array of items with the 'itemid' and 'value_type' properties.
	 * @param int   $period  The maximum period of time to search for history values within.
	 *
	 * @return array  An array with items IDs as keys and the original item data as values.
	 */
	public function getItemsHavingValues(array $items, $period = null) {
		$items = zbx_toHash($items, 'itemid');

		$results = [];
		$grouped_items = $this->getItemsGroupedByStorage($items);

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getLastValuesFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC], 1, $period);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			$results += $this->getItemsHavingValuesFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL], $period);
		}

		return array_intersect_key($items, $results);
	}

	/**
	 * SQL specific implementation of getItemsHavingValues.
	 *
	 * @see CHistoryManager::getItemsHavingValues
	 */
	private function getItemsHavingValuesFromSql(array $items, $period = null) {
		$results = [];

		if ($period) {
			$period = time() - $period;
		}

		$items = zbx_toHash($items, 'itemid');

		$itemids_by_type = [];

		foreach ($items as $itemid => $item) {
			$itemids_by_type[$item['value_type']][] = $itemid;
		}

		foreach ($itemids_by_type as $type => $type_itemids) {
			$type_results = DBfetchColumn(DBselect(
				'SELECT itemid'.
				' FROM '.self::getTableName($type).
				' WHERE '.dbConditionInt('itemid', $type_itemids).
					($period ? ' AND clock>'.$period : '').
				' GROUP BY itemid'
			), 'itemid');

			$results += array_intersect_key($items, array_flip($type_results));
		}

		return $results;
	}

	/**
	 * Returns the last $limit history objects for the given items.
	 *
	 * @param array $items   An array of items with the 'itemid' and 'value_type' properties.
	 * @param int   $limit   Max object count to be returned.
	 * @param int   $period  The maximum period to retrieve data for.
	 *
	 * @return array  An array with items IDs as keys and arrays of history objects as values.
	 */
	public function getLastValues(array $items, $limit = 1, $period = null) {
		$results = [];
		$grouped_items = $this->getItemsGroupedByStorage($items);

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getLastValuesFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC], $limit,
					$period
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			if ($this->primary_keys_enabled) {
				$results += $this->getLastValuesFromSqlWithPk($grouped_items[ZBX_HISTORY_SOURCE_SQL], $limit, $period);
			}
			else {
				$results += $this->getLastValuesFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL], $limit, $period);
			}
		}

		return $results;
	}

	/**
	 * Elasticsearch specific implementation of getLastValues.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	private function getLastValuesFromElasticsearch($items, $limit, $period) {
		$terms = [];
		$results = [];
		$filter = [];

		foreach ($items as $item) {
			$terms[$item['value_type']][] = $item['itemid'];
		}

		$query = [
			'aggs' => [
				'group_by_itemid' => [
					'terms' => [
						'field' => 'itemid'
					],
					'aggs' => [
						'group_by_docs' => [
							'top_hits' => [
								'size' => $limit,
								'sort' => [
									'clock' => ZBX_SORT_DOWN
								]
							]
						]
					]
				]
			],
			'size' => 0
		];

		if ($period) {
			$filter[] = [
				'range' => [
					'clock' => [
						'gt' => (time() - $period)
					]
				]
			];
		}

		foreach (self::getElasticsearchEndpoints(array_keys($terms)) as $type => $endpoint) {
			$query['query']['bool']['must'] = array_merge([[
				'terms' => [
					'itemid' => $terms[$type]
				]
			]], $filter);
			// Assure that aggregations for all terms are returned.
			$query['aggs']['group_by_itemid']['terms']['size'] = count($terms[$type]);
			$data = CElasticsearchHelper::query('POST', $endpoint, $query);

			if (!is_array($data) || !array_key_exists('group_by_itemid', $data)
					|| !array_key_exists('buckets', $data['group_by_itemid'])
					|| !is_array($data['group_by_itemid']['buckets'])) {
				continue;
			}

			foreach ($data['group_by_itemid']['buckets'] as $item) {
				if (!is_array($item['group_by_docs']) || !array_key_exists('hits', $item['group_by_docs'])
						|| !is_array($item['group_by_docs']['hits'])
						|| !array_key_exists('hits', $item['group_by_docs']['hits'])
						|| !is_array($item['group_by_docs']['hits']['hits'])) {
					continue;
				}

				foreach ($item['group_by_docs']['hits']['hits'] as $row) {
					if (!array_key_exists('_source', $row) || !is_array($row['_source'])) {
						continue;
					}

					$results[$item['key']][] = $row['_source'];
				}
			}
		}

		return $results;
	}

	/**
	 * SQL specific implementation of getLastValues that makes use of primary key existence in history tables.
	 *
	 * @see CHistoryManager::getLastValues
	 * @return array  Of itemid => [up to $limit values].
	 */
	private function getLastValuesFromSqlWithPk(array $items, int $limit, ?int $period): array {
		$results = [];

		if ($period) {
			$period = time() - $period;
		}

		$items_by_type = [];

		foreach ($items as $key => $item) {
			$value_type = $item['value_type'];

			if (!array_key_exists($value_type, $items_by_type)) {
				$items_by_type[$value_type] = [];
			}

			$items_by_type[$value_type][] = $item;
			unset($items[$key]);
		}

		if ($limit == 1) {
			foreach ($items_by_type as $value_type => $items) {
				$history_table = self::getTableName($value_type);

				$max_clock_per_item = DBselect(
					'SELECT h.itemid, MAX(h.clock) AS clock'.
					' FROM '.$history_table.' h'.
					' WHERE '.dbConditionId('h.itemid', array_column($items, 'itemid')).
						($period ? ' AND h.clock > '.$period : '').
					' GROUP BY h.itemid'
				);

				while ($itemid_clock = DBfetch($max_clock_per_item, false)) {
					$db_value = DBfetchArray(DBselect(
						'SELECT *'.
						' FROM '.$history_table.' h'.
						' WHERE h.itemid='.zbx_dbstr($itemid_clock['itemid']).
							' AND h.clock='.zbx_dbstr($itemid_clock['clock']).
						' ORDER BY h.ns DESC',
						$limit
					));

					if ($db_value) {
						$results[$itemid_clock['itemid']] = $db_value;
					}
				}
			}
		}
		else {
			foreach ($items_by_type as $value_type => $items) {
				$history_table = self::getTableName($value_type);

				foreach ($items as $item) {
					$db_values = DBselect('SELECT *'.
						' FROM '.$history_table.' h'.
						' WHERE h.itemid='.zbx_dbstr($item['itemid']).
							($period ? ' AND h.clock > '.$period : '').
						' ORDER BY h.clock DESC, h.ns DESC',
						$limit
					);

					$values = [];

					while ($db_value = DBfetch($db_values, false)) {
						$values[] = $db_value;
					}

					if ($values) {
						$results[$item['itemid']] = $values;
					}
				}
			}
		}

		return $results;
	}

	/**
	 * SQL specific implementation of getLastValues.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	private function getLastValuesFromSql($items, $limit, $period) {
		$results = [];

		if ($period) {
			$period = time() - $period;
		}

		if ($limit == 1) {
			foreach ($items as $item) {
				// Executing two subsequent queries individually for the sake of performance.

				$clock_max = DBfetch(DBselect(
					'SELECT MAX(h.clock)'.
					' FROM '.self::getTableName($item['value_type']).' h'.
					' WHERE h.itemid='.zbx_dbstr($item['itemid']).
						($period ? ' AND h.clock>'.$period : '')
				), false);

				if ($clock_max) {
					$clock_max = reset($clock_max);

					if ($clock_max !== null) {
						$values = DBfetchArray(DBselect(
							'SELECT *'.
							' FROM '.self::getTableName($item['value_type']).' h'.
							' WHERE h.itemid='.zbx_dbstr($item['itemid']).
								' AND h.clock='.zbx_dbstr($clock_max).
							' ORDER BY h.ns DESC',
							$limit
						));

						if ($values) {
							$results[$item['itemid']] = $values;
						}
					}
				}
			}
		}
		else {
			foreach ($items as $item) {
				// Cannot order by h.ns directly here due to performance issues.
				$values = DBfetchArray(DBselect(
					'SELECT *'.
					' FROM '.self::getTableName($item['value_type']).' h'.
					' WHERE h.itemid='.zbx_dbstr($item['itemid']).
						($period ? ' AND h.clock>'.$period : '').
					' ORDER BY h.clock DESC',
					$limit + 1
				));

				if ($values) {
					$count = count($values);
					$clock = $values[$count - 1]['clock'];

					if ($count == $limit + 1 && $values[$count - 2]['clock'] == $clock) {
						/*
						 * The last selected entries having the same clock means the selection (not just the order)
						 * of the last entries is possibly wrong due to unordered by nanoseconds.
						 */

						do {
							unset($values[--$count]);
						} while ($values && $values[$count - 1]['clock'] == $clock);

						$db_values = DBselect(
							'SELECT *'.
							' FROM '.self::getTableName($item['value_type']).' h'.
							' WHERE h.itemid='.zbx_dbstr($item['itemid']).
								' AND h.clock='.$clock.
							' ORDER BY h.ns DESC',
							$limit - $count
						);

						while ($db_value = DBfetch($db_values)) {
							$values[] = $db_value;
							$count++;
						}
					}

					CArrayHelper::sort($values, [
						['field' => 'clock', 'order' => ZBX_SORT_DOWN],
						['field' => 'ns', 'order' => ZBX_SORT_DOWN]
					]);

					$values = array_values($values);

					while ($count > $limit) {
						unset($values[--$count]);
					}

					$results[$item['itemid']] = $values;
				}
			}
		}

		return $results;
	}

	/**
	 * Returns the history data of the item at the given time. If no data exists at the given time, the function will
	 * return the previous data.
	 *
	 * The $item parameter must have the value_type and itemid properties set.
	 *
	 * @param array  $item
	 * @param string $item['itemid']
	 * @param int    $item['value_type']
	 * @param int    $clock
	 * @param int    $ns
	 *
	 * @return array|null  Item data at specified time of first data before specified time. null if data is not found.
	 */
	public function getValueAt(array $item, $clock, $ns) {
		switch (self::getDataSourceType($item['value_type'])) {
			case ZBX_HISTORY_SOURCE_ELASTIC:
				return $this->getValueAtFromElasticsearch($item, $clock, $ns);

			default:
				return $this->primary_keys_enabled
					? $this->getValueAtFromSqlWithPk($item, $clock, $ns)
					: $this->getValueAtFromSql($item, $clock, $ns);
		}
	}

	/**
	 * Elasticsearch specific implementation of getValueAt.
	 *
	 * @see CHistoryManager::getValueAt
	 */
	private function getValueAtFromElasticsearch(array $item, $clock, $ns) {
		$query = [
			'sort' => [
				'clock' => ZBX_SORT_DOWN,
				'ns' => ZBX_SORT_DOWN
			],
			'size' => 1
		];

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$filters = [
			[
				[
					'term' => [
						'itemid' => $item['itemid']
					]
				],
				[
					'term' => [
						'clock' => $clock
					]
				],
				[
					'range' => [
						'ns' => [
							'lte' => $ns
						]
					]
				]
			],
			[
				[
					'term' => [
						'itemid' => $item['itemid']
					]
				],
				[
					'range' => [
						'clock' => [
							'lt' => $clock
						] + ($history_period ? ['gte' => $clock - $history_period] : [])
					]
				]
			]
		];

		foreach ($filters as $filter) {
			$query['query']['bool']['must'] = $filter;
			$endpoints = self::getElasticsearchEndpoints($item['value_type']);

			if (count($endpoints) !== 1) {
				break;
			}

			$result = CElasticsearchHelper::query('POST', reset($endpoints), $query);

			if (count($result) === 1 && is_array($result[0]) && array_key_exists('value', $result[0])) {
				return $result[0];
			}
		}

		return null;
	}

	/**
	 * Implementation that uses existence of primary key in history tables.
	 * @see CHistoryManager->getValueAtFromSql()
	 *
	 * @param string $item['itemid']
	 * @param int    $item['value_type']
	 * @param int    $clock
	 * @param int    $ns
	 *
	 * @return array|null  Item data at specified time of first data before specified time. null if data is not found.
	 */
	private function getValueAtFromSqlWithPk(array $item, $clock, $ns): ?array {
		$history_table = self::getTableName($item['value_type']);

		$sql = 'SELECT *'.
			' FROM '.$history_table.
			' WHERE itemid='.zbx_dbstr($item['itemid']).
				' AND clock='.zbx_dbstr($clock).
				' AND ns<='.zbx_dbstr($ns).
			' ORDER BY ns DESC';

		if (($row = DBfetch(DBselect($sql, 1))) !== false) {
			return $row;
		}

		$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
		$sql = 'SELECT *'.
			' FROM '.$history_table.
			' WHERE itemid='.zbx_dbstr($item['itemid']).
				' AND clock<'.zbx_dbstr($clock).
				($history_period ? ' AND clock >= '.zbx_dbstr($clock - $history_period) : '').
			' ORDER BY clock DESC, ns DESC';

		if (($row = DBfetch(DBselect($sql, 1))) !== false) {
			return $row;
		}

		return null;
	}

	/**
	 * SQL specific implementation of getValueAt.
	 *
	 * @see CHistoryManager::getValueAt
	 */
	private function getValueAtFromSql(array $item, $clock, $ns) {
		$result = null;
		$table = self::getTableName($item['value_type']);

		$sql = 'SELECT *'.
				' FROM '.$table.
				' WHERE itemid='.zbx_dbstr($item['itemid']).
					' AND clock='.zbx_dbstr($clock).
					' AND ns='.zbx_dbstr($ns);

		if (($row = DBfetch(DBselect($sql, 1))) !== false) {
			$result = $row;
		}

		if ($result !== null) {
			return $result;
		}

		$max_clock = 0;
		$sql = 'SELECT DISTINCT clock'.
				' FROM '.$table.
				' WHERE itemid='.zbx_dbstr($item['itemid']).
					' AND clock='.zbx_dbstr($clock).
					' AND ns<'.zbx_dbstr($ns);

		if (($row = DBfetch(DBselect($sql))) !== false) {
			$max_clock = $row['clock'];
		}

		if ($max_clock == 0) {
			$history_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));
			$sql = 'SELECT MAX(clock) AS clock'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock<'.zbx_dbstr($clock).
						($history_period ? ' AND clock>='.zbx_dbstr($clock - $history_period) : '');

			if (($row = DBfetch(DBselect($sql))) !== false) {
				$max_clock = $row['clock'];
			}
		}

		if ($max_clock == 0) {
			return $result;
		}

		if ($clock == $max_clock) {
			$sql = 'SELECT *'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock='.zbx_dbstr($clock).
						' AND ns<'.zbx_dbstr($ns);
		}
		else {
			$sql = 'SELECT *'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock='.zbx_dbstr($max_clock).
					' ORDER BY itemid,clock desc,ns desc';
		}

		if (($row = DBfetch(DBselect($sql, 1))) !== false) {
			$result = $row;
		}

		return $result;
	}

	/**
	 * Returns history value aggregation.
	 *
	 * The $item parameter must have the value_type, itemid and source properties set.
	 *
	 * @param array  $items      Items to get aggregated values for.
	 * @param int    $time_from  Minimal timestamp (seconds) to get data from.
	 * @param int    $time_to    Maximum timestamp (seconds) to get data from.
	 * @param string $function   Function for data aggregation.
	 * @param string $interval   Aggregation interval in seconds.
	 *
	 * @return array  History value aggregation.
	 */
	public function getAggregationByInterval(array $items, $time_from, $time_to, $function, $interval) {
		$grouped_items = $this->getItemsGroupedByStorage($items);

		$results = [];
		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results = $this->getAggregationByIntervalFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC],
				$time_from, $time_to, $function, $interval
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			$results += $this->getAggregationByIntervalFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL],
				$time_from, $time_to, $function, $interval
			);
		}

		return $results;
	}

	/**
	 * Elasticsearch specific implementation of getAggregationByInterval.
	 *
	 * @see CHistoryManager::getAggregationByInterval
	 */
	private function getAggregationByIntervalFromElasticsearch(array $items, $time_from, $time_to, $function,
			$interval) {
		$terms = [];

		foreach ($items as $item) {
			$terms[$item['value_type']][] = $item['itemid'];
		}

		$aggs = ['clock' => ['max' => ['field' => 'clock']]];

		switch ($function) {
			case AGGREGATE_MIN:
				$aggs['value'] = ['min' => ['field' => 'value']];
				break;
			case AGGREGATE_MAX:
				$aggs['value'] = ['max' => ['field' => 'value']];
				break;
			case AGGREGATE_AVG:
				$aggs['value'] = ['avg' => ['field' => 'value']];
				break;
			case AGGREGATE_SUM:
				$aggs['value'] = ['sum' => ['field' => 'value']];
				break;
			case AGGREGATE_FIRST:
				$aggs['value'] = ['top_hits' => ['size' => 1, 'sort' => ['clock' => ['order' => 'asc']]]];
				$aggs['clock'] = ['min' => ['field' => 'clock']];
				break;
			case AGGREGATE_LAST:
				$aggs['value'] = ['top_hits' => ['size' => 1, 'sort' => ['clock' => ['order' => 'desc']]]];
				break;
		}

		$query = [
			'aggs' => [
				'group_by_itemid' => [
					'terms' => [
						// Assure that aggregations for all terms are returned.
						'size' => count($items),
						'field' => 'itemid'
					]
				]
			],
			'size' => 0
		];

		// Clock value is divided by 1000 as it is stored as milliseconds.
		$formula = '((doc[\'clock\'].date.getMillis()/1000) - ((doc[\'clock\'].date.getMillis()/1000)%params.interval))';

		$query['aggs']['group_by_itemid']['aggs'] = [
			'group_by_script' => [
				'terms' => [
					'size' => (($time_to - $time_from) / $interval) + 1,
					'script' => [
						'inline' => $formula,
						'params' => [
							'interval' => $interval
						]
					]
				],
				'aggs' => $aggs
			]
		];

		$results = [];
		foreach (self::getElasticsearchEndpoints(array_keys($terms)) as $type => $endpoint) {
			$query['query']['bool']['must'] = [
				[
					'terms' => [
						'itemid' => $terms[$type]
					]
				],
				[
					'range' => [
						'clock' => [
							'gte' => $time_from,
							'lte' => $time_to
						]
					]
				]
			];

			$data = CElasticsearchHelper::query('POST', $endpoint, $query);

			foreach ($data['group_by_itemid']['buckets'] as $item) {
				if (!is_array($item['group_by_script']) || !array_key_exists('buckets', $item['group_by_script'])
					|| !is_array($item['group_by_script']['buckets'])) {
					continue;
				}

				foreach ($item['group_by_script']['buckets'] as $point) {
					$row = [
						'itemid' => $item['key'],
						'tick' => (int)$point['key'],
						'count' => $point['doc_count'],
						'clock' => (int)$point['clock']['value_as_string']
					];

					if ($function == AGGREGATE_FIRST || $function == AGGREGATE_LAST) {
						$row['value'] = $point['value']['hits']['hits'][0]['_source']['value'];
					}
					else {
						$row['value'] = array_key_exists('value', $point) ? $point['value']['value'] : null;
					}

					$results[$item['key']]['data'][] = $row;
				}

				if (array_key_exists($item['key'], $results)) {
					$results[$item['key']]['source'] = 'history';
				}
			}
		}

		return $results;
	}

	/**
	 * SQL specific implementation of getAggregationByInterval.
	 *
	 * @see CHistoryManager::getAggregationByInterval
	 */
	private function getAggregationByIntervalFromSql(array $items, $time_from, $time_to, $function, $interval) {
		$items_by_table = [];
		foreach ($items as $item) {
			$items_by_table[$item['value_type']][$item['source']][] = $item['itemid'];
		}

		$result = [];

		foreach ($items_by_table as $value_type => $items_by_source) {
			foreach ($items_by_source as $source => $itemids) {
				$sql_select = ['itemid'];
				$sql_group_by = ['itemid'];

				$calc_field = zbx_dbcast_2bigint('clock').'-'.zbx_sql_mod(zbx_dbcast_2bigint('clock'), $interval);
				$sql_select[] = $calc_field.' AS tick';
				$sql_group_by[] = $calc_field;

				if ($source === 'history') {
					switch ($function) {
						case AGGREGATE_MIN:
							$sql_select[] = 'MIN(value) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_MAX:
							$sql_select[] = 'MAX(value) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_AVG:
							$sql_select[] = 'AVG(value) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_COUNT:
							$sql_select[] = 'COUNT(*) AS count, MAX(clock) AS clock';
							break;
						case AGGREGATE_SUM:
							$sql_select[] = 'SUM(value) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_FIRST:
							$sql_select[] = 'MIN(clock) AS clock';
							break;
						case AGGREGATE_LAST:
							$sql_select[] = 'MAX(clock) AS clock';
							break;
					}
					$sql_from = ($value_type == ITEM_VALUE_TYPE_UINT64) ? 'history_uint' : 'history';
				}
				else {
					switch ($function) {
						case AGGREGATE_MIN:
							$sql_select[] = 'MIN(value_min) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_MAX:
							$sql_select[] = 'MAX(value_max) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_AVG:
							$sql_select[] = 'AVG(value_avg) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_COUNT:
							$sql_select[] = 'SUM(num) AS count, MAX(clock) AS clock';
							break;
						case AGGREGATE_SUM:
							$sql_select[] = 'SUM(value_avg * num) AS value, MAX(clock) AS clock';
							break;
						case AGGREGATE_FIRST:
							$sql_select[] = 'MIN(clock) AS clock';
							break;
						case AGGREGATE_LAST:
							$sql_select[] = 'MAX(clock) AS clock';
							break;
					}
					$sql_from = ($value_type == ITEM_VALUE_TYPE_UINT64) ? 'trends_uint' : 'trends';
				}

				$sql = 'SELECT '.implode(', ', $sql_select).
					' FROM '.$sql_from.
					' WHERE '.dbConditionInt('itemid', $itemids).
					' AND clock >= '.zbx_dbstr($time_from).
					' AND clock <= '.zbx_dbstr($time_to).
					' GROUP BY '.implode(', ', $sql_group_by);

				if ($function == AGGREGATE_FIRST || $function == AGGREGATE_LAST) {
					$sql = 'SELECT DISTINCT h.itemid, h.'.($source === 'history' ? 'value' : 'value_avg').' AS value, h.clock, hi.tick'.
						' FROM '.$sql_from.' h'.
						' JOIN('.$sql.') hi ON h.itemid = hi.itemid AND h.clock = hi.clock';
				}

				$sql_result = DBselect($sql);

				while (($row = DBfetch($sql_result)) !== false) {
					$result[$row['itemid']]['source'] = $source;
					$result[$row['itemid']]['data'][] = $row;
				}
			}
		}

		return $result;
	}

	/**
	 * Returns history value aggregation for graphs.
	 *
	 * The $item parameter must have the value_type, itemid and source properties set.
	 *
	 * @param array $items      Items to get aggregated values for.
	 * @param int   $time_from  Minimal timestamp (seconds) to get data from.
	 * @param int   $time_to    Maximum timestamp (seconds) to get data from.
	 * @param int   $width      Graph width in pixels (is not required for pie charts).
	 *
	 * @return array  History value aggregation for graphs.
	 */
	public function getGraphAggregationByWidth(array $items, $time_from, $time_to, $width = null) {
		$grouped_items = $this->getItemsGroupedByStorage($items);

		$results = [];
		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getGraphAggregationByWidthFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC],
					$time_from, $time_to, $width
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			$results += $this->getGraphAggregationByWidthFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL],
				$time_from, $time_to, $width
			);
		}

		return $results;
	}

	/**
	 * Elasticsearch specific implementation of getGraphAggregationByWidth.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	private function getGraphAggregationByWidthFromElasticsearch(array $items, $time_from, $time_to, $width) {
		$terms = [];

		foreach ($items as $item) {
			$terms[$item['value_type']][] = $item['itemid'];
		}

		$aggs = [
			'max_value' => [
				'max' => [
					'field' => 'value'
				]
			],
			'avg_value' => [
				'avg' => [
					'field' => 'value'
				]
			],
			'min_value' => [
				'min' => [
					'field' => 'value'
				]
			],
			'max_clock' => [
				'max' => [
					'field' => 'clock'
				]
			]
		];

		$query = [
			'aggs' => [
				'group_by_itemid' => [
					'terms' => [
						// Assure that aggregations for all terms are returned.
						'size' => count($items),
						'field' => 'itemid'
					]
				]
			],
			'query' => [
				'bool' => [
					'must' => [
						[
							'terms' => [
								'itemid' => $terms
							]
						],
						[
							'range' => [
								'clock' => [
									'gte' => $time_from,
									'lte' => $time_to
								]
							]
						]
					]
				]
			],
			'size' => 0
		];

		if ($width !== null) {
			$size = $time_to - $time_from;
			$delta = $size - $time_from % $size;

			// Additional grouping for line graphs.
			$aggs['max_clock'] = [
				'max' => [
					'field' => 'clock'
				]
			];

			// Clock value is divided by 1000 as it is stored as milliseconds.
			$formula = 'Math.floor((params.width*((doc[\'clock\'].value.getMillis()/1000+params.delta)%params.size))'.
					'/params.size)';

			$script = [
				'inline' => $formula,
				'params' => [
					'width' => (int)$width,
					'delta' => $delta,
					'size' => $size
				]
			];
			$aggs = [
				'group_by_script' => [
					'terms' => [
						'size' => $width,
						'script' => $script
					],
					'aggs' => $aggs
				]
			];
		}

		$query['aggs']['group_by_itemid']['aggs'] = $aggs;

		$results = [];

		foreach (self::getElasticsearchEndpoints(array_keys($terms)) as $type => $endpoint) {
			$query['query']['bool']['must'] = [
				[
					'terms' => [
						'itemid' => $terms[$type]
					]
				],
				[
					'range' => [
						'clock' => [
							'gte' => $time_from,
							'lte' => $time_to
						]
					]
				]
			];

			$data = CElasticsearchHelper::query('POST', $endpoint, $query);

			if (array_key_exists('group_by_itemid', $data)) {
				if ($width !== null) {
					foreach ($data['group_by_itemid']['buckets'] as $item) {
						if (!is_array($item['group_by_script']) || !array_key_exists('buckets', $item['group_by_script'])
								|| !is_array($item['group_by_script']['buckets'])) {
							continue;
						}

						$results[$item['key']]['source'] = 'history';
						foreach ($item['group_by_script']['buckets'] as $point) {
							$results[$item['key']]['data'][] = [
								'itemid' => $item['key'],
								'i' => $point['key'],
								'count' => $point['doc_count'],
								'min' => $point['min_value']['value'],
								'avg' => $point['avg_value']['value'],
								'max' => $point['max_value']['value'],
								// Field value_as_string is used to get value as seconds instead of milliseconds.
								'clock' => $point['max_clock']['value_as_string']
							];
						}
					}
				}
				else {
					foreach ($data['group_by_itemid']['buckets'] as $item) {
						$results[$item['key']]['source'] = 'history';
						$results[$item['key']]['data'][] = [
							'itemid' => $item['key'],
							'min' => $item['min_value']['value'],
							'avg' => $item['avg_value']['value'],
							'max' => $item['max_value']['value'],
							// Field value_as_string is used to get value as seconds instead of milliseconds.
							'clock' => $item['max_clock']['value_as_string']
						];
					}
				}
			}
		}

		return $results;
	}

	/**
	 * SQL specific implementation of getGraphAggregationByWidth.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	private function getGraphAggregationByWidthFromSql(array $items, $time_from, $time_to, $width) {
		$group_by = 'itemid';
		$sql_select_extra = '';

		if ($width !== null) {
			$size = $time_to - $time_from;
			$delta = $size - $time_from % $size;

			// Required for 'group by' support of Oracle.
			$calc_field = 'round('.$width.'*'.zbx_sql_mod(zbx_dbcast_2bigint('clock').'+'.$delta, $size)
					.'/('.$size.'),0)';

			$sql_select_extra = ','.$calc_field.' AS i';
			$group_by .= ','.$calc_field;
		}

		$results = [];

		foreach ($items as $item) {
			if ($item['source'] === 'history') {
				$sql_select = 'COUNT(*) AS count,AVG(value) AS avg,MIN(value) AS min,MAX(value) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'history_uint' : 'history';
			}
			else {
				$sql_select = 'SUM(num) AS count,AVG(value_avg) AS avg,MIN(value_min) AS min,MAX(value_max) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'trends_uint' : 'trends';
			}

			$result = DBselect(
				'SELECT itemid,'.$sql_select.$sql_select_extra.',MAX(clock) AS clock'.
				' FROM '.$sql_from.
				' WHERE itemid='.zbx_dbstr($item['itemid']).
					' AND clock>='.zbx_dbstr($time_from).
					' AND clock<='.zbx_dbstr($time_to).
				' GROUP BY '.$group_by
			);

			$data = [];
			while (($row = DBfetch($result)) !== false) {
				$data[] = $row;
			}

			$results[$item['itemid']]['source'] = $item['source'];
			$results[$item['itemid']]['data'] = $data;
		}

		return $results;
	}

	/**
	 * Returns aggregated history value.
	 *
	 * The $item parameter must have the value_type and itemid properties set.
	 *
	 * @param array  $item         Item to get aggregated value for.
	 * @param string $aggregation  Aggregation to be applied (min / max / avg).
	 * @param int    $time_from    Timestamp (seconds).
	 *
	 * @return string  Aggregated history value.
	 */
	public function getAggregatedValue(array $item, $aggregation, $time_from) {
		switch (self::getDataSourceType($item['value_type'])) {
			case ZBX_HISTORY_SOURCE_ELASTIC:
				return $this->getAggregatedValueFromElasticsearch($item, $aggregation, $time_from);

			default:
				return $this->getAggregatedValueFromSql($item, $aggregation, $time_from);
		}
	}

	/**
	 * Elasticsearch specific implementation of getAggregatedValue.
	 *
	 * @see CHistoryManager::getAggregatedValue
	 */
	private function getAggregatedValueFromElasticsearch(array $item, $aggregation, $time_from) {
		$query = [
			'aggs' => [
				$aggregation.'_value' => [
					$aggregation => [
						'field' => 'value'
					]
				]
			],
			'query' => [
				'bool' => [
					'must' => [
						[
							'term' => [
								'itemid' => $item['itemid']
							]
						],
						[
							'range' => [
								'clock' => [
									'gte' => $time_from
								]
							]
						]
					]
				]
			],
			'size' => 0
		];

		$endpoints = self::getElasticsearchEndpoints($item['value_type']);

		if ($endpoints) {
			$data = CElasticsearchHelper::query('POST', reset($endpoints), $query);

			if (array_key_exists($aggregation.'_value', $data)
					&& array_key_exists('value', $data[$aggregation.'_value'])) {
				return $data[$aggregation.'_value']['value'];
			}
		}

		return null;
	}

	/**
	 * SQL specific implementation of getAggregatedValue.
	 *
	 * @see CHistoryManager::getAggregatedValue
	 */
	private function getAggregatedValueFromSql(array $item, $aggregation, $time_from) {
		$result = DBselect(
			'SELECT '.$aggregation.'(value) AS value'.
			' FROM '.self::getTableName($item['value_type']).
			' WHERE clock>'.$time_from.
			' AND itemid='.zbx_dbstr($item['itemid']).
			' HAVING COUNT(*)>0' // Necessary because DBselect() return 0 if empty data set, for graph templates.
		);

		if (($row = DBfetch($result)) !== false) {
			return $row['value'];
		}

		return null;
	}

	/**
	 * Clear item history and trends by provided item IDs. History is deleted from both SQL and Elasticsearch.
	 *
	 * @param array $items  Key - itemid, value - value_type.
	 *
	 * @return bool
	 */
	public function deleteHistory(array $items) {
		return $this->deleteHistoryFromSql($items) && $this->deleteHistoryFromElasticsearch(array_keys($items));
	}

	/**
	 * Elasticsearch specific implementation of deleteHistory.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	private function deleteHistoryFromElasticsearch(array $itemids) {
		global $HISTORY;

		if (is_array($HISTORY) && array_key_exists('types', $HISTORY) && is_array($HISTORY['types'])
				&& count($HISTORY['types']) > 0) {

			$query = [
				'query' => [
					'terms' => [
						'itemid' => array_values($itemids)
					]
				]
			];

			$types = [];
			foreach ($HISTORY['types'] as $type) {
				$types[] = self::getTypeIdByTypeName($type);
			}

			foreach (self::getElasticsearchEndpoints($types, '_delete_by_query') as $endpoint) {
				if (!CElasticsearchHelper::query('POST', $endpoint, $query)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * SQL specific implementation of deleteHistory.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	private function deleteHistoryFromSql(array $items) {
		global $DB;

		$item_tables = array_map('self::getTableName', array_unique($items));
		$table_names = array_flip(self::getTableName());

		if (in_array(ITEM_VALUE_TYPE_UINT64, $items)) {
			$item_tables[] = 'trends_uint';
			$table_names['trends_uint'] = ITEM_VALUE_TYPE_UINT64;
		}

		if (in_array(ITEM_VALUE_TYPE_FLOAT, $items)) {
			$item_tables[] = 'trends';
			$table_names['trends'] = ITEM_VALUE_TYPE_FLOAT;
		}

		if ($DB['TYPE'] == ZBX_DB_POSTGRESQL && PostgresqlDbBackend::isCompressed($item_tables)) {
			error(_('Some of the history for this item may be compressed, deletion is not available.'));

			return false;
		}

		foreach ($item_tables as $table_name) {
			$itemids = array_keys(array_intersect($items, [(string) $table_names[$table_name]]));

			if (!DBexecute('DELETE FROM '.$table_name.' WHERE '.dbConditionInt('itemid', $itemids))) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get type name by value type id.
	 *
	 * @param int $value_type  Value type id.
	 *
	 * @return string  Value type name.
	 */
	public static function getTypeNameByTypeId($value_type) {
		$mapping = [
			ITEM_VALUE_TYPE_FLOAT => 'dbl',
			ITEM_VALUE_TYPE_STR => 'str',
			ITEM_VALUE_TYPE_LOG => 'log',
			ITEM_VALUE_TYPE_UINT64 => 'uint',
			ITEM_VALUE_TYPE_TEXT => 'text'
		];

		if (array_key_exists($value_type, $mapping)) {
			return $mapping[$value_type];
		}

		// Fallback to float.
		return $mapping[ITEM_VALUE_TYPE_FLOAT];
	}

	/**
	 * Get type id by value type name.
	 *
	 * @param int $type_name  Value type name.
	 *
	 * @return int  Value type id.
	 */
	public static function getTypeIdByTypeName($type_name) {
		$mapping = [
			'dbl' => ITEM_VALUE_TYPE_FLOAT,
			'str' => ITEM_VALUE_TYPE_STR,
			'log' => ITEM_VALUE_TYPE_LOG,
			'uint' => ITEM_VALUE_TYPE_UINT64,
			'text' => ITEM_VALUE_TYPE_TEXT
		];

		if (array_key_exists($type_name, $mapping)) {
			return $mapping[$type_name];
		}

		// Fallback to float.
		return ITEM_VALUE_TYPE_FLOAT;
	}

	/**
	 * Get data source (SQL or Elasticsearch) type based on value type id.
	 *
	 * @param int $value_type  Value type id.
	 *
	 * @return string  Data source type.
	 */
	public static function getDataSourceType($value_type) {
		static $cache = [];

		if (!array_key_exists($value_type, $cache)) {
			global $HISTORY;

			if (is_array($HISTORY) && array_key_exists('types', $HISTORY) && is_array($HISTORY['types'])) {
				$cache[$value_type] = in_array(self::getTypeNameByTypeId($value_type), $HISTORY['types'])
						? ZBX_HISTORY_SOURCE_ELASTIC : ZBX_HISTORY_SOURCE_SQL;
			}
			else {
				// SQL is a fallback data source.
				$cache[$value_type] = ZBX_HISTORY_SOURCE_SQL;
			}
		}

		return $cache[$value_type];
	}

	private static function getElasticsearchUrl($value_name) {
		static $urls = [];
		static $invalid = [];

		// Additional check to limit error count produced by invalid configuration.
		if (array_key_exists($value_name, $invalid)) {
			return null;
		}

		if (!array_key_exists($value_name, $urls)) {
			global $HISTORY;

			if (!is_array($HISTORY) || !array_key_exists('url', $HISTORY)) {
				$invalid[$value_name] = true;
				error(_s('Elasticsearch URL is not set for type: %1$s.', $value_name));

				return null;
			}

			$url = $HISTORY['url'];
			if (is_array($url)) {
				if (!array_key_exists($value_name, $url)) {
					$invalid[$value_name] = true;
					error(_s('Elasticsearch URL is not set for type: %1$s.', $value_name));

					return null;
				}

				$url = $url[$value_name];
			}

			if (substr($url, -1) !== '/') {
				$url .= '/';
			}

			$urls[$value_name] = $url;
		}

		return $urls[$value_name];
	}

	/**
	 * Get endpoints for Elasticsearch requests.
	 *
	 * @param mixed $value_types  Value type(s).
	 *
	 * @return array  Elasticsearch query endpoints.
	 */
	public static function getElasticsearchEndpoints($value_types, $action = '_search') {
		if (!is_array($value_types)) {
			$value_types = [$value_types];
		}

		$indices = [];
		$endponts = [];

		foreach (array_unique($value_types) as $type) {
			if (self::getDataSourceType($type) === ZBX_HISTORY_SOURCE_ELASTIC) {
				$indices[$type] = self::getTypeNameByTypeId($type);
			}
		}

		foreach ($indices as $type => $index) {
			if (($url = self::getElasticsearchUrl($index)) !== null) {
				$endponts[$type] = $url.$index.'*/'.$action;
			}
		}

		return $endponts;
	}

	/**
	 * Return the name of the table where the data for the given value type is stored.
	 *
	 * @param int $value_type  Value type.
	 *
	 * @return string|array  Table name | all tables.
	 */
	public static function getTableName($value_type = null) {
		$tables = [
			ITEM_VALUE_TYPE_LOG => 'history_log',
			ITEM_VALUE_TYPE_TEXT => 'history_text',
			ITEM_VALUE_TYPE_STR => 'history_str',
			ITEM_VALUE_TYPE_FLOAT => 'history',
			ITEM_VALUE_TYPE_UINT64 => 'history_uint'
		];

		return ($value_type === null) ? $tables : $tables[$value_type];
	}

	/**
	 * Returns the items grouped by the storage type.
	 *
	 * @param array $items  An array of items with the 'value_type' property.
	 *
	 * @return array  An array with storage type as a keys and item arrays as a values.
	 */
	private function getItemsGroupedByStorage(array $items) {
		$grouped_items = [];

		foreach ($items as $item) {
			$source = self::getDataSourceType($item['value_type']);
			$grouped_items[$source][] = $item;
		}

		return $grouped_items;
	}
}
