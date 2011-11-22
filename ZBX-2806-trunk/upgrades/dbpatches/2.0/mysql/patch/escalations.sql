ALTER TABLE escalations MODIFY escalationid bigint unsigned NOT NULL,
			MODIFY actionid bigint unsigned NOT NULL,
			MODIFY triggerid bigint unsigned NULL,
			MODIFY eventid bigint unsigned NOT NULL,
			MODIFY r_eventid bigint unsigned NULL;
UPDATE escalations SET triggerid=NULL WHERE triggerid=0;
UPDATE escalations SET r_eventid=NULL WHERE r_eventid=0;
-- 0: ESCALATION_STATUS_ACTIVE
-- 1: ESCALATION_STATUS_RECOVERY
-- 2: ESCALATION_STATUS_SLEEP
-- 4: ESCALATION_STATUS_SUPERSEDED_ACTIVE
-- 5: ESCALATION_STATUS_SUPERSEDED_RECOVERY
UPDATE escalations SET status=2 WHERE status=1;
UPDATE escalations SET status=0 WHERE status in (4,5);
