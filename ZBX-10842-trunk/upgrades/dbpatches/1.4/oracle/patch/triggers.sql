CREATE TABLE triggers_tmp (
	triggerid               number(20)              DEFAULT '0'     NOT NULL,
	expression              varchar2(255)           DEFAULT ''      ,
	description             varchar2(255)           DEFAULT ''      ,
	url             varchar2(255)           DEFAULT ''      ,
	status          number(10)              DEFAULT '0'     NOT NULL,
	value           number(10)              DEFAULT '0'     NOT NULL,
	priority                number(10)              DEFAULT '0'     NOT NULL,
	lastchange              number(10)              DEFAULT '0'     NOT NULL,
	dep_level               number(10)              DEFAULT '0'     NOT NULL,
	comments                varchar2(2048)                  ,
	error           varchar2(128)           DEFAULT ''      ,
	templateid              number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (triggerid)
);
CREATE INDEX triggers_1 on triggers_tmp (status);
CREATE INDEX triggers_2 on triggers_tmp (value);

insert into triggers_tmp select * from triggers;
drop trigger triggers_trigger;
drop sequence triggers_triggerid;
drop table triggers;
alter table triggers_tmp rename to triggers;
