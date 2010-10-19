ALTER TABLE triggers MODIFY triggerid bigint unsigned NOT NULL,
			 CHANGE COLUMN description name VARCHAR(255) NOT NULL DEFAULT '',
			 CHANGE COLUMN comments description TEXT NOT NULL DEFAULT '',
		     MODIFY templateid bigint unsigned NULL;
UPDATE triggers SET templateid=NULL WHERE templateid=0;
CREATE TEMPORARY TABLE tmp_triggers_triggerid (triggerid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_triggers_triggerid (triggerid) (SELECT triggerid FROM triggers);
UPDATE triggers SET templateid=NULL WHERE NOT templateid IS NULL AND NOT templateid IN (SELECT triggerid FROM tmp_triggers_triggerid);
DROP TABLE tmp_triggers_triggerid;
ALTER TABLE triggers ADD CONSTRAINT c_triggers_1 FOREIGN KEY (templateid) REFERENCES triggers (triggerid) ON DELETE CASCADE;
