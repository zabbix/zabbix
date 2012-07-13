alter table alerts add userid int4 DEFAULT '0' NOT NULL;

CREATE INDEX alerts_userid on alerts (userid);
