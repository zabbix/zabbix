CREATE TABLE auditlog_tmp (
	auditid         number(20)              DEFAULT '0'     NOT NULL,
	userid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	action          number(10)              DEFAULT '0'     NOT NULL,
	resourcetype            number(10)              DEFAULT '0'     NOT NULL,
	details         varchar2(128)           DEFAULT '0'     ,
	PRIMARY KEY (auditid)
);
CREATE INDEX auditlog_1 on auditlog_tmp (userid,clock);
CREATE INDEX auditlog_2 on auditlog_tmp (clock);

insert into auditlog_tmp select auditid,userid,clock,action,resourcetype,details from auditlog;
drop trigger auditlog_trigger;
drop sequence auditlog_auditid;
drop table auditlog;
alter table auditlog_tmp rename to auditlog;
