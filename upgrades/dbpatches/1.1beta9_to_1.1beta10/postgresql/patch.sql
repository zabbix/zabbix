ALTER TABLE config ADD work_period varchar(100) DEFAULT '1-7,00:00-23:59' NOT NULL;
ALTER TABLE graphs ADD show_work_period int2 DEFAULT '1' NOT NULL;
