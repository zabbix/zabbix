CREATE TABLE screens_tmp (
	screenid	number(20)	DEFAULT '0'		NOT NULL,
	name		varchar2(255)	DEFAULT 'Screen',
	hsize		number(10)	DEFAULT '1'		NOT NULL,
	vsize		number(10)	DEFAULT '1'		NOT NULL,
	PRIMARY KEY (screenid)
);

insert into screens_tmp select * from screens;
drop trigger screens_trigger;
drop sequence screens_screenid;
drop table screens;
alter table screens_tmp rename to screens;
