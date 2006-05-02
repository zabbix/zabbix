ALTER TABLE config ADD work_period varchar(100) DEFAULT '1-5,00:00-24:00' NOT NULL;
ALTER TABLE graphs ADD show_work_period int2 DEFAULT '1' NOT NULL;
ALTER TABLE graphs ADD show_triggers int2 DEFAULT '1' NOT NULL;
