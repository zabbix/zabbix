CREATE TABLE help_items_tmp (
	itemtype                number(10)              DEFAULT '0'     NOT NULL,
	key_            varchar2(255)           DEFAULT ''      ,
	description             varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (itemtype,key_)
);

insert into help_items_tmp select * from help_items;
drop table help_items;
alter table help_items_tmp rename to help_items;
