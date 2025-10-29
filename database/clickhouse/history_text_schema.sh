#!/bin/bash

. ./clickhouse.sh

echo "DROP TABLE IF EXISTS $CH_DB.history_text" | curl $CH_URL --data-binary @-

cat << EOF | curl $CH_URL --data-binary @-
CREATE TABLE IF NOT EXISTS $CH_DB.history_text
(
	itemid UInt64,
	timestamp DateTime64(9),
	value String
)
ENGINE = MergeTree()
PARTITION BY toDate(timestamp)
PRIMARY KEY (itemid, timestamp)
TTL timestamp + toIntervalSecond($CH_TTL)
EOF


