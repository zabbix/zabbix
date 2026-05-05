#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

curl -X POST "$CH_URL?query=INSERT%20INTO%20$CH_DB.history_str%20FORMAT%20CSV" -T /tmp/history_str.csv
