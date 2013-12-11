CREATE TABLE graphs_items_tmp (
	gitemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	graphid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	drawtype		integer		DEFAULT '0'	NOT NULL,
	sortorder		integer		DEFAULT '0'	NOT NULL,
	color		varchar(32)		DEFAULT 'Dark Green'	NOT NULL,
	yaxisside		integer		DEFAULT '1'	NOT NULL,
	calc_fnc		integer		DEFAULT '2'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	periods_cnt		integer		DEFAULT '5'	NOT NULL,
	PRIMARY KEY (gitemid)
) ENGINE=InnoDB;

insert into graphs_items_tmp select gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type,periods_cnt from graphs_items;
drop table graphs_items;
alter table graphs_items_tmp rename graphs_items;
