CREATE TABLE history_str_tmp (
	itemid		bigint DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL
) with OIDS;
CREATE INDEX history_str_1 on history_str_tmp (itemid,clock);

insert into history_str_tmp select * from history_str;
drop table history_str;
alter table history_str_tmp rename to history_str;
