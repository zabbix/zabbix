alter table screens_items add	rowspan		int(4)	DEFAULT '0' NOT NULL;

--
-- Table structure for table 'escalation_rules'
--

CREATE TABLE escalation_rules (
  ruleid		int(4)		NOT NULL auto_increment,
  escalationid		int(4)		DEFAULT '0' NOT NULL,
  level			int(4)		DEFAULT '0' NOT NULL,
  actiontype		int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (ruleid),
  KEY (escalationid)
) type=InnoDB;
