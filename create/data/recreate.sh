echo Killing servers
killall zabbix_server >/dev/null 2>/dev/null
sleep 1
killall -9 zabbix_server >/dev/null 2>/dev/null
echo Removing log files
rm ~zabbix/logs/node*

echo Creating MySQL databases
for i in 1 2 3 4 5 6 7; do
	echo "drop database node$i"|mysql -uroot
	echo "create database node$i"|mysql -uroot
	cat ../schema/mysql.sql|mysql -uroot node$i
	cat data.sql|mysql -uroot node$i
#	cat data.sql|sed -e "s/{10010}/{100100$i}/g"|mysql -uroot node$i
done
cat nodes.sql|mysql -uroot

for i in 1 2 3 4 5 6 7; do
	echo "update config set configid=100*configid+$i"|mysql -uroot node$i
	echo "update media_type set mediatypeid=100*mediatypeid+$i"|mysql -uroot node$i
	echo "update users set userid=100*userid+$i"|mysql -uroot node$i
	echo "update usrgrp set usrgrpid=100*usrgrpid+$i"|mysql -uroot node$i
	echo "update rights set rightid=100*rightid+$i"|mysql -uroot node$i
	echo "update rights set userid=100*userid+$i"|mysql -uroot node$i
	echo "update hosts set hostid=100*hostid+$i"|mysql -uroot node$i
	echo "update groups set groupid=100*groupid+$i"|mysql -uroot node$i
	echo "update hosts_groups set hostgroupid=100*hostgroupid+$i"|mysql -uroot node$i
	echo "update hosts_groups set hostid=100*hostid+$i"|mysql -uroot node$i
	echo "update hosts_groups set groupid=100*groupid+$i"|mysql -uroot node$i
	echo "update items set itemid=100*itemid+$i"|mysql -uroot node$i
	echo "update items set hostid=100*hostid+$i"|mysql -uroot node$i
	echo "update functions set functionid=100*functionid+$i"|mysql -uroot node$i
	echo "update functions set itemid=100*itemid+$i"|mysql -uroot node$i
	echo "update functions set triggerid=100*triggerid+$i"|mysql -uroot node$i
	echo "update triggers set triggerid=100*triggerid+$i"|mysql -uroot node$i
	echo "update actions set actionid=100*actionid+$i"|mysql -uroot node$i
	echo "update actions set userid=100*userid+$i"|mysql -uroot node$i
	echo "update media set mediaid=100*mediaid+$i"|mysql -uroot node$i
	echo "update media set userid=100*userid+$i"|mysql -uroot node$i
	echo "update media set mediatypeid=100*mediatypeid+$i"|mysql -uroot node$i
	echo "update images set imageid=100*imageid+$i"|mysql -uroot node$i
done

echo Making MySQL server
cd ../..
#./configure --enable-agent --enable-server --with-mysql --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
#make clean >/dev/null
make install >/dev/null
cd - >/dev/null
echo Staring servers
for i in 1 2 3 4 5 6 7; do
	../../bin/zabbix_server -c /etc/zabbix/node$i.conf >/dev/null
done
