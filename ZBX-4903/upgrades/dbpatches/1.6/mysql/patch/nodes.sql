alter table nodes modify ip varchar(39) DEFAULT '' NOT NULL;

alter table nodes drop event_lastid;
alter table nodes drop history_lastid;
alter table nodes drop history_str_lastid;
alter table nodes drop history_uint_lastid;
