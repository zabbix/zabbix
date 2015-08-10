CREATE TABLE actions_tmp (
	actionid                number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(255)           DEFAULT ''      ,
	eventsource             number(10)              DEFAULT '0'     NOT NULL,
	evaltype                number(10)              DEFAULT '0'     NOT NULL,
	status          number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (actionid)
);

CREATE TABLE operations (
	operationid             number(20)              DEFAULT '0'     NOT NULL,
	actionid                number(20)              DEFAULT '0'     NOT NULL,
	operationtype           number(10)              DEFAULT '0'     NOT NULL,
	object          number(10)              DEFAULT '0'     NOT NULL,
	objectid                number(20)              DEFAULT '0'     NOT NULL,
	shortdata               varchar2(255)           DEFAULT ''      ,
	longdata                varchar2(2048)          DEFAULT ''      ,
	scripts_tmp             varchar2(2048)          DEFAULT ''      ,
	PRIMARY KEY (operationid)
);
CREATE INDEX operations_1 on operations (actionid);

insert into actions_tmp select actionid,actionid,source,0,status from actions;

insert into operations select actionid,actionid,actiontype,recipient,userid,subject,message,scripts from actions;
update operations set longdata=scripts_tmp where operationtype=1;
alter table operations drop column scripts_tmp;

drop trigger actions_trigger;
drop sequence actions_actionid;
drop table actions;
alter table actions_tmp rename to actions;
