#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

echo "DROP TABLE IF EXISTS $CH_DB.history" | curl $CH_CURL_AUTH $CH_URL --data-binary @-

cat << EOF | curl $CH_CURL_AUTH $CH_URL --data-binary @-
CREATE TABLE $CH_DB.history
(
	itemid UInt64,
	clock_ns DateTime64(9),
	value Float64
)
ENGINE = MergeTree()
PARTITION BY $CH_PARTITION(clock_ns)
PRIMARY KEY (itemid, clock_ns)
TTL clock_ns + toIntervalSecond($CH_TTL)
EOF
