alter table users add refresh	int(4)		DEFAULT '30' NOT NULL;


alter table hosts add serverid int(4) DEFAULT '1' NOT NULL AFTER hostid;
alter table hosts add KEY (serverid);

alter table items add serverid int(4) DEFAULT '1' NOT NULL AFTER hostid;
alter table items add KEY (serverid);

--
--  Table structure for table 'servers'
--

CREATE TABLE servers (
      serverid        int(4)          NOT NULL auto_increment,
      host            varchar(64)     DEFAULT '' NOT NULL,
      ip              varchar(15)     DEFAULT '127.0.0.1' NOT NULL,
      port            int(4)          DEFAULT '0' NOT NULL,
      PRIMARY KEY     (serverid),
      UNIQUE          (host)
) type=InnoDB;
insert into servers values(1,'DEFAULT','127.0.0.1',10051);

