 #!/bin/bash

DIR="$(dirname "$0")"
. "$DIR/clickhouse.sh"

curl $CH_CURL_AUTH -X POST "$CH_URL?query=INSERT%20INTO%20$CH_DB.history_uint%20FORMAT%20CSV" -T "$CH_IMPORT_DIR/history_uint_clockns.csv"
