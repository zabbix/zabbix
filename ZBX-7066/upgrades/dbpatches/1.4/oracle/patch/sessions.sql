CREATE TABLE sessions_tmp (
	sessionid	varchar2(32)	DEFAULT '',
	userid		number(20)	DEFAULT '0'	NOT NULL,
	lastaccess	number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (sessionid)
);

insert into sessions_tmp select * from sessions;
drop table sessions;
alter table sessions_tmp rename to sessions;
