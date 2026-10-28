#!/bin/bash

DIR="$(dirname "$0")"
CH_SCRIPT=schema
. "$DIR/clickhouse.sh"

# columns and indexes shared by all metric tables
COMMON_COLUMNS="
	ResourceAttributes Map(LowCardinality(String), String) CODEC(ZSTD(1)),
	ResourceSchemaUrl String CODEC(ZSTD(1)),
	ScopeName String CODEC(ZSTD(1)),
	ScopeVersion String CODEC(ZSTD(1)),
	ScopeAttributes Map(LowCardinality(String), String) CODEC(ZSTD(1)),
	ScopeDroppedAttrCount UInt32 CODEC(ZSTD(1)),
	ScopeSchemaUrl String CODEC(ZSTD(1)),
	ServiceName LowCardinality(String) CODEC(ZSTD(1)),
	MetricName String CODEC(ZSTD(1)),
	MetricDescription String CODEC(ZSTD(1)),
	MetricUnit String CODEC(ZSTD(1)),
	Attributes Map(LowCardinality(String), String) CODEC(ZSTD(1)),
	StartTimeUnix DateTime64(9) CODEC(Delta, ZSTD(1)),
	TimeUnix DateTime64(9) CODEC(Delta, ZSTD(1)),"

EXEMPLARS="
	\"Exemplars.FilteredAttributes\" Array(Map(LowCardinality(String), String)) CODEC(ZSTD(1)),
	\"Exemplars.TimeUnix\" Array(DateTime64(9)) CODEC(ZSTD(1)),
	\"Exemplars.Value\" Array(Float64) CODEC(ZSTD(1)),
	\"Exemplars.SpanId\" Array(String) CODEC(ZSTD(1)),
	\"Exemplars.TraceId\" Array(String) CODEC(ZSTD(1)),"

COMMON_INDEXES="
	INDEX idx_res_attr_key mapKeys(ResourceAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_res_attr_value mapValues(ResourceAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_scope_attr_key mapKeys(ScopeAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_scope_attr_value mapValues(ScopeAttributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_attr_key mapKeys(Attributes) TYPE bloom_filter(0.01) GRANULARITY 1,
	INDEX idx_attr_value mapValues(Attributes) TYPE bloom_filter(0.01) GRANULARITY 1"

TABLE_OPTIONS="
ENGINE = $CH_ENGINE
PARTITION BY $CH_PARTITION(TimeUnix)
ORDER BY (ServiceName, MetricName, Attributes, toUnixTimestamp64Nano(TimeUnix))
TTL toDateTime(TimeUnix) + toIntervalSecond($CH_TTL)
SETTINGS index_granularity = 8192, ttl_only_drop_parts = 1"

ch_query << EOF || exit 1
CREATE TABLE IF NOT EXISTS $CH_DB.otel_metrics_gauge$CH_LOCAL_SUFFIX$CH_ON_CLUSTER
($COMMON_COLUMNS
	Value Float64 CODEC(ZSTD(1)),
	Flags UInt32 CODEC(ZSTD(1)),$EXEMPLARS$COMMON_INDEXES
)$TABLE_OPTIONS
EOF

ch_query << EOF || exit 1
CREATE TABLE IF NOT EXISTS $CH_DB.otel_metrics_sum$CH_LOCAL_SUFFIX$CH_ON_CLUSTER
($COMMON_COLUMNS
	Value Float64 CODEC(ZSTD(1)),
	Flags UInt32 CODEC(ZSTD(1)),$EXEMPLARS
	AggregationTemporality Int32 CODEC(ZSTD(1)),
	IsMonotonic Boolean CODEC(Delta, ZSTD(1)),$COMMON_INDEXES
)$TABLE_OPTIONS
EOF

ch_query << EOF || exit 1
CREATE TABLE IF NOT EXISTS $CH_DB.otel_metrics_histogram$CH_LOCAL_SUFFIX$CH_ON_CLUSTER
($COMMON_COLUMNS
	Count UInt64 CODEC(Delta, ZSTD(1)),
	Sum Float64 CODEC(ZSTD(1)),
	BucketCounts Array(UInt64) CODEC(ZSTD(1)),
	ExplicitBounds Array(Float64) CODEC(ZSTD(1)),$EXEMPLARS
	Flags UInt32 CODEC(ZSTD(1)),
	Min Float64 CODEC(ZSTD(1)),
	Max Float64 CODEC(ZSTD(1)),
	AggregationTemporality Int32 CODEC(ZSTD(1)),$COMMON_INDEXES
)$TABLE_OPTIONS
EOF

ch_query << EOF || exit 1
CREATE TABLE IF NOT EXISTS $CH_DB.otel_metrics_exponential_histogram$CH_LOCAL_SUFFIX$CH_ON_CLUSTER
($COMMON_COLUMNS
	Count UInt64 CODEC(Delta, ZSTD(1)),
	Sum Float64 CODEC(ZSTD(1)),
	Scale Int32 CODEC(ZSTD(1)),
	ZeroCount UInt64 CODEC(ZSTD(1)),
	PositiveOffset Int32 CODEC(ZSTD(1)),
	PositiveBucketCounts Array(UInt64) CODEC(ZSTD(1)),
	NegativeOffset Int32 CODEC(ZSTD(1)),
	NegativeBucketCounts Array(UInt64) CODEC(ZSTD(1)),$EXEMPLARS
	Flags UInt32 CODEC(ZSTD(1)),
	Min Float64 CODEC(ZSTD(1)),
	Max Float64 CODEC(ZSTD(1)),
	AggregationTemporality Int32 CODEC(ZSTD(1)),$COMMON_INDEXES
)$TABLE_OPTIONS
EOF

for table in otel_metrics_gauge otel_metrics_sum otel_metrics_histogram otel_metrics_exponential_histogram; do
	ch_create_otel_distributed_table $table "rand()" || exit 1
done
