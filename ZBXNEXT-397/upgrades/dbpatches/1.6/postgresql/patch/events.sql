drop index events_1;
drop index events_2;

CREATE INDEX events_1 on events (object,objectid,eventid);
CREATE INDEX events_2 on events (clock);
