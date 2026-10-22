#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

ch_query << EOF || exit 1
CREATE TABLE $CH_DB.otel_traces
(
	Timestamp DateTime64(9) CODEC(Delta, ZSTD(1)),
	TraceId String CODEC(ZSTD(1)),
	SpanId String CODEC(ZSTD(1)),
	ParentSpanId String CODEC(ZSTD(1)),
	TraceState String CODEC(ZSTD(1)),
	SpanName LowCardinality(String) CODEC(ZSTD(1)),
	SpanKind LowCardinality(String) CODEC(ZSTD(1)),
	ServiceName LowCardinality(String) CODEC(ZSTD(1)),
	ResourceAttributes Map(LowCardinality(String), String) CODEC(ZSTD(1)),
	ScopeName String CODEC(ZSTD(1)),
	ScopeVersion String CODEC(ZSTD(1)),
	SpanAttributes Map(LowCardinality(String), String) CODEC(ZSTD(1)),
	Duration UInt64 CODEC(ZSTD(1)),
	StatusCode LowCardinality(String) CODEC(ZSTD(1)),
	StatusMessage String CODEC(ZSTD(1)),
	"Events.Timestamp" Array(DateTime64(9)) CODEC(ZSTD(1)),
	"Events.Name" Array(LowCardinality(String)) CODEC(ZSTD(1)),
	"Events.Attributes" Array(Map(LowCardinality(String), String)) CODEC(ZSTD(1)),
	"Links.TraceId" Array(String) CODEC(ZSTD(1)),
	"Links.SpanId" Array(String) CODEC(ZSTD(1)),
	"Links.TraceState" Array(String) CODEC(ZSTD(1)),
	"Links.Attributes" Array(Map(LowCardinality(String), String)) CODEC(ZSTD(1)),
	INDEX idx_trace_id TraceId TYPE bloom_filter(0.001) GRANULARITY 1,
	INDEX idx_res_attr_key mapKeys(ResourceAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_res_attr_value mapValues(ResourceAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_span_attr_key mapKeys(SpanAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_span_attr_value mapValues(SpanAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_duration Duration TYPE minmax GRANULARITY 1
)
ENGINE = $CH_ENGINE
PARTITION BY $CH_PARTITION(Timestamp)
ORDER BY (ServiceName, SpanName, toDateTime(Timestamp))
TTL toDateTime(Timestamp) + toIntervalSecond($CH_TTL)
SETTINGS index_granularity = 8192, ttl_only_drop_parts = 1
EOF
