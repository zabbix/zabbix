#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

cat << EOF | curl $CH_CURL_AUTH $CH_URL --data-binary @-
CREATE TABLE $CH_DB.history_text$CH_LOCAL_SUFFIX$CH_ON_CLUSTER
(
	itemid UInt64,
	clock_ns DateTime64(9),
	value String
)
ENGINE = $CH_ENGINE
PARTITION BY $CH_PARTITION(clock_ns)
PRIMARY KEY (itemid, clock_ns)
TTL clock_ns + toIntervalSecond($CH_TTL)
EOF

ch_create_distributed_table history_text
