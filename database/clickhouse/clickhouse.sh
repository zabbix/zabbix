display_help()
{
	echo "Arguments:"
	echo "  -u|--url		ClickHouse URL ($CH_URL)"
	echo "  -d|--db			database name ($CH_DB)"
	echo "  -t|--ttl		housekeeping interval in seconds ($CH_TTL)"
	echo "  -p|--partition		partitioning schema ($CH_PARTITION)"
	echo "  -h|--help		help message"

	exit 0
}

CH_URL=http://localhost:8123
CH_DB=zabbix

# default TTL time - 31 days
CH_TTL="2678400"

CH_PARTITION="toDate"

if [ $# -eq 0 ]; then
	display_help
fi

if ! curl --version > /dev/null 2>&1; then
	echo "This script requires curl command utility"
	exit 0
fi

while [ $# -gt 0 ]; do
	case $1 in
	-u|--url)
		shift
		CH_URL=$1
		;;
	-d|--db)
		shift
		CH_DB=$1
		;;
	-t|--ttl)
		shift
		CH_TTL=$1
		;;
	-p|--partition)
		shift
		CH_PARTITION=$1
		;;
	-v|--help)
		display_help
		;;
	*)
		display_help
		;;
	esac

	shift
done
