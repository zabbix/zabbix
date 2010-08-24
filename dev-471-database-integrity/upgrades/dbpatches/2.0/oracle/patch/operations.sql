ALTER TABLE operations MODIFY operationid DEFAULT NULL;
ALTER TABLE operations MODIFY actionid DEFAULT NULL;
DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON UPDATE CASCADE ON DELETE CASCADE;
