ALTER TABLE operations MODIFY operationid bigint unsigned NOT NULL,
		       MODIFY actionid bigint unsigned NOT NULL;
DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
