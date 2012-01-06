ALTER TABLE media_type
	MODIFY mediatypeid bigint unsigned NOT NULL,
	ADD status integer DEFAULT '0' NOT NULL;
CREATE INDEX media_type_1 ON media_type (status);
