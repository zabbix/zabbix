#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

while IFS=, read -r itemid clock_ns value; do
	val="${value%\"}"
	val="${val#\"}"
	val="${val//\"\"/\"}"

	# If value looks like a JSON object, insert into JSON
	if [[ "$val" =~ ^\{.*\}$ ]]; then
	    echo "$itemid,$clock_ns,$val,"  # value_str empty
	else
	    echo "$itemid,$clock_ns,,\"$val\""  # value empty, goes to value_str
	fi
done < /tmp/history_json.csv | curl -X POST "$CH_URL?query=INSERT%20INTO%20$CH_DB.history_json%20FORMAT%20CSV" -T -
