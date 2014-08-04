CREATE TABLE history_tmp (
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	value           number(20,4)            DEFAULT '0.0000'        NOT NULL
);
CREATE INDEX history_1 on history_tmp (itemid,clock);

insert into history_tmp select * from history;
drop table history;
alter table history_tmp rename to history;
