alter table functions modify lastvalue varchar(255);
alter table functions modify parameter varchar(255) default '0' not null;

--
-- Table structure for table 'service_alarms'
--

CREATE TABLE service_alarms (
  servicealarmid	int(4)		NOT NULL auto_increment,
  serviceid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid),
  KEY (serviceid,clock),
  KEY (clock)
);

