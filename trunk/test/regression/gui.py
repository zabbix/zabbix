#!/usr/bin/python

### ZABBIX
### Copyright (C) 2000-2005 SIA Zabbix
###
### This program is free software; you can redistribute it and/or modify
### it under the terms of the GNU General Public License as published by
### the Free Software Foundation; either version 2 of the License, or
### (at your option) any later version.
###
### This program is distributed in the hope that it will be useful,
### but WITHOUT ANY WARRANTY; without even the implied warranty of
### MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
### GNU General Public License for more details.
###
### You should have received a copy of the GNU General Public License
### along with this program; if not, write to the Free Software
### Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

import unittest, re, httplib, sys, time, os, MySQLdb

connection = MySQLdb.connect(host="localhost",
            user="root", passwd="", db="test" )   

def TestGUI(name, page, gets, expect):
	conn = httplib.HTTPConnection("localhost")
	print name
	url = '/~test/'+page+'?'+gets
#	print url
	conn.request("GET", url)
	r1 = conn.getresponse()
	###print page, '\t\t', r1.status, r1.reason
	data = r1.read()
	p = re.compile('.*'+expect+'.*', re.DOTALL)
	m = p.match(data)
	if not m:
		print '\tGUI: NOT OK'
###	else:
###		print '\tGUI: OK'
	p = re.compile('.*Undefined variable.*', re.DOTALL)
	m = p.match(data)
	if m:
		print '\tWARNING: Undefined variable'
	conn.close()

def InitDB():
###	os.system('rm -rf /home/test/zabbix')
###	os.system('cd /home/test; . ./env')
###	print "Init DB"
	os.system('echo "drop database test"|mysql -uroot')
	os.system('echo "create database test"|mysql -uroot')
	os.system('cat /home/test/zabbix/create/mysql/schema.sql|mysql -uroot test')
	os.system('cat /home/test/zabbix/create/data/data.sql|mysql -uroot test')
	os.system('echo "update rights set userid=2;"|mysql -uroot test')

def TestDBCount(table, condition, num):
	cursor = connection.cursor()
	sql = "SELECT count(*) FROM "+table+" where "+condition
	cursor.execute(sql)
#	print sql
	row = cursor.fetchone()

	if row[0]!=num:
		print '\tDB: NOT OK'
###	else:
###		print '\tDB: OK'

def DBGetID(table, condition, column):
	cursor = connection.cursor()
	sql="SELECT " + column + " FROM "+table+" where "+condition
	cursor.execute(sql)
#	print sql
	row = cursor.fetchone()

	return row[0]

def header(msg):
	print msg.ljust(80,"-")

def GUI_Login():
	header("GUI_Login")

	TestGUI('Logging in', "index.php", "name=Admin&register=Enter&password=", "disconnect")
	TestDBCount("sessions","1=1", 1)
	TestGUI('Logging out', "index.php", "reconnect=1", "Login name")
	TestDBCount("sessions","1=1", 0)

def GUI_Config_General_Housekeeper():
	header("GUI_Config_General_Mediatype")

	TestGUI('General->Housekeeper', "config.php", "alert_history=33&alarm_history=44&refresh_unsupported=123&register=update", "Configuration updated")
	TestDBCount("config","alert_history=33 and alarm_history=44 and refresh_unsupported=123", 1)

def GUI_Config_General_Mediatype_Email():
	header("GUI_Config_General_Mediatype_Email")

	TestGUI('General->Media type->Add (email)', "config.php", "config=1&description=Zzz&type=0&exec_path=&smtp_server=localhost&smtp_helo=localhost&smtp_email=zabbix%40localhost&register=add", "Added new media type")
	TestDBCount("media_type","description='Zzz' and type=0", 1)
	mediatypeid = DBGetID("media_type","description='Zzz'", "mediatypeid")

	TestGUI('General->Media type->Update (email)', "config.php", "mediatypeid="+str(mediatypeid)+"&config=1&description=Zzz2&type=0&exec_path=&smtp_server=localhost&smtp_helo=localhost&smtp_email=zabbix%40localhost&register=update+media", "Media type updated")
	TestDBCount("media_type","description='Zzz2' and mediatypeid="+str(mediatypeid), 1)

	TestGUI('General->Media type->Delete (email)', "config.php", "mediatypeid="+str(mediatypeid)+"&config=1&description=Zzz2&type=0&exec_path=&smtp_server=localhost&smtp_helo=localhost&smtp_email=zabbix%40localhost&register=delete", "Media type deleted")
	TestDBCount("media_type","description='Zzz2'", 0)

