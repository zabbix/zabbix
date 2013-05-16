ALTER TABLE user_history MODIFY userhistoryid DEFAULT NULL;
ALTER TABLE user_history MODIFY userid DEFAULT NULL;
DELETE FROM user_history WHERE NOT userid IN (SELECT userid FROM users);
ALTER TABLE user_history ADD CONSTRAINT c_user_history_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
