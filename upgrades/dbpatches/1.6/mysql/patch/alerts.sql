alter table alerts drop index alerts_3;
alter table alerts drop triggerid;
alter table alerts add eventid bigint(20) unsigned NOT NULL default '0';
alter table alerts add esc_step integer DEFAULT '0'     NOT NULL;
alter table alerts add alerttype integer DEFAULT '0'     NOT NULL;

CREATE INDEX alerts_3 on alerts (eventid);

update alerts set status=3 where retries>=2;