def GUI_Config_General_Mediatype_Script():
	header("GUI_Config_General_Mediatype_Script")

	TestGUI('General->Media type->Add (script)', "config.php", "config=1&description=SMS&type=1&smtp_server=localhost&smtp_helo=localhost&smtp_email=zabbix%40localhost&exec_path=sms.pl&register=add", "Added new media type")
	TestDBCount("media_type","description='SMS' and type=1", 1)
	mediatypeid = DBGetID("media_type","description='SMS'", "mediatypeid")

	TestGUI('General->Media type->Update (script)', "config.php", "mediatypeid="+str(mediatypeid)+"&config=1&description=SMS2&type=1&smtp_server=localhost&smtp_helo=localhost&smtp_email=zabbix%40localhost&exec_path=sms2.pl&register=update+media", "Media type updated")
	TestDBCount("media_type","description='SMS2' and exec_path='sms2.pl' and type=1 and mediatypeid="+str(mediatypeid), 1)

	TestGUI('General->Media type->Delete (script)', "config.php", "mediatypeid="+str(mediatypeid)+"&config=1&register=delete", "Media type deleted")
	TestDBCount("media_type","description='Zzz2'", 0)

def GUI_Config_General_Autoregistration():
	header("GUI_Config_Autoregistration")

	TestGUI('Hosts->Add', "hosts.php", "host=regression&newgroup=&useip=on&ip=127.0.0.1&port=10050&status=0&host_templateid=0&register=add#form", "Host added")
	TestDBCount("hosts","host='regression'", 1)
	hostid = DBGetID("hosts","host='regression'", "hostid")

	TestGUI('General->Autoregistration->Add', "config.php", "config=4&pattern=*&priority=10&hostid="+str(hostid)+"&register=add+autoregistration", "Autoregistration added")
	TestDBCount("autoreg","priority=10 and pattern='*' and hostid="+str(hostid), 1)
	id = DBGetID("autoreg","priority=10 and pattern='*' and hostid="+str(hostid), "id")

	TestGUI('General->Autoregistration->Update', "config.php", "config=4&id=1&pattern=***&priority=12&hostid="+str(hostid)+"&register=update+autoregistration", "Autoregistration updated")
	TestDBCount("autoreg","priority=12 and pattern='***' and hostid="+str(hostid), 1)

	TestGUI('General->Autoregistration->Delete', "config.php", "config=4&id="+str(id)+"&register=delete+autoregistration", "Autoregistration deleted")
	TestDBCount("autoreg","id="+str(id), 0)

	TestGUI('Hosts->Delete', "hosts.php", "register=delete&hostid="+str(hostid), "Host deleted")
	TestDBCount("hosts","status=4 and hostid="+str(hostid), 1)

def GUI_Config_General_Mediatype():
	header("GUI_Config_General_Mediatype")

	GUI_Config_General_Mediatype_Email()
	GUI_Config_General_Mediatype_Script()

def GUI_Config_Users():
	header("GUI_Config_Users")

	TestGUI('Users->Add', "users.php", "config=0&alias=Regression&name=Name&surname=Surname&password1=password&password2=password&lang=en_gb&autologout=123&url=zabbix&refresh=34&register=add", "User added")
	TestDBCount("users","alias='Regression' and name='Name' and surname='Surname' and passwd='5f4dcc3b5aa765d61d8327deb882cf99' and url='zabbix' and autologout=123 and lang='en_gb' and refresh=34", 1)
	id = DBGetID("users","alias='Regression'", "userid")

	TestGUI('Users->Update', "users.php", "userid="+str(id)+"&right=Configuration+of+Zabbix&permission=R&id=0&config=0&alias=Regression2&name=Name2&surname=Surname2&password1=password2&password2=password2&lang=fr_fr&autologout=321&url=zabbix2&refresh=43&register=update", "User updated")
	TestDBCount("users","alias='Regression2' and name='Name2' and surname='Surname2' and passwd='6cb75f652a9b52798eb6cf2201057c73' and url='zabbix2' and autologout=321 and lang='fr_fr' and refresh=43 and userid="+str(id), 1)

	TestGUI('Users->Delete', "users.php", "userid="+str(id)+"&right=Configuration+of+Zabbix&permission=R&id=0&config=0&alias=Regression2&name=Name2&surname=Surname2&password1=&password2=&lang=fr_fr&autologout=321&url=zabbix2&refresh=43&register=delete", "User deleted")
	TestDBCount("users","userid="+str(id), 0)

