ALTER TABLE ONLY opmediatypes ALTER opmediatypeid DROP DEFAULT,
			      ALTER operationid DROP DEFAULT,
			      ALTER mediatypeid DROP DEFAULT;
DELETE FROM opmediatypes WHERE operationid NOT IN (SELECT operationid FROM operations);
DELETE FROM opmediatypes WHERE mediatypeid NOT IN (SELECT mediatypeid FROM media_type);
ALTER TABLE ONLY opmediatypes ADD CONSTRAINT c_opmediatypes_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON DELETE CASCADE;
ALTER TABLE ONLY opmediatypes ADD CONSTRAINT c_opmediatypes_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON DELETE CASCADE;
