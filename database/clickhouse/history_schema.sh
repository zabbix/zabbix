#!/bin/bash

. ./clickhouse.sh

echo "DROP TABLE IF EXISTS $CH_DB.history" | curl $CH_URL --data-binary @-

cat << EOF | curl $CH_URL --data-binary @-
CREATE TABLE $CH_DB.history
(
	itemid UInt64,
	timestamp DateTime64(9),
	value Float64
)
ENGINE = MergeTree()
PARTITION BY toDate(timestamp)
PRIMARY KEY (itemid, timestamp)
TTL timestamp + toIntervalSecond($CH_TTL)
EOF


