ALTER TABLE ONLY media_type RENAME COLUMN description TO name;
ALTER TABLE ONLY media_type ALTER mediatypeid DROP DEFAULT;