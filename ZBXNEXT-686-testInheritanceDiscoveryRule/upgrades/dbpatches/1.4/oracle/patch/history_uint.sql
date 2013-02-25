CREATE TABLE history_uint_tmp (
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	value           number(20)              DEFAULT '0'     NOT NULL
);
CREATE INDEX history_uint_1 on history_uint_tmp (itemid,clock);

insert into history_uint_tmp select * from history_uint;
drop table history_uint;
alter table history_uint_tmp rename to history_uint;
