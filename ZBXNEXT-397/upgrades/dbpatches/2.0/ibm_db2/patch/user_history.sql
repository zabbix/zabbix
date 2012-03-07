ALTER TABLE user_history ALTER COLUMN userhistoryid SET WITH DEFAULT NULL
/
REORG TABLE user_history
/
ALTER TABLE user_history ALTER COLUMN userid SET WITH DEFAULT NULL
/
REORG TABLE user_history
/
DELETE FROM user_history WHERE NOT userid IN (SELECT userid FROM users)
/
ALTER TABLE user_history ADD CONSTRAINT c_user_history_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE
/
