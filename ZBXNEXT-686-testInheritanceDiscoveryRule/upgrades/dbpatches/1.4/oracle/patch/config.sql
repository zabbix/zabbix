CREATE TABLE config_tmp (
	configid                number(20)              DEFAULT '0'     NOT NULL,
	alert_history           number(10)              DEFAULT '0'     NOT NULL,
	event_history           number(10)              DEFAULT '0'     NOT NULL,
	refresh_unsupported             number(10)              DEFAULT '0'     NOT NULL,
	work_period             varchar2(100)           DEFAULT '1-5,00:00-24:00'       ,
	alert_usrgrpid          number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (configid)
);

insert into config_tmp select 1,alert_history,alarm_history,refresh_unsupported,work_period,0 from config;
drop table config;
alter table config_tmp rename to config;
