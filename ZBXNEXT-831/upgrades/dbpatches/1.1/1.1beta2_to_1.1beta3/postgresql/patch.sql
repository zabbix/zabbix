alter table users add refresh	int4		DEFAULT '30' NOT NULL;

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
alter table config add refresh_unsupported  int4          DEFAULT '0' NOT NULL;
