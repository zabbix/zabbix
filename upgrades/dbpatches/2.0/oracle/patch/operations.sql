DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE operations MODIFY actionid DEFAULT NULL;
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
