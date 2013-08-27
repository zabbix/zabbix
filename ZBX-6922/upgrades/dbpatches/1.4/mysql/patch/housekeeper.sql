CREATE TABLE housekeeper_tmp (
	housekeeperid		bigint unsigned		DEFAULT '0'	NOT NULL,
	tablename		varchar(64)		DEFAULT ''	NOT NULL,
	field		varchar(64)		DEFAULT ''	NOT NULL,
	value		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (housekeeperid)
) ENGINE=InnoDB;

insert into housekeeper_tmp select * from housekeeper;
drop table housekeeper;
alter table housekeeper_tmp rename housekeeper;
