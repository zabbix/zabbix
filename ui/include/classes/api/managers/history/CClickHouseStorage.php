<?php declare(strict_types = 0);
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
 * History data storage class for ClickHouse.
 */
class CClickHouseStorage {

	public const PROVIDER_TYPE = ZBX_HISTORY_SOURCE_CLICKHOUSE;

	public const VALUE_TYPE_TABLE = [
		ITEM_VALUE_TYPE_LOG => 'history_log',
		ITEM_VALUE_TYPE_TEXT => 'history_text',
		ITEM_VALUE_TYPE_STR => 'history_str',
		ITEM_VALUE_TYPE_FLOAT => 'history',
		ITEM_VALUE_TYPE_UINT64 => 'history_uint',
		ITEM_VALUE_TYPE_JSON => 'history_json'
	];

	public const VALUE_TYPE_SCHEMA = [
		ITEM_VALUE_TYPE_LOG => [
			'itemid'	=> 'UInt64',
			'clock_ns'	=> 'DateTime64(9)',
			'value'		=> 'String',
			'source'	=> 'String',
			'severity'	=> 'Int32',
			'logeventid' => 'Int32',
			'timestamp' => 'Int64'
		],
		ITEM_VALUE_TYPE_TEXT => [
			'itemid'	=> 'UInt64',
			'clock_ns'	=> 'DateTime64(9)',
			'value'		=> 'String'
		],
		ITEM_VALUE_TYPE_STR => [
			'itemid'	=> 'UInt64',
			'clock_ns'	=> 'DateTime64(9)',
			'value'		=> 'String'
		],
		ITEM_VALUE_TYPE_FLOAT => [
			'itemid'	=> 'UInt64',
			'clock_ns'	=> 'DateTime64(9)',
			'value'		=> 'Float64'
		],
		ITEM_VALUE_TYPE_UINT64 => [
			'itemid'	=> 'UInt64',
			'clock_ns'	=> 'DateTime64(9)',
			'value'		=> 'UInt64'
		],
		ITEM_VALUE_TYPE_JSON => [
			'itemid'	=> 'UInt64',
			'clock_ns'	=> 'DateTime64(9)',
			'value'		=> 'JSON',
			'value_str'	=> 'String'
		]
	];

	private CUrl $url;
	private array $request_context_data = [];
	/**
	 * Last request HTTP error code. null when no errors.
	 * @var int|null
	 */
	private ?int $error_code = null;
	private ?string $error_message = null;

	/**
	 * Array of value type TTL values, key is value type and value is it TTL.
	 * Value is set to null when no TTL exists for specific value type.
	 *
	 * @var array $value_type_ttl
	 */
	private array $value_type_ttl = [];

	private const OP = ['ge' => '>=', 'gt' => '>', 'le' => '<=', 'lt' => '<'];

	public function __construct(array $config) {
		$this->value_type_ttl = $config['value_type_ttl'];
		$this->url = (new CUrl($config['url']))->setArgument('database', $config['db']);
		$this->request_context_data = [
			'http' => [
				'header'  => [
					'Authorization: Basic '.base64_encode($config['username'].':'.$config['password']),
					'X-ClickHouse-Format: JSON'
				],
				'user_agent' => 'Zabbix API '.ZABBIX_API_VERSION,
				'method'  => 'POST',
				'ignore_errors' => true,
				'content' => ''
			]
		];
	}

	/**
	 * Get error code for last query. When no errors null is returned.
	 */
	public function getErrorCode(): ?int {
		return $this->error_code;
	}

	/**
	 * Get error message for last query. When no errors null is returned.
	 */
	public function getErrorMessage(): ?string {
		return _s('ClickHouse error: %1$s.', $this->error_message);
	}

	/**
	 * Get array with value type as key and it TTL as value.
	 */
	public function getValueTypesTtl(): array {
		return $this->value_type_ttl;
	}

