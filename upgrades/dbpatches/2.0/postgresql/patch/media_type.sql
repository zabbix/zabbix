ALTER TABLE ONLY media_type
	ALTER mediatypeid DROP DEFAULT,
	ADD status integer DEFAULT '0' NOT NULL;
