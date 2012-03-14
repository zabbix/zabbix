CREATE TABLE graphs_tmp (
	graphid		bigint	DEFAULT '0'	NOT NULL,
	name		varchar(128)		DEFAULT ''	NOT NULL,
	width		integer	DEFAULT '0'	NOT NULL,
	height		integer	DEFAULT '0'	NOT NULL,
	yaxistype	integer	DEFAULT '0'	NOT NULL,
	yaxismin	numeric(16,4)		DEFAULT '0'	NOT NULL,
	yaxismax	numeric(16,4)		DEFAULT '0'	NOT NULL,
	templateid	bigint DEFAULT '0'	NOT NULL,
	show_work_period	integer		DEFAULT '1'	NOT NULL,
	show_triggers		integer		DEFAULT '1'	NOT NULL,
	graphtype		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (graphid)
) with OIDS;
CREATE INDEX graphs_graphs_1 on graphs_tmp (name);

insert into graphs_tmp select *,0 from graphs;
drop table graphs;
alter table graphs_tmp rename to graphs;
