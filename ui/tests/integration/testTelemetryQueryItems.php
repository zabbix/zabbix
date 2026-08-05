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

use function PHPUnit\Framework\assertEquals;

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_telemetry_query_items
 * @backup history
 */
class testTelemetryQueryItems extends CIntegrationTest {
	/* CLICKHOUSE_* constants must not contain \ or " (must not require escaping) */
	const CLICKHOUSE_URL = 'http://127.0.0.1:8123';
	const CLICKHOUSE_USERNAME = 'otel';
	const CLICKHOUSE_PASSWORD = 'otelpass';
	const CLICKHOUSE_DB = 'otel';

	const COLLECTOR_ADDRESS = 'localhost:4317';
	const PROTO_DIR = PHPUNIT_BASEDIR . '/src/zabbix_proxy/apm/';

	const HOST_NAME = 'test_telemetry_query_items';

	const ITEM_KEY = 'test_tq_item';
	const ITEM_VALUE_TYPE = ITEM_VALUE_TYPE_TEXT;

	private static function tsOffStr(int $now, int $offset): string {
		return (string)(($now + $offset) * 1000000000);
	}

	private static function qcol(string $column, string $attribute_key = ''): array {
		return [
			'column' => $column,
			'attribute_key' => $attribute_key
		];
	}

	private static function qagg(string $column, int $function, string $alias, array $parameters = []): array {
		return [
			'column' => $column,
			'function' => $function,
			'parameters' => $parameters,
			'alias' => $alias
		];
	}

	private static function qcond(string $column, int $operator, string $value = '', string $attribute_key = ''): array {
		return [
			'column' => $column,
			'attribute_key' => $attribute_key,
			'operator' => $operator,
			'value' => $value
		];
	}

	private static function qfilter(int $evaltype, array $conditions = [], string $formula = ''): array {
		if ($evaltype == CONDITION_EVAL_TYPE_EXPRESSION)
		{
			for ($i = 0; $i < count($conditions); $i++) {
				$conditions[$i]['formulaid'] = chr(ord('A') + $i);
			}
		}

		return [
			'evaltype' => $evaltype,
			'formula' => $formula,
			'conditions' => $conditions
		];
	}

	private static function emptyFilter(): array {
		return self::qfilter(CONDITION_EVAL_TYPE_AND_OR);
	}

	private static function tqItem(int $signal_type, int $metric_point_type, array $columns,
			array $aggregated_columns, array $filter, string $time_shift = '0', string $lookback_limit = '120',
			string $granularity = '120'): array {
		return [
			'query' => [
				'signal_type' => $signal_type,
				'metric_point_type' => $metric_point_type,
				'columns' => $columns,
				'aggregated_columns' => $aggregated_columns,
				'filter' => $filter
			],
			'time_shift' => $time_shift,
			'lookback_limit' => $lookback_limit,
			'granularity' => $granularity
		];
	}

