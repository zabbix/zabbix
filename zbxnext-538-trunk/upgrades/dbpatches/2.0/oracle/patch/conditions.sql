ALTER TABLE conditions MODIFY conditionid DEFAULT NULL;
ALTER TABLE conditions MODIFY actionid DEFAULT NULL;
DELETE FROM conditions WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE conditions ADD CONSTRAINT c_conditions_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
