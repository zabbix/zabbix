ALTER TABLE escalations ALTER COLUMN escalationid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN actionid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN triggerid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN triggerid DROP NOT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN eventid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN eventid DROP NOT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN r_eventid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN r_eventid DROP NOT NULL
/
REORG TABLE escalations
/
DROP INDEX escalations_2
/

-- 0: ESCALATION_STATUS_ACTIVE
-- 1: ESCALATION_STATUS_RECOVERY
-- 2: ESCALATION_STATUS_SLEEP
-- 4: ESCALATION_STATUS_SUPERSEDED_ACTIVE
-- 5: ESCALATION_STATUS_SUPERSEDED_RECOVERY
UPDATE escalations SET status=0 WHERE status in (1,4,5)
/

CREATE SEQUENCE escalations_seq AS bigint
/

CREATE PROCEDURE zbx_convert_escalations()
LANGUAGE SQL
BEGIN
	DECLARE max_escalationid bigint;
	DECLARE m_done integer DEFAULT 0;
	DECLARE m_not_found CONDITION FOR SQLSTATE '02000';
	DECLARE m_cur CURSOR FOR (SELECT MAX(escalationid) FROM escalations);
	DECLARE CONTINUE HANDLER FOR m_not_found SET m_done = 1;

	OPEN m_cur;

	m_loop: LOOP
		FETCH m_cur INTO max_escalationid;

		IF m_done = 1 THEN
			LEAVE m_loop;
		END IF;

		BEGIN
			DECLARE v_actionid bigint;
			DECLARE v_triggerid bigint;
			DECLARE v_r_eventid bigint;
			DECLARE e_done integer DEFAULT 0;
			DECLARE e_not_found CONDITION FOR SQLSTATE '02000';
			DECLARE e_cur CURSOR FOR (
				SELECT actionid, triggerid, r_eventid
					FROM escalations
					WHERE status = 0
						AND eventid IS NOT NULL
						AND r_eventid IS NOT NULL);
			DECLARE CONTINUE HANDLER FOR e_not_found SET e_done = 1;

			OPEN e_cur;

			e_loop: LOOP
				FETCH e_cur INTO v_actionid, v_triggerid, v_r_eventid;

				IF e_done = 1 THEN
					LEAVE e_loop;
				END IF;

				INSERT INTO escalations (escalationid, actionid, triggerid, r_eventid) VALUES
					(max_escalationid + (NEXTVAL FOR escalations_seq), v_actionid, v_triggerid, v_r_eventid);
			END LOOP e_loop;

			CLOSE e_cur;
		END;
	END LOOP m_loop;

	CLOSE m_cur;
END
/

CALL zbx_convert_escalations
/

DROP PROCEDURE zbx_convert_escalations
/

DROP SEQUENCE escalations_seq
/

UPDATE escalations SET r_eventid = NULL WHERE eventid IS NOT NULL AND r_eventid IS NOT NULL
/
