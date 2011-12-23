ALTER TABLE media_type ALTER COLUMN mediatypeid SET WITH DEFAULT NULL
/
REORG TABLE media_type
/
UPDATE media_type SET type = 1 WHERE type = 100
/
