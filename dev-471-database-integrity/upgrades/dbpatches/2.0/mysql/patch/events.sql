ALTER TABLE events MODIFY eventid bigint unsigned NOT NULL,
		   ADD ns integer DEFAULT '0' NOT NULL;
