CREATE UNIQUE INDEX hosts_host on hosts (host);

--
-- Table structure for table 'profiles'
--

CREATE TABLE profiles (
  profileid		serial,
  userid		int4		DEFAULT '0' NOT NULL,
  idx			varchar(64)	DEFAULT '' NOT NULL,
  value			varchar(64)	DEFAULT '' NOT NULL,
  PRIMARY KEY (profileid)
);

CREATE INDEX profiles_userid on profiles (userid);
CREATE UNIQUE INDEX profiles_userid_idx on profiles (userid,idx);

alter table items add snmp_port int4 DEFAULT '161' NOT NULL;
alter table services add showsla int4 DEFAULT '0' NOT NULL;
alter table services add goodsla int4 DEFAULT '99.9' NOT NULL;
