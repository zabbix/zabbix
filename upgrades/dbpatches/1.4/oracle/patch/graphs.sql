CREATE TABLE graphs_tmp (
	graphid         number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(128)           DEFAULT ''      ,
	width           number(10)              DEFAULT '0'     NOT NULL,
	height          number(10)              DEFAULT '0'     NOT NULL,
	yaxistype               number(10)              DEFAULT '0'     NOT NULL,
	yaxismin                number(20,4)            DEFAULT '0'     NOT NULL,
	yaxismax                number(20,4)            DEFAULT '0'     NOT NULL,
	templateid              number(20)              DEFAULT '0'     NOT NULL,
	show_work_period                number(10)              DEFAULT '1'     NOT NULL,
	show_triggers           number(10)              DEFAULT '1'     NOT NULL,
	graphtype               number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (graphid)
);
CREATE INDEX graphs_graphs_1 on graphs_tmp (name);

insert into graphs_tmp select graphid,name,width,height,yaxistype,yaxismin,yaxismax,templateid,show_work_period,show_triggers,0 from graphs;
drop trigger graphs_trigger;
drop sequence graphs_graphid;
drop table graphs;
alter table graphs_tmp rename to graphs;
