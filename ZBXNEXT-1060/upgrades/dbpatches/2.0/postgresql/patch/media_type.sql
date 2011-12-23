ALTER TABLE ONLY media_type ALTER mediatypeid DROP DEFAULT;
UPDATE media_type SET type = 1 WHERE type = 100;
