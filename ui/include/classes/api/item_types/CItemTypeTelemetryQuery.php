<?php declare(strict_types = 1);
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


class CItemTypeTelemetryQuery extends CItemType {

	/**
	 * @inheritDoc
	 */
	public const TYPE = ITEM_TYPE_TELEMETRY_QUERY;

	/**
	 * @inheritDoc
	 */
	public const FIELD_NAMES = ['time_shift', 'lookback_limit', 'granularity', 'query'];

	public const SIGNAL_TYPE_TRACES = 0;
	public const SIGNAL_TYPE_METRICS = 1;
	public const SIGNAL_TYPE_LOGS = 2;

	public const METRICS_POINT_SUM = 0;
	public const METRICS_POINT_GAUGE = 1;
	public const METRICS_POINT_HISTOGRAM = 2;
	public const METRICS_POINT_EXPONENTIAL_HISTOGRAM = 3;

	// Column names require "attribute_key" to be set.
	public const COMPLEX_COLUMN_NAME = [
		'SpanAttributes', 'Events.Attributes', 'LogAttributes', 'ResourceAttributes', 'ScopeAttributes', 'Attributes',
		'Exemplars.FilteredAttributes'
	];

	// SIGNAL_TYPE_TRACES column names
	public const TRACES_COLUMNS_COLUMN = [
		'Timestamp', 'TraceId', 'SpanId', 'ParentSpanId', 'TraceState', 'SpanName', 'SpanKind', 'ServiceName',
		'ResourceAttributes', 'SpanAttributes', 'ScopeName', 'ScopeVersion', 'Duration', 'StatusCode', 'StatusMessage'
	];
	public const TRACES_AGGREGATED_COLUMN = [
		'Timestamp', 'Duration'
	];
	public const TRACES_CONDITIONS_COLUMN = [
		'TraceId', 'SpanId', 'ParentSpanId', 'TraceState', 'SpanName', 'SpanKind', 'ServiceName', 'ResourceAttributes',
		'SpanAttributes', 'ScopeName', 'ScopeVersion', 'StatusCode', 'StatusMessage', 'Events.Attributes', 'Events.Name'
	];

	// SIGNAL_TYPE_LOGS column names
	public const LOGS_COLUMNS_COLUMN = [
		'Timestamp', 'TraceId', 'SpanId', 'TraceFlags', 'SeverityText', 'SeverityNumber', 'ServiceName', 'Body',
		'ResourceSchemaUrl', 'ScopeSchemaUrl', 'ScopeName', 'ScopeVersion', 'ResourceAttributes', 'ScopeAttributes',
		'LogAttributes', 'EventName'
	];
	public const LOGS_AGGREGATED_COLUMN = [
		'Timestamp', 'SeverityNumber'
	];
	public const LOGS_CONDITIONS_COLUMN = [
		'TraceId', 'SpanId', 'SeverityText', 'ServiceName', 'Body', 'ResourceSchemaUrl', 'ScopeSchemaUrl', 'ScopeName',
		'ScopeVersion', 'ResourceAttributes', 'ScopeAttributes', 'LogAttributes', 'EventName'
	];

