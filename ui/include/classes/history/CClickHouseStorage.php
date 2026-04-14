<?php

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

	protected CUrl $url;

	protected array $request_context_data = [];

	protected ?int $error_code;
	protected ?string $error_message;

	/**
	 * @var array $value_type_ttl  Array of value type TTL values, key is value type and value is it TTL.
	 */
	protected array $value_type_ttl = [];

	public function __construct(array $config, array $value_type_ttl) {
		$this->value_type_ttl = array_intersect_key($value_type_ttl, array_flip($config['types']));
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
	 * Get error code for last query. When no errors NULL is returned.
	 */
	public function getErrorCode(): ?int {
		return $this->error_code;
	}

	/**
	 * Get error message for last query. When no errors NULL is returned.
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
	 * Query storage using API like options. Return NULL on error.
	 *
	 * @see CHistory::get()
	 * @param array $options
	 */
	public function select(array $options): ?array {
		$query_parts = $this->getQueryPartsFromOptions($options);

		return $this->query($this->buildQueryFromParts($query_parts), $query_parts['param']);
	}

	/**
	 * @see CHistoryManager::deleteHistory
	 */
	public function delete(array $value_type_itemids): bool {
		// TODO: recheck!
		foreach ($value_type_itemids as $value_type => $itemids) {
			$table = $this->getTableName($value_type);
			$result = $this->query(
				'ALTER TABLE '.$table.' DELETE WHERE itemid IN {itemids:Array(UInt64)}',
				[
					'UInt64' => [
						'itemids' => array_keys($itemids)
					]
				]
			);

			if ($result === null || !$result) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @see CHistoryManager::getItemsHavingValues
	 */
	public function getItemsHavingValues(array $value_type_itemids, $lastn_sec = null): array {
		$results = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$time_from = $lastn_sec === null ? null : $this->getTtlLimitedTimestamp($value_type, time() - $lastn_sec);
			$rows = $this->select([
				'output' => ['itemid'],
				'history' => $value_type,
				'time_from' => $time_from,
				'itemids' => array_keys($itemids),
				'limit' => 1,
				'limit_by' => 'itemid'
			]);

			if ($rows === null) {
				break;
			}

			$results += array_column($rows, null, 'itemid');
		}

		return $results;
	}

	/**
	 * @see CHistoryManager::getLastValues
	 */
	public function getLastValues(array $value_type_itemids, int $limit, ?int $lastn_sec, ?int $length): array {
		$results = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$time_from = $lastn_sec === null ? null : $this->getTtlLimitedTimestamp($value_type, time() - $lastn_sec);
			$fields = array_keys(self::VALUE_TYPE_SCHEMA[$value_type]);
			$fields = array_diff($fields, ['clock_ns']);
			$fields[] = 'clock';
			$fields[] = 'ns';

			$rows = $this->select([
				'output' => $fields,
				'history' => $value_type,
				'time_from' => $time_from,
				'maxValueSize' => $length,
				'itemids' => array_keys($itemids),
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $limit,
				'limit_by' => 'itemid'
			]);

			if ($rows === null) {
				break;
			}

			foreach ($rows as $row) {
				$results[$row['itemid']][] = $row;
			}
		}

		return $results;
	}

	/**
	 * @see CHistoryManager::getValueAt
	 */
	public function getValueAt(array $item, int $clock, int $ns): ?array {
		if (!$this->isTtlValidTimestamp($item['value_type'], $clock)) {
			return null;
		}

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$item['value_type']]);
		$fields = array_diff($fields, ['clock_ns']);
		$fields[] = 'clock';
		$fields[] = 'ns';

		$rows = $this->select([
			'output' => $fields,
			'history' => $item['value_type'],
			'filter' => [
				'clock' => $clock
			],
			'itemids' => [$item['itemid']]
		]);

		if ($rows === null) {
			return null;
		}

		$rows = array_column($rows, null, 'ns');

		if (array_key_exists($ns, $rows)) {
			return $rows[$ns];
		}

		$rows = $this->select([
			'output' => $fields,
			'history' => $item['value_type'],
			'time_till' => $clock,
			'itemids' => [$item['itemid']],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		]);

		if ($rows === null) {
			return null;
		}

		return $rows[0] ?? null;
	}

	/**
	 * @see CHistoryManager::getAggregationByInterval
	 */
	public function getAggregationByInterval(array $value_type_itemids, int $time_from, int $time_to, int $function,
			int $interval): array {
		$results = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
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
					' FROM '.self::VALUE_TYPE_TABLE[$value_type].
					' PREWHERE itemid IN {pre_itemids:Array(UInt64)}'.
					' WHERE clock_ns BETWEEN toDateTime64({time_gte:UInt64},9) AND toDateTime64({time_lte:UInt64},9)'.
					' GROUP BY itemid,tick'.
					' ORDER BY itemid,tick'.
				')',
				[
					'UInt64' => [
						'pre_itemids' => array_keys($itemids),
						'time_gte' => $this->getTtlLimitedTimestamp($value_type, $time_from),
						'time_lte' => $this->getTtlLimitedTimestamp($value_type, $time_to),
						'interval' => $interval
					]
				]
			);

			if ($values === null || !$values) {
				continue;
			}

			$results += array_fill_keys(
				array_column($values, 'itemid', 'itemid'),
				['data' => [], 'source' => 'history']
			);

			foreach ($values as $value) {
				$results[$value['itemid']]['data'][] = $value;
			}
		}

		return $results;
	}

	/**
	 * Returns history value aggregation for graphs.
	 *
	 * @see CHistoryManager::getGraphAggregationByWidth
	 */
	public function getGraphAggregationByWidth(array $value_type_itemids, int $time_from, int $time_to,
			?int $width): array {
		$results = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$_time_from = $this->getTtlLimitedTimestamp($value_type, $time_from);
			$_time_to = $this->getTtlLimitedTimestamp($value_type, $time_to);
			$seconds = $_time_to - $_time_from;

			$values = $this->query(
				'SELECT itemid,count,avg,min,max,toUnixTimestamp(ts) AS clock'.($width === null ? '' : ',i').
				' FROM ('.
					'SELECT itemid,count() AS count,avg(value) AS avg,min(value) AS min,max(value) AS max,'.
						'max(clock_ns) as ts'.
						($width === null
							? ''
							: ',round({width:UInt64}*(toUnixTimestamp(clock_ns)-{time_gte:UInt64})/{seconds:UInt64}) AS i'
						).
					' FROM '.self::VALUE_TYPE_TABLE[$value_type].
					' WHERE itemid IN {itemids:Array(UInt64)}'.
						' AND clock_ns BETWEEN toDateTime64({time_gte:UInt64},9) AND toDateTime64({time_lte:UInt64},9)'.
					' GROUP BY itemid'.($width === null ? '' : ',i').
					' ORDER BY itemid'.($width === null ? '' : ',i').
				')',
				[
					'UInt64' => [
						'itemids' => array_keys($itemids),
						'time_gte' => $_time_from,
						'time_lte' => $_time_to,
						'width' => $width,
						'seconds' => $seconds
					]
				]
			);

			if (!$values) {
				continue;
			}

			$results += array_fill_keys(
				array_column($values, 'itemid', 'itemid'),
				['data' => [], 'source' => 'history']
			);

			foreach ($values as $value) {
				$results[$value['itemid']]['data'][] = $value;
			}
		}

		return $results;
	}

	/**
	 * @see CHistoryManager::getAggregatedValues
	 */
	public function getAggregatedValues(array $value_type_itemids, int $function, int $time_from,
			?int $time_to): array {
		$result = [];

		foreach ($value_type_itemids as $value_type => $itemids) {
			$values = $this->query(
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
					' FROM '.self::VALUE_TYPE_TABLE[$value_type].
					' WHERE itemid IN {itemids:Array(UInt64)}'.
						' AND '.($time_to === null
							? 'clock_ns>=toDateTime64({time_gte:UInt64},9)'
							: 'clock_ns BETWEEN toDateTime64({time_gte:UInt64},9) AND toDateTime64({time_lte:UInt64},9)'
						).
					' GROUP BY itemid'.
				')',
				[
					'UInt64' => [
						'itemids' => array_keys($itemids),
						'time_gte' => $this->getTtlLimitedTimestamp($value_type, $time_from),
						'time_lte' => $this->getTtlLimitedTimestamp($value_type, $time_to)
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
	 * Build query multipart form data context array to query ClickHouse.
	 *
	 * @param array  $stream_context  Base configuration for returned stream context
	 * @param string $query           ClickHouse query
	 * @param array  $param           ClickHouse query parameters
	 */
	protected function buildMultipartFormData(array $stream_context, string $query, array $param = []): array {
		$content = [];
		$boundary = uniqid();
		$stream_context['http']['header'][] = 'Content-Type: multipart/form-data; boundary='.$boundary;
		$boundary = '--'.$boundary;

		array_push($content, $boundary, 'Content-Disposition: form-data; name="query"', '', $query);

		foreach ($param as $column_type => $columns_values) {
			foreach ($columns_values as $column => $value) {
				$form_value = null;

				switch ($column_type) {
					case 'Int32':
					case 'Int64':
					case 'UInt64':
						if (is_array($value)) {
							$value = array_filter($value, fn($v) => is_int($v) || ctype_digit($v));
							$form_value = $value ? '['.implode(',', $value).']' : null;
						}
						elseif (is_int($value) || is_float($value) || ctype_digit($value)) {
							$form_value = $value;
						}

						break;
					case 'String':
						if (is_array($value)) {
							$value = array_map(
								fn($v) => '\''.addcslashes($v, '\\\'').'\'',
								$value
							);
							$form_value = $value ? '['.implode(',', $value).']' : null;
						}
						else {
							$form_value = $value;
						}
						break;
				}

				if ($form_value === null) {
					continue;
				}

				array_push($content, $boundary, 'Content-Disposition: form-data; name="param_'.$column.'"', '', $form_value);
			}
		}

		array_push($content, $boundary.'--', '');

		$stream_context['http']['content'] = implode("\r\n", $content);
		$stream_context['http']['header'][] = 'Content-Length: '.strlen($stream_context['http']['content']);

		return $stream_context;
	}

	/**
	 * Get result of HTTP query to ClickHouse.
	 *
	 * @param string $query  Query string.
	 * @param array  $param  Array of query parameters grouped by column value type.
	 */
	protected function query(string $query, array $param = []): ?array {
		$result = null;
		$time_start = microtime(true);
		$stream_context = $this->buildMultipartFormData($this->request_context_data, $query, $param);

		try {
			$this->error_code = null;
			$this->error_message = null;
			$stream_context['http']['header'] = implode("\r\n", $stream_context['http']['header']);
			$result_raw = file_get_contents($this->url->getUrl(), false, stream_context_create($stream_context));

			sscanf($http_response_header[0], 'HTTP/%*s %d', $http_code);
			$result = json_decode($result_raw, true);

			if ($http_code != 200) {
				$this->error_code = $http_code;
				$error_message = is_array($result) && array_key_exists('exception', $result)
					? $result['exception']
					: $result_raw;

				throw new Exception(_s('ClickHouse error: %1$s.', $error_message));
			}
		} catch (Throwable $error) {
			$result = null;
			$this->error_message = $error->getMessage();
		}

		CProfiler::getInstance()->profileClickhouse(microtime(true) - $time_start, $stream_context['http']['method'],
			$this->url->getUrl(), $stream_context['http']['content']
		);

		return $result === null ? null : $result['data'];
	}

	/**
	 * Create array with 'query' and 'param' keys from $sql_parts.
	 *
	 * @param array $sql_parts
	 */
	protected function buildQueryFromParts(array $sql_parts): string {
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
			($sql_parts['order'] ? ' ORDER BY '.implode(',', $sql_parts['order']) : '').
			(ctype_digit((string) $sql_parts['limit'])
				? ' LIMIT '.$sql_parts['limit'].($sql_parts['limit_by'] === 'itemid' ? ' BY itemid' : '')
				: ''
			);
	}

	/**
	 * Create sql_parts array from API options array.
	 * Does not support 'group' options.
	 *
	 * @param array $options
	 * @param int   $options['history']
	 */
	protected function getQueryPartsFromOptions(array $options): array {
		$options += [
			'time_from' => null,
			'time_till' => null,
			'maxValueSize' => null,
			'filter' => null,
			'search' => null,
			'startSearch' => false,
			'excludeSearch' => false,
			'searchWildcardsEnabled' => false,
			'searchByAny' => false,
			'sortfield' => null,
			'sortorder' => null,
			'limit' => null,
			'limit_by' => null
		];

		$sql_parts = [
			'select'	=> [],
			'from'		=> [self::VALUE_TYPE_TABLE[$options['history']]],
			'prewhere'	=> [],
			'where'		=> [],
			// 'group'		=> [],
			'order'		=> [],
			'limit'		=> $options['limit'],
			'limit_by'	=> $options['limit_by'],
			'param'		=> []
		];

		// TODO: check does it work with searchByAny!
		if (is_array($options['filter']) && array_key_exists('itemid', $options['filter'])) {
			$options['itemids'] = array_merge((array) $options['itemids'], (array) $options['filter']['itemid']);
			unset($options['filter']['itemid']);
		}

		if ($options['itemids'] !== null) {
			$sql_parts['param']['UInt64']['pre_itemids'] = array_unique((array) $options['itemids']);
			$sql_parts['prewhere']['itemid'] = is_array($options['itemids'])
				? 'itemid IN {pre_itemids:Array(UInt64)}'
				: 'itemid={pre_itemids:UInt64}';
		}

		$time_from = $options['time_from'] === null
			? null
			: $this->getTtlLimitedTimestamp($options['history'], (int) $options['time_from']);
		$time_till = $options['time_till'] === null
			? null
			: $this->getTtlLimitedTimestamp($options['history'], (int) $options['time_till']);

		if ($time_from !== null || $time_till !== null) {
			if ($time_till === null) {
				$sql_parts['param']['UInt64']['pre_time_gte'] = $time_from;
				$condition = 'clock_ns>=toDateTime64({pre_time_gte:UInt64},9)';
			}
			elseif ($time_from === null) {
				$sql_parts['param']['UInt64']['pre_time_lte'] = $time_till;
				$condition = 'clock_ns<=toDateTime64({pre_time_lte:UInt64},9)';
			}
			else {
				$sql_parts['param']['UInt64']['pre_time_gte'] = $time_from;
				$sql_parts['param']['UInt64']['pre_time_lte'] = $time_till;
				$condition = 'clock_ns BETWEEN toDateTime64({pre_time_gte:UInt64},9)'.
					' AND '.(
						(string) $time_from === (string) $time_till
							? 'addNanoseconds(toDateTime64({pre_time_lte:UInt64},9),999999999)'
							: 'toDateTime64({pre_time_lte:UInt64},9)'
					);
			}

			$sql_parts['prewhere']['clock'] = $condition;
		}

		if ($options['filter'] !== null && $options['filter']) {
			$sql_parts = $this->addQueryFilterOptions($sql_parts, $options);
		}

		if ($options['search'] !== null && $options['search']) {
			$sql_parts = $this->addQuerySearchOptions($sql_parts, $options);
		}

		if ($options['sortfield'] !== null && $options['sortfield']) {
			$sql_parts = $this->addQuerySortOptions($sql_parts, $options);
		}

		return $this->addQueryOutputOptions($sql_parts, $options);
	}

	/**
	 * Add to sql_parts changes according to output related options.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 */
	protected function addQueryOutputOptions(array $sql_parts, array $options): array {
		$value = 'value';

		if ($options['maxValueSize'] !== null) {
			$value = match ($options['history']) {
				ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64 => 'value',
				ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG,
				ITEM_VALUE_TYPE_TEXT => 'substringUTF8(value,1,'.intval($options['maxValueSize']).')',
				ITEM_VALUE_TYPE_JSON => 'substringUTF8(toString(value,1,'.intval($options['maxValueSize']).')'
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
	 */
	protected function addQuerySortOptions(array $sql_parts, array $options): array {
		$sortorder = (array) ($options['sortorder'] ?? ZBX_SORT_UP);
		$sortfield = (array) $options['sortfield'];

		$fields = array_keys(self::VALUE_TYPE_SCHEMA[$options['history']]);
		$fields[] = 'clock';
		foreach ($sortfield as $i => $field) {
			if (!in_array($field, $fields)) {
				continue;
			}

			if ($field === 'clock' || $field === 'ns') {
				$field = 'clock_ns';
			}

			$sql_parts['order'][$field] = $field.' '.(
				array_key_exists($i, $sortorder) && $sortorder[$i] === ZBX_SORT_DOWN
					? ZBX_SORT_DOWN
					: ZBX_SORT_UP
			);
		}

		return $sql_parts;
	}

	/**
	 * Add API filter options to sql parts. Array $sql_parts is modified by reference.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 */
	protected function addQueryFilterOptions(array $sql_parts, array $options): array {
		$filter = [];
		$schema = self::VALUE_TYPE_SCHEMA[$options['history']];
		// Escaping of clock_ns field is not supported.
		unset($schema['clock_ns']);

		foreach (array_intersect_key($schema, $options['filter']) as $field => $param_type) {
			$sql_parts['param'][$param_type]['filter_'.$field] = $options['filter'][$field];
			$filter[$field] = is_array($options['filter'][$field])
				? $field.' IN {filter_'.$field.':Array('.$param_type.')}'
				: $field.'={filter_'.$field.':'.$param_type.'}';
		}

		$clock_fields = array_intersect_key($options['filter'], array_flip(['clock', 'ns']));

		if ($clock_fields) {
			$clock_fields += ['clock' => time()];
			$filter['clock'] = is_array($clock_fields['clock'])
				? 'toUnixTimestamp(clock_ns) IN {filter_clock:Array(UInt64)}'
				: 'toUnixTimestamp(clock_ns)={filter_clock:UInt64}';
			$sql_parts['param']['UInt64']['filter_clock'] = $clock_fields['clock'];

			if (array_key_exists('ns', $clock_fields)) {
				$ns = is_array($clock_fields['ns'])
					? 'toUnixTimestamp64Nano(clock_ns)%1000000000 IN {filter_ns:Array(Int32)}'
					: 'toUnixTimestamp64Nano(clock_ns)%1000000000={filter_ns:Int32}';
				$sql_parts['param']['Int32']['filter_ns'] = $clock_fields['ns'];
				$filter['clock'] = '('.$filter['clock'].' AND '.$ns.')';
			}

			if ($options['time_from'] === null && $options['time_till'] === null) {
				$gte = is_array($clock_fields['clock']) ? min($clock_fields['clock']) : $clock_fields['clock'];
				$lte = is_array($clock_fields['clock']) ? max($clock_fields['clock']) : $clock_fields['clock'];

				$sql_parts['param']['UInt64']['pre_time_gte'] = $gte;
				$sql_parts['param']['UInt64']['pre_time_lte'] = $lte;
				$sql_parts['prewhere']['clock'] = 'clock_ns BETWEEN toDateTime64({pre_time_gte:UInt64},9)'.
					' AND '.(
						(string) $gte === (string) $lte
							? 'addNanoseconds(toDateTime64({pre_time_lte:UInt64}, 9), 999999999)'
							: 'toDateTime64({pre_time_lte:UInt64},9)'
					);
			}
		}

		$sql_parts['where']['filter'] = implode($options['searchByAny'] ? ' OR ' : ' AND ', $filter);

		if ($options['searchByAny'] && count($filter) > 1) {
			$sql_parts['where']['filter'] = '('.$sql_parts['where']['filter'].')';
		}

		return $sql_parts;
	}

	/**
	 * Add API search options to sql parts.
	 *
	 * @param array $sql_parts
	 * @param array $options
	 */
	protected function addQuerySearchOptions(array $sql_parts, array $options): array {
		$search = [];
		$schema = self::VALUE_TYPE_SCHEMA[$options['history']];
		$prefix = $options['startSearch'] ? '' : '%';
		$operation = $options['excludeSearch'] ? ' NOT ILIKE ' : ' ILIKE ';
		$pairs = $options['searchWildcardsEnabled']
			? ['*' => '%', '%' => '\%', '_' => '\_']
			: ['%' => '\%', '_' => '\_'];

		foreach (array_intersect_key($schema, $options['search']) as $field => $param_type) {
			if ($param_type !== 'String') {
				continue;
			}

			$patterns = array_filter((array) $options['search'][$field], 'strlen');

			if (!$patterns) {
				continue;
			}

			foreach ($patterns as &$pattern) {
				$pattern = $prefix.strtr($pattern, $pairs).'%';
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

		$sql_parts['where']['search'] = implode($options['searchByAny'] ? ' OR ' : ' AND ', $search);

		if ($options['searchByAny'] && count($search) > 1) {
			$sql_parts['where']['search'] = '('.$sql_parts['where']['search'].')';
		}

		return $sql_parts;
	}

	/**
	 * Get timestamp limited within value type supported TTL range.
	 * TODO: Check is this method necessary beacuse of TTL there will be no data.
	 *       If method have to stay then check is it required to apply it to clock set in filter
	 *
	 * @param int $value_type
	 * @param int $timestamp
	 */
	protected function getTtlLimitedTimestamp(int $value_type, int $timestamp): int {
		$value_ttl = $this->value_type_ttl[$value_type];

		return $value_ttl === null ? $timestamp : max($timestamp, time() - $value_ttl);
	}

	/**
	 * Check is timestamp within value type limited, by TTL, time range.
	 * Return true if no TTL is configured for requested value type.
	 *
	 * @param int $value_type
	 * @param int $timestamp
	 */
	protected function isTtlValidTimestamp(int $value_type, int $timestamp): bool {
		$value_ttl = $this->value_type_ttl[$value_type];

		return $value_ttl === null ? true : ($timestamp >= time() - $value_ttl);
	}
}
