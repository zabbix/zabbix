alter table screens_items add	rowspan		int4	DEFAULT '0' NOT NULL;

--
-- Table structure for table 'escalation_rules'
--

CREATE TABLE escalation_rules (
  ruleid		serial,
  escalationid		int4		DEFAULT '0' NOT NULL,
  level			int4		DEFAULT '0' NOT NULL,
  actiontype		int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (ruleid),
  FOREIGN KEY (escalationid) REFERENCES escalations
);
