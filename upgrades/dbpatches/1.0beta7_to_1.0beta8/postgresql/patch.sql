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
