alter table history_log add timestamp int4 DEFAULT '0' NOT NULL;
alter table history_log add source varchar(64) DEFAULT '' NOT NULL;
alter table history_log add severity int4 DEFAULT '0' NOT NULL;
