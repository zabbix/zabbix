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

	public function __construct(array $config, array $value_type_ttl) {
		$_value_type_ttl = array_fill_keys($config['types'], null);
		$this->value_type_ttl = array_replace($_value_type_ttl, array_intersect_key($value_type_ttl, $_value_type_ttl));
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
		return $this->error_message;
	}

	/**
	 * Get storage table name for specified value type.
	 *
	 * @param int $value_type
	 */
	public function getTableName(int $value_type): string {
		return static::VALUE_TYPE_TABLE[$value_type];
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
			$options['output'] = ['itemid'];
			$options['countOutput'] = false;
			$query_parts = $this->getQueryPartsFromOptions($options);
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
	public function delete(array $itemid_value_type): bool {
		foreach (array_keys($this->value_type_ttl) as $value_type) {
			$itemids = array_keys($itemid_value_type, $value_type);

			if (!$itemids) {
				continue;
			}

			$table = $this->getTableName($value_type);
			$result = $this->query(
				'ALTER TABLE '.$table.' DELETE WHERE itemid IN {itemids:Array(UInt64)}',
				[
					'UInt64' => [
						'itemids' => $itemids
					]
				]
			);

			if ($result === null) {
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
				$time_from = $time - $time_from + 1;
			}

			$resource = $this->select([
				'output' => ['itemid'],
				'itemids' => array_keys($itemids),
				'history' => $value_type,
				'time_from' => $time_from,
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
				$time_from = $time - $time_from + 1;
			}

			$fields = array_keys(self::VALUE_TYPE_SCHEMA[$value_type]);
			$fields = array_diff($fields, ['clock_ns']);
			array_push($fields, 'clock', 'ns');

			$resource = $this->select([
				'output' => $fields,
				'maxValueSize' => $length,
				'itemids' => array_keys($itemids),
				'history' => $value_type,
				'time_from' => $time_from,
				'sortfield' => 'clock',
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
			$time_from = max($time_from, $time - $this->value_type_ttl[$value_type] + 1);
		}

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$value_type]);
		$fields = array_diff($fields, ['clock_ns']);
		array_push($fields, 'clock', 'ns');

		return $this->select([
			'output' => $fields,
			'itemids' => [$item['itemid']],
			'history' => $value_type,
			'time_from' => $time_from,
			'clock_ns_till' => ['clock' => $clock, 'ns' => $ns],
			'sortfield' => 'clock_ns',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		])[0] ?? null;
	}

	/**
	 * @see CHistoryManager::getAggregationByInterval
	 */
	public function getAggregationByInterval(array $value_type_itemids, int $time_from, int $time_to, int $function,
			int $interval): array {
		$result = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$table = $this->getTableName($value_type);
			$values = $this->query(
				'SELECT itemid,value,toUnixTimestamp(tick) AS tick,toUnixTimestamp(ts) AS clock,'.
					'toUnixTimestamp64Nano(ts)%1000000000 AS ns'.
					($function == AGGREGATE_AVG ? ',num': '').
				' FROM ('.
					'SELECT itemid,toStartOfInterval(clock_ns, toIntervalSecond({interval:UInt64})) AS tick,'.
						match ($function) {
							AGGREGATE_MIN	=> 'min(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_MAX	=> 'max(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_AVG	=> 'avg(value) AS value,max(clock_ns) AS ts,count(*) AS num',
							AGGREGATE_COUNT	=> 'count() AS value,max(clock_ns) AS ts',
							AGGREGATE_SUM	=> 'sum(value) AS value,max(clock_ns) AS ts',
							AGGREGATE_FIRST	=> 'argMin(value, clock_ns) AS value,min(clock_ns) AS ts',
							AGGREGATE_LAST	=> 'argMax(value, clock_ns) AS value,max(clock_ns) AS ts'
						}.
					' FROM '.$table.
					' PREWHERE itemid IN {pre_itemids:Array(UInt64)}'.
					' WHERE clock_ns>=toDateTime64({time_gte:UInt64},9)'.
						' AND clock_ns<=addNanoseconds(toDateTime64({time_lte:UInt64},9),999999999)'.
					' GROUP BY itemid,tick'.
					' ORDER BY itemid,tick'.
				')',
				[
					'UInt64' => [
						'pre_itemids' => array_keys($itemids),
						'time_gte' => $this->getTtlLimitedTimestamp($value_type, $time_from),
						'time_lte' => $time_to,
						'interval' => $interval
					]
				]
			);

			if ($values === null || !$values) {
				continue;
			}

			$result += array_fill_keys(array_column($values, 'itemid', 'itemid'),
				['data' => [], 'source' => 'history']
			);

			foreach ($values as $value) {
				$result[$value['itemid']]['data'][] = $value;
			}
		}

		return $result;
	}

	/**
	 * Returns history value aggregation for graphs.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	public function getGraphAggregationByWidth(array $value_type_itemids, int $time_from, int $time_to,
			?int $width): array {
		$result = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$table = $this->getTableName($value_type);
			$_time_from = $this->getTtlLimitedTimestamp($value_type, $time_from);
			$seconds = $time_to - $_time_from;

			$values = $this->query(
				'SELECT itemid,count,avg,min,max,toUnixTimestamp(ts) AS clock'.($width === null ? '' : ',i').
				' FROM ('.
					'SELECT itemid,count() AS count,avg(value) AS avg,min(value) AS min,max(value) AS max,'.
						'max(clock_ns) as ts'.
						($width === null
							? ''
							: ',round({width:UInt64}*(toUnixTimestamp(clock_ns)-{time_gte:UInt64})/{seconds:UInt64}) AS i'
						).
					' FROM '.$table.
					' PREWHERE itemid IN {pre_itemids:Array(UInt64)}'.
					' WHERE clock_ns>=toDateTime64({time_gte:UInt64},9)'.
						' AND clock_ns<=addNanoseconds(toDateTime64({time_lte:UInt64},9),999999999)'.
					' GROUP BY itemid'.($width === null ? '' : ',i').
					' ORDER BY itemid'.($width === null ? '' : ',i').
				')',
				[
					'UInt64' => [
						'pre_itemids' => array_keys($itemids),
						'time_gte' => $_time_from,
						'time_lte' => $time_to,
						'width' => $width,
						'seconds' => $seconds
					]
				]
			);

			if (!$values) {
				continue;
			}

			$result += array_fill_keys(array_column($values, 'itemid', 'itemid'),
				['data' => [], 'source' => 'history']
			);

			foreach ($values as $value) {
				$result[$value['itemid']]['data'][] = $value;
			}
		}

		return $result;
	}

	/**
	 * @see CHistoryManager::getAggregatedValues
	 */
	public function getAggregatedValues(array $value_type_itemids, int $function, int $time_from,
			?int $time_to): array {
		$result = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$table = $this->getTableName($value_type);
			$values = $this->query(
				'SELECT itemid,value,toUnixTimestamp(ts) AS clock'.
				' FROM ('.
					'SELECT itemid,'.
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
					' WHERE itemid IN {itemids:Array(UInt64)}'.
						' AND clock_ns>=toDateTime64({time_gte:UInt64},9)'.
						($time_to !== null ? ' AND clock_ns<=addNanoseconds(toDateTime64({time_lte:UInt64},9),999999999)' : '').
					' GROUP BY itemid'.
				')',
				[
					'UInt64' => [
						'itemids' => array_keys($itemids),
						'time_gte' => $this->getTtlLimitedTimestamp($value_type, $time_from),
						'time_lte' => $time_to
					]
				]
			);

			if ($values === null || !$values) {
				continue;
			}

			foreach ($values as $value) {
				$result[$value['itemid']] = $value;
			}
		}

		return $result;
	}

	/**
	 * Encodes typed parameters into a column-value map.
	 *
	 * Supports integer and string types. Arrays are filtered and serialized
	 * into bracketed lists (e.g. "[1,2]" or "['a','b']").
	 * Invalid or empty values are skipped.
	 *
	 * @param array $params  Array of typed parameters.
	 */
	private function getEncodedParamMap(array $params): array {
		$form_values = [];

		foreach ($params as $column_type => $columns_values) {
			foreach ($columns_values as $column => $value) {
				$form_value = null;

				switch ($column_type) {
					case 'Int32':
					case 'Int64':
						$parser = new CNumberParser(['with_float' => false]);

						if (is_array($value)) {
							$form_value = array_filter($value,
								fn($v) => is_int($v) || $parser->parse((string) $v) == CParser::PARSE_SUCCESS
							);
						}
						elseif (is_int($value) || $parser->parse((string) $value) == CParser::PARSE_SUCCESS) {
							$form_value = $value;
						}
						break;

					case 'Float64':
						$parser = new CNumberParser();

						if (is_array($value)) {
							$form_value = array_filter($value,
								fn($v) => is_float($v) || $parser->parse((string) $v) == CParser::PARSE_SUCCESS
							);
						}
						elseif (is_float($value) || $parser->parse((string) $value) == CParser::PARSE_SUCCESS) {
							$form_value = $value;
						}
						break;

					case 'UInt64':
						$parser = new CNumberParser(['with_float' => false, 'with_minus' => false]);

						if (is_array($value)) {
							$form_value = array_filter($value,
								fn($v) => is_int($v) || $parser->parse((string) $v) == CParser::PARSE_SUCCESS
							);
						}
						elseif (is_int($value) || $parser->parse((string) $value) == CParser::PARSE_SUCCESS) {
							$form_value = $value;
						}
						break;

					case 'String':
						if (is_array($value)) {
							$form_value = array_map(
								fn($v) => '\''.addcslashes($v, '\\\'').'\'',
								array_filter($value, 'is_string')
							);
						}
						elseif (is_string($value)) {
							// Single value should not be quoted.
							$form_value = $value;
						}
						break;
				}

				if ($form_value === null) {
					continue;
				}

				$form_values[$column] = is_array($form_value)
					? '['.implode(',', array_map('strval', $form_value)).']'
					: (string) $form_value;
			}
		}

		return $form_values;
	}

	/**
	 * Get result of HTTP query to ClickHouse.
	 *
	 * @param string $query  Query string.
	 * @param array  $param  Array of query parameters grouped by column value type.
	 */
	private function query(string $query, array $param = []): ?array {
		$result = null;
		$time_start = microtime(true);

		$form_values = $this->getEncodedParamMap($param);
		$boundary = bin2hex(random_bytes(16));
		$content = [
			'--'.$boundary,
			'Content-Disposition: form-data; name="query"',
			'',
			$query
		];

		foreach ($form_values as $name => $value) {
			$content[] = '--'.$boundary;
			$content[] = 'Content-Disposition: form-data; name="param_'.$name.'"';
			$content[] = '';
			$content[] = $value;
		}

		$content[] = '--'.$boundary.'--';
		$content[] = '';

		$stream_context = $this->request_context_data;
		$stream_context['http']['content'] = implode("\r\n", $content);
		$stream_context['http']['header'][] = 'Content-Type: multipart/form-data; boundary='.$boundary;
		$stream_context['http']['header'][] = 'Content-Length: '.strlen($stream_context['http']['content']);

		try {
			$this->error_code = null;
			$this->error_message = null;
			$stream_context['http']['header'] = implode("\r\n", $stream_context['http']['header']);
			$result_raw = file_get_contents($this->url->getUrl(), false, stream_context_create($stream_context));
			$result = json_decode($result_raw, true);
			$http_code = 500;

			// The variable $http_response_header is defined only when file_get_contents succeeds.
			if (isset($http_response_header)) {
				sscanf($http_response_header[0], 'HTTP/%*s %d', $http_code);
			}
			elseif ($result_raw === false) {
				['message' => $result_raw] = error_get_last();
			}

			if ($http_code != 200) {
				$error_message = is_array($result) && array_key_exists('exception', $result)
					? $result['exception']
					: $result_raw;

				$result = null;
				$this->error_code = $http_code;
				$this->error_message = _s('ClickHouse error: %1$s.', $error_message);
			}
		} catch (Throwable $error) {
			$result = null;
			// Internal server error code
			$this->error_code = 500;
			$this->error_message = $error->getMessage();
		}

		CProfiler::getInstance()->profileClickHouse(microtime(true) - $time_start,
			json_encode(['query' => $query] + $form_values)
		);

		return $result === null ? null : $result['data'];
	}

	/**
	 * Create query string from $sql_parts.
	 *
	 * @param array $sql_parts
	 */
	private function buildQueryFromParts(array $sql_parts): string {
		$select = implode(',', array_map(
			fn($field, $expression) => $field === $expression ? $field : $expression.' AS '.$field,
			array_keys($sql_parts['select']),
			array_values($sql_parts['select'])
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
			'time_from' => null,
			'time_till' => null,
			'clock_ns_till' => null,
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

		if ($options['limit'] !== null && !ctype_digit((string) $options['limit'])) {
			$options['limit'] = null;
		}

		if (is_array($options['limit_by'])) {
			[$limit, $by] = array_pad($options['limit_by'], 2, '');

			if (!ctype_digit((string) $limit) || $by !== 'itemid') {
				$options['limit_by'] = null;
			}
		}

		$table = $this->getTableName($options['history']);
		$sql_parts = [
			'select'	=> [],
			'from'		=> [$table],
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

		if ($options['time_from'] !== null) {
			$time_from = $this->getTtlLimitedTimestamp($options['history'], (int) $options['time_from']);
			$sql_parts['param']['UInt64']['pre_time_gte'] = $time_from;
			$sql_parts['prewhere']['pre_time_gte'] = 'clock_ns>=toDateTime64({pre_time_gte:UInt64},9)';
		}

		if ($options['time_till'] !== null) {
			$sql_parts['param']['UInt64']['pre_time_lte'] = (int) $options['time_till'];
			$sql_parts['prewhere']['pre_time_lte'] = 'clock_ns<=addNanoseconds(toDateTime64({pre_time_lte:UInt64},9),999999999)';
		}

		if ($options['clock_ns_till'] !== null) {
			$sql_parts['param']['UInt64']['pre_clock_lte'] = $options['clock_ns_till']['clock'];
			$sql_parts['param']['UInt64']['pre_ns_lte'] = $options['clock_ns_till']['ns'];
			$sql_parts['prewhere']['pre_clock_ns_lte'] =
				'clock_ns<=addNanoseconds(toDateTime64({pre_clock_lte:UInt64},9),{pre_ns_lte:UInt64})';
		}

		if (is_array($options['filter']) && $options['filter']) {
			$sql_parts = $this->addQueryFilterOptions($sql_parts, $options);
		}

		if (is_array($options['search']) && $options['search']) {
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
		$value = 'value';

		if ($options['maxValueSize'] !== null) {
			$max_length = (int) $options['maxValueSize'];
			$value = match ($options['history']) {
				ITEM_VALUE_TYPE_STR,
				ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_TEXT => 'substringUTF8(value,1,'.$max_length.')',
				ITEM_VALUE_TYPE_JSON => 'substringUTF8(value_str!=\'\'?value_str:toString(value),1,'.$max_length.')',
				default => 'value'
			};
		}

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$options['history']]);
		$fields[] = 'clock';
		$fields[] = 'ns';
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
		$sortfield = (array) $options['sortfield'];
		$sortorder = (array) $options['sortorder'] + array_fill_keys(
			array_keys($sortfield),
			is_string($options['sortorder']) ? $options['sortorder'] : ZBX_SORT_UP
		);

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$options['history']]);
		$fields = array_diff($fields, ['value_str']);
		$fields[] = 'clock';
		foreach ($sortfield as $i => $field) {
			if (!in_array($field, $fields)) {
				continue;
			}

			$field = $field === 'clock' ? 'clock_ns' : $field;
			$sql_parts['order'][$field] = $field.' '.($sortorder[$i] === ZBX_SORT_DOWN ? ZBX_SORT_DOWN : ZBX_SORT_UP);
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

		$fields = ['clock' => 'UInt64', 'ns' => 'Int32'] + self::VALUE_TYPE_SCHEMA[$options['history']];
		foreach (array_intersect_key($fields, $options['filter']) as $field => $param_type) {
			$sql_parts['param'][$param_type]['filter_'.$field] = $options['filter'][$field];
			$condition = is_array($options['filter'][$field])
				? ' IN {filter_'.$field.':Array('.$param_type.')}'
				: '={filter_'.$field.':'.$param_type.'}';
			$filter[$field] = match ($field) {
				'clock' => 'toUnixTimestamp(clock_ns)'.$condition,
				'ns' => 'toUnixTimestamp64Nano(clock_ns)%1000000000'.$condition,
				default => $field.$condition
			};
		}

		if (!$filter) {
			return $sql_parts;
		}

		$sql_parts['where']['filter'] = implode($options['searchByAny'] ? ' OR ' : ' AND ', $filter);

		if ($options['searchByAny'] && count($filter) > 1) {
			$sql_parts['where']['filter'] = '('.$sql_parts['where']['filter'].')';
		}

		/**
		 * When filtering by 'clock' or 'ns' without 'time_from' and 'time_till' to improve performance add
		 * PREWHERE by max/min clock. If filter do not contain 'clock' PREWHERE is added within current timestamp
		 */
		$clock_fields = array_intersect_key($options['filter'], array_flip(['clock', 'ns']));
		if ($clock_fields && $options['time_from'] === null && $options['time_till'] === null) {
			$clock = array_key_exists('clock', $clock_fields) ? $clock_fields['clock'] : time();
			$sql_parts['param']['UInt64']['pre_time_gte'] = is_array($clock) ? min($clock) : $clock;
			$sql_parts['param']['UInt64']['pre_time_lte'] = is_array($clock) ? max($clock) : $clock;
			$sql_parts['prewhere']['pre_time_gte'] = 'clock_ns>=toDateTime64({pre_time_gte:UInt64},9)';
			$sql_parts['prewhere']['pre_time_lte'] = 'clock_ns<=addNanoseconds(toDateTime64({pre_time_lte:UInt64},9),999999999)';
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
		$operation = $options['excludeSearch'] ? ' NOT ILIKE ' : ' ILIKE ';
		$escape_pairs = ($options['searchWildcardsEnabled'] ? ['*' => '%'] : []) + ['%' => '\%', '_' => '\_'];

		foreach (array_intersect_key($fields, $options['search']) as $field => $param_type) {
			if ($param_type !== 'String') {
				continue;
			}

			$patterns = array_filter((array) $options['search'][$field], 'strlen');

			if (!$patterns) {
				continue;
			}

			foreach ($patterns as &$pattern) {
				$pattern = $prefix.strtr($pattern, $escape_pairs).$suffix;
			}
			unset($pattern);

			if (count($patterns) > 1) {
				$sql_parts['param'][$param_type]['search_'.$field] = $patterns;
				$search[$field] = $options['searchByAny'] === true
					? 'arrayExists(p -> '.$field.$operation.'p, {search_'.$field.':Array(String)})'
					: 'arrayAll(p -> '.$field.$operation.'p, {search_'.$field.':Array(String)})';
			}
			else {
				$sql_parts['param'][$param_type]['search_'.$field] = reset($patterns);
				$search[$field] = $field.$operation.'{search_'.$field.':String}';
			}
		}

		if (!$search) {
			return $sql_parts;
		}

		$sql_parts['where']['search'] = implode($options['searchByAny'] ? ' OR ' : ' AND ', $search);

		if ($options['searchByAny'] && count($search) > 1) {
			$sql_parts['where']['search'] = '('.$sql_parts['where']['search'].')';
		}

		return $sql_parts;
	}

	/**
	 * Get timestamp limited within value type supported TTL range.
	 *
	 * @param int $value_type
	 * @param int $timestamp
	 */
	private function getTtlLimitedTimestamp(int $value_type, int $timestamp): int {
		$value_ttl = $this->value_type_ttl[$value_type];

		return $value_ttl === null ? $timestamp : max($timestamp, time() - $value_ttl);
	}
}
