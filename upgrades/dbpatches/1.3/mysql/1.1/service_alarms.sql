CREATE TABLE service_alarms (
  servicealarmid	int(4)		NOT NULL auto_increment,
  serviceid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid),
  KEY (serviceid,clock),
  KEY (clock)
) type=InnoDB;
