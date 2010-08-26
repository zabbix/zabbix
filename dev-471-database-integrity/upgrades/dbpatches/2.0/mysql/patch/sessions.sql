ALTER TABLE sessions MODIFY userid bigint unsigned NOT NULL;
DELETE FROM sessions WHERE NOT userid IN (SELECT userid FROM users);
ALTER TABLE sessions ADD CONSTRAINT c_sessions_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
