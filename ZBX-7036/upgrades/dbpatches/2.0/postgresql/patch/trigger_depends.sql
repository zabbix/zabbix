ALTER TABLE ONLY trigger_depends ALTER triggerdepid DROP DEFAULT,
				 ALTER triggerid_down DROP DEFAULT,
				 ALTER triggerid_up DROP DEFAULT;
DROP INDEX trigger_depends_1;
DELETE FROM trigger_depends WHERE NOT EXISTS (SELECT 1 FROM triggers WHERE triggers.triggerid=trigger_depends.triggerid_down);
DELETE FROM trigger_depends WHERE NOT EXISTS (SELECT 1 FROM triggers WHERE triggers.triggerid=trigger_depends.triggerid_up);
-- remove duplicates to allow unique index
DELETE FROM trigger_depends
	WHERE triggerdepid IN (
		SELECT td1.triggerdepid
		FROM trigger_depends td1
		LEFT OUTER JOIN (
			SELECT MIN(td2.triggerdepid) AS triggerdepid
			FROM trigger_depends td2
			GROUP BY td2.triggerid_down,td2.triggerid_up
		) keep_rows ON
			td1.triggerdepid=keep_rows.triggerdepid
		WHERE keep_rows.triggerdepid IS NULL
	);
CREATE UNIQUE INDEX trigger_depends_1 ON trigger_depends (triggerid_down,triggerid_up);
ALTER TABLE ONLY trigger_depends ADD CONSTRAINT c_trigger_depends_1 FOREIGN KEY (triggerid_down) REFERENCES triggers (triggerid) ON DELETE CASCADE;
ALTER TABLE ONLY trigger_depends ADD CONSTRAINT c_trigger_depends_2 FOREIGN KEY (triggerid_up) REFERENCES triggers (triggerid) ON DELETE CASCADE;
