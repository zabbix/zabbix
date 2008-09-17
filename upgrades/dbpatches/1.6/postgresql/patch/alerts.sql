drop index alerts_1;
drop index alerts_2;
drop index alerts_3;
drop index alerts_4;
drop index alerts_5;
drop index alerts_6;

alter table alerts drop triggerid;
alter table alerts add eventid         bigint          DEFAULT '0'     NOT NULL;
alter table alerts add esc_step integer DEFAULT '0'     NOT NULL;
alter table alerts add alerttype integer DEFAULT '0'     NOT NULL;

CREATE INDEX alerts_1 on alerts (actionid);
CREATE INDEX alerts_2 on alerts (clock);
CREATE INDEX alerts_3 on alerts (eventid);
CREATE INDEX alerts_4 on alerts (status,retries);
CREATE INDEX alerts_5 on alerts (mediatypeid);
CREATE INDEX alerts_6 on alerts (userid);

update alerts set status=3 where retries>=2;
