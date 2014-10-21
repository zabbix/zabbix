CREATE TABLE history_tmp (
	itemid		bigint	DEFAULT '0'	NOT NULL,
	clock		integer	DEFAULT '0'	NOT NULL,
	value		numeric(16,4)	DEFAULT '0.0000'	NOT NULL
) with OIDS;
CREATE INDEX history_1 on history_tmp (itemid,clock);

insert into history_tmp select * from history;
drop table history;
alter table history_tmp rename to history;
