DROP INDEX events_2;
CREATE INDEX events_2 on events (clock, objectid);
