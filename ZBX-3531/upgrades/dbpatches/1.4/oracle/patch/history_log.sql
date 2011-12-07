CREATE TABLE history_log_tmp (
	id              number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	timestamp               number(10)              DEFAULT '0'     NOT NULL,
	source          varchar2(64)            DEFAULT ''      ,
	severity                number(10)              DEFAULT '0'     NOT NULL,
	value           varchar2(2048)          DEFAULT ''      ,
	PRIMARY KEY (id)
);
CREATE INDEX history_log_1 on history_log_tmp (itemid,clock);

insert into history_log_tmp select id,itemid,clock,timestamp,source,severity,value from history_log;
drop trigger history_log_trigger;
drop sequence history_log_id;
drop table history_log;
alter table history_log_tmp rename to history_log;
