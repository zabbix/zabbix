----
---- Patching table `events`
----

DROP INDEX events_2
/
CREATE INDEX events_2 on events (clock)
/
ALTER TABLE events ALTER COLUMN eventid SET WITH DEFAULT NULL
/
REORG TABLE events
/
ALTER TABLE events ADD ns integer DEFAULT '0' NOT NULL
/
REORG TABLE events
/
ALTER TABLE events ADD value_changed integer DEFAULT '0' NOT NULL
/
REORG TABLE events
/

-- Begin event redesign patch

CREATE TABLE tmp_events_eventid (eventid bigint NOT NULL PRIMARY KEY,prev_value integer,value integer)
/
CREATE INDEX tmp_events_index on events (source, object, objectid, clock, eventid, value)
/

-- Which OK events should have value_changed flag set?
-- Those that have a PROBLEM event (or no event) before them.

INSERT INTO tmp_events_eventid (eventid,prev_value,value)
(
	SELECT e1.eventid,(SELECT e2.value
				FROM events e2
				WHERE e2.source=e1.source
					AND e2.object=e1.object
					AND e2.objectid=e1.objectid
					AND (e2.clock<e1.clock OR (e2.clock=e1.clock AND e2.eventid<e1.eventid))
					AND e2.value IN (0,1)	-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
				ORDER BY e2.source DESC,
						e2.object DESC,
						e2.objectid DESC,
						e2.clock DESC,
						e2.eventid DESC,
						e2.value DESC
				FETCH FIRST 1 ROWS ONLY),e1.value
		FROM events e1
		WHERE e1.source=0				-- EVENT_SOURCE_TRIGGERS
			AND e1.object=0 			-- EVENT_OBJECT_TRIGGER
			AND e1.value=0				-- TRIGGER_VALUE_FALSE (OK)
)
/

-- Which PROBLEM events should have value_changed flag set?
-- (1) Those that have an OK event (or no event) before them.

INSERT INTO tmp_events_eventid (eventid,prev_value,value)
(
	SELECT e1.eventid,(SELECT e2.value
				FROM events e2
				WHERE e2.source=e1.source
					AND e2.object=e1.object
					AND e2.objectid=e1.objectid
					AND (e2.clock<e1.clock OR (e2.clock=e1.clock AND e2.eventid<e1.eventid))
					AND e2.value IN (0,1)	-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
				ORDER BY e2.source DESC,
						e2.object DESC,
						e2.objectid DESC,
						e2.clock DESC,
						e2.eventid DESC,
						e2.value DESC
				FETCH FIRST 1 ROWS ONLY),e1.value
		FROM events e1,triggers t
		WHERE e1.source=0				-- EVENT_SOURCE_TRIGGERS
			AND e1.object=0 			-- EVENT_OBJECT_TRIGGER
			AND e1.objectid=t.triggerid
			AND e1.value=1				-- TRIGGER_VALUE_TRUE
			AND t.type=0				-- TRIGGER_TYPE_NORMAL
)
/

-- (2) Those that came from a "MULTIPLE PROBLEM" trigger.

INSERT INTO tmp_events_eventid (eventid,value)
(
	SELECT e1.eventid,e1.value
		FROM events e1,triggers t
		WHERE e1.source=0				-- EVENT_SOURCE_TRIGGERS
			AND e1.object=0 			-- EVENT_OBJECT_TRIGGER
			AND e1.objectid=t.triggerid
			AND e1.value=1				-- TRIGGER_VALUE_TRUE (PROBLEM)
			AND t.type=1				-- TRIGGER_TYPE_MULTIPLE_TRUE
)
/

DELETE FROM tmp_events_eventid WHERE prev_value = value
/

-- Update the value_changed flag.

DROP INDEX tmp_events_index
/

UPDATE events SET value_changed=1 WHERE eventid IN (SELECT eventid FROM tmp_events_eventid)
/

DROP TABLE tmp_events_eventid
/

-- End event redesign patch

----
---- Patching table `triggers`
----

ALTER TABLE triggers ALTER COLUMN triggerid SET WITH DEFAULT NULL
/
REORG TABLE triggers
/
ALTER TABLE triggers ALTER COLUMN templateid SET WITH DEFAULT NULL
/
REORG TABLE triggers
/
ALTER TABLE triggers ALTER COLUMN templateid DROP NOT NULL
/
REORG TABLE triggers
/
ALTER TABLE triggers DROP COLUMN dep_level
/
REORG TABLE triggers
/
ALTER TABLE triggers ADD value_flags integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE triggers
/
ALTER TABLE triggers ADD flags integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE triggers
/
UPDATE triggers SET templateid=NULL WHERE templateid=0
/
UPDATE triggers SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT triggerid FROM triggers)
/
ALTER TABLE triggers ADD CONSTRAINT c_triggers_1 FOREIGN KEY (templateid) REFERENCES triggers (triggerid) ON DELETE CASCADE
/

-- Begin event redesign patch

CREATE TABLE tmp_triggers (triggerid bigint NOT NULL PRIMARY KEY, eventid bigint)
/

INSERT INTO tmp_triggers (triggerid, eventid)
(
	SELECT t.triggerid, MAX(e.eventid)
		FROM triggers t, events e
		WHERE t.value=2			-- TRIGGER_VALUE_UNKNOWN
			AND e.source=0		-- EVENT_SOURCE_TRIGGERS
			AND e.object=0		-- EVENT_OBJECT_TRIGGER
			AND e.objectid=t.triggerid
			AND e.value IN (0,1)	-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)
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
	SET value=0,				-- TRIGGER_VALUE_FALSE (OK)
		value_flags=1
	WHERE value NOT IN (0,1)		-- TRIGGER_VALUE_FALSE (OK), TRIGGER_VALUE_TRUE (PROBLEM)

/

DROP TABLE tmp_triggers
/

-- End event redesign patch
