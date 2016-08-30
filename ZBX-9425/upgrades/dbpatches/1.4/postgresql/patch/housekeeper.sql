CREATE TABLE housekeeper_tmp (
	housekeeperid	bigint DEFAULT '0'	NOT NULL,
	tablename	varchar(64)		DEFAULT ''	NOT NULL,
	field		varchar(64)		DEFAULT ''	NOT NULL,
	value		bigint		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (housekeeperid)
) with OIDS;

insert into housekeeper_tmp select * from housekeeper;
drop table housekeeper;
alter table housekeeper_tmp rename to housekeeper;
