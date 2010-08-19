ALTER TABLE ONLY operations ALTER operationid DROP DEFAULT,
			    ALTER actionid DROP DEFAULT;
DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE ONLY operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
