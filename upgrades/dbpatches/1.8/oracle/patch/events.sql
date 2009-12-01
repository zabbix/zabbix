DROP INDEX events_2 on events;
CREATE INDEX events_2 on events (clock, objectid);