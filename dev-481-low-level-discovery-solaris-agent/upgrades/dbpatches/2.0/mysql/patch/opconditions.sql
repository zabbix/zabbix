ALTER TABLE opconditions MODIFY opconditionid bigint unsigned NOT NULL,
			 MODIFY operationid bigint unsigned NOT NULL;
DELETE FROM opconditions WHERE NOT operationid IN (SELECT operationid FROM operations);
ALTER TABLE opconditions ADD CONSTRAINT c_opconditions_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
