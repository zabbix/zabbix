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
		'getSpans' =>	['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getLogs' =>	['min_user_type' => USER_TYPE_ZABBIX_USER],
		'getMetrics' =>	['min_user_type' => USER_TYPE_ZABBIX_USER]
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

		$inner_query = (new CClickHouseQuery())
			->from('otel_traces', 'ti')
			->select('ti.TraceId')
			->where('ti.Timestamp>=toDateTime64({time_from:Int32},9)', ['time_from' => $options['time_from']])
			->where('ti.Timestamp<toDateTime64({time_till:Int32},9)', ['time_till' => $options['time_till']])
			->group('ti.TraceId');

		if ($options['traceids'] !== null) {
			$inner_query->where('ti.TraceId IN {traceids:Array(String)}', ['traceids' => $options['traceids']]);
		}

		if ($options['min_duration'] !== null) {
			$inner_query->where('ti.Duration>={min_duration:Int64}', ['min_duration' => $options['min_duration']]);
		}

		if ($options['max_duration'] !== null) {
			$inner_query->where('ti.Duration<={max_duration:Int64}', ['max_duration' => $options['max_duration']]);
		}

		if ($options['filter'] || $options['search']
				|| $options['resource_attributes'] !== null || $options['span_attributes'] !== null) {
			$outer_query = (CClickHouseHelper::createQueryFromOptions('otel_traces', 'to', $db_schema, [
				'output' => ['TraceId'],
				...array_intersect_key($options, array_flip(
					['filter', 'search', 'searchByAny', 'startSearch', 'excludeSearch', 'searchWildcardsEnabled']
				))
			]))
				->linkQuery($inner_query)
				->where('to.TraceId IN ('.$inner_query->getSql().')')
				->group('to.TraceId');

			if ($options['resource_attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($outer_query, 'to.ResourceAttributes',
					$options['resource_attributes'], $options['resource_attributes_evaltype']
				);
			}

			if ($options['span_attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($outer_query, 'to.SpanAttributes',
					$options['span_attributes'], $options['span_attributes_evaltype']
				);
			}
		}
		else {
			$outer_query = $inner_query;
		}

		$query = (CClickHouseHelper::createQueryFromOptions('otel_traces', 't', $db_schema,
			array_intersect_key($options, array_flip(['countOutput', 'limit']))
		))
			->linkQuery($outer_query)
			->where('t.TraceId IN ('.$outer_query->getSql().')')
			->group('t.TraceId');

		foreach (array_intersect(array_keys(self::CLICKHOUSE_TRACES_FIELDS), $options['output']) as $field) {
			$query->select(match($field) {
				'TraceId' => 't.TraceId',
				'span_count' => 'count()',
				'error_count' => 'countIf(t.StatusCode=\'Error\')',
				default => 'anyIf(t.'.$field.',t.ParentSpanId=\'\')'
			}, $field);
		}

		foreach ($options['sortfield'] as $i => $field) {
			$order_by = match($field) {
				'TraceId' => 't.TraceId',
				'span_count' => 't.span_count',
				'error_count' => 't.error_count',
				default => 'anyIf(t.'.$field.',t.ParentSpanId=\'\')'
			};

			$sort_order = $options['sortorder'];
			$sort_order = match(true) {
				is_string($sort_order) => $sort_order,
				is_array($sort_order) && array_key_exists($i, $sort_order) => $sort_order[$i],
				default => ZBX_SORT_UP
			};

			$query->order($order_by, $sort_order);
		}

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_traces = [];

		foreach ($db->fetch($query->getSql(), $query->getParams()) as $row) {
			if ($options['countOutput']) {
				return (string) $row['rowscount'];
			}

			$db_traces[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_TRACES_FIELDS);
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

		$query = (CClickHouseHelper::createQueryFromOptions('otel_traces', 't', $db_schema, $options))
			->where('t.Timestamp>=toDateTime64({time_from:Int32},9)', ['time_from' => $options['time_from']])
			->where('t.Timestamp<toDateTime64({time_till:Int32},9)', ['time_till' => $options['time_till']]);

		if ($options['traceids'] !== null) {
			$query->where('t.TraceId IN {traceids:Array(String)}', ['traceids' => $options['traceids']]);
		}

		if ($options['spanids'] !== null) {
			$query->where('t.SpanId IN {spanids:Array(String)}', ['spanids' => $options['spanids']]);
		}

		if ($options['parent_spanids'] !== null) {
			$query->where('t.ParentSpanId IN {parent_spanids:Array(String)}', [
				'parent_spanids' => $options['parent_spanids']
			]);
		}

		if ($options['resource_attributes'] !== null) {
			CClickHouseHelper::addAttributeFilter($query, 't.ResourceAttributes', $options['resource_attributes'],
				$options['resource_attributes_evaltype']
			);
		}

		if ($options['span_attributes'] !== null) {
			CClickHouseHelper::addAttributeFilter($query, 't.SpanAttributes', $options['span_attributes'],
				$options['span_attributes_evaltype']
			);
		}

		if ($options['min_duration'] !== null) {
			$query->where('t.Duration>={min_duration:Int64}', ['min_duration' => $options['min_duration']]);
		}

		if ($options['max_duration'] !== null) {
			$query->where('t.Duration<={max_duration:Int64}', ['max_duration' => $options['max_duration']]);
		}

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_spans = [];

		foreach ($db->fetch($query->getSql(), $query->getParams()) as $row) {
			if ($options['countOutput']) {
				return (string) $row['rowscount'];
			}

			$db_spans[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_SPANS_FIELDS);
		}

		return $db_spans;
	}

	private const CLICKHOUSE_LOGS_OUTPUT_FIELDS = [
		'timestamp'				=> 'Timestamp',
		'traceid'				=> 'TraceId',
		'spanid'				=> 'SpanId',
		'trace_flags'			=> 'TraceFlags',
		'severity_text'			=> 'SeverityText',
		'severity_number'		=> 'SeverityNumber',
		'service_name'			=> 'ServiceName',
		'body'					=> 'Body',
		'resource_schema_url'	=> 'ResourceSchemaUrl',
		'resource_attributes'	=> 'ResourceAttributes',
		'scope_schema_url'		=> 'ScopeSchemaUrl',
		'scope_name'			=> 'ScopeName',
		'scope_version'			=> 'ScopeVersion',
		'scope_attributes'		=> 'ScopeAttributes',
		'log_attributes'		=> 'LogAttributes',
		'event_name'			=> 'EventName'
	];

	private const CLICKHOUSE_LOGS_FIELDS = [
		'Timestamp'				=> 'timestamp',
		'TraceId'				=> 'traceid',
		'SpanId'				=> 'spanid',
		'TraceFlags'			=> 'trace_flags',
		'SeverityText'			=> 'severity_text',
		'SeverityNumber'		=> 'severity_number',
		'ServiceName'			=> 'service_name',
		'Body'					=> 'body',
		'ResourceSchemaUrl'		=> 'resource_schema_url',
		'ResourceAttributes'	=> 'resource_attributes',
		'ScopeSchemaUrl'		=> 'scope_schema_url',
		'ScopeName'				=> 'scope_name',
		'ScopeVersion'			=> 'scope_version',
		'ScopeAttributes'		=> 'scope_attributes',
		'LogAttributes'			=> 'log_attributes',
		'EventName'				=> 'event_name'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function getLogs(array $options = []): array|string {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'time_from' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'time_till' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'traceids' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'spanids' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'with_flags_on' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'with_flags_off' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'resource_attributes' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'resource_attributes_evaltype' =>	['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'scope_attributes' =>				['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'scope_attributes_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'log_attributes' =>					['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'log_attributes_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'filter' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['traceid', 'spanid', 'trace_flags', 'severity_text', 'severity_number', 'service_name', 'resource_schema_url', 'scope_schema_url', 'scope_name', 'scope_version', 'event_name']],
			'search' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['severity_text', 'service_name', 'body', 'resource_schema_url', 'scope_schema_url', 'scope_name', 'scope_version', 'event_name']],
			'searchByAny' =>					['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>					['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>					['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>			['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_LOGS_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['timestamp', 'traceid', 'spanid', 'parent_spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'duration', 'status_code', 'status_message']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getLogsFromClickHouse($options);
	}

	protected function getLogsFromClickHouse(array $options): array|string {
		$db_schema = CApmData::getClickHouseDbSchema();

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_LOGS_OUTPUT_FIELDS);

		$query = (CClickHouseHelper::createQueryFromOptions('otel_logs', 'l', $db_schema, $options))
			->where('l.Timestamp>=toDateTime64({time_from:Int32},9)', ['time_from' => $options['time_from']])
			->where('l.Timestamp<toDateTime64({time_till:Int32},9)', ['time_till' => $options['time_till']]);

		if ($options['traceids'] !== null) {
			$query->where('l.TraceId IN {traceids:Array(String)}', ['traceids' => $options['traceids']]);
		}

		if ($options['spanids'] !== null) {
			$query->where('l.SpanId IN {spanids:Array(String)}', ['spanids' => $options['spanids']]);
		}

		if ($options['with_flags_on'] !== null) {
			$query->where('bitAnd(l.TraceFlags, {flags_on:Int32})={flags_on:Int32}', [
				'flags_on' => $options['with_flags_on']
			]);
		}

		if ($options['with_flags_off'] !== null) {
			$query->where('bitAnd(l.TraceFlags, {flags_off:Int32})=0', [
				'flags_off' => $options['with_flags_off']
			]);
		}

		if ($options['resource_attributes'] !== null) {
			CClickHouseHelper::addAttributeFilter($query, 'l.ResourceAttributes', $options['resource_attributes'],
				$options['resource_attributes_evaltype']
			);
		}

		if ($options['scope_attributes'] !== null) {
			CClickHouseHelper::addAttributeFilter($query, 'l.ScopeAttributes', $options['scope_attributes'],
				$options['scope_attributes_evaltype']
			);
		}

		if ($options['log_attributes'] !== null) {
			CClickHouseHelper::addAttributeFilter($query, 'l.LogAttributes', $options['log_attributes'],
				$options['log_attributes_evaltype']
			);
		}

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_logs = [];

		foreach ($db->fetch($query->getSql(), $query->getParams()) as $row) {
			if ($options['countOutput']) {
				return (string) $row['rowscount'];
			}

			$db_logs[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_LOGS_FIELDS);
		}

		return $db_logs;
	}

	private const CLICKHOUSE_METRICS_OUTPUT_FIELDS = [
		'type'						=> 'Type',
		'resource_attributes'		=> 'ResourceAttributes',
		'resource_schema_url'		=> 'ResourceSchemaUrl',
		'scope_name'				=> 'ScopeName',
		'scope_version'				=> 'ScopeVersion',
		'scope_attributes'			=> 'ScopeAttributes',
		'scope_schema_url'			=> 'ScopeSchemaUrl',
		'service_name'				=> 'ServiceName',
		'metric_name'				=> 'MetricName',
		'metric_description'		=> 'MetricDescription',
		'metric_unit'				=> 'MetricUnit',
		'attributes'				=> 'Attributes',
		'start_time_unix'			=> 'StartTimeUnix',
		'time_unix'					=> 'TimeUnix',
		'value'						=> 'Value',
		'flags'						=> 'Flags',
		'exemplars'					=> ['Exemplars.FilteredAttributes', 'Exemplars.TimeUnix', 'Exemplars.Value', 'Exemplars.SpanId', 'Exemplars.TraceId'],
		'aggregation_temporality'	=> 'AggregationTemporality',
		'is_monotonic'				=> 'IsMonotonic',
		'count'						=> 'Count',
		'sum'						=> 'Sum',
		'bucket_counts'				=> 'BucketCounts',
		'explicit_bounds'			=> 'ExplicitBounds',
		'min'						=> 'Min',
		'max'						=> 'Max',
		'scale'						=> 'Scale',
		'zero_count'				=> 'ZeroCount',
		'positive_offset'			=> 'PositiveOffset',
		'positive_bucket_counts'	=> 'PositiveBucketCounts',
		'negative_offset'			=> 'NegativeOffset',
		'negative_bucket_counts'	=> 'NegativeBucketCounts'
	];

	private const CLICKHOUSE_METRICS_FIELDS = [
		'Type'							=> 'type',
		'ResourceAttributes'			=> 'resource_attributes',
		'ResourceSchemaUrl'				=> 'resource_schema_url',
		'ScopeName'						=> 'scope_name',
		'ScopeVersion'					=> 'scope_version',
		'ScopeAttributes'				=> 'scope_attributes',
		'ScopeSchemaUrl'				=> 'scope_schema_url',
		'ServiceName'					=> 'service_name',
		'MetricName'					=> 'metric_name',
		'MetricDescription'				=> 'metric_description',
		'MetricUnit'					=> 'metric_unit',
		'Attributes'					=> 'attributes',
		'StartTimeUnix'					=> 'start_time_unix',
		'TimeUnix'						=> 'time_unix',
		'Value'							=> 'value',
		'Flags'							=> 'flags',
		'Exemplars.FilteredAttributes'	=> ['exemplars', 'filtered_attributes'],
		'Exemplars.TimeUnix'			=> ['exemplars', 'time_unix'],
		'Exemplars.Value'				=> ['exemplars', 'value'],
		'Exemplars.SpanId'				=> ['exemplars', 'spanid'],
		'Exemplars.TraceId'				=> ['exemplars', 'traceid'],
		'AggregationTemporality'		=> 'aggregation_temporality',
		'IsMonotonic'					=> 'is_monotonic',
		'Count'							=> 'count',
		'Sum'							=> 'sum',
		'BucketCounts'					=> 'bucket_counts',
		'ExplicitBounds'				=> 'explicit_bounds',
		'Min'							=> 'min',
		'Max'							=> 'max',
		'Scale'							=> 'scale',
		'ZeroCount'						=> 'zero_count',
		'PositiveOffset'				=> 'positive_offset',
		'PositiveBucketCounts'			=> 'positive_bucket_counts',
		'NegativeOffset'				=> 'negative_offset',
		'NegativeBucketCounts'			=> 'negative_bucket_counts'
	];

	private const CLICKHOUSE_METRICS_FIELDS_DEFAULTS = [
		'ResourceAttributes'			=> '[]',
		'ResourceSchemaUrl'				=> '\'\'',
		'ScopeName'						=> '\'\'',
		'ScopeVersion'					=> '\'\'',
		'ScopeAttributes'				=> '[]',
		'ScopeSchemaUrl'				=> '\'\'',
		'ServiceName'					=> '\'\'',
		'MetricName'					=> '\'\'',
		'MetricDescription'				=> '\'\'',
		'MetricUnit'					=> '\'\'',
		'Attributes'					=> '[]',
		'StartTimeUnix'					=> '1970-01-01 00:00:00.000000000',
		'TimeUnix'						=> '1970-01-01 00:00:00.000000000',
		'Value'							=> '0',
		'Flags'							=> '0',
		'Exemplars.FilteredAttributes'	=> '[]',
		'Exemplars.TimeUnix'			=> '[]',
		'Exemplars.Value'				=> '[]',
		'Exemplars.SpanId'				=> '[]',
		'Exemplars.TraceId'				=> '[]',
		'AggregationTemporality'		=> '0',
		'IsMonotonic'					=> 'false',
		'Count'							=> '0',
		'Sum'							=> '0',
		'BucketCounts'					=> '[]',
		'ExplicitBounds'				=> '[]',
		'Min'							=> '0',
		'Max'							=> '0',
		'Scale'							=> '0',
		'ZeroCount'						=> '0',
		'PositiveOffset'				=> '0',
		'PositiveBucketCounts'			=> '[]',
		'NegativeOffset'				=> '0',
		'NegativeBucketCounts'			=> '[]'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function getMetrics(array $options = []): array|string {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'time_from' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'time_till' =>						['type' => API_TIMESTAMP, 'flags' => API_REQUIRED],
			'types' =>							['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [APM_METRIC_TYPE_GAUGE, APM_METRIC_TYPE_SUM, APM_METRIC_TYPE_HISTOGRAM, APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM]), 'uniq' => true, 'default' => null],
			'with_flags_on' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'with_flags_off' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'default' => null],
			'resource_attributes' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'resource_attributes_evaltype' =>	['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'scope_attributes' =>				['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'scope_attributes_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'attributes' =>						['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_OPERATOR_LIKE, APM_ATTRIBUTE_OPERATOR_EQUAL, APM_ATTRIBUTE_OPERATOR_NOT_LIKE, APM_ATTRIBUTE_OPERATOR_NOT_EQUAL, APM_ATTRIBUTE_OPERATOR_EXISTS, APM_ATTRIBUTE_OPERATOR_NOT_EXISTS]), 'default' => APM_ATTRIBUTE_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'attributes_evaltype' =>			['type' => API_INT32, 'in' => implode(',', [APM_ATTRIBUTE_EVAL_TYPE_AND_OR, APM_ATTRIBUTE_EVAL_TYPE_OR]), 'default' => APM_ATTRIBUTE_EVAL_TYPE_AND_OR],
			'filter' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['resource_schema_url', 'scope_name', 'scope_version', 'scope_schema_url', 'service_name', 'metric_name', 'metric_unit']],
			'search' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['resource_schema_url', 'scope_name', 'scope_version', 'scope_schema_url', 'service_name', 'metric_name', 'metric_description', 'metric_unit']],
			'searchByAny' =>					['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>					['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>					['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>			['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_METRICS_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['type', 'resource_schema_url', 'scope_name', 'scope_version', 'scope_schema_url', 'service_name', 'metric_name', 'metric_unit', 'time_unix']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getMetricsFromClickHouse($options);
	}

	private const CLICKHOUSE_METRICS_TABLES = [
		APM_METRIC_TYPE_GAUGE					=> ['table' => 'otel_metrics_gauge', 'table_alias' => 'mg'],
		APM_METRIC_TYPE_SUM						=> ['table' => 'otel_metrics_sum', 'table_alias' => 'ms'],
		APM_METRIC_TYPE_HISTOGRAM				=> ['table' => 'otel_metrics_histogram', 'table_alias' => 'mh'],
		APM_METRIC_TYPE_EXPONENTIAL_HISTOGRAM	=> ['table' => 'otel_metrics_exponential_histogram', 'table_alias' => 'mx']
	];

	private function getMetricsFromClickHouse(array $options): array|string {
		$db_schema = CApmData::getClickHouseDbSchema();

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_METRICS_OUTPUT_FIELDS);

		if ($options['types'] !== null && !$options['types']) {
			return $options['countOutput'] ? '0' : [];
		}

		$select_tables = $options['types'] !== null
			? array_intersect_key(self::CLICKHOUSE_METRICS_TABLES, array_flip($options['types']))
			: self::CLICKHOUSE_METRICS_TABLES;

		$sub_queries = [];

		foreach ($select_tables as $type => ['table' => $table, 'table_alias' => $table_alias]) {
			$sub_query = (CClickHouseHelper::createQueryFromOptions($table, $table_alias, $db_schema,
				array_diff_key($options, array_flip(['output', 'sortfield', 'sortorder', 'limit']))
			))
				->where($table_alias.'.TimeUnix>=toDateTime64({time_from:Int32},9)', [
					'time_from' => $options['time_from']
				])
				->where($table_alias.'.TimeUnix<toDateTime64({time_till:Int32},9)', [
					'time_till' => $options['time_till']
				]);

			if ($options['with_flags_on'] !== null) {
				$sub_query->where('bitAnd('.$table_alias.'.Flags, {flags_on:Int32})={flags_on:Int32}', [
					'flags_on' => $options['with_flags_on']
				]);
			}

			if ($options['with_flags_off'] !== null) {
				$sub_query->where('bitAnd('.$table_alias.'.Flags, {flags_off:Int32})=0', [
					'flags_off' => $options['with_flags_off']
				]);
			}

			if ($options['resource_attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($sub_query, $table_alias.'.ResourceAttributes',
					$options['resource_attributes'], $options['resource_attributes_evaltype']
				);
			}

			if ($options['scope_attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($sub_query, $table_alias.'.ScopeAttributes',
					$options['scope_attributes'], $options['scope_attributes_evaltype']
				);
			}

			if ($options['attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($sub_query, $table_alias.'.Attributes', $options['attributes'],
					$options['attributes_evaltype']
				);
			}

			if (!$options['countOutput']) {
				// Type inclusion is mandatory for sorting capability.
				$sub_query->select($type, 'Type');

				foreach (array_intersect(array_keys(self::CLICKHOUSE_METRICS_FIELDS), $options['output']) as $field) {
					if ($field === 'Type') {
						continue;
					}

					if (array_key_exists($field, $db_schema[$table])) {
						$sub_query->select($table_alias.'.'.$field);
					}
					else {
						$sub_query->select(self::CLICKHOUSE_METRICS_FIELDS_DEFAULTS[$field], $field);
					}
				}
			}

			$sub_queries[] = $sub_query;
		}

		$query = new CClickHouseQuery();

		$sub_queries_sql = [];

		foreach ($sub_queries as $sub_query) {
			$query->linkQuery($sub_query);
			$sub_queries_sql[] = $sub_query->getSQL();
		}

		$query->from('('.implode(' UNION ALL ', $sub_queries_sql).')', 'u');

		if ($options['countOutput']) {
			$query->select('sum(rowscount)', 'rowscount');
		}
		else {
			foreach (array_intersect(array_keys(self::CLICKHOUSE_METRICS_FIELDS), $options['output']) as $field) {
				$query->select('u.'.$field);
			}

			foreach ($options['sortfield'] as $i => $field) {
				$sort_order = $options['sortorder'];
				$sort_order = match(true) {
					is_string($sort_order) => $sort_order,
					is_array($sort_order) && array_key_exists($i, $sort_order) => $sort_order[$i],
					default => ZBX_SORT_UP
				};

				$query->order('u.'.$field, $sort_order);
			}

			$query->limit($options['limit']);
		}

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_metrics = [];

		foreach ($db->fetch($query->getSql(), $query->getParams()) as $row) {
			if ($options['countOutput']) {
				return (string) $row['rowscount'];
			}

			$db_metrics[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_METRICS_FIELDS);
		}

		return $db_metrics;
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

		$options['sortfield'] = array_map(static fn (string $value) => $output_fields[$value], $options['sortfield']);

		return $options;
	}

	private static function fixRowForClickHouse(array $db_row, array $fields_spec): array {
		$row = [];

		foreach ($db_row as $field => $value) {
			if (is_array($fields_spec[$field])) {
				if (!array_key_exists($fields_spec[$field][0], $row)) {
					$row[$fields_spec[$field][0]] = [];
				}

				foreach ($value as $index => $sub_value) {
					$row[$fields_spec[$field][0]][$index][$fields_spec[$field][1]] = $sub_value;
				}
			}
			else {
				$row[$fields_spec[$field]] = $value;
			}
		}

		return $row;
	}
}
