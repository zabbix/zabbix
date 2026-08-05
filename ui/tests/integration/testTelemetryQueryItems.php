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
	/* CLICKHOUSE_* constants must not contain '\' or '"' (must not require escaping) */
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

	private static function tmplTrace(string $startTimeUnixNano, string $endTimeUnixNano) {
		return [
			'resourceSpans' => [
				[
					'resource' => [
						'attributes' => [
							[
								'key' => 'service.name',
								'value' => ['stringValue' => 'trace-test-service'],
							],
							[
								'key' => 'host.name',
								'value' => ['stringValue' => 'trace-test-host'],
							],
							[
								'key' => 'deployment.environment',
								'value' => ['stringValue' => 'test'],
							],
							[
								'key' => 'rum.sessionId',
								'value' => ['stringValue' => 'rum-session-123'],
							],
						],
					],
					'schemaUrl' => 'https://example.com/schemas/resource/trace/1.0.0',
					'scopeSpans' => [
						[
							'scope' => [
								'name' => 'trace-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									[
										'key' => 'scope.attr',
										'value' => ['stringValue' => 'trace-scope-value'],
									],
								],
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
										[
											'key' => 'http.method',
											'value' => ['stringValue' => 'GET'],
										],
										[
											'key' => 'http.route',
											'value' => ['stringValue' => '/trace'],
										],
										[
											'key' => 'SampleRate',
											'value' => ['stringValue' => '100'],
										],
									],
									'events' => [
										[
											'timeUnixNano' => $endTimeUnixNano,
											'name' => 'test-span-event',
											'attributes' => [
												[
													'key' => 'event.attr',
													'value' => ['stringValue' => 'event-value'],
												],
											],
										],
									],
									'links' => [
										[
											'traceId' => base64_encode(hex2bin('5123456789abcdef0123456789abcdef')),
											'spanId' => base64_encode(hex2bin('7122334455667788')),
											'traceState' => 'linked-vendor=value',
											'attributes' => [
												[
													'key' => 'link.attr',
													'value' => ['stringValue' => 'link-value'],
												],
											],
										],
									],
									'status' => [
										'code' => 'STATUS_CODE_OK',
										'message' => 'trace status message',
									],
								],
							],
						],
					],
				],
			],
		];
	}

	private static function tmplLog(string $timeUnixNano) {
		return [
			'resourceLogs' => [
				[
					'resource' => [
						'attributes' => [
							[
								'key' => 'service.name',
								'value' => ['stringValue' => 'log-test-service'],
							],
							[
								'key' => 'host.name',
								'value' => ['stringValue' => 'log-test-host'],
							],
							[
								'key' => 'k8s.cluster.name',
								'value' => ['stringValue' => 'test-cluster'],
							],
							[
								'key' => 'k8s.container.name',
								'value' => ['stringValue' => 'test-container'],
							],
							[
								'key' => 'k8s.deployment.name',
								'value' => ['stringValue' => 'test-deployment'],
							],
							[
								'key' => 'k8s.namespace.name',
								'value' => ['stringValue' => 'test-namespace'],
							],
							[
								'key' => 'k8s.node.name',
								'value' => ['stringValue' => 'test-node'],
							],
							[
								'key' => 'k8s.pod.name',
								'value' => ['stringValue' => 'test-pod'],
							],
							[
								'key' => 'k8s.pod.uid',
								'value' => ['stringValue' => 'test-pod-uid'],
							],
							[
								'key' => 'deployment.environment.name',
								'value' => ['stringValue' => 'test'],
							],
						],
					],
					'schemaUrl' => 'https://example.com/schemas/resource/log/1.0.0',
					'scopeLogs' => [
						[
							'scope' => [
								'name' => 'log-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									[
										'key' => 'scope.attr',
										'value' => ['stringValue' => 'log-scope-value'],
									],
								],
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
									'body' => [
										'stringValue' => 'test log body',
									],
									'attributes' => [
										[
											'key' => 'log.attr',
											'value' => ['stringValue' => 'log-attribute-value'],
										],
										[
											'key' => 'logger.name',
											'value' => ['stringValue' => 'test-logger'],
										],
									],
									'eventName' => 'test-log-event',
								],
							],
						],
					],
				],
			],
		];
	}

	private static function tmplMetricSum(string $startTimeUnixNano, string $timeUnixNano) {
		return [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							[
								'key' => 'service.name',
								'value' => ['stringValue' => 'sum-test-service'],
							],
							[
								'key' => 'host.name',
								'value' => ['stringValue' => 'sum-test-host'],
							],
							[
								'key' => 'deployment.environment',
								'value' => ['stringValue' => 'test'],
							],
						],
					],
					'schemaUrl' => 'https://example.com/schemas/resource/sum/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'sum-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									[
										'key' => 'scope.attr',
										'value' => ['stringValue' => 'sum-scope-value'],
									],
								],
								'droppedAttributesCount' => 11,
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
													[
														'key' => 'endpoint',
														'value' => ['stringValue' => '/sum'],
													],
													[
														'key' => 'method',
														'value' => ['stringValue' => 'grpcurl'],
													],
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'asDouble' => 42.5,
												'flags' => 1,
												'exemplars' => [
													[
														'filteredAttributes' => [
															[
																'key' => 'exemplar.attr',
																'value' => ['stringValue' => 'sum-exemplar-value'],
															],
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 42.5,
														'spanId' => base64_encode(hex2bin('2122334455667788')),
														'traceId' => base64_encode(hex2bin('1123456789abcdef0123456789abcdef')),
													],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	private static function tmplMetricGauge(string $startTimeUnixNano, string $timeUnixNano) {
		return [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							[
								'key' => 'service.name',
								'value' => ['stringValue' => 'gauge-test-service'],
							],
							[
								'key' => 'host.name',
								'value' => ['stringValue' => 'test-host'],
							],
							[
								'key' => 'deployment.environment',
								'value' => ['stringValue' => 'test'],
							],
						],
					],
					'schemaUrl' => 'https://example.com/schemas/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'test-scope',
								'version' => '1.0.0',
								'attributes' => [
									[
										'key' => 'scope.attr',
										'value' => ['stringValue' => 'scope-value'],
									],
								],
								'droppedAttributesCount' => 7,
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
													[
														'key' => 'endpoint',
														'value' => ['stringValue' => '/test'],
													],
													[
														'key' => 'method',
														'value' => ['stringValue' => 'grpcurl'],
													],
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'asDouble' => 123.456,
												'flags' => 1,
												'exemplars' => [
													[
														'filteredAttributes' => [
															[
																'key' => 'exemplar.attr',
																'value' => ['stringValue' => 'exemplar-value'],
															],
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 123.456,
														'spanId' => base64_encode(hex2bin('1122334455667788')),
														'traceId' => base64_encode(hex2bin('0123456789abcdef0123456789abcdef')),
													],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	private static function tmplMetricHistogram(string $startTimeUnixNano, string $timeUnixNano) {
		return [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							[
								'key' => 'service.name',
								'value' => ['stringValue' => 'histogram-test-service'],
							],
							[
								'key' => 'host.name',
								'value' => ['stringValue' => 'histogram-test-host'],
							],
							[
								'key' => 'deployment.environment',
								'value' => ['stringValue' => 'test'],
							],
						],
					],
					'schemaUrl' => 'https://example.com/schemas/resource/histogram/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'histogram-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									[
										'key' => 'scope.attr',
										'value' => ['stringValue' => 'histogram-scope-value'],
									],
								],
								'droppedAttributesCount' => 13,
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
													[
														'key' => 'endpoint',
														'value' => ['stringValue' => '/histogram'],
													],
													[
														'key' => 'method',
														'value' => ['stringValue' => 'grpcurl'],
													],
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
															[
																'key' => 'exemplar.attr',
																'value' => ['stringValue' => 'histogram-exemplar-value'],
															],
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 25.0,
														'spanId' => base64_encode(hex2bin('3122334455667788')),
														'traceId' => base64_encode(hex2bin('2123456789abcdef0123456789abcdef')),
													],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	private static function tmplMetricExponentialHistogram(string $startTimeUnixNano, string $timeUnixNano) {
		return [
			'resourceMetrics' => [
				[
					'resource' => [
						'attributes' => [
							[
								'key' => 'service.name',
								'value' => ['stringValue' => 'exponential-histogram-test-service'],
							],
							[
								'key' => 'host.name',
								'value' => ['stringValue' => 'exponential-histogram-test-host'],
							],
							[
								'key' => 'deployment.environment',
								'value' => ['stringValue' => 'test'],
							],
						],
					],
					'schemaUrl' => 'https://example.com/schemas/resource/exponential-histogram/1.0.0',
					'scopeMetrics' => [
						[
							'scope' => [
								'name' => 'exponential-histogram-test-scope',
								'version' => '1.0.0',
								'attributes' => [
									[
										'key' => 'scope.attr',
										'value' => ['stringValue' => 'exponential-histogram-scope-value'],
									],
								],
								'droppedAttributesCount' => 17,
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
													[
														'key' => 'endpoint',
														'value' => ['stringValue' => '/exponential-histogram'],
													],
													[
														'key' => 'method',
														'value' => ['stringValue' => 'grpcurl'],
													],
												],
												'startTimeUnixNano' => $startTimeUnixNano,
												'timeUnixNano' => $timeUnixNano,
												'count' => '7',
												'sum' => 77.0,
												'scale' => 2,
												'zeroCount' => '1',
												'positive' => [
													'offset' => 1,
													'bucketCounts' => ['2', '3'],
												],
												'negative' => [
													'offset' => -2,
													'bucketCounts' => ['1'],
												],
												'flags' => 1,
												'min' => -8.0,
												'max' => 32.0,
												'exemplars' => [
													[
														'filteredAttributes' => [
															[
																'key' => 'exemplar.attr',
																'value' => ['stringValue' => 'exponential-histogram-exemplar-value'],
															],
														],
														'timeUnixNano' => $timeUnixNano,
														'asDouble' => 16.0,
														'spanId' => base64_encode(hex2bin('4122334455667788')),
														'traceId' => base64_encode(hex2bin('3123456789abcdef0123456789abcdef')),
													],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	private function getSubcases(): array {
		return [
			[
				'description' => 'Minimal traces test',
				'item' => [
					'query' => [
						'signal_type' => APM_SIGNAL_TYPE_TRACES,
						'metric_point_type' => APM_METRICS_POINT_SUM,
						'columns' => [],
						'aggregated_columns' => [
							[
								'column' => '',
								'function' => AGGREGATE_COUNT,
								'parameters' => [],
								'alias' => 'cnt'
							]
						],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => '',
							'conditions' => []
						]
					],
					'time_shift' => '0',
					'lookback_limit' => '480',
					'granularity' => '480'
				],
				'buckets' => [
					[
						'id' => 1,
						'columns' => [
							'cnt' => 1
						]
					],
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'traces' => [
						self::tmplTrace(self::tsOffStr($now, -10), self::tsOffStr($now, -5))
					]
				]
			],
			[
				'description' => 'Minimal logs test',
				'item' => [
					'query' => [
						'signal_type' => APM_SIGNAL_TYPE_LOGS,
						'metric_point_type' => APM_METRICS_POINT_SUM,
						'columns' => [],
						'aggregated_columns' => [
							[
								'column' => '',
								'function' => AGGREGATE_COUNT,
								'parameters' => [],
								'alias' => 'cnt'
							]
						],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => '',
							'conditions' => []
						]
					],
					'time_shift' => '0',
					'lookback_limit' => '480',
					'granularity' => '480'
				],
				'buckets' => [
					[
						'id' => 1,
						'columns' => [
							'cnt' => 1
						]
					],
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'logs' => [
						self::tmplLog(self::tsOffStr($now, -10))
					]
				]
			],
			[
				'description' => 'Minimal metrics sum test',
				'item' => [
					'query' => [
						'signal_type' => APM_SIGNAL_TYPE_METRICS,
						'metric_point_type' => APM_METRICS_POINT_SUM,
						'columns' => [],
						'aggregated_columns' => [
							[
								'column' => '',
								'function' => AGGREGATE_COUNT,
								'parameters' => [],
								'alias' => 'cnt'
							]
						],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => '',
							'conditions' => []
						]
					],
					'time_shift' => '0',
					'lookback_limit' => '480',
					'granularity' => '480'
				],
				'buckets' => [
					[
						'id' => 1,
						'columns' => [
							'cnt' => 1
						]
					],
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [
						self::tmplMetricSum(self::tsOffStr($now, -10), self::tsOffStr($now, -5))
					]
				]
			],
			[
				'description' => 'Minimal metrics gauge test',
				'item' => [
					'query' => [
						'signal_type' => APM_SIGNAL_TYPE_METRICS,
						'metric_point_type' => APM_METRICS_POINT_GAUGE,
						'columns' => [],
						'aggregated_columns' => [
							[
								'column' => '',
								'function' => AGGREGATE_COUNT,
								'parameters' => [],
								'alias' => 'cnt'
							]
						],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => '',
							'conditions' => []
						]
					],
					'time_shift' => '0',
					'lookback_limit' => '480',
					'granularity' => '480'
				],
				'buckets' => [
					[
						'id' => 1,
						'columns' => [
							'cnt' => 1
						]
					],
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [
						self::tmplMetricGauge(self::tsOffStr($now, -10), self::tsOffStr($now, -5))
					]
				]
			],
			[
				'description' => 'Minimal metrics histogram test',
				'item' => [
					'query' => [
						'signal_type' => APM_SIGNAL_TYPE_METRICS,
						'metric_point_type' => APM_METRICS_POINT_HISTOGRAM,
						'columns' => [],
						'aggregated_columns' => [
							[
								'column' => '',
								'function' => AGGREGATE_COUNT,
								'parameters' => [],
								'alias' => 'cnt'
							]
						],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => '',
							'conditions' => []
						]
					],
					'time_shift' => '0',
					'lookback_limit' => '480',
					'granularity' => '480'
				],
				'buckets' => [
					[
						'id' => 1,
						'columns' => [
							'cnt' => 1
						]
					],
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [
						self::tmplMetricHistogram(self::tsOffStr($now, -10), self::tsOffStr($now, -5))
					]
				]
			],
			[
				'description' => 'Minimal metrics exponential histogram test',
				'item' => [
					'query' => [
						'signal_type' => APM_SIGNAL_TYPE_METRICS,
						'metric_point_type' => APM_METRICS_POINT_EXPHISTOGRAM,
						'columns' => [],
						'aggregated_columns' => [
							[
								'column' => '',
								'function' => AGGREGATE_COUNT,
								'parameters' => [],
								'alias' => 'cnt'
							]
						],
						'filter' => [
							'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
							'formula' => '',
							'conditions' => []
						]
					],
					'time_shift' => '0',
					'lookback_limit' => '480',
					'granularity' => '480'
				],
				'buckets' => [
					[
						'id' => 1,
						'columns' => [
							'cnt' => 1
						]
					],
				],
				'delay' => 0,
				'input_tmpl' => fn(int $now) => [
					'metrics' => [
						self::tmplMetricExponentialHistogram(self::tsOffStr($now, -10), self::tsOffStr($now, -5))
					]
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

		$response = $this->waitForBuckets($itemid, count($subcase['buckets']), 30, 1, $msg);

		self::debugLog($response['result']);

		for ($i = 0; $i < count($subcase['buckets']); $i++) {
			$this->assertArrayHasKey('value', $response['result'][$i], $msg);
			$value = json_decode($response['result'][$i]['value'], true, 512, JSON_THROW_ON_ERROR);

			$this->assertArrayHasKey('timestamp', $value, $msg);
			unset($value['timestamp']);

			$this->assertSame($subcase['buckets'][$i], $value, $msg);
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