	// SIGNAL_TYPE_METRICS column names
	public const METRICS_COLUMNS_COLUMN = [
		self::METRICS_POINT_SUM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Value', 'Flags', 'AggregationTemporality', 'IsMonotonic'
		],
		self::METRICS_POINT_GAUGE => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Value', 'Flags'
		],
		self::METRICS_POINT_HISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Flags', 'Min', 'Max', 'AggregationTemporality'
		],
		self::METRICS_POINT_EXPONENTIAL_HISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Scale', 'ZeroCount', 'PositiveOffset',
			'NegativeOffset', 'Flags', 'Min', 'Max', 'AggregationTemporality'
		]
	];
	public const METRICS_AGGREGATED_COLUMN = [
		self::METRICS_POINT_SUM => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Value'
		],
		self::METRICS_POINT_GAUGE => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Value'
		],
		self::METRICS_POINT_HISTOGRAM => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Min', 'Max'
		],
		self::METRICS_POINT_EXPONENTIAL_HISTOGRAM => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Scale', 'ZeroCount',
			'PositiveOffset', 'NegativeOffset', 'Min', 'Max'
		]
	];
	public const METRICS_CONDITIONS_COLUMN = [
		self::METRICS_POINT_SUM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		],
		self::METRICS_POINT_GAUGE => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		],
		self::METRICS_POINT_HISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		],
		self::METRICS_POINT_EXPONENTIAL_HISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		]
	];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		$api_allow_lld_macro = $item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE ? API_ALLOW_LLD_MACRO : 0;
		$flags = API_NOT_EMPTY | API_ALLOW_USER_MACRO | $api_allow_lld_macro;

		return [
			'time_shift' =>		['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '0:'.SEC_PER_DAY, 'length' => DB::getFieldLength('items', 'time_shift'), 'default' => DB::getDefault('items', 'time_shift')],
			'lookback_limit' =>	['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '1:'.(3 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'lookback_limit'), 'default' => DB::getDefault('items', 'lookback_limit')],
			'granularity' =>	['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '1:'.SEC_PER_DAY, 'length' => DB::getFieldLength('items', 'granularity'), 'default' => DB::getDefault('items', 'granularity')],
			'query' =>			['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => self::getQueryFieldValidationRules($item)],
			'timeout' =>		self::getCreateFieldRule('timeout', $item),
			'delay' =>			self::getCreateFieldRule('delay', $item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		$api_allow_lld_macro = $db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE ? API_ALLOW_LLD_MACRO : 0;
		$flags = API_NOT_EMPTY | API_ALLOW_USER_MACRO | $api_allow_lld_macro;

		return [
			'time_shift' =>		['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '0:'.(1 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'time_shift')],
			'lookback_limit' =>	['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '1:'.(3 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'lookback_limit')],
			'granularity' =>	['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '1:'.(1 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'granularity')],
			'query' =>			['type' => API_OBJECT, 'fields' => self::getQueryFieldValidationRules($db_item)],
			'timeout' =>		self::getUpdateFieldRule('timeout', $db_item),
			'delay' =>			self::getUpdateFieldRule('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		$api_allow_lld_macro = $db_item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE ? API_ALLOW_LLD_MACRO : 0;
		$flags = API_NOT_EMPTY | API_ALLOW_USER_MACRO | $api_allow_lld_macro;

		return [
			'time_shift' =>		['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '0:'.SEC_PER_DAY, 'length' => DB::getFieldLength('items', 'time_shift')],
			'lookback_limit' =>	['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '1:'.(3 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'lookback_limit')],
			'granularity' =>	['type' => API_TIME_UNIT, 'flags' => $flags, 'in' => '1:'.SEC_PER_DAY, 'length' => DB::getFieldLength('items', 'granularity')],
			'query' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_INHERITED],
			'timeout' =>		self::getUpdateFieldRuleInherited('timeout', $db_item),
			'delay' =>			self::getUpdateFieldRuleInherited('delay', $db_item)
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		return [
			'time_shift' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '0:'.SEC_PER_DAY, 'length' => DB::getFieldLength('items', 'time_shift')],
			'lookback_limit' =>	['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.(3 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'lookback_limit')],
			'granularity' =>	['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_DAY, 'length' => DB::getFieldLength('items', 'granularity')],
			'query' =>			['type' => API_UNEXPECTED, 'error_type' => API_ERR_DISCOVERED],
			'timeout' =>		self::getUpdateFieldRuleDiscovered('timeout'),
			'delay' =>			self::getUpdateFieldRuleDiscovered('delay')
		];
	}

	/**
	 * Validate "query.aggregated_columns":
	 * - for "function" AGGREGATE_PERCENTILE "parameters" array may have only single value
	 *
	 * @param array       $item   Telemetry item to validate.
	 * @param string      $path   Path for validation message.
	 * @param string|null $error  Error message when validation fails, set by reference.
	 */
	public static function validateAggregatedColumns(array $item, string $path, ?string &$error): bool {
		foreach ($item['query']['aggregated_columns'] as $i => $column) {
			if ($column['function'] == AGGREGATE_PERCENTILE && count($column['parameters']) > 1) {
				$error = _s('Invalid parameter "%1$s": %2$s.',
					$path.'/query/aggregated_columns/'.($i + 1).'/parameters',
					_s('maximum number of array elements is %1$s', 1)
				);

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate "query.filter":
	 * - for "evaltype" CONDITION_EVAL_TYPE_EXPRESSION each "formulaid" should be in use by "formula" field
	 *
	 * @param array       $item   Telemetry item to validate.
	 * @param string      $path   Path in validation message.
	 * @param string|null $error  Error message when validation fails, set by reference.
	 */
	public static function validateFilter(array $item, string $path, ?string &$error): bool {
		if ($item['query']['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			$condition_formula_parser = new CConditionFormulaParser();
			$condition_formula_parser->parse($item['query']['filter']['formula']);
			$constants = array_column($condition_formula_parser->getConstants(), 'value', 'value');
			$formulaids = array_column($item['query']['filter']['conditions'], 'formulaid');

			if (count($formulaids) != count($constants)) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path.'/query/filter/conditions',
					_('incorrect number of conditions')
				);

				return false;
			}

			$formulaids = array_diff_key(array_flip($formulaids), $constants);

			if ($formulaids) {
				$error = _s('Invalid parameter "%1$s": %2$s.',
					$path.'/query/filter/conditions/'.(reset($formulaids) + 1).'/formulaid',
					_('an identifier is not defined in the formula')
				);

				return false;
			}
		}

		return true;
	}

	/**
	 * Value uniqueness within columns:
	 * - "query.columns[].column" (or "query.columns[].column" and "query.columns[].attribute_key" for complex column)
	 * - "query.aggregated_columns[].alias"
	 *
	 * @param array       $item   Telemetry item to validate.
	 * @param string      $path   Path in validation message.
	 * @param string|null $error  Error message when validation fails, set by reference.
	 */
	public static function validateColumnsAggregatedColumnsUnique(array $item, string $path, ?string &$error): bool {
		$uniq = [];

		foreach ($item['query']['columns'] as $i => $column) {
			$uniq_value = in_array($column['column'], self::COMPLEX_COLUMN_NAME, true)
				? $column['column'].'.'.$column['attribute_key']
				: $column['column'];
			$uniq[$uniq_value] = true;
		}

		foreach ($item['query']['aggregated_columns'] as $i => $column) {
			if (array_key_exists($column['alias'], $uniq)) {
				$error = _s('Invalid parameter "%1$s": %2$s.', $path.'/query/aggregated_columns/'.($i + 1),
					_s('value %1$s already exists', '(alias)=('.$column['alias'].')')
				);

				return false;
			}

			$uniq[$column['alias']] = true;
		}

		return true;
	}

	/**
	 * Serialize "item.query" to JSON string.
	 * Convert "item.query.filter.formula" from "A or B" like notation to "{0} or {1}" notation
	 * removing "formulaid" property for each condition.
	 *
	 * @param array $query  Array for "item.query" configuration
	 */
	public static function prepareQueryFieldForDb(array $query): string {
		foreach ($query['aggregated_columns'] as &$column) {
			if ($column['function'] == AGGREGATE_PERCENTILE) {
				// Server expects "query.aggregated_columns[].parameters" to be stored as array of strings.
				$column['parameters'] = array_map('strval', $column['parameters']);
			}
		}
		unset($column);

		return json_encode($query, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Deserialize "item.query" field from JSON string for API output.
	 *
	 * @param string $query               JSON encoded string with "item.query" configuration
	 */
	public static function prepareQueryFieldForApi(string $query): array {
		if ($query === '') {
			return [];
		}

		$query = json_decode($query, true);

		if (json_last_error() != JSON_ERROR_NONE) {
			return [];
		}

		return $query;
	}

	/**
	 * Convert "query.filter" from expression (database, audit log: "{0} or {1}") to formula (API: "A or B").
	 *
	 * @param array $query  Item "query" configuration.
	 * @return array
	 */
	public static function convertFilterExpressionToFormula(array $query): array {
		if ($query['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			return $query;
		}

		$i = 0;

		foreach ($query['filter']['conditions'] as &$condition) {
			$condition['formulaid'] = num2letter($i);
			$i++;
		}
		unset($condition);

		CConditionHelper::replaceConditionIds($query['filter']['formula'], $query['filter']['conditions']);

		return $query;
	}

	/**
	 * Convert "query.filter" from formula (API: "A or B") to expression (database, audit log: "{0} or {1}").
	 *
	 * @param array $query  Item "query" configuration.
	 * @return array
	 */
	public static function convertFilterFormulaToExpression(array $query): array {
		if ($query['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			return $query;
		}

		CConditionHelper::replaceFormulaIds($query['filter']['formula'], $query['filter']['conditions']);

		foreach ($query['filter']['conditions'] as &$condition) {
			unset($condition['formulaid']);
		}
		unset($condition);

		return $query;
	}

	private static function getQueryFieldValidationRules(array $item): array {
		switch ($item['query']['signal_type'] ?? self::SIGNAL_TYPE_TRACES) {
			case self::SIGNAL_TYPE_TRACES:
				$columns_column = self::TRACES_COLUMNS_COLUMN;
				$aggregated_column = self::TRACES_AGGREGATED_COLUMN;
				$condition_column = self::TRACES_CONDITIONS_COLUMN;
				break;

			case self::SIGNAL_TYPE_LOGS:
				$columns_column = self::LOGS_COLUMNS_COLUMN;
				$aggregated_column = self::LOGS_AGGREGATED_COLUMN;
				$condition_column = self::LOGS_CONDITIONS_COLUMN;
				break;

			case self::SIGNAL_TYPE_METRICS:
				$point_type = $item['query']['metric_point_type'] ?? self::METRICS_POINT_SUM;
				$columns_column = self::METRICS_COLUMNS_COLUMN[$point_type] ?? [];
				$aggregated_column = self::METRICS_AGGREGATED_COLUMN[$point_type] ?? [];
				$condition_column = self::METRICS_CONDITIONS_COLUMN[$point_type] ?? [];
				break;

			default:
				$columns_column = [];
				$aggregated_column = [];
				$condition_column = [];
				break;
		}

		$condition_fields = [
			'column' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', $condition_column)],
			'attribute_key' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'column', 'in' => implode(',', self::COMPLEX_COLUMN_NAME)],
										'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]],
			'operator' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'column', 'in' => implode(',', self::COMPLEX_COLUMN_NAME)],
										'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_EXISTS])],
									['else' => true, 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])]
			]],
			'value' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operator', 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])],
										'type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]]
		];

		return [
			'signal_type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::SIGNAL_TYPE_TRACES, self::SIGNAL_TYPE_METRICS, self::SIGNAL_TYPE_LOGS])],
			'metric_point_type' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'signal_type', 'in' => self::SIGNAL_TYPE_METRICS],
												'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [self::METRICS_POINT_SUM, self::METRICS_POINT_GAUGE, self::METRICS_POINT_HISTOGRAM, self::METRICS_POINT_EXPONENTIAL_HISTOGRAM])],
											['else' => true, 'type' => API_INT32, 'in' => self::METRICS_POINT_SUM, 'default' => self::METRICS_POINT_SUM]
			]],
			'columns' =>				['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'uniq' => [['column', 'attribute_key']], 'fields' => [
				'column' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', $columns_column)],
				'attribute_key' =>			['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'column', 'in' => implode(',', self::COMPLEX_COLUMN_NAME)],
													'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]]]],
			'aggregated_columns' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['alias']], 'fields' => [
				'function' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_PERCENTILE])],
				'column' =>					['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'function', 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_SUM, AGGREGATE_PERCENTILE])],
													'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', $aggregated_column)],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
				]],
				'parameters' =>				['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'function', 'in' => AGGREGATE_PERCENTILE],
													'type' => API_FLOATS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => '0:100'],
												['else' => true, 'type' => API_OBJECTS, 'length' => 0, 'unset' => true]
				]],
				'alias' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
			]],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'evaltype' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
				'formula' =>				['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION],
													'type' => API_COND_FORMULA, 'flags' => API_REQUIRED],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
				]],
				'conditions' =>				['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION],
													'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['formulaid']], 'fields' => [
														'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
													] + $condition_fields],
												['else' => true, 'type' => API_OBJECTS, 'fields' => $condition_fields]
				]]
			]]
		];
	}
}
