ALTER TABLE ONLY conditions ALTER conditionid DROP DEFAULT,
			    ALTER actionid DROP DEFAULT;
DELETE FROM conditions WHERE NOT EXISTS (SELECT 1 FROM actions WHERE actions.actionid=conditions.actionid);
ALTER TABLE ONLY conditions ADD CONSTRAINT c_conditions_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
