alter table users add refresh	int4		DEFAULT '30' NOT NULL;


alter table hosts add serverid int4 DEFAULT '1' NOT NULL AFTER hostid;
alter table hosts add INDEX hosts_server on hosts (serverid);

alter table items add serverid int4 DEFAULT '1' NOT NULL AFTER hostid;
alter table items add INDEX items_server on items (serverid);

--
--  Table structure for table 'servers'
--

CREATE TABLE servers (
      serverid        serial
      host            varchar(64)     DEFAULT '' NOT NULL,
      ip              varchar(15)     DEFAULT '127.0.0.1' NOT NULL,
      port            int4            DEFAULT '0' NOT NULL,
      PRIMARY KEY     (serverid),
      PRIMARY KEY (serverid)
);
insert into servers values(1,'DEFAULT','127.0.0.1',10051);

--
-- Table structure for table 'history_uint'
--

CREATE TABLE history_uint (
  itemid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			double precision	DEFAULT '0' NOT NULL,
--  PRIMARY KEY (itemid,clock),
  FOREIGN KEY (itemid) REFERENCES items
);

CREATE INDEX history_uint_i_c on history_uint (itemid, clock);

alter table graphs_items add  yaxisside		int2		DEFAULT '1' NOT NULL;
