alter table items add value_type int(4) DEFAULT '0' NOT NULL;

CREATE TABLE history_str (
  itemid int(4) DEFAULT '0' NOT NULL,
  clock int(4) DEFAULT '0' NOT NULL,
  value varchar(255) DEFAULT '' NOT NULL,
  PRIMARY KEY (itemid,clock)
);
