DROP INDEX events_2;
CREATE INDEX events_2 on events (clock);
ALTER TABLE events ALTER COLUMN eventid SET WITH DEFAULT NULL;
REORG TABLE events;
ALTER TABLE events ADD ns integer DEFAULT '0' NOT NULL;
REORG TABLE events;
