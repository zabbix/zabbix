--
-- Patching table `events`
--

DROP INDEX events_2 ON events;
CREATE INDEX events_2 ON events (clock);
ALTER TABLE events
	MODIFY eventid bigint unsigned NOT NULL,
	ADD ns integer DEFAULT '0' NOT NULL,
	ADD value_changed integer DEFAULT '0' NOT NULL;

-- Begin event redesign patch

DELIMITER $
CREATE PROCEDURE zbx_convert_events()
LANGUAGE SQL
BEGIN
	DECLARE v_eventid bigint unsigned;
	DECLARE v_triggerid bigint unsigned;
	DECLARE v_value integer;
	DECLARE v_type integer;
	DECLARE prev_triggerid bigint unsigned;
	DECLARE prev_value integer;

	DECLARE n_done integer DEFAULT 0;
	DECLARE n_cur CURSOR FOR (
		SELECT e.eventid, e.objectid, e.value, t.type
			FROM events e
			JOIN triggers t ON t.triggerid = e.objectid
			WHERE e.source = 0		-- EVENT_SOURCE_TRIGGERS
				AND e.object = 0	-- EVENT_OBJECT_TRIGGER
				AND e.value IN (0,1)	-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
			ORDER BY e.objectid, e.clock, e.eventid
		);
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET n_done = 1;

	OPEN n_cur;

	n_loop: LOOP
		FETCH n_cur INTO v_eventid, v_triggerid, v_value, v_type;

		IF n_done THEN
			LEAVE n_loop;
		END IF;

		IF prev_triggerid IS NULL OR prev_triggerid <> v_triggerid THEN
			SET prev_value = NULL;
		END IF;

		IF v_value = 0 THEN	-- TRIGGER_VALUE_FALSE (OK)
			-- Which OK events should have value_changed flag set?
			-- (1) those that have a PROBLEM event (or no event) before them
			IF prev_value IS NULL OR prev_value = 1 THEN
				UPDATE events set value_changed = 1 WHERE eventid = v_eventid;
			END IF;
		ELSE			-- TRIGGER_VALUE_TRUE (PROBLEM)
			-- Which PROBLEM events should have value_changed flag set?
			-- (1) those that have an OK event (or no event) before them
			-- (2) those that came from a "MULTIPLE PROBLEM" trigger
			IF v_type = 1 OR prev_value IS NULL OR prev_value = 0 THEN
				UPDATE events set value_changed = 1 WHERE eventid = v_eventid;
			END IF;
		END IF;

		SET prev_value = v_value;
		SET prev_triggerid = v_triggerid;
	END LOOP n_loop;

	CLOSE n_cur;
END$
DELIMITER ;

CALL zbx_convert_events();

DROP PROCEDURE zbx_convert_events;

-- End event redesign patch

--
-- Patching table `triggers`
--

ALTER TABLE triggers
	MODIFY triggerid bigint unsigned NOT NULL,
	MODIFY templateid bigint unsigned NULL,
	MODIFY comments text NOT NULL,
	DROP COLUMN dep_level,
	ADD value_flags integer DEFAULT '0' NOT NULL,
	ADD flags integer DEFAULT '0' NOT NULL;
UPDATE triggers SET templateid=NULL WHERE templateid=0;
CREATE TEMPORARY TABLE tmp_triggers_triggerid (triggerid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_triggers_triggerid (triggerid) (SELECT triggerid FROM triggers);
UPDATE triggers SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT triggerid FROM tmp_triggers_triggerid);
DROP TABLE tmp_triggers_triggerid;
ALTER TABLE triggers ADD CONSTRAINT c_triggers_1 FOREIGN KEY (templateid) REFERENCES triggers (triggerid) ON DELETE CASCADE;

-- Begin event redesign patch

CREATE TEMPORARY TABLE tmp_triggers (triggerid bigint unsigned PRIMARY KEY, eventid bigint unsigned);

INSERT INTO tmp_triggers (triggerid, eventid)
(
	SELECT t.triggerid, MAX(e.eventid)
		FROM triggers t, events e
		WHERE t.value=2				-- TRIGGER_VALUE_UNKNOWN
			AND e.source=0			-- EVENT_SOURCE_TRIGGERS
			AND e.object=0			-- EVENT_OBJECT_TRIGGER
			AND e.objectid=t.triggerid
			AND e.value<>2			-- TRIGGER_VALUE_UNKNOWN
		GROUP BY t.triggerid
);

UPDATE triggers t1, tmp_triggers t2, events e
	SET t1.value=e.value,
		t1.value_flags=1
	WHERE t1.triggerid=t2.triggerid
		AND t2.eventid=e.eventid;

UPDATE triggers
	SET value=0,					-- TRIGGER_VALUE_FALSE
		value_flags=1
	WHERE value=2;					-- TRIGGER_VALUE_UNKNOWN

DROP TABLE tmp_triggers;

-- End event redesign patch
