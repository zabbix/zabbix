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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../../include/classes/api/item_types/CItemType.php';
require_once __DIR__.'/../../include/classes/api/item_types/CItemTypeTelemetryQuery.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 * @backup items
 */
class testDiscoveryRulePrototype extends CAPITest {

	public static function prepareTestData() {
		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'lld_prototype_telemetry_query_host_group']
			],
			'hosts' => [
				[
					'host' => 'lld_prototype_telemetry_query_host',
					'groups' => ['groupid' => ':host_group:lld_prototype_telemetry_query_host_group'],
					'lld_rules' => [
						[
							'name' => 'parent lld',
							'key_' => 'lld_parent',
							'type' => ITEM_TYPE_TRAPPER
						]
					],
					'lld_rule_prototypes' => [
						[
							'ruleid' => ':lld_rule:lld_parent',
							'name' => 'telemetry query lld prototype',
							'key_' => 'lld_telemetry_query[{#M}]',
							'type' => ITEM_TYPE_TELEMETRY_QUERY,
							'delay' => '1m',
							'query' => [
								'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
								'columns' => [],
								'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
								'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
							]
						],
						[
							'ruleid' => ':lld_rule:lld_parent',
							'name' => 'trapper lld prototype',
							'key_' => 'lld_trapper[{#M}]',
							'type' => ITEM_TYPE_TRAPPER
						]
					]
				]
			]
		]);
	}

	public static function cleanTestData() {
		CTestDataHelper::cleanUp();
	}

	public static function dataInvalidDiscoveryRulePrototypeTelemetryQuery_Create() {
		$discoveryrule = [
			'name' => 'API LLD rule prototype telemetry query',
			'hostid' => ':host:lld_prototype_telemetry_query_host',
			'ruleid' => ':lld_rule:lld_parent',
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'delay' => '30s'
		];
		$query = [
			'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
			'columns' => [],
			'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
			'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
		];

		yield 'Telemetry query "granularity" cannot be empty' => [
			[
				'key_' => 'telemetry_query_create_invalid_1[{#M}]',
				'granularity' => '',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/granularity": cannot be empty.'
		];

		yield 'Telemetry query "granularity" less than allowed' => [
			[
				'key_' => 'telemetry_query_create_invalid_2[{#M}]',
				'granularity' => '0',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/granularity": value must be one of 1-86400.'
		];

		yield 'Telemetry query "granularity" greater than allowed' => [
			[
				'key_' => 'telemetry_query_create_invalid_3[{#M}]',
				'granularity' => '86401s',
				'lookback_limit' => '3d',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/granularity": value must be one of 1-86400.'
		];

		yield 'Telemetry query "granularity" cannot be greater than "lookback_limit"' => [
			[
				'key_' => 'telemetry_query_create_invalid_4[{#M}]',
				'granularity' => '30s',
				'lookback_limit' => '10s',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/granularity": cannot be greater than the value of parameter "/1/lookback_limit".'
		];

		yield 'Telemetry query "granularity" is not supported for non telemetry query type' => [
			[
				'key_' => 'telemetry_query_create_invalid_5[{#M}]',
				'type' => ITEM_TYPE_TRAPPER,
				'delay' => '0',
				'granularity' => '30s'
			] + $discoveryrule,
			'Invalid parameter "/1/granularity": value must be "15s".'
		];

		yield 'Telemetry query "lookback_limit" cannot be empty' => [
			[
				'key_' => 'telemetry_query_create_invalid_6[{#M}]',
				'lookback_limit' => '',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/lookback_limit": cannot be empty.'
		];

		yield 'Telemetry query "lookback_limit" less than allowed' => [
			[
				'key_' => 'telemetry_query_create_invalid_7[{#M}]',
				'lookback_limit' => '0',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/lookback_limit": value must be one of 1-259200.'
		];

		yield 'Telemetry query "lookback_limit" greater than allowed' => [
			[
				'key_' => 'telemetry_query_create_invalid_8[{#M}]',
				'lookback_limit' => '259201s',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/lookback_limit": value must be one of 1-259200.'
		];

		yield 'Telemetry query "lookback_limit" is not supported for non telemetry query type' => [
			[
				'key_' => 'telemetry_query_create_invalid_9[{#M}]',
				'type' => ITEM_TYPE_TRAPPER,
				'delay' => '0',
				'lookback_limit' => '30s'
			] + $discoveryrule,
			'Invalid parameter "/1/lookback_limit": value must be "10m".'
		];

		yield 'Telemetry query "time_shift" cannot be empty' => [
			[
				'key_' => 'telemetry_query_create_invalid_10[{#M}]',
				'time_shift' => '',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/time_shift": cannot be empty.'
		];

		yield 'Telemetry query "time_shift" greater than allowed' => [
			[
				'key_' => 'telemetry_query_create_invalid_11[{#M}]',
				'time_shift' => '86401s',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/time_shift": value must be one of 0-86400.'
		];

		yield 'Telemetry query "query" is required' => [
			$discoveryrule + [
				'key_' => 'telemetry_query_create_invalid_12[{#M}]',
			],
			'Invalid parameter "/1": the parameter "query" is missing.'
		];

		yield 'Telemetry query "query.signal_type" is required' => [
			[
				'key_' => 'telemetry_query_create_invalid_13[{#M}]',
				'query' => []
			] + $discoveryrule,
			'Invalid parameter "/1/query": the parameter "signal_type" is missing.'
		];

		yield 'Telemetry query "query" unexpected parameter' => [
			[
				'key_' => 'telemetry_query_create_invalid_14[{#M}]',
				'query' => $query + ['unexpected' => '']
			] + $discoveryrule,
			'Invalid parameter "/1/query": unexpected parameter "unexpected".'
		];

		yield 'Telemetry query "query.signal_type" unsupported value' => [
			[
				'key_' => 'telemetry_query_create_invalid_15[{#M}]',
				'query' => ['signal_type' => 3] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/signal_type": value must be one of '.implode(', ', [
				CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES, CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
				CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS
			]).'.'
		];

		yield 'Telemetry query "query.signal_type" metrics require "query.metric_point_type"' => [
			[
				'key_' => 'telemetry_query_create_invalid_16[{#M}]',
				'query' => ['signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query": the parameter "metric_point_type" is missing.'
		];

		yield 'Telemetry query "query.aggregated_columns" cannot be empty' => [
			[
				'key_' => 'telemetry_query_create_invalid_17[{#M}]',
				'query' => ['aggregated_columns' => []] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/aggregated_columns": cannot be empty.'
		];

		yield 'Telemetry query "query.aggregated_columns[].alias" cannot be "clock"' => [
			[
				'key_' => 'telemetry_query_create_invalid_24[{#M}]',
				'query' => [
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'clock']]
				] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/aggregated_columns/1/alias": value must be not "clock".'
		];

		yield 'Telemetry query "query.columns" column' => [
			[
				'key_' => 'telemetry_query_create_invalid_18[{#M}]',
				'query' => ['columns' => [['column' => 'Body']]] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/columns/1/column": value must be one of "'.
				implode('", "', CItemTypeTelemetryQuery::TRACES_COLUMNS_COLUMN).'".'
		];

		yield 'Telemetry query "query.filter" is required' => [
			[
				'key_' => 'telemetry_query_create_invalid_19[{#M}]',
				'query' => array_diff_key($query, ['filter' => ''])
			] + $discoveryrule,
			'Invalid parameter "/1/query": the parameter "filter" is missing.'
		];

		yield 'Telemetry query "query.filter.formula" is required for custom expression' => [
			[
				'key_' => 'telemetry_query_create_invalid_20[{#M}]',
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'conditions' => [
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A']
						]
					]
				] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/filter": the parameter "formula" is missing.'
		];

		yield 'Telemetry query "query.filter.formula" has more conditions than "query.filter.conditions"' => [
			[
				'key_' => 'telemetry_query_create_invalid_21[{#M}]',
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A']
						]
					]
				] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/filter/conditions": incorrect number of conditions.'
		];

		yield 'Telemetry query "query.filter.conditions[].formulaid" is not used in "query.filter.formula"' => [
			[
				'key_' => 'telemetry_query_create_invalid_22[{#M}]',
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A'],
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'c', 'formulaid' => 'C']
						]
					]
				] + $query
			] + $discoveryrule,
			'Invalid parameter "/1/query/filter/conditions/2/formulaid": an identifier is not defined in the formula.'
		];

		yield 'Telemetry query "query" should be empty for non telemetry query type' => [
			[
				'key_' => 'telemetry_query_create_invalid_23[{#M}]',
				'type' => ITEM_TYPE_TRAPPER,
				'delay' => '0',
				'query' => $query
			] + $discoveryrule,
			'Invalid parameter "/1/query": should be empty.'
		];
	}

	public static function dataValidDiscoveryRulePrototypeTelemetryQuery_Create() {
		$discoveryrule = [
			'name' => 'API LLD rule prototype telemetry query',
			'hostid' => ':host:lld_prototype_telemetry_query_host',
			'ruleid' => ':lld_rule:lld_parent',
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'delay' => '30s'
		];
		$query = [
			'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
			'columns' => [],
			'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
			'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
		];

		yield 'Telemetry query with default "time_shift", "lookback_limit" and "granularity"' => [
			[
				'key_' => 'telemetry_query_create_1[{#M}]',
				'query' => $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query with minimal "time_shift", "lookback_limit" and "granularity"' => [
			[
				'key_' => 'telemetry_query_create_2[{#M}]',
				'time_shift' => '0',
				'lookback_limit' => '1s',
				'granularity' => '1s',
				'query' => $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query with maximal "time_shift", "lookback_limit" and "granularity"' => [
			[
				'key_' => 'telemetry_query_create_3[{#M}]',
				'time_shift' => '1d',
				'lookback_limit' => '3d',
				'granularity' => '1d',
				'query' => $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query "granularity" equal to "lookback_limit"' => [
			[
				'key_' => 'telemetry_query_create_4[{#M}]',
				'lookback_limit' => '5m',
				'granularity' => '300',
				'query' => $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query user macro in "time_shift", "lookback_limit" and "granularity"' => [
			[
				'key_' => 'telemetry_query_create_5[{#M}]',
				'time_shift' => '{$MACRO}',
				'lookback_limit' => '{$MACRO}',
				'granularity' => '{$MACRO}',
				'query' => $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query LLD macro in "time_shift", "lookback_limit" and "granularity"' => [
			[
				'key_' => 'telemetry_query_create_6[{#M}]',
				'time_shift' => '{#MACRO}',
				'lookback_limit' => '{#MACRO}',
				'granularity' => '{#MACRO}',
				'query' => $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query "query.signal_type" logs' => [
			[
				'key_' => 'telemetry_query_create_7[{#M}]',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_LOGS,
					'columns' => [['column' => 'ServiceName'], ['column' => 'LogAttributes', 'attribute_key' => 'key']],
					'aggregated_columns' => [['column' => 'SeverityNumber', 'function' => AGGREGATE_MAX, 'alias' => 'max']]
				] + $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query "query.signal_type" metrics' => [
			[
				'key_' => 'telemetry_query_create_8[{#M}]',
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
					'columns' => [['column' => 'MetricName']],
					'aggregated_columns' => [['column' => 'Value', 'function' => AGGREGATE_AVG, 'alias' => 'avg']]
				] + $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query "query.aggregated_columns" with count and percentile functions' => [
			[
				'key_' => 'telemetry_query_create_9[{#M}]',
				'query' => [
					'aggregated_columns' => [
						['function' => AGGREGATE_COUNT, 'alias' => 'count'],
						['column' => 'Duration', 'function' => AGGREGATE_PERCENTILE, 'parameters' => [99.9999], 'alias' => 'p99']
					]
				] + $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query "query.filter" with "and/or" conditions' => [
			[
				'key_' => 'telemetry_query_create_10[{#M}]',
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
						'conditions' => [
							['column' => 'ServiceName', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'api'],
							['column' => 'SpanName', 'operator' => CONDITION_OPERATOR_LIKE, 'value' => 'GET']
						]
					]
				] + $query
			] + $discoveryrule,
			null
		];

		yield 'Telemetry query "query.filter" with custom expression' => [
			[
				'key_' => 'telemetry_query_create_11[{#M}]',
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A'],
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_NOT_EQUAL, 'value' => 'b', 'formulaid' => 'B']
						]
					]
				] + $query
			] + $discoveryrule,
			null
		];
	}

	/**
	 * @dataProvider dataInvalidDiscoveryRulePrototypeTelemetryQuery_Create
	 * @dataProvider dataValidDiscoveryRulePrototypeTelemetryQuery_Create
	 */
	public function testDiscoveryRulePrototypeTelemetryQuery_Create(array $discoveryrule, ?string $error) {
		CTestDataHelper::convertLldRulePrototypeReferences($discoveryrule);

		$this->call('discoveryruleprototype.create', $discoveryrule, $error);
	}

	public static function dataInvalidDiscoveryRulePrototypeTelemetryQuery_Update() {
		$discoveryrule = [
			'itemid' => ':lld_rule_prototype:lld_telemetry_query[{#M}]'
		];
		$query = [
			'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
			'columns' => [],
			'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
			'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
		];

		yield 'Telemetry query "granularity" cannot be empty' => [
			['granularity' => ''] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": cannot be empty.'
		];

		yield 'Telemetry query "granularity" less than allowed' => [
			['granularity' => '0'] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": value must be one of 1-86400.'
		];

		yield 'Telemetry query "granularity" greater than allowed' => [
			['granularity' => '86401s', 'lookback_limit' => '3d'] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": value must be one of 1-86400.'
		];

		yield 'Telemetry query "granularity" cannot be greater than "lookback_limit"' => [
			['granularity' => '30s', 'lookback_limit' => '10s'] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": cannot be greater than the value of parameter "/1/lookback_limit".'
		];

		yield 'Telemetry query "granularity" cannot be greater than "lookback_limit" stored in database' => [
			['granularity' => '1h'] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": cannot be greater than the value of parameter "/1/lookback_limit".'
		];

		yield 'Telemetry query "granularity" is not supported for non telemetry query type' => [
			['itemid' => ':lld_rule_prototype:lld_trapper[{#M}]', 'granularity' => '30s'] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": value must be "15s".'
		];

		yield 'Telemetry query "lookback_limit" cannot be empty' => [
			['lookback_limit' => ''] + $discoveryrule,
			[],
			'Invalid parameter "/1/lookback_limit": cannot be empty.'
		];

		yield 'Telemetry query "lookback_limit" less than allowed' => [
			['lookback_limit' => '0'] + $discoveryrule,
			[],
			'Invalid parameter "/1/lookback_limit": value must be one of 1-259200.'
		];

		yield 'Telemetry query "lookback_limit" greater than allowed' => [
			['lookback_limit' => '259201s'] + $discoveryrule,
			[],
			'Invalid parameter "/1/lookback_limit": value must be one of 1-259200.'
		];

		yield 'Telemetry query "lookback_limit" less than "granularity" stored in database' => [
			['lookback_limit' => '1s'] + $discoveryrule,
			[],
			'Invalid parameter "/1/granularity": cannot be greater than the value of parameter "/1/lookback_limit".'
		];

		yield 'Telemetry query "lookback_limit" is not supported for non telemetry query type' => [
			['itemid' => ':lld_rule_prototype:lld_trapper[{#M}]', 'lookback_limit' => '30s'] + $discoveryrule,
			[],
			'Invalid parameter "/1/lookback_limit": value must be "10m".'
		];

		yield 'Telemetry query "query" cannot be string' => [
			['query' => ''] + $discoveryrule,
			[],
			'Invalid parameter "/1/query": an array is expected.'
		];

		yield 'Telemetry query "query.signal_type" is required' => [
			['query' => []] + $discoveryrule,
			[],
			'Invalid parameter "/1/query": the parameter "signal_type" is missing.'
		];

		yield 'Telemetry query "query" unexpected parameter' => [
			['query' => $query + ['unexpected' => '']] + $discoveryrule,
			[],
			'Invalid parameter "/1/query": unexpected parameter "unexpected".'
		];

		yield 'Telemetry query "query.aggregated_columns" cannot be empty' => [
			['query' => ['aggregated_columns' => []] + $query] + $discoveryrule,
			[],
			'Invalid parameter "/1/query/aggregated_columns": cannot be empty.'
		];

		yield 'Telemetry query "query.aggregated_columns[].alias" cannot be "clock"' => [
			[
				'query' => [
					'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'clock']]
				] + $query
			] + $discoveryrule,
			[],
			'Invalid parameter "/1/query/aggregated_columns/1/alias": value must be not "clock".'
		];

		yield 'Telemetry query "query.filter.conditions[].formulaid" is not used in "query.filter.formula"' => [
			[
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A'],
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'c', 'formulaid' => 'C']
						]
					]
				] + $query
			] + $discoveryrule,
			[],
			'Invalid parameter "/1/query/filter/conditions/2/formulaid": an identifier is not defined in the formula.'
		];

		yield 'Telemetry query "query" is required on type change' => [
			['itemid' => ':lld_rule_prototype:lld_trapper[{#M}]', 'type' => ITEM_TYPE_TELEMETRY_QUERY, 'delay' => '1m'] + $discoveryrule,
			[],
			'Invalid parameter "/1": the parameter "query" is missing.'
		];

		yield 'Telemetry query "query" should be empty for non telemetry query type' => [
			['itemid' => ':lld_rule_prototype:lld_trapper[{#M}]', 'query' => $query] + $discoveryrule,
			[],
			'Invalid parameter "/1/query": should be empty.'
		];
	}

	public static function dataValidDiscoveryRulePrototypeTelemetryQuery_Update() {
		$discoveryrule = [
			'itemid' => ':lld_rule_prototype:lld_telemetry_query[{#M}]'
		];
		$query = [
			'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
			'columns' => [],
			'aggregated_columns' => [['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp']],
			'filter' => ['evaltype' => CONDITION_EVAL_TYPE_AND_OR, 'conditions' => []]
		];
		$expected_query = [
			'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_TRACES,
			'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_SUM,
			'columns' => [],
			'aggregated_columns' => [
				['column' => 'Timestamp', 'function' => AGGREGATE_MIN, 'alias' => 'Timestamp', 'parameters' => []]
			],
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'formula' => '',
				'conditions' => [],
				'eval_formula' => ''
			]
		];

		yield 'Telemetry query with maximal "time_shift", "lookback_limit" and "granularity"' => [
			['time_shift' => '1d', 'lookback_limit' => '3d', 'granularity' => '1d'] + $discoveryrule,
			['time_shift' => '1d', 'lookback_limit' => '3d', 'granularity' => '1d'],
			null
		];

		yield 'Telemetry query "granularity" equal to "lookback_limit"' => [
			['lookback_limit' => '5m', 'granularity' => '300'] + $discoveryrule,
			['time_shift' => '1d', 'lookback_limit' => '5m', 'granularity' => '300'],
			null
		];

		yield 'Telemetry query user macro in "time_shift", "lookback_limit" and "granularity"' => [
			['time_shift' => '{$MACRO}', 'lookback_limit' => '{$MACRO}', 'granularity' => '{$MACRO}'] + $discoveryrule,
			['time_shift' => '{$MACRO}', 'lookback_limit' => '{$MACRO}', 'granularity' => '{$MACRO}'],
			null
		];

		yield 'Telemetry query LLD macro in "time_shift", "lookback_limit" and "granularity"' => [
			['time_shift' => '{#MACRO}', 'lookback_limit' => '{#MACRO}', 'granularity' => '{#MACRO}'] + $discoveryrule,
			['time_shift' => '{#MACRO}', 'lookback_limit' => '{#MACRO}', 'granularity' => '{#MACRO}'],
			null
		];

		yield 'Telemetry query "granularity" with LLD macro in "lookback_limit" stored in database' => [
			['granularity' => '1d'] + $discoveryrule,
			['time_shift' => '{#MACRO}', 'lookback_limit' => '{#MACRO}', 'granularity' => '1d'],
			null
		];

		yield 'Telemetry query with minimal "time_shift"' => [
			['time_shift' => '0'] + $discoveryrule,
			['time_shift' => '0', 'lookback_limit' => '{#MACRO}', 'granularity' => '1d'],
			null
		];

		yield 'Telemetry query "query.signal_type" metrics' => [
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
					'columns' => [['column' => 'MetricName']],
					'aggregated_columns' => [['column' => 'Value', 'function' => AGGREGATE_AVG, 'alias' => 'avg']]
				] + $query
			] + $discoveryrule,
			[
				'query' => [
					'signal_type' => CItemTypeTelemetryQuery::SIGNAL_TYPE_METRICS,
					'metric_point_type' => CItemTypeTelemetryQuery::METRICS_POINT_GAUGE,
					'columns' => [['column' => 'MetricName', 'attribute_key' => '']],
					'aggregated_columns' => [
						['column' => 'Value', 'function' => AGGREGATE_AVG, 'alias' => 'avg', 'parameters' => []]
					]
				] + $expected_query
			],
			null
		];

		yield 'Telemetry query "query.filter" with custom expression' => [
			[
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A'],
							['column' => 'TraceId', 'operator' => CONDITION_OPERATOR_NOT_EQUAL, 'value' => 'b', 'formulaid' => 'B']
						]
					]
				] + $query
			] + $discoveryrule,
			[
				'query' => [
					'filter' => [
						'evaltype' => CONDITION_EVAL_TYPE_EXPRESSION,
						'formula' => 'A or B',
						'conditions' => [
							['column' => 'TraceId', 'attribute_key' => '', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => 'a', 'formulaid' => 'A'],
							['column' => 'TraceId', 'attribute_key' => '', 'operator' => CONDITION_OPERATOR_NOT_EQUAL, 'value' => 'b', 'formulaid' => 'B']
						],
						'eval_formula' => 'A or B'
					]
				] + $expected_query
			],
			null
		];

		yield 'Telemetry query type change from trapper' => [
			[
				'itemid' => ':lld_rule_prototype:lld_trapper[{#M}]',
				'type' => ITEM_TYPE_TELEMETRY_QUERY,
				'delay' => '1m',
				'lookback_limit' => '1h',
				'granularity' => '1m',
				'query' => $query
			] + $discoveryrule,
			[
				'type' => (string) ITEM_TYPE_TELEMETRY_QUERY,
				'delay' => '1m',
				'time_shift' => '15s',
				'lookback_limit' => '1h',
				'granularity' => '1m',
				'query' => $expected_query
			],
			null
		];
	}

	/**
	 * @dataProvider dataInvalidDiscoveryRulePrototypeTelemetryQuery_Update
	 * @dataProvider dataValidDiscoveryRulePrototypeTelemetryQuery_Update
	 */
	public function testDiscoveryRulePrototypeTelemetryQuery_Update(array $discoveryrule, array $expected,
			?string $error) {
		CTestDataHelper::convertLldRulePrototypeReferences($discoveryrule);

		$this->call('discoveryruleprototype.update', $discoveryrule, $error);

		if ($error !== null) {
			return;
		}

		['result' => $result] = $this->call('discoveryruleprototype.get', [
			'output' => array_keys($expected),
			'itemids' => $discoveryrule['itemid']
		]);

		$this->assertEquals($expected, array_intersect_key(reset($result), $expected));
	}
}
