CREATE TABLE config_tmp (
	configid		bigint unsigned		DEFAULT '0'	NOT NULL,
	alert_history		integer		DEFAULT '0'	NOT NULL,
	event_history		integer		DEFAULT '0'	NOT NULL,
	refresh_unsupported		integer		DEFAULT '0'	NOT NULL,
	work_period		varchar(100)		DEFAULT '1-5,00:00-24:00'	NOT NULL,
	alert_usrgrpid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (configid)
) ENGINE=InnoDB;

insert into config_tmp select 1,alert_history,alarm_history,refresh_unsupported,work_period,0 from config;
drop table config;
alter table config_tmp rename config;
