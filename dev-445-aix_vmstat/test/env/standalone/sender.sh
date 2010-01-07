while true; do
	../../../sbin/zabbix_sender -z 127.0.0.1 -p 13000 -i data.sender -vv
done
