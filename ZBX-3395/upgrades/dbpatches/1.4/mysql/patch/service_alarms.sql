CREATE TABLE service_alarms_tmp (
	servicealarmid		bigint unsigned		DEFAULT '0'	NOT NULL,
	serviceid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (servicealarmid)
) ENGINE=InnoDB;
CREATE INDEX service_alarms_1 on service_alarms_tmp (serviceid,clock);
CREATE INDEX service_alarms_2 on service_alarms_tmp (clock);

insert into service_alarms_tmp select * from service_alarms;
drop table service_alarms;
alter table service_alarms_tmp rename service_alarms;
