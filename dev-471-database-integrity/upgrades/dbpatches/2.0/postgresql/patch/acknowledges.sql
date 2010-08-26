ALTER TABLE ONLY acknowledges ALTER acknowledgeid DROP DEFAULT,
			      ALTER userid DROP DEFAULT,
			      ALTER eventid DROP DEFAULT;
DELETE FROM acknowledges WHERE NOT userid IN (SELECT userid FROM users);
DELETE FROM acknowledges WHERE NOT eventid IN (SELECT eventid FROM events);
ALTER TABLE ONLY acknowledges ADD CONSTRAINT c_acknowledges_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
ALTER TABLE ONLY acknowledges ADD CONSTRAINT c_acknowledges_2 FOREIGN KEY (eventid) REFERENCES events (eventid) ON DELETE CASCADE;
