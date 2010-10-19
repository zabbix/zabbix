ALTER TABLE media_type
	MODIFY mediatypeid bigint unsigned NOT NULL,
	CHANGE COLUMN description name VARCHAR(255) NOT NULL DEFAULT '';
