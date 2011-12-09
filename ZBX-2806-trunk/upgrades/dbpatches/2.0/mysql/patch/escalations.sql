ALTER TABLE escalations MODIFY escalationid bigint unsigned NOT NULL,
			MODIFY actionid bigint unsigned NOT NULL,
			MODIFY triggerid bigint unsigned NULL,
			MODIFY eventid bigint unsigned NULL,
			MODIFY r_eventid bigint unsigned NULL;
DELETE FROM escalations;
