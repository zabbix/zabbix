ALTER TABLE escalations MODIFY actionid bigint unsigned NOT NULL;
ALTER TABLE escalations MODIFY triggerid bigint unsigned NULL;
ALTER TABLE escalations MODIFY eventid bigint unsigned NOT NULL;
ALTER TABLE escalations MODIFY r_eventid bigint unsigned NULL;
UPDATE escalations SET triggerid=NULL WHERE triggerid=0;
UPDATE escalations SET r_eventid=NULL WHERE r_eventid=0;
