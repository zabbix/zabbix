alter table users add refresh	int(4)		DEFAULT '30' NOT NULL;

--
-- Table structure for table 'history_uint'
--

CREATE TABLE history_uint (
  itemid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			bigint unsigned	DEFAULT '0' NOT NULL,
--  PRIMARY KEY (itemid,clock)
  KEY itemidclock (itemid, clock)
) ENGINE=InnoDB;

alter table graphs_items add  yaxisside		int(1)		DEFAULT '1' NOT NULL;
alter table config add refresh_unsupported  int(4)          DEFAULT '600' NOT NULL;
