ALTER TABLE ONLY sessions ALTER userid DROP DEFAULT;
DELETE FROM sessions WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=sessions.userid);
ALTER TABLE ONLY sessions ADD CONSTRAINT c_sessions_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
