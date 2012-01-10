ALTER TABLE ONLY escalations
	ALTER escalationid DROP DEFAULT,
	ALTER actionid DROP DEFAULT,
	ALTER triggerid DROP DEFAULT,
	ALTER triggerid DROP NOT NULL,
	ALTER eventid DROP DEFAULT,
	ALTER eventid DROP NOT NULL,
	ALTER r_eventid DROP DEFAULT,
	ALTER r_eventid DROP NOT NULL;
DROP INDEX escalations_2;

-- 0: ESCALATION_STATUS_ACTIVE
-- 1: ESCALATION_STATUS_RECOVERY
-- 2: ESCALATION_STATUS_SLEEP
-- 4: ESCALATION_STATUS_SUPERSEDED_ACTIVE
-- 5: ESCALATION_STATUS_SUPERSEDED_RECOVERY
UPDATE escalations SET status=0 WHERE status in (1,4,5);

CREATE SEQUENCE escalations_seq;
SELECT setval('escalations_seq', max(escalationid)) FROM escalations;

INSERT INTO escalations (escalationid, actionid, triggerid, r_eventid)
	SELECT NEXTVAL('escalations_seq'), actionid, triggerid, r_eventid
		FROM escalations
		WHERE status = 0
			AND eventid IS NOT NULL
			AND r_eventid IS NOT NULL;
UPDATE escalations SET r_eventid = NULL WHERE eventid IS NOT NULL AND r_eventid IS NOT NULL;

DROP SEQUENCE escalations_seq;
