ALTER TABLE ONLY media_type
	ALTER mediatypeid DROP DEFAULT,
	ADD status integer DEFAULT '0' NOT NULL;
CREATE INDEX media_type_1 ON media_type (status);
