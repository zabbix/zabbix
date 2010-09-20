DROP INDEX events_2;
CREATE INDEX events_2 on events (clock);
ALTER TABLE events MODIFY eventid DEFAULT NULL;
ALTER TABLE events ADD ns number(10) DEFAULT '0' NOT NULL;
