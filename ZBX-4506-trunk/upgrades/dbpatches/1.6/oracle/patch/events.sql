CREATE TABLE events_tmp (
	eventid		number(20)		DEFAULT '0'	NOT NULL,
	source		number(10)		DEFAULT '0'	NOT NULL,
	object		number(10)		DEFAULT '0'	NOT NULL,
	objectid		number(20)		DEFAULT '0'	NOT NULL,
	clock		number(10)		DEFAULT '0'	NOT NULL,
	value		number(10)		DEFAULT '0'	NOT NULL,
	acknowledged		number(10)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (eventid)
);
insert into events_tmp select eventid,source,object,objectid,clock,value,acknowledged from events;
drop table events;
alter table events_tmp rename to events;
CREATE INDEX events_1 on events (object,objectid,eventid);
CREATE INDEX events_2 on events (clock);
