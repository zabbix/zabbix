CREATE TABLE sysmaps_link_triggers (
	linktriggerid	number(20)	DEFAULT '0'	NOT NULL,
	linkid		number(20)	DEFAULT '0'	NOT NULL,
	triggerid	number(20)	DEFAULT '0'	NOT NULL,
	drawtype	number(10)	DEFAULT '0'	NOT NULL,
	color		varchar2(6)	DEFAULT '000000',
	PRIMARY KEY (linktriggerid)
);
CREATE UNIQUE INDEX sysmaps_link_triggers_1 on sysmaps_link_triggers (linkid,triggerid);
update sysmaps_links set color_on='FF0000' where color_on='Red';
update sysmaps_links set color_on='960000' where color_on='Dark Red';
update sysmaps_links set color_on='00FF00' where color_on='Green';
update sysmaps_links set color_on='009600' where color_on='Dark Green';
update sysmaps_links set color_on='0000FF' where color_on='Blue';
update sysmaps_links set color_on='000096' where color_on='Dark Blue';
update sysmaps_links set color_on='FFFF00' where color_on='Yellow';
update sysmaps_links set color_on='969600' where color_on='Dark Yellow';
update sysmaps_links set color_on='00FFFF' where color_on='Cyan';
update sysmaps_links set color_on='000000' where color_on='Black';
update sysmaps_links set color_on='969696' where color_on='Gray';
update sysmaps_links set color_on='FFFFFF' where color_on='White';
update sysmaps_links set color_off='FF0000' where color_off='Red';
update sysmaps_links set color_off='960000' where color_off='Dark Red';
update sysmaps_links set color_off='00FF00' where color_off='Green';
update sysmaps_links set color_off='009600' where color_off='Dark Green';
update sysmaps_links set color_off='0000FF' where color_off='Blue';
update sysmaps_links set color_off='000096' where color_off='Dark Blue';
update sysmaps_links set color_off='FFFF00' where color_off='Yellow';
update sysmaps_links set color_off='969600' where color_off='Dark Yellow';
update sysmaps_links set color_off='00FFFF' where color_off='Cyan';
update sysmaps_links set color_off='000000' where color_off='Black';
update sysmaps_links set color_off='969696' where color_off='Gray';
update sysmaps_links set color_off='FFFFFF' where color_off='White';
insert into sysmaps_link_triggers select linkid,linkid,triggerid,drawtype_on,color_on from sysmaps_links;
alter table sysmaps_links drop column triggerid;
alter table sysmaps_links rename column drawtype_off to drawtype;
alter table sysmaps_links rename column color_off to color;
alter table sysmaps_links modify color varchar2(6) DEFAULT '000000';
alter table sysmaps_links drop column drawtype_on;
alter table sysmaps_links drop column color_on;
