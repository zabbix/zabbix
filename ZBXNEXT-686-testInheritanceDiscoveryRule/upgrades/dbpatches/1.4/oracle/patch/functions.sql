CREATE TABLE functions_tmp (
	functionid              number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	triggerid               number(20)              DEFAULT '0'     NOT NULL,
	lastvalue               varchar2(255)                   ,
	function                varchar2(12)            DEFAULT ''      ,
	parameter               varchar2(255)           DEFAULT '0'     ,
	PRIMARY KEY (functionid)
);
CREATE INDEX functions_1 on functions_tmp (triggerid);
CREATE INDEX functions_2 on functions_tmp (itemid,function,parameter);

insert into functions_tmp select * from functions;
drop trigger functions_trigger;
drop sequence functions_functionid;
drop table functions;
alter table functions_tmp rename to functions;
