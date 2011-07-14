ALTER TABLE ONLY profiles ALTER profileid DROP DEFAULT,
			  ALTER userid DROP DEFAULT;
DELETE FROM profiles WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=profiles.userid);
ALTER TABLE ONLY profiles ADD CONSTRAINT c_profiles_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
