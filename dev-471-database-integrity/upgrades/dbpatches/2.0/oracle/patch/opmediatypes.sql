DROP INDEX opmediatypes_1;
ALTER TABLE opmediatypes DROP COLUMN opmediatypeid;
ALTER TABLE opmediatypes MODIFY operationid DEFAULT NULL;
ALTER TABLE opmediatypes MODIFY mediatypeid DEFAULT NULL;
ALTER TABLE opmediatypes ADD PRIMARY KEY (operationid);
DELETE FROM opmediatypes WHERE operationid NOT IN (SELECT operationid FROM operations);
DELETE FROM opmediatypes WHERE mediatypeid NOT IN (SELECT mediatypeid FROM media_type);
ALTER TABLE opmediatypes ADD CONSTRAINT c_opmediatypes_1 FOREIGN KEY (operationid) REFERENCES operations (operationid) ON UPDATE CASCADE ON DELETE CASCADE;
ALTER TABLE opmediatypes ADD CONSTRAINT c_opmediatypes_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON UPDATE CASCADE ON DELETE CASCADE;
