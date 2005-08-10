alter table history_log add timestamp int(4) DEFAULT '0' NOT NULL;
alter table history_log add source varchar(64) DEFAULT '' NOT NULL;
alter table history_log add severity int(4) DEFAULT '0' NOT NULL;

alter table history_log modify value text default '' not null;
