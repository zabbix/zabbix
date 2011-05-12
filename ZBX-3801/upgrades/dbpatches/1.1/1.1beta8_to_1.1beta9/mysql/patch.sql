alter table alerts add userid int(4) DEFAULT '0' NOT NULL;
create index alerts_userid on alerts (userid);
