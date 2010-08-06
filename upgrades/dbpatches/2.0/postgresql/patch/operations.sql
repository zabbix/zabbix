DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE ONLY operations ALTER actionid DROP DEFAULT;
ALTER TABLE ONLY operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
