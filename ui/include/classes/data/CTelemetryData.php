<?php declare(strict_types = 0);
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
 * Class containing information on telemetry query items.
 */
final class CTelemetryData {

	// Trace columns.
	public const COL_TIMESTAMP = 'Timestamp';
	public const COL_TRACE_ID = 'TraceId';
	public const COL_SPAN_ID = 'SpanId';
	public const COL_PARENT_SPAN_ID = 'ParentSpanId';
	public const COL_TRACE_STATE = 'TraceState';
	public const COL_SPAN_NAME = 'SpanName';
	public const COL_SPAN_KIND = 'SpanKind';
	public const COL_SERVICE_NAME = 'ServiceName';
	public const COL_RESOURCE_ATTRIBUTES = 'ResourceAttributes';
	public const COL_SPAN_ATTRIBUTES = 'SpanAttributes';
	public const COL_SCOPE_NAME = 'ScopeName';
	public const COL_SCOPE_VERSION = 'ScopeVersion';
	public const COL_DURATION = 'Duration';
	public const COL_STATUS_CODE = 'StatusCode';
	public const COL_STATUS_MESSAGE = 'StatusMessage';
	public const COL_EVENTS_NAME = 'Events.Name';
	public const COL_EVENTS_ATTRIBUTES = 'Events.Attributes';

	// Log columns.
	public const COL_TRACE_FLAGS = 'TraceFlags';
	public const COL_SEVERITY_TEXT = 'SeverityText';
	public const COL_SEVERITY_NUMBER = 'SeverityNumber';
	public const COL_BODY = 'Body';
	public const COL_RESOURCE_SCHEMA_URL = 'ResourceSchemaUrl';
	public const COL_SCOPE_SCHEMA_URL = 'ScopeSchemaUrl';
	public const COL_SCOPE_ATTRIBUTES = 'ScopeAttributes';
	public const COL_LOG_ATTRIBUTES = 'LogAttributes';
	public const COL_EVENT_NAME = 'EventName';

	// Metric columns.
	public const COL_SCOPE_DROPPED_ATTR_COUNT = 'ScopeDroppedAttrCount';
	public const COL_METRIC_NAME = 'MetricName';
	public const COL_METRIC_DESCRIPTION = 'MetricDescription';
	public const COL_METRIC_UNIT = 'MetricUnit';
	public const COL_ATTRIBUTES = 'Attributes';
	public const COL_START_TIME_UNIX = 'StartTimeUnix';
	public const COL_TIME_UNIX = 'TimeUnix';
	public const COL_FLAGS = 'Flags';
	public const COL_VALUE = 'Value';
	public const COL_AGGREGATION_TEMPORALITY = 'AggregationTemporality';
	public const COL_IS_MONOTONIC = 'IsMonotonic';
	public const COL_COUNT = 'Count';
	public const COL_SUM = 'Sum';
	public const COL_MIN = 'Min';
	public const COL_MAX = 'Max';
	public const COL_SCALE = 'Scale';
	public const COL_ZERO_COUNT = 'ZeroCount';
	public const COL_POSITIVE_OFFSET = 'PositiveOffset';
	public const COL_NEGATIVE_OFFSET = 'NegativeOffset';
	public const COL_EXEMPLARS_FILTERED_ATTRIBUTES = 'Exemplars.FilteredAttributes';

	/**
	 * Metric columns shared by all metric point types, per section.
	 */
	private const METRICS_COLUMNS = [
		self::COL_RESOURCE_ATTRIBUTES,
		self::COL_RESOURCE_SCHEMA_URL,
		self::COL_SCOPE_NAME,
		self::COL_SCOPE_VERSION,
		self::COL_SCOPE_ATTRIBUTES,
		self::COL_SCOPE_DROPPED_ATTR_COUNT,
		self::COL_SCOPE_SCHEMA_URL,
		self::COL_SERVICE_NAME,
		self::COL_METRIC_NAME,
		self::COL_METRIC_DESCRIPTION,
		self::COL_METRIC_UNIT,
		self::COL_ATTRIBUTES,
		self::COL_START_TIME_UNIX,
		self::COL_TIME_UNIX,
		self::COL_FLAGS,
	];

	private const METRICS_AGGREGATED = [
		self::COL_SCOPE_DROPPED_ATTR_COUNT,
		self::COL_START_TIME_UNIX,
		self::COL_TIME_UNIX,
	];

	private const METRICS_CONDITIONS = [
		self::COL_RESOURCE_ATTRIBUTES,
		self::COL_RESOURCE_SCHEMA_URL,
		self::COL_SCOPE_NAME,
		self::COL_SCOPE_VERSION,
		self::COL_SCOPE_ATTRIBUTES,
		self::COL_SCOPE_SCHEMA_URL,
		self::COL_SERVICE_NAME,
		self::COL_METRIC_NAME,
		self::COL_METRIC_DESCRIPTION,
		self::COL_METRIC_UNIT,
		self::COL_ATTRIBUTES,
		self::COL_EXEMPLARS_FILTERED_ATTRIBUTES,
	];

