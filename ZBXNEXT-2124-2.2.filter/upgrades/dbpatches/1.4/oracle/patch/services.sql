CREATE TABLE services_tmp (
	serviceid               number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(128)           DEFAULT ''      ,
	status          number(10)              DEFAULT '0'     NOT NULL,
	algorithm               number(10)              DEFAULT '0'     NOT NULL,
	triggerid               number(20)                      ,
	showsla         number(10)              DEFAULT '0'     NOT NULL,
	goodsla         number(5,2)             DEFAULT '99.9'  NOT NULL,
	sortorder               number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (serviceid)
);

insert into services_tmp select * from services;
drop trigger services_trigger;
drop sequence services_serviceid;
drop table services;
alter table services_tmp rename to services;
