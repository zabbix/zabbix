DROP INDEX events_2;
CREATE INDEX events_2 on events (clock);
ALTER TABLE ONLY events ALTER eventid DROP DEFAULT,
			ADD ns integer DEFAULT '0' NOT NULL;
