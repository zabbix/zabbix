CREATE TABLE alarms (
  alarmid		int(4)		NOT NULL auto_increment,
  triggerid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  acknowledged		int(1)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (alarmid),
  KEY (triggerid,clock),
  KEY (clock)
) type=InnoDB;
