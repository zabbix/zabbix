ALTER TABLE ONLY trigger_depends ALTER triggerdepid DROP DEFAULT,
				 ALTER triggerid_down DROP DEFAULT,
				 ALTER triggerid_up DROP DEFAULT;
DROP INDEX trigger_depends_1;
DELETE FROM trigger_depends WHERE NOT EXISTS (SELECT 1 FROM triggers WHERE triggers.triggerid=trigger_depends.triggerid_down);
DELETE FROM trigger_depends WHERE NOT EXISTS (SELECT 1 FROM triggers WHERE triggers.triggerid=trigger_depends.triggerid_up);
CREATE UNIQUE INDEX trigger_depends_1 ON trigger_depends (triggerid_down,triggerid_up);
ALTER TABLE ONLY trigger_depends ADD CONSTRAINT c_trigger_depends_1 FOREIGN KEY (triggerid_down) REFERENCES triggers (triggerid) ON DELETE CASCADE;
ALTER TABLE ONLY trigger_depends ADD CONSTRAINT c_trigger_depends_2 FOREIGN KEY (triggerid_up) REFERENCES triggers (triggerid) ON DELETE CASCADE;