def GUI_Config_Media():
	header("GUI_Config_Media")

	TestGUI('Users->Add', "users.php", "config=0&alias=Regression&name=Name&surname=Surname&password1=password&password2=password&lang=en_gb&autologout=123&url=zabbix&refresh=34&register=add", "User added")
	TestDBCount("users","alias='Regression' and name='Name' and surname='Surname' and passwd='5f4dcc3b5aa765d61d8327deb882cf99' and url='zabbix' and autologout=123 and lang='en_gb' and refresh=34", 1)
	userid = DBGetID("users","alias='Regression'", "userid")

	TestGUI('Media->Add', "media.php", "userid="+str(userid)+"&mediatypeid=1&sendto=alex%40zabbix.com&period=1-7%2C00%3A00-23%3A59&0=0&1=1&2=2&3=3&4=4&5=5&active=0&register=add", "Media added")
	TestDBCount("media","userid="+str(userid)+" and severity=63 and mediatypeid=1 and sendto='alex@zabbix.com' and active=0 and period='1-7,00:00-23:59'", 1)
	mediaid = DBGetID("media","userid="+str(userid)+" and severity=63 and mediatypeid=1 and sendto='alex@zabbix.com' and active=0 and period='1-7,00:00-23:59'", "mediaid")

	TestGUI('Media->Update', "media.php", "userid="+str(userid)+"&mediaid="+str(mediaid)+"&mediatypeid=1&sendto=test%40zabbix.com&period=1-5%2C10%3A00-22%3A00&4=4&5=5&active=1&register=update", "Media updated")
	TestDBCount("media","mediaid="+str(mediaid)+" and userid="+str(userid)+" and severity=48 and mediatypeid=1 and sendto='test@zabbix.com' and active=1 and period='1-5,10:00-22:00'", 1)

	TestGUI('Media->Delete', "media.php", "userid="+str(userid)+"&mediaid="+str(mediaid)+"&mediatypeid=1&sendto=test%40zabbix.com&period=1-5%2C10%3A00-22%3A00&4=4&5=5&active=1&register=delete", "Media deleted")
	TestDBCount("media","mediaid="+str(mediaid),0)

def GUI_Config_Hosts():
	header("GUI_Config_Hosts")

	TestGUI('Hosts->Add', "hosts.php", "host=regression&newgroup=&useip=on&ip=127.0.0.1&port=10050&status=0&host_templateid=0&register=add#form", "Host added")
	TestDBCount("hosts","host='regression'", 1)
	hostid = DBGetID("hosts","host='regression'", "hostid")

	TestGUI('Hosts->Add (duplicate)', "hosts.php", "host=regression&newgroup=&useip=on&ip=127.0.0.1&port=10050&status=0&host_templateid=0&register=add#form", "already exists")
	TestDBCount("hosts","host='regression' and useip=1 and port=10050", 1)

	TestGUI('Hosts->Delete', "hosts.php", "register=delete&hostid="+str(hostid), "Host deleted")
	TestDBCount("hosts","status=4 and hostid="+str(hostid), 1)

def GUI_Config_Hosts_Groups():
	header("GUI_Config_Hosts_Groups")

	TestGUI('Hosts->Host groups->Add', "hosts.php", "name=Regression&register=add+group", "Group added")
	TestDBCount("groups","name='Regression'", 1)
	id = DBGetID("groups","name='regression'", "groupid")

	TestGUI('Hosts->Host groups->Add (duplicate)', "hosts.php", "name=Regression&register=add+group", "already exists")
	TestDBCount("groups","name='Regression'", 1)

	TestGUI('Hosts->Host groups->Delete', "hosts.php", "groupid="+str(id)+"&name=Regression&register=delete+group", "Group deleted")
	TestDBCount("groups","name='Regression'", 0)

