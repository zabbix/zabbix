CREATE TABLE history_uint_tmp (
	itemid		bigint DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint DEFAULT '0'	NOT NULL
) with OIDS;
CREATE INDEX history_uint_1 on history_uint_tmp (itemid,clock);

insert into history_uint_tmp select * from history_uint;
drop table history_uint;
alter table history_uint_tmp rename to history_uint;
