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
 * Class to perform low level history related actions.
 */
class CHistoryManager {

	/**
	 * Returns the last $limit history objects for the given items.
	 *
	 * @param array $items     an array of items with the 'itemid' and 'value_type' properties
	 * @param int   $limit     max object count to be returned
	 * @param int   $period    the maximum period to retrieve data for
	 *
	 * @return array    an array with items IDs as keys and arrays of history objects as values
	 */
	public function getLastValues(array $items, $limit = 1, $period = null) {
		$results = [];
		$grouped_items = self::getItemsGroupedByStorage($items);

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getLastValuesFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC], $limit,
					$period
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			$results += $this->getLastValuesFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL], $limit, $period);
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
	 * SQL specific implementation of getLastValues.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	private function getLastValuesFromSql($items, $limit, $period) {
		$results = [];

		foreach ($items as $item) {
			$values = DBfetchArray(DBselect(
				'SELECT *'.
				' FROM '.self::getTableName($item['value_type']).' h'.
				' WHERE h.itemid='.zbx_dbstr($item['itemid']).
					($period ? ' AND h.clock>'.(time() - $period) : '').
				' ORDER BY h.clock DESC',
				$limit
			));

			if ($values) {
				$results[$item['itemid']] = $values;
			}
		}

		return $results;
	}

	/**
	 * Returns the history value of the item at the given time. If no value exists at the given time, the function
	 * will return the previous value.
	 *
	 * The $item parameter must have the value_type and itemid properties set.
	 *
	 * @param array $item     item to get value for
	 * @param int   $clock    timestamp (seconds)
	 * @param int   $ns       timestamp (nanoseconds)
	 *
	 * @return string    value at specified time of first value before specified time
	 */
	public function getValueAt($item, $clock, $ns) {
		switch (self::getDataSourceType($item['value_type'])) {
			case ZBX_HISTORY_SOURCE_ELASTIC:
				return $this->getValueAtFromElasticsearch($item, $clock, $ns);

			default:
				return $this->getValueAtFromSql($item, $clock, $ns);
		}
	}

	/**
	 * Elasticsearch specific implementation of getValueAt.
	 *
	 * @see CHistoryManager::getValueAt
	 */
	private function getValueAtFromElasticsearch($item, $clock, $ns) {
		$query = [
			'sort' => [
				'clock' => ZBX_SORT_DOWN,
				'ns' => ZBX_SORT_DOWN
			],
			'size' => 1
		];

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
						]
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
				return $result[0]['value'];
			}
		}

		return null;
	}

	/**
	 * SQL specific implementation of getValueAt.
	 *
	 * @see CHistoryManager::getValueAt
	 */
	private function getValueAtFromSql($item, $clock, $ns) {
		$value = null;
		$table = self::getTableName($item['value_type']);

		$sql = 'SELECT value'.
				' FROM '.$table.
				' WHERE itemid='.zbx_dbstr($item['itemid']).
					' AND clock='.zbx_dbstr($clock).
					' AND ns='.zbx_dbstr($ns);

		if (($row = DBfetch(DBselect($sql, 1))) !== false) {
			$value = $row['value'];
		}

		if ($value !== null) {
			return $value;
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
			$sql = 'SELECT MAX(clock) AS clock'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock<'.zbx_dbstr($clock);

			if (($row = DBfetch(DBselect($sql))) !== false) {
				$max_clock = $row['clock'];
			}
		}

		if ($max_clock == 0) {
			return $value;
		}

		if ($clock == $max_clock) {
			$sql = 'SELECT value'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock='.zbx_dbstr($clock).
						' AND ns<'.zbx_dbstr($ns);
		}
		else {
			$sql = 'SELECT value'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock='.zbx_dbstr($max_clock).
					' ORDER BY itemid,clock desc,ns desc';
		}

		if (($row = DBfetch(DBselect($sql, 1))) !== false) {
			$value = $row['value'];
		}

		return $value;
	}

	/**
	 * Returns history value aggregation for graphs.
	 *
	 * The $item parameter must have the value_type, itemid and source properties set.
	 *
	 * @param array  $items        items to get aggregated values for
	 * @param int    $time_from    minimal timestamp (seconds) to get data from
	 * @param int    $time_to      maximum timestamp (seconds) to get data from
	 * @param int    $width        graph width in pixels (is not required for pie charts)
	 *
	 * @return array    history value aggregation for graphs
	 */
	public function getGraphAggregation(array $items, $time_from, $time_to, $width = null) {
		if ($width !== null) {
			$size = $time_to - $time_from;
			$delta = $size - $time_from % $size;
		}
		else {
			$size = null;
			$delta = null;
		}

		$grouped_items = self::getItemsGroupedByStorage($items);

		$results = [];
		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getGraphAggregationFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC],
					$time_from, $time_to, $width, $size, $delta
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			$results += $this->getGraphAggregationFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL], $time_from, $time_to,
					$width, $size, $delta
			);
		}

		return $results;
	}

	/**
	 * Elasticsearch specific implementation of getGraphAggregation.
	 *
	 * @see CHistoryManager::getGraphAggregation
	 */
	private function getGraphAggregationFromElasticsearch(array $items, $time_from, $time_to, $width, $size, $delta) {
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

		if ($width !== null && $size !== null && $delta !== null) {
			// Additional grouping for line graphs.
			$aggs['max_clock'] = [
				'max' => [
					'field' => 'clock'
				]
			];

			// Clock value is divided by 1000 as it is stored as milliseconds.
			$formula = 'Math.floor((params.width*((doc[\'clock\'].date.getMillis()/1000+params.delta)%params.size))'.
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

			if ($width !== null && $size !== null && $delta !== null) {
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

		return $results;
	}

	/**
	 * SQL specific implementation of getGraphAggregation.
	 *
	 * @see CHistoryManager::getGraphAggregation
	 */
	private function getGraphAggregationFromSql(array $items, $time_from, $time_to, $width, $size, $delta) {
		$group_by = 'itemid';
		$sql_select_extra = '';

		if ($width !== null && $size !== null && $delta !== null) {
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
	 * @param array  $item         item to get aggregated value for
	 * @param string $aggregation  aggregation to be applied (min / max / avg)
	 * @param int    $time_from    timestamp (seconds)
	 *
	 * @return string    aggregated history value
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
	 * Return the time of the 1st appearance of items in history or trends.
	 *
	 * @param array $items
	 * @param array $items[]['itemid']
	 * @param array $items[]['hostid']
	 * @param array $items[]['value_type']
	 * @param array $items[]['history']
	 * @param array $items[]['trends']
	 *
	 * @return int    timestamp (unixtime)
	 */
	public function getMinClock(array $items) {
		$item_types = [
			ITEM_VALUE_TYPE_FLOAT => [],
			ITEM_VALUE_TYPE_STR => [],
			ITEM_VALUE_TYPE_LOG => [],
			ITEM_VALUE_TYPE_UINT64 => [],
			ITEM_VALUE_TYPE_TEXT => []
		];
		$max = ['history' => 0, 'trends' => 0];
		$simple_interval_parser = new CSimpleIntervalParser();

		$items = CMacrosResolverHelper::resolveTimeUnitMacros($items, ['history', 'trends']);

		foreach ($items as $item) {
			$item_types[$item['value_type']][] = $item['itemid'];

			if ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64) {
				if ($simple_interval_parser->parse($item['history']) == CParser::PARSE_SUCCESS) {
					$item['history'] = (int) timeUnitToSeconds($item['history']);
					$max['history'] = max($max['history'], $item['history']);
				}

				if ($simple_interval_parser->parse($item['trends']) == CParser::PARSE_SUCCESS) {
					$item['trends'] = (int) timeUnitToSeconds($item['trends']);
					$max['trends'] = max($max['trends'], $item['trends']);
				}
			}
		}

		$source = ($max['history'] > $max['trends']) ? 'history' : 'trends';
		$storage_items = [];

		foreach ($item_types as $type => $itemids) {
			if (!$itemids) {
				continue;
			}

			$history_source = self::getDataSourceType($type);
			$storage_items[$history_source][$type] = $itemids;
		}

		$min_clock = [];

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $storage_items)) {
			$min_clock[] = $this->getMinClockFromElasticsearch($storage_items[ZBX_HISTORY_SOURCE_ELASTIC]);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $storage_items)) {
			$min_clock[] = $this->getMinClockFromSql($storage_items[ZBX_HISTORY_SOURCE_SQL], $source);
		}

		$min_clock = min($min_clock);

		// If DB clock column is corrupted having negative numbers, return min clock from max possible history storage.
		if ($min_clock == 0) {
			if ($item_types[ITEM_VALUE_TYPE_FLOAT] || $item_types[ITEM_VALUE_TYPE_UINT64]) {
				$min_clock = time() - max($max['history'], $max['trends']);

				/*
				 * In case history storage exceeds the maximum time difference between current year and minimum 1970
				 * (for example year 2014 - 200 years < year 1970), correct year to 1970 (unix time timestamp 0).
				 */
				if ($min_clock < 0) {
					$min_clock = 0;
				}
			}
			else {
				$min_clock = time() - SEC_PER_YEAR;
			}
		}

		return $min_clock;
	}

	/**
	 * Elasticsearch specific implementation of getMinClock.
	 *
	 * @see CHistoryManager::getMinClock
	 */
	private function getMinClockFromElasticsearch(array $items) {
		$query = [
			'aggs'=> [
				'min_clock' => [
					'min' => [
						'field' => 'clock'
					]
				]
			],
			'size'=> 0
		];

		$min_clock = [];

		foreach (self::getElasticsearchEndpoints(array_keys($items)) as $type => $endpoint) {
			$query['query']['terms']['itemid'] = $items[$type];
			$data = CElasticsearchHelper::query('POST', $endpoint, $query);

			// Field value_as_string is used as a workaround for date aggregation being presented as milliseconds.
			if (array_key_exists('min_clock', $data) && array_key_exists('value_as_string', $data['min_clock'])) {
				$min_clock[] = $data['min_clock']['value_as_string'];
			}
		}

		if ($min_clock) {
			return min($min_clock);
		}

		return null;
	}

	/**
	 * SQL specific implementation of getMinClock.
	 *
	 * @see CHistoryManager::getMinClock
	 */
	private function getMinClockFromSql(array $items, $source) {
		$sql_unions = [];

		foreach ($items as $type => $itemids) {
			if (!$itemids) {
				continue;
			}

			switch ($type) {
				case ITEM_VALUE_TYPE_FLOAT:
					$sql_from = $source;
					break;
				case ITEM_VALUE_TYPE_STR:
					$sql_from = 'history_str';
					break;
				case ITEM_VALUE_TYPE_LOG:
					$sql_from = 'history_log';
					break;
				case ITEM_VALUE_TYPE_UINT64:
					$sql_from = $source.'_uint';
					break;
				case ITEM_VALUE_TYPE_TEXT:
					$sql_from = 'history_text';
					break;
				default:
					$sql_from = 'history';
			}

			foreach ($itemids as $itemid) {
				$sql_unions[] =
					'SELECT MIN(h.clock) AS clock'.
					' FROM '.$sql_from.' h'.
					' WHERE h.itemid='.zbx_dbstr($itemid);
			}
		}

		$row = DBfetch(DBselect(
			'SELECT MIN(h.clock) AS min_clock'.
			' FROM ('.implode(' UNION ALL ', $sql_unions).') h'
		));

		return $row['min_clock'];
	}

	/**
	 * Clear item history and trends by provided item IDs. History is deleted from both SQL and Elasticsearch.
	 *
	 * @param array $itemids    item ids to delete history for
	 *
	 * @return bool
	 */
	public function deleteHistory(array $itemids) {
		return $this->deleteHistoryFromSql($itemids) && $this->deleteHistoryFromElasticsearch($itemids);
	}

	/**
	 * Elasticsearch specific implementation of deleteHistory.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	private function deleteHistoryFromElasticsearch(array $itemids) {
		global $HISTORY;

		if (is_array($HISTORY) && array_key_exists('types', $HISTORY) && is_array($HISTORY['types'])
				&& count($HISTORY['types'] > 0)) {

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
	private function deleteHistoryFromSql(array $itemids) {
		return DBexecute('DELETE FROM trends WHERE '.dbConditionInt('itemid', $itemids))
				&& DBexecute('DELETE FROM trends_uint WHERE '.dbConditionInt('itemid', $itemids))
				&& DBexecute('DELETE FROM history_text WHERE '.dbConditionInt('itemid', $itemids))
				&& DBexecute('DELETE FROM history_log WHERE '.dbConditionInt('itemid', $itemids))
				&& DBexecute('DELETE FROM history_uint WHERE '.dbConditionInt('itemid', $itemids))
				&& DBexecute('DELETE FROM history_str WHERE '.dbConditionInt('itemid', $itemids))
				&& DBexecute('DELETE FROM history WHERE '.dbConditionInt('itemid', $itemids));
	}

	/**
	 * Get type name by value type id.
	 *
	 * @param int $value_type    value type id
	 *
	 * @return string    value type name
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
	 * @param int $type_name    value type name
	 *
	 * @return int    value type id
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
	 * @param int $value_type    value type id
	 *
	 * @return string    data source type
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
				error(_s('Elasticsearch url is not set for type: %1$s.', $value_name));

				return null;
			}

			$url = $HISTORY['url'];
			if (is_array($url)) {
				if (!array_key_exists($value_name, $url)) {
					$invalid[$value_name] = true;
					error(_s('Elasticsearch url is not set for type: %1$s.', $value_name));

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
	 * @param mixed $value_types    value type(s)
	 *
	 * @return array    Elasticsearch query endpoints
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
				$endponts[$type] = $url.$index.'*/values/'.$action;
			}
		}

		return $endponts;
	}

	/**
	 * Return the name of the table where the data for the given value type is stored.
	 *
	 * @param int $value_type    value type
	 *
	 * @return string    table name
	 */
	public static function getTableName($value_type) {
		$tables = [
			ITEM_VALUE_TYPE_LOG => 'history_log',
			ITEM_VALUE_TYPE_TEXT => 'history_text',
			ITEM_VALUE_TYPE_STR => 'history_str',
			ITEM_VALUE_TYPE_FLOAT => 'history',
			ITEM_VALUE_TYPE_UINT64 => 'history_uint'
		];

		return $tables[$value_type];
	}

	/**
	 * Returns the items grouped by the storage type.
	 *
	 * @param array $items     an array of items with the 'value_type' property
	 *
	 * @return array    an array with storage type as a keys and item arrays as a values
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
