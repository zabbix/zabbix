ALTER TABLE media_type
	MODIFY mediatypeid bigint unsigned NOT NULL,
	ADD status integer DEFAULT '0' NOT NULL;
