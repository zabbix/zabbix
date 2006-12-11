create unique index hosts_host on hosts (host);

--
-- Table structure for table 'profiles'
--

CREATE TABLE profiles (
  profileid		int(4)		NOT NULL auto_increment,
  userid		int(4)		DEFAULT '0' NOT NULL,
  idx			varchar(64)	DEFAULT '' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (profileid),
  KEY (userid),
  UNIQUE (userid,idx)
) type=InnoDB;

alter table items add snmp_port	int(4) DEFAULT '161' NOT NULL;
alter table services add showsla int(1) DEFAULT '0' NOT NULL;
alter table services add goodsla int(4) DEFAULT '99.9' NOT NULL;

