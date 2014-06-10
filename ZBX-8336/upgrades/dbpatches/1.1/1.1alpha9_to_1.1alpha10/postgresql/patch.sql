--
-- Table structure for table 'history_log'
--

CREATE TABLE history_log (
  id			serial,
  itemid                int4            DEFAULT '0' NOT NULL,
  clock                 int4            DEFAULT '0' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (itemid) REFERENCES items
);

CREATE INDEX history_log_i_c on history_str (itemid, clock);

alter table media add	period		varchar(100)	DEFAULT '1-7,00:00-23:59' NOT NULL;
alter table screens_items add	colspan		int4	DEFAULT '0' NOT NULL;
alter table items add	lastlogsize		int4	DEFAULT '0' NOT NULL;
