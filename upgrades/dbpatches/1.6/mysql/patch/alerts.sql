alter table alerts drop triggerid;
alter table alerts add eventid bigint(20) unsigned NOT NULL default '0';

update alerts set status=3 where retries>=2;
