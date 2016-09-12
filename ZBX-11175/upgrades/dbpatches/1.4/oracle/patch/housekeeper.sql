CREATE TABLE housekeeper_tmp (
	housekeeperid           number(20)              DEFAULT '0'     NOT NULL,
	tablename               varchar2(64)            DEFAULT ''      ,
	field           varchar2(64)            DEFAULT ''      ,
	value           number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (housekeeperid)
);

insert into housekeeper_tmp select * from housekeeper;
drop trigger housekeeper_trigger;
drop sequence housekeeper_housekeeperid;
drop table housekeeper;
alter table housekeeper_tmp rename to housekeeper;
