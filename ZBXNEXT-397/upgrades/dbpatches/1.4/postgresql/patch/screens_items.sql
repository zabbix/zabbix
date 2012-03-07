CREATE TABLE screens_items_tmp (
	screenitemid	bigint DEFAULT '0'	NOT NULL,
	screenid	bigint DEFAULT '0'	NOT NULL,
	resourcetype	integer		DEFAULT '0'	NOT NULL,
	resourceid	bigint DEFAULT '0'	NOT NULL,
	width		integer		DEFAULT '320'	NOT NULL,
	height		integer		DEFAULT '200'	NOT NULL,
	x		integer		DEFAULT '0'	NOT NULL,
	y		integer		DEFAULT '0'	NOT NULL,
	colspan		integer		DEFAULT '0'	NOT NULL,
	rowspan		integer		DEFAULT '0'	NOT NULL,
	elements	integer		DEFAULT '25'	NOT NULL,
	valign		integer		DEFAULT '0'	NOT NULL,
	halign		integer		DEFAULT '0'	NOT NULL,
	style		integer		DEFAULT '0'	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (screenitemid)
) with OIDS;

insert into screens_items_tmp select NULL,screenid,resourcetype,resourceid,width,height,x,y,colspan,rowspan,elements,valign,halign,style,url from screens_items;
drop table screens_items;
alter table screens_items_tmp rename to screens_items;

CREATE TABLE screens_items_tmp (
	screenitemid	bigint DEFAULT '0'	NOT NULL,
	screenid	bigint DEFAULT '0'	NOT NULL,
	resourcetype	integer		DEFAULT '0'	NOT NULL,
	resourceid	bigint DEFAULT '0'	NOT NULL,
	width		integer		DEFAULT '320'	NOT NULL,
	height		integer		DEFAULT '200'	NOT NULL,
	x		integer		DEFAULT '0'	NOT NULL,
	y		integer		DEFAULT '0'	NOT NULL,
	colspan		integer		DEFAULT '0'	NOT NULL,
	rowspan		integer		DEFAULT '0'	NOT NULL,
	elements	integer		DEFAULT '25'	NOT NULL,
	valign		integer		DEFAULT '0'	NOT NULL,
	halign		integer		DEFAULT '0'	NOT NULL,
	style		integer		DEFAULT '0'	NOT NULL,
	url		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (screenitemid)
) with OIDS;

insert into screens_items_tmp select * from screens_items;
drop table screens_items;
alter table screens_items_tmp rename to screens_items;
