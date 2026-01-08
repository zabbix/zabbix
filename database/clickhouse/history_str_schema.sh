#!/bin/bash

. ./clickhouse.sh

echo "DROP TABLE IF EXISTS $CH_DB.history_str" | curl $CH_URL --data-binary @-

cat << EOF | curl $CH_URL --data-binary @-
CREATE TABLE $CH_DB.history_str
(
	itemid UInt64,
	timestamp DateTime64(9),
	value String
)
ENGINE = MergeTree()
PARTITION BY $CH_PARTITION(timestamp)
PRIMARY KEY (itemid, timestamp)
TTL timestamp + toIntervalSecond($CH_TTL)
EOF


