ALTER TABLE ONLY media ALTER mediaid DROP DEFAULT,
		       ALTER userid DROP DEFAULT,
		       ALTER mediatypeid DROP DEFAULT,
		       ALTER period SET DEFAULT '1-7,00:00-24:00';
DELETE FROM media WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=media.userid);
DELETE FROM media WHERE NOT EXISTS (SELECT 1 FROM media_type WHERE media_type.mediatypeid=media.mediatypeid);
ALTER TABLE ONLY media ADD CONSTRAINT c_media_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
ALTER TABLE ONLY media ADD CONSTRAINT c_media_2 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON DELETE CASCADE;