	/**
	 * Check is requested value type supported by storage instance.
	 *
	 * @param int $value_type
	 */
	public function isValueTypeSupported(int $value_type): bool {
		return array_key_exists($value_type, $this->value_type_ttl);
	}

	/**
	 * Query storage history data using API like options. Return null on error.
	 *
	 * @see CHistory::get()
	 * @param array $options
	 */
	public function select(array $options): ?array {
		$query_parts = $this->getQueryPartsFromOptions($options);
		$query = $this->buildQueryFromParts($query_parts);

		return $this->query($query, $query_parts['param']);
	}

	/**
	 * Query storage trends data using API like options. Return null on error.
	 *
	 * @param array $options
	 */
	public function selectTrends(array $options): ?array {
		if ($options['countOutput']) {
			$query_parts = $this->getQueryPartsFromOptions(['output' => ['itemid'], 'countOutput' => false] + $options);
			$query_parts['group'] = ['itemid', 'toStartOfHour(clock_ns)'];
			$query = 'SELECT count() AS rowscount FROM ('.$this->buildQueryFromParts($query_parts).')';

			return $this->query($query, $query_parts['param']);
		}

		// Create query parts without SELECT
		$query_parts = $this->getQueryPartsFromOptions(['output' => []] + $options);

		// Add columns required for GROUP and ORDER
		$output = array_merge($options['output'], ['itemid', 'clock']);
		$query_parts['order'] = ['clock ASC'];
		$query_parts['group'] = ['itemid', 'toStartOfHour(clock_ns)'];

		$query_parts['select'] = array_intersect_key([
			'itemid' => 'itemid',
			'clock' => 'toUnixTimestamp(toStartOfHour(clock_ns))',
			'num' => 'count()',
			'value_min' => 'min(value)',
			'value_avg' => 'avg(value)',
			'value_max' => 'max(value)'
		], array_flip($output));

		$query = $this->buildQueryFromParts($query_parts);
		$rows = $this->query($query, $query_parts['param']);

		if (is_array($rows) && array_diff($output, $options['output'])) {
			$output = array_flip($options['output']);
			foreach ($rows as &$row) {
				$row = array_intersect_key($row, $output);
			}
			unset($row);
		}

		return $rows;
	}

