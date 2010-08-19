ALTER TABLE ONLY user_history ALTER userhistoryid DROP DEFAULT,
			      ALTER userid DROP DEFAULT;
DELETE FROM user_history WHERE NOT userid IN (SELECT userid FROM users);
ALTER TABLE ONLY user_history ADD CONSTRAINT c_user_history_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
