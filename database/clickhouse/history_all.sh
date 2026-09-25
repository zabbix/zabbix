#!/bin/sh
DIR="$(dirname "$0")"
"$DIR/history_schema.sh" "$@"
"$DIR/history_str_schema.sh" "$@"
"$DIR/history_log_schema.sh" "$@"
"$DIR/history_uint_schema.sh" "$@"
"$DIR/history_text_schema.sh" "$@"
"$DIR/history_json_schema.sh" "$@"
