display_help()
{
	echo "Arguments:"
	printf "  %-16s %s\n" "-s|--server" "ClickHouse URL ($CH_URL)"
	printf "  %-16s %s\n" "-d|--db" "Database name ($CH_DB)"
	printf "  %-16s %s\n" "-u|--user" "ClickHouse username"
	printf "  %-16s %s\n" "-p|--password" "ClickHouse password"
	printf "  %-16s %s\n" "-t|--ttl" "Housekeeping interval in seconds ($CH_TTL)"
	printf "  %-16s %s\n" "-P|--partition" "Partitioning schema ($CH_PARTITION)"
	printf "  %-16s %s\n" "-i|--import-dir" "Directory with the exported CSV files ($CH_IMPORT_DIR)"
	printf "  %-16s %s\n" "-h|--help" "Help message"

	exit 0
}

CH_URL=http://localhost:8123
CH_DB=zabbix
CH_USER=""
CH_PASSWORD=""

# default TTL time - 31 days
CH_TTL="2678400"

CH_PARTITION="toDate"

# directory with the exported CSV files
CH_IMPORT_DIR="/tmp"

if [ $# -eq 0 ]; then
	display_help
fi

if ! curl --version > /dev/null 2>&1; then
	echo "This script requires curl command utility"
	exit 0
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
		shift
		CH_TTL=$1
		;;
	-P|--partition)
		shift
		CH_PARTITION=$1
		;;
	-i|--import-dir)
		shift
		CH_IMPORT_DIR=$1
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

CH_CURL_AUTH=""
if [ -n "$CH_USER" ]; then
	CH_CURL_AUTH="-u $CH_USER:$CH_PASSWORD"
fi
