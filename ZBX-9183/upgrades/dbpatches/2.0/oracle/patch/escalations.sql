ALTER TABLE escalations MODIFY escalationid DEFAULT NULL;
ALTER TABLE escalations MODIFY actionid DEFAULT NULL;
ALTER TABLE escalations MODIFY triggerid DEFAULT NULL;
ALTER TABLE escalations MODIFY triggerid NULL;
ALTER TABLE escalations MODIFY eventid DEFAULT NULL;
ALTER TABLE escalations MODIFY eventid NULL;
ALTER TABLE escalations MODIFY r_eventid DEFAULT NULL;
ALTER TABLE escalations MODIFY r_eventid NULL;
DROP INDEX escalations_2;

-- 0: ESCALATION_STATUS_ACTIVE
-- 1: ESCALATION_STATUS_RECOVERY
-- 2: ESCALATION_STATUS_SLEEP
-- 4: ESCALATION_STATUS_SUPERSEDED_ACTIVE
-- 5: ESCALATION_STATUS_SUPERSEDED_RECOVERY
UPDATE escalations SET status=0 WHERE status in (1,4,5);

VARIABLE escalation_maxid number;
BEGIN
SELECT MAX(escalationid) INTO :escalation_maxid FROM escalations;
END;
/

CREATE SEQUENCE escalations_seq;

INSERT INTO escalations (escalationid, actionid, triggerid, r_eventid)
	SELECT :escalation_maxid + escalations_seq.NEXTVAL, actionid, triggerid, r_eventid
		FROM escalations
		WHERE status = 0
			AND eventid IS NOT NULL
			AND r_eventid IS NOT NULL;
UPDATE escalations SET r_eventid = NULL WHERE eventid IS NOT NULL AND r_eventid IS NOT NULL;

DROP SEQUENCE escalations_seq;
