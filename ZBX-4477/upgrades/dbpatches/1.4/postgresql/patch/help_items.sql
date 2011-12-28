CREATE TABLE help_items_tmp (
	itemtype	integer		DEFAULT '0'	NOT NULL,
	key_		varchar(255)		DEFAULT ''	NOT NULL,
	description	varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (itemtype,key_)
) with OIDS;

insert into help_items_tmp select * from help_items;
drop table help_items;
alter table help_items_tmp rename to help_items;
