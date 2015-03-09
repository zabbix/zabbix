CREATE TABLE history_uint_tmp (
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		bigint unsigned		DEFAULT '0'	NOT NULL
) ENGINE=InnoDB;
CREATE INDEX history_uint_1 on history_uint_tmp (itemid,clock);

insert into history_uint_tmp select * from history_uint;
drop table history_uint;
alter table history_uint_tmp rename history_uint;
