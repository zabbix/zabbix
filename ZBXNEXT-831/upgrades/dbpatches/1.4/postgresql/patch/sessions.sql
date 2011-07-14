CREATE TABLE sessions_tmp (
	sessionid	varchar(32)		DEFAULT ''	NOT NULL,
	userid		bigint DEFAULT '0'	NOT NULL,
	lastaccess	integer		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sessionid)
) with OIDS;

insert into sessions_tmp select * from sessions;
drop table sessions;
alter table sessions_tmp rename to sessions;
