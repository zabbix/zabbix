ALTER TABLE escalations
	MODIFY escalationid bigint unsigned NOT NULL,
	MODIFY actionid bigint unsigned NOT NULL,
	MODIFY triggerid bigint unsigned NULL,
	MODIFY eventid bigint unsigned NULL,
	MODIFY r_eventid bigint unsigned NULL;
DROP INDEX escalations_2 ON escalations;

-- 0: ESCALATION_STATUS_ACTIVE
-- 1: ESCALATION_STATUS_RECOVERY
-- 2: ESCALATION_STATUS_SLEEP
-- 4: ESCALATION_STATUS_SUPERSEDED_ACTIVE
-- 5: ESCALATION_STATUS_SUPERSEDED_RECOVERY
UPDATE escalations SET status=0 WHERE status in (1,4,5);

SET @escalationid = (SELECT MAX(escalationid) FROM escalations);
INSERT INTO escalations (escalationid, actionid, triggerid, r_eventid)
	SELECT @escalationid := @escalationid + 1, actionid, triggerid, r_eventid
		FROM escalations
		WHERE status = 0
			AND eventid IS NOT NULL
			AND r_eventid IS NOT NULL;
UPDATE escalations SET r_eventid = NULL WHERE eventid IS NOT NULL AND r_eventid IS NOT NULL;