	private static function tmplTrace(string $startTimeUnixNano, string $endTimeUnixNano, ?Closure $mutate = null) {
		$payload = [
			'resourceSpans' => [
				[
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => 'trace-test-service']],
							['key' => 'host.name', 'value' => ['stringValue' => 'trace-test-host']],
							['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']],
							['key' => 'rum.sessionId', 'value' => ['stringValue' => 'rum-session-123']]
						]
					],
					'schemaUrl' => 'https://example.com/schemas/resource/trace/1.0.0',
					'scopeSpans' => [
						[
							'scope' => [
								'name' => 'trace-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									['key' => 'scope.attr', 'value' => ['stringValue' => 'trace-scope-value']]
								]
							],
							'schemaUrl' => 'https://example.com/schemas/scope/trace/1.0.0',
							'spans' => [
								[
									'traceId' => base64_encode(hex2bin('4123456789abcdef0123456789abcdef')),
									'spanId' => base64_encode(hex2bin('5122334455667788')),
									'parentSpanId' => base64_encode(hex2bin('6122334455667788')),
									'traceState' => 'vendor=value',
									'name' => 'test-span',
									'kind' => 'SPAN_KIND_SERVER',
									'startTimeUnixNano' => $startTimeUnixNano,
									'endTimeUnixNano' => $endTimeUnixNano,
									'attributes' => [
										['key' => 'http.method', 'value' => ['stringValue' => 'GET']],
										['key' => 'http.route', 'value' => ['stringValue' => '/trace']],
										['key' => 'SampleRate', 'value' => ['stringValue' => '100']]
									],
									'events' => [
										[
											'timeUnixNano' => $endTimeUnixNano,
											'name' => 'test-span-event',
											'attributes' => [
												['key' => 'event.attr', 'value' => ['stringValue' => 'event-value']]
											]
										]
									],
									'links' => [
										[
											'traceId' => base64_encode(hex2bin('5123456789abcdef0123456789abcdef')),
											'spanId' => base64_encode(hex2bin('7122334455667788')),
											'traceState' => 'linked-vendor=value',
											'attributes' => [
												['key' => 'link.attr', 'value' => ['stringValue' => 'link-value']]
											]
										]
									],
									'status' => [
										'code' => 'STATUS_CODE_OK',
										'message' => 'trace status message'
									]
								]
							]
						]
					]
				]
			]
		];

		if ($mutate !== null) {
			$mutate($payload);
		}

		return $payload;
	}

	private static function tmplLog(string $timeUnixNano, ?Closure $mutate = null) {
		$payload = [
			'resourceLogs' => [
				[
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => 'log-test-service']],
							['key' => 'host.name', 'value' => ['stringValue' => 'log-test-host']],
							['key' => 'k8s.cluster.name', 'value' => ['stringValue' => 'test-cluster']],
							['key' => 'k8s.container.name', 'value' => ['stringValue' => 'test-container']],
							['key' => 'k8s.deployment.name', 'value' => ['stringValue' => 'test-deployment']],
							['key' => 'k8s.namespace.name', 'value' => ['stringValue' => 'test-namespace']],
							['key' => 'k8s.node.name', 'value' => ['stringValue' => 'test-node']],
							['key' => 'k8s.pod.name', 'value' => ['stringValue' => 'test-pod']],
							['key' => 'k8s.pod.uid', 'value' => ['stringValue' => 'test-pod-uid']],
							['key' => 'deployment.environment.name', 'value' => ['stringValue' => 'test']]
						]
					],
					'schemaUrl' => 'https://example.com/schemas/resource/log/1.0.0',
					'scopeLogs' => [
						[
							'scope' => [
								'name' => 'log-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									['key' => 'scope.attr', 'value' => ['stringValue' => 'log-scope-value']]
								]
							],
							'schemaUrl' => 'https://example.com/schemas/scope/log/1.0.0',
							'logRecords' => [
								[
									'timeUnixNano' => $timeUnixNano,
									'observedTimeUnixNano' => $timeUnixNano,
									'traceId' => base64_encode(hex2bin('6123456789abcdef0123456789abcdef')),
									'spanId' => base64_encode(hex2bin('8122334455667788')),
									'flags' => 1,
									'severityText' => 'INFO',
									'severityNumber' => 'SEVERITY_NUMBER_INFO',
									'body' => ['stringValue' => 'test log body'],
									'attributes' => [
										['key' => 'log.attr', 'value' => ['stringValue' => 'log-attribute-value']],
										['key' => 'logger.name', 'value' => ['stringValue' => 'test-logger']]
									],
									'eventName' => 'test-log-event'
								]
							]
						]
					]
				]
			]
		];

		if ($mutate !== null) {
			$mutate($payload);
		}

		return $payload;
	}

	private static function tmplMetricSum(string $startTimeUnixNano, string $timeUnixNano, ?Closure $mutate = null) {
		$payload = [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => 'sum-test-service']],
							['key' => 'host.name', 'value' => ['stringValue' => 'sum-test-host']],
							['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']]
						]
					],
					'schemaUrl' => 'https://example.com/schemas/resource/sum/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'sum-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									['key' => 'scope.attr', 'value' => ['stringValue' => 'sum-scope-value']]
								],
								'droppedAttributesCount' => 11
							],
							'schemaUrl' => 'https://example.com/schemas/scope/sum/1.0.0',
							'metrics' => [
								[
									'name' => 'test_sum',
									'description' => 'Test sum description',
									'unit' => 'requests',
									'sum' => [
										'aggregationTemporality' => 'AGGREGATION_TEMPORALITY_CUMULATIVE',
										'isMonotonic' => true,
										'dataPoints' => [
											[
												'attributes' => [
													['key' => 'endpoint', 'value' => ['stringValue' => '/sum']],
													['key' => 'method', 'value' => ['stringValue' => 'grpcurl']]
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'asDouble' => 42.5,
												'flags' => 1,
												'exemplars' => [
													[
														'filteredAttributes' => [
															['key' => 'exemplar.attr', 'value' => ['stringValue' => 'sum-exemplar-value']]
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 42.5,
														'spanId' => base64_encode(hex2bin('2122334455667788')),
														'traceId' => base64_encode(hex2bin('1123456789abcdef0123456789abcdef'))
													]
												]
											]
										]
									]
								]
							]
						]
					]
				]
			]
		];

		if ($mutate !== null) {
			$mutate($payload);
		}

		return $payload;
	}

	private static function tmplMetricGauge(string $startTimeUnixNano, string $timeUnixNano, ?Closure $mutate = null) {
		$payload = [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => 'gauge-test-service']],
							['key' => 'host.name', 'value' => ['stringValue' => 'test-host']],
							['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']]
						]
					],
					'schemaUrl' => 'https://example.com/schemas/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'test-scope',
								'version' => '1.0.0',
								'attributes' => [
									['key' => 'scope.attr', 'value' => ['stringValue' => 'scope-value']]
								],
								'droppedAttributesCount' => 7
							],
							'schemaUrl' => 'https://example.com/schemas/scope/1.0.0',
							'metrics' => [
								[
									'name' => 'test_gauge',
									'description' => 'Test description',
									'unit' => 'ms',
									'gauge' => [
										'dataPoints' => [
											[
												'attributes' => [
													['key' => 'endpoint', 'value' => ['stringValue' => '/test']],
													['key' => 'method', 'value' => ['stringValue' => 'grpcurl']]
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'asDouble' => 123.456,
												'flags' => 1,
												'exemplars' => [
													[
														'filteredAttributes' => [
															['key' => 'exemplar.attr', 'value' => ['stringValue' => 'exemplar-value']]
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 123.456,
														'spanId' => base64_encode(hex2bin('1122334455667788')),
														'traceId' => base64_encode(hex2bin('0123456789abcdef0123456789abcdef'))
													]
												]
											]
										]
									]
								]
							]
						]
					]
				]
			]
		];

		if ($mutate !== null) {
			$mutate($payload);
		}

		return $payload;
	}

	private static function tmplMetricHistogram(string $startTimeUnixNano, string $timeUnixNano, ?Closure $mutate = null) {
		$payload = [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => 'histogram-test-service']],
							['key' => 'host.name', 'value' => ['stringValue' => 'histogram-test-host']],
							['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']]
						]
					],
					'schemaUrl' => 'https://example.com/schemas/resource/histogram/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'histogram-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									['key' => 'scope.attr', 'value' => ['stringValue' => 'histogram-scope-value']]
								],
								'droppedAttributesCount' => 13
							],
							'schemaUrl' => 'https://example.com/schemas/scope/histogram/1.0.0',
							'metrics' => [
								[
									'name' => 'test_histogram',
									'description' => 'Test histogram description',
									'unit' => 'ms',
									'histogram' => [
										'aggregationTemporality' => 'AGGREGATION_TEMPORALITY_DELTA',
										'dataPoints' => [
											[
												'attributes' => [
													['key' => 'endpoint', 'value' => ['stringValue' => '/histogram']],
													['key' => 'method', 'value' => ['stringValue' => 'grpcurl']]
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'count' => '6',
												'sum' => 63.0,
												'bucketCounts' => ['1', '2', '3'],
												'explicitBounds' => [10.0, 50.0],
												'flags' => 1,
												'min' => 5.0,
												'max' => 40.0,
												'exemplars' => [
													[
														'filteredAttributes' => [
															['key' => 'exemplar.attr', 'value' => ['stringValue' => 'histogram-exemplar-value']]
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 25.0,
														'spanId' => base64_encode(hex2bin('3122334455667788')),
														'traceId' => base64_encode(hex2bin('2123456789abcdef0123456789abcdef'))
													]
												]
											]
										]
									]
								]
							]
						]
					]
				]
			]
		];

		if ($mutate !== null) {
			$mutate($payload);
		}

		return $payload;
	}

	private static function tmplMetricExponentialHistogram(string $startTimeUnixNano, string $timeUnixNano,
			?Closure $mutate = null) {
		$payload = [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							['key' => 'service.name', 'value' => ['stringValue' => 'exponential-histogram-test-service']],
							['key' => 'host.name', 'value' => ['stringValue' => 'exponential-histogram-test-host']],
							['key' => 'deployment.environment', 'value' => ['stringValue' => 'test']]
						]
					],
					'schemaUrl' => 'https://example.com/schemas/resource/exponential-histogram/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'exponential-histogram-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									['key' => 'scope.attr', 'value' => ['stringValue' => 'exponential-histogram-scope-value']]
								],
								'droppedAttributesCount' => 17
							],
							'schemaUrl' => 'https://example.com/schemas/scope/exponential-histogram/1.0.0',
							'metrics' => [
								[
									'name' => 'test_exponential_histogram',
									'description' => 'Test exponential histogram description',
									'unit' => 'ms',
									'exponentialHistogram' => [
										'aggregationTemporality' => 'AGGREGATION_TEMPORALITY_DELTA',
										'dataPoints' => [
											[
												'attributes' => [
													['key' => 'endpoint', 'value' => ['stringValue' => '/exponential-histogram']],
													['key' => 'method', 'value' => ['stringValue' => 'grpcurl']]
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'count' => '7',
												'sum' => 77.0,
												'scale' => 2,
												'zeroCount' => '1',
												'positive' => ['offset' => 1, 'bucketCounts' => ['2', '3']],
												'negative' => ['offset' => -2, 'bucketCounts' => ['1']],
												'flags' => 1,
												'min' => -8.0,
												'max' => 32.0,
												'exemplars' => [
													[
														'filteredAttributes' => [
															['key' => 'exemplar.attr', 'value' => ['stringValue' => 'exponential-histogram-exemplar-value']]
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 16.0,
														'spanId' => base64_encode(hex2bin('4122334455667788')),
														'traceId' => base64_encode(hex2bin('3123456789abcdef0123456789abcdef'))
													]
												]
											]
										]
									]
								]
							]
						]
					]
				]
			]
		];

		if ($mutate !== null) {
			$mutate($payload);
		}

		return $payload;
	}

	/* TODO: do something with float/int not matching failing the test */
	private function getSubcases(): array {
		return [
			[
				'description' => 'Minimal traces test',
				'item' => self::tqItem(APM_SIGNAL_TYPE_TRACES, APM_METRICS_POINT_SUM, [], [
					self::qagg('', AGGREGATE_COUNT, 'cnt')
				], self::emptyFilter()),
				'buckets' => [['id' => 1, 'columns' => ['cnt' => 1]]],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'traces' => [self::tmplTrace(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Minimal logs test',
				'item' => self::tqItem(APM_SIGNAL_TYPE_LOGS, APM_METRICS_POINT_SUM, [], [
					self::qagg('', AGGREGATE_COUNT, 'cnt')
				], self::emptyFilter()),
				'buckets' => [['id' => 1, 'columns' => ['cnt' => 1]]],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'logs' => [self::tmplLog(self::tsOffStr($now, -10))]
				]
			],
			[
				'description' => 'Minimal metrics sum test',
				'item' => self::tqItem(APM_SIGNAL_TYPE_METRICS, APM_METRICS_POINT_SUM, [], [
					self::qagg('', AGGREGATE_COUNT, 'cnt')
				], self::emptyFilter()),
				'buckets' => [['id' => 1, 'columns' => ['cnt' => 1]]],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricSum(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Minimal metrics gauge test',
				'item' => self::tqItem(APM_SIGNAL_TYPE_METRICS, APM_METRICS_POINT_GAUGE, [], [
					self::qagg('', AGGREGATE_COUNT, 'cnt')
				], self::emptyFilter()),
				'buckets' => [['id' => 1, 'columns' => ['cnt' => 1]]],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricGauge(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Minimal metrics histogram test',
				'item' => self::tqItem(APM_SIGNAL_TYPE_METRICS, APM_METRICS_POINT_HISTOGRAM, [], [
					self::qagg('', AGGREGATE_COUNT, 'cnt')
				], self::emptyFilter()),
				'buckets' => [['id' => 1, 'columns' => ['cnt' => 1]]],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricHistogram(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Minimal metrics exponential histogram test',
				'item' => self::tqItem(APM_SIGNAL_TYPE_METRICS, APM_METRICS_POINT_EXPHISTOGRAM, [], [
					self::qagg('', AGGREGATE_COUNT, 'cnt')
				], self::emptyFilter()),
				'buckets' => [['id' => 1, 'columns' => ['cnt' => 1]]],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricExponentialHistogram(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Traces columns, aggregates, all aggregate functions and formula filter test',
				'item' => self::tqItem(
					APM_SIGNAL_TYPE_TRACES,
					APM_METRICS_POINT_SUM,
					[
						self::qcol('Timestamp'),
						self::qcol('SpanId'),
						self::qcol('ParentSpanId'),
						self::qcol('TraceState'),
						self::qcol('SpanName'),
						self::qcol('SpanKind'),
						self::qcol('ServiceName'),
						self::qcol('ResourceAttributes', 'host.name'),
						self::qcol('SpanAttributes', 'http.method'),
						self::qcol('ScopeName'),
						self::qcol('ScopeVersion'),
						self::qcol('Duration'),
						self::qcol('StatusCode'),
						self::qcol('StatusMessage')
					],
					[
						self::qagg('', AGGREGATE_COUNT, 'cnt'),
						self::qagg('Timestamp', AGGREGATE_MIN, 'timestamp_min'),
						self::qagg('Duration', AGGREGATE_MIN, 'duration_min'),
						self::qagg('Duration', AGGREGATE_MAX, 'duration_max'),
						self::qagg('Duration', AGGREGATE_AVG, 'duration_avg'),
						self::qagg('Duration', AGGREGATE_SUM, 'duration_sum'),
						self::qagg('Duration', AGGREGATE_PCTILE, 'duration_p90', ['90'])
					],
					self::qfilter(
						CONDITION_EVAL_TYPE_EXPRESSION,
						[
							self::qcond('TraceId', CONDITION_OPERATOR_EQUAL, '4123456789abcdef0123456789abcdef'),
							self::qcond('SpanId', CONDITION_OPERATOR_NOT_EQUAL, '0000000000000000'),
							self::qcond('ParentSpanId', CONDITION_OPERATOR_EQUAL, '6122334455667788'),
							self::qcond('TraceState', CONDITION_OPERATOR_LIKE, 'vendor'),
							self::qcond('SpanName', CONDITION_OPERATOR_NOT_LIKE, 'missing-span'),
							self::qcond('SpanKind', CONDITION_OPERATOR_EQUAL, 'Server'),
							self::qcond('ServiceName', CONDITION_OPERATOR_EQUAL, 'trace-test-service'),
							self::qcond('ResourceAttributes', CONDITION_OPERATOR_EXISTS, '', 'host.name'),
							self::qcond('SpanAttributes', CONDITION_OPERATOR_EQUAL, 'GET', 'http.method'),
							self::qcond('ScopeName', CONDITION_OPERATOR_EQUAL, 'trace-test-scope'),
							self::qcond('ScopeVersion', CONDITION_OPERATOR_EQUAL, '1.0.0'),
							self::qcond('StatusCode', CONDITION_OPERATOR_EQUAL, 'Ok'),
							self::qcond('StatusMessage', CONDITION_OPERATOR_LIKE, 'status'),
							self::qcond('Events.Name', CONDITION_OPERATOR_EQUAL, 'test-span-event'),
							self::qcond('Events.Attributes', CONDITION_OPERATOR_EXISTS, '', 'event.attr')
						],
						'A and B and C and D and E and F and G and H and I and J and K and L and M and N and O'
					)
				),
				'buckets' => fn(int $now) => [
					[
						'id' => 1,
						'columns' => [
							'Timestamp' => $now - 10,
							'SpanId' => '5122334455667788',
							'ParentSpanId' => '6122334455667788',
							'TraceState' => 'vendor=value',
							'SpanName' => 'test-span',
							'SpanKind' => 'Server',
							'ServiceName' => 'trace-test-service',
							'ResourceAttributes.host.name' => 'trace-test-host',
							'SpanAttributes.http.method' => 'GET',
							'ScopeName' => 'trace-test-scope',
							'ScopeVersion' => '1.0.0',
							'Duration' => 5000000000,
							'StatusCode' => 'Ok',
							'StatusMessage' => 'trace status message',
							'cnt' => 1,
							'timestamp_min' => $now - 10,
							'duration_min' => 5000000000,
							'duration_max' => 5000000000,
							'duration_avg' => 5000000000,
							'duration_sum' => 5000000000,
							'duration_p90' => 5000000000
						]
					]
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'traces' => [self::tmplTrace(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Logs columns, aggregates and AND filter test',
				'item' => self::tqItem(
					APM_SIGNAL_TYPE_LOGS,
					APM_METRICS_POINT_SUM,
					[
						self::qcol('Timestamp'),
						self::qcol('TraceId'),
						self::qcol('SpanId'),
						self::qcol('TraceFlags'),
						self::qcol('SeverityText'),
						self::qcol('SeverityNumber'),
						self::qcol('ServiceName'),
						self::qcol('Body'),
						self::qcol('ResourceSchemaUrl'),
						self::qcol('ScopeSchemaUrl'),
						self::qcol('ScopeName'),
						self::qcol('ScopeVersion'),
						self::qcol('ResourceAttributes', 'host.name'),
						self::qcol('ScopeAttributes', 'scope.attr'),
						self::qcol('LogAttributes', 'logger.name'),
						self::qcol('EventName')
					],
					[
						self::qagg('', AGGREGATE_COUNT, 'cnt'),
						self::qagg('Timestamp', AGGREGATE_MIN, 'timestamp_min'),
						self::qagg('SeverityNumber', AGGREGATE_MIN, 'severity_min'),
						self::qagg('SeverityNumber', AGGREGATE_MAX, 'severity_max'),
						self::qagg('SeverityNumber', AGGREGATE_AVG, 'severity_avg'),
						self::qagg('SeverityNumber', AGGREGATE_SUM, 'severity_sum'),
						self::qagg('SeverityNumber', AGGREGATE_PCTILE, 'severity_p50', ['50'])
					],
					self::qfilter(CONDITION_EVAL_TYPE_AND, [
						self::qcond('TraceId', CONDITION_OPERATOR_EQUAL, '6123456789abcdef0123456789abcdef'),
						self::qcond('SpanId', CONDITION_OPERATOR_EQUAL, '8122334455667788'),
						self::qcond('SeverityText', CONDITION_OPERATOR_EQUAL, 'INFO'),
						self::qcond('ServiceName', CONDITION_OPERATOR_EQUAL, 'log-test-service'),
						self::qcond('Body', CONDITION_OPERATOR_LIKE, 'log body'),
						self::qcond('ResourceSchemaUrl', CONDITION_OPERATOR_LIKE, 'resource/log'),
						self::qcond('ScopeSchemaUrl', CONDITION_OPERATOR_LIKE, 'scope/log'),
						self::qcond('ScopeName', CONDITION_OPERATOR_EQUAL, 'log-test-scope'),
						self::qcond('ScopeVersion', CONDITION_OPERATOR_EQUAL, '1.0.0'),
						self::qcond('ResourceAttributes', CONDITION_OPERATOR_NOT_EQUAL, 'other-host', 'host.name'),
						self::qcond('ScopeAttributes', CONDITION_OPERATOR_EXISTS, '', 'scope.attr'),
						self::qcond('LogAttributes', CONDITION_OPERATOR_EXISTS, '', 'logger.name'),
						self::qcond('EventName', CONDITION_OPERATOR_NOT_LIKE, 'missing-event')
					])
				),
				'buckets' => fn(int $now) => [
					[
						'id' => 1,
						'columns' => [
							'Timestamp' => $now - 10,
							'TraceId' => '6123456789abcdef0123456789abcdef',
							'SpanId' => '8122334455667788',
							'TraceFlags' => 1,
							'SeverityText' => 'INFO',
							'SeverityNumber' => 9,
							'ServiceName' => 'log-test-service',
							'Body' => 'test log body',
							'ResourceSchemaUrl' => 'https://example.com/schemas/resource/log/1.0.0',
							'ScopeSchemaUrl' => 'https://example.com/schemas/scope/log/1.0.0',
							'ScopeName' => 'log-test-scope',
							'ScopeVersion' => '1.0.0',
							'ResourceAttributes.host.name' => 'log-test-host',
							'ScopeAttributes.scope.attr' => 'log-scope-value',
							'LogAttributes.logger.name' => 'test-logger',
							'EventName' => 'test-log-event',
							'cnt' => 1,
							'timestamp_min' => $now - 10,
							'severity_min' => 9,
							'severity_max' => 9,
							'severity_avg' => 9,
							'severity_sum' => 9,
							'severity_p50' => 9
						]
					]
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'logs' => [self::tmplLog(self::tsOffStr($now, -10))]
				]
			],
			[
				'description' => 'Metrics sum all columns, aggregates and OR filter test',
				'item' => self::tqItem(
					APM_SIGNAL_TYPE_METRICS,
					APM_METRICS_POINT_SUM,
					[
						self::qcol('ResourceAttributes', 'host.name'),
						self::qcol('ResourceSchemaUrl'),
						self::qcol('ScopeName'),
						self::qcol('ScopeVersion'),
						self::qcol('ScopeAttributes', 'scope.attr'),
						self::qcol('ScopeDroppedAttrCount'),
						self::qcol('ScopeSchemaUrl'),
						self::qcol('ServiceName'),
						self::qcol('MetricName'),
						self::qcol('MetricDescription'),
						self::qcol('MetricUnit'),
						self::qcol('Attributes', 'endpoint'),
						self::qcol('StartTimeUnix'),
						self::qcol('TimeUnix'),
						self::qcol('Value'),
						self::qcol('Flags'),
						self::qcol('AggregationTemporality'),
						self::qcol('IsMonotonic')
					],
					[
						self::qagg('', AGGREGATE_COUNT, 'cnt'),
						self::qagg('StartTimeUnix', AGGREGATE_MIN, 'start_min'),
						self::qagg('TimeUnix', AGGREGATE_MAX, 'time_max'),
						self::qagg('ScopeDroppedAttrCount', AGGREGATE_MAX, 'scope_dropped_max'),
						self::qagg('Value', AGGREGATE_MIN, 'value_min'),
						self::qagg('Value', AGGREGATE_MAX, 'value_max'),
						self::qagg('Value', AGGREGATE_AVG, 'value_avg'),
						self::qagg('Value', AGGREGATE_SUM, 'value_sum'),
						self::qagg('Value', AGGREGATE_PCTILE, 'value_p50', ['50'])
					],
					self::qfilter(CONDITION_EVAL_TYPE_OR, [
						self::qcond('MetricName', CONDITION_OPERATOR_EQUAL, 'will-not-match'),
						self::qcond('ResourceAttributes', CONDITION_OPERATOR_EXISTS, '', 'host.name'),
						self::qcond('ScopeAttributes', CONDITION_OPERATOR_EQUAL, 'sum-scope-value', 'scope.attr'),
						self::qcond('Attributes', CONDITION_OPERATOR_EQUAL, '/sum', 'endpoint'),
						self::qcond('Exemplars.FilteredAttributes', CONDITION_OPERATOR_EXISTS, '', 'exemplar.attr')
					])
				),
				'buckets' => fn(int $now) => [
					[
						'id' => 1,
						'columns' => [
							'ResourceAttributes.host.name' => 'sum-test-host',
							'ResourceSchemaUrl' => 'https://example.com/schemas/resource/sum/1.0.0',
							'ScopeName' => 'sum-test-scope',
							'ScopeVersion' => '1.0.0',
							'ScopeAttributes.scope.attr' => 'sum-scope-value',
							'ScopeDroppedAttrCount' => 11,
							'ScopeSchemaUrl' => 'https://example.com/schemas/scope/sum/1.0.0',
							'ServiceName' => 'sum-test-service',
							'MetricName' => 'test_sum',
							'MetricDescription' => 'Test sum description',
							'MetricUnit' => 'requests',
							'Attributes.endpoint' => '/sum',
							'StartTimeUnix' => $now - 10,
							'TimeUnix' => $now - 5,
							'Value' => 42.5,
							'Flags' => 1,
							'AggregationTemporality' => 2,
							'IsMonotonic' => 1,
							'cnt' => 1,
							'start_min' => $now - 10,
							'time_max' => $now - 5,
							'scope_dropped_max' => 11,
							'value_min' => 42.5,
							'value_max' => 42.5,
							'value_avg' => 42.5,
							'value_sum' => 42.5,
							'value_p50' => 42.5
						]
					]
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricSum(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Metrics gauge columns, timestamp aggregates and empty AND_OR filter test',
				'item' => self::tqItem(
					APM_SIGNAL_TYPE_METRICS,
					APM_METRICS_POINT_GAUGE,
					[
						self::qcol('ResourceAttributes', 'host.name'),
						self::qcol('ResourceSchemaUrl'),
						self::qcol('ScopeName'),
						self::qcol('ScopeVersion'),
						self::qcol('ScopeAttributes', 'scope.attr'),
						self::qcol('ScopeDroppedAttrCount'),
						self::qcol('ScopeSchemaUrl'),
						self::qcol('ServiceName'),
						self::qcol('MetricName'),
						self::qcol('MetricDescription'),
						self::qcol('MetricUnit'),
						self::qcol('Attributes', 'method'),
						self::qcol('StartTimeUnix'),
						self::qcol('TimeUnix'),
						self::qcol('Value'),
						self::qcol('Flags')
					],
					[
						self::qagg('', AGGREGATE_COUNT, 'cnt'),
						self::qagg('StartTimeUnix', AGGREGATE_MIN, 'start_min'),
						self::qagg('TimeUnix', AGGREGATE_MAX, 'time_max'),
						self::qagg('ScopeDroppedAttrCount', AGGREGATE_SUM, 'scope_dropped_sum'),
						self::qagg('Value', AGGREGATE_AVG, 'value_avg')
					],
					self::emptyFilter()
				),
				'buckets' => fn(int $now) => [
					[
						'id' => 1,
						'columns' => [
							'ResourceAttributes.host.name' => 'test-host',
							'ResourceSchemaUrl' => 'https://example.com/schemas/1.0.0',
							'ScopeName' => 'test-scope',
							'ScopeVersion' => '1.0.0',
							'ScopeAttributes.scope.attr' => 'scope-value',
							'ScopeDroppedAttrCount' => 7,
							'ScopeSchemaUrl' => 'https://example.com/schemas/scope/1.0.0',
							'ServiceName' => 'gauge-test-service',
							'MetricName' => 'test_gauge',
							'MetricDescription' => 'Test description',
							'MetricUnit' => 'ms',
							'Attributes.method' => 'grpcurl',
							'StartTimeUnix' => $now - 10,
							'TimeUnix' => $now - 5,
							'Value' => 123.456,
							'Flags' => 1,
							'cnt' => 1,
							'start_min' => $now - 10,
							'time_max' => $now - 5,
							'scope_dropped_sum' => 7,
							'value_avg' => 123.456
						]
					]
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricGauge(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Metrics histogram columns, aggregates and AND_OR complex condition test',
				'item' => self::tqItem(
					APM_SIGNAL_TYPE_METRICS,
					APM_METRICS_POINT_HISTOGRAM,
					[
						self::qcol('ResourceAttributes', 'host.name'),
						self::qcol('ResourceSchemaUrl'),
						self::qcol('ScopeName'),
						self::qcol('ScopeVersion'),
						self::qcol('ScopeAttributes', 'scope.attr'),
						self::qcol('ScopeDroppedAttrCount'),
						self::qcol('ScopeSchemaUrl'),
						self::qcol('ServiceName'),
						self::qcol('MetricName'),
						self::qcol('MetricDescription'),
						self::qcol('MetricUnit'),
						self::qcol('Attributes', 'endpoint'),
						self::qcol('StartTimeUnix'),
						self::qcol('TimeUnix'),
						self::qcol('Count'),
						self::qcol('Sum'),
						self::qcol('Flags'),
						self::qcol('Min'),
						self::qcol('Max'),
						self::qcol('AggregationTemporality')
					],
					[
						self::qagg('', AGGREGATE_COUNT, 'cnt'),
						self::qagg('StartTimeUnix', AGGREGATE_MIN, 'start_min'),
						self::qagg('TimeUnix', AGGREGATE_MAX, 'time_max'),
						self::qagg('ScopeDroppedAttrCount', AGGREGATE_MAX, 'scope_dropped_max'),
						self::qagg('Count', AGGREGATE_SUM, 'count_sum'),
						self::qagg('Sum', AGGREGATE_AVG, 'sum_avg'),
						self::qagg('Min', AGGREGATE_MIN, 'min_min'),
						self::qagg('Max', AGGREGATE_MAX, 'max_max'),
						self::qagg('Sum', AGGREGATE_PCTILE, 'sum_p95', ['95'])
					],
					self::qfilter(CONDITION_EVAL_TYPE_AND_OR, [
						self::qcond('MetricName', CONDITION_OPERATOR_EQUAL, 'test_histogram'),
						self::qcond('Attributes', CONDITION_OPERATOR_EQUAL, '/histogram', 'endpoint'),
						self::qcond('Exemplars.FilteredAttributes', CONDITION_OPERATOR_EXISTS, '', 'exemplar.attr')
					])
				),
				'buckets' => fn(int $now) => [
					[
						'id' => 1,
						'columns' => [
							'ResourceAttributes.host.name' => 'histogram-test-host',
							'ResourceSchemaUrl' => 'https://example.com/schemas/resource/histogram/1.0.0',
							'ScopeName' => 'histogram-test-scope',
							'ScopeVersion' => '1.0.0',
							'ScopeAttributes.scope.attr' => 'histogram-scope-value',
							'ScopeDroppedAttrCount' => 13,
							'ScopeSchemaUrl' => 'https://example.com/schemas/scope/histogram/1.0.0',
							'ServiceName' => 'histogram-test-service',
							'MetricName' => 'test_histogram',
							'MetricDescription' => 'Test histogram description',
							'MetricUnit' => 'ms',
							'Attributes.endpoint' => '/histogram',
							'StartTimeUnix' => $now - 10,
							'TimeUnix' => $now - 5,
							'Count' => 6,
							'Sum' => 63.0,
							'Flags' => 1,
							'Min' => 5.0,
							'Max' => 40.0,
							'AggregationTemporality' => 1,
							'cnt' => 1,
							'start_min' => $now - 10,
							'time_max' => $now - 5,
							'scope_dropped_max' => 13,
							'count_sum' => 6,
							'sum_avg' => 63.0,
							'min_min' => 5.0,
							'max_max' => 40.0,
							'sum_p95' => 63.0
						]
					]
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricHistogram(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			],
			[
				'description' => 'Metrics exponential histogram columns and aggregates test',
				'item' => self::tqItem(
					APM_SIGNAL_TYPE_METRICS,
					APM_METRICS_POINT_EXPHISTOGRAM,
					[
						self::qcol('ResourceAttributes', 'host.name'),
						self::qcol('ResourceSchemaUrl'),
						self::qcol('ScopeName'),
						self::qcol('ScopeVersion'),
						self::qcol('ScopeAttributes', 'scope.attr'),
						self::qcol('ScopeDroppedAttrCount'),
						self::qcol('ScopeSchemaUrl'),
						self::qcol('ServiceName'),
						self::qcol('MetricName'),
						self::qcol('MetricDescription'),
						self::qcol('MetricUnit'),
						self::qcol('Attributes', 'endpoint'),
						self::qcol('StartTimeUnix'),
						self::qcol('TimeUnix'),
						self::qcol('Count'),
						self::qcol('Sum'),
						self::qcol('Scale'),
						self::qcol('ZeroCount'),
						self::qcol('PositiveOffset'),
						self::qcol('NegativeOffset'),
						self::qcol('Flags'),
						self::qcol('Min'),
						self::qcol('Max'),
						self::qcol('AggregationTemporality')
					],
					[
						self::qagg('', AGGREGATE_COUNT, 'cnt'),
						self::qagg('StartTimeUnix', AGGREGATE_MIN, 'start_min'),
						self::qagg('TimeUnix', AGGREGATE_MAX, 'time_max'),
						self::qagg('ScopeDroppedAttrCount', AGGREGATE_MAX, 'scope_dropped_max'),
						self::qagg('Count', AGGREGATE_SUM, 'count_sum'),
						self::qagg('Sum', AGGREGATE_AVG, 'sum_avg'),
						self::qagg('Scale', AGGREGATE_MAX, 'scale_max'),
						self::qagg('ZeroCount', AGGREGATE_MIN, 'zero_count_min'),
						self::qagg('PositiveOffset', AGGREGATE_MAX, 'positive_offset_max'),
						self::qagg('NegativeOffset', AGGREGATE_MIN, 'negative_offset_min'),
						self::qagg('Min', AGGREGATE_MIN, 'min_min'),
						self::qagg('Max', AGGREGATE_MAX, 'max_max'),
						self::qagg('Max', AGGREGATE_PCTILE, 'max_p99', ['99'])
					],
					self::qfilter(CONDITION_EVAL_TYPE_AND, [
						self::qcond('MetricName', CONDITION_OPERATOR_EQUAL, 'test_exponential_histogram'),
						self::qcond('Attributes', CONDITION_OPERATOR_EQUAL, '/exponential-histogram', 'endpoint'),
						self::qcond('Exemplars.FilteredAttributes', CONDITION_OPERATOR_EXISTS, '', 'exemplar.attr')
					])
				),
				'buckets' => fn(int $now) => [
					[
						'id' => 1,
						'columns' => [
							'ResourceAttributes.host.name' => 'exponential-histogram-test-host',
							'ResourceSchemaUrl' => 'https://example.com/schemas/resource/exponential-histogram/1.0.0',
							'ScopeName' => 'exponential-histogram-test-scope',
							'ScopeVersion' => '1.0.0',
							'ScopeAttributes.scope.attr' => 'exponential-histogram-scope-value',
							'ScopeDroppedAttrCount' => 17,
							'ScopeSchemaUrl' => 'https://example.com/schemas/scope/exponential-histogram/1.0.0',
							'ServiceName' => 'exponential-histogram-test-service',
							'MetricName' => 'test_exponential_histogram',
							'MetricDescription' => 'Test exponential histogram description',
							'MetricUnit' => 'ms',
							'Attributes.endpoint' => '/exponential-histogram',
							'StartTimeUnix' => $now - 10,
							'TimeUnix' => $now - 5,
							'Count' => 7,
							'Sum' => 77.0,
							'Scale' => 2,
							'ZeroCount' => 1,
							'PositiveOffset' => 1,
							'NegativeOffset' => -2,
							'Flags' => 1,
							'Min' => -8.0,
							'Max' => 32.0,
							'AggregationTemporality' => 1,
							'cnt' => 1,
							'start_min' => $now - 10,
							'time_max' => $now - 5,
							'scope_dropped_max' => 17,
							'count_sum' => 7,
							'sum_avg' => 77.0,
							'scale_max' => 2,
							'zero_count_min' => 1,
							'positive_offset_max' => 1,
							'negative_offset_min' => -2,
							'min_min' => -8.0,
							'max_max' => 32.0,
							'max_p99' => 32.0
						]
					]
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [self::tmplMetricExponentialHistogram(self::tsOffStr($now, -10), self::tsOffStr($now, -5))]
				]
			]
		];
	}

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'LogFile' => self::getLogPath(self::COMPONENT_SERVER),
				'TelemetryProvider' =>	'clickhouse;url="' . self::CLICKHOUSE_URL . '",' .
										'username="' . self::CLICKHOUSE_USERNAME . '",' .
										'password="' . self::CLICKHOUSE_PASSWORD . '",' .
										'db="' . self::CLICKHOUSE_DB . '"'
			]
		];
	}

	/* TODO: remove */
	static private function debugLog(mixed $x) {
		echo "\n\n" . json_encode($x, JSON_PRETTY_PRINT) . "\n\n";
	}

	private function createTQItem(int $hostid, array $item_fields): int {
		$response = $this->call('item.create', array_merge($item_fields, [
			'hostid' => $hostid,
			'name' => self::ITEM_KEY,
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_TELEMETRY_QUERY,
			'value_type' => self::ITEM_VALUE_TYPE,
			'timeout' => '3s',
			'delay' => '1s',
		]));

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$itemid = $response['result']['itemids'][0];

		return $itemid;
	}

	private function deleteTQItem(int $itemid) {
		$response = $this->call('item.delete', [$itemid]);

		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$this->assertEquals($itemid, $response['result']['itemids'][0]);
	}

	private function waitForBuckets(int $itemid, int $min_bucket_count, int $iterations, int $delay, string $msg): array {
		/* TODO: maybe also check that there isn't more buckets than expected */
		for ($i = 0; $i < $iterations; $i++) {
			$response = $this->call('history.get', [
				'output' => ['value'],
				'itemids' => [$itemid],
				'history' => self::ITEM_VALUE_TYPE,
				'sortorder' => 'ASC',
				'sortfield' => 'clock'
			]);

			if (isset($response['result']) && count($response['result']) >= $min_bucket_count) {
				return $response;
			}

			sleep($delay);
		}

		$msg2 = "$msg\nFailed to wait for the minimum of $min_bucket_count buckets";

		if (isset($response)) {
			$msg2 .= "\nLast response:\n".json_encode($response);
		}

		$this->fail($msg2);
	}

	private function sendOTLP(string $import_path, string $proto, string $payload, string $address, string $method) {
		$cmd =
			'grpcurl '.
			'-plaintext ' .
			'-import-path ' . escapeshellarg($import_path) . ' ' .
			'-proto ' . escapeshellarg($proto) . ' ' .
			'-d ' . escapeshellarg($payload) . ' ' .
			escapeshellarg($address) . ' ' .
			escapeshellarg($method);

		$output_lines = [];
		$exitCode = 0;

		exec($cmd, $output_lines, $exitCode);

		$this->assertEquals(0, $exitCode);

		self::debugLog($output_lines);
	}

	private function sendInput(array $input) {
		foreach (($input['metrics'] ?? []) as $metric) {
			$json = json_encode($metric);

			$this->sendOTLP(
				self::PROTO_DIR,
				'opentelemetry/proto/collector/metrics/v1/metrics_service.proto',
				$json,
				self::COLLECTOR_ADDRESS,
				'opentelemetry.proto.collector.metrics.v1.MetricsService/Export'
			);
		}

		foreach (($input['traces'] ?? []) as $trace) {
			$json = json_encode($trace);

			$this->sendOTLP(
				self::PROTO_DIR,
				'opentelemetry/proto/collector/trace/v1/trace_service.proto',
				$json,
				self::COLLECTOR_ADDRESS,
				'opentelemetry.proto.collector.trace.v1.TraceService/Export'
			);
		}

		foreach (($input['logs'] ?? []) as $log) {
			$json = json_encode($log);

			$this->sendOTLP(
				self::PROTO_DIR,
				'opentelemetry/proto/collector/logs/v1/logs_service.proto',
				$json,
				self::COLLECTOR_ADDRESS,
				'opentelemetry.proto.collector.logs.v1.LogsService/Export'
			);
		}
	}

	private function deleteOTData() {
		$handle = curl_init();

		$sql = "TRUNCATE ALL TABLES FROM " . self::CLICKHOUSE_DB . " LIKE 'otel_%'";

		curl_setopt_array($handle, [
			CURLOPT_URL => self::CLICKHOUSE_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $sql,
			CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERNAME => self::CLICKHOUSE_USERNAME,
			CURLOPT_PASSWORD => self::CLICKHOUSE_PASSWORD
		]);

		$response = curl_exec($handle);
		$http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

		$this->assertEquals(200, $http_code, 'Unexpected http code: ' . $http_code . ', response: ' . $response);
	}

	private function executeSubcase(int $hostid, array $subcase) {
		$msg = $subcase['description'];

		$this->deleteOTData();

		$itemid = $this->createTQItem($hostid, $subcase['item']);

		$now = time();
		$this->sendInput($subcase['input_tmpl']($now));

		if (isset($subcase['delay'])) {
			sleep($subcase['delay']);
		}

		$expected_buckets = is_callable($subcase['buckets'])
			? $subcase['buckets']($now)
			: $subcase['buckets'];

		$response = $this->waitForBuckets($itemid, count($expected_buckets), 30, 1, $msg);

		self::debugLog($response['result']);

		for ($i = 0; $i < count($expected_buckets); $i++) {
			$this->assertArrayHasKey('value', $response['result'][$i], $msg);
			$value = json_decode($response['result'][$i]['value'], true, 512, JSON_THROW_ON_ERROR);

			$this->assertArrayHasKey('timestamp', $value, $msg);
			unset($value['timestamp']);

			$this->assertSame($expected_buckets[$i], $value, $msg);
		}

		$this->deleteTQItem($itemid);
	}

	public function testTelemetryQueryItems_checkData() {
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid = $response['result']['hostids'][0];

		foreach ($this->getSubcases() as $subcase) {
			$this->executeSubcase($hostid, $subcase);
		}
	}
}
