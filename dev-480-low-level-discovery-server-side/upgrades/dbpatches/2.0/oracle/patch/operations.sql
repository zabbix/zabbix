ALTER TABLE operations MODIFY operationid DEFAULT NULL;
ALTER TABLE operations MODIFY actionid DEFAULT NULL;
ALTER TABLE operations ADD mediatypeid number(20) NULL;
DELETE FROM operations WHERE NOT actionid IN (SELECT actionid FROM actions);
ALTER TABLE operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
ALTER TABLE operations ADD CONSTRAINT c_operations_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON DELETE CASCADE;
UPDATE operations SET mediatypeid=(SELECT mediatypeid FROM opmediatypes WHERE operationid = operations.operationid);
DROP TABLE opmediatypes;
