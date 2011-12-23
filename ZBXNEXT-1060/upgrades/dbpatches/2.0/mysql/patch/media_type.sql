ALTER TABLE media_type MODIFY mediatypeid bigint unsigned NOT NULL;
UPDATE media_type SET type = 1 WHERE type = 100;