	/**
	 * @see CHistoryManager::deleteHistory
	 */
	public function deleteHistory(array $itemid_value_type): bool {
		foreach (array_keys($this->value_type_ttl) as $value_type) {
			$itemids = array_keys($itemid_value_type, $value_type);

			if (!$itemids) {
				continue;
			}

			$resource = $this->query(
				'ALTER TABLE '.$this->getTableName($value_type).' DELETE WHERE itemid IN {itemids:Array(UInt64)}',
				[
					'UInt64' => [
						'itemids' => $itemids
					]
				]
			);

			if ($resource === null) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @see CHistoryManager::getItemsHavingValues
	 */
	public function getItemsHavingValues(array $itemids_by_value_type, ?int $period): array {
		$result = [];
		$time = time();

		foreach ($itemids_by_value_type as $value_type => $itemids) {
			$time_from = $period;

			if ($this->value_type_ttl[$value_type] !== null) {
				$time_from = $period !== null
					? min($period, $this->value_type_ttl[$value_type])
					: $this->value_type_ttl[$value_type];
			}

			if ($time_from !== null) {
				$time_from = $time - $time_from;
			}

			$resource = $this->select([
				'output' => ['itemid'],
				'itemids' => array_keys($itemids),
				'history' => $value_type,
				'clock' => ['gt' => $time_from],
				'limit_by' => [1, 'itemid']
			]);

			if ($resource === null) {
				break;
			}

			$result += array_column($resource, 'itemid', 'itemid');
		}

		return $result;
	}

	/**
	 * @see CHistoryManager::getLastValues
	 */
	public function getLastValues(array $itemids_by_value_type, int $limit, ?int $period, ?int $length): array {
		$result = [];
		$time = time();

		foreach ($itemids_by_value_type as $value_type => $itemids) {
			$time_from = $period;

			if ($this->value_type_ttl[$value_type] !== null) {
				$time_from = $period !== null
					? min($period, $this->value_type_ttl[$value_type])
					: $this->value_type_ttl[$value_type];
			}

			if ($time_from !== null) {
				$time_from = $time - $time_from;
			}

			$fields = array_keys(self::VALUE_TYPE_SCHEMA[$value_type]);
			$fields = array_diff($fields, ['clock_ns']);
			array_push($fields, 'clock', 'ns');

			$resource = $this->select([
				'output' => $fields,
				'maxValueSize' => $length,
				'itemids' => array_keys($itemids),
				'history' => $value_type,
				'clock' => ['gt' => $time_from],
				'sortfield' => ['clock'],
				'sortorder' => ZBX_SORT_DOWN,
				'limit_by' => [$limit, 'itemid']
			]);

			if ($resource === null) {
				break;
			}

			foreach ($resource as $row) {
				$result[$row['itemid']][] = $row;
			}
		}

		return $result;
	}

	/**
	 * @see CHistoryManager::getValueAt
	 */
	public function getValueAt(array $item, int $clock, int $ns): ?array {
		$time = time();
		$value_type = $item['value_type'];

		if ($this->value_type_ttl[$value_type] !== null && $clock <= $time - $this->value_type_ttl[$value_type]) {
			return null;
		}

		$time_from = $clock - timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::HISTORY_PERIOD));

		if ($this->value_type_ttl[$value_type] !== null) {
			$time_from = max($time_from, $time - $this->value_type_ttl[$value_type]);
		}

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$value_type]);
		$fields = array_diff($fields, ['clock_ns']);
		array_push($fields, 'clock', 'ns');

		return $this->select([
			'output' => $fields,
			'itemids' => [$item['itemid']],
			'history' => $value_type,
			'clock' => ['gt' => $time_from],
			'clock_ns' => ['le' => ['clock' => $clock, 'ns' => $ns]],
			'sortfield' => ['clock_ns'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		])[0] ?? null;
	}

	/**
	 * @see CHistoryManager::getAggregationByInterval
	 */
	public function getAggregationByInterval(array $itemids_by_value_type, int $time_from, int $time_to, int $function,
			int $interval): array {
		$result = [];
		$time = time();

		foreach ($itemids_by_value_type as $value_type => $itemids) {
			$itemids = array_keys($itemids);
			$_time_from = $this->value_type_ttl[$value_type] !== null
				? max($time_from, $time - $this->value_type_ttl[$value_type] + 1)
				: $time_from;

			$resource = $_time_from <= $time_to
				? $this->query(
					'SELECT itemid,'.
						'toUnixTimestamp(toStartOfInterval(clock_ns,toIntervalSecond({interval:UInt64}))) AS tick,'.
						match ($function) {
							AGGREGATE_MIN => 'min(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
							AGGREGATE_MAX => 'max(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
							AGGREGATE_AVG => 'avg(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock,'.
								'count() AS num',
							AGGREGATE_COUNT => 'count() AS value,toUnixTimestamp(max(clock_ns)) AS clock',
							AGGREGATE_SUM => 'sum(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
							AGGREGATE_FIRST => 'argMin(value,clock_ns) AS value,'.
								'toUnixTimestamp(min(clock_ns)) AS clock,'.
								'toUnixTimestamp64Nano(min(clock_ns))%1000000000 AS ns',
							AGGREGATE_LAST => 'argMax(value,clock_ns) AS value,'.
								'toUnixTimestamp(max(clock_ns)) AS clock,'.
								'toUnixTimestamp64Nano(max(clock_ns))%1000000000 AS ns'
						}.
					' FROM '.$this->getTableName($value_type).
					' PREWHERE itemid IN {pre_itemids:Array(UInt64)}'.
					' WHERE clock_ns BETWEEN toDateTime64({clock_ge:UInt64},9)'.
						' AND addNanoseconds(toDateTime64({clock_le:UInt64},9),999999999)'.
					' GROUP BY itemid,tick'.
					' ORDER BY itemid,tick',
					[
						'UInt64' => [
							'pre_itemids' => $itemids,
							'clock_ge' => $_time_from,
							'clock_le' => $time_to,
							'interval' => $interval
						]
					]
				)
				: [];

			if ($resource === null) {
				continue;
			}

			foreach ($resource as $row) {
				$result[$row['itemid']]['data'][] = $row;
			}

			if ($function == AGGREGATE_COUNT) {
				foreach ($itemids as $itemid) {
					$db_ticks = array_key_exists($itemid, $result)
						? array_column($result[$itemid]['data'], 'tick', 'tick')
						: [];

					for ($tick = $_time_from - $time_from % $interval; $tick <= $time_to; $tick += $interval) {
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

		foreach ($result as $itemid => $row) {
			$result[$itemid] = ['source' => 'history'] + $row;
		}

		return $result;
	}

	/**
	 * Returns history value aggregation for graphs.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	public function getGraphAggregationByWidth(array $itemids_by_value_type, int $time_from, int $time_to,
			?int $width): array {
		$sql_select_extra = '';
		$sql_params_extra = [];
		$group_by = 'itemid';
		$order_by = '';

		if ($width !== null) {
			$sql_select_extra =
				',round({width:UInt64}*(toUnixTimestamp(clock_ns)-{time_from:UInt64})/{period:UInt64}) AS i';
			$sql_params_extra = ['width' => $width, 'period' => $time_to - $time_from, 'time_from' => $time_from];

			$group_by .= ',i';
			$order_by .= ' ORDER BY itemid,i';
		}

		$result = [];
		$time = time();

		foreach ($itemids_by_value_type as $value_type => $itemids) {
			$itemids = array_keys($itemids);
			$_time_from = $this->value_type_ttl[$value_type] !== null
				? max($time_from, $time - $this->value_type_ttl[$value_type] + 1)
				: $time_from;

			foreach ($itemids as $itemid) {
				$result[$itemid] = ['source' => 'history', 'data' => []];
			}

			$resource = $_time_from <= $time_to
				? $this->query(
					'SELECT itemid,count() AS count,avg(value) AS avg,min(value) AS min,max(value) AS max'.
						$sql_select_extra.',toUnixTimestamp(max(clock_ns)) as clock'.
					' FROM '.$this->getTableName($value_type).
					' PREWHERE itemid IN {pre_itemids:Array(UInt64)}'.
					' WHERE clock_ns>=toDateTime64({clock_ge:UInt64},9)'.
						' AND clock_ns<=addNanoseconds(toDateTime64({clock_le:UInt64},9),999999999)'.
					' GROUP BY '.$group_by.
					$order_by,
					[
						'UInt64' => [
							'pre_itemids' => $itemids,
							'clock_ge' => $_time_from,
							'clock_le' => $time_to
						] + $sql_params_extra
					]
				)
				: [];

			if ($resource === null) {
				continue;
			}

			foreach ($resource as $row) {
				$result[$row['itemid']]['data'][] = $row;
			}
		}

		return $result;
	}

	/**
	 * @see CHistoryManager::getAggregatedValues
	 */
	public function getAggregatedValues(array $value_type_itemids, int $function, int $time_from,
			?int $time_to): array {
		$sql_where_extra = '';
		$sql_params_extra = [];

		if ($time_to !== null) {
			$sql_where_extra = ' AND clock_ns<=addNanoseconds(toDateTime64({clock_le:UInt64},9),999999999)';
			$sql_params_extra = ['clock_le' => $time_to];
		}

		$result = [];
		$time = time();

		foreach ($value_type_itemids as $value_type => $itemids) {
			$itemids = array_keys($itemids);
			$_time_from = $this->value_type_ttl[$value_type] !== null
				? max($time_from, $time - $this->value_type_ttl[$value_type] + 1)
				: $time_from;

			$resource = $this->query(
				'SELECT itemid,'.
					match ($function) {
						AGGREGATE_MIN => 'min(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
						AGGREGATE_MAX => 'max(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
						AGGREGATE_AVG => 'avg(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
						AGGREGATE_COUNT => 'count() AS value,toUnixTimestamp(max(clock_ns)) AS clock',
						AGGREGATE_SUM => 'sum(value) AS value,toUnixTimestamp(max(clock_ns)) AS clock',
						AGGREGATE_FIRST => 'argMin(value,clock_ns) AS value,toUnixTimestamp(min(clock_ns)) AS clock',
						AGGREGATE_LAST => 'argMax(value,clock_ns) AS value,toUnixTimestamp(max(clock_ns)) AS clock'
					}.
				' FROM '.$this->getTableName($value_type).
				' WHERE itemid IN {itemids:Array(UInt64)}'.
					' AND clock_ns>=toDateTime64({clock_ge:UInt64},9)'.$sql_where_extra.
				' GROUP BY itemid',
				[
					'UInt64' => [
						'itemids' => $itemids,
						'clock_ge' => $_time_from
					] + $sql_params_extra
				]
			);

			if ($resource === null) {
				continue;
			}

			foreach ($resource as $row) {
				$result[$row['itemid']] = $row;
			}

			if ($function == AGGREGATE_COUNT) {
				foreach ($itemids as $itemid) {
					if (!array_key_exists($itemid, $result)) {
						$result[$itemid] = [
							'itemid' => (string) $itemid,
							'value' => '0',
							'clock' => (string) $_time_from
						];
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Get result of HTTP query to ClickHouse.
	 *
	 * @param string $query   Query string.
	 * @param array  $params  Array of query parameters grouped by column value type.
	 */
	private function query(string $query, array $params = []): ?array {
		$result = null;
		$this->error_code = null;
		$this->error_message = null;
		$time_start = microtime(true);

		$boundary = bin2hex(random_bytes(16));
		$content = '--'.$boundary."\r\n".
			'Content-Disposition: form-data; name="query"'."\r\n\r\n".
			$query."\r\n";

		foreach ($params as $column_type => $columns_values) {
			foreach ($columns_values as $column => $value) {
				if ($column_type === 'String' && is_array($value)) {
					$value = array_map(static fn ($v) => '\''.addcslashes((string) $v, '\\\'').'\'', $value);
				}

				$value = is_array($value) ? '['.implode(',', array_map('strval', $value)).']' : (string) $value;

				$content .= '--'.$boundary."\r\n".
					'Content-Disposition: form-data; name="param_'.$column.'"'."\r\n\r\n".$value."\r\n";
			}
		}

		$content .= '--'.$boundary.'--'."\r\n";
		$stream_context = $this->request_context_data;
		$stream_context['http']['content'] = $content;
		$stream_context['http']['header'] = implode("\r\n", array_merge($stream_context['http']['header'], [
			'Content-Type: multipart/form-data; boundary='.$boundary,
			'Content-Length: '.strlen($stream_context['http']['content'])
		]));
		$result_raw = file_get_contents($this->url->getUrl(), false, stream_context_create($stream_context));
		$result = $result_raw === false ? ['exception' => error_get_last()['message']] : json_decode($result_raw, true);
		$http_code = 500;

		// The variable $http_response_header is defined only when file_get_contents succeeds.
		if (isset($http_response_header)) {
			sscanf($http_response_header[0], 'HTTP/%*s %d', $http_code);
		}

		if (!is_array($result) || array_key_exists('exception', $result)) {
			$this->error_code = $http_code;
			$this->error_message = is_array($result) && array_key_exists('exception', $result)
				? $result['exception']
				: $result_raw;
			$result = null;
		}
		else {
			$result = $result['data'];

			foreach ($result as &$row) {
				$row = array_map('strval', $row);
			}
			unset($row);
		}

		CProfiler::getInstance()->profileClickHouse(microtime(true) - $time_start,
			json_encode(['query' => $query] + $params)
		);

		return $result;
	}

	/**
	 * Create query string from $sql_parts.
	 *
	 * @param array $sql_parts
	 */
	private function buildQueryFromParts(array $sql_parts): string {
		$select = implode(',', array_map(
			static fn ($field, $expression) => $field === $expression ? $field : $expression.' AS '.$field,
			array_keys($sql_parts['select']),
			$sql_parts['select']
		));

		return
			'SELECT '.$select.
			' FROM '.implode(',', $sql_parts['from']).
			($sql_parts['prewhere'] ? ' PREWHERE '.implode(' AND ', $sql_parts['prewhere']) : '').
			($sql_parts['where'] ? ' WHERE '.implode(' AND ', $sql_parts['where']) : '').
			($sql_parts['group'] ? ' GROUP BY '.implode(',', $sql_parts['group']) : '').
			($sql_parts['order'] ? ' ORDER BY '.implode(',', $sql_parts['order']) : '').
			($sql_parts['limit'] ? ' LIMIT '.$sql_parts['limit'] : '').
			($sql_parts['limit_by']
				? ' LIMIT '.array_shift($sql_parts['limit_by']).' BY '.implode(',', $sql_parts['limit_by'])
				: ''
			);
	}

	/**
	 * Create sql_parts array from API options array.
	 * Does not support 'group' options.
	 *
	 * @param array $options
	 * @param int   $options['history']  Item value type, required.
	 */
	private function getQueryPartsFromOptions(array $options): array {
		$options = array_replace([
			'output' => ['itemid'],
			'itemids' => null,
			'clock' => null,
			'clock_ns' => null,
			'filter' => null,
			'search' => null,
			'searchByAny' => false,
			'startSearch' => false,
			'excludeSearch' => false,
			'searchWildcardsEnabled' => false,
			'sortfield' => null,
			'sortorder' => null,
			'limit' => null,
			'limit_by' => null,
			'maxValueSize' => null,
			'countOutput' => false
		], $options);

		$sql_parts = [
			'select'	=> [],
			'from'		=> [$this->getTableName($options['history'])],
			'prewhere'	=> [],
			'where'		=> [],
			'order'		=> [],
			'group'		=> [],
			'limit'		=> $options['limit'],
			'limit_by'	=> $options['limit_by'],
			'param'		=> []
		];

		if ($options['itemids'] !== null) {
			$sql_parts['param']['UInt64']['pre_itemids'] = array_unique((array) $options['itemids']);
			$sql_parts['prewhere']['pre_itemids'] = is_array($options['itemids'])
				? 'itemid IN {pre_itemids:Array(UInt64)}'
				: 'itemid={pre_itemids:UInt64}';
		}

		if ($options['clock'] !== null) {
			foreach ($options['clock'] as $op => $value) {
				if ($value !== null) {
					$param = 'pre_clock_'.$op;
					$sql_parts['param']['UInt64'][$param] = $value;
					$sql_parts['prewhere'][$param] = 'clock_ns'.self::OP[$op].'toDateTime64({'.$param.':UInt64},9)';
				}
			}
		}

		if ($options['clock_ns'] !== null) {
			foreach ($options['clock_ns'] as $op => $value) {
				$param = 'pre_clock_ns_'.$op;
				$sql_parts['param']['UInt64'][$param.'_clock'] = $value['clock'];
				$sql_parts['param']['UInt64'][$param.'_ns'] = $value['ns'];
				$sql_parts['prewhere'][$param] = 'clock_ns'.self::OP[$op].
					'addNanoseconds(toDateTime64({'.$param.'_clock'.':UInt64},9),{'.$param.'_ns'.':UInt64})';
			}
		}

		if ($options['filter'] !== null && $options['filter']) {
			$sql_parts = $this->addQueryFilterOptions($sql_parts, $options);
		}

		if ($options['search'] !== null && $options['search']) {
			$sql_parts = $this->addQuerySearchOptions($sql_parts, $options);
		}

		if ($options['countOutput']) {
			$sql_parts['select'] = ['rowscount' => 'count()'];

			return $sql_parts;
		}

		if ($options['sortfield'] !== null) {
			$sql_parts = $this->addQuerySortOptions($sql_parts, $options);
		}

		if (is_array($options['output']) && $options['output']) {
			$sql_parts = $this->addQueryOutputOptions($sql_parts, $options);
		}

		return $sql_parts;
	}

	/**
	 * Add to sql_parts changes according to output related options.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 * @param int   $options['history']       Item value type, required.
	 * @param ?int  $options['maxValueSize']  Value max length, required.
	 */
	private function addQueryOutputOptions(array $sql_parts, array $options): array {
		$value = $options['maxValueSize'] !== null && $options['history'] == ITEM_VALUE_TYPE_JSON
			? 'substringUTF8(value_str!=\'\'?value_str:toString(value),1,'.$options['maxValueSize'].')'
			: 'value';

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$options['history']]);
		array_push($fields, 'clock', 'ns');
		foreach (array_intersect($options['output'], $fields) as $field) {
			$sql_parts['select'][$field] = match ($field) {
				'clock' => 'toUnixTimestamp(clock_ns)',
				'ns' => 'toUnixTimestamp64Nano(clock_ns)%1000000000',
				'value' => $value,
				default => $field
			};
		}

		return $sql_parts;
	}

	/**
	 * Add to sql_parts changes according to sortorder and sortfield related options.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 * @param int   $options['history']    Item value type, required.
	 * @param mixed $options['sortfield']  Sorting field, required.
	 * @param mixed $options['sortorder']  Sorting order, required.
	 */
	private function addQuerySortOptions(array $sql_parts, array $options): array {
		$sortorder = (array) $options['sortorder'] + array_fill_keys(
			array_keys($options['sortfield']),
			is_string($options['sortorder']) ? $options['sortorder'] : ZBX_SORT_UP
		);

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$options['history']]);
		$fields = array_diff($fields, ['value_str']);
		$fields[] = 'clock';
		foreach ($options['sortfield'] as $i => $field) {
			if (!in_array($field, $fields)) {
				continue;
			}

			$field = $field === 'clock' ? 'clock_ns' : $field;
			$sql_parts['order'][$field] = $field.($sortorder[$i] === ZBX_SORT_DOWN ? ' '.ZBX_SORT_DOWN : '');
		}

		return $sql_parts;
	}

	/**
	 * Add API filter options to sql parts. Array $sql_parts is modified by reference.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 * @param int   $options['history']      Item value type, required.
	 * @param bool  $options['searchByAny']  Flag, required.
	 */
	private function addQueryFilterOptions(array $sql_parts, array $options): array {
		$filter = [];

		$fields = self::VALUE_TYPE_SCHEMA[$options['history']] + ['clock' => 'UInt64', 'ns' => 'Int32'];
		foreach (array_intersect_key($fields, $options['filter']) as $field => $param_type) {
			$values = [];

			switch ($param_type) {
				case 'Int32':
					foreach ($options['filter'][$field] as $val) {
						if (!is_int($val) && (!is_string($val) || !preg_match('/^'.ZBX_PREG_INT.'$/', $val))) {
							continue;
						}

						if ($val < ZBX_MIN_INT32 || $val > ZBX_MAX_INT32) {
							continue;
						}

						$values[] = $val;
					}
					break;

				case 'Int64':
					foreach ($options['filter'][$field] as $val) {
						if (!is_int($val) && (!is_string($val) || !preg_match('/^'.ZBX_PREG_INT.'$/', $val))) {
							continue;
						}

						if (bccomp((string) $val, ZBX_MIN_INT64) < 0 || bccomp((string) $val, ZBX_MAX_INT64) > 0) {
							continue;
						}

						$values[] = $val;
					}
					break;

				case 'UInt64':
					foreach ($options['filter'][$field] as $val) {
						if (!is_int($val) && (!is_string($val) || !ctype_digit($val))) {
							continue;
						}

						if ($val < 0 || bccomp((string) $val, ZBX_MAX_UINT64) > 0) {
							continue;
						}

						$values[] = $val;
					}
					break;

				case 'Float64':
					foreach ($options['filter'][$field] as $val) {
						if (!is_numeric($val)) {
							continue;
						}

						$values[] = $val;
					}
					break;

				default:
					$values = $options['filter'][$field];
			}

			$sql_parts['param'][$param_type]['filter_'.$field] = $values;
			$filter[$field] = match ($field) {
				'clock' => 'toUnixTimestamp(clock_ns)',
				'ns' => 'toUnixTimestamp64Nano(clock_ns)%1000000000',
				default => $field
			};
			$filter[$field] .= ' IN {filter_'.$field.':Array('.$param_type.')}';
		}

		if (!$filter) {
			return $sql_parts;
		}

		$sql_parts['where']['filter'] = implode($options['searchByAny'] ? ' OR ' : ' AND ', $filter);

		if ($options['searchByAny'] && count($filter) > 1) {
			$sql_parts['where']['filter'] = '('.$sql_parts['where']['filter'].')';
		}

		return $sql_parts;
	}

	/**
	 * Add string search filtering options to query.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 * @param int   $options['history']                 Item value type, required.
	 * @param bool  $options['searchByAny']             Flag, required.
	 * @param bool  $options['startSearch']             Flag, required.
	 * @param bool  $options['excludeSearch']           Flag, required.
	 * @param bool  $options['searchWildcardsEnabled']  Flag, required.
	 */
	private function addQuerySearchOptions(array $sql_parts, array $options): array {
		$search = [];
		$fields = self::VALUE_TYPE_SCHEMA[$options['history']];
		$prefix = $options['startSearch'] || $options['searchWildcardsEnabled'] ? '' : '%';
		$suffix = $options['searchWildcardsEnabled'] ? '' : '%';
		$escape_pairs = ($options['searchWildcardsEnabled'] ? ['*' => '%'] : []) +
			['%' => '\\%', '_' => '\\_', '\\' => '\\\\'];

		foreach (array_intersect_key($fields, $options['search']) as $field => $param_type) {
			$patterns = $options['search'][$field];

			foreach ($patterns as &$pattern) {
				$pattern = $prefix.strtr($pattern, $escape_pairs).$suffix;
			}
			unset($pattern);

			$sql_parts['param'][$param_type]['search_'.$field] = $patterns;
			$search[$field] = $options['searchByAny'] === true
				? 'arrayExists(p -> '.$field.' ILIKE p, {search_'.$field.':Array(String)})'
				: 'arrayAll(p -> '.$field.' ILIKE p, {search_'.$field.':Array(String)})';
		}

		if (!$search) {
			return $sql_parts;
		}

		$sql_parts['where']['search'] = implode($options['searchByAny'] ? ' OR ' : ' AND ', $search);

		if (($options['searchByAny'] || $options['excludeSearch']) && count($search) > 1) {
			$sql_parts['where']['search'] = '('.$sql_parts['where']['search'].')';
		}

		if ($options['excludeSearch']) {
			$sql_parts['where']['search'] = 'NOT '.$sql_parts['where']['search'];
		}

		return $sql_parts;
	}

	/**
	 * Get storage table name for specified value type.
	 *
	 * @param int $value_type
	 */
	private function getTableName(int $value_type): string {
		return static::VALUE_TYPE_TABLE[$value_type];
	}
}
