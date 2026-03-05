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
 * A helper class for working with Clickhouse.
 */
class CClickhouseHelper {

	/**
	 * Query CickHouse
	 *
	 * @param string $query    SQL like query to be sent.
	 * @param array  $storage  ClickHouse storage configuration.
	 */
	public static function query(string $query, array $storage): ?array {
		$url = (new CUrl($storage['url']))
			->setArgument('database', $storage['db'])
			->getUrl();
		$options = [
			'http' => [
				'header'  => implode("\r\n", [
					'Content-Type: text/plain',
					'Authorization: Basic '.base64_encode($storage['username'].':'.$storage['password']),
					'X-ClickHouse-Format: JSON'
				]),
				'method'  => 'POST',
				'ignore_errors' => true,
				'content' => $query
			]
		];
		$result = null;
		$time_start = microtime(true);

		try {
			$result_raw = file_get_contents($url, false, stream_context_create($options));
			sscanf($http_response_header[0], 'HTTP/%*s %d', $http_code);
			$result = json_decode($result_raw, true);

			if ($http_code != 200) {
				$error_message = is_array($result) && array_key_exists('exception', $result)
					? $result['exception']
					: $result_raw;

				throw new Exception(_s('ClickHouse error: %1$s.', $error_message));
			}
		} catch (Throwable $error) {
			$result = null;

			error($error->getMessage(), true);
		}

		CProfiler::getInstance()->profileClickhouse(microtime(true) - $time_start, $options['http']['method'], $url,
			$query
		);

		return $result !== null ? $result['data'] : null;
	}

