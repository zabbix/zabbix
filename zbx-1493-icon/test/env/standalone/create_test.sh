#echo "create database trunk"|mysql -uroot
#cat data.sql|mysql -uroot trunk

DEST=/home/alex/test
TMP_DIR=\\/home\\/alex\\/test\\/tmp
PROXY_PORT=11000
AGENT_PORT=12000
SERVER_PORT=13000
PROXY_NUM=2
DEBUG=3

echo Killing all processes
killall zabbix_agentd 2>/dev/null
killall zabbix_proxy 2>/dev/null
killall zabbix_server 2>/dev/null
sleep 3
killall -9 zabbix_agentd 2>/dev/null
killall -9 zabbix_proxy 2>/dev/null
killall -9 zabbix_server 2>/dev/null

echo Removing all temp files
mkdir $DEST 2>/dev/null
rm -f $DEST/*.log
mkdir $DEST/conf 2>/dev/null
mkdir $DEST/sbin 2>/dev/null
mkdir $DEST/tmp 2>/dev/null

echo Making binaries
cd ../../..
./go
cd -
cp ../../../sbin/* $DEST/sbin

echo Recreating database
#echo "drop database trunk"|mysql -uroot
#echo "create database trunk"|mysql -uroot
#cat data.sql|mysql -uroot trunk

for ((i=1; i <= $PROXY_NUM ; i++)); do
	echo Processing proxy$i
	echo "drop database proxy$i"|mysql -uroot
	echo "create database proxy$i"|mysql -uroot
	../../../create/schema/gen.pl mysql|mysql -uroot proxy$i

	PORT=`echo "$PROXY_PORT+$i"|bc`
	cat conf/template_proxy.conf|\
		sed "s/{SERVER_PORT}/$SERVER_PORT/g"|\
		sed "s/{NUM}/$i/g"|sed "s/{PORT}/$PORT/g"|\
		sed "s/{TMP_DIR}/$TMP_DIR/g"|\
		sed "s/{DebugLevel}/$DEBUG/g"\
		>$DEST/conf/proxy$i.conf

	$DEST/sbin/zabbix_proxy -c $DEST/conf/proxy$i.conf
done

for ((i=1; i <= 20 ; i++)); do
	echo Processing agent$i
	PORT=`echo "$AGENT_PORT+$i"|bc`
	cat conf/template_agent.conf|\
		sed "s/{SERVER_PORT}/$SERVER_PORT/g"|\
		sed "s/{NUM}/$i/g"|sed "s/{PORT}/$PORT/g"|\
		sed "s/{TMP_DIR}/$TMP_DIR/g"|\
		sed "s/{DebugLevel}/$DEBUG/g"\
		>$DEST/conf/agent$i.conf

	echo "update hosts set port=$PORT where host='u$i'"|mysql -uroot trunk

	$DEST/sbin/zabbix_agentd -c $DEST/conf/agent$i.conf
done

echo Processing server
cat conf/template_server.conf|\
	sed "s/{PORT}/$SERVER_PORT/g"|\
	sed "s/{TMP_DIR}/$TMP_DIR/g"|\
	sed "s/{DebugLevel}/$DEBUG/g"\
	>$DEST/conf/server.conf

$DEST/sbin/zabbix_server -c $DEST/conf/server.conf
