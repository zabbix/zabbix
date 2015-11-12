--
-- Table structure for table 'autoreg'
--

CREATE TABLE autoreg (
  id			int(4)		NOT NULL auto_increment,
  priority              int(4)          DEFAULT '0' NOT NULL,
  pattern               varchar(255)    DEFAULT '' NOT NULL,
  hostid                int(4)          DEFAULT '0' NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

alter table alerts add triggerid	int(4)	DEFAULT '0' NOT NULL after actionid;
alter table alerts add repeats		int(4)		DEFAULT '0' NOT NULL;
alter table alerts add maxrepeats	int(4)		DEFAULT '0' NOT NULL;
alter table alerts add nextcheck	int(4)		DEFAULT '0' NOT NULL;
alter table alerts add delay		int(4)		DEFAULT '0' NOT NULL;
alter table actions add maxrepeats	int(4)		DEFAULT '0' NOT NULL;
alter table actions add repeatdelay	int(4)		DEFAULT '600' NOT NULL;
