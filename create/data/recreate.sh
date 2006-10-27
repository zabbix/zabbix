db_name="node"
local_id="1"

echo Killing servers
killall zabbix_server >/dev/null 2>/dev/null
sleep 1
killall -9 zabbix_server >/dev/null 2>/dev/null
echo Removing log files
rm ~zabbix/logs/node*

echo Generate Database schems
cd ../schema
rm mysql.sql
./generate_schemas.sh
cd -
	
echo Creating MySQL databases
for i in 1 2 3 4 5 6 7; do
	echo "drop database $db_name$i"|mysql -uroot
	echo "create database $db_name$i"|mysql -uroot
	cat ../schema/mysql.sql|mysql -uroot $db_name$i
	cat data.sql|mysql -uroot $db_name$i
#	cat data.sql|sed -e "s/{10010}/{100100$i}/g"|mysql -uroot $db_name$i
done
cat nodes.sql|mysql -uroot

echo Updating MySQL databases
for i in 1 2 3 4 5 6 7; do
	echo "update config set configid=configid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update media_type set mediatypeid=mediatypeid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update users set userid=userid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update usrgrp set usrgrpid=usrgrpid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update rights set rightid=rightid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update rights set groupid=groupid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update hosts set hostid=hostid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update groups set groupid=groupid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update hosts_groups set hostgroupid=hostgroupid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update hosts_groups set hostid=hostid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update hosts_groups set groupid=groupid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update items set itemid=itemid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update items set hostid=hostid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update functions set functionid=functionid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update functions set itemid=itemid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update functions set triggerid=triggerid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update triggers set triggerid=triggerid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update actions set actionid=actionid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update actions set userid=userid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update media set mediaid=mediaid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update media set userid=userid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update media set mediatypeid=mediatypeid+0000100000000000000*$i"|mysql -uroot $db_name$i
	echo "update images set imageid=imageid+0000100000000000000*$i"|mysql -uroot $db_name$i
done

#echo Importing MySQL databases
#for i in 2 3 4 5 6 7; do
#	mysqldump --add-drop-table=false --add-locks=FALSE --no-create-db=FALSE --create-options=FALSE --no-create-info=TRUE --ignore-table="$db_name$i.help_items" --ignore-table="$db_name$i.nodes" -uroot $db_name$i | mysql -f -uroot $db_name$local_id
#done

#echo Making MySQL server
#cd ../..
#./configure --enable-agent --enable-server --with-mysql --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
#make clean >/dev/null
#make install >/dev/null
#cd - >/dev/null
echo Staring servers
for i in 1 2 3 4 5 6 7; do
	../../bin/zabbix_server -c /etc/zabbix/$db_name$i.conf >/dev/null
done

