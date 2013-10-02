----
---- Patching table `events`
----

DROP INDEX events_2;
CREATE INDEX events_2 on events (clock);
ALTER TABLE events MODIFY eventid DEFAULT NULL;
ALTER TABLE events ADD ns number(10) DEFAULT '0' NOT NULL;
ALTER TABLE events ADD value_changed number(10) DEFAULT '0' NOT NULL;

-- Begin event redesign patch

CREATE TABLE tmp_events_eventid (eventid number(20) PRIMARY KEY,prev_value number(10),value number(10));
CREATE INDEX tmp_events_index on events (source, object, objectid, clock, eventid, value);

CREATE OR REPLACE FUNCTION get_prev_value(eventid IN NUMBER, triggerid IN NUMBER, clock IN NUMBER)
RETURN NUMBER IS
prev_value NUMBER(10);
BEGIN
	SELECT value
		INTO prev_value
		FROM (
		SELECT value
			FROM events
			WHERE source=0			-- EVENT_SOURCE_TRIGGERS
				AND object=0		-- EVENT_OBJECT_TRIGGER
				AND objectid=get_prev_value.triggerid
				AND (clock<get_prev_value.clock
					OR (clock=get_prev_value.clock
						AND eventid<get_prev_value.eventid)
					)
				AND value IN (0,1)	-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
			ORDER BY source DESC,
				object DESC,
				objectid DESC,
				clock DESC,
				eventid DESC,
				value DESC
		) WHERE rownum = 1;
	RETURN prev_value;
END;
/

-- Which OK events should have value_changed flag set?
-- Those that have a PROBLEM event (or no event) before them.

INSERT INTO tmp_events_eventid (eventid, prev_value, value)
	SELECT eventid,get_prev_value(eventid, objectid, clock) AS prev_value, value
	FROM events
	WHERE source=0					-- EVENT_SOURCE_TRIGGERS
		AND object=0				-- EVENT_OBJECT_TRIGGER
		AND value=0				-- TRIGGER_VALUE_FALSE (OK)
/

-- Which PROBLEM events should have value_changed flag set?
-- (1) Those that have an OK event (or no event) before them.

INSERT INTO tmp_events_eventid (eventid, prev_value, value)
	SELECT e.eventid,get_prev_value(e.eventid, e.objectid, e.clock) AS prev_value, e.value
	FROM events e,triggers t
	WHERE e.source=0				-- EVENT_SOURCE_TRIGGERS
		AND e.object=0				-- EVENT_OBJECT_TRIGGER
		AND e.objectid=t.triggerid
		AND e.value=1				-- TRIGGER_VALUE_TRUE (PROBLEM)
		AND t.type=0
/

-- (2) Those that came from a "MULTIPLE PROBLEM" trigger.

INSERT INTO tmp_events_eventid (eventid, value)
	SELECT e.eventid, e.value
		FROM events e,triggers t
		WHERE e.source=0			-- EVENT_SOURCE_TRIGGERS
			AND e.object=0			-- EVENT_OBJECT_TRIGGER
			AND e.objectid=t.triggerid
			AND e.value=1			-- TRIGGER_VALUE_TRUE (PROBLEM)
			AND t.type=1
/

DELETE FROM tmp_events_eventid WHERE prev_value = value;

-- Update the value_changed flag.

DROP INDEX tmp_events_index;
DROP FUNCTION get_prev_value;

UPDATE events SET value_changed=1 WHERE eventid IN (SELECT eventid FROM tmp_events_eventid);

DROP TABLE tmp_events_eventid;

-- End event redesign patch

----
---- Patching table `triggers`
----

ALTER TABLE triggers MODIFY triggerid DEFAULT NULL;
ALTER TABLE triggers MODIFY templateid DEFAULT NULL;
ALTER TABLE triggers MODIFY templateid NULL;
ALTER TABLE triggers DROP COLUMN dep_level;
ALTER TABLE triggers ADD value_flags number(10) DEFAULT '0' NOT NULL;
ALTER TABLE triggers ADD flags number(10) DEFAULT '0' NOT NULL;
UPDATE triggers SET templateid=NULL WHERE templateid=0;
UPDATE triggers SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT triggerid FROM triggers);
ALTER TABLE triggers ADD CONSTRAINT c_triggers_1 FOREIGN KEY (templateid) REFERENCES triggers (triggerid) ON DELETE CASCADE;

-- Begin event redesign patch

CREATE TABLE tmp_triggers (triggerid number(20) PRIMARY KEY, eventid number(20))
/

INSERT INTO tmp_triggers (triggerid, eventid)
(
	SELECT t.triggerid, MAX(e.eventid)
		FROM triggers t, events e
		WHERE t.value=2				-- TRIGGER_VALUE_UNKNOWN
			AND e.source=0			-- EVENT_SOURCE_TRIGGERS
			AND e.object=0			-- EVENT_OBJECT_TRIGGER
			AND e.objectid=t.triggerid
			AND e.value IN (0,1)		-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
		GROUP BY t.triggerid
)
/

UPDATE triggers
	SET value=(
		SELECT e.value
			FROM events e,tmp_triggers t
			WHERE e.eventid=t.eventid
				AND triggers.triggerid=t.triggerid
	)
	WHERE triggerid IN (
		SELECT triggerid
			FROM tmp_triggers
	)
/

UPDATE triggers
	SET value=0,					-- TRIGGER_VALUE_FALSE
		value_flags=1
	WHERE value NOT IN (0,1)			-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
/

DROP TABLE tmp_triggers
/

-- End event redesign patch
