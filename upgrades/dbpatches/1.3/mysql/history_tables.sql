CREATE TABLE alerts_tmp (
	alertid		bigint unsigned		DEFAULT '0'	NOT NULL,
	actionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	sendto		varchar(100)		DEFAULT ''	NOT NULL,
	subject		varchar(255)		DEFAULT ''	NOT NULL,
	message		blob		DEFAULT ''	NOT NULL,
	status		integer		DEFAULT '0'	NOT NULL,
	retries		integer		DEFAULT '0'	NOT NULL,
	error		varchar(128)		DEFAULT ''	NOT NULL,
	repeats		integer		DEFAULT '0'	NOT NULL,
	maxrepeats		integer		DEFAULT '0'	NOT NULL,
	nextcheck		integer		DEFAULT '0'	NOT NULL,
	delay		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (alertid)
);
CREATE INDEX alerts_1 on alerts_tmp (actionid);
CREATE INDEX alerts_2 on alerts_tmp (clock);
CREATE INDEX alerts_3 on alerts_tmp (triggerid);
CREATE INDEX alerts_4 on alerts_tmp (status,retries);
CREATE INDEX alerts_5 on alerts_tmp (mediatypeid);
CREATE INDEX alerts_6 on alerts_tmp (userid);

insert into alerts_tmp select * from alerts;
drop table alerts;
alter table alerts_tmp rename alerts;

CREATE TABLE events (
	eventid		bigint unsigned		DEFAULT '0'	NOT NULL,
	triggerid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		integer		DEFAULT '0'	NOT NULL,
	acknowledged		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (eventid)
);
CREATE INDEX events_1 on events (triggerid,clock);
CREATE INDEX events_2 on events (clock);

insert into events select * from alarms;
drop table alarms;

CREATE TABLE history_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		double(16,4)		DEFAULT '0.0000'	NOT NULL
);
CREATE INDEX history_1 on history_tmp (itemid,clock);

insert into history_tmp select * from history;
drop table history;
alter table history_tmp rename history;

CREATE TABLE history_uint_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint unsigned		DEFAULT '0'	NOT NULL
);
CREATE INDEX history_uint_1 on history_uint_tmp (itemid,clock);

insert into history_uint_tmp select * from history_uint;
drop table history_uint;
alter table history_uint_tmp rename history_uint;

CREATE TABLE history_str_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL
);
CREATE INDEX history_str_1 on history_str_tmp (itemid,clock);

insert into history_str_tmp select * from history_str;
drop table history_str;
alter table history_str_tmp rename history_str;

CREATE TABLE history_log_tmp (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	timestamp		integer		DEFAULT '0'	NOT NULL,
	source		varchar(64)		DEFAULT ''	NOT NULL,
	severity		integer		DEFAULT '0'	NOT NULL,
	value		text		DEFAULT ''	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_log_1 on history_log_tmp (itemid,clock);

insert into history_log_tmp select * from history_log;
drop table history_log;
alter table history_log_tmp rename history_log;

CREATE TABLE history_text_tmp (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		text		DEFAULT ''	NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_text_1 on history_text_tmp (itemid,clock);

insert into history_text_tmp select * from history_text;
drop table history_text;
alter table history_text_tmp rename history_text;

CREATE TABLE trends_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	num		integer		DEFAULT '0'	NOT NULL,
	value_min		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	value_avg		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	value_max		double(16,4)		DEFAULT '0.0000'	NOT NULL,
	PRIMARY KEY (itemid,clock)
);

insert into trends_tmp select * from trends;
drop table trends;
alter table trends_tmp rename trends;
