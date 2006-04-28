alter table config add work_period varchar(100) DEFAULT '1-7,00:00-23:59' NOT NULL;
alter table graphs add show_work_period int(1) DEFAULT '1' NOT NULL;
