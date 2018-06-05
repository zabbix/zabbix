alter table screens_items add	rowspan		int(4)	DEFAULT '0' NOT NULL;
alter table users add	autologout		int(4)	DEFAULT '900' NOT NULL;
alter table users add	lang			varchar(5)	DEFAULT 'en_gb' NOT NULL;

drop table if exists escalation_rules;
drop table escalations;

--
-- Table structure for table 'escalations'
--

CREATE TABLE escalations (
  escalationid		int(4)		NOT NULL auto_increment,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  dflt			int(2)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid),
  UNIQUE (name)
) ENGINE=InnoDB;

--
-- Table structure for table 'escalation_rules'
--

CREATE TABLE escalation_rules (
  escalationruleid		int(4)		NOT NULL auto_increment,
  escalationid		int(4)		DEFAULT '0' NOT NULL,
  level			int(4)		DEFAULT '0' NOT NULL,
  period		varchar(100)	DEFAULT '1-7,00:00-23:59' NOT NULL,
  delay			int(4)		DEFAULT '0' NOT NULL,
  actiontype		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationruleid),
  KEY (escalationid)
) ENGINE=InnoDB;

--
-- Table structure for table 'escalation_log'
--

CREATE TABLE escalation_log (
  escalationlogid       int(4)          NOT NULL auto_increment,
  triggerid             int(4)          DEFAULT '0' NOT NULL,
  alarmid               int(4)          DEFAULT '0' NOT NULL,
  escalationid          int(4)          DEFAULT '0' NOT NULL,
  actiontype            int(4)          DEFAULT '0' NOT NULL,
  level                 int(4)          DEFAULT '0' NOT NULL,
  adminlevel            int(4)          DEFAULT '0' NOT NULL,
  nextcheck             int(4)          DEFAULT '0' NOT NULL,
  status                int(4)          DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationlogid),
  KEY (alarmid,escalationid),
  KEY (triggerid)
) ENGINE=InnoDB;
