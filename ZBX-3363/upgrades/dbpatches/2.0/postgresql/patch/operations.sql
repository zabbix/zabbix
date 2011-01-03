ALTER TABLE ONLY operations ALTER operationid DROP DEFAULT,
			    ALTER actionid DROP DEFAULT,
			    ADD mediatypeid bigint NULL;
DELETE FROM operations WHERE NOT EXISTS (SELECT 1 FROM actions WHERE actions.actionid=operations.actionid);
ALTER TABLE ONLY operations ADD CONSTRAINT c_operations_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
ALTER TABLE ONLY operations ADD CONSTRAINT c_operations_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON DELETE CASCADE;
UPDATE operations SET mediatypeid=(SELECT mediatypeid FROM opmediatypes WHERE operationid = operations.operationid);
DROP TABLE opmediatypes;
