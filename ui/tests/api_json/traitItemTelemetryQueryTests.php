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


require_once __DIR__.'/../../include/classes/api/item_types/CItemType.php';
require_once __DIR__.'/../../include/classes/api/item_types/CItemTypeTelemetryQuery.php';

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
							'type' => 24,
							'delay' => '1m',
							'value_type' => ITEM_VALUE_TYPE_UINT64,
							'query' => [
								'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
								'columns' => [],
								'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
								'filter' => [
									'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
									'conditions' => []
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
					'templates' => [['templateid' => ':template:telemetry_query_template']],
					'items' => [
						[
							'name' => 'host item',
							'key_' => 'host_item_telemetry_query',
							'type' => ITEM_TYPE_TELEMETRY_QUERY,
							'delay' => '1m',
							'value_type' => ITEM_VALUE_TYPE_UINT64,
							'query' => [
								'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
								'columns' => [],
								'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
								'filter' => [
									'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
									'conditions' => []
								]
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
										'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
										'columns' => [],
										'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
										'filter' => [
											'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
											'conditions' => []
										]
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
										'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
										'columns' => [],
										'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
										'filter' => [
											'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
											'conditions' => []
										]
									],
									'discovered_items' => [
										[
											'type' => ITEM_TYPE_TELEMETRY_QUERY,
											'key_' => 'host_discovered_telemetry_query[A]',
											'delay' => '1m',
											'query' => [
												'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
												'columns' => [],
												'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
												'filter' => [
													'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
													'conditions' => []
												]
											],
										]
									]
								]
							]
						]
					]
				]
			]
		]);
	}

	public static function dataProviderItemPrototypeTelemetryQueryCreate() {
		yield 'lld macro for "time_shift", "lookback_limit" and "granularity"' => [
			[
				'time_shift' => '{#M}',
				'lookback_limit' => '{#M}',
				'granularity' => '{#M}',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];
	}

	public static function dataProviderItemDiscoveredTelemetryQueryUpdate() {
		yield 'without changes' => [
			[],
			null
		];

		yield '"time_shift" fail' => [
			[
				'time_shift' => '{$M}'
			],
			'Invalid parameter "/1": unexpected parameter "time_shift".'
		];

		yield '"lookback_limit" fail' => [
			[
				'lookback_limit' => '{$M}'
			],
			'Invalid parameter "/1": unexpected parameter "lookback_limit".'
		];

		yield '"granularity" fail' => [
			[
				'granularity' => '{$M}'
			],
			'Invalid parameter "/1": unexpected parameter "granularity".'
		];
	}

	public static function dataProviderTelemetryQueryCreate() {
		yield 'user macro for "time_shift", "lookback_limit" and "granularity"' => [
			[
				'time_shift' => '{$M}',
				'lookback_limit' => '{$M}',
				'granularity' => '{$M}',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield '"granularity" value less than "lookback_limit" value' => [
			[
				'lookback_limit' => '30s',
				'granularity' => '10s',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield '"granularity" value user macro' => [
			[
				'lookback_limit' => '30s',
				'granularity' => '{$M}',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield 'duplicates in "query.aggregated_columns[].column" when "query.aggregated_columns[].alias" are unique' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'Timestamp']
					],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_MAX, 'alias' => 'time_max'],
						['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'time_min']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield 'duplicate "query.filter.conditions[].column"' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL],
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					]
				]
			],
			null
		];

		yield '"query.filter.conditions[].value" value empty string for operation equal/notequal/exists' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B or C',
						'conditions' => [
							['column' => 'TraceId', 'value' => '', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL],
							['column' => 'TraceId', 'value' => '', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_NOT_EQUAL],
							['column' => 'Events.Attributes', 'attribute_key' => 'a', 'value' => '', 'formulaid' => 'C', 'operator' => CONDITION_OPERATOR_EXISTS]
						]
					]
				]
			],
			null
		];

		yield '"query.columns[].attribute_key" for complex "query.columns[].column"' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					'columns' => [
						['column' => 'ResourceAttributes', 'attribute_key' => 'attr1']
					],
					'aggregated_columns' => [['column' => 'StartTimeUnix', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield 'empty "query.columns[].attribute_key" for non complex "query.columns[].column"' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'TraceId', 'attribute_key' => '']
					],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield '"query.aggregated_columns[].parameters" set to single value array' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PERCENTILE, 'parameters' => [10], 'alias' => 'time']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield '"query.aggregated_columns[].parameters" value fractional part is 4 digits' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PERCENTILE, 'parameters' => [10.1234], 'alias' => 'time']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			null
		];

		yield '"storage_mode" value fail' => [
			[
				'lookback_limit' => '10s',
				'storage_mode' => 0,
				'granularity' => '30s',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1": unexpected parameter "storage_mode".'
		];

		yield '"granularity" value greater than "lookback_limit" value fail' => [
			[
				'lookback_limit' => '10s',
				'granularity' => '30s',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/granularity": cannot be greater than the value of parameter "/1/lookback_limit".'
		];

		yield 'serialized "query" longer than 64Kb fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => array_map(
						static fn($i) => [
							'function' => AGGREGATE_COUNT, 'alias' => str_pad($i, 255, 'A')
						], range(0, 300, 1)
					),
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query": value is too long.'
		];

		yield 'binary "value_type" fail' => [
			[
				'value_type' => ITEM_VALUE_TYPE_BINARY,
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
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

		yield '"query.aggregated_columns" not set fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [['column' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query": the parameter "aggregated_columns" is missing.'
		];

		yield '"query.filter" not set fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']]
				]
			],
			'Invalid parameter "/1/query": the parameter "filter" is missing.'
		];

		yield '"evaltype" not expression and not empty "query.filter.formula" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'formula' => 'A or A',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A']
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/formula": value must be empty.'
		];

		yield '"query.filter.formula" more conditions than "query.filter.conditions" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions": incorrect number of conditions.'
		];

		yield '"query.filter.formula" less conditions than "query.filter.conditions" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or A',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL],
							['column' => 'TraceId', 'value' => 'test2', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions": incorrect number of conditions.'
		];

		yield '"query.filter.formula" non existing conditions in "query.filter.conditions" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or D',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL],
							['column' => 'TraceId', 'value' => 'test2', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions/2/formulaid": an identifier is not defined in the formula.'
		];

		yield '"query.filter.formula" value too long fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A'.str_repeat(' or A', 60),
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/formula": value is too long.'
		];

		yield '"query.filter.conditions" not unique "formulaid" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or A',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL],
							['column' => 'TraceId', 'value' => 'test2', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions/2": value (formulaid)=(A) already exists.'
		];

		yield '"query.filter.conditions[].value" value empty string for operation like fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => '', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_LIKE],
							['column' => 'TraceId', 'value' => 'a', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_NOT_LIKE]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions/1/value": cannot be empty.'
		];

		yield '"query.filter.conditions[].value" value empty string for operation not like fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'a', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_LIKE],
							['column' => 'TraceId', 'value' => '', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_NOT_LIKE]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions/2/value": cannot be empty.'
		];

		yield '"query.filter.conditions[].value" value too long fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_OR,
						'formula' => '',
						'conditions' => [
							['column' => 'TraceId', 'value' => str_repeat('a', 256), 'operator' => CONDITION_OPERATOR_LIKE]
						]
					]
				]
			],
			'Invalid parameter "/1/query/filter/conditions/1/value": value is too long.'
		];

		yield '"query.filter.conditions[].attribute_key" value too long fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							['column' => 'ResourceAttributes', 'attribute_key' => str_repeat('A', 256), 'operator' => CONDITION_OPERATOR_EQUAL]
					]]
				]
			],
			'Invalid parameter "/1/query/filter/conditions/1/attribute_key": value is too long.'
		];

		yield '"query.aggregated_columns[].alias" value start and end with whitespace fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => ' a ']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/alias": value cannot start or end with whitespace.'
		];

		yield '"query.aggregated_columns[].alias" value too long fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'StartTimeUnix', 'function' => AGGREGATE_MIN, 'alias' => str_repeat('A', 256)]
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/alias": value is too long.'
		];

		yield '"query.aggregated_columns[].parameters" set to single invalid value array fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PERCENTILE, 'parameters' => [200], 'alias' => 'time']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/parameters/1": value must be within the range of 0-100.'
		];

		yield '"query.aggregated_columns[].parameters" value have 5 fractional part digits fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PERCENTILE, 'parameters' => [10.12345], 'alias' => 'time']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/parameters/1": value cannot have more than 4 fractional digits.'
		];

		yield '"query.aggregated_columns[].parameters" set to multiple values array fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [],
					'aggregated_columns' => [
						['column' => 'Timestamp', 'function' => AGGREGATE_PERCENTILE, 'parameters' => [2, 10], 'alias' => 'time']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/parameters": maximum number of array elements is 1.'
		];

		yield '"query.columns[].attribute_key" set for non complex "query.columns[].column" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []],
					'columns' => [
						['column' => 'Timestamp', 'attribute_key' => 'attr1']
					]
				]
			],
			'Invalid parameter "/1/query/columns/1/attribute_key": value must be empty.'
		];

		yield '"query.columns[].attribute_key" value too long fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					'columns' => [
						['column' => 'ResourceAttributes', 'attribute_key' => str_repeat('A', 256)]
					],
					'aggregated_columns' => [['column' => 'StartTimeUnix', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/columns/1/attribute_key": value is too long.'
		];

		yield 'duplicates in "query.columns[].column" and "query.aggregated_columns[].alias" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'TraceId']
					],
					'aggregated_columns' => [
						['function' => AGGREGATE_COUNT, 'alias' => 'TraceId']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1": value (alias)=(TraceId) already exists.'
		];

		yield 'duplicates in complex "query.columns[].column","query.columns[].attribute_key" and "query.aggregated_columns[].alias" fail' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'SpanAttributes', 'attribute_key' => 'attr']
					],
					'aggregated_columns' => [
						['function' => AGGREGATE_COUNT, 'alias' => 'SpanAttributes.attr']
					],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1": value (alias)=(SpanAttributes.attr) already exists.'
		];
	}

	public static function dataProviderItemPrototypeTelemetryQueryUpdate() {
		yield 'lld macro for "time_shift", "lookback_limit" and "granularity"' => [
			[
				'time_shift' => '{#M}',
				'lookback_limit' => '{#M}',
				'granularity' => '{#M}'
			],
			null
		];

		yield '"query.signal_type" change validate "query.aggregated_columns[].column" not by database value' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/column": value must be one of "ScopeDroppedAttrCount", "StartTimeUnix", "TimeUnix", "Value".'
		];
	}

	public static function dataProviderTelemetryQueryUpdate() {
		yield '"time_shift", "lookback_limit" and "granularity"' => [
			[
				'time_shift' => '2s',
				'lookback_limit' => '2s',
				'granularity' => '2s'
			],
			null
		];

		yield '"time_shift" set to 0' =>[
			[
				'time_shift' => '0'
			],
			null
		];

		yield '"time_shift" empty value fail' =>[
			[
				'time_shift' => ''
			],
			'Invalid parameter "/1/time_shift": cannot be empty.'
		];

		yield '"lookback_limit" empty value fail' =>[
			[
				'lookback_limit' => ''
			],
			'Invalid parameter "/1/lookback_limit": cannot be empty.'
		];

		yield '"granularity" empty value fail' =>[
			[
				'granularity' => ''
			],
			'Invalid parameter "/1/granularity": cannot be empty.'
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
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
					'columns' => [
						['column' => 'ScopeVersion'],
						['column' => 'ScopeName']
					],
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'value' => 'test', 'formulaid' => 'A', 'operator' => CONDITION_OPERATOR_EQUAL],
							['column' => 'ScopeName', 'value' => 'test', 'formulaid' => 'B', 'operator' => CONDITION_OPERATOR_EQUAL]
						]
					],
					'aggregated_columns' => [
						['column' => 'Duration', 'function' => AGGREGATE_MIN, 'alias' => 'duration'],
						['column' => 'Timestamp', 'function' => AGGREGATE_MAX, 'alias' => 'Timestamp']
					]
				]
			],
			null
		];

		yield '"storage_mode" value fail' => [
			['storage_mode' => 0],
			'Invalid parameter "/1": unexpected parameter "storage_mode".'
		];

		yield '"query" empty fail' => [
			['query' => ''],
			'Invalid parameter "/1/query": an array is expected.'
		];

		yield '"query.signal_type" change validate "query.aggregated_columns[].column" not by database value' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_SUM,
					'columns' => [],
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
					'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
				]
			],
			'Invalid parameter "/1/query/aggregated_columns/1/column": value must be one of "ScopeDroppedAttrCount", "StartTimeUnix", "TimeUnix", "Value".'
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
					'query' => [
						'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
						'columns' => [],
						'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
						'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
					]
				]
			],
			'Invalid parameter "/1": cannot update readonly parameter "query" of inherited object.'
		];
	}
}
