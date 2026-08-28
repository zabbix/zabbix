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
 * Application performance monitoring API implementation.
 */
class CApm extends CApiService {

	public const ACCESS_RULES = [
		'getTraces' =>	['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getSpans' =>	['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	private const CLICKHOUSE_TRACES_OUTPUT_FIELDS = [
		'timestamp'				=> 'Timestamp',
		'traceid'				=> 'TraceId',
		'spanid'				=> 'SpanId',
		'trace_state'			=> 'TraceState',
		'span_name'				=> 'SpanName',
		'span_kind'				=> 'SpanKind',
		'service_name'			=> 'ServiceName',
		'resource_attributes'	=> 'ResourceAttributes',
		'scope_name'			=> 'ScopeName',
		'scope_version'			=> 'ScopeVersion',
		'span_attributes'		=> 'SpanAttributes',
		'duration'				=> 'Duration',
		'status_code'			=> 'StatusCode',
		'status_message'		=> 'StatusMessage',
		'events'				=> ['Events.Timestamp', 'Events.Name', 'Events.Attributes'],
		'links'					=> ['Links.TraceId', 'Links.SpanId', 'Links.TraceState', 'Links.Attributes'],
		'span_count'			=> 'span_count',
		'error_count'			=> 'error_count'
	];

	private const CLICKHOUSE_TRACES_FIELDS = [
		'Timestamp'				=> 'timestamp',
		'TraceId'				=> 'traceid',
		'SpanId'				=> 'spanid',
		'TraceState'			=> 'trace_state',
		'SpanName'				=> 'span_name',
		'SpanKind'				=> 'span_kind',
		'ServiceName'			=> 'service_name',
		'ResourceAttributes'	=> 'resource_attributes',
		'ScopeName'				=> 'scope_name',
		'ScopeVersion'			=> 'scope_version',
		'SpanAttributes'		=> 'span_attributes',
		'Duration'				=> 'duration',
		'StatusCode'			=> 'status_code',
		'StatusMessage'			=> 'status_message',
		'Events.Timestamp'		=> ['events', 'timestamp'],
		'Events.Name'			=> ['events', 'name'],
		'Events.Attributes'		=> ['events', 'attributes'],
		'Links.TraceId'			=> ['links', 'traceid'],
		'Links.SpanId'			=> ['links', 'spanid'],
		'Links.TraceState'		=> ['links', 'trace_state'],
		'Links.Attributes'		=> ['links', 'attributes'],
		'span_count'			=> 'span_count',
		'error_count'			=> 'error_count'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function getTraces(array $options = []): array|string {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'time_from' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'time_till' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'traceids' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'spanids' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'parent_spanids' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'resource_attributes' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'resource_attributes_evaltype' =>	['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'span_attributes' =>				['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'span_attributes_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'min_duration' =>					['type' => API_UINT64, 'flags' => API_ALLOW_NULL, 'default' => null],
			'max_duration' =>					['type' => API_UINT64, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['traceid', 'spanid', 'span_kind', 'status_code', 'service_name', 'scope_name']],
			'search' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['trace_state', 'span_name', 'service_name', 'scope_name', 'scope_version', 'status_message']],
			'searchByAny' =>					['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>					['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>					['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>			['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_TRACES_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['timestamp', 'traceid', 'spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'duration', 'status_code', 'status_message']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getTracesFromClickHouse($options);
	}

	protected function getTracesFromClickHouse(array $options): array|string {
		$db_schema = CApmData::getClickHouseDbSchema();

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_TRACES_OUTPUT_FIELDS);

		$inner_query_parts = CClickHouseHelper::getQueryParts('otel_traces', 'ti');

		$inner_query_parts['select'][] = 'ti.TraceId';

		$inner_query_parts['where'][] = 'ti.Timestamp>=toDateTime64({time_from:Int32},9)';
		$inner_query_parts['param']['time_from'] = $options['time_from'];

		$inner_query_parts['where'][] = 'ti.Timestamp<toDateTime64({time_till:Int32},9)';
		$inner_query_parts['param']['time_till'] = $options['time_till'];

		if ($options['traceids'] !== null) {
			$inner_query_parts['where'][] = 'ti.TraceId IN {traceids:Array(String)}';
			$inner_query_parts['param']['traceids'] = $options['traceids'];
		}

		if ($options['min_duration'] !== null) {
			$inner_query_parts['where'][] = 'ti.Duration>={min_duration:Int64}';
			$inner_query_parts['param']['min_duration'] = $options['min_duration'];
		}

		if ($options['max_duration'] !== null) {
			$inner_query_parts['where'][] = 'ti.Duration<={max_duration:Int64}';
			$inner_query_parts['param']['max_duration'] = $options['max_duration'];
		}

		$inner_query_parts['group'][] = 'ti.TraceId';

		$inner_query = CClickHouseHelper::buildQueryFromParts($inner_query_parts);

		if ($options['filter'] || $options['search']) {
			$outer_query_parts = CClickHouseHelper::getQueryPartsFromOptions('otel_traces', 'to', $db_schema, [
				'output' => ['TraceId'],
				...array_intersect_key($options, array_flip(
					['filter', 'search', 'searchByAny', 'startSearch', 'excludeSearch', 'searchWildcardsEnabled']
				))
			]);

			$outer_query_parts['param'] += $inner_query_parts['param'];

			$outer_query_parts['where'][] = 'to.TraceId IN ('.$inner_query.')';
			$outer_query_parts['group'][] = 'to.TraceId';

			$outer_query = CClickHouseHelper::buildQueryFromParts($outer_query_parts);
		}
		else {
			$outer_query_parts = $inner_query_parts;
			$outer_query = $inner_query;
		}

		$query_parts = CClickHouseHelper::getQueryPartsFromOptions('otel_traces', 't', $db_schema,
			array_intersect_key($options, array_flip(['countOutput', 'limit']))
		);

		$query_parts['param'] += $outer_query_parts['param'];

		foreach (array_intersect(array_keys(self::CLICKHOUSE_TRACES_FIELDS), $options['output']) as $field) {
			$query_parts['select'][$field] = match($field) {
				'TraceId' => 't.TraceId',
				'span_count' => 'count()',
				'error_count' => 'countIf(t.StatusCode=\'STATUS_CODE_ERROR\')',
				default => 'anyIf(t.'.$field.',t.ParentSpanId=\'\')'
			};
		}

		$query_parts['where'][] = 't.TraceId IN ('.$outer_query.')';
		$query_parts['group'][] = 't.TraceId';

		foreach ($options['sortfield'] as $i => $field) {
			$order_by = match($field) {
				'TraceId' => 't.TraceId',
				'span_count' => 't.span_count',
				'error_count' => 't.error_count',
				default => 'anyIf(t.'.$field.',t.ParentSpanId=\'\')'
			};

			$sortorder = $options['sortorder'];
			$sortorder = match(true) {
				is_string($sortorder) => $sortorder,
				is_array($sortorder) && array_key_exists($i, $sortorder) => $sortorder[$i],
				default => ZBX_SORT_UP
			};

			$query_parts['order'][] = $order_by.($sortorder === ZBX_SORT_DOWN ? ' '.ZBX_SORT_DOWN : '');
		}

		$query = CClickHouseHelper::buildQueryFromParts($query_parts);

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_traces = [];

		foreach ($db->fetch($query, $query_parts['param']) as $row) {
			if ($options['countOutput']) {
				return (string) $row['rowscount'];
			}

			$row_fixed = [];

			foreach ($row as $field => $value) {
				if (is_array(self::CLICKHOUSE_TRACES_FIELDS[$field])) {
					if (!array_key_exists(self::CLICKHOUSE_TRACES_FIELDS[$field][0], $row_fixed)) {
						$row_fixed[self::CLICKHOUSE_TRACES_FIELDS[$field][0]] = [];
					}

					foreach ($value as $index => $sub_value) {
						$row_fixed[self::CLICKHOUSE_TRACES_FIELDS[$field][0]][$index]
							[self::CLICKHOUSE_TRACES_FIELDS[$field][1]] = $sub_value;
					}
				}
				else {
					$row_fixed[self::CLICKHOUSE_TRACES_FIELDS[$field]] = $value;
				}
			}

			$db_traces[] = $row_fixed;
		}

		return $db_traces;
	}

	private const CLICKHOUSE_SPANS_OUTPUT_FIELDS = [
		'timestamp'				=> 'Timestamp',
		'traceid'				=> 'TraceId',
		'spanid'				=> 'SpanId',
		'parent_spanid'			=> 'ParentSpanId',
		'trace_state'			=> 'TraceState',
		'span_name'				=> 'SpanName',
		'span_kind'				=> 'SpanKind',
		'service_name'			=> 'ServiceName',
		'resource_attributes'	=> 'ResourceAttributes',
		'scope_name'			=> 'ScopeName',
		'scope_version'			=> 'ScopeVersion',
		'span_attributes'		=> 'SpanAttributes',
		'duration'				=> 'Duration',
		'status_code'			=> 'StatusCode',
		'status_message'		=> 'StatusMessage',
		'events'				=> ['Events.Timestamp', 'Events.Name', 'Events.Attributes'],
		'links'					=> ['Links.TraceId', 'Links.SpanId', 'Links.TraceState', 'Links.Attributes']
	];

	private const CLICKHOUSE_SPANS_FIELDS = [
		'Timestamp'				=> 'timestamp',
		'TraceId'				=> 'traceid',
		'SpanId'				=> 'spanid',
		'ParentSpanId'			=> 'parent_spanid',
		'TraceState'			=> 'trace_state',
		'SpanName'				=> 'span_name',
		'SpanKind'				=> 'span_kind',
		'ServiceName'			=> 'service_name',
		'ResourceAttributes'	=> 'resource_attributes',
		'ScopeName'				=> 'scope_name',
		'ScopeVersion'			=> 'scope_version',
		'SpanAttributes'		=> 'span_attributes',
		'Duration'				=> 'duration',
		'StatusCode'			=> 'status_code',
		'StatusMessage'			=> 'status_message',
		'Events.Timestamp'		=> ['events', 'timestamp'],
		'Events.Name'			=> ['events', 'name'],
		'Events.Attributes'		=> ['events', 'attributes'],
		'Links.TraceId'			=> ['links', 'traceid'],
		'Links.SpanId'			=> ['links', 'spanid'],
		'Links.TraceState'		=> ['links', 'trace_state'],
		'Links.Attributes'		=> ['links', 'attributes']
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function getSpans(array $options = []): array|string {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'time_from' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'time_till' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'traceids' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'spanids' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'parent_spanids' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'resource_attributes' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'resource_attributes_evaltype' =>	['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'span_attributes' =>				['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'span_attributes_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'min_duration' =>					['type' => API_UINT64, 'flags' => API_ALLOW_NULL, 'default' => null],
			'max_duration' =>					['type' => API_UINT64, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['traceid', 'spanid', 'parent_spanid', 'span_kind', 'status_code', 'service_name', 'scope_name']],
			'search' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['trace_state', 'span_name', 'service_name', 'scope_name', 'scope_version', 'status_message']],
			'searchByAny' =>					['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>					['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>					['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>			['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_SPANS_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['timestamp', 'traceid', 'spanid', 'parent_spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'duration', 'status_code', 'status_message']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getSpansFromClickHouse($options);
	}

	protected function getSpansFromClickHouse(array $options): array|string {
		$db_schema = CApmData::getClickHouseDbSchema();

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_SPANS_OUTPUT_FIELDS);

		$query_parts = CClickHouseHelper::getQueryPartsFromOptions('otel_traces', 't', $db_schema, $options);

		$query_parts['where'][] = 't.Timestamp>=toDateTime64({time_from:Int32},9)';
		$query_parts['param']['time_from'] = $options['time_from'];

		$query_parts['where'][] = 't.Timestamp<toDateTime64({time_till:Int32},9)';
		$query_parts['param']['time_till'] = $options['time_till'];

		if ($options['traceids'] !== null) {
			$query_parts['where'][] = 't.TraceId IN {traceids:Array(String)}';
			$query_parts['param']['traceids'] = $options['traceids'];
		}

		if ($options['spanids'] !== null) {
			$query_parts['where'][] = 't.SpanId IN {spanids:Array(String)}';
			$query_parts['param']['spanids'] = $options['spanids'];
		}

		if ($options['parent_spanids'] !== null) {
			$query_parts['where'][] = 't.ParentSpanId IN {parent_spanids:Array(String)}';
			$query_parts['param']['parent_spanids'] = $options['parent_spanids'];
		}

		if ($options['resource_attributes'] !== null) {
			$query_parts = CClickHouseHelper::addAttributeFilter($query_parts, 't.ResourceAttributes',
				$options['resource_attributes'], $options['resource_attributes_evaltype']
			);
		}

		if ($options['span_attributes'] !== null) {
			$query_parts = CClickHouseHelper::addAttributeFilter($query_parts, 't.SpanAttributes',
				$options['span_attributes'], $options['span_attributes_evaltype']
			);
		}

		if ($options['min_duration'] !== null) {
			$query_parts['where'][] = 't.Duration>={min_duration:Int64}';
			$query_parts['param']['min_duration'] = $options['min_duration'];
		}

		if ($options['max_duration'] !== null) {
			$query_parts['where'][] = 't.Duration<={max_duration:Int64}';
			$query_parts['param']['max_duration'] = $options['max_duration'];
		}

		$query = CClickHouseHelper::buildQueryFromParts($query_parts);

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_spans = [];

		foreach ($db->fetch($query, $query_parts['param']) as $row) {
			if ($options['countOutput']) {
				return (string) $row['rowscount'];
			}

			$row_fixed = [];

			foreach ($row as $field => $value) {
				if (is_array(self::CLICKHOUSE_SPANS_FIELDS[$field])) {
					if (!array_key_exists(self::CLICKHOUSE_SPANS_FIELDS[$field][0], $row_fixed)) {
						$row_fixed[self::CLICKHOUSE_SPANS_FIELDS[$field][0]] = [];
					}

					foreach ($value as $index => $sub_value) {
						$row_fixed[self::CLICKHOUSE_SPANS_FIELDS[$field][0]][$index]
							[self::CLICKHOUSE_SPANS_FIELDS[$field][1]] = $sub_value;
					}
				}
				else {
					$row_fixed[self::CLICKHOUSE_SPANS_FIELDS[$field]] = $value;
				}
			}

			$db_spans[] = $row_fixed;
		}

		return $db_spans;
	}

	private static function fixOptionsForClickHouse(array $options, array $output_fields): array {
		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = array_keys($output_fields);
		}

		$output = [];

		foreach ($options['output'] as $field) {
			if (is_array($output_fields[$field])) {
				$output = array_merge($output, $output_fields[$field]);
			}
			else {
				$output[] = $output_fields[$field];
			}
		}

		$options['output'] = $output;

		if ($options['filter'] !== null) {
			$filter = [];

			foreach ($options['filter'] as $key => $value) {
				$filter[$output_fields[$key]] = $value;
			}

			$options['filter'] = $filter;
		}

		if ($options['search'] !== null) {
			$search = [];

			foreach ($options['search'] as $key => $value) {
				$search[$output_fields[$key]] = $value;
			}

			$options['search'] = $search;
		}

		$options['sortfield'] = array_map(static fn ($value) => $output_fields[$value], $options['sortfield']);

		return $options;
	}
}
