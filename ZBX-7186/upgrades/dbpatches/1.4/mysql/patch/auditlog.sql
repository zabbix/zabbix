CREATE TABLE auditlog_tmp (
	auditid		bigint unsigned		DEFAULT '0'	NOT NULL,
	userid		bigint unsigned		DEFAULT '0'	NOT NULL,
	clock		integer		DEFAULT '0'	NOT NULL,
	action		integer		DEFAULT '0'	NOT NULL,
	resourcetype		integer		DEFAULT '0'	NOT NULL,
	details		varchar(128)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (auditid)
) ENGINE=InnoDB;
CREATE INDEX auditlog_1 on auditlog_tmp (userid,clock);
CREATE INDEX auditlog_2 on auditlog_tmp (clock);

insert into auditlog_tmp select auditid,userid,clock,action,resourcetype,details from auditlog;
drop table auditlog;
alter table auditlog_tmp rename auditlog;
