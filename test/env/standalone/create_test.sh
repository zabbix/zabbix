#echo "create database trunk"|mysql -uroot
#cat data.sql|mysql -uroot trunk

HOME=/home/zabbix/ttt
PROXY_PORT=11000
AGENT_PORT=12000
SERVER_PORT=13000
TMP_DIR=\\/tmp
PROXY_NUM=2

echo Killing all processes
killall zabbix_agentd
killall zabbix_proxy
killall zabbix_server

echo Removing all temp files
rm -f $TMP_DIR/*.log
rm -f $TMP_DIR/*.log.old
rm -f $TMP_DIR/*.pid

echo Recreating database
echo "drop database trunk"|mysql -uroot
echo "create database trunk"|mysql -uroot
cat data.sql|mysql -uroot trunk

for ((i=1; i <= $PROXY_NUM ; i++)); do
	echo Processing proxy$i
	echo "drop database proxy$i"|mysql -uroot
	echo "create database proxy$i"|mysql -uroot
	$HOME/create/schema/gen.pl mysql|mysql -uroot proxy$i

	PORT=`echo "$PROXY_PORT+$i"|bc`
	cat conf/template_proxy.conf|sed "s/{SERVER_PORT}/$SERVER_PORT/g"|sed "s/{NUM}/$i/g"|sed "s/{PORT}/$PORT/g"|sed "s/{TMP_DIR}/$TMP_DIR/g" >conf/proxy$i.conf

	$HOME/sbin/zabbix_proxy -c $HOME/test/env/standalone/conf/proxy$i.conf
done

for ((i=1; i <= 20 ; i++)); do
	echo Processing agent$i
	PORT=`echo "$AGENT_PORT+$i"|bc`
	cat conf/template_agent.conf|sed "s/{SERVER_PORT}/$SERVER_PORT/g"|sed "s/{NUM}/$i/g"|sed "s/{PORT}/$PORT/g"|sed "s/{TMP_DIR}/$TMP_DIR/g" >conf/agent$i.conf

	echo "update hosts set port=$PORT where host='u$i'"|mysql -uroot trunk

	$HOME/sbin/zabbix_agentd -c $HOME/test/env/standalone/conf/agent$i.conf
done

echo Processing server
cat conf/template_server.conf|sed "s/{PORT}/$SERVER_PORT/g"|sed "s/{TMP_DIR}/$TMP_DIR/g" >conf/server.conf

$HOME/sbin/zabbix_server -c $HOME/test/env/standalone/conf/server.conf