	/**
	 * Telemetry query item column configuration.
	 *
	 * Each section ("columns", "aggregated_columns", "conditions") has its own allowed column names, keyed by
	 * signal_type (metrics keyed additionally by metric_point_type).
	 *
	 */
	private const COLUMN_CONFIG = [
		'columns' => [
			APM_SIGNAL_TYPE_TRACES => [
				self::COL_TIMESTAMP,
				self::COL_TRACE_ID,
				self::COL_SPAN_ID,
				self::COL_PARENT_SPAN_ID,
				self::COL_TRACE_STATE,
				self::COL_SPAN_NAME,
				self::COL_SPAN_KIND,
				self::COL_SERVICE_NAME,
				self::COL_RESOURCE_ATTRIBUTES,
				self::COL_SPAN_ATTRIBUTES,
				self::COL_SCOPE_NAME,
				self::COL_SCOPE_VERSION,
				self::COL_DURATION,
				self::COL_STATUS_CODE,
				self::COL_STATUS_MESSAGE
			],
			APM_SIGNAL_TYPE_LOGS => [
				self::COL_TIMESTAMP,
				self::COL_TRACE_ID,
				self::COL_SPAN_ID,
				self::COL_TRACE_FLAGS,
				self::COL_SEVERITY_TEXT,
				self::COL_SEVERITY_NUMBER,
				self::COL_SERVICE_NAME,
				self::COL_BODY,
				self::COL_RESOURCE_SCHEMA_URL,
				self::COL_SCOPE_SCHEMA_URL,
				self::COL_SCOPE_NAME,
				self::COL_SCOPE_VERSION,
				self::COL_RESOURCE_ATTRIBUTES,
				self::COL_SCOPE_ATTRIBUTES,
				self::COL_LOG_ATTRIBUTES,
				self::COL_EVENT_NAME
			],
			APM_SIGNAL_TYPE_METRICS => [
				APM_METRICS_POINT_SUM => [
					...self::METRICS_COLUMNS,
					self::COL_VALUE,
					self::COL_AGGREGATION_TEMPORALITY,
					self::COL_IS_MONOTONIC
				],
				APM_METRICS_POINT_GAUGE => [
					...self::METRICS_COLUMNS,
					self::COL_VALUE
				],
				APM_METRICS_POINT_HISTOGRAM => [
					...self::METRICS_COLUMNS,
					self::COL_COUNT,
					self::COL_SUM,
					self::COL_MIN,
					self::COL_MAX,
					self::COL_AGGREGATION_TEMPORALITY
				],
				APM_METRICS_POINT_EXPHISTOGRAM => [
					...self::METRICS_COLUMNS,
					self::COL_COUNT,
					self::COL_SUM,
					self::COL_SCALE,
					self::COL_ZERO_COUNT,
					self::COL_POSITIVE_OFFSET,
					self::COL_NEGATIVE_OFFSET,
					self::COL_MIN,
					self::COL_MAX,
					self::COL_AGGREGATION_TEMPORALITY
				]
			]
		],
		'aggregated_columns' => [
			APM_SIGNAL_TYPE_TRACES => [
				self::COL_TIMESTAMP,
				self::COL_DURATION
			],
			APM_SIGNAL_TYPE_LOGS => [
				self::COL_TIMESTAMP,
				self::COL_SEVERITY_NUMBER
			],
			APM_SIGNAL_TYPE_METRICS => [
				APM_METRICS_POINT_SUM => [
					...self::METRICS_AGGREGATED,
					self::COL_VALUE
				],
				APM_METRICS_POINT_GAUGE => [
					...self::METRICS_AGGREGATED,
					self::COL_VALUE
				],
				APM_METRICS_POINT_HISTOGRAM => [
					...self::METRICS_AGGREGATED,
					self::COL_COUNT,
					self::COL_SUM,
					self::COL_MIN,
					self::COL_MAX
				],
				APM_METRICS_POINT_EXPHISTOGRAM => [
					...self::METRICS_AGGREGATED,
					self::COL_COUNT,
					self::COL_SUM,
					self::COL_SCALE,
					self::COL_ZERO_COUNT,
					self::COL_POSITIVE_OFFSET,
					self::COL_NEGATIVE_OFFSET,
					self::COL_MIN,
					self::COL_MAX
				]
			]
		],
		'conditions' => [
			APM_SIGNAL_TYPE_TRACES => [
				self::COL_TRACE_ID,
				self::COL_SPAN_ID,
				self::COL_PARENT_SPAN_ID,
				self::COL_TRACE_STATE,
				self::COL_SPAN_NAME,
				self::COL_SPAN_KIND,
				self::COL_SERVICE_NAME,
				self::COL_RESOURCE_ATTRIBUTES,
				self::COL_SPAN_ATTRIBUTES,
				self::COL_SCOPE_NAME,
				self::COL_SCOPE_VERSION,
				self::COL_STATUS_CODE,
				self::COL_STATUS_MESSAGE,
				self::COL_EVENTS_NAME,
				self::COL_EVENTS_ATTRIBUTES
			],
			APM_SIGNAL_TYPE_LOGS => [
				self::COL_TRACE_ID,
				self::COL_SPAN_ID,
				self::COL_SEVERITY_TEXT,
				self::COL_SERVICE_NAME,
				self::COL_BODY,
				self::COL_RESOURCE_SCHEMA_URL,
				self::COL_SCOPE_SCHEMA_URL,
				self::COL_SCOPE_NAME,
				self::COL_SCOPE_VERSION,
				self::COL_RESOURCE_ATTRIBUTES,
				self::COL_SCOPE_ATTRIBUTES,
				self::COL_LOG_ATTRIBUTES,
				self::COL_EVENT_NAME
			],
			APM_SIGNAL_TYPE_METRICS => [
				APM_METRICS_POINT_SUM => self::METRICS_CONDITIONS,
				APM_METRICS_POINT_GAUGE => self::METRICS_CONDITIONS,
				APM_METRICS_POINT_HISTOGRAM => self::METRICS_CONDITIONS,
				APM_METRICS_POINT_EXPHISTOGRAM => self::METRICS_CONDITIONS
			]
		],
		'complex' => [
			self::COL_RESOURCE_ATTRIBUTES,
			self::COL_SPAN_ATTRIBUTES,
			self::COL_SCOPE_ATTRIBUTES,
			self::COL_ATTRIBUTES,
			self::COL_LOG_ATTRIBUTES,
			self::COL_EVENTS_ATTRIBUTES,
			self::COL_EXEMPLARS_FILTERED_ATTRIBUTES
		]
	];

