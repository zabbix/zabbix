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

	public const COMPLEX_COLUMN_NAME = [
		'SpanAttributes', 'Events.Attributes', 'LogAttributes', 'ResourceAttributes', 'ScopeAttributes', 'Attributes',
		'Exemplars.FilteredAttributes'
	];

	// APM_SIGNAL_TYPE_TRACES column names
	public const APM_TRACES_COLUMNS_COLUMN = [
		'Timestamp', 'TraceId', 'SpanId', 'ParentSpanId', 'TraceState', 'SpanName', 'SpanKind', 'ServiceName',
		'ResourceAttributes', 'SpanAttributes', 'ScopeName', 'ScopeVersion', 'Duration', 'StatusCode',
		'StatusMessage', 'Events.Name', 'Events.Attributes'
	];
	public const APM_TRACES_AGGREGATED_COLUMN = [
		'Timestamp', 'Duration'
	];
	public const APM_TRACES_CONDITIONS_COLUMN = [
		'TraceId', 'SpanId', 'ParentSpanId', 'TraceState', 'SpanName', 'SpanKind', 'ServiceName', 'ResourceAttributes',
		'SpanAttributes', 'ScopeName', 'ScopeVersion', 'StatusCode', 'StatusMessage', 'Events.Attributes', 'Events.Name'
	];

	// APM_SIGNAL_TYPE_LOGS column names
	public const APM_LOGS_COLUMNS_COLUMN = [
		'Timestamp', 'TraceId', 'SpanId', 'TraceFlags', 'SeverityText', 'SeverityNumber', 'ServiceName', 'Body',
		'ResourceSchemaUrl', 'ScopeSchemaUrl', 'ScopeName', 'ScopeVersion', 'ResourceAttributes', 'ScopeAttributes',
		'LogAttributes', 'EventName'
	];
	public const APM_LOGS_AGGREGATED_COLUMN = [
		'Timestamp', 'SeverityNumber'
	];
	public const APM_LOGS_CONDITIONS_COLUMN = [
		'TraceId', 'SpanId', 'TraceFlags', 'SeverityText', 'ServiceName', 'Body', 'ResourceSchemaUrl', 'ScopeSchemaUrl',
		'ScopeName', 'ScopeVersion', 'ResourceAttributes', 'ScopeAttributes', 'LogAttributes', 'EventName'
	];

	// APM_SIGNAL_TYPE_METRICS column names
	public const APM_METRICS_COLUMNS_COLUMN = [
		APM_METRICS_POINT_SUM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Value', 'Flags', 'Exemplars.FilteredAttributes',
			'AggregationTemporality', 'IsMonotonic'
		],
		APM_METRICS_POINT_GAUGE => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription',
			'MetricUnit', 'Attributes', 'StartTimeUnix', 'TimeUnix', 'Value', 'Flags', 'Exemplars.FilteredAttributes'
		],
		APM_METRICS_POINT_HISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Exemplars.FilteredAttributes', 'Flags',
			'Min', 'Max', 'AggregationTemporality'
		],
		APM_METRICS_POINT_EXPHISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeDroppedAttrCount', 'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit',
			'Attributes', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Scale', 'ZeroCount', 'PositiveOffset',
			'NegativeOffset', 'Exemplars.FilteredAttributes', 'Flags', 'Min', 'Max', 'AggregationTemporality'
		]
	];
	public const APM_METRICS_AGGREGATED_COLUMN = [
		APM_METRICS_POINT_SUM => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Value'
		],
		APM_METRICS_POINT_GAUGE => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Value'
		],
		APM_METRICS_POINT_HISTOGRAM => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Min', 'Max'
		],
		APM_METRICS_POINT_EXPHISTOGRAM => [
			'ScopeDroppedAttrCount', 'StartTimeUnix', 'TimeUnix', 'Count', 'Sum', 'Scale', 'ZeroCount',
			'PositiveOffset', 'NegativeOffset', 'Min', 'Max'
		]
	];
	public const APM_METRICS_CONDITIONS_COLUMN = [
		APM_METRICS_POINT_SUM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes',
			'ScopeSchemaUrl', 'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes',
			'Exemplars.FilteredAttributes'
		],
		APM_METRICS_POINT_GAUGE => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		],
		APM_METRICS_POINT_HISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		],
		APM_METRICS_POINT_EXPHISTOGRAM => [
			'ResourceAttributes', 'ResourceSchemaUrl', 'ScopeName', 'ScopeVersion', 'ScopeAttributes', 'ScopeSchemaUrl',
			'ServiceName', 'MetricName', 'MetricDescription', 'MetricUnit', 'Attributes', 'Exemplars.FilteredAttributes'
		]
	];

	/**
	 * @inheritDoc
	 */
	public static function getCreateValidationRules(array $item): array {
		return [
			'time_shift'		=> ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.(1 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'time_shift'), 'default' => DB::getDefault('items', 'time_shift')],
			'lookback_limit'	=> ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.(3 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'lookback_limit'), 'default' => DB::getDefault('items', 'lookback_limit')],
			'granularity'		=> ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.(1 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'granularity'), 'default' => DB::getDefault('items', 'granularity')],
			'query'				=> ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => self::getQueryFieldValidationRules($item)]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRules(array $db_item): array {
		return [
			'time_shift'		=> ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.(1 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'time_shift')],
			'lookback_limit'	=> ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.(3 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'lookback_limit')],
			'granularity'		=> ['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.(1 * SEC_PER_DAY), 'length' => DB::getFieldLength('items', 'granularity')],
			'query'				=> ['type' => API_OBJECT, 'fields' => self::getQueryFieldValidationRules($db_item)]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesInherited(array $db_item): array {
		// TODO
		throw new Exception('Not implemented');
	}

	/**
	 * @inheritDoc
	 */
	public static function getUpdateValidationRulesDiscovered(): array {
		// TODO
		throw new Exception('Not implemented');
	}

	/**
	 * Validate "query.aggregated_columns":
	 * - for "function" AGGREGATE_PCTILE "parameters" array may have only single value
	 *
	 * @param array       $item   Telemetry item to validate.
	 * @param string      $path   Path for validation message.
	 * @param string|null $error  Error message when validation fails, set by reference.
	 */
	public static function validateAggregatedColumns(array $item, string $path, ?string &$error): bool {
		foreach ($item['query']['aggregated_columns'] as $i => $column) {
			if ($column['function'] == AGGREGATE_PCTILE && count($column['parameters']) != 1) {
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
	 * Validate value is unique in columns:
	 * - "query.columns[].column" (or "query.columns[].column" and "query.columns[].attribute_key" for complex column)
	 * - "query.aggregated_columns[].column"
	 * - "query.aggregated_columns[].alias"
	 *
	 * @param array       $item   Telemetry item to validate.
	 * @param string      $path   Path in validation message.
	 * @param string|null $error  Error message when validation fails, set by reference.
	 */
	public static function validateColumnsAggregatedColumnsUnique(array $item, string $path, ?string &$error): bool {
		$uniq = [];

		foreach ($item['query']['columns'] as $i => $column) {
			$uniq_value = in_array($column['column'], self::COMPLEX_COLUMN_NAME)
				? $column['column'].'.'.$column['attribute_key']
				: $column['column'];

			if (array_key_exists($uniq_value, $uniq)) {
				$error = _s('Invalid parameter "%1$s": %2$s.',
					$path.'/query/columns/'.($i + 1).'/column',
					_s('value %1$s already exists', '('.$uniq_value.')')
				);

				return false;
			}

			$uniq[$uniq_value] = true;
		}

		foreach ($item['query']['aggregated_columns'] as $i => $column) {
			$uniq_value = $column['column'];

			if ($uniq_value !== '' && array_key_exists($uniq_value, $uniq)) {
				$error = _s('Invalid parameter "%1$s": %2$s.',
					$path.'/query/aggregated_columns/'.($i + 1).'/column',
					_s('value %1$s already exists', '('.$uniq_value.')')
				);

				return false;
			}
			elseif ($uniq_value !== '') {
				$uniq[$uniq_value] = true;
			}

			$uniq_value = $column['alias'];

			if ($uniq_value !== '' && array_key_exists($uniq_value, $uniq)) {
				$error = _s('Invalid parameter "%1$s": %2$s.',
					$path.'/query/aggregated_columns/'.($i + 1).'/alias',
					_s('value %1$s already exists', '('.$uniq_value.')')
				);

				return false;
			}
			elseif ($uniq_value !== '') {
				$uniq[$uniq_value] = true;
			}

			$uniq[$uniq_value] = true;
		}

		return true;
	}

	private static function getQueryFieldValidationRules(array $item): array {
		switch ($item['query']['signal_type'] ?? APM_SIGNAL_TYPE_TRACES) {
			case APM_SIGNAL_TYPE_TRACES:
				$columns_column = self::APM_TRACES_COLUMNS_COLUMN;
				$aggregated_column = self::APM_TRACES_AGGREGATED_COLUMN;
				$condition_column = self::APM_TRACES_CONDITIONS_COLUMN;
				break;

			case APM_SIGNAL_TYPE_LOGS:
				$columns_column = self::APM_LOGS_COLUMNS_COLUMN;
				$aggregated_column = self::APM_LOGS_AGGREGATED_COLUMN;
				$condition_column = self::APM_LOGS_CONDITIONS_COLUMN;
				break;

			case APM_SIGNAL_TYPE_METRICS:
				$point_type = $item['query']['metric_point_type'] ?? APM_METRICS_POINT_SUM;
				$columns_column = self::APM_METRICS_COLUMNS_COLUMN[$point_type];
				$aggregated_column = self::APM_METRICS_AGGREGATED_COLUMN[$point_type];
				$condition_column = self::APM_METRICS_CONDITIONS_COLUMN[$point_type];
				break;

			default:
				$columns_column = [];
				$aggregated_column = [];
				$condition_column = [];
				break;
		}

		$condition_fields = [
			'column'			=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', $condition_column)],
			'attribute_key'		=> ['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'column', 'in' => implode(',', self::COMPLEX_COLUMN_NAME)], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]],
			'operator'			=> ['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'column', 'in' => implode(',', self::COMPLEX_COLUMN_NAME)], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_EXISTS]), 'default' => CONDITION_OPERATOR_EQUAL],
									['else' => true, 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE]), 'default' => CONDITION_OPERATOR_EQUAL]
			]],
			'value'				=> ['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'operator', 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]]
		];

		/**
		 * TODO: validation
		 * - set 'length' for API_STRING_UTF8, API_COND_FORMULA rules
		 */
		return [
			'signal_type'			=> ['type' => API_INT32, 'in' => implode(',', [APM_SIGNAL_TYPE_TRACES, APM_SIGNAL_TYPE_METRICS, APM_SIGNAL_TYPE_LOGS]), 'default' => APM_SIGNAL_TYPE_TRACES],
			'metric_point_type'		=> ['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'signal_type', 'in' => APM_SIGNAL_TYPE_METRICS], 'type' => API_INT32, 'in' => implode(',', [APM_METRICS_POINT_SUM, APM_METRICS_POINT_GAUGE, APM_METRICS_POINT_HISTOGRAM, APM_METRICS_POINT_EXPHISTOGRAM]), 'default' => APM_METRICS_POINT_SUM],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]],
			'columns'				=> ['type' => API_OBJECTS, 'default' => [], 'uniq' => [['column', 'attribute_key']], 'fields' => [
				'column'				=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', $columns_column)],
				'attribute_key'			=> ['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'column', 'in' => implode(',', self::COMPLEX_COLUMN_NAME)], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
			]]]],
			'aggregated_columns'	=> ['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['column', 'alias']], 'fields' => [
				'function'				=> ['type' => API_INT32, 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_COUNT, AGGREGATE_SUM, AGGREGATE_PCTILE]), 'default' => AGGREGATE_COUNT],
				'column'				=> ['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'function', 'in' => implode(',', [AGGREGATE_MIN, AGGREGATE_MAX, AGGREGATE_AVG, AGGREGATE_SUM, AGGREGATE_PCTILE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => implode(',', $aggregated_column)],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
				]],
				'parameters'			=> ['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'function', 'in' => AGGREGATE_PCTILE], 'type' => API_INTS32, 'in' => '1:100', 'flags' => API_REQUIRED | API_NOT_EMPTY],
												['else' => true, 'type' => API_OBJECTS, 'length' => 0, 'default' => []]
				]],
				'alias'					=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
			]],
			'filter'				=> ['type' => API_OBJECT, 'fields' => [
				'evaltype'				=> ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
				'formula'				=> ['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED],
												['else' => true, 'type' => API_STRING_UTF8, 'in' => '', 'default' => '']
				]],
				'conditions'			=>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
												['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['formulaid']], 'fields' => [
													'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
												] + $condition_fields],
												['else' => true, 'type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'fields' => $condition_fields]
				]]
			]]
		];
	}
}
