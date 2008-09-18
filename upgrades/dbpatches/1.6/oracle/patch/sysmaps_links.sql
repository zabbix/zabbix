CREATE TABLE sysmaps_link_triggers (
	linktriggerid		number(20)		DEFAULT '0'	NOT NULL,
	linkid		number(20)		DEFAULT '0'	NOT NULL,
	triggerid		number(20)		DEFAULT '0'	NOT NULL,
	drawtype		number(10)		DEFAULT '0'	NOT NULL,
	color		varchar2(6)		DEFAULT '000000'	,
	PRIMARY KEY (linktriggerid)
);
CREATE UNIQUE INDEX sysmaps_link_triggers_1 on sysmaps_link_triggers (linkid,triggerid);
insert into sysmaps_link_triggers select linkid,linkid,triggerid,drawtype_on,color_on from sysmaps_links;
alter table sysmaps_links drop column triggerid;
alter table sysmaps_links rename column drawtype_off to drawtype;
alter table sysmaps_links rename column color_off to color;
alter table sysmaps_links modify color varchar2(6) DEFAULT '000000';
alter table sysmaps_links drop column drawtype_on;
alter table sysmaps_links drop column color_on;
update sysmaps_link_triggers set color='FF0000' where color='Red';
update sysmaps_link_triggers set color='960000' where color='Dark Red';
update sysmaps_link_triggers set color='00FF00' where color='Green';
update sysmaps_link_triggers set color='009600' where color='Dark Green';
update sysmaps_link_triggers set color='0000FF' where color='Blue';
update sysmaps_link_triggers set color='000096' where color='Dark Blue';
update sysmaps_link_triggers set color='FFFF00' where color='Yellow';
update sysmaps_link_triggers set color='969600' where color='Dark Yellow';
update sysmaps_link_triggers set color='00FFFF' where color='Cyan';
update sysmaps_link_triggers set color='000000' where color='Black';
update sysmaps_link_triggers set color='969696' where color='Gray';
update sysmaps_link_triggers set color='FFFFFF' where color='White';
update sysmaps_links set color='FF0000' where color='Red';
update sysmaps_links set color='960000' where color='Dark Red';
update sysmaps_links set color='00FF00' where color='Green';
update sysmaps_links set color='009600' where color='Dark Green';
update sysmaps_links set color='0000FF' where color='Blue';
update sysmaps_links set color='000096' where color='Dark Blue';
update sysmaps_links set color='FFFF00' where color='Yellow';
update sysmaps_links set color='969600' where color='Dark Yellow';
update sysmaps_links set color='00FFFF' where color='Cyan';
update sysmaps_links set color='000000' where color='Black';
update sysmaps_links set color='969696' where color='Gray';
update sysmaps_links set color='FFFFFF' where color='White';
