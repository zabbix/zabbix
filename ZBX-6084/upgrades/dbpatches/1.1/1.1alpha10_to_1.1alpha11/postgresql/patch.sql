alter table screens_items add	rowspan		int4	DEFAULT '0' NOT NULL;
alter table users add	autologout		int4	DEFAULT '900' NOT NULL;
alter table users add	lang			varchar(5)	DEFAULT 'en_gb' NOT NULL;

drop table if exists escalation_rules;
drop table escalations;

--
-- Table structure for table 'escalations'
--

CREATE TABLE escalations (
  escalationid		serial,
  name			varchar(64)	DEFAULT '0' NOT NULL,
  dflt			int2		DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationid)
);

CREATE UNIQUE INDEX escalations_name on escalations (name);

--
-- Table structure for table 'escalation_rules'
--

CREATE TABLE escalation_rules (
  escalationruleid	serial,
  escalationid		int4		DEFAULT '0' NOT NULL,
  level			int4		DEFAULT '0' NOT NULL,
  period		varchar(100)	DEFAULT '1-7,00:00-23:59' NOT NULL,
  delay			int4		DEFAULT '0' NOT NULL,
  actiontype		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationruleid),
  FOREIGN KEY (escalationid) REFERENCES escalations
);

--
-- Table structure for table 'escalation_log'
--

CREATE TABLE escalation_log (
  escalationlogid       serial,
  triggerid             int4            DEFAULT '0' NOT NULL,
  alarmid               int4            DEFAULT '0' NOT NULL,
  escalationid          int4            DEFAULT '0' NOT NULL,
  actiontype            int4            DEFAULT '0' NOT NULL,
  level                 int4            DEFAULT '0' NOT NULL,
  adminlevel            int4            DEFAULT '0' NOT NULL,
  nextcheck             int4            DEFAULT '0' NOT NULL,
  status                int4            DEFAULT '0' NOT NULL,
  PRIMARY KEY (escalationlogid)
);

CREATE INDEX escalations_log_alarmid_escalationid on escalations_log (alarmid,escalationid);
CREATE INDEX escalations_log_triggerid on escalations_log (triggerid);