def GUI_Config_Hosts_Template_Linkage():
	header("GUI_Config_Hosts_Template_Linkage")

	TestGUI('Hosts->Add', "hosts.php", "host=regression3&newgroup=&useip=on&ip=127.0.0.1&port=10050&status=0&host_templateid=0&register=add#form", "Host added")
	TestDBCount("hosts","host='regression3'", 1)
	hostid = DBGetID("hosts","host='regression3'", "hostid")

	TestGUI('Hosts->Template linkage->Add', "hosts.php", "config=2&hostid="+str(hostid)+"&templateid=10001&items_add=on&items_update=on&items_delete=on&triggers_add=on&triggers_update=on&triggers_delete=on&actions_add=on&actions_update=on&actions_delete=on&graphs_add=on&graphs_update=on&graphs_delete=on&screens_add=on&screens_update=on&screens_delete=on&register=add+linkage", "Template linkage added")
	TestDBCount("hosts_templates","items=7 and actions=7 and triggers=7 and graphs=7 and screens=7 and templateid=10001 and hostid="+str(hostid), 1)
	hosttemplateid = DBGetID("hosts_templates","templateid=10001 and hostid="+str(hostid), "hosttemplateid")

	TestGUI('Hosts->Template linkage->Update', "hosts.php", "config=2&hostid="+str(hostid)+"&hosttemplateid="+str(hosttemplateid)+"&templateid=10001&items_update=on&items_delete=on&triggers_add=on&triggers_delete=on&actions_add=on&actions_update=on&graphs_add=on&graphs_delete=on&screens_update=on&screens_delete=on&register=update+linkage", "Template linkage updated")
	TestDBCount("hosts_templates","items=6 and actions=3 and triggers=5 and graphs=5 and screens=6 and templateid=10001 and hosttemplateid="+str(hosttemplateid)+" and hostid="+str(hostid), 1)

	TestGUI('Hosts->Template linkage->Delete', "hosts.php", "hosttemplateid="+str(hosttemplateid)+"&register=delete+linkage", "Template linkage deleted")
	TestDBCount("hosts_templates","hosttemplateid="+str(hosttemplateid), 0)

	TestGUI('Hosts->Delete', "hosts.php", "register=delete&hostid="+str(hostid), "Host deleted")
	TestDBCount("hosts","status=4 and hostid="+str(hostid), 1)

def GUI_Config_Items():
	header("GUI_Config_Items")

	TestGUI('Hosts->Add', "hosts.php", "host=regression&newgroup=&useip=on&ip=127.0.0.1&port=10050&status=0&host_templateid=0&register=add#form", "Host added")
	TestDBCount("hosts","host='regression'", 1)
	hostid = DBGetID("hosts","host='regression'", "hostid")

	TestGUI('Items->Add', "items.php", "description=Processor+load&hostid="+str(hostid)+"&type=0&snmp_community=public&snmp_oid=interfaces.ifTable.ifEntry.ifInOctets.1&snmp_port=161&snmpv3_securityname=&snmpv3_securitylevel=0&snmpv3_authpassphrase=&snmpv3_privpassphrase=&key=system.cpu.load%5Ball%2Cavg1%5D&units=&multiplier=0&formula=1&delay=5&history=90&trends=365&status=0&value_type=0&logtimefmt=&delta=0&trapper_hosts=&register=add&groupid=1&action=add+to+group#form", "Item added")
	TestDBCount("items","key_='system.cpu.load[all,avg1]'", 1)
	itemid = DBGetID("items","key_='system.cpu.load[all,avg1]'", "itemid")

	TestGUI('Items->Change status (Not active)', "items.php", "itemid="+str(itemid)+"&register=changestatus&status=1", "Status updated")
	TestDBCount("items","status=1 and itemid="+str(itemid), 1)

	TestGUI('Items->Change status (Active)', "items.php", "itemid="+str(itemid)+"&register=changestatus&status=0", "Status updated")
	TestDBCount("items","status=0 and itemid="+str(itemid), 1)

	TestGUI('Items->Update', "items.php", "itemid="+str(itemid)+"&description=Processor+load&hostid="+str(hostid)+"&type=7&snmp_community=public&snmp_oid=interfaces.ifTable.ifEntry.ifInOctets.1&snmp_port=161&snmpv3_securityname=&snmpv3_securitylevel=0&snmpv3_authpassphrase=&snmpv3_privpassphrase=&key=system.cpu.load%5Ball%2Cavg5%5D&units=&multiplier=1&formula=1&delay=4&history=91&trends=364&status=0&value_type=0&logtimefmt=&delta=0&trapper_hosts=&register=update&groupid=1&action=add+to+group#form", "Item updated")
	TestDBCount("items","key_='system.cpu.load[all,avg5]' and itemid="+str(itemid), 1)

	TestGUI('Items->Delete', "items.php", "itemid="+str(itemid)+"&register=delete", "Item deleted")
	TestDBCount("items","itemid="+str(itemid), 0)

	TestGUI('Hosts->Delete', "hosts.php", "register=delete&hostid="+str(hostid), "Host deleted")
	TestDBCount("hosts","status=4 and hostid="+str(hostid), 1)

