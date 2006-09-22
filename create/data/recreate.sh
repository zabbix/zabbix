#echo Killing servers
#killall zabbix_server >/dev/null 2>/dev/null
#sleep 1
#killall -9 zabbix_server >/dev/null 2>/dev/null
#echo Removing log files
#rm ~zabbix/logs/node*

for i in 1 2 3; do
	echo "drop database 1_3_rights$i"|mysql -uroot
	echo "create database 1_3_rights$i"|mysql -uroot
	cat ../mysql/schema.sql|mysql -uroot 1_3_rights$i
	cat data.sql|sed -e "s/{10010}/{100100$i}/g"|mysql -uroot 1_3_rights$i
done
cat nodes.sql|mysql -uroot

for i in 1 2 3; do #node ids
	echo "update config set configid=100*configid+$i"|mysql -uroot 1_3_rights$i
	echo "update media_type set mediatypeid=100*mediatypeid+$i"|mysql -uroot 1_3_rights$i
	echo "update users set userid=100*userid+$i"|mysql -uroot 1_3_rights$i
	echo "update usrgrp set usrgrpid=100*usrgrpid+$i"|mysql -uroot 1_3_rights$i
	echo "update rights set rightid=100*rightid+$i"|mysql -uroot 1_3_rights$i
	echo "update rights set userid=100*userid+$i"|mysql -uroot 1_3_rights$i
	echo "update hosts set hostid=100*hostid+$i"|mysql -uroot 1_3_rights$i
	echo "update groups set groupid=100*groupid+$i"|mysql -uroot 1_3_rights$i
	echo "update hosts_groups set hostgroupid=100*hostgroupid+$i"|mysql -uroot 1_3_rights$i
	echo "update hosts_groups set hostid=100*hostid+$i"|mysql -uroot 1_3_rights$i
	echo "update hosts_groups set groupid=100*groupid+$i"|mysql -uroot 1_3_rights$i
	echo "update items set itemid=100*itemid+$i"|mysql -uroot 1_3_rights$i
	echo "update items set hostid=100*hostid+$i"|mysql -uroot 1_3_rights$i
	echo "update functions set functionid=100*functionid+$i"|mysql -uroot 1_3_rights$i
	echo "update functions set itemid=100*itemid+$i"|mysql -uroot 1_3_rights$i
	echo "update functions set triggerid=100*triggerid+$i"|mysql -uroot 1_3_rights$i
	echo "update triggers set triggerid=100*triggerid+$i"|mysql -uroot 1_3_rights$i
	echo "update actions set actionid=100*actionid+$i"|mysql -uroot 1_3_rights$i
	echo "update actions set userid=100*userid+$i"|mysql -uroot 1_3_rights$i
	echo "update media set mediaid=100*mediaid+$i"|mysql -uroot 1_3_rights$i
	echo "update media set userid=100*userid+$i"|mysql -uroot 1_3_rights$i
	echo "update media set mediatypeid=100*mediatypeid+$i"|mysql -uroot 1_3_rights$i
done


#for i in 1 2 3; do
#	~zabbix/distributed/bin/zabbix_server -c /etc/zabbix/1_3_rights$i.conf
#done
