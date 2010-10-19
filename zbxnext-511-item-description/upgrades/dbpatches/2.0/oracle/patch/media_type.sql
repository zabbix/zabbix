ALTER TABLE media_type RENAME COLUMN description to name;
ALTER TABLE media_type MODIFY mediatypeid DEFAULT NULL;