def GUI_Config_Maps():
	header("GUI_Config_Maps")

	TestGUI('Configuration->Maps->Add', "sysmaps.php", "name=Regression&width=800&height=600&background=&label_type=0&register=add", "Network map added")
	TestGUI('Maps->Add (duplicate)', "sysmaps.php", "name=Regression&width=800&height=600&background=&label_type=0&register=add", "Cannot add network map")
	TestGUI('Maps->Delete', "sysmaps.php", "sysmapid=1&name=Regression&width=800&height=600&background=&label_type=0&register=delete", "Network map deleted")

def GUI_Config_Graphs():
	header("GUI_Config_Graphs")

	TestGUI('Graphs->Add', "graphs.php", "name=Regression&width=900&height=200&yaxistype=0&yaxismin=0&yaxismax=100&register=add", "Graph added")
	TestDBCount("graphs","name='Regression'", 1)
	graphid = DBGetID("graphs","name='Regression'", "graphid")

	TestGUI('Graphs->Delete', "graphs.php", "graphid="+str(graphid)+"&register=delete", "Graph deleted")
	TestDBCount("graphs","graphid="+str(graphid), 0)

def GUI_Config_Screens():
	header("GUI_Config_Screens")

	TestGUI('Screens->Add', "screenconf.php", "name=Regression&cols=2&rows=2&register=add", "Screen added")
	TestDBCount("screens","name='Regression'", 1)
	id = DBGetID("screens","name='Regression'", "screenid")

	TestGUI('Screens->Update', "screenconf.php", "screenid="+str(id)+"&name=Yo&cols=1&rows=1&register=update", "Screen updated")
	TestDBCount("screens","screenid="+str(id), 1)

	TestGUI('Screens->Delete', "screenconf.php", "screenid="+str(id)+"&name=Regression&cols=2&rows=2&register=delete", "Screen deleted")
	TestDBCount("screens","screenid="+str(id), 0)

def GUI_Config_Screen_Elements():
	header("GUI_Config_Screens")

	TestGUI('Screens->Add', "screenconf.php", "name=Regression&cols=1&rows=1&register=add", "Screen added")
	TestDBCount("screens","name='Regression'", 1)
	screenid = DBGetID("screens","name='Regression'", "screenid")

	TestGUI('Screens->Element->Show', "screenedit.php", "screenid="+str(screenid), "Empty")
	TestGUI('Screens->Element->Screen cell configuration', "screenedit.php", "register=edit&screenid="+str(screenid)+"&x=0&y=0", "Screen cell configuration")
	TestGUI('Screens->Element->Screen cell configuration', "screenedit.php", "resource=0&register=edit&screenid="+str(screenid)+"&x=0&y=0", "Graph&nbsp;name")
	TestGUI('Screens->Element->Screen cell configuration', "screenedit.php", "resource=1&register=edit&screenid="+str(screenid)+"&x=0&y=0", "Parameter")
	TestGUI('Screens->Element->Screen cell configuration', "screenedit.php", "resource=2&register=edit&screenid="+str(screenid)+"&x=0&y=0", "Map")
	TestGUI('Screens->Element->Screen cell configuration', "screenedit.php", "resource=3&register=edit&screenid="+str(screenid)+"&x=0&y=0", "Parameter")

