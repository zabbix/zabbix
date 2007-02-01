CREATE TABLE sessions_tmp (
	sessionid		varchar(32)		DEFAULT ''	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	lastaccess		integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sessionid)
) ENGINE=InnoDB;

insert into sessions_tmp select * from sessions;
drop table sessions;
alter table sessions_tmp rename sessions;
