CREATE TABLE events (
	eventid		bigint unsigned		DEFAULT '0'	NOT NULL,
	source		integer		DEFAULT '0'	NOT NULL,
	object		integer		DEFAULT '0'	NOT NULL,
	objectid	bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	acknowledged		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (eventid)
) ENGINE=InnoDB;
CREATE INDEX events_1 on events (object,objectid,clock);
CREATE INDEX events_2 on events (clock);

insert into events select alarmid,0,0,triggerid,clock,value,acknowledged from alarms;
drop table alarms;
