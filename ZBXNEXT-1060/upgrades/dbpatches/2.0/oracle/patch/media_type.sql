ALTER TABLE media_type MODIFY mediatypeid DEFAULT NULL;
UPDATE media_type SET type = 1 WHERE type = 100;
