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
	fi
	if [ "$CH_SCRIPT" = "import" ]; then
		printf "  %-16s %s\n" "-i|--import-dir" "Directory with the exported CSV files ($CH_IMPORT_DIR)"
	fi
	printf "  %-16s %s\n" "--ca" "CA certificate file to verify the server certificate"
	printf "  %-16s %s\n" "--cert" "Client certificate file"
	printf "  %-16s %s\n" "--key" "Client private key file"
	printf "  %-16s %s\n" "--insecure" "Skip server certificate and host name verification"
	printf "  %-16s %s\n" "-h|--help" "Help message"

	exit 1
}

CH_URL=http://localhost:8123
CH_DB=zabbix
CH_USER=""
CH_PASSWORD=""

# default TTL time - 31 days
CH_TTL="2678400"

CH_PARTITION="toDate"

CH_ENGINE="MergeTree()"

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
	CH_PORT=8123
	if [ "${BASH_REMATCH[1],,}" = "https://" ]; then
		CH_PORT=8443
	fi

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
