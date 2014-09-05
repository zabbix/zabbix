----
---- Patching table `events`
----

DROP INDEX events_2;
CREATE INDEX events_2 on events (clock);
ALTER TABLE ONLY events ALTER eventid DROP DEFAULT,
			ADD ns integer DEFAULT '0' NOT NULL,
			ADD value_changed integer DEFAULT '0' NOT NULL;

-- Begin event redesign patch

CREATE LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION zbx_convert_events() RETURNS BOOLEAN AS $$
	DECLARE prev_triggerid bigint;
	DECLARE prev_value integer;
	r RECORD;
BEGIN
	FOR r IN
		SELECT e.eventid, t.triggerid, e.value, t.type
		FROM events e
			JOIN triggers t ON t.triggerid = e.objectid
		WHERE e.source = 0
			AND e.object = 0
			AND e.value IN (0, 1)
		ORDER BY e.objectid, e.clock, e.eventid
	LOOP

	IF prev_triggerid IS NULL OR prev_triggerid <> r.triggerid THEN
		prev_value := NULL;
	END IF;

	IF r.value = 0 THEN
		IF prev_value IS NULL OR prev_value = 1 THEN
			UPDATE events set value_changed = 1 WHERE eventid = r.eventid;
		END IF;
	ELSE
		IF r.type = 1 OR prev_value IS NULL OR prev_value = 0 THEN
			UPDATE events set value_changed = 1 WHERE eventid = r.eventid;
		END IF;
	END IF;

	prev_value := r.value;
	prev_triggerid := r.triggerid;

	END LOOP;

	RETURN 1;
END;
$$ LANGUAGE plpgsql;

SELECT zbx_convert_events();

DROP FUNCTION zbx_convert_events();

-- End event redesign patch

----
---- Patching table `triggers`
----

ALTER TABLE ONLY triggers ALTER triggerid DROP DEFAULT,
			  ALTER templateid DROP DEFAULT,
			  ALTER templateid DROP NOT NULL,
			  DROP COLUMN dep_level,
			  ADD value_flags integer DEFAULT '0' NOT NULL,
			  ADD flags integer DEFAULT '0' NOT NULL;
UPDATE triggers SET templateid=NULL WHERE templateid=0;
UPDATE triggers SET templateid=NULL WHERE templateid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM triggers t WHERE t.triggerid=triggers.templateid);
ALTER TABLE ONLY triggers ADD CONSTRAINT c_triggers_1 FOREIGN KEY (templateid) REFERENCES triggers (triggerid) ON DELETE CASCADE;

-- Begin event redesign patch

CREATE TEMPORARY TABLE tmp_triggers (triggerid bigint PRIMARY KEY, eventid bigint);

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
	);

UPDATE triggers
	SET value=0,					-- TRIGGER_VALUE_FALSE
		value_flags=1
	WHERE value=2;					-- TRIGGER_VALUE_UNKNOWN

DROP TABLE tmp_triggers;

-- End event redesign patch
