--
-- Table structure for table 'platforms'
--

CREATE TABLE platforms (
  platformid		serial,
  platform		varchar(32)	DEFAULT '' NOT NULL,
  PRIMARY KEY (platformid)
);

insert into platforms (platformid,platform)	values (1,'Linux v2.2');
insert into platforms (platformid,platform)	values (20,'HP-UX 10.xx or 11.xx');
insert into platforms (platformid,platform)	values (30,'AIX 4.xx');
insert into platforms (platformid,platform)	values (40,'Open BSD 2.8');
insert into platforms (platformid,platform)	values (100,'MS Windows 98');
insert into platforms (platformid,platform)	values (110,'MS Windows 2000');

--
-- Table structure for table 'hosts'
--

CREATE TABLE hosts (
  hostid		serial,
  platformid		int4		NOT NULL,
  host			varchar(64)	DEFAULT '' NOT NULL,
  port			int4		DEFAULT '0' NOT NULL,
  status		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (hostid),
  FOREIGN KEY (platformid) REFERENCES platforms
);

CREATE INDEX hosts_platformid on hosts (platformid);
CREATE INDEX hosts_status on hosts (status);

--
-- Table structure for table 'items'
--

CREATE TABLE items (
  itemid		serial,
  hostid		int4		NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  key_			varchar(64)	DEFAULT '' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  history		int4		DEFAULT '0' NOT NULL,
  lastdelete		int4		DEFAULT '0' NOT NULL,
  nextcheck		int4		DEFAULT '0' NOT NULL,
  lastvalue		float8		DEFAULT NULL,
  lastclock		int4		DEFAULT NULL,
  prevvalue		float8		DEFAULT NULL,
  status		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemid),
  FOREIGN KEY (hostid) REFERENCES hosts
);

CREATE UNIQUE INDEX items_hostid_key on items (hostid,key_);
CREATE INDEX items_hostid on items (hostid);

--
-- Table structure for table 'config'
--

CREATE TABLE config (
  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
  password_required	int4		DEFAULT '0' NOT NULL
);

insert into config (smtp_server,smtp_helo,smtp_email) values ('localhost','localhost','zabbix@localhost');

--
-- Table structure for table 'groups'
--

CREATE TABLE groups (
  groupid		serial		NOT NULL,
  name			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (groupid)
);

insert into groups (groupid,name) values (1,'Administrators');
insert into groups (groupid,name) values (2,'Zabbix user');


--
-- Table structure for table 'triggers'
--

CREATE TABLE triggers (
  triggerid		serial,
  expression		varchar(255)	DEFAULT '' NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  istrue		int4		DEFAULT '0' NOT NULL,
  lastcheck		int4		DEFAULT '0' NOT NULL,
  priority		int2		DEFAULT '0' NOT NULL,
  lastchange		int4		DEFAULT '0' NOT NULL,
  comments		text,
  PRIMARY KEY (triggerid)
);

--
-- Table structure for table 'users'
--

CREATE TABLE users (
  userid		serial,
  groupid		int4		NOT NULL DEFAULT '0',
  alias			varchar(100)	DEFAULT '' NOT NULL,
  name			varchar(100)	DEFAULT '' NOT NULL,
  surname		varchar(100)	DEFAULT '' NOT NULL,
  passwd		varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (userid),
  FOREIGN KEY (groupid) REFERENCES groups
);

CREATE UNIQUE INDEX users_alias on users (alias);

insert into users (userid,groupid,alias,name,surname,passwd) values (1,1,'Admin','Zabbix','Administrator','');

--
-- Table structure for table 'actions'
--

CREATE TABLE actions (
  actionid		serial,
  triggerid		int4		DEFAULT '0' NOT NULL,
  userid		int4		DEFAULT '0' NOT NULL,
  good			int4		DEFAULT '0' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		varchar(255)	DEFAULT '' NOT NULL,
  nextcheck		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (actionid),
  FOREIGN KEY (triggerid) REFERENCES triggers,
  FOREIGN KEY (userid) REFERENCES users
);

--
-- Table structure for table 'alerts'
--

CREATE TABLE alerts (
  alertid		serial,
  actionid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  type			varchar(10)	DEFAULT '' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  subject		varchar(255)	DEFAULT '' NOT NULL,
  message		varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (alertid),
  FOREIGN KEY (actionid) REFERENCES actions
);

CREATE INDEX alerts_actionid on alerts (actionid);
CREATE INDEX alerts_clock on alerts (clock);

--
-- Table structure for table 'alarms'
--

CREATE TABLE alarms (
  alarmid		serial,
  triggerid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  istrue		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid),
  FOREIGN KEY (triggerid) REFERENCES triggers
);

--
-- Table structure for table 'functions'
--

CREATE TABLE functions (
  functionid		serial,
  itemid		int4		DEFAULT '0' NOT NULL,
  triggerid		int4		DEFAULT '0' NOT NULL,
  lastvalue		float8		DEFAULT '0.0000' NOT NULL,
  function		varchar(10)	DEFAULT '' NOT NULL,
  parameter		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (functionid),
  FOREIGN KEY (itemid) REFERENCES items,
  FOREIGN KEY (triggerid) REFERENCES triggers
);

CREATE INDEX functions_itemid on functions (itemid);
CREATE INDEX funtions_triggerid on functions (triggerid);
CREATE UNIQUE INDEX functions_i_f_p on functions (itemid,function,parameter);

--
-- Table structure for table 'history'
--

CREATE TABLE history (
  itemid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			float8		DEFAULT '0.0000' NOT NULL,
  PRIMARY KEY (itemid,clock),
  FOREIGN KEY (itemid) REFERENCES items
);


--
-- Table structure for table 'items_template'
--

CREATE TABLE items_template (
  itemtemplateid	int4		NOT NULL,
  platformid		int4		NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  key_			varchar(64)	DEFAULT '' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (itemtemplateid),
  FOREIGN KEY (platformid) REFERENCES platforms
);

CREATE UNIQUE INDEX items_template_p_k on items_template (platformid, key_);
CREATE INDEX items_template_itemid on items_template (platformid);

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
	values (9,1,'Number of processes','proccount', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (10,1,'Processor load','procload', 10);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (11,1,'Processor load5','procload5', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (12,1,'Processor load15','procload15', 60);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (13,1,'Number of running processes','procrunning', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (14,1,'Free swap space (Kb)','swap[free]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (16,1,'Size of /var/log/syslog','filesize[/var/log/syslog]', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (17,1,'Number of users connected','users', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (18,1,'Number of established TCP connections','tcp_count', 30);
insert into items_template (itemtemplateid,platformid,description,key_,delay)
	values (19,1,'Checksum of /etc/inetd.conf','cksum[/etc/inetd.conf]', 600);
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
	values (27,1,'Host uptime (in sec)','uptime', 300);
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

--
-- Table structure for table 'triggers_template'
--

CREATE TABLE triggers_template (
  triggertemplateid	int4		NOT NULL,
  itemtemplateid	int4		NOT NULL,
  description		varchar(255)	DEFAULT '' NOT NULL,
  expression		varchar(255)	DEFAULT '' NOT NULL,
  PRIMARY KEY (triggertemplateid),
  FOREIGN KEY (itemtemplateid) REFERENCES items_template
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

--
-- Table structure for table 'media'
--

CREATE TABLE media (
  mediaid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
  type			varchar(10)	DEFAULT '' NOT NULL,
  sendto		varchar(100)	DEFAULT '' NOT NULL,
  active		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (mediaid),
  FOREIGN KEY (userid) REFERENCES users
);
