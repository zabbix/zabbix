alter table history_log add logeventid              integer         DEFAULT '0'     NOT NULL;

CREATE UNIQUE INDEX history_log_2 on history_log (itemid,id);
