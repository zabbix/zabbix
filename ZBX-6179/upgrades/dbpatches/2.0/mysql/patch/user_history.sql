ALTER TABLE user_history MODIFY userhistoryid bigint unsigned NOT NULL,
			 MODIFY userid bigint unsigned NOT NULL;
DELETE FROM user_history WHERE NOT userid IN (SELECT userid FROM users);
ALTER TABLE user_history ADD CONSTRAINT c_user_history_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
