alter table alarms add key (clock);
alter table config add alert_history         int(4)          DEFAULT '0' NOT NULL;
alter table config add alarm_history         int(4)          DEFAULT '0' NOT NULL;
