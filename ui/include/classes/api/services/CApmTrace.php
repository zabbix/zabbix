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
 * Application performance monitoring trace API implementation.
 */
class CApmTrace extends CApmGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	private const CLICKHOUSE_OUTPUT_FIELDS = [
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

	private const CLICKHOUSE_FIELDS = [
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
	public function get(array $options = []): array|string {
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
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['timestamp', 'traceid', 'spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'duration', 'status_code', 'status_message']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getFromClickHouse($options);
	}

	protected function getFromClickHouse(array $options): array|string {
		$db_schema = CApmData::getClickHouseDbSchema();

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_OUTPUT_FIELDS);

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

		foreach (array_intersect(array_keys(self::CLICKHOUSE_FIELDS), $options['output']) as $field) {
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

			$db_traces[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_FIELDS);
		}

		return $db_traces;
	}
}
