#
# Table structure for table 'config'
#

CREATE TABLE config (
  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
  password_required	int(1)		DEFAULT '0' NOT NULL
);

insert into config (smtp_server,smtp_helo,smtp_email) values ('localhost','localhost','zabbix@localhost');

#
# Table structure for table 'groups'
#

CREATE TABLE groups (
  groupid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid),
  UNIQUE (name)
);

insert into groups (groupid,name) values (1,'Administrators');
insert into groups (groupid,name) values (2,'Zabbix user');

#
# Table structure for table 'alerts'
#

CREATE TABLE alerts (
  alertid		int(4)		NOT NULL auto_increment,
  actionid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  type			varchar(10)	DEFAULT '' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (alertid),
  INDEX (actionid),
  KEY clock (clock)
);

#
# Table structure for table 'actions'
#

CREATE TABLE actions (
  actionid		int(4)		NOT NULL auto_increment,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  userid		int(4)		DEFAULT '0' NOT NULL,
  good			int(4)		DEFAULT '0' NOT NULL,
  delay			int(4)		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		varchar(255)	DEFAULT '' NOT NULL,
  nextcheck		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (actionid)
);

#
# Table structure for table 'alarms'
#

CREATE TABLE alarms (
  alarmid		int(4)		NOT NULL auto_increment,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  istrue		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid)
);

#
# Table structure for table 'functions'
#

CREATE TABLE functions (
  functionid int(4) NOT NULL auto_increment,
  itemid int(4) DEFAULT '0' NOT NULL,
  triggerid int(4) DEFAULT '0' NOT NULL,
  lastvalue double(16,4) DEFAULT '0.0000' NOT NULL,
  function varchar(10) DEFAULT '' NOT NULL,
  parameter int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid),
  KEY itemid (itemid),
  KEY triggerid (triggerid),
  UNIQUE itemidfunctionparameter (itemid,function,parameter)
);

#
# Table structure for table 'history'
#

CREATE TABLE history (
  itemid int(4) DEFAULT '0' NOT NULL,
  clock int(4) DEFAULT '0' NOT NULL,
  value double(16,4) DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock)
);

#
# Table structure for table 'hosts'
#

CREATE TABLE hosts (
  hostid int(4) NOT NULL auto_increment,
  platformid int(4) NOT NULL,
  host varchar(64) DEFAULT '' NOT NULL,
  port int(4) DEFAULT '0' NOT NULL,
  status int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid),
  KEY (platformid),
  KEY (status)
);

#
# Table structure for table 'platforms'
#

CREATE TABLE platforms (
  platformid int(4) NOT NULL,
  platform varchar(32) DEFAULT '' NOT NULL,
  PRIMARY KEY (platformid)
);

insert into platforms (platformid,platform)	values (1,'Linux v2.2');
insert into platforms (platformid,platform)	values (20,'HP-UX 10.xx or 11.xx');
insert into platforms (platformid,platform)	values (30,'AIX 4.xx');
insert into platforms (platformid,platform)	values (40,'Open BSD 2.8');
insert into platforms (platformid,platform)	values (100,'MS Windows 98');
insert into platforms (platformid,platform)	values (110,'MS Windows 2000');

#
# Table structure for table 'items_template'
#

CREATE TABLE items_template (
  itemtemplateid int(4) NOT NULL,
  platformid int(4) NOT NULL,
  description varchar(255) DEFAULT '' NOT NULL,
  key_ varchar(64) DEFAULT '' NOT NULL,
  delay int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemtemplateid),
  UNIQUE (platformid, key_),
  KEY (platformid)
);

insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (1,1,'Free memory','memory[free]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (2,1,'Free disk space on /','diskfree[/]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (3,1,'Free disk space on /tmp','diskfree[/tmp]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (4,1,'Free disk space on /usr','diskfree[/usr]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (5,1,'Free number of inodes on /','inodefree[/]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (6,1,'Free number of inodes on /opt','inodefree[/opt]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (7,1,'Free number of inodes on /tmp','inodefree[/tmp]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (8,1,'Free number of inodes on /usr','inodefree[/usr]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (9,1,'Number of processes','system[proccount]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (10,1,'Processor load','system[procload]', 10);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (11,1,'Processor load5','system[procload5]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (12,1,'Processor load15','system[procload15]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (13,1,'Number of running processes','system[procrunning]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (14,1,'Free swap space (Kb)','swap[free]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (16,1,'Size of /var/log/syslog','filesize[/var/log/syslog]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (17,1,'Number of users connected','system[users]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (18,1,'Number of established TCP connections','tcp_count', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (19,1,'Checksum of /etc/inetd.conf','cksum[/etc/inetd_conf]', 600);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (20,1,'Checksum of /vmlinuz','cksum[/vmlinuz]', 600);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (21,1,'Checksum of /etc/passwd','cksum[/etc/passwd]', 600);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (22,1,'Ping of server','ping', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (23,1,'Free disk space on /home','diskfree[/home]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (24,1,'Free number of inodes on /home','inodefree[/home]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (25,1,'Free disk space on /var','diskfree[/var]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (26,1,'Free disk space on /opt','diskfree[/opt]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (27,1,'Host uptime (in sec)','system[uptime]', 300);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (28,1,'Total memory (kB)','memory[total]', 1800);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (29,1,'Shared memory (kB)','memory[shared]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (30,1,'Buffers memory (kB)','memory[buffers]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (31,1,'Cached memory (kB)','memory[cached]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (32,1,'Total swap space (Kb)','swap[total]', 1800);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (33,1,'Amount of memory swapped in from disk (kB/s)','swap[in]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (34,1,'Amount of memory swapped to disk (kB/s)','swap[out]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (35,1,'Blocks sent to a block device (blocks/s)','io[in]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (36,1,'Blocks received from a block device (blocks/s)','io[out]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (37,1,'The number of interrupts per second, including the clock','system[interrupts]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (38,1,'The number of context switches per second','system[switches]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (39,1,'Email (SMTP) server is running','net[listen_25]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (40,1,'FTP server is running','net[listen_21]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (41,1,'SSH server is running','net[listen_22]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (42,1,'Telnet server is running','net[listen_23]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (43,1,'WEB server is running','net[listen_80]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (44,1,'POP3 server is running','net[listen_110]', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (45,1,'IMAP server is running','net[listen_143]', 60);

#
# Table structure for table 'triggers_template'
#

CREATE TABLE triggers_template (
  triggertemplateid int(4) NOT NULL,
  itemtemplateid int(4) NOT NULL,
  description varchar(255) DEFAULT '' NOT NULL,
  expression varchar(255) DEFAULT '' NOT NULL,
  PRIMARY KEY (triggertemplateid),
  KEY (itemtemplateid)
);

insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (1,1,'Lack of free memory','{:.last(0)}<1000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (2,2,'Low free disk space on /','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (3,3,'Low free disk space on /tmp','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (4,4,'Low free disk space on /usr','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (5,5,'Low number of free inodes on /','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (6,6,'Low number of free inodes on /opt','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (7,7,'Low number of free inodes on /tmp','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (8,8,'Low number of free inodes on /usr','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (9,9,'Too many processes running','{:.last(0)}>500');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (10,10,'Processor load is too high','{:.last(0)}>5');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (13,13,'Too many processes running','{:.last(0)}>10');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (14,14,'Lack of free swap space','{:.last(0)}<100000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (17,17,'Too may users connected','{:.last(0)}>50');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (18,18,'Too may established TCP connections','{:.last(0)}>500');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (19,19,'/etc/inetd.conf has been changed','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (20,20,'/vmlinuz has been changed','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (21,21,'/passwd has been changed','{:.diff(0)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (22,22,'No ping from server','{:.nodata(60)}>0');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (23,23,'Low free disk space on /home','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (24,24,'Low number of free inodes on /home','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (25,25,'Low free disk space on /var','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (26,26,'Low free disk space on /opt','{:.last(0)}<1000000000');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (27,27,'Host have just been restarted','{:.last(0)}<600');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (39,39,'Email (SMTP) server is down','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (40,40,'FTP server is down','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (41,41,'SSH server is down','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (42,42,'Telnet server is down','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (43,43,'WEB server is down','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (44,44,'POP3 server is down','{:.last(0)}<1');
insert into triggers_template (triggertemplateid,itemtemplateid,description,expression)
	values (45,45,'IMAP server is down','{:.last(0)}<1');

#
# Table structure for table 'items'
#

CREATE TABLE items (
  itemid int(4) NOT NULL auto_increment,
  hostid int(4) NOT NULL,
  description varchar(255) DEFAULT '' NOT NULL,
  key_ varchar(64) DEFAULT '' NOT NULL,
  delay int(4) DEFAULT '0' NOT NULL,
  history int(4) DEFAULT '0' NOT NULL,
  lastdelete int(4) DEFAULT '0' NOT NULL,
  nextcheck int(4) DEFAULT '0' NOT NULL,
  lastvalue double(16,4) DEFAULT NULL,
  lastclock int(4) DEFAULT NULL,
  prevvalue double(16,4) DEFAULT NULL,
  status int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemid),
  UNIQUE shortname (hostid,key_),
  KEY (hostid)
);

#
# Table structure for table 'media'
#

CREATE TABLE media (
  mediaid int(4) NOT NULL auto_increment,
  userid int(4) DEFAULT '0' NOT NULL,
  type varchar(10) DEFAULT '' NOT NULL,
  sendto varchar(100) DEFAULT '' NOT NULL,
  active int(4) DEFAULT '0' NOT NULL,
  PRIMARY KEY (mediaid)
);

#
# Table structure for table 'triggers'
#

CREATE TABLE triggers (
  triggerid int(4) NOT NULL auto_increment,
  expression varchar(255) DEFAULT '' NOT NULL,
  description varchar(255) DEFAULT '' NOT NULL,
  istrue int(4) DEFAULT '0' NOT NULL,
  lastcheck int(4) DEFAULT '0' NOT NULL,
  priority int(2) DEFAULT '0' NOT NULL,
  lastchange int(4) DEFAULT '0' NOT NULL,
  comments blob,
  PRIMARY KEY (triggerid)
);

#
# Table structure for table 'users'
#

CREATE TABLE users (
  userid		int(4)		NOT NULL auto_increment,
  groupid		int(4)		NOT NULL DEFAULT '0',
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (userid),
  UNIQUE (alias)
);

insert into users (userid,groupid,alias,name,surname,passwd) values (1,1,'Admin','Zabbix','Administrator','');

