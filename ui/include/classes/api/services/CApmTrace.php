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

	protected const CLICKHOUSE_OUTPUT_FIELDS = [
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
		'has_root_span'			=> 'has_root_span',
		'span_count'			=> 'span_count',
		'error_count'			=> 'error_count'
	];

	protected const CLICKHOUSE_FIELDS = [
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
		'has_root_span'			=> 'has_root_span',
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
			'resource_attributes' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS]), 'default' => TAG_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'resource_attributes_evaltype' =>	['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'span_attributes' =>				['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
				'key' =>							['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
				'operator' =>						['type' => API_INT32, 'in' => implode(',', [TAG_OPERATOR_LIKE, TAG_OPERATOR_EQUAL, TAG_OPERATOR_NOT_LIKE, TAG_OPERATOR_NOT_EQUAL, TAG_OPERATOR_EXISTS, TAG_OPERATOR_NOT_EXISTS]), 'default' => TAG_OPERATOR_LIKE],
				'value' =>							['type' => API_STRING_UTF8, 'default' => '']
			]],
			'span_attributes_evaltype' =>		['type' => API_INT32, 'in' => implode(',', [TAG_EVAL_TYPE_AND_OR, TAG_EVAL_TYPE_OR]), 'default' => TAG_EVAL_TYPE_AND_OR],
			'min_duration' =>					['type' => API_UINT64, 'flags' => API_ALLOW_NULL, 'default' => null],
			'max_duration' =>					['type' => API_UINT64, 'flags' => API_ALLOW_NULL, 'default' => null],
			'filter' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['traceid', 'spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'status_code', 'status_message']],
			'search' =>							['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['trace_state', 'span_name', 'service_name', 'scope_name', 'scope_version', 'status_message']],
			'searchByAny' =>					['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>					['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>					['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>			['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>							['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', array_keys(self::CLICKHOUSE_OUTPUT_FIELDS)), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>					['type' => API_BOOLEAN, 'default' => false],
			// sort and limit
			'sortfield' =>						['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['timestamp', 'traceid', 'spanid', 'trace_state', 'span_name', 'span_kind', 'service_name', 'scope_name', 'scope_version', 'duration', 'status_code', 'status_message']), 'uniq' => true, 'default' => []],
			'sortorder' =>						['type' => API_SORTORDER, 'default' => []],
			'limit' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			'offset' =>							['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '0:'.ZBX_MAX_INT32, 'default' => null]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		return $this->getFromClickHouse($options);
	}

	protected function getFromClickHouse(array $options): array|string {
		$db_schema = CApmData::getClickHouseDbSchema();

		$options = self::translateOptionsForClickHouse($options, self::CLICKHOUSE_OUTPUT_FIELDS);

		$real_count_output = $options['countOutput'];

		if (!$options['output']) {
			$options['countOutput'] = true;
		}

		$query = (new CClickHouseQuery())
			->from('otel_traces', 't')
			->where('t.Timestamp>=toDateTime64({lookup_time_from:Int32},9)')
			->where('t.Timestamp<toDateTime64({lookup_time_till:Int32},9)')
			->group('t.TraceId')
			->params([
				'lookup_time_from' => ['Int32', $options['time_from'] - APM_TRACE_MAX_DURATION],
				'lookup_time_till' => ['Int32', $options['time_till'] + APM_TRACE_MAX_DURATION],
				'time_from' => ['Int32', $options['time_from']],
				'time_till' => ['Int32', $options['time_till']]
			]);

		if ($options['traceids'] !== null) {
			$query->where('t.TraceId IN {traceids:Array(String)}', ['traceids' => ['String', $options['traceids']]]);
		}

		$having_root = [
			'countIf(t.ParentSpanId=\'\')>0',
			'minIf(t.Timestamp,t.ParentSpanId=\'\')>=toDateTime64({time_from:Int32},9)',
			'minIf(t.Timestamp,t.ParentSpanId=\'\')<toDateTime64({time_till:Int32},9)'
		];

		$having_no_root = [
			'countIf(t.ParentSpanId=\'\')=0',
			'min(t.Timestamp)>=toDateTime64({time_from:Int32},9)',
			'min(t.Timestamp)<toDateTime64({time_till:Int32},9)'
		];

		$timestamp_ns = CClickHouseHelper::getCustomTypeSelect('DateTime64(9)', 'Timestamp', 't');

		if ($options['min_duration'] !== null) {
			$having_root[] = 'argMinIf(t.Duration,t.Timestamp,t.ParentSpanId=\'\')>={min_duration:UInt64}';
			$having_no_root[] = 'max('.$timestamp_ns.'+t.Duration)-min('.$timestamp_ns.')>={min_duration:UInt64}';
			$query->param('min_duration', 'UInt64', $options['min_duration']);
		}

		if ($options['max_duration'] !== null) {
			$having_root[] = 'argMinIf(t.Duration,t.Timestamp,t.ParentSpanId=\'\')<={max_duration:UInt64}';
			$having_no_root[] = 'max('.$timestamp_ns.'+t.Duration)-min('.$timestamp_ns.')<={max_duration:UInt64}';
			$query->param('max_duration', 'UInt64', $options['max_duration']);
		}

		$query->having('('.implode(' AND ', $having_root).') OR ('.implode(' AND ', $having_no_root).')');

		$use_span_filtering = $options['filter'] || $options['search']
			|| ($options['resource_attributes'] !== null
				&& array_filter($options['resource_attributes'],
					static fn (array $attribute) => $attribute['key'] !== ''
				)
			)
			|| ($options['span_attributes'] !== null
				&& array_filter($options['span_attributes'],
					static fn (array $attribute) => $attribute['key'] !== ''
				)
			);

		if ($use_span_filtering) {
			$filter_query = (CClickHouseHelper::createQueryFromOptions('otel_traces', 'tf', $db_schema,
				array_intersect_key($options, array_flip(['filter', 'search', 'searchByAny', 'startSearch',
					'excludeSearch', 'searchWildcardsEnabled'
				]))
			))
				->select('1')
				->where('tf.TraceId=t.TraceId')
				->where('tf.Timestamp>=toDateTime64({lookup_time_from:Int32},9)')
				->where('tf.Timestamp<toDateTime64({lookup_time_till:Int32},9)');

			if ($options['resource_attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($filter_query, 'tf.ResourceAttributes',
					$options['resource_attributes'], $options['resource_attributes_evaltype']
				);
			}

			if ($options['span_attributes'] !== null) {
				CClickHouseHelper::addAttributeFilter($filter_query, 'tf.SpanAttributes',
					$options['span_attributes'], $options['span_attributes_evaltype']
				);
			}

			$query
				->linkQuery($filter_query)
				->where('EXISTS ('.$filter_query->getSql().')');
		}

		if ($options['countOutput']) {
			$query->select('1');

			$query = (new CClickHouseQuery())
				->linkQuery($query)
				->select('count()', 'rowscount')
				->from('('.$query->getSql().')');
		}
		else {
			$table_schema = $db_schema['otel_traces'];

			foreach (array_intersect(array_keys(self::CLICKHOUSE_FIELDS), $options['output']) as $field) {
				switch ($field) {
					case 'span_count':
						$query->select('count()', $field);
						break;

					case 'error_count':
						$query->select('countIf(t.StatusCode=\'Error\')', $field);
						break;

					case 'has_root_span':
						$query->select('countIf(t.ParentSpanId=\'\')>0', $field);
						break;

					case 'Duration':
						$query->select(self::getDurationExpression('t'), $field);
						break;

					default:
						$field_schema = $table_schema[$field];

						$custom_select = CClickHouseHelper::getCustomTypeSelect($field_schema['type'], $field, 't');

						$select = $custom_select ?? 't.'.$field;
						$select_alias = $custom_select !== null ? $field : null;

						if ($field !== 'TraceId') {
							$select = 'argMin('.$select.',tuple(t.ParentSpanId!=\'\',t.Timestamp))';
							$select_alias = $field;
						}

						$query->select($select, $select_alias);
				}
			}

			foreach ($options['sortfield'] as $i => $field) {
				$order_by = match($field) {
					'TraceId' => 't.TraceId',
					'Duration' => self::getDurationExpression('t'),
					default => 'argMin(t.'.$field.',tuple(t.ParentSpanId!=\'\',t.Timestamp))'
				};

				$sort_order = $options['sortorder'];
				$sort_order = match(true) {
					is_string($sort_order) => $sort_order,
					is_array($sort_order) && array_key_exists($i, $sort_order) => $sort_order[$i],
					default => ZBX_SORT_UP
				};

				$query->order($order_by, $sort_order);
			}

			$query
				->limit($options['limit'])
				->offset($options['offset']);
		}

		$db = ApmDbClickHouse::getInstance(ApmDb::getInstance()->getConfig());

		$db_traces = [];

		foreach ($db->fetch($query->getSql(), $query->getParams()) as $row) {
			if ($options['countOutput']) {
				if ($real_count_output) {
					return (string) $row['rowscount'];
				}

				$count = $row['rowscount'];

				if ($options['limit'] !== null) {
					$count = min($options['limit'], $count);
				}

				return array_fill(0, $count, []);
			}

			$db_traces[] = self::translateRowForClickHouse($row, self::CLICKHOUSE_FIELDS);
		}

		return $db_traces;
	}

	private static function getDurationExpression(string $table_alias): string {
		$timestamp_ns = CClickHouseHelper::getCustomTypeSelect('DateTime64(9)', 'Timestamp', $table_alias);

		return 'if(countIf('.$table_alias.'.ParentSpanId=\'\')>0,'.
			'toUInt64('.
				'argMinIf('.$table_alias.'.Duration,'.$table_alias.'.Timestamp,'.$table_alias.'.ParentSpanId=\'\')'.
			'),'.
			'toUInt64(max('.$timestamp_ns.'+'.$table_alias.'.Duration)-min('.$timestamp_ns.'))'.
		')';
	}
}
