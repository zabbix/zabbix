#!/bin/bash

. ./clickhouse.sh

echo "DROP TABLE IF EXISTS $CH_DB.history_text" | curl $CH_URL --data-binary @-

cat << EOF | curl $CH_URL --data-binary @-
CREATE TABLE $CH_DB.history_text
(
	itemid UInt64,
	clock_ns DateTime64(9),
	value String
)
ENGINE = MergeTree()
PARTITION BY $CH_PARTITION(clock_ns)
PRIMARY KEY (itemid, clock_ns)
TTL clock_ns + toIntervalSecond($CH_TTL)
EOF
