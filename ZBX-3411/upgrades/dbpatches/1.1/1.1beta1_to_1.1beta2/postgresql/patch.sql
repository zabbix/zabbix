--
-- Table structure for table 'autoreg'
--

CREATE TABLE autoreg (
  id                    serial,
  priority              int4            DEFAULT '0' NOT NULL,
  pattern               varchar(255)    DEFAULT '' NOT NULL,
  hostid                int4            DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
);

alter table alerts add triggerid		int4          DEFAULT '0' NOT NULL after actionid;

alter table alerts add repeats			int4		DEFAULT '0' NOT NULL;
alter table alerts add maxrepeats		int4		DEFAULT '0' NOT NULL;
alter table alerts add nextcheck		int4		DEFAULT '0' NOT NULL;
alter table alerts add delay			int4		DEFAULT '0' NOT NULL;

alter table actions add maxrepeats	int4		DEFAULT '0' NOT NULL;
alter table actions add repeatdelay	int4		DEFAULT '600' NOT NULL;