	/**
	 * Returns the last $limit history objects for the given items.
	 *
	 * @see CHistoryManager::getLastValues
	 */
	public static function getLastValues(array $value_type_itemids, int $limit, ?int $period): array {
		$results = [];

		foreach ($value_type_itemids as $type => $itemids) {
			$storage = Manager::History()->getStorageForValueType($type);
			$table = Manager::History()->getTableName($type);
			$hk_history = $storage['value_ttl'] ?? null;
			$effective_periods = array_filter([$period, $hk_history]);
			$value_type_fields = array_diff(Manager::History()->getOutputFields($type),
				['itemid', 'value', 'clock', 'ns']
			);
			$sql_clock = $effective_periods
				? ' AND clock_ns>=toDateTime64('.(time() - min($effective_periods)).',9)'
				: '';

			foreach (array_keys($itemids) as $itemid) {
				$values = self::query(
					'SELECT itemid,value,toUnixTimestamp(clock_ns) AS clock,toUnixTimestamp64Nano(clock_ns)%1000000000 AS ns'.
						($value_type_fields ? ','.implode(',', $value_type_fields) : '').
					' FROM '.$table.
					' WHERE '.dbConditionId('itemid', [$itemid]).
						$sql_clock.
					' ORDER BY clock_ns DESC'.
					' LIMIT '.$limit,
					$storage
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
	 * @see CHistoryManager::getValueAt
	 */
	public static function getValueAt(array $item, int $clock, int $ns): ?array {
		$storage = Manager::History()->getStorageForValueType($item['value_type']);
		$table = Manager::History()->getTableName($item['value_type']);
		$hk_history = $storage['value_ttl'] ?? null;

		if ($hk_history !== null && ($clock <= time() - $hk_history)) {
			return null;
		}

		$value_type_fields = array_diff(Manager::History()->getOutputFields($item['value_type']),
			['itemid', 'value', 'clock', 'ns']
		);
		$values = self::query(
			'SELECT itemid,value,toUnixTimestamp(clock_ns) AS clock,toUnixTimestamp64Nano(clock_ns)%1000000000 AS ns'.
				($value_type_fields ? ','.implode(',', $value_type_fields) : '').
			' FROM '.$table.
			' WHERE '.dbConditionId('itemid', [$item['itemid']]).
				' AND clock_ns<=fromUnixTimestamp64Nano('.$clock.'*1000000000+'.$ns.')'.
			' ORDER BY clock_ns DESC'.
			' LIMIT 1',
			$storage
		);

		return $values ? reset($values) : null;
	}

	/**
	 * Get value aggregation by interval within the specified time period.
	 *
	 * @see CHistoryManager::getAggregationByInterval
	 */
	public static function getAggregationByInterval(array $value_type_itemids, int $time_from, int $time_to,
			int $function, int $interval): array {
		$results = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$storage = Manager::History()->getStorageForValueType($value_type);
			$table = Manager::History()->getTableName($value_type);
			$hk_history = $storage['value_ttl'] ?? null;
			$_time_from = $hk_history === null ? $time_from : max($time_from, time() - $hk_history);

			$values = self::query(
				'SELECT itemid,value,toUnixTimestamp(tick) AS tick,toUnixTimestamp(ts) AS clock,'.
					'toUnixTimestamp64Nano(ts)%1000000000 AS ns'.
				' FROM ('.
					'SELECT itemid,toStartOfInterval(clock_ns, toIntervalSecond('.$interval.')) AS tick,'.
						match ($function) {
							AGGREGATE_MIN	=> 'min(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_MAX	=> 'max(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_AVG	=> 'avg(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_COUNT	=> 'count() AS value,max(clock_ns) AS ts',
							AGGREGATE_SUM	=> 'sum(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_FIRST	=> 'argMin(value, clock_ns) AS value,min(clock_ns) AS ts',
							AGGREGATE_LAST	=> 'argMax(value, clock_ns) AS value,max(clock_ns) AS ts'
						}.
					' FROM '.$table.
					' WHERE '.dbConditionId('itemid', array_keys($itemids)).
						' AND clock_ns BETWEEN toDateTime64('.$_time_from.',9)'.' AND toDateTime64('.$time_to.',9)'.
					' GROUP BY itemid,tick'.
					' ORDER BY itemid,tick'.
				')',
				$storage
			);

			if ($values) {
				foreach ($values as $value) {
					$results[$value['itemid']]['data'][] = $value;
				}
			}
		}

		foreach ($results as &$row) {
			$row['source'] = 'history';
		}
		unset($row);

		return $results;
	}

	/**
	 * Returns history value aggregation for graphs.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	public static function getGraphAggregationByWidth(array $value_type_itemids, int $time_from, int $time_to,
			?int $width): array {
		$index_field = '';
		$index_field_alias = '';

		if ($width !== null) {
			$index_field_alias = ',i';
			$seconds_count = $time_to - $time_from;
			$index_field = ',round('.$width.'*(toUnixTimestamp(clock_ns)-'.$time_from.')/'.$seconds_count.') AS i';
		}

		$results = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$storage = Manager::History()->getStorageForValueType($value_type);
			$table = Manager::History()->getTableName($value_type);
			$hk_history = $storage['value_ttl'] ?? null;
			$_time_from = $hk_history === null ? $time_from : max($time_from, time() - $hk_history);

			$values = self::query(
				'SELECT itemid,count,avg,min,max,toUnixTimestamp(ts) AS clock'.$index_field_alias.
				' FROM ('.
					'SELECT itemid,count() AS count,avg(value) AS avg,min(value) AS min,max(value) AS max,'.
						'max(clock_ns) as ts'.$index_field.
					' FROM '.$table.
					' WHERE '.dbConditionId('itemid', array_keys($itemids)).
						' AND clock_ns BETWEEN toDateTime64('.$_time_from.',9) AND toDateTime64('.$time_to.',9)'.
					' GROUP BY itemid'.$index_field_alias.
					' ORDER BY itemid'.$index_field_alias.
				' )',
				$storage
			);

			if ($values) {
				foreach ($values as $value) {
					$results[$value['itemid']]['data'][] = $value;
				}
			}
		}

		foreach ($results as &$row) {
			$row['source'] = 'history';
		}
		unset($row);

		return $results;
	}

	/**
	 * Return aggregater values by time range.
	 *
	 * @see CHistoryManager::getAggregatedValues
	 */
	public static function getAggregatedValues(array $value_type_itemids, int $function, int $time_from,
			?int $time_to): array {
		$result = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$storage = Manager::History()->getStorageForValueType($value_type);
			$table = Manager::History()->getTableName($value_type);
			$hk_history = $storage['value_ttl'] ?? null;
			$_time_from = $hk_history === null ? $time_from : max($time_from, time() - $hk_history);
			$sql_clock = $time_to === null
				? ' AND clock_ns>=toDateTime64('.$_time_from.',9)'
				: ' AND clock_ns BETWEEN toDateTime64('.$_time_from.',9)'.' AND toDateTime64('.$time_to.',9)';

			$values = self::query(
				'SELECT itemid,value,toUnixTimestamp(ts) AS clock'.
				' FROM ('.
					'SELECT itemid,'.
						match($function) {
							AGGREGATE_MIN	=> 'min(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_MAX	=> 'max(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_AVG	=> 'avg(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_COUNT	=> 'count() AS value,max(clock_ns) AS ts',
							AGGREGATE_SUM	=> 'sum(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_FIRST	=> 'argMin(value, clock_ns) AS value,min(clock_ns) AS ts',
							AGGREGATE_LAST	=> 'argMax(value, clock_ns) AS value,max(clock_ns) AS ts'
						}.
					' FROM '.$table.
					' WHERE '.dbConditionId('itemid', array_keys($itemids)).
						$sql_clock.
					' GROUP BY itemid'.
				')',
				$storage
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
	 * Clear item history and trends by provided item IDs.
	 *
	 * @see CHistoryManager::deleteHistory
	 */
	public static function delete(array $value_type_itemids): bool {
		$result = true;

		foreach ($value_type_itemids as $value_type => $itemids) {
			$storage = Manager::History()->getStorageForValueType($value_type);
			$table = Manager::History()->getTableName($value_type);
			$query = 'DELETE FROM '.$table.' WHERE '.dbConditionId('itemid', array_keys($itemids));

			if (!self::query($query, $storage)) {
				$result = false;
			}
		}

		return $result;
	}
}
