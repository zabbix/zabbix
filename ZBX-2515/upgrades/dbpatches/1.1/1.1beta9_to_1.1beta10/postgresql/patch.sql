ALTER TABLE config ADD work_period varchar(100) DEFAULT '1-5,00:00-24:00' NOT NULL;
ALTER TABLE graphs ADD show_work_period int2 DEFAULT '1' NOT NULL;
ALTER TABLE graphs ADD show_triggers int2 DEFAULT '1' NOT NULL;

ALTER TABLE profiles ALTER COLUMN	value		TYPE	varchar(255);
ALTER TABLE profiles ADD COLUMN		valuetype	int4            DEFAULT 0 NOT NULL;

--
-- Table structure for table 'applications'
--

CREATE TABLE applications (
        applicationid           serial,
        hostid                  int4		DEFAULT '0' NOT NULL,
        name                    varchar(255)	DEFAULT '' NOT NULL,
        templateid              int4		DEFAULT '0' NOT NULL,
        PRIMARY KEY (applicationid),
        FOREIGN KEY hostid (hostid) REFERENCES hosts
);
CREATE UNIQUE INDEX applications_hostid_key on items (hostid,name);

--
-- Table structure for table 'items_applications'
--

CREATE TABLE items_applications (
        applicationid           int4          DEFAULT '0' NOT NULL,
        itemid                  int4          DEFAULT '0' NOT NULL,
        PRIMARY KEY (applicationid,itemid),
	FOREIGN KEY (applicationid) REFERENCES applications,
	FOREIGN KEY (itemid) REFERENCES items
);

alter table audit rename auditlog;

alter table auditlog add resourcetype          int4          DEFAULT '0' NOT NULL;
update auditlog set resourcetype=resource;
alter table auditlog drop resource;

alter table screens add hsize   int4  DEFAULT '1' NOT NULL;
alter table screens add vsize   int4  DEFAULT '1' NOT NULL;
update screens set hsize=cols;
update screens set vsize=rows;
alter table screens drop cols;
alter table screens drop rows;

alter table screens_items add resourcetype	int4	DEFAULT '0' NOT NULL;
update screens_items set resourcetype=resource;
alter table screens_items drop resource;

alter table functions change function function varchar(12) DEFAULT '' NOT NULL;

CREATE TABLE help_items (
        itemtype        int4            DEFAULT '0' NOT NULL,
        key_            varchar(64)     DEFAULT '' NOT NULL,
        description     varchar(255)    DEFAULT '' NOT NULL,
        PRIMARY KEY     (itemtype, key_)
);


insert into help_items values (3,'icmpping','Checks if server accessible by ICMP ping	0 - ICMP ping fails 1 - ICMP ping successful	One of zabbix_server processes performs ICMP pings once per PingerFrequency seconds.');
insert into help_items values (3,'icmppingsec','Return ICMP ping response time	Number of seconds Example: 0.02');
insert into help_items values (3,'ftp&lt;,port&gt;','Checks if FTP server is running and accepting connections	0 - FTP server is down 1 - FTP server is running');
insert into help_items values (3,'http&lt;,port&gt;','Checks if HTTP (WEB) server is running and accepting connections	0 - HTTP server is down 1 - HTTP server is running');
insert into help_items values (3,'imap&lt;,port&gt;','Checks if IMAP server is running and accepting connections	0 - IMAP server is down 1 - IMAP server is running');
insert into help_items values (3,'nntp&lt;,port&gt;','Checks if NNTP server is running and accepting connections	0 - NNTP server is down 1 - NNTP server is running');
insert into help_items values (3,'pop&lt;,port&gt;','Checks if POP server is running and accepting connections	0 - POP server is down 1 - POP server is running');
insert into help_items values (3,'smtp&lt;,port&gt;','Checks if SMTP server is running and accepting connections	0 - SMTP server is down 1 - SMTP server is running');
insert into help_items values (3,'ssh&lt;,port&gt;','Checks if SSH server is running and accepting connections	0 - SSH server is down 1 - SSH server is running');
insert into help_items values (3,'tcp,port','Checks if TCP service is running and accepting connections on port	0 - the serivce on the por t is down 1 - the service is running');
insert into help_items values (3,'ftp_perf&lt;,port&gt;','Checks if FTP server is running and accepting connections	0 - FTP server is down Otherwise, number of milliseconds spent connecting to FTP server');
insert into help_items values (3,'http_perf&lt;,port&gt;','Checks if HTTP (WEB) server is running and accepting connections	0 - HTTP server is down Otherwise, number of milliseconds spent connecting to HTTP server');
insert into help_items values (3,'imap_perf&lt;,port&gt;','Checks if IMAP server is running and accepting connections	0 - IMAP server is down Otherwise, number of milliseconds spent connecting to IMAP server');
insert into help_items values (3,'nntp_perf&lt;,port&gt;','Checks if NNTP server is running and accepting connections	0 - NNTP server is down Otherwise, number of milliseconds spent connecting to NNTP server');
insert into help_items values (3,'pop_perf&lt;,port&gt;','Checks if POP server is running and accepting connections	0 - POP server is down Otherwise, number of milliseconds spent connecting to POP server');
insert into help_items values (3,'smtp_perf&lt;,port&gt;','Checks if SMTP server is running and accepting connections	0 - SMTP server is down Otherwise, number of milliseconds spent connecting to SMTP server');
insert into help_items values (3,'ssh_perf&lt;,port&gt;','Checks if SSH server is running and accepting connections	0 - SSH server is down Otherwise, number of milliseconds spent connecting to SSH server');

