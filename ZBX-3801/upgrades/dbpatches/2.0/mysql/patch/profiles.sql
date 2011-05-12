ALTER TABLE profiles MODIFY profileid bigint unsigned NOT NULL,
		     MODIFY userid bigint unsigned NOT NULL;
DELETE FROM profiles WHERE NOT userid IN (SELECT userid FROM users);
ALTER TABLE profiles ADD CONSTRAINT c_profiles_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
