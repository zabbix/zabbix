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
	echo "update config set configid=configid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update media_type set mediatypeid=mediatypeid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update users set userid=userid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update usrgrp set usrgrpid=usrgrpid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update rights set rightid=rightid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update rights set userid=userid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update hosts set hostid=hostid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update groups set groupid=groupid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update hosts_groups set hostgroupid=hostgroupid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update hosts_groups set hostid=hostid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update hosts_groups set groupid=groupid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update items set itemid=itemid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update items set hostid=hostid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update functions set functionid=functionid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update functions set itemid=itemid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update functions set triggerid=triggerid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update triggers set triggerid=triggerid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update actions set actionid=actionid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update actions set userid=userid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update media set mediaid=mediaid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update media set userid=userid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update media set mediatypeid=mediatypeid+0000100000000000000*$i"|mysql -uroot node$i
	echo "update images set imageid=imageid+0000100000000000000*$i"|mysql -uroot node$i
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
