CREATE TABLE screens_items_tmp (
	screenitemid            number(20)              DEFAULT '0'     NOT NULL,
	screenid                number(20)              DEFAULT '0'     NOT NULL,
	resourcetype            number(10)              DEFAULT '0'     NOT NULL,
	resourceid              number(20)              DEFAULT '0'     NOT NULL,
	width           number(10)              DEFAULT '320'   NOT NULL,
	height          number(10)              DEFAULT '200'   NOT NULL,
	x               number(10)              DEFAULT '0'     NOT NULL,
	y               number(10)              DEFAULT '0'     NOT NULL,
	colspan         number(10)              DEFAULT '0'     NOT NULL,
	rowspan         number(10)              DEFAULT '0'     NOT NULL,
	elements                number(10)              DEFAULT '25'    NOT NULL,
	valign          number(10)              DEFAULT '0'     NOT NULL,
	halign          number(10)              DEFAULT '0'     NOT NULL,
	style           number(10)              DEFAULT '0'     NOT NULL,
	url             varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (screenitemid)
);

insert into screens_items_tmp select * from screens_items;
drop trigger screens_items_trigger;
drop sequence screens_items_screenid;
drop table screens_items;
alter table screens_items_tmp rename to screens_items;