def GUI_Config_Services():
	header("GUI_Config_Services")

	TestGUI('Service->Add', "services.php", "name=IT+Services&algorithm=1&showsla=on&goodsla=99.05&groupid=0&hostid=0&sortorder=0&register=add&softlink=true", "Service added")
	TestDBCount("services","name='IT Services'", 1)
	id = DBGetID("services","name='IT Services'", "serviceid")

	TestGUI('Service->Update', "services.php", "serviceid="+str(id)+"&name=IT+Services+new&algorithm=1&showsla=on&goodsla=99.10&groupid=0&hostid=0&sortorder=33&register=update&serviceupid=1&servicedownid=1&softlink=true&serviceid=1&serverid=10003", "Service updated")
	TestDBCount("services","serviceid="+str(id), 1)

	TestGUI('Service->Delete', "services.php", "serviceid="+str(id)+"&name=IT+Services+new&algorithm=1&showsla=on&goodsla=99.10&groupid=0&hostid=0&sortorder=33&register=delete&serviceupid=1&servicedownid=1&softlink=true&serviceid=1&serverid=10003", "Service deleted")
	TestDBCount("services","serviceid="+str(id), 0)

def GUI_Config_Triggers():
	header("GUI_Config_Triggers")

	TestGUI('Hosts->Add', "hosts.php", "host=regression&newgroup=&useip=on&ip=127.0.0.1&port=10050&status=0&host_templateid=0&register=add#form", "Host added")
	TestDBCount("hosts","host='regression'", 1)
	hostid = DBGetID("hosts","host='regression'", "hostid")

	TestGUI('Items->Add', "items.php", "description=Processor+load&hostid="+str(hostid)+"&type=0&snmp_community=public&snmp_oid=interfaces.ifTable.ifEntry.ifInOctets.1&snmp_port=161&snmpv3_securityname=&snmpv3_securitylevel=0&snmpv3_authpassphrase=&snmpv3_privpassphrase=&key=system.cpu.load%5Ball%2Cavg1%5D&units=&multiplier=0&formula=1&delay=5&history=90&trends=365&status=0&value_type=0&logtimefmt=&delta=0&trapper_hosts=&register=add&groupid=1&action=add+to+group#form", "Item added")
	TestDBCount("items","key_='system.cpu.load[all,avg1]'", 1)
	itemid = DBGetID("items","key_='system.cpu.load[all,avg1]'", "itemid")

	TestGUI('Trigger->Add', "triggers.php", "description=Processor+load+is+too+high+on+%7BHOSTNAME%7D&expression=%7Bregression%3Asystem.cpu.load%5Ball%2Cavg1%5D.min%2860%29%7D%3E1&priority=1&comments=comment&url=http%3A%2F%2Fwww.zabbix.com&register=add", "Trigger added")
	TestDBCount("triggers","description='Processor load is too high on {HOSTNAME}' and status=0 and url='http://www.zabbix.com' and comments='comment'", 1)
	triggerid = DBGetID("triggers","description='Processor load is too high on {HOSTNAME}' and status=0 and url='http://www.zabbix.com' and comments='comment'", "triggerid")
	TestDBCount("functions","itemid="+str(itemid)+" and lastvalue is NULL and function='min' and parameter='60' and  triggerid="+str(triggerid), 1)

	TestGUI('Triggers->Delete', "triggers.php", "triggerid="+str(triggerid)+"&register=delete", "Trigger deleted")
	TestDBCount("triggers","triggerid="+str(triggerid), 0)
	TestDBCount("functions","triggerid="+str(triggerid), 0)

	TestGUI('Items->Delete', "items.php", "itemid="+str(itemid)+"&register=delete", "Item deleted")
	TestDBCount("items","itemid="+str(itemid), 0)

	TestGUI('Hosts->Delete', "hosts.php", "register=delete&hostid="+str(hostid), "Host deleted")
	TestDBCount("hosts","status=4 and hostid="+str(hostid), 1)

def	testGUI():
	print "GUI REGRESSION TESTING"
	InitDB()

	GUI_Login()
	GUI_Config_General_Housekeeper()
	GUI_Config_General_Mediatype()
	GUI_Config_General_Autoregistration()
	GUI_Config_Users()
	GUI_Config_Media()
	GUI_Config_Hosts()
	GUI_Config_Hosts_Groups()
	GUI_Config_Hosts_Template_Linkage()
	GUI_Config_Items()
	GUI_Config_Triggers()
	GUI_Config_Maps()
	GUI_Config_Graphs()
	GUI_Config_Screens()
	GUI_Config_Screen_Elements()
	GUI_Config_Services()
