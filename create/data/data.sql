--
-- Data for table config
--

insert into config (smtp_server,smtp_helo,smtp_email) values ('localhost','localhost','zabbix@localhost');

--
-- Data for table groups
--

insert into groups (groupid,name) values (1,'Administrators');
insert into groups (groupid,name) values (2,'Zabbix user');

--
-- Data for table users
--

insert into users (userid,groupid,alias,name,surname,passwd) values (1,1,'Admin','Zabbix','Administrator','');

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
	values (5,'Free number of inodes on /','inodefree[/]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (6,'Free number of inodes on /opt','inodefree[/opt]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (7,'Free number of inodes on /tmp','inodefree[/tmp]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (8,'Free number of inodes on /usr','inodefree[/usr]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (9,'Number of processes','system[proccount]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (10,'Processor load','system[procload]', 10);
insert into items_template (itemtemplateid,description,key_,delay)
	values (11,'Processor load5','system[procload5]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (12,'Processor load15','system[procload15]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (13,'Number of running processes','system[procrunning]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (14,'Free swap space (Kb)','swap[free]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (16,'Size of /var/log/syslog','filesize[/var/log/syslog]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (17,'Number of users connected','system[users]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (18,'Number of established TCP connections','tcp_count', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (19,'Checksum of /etc/inetd.conf','cksum[/etc/inetd_conf]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (20,'Checksum of /vmlinuz','cksum[/vmlinuz]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (21,'Checksum of /etc/passwd','cksum[/etc/passwd]', 600);
insert into items_template (itemtemplateid,description,key_,delay)
	values (22,'Ping to the server (TCP)','ping', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (23,'Free disk space on /home','diskfree[/home]', 30);
insert into items_template (itemtemplateid,description,key_,delay)
	values (24,'Free number of inodes on /home','inodefree[/home]', 30);
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
	values (39,'Email (SMTP) server is running','net[listen_25]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (40,'FTP server is running','net[listen_21]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (41,'SSH server is running','net[listen_22]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (42,'Telnet server is running','net[listen_23]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (43,'WEB server is running','net[listen_80]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (44,'POP3 server is running','net[listen_110]', 60);
insert into items_template (itemtemplateid,description,key_,delay)
	values (45,'IMAP server is running','net[listen_143]', 60);

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
	values (9,9,'Too many processes running on %s','{:.last(0)}>300');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (10,10,'Processor load is too high on %s','{:.last(0)}>5');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (13,13,'Too many processes running on %s','{:.last(0)}>10');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (14,14,'Lack of free swap space on %s','{:.last(0)}<100000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (17,17,'Too may users connected on server %s','{:.last(0)}>50');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (18,18,'Too may established TCP connections on server %s','{:.last(0)}>500');
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
	values (27,27,'%s have just been restarted','{:.last(0)}<600');
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
