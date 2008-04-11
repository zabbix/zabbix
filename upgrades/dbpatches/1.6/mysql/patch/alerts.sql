alter table alerts drop index alerts_3;
alter table alerts drop triggerid;
alter table alerts add eventid bigint(20) unsigned NOT NULL default '0';
CREATE INDEX alerts_3 on alerts (eventid);

update alerts set status=3 where retries>=2;
