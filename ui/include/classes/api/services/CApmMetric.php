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
 * Application performance monitoring metric API implementation.
 */
class CApmMetric extends CApmGeneral {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER]
	];

	private const CLICKHOUSE_OUTPUT_FIELDS = [
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

	private const CLICKHOUSE_FIELDS = [
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

	private const CLICKHOUSE_FIELDS_DEFAULTS = [
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
	public function get(array $options = []): array|string {
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
			'output' =>							['type' => API_OUTPUT, 'in' => implode(',', array_keys(self::CLICKHOUSE_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
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

		$options = self::fixOptionsForClickHouse($options, self::CLICKHOUSE_OUTPUT_FIELDS);

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

				foreach (array_intersect(array_keys(self::CLICKHOUSE_FIELDS), $options['output']) as $field) {
					if ($field === 'Type') {
						continue;
					}

					if (array_key_exists($field, $db_schema[$table])) {
						$sub_query->select($table_alias.'.'.$field);
					}
					else {
						$sub_query->select(self::CLICKHOUSE_FIELDS_DEFAULTS[$field], $field);
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
			foreach (array_intersect(array_keys(self::CLICKHOUSE_FIELDS), $options['output']) as $field) {
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

			$db_metrics[] = self::fixRowForClickHouse($row, self::CLICKHOUSE_FIELDS);
		}

		return $db_metrics;
	}
}
