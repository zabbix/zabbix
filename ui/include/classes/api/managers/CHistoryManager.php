<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Class to perform low level history related actions.
 */
class CHistoryManager {

	private bool $primary_keys_enabled = false;

	private static array $providers = [
		ZBX_HISTORY_SOURCE_CLICKHOUSE => 'ClickHouse',
		ZBX_HISTORY_SOURCE_ELASTIC => 'ElasticDB'
	];

	private static array $value_type_mapping = [
		ITEM_VALUE_TYPE_FLOAT => 'dbl',
		ITEM_VALUE_TYPE_STR => 'str',
		ITEM_VALUE_TYPE_LOG => 'log',
		ITEM_VALUE_TYPE_UINT64 => 'uint',
		ITEM_VALUE_TYPE_TEXT => 'text',
		ITEM_VALUE_TYPE_BINARY => 'binary',
		ITEM_VALUE_TYPE_JSON => 'json'
	];

	private static ?array $value_type_sources = null;

	private static function initSources(): void {
		if (self::$value_type_sources !== null) {
			return;
		}

		global $HISTORY, $HISTORY_PROVIDERS;

		self::$value_type_sources = [];

		if ($HISTORY_PROVIDERS) {
			foreach ($HISTORY_PROVIDERS as $provider) {
				$provider_data = [
					'provider' => $provider['provider'],
					'url' => array_key_exists('url', $provider) ? rtrim($provider['url'], '/').'/' : null,
					'db' => array_key_exists('db', $provider) ? $provider['db'] : null,
					'username' => array_key_exists('username', $provider) ? $provider['username'] : null,
					'password' => array_key_exists('password', $provider) ? $provider['password'] : null
				];

				foreach ($provider['types'] as $type) {
					self::$value_type_sources[$type] = $provider_data;
				}
			}
		}
		elseif ($HISTORY) {
			$url = array_key_exists('url', $HISTORY) ? $HISTORY['url'] : null;

			foreach ($HISTORY['types'] as $type) {
				$target_url = $url;

				if (is_array($target_url)) {
					$target_url = array_key_exists($type, $target_url) ? $target_url[$type] : null;
				}

				self::$value_type_sources[$type] = [
					'provider' => ZBX_HISTORY_SOURCE_ELASTIC,
					'url' => $target_url !== null ? rtrim($target_url, '/').'/' : null
				];
			}
		}
	}

