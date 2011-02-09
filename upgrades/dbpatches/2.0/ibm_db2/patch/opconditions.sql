ALTER TABLE opconditions ALTER COLUMN opconditionid SET WITH DEFAULT NULL
/
REORG TABLE opconditions
/
ALTER TABLE opconditions ALTER COLUMN operationid SET WITH DEFAULT NULL
/
REORG TABLE opconditions
/
DELETE FROM opconditions WHERE NOT operationid IN (SELECT operationid FROM operations)
/
ALTER TABLE opconditions ADD CONSTRAINT c_opconditions_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE
/
