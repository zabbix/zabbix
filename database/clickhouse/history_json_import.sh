#!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

curl $CH_CURL_AUTH -X POST "$CH_URL?query=INSERT%20INTO%20$CH_DB.history_json%20FORMAT%20CSV&input_format_try_infer_dates=0&input_format_try_infer_datetimes=0" -T "$CH_IMPORT_DIR/history_json_clockns.csv"