	/**
	 * Whether to enable optimizations that make use of PRIMARY KEY (itemid, clock, ns) on the history tables.
	 *
	 * @param bool $enabled
	 *
	 * @return CHistoryManager
	 */
	public function setPrimaryKeysEnabled(bool $enabled = true): CHistoryManager {
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
			$length = 1;
			$results += $this->getLastValuesFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC], 1,
				$period, $length
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_CLICKHOUSE, $grouped_items)) {
			$results += $this->getLastValuesFromClickhouse($grouped_items[ZBX_HISTORY_SOURCE_CLICKHOUSE], 1,
				$period, $length
			);
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

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
			$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
			$period = $period !== null ? min($period, $hk_history) : $hk_history;
		}

		if ($period !== null) {
			$period = time() - $period;
		}

		$items = zbx_toHash($items, 'itemid');

		$itemids_by_type = [];

		foreach ($items as $itemid => $item) {
			$itemids_by_type[$item['value_type']][] = $itemid;
		}

		foreach ($itemids_by_type as $type => $type_itemids) {
			$type_results = DBfetchColumn(DBselect(
				'SELECT DISTINCT itemid'.
				' FROM '.self::getTableName($type).
				' WHERE '.dbConditionInt('itemid', $type_itemids).
					($period !== null ? ' AND clock>'.$period : '')
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
	 * @param int   $length  The maximum length of value to be returned.
	 *
	 * @return array  An array with items IDs as keys and arrays of history objects as values.
	 */
	public function getLastValues(array $items, $limit = 1, $period = null, ?int $length = null) {
		$results = [];
		$grouped_items = $this->getItemsGroupedByStorage($items);

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getLastValuesFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC], $limit,
				$period, $length
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_CLICKHOUSE, $grouped_items)) {
			$results += $this->getLastValuesFromClickhouse($grouped_items[ZBX_HISTORY_SOURCE_CLICKHOUSE], $limit,
				$period, $length
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			if ($this->primary_keys_enabled) {
				$results += $this->getLastValuesFromSqlWithPk($grouped_items[ZBX_HISTORY_SOURCE_SQL], $limit, $period,
					$length
				);
			}
			else {
				$results += $this->getLastValuesFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL], $limit, $period,
					$length
				);
			}
		}

		return $results;
	}

	/**
	 * Elasticsearch specific implementation of getLastValues.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	private function getLastValuesFromElasticsearch($items, $limit, $period, ?int $length) {
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
					'aggs' => []
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
			$query['aggs']['group_by_itemid']['aggs']['group_by_docs'] = [
				'top_hits' => [
					'size' => $limit,
					'sort' => [
						'clock' => ZBX_SORT_DOWN
					]
				]
			];

			if ($type == ITEM_VALUE_TYPE_JSON && $length !== null) {
				$query['aggs']['group_by_itemid']['aggs']['group_by_docs']['top_hits'] += [
					'_source' => ['itemid', 'clock', 'ns'],
					'script_fields' => [
						'value' => CElasticsearchHelper::getSubstring('value', $length)
					]
				];
			}

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
					if (!array_key_exists('_source', $row) && !array_key_exists('fields', $row)) {
						continue;
					}

					if (array_key_exists('fields', $row)) {
						foreach ($row['fields'] as $name => $value) {
							// Elasticsearch returns field values as arrays and first element holds the value.
							$row['_source'][$name] = $value[0];
						}
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
	 *
	 * @return array  Of itemid => [up to $limit values].
	 */
	private function getLastValuesFromSqlWithPk(array $items, int $limit, ?int $period, ?int $length): array {
		$results = [];

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
			$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
			$period = $period !== null ? min($period, $hk_history) : $hk_history;
		}

		$period_condition = $period === null ? '' : ' AND h.clock>'.(time() - $period);
		$value_expression = $length === null ? 'h.value' : dbSubstring('value', 1, $length);

		foreach ($items as $item) {
			$select_fields = DB::getSchema(self::getTableName($item['value_type']))['fields'];
			$select_fields = array_diff(array_keys($select_fields), ['value']);
			$db_values = DBselect(
				'SELECT h.'.implode(',h.', $select_fields).','.
					($item['value_type'] == ITEM_VALUE_TYPE_JSON || $item['value_type'] == ITEM_VALUE_TYPE_BINARY
						? $value_expression
						: 'h.value').
				' FROM '.self::getTableName($item['value_type']).' h'.
				' WHERE h.itemid='.zbx_dbstr($item['itemid']).
					$period_condition.
				' ORDER BY h.clock DESC,h.ns DESC',
				$limit
			);

			while ($db_value = DBfetch($db_values, false)) {
				$results[$db_value['itemid']][] = $db_value;
			}
		}

		return $results;
	}

	/**
	 * SQL specific implementation of getLastValues.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	private function getLastValuesFromSql($items, $limit, $period, ?int $length) {
		$results = [];
		$value_expression = $length === null ? 'h.value' : dbSubstring('value', 1, $length);

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
			$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
			$period = $period !== null ? min($period, $hk_history) : $hk_history;
		}

		if ($period !== null) {
			$period = time() - $period;
		}

		if ($limit == 1) {
			foreach ($items as $item) {
				// Executing two subsequent queries individually for the sake of performance.

				$clock_max = DBfetch(DBselect(
					'SELECT MAX(h.clock)'.
					' FROM '.self::getTableName($item['value_type']).' h'.
					' WHERE h.itemid='.zbx_dbstr($item['itemid']).
						($period !== null ? ' AND h.clock>'.$period : '')
				), false);
				$clock_max = $clock_max ? reset($clock_max) : null;

				if ($clock_max === null) {
					continue;
				}

				$select_fields = DB::getSchema(self::getTableName($item['value_type']))['fields'];
				$select_fields = array_diff(array_keys($select_fields), ['value']);
				$db_values = DBfetchArray(DBselect(
					'SELECT h.'.implode(',h.', $select_fields).','.
						($item['value_type'] == ITEM_VALUE_TYPE_JSON || $item['value_type'] == ITEM_VALUE_TYPE_BINARY
							? $value_expression
							: 'h.value').
					' FROM '.self::getTableName($item['value_type']).' h'.
					' WHERE h.itemid='.zbx_dbstr($item['itemid']).
						' AND h.clock='.zbx_dbstr($clock_max).
					' ORDER BY h.ns DESC',
					$limit
				));

				if ($db_values) {
					$results[$item['itemid']] = $db_values;
				}
			}
		}
		else {
			foreach ($items as $item) {
				$select_fields = DB::getSchema(self::getTableName($item['value_type']))['fields'];
				$select_fields = array_diff(array_keys($select_fields), ['value']);
				// Cannot order by h.ns directly here due to performance issues.
				$values = DBfetchArray(DBselect(
					'SELECT h.'.implode(',h.', $select_fields).','.
						($item['value_type'] == ITEM_VALUE_TYPE_JSON || $item['value_type'] == ITEM_VALUE_TYPE_BINARY
							? $value_expression
							: 'h.value').
					' FROM '.self::getTableName($item['value_type']).' h'.
					' WHERE h.itemid='.zbx_dbstr($item['itemid']).
						($period !== null ? ' AND h.clock>'.$period : '').
					' ORDER BY h.clock DESC',
					$limit + 1
				));

				if (!$values) {
					continue;
				}

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
						'SELECT h.'.implode(',h.', $select_fields).','.
							($item['value_type'] == ITEM_VALUE_TYPE_JSON || $item['value_type'] == ITEM_VALUE_TYPE_BINARY
								? $value_expression
								: 'h.value').
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

		return $results;
	}

	/**
	 * ClickHouse specific implementation of getLastValues.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	private function getLastValuesFromClickhouse(array $items, int $limit, ?int $period): array {
		$value_type_itemids = [];

		foreach ($items as $item) {
			$value_type_itemids[$item['value_type']][] = $item['itemid'];
		}

		$results = [];

		foreach (self::getClickhouseEndpoints(array_keys($value_type_itemids)) as $type => $endpoint) {
			$period_condition = '';

			$hk_history = self::getClickhouseTtlByTypeId($type);

			$effective_periods = array_filter([$period, $hk_history]);

			if ($effective_periods) {
				$period_condition = ' AND h.clock_ns>'.db_utc_to_datetime64(time() - min($effective_periods));
			}

			$itemids = $value_type_itemids[$type];

			foreach ($itemids as $itemid) {
				$values = CClickhouseHelper::fetch(
					'SELECT '.self::getClickhouseSelectFieldsByValueType($type).
					' FROM '.self::getTableName($type).' h'.
					' WHERE h.itemid='.zbx_dbstr($itemid).
						$period_condition.
					' ORDER BY h.clock_ns DESC'.
					' LIMIT '.$limit,
					$endpoint['endpoint'], $endpoint['username'], $endpoint['password']
				);

				if ($values) {
					$results[$itemid] = $values;
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

			case ZBX_HISTORY_SOURCE_CLICKHOUSE:
				return $this->getValueAtFromClickhouse($item, $clock, $ns);

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
							'lt' => $clock,
							'gte' => $clock - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD))
						]
					]
				]
			]
		];

		foreach ($filters as $filter) {
			$query['query']['bool']['must'] = $filter;
			$endpoints = self::getElasticsearchEndpoints([$item['value_type']]);

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
		$hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);
		$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));

		if ($hk_history_global == 1 && $clock <= time() - $hk_history) {
			return null;
		}

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

		$time_from = $clock - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		if ($hk_history_global == 1) {
			$time_from = max($time_from, time() - $hk_history + 1);
		}

		$sql = 'SELECT *'.
			' FROM '.$history_table.
			' WHERE itemid='.zbx_dbstr($item['itemid']).
				' AND clock<'.zbx_dbstr($clock).
				' AND clock>='.zbx_dbstr($time_from).
			' ORDER BY clock DESC,ns DESC';

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
		$hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);
		$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));

		if ($hk_history_global == 1 && $clock <= time() - $hk_history) {
			return null;
		}

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
			$time_from = $clock - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

			if ($hk_history_global == 1) {
				$time_from = max($time_from, time() - $hk_history + 1);
			}

			$sql = 'SELECT MAX(clock) AS clock'.
					' FROM '.$table.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock<'.zbx_dbstr($clock).
						' AND clock>='.zbx_dbstr($time_from);

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
	 * Implementation that uses existence of primary key in history tables.
	 * @see CHistoryManager->getValueAtFromClickhouse()
	 *
	 * @param string $item['itemid']
	 * @param int    $item['value_type']
	 * @param int    $clock
	 * @param int    $ns
	 *
	 * @return array|null  Item data at specified time of first data before specified time. null if data is not found.
	 */
	private function getValueAtFromClickhouse(array $item, int $clock, int $ns): ?array {
		$hk_history = self::getClickhouseTtlByTypeId($item['value_type']);

		if ($hk_history !== null && ($clock <= time() - $hk_history)) {
			return null;
		}

		$endpoints = CHistoryManager::getClickhouseEndpoints([$item['value_type']]);

		if (!$endpoints) {
			return null;
		}

		$endpoint = reset($endpoints);

		$values = CClickhouseHelper::fetch(
			'SELECT '.self::getClickhouseSelectFieldsByValueType($item['value_type']).
			' FROM '.self::getTableName($item['value_type']).' h'.
			' WHERE h.itemid='.zbx_dbstr($item['itemid']).
			' AND h.clock_ns<='.db_utc_to_datetime64($clock, $ns).
			' ORDER BY h.clock_ns DESC'.
			' LIMIT 1',
			$endpoint['endpoint'], $endpoint['username'], $endpoint['password']
		);

		if (!$values) {
			return null;
		}

		return $values;
	}

	/**
	 * Get value aggregation by interval within the specified time period.
	 *
	 * The $item parameter must have the value_type, itemid and source properties set.
	 *
	 * @param array  $items      Items to get aggregated values for.
	 * @param int    $time_from  Start of time period, inclusive (unix time stamp).
	 * @param int    $time_to    End of time period, inclusive (unix time stamp).
	 * @param int    $function   Aggregation function.
	 * @param int    $interval   Interval length (in seconds).
	 *
	 * @return array
	 */
	public function getAggregationByInterval(array $items, int $time_from, int $time_to, int $function, int $interval)
			: array {
		$grouped_items = $this->getItemsGroupedByStorage($items);

		$results = [];

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getAggregationByIntervalFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC],
				$time_from, $time_to, $function, $interval
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_CLICKHOUSE, $grouped_items)) {
			$results += $this->getAggregationByIntervalFromClickhouse($grouped_items[ZBX_HISTORY_SOURCE_CLICKHOUSE],
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
	private function getAggregationByIntervalFromElasticsearch(array $items, int $time_from, int $time_to,
			int $function, int $interval): array {
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
				$aggs['value'] = [
					'top_hits' => [
						'size' => 1,
						'sort' => ['clock' => 'ASC', 'ns' => 'ASC']
					]
				];
				$aggs['clock'] = ['min' => ['field' => 'clock']];
				break;
			case AGGREGATE_LAST:
				$aggs['value'] = [
					'top_hits' => [
						'size' => 1,
						'sort' => ['clock' => 'DESC', 'ns' => 'DESC']
					]
				];
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
		$formula = "doc['clock'].value.getMillis()/1000-doc['clock'].value.getMillis()/1000%params.interval";

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
						'tick' => (int) $point['key'],
						'num' => $point['doc_count'],
						'clock' => (int) $point['clock']['value_as_string']
					];

					if ($function == AGGREGATE_COUNT) {
						$row['value'] = $point['doc_count'];
					}
					elseif ($function == AGGREGATE_FIRST || $function == AGGREGATE_LAST) {
						$row['value'] = $point['value']['hits']['hits'][0]['_source']['value'];
						$row['ns'] = $point['value']['hits']['hits'][0]['_source']['ns'];
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
	 * ClickHouse specific implementation of getAggregationByInterval.
	 *
	 * @see CHistoryManager::getAggregationByInterval
	 */
	private function getAggregationByIntervalFromClickhouse(array $items, int $time_from, int $time_to,
			int $function, int $interval): array {
		$value_type_itemids = [];

		foreach ($items as $item) {
			$value_type_itemids[$item['value_type']][$item['itemid']] = true;
		}

		$sql_select = ['s.itemid', 's.value', 'toUnixTimestamp(s.tick) AS tick', 'toUnixTimestamp(s.ts) AS clock',
			'toUnixTimestamp64Nano(s.ts) % 1000000000 AS ns'
		];
		$sql_sub_select = ['h.itemid', 'toStartOfInterval(h.clock_ns, toIntervalSecond('.$interval.')) AS tick'];

		switch ($function) {
			case AGGREGATE_MIN:
				$sql_sub_select[] = 'min(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_MAX:
				$sql_sub_select[] = 'max(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_AVG:
				$sql_sub_select[] = 'avg(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_COUNT:
				$sql_sub_select[] = 'count() AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_SUM:
				$sql_sub_select[] = 'sum(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_FIRST:
				$sql_sub_select[] = 'argMin(h.value, h.clock_ns) AS value,min(h.clock_ns) AS ts';
				break;
			case AGGREGATE_LAST:
				$sql_sub_select[] = 'argMax(h.value, h.clock_ns) AS value,max(h.clock_ns) AS ts';
				break;
		}

		$results = [];

		foreach (self::getClickhouseEndpoints(array_keys($value_type_itemids)) as $value_type => $endpoint) {
			$hk_history = self::getClickhouseTtlByTypeId($value_type);

			$effective_time_from = $hk_history === null ? $time_from : max($time_from, time() - $hk_history);

			$period_condition = ' AND h.clock_ns BETWEEN '.db_utc_to_datetime64($effective_time_from).
				' AND '.db_utc_to_datetime64($time_to);

			$values = CClickhouseHelper::fetch(
				'SELECT '.implode(',', $sql_select).
				' FROM ('.
					'SELECT '.implode(',', $sql_sub_select).
					' FROM '.self::getTableName($value_type).' h'.
					' WHERE '.dbConditionId('h.itemid', array_keys($value_type_itemids[$value_type])).
					$period_condition.
					' GROUP BY h.itemid,tick'.
				') s',
				$endpoint['endpoint'], $endpoint['username'], $endpoint['password']
			);

			if ($values) {
				foreach ($values as $value) {
					$results[$value['itemid']]['data'][] = $value;
				}
			}
		}

		foreach ($items as $item) {
			if (array_key_exists($item['itemid'], $results)) {
				$results[$item['itemid']]['source'] = 'history';
			}
		}

		return $results;
	}

	/**
	 * SQL specific implementation of getAggregationByInterval.
	 *
	 * @see CHistoryManager::getAggregationByInterval
	 */
	private function getAggregationByIntervalFromSql(array $items, int $time_from, int $time_to, int $function,
			int $interval): array {
		$items_by_table = [];
		foreach ($items as $item) {
			$items_by_table[$item['value_type']][$item['source']][] = $item['itemid'];
		}

		$result = [];

		$hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);
		$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
		$hk_trends_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL);
		$hk_trends = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));

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
							$sql_select[] = 'MIN(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_MAX:
							$sql_select[] = 'MAX(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_AVG:
							$sql_select[] = 'AVG(value) AS value,MAX(clock) AS clock,COUNT(*) AS num';
							break;
						case AGGREGATE_COUNT:
							$sql_select[] = 'COUNT(*) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_SUM:
							$sql_select[] = 'SUM(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_FIRST:
							$sql_select[] = 'MIN(clock) AS clock';
							break;
						case AGGREGATE_LAST:
							$sql_select[] = 'MAX(clock) AS clock';
							break;
					}
					$sql_from = ($value_type == ITEM_VALUE_TYPE_UINT64) ? 'history_uint' : 'history';

					$_time_from = $hk_history_global == 1
						? max($time_from, time() - $hk_history + 1)
						: $time_from;
				}
				else {
					switch ($function) {
						case AGGREGATE_MIN:
							$sql_select[] = 'MIN(value_min) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_MAX:
							$sql_select[] = 'MAX(value_max) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_AVG:
							$sql_select[] = 'AVG(value_avg) AS value,MAX(clock) AS clock,SUM(num) AS num';
							break;
						case AGGREGATE_COUNT:
							$sql_select[] = 'SUM(num) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_SUM:
							$sql_select[] = 'SUM(value_avg * num) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_FIRST:
							$sql_select[] = 'MIN(clock) AS clock';
							break;
						case AGGREGATE_LAST:
							$sql_select[] = 'MAX(clock) AS clock';
							break;
					}
					$sql_from = ($value_type == ITEM_VALUE_TYPE_UINT64) ? 'trends_uint' : 'trends';

					$_time_from = $hk_trends_global == 1
						? max($time_from, time() - $hk_trends + 1)
						: $time_from;
				}

				$sql = 'SELECT '.implode(',', $sql_select).
					' FROM '.$sql_from.
					' WHERE '.dbConditionInt('itemid', $itemids).
						' AND clock BETWEEN '.$_time_from.' AND '.$time_to.
					' GROUP BY '.implode(',', $sql_group_by);

				if ($function == AGGREGATE_FIRST || $function == AGGREGATE_LAST) {
					if ($source === 'history') {
						$sql_function = $function == AGGREGATE_FIRST ? 'MIN' : 'MAX';
						$sql =
							'SELECT h.itemid,h.value,h.clock,h.ns,s.tick'.
							' FROM '.$sql_from.' h'.
							' JOIN ('.
								'SELECT h2.itemid,h2.clock,'.$sql_function.'(h2.ns) AS ns,s2.tick'.
								' FROM '.$sql_from.' h2'.
									' JOIN ('.$sql.') s2 ON h2.itemid=s2.itemid AND h2.clock=s2.clock'.
								' WHERE h2.clock BETWEEN '.$_time_from.' AND '.$time_to.
								' GROUP BY h2.itemid,h2.clock,s2.tick'.
							') s ON h.itemid=s.itemid AND h.clock=s.clock AND h.ns=s.ns'.
							' WHERE h.clock BETWEEN '.$_time_from.' AND '.$time_to;
					}
					else {
						$sql =
							'SELECT DISTINCT h.itemid,h.value_avg AS value,h.clock,0 AS ns,s.tick'.
							' FROM '.$sql_from.' h'.
								' JOIN ('.$sql.') s ON h.itemid=s.itemid AND h.clock=s.clock'.
							' WHERE h.clock BETWEEN '.$_time_from.' AND '.$time_to;
					}
				}

				$sql_result = DBselect($sql);

				while (($row = DBfetch($sql_result)) !== false) {
					$result[$row['itemid']]['source'] = $source;
					$result[$row['itemid']]['data'][] = $row;
				}

				if ($function == AGGREGATE_AVG && $value_type == ITEM_VALUE_TYPE_UINT64) {
					// Fix PostgreSQL number formatting (remove trailing zeros) in AVG aggregation on *_uint tables.

					foreach ($itemids as $itemid) {
						if (array_key_exists($itemid, $result)) {
							foreach ($result[$itemid]['data'] as &$row) {
								$row['value'] = (string) (float) $row['value'];
							}
							unset($row);
						}
					}
				}

				if ($function == AGGREGATE_COUNT) {
					foreach ($itemids as $itemid) {
						if (!array_key_exists($itemid, $result)) {
							$result[$itemid] = [
								'source' => $source,
								'data' => []
							];
						}

						$db_ticks = array_column($result[$itemid]['data'], 'tick', 'tick');

						for ($tick = $_time_from - $_time_from % $interval; $tick <= $time_to; $tick += $interval) {
							if (!array_key_exists($tick, $db_ticks)) {
								$result[$itemid]['data'][] = [
									'itemid' => (string) $itemid,
									'tick' => (string) $tick,
									'value' => '0',
									'clock' => (string) $tick
								];
							}
						}
					}
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

		if (array_key_exists(ZBX_HISTORY_SOURCE_CLICKHOUSE, $grouped_items)) {
			$results += $this->getGraphAggregationByWidthFromClickhouse($grouped_items[ZBX_HISTORY_SOURCE_CLICKHOUSE],
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
			$period = $time_to - $time_from;

			// Additional grouping for line graphs.
			$aggs['max_clock'] = [
				'max' => [
					'field' => 'clock'
				]
			];

			// Clock value is divided by 1000 as it is stored as milliseconds.
			$formula = "Math.round(params.width*(doc['clock'].value.getMillis()/1000-params.time_from)/params.period)";

			$script = [
				'inline' => $formula,
				'params' => [
					'width' => (int)$width,
					'time_from' => $time_from,
					'period' => $period
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
	 * ClickHouse specific implementation of getGraphAggregationByWidth.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	private function getGraphAggregationByWidthFromClickhouse(array $items, int $time_from, int $time_to,
			?int $width): array {
		$value_type_itemids = [];

		foreach ($items as $item) {
			$value_type_itemids[$item['value_type']][$item['itemid']] = true;
		}

		$sql_select = ['s.itemid', 's.count', 's.avg', 's.min', 's.max', 'toUnixTimestamp(s.ts) AS clock'];

		$sql_sub_select = ['h.itemid', 'count() AS count', 'avg(h.value) AS avg', 'min(h.value) AS min',
			'max(h.value) AS max', 'max(h.clock_ns) as ts'
		];

		$group_by = ['h.itemid'];

		if ($width !== null) {
			$sql_sub_select[] =
				'round('.$width.'*(toUnixTimestamp(h.clock_ns)-'.$time_from.')/('.$time_to.'-'.$time_from.')) AS i';

			$sql_select[] = 'i';
			$group_by[] = 'i';
		}

		$results = [];

		foreach (self::getClickhouseEndpoints(array_keys($value_type_itemids)) as $value_type => $endpoint) {
			$hk_history = self::getClickhouseTtlByTypeId($value_type);

			$effective_time_from = $hk_history === null ? $time_from : max($time_from, time() - $hk_history);

			$period_condition = ' AND h.clock_ns BETWEEN '.db_utc_to_datetime64($effective_time_from).
				' AND '.db_utc_to_datetime64($time_to);

			$values = CClickhouseHelper::fetch(
				'SELECT '.implode(',', $sql_select).
				' FROM ('.
					'SELECT '.implode(',', $sql_sub_select).
					' FROM '.self::getTableName($value_type).' h'.
					' WHERE '.dbConditionId('h.itemid', array_keys($value_type_itemids[$value_type])).
					$period_condition.
					' GROUP BY '.implode(',', $group_by).
				') s',
				$endpoint['endpoint'], $endpoint['username'], $endpoint['password']
			);

			if ($values) {
				foreach ($values as $value) {
					$results[$value['itemid']]['data'][] = $value;
				}
			}
		}

		foreach ($items as $item) {
			if (array_key_exists($item['itemid'], $results)) {
				$results[$item['itemid']]['source'] = 'history';
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
			$period = $time_to - $time_from;

			$calc_field = 'round('.$width.'.0*(clock-'.$time_from.')/'.$period.',0)';

			$sql_select_extra = ','.$calc_field.' AS i';
			$group_by .= ','.$calc_field;
		}

		$results = [];

		$hk_history_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL);
		$hk_history = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY));
		$hk_trends_global = CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL);
		$hk_trends = timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS));

		foreach ($items as $item) {
			if ($item['source'] === 'history') {
				$sql_select = 'COUNT(*) AS count,AVG(value) AS avg,MIN(value) AS min,MAX(value) AS max';
				$sql_from = $item['value_type'] == ITEM_VALUE_TYPE_UINT64 ? 'history_uint' : 'history';

				$_time_from = $hk_history_global == 1
					? max($time_from, time() - $hk_history + 1)
					: $time_from;
			}
			else {
				$sql_select = 'SUM(num) AS count,AVG(value_avg) AS avg,MIN(value_min) AS min,MAX(value_max) AS max';
				$sql_from = ($item['value_type'] == ITEM_VALUE_TYPE_UINT64) ? 'trends_uint' : 'trends';

				$_time_from = $hk_trends_global == 1
					? max($time_from, time() - $hk_trends + 1)
					: $time_from;
			}

			$data = [];

			if ($_time_from <= $time_to) {
				$result = DBselect(
					'SELECT itemid,'.$sql_select.$sql_select_extra.',MAX(clock) AS clock'.
					' FROM '.$sql_from.
					' WHERE itemid='.zbx_dbstr($item['itemid']).
						' AND clock>='.zbx_dbstr($_time_from).
						' AND clock<='.zbx_dbstr($time_to).
					' GROUP BY '.$group_by
				);

				while (($row = DBfetch($result)) !== false) {
					$data[] = $row;
				}
			}

			$results[$item['itemid']]['source'] = $item['source'];
			$results[$item['itemid']]['data'] = $data;
		}

		return $results;
	}

	/**
	 * Get value aggregation within the specified time period.
	 *
	 * The $items parameter must have the 'value_type', 'itemid' and 'source' properties set for each item.
	 * Will not return values for non-numeric items, for which min/max/avg/sum aggregation is requested.
	 * Will not return values for non-numeric items with trends source.
	 *
	 * @param array    $items      Items to get aggregated values for.
	 * @param int      $function   Aggregation function (AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT,
	 *                             AGGREGATE_SUM, AGGREGATE_FIRST, AGGREGATE_LAST).
	 * @param int      $time_from  Start of time period, inclusive (unix time stamp).
	 * @param int|null $time_to    End of time period, inclusive (unix time stamp).
	 *
	 * @return array  Aggregation data of items. Each entry will contain 'value', 'clock' and 'itemid' properties.
	 */
	public function getAggregatedValues(array $items, int $function, int $time_from, ?int $time_to = null): ?array {
		$is_numeric_aggregation = in_array($function, [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_SUM]);

		$items_valid = [];

		foreach ($items as $item) {
			$is_numeric_item = in_array($item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);

			if ($is_numeric_item || ($item['source'] === 'history' && !$is_numeric_aggregation)) {
				$items_valid[] = $item;
			}
		}

		$grouped_items = $this->getItemsGroupedByStorage($items_valid);

		$results = [];

		if (array_key_exists(ZBX_HISTORY_SOURCE_ELASTIC, $grouped_items)) {
			$results += $this->getAggregatedValuesFromElasticsearch($grouped_items[ZBX_HISTORY_SOURCE_ELASTIC],
				$function, $time_from, $time_to
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_CLICKHOUSE, $grouped_items)) {
			$results += $this->getAggregatedValuesFromClickhouse($grouped_items[ZBX_HISTORY_SOURCE_CLICKHOUSE],
				$function, $time_from, $time_to
			);
		}

		if (array_key_exists(ZBX_HISTORY_SOURCE_SQL, $grouped_items)) {
			$results += $this->getAggregatedValuesFromSql($grouped_items[ZBX_HISTORY_SOURCE_SQL], $function, $time_from,
				$time_to
			);
		}

		return $results;
	}

	/**
	 * Elasticsearch specific implementation of getAggregatedValues.
	 *
	 * @see CHistoryManager::getAggregatedValues
	 */
	private function getAggregatedValuesFromElasticsearch(array $items, int $function, int $time_from, ?int $time_to)
			: array {
		$aggs_value_clock = [
			'clock' => [
				'max' => [
					'field' => 'clock'
				]
			]
		];

		switch ($function) {
			case AGGREGATE_MIN:
				$aggs_value_clock['value'] = ['min' => ['field' => 'value']];
				break;
			case AGGREGATE_MAX:
				$aggs_value_clock['value'] = ['max' => ['field' => 'value']];
				break;
			case AGGREGATE_AVG:
				$aggs_value_clock['value'] = ['avg' => ['field' => 'value']];
				break;
			case AGGREGATE_COUNT:
				$aggs_value_clock['value'] = ['value_count' => ['field' => 'clock']];
				break;
			case AGGREGATE_SUM:
				$aggs_value_clock['value'] = ['sum' => ['field' => 'value']];
				break;
			case AGGREGATE_FIRST:
				$aggs_value_clock['value'] = [
					'top_hits' => [
						'size' => 1,
						'sort' => ['clock' => 'ASC', 'ns' => 'ASC']
					]
				];
				$aggs_value_clock['clock'] = ['min' => ['field' => 'clock']];
				break;
			case AGGREGATE_LAST:
				$aggs_value_clock['value'] = [
					'top_hits' => [
						'size' => 1,
						'sort' => ['clock' => 'DESC', 'ns' => 'DESC']
					]
				];
				break;
		}

		$itemids_by_value_type = [];

		foreach ($items as $item) {
			$itemids_by_value_type[$item['value_type']][$item['itemid']] = true;
		}

		$result = [];

		foreach (self::getElasticsearchEndpoints(array_keys($itemids_by_value_type)) as $value_type => $endpoint) {
			$query = [
				'query' => [
					'bool' => [
						'must' => [
							[
								'terms' => [
									'itemid' => array_keys($itemids_by_value_type[$value_type])
								]
							],
							[
								'range' => [
									'clock' => ['gte' => $time_from] + ($time_to !== null ? ['lte' => $time_to] : [])
								]
							]
						]
					]
				],
				'aggs' => [
					'group_by_itemid' => [
						'terms' => [
							'field' => 'itemid',
							'size' => count($itemids_by_value_type[$value_type])
						],
						'aggs' => $aggs_value_clock
					]
				],
				'size' => 0
			];

			$data = CElasticsearchHelper::query('POST', $endpoint, $query);

			if (array_key_exists('group_by_itemid', $data)) {
				foreach ($data['group_by_itemid']['buckets'] as $item_data) {
					$result[$item_data['key']] = [
						'itemid' => $item_data['key'],
						'value' => $function == AGGREGATE_FIRST || $function == AGGREGATE_LAST
							? (string) $item_data['value']['hits']['hits'][0]['_source']['value']
							: (string) $item_data['value']['value'],
						'clock' => (string) $item_data['clock']['value_as_string']
					];
				}
			}
		}

		return $result;
	}

	/**
	 * ClickHouse specific implementation of getAggregatedValues.
	 *
	 * @see CHistoryManager::getAggregatedValues
	 */
	private function getAggregatedValuesFromClickhouse(array $items, int $function, int $time_from,
			?int $time_to): array {
		$value_type_itemids = [];

		foreach ($items as $item) {
			$value_type_itemids[$item['value_type']][$item['itemid']] = true;
		}

		$sql_select = ['h.itemid'];

		switch ($function) {
			case AGGREGATE_MIN:
				$sql_select[] = 'min(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_MAX:
				$sql_select[] = 'max(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_AVG:
				$sql_select[] = 'avg(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_COUNT:
				$sql_select[] = 'count() AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_SUM:
				$sql_select[] = 'sum(h.value) AS value,max(h.clock_ns) AS ts';
				break;
			case AGGREGATE_FIRST:
				$sql_select[] = 'argMin(h.value, h.clock_ns) AS value,min(h.clock_ns) AS ts';
				break;
			case AGGREGATE_LAST:
				$sql_select[] = 'argMax(h.value, h.clock_ns) AS value,max(h.clock_ns) AS ts';
				break;
		}

		$result = [];

		foreach (self::getClickhouseEndpoints(array_keys($value_type_itemids)) as $value_type => $endpoint) {
			$hk_history = self::getClickhouseTtlByTypeId($value_type);

			$effective_time_from = $hk_history === null ? $time_from : max($time_from, time() - $hk_history);

			$period_condition = ' AND h.clock_ns>='.db_utc_to_datetime64($effective_time_from).
				($time_to !== null ? ' AND h.clock_ns<='.db_utc_to_datetime64($time_to) : '');

			$values = CClickhouseHelper::fetch(
				'SELECT s.itemid,s.value,toUnixTimestamp(s.ts) AS clock'.
				' FROM ('.
					'SELECT '.implode(',', $sql_select).
					' FROM '.self::getTableName($value_type).' h'.
					' WHERE '.dbConditionId('h.itemid', array_keys($value_type_itemids[$value_type])).
					$period_condition.
					' GROUP BY h.itemid'.
				') s',
				$endpoint['endpoint'], $endpoint['username'], $endpoint['password']
			);

			if ($values) {
				foreach ($values as $value) {
					$result[$value['itemid']] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * SQL specific implementation of getAggregatedValues.
	 *
	 * @see CHistoryManager::getAggregatedValues
	 */
	private function getAggregatedValuesFromSql(array $items, int $function, int $time_from, ?int $time_to): array {
		$time_from_by_source = ['history' => $time_from, 'trends' => $time_from];

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL) == 1) {
			$time_from_by_source['history'] = max($time_from_by_source['history'],
				time() - timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY)) + 1
			);
		}

		if (CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL) == 1) {
			$time_from_by_source['trends'] = max($time_from_by_source['trends'],
				time() - timeUnitToSeconds(CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS)) + 1
			);
		}

		$items_by_table = [];

		foreach ($items as $item) {
			$items_by_table[$item['value_type']][$item['source']][] = $item['itemid'];
		}

		$result = [];

		foreach ($items_by_table as $value_type => $items_by_source) {
			foreach ($items_by_source as $source => $itemids) {
				$sql_select = ['itemid'];

				if ($source === 'history') {
					switch ($function) {
						case AGGREGATE_MIN:
							$sql_select[] = 'MIN(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_MAX:
							$sql_select[] = 'MAX(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_AVG:
							$sql_select[] = 'AVG(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_COUNT:
							$sql_select[] = 'COUNT(*) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_SUM:
							$sql_select[] = 'SUM(value) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_FIRST:
							$sql_select[] = 'MIN(clock) AS clock';
							break;
						case AGGREGATE_LAST:
							$sql_select[] = 'MAX(clock) AS clock';
							break;
					}

					$sql_from = self::getTableName($value_type);
				}
				else {
					switch ($function) {
						case AGGREGATE_MIN:
							$sql_select[] = 'MIN(value_min) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_MAX:
							$sql_select[] = 'MAX(value_max) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_AVG:
							$sql_select[] = 'AVG(value_avg) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_COUNT:
							$sql_select[] = 'SUM(num) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_SUM:
							$sql_select[] = 'SUM(value_avg * num) AS value,MAX(clock) AS clock';
							break;
						case AGGREGATE_FIRST:
							$sql_select[] = 'MIN(clock) AS clock';
							break;
						case AGGREGATE_LAST:
							$sql_select[] = 'MAX(clock) AS clock';
							break;
					}

					$sql_from = $value_type == ITEM_VALUE_TYPE_UINT64 ? 'trends_uint' : 'trends';
				}

				$sql = 'SELECT '.implode(',', $sql_select).
					' FROM '.$sql_from.
					' WHERE '.dbConditionInt('itemid', $itemids).
						' AND clock>='.$time_from_by_source[$source].
						($time_to !== null ? ' AND clock<='.$time_to : '').
					' GROUP BY itemid';

				if ($function == AGGREGATE_FIRST || $function == AGGREGATE_LAST) {
					if ($source === 'history') {
						$sql_function = $function == AGGREGATE_FIRST ? 'MIN' : 'MAX';
						$sql =
							'SELECT h.itemid,h.value,h.clock'.
							' FROM '.$sql_from.' h'.
							' JOIN ('.
								'SELECT h2.itemid,h2.clock,'.$sql_function.'(h2.ns) AS ns'.
								' FROM '.$sql_from.' h2'.
									' JOIN ('.$sql.') s2 ON h2.itemid=s2.itemid AND h2.clock=s2.clock'.
								' WHERE h2.clock>='.$time_from_by_source[$source].
									($time_to !== null ? ' AND h2.clock<='.$time_to : '').
								' GROUP BY h2.itemid,h2.clock'.
							') s ON h.itemid=s.itemid AND h.clock=s.clock AND h.ns=s.ns'.
							' WHERE h.clock>='.$time_from_by_source[$source].
								($time_to !== null ? ' AND h.clock<='.$time_to : '');
					}
					else {
						$sql =
							'SELECT DISTINCT h.itemid,h.value_avg AS value,h.clock'.
							' FROM '.$sql_from.' h'.
								' JOIN ('.$sql.') s ON h.itemid=s.itemid AND h.clock=s.clock'.
							' WHERE h.clock>='.$time_from_by_source[$source].
								($time_to !== null ? ' AND h.clock<='.$time_to : '');
					}
				}

				$sql_result = DBselect($sql);

				while (($row = DBfetch($sql_result)) !== false) {
					$result[$row['itemid']] = $row;
				}

				if ($function == AGGREGATE_AVG && $value_type == ITEM_VALUE_TYPE_UINT64) {
					// Fix PostgreSQL number formatting (remove trailing zeros) in AVG aggregation on *_uint tables.

					foreach ($itemids as $itemid) {
						if (array_key_exists($itemid, $result)) {
							$result[$itemid]['value'] = (string) (float) $result[$itemid]['value'];
						}
					}
				}

				if ($function == AGGREGATE_COUNT) {
					foreach ($itemids as $itemid) {
						if (!array_key_exists($itemid, $result)) {
							$result[$itemid] = [
								'itemid' => (string) $itemid,
								'value' => '0',
								'clock' => (string) $time_from_by_source[$source]
							];
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Clear item history and trends by provided item IDs. History is deleted from both SQL and Elasticsearch.
	 *
	 * @param array $itemid_types  Key - itemid, value - value_type.
	 *
	 * @return bool
	 */
	public function deleteHistory(array $itemid_types) {
		self::initSources();

		return $this->deleteHistoryFromSql($itemid_types) && $this->deleteHistoryFromElasticsearch($itemid_types)
			&& $this->deleteHistoryFromClickhouse($itemid_types);
	}

	/**
	 * Elasticsearch specific implementation of deleteHistory.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	private function deleteHistoryFromElasticsearch(array $itemid_types) {
		$value_type_endpoints = self::getElasticsearchEndpoints($itemid_types, '_delete_by_query');

		if (!$value_type_endpoints) {
			return true;
		}

		$itemid_types = array_filter($itemid_types,
			static fn(int $value_type) => array_key_exists($value_type, $value_type_endpoints)
		);

		$query = [
			'query' => [
				'terms' => [
					'itemid' => array_keys($itemid_types)
				]
			]
		];

		foreach ($value_type_endpoints as $endpoint) {
			if (!CElasticsearchHelper::query('POST', $endpoint, $query)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * SQL specific implementation of deleteHistory.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	private function deleteHistoryFromSql(array $itemid_types) {
		global $DB;

		$item_tables = array_map([self::class, 'getTableName'], array_unique($itemid_types));
		$table_names = array_flip(self::getTableName());

		if (in_array(ITEM_VALUE_TYPE_UINT64, $itemid_types)) {
			$item_tables[] = 'trends_uint';
			$table_names['trends_uint'] = ITEM_VALUE_TYPE_UINT64;
		}

		if (in_array(ITEM_VALUE_TYPE_FLOAT, $itemid_types)) {
			$item_tables[] = 'trends';
			$table_names['trends'] = ITEM_VALUE_TYPE_FLOAT;
		}

		if ($DB['TYPE'] == ZBX_DB_POSTGRESQL && PostgresqlDbBackend::isCompressed($item_tables)) {
			error(_('Some of the history for this item may be compressed, deletion is not available.'));

			return false;
		}

		foreach ($item_tables as $table_name) {
			$itemids = array_keys(array_intersect($itemid_types, [(string) $table_names[$table_name]]));

			if (!DBexecute('DELETE FROM '.$table_name.' WHERE '.dbConditionInt('itemid', $itemids))) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clickhouse specific implementation of deleteHistory.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	private function deleteHistoryFromClickhouse(array $itemid_types): bool {
		$value_type_endpoints = self::getClickhouseEndpoints($itemid_types);

		if ($value_type_endpoints) {
			$itemid_types = array_filter($itemid_types,
				static fn(int $value_type) => array_key_exists($value_type, $value_type_endpoints)
			);
		}

		foreach ($value_type_endpoints as $value_type => $endpoint) {
			$query = 'DELETE FROM '.self::getTableName($value_type).' h'.
				' WHERE '.dbConditionId('h.itemid', array_keys($itemid_types));

			$result = CClickhouseHelper::execute($query,
				$endpoint['endpoint'], $endpoint['username'], $endpoint['password']
			);

			if ($result === false) {
				return false;
			}
		}

		return true;
	}

	public static function getTypeNameByTypeId(int $value_type): string {
		if (array_key_exists($value_type, self::$value_type_mapping)) {
			return self::$value_type_mapping[$value_type];
		}

		// Fallback to float.
		return self::$value_type_mapping[ITEM_VALUE_TYPE_FLOAT];
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
			'text' => ITEM_VALUE_TYPE_TEXT,
			'binary' => ITEM_VALUE_TYPE_BINARY,
			'json' => ITEM_VALUE_TYPE_JSON
		];

		if (array_key_exists($type_name, $mapping)) {
			return $mapping[$type_name];
		}

		// Fallback to float.
		return ITEM_VALUE_TYPE_FLOAT;
	}

	/**
	 * Get data source (sql, elastic or clickhouse) type based on value type id.
	 *
	 * @param int $value_type  Value type id.
	 *
	 * @return string  Data source type.
	 */
	public static function getDataSourceType(int $value_type): string {
		static $cache = [];

		self::initSources();

		if (!array_key_exists($value_type, $cache)) {
			$value_name = self::getTypeNameByTypeId($value_type);

			$cache[$value_type] = array_key_exists($value_name, self::$value_type_sources)
				? self::$value_type_sources[$value_name]['provider']
				: ZBX_HISTORY_SOURCE_SQL;
		}

		return $cache[$value_type];
	}

	private static function getSourceConfiguration(string $value_name): ?array {
		static $invalid = [];
		static $sources_data = [];

		if (array_key_exists($value_name, $sources_data)) {
			return $sources_data[$value_name];
		}

		// Additional check to limit error count produced by invalid configuration.
		if (array_key_exists($value_name, $invalid)) {
			return null;
		}

		$source = self::$value_type_sources[$value_name];

		if ($source['url'] === null) {
			$invalid[$value_name] = true;

			error(_s(self::$providers[$source['provider']].' URL is not set for type: %1$s.', $value_name));

			return null;
		}

		if ($source['provider'] == ZBX_HISTORY_SOURCE_CLICKHOUSE
				&& ($source['db'] === null || $source['username'] === null || $source['password'] === null)) {
			$invalid[$value_name] = true;

			error(_s(self::$providers[$source['provider']].' credentials are not set for type: %1$s.', $value_name));

			return null;
		}

		$sources_data[$value_name] = $source;

		return $source;
	}

	/**
	 * Get endpoints for Elasticsearch requests.
	 *
	 * @param array $value_types  Value type(s).
	 *
	 * @return array  Elasticsearch query endpoints.
	 */
	public static function getElasticsearchEndpoints(array $value_types, $action = '_search'): array {
		$indices = [];
		$endpoints = [];

		foreach (array_unique($value_types) as $type) {
			if (self::getDataSourceType($type) === ZBX_HISTORY_SOURCE_ELASTIC) {
				$indices[$type] = self::getTypeNameByTypeId($type);
			}
		}

		foreach ($indices as $type => $name) {
			if (($source_configuration = self::getSourceConfiguration($name)) !== null) {
				$endpoints[$type] = $source_configuration['url'].$name.'*/'.$action;
			}
		}

		return $endpoints;
	}

	public static function getClickhouseEndpoints(array $value_types): array {
		$endpoints = [];

		foreach (array_unique($value_types) as $type) {
			if (self::getDataSourceType($type) === ZBX_HISTORY_SOURCE_CLICKHOUSE) {
				$source_configuration = self::getSourceConfiguration(self::getTypeNameByTypeId($type));

				if ($source_configuration !== null) {
					$source_configuration['endpoint'] =
						$source_configuration['url'].'?database='.urlencode($source_configuration['db']);

					$endpoints[$type] = $source_configuration;
				}
			}
		}

		return $endpoints;
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
			ITEM_VALUE_TYPE_UINT64 => 'history_uint',
			ITEM_VALUE_TYPE_BINARY => 'history_bin',
			ITEM_VALUE_TYPE_JSON => 'history_json'
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

	private static function getClickhouseTtlByTypeId(int $value_type): ?int {
		$ttl = null;

		$dbversion_history_status = CSettingsHelper::getDbVersionHistoryStatus();

		foreach ($dbversion_history_status as $provider) {
			if ($provider['database'] !== self::$providers[ZBX_HISTORY_SOURCE_CLICKHOUSE]) {
				continue;
			}

			foreach ($provider['value_types'] as $type ) {
				if ($type['type'] == self::getTypeNameByTypeId($value_type) && array_key_exists('ttl', $type)) {
					$ttl = (int) $type['ttl'];

					break 2;
				}
			}
		}

		return $ttl;
	}

	public static function isHistoryProvidersDataValid($history_providers, &$error): bool {
		if (!is_array($history_providers) || !$history_providers) {
			$error = _s('Unsupported format of %1$s.', '$HISTORY_PROVIDERS');

			return false;
		}

		$providers_value_types = [];

		foreach ($history_providers as $provider) {
			if (!is_array($provider) || !array_key_exists('types', $provider) || !is_array($provider['types'])
					|| !$provider['types'] || !array_key_exists('provider', $provider)) {
				$error = _s('Unsupported format of %1$s.', '$HISTORY_PROVIDERS');

				return false;
			}

			if (!array_key_exists($provider['provider'], self::$providers)) {
				$error = _s('Unsupported history storage provider %1$s.', $provider['provider']);

				return false;
			}

			foreach ($provider['types'] as $type) {
				if (!in_array($type, self::$value_type_mapping)) {
					$error = _s('Unsupported value type %1$s for history provider %2$s.', $type, $provider['provider']);

					return false;
				}

				if (array_key_exists($provider['provider'], $providers_value_types)
						&& array_key_exists($type, $providers_value_types[$provider['provider']])) {
					$error = _s('Duplicate value type %1$s configured for history provider %2$s.',
						$type, $provider['provider']
					);

					return false;
				}

				$providers_value_types[$provider['provider']][$type] = true;
			}
		}

		return true;
	}

	public static function isHistoryDataValid($history, &$error): bool {
		if (!is_array($history) || !array_key_exists('types', $history) || !is_array($history['types'])
				|| !$history['types']) {
			$error = _s('Unsupported format of %1$s.', '$HISTORY');

			return false;
		}

		$value_types = [];

		foreach ($history['types'] as $type) {
			if (!in_array($type, self::$value_type_mapping)) {
				$error = _s('Unsupported value type %1$s for history provider %2$s.',
					$type, ZBX_HISTORY_SOURCE_ELASTIC
				);

				return false;
			}

			if (array_key_exists($type, $value_types)) {
				$error = _s('Duplicate value type %1$s configured for history provider %2$s.',
					$type, ZBX_HISTORY_SOURCE_ELASTIC
				);

				return false;
			}

			$value_types[$type] = true;
		}

		return true;
	}

	private function getClickhouseSelectFieldsByValueType(int $value_type, string $table_alias = 'h'): string {
		$fields = array_diff(array_keys(DB::getSchema(self::getTableName($value_type))['fields']), ['clock', 'ns']);

		foreach ($fields as &$field) {
			$field = $table_alias.'.'.$field;
		}
		unset($field);

		$sql_select = array_merge($fields, ['toUnixTimestamp('.$table_alias.'.clock_ns) AS clock',
			'toUnixTimestamp64Nano('.$table_alias.'.clock_ns) % 1000000000 AS ns'
		]);

		return implode(',', $sql_select);
	}
}
