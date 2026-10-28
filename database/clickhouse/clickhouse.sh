# CH_SCRIPT must be set by the sourcing script to the supported option set: schema or import

display_help()
{
	echo "Arguments:"
	printf "  %-16s %s\n" "-s|--server" "ClickHouse URL ($CH_URL)"
	printf "  %-16s %s\n" "-d|--db" "Database name ($CH_DB)"
	printf "  %-16s %s\n" "-u|--user" "ClickHouse username"
	printf "  %-16s %s\n" "-p|--password" "ClickHouse password"
	if [ "$CH_SCRIPT" = "schema" ]; then
		printf "  %-16s %s\n" "-t|--ttl" "Housekeeping interval in seconds ($CH_TTL)"
		printf "  %-16s %s\n" "-P|--partition" "Partitioning schema ($CH_PARTITION)"
		printf "  %-16s %s\n" "-e|--engine" "Table engine ($CH_ENGINE)"
		printf "  %-16s %s\n" "-c|--cluster" "Cluster name, creates Distributed tables over local tables on all nodes"
	fi
	if [ "$CH_SCRIPT" = "import" ]; then
		printf "  %-16s %s\n" "-i|--import-dir" "Directory with the exported CSV files ($CH_IMPORT_DIR)"
	fi
	printf "  %-16s %s\n" "--ca" "CA certificate file to verify the server certificate"
	printf "  %-16s %s\n" "--cert" "Client certificate file"
	printf "  %-16s %s\n" "--key" "Client private key file"
	printf "  %-16s %s\n" "--insecure" "Skip server certificate and host name verification"
	printf "  %-16s %s\n" "-h|--help" "Help message"

	# otel_all.sh relies on this exit code to tell help from failure
	exit 2
}

CH_URL=http://localhost:8123
CH_DB=zabbix
CH_USER=""
CH_PASSWORD=""

# default TTL time - 31 days
CH_TTL="2678400"

CH_PARTITION="toDate"

CH_ENGINE="MergeTree()"

CH_CLUSTER=""

# directory with the exported CSV files
CH_IMPORT_DIR="/tmp"

CH_CA=""
CH_CERT=""
CH_KEY=""
CH_INSECURE=""

if [ $# -eq 0 ]; then
	display_help
fi

if ! curl --version > /dev/null 2>&1; then
	echo "This script requires curl command utility"
	exit 1
fi

while [ $# -gt 0 ]; do
	case $1 in
	-s|--server)
		shift
		CH_URL=$1
		;;
	-d|--db)
		shift
		CH_DB=$1
		;;
	-u|--user)
		shift
		CH_USER=$1
		;;
	-p|--password)
		shift
		CH_PASSWORD=$1
		;;
	-t|--ttl)
		[ "$CH_SCRIPT" = "schema" ] || display_help
		shift
		CH_TTL=$1
		;;
	-P|--partition)
		[ "$CH_SCRIPT" = "schema" ] || display_help
		shift
		CH_PARTITION=$1
		;;
	-e|--engine)
		[ "$CH_SCRIPT" = "schema" ] || display_help
		shift
		CH_ENGINE=$1
		;;
	-c|--cluster)
		[ "$CH_SCRIPT" = "schema" ] || display_help
		shift
		CH_CLUSTER=$1
		;;
	-i|--import-dir)
		[ "$CH_SCRIPT" = "import" ] || display_help
		shift
		CH_IMPORT_DIR=$1
		;;
	--ca)
		shift
		CH_CA=$1
		;;
	--cert)
		shift
		CH_CERT=$1
		;;
	--key)
		shift
		CH_KEY=$1
		;;
	--insecure)
		CH_INSECURE=1
		;;
	-h|--help)
		display_help
		;;
	*)
		display_help
		;;
	esac

	shift
done

if [ -z "$CH_PARTITION" ]; then
	echo "Partitioning schema cannot be empty"
	exit 1
fi

