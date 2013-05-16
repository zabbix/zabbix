CREATE TABLE history_str_tmp (
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	value           varchar2(255)           DEFAULT ''
);
CREATE INDEX history_str_1 on history_str_tmp (itemid,clock);

insert into history_str_tmp select * from history_str;
drop table history_str;
alter table history_str_tmp rename to history_str;
