display_help()
{
	echo "Arguments:"
	echo "  -u|--url	ClickHouse URL ($CH_URL)"
	echo "  -d|--db		database name ($CH_DB)"
	echo "  -t|--ttl	housekeeping interval ($CH_TTL)"
	echo "  -h|--help	help message"

	exit 0
}

CH_URL=http://localhost:8123
CH_DB=zabbix
CH_TTL="1 MONTH"

if [ $# -eq 0 ]; then
	display_help
fi

if ! curl --version > /dev/null 2>&1; then
	echo "This script requries curl command utility"
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
	-v|--help)
		display_help
		;;
	*)
		display_help
		;;
	esac
	
	shift
done

