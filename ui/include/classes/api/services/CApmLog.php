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
 * Application performance monitoring log API implementation.
 */
class CApmLog extends CApmGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	private const CLICKHOUSE_OUTPUT_FIELDS = [
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

	private const CLICKHOUSE_FIELDS = [
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
	public function get(array $options = []): array|string {
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
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
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

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_OUTPUT_FIELDS);

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

			$db_logs[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_FIELDS);
		}

		return $db_logs;
	}
}
