--
-- Data for table config
--

insert into config (smtp_server,smtp_helo,smtp_email,alert_history,alarm_history) values ('localhost','localhost','zabbix@localhost',12*31*24*3600,12*31*24*3600);

--
-- Data for table groups
--

insert into groups (groupid,name) values (1,'Administrators');
insert into groups (groupid,name) values (2,'Zabbix user');

--
-- Data for table users
--

insert into users (userid,groupid,alias,name,surname,passwd) values (1,1,'Admin','Zabbix','Administrator','d41d8cd98f00b204e9800998ecf8427e');

--
-- Data for table items_template 
--

insert into items_template (itemtemplateid,description,key_,delay)
	values (1,'Free memory','memory[free]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (2,'Free disk space on /','diskfree[/]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (3,'Free disk space on /tmp','diskfree[/tmp]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (4,'Free disk space on /usr','diskfree[/usr]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (5,'Free number of inodes on /','inodefree[/]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (6,'Free number of inodes on /opt','inodefree[/opt]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (7,'Free number of inodes on /tmp','inodefree[/tmp]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (8,'Free number of inodes on /usr','inodefree[/usr]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (9,'Number of processes','system[proccount]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (10,'Processor load','system[procload]', 5);
insert into items_template (itemtemplateid,description,key_,delay)
	values (11,'Processor load5','system[procload5]', 10);
insert into items_template (itemtemplateid,description,key_,delay)
	values (12,'Processor load15','system[procload15]', 20);
insert into items_template (itemtemplateid,description,key_,delay)
	values (13,'Number of running processes','system[procrunning]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (14,'Free swap space (Kb)','swap[free]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (16,'Size of /var/log/syslog','filesize[/var/log/syslog]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (17,'Number of users connected','system[users]', 30);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (18,'Number of established TCP connections','tcp_count', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (19,'Checksum of /etc/inetd.conf','cksum[/etc/inetd.conf]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (20,'Checksum of /vmlinuz','cksum[/vmlinuz]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (21,'Checksum of /etc/passwd','cksum[/etc/passwd]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (22,'Ping to the server (TCP)','ping', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (23,'Free disk space on /home','diskfree[/home]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (24,'Free number of inodes on /home','inodefree[/home]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (25,'Free disk space on /var','diskfree[/var]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (26,'Free disk space on /opt','diskfree[/opt]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (27,'Host uptime (in sec)','system[uptime]', 300);
insert into items_template (itemtemplateid,description,key_,delay)
	values (28,'Total memory (kB)','memory[total]', 1800);
insert into items_template (itemtemplateid,description,key_,delay)
	values (29,'Shared memory (kB)','memory[shared]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (30,'Buffers memory (kB)','memory[buffers]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (31,'Cached memory (kB)','memory[cached]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (32,'Total swap space (Kb)','swap[total]', 1800);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (33,'Amount of memory swapped in from disk (kB/s)','swap[in]', 30);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (34,'Amount of memory swapped to disk (kB/s)','swap[out]', 30);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (35,'Blocks sent to a block device (blocks/s)','io[in]', 30);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (36,'Blocks received from a block device (blocks/s)','io[out]', 30);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (37,'The number of interrupts per second, including the clock','system[interrupts]', 30);
--insert into items_template (itemtemplateid,description,key_,delay)
--	values (38,'The number of context switches per second','system[switches]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (39,'Email (SMTP) server is running','check_service[smtp]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (40,'FTP server is running','check_service[ftp]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (41,'SSH server is running','check_service[ssh]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (42,'Telnet server is running','net[listen_23]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (43,'WEB server is running','net[listen_80]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (44,'POP3 server is running','check_service[pop]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (45,'IMAP server is running','check_service[imap]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (46,'Checksum of /usr/sbin/sshd','cksum[/usr/sbin/sshd]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (47,'Checksum of /usr/bin/ssh','cksum[/usr/bin/ssh]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (48,'Checksum of /etc/services','cksum[/etc/services]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (49,'Number of disks read/write operations','io[disk_io]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (50,'Number of disks read operations','io[disk_rio]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (51,'Number of disks write operations','io[disk_wio]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (52,'Number of block read from disks','io[disk_rblk]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (53,'Number of block written to disks','io[disk_wblk]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (54,'News (NNTP) server is running','check_service[nntp]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (55,'Number of running processes inetd','proc_cnt[inetd]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (56,'Number of running processes apache','proc_cnt[httpd]', 60);
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
insert into items_template (itemtemplateid,description,key_,delay)
	values (63,'Maximum number of processes','kern[maxproc]', 1800);
insert into items_template (itemtemplateid,description,key_,delay)
	values (64,'Maximum number of opened files','kern[maxfiles]', 1800);

--
-- Data for table triggers_template
--

insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (1,1,'Lack of free memory on server %s','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (2,2,'Low free disk space on %s\'s volume /','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (3,3,'Low free disk space on %s\'s volume /tmp','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (4,4,'Low free disk space on %s\'s volume /usr','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (5,5,'Low number of free inodes on %s\'s volume /','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (6,6,'Low number of free inodes on %s\'s volume /opt','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (7,7,'Low number of free inodes on %s\'s volume /tmp','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (8,8,'Low number of free inodes on %s\'s volume /usr','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (9,9,'Too many processes on %s','{:.last(0)}>300');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (10,10,'Processor load is too high on %s','{:.last(0)}>5');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (13,13,'Too many processes running on %s','{:.last(0)}>10');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (14,14,'Lack of free swap space on %s','{:.last(0)}<100000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (17,17,'Too may users connected on server %s','{:.last(0)}>50');
--insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
--	values (18,18,'Too may established TCP connections on server %s','{:.last(0)}>500');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (19,19,'/etc/inetd.conf has been changed on server %s','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (20,20,'/vmlinuz has been changed on server %s','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (21,21,'/passwd has been changed on server %s','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (23,23,'Low free disk space on %s\'s volume /home','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (24,24,'Low number of free inodes on %s\' volume /home','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (25,25,'Low free disk space on %s\'s volume /var','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (26,26,'Low free disk space on %s\'s volume /opt','{:.last(0)}<10000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (27,27,'%s has just been restarted','{:.last(0)}<600');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (39,39,'Email (SMTP) server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (40,40,'FTP server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (41,41,'SSH server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (42,42,'Telnet server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (43,43,'WEB server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (44,44,'POP3 server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (45,45,'IMAP server is down on %s','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (46,46,'/usr/sbin/sshd has been changed on server %s','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (47,47,'/usr/bin/ssh has been changed on server %s','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (48,48,'/etc/services has been changed on server %s','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (54,54,'News (NNTP) server is down on %s','{:.last(0)}<1');
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
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (63,63,'Configured max number of processes is too low on %s','{:.last(0)}<256');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (64,64,'Configured max number of opened files is too low on %s','{:.last(0)}<512');

