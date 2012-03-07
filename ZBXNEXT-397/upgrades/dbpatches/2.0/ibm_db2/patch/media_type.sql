ALTER TABLE media_type ADD status integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE media_type
/
ALTER TABLE media_type ALTER COLUMN mediatypeid SET WITH DEFAULT NULL
/
REORG TABLE media_type
/
