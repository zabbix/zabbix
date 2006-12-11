insert into items_template (itemtemplateid,description,key_,delay)
	values (55,'Number of running processes inetd','proc_cnt[inetd]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (56,'Number of running processes apache','proc_cnt[apache]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (57,'Number of running processes mysqld','proc_cnt[mysqld]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (58,'Number of running processes syslogd','proc_cnt[syslogd]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (59,'Number of running processes sshd','proc_cnt[sshd]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (60,'Number of running processes zabbix_agentd','proc_cnt[zabbix_agentd]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (61,'Number of running processes zabbix_suckerd','proc_cnt[zabbix_suckerd]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (62,'Number of running processes zabbix_trapperd','proc_cnt[zabbix_trapperd]', 60);

insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (55,55,'Inetd is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (56,56,'Apache is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (57,57,'Mysql is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (58,58,'Syslogd is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (59,59,'Sshd is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (60,60,'Zabbix_agentd is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (61,61,'Zabbix_suckerd is not running on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (62,62,'Zabbix_trapperd is not running on %s','{:.last(0)}<1');

alter table triggers drop lastcheck;

delete from triggers_template where triggertemplateid=18;
delete from items_template where itemtemplateid=18;
