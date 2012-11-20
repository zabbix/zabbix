alter table history_log add timestamp int(4) DEFAULT '0' NOT NULL;
alter table history_log add source varchar(64) DEFAULT '' NOT NULL;
alter table history_log add severity int(4) DEFAULT '0' NOT NULL;

alter table history_log modify value text default '' not null;

--
-- Table structure for table 'hosts_profiles'
--

CREATE TABLE hosts_profiles (
  hostid                int(4)          DEFAULT '0' NOT NULL,
  devicetype            varchar(64)     DEFAULT '' NOT NULL,
  name                  varchar(64)     DEFAULT '' NOT NULL,
  os                    varchar(64)     DEFAULT '' NOT NULL,
  serialno              varchar(64)     DEFAULT '' NOT NULL,
  tag                   varchar(64)     DEFAULT '' NOT NULL,
  macaddress            varchar(64)     DEFAULT '' NOT NULL,
  hardware              blob            DEFAULT '' NOT NULL,
  software              blob            DEFAULT '' NOT NULL,
  contact               blob            DEFAULT '' NOT NULL,
  location              blob            DEFAULT '' NOT NULL,
  notes                 blob            DEFAULT '' NOT NULL,
  PRIMARY KEY (hostid)
) ENGINE=InnoDB;
