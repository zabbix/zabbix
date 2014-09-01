CREATE TABLE applications_tmp (
	applicationid           number(20)              DEFAULT '0'     NOT NULL,
	hostid          number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(255)           DEFAULT ''      ,
	templateid              number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (applicationid)
);
CREATE INDEX applications_1 on applications_tmp (templateid);
CREATE UNIQUE INDEX applications_2 on applications_tmp (hostid,name);

insert into applications_tmp select * from applications;
drop trigger applications_trigger;
drop sequence applications_applicationid;
drop table applications;
alter table applications_tmp rename to applications;
