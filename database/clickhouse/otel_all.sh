#!/bin/sh
DIR="$(dirname "$0")"

for script in otel_logs_schema.sh otel_traces_schema.sh otel_metrics_schema.sh; do
	if ! "$DIR/$script" "$@"; then
		echo "$script failed" >&2
		exit 1
	fi
done
