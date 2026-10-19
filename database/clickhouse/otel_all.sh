#!/bin/sh
set -e
DIR="$(dirname "$0")"
"$DIR/otel_logs_schema.sh" "$@"
"$DIR/otel_traces_schema.sh" "$@"
"$DIR/otel_metrics_schema.sh" "$@"
