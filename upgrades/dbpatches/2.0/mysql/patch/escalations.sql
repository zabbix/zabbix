ALTER TABLE escalations MODIFY actionid bigint unsigned NOT NULL;
ALTER TABLE escalations MODIFY triggerid bigint unsigned NOT NULL;
ALTER TABLE escalations MODIFY eventid bigint unsigned NOT NULL;
ALTER TABLE escalations MODIFY r_eventid bigint unsigned NULL;
