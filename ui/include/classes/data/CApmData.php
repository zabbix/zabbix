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


class CApmData {

	public static function getClickHouseDbSchema(): array {
		return [
			'otel_traces' => [
				'Timestamp'						=> ['type' => 'DateTime64(9)'],
				'TraceId'						=> ['type' => 'String'],
				'SpanId'						=> ['type' => 'String'],
				'ParentSpanId'					=> ['type' => 'String'],
				'TraceState'					=> ['type' => 'String'],
				'SpanName'						=> ['type' => 'String'],
				'SpanKind'						=> ['type' => 'String'],
				'ServiceName'					=> ['type' => 'String'],
				'ResourceAttributes'			=> ['type' => 'Map'],
				'ScopeName'						=> ['type' => 'String'],
				'ScopeVersion'					=> ['type' => 'String'],
				'SpanAttributes'				=> ['type' => 'Map'],
				'Duration'						=> ['type' => 'Int64'],
				'StatusCode'					=> ['type' => 'String'],
				'StatusMessage'					=> ['type' => 'String'],
				'Events.Timestamp'				=> ['type' => 'Array'],
				'Events.Name'					=> ['type' => 'Array'],
				'Events.Attributes'				=> ['type' => 'Array'],
				'Links.TraceId'					=> ['type' => 'Array'],
				'Links.SpanId'					=> ['type' => 'Array'],
				'Links.TraceState'				=> ['type' => 'Array'],
				'Links.Attributes'				=> ['type' => 'Array']
			],
			'otel_logs' => [
				'Timestamp'						=> ['type' => 'DateTime64(9)'],
				'TraceId'						=> ['type' => 'String'],
				'SpanId'						=> ['type' => 'String'],
				'TraceFlags'					=> ['type' => 'UInt8'],
				'SeverityText'					=> ['type' => 'String'],
				'SeverityNumber'				=> ['type' => 'UInt8'],
				'ServiceName'					=> ['type' => 'String'],
				'Body'							=> ['type' => 'String'],
				'ResourceSchemaUrl'				=> ['type' => 'String'],
				'ResourceAttributes'			=> ['type' => 'Map'],
				'ScopeSchemaUrl'				=> ['type' => 'String'],
				'ScopeName'						=> ['type' => 'String'],
				'ScopeVersion'					=> ['type' => 'String'],
				'ScopeAttributes'				=> ['type' => 'Map'],
				'LogAttributes'					=> ['type' => 'Map'],
				'EventName'						=> ['type' => 'String']
			],
			'otel_metrics_gauge' => [
				'ResourceAttributes'			=> ['type' => 'Map'],
				'ResourceSchemaUrl'				=> ['type' => 'String'],
				'ScopeName'						=> ['type' => 'String'],
				'ScopeVersion'					=> ['type' => 'String'],
				'ScopeAttributes'				=> ['type' => 'Map'],
				'ScopeDroppedAttrCount'			=> ['type' => 'Map'],
				'ScopeSchemaUrl'				=> ['type' => 'String'],
				'ServiceName'					=> ['type' => 'String'],
				'MetricName'					=> ['type' => 'String'],
				'MetricDescription'				=> ['type' => 'String'],
				'MetricUnit'					=> ['type' => 'String'],
				'Attributes'					=> ['type' => 'Map'],
				'StartTimeUnix'					=> ['type' => 'DateTime64(9)'],
				'TimeUnix'						=> ['type' => 'DateTime64(9)'],
				'Value'							=> ['type' => 'Float64'],
				'Flags'							=> ['type' => 'UInt32'],
				'Exemplars.FilteredAttributes'	=> ['type' => 'Array'],
				'Exemplars.TimeUnix'			=> ['type' => 'Array'],
				'Exemplars.Value'				=> ['type' => 'Array'],
				'Exemplars.SpanId'				=> ['type' => 'Array'],
				'Exemplars.TraceId'				=> ['type' => 'Array']
			],
			'otel_metrics_sum' => [
				'ResourceAttributes'			=> ['type' => 'Map'],
				'ResourceSchemaUrl'				=> ['type' => 'String'],
				'ScopeName'						=> ['type' => 'String'],
				'ScopeVersion'					=> ['type' => 'String'],
				'ScopeAttributes'				=> ['type' => 'Map'],
				'ScopeDroppedAttrCount'			=> ['type' => 'Map'],
				'ScopeSchemaUrl'				=> ['type' => 'String'],
				'ServiceName'					=> ['type' => 'String'],
				'MetricName'					=> ['type' => 'String'],
				'MetricDescription'				=> ['type' => 'String'],
				'MetricUnit'					=> ['type' => 'String'],
				'Attributes'					=> ['type' => 'Map'],
				'StartTimeUnix'					=> ['type' => 'DateTime64(9)'],
				'TimeUnix'						=> ['type' => 'DateTime64(9)'],
				'Value'							=> ['type' => 'Float64'],
				'Flags'							=> ['type' => 'UInt32'],
				'Exemplars.FilteredAttributes'	=> ['type' => 'Array'],
				'Exemplars.TimeUnix'			=> ['type' => 'Array'],
				'Exemplars.Value'				=> ['type' => 'Array'],
				'Exemplars.SpanId'				=> ['type' => 'Array'],
				'Exemplars.TraceId'				=> ['type' => 'Array'],
				'AggregationTemporality'		=> ['type' => 'Int32'],
				'IsMonotonic'					=> ['type' => 'Bool']
			],
			'otel_metrics_histogram' => [
				'ResourceAttributes'			=> ['type' => 'Map'],
				'ResourceSchemaUrl'				=> ['type' => 'String'],
				'ScopeName'						=> ['type' => 'String'],
				'ScopeVersion'					=> ['type' => 'String'],
				'ScopeAttributes'				=> ['type' => 'Map'],
				'ScopeDroppedAttrCount'			=> ['type' => 'Map'],
				'ScopeSchemaUrl'				=> ['type' => 'String'],
				'ServiceName'					=> ['type' => 'String'],
				'MetricName'					=> ['type' => 'String'],
				'MetricDescription'				=> ['type' => 'String'],
				'MetricUnit'					=> ['type' => 'String'],
				'Attributes'					=> ['type' => 'Map'],
				'StartTimeUnix'					=> ['type' => 'DateTime64(9)'],
				'TimeUnix'						=> ['type' => 'DateTime64(9)'],
				'Count'							=> ['type' => 'UInt64'],
				'Sum'							=> ['type' => 'Float64'],
				'BucketCounts'					=> ['type' => 'Array'],
				'ExplicitBounds'				=> ['type' => 'Array'],
				'Exemplars.FilteredAttributes'	=> ['type' => 'Array'],
				'Exemplars.TimeUnix'			=> ['type' => 'Array'],
				'Exemplars.Value'				=> ['type' => 'Array'],
				'Exemplars.SpanId'				=> ['type' => 'Array'],
				'Exemplars.TraceId'				=> ['type' => 'Array'],
				'Flags'							=> ['type' => 'UInt32'],
				'Min'							=> ['type' => 'Float64'],
				'Max'							=> ['type' => 'Float64'],
				'AggregationTemporality'		=> ['type' => 'Int32']
			],
			'otel_metrics_exponential_histogram' => [
				'ResourceAttributes'			=> ['type' => 'Map'],
				'ResourceSchemaUrl'				=> ['type' => 'String'],
				'ScopeName'						=> ['type' => 'String'],
				'ScopeVersion'					=> ['type' => 'String'],
				'ScopeAttributes'				=> ['type' => 'Map'],
				'ScopeDroppedAttrCount'			=> ['type' => 'Map'],
				'ScopeSchemaUrl'				=> ['type' => 'String'],
				'ServiceName'					=> ['type' => 'String'],
				'MetricName'					=> ['type' => 'String'],
				'MetricDescription'				=> ['type' => 'String'],
				'MetricUnit'					=> ['type' => 'String'],
				'Attributes'					=> ['type' => 'Map'],
				'StartTimeUnix'					=> ['type' => 'DateTime64(9)'],
				'TimeUnix'						=> ['type' => 'DateTime64(9)'],
				'Count'							=> ['type' => 'UInt64'],
				'Sum'							=> ['type' => 'Float64'],
				'Scale'							=> ['type' => 'Int32'],
				'ZeroCount'						=> ['type' => 'UInt64'],
				'PositiveOffset'				=> ['type' => 'Int32'],
				'PositiveBucketCounts'			=> ['type' => 'Array'],
				'NegativeOffset'				=> ['type' => 'Int32'],
				'NegativeBucketCounts'			=> ['type' => 'Array'],
				'Exemplars.FilteredAttributes'	=> ['type' => 'Array'],
				'Exemplars.TimeUnix'			=> ['type' => 'Array'],
				'Exemplars.Value'				=> ['type' => 'Array'],
				'Exemplars.SpanId'				=> ['type' => 'Array'],
				'Exemplars.TraceId'				=> ['type' => 'Array'],
				'Flags'							=> ['type' => 'UInt32'],
				'Min'							=> ['type' => 'Float64'],
				'Max'							=> ['type' => 'Float64'],
				'AggregationTemporality'		=> ['type' => 'Int32']
			]
		];
	}
}
