update config set alert_history=alert_history/(24*3600);
update config set alarm_history=alarm_history/(24*3600);

alter table services add algorithm int4 DEFAULT '0' NOT NULL;