# split URL into scheme, userinfo, host (possibly bracketed IPv6), port and the rest
CH_URL_REGEX='^([A-Za-z][A-Za-z0-9+.-]*://)?([^/?#@]*@)?(\[[^]/]*\]|[^]/?#:[]*)(:[0-9]*)?(.*)$'

if [[ $CH_URL =~ $CH_URL_REGEX ]] && [ -z "${BASH_REMATCH[4]}" ]; then
	case ${BASH_REMATCH[1]} in
		[Hh][Tt][Tt][Pp][Ss]://)
			CH_PORT=8443
			;;
		*)
			CH_PORT=8123
			;;
	esac

	CH_URL="${BASH_REMATCH[1]}${BASH_REMATCH[2]}${BASH_REMATCH[3]}:$CH_PORT${BASH_REMATCH[5]}"
fi

CH_CURL_OPTS=()
if [ -n "$CH_USER" ]; then
	CH_CURL_OPTS+=(-u "$CH_USER:$CH_PASSWORD")
fi

if [ -n "$CH_CA" ]; then
	CH_CURL_OPTS+=(--cacert "$CH_CA")
fi

if [ -n "$CH_CERT" ]; then
	CH_CURL_OPTS+=(--cert "$CH_CERT")
fi

if [ -n "$CH_KEY" ]; then
	CH_CURL_OPTS+=(--key "$CH_KEY")
fi

if [ -n "$CH_INSECURE" ]; then
	CH_CURL_OPTS+=(--insecure)
fi

# executes query read from stdin, fails on transfer or ClickHouse errors
ch_query()
{
	local out

	if ! out=$(curl -sS "${CH_CURL_OPTS[@]}" -w '\n%{http_code}' "$CH_URL" --data-binary @-); then
		return 1
	fi

	if [ "${out##*$'\n'}" != "200" ]; then
		printf "%s" "${out%$'\n'*}" >&2
		return 1
	fi

	printf "%s" "${out%$'\n'*}"
}

# with a cluster the data is stored in <table>_local on every node and <table> is a Distributed table over them
CH_ON_CLUSTER=""
CH_LOCAL_SUFFIX=""
if [ -n "$CH_CLUSTER" ]; then
	CH_ON_CLUSTER=" ON CLUSTER '$CH_CLUSTER'"
	CH_LOCAL_SUFFIX="_local"
fi

ch_create_distributed_table()
{
	if [ -z "$CH_CLUSTER" ]; then
		return
	fi

	cat << EOF | curl "${CH_CURL_OPTS[@]}" "$CH_URL" --data-binary @-
CREATE TABLE $CH_DB.$1$CH_ON_CLUSTER AS $CH_DB.$1$CH_LOCAL_SUFFIX
ENGINE = Distributed('$CH_CLUSTER', '$CH_DB', '$1$CH_LOCAL_SUFFIX', intHash64(itemid))
EOF
}

# creates Distributed table $1 sharded by $2, an existing Distributed table is skipped, while any other
# existing table is an error, as it would silently take the place of the Distributed table
ch_create_otel_distributed_table()
{
	local engine

	if [ -z "$CH_CLUSTER" ]; then
		return
	fi

	engine=$(echo "SELECT engine FROM system.tables WHERE database = '$CH_DB' AND name = '$1'" | ch_query) \
			|| return 1

	if [ "$engine" = "Distributed" ]; then
		return
	fi

	if [ -n "$engine" ]; then
		echo "Table $CH_DB.$1 already exists with $engine engine, drop it to create Distributed table" >&2
		return 1
	fi

	ch_query << EOF
CREATE TABLE IF NOT EXISTS $CH_DB.$1$CH_ON_CLUSTER AS $CH_DB.$1$CH_LOCAL_SUFFIX
ENGINE = Distributed('$CH_CLUSTER', '$CH_DB', '$1$CH_LOCAL_SUFFIX', $2)
EOF
}
