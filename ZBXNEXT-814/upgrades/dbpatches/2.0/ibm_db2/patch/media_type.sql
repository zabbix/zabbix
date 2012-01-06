ALTER TABLE media_type ADD status integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE media_type
/
ALTER TABLE media_type ALTER COLUMN mediatypeid SET WITH DEFAULT NULL
/
REORG TABLE media_type
/
CREATE INDEX media_type_1 ON media_type (status)
/
REORG TABLE media_type
/
