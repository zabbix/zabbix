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
		'getSpans' =>	['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	private const CLICKHOUSE_SPAN_OUTPUT_FIELDS = [
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

	private const CLICKHOUSE_SPAN_FIELDS = [
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
	 * @return array|int
	 */
	public function getSpans(array $options = []): array|int {
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
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_SPAN_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['timestamp', 'traceid', 'spanid', 'parent_spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'duration', 'status_code', 'status_message']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>					['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getSpansFromClickHouse($options);
	}

	protected function getSpansFromClickHouse(array $options): array {
		$db_schema = CApmData::getClickHouseDbSchema();

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = array_keys(self::CLICKHOUSE_SPAN_OUTPUT_FIELDS);
		}

		$output = [];

		foreach ($options['output'] as $field) {
			if (is_array(self::CLICKHOUSE_SPAN_OUTPUT_FIELDS[$field])) {
				$output = array_merge($output, self::CLICKHOUSE_SPAN_OUTPUT_FIELDS[$field]);
			}
			else {
				$output[] = self::CLICKHOUSE_SPAN_OUTPUT_FIELDS[$field];
			}
		}

		$options['output'] = $output;

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
				return $row['rowscount'];
			}

			$row_fixed = [];

			foreach ($row as $field => $value) {
				if (is_array(self::CLICKHOUSE_SPAN_FIELDS[$field])) {
					$row_fixed[self::CLICKHOUSE_SPAN_FIELDS[$field][0]] = [];

					foreach ($value as $index => $sub_value) {
						$row_fixed[self::CLICKHOUSE_SPAN_FIELDS[$field][0]][$index]
							[self::CLICKHOUSE_SPAN_FIELDS[$field][1]] = $sub_value;
					}
				}
				else {
					$row_fixed[self::CLICKHOUSE_SPAN_FIELDS[$field]] = $value;
				}
			}

			$db_spans[] = $row_fixed;
		}

		return $db_spans;
	}
}
