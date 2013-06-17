CREATE TABLE service_alarms_tmp (
	servicealarmid	number(20)	DEFAULT '0'	NOT NULL,
	serviceid	number(20)	DEFAULT '0'	NOT NULL,
	clock		number(10)	DEFAULT '0'	NOT NULL,
	value		number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (servicealarmid)
);
CREATE INDEX service_alarms_1 on service_alarms_tmp (serviceid,clock);
CREATE INDEX service_alarms_2 on service_alarms_tmp (clock);

insert into service_alarms_tmp select * from service_alarms;
drop trigger service_alarms_trigger;
drop sequence service_alarms_servicealarmid;
drop table service_alarms;
alter table service_alarms_tmp rename to service_alarms;
