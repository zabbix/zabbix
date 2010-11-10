ALTER TABLE media MODIFY mediaid bigint unsigned NOT NULL,
		  MODIFY userid bigint unsigned NOT NULL,
		  MODIFY mediatypeid bigint unsigned NOT NULL,
		  MODIFY period varchar(100) DEFAULT '1-7,00:00-24:00' NOT NULL;
DELETE FROM media WHERE NOT userid IN (SELECT userid FROM users);
DELETE FROM media WHERE NOT mediatypeid IN (SELECT mediatypeid FROM media_type);
ALTER TABLE media ADD CONSTRAINT c_media_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
ALTER TABLE media ADD CONSTRAINT c_media_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON DELETE CASCADE;
