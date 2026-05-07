#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

curl -X POST "$CH_URL?query=INSERT%20INTO%20$CH_DB.history_json%20FORMAT%20CSV" -T /tmp/history_json.csv
