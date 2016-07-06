CREATE TABLE screens_tmp (
	screenid		bigint unsigned		DEFAULT '0'	NOT NULL,
	name		varchar(255)		DEFAULT 'Screen'	NOT NULL,
	hsize		integer		DEFAULT '1'	NOT NULL,
	vsize		integer		DEFAULT '1'	NOT NULL,
	PRIMARY KEY (screenid)
) ENGINE=InnoDB;

insert into screens_tmp select * from screens;
drop table screens;
alter table screens_tmp rename screens;
