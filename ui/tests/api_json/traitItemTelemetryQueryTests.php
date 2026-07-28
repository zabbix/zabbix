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
 * Telemetry Query item and item prototype tests data.
 */
trait traitItemTelemetryQueryTests {

	public static function initItemTestsData() {
		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'telemetry_query_template_group']
			],
			'host_groups' => [
				['name' => 'telemetry_query_host_group']
			],
			'templates' => [
				[
					'host' => 'telemetry_query_template',
					'groups' => ['groupid' => ':template_group:telemetry_query_template_group'],
					'items' => [
						[
							'name' => 'template item',
							'key_' => 'template_item_telemetry_query',
							'type' => ITEM_TYPE_TELEMETRY_QUERY,
							'delay' => '1m',
							'value_type' => ITEM_VALUE_TYPE_UINT64,
							'query' => [
								'signal_type' => APM_SIGNAL_TYPE_TRACES,
								'columns' => [],
								'aggregated_columns' => [['alias' => 'Timestamp']],
								'filter' => []
							]
						]
					]
				]
			],
			'hosts' => [
				[
					'host' => 'telemetry_query_host',
					'groups' => ['groupid' => ':host_group:telemetry_query_host_group'],
					'templates' => [['templateid' => ':template:telemetry_query_template']],
					'items' => [
						[
							'name' => 'host item',
							'key_' => 'host_item_telemetry_query',
							'type' => ITEM_TYPE_TELEMETRY_QUERY,
							'delay' => '1m',
							'value_type' => ITEM_VALUE_TYPE_UINT64,
							'query' => [
								'signal_type' => APM_SIGNAL_TYPE_TRACES,
								'columns' => [],
								'aggregated_columns' => [['alias' => 'Timestamp']],
								'filter' => []
							]
						]
					]
				]
			]
		]);
	}

	public static function initItemPrototypeTestsData() {
		CTestDataHelper::createObjects([
			'template_groups' => [
				['name' => 'telemetry_query_template_group']
			],
			'host_groups' => [
				['name' => 'telemetry_query_host_group']
			],
			'templates' => [
				[
					'host' => 'telemetry_query_template',
					'groups' => ['groupid' => ':template_group:telemetry_query_template_group'],
					'lld_rules' => [
						[
							'name' => 'template lld',
							'key_' => 'template_lld_telemetry_query',
							'item_prototypes' => [
								[
									'name' => 'template item prototype',
									'key_' => 'template_item_prototype_telemetry_query[{#M}]',
									'type' => ITEM_TYPE_TELEMETRY_QUERY,
									'delay' => '1m',
									'value_type' => ITEM_VALUE_TYPE_UINT64,
									'query' => [
										'signal_type' => APM_SIGNAL_TYPE_TRACES,
										'columns' => [],
										'aggregated_columns' => [['alias' => 'Timestamp']],
										'filter' => []
									]
								]
							]
						]
					]
				]
			],
			'hosts' => [
				[
					'host' => 'telemetry_query_host',
					'groups' => ['groupid' => ':host_group:telemetry_query_host_group'],
					'lld_rules' => [
						[
							'name' => 'host lld',
							'key_' => 'host_lld_telemetry_query',
							'item_prototypes' => [
								[
									'name' => 'host item prototype',
									'key_' => 'host_item_prototype_telemetry_query[{#M}]',
									'delay' => '1m',
									'type' => ITEM_TYPE_TELEMETRY_QUERY,
									'value_type' => ITEM_VALUE_TYPE_UINT64,
									'query' => [
										'signal_type' => APM_SIGNAL_TYPE_TRACES,
										'columns' => [],
										'aggregated_columns' => [['alias' => 'Timestamp']],
										'filter' => []
									]
								]
							]
						]
					]
				]
			]
		]);
	}

	public static function dataProviderTelemetryQueryCreate() {
		$params = [
			'query' => ['aggregated_columns' => [['alias' => 'Timestamp']]]
		];

		yield 'user macro for "time_shift", "lookback_limit" and "granularity"' => [
			[
				'time_shift' => '{$M}',
				'lookback_limit' => '{$M}',
				'granularity' => '{$M}'
			] + $params,
			null
		];

		yield 'empty "query.filter"' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => []
				] + $params['query']
			],
			null
		];

		yield 'duplicate "query.filter.conditions[].column"' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A'],
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'B']
						]
					]
				] + $params['query']
			],
			null
		];

		yield '"query.columns[].attribute_key" for complex "query.columns[].column"' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_METRICS,
					'metric_point_type' => APM_METRICS_POINT_SUM,
					'columns' => [
						['column' => 'Exemplars.FilteredAttributes', 'attribute_key' => 'attr1']
					]
				] + $params['query']
			],
			null
		];

		yield 'empty "query.columns[].attribute_key" for non complex "query.columns[].column"' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'TraceId', 'attribute_key' => '']
					]
				] + $params['query']
			],
			null
		];

		yield '"query.aggregated_columns[].parameters" set to single value array' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PCTILE, 'parameters' => [10], 'alias' => 'time']
					]
				]
			],
			null
		];

		yield 'serialized "query" longer than 64Kb fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'aggregated_columns' => [
						['function' => AGGREGATE_COUNT, 'alias' => str_repeat('A', 65535)]
					]
				]
			],
			'Invalid parameter "/1/query": value is too long.'
		];

		yield 'binary "value_type" fail' => [
			[
				'value_type' => ITEM_VALUE_TYPE_BINARY
			] + $params,
			'Invalid parameter "/1/value_type": value must be one of 0, 1, 2, 3, 4, 6.'
		];

		yield 'no "query" fail' => [
			[],
			'Invalid parameter "/1": the parameter "query" is missing.'
		];

		yield '"query" is string fail' => [
			[
				'query' => ''
			],
			'Invalid parameter "/1/query": an array is expected.'
		];

		yield 'no "query.aggregated_columns" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [['column' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [['column' => 'TraceId', 'value' => 'test']]
					]
				]
			],
			'Invalid parameter "/1/query": the parameter "aggregated_columns" is missing.'
		];

		yield '"evaltype" not expression and "query.filter.formula" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => 'A or A',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A']
						]
					]
				] + $params['query']
			],
			'Invalid parameter "/1/query/filter/formula": value must be empty.'
		];

		yield '"query.filter.formula" more conditions than "query.filter.conditions" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A']
						]
					]
				] + $params['query']
			],
			'Invalid parameter "/1/query/filter/conditions": incorrect number of conditions.'
		];

		yield '"query.filter.formula" less conditions than "query.filter.conditions" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or A',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A'],
							['column' => 'TraceId', 'value' => 'test2', 'formulaid' => 'B']
						]
					]
				] + $params['query']
			],
			'Invalid parameter "/1/query/filter/conditions": incorrect number of conditions.'
		];

		yield '"query.filter.formula" non existing conditions in "query.filter.conditions" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or D',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A'],
							['column' => 'TraceId', 'value' => 'test2', 'formulaid' => 'B']
						]
					]
				] + $params['query']
			],
			'Invalid parameter "/1/query/filter/conditions/2/formulaid": an identifier is not defined in the formula.'
		];

		yield '"query.filter.conditions" not unique "formulaid" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or A',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A'],
							['column' => 'TraceId', 'value' => 'test2', 'formulaid' => 'A']
						]
					]
				] + $params['query']
			],
			'Invalid parameter "/1/query/filter/conditions/2": value (formulaid)=(A) already exists.'
		];

		yield '"query.columns[].attribute_key" for non complex "query.columns[].column" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'Timestamp', 'attribute_key' => 'attr1']
					]
				] + $params['query']
			],
			'Invalid parameter "/1/query/columns/1/attribute_key": value must be empty.'
		];

		yield '"query.aggregated_columns[].parameters" set to single invalid value array fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PCTILE, 'parameters' => [200], 'alias' => 'time']
					]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/parameters/1": value must be one of 1-100.'
		];

		yield '"query.aggregated_columns[].parameters" set to multiple values array fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PCTILE, 'parameters' => [2, 10], 'alias' => 'time']
					]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/parameters": maximum number of array elements is 1.'
		];

		yield 'duplicates in "query.columns[].column" and "query.aggregated_columns[].alias" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'TraceId']
					],
					'aggregated_columns' => [
						['function' => AGGREGATE_COUNT, 'alias' => 'TraceId']
					]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/alias": value (TraceId) already exists.'
		];

		yield 'duplicates in complex "query.columns[].column","query.columns[].attribute_key" and "query.aggregated_columns[].alias" fail' => [
			[
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'aggregated_columns' => [
						['function' => AGGREGATE_COUNT, 'alias' => 'SpanAttributes.attr']
					],
					'columns' => [
						['column' => 'SpanAttributes', 'attribute_key' => 'attr']
					]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/alias": value (SpanAttributes.attr) already exists.'
		];
	}

	public static function dataProviderTelemetryQueryUpdate() {
		yield 'only "time_shift" changes' => [
			['time_shift' => '2s'],
			null
		];

		yield '"delay" and "timeout"' => [
			[
				'delay' => '3m',
				'timeout' => '5s'
			],
			null
		];

		yield 'only "query" field' => [
			[
				'name' => 'Telemetry query updated query',
				'query' => [
					'signal_type' => APM_SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'ScopeVersion'],
						['column' => 'ScopeName']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A'],
							['column' => 'ScopeName', 'value' => 'test', 'formulaid' => 'B']
						]
					],
					'aggregated_columns' => [
						['column' => 'Duration', 'function' => AGGREGATE_MIN, 'alias' => 'duration'],
						['alias' => 'Timestamp']
					]
				]
			],
			null
		];

		yield '"query" empty array fail' => [
			['query' => ''],
			'Invalid parameter "/1/query": an array is expected.'
		];
	}

	public static function dataProviderHostInheritedTelemetryQueryUpdate() {
		yield 'user macro in "time_shift", "lookback_limit" and "granularity" for inherited' => [
			[
				'time_shift' => '{$M}',
				'lookback_limit' => '{$M}',
				'granularity' => '{$M}'
			],
			null
		];

		yield '"delay" for inherited' => [
			[
				'delay' => '2m'
			],
			null
		];

		yield '"timeout" for inherited fail' => [
			[
				'timeout' => '10s'
			],
			'Invalid parameter "/1": cannot update readonly parameter "timeout" of inherited object.'
		];

		yield '"query" for inherited fail' => [
			[
				'query' => [
					'query' => ['aggregated_columns' => [['alias' => 'Timestamp']]]
				]
			],
			'Invalid parameter "/1": cannot update readonly parameter "query" of inherited object.'
		];
	}
}
