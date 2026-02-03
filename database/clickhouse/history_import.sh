#!/bin/bash

. clickhouse.sh

cat - | echo curl "$CH_URL?query=INSERT%20INTO%20$CH_DB.history%20FORMAT%20CSV" --data-binary @-
