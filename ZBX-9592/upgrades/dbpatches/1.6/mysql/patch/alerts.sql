alter table alerts drop index alerts_1;
alter table alerts drop index alerts_2;
alter table alerts drop index alerts_3;
alter table alerts drop index alerts_4;
alter table alerts drop index alerts_5;
alter table alerts drop index alerts_6;

alter table alerts add eventid bigint(20) unsigned NOT NULL default '0' after actionid;
alter table alerts add esc_step integer DEFAULT '0'     NOT NULL;
alter table alerts add alerttype integer DEFAULT '0'     NOT NULL;

update alerts, events set alerts.eventid = events.eventid where events.objectid = alerts.triggerid and events.object = 0 and alerts.eventid = 0 and events.clock = alerts.clock;
update alerts, events set alerts.eventid = events.eventid where events.objectid = alerts.triggerid and events.object = 0 and alerts.eventid = 0 and events.clock = alerts.clock + 1;
alter table alerts drop triggerid;

CREATE INDEX alerts_1 on alerts (actionid);
CREATE INDEX alerts_2 on alerts (clock);
CREATE INDEX alerts_3 on alerts (eventid);
CREATE INDEX alerts_4 on alerts (status,retries);
CREATE INDEX alerts_5 on alerts (mediatypeid);
CREATE INDEX alerts_6 on alerts (userid);

update alerts set status=3 where retries>=2;
