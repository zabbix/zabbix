ALTER TABLE trigger_depends MODIFY triggerdepid DEFAULT NULL;
ALTER TABLE trigger_depends MODIFY triggerid_down DEFAULT NULL;
ALTER TABLE trigger_depends MODIFY triggerid_up DEFAULT NULL;
DROP INDEX trigger_depends_1;
DELETE FROM trigger_depends WHERE triggerid_down NOT IN (SELECT triggerid FROM triggers);
DELETE FROM trigger_depends WHERE triggerid_up NOT IN (SELECT triggerid FROM triggers);
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
ALTER TABLE trigger_depends ADD CONSTRAINT c_trigger_depends_1 FOREIGN KEY (triggerid_down) REFERENCES triggers (triggerid) ON DELETE CASCADE;
ALTER TABLE trigger_depends ADD CONSTRAINT c_trigger_depends_2 FOREIGN KEY (triggerid_up) REFERENCES triggers (triggerid) ON DELETE CASCADE;
