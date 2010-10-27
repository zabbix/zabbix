DROP INDEX events_2 ON events;
CREATE INDEX events_2 on events (clock);
ALTER TABLE events MODIFY eventid bigint unsigned NOT NULL,
		   ADD ns integer DEFAULT '0' NOT NULL;
