ALTER TABLE ONLY acknowledges ALTER acknowledgeid DROP DEFAULT,
			      ALTER userid DROP DEFAULT,
			      ALTER eventid DROP DEFAULT;
DELETE FROM acknowledges WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=acknowledges.userid);
DELETE FROM acknowledges WHERE NOT EXISTS (SELECT 1 FROM events WHERE events.eventid=acknowledges.eventid);
ALTER TABLE ONLY acknowledges ADD CONSTRAINT c_acknowledges_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
ALTER TABLE ONLY acknowledges ADD CONSTRAINT c_acknowledges_2 FOREIGN KEY (eventid) REFERENCES events (eventid) ON DELETE CASCADE;
