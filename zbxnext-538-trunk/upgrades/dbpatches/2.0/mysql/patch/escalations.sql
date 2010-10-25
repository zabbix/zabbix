ALTER TABLE escalations MODIFY escalationid bigint unsigned NOT NULL,
			MODIFY actionid bigint unsigned NOT NULL,
			MODIFY triggerid bigint unsigned NULL,
			MODIFY eventid bigint unsigned NOT NULL,
			MODIFY r_eventid bigint unsigned NULL;
UPDATE escalations SET triggerid=NULL WHERE triggerid=0;
UPDATE escalations SET r_eventid=NULL WHERE r_eventid=0;
