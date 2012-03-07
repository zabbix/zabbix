alter table nodes modify ip varchar2(39) DEFAULT '';
alter table nodes drop column event_lastid;
alter table nodes drop column history_lastid;
alter table nodes drop column history_str_lastid;
alter table nodes drop column history_uint_lastid;
