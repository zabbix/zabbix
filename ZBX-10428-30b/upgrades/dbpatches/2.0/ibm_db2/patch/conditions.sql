ALTER TABLE conditions ALTER COLUMN conditionid SET WITH DEFAULT NULL
/
REORG TABLE conditions
/
ALTER TABLE conditions ALTER COLUMN actionid SET WITH DEFAULT NULL
/
REORG TABLE conditions
/
DELETE FROM conditions WHERE NOT actionid IN (SELECT actionid FROM actions)
/
ALTER TABLE conditions ADD CONSTRAINT c_conditions_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE
/