insert into help_items values (5,'zabbix[history]','Number of values stored in table HISTORY');
insert into help_items values (5,'zabbix[history_str]','Number of values stored in table HISTORY_STR');
insert into help_items values (5,'zabbix[items]','Number of items in ZABBIX database');
insert into help_items values (5,'zabbix[items_unsupported]','Number of unsupported items in ZABBIX database');
insert into help_items values (5,'zabbix[log]','Stores warning and error messages generated by ZABBIX server.');
insert into help_items values (5,'zabbix[queue]','Number of items in the queue');
insert into help_items values (5,'zabbix[trends]','Number of values stored in table TRENDS');
insert into help_items values (5,'zabbix[triggers]','Number of triggers in ZABBIX database');

insert into help_items values (8,'grpfunc(&lt;Group&gt;,&lt;Key&gt;,&lt;func&gt;,&lt;param&gt;)','Aggregate checks does not require any agent running on a host being monitored. ZABBIX server collects aggregate information by doing direct database queries. See ZABBIX Manual.');

insert into help_items values(0,'agent.ping','Check the agent usability. Always return 1. Can be used as a TCP ping.');
insert into help_items values(0,'agent.version','Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1');
insert into help_items values(0,'kernel.maxfiles','Maximum number of opened file supported by OS.');
insert into help_items values(0,'kernel.maxproc','Maximum number of processes supported by OS.');
insert into help_items values(0,'net.if.collisions[if]','Out-of-window collision. Collisions count.');
insert into help_items values(0,'net.if.in[if &lt;,mode&gt;]','Network interfice input statistic. Integer value. If mode is missing &lt;b&gt;bytes&lt;/b&gt; is used.');
insert into help_items values(0,'net.if.out[if &lt;,mode&gt;]','Network interfice output statistic. Integer value. If mode is missing &lt;b&gt;bytes&lt;/b&gt; is used.');
insert into help_items values(0,'net.tcp.dns[ip, zone]','Checks if DNS service is up. 0 - DNS is down, 1 - DNS is up.');
insert into help_items values(0,'net.tcp.listen[port]','Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.');
insert into help_items values(0,'net.tcp.port[&lt;ip&gt;, port]','Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]');
insert into help_items values(0,'net.tcp.service[service &lt;,ip&gt; &lt;,port&gt;]','Check if service server is running and accepting connections. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].');
insert into help_items values(0,'net.tcp.service.perf[service &lt;,ip&gt; &lt;,port&gt;]','Check performance of service server. 0 - service server is down, &lt;sec&gt; - number of seconds spent on connection to the service server. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.');
insert into help_items values(0,'proc.mem[&lt;name&gt; &lt;,user&gt; &lt;,mode&gt;]','Memory used of process with name name running under user &lt;b&gt;user&lt;/b&gt;. Memory used by processes. Process name, user and mode is optional. If name or user is missing all processes will be calculated. If &lt;b&gt;mode&lt;/b&gt; is missing &lt;b&gt;sum&lt;/b&gt; is used.  Examples: proc.mem[,root]');
insert into help_items values(0,'proc.num[&lt;name&gt; &lt;,user&gt; &lt;,state&gt;]','Number of processes with name &lt;b&gt;name&lt;/b&gt; running under user &lt;b&gt;user&lt;/b&gt; having state &lt;b&gt;state&lt;/b&gt;.	Process name, user and state are optional. Example: proc.num[,root]');
insert into help_items values(0,'system.cpu.intr','Device interrupts.');
insert into help_items values(0,'system.cpu.load[&lt;cpu&gt; &lt;,mode&gt;]','CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing &lt;b&gt;all&lt;/b&gt; is used.  If mode is missing &lt;b&gt;avg1&lt;/b&gt; is used. Note that this is not percentage.');
insert into help_items values(0,'system.cpu.switches','Context switches.');
insert into help_items values(0,'system.cpu.util[&lt;cpu&gt; &lt;,type&gt; &lt;,mode&gt;]','CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing &lt;b&gt;all&lt;/b&gt; is used.  If type is missing &lt;b&gt;user&lt;/b&gt; is used. If mode is missing &lt;b&gt;avg1&lt;/b&gt; is used.&lt;/b&gt;');
insert into help_items values(0,'system.hostname','Return host name. String value. Example of returned value: www.zabbix.com');
insert into help_items values(0,'system.localtime','System local time. Time in seconds.');
insert into help_items values(0,'system.swap.in[&lt;swap&gt; &lt;,type&gt;]','Swap in. If type is &lt;b&gt;count&lt;b&gt; - swapins is returned. If type is &lt;b&gt;pages&lt;/b&gt; - pages swapped in is returned.	If swap is missing &lt;b&gt;all&lt;/b&gt; is used.');
insert into help_items values(0,'system.swap.out[&lt;swap&gt; &lt;,type&gt;]','Swap out. If type is &lt;b&gt;count&lt;/b&gt; - swapouts is returned. If type is &lt;b&gt;pages&lt;/b&gt; - pages swapped in is returned.  If swap is missing &lt;b&gt;all&lt;/b&gt; is used.');
insert into help_items values(0,'system.swap.size[&lt;swap&gt; &lt;,mode&gt;]','Swap space.	Number of bytes. If swap is missing &lt;b&gt;all&lt;/b&gt; is used. If mode is missing &lt;b&gt;free&lt;/b&gt; is used.');
insert into help_items values(0,'system.uname','Returns detailed host information. String value');
insert into help_items values(0,'system.uptime','System uptime in seconds.');
insert into help_items values(0,'system.users.num','Number of users connected. Command &lt;b&gt;who&lt;/b&gt; is used on agent side.');
insert into help_items values(0,'vfs.dev.read[device &lt;,type&gt; &lt;,mode&gt;]','Device read statistics.');
insert into help_items values(0,'vfs.dev.write[device &lt;,type&gt; &lt;,mode&gt;]','Device write statistics.');
insert into help_items values(0,'vfs.file.cksum[file]','Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum.	Example: vfs.file.cksum[/etc/passwd]');
insert into help_items values(0,'vfs.file.exists[file]','Check file existance. 0 - file does not exists, 1 - file exists');
insert into help_items values(0,'vfs.file.md5sum[file]','Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/etc/zabbix/zabbix_agentd.conf]');
insert into help_items values(0,'vfs.file.regexp[file, user]','');
insert into help_items values(0,'vfs.file.regmatch[file, user]','');
insert into help_items values(0,'vfs.file.size[file]','Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]');
insert into help_items values(0,'vfs.file.time[file&lt;, mode&gt;]','File time information. Number of seconds.	The mode is optional. If mode is missing &lt;b&gt;modify&lt;/b&gt; is used.');
insert into help_items values(0,'vfs.fs.inode[fs &lt;,mode&gt;]','Number of inodes for a given volume. If mode is missing &lt;b&gt;total&lt;/b&gt; is used.');
insert into help_items values(0,'vfs.fs.size[fs &lt;,mode&gt;]','Calculate disk space for a given volume. Disk space in KB. If mode is missing &lt;b&gt;total&lt;/b&gt; is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].');
insert into help_items values(0,'vm.memory.size[&lt;mode&gt;]','Amount of memory size in bytes. If mode is missing &lt;b&gt;total&lt;/b&gt; is used.');
