CREATE TABLE history_text_tmp (
	id		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	value		text		DEFAULT ''	NOT NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB;
CREATE INDEX history_text_1 on history_text_tmp (itemid,clock);

insert into history_text_tmp select * from history_text;
drop table history_text;
alter table history_text_tmp rename history_text;
