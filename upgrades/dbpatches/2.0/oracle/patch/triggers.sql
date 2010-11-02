ALTER TABLE triggers MODIFY triggerid DEFAULT NULL;
ALTER TABLE triggers MODIFY templateid DEFAULT NULL;
ALTER TABLE triggers MODIFY templateid NULL;
ALTER TABLE triggers DROP dep_level;
ALTER TABLE triggers ADD value_flags number(10) DEFAULT '0' NOT NULL;
ALTER TABLE triggers ADD flags number(10) DEFAULT '0' NOT NULL;
UPDATE triggers SET templateid=NULL WHERE templateid=0;
UPDATE triggers SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT triggerid FROM triggers);
ALTER TABLE triggers ADD CONSTRAINT c_triggers_1 FOREIGN KEY (templateid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
