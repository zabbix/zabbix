ALTER TABLE ONLY opconditions ALTER opconditionid DROP DEFAULT,
			      ALTER operationid DROP DEFAULT;
DELETE FROM opconditions WHERE NOT operationid IN (SELECT operationid FROM operations);
ALTER TABLE ONLY opconditions ADD CONSTRAINT c_opconditions_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
