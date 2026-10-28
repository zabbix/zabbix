#!/bin/sh
DIR="$(dirname "$0")"

for script in otel_logs_schema.sh otel_traces_schema.sh otel_metrics_schema.sh; do
	"$DIR/$script" "$@"

	case $? in
	0)
		;;
	2)
		exit 2
		;;
	*)
		echo "$script failed" >&2
		exit 1
		;;
	esac
done
