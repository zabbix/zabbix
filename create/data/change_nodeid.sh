dbname="zabbix"
nodeid=$1

echo "update config set configid=configid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update media_type set mediatypeid=mediatypeid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update users set userid=userid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update usrgrp set usrgrpid=usrgrpid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update rights set rightid=rightid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update rights set groupid=groupid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update hosts set hostid=hostid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update groups set groupid=groupid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update hosts_groups set hostgroupid=hostgroupid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update hosts_groups set hostid=hostid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update hosts_groups set groupid=groupid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update items set itemid=itemid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update items set hostid=hostid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update functions set functionid=functionid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update functions set itemid=itemid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update functions set triggerid=triggerid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update triggers set triggerid=triggerid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update actions set actionid=actionid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update actions set userid=userid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update media set mediaid=mediaid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update media set userid=userid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update media set mediatypeid=mediatypeid+0000100000000000000*$nodeid"|mysql -uroot $dbname
echo "update images set imageid=imageid+0000100000000000000*$nodeid"|mysql -uroot $dbname

echo "select concat(triggerid,'_',expression) from triggers"|mysql -uroot $dbname >tmp
for i in `cat tmp`; do
	expression=`echo $i|cut -f2 -d"_"`
	recid=`echo $i|cut -f1 -d"_"`
	id=`echo $i|cut -f2 -d"{"|cut -f1 -d "}"`
	newid=`echo "$id+0000100000000000000"|bc`
	newexpression=`echo $expression|sed "s/{$id}/{$newid}/g"`
	echo "update triggers set expression='$newexpression' where triggerid=$recid"|mysql -uroot $dbname
done
rm tmp
