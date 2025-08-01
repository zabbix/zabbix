#!/bin/bash

. clickhouse.sh


echo "DROP TABLE IF EXISTS $CH_DB.history_uint" | curl $CH_URL --data-binary @-

cat << EOF | curl $CH_URL --data-binary @-
CREATE TABLE $CH_DB.history_uint
(
 	itemid UInt64,
 	value UInt64,
 	timestamp DateTime64(9)
)
ENGINE = MergeTree()
PRIMARY KEY (itemid, timestamp)
TTL toDateTime(timestamp) + INTERVAL $CH_TTL
EOF


