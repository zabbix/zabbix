alter table functions alter lastvalue varchar(255);
alter table functions alter parameter varchar(255) default '0' not null;

--
-- Table structure for table 'services_alarms'
--

CREATE TABLE service_alarms (
  servicealarmid	serial,
  serviceid		int4		DEFAULT '0' NOT NULL,
  clock			int4		DEFAULT '0' NOT NULL,
  value			int4		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid)
);

CREATE INDEX services_alarms_serviceid_clock on service_alarms (serviceid,clock);
CREATE INDEX services_alarms_clock on service_alarms (clock);

