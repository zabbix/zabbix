update items set status=0, type=2 where status=2;

drop table items_template;
drop table triggers_template;

--
-- Data for table hosts 
--

INSERT INTO hosts VALUES (10001,'UNIX_ZABBIX_AGENT',0,'',10000,3,0,0);
INSERT INTO hosts VALUES (10002,'WIN32_ZABBIX_AGENT',0,'',10000,3,0,0);
INSERT INTO hosts VALUES (10004,'STANDALONE',0,'',10000,3,0,0);

--
-- Table structure for table 'groups'
--

CREATE TABLE groups (
  groupid		serial,
  name			varchar(64)     DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  UNIQUE (name)
);

CREATE UNIQUE INDEX groups_name on groups (name);

--
-- Table structure for table 'hosts_groups'
--

CREATE TABLE hosts_groups (
  hostid		int4		DEFAULT '0' NOT NULL,
  groupid		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid,groupid)
);

--
-- Data for table items
--

INSERT INTO items VALUES (10001,0,'','',10001,'Free memory','memory[free]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10002,0,'','',10001,'Free disk space on /','diskfree[/]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10003,0,'','',10001,'Free disk space on /tmp','diskfree[/tmp]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10004,0,'','',10001,'Free disk space on /usr','diskfree[/usr]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10005,0,'','',10001,'Free number of inodes on /','inodefree[/]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10006,0,'','',10001,'Free number of inodes on /opt','inodefree[/opt]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10007,0,'','',10001,'Free number of inodes on /tmp','inodefree[/tmp]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10008,0,'','',10001,'Free number of inodes on /usr','inodefree[/usr]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10009,0,'','',10001,'Number of processes','system[proccount]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10010,0,'','',10001,'Processor load','system[procload]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10011,0,'','',10001,'Processor load5','system[procload5]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10012,0,'','',10001,'Processor load15','system[procload15]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10013,0,'','',10001,'Number of running processes','system[procrunning]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10014,0,'','',10001,'Free swap space (Kb)','swap[free]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10015,0,'','',10001,'Size of /var/log/syslog','filesize[/var/log/syslog]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10016,0,'','',10001,'Number of users connected','system[users]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10017,0,'','',10001,'Checksum of /etc/inetd.conf','cksum[/etc/inetd.conf]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10018,0,'','',10001,'Checksum of /vmlinuz','cksum[/vmlinuz]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10019,0,'','',10001,'Checksum of /etc/passwd','cksum[/etc/passwd]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10020,0,'','',10001,'Ping to the server (TCP)','ping',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10021,0,'','',10001,'Free disk space on /home','diskfree[/home]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10022,0,'','',10001,'Free number of inodes on /home','inodefree[/home]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10023,0,'','',10001,'Free disk space on /var','diskfree[/var]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10024,0,'','',10001,'Free disk space on /opt','diskfree[/opt]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10025,0,'','',10001,'Host uptime (in sec)','system[uptime]',300,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10026,0,'','',10001,'Total memory (kB)','memory[total]',1800,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10027,0,'','',10001,'Shared memory (kB)','memory[shared]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10028,0,'','',10001,'Buffers memory (kB)','memory[buffers]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10029,0,'','',10001,'Cached memory (kB)','memory[cached]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10030,0,'','',10001,'Total swap space (Kb)','swap[total]',1800,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10031,0,'','',10001,'Email (SMTP) server is running','check_service[smtp]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10032,0,'','',10001,'FTP server is running','check_service[ftp]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10033,0,'','',10001,'SSH server is running','check_service[ssh]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10034,0,'','',10001,'Telnet server is running','net[listen_23]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10035,0,'','',10001,'WEB server is running','net[listen_80]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10036,0,'','',10001,'POP3 server is running','check_service[pop]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10037,0,'','',10001,'IMAP server is running','check_service[imap]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10038,0,'','',10001,'Checksum of /usr/sbin/sshd','cksum[/usr/sbin/sshd]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10039,0,'','',10001,'Checksum of /usr/bin/ssh','cksum[/usr/bin/ssh]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10040,0,'','',10001,'Checksum of /etc/services','cksum[/etc/services]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10041,0,'','',10001,'Number of disks read/write operations','io[disk_io]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10042,0,'','',10001,'Number of disks read operations','io[disk_rio]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10043,0,'','',10001,'Number of disks write operations','io[disk_wio]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10044,0,'','',10001,'Number of block read from disks','io[disk_rblk]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10045,0,'','',10001,'Number of block written to disks','io[disk_wblk]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10046,0,'','',10001,'News (NNTP) server is running','check_service[nntp]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10047,0,'','',10001,'Number of running processes inetd','proc_cnt[inetd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10048,0,'','',10001,'Number of running processes apache','proc_cnt[httpd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10049,0,'','',10001,'Number of running processes mysqld','proc_cnt[mysqld]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10050,0,'','',10001,'Number of running processes syslogd','proc_cnt[syslogd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10051,0,'','',10001,'Number of running processes sshd','proc_cnt[sshd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10052,0,'','',10001,'Number of running processes zabbix_agentd','proc_cnt[zabbix_agentd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10053,0,'','',10001,'Number of running processes zabbix_suckerd','proc_cnt[zabbix_suckerd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10054,0,'','',10001,'Number of running processes zabbix_trapperd','proc_cnt[zabbix_trapperd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10055,0,'','',10001,'Maximum number of processes','kern[maxproc]',1800,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10056,0,'','',10001,'Maximum number of opened files','kern[maxfiles]',1800,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10057,0,'','',10001,'Host name','system[hostname]',1800,30,0,0,NULL,NULL,NULL,0,1,'');
INSERT INTO items VALUES (10058,0,'','',10001,'Host information','system[uname]',1800,30,0,0,NULL,NULL,NULL,0,1,'');
INSERT INTO items VALUES (10059,0,'','',10001,'Version of zabbix_agent(d) running','version[zabbix_agent]',1800,30,0,0,NULL,NULL,NULL,0,1,'');
INSERT INTO items VALUES (10060,0,'','',10001,'WEB (HTTP) server is running','check_service[http]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10061,0,'','',10001,'Host status','status',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10062,0,'','',10001,'Total number of inodes on /','inodetotal[/]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10063,0,'','',10001,'Total number of inodes on /opt','inodetotal[/opt]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10064,0,'','',10001,'Total number of inodes on /tmp','inodetotal[/tmp]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10065,0,'','',10001,'Total number of inodes on /usr','inodetotal[/usr]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10066,0,'','',10001,'Total number of inodes on /home','inodetotal[/home]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10067,0,'','',10001,'Total disk space on /','disktotal[/]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10068,0,'','',10001,'Total disk space on /opt','disktotal[/opt]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10069,0,'','',10001,'Total disk space on /tmp','disktotal[/tmp]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10070,0,'','',10001,'Total disk space on /usr','disktotal[/usr]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10071,0,'','',10001,'Total disk space on /home','disktotal[/home]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10072,0,'','',10001,'Average number of bytes received on interface lo (1min)','netloadin1[lo]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10073,0,'','',10001,'Average number of bytes received on interface lo (5min)','netloadin5[lo]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10074,0,'','',10001,'Average number of bytes received on interface lo (15min)','netloadin15[lo]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10075,0,'','',10001,'Average number of bytes received on interface eth0 (1min)','netloadin1[eth0]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10076,0,'','',10001,'Average number of bytes received on interface eth0 (5min)','netloadin5[eth0]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10077,0,'','',10001,'Average number of bytes received on interface eth0 (15min)','netloadin15[eth0]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10078,0,'','',10001,'Average number of bytes received on interface eth1 (1min)','netloadin1[eth1]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10079,0,'','',10001,'Average number of bytes received on interface eth1 (5min)','netloadin5[eth1]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10080,0,'','',10001,'Average number of bytes received on interface eth1 (15min)','netloadin15[eth1]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10081,0,'','',10001,'Average number of bytes sent from interface lo (1min)','netloadout1[lo]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10082,0,'','',10001,'Average number of bytes sent from interface lo (5min)','netloadout5[lo]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10083,0,'','',10001,'Average number of bytes sent from interface lo (15min)','netloadout15[lo]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10084,0,'','',10001,'Average number of bytes sent from interface eth0 (1min)','netloadout1[eth0]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10085,0,'','',10001,'Average number of bytes sent from interface eth0 (5min)','netloadout5[eth0]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10086,0,'','',10001,'Average number of bytes sent from interface eth0 (15min)','netloadout15[eth0]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10087,0,'','',10001,'Average number of bytes sent from interface eth1 (1min)','netloadout1[eth1]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10088,0,'','',10001,'Average number of bytes sent from interface eth1 (5min)','netloadout5[eth1]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10089,0,'','',10001,'Average number of bytes sent from interface eth1 (15min)','netloadout15[eth1]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10090,0,'','',10002,'Free memory','memory[free]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10091,0,'','',10002,'Free disk space on c:','diskfree[c:]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10098,0,'','',10002,'Number of processes','system[proccount]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10099,0,'','',10002,'Processor load','system[procload]',5,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10100,0,'','',10002,'Processor load5','system[procload5]',10,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10101,0,'','',10002,'Processor load15','system[procload15]',20,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10102,0,'','',10002,'Number of running processes','system[procrunning]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10103,0,'','',10002,'Free swap space (Kb)','swap[free]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10104,0,'','',10002,'Size of c:\\msdos.sys','filesize[c:\\msdos.sys]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10106,0,'','',10002,'Checksum of c:\\autoexec.bat','cksum[c:\\autoexec.bat]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10109,0,'','',10002,'Ping to the server (TCP)','ping',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10114,0,'','',10002,'Host uptime (in sec)','system[uptime]',300,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10115,0,'','',10002,'Total memory (kB)','memory[total]',1800,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10119,0,'','',10002,'Total swap space (Kb)','swap[total]',1800,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10137,0,'','',10002,'Number of running processes apache','proc_cnt[httpd]',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10147,0,'','',10002,'Host information','system[uname]',1800,30,0,0,NULL,NULL,NULL,0,1,'');
INSERT INTO items VALUES (10148,0,'','',10002,'Version of zabbix_agent(d) running','version[zabbix_agent]',1800,30,0,0,NULL,NULL,NULL,0,1,'');
INSERT INTO items VALUES (10150,0,'','',10002,'Host status','status',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10156,0,'','',10002,'Total disk space on c:','disktotal[c:]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10359,0,'','',10002,'Total disk space on d:','disktotal[d:]',3600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10357,0,'','',10002,'Checksum of c:\\config.sys','cksum[c:\\config.sys]',600,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10358,0,'','',10002,'Free disk space on d:','diskfree[d:]',30,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10298,3,'','',10004,'Email (SMTP) server is running','smtp',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10299,3,'','',10004,'FTP server is running','ftp',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10300,3,'','',10004,'SSH server is running','ssh',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10303,3,'','',10004,'POP3 server is running','pop',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10304,3,'','',10004,'IMAP server is running','imap',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10313,3,'','',10004,'News (NNTP) server is running','nntp',60,30,0,0,NULL,NULL,NULL,0,0,'');
INSERT INTO items VALUES (10327,3,'','',10004,'WEB (HTTP) server is running','http',60,30,0,0,NULL,NULL,NULL,0,0,'');

--
-- Data for table triggers
--

INSERT INTO triggers VALUES (10001,'{10211}<10000','Lack of free memory on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10002,'{10213}<10000','Low free disk space on %s\'s volume /','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10189,'{10219}<10000','Low free disk space on %s\\\'s volume /tmp','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10004,'{10217}<10000','Low free disk space on %s\'s volume /usr','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10005,'{10221}<10000','Low number of free inodes on %s\'s volume /','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10006,'{10223}<10000','Low number of free inodes on %s\'s volume /opt','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10007,'{10222}<10000','Low number of free inodes on %s\'s volume /tmp','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10008,'{10224}<10000','Low number of free inodes on %s\'s volume /usr','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10190,'{10233}>300','Too many processes on %s','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10010,'{10010}>5','Processor load is too high on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10011,'{10234}>10','Too many processes running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10012,'{10212}<100000','Lack of free swap space on %s','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10013,'{10013}>50','Too may users connected on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10014,'{10197}>0','/etc/inetd.conf has been changed on server %s','',0,2,2,0,0,'');
INSERT INTO triggers VALUES (10015,'{10201}>0','/vmlinuz has been changed on server %s','',0,2,2,0,0,'');
INSERT INTO triggers VALUES (10016,'{10199}>0','/passwd has been changed on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10017,'{10214}<10000','Low free disk space on %s\'s volume /home','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10018,'{10220}<10000','Low number of free inodes on %s\' volume /home','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10019,'{10218}<10000','Low free disk space on %s\'s volume /var','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10020,'{10215}<10000','Low free disk space on %s\'s volume /opt','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10021,'{10196}<600','%s has just been restarted','',0,2,1,0,0,'');
INSERT INTO triggers VALUES (10022,'{10205}<1','Email (SMTP) server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10023,'{10206}<1','FTP server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10024,'{10229}<1','SSH server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10025,'{10232}<1','Telnet server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10026,'{10026}<1','WEB server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10027,'{10227}<1','POP3 server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10028,'{10209}<1','IMAP server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10029,'{10200}>0','/usr/sbin/sshd has been changed on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10030,'{10030}>0','/usr/bin/ssh has been changed on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10031,'{10198}>0','/etc/services has been changed on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10032,'{10226}<1','News (NNTP) server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10033,'{10210}<1','Inetd is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10034,'{10202}<1','Apache is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10035,'{10225}<1','Mysql is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10036,'{10231}<1','Syslogd is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10037,'{10230}<1','Sshd is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10038,'{10237}<1','Zabbix_agentd is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10039,'{10238}<1','Zabbix_suckerd is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10040,'{10239}<1','Zabbix_trapperd is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10041,'{10204}<256','Configured max number of processes is too low on %s','',0,2,1,0,0,'');
INSERT INTO triggers VALUES (10042,'{10203}<512','Configured max number of opened files is too low on %s','',0,2,1,0,0,'');
INSERT INTO triggers VALUES (10043,'{10208}>0','Hostname was changed on %s','',0,2,1,0,0,'');
INSERT INTO triggers VALUES (10044,'{10207}>0','Host information was changed on %s','',0,2,1,0,0,'');
INSERT INTO triggers VALUES (10045,'{10235}>0','Version of zabbix_agent(d) was changed on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10046,'{10236}<1','WEB (HTTP) server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10047,'{10228}=2','Server %s is unreachable','',0,2,4,0,0,'');
INSERT INTO triggers VALUES (10048,'{10048}<10000','Lack of free memory on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10049,'{10241}<10000','Low free disk space on %s\'s volume c:','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10056,'{10056}>300','Too many processes on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10057,'{10057}>5','Processor load is too high on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10058,'{10058}>10','Too many processes running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10059,'{10059}<100000','Lack of free swap space on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10061,'{10240}>0','c:\\autoexec.bat has been changed on server %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10068,'{10068}<600','%s has just been restarted','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10081,'{10081}<1','Apache is not running on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10091,'{10091}>0','Host information was changed on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10092,'{10243}>0','Version of zabbix_agent(d) was changed on %s','',0,2,1,0,0,'');
INSERT INTO triggers VALUES (10094,'{10094}=2','Server %s is unreachable','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10191,'{10242}<10000','Low free disk space on %s\\\'s volume d:','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10163,'{10189}<1','Email (SMTP) server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10164,'{10190}<1','FTP server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10165,'{10194}<1','SSH server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10168,'{10193}<1','POP3 server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10169,'{10191}<1','IMAP server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10173,'{10192}<1','News (NNTP) server is down on %s','',0,2,3,0,0,'');
INSERT INTO triggers VALUES (10187,'{10195}<1','WEB (HTTP) server is down on %s','',0,2,3,0,0,'');

--
-- Data for table functions
--

INSERT INTO functions VALUES (10211,10001,10001,NULL,'last','0');
INSERT INTO functions VALUES (10213,10002,10002,NULL,'last','0');
INSERT INTO functions VALUES (10219,10003,10189,NULL,'last','0');
INSERT INTO functions VALUES (10217,10004,10004,NULL,'last','0');
INSERT INTO functions VALUES (10221,10005,10005,NULL,'last','0');
INSERT INTO functions VALUES (10223,10006,10006,NULL,'last','0');
INSERT INTO functions VALUES (10222,10007,10007,NULL,'last','0');
INSERT INTO functions VALUES (10224,10008,10008,NULL,'last','0');
INSERT INTO functions VALUES (10233,10009,10190,NULL,'last','0');
INSERT INTO functions VALUES (10010,10010,10010,NULL,'last','0');
INSERT INTO functions VALUES (10234,10013,10011,NULL,'last','0');
INSERT INTO functions VALUES (10212,10014,10012,NULL,'last','0');
INSERT INTO functions VALUES (10013,10016,10013,NULL,'last','0');
INSERT INTO functions VALUES (10197,10017,10014,NULL,'diff','0');
INSERT INTO functions VALUES (10201,10018,10015,NULL,'diff','0');
INSERT INTO functions VALUES (10199,10019,10016,NULL,'diff','0');
INSERT INTO functions VALUES (10214,10021,10017,NULL,'last','0');
INSERT INTO functions VALUES (10220,10022,10018,NULL,'last','0');
INSERT INTO functions VALUES (10218,10023,10019,NULL,'last','0');
INSERT INTO functions VALUES (10215,10024,10020,NULL,'last','0');
INSERT INTO functions VALUES (10196,10025,10021,NULL,'last','0');
INSERT INTO functions VALUES (10205,10031,10022,NULL,'last','0');
INSERT INTO functions VALUES (10206,10032,10023,NULL,'last','0');
INSERT INTO functions VALUES (10229,10033,10024,NULL,'last','0');
INSERT INTO functions VALUES (10232,10034,10025,NULL,'last','0');
INSERT INTO functions VALUES (10026,10035,10026,NULL,'last','0');
INSERT INTO functions VALUES (10227,10036,10027,NULL,'last','0');
INSERT INTO functions VALUES (10209,10037,10028,NULL,'last','0');
INSERT INTO functions VALUES (10200,10038,10029,NULL,'diff','0');
INSERT INTO functions VALUES (10030,10039,10030,NULL,'diff','0');
INSERT INTO functions VALUES (10198,10040,10031,NULL,'diff','0');
INSERT INTO functions VALUES (10226,10046,10032,NULL,'last','0');
INSERT INTO functions VALUES (10210,10047,10033,NULL,'last','0');
INSERT INTO functions VALUES (10202,10048,10034,NULL,'last','0');
INSERT INTO functions VALUES (10225,10049,10035,NULL,'last','0');
INSERT INTO functions VALUES (10231,10050,10036,NULL,'last','0');
INSERT INTO functions VALUES (10230,10051,10037,NULL,'last','0');
INSERT INTO functions VALUES (10237,10052,10038,NULL,'last','0');
INSERT INTO functions VALUES (10238,10053,10039,NULL,'last','0');
INSERT INTO functions VALUES (10239,10054,10040,NULL,'last','0');
INSERT INTO functions VALUES (10204,10055,10041,NULL,'last','0');
INSERT INTO functions VALUES (10203,10056,10042,NULL,'last','0');
INSERT INTO functions VALUES (10208,10057,10043,NULL,'diff','0');
INSERT INTO functions VALUES (10207,10058,10044,NULL,'diff','0');
INSERT INTO functions VALUES (10235,10059,10045,NULL,'diff','0');
INSERT INTO functions VALUES (10236,10060,10046,NULL,'last','0');
INSERT INTO functions VALUES (10228,10061,10047,NULL,'last','0');
INSERT INTO functions VALUES (10048,10090,10048,NULL,'last','0');
INSERT INTO functions VALUES (10241,10091,10049,NULL,'last','0');
INSERT INTO functions VALUES (10056,10098,10056,NULL,'last','0');
INSERT INTO functions VALUES (10057,10099,10057,NULL,'last','0');
INSERT INTO functions VALUES (10058,10102,10058,NULL,'last','0');
INSERT INTO functions VALUES (10059,10103,10059,NULL,'last','0');
INSERT INTO functions VALUES (10240,10106,10061,NULL,'diff','0');
INSERT INTO functions VALUES (10242,10358,10191,NULL,'last','0');
INSERT INTO functions VALUES (10068,10114,10068,NULL,'last','0');
INSERT INTO functions VALUES (10081,10137,10081,NULL,'last','0');
INSERT INTO functions VALUES (10091,10147,10091,NULL,'diff','0');
INSERT INTO functions VALUES (10243,10148,10092,NULL,'diff','0');
INSERT INTO functions VALUES (10094,10150,10094,NULL,'last','0');
INSERT INTO functions VALUES (10189,10298,10163,NULL,'last','0');
INSERT INTO functions VALUES (10190,10299,10164,NULL,'last','0');
INSERT INTO functions VALUES (10194,10300,10165,NULL,'last','0');
INSERT INTO functions VALUES (10193,10303,10168,NULL,'last','0');
INSERT INTO functions VALUES (10191,10304,10169,NULL,'last','0');
INSERT INTO functions VALUES (10192,10313,10173,NULL,'last','0');
INSERT INTO functions VALUES (10195,10327,10187,NULL,'last','0');

