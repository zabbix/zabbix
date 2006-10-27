for cmd
do
  case "$cmd" in
    osmiy )	osmiy=1;;
  esac
done

if [ $osmiy ]
then
	echo "Run OSMIY script";
	
	db_name="osmiy"
	local_id="1"
else
	echo "Run ALEX script";
	
	alex=1;
	db_name="node"
	local_id="1"
fi
	
if [ $alex ]
then
	
	echo Killing servers
	killall zabbix_server >/dev/null 2>/dev/null
	sleep 1
	killall -9 zabbix_server >/dev/null 2>/dev/null
	echo Removing log files
	rm ~zabbix/logs/node*

fi

echo Generate Database schems
cd ../schema
rm mysql.sql
./generate_schemas.sh
cd -
	
echo Creating MySQL databases
for i in 1 2 3 4 5 6 7; do
	echo "- $i -"
	echo "drop database $db_name$i"|mysql -uroot
	echo "create database $db_name$i"|mysql -uroot
	cat ../schema/mysql.sql|mysql -uroot $db_name$i
	cat data.sql|mysql -uroot $db_name$i
#	cat data.sql|sed -e "s/{10010}/{100100$i}/g"|mysql -uroot $db_name$i
done

if [ $alex ]
then
	cat nodes.sql|mysql -uroot
fi

echo Updating MySQL databases
for i in 1 2 3 4 5 6 7; do
	echo "- $i -"
	
	if [ $osmiy ]
	then
		if [ $i = '1' ] 
		then 
			l1=1 
		else 
			l1=0
		fi
		if [ $i = '2' ]
		then
			l2=1
		else
			l2=0
		fi
		if [ $i = '3' ]
		then
			l3=1
		else
			l3=0
		fi
		if [ $i = '4' ]
		then
			l4=1
		else
			l4=0
		fi
		if [ $i = '5' ]
		then
			l5=1
		else
			l5=0
		fi
		if [ $i = '6' ]
		then
			l6=1
		else
			l6=0
		fi
		if [ $i = '7' ]
		then
			l7=1
		else
			l7=0
		fi

		echo "delete from nodes"|mysql -uroot $db_name$i
		echo "insert into nodes values (7, 'Cologne',2, '127.0.0.1', 15057, 30, 365, 0, 0, $l7, 5)"|mysql -uroot $db_name$i
		echo "insert into nodes values (6, 'Berlin', 2, '127.0.0.1', 15056, 30, 365, 0, 0, $l6, 5)"|mysql -uroot $db_name$i
		echo "insert into nodes values (5, 'Germany',2, '127.0.0.1', 15055, 30, 365, 0, 0, $l5, 4)"|mysql -uroot $db_name$i
		echo "insert into nodes values (4, 'Zabbix', 2, '127.0.0.1', 15054, 30, 365, 0, 0, $l4, 0)"|mysql -uroot $db_name$i
		echo "insert into nodes values (3, 'Latvia', 2, '127.0.0.1', 15053, 30, 365, 0, 0, $l3, 4)"|mysql -uroot $db_name$i
		echo "insert into nodes values (2, 'Riga',   2, '127.0.0.1', 15052, 30, 365, 0, 0, $l2, 3)"|mysql -uroot $db_name$i
		echo "insert into nodes values (1, 'Dpils',  2, '127.0.0.1', 15051, 30, 365, 0, 0, $l1, 3)"|mysql -uroot $db_name$i
	fi

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

if [ $osmiy ]
then

	echo Importing MySQL databases
	for i in 2 3 4 5 6 7; do
		echo "- $i -"
		mysqldump --add-drop-table=false --add-locks=FALSE --no-create-db=FALSE --create-options=FALSE --no-create-info=TRUE --ignore-table="$db_name$i.help_items" --ignore-table="$db_name$i.nodes" -uroot $db_name$i | mysql -f -uroot $db_name$local_id
	done

fi

if [ $alex ]
then

#	 echo Making MySQL server
#	 cd ../..
#	 ./configure --enable-agent --enable-server --with-mysql --with-net-snmp --prefix=`pwd` 2>>WARNINGS >/dev/null
#	 make clean >/dev/null
#	 make install >/dev/null
#	 cd - >/dev/null
	echo Staring servers
	for i in 1 2 3 4 5 6 7; do
		echo "- $i -"
		../../bin/zabbix_server -c /etc/zabbix/$db_name$i.conf >/dev/null
	done

fi