	/**
	 * Returns the main telemetry query item column configuration.
	 *
	 * @return array
	 */
	public static function getColumnConfig(): array {
		return self::COLUMN_CONFIG;
	}

	/**
	 * Returns the complex (map) column names that require an attribute key.
	 *
	 * @return array
	 */
	public static function getComplexColumns(): array {
		return self::COLUMN_CONFIG['complex'];
	}

	/**
	 * Display labels for the aggregation functions, keyed by AGGREGATE_* constants.
	 *
	 * @return array
	 */
	public static function getFunctionLabels(): array {
		return [
			AGGREGATE_MIN => _('min'),
			AGGREGATE_MAX => _('max'),
			AGGREGATE_AVG => _('avg'),
			AGGREGATE_COUNT => _('count'),
			AGGREGATE_SUM => _('sum'),
			AGGREGATE_PCTILE => _('percentile')
		];
	}

	/**
	 * Display labels for the condition operators, keyed by CONDITION_OPERATOR_* constants.
	 *
	 * @return array
	 */
	public static function getOperatorLabels(): array {
		return [
			CONDITION_OPERATOR_EQUAL => _('equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('does not equal'),
			CONDITION_OPERATOR_LIKE => _('contains'),
			CONDITION_OPERATOR_NOT_LIKE => _('does not contain'),
			CONDITION_OPERATOR_EXISTS => _('exists')
		];
	}

	/**
	 * Condition operators allowed per column type.
	 *
	 * @return array
	 */
	public static function getConditionOperators(): array {
		return [
			'complex' => [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_EXISTS
			],
			'simple' => [
				CONDITION_OPERATOR_EQUAL,
				CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_LIKE,
				CONDITION_OPERATOR_NOT_LIKE
			]
		];
	}

	/**
	 * Column options (name => name) available in the Aggregated columns table, for the given signal/metric type.
	 *
	 * @return array
	 */
	public static function getAggregatedColumns(int $signal_type, int $metric_point_type): array {
		$names = self::getSectionColumns('aggregated_columns', $signal_type, $metric_point_type);

		return array_combine($names, $names);
	}

	/**
	 * Column options (name => name) available in the Conditions table, sorted alphabetically, for the given
	 * signal/metric type.
	 *
	 * @return array
	 */
	public static function getConditionColumns(int $signal_type, int $metric_point_type): array {
		$names = self::getSectionColumns('conditions', $signal_type, $metric_point_type);
		sort($names);

		return array_combine($names, $names);
	}

	private static function getSectionColumns(string $section, int $signal_type, int $metric_point_type): array {
		$config = self::getColumnConfig();

		return $signal_type == APM_SIGNAL_TYPE_METRICS
			? $config[$section][$signal_type][$metric_point_type]
			: $config[$section][$signal_type];
	}
}
